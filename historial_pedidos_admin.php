<?php
/** Gestión de pedidos: filtros, detalle, cambio de estado, comprobante PDF y exportación. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/pedidos.php';
require_once __DIR__ . '/includes/filtros_pedidos.php';
requerir_sesion('administrador');
liberar_pedidos_vencidos(); // devuelve el inventario de pedidos sin pagar cuya reserva venció

if (es_post()) {
    csrf_verificar();
    $pedido = pedido_por_id((string)($_POST['id'] ?? ''));
    $nuevo = (string)($_POST['estado'] ?? '');
    if (!$pedido) {
        flash('error', 'El pedido no existe.');
    } else {
        $r = pedido_cambiar_estado($pedido, $nuevo, 'admin: ' . (usuario_actual()['nombre'] ?? ''));
        flash($r['ok'] ? 'success' : 'error', 'Pedido ' . pedido_numero($pedido) . ': ' . $r['mensaje']);
    }
    $volver = (string)($_POST['volver'] ?? '');
    header('Location: ' . (strpos($volver, url('historial_pedidos_admin.php')) === 0 ? $volver : url('historial_pedidos_admin.php')), true, 303);
    exit;
}

[$f, $q] = filtros_pedidos();
$col = mongo()->selectCollection('pedidos');
$porPagina = 15;
$total = $col->countDocuments($q);
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
$pedidos = iterator_to_array($col->find($q, ['sort' => ['fecha' => -1], 'skip' => ($pagina - 1) * $porPagina, 'limit' => $porPagina]), false);
$clientes = clientes_de_pedidos($pedidos);
$detalles = detalles_de_pedidos(array_map(fn($p) => $p['_id'], $pedidos));
// Un filtro vacío debe enviarse como documento ({}), no como lista ([]): MongoDB rechaza $match con una lista
$sumaFiltro = $col->aggregate([['$match' => $q ?: new stdClass()], ['$group' => ['_id' => null, 't' => ['$sum' => '$total']]]])->toArray();
$params = array_filter($f);

admin_inicio('Pedidos', 'pedidos', ['acciones' => boton_exportar('exportar_pedidos_excel.php', $params)]);
?>
<form method="get" class="cs-panel mb-4" data-sin-bloqueo>
    <div class="row g-2 align-items-end">
        <div class="col-6 col-md-2"><label class="form-label small" for="desde">Desde</label><input type="date" id="desde" name="desde" class="form-control" value="<?= e($f['desde']) ?>"></div>
        <div class="col-6 col-md-2"><label class="form-label small" for="hasta">Hasta</label><input type="date" id="hasta" name="hasta" class="form-control" value="<?= e($f['hasta']) ?>"></div>
        <div class="col-md-3"><label class="form-label small" for="cliente">Cliente (nombre o correo)</label><input type="search" id="cliente" name="cliente" class="form-control" value="<?= e($f['cliente']) ?>"></div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="estado">Estado</label>
            <select id="estado" name="estado" class="form-select"><option value="">Todos</option>
                <?php foreach (estados_pedido() as $es): ?><option <?= $f['estado'] === $es ? 'selected' : '' ?>><?= e($es) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1">
            <label class="form-label small" for="origen">Origen</label>
            <select id="origen" name="origen" class="form-select"><option value="">Todos</option><option value="web" <?= $f['origen'] === 'web' ? 'selected' : '' ?>>Web</option><option value="app_android" <?= $f['origen'] === 'app_android' ? 'selected' : '' ?>>App</option></select>
        </div>
        <div class="col-6 col-md-2"><label class="form-label small" for="numero">N.º pedido</label><input type="search" id="numero" name="numero" class="form-control" placeholder="CS-000001" value="<?= e($f['numero']) ?>"></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-3">
        <button type="submit" class="btn btn-cs"><i class="bi bi-filter" aria-hidden="true"></i> Filtrar</button>
        <a href="<?= e(url('historial_pedidos_admin.php')) ?>" class="btn btn-outline-secondary">Restablecer</a>
    </div>
</form>

<p class="text-secondary small" role="status"><?= (int)$total ?> pedido(s) · suma: <strong><?= e(dinero($sumaFiltro[0]['t'] ?? 0)) ?></strong><?= $paginas > 1 ? ' · página ' . $pagina . ' de ' . $paginas : '' ?></p>

<?php if (!$pedidos): ?>
<div class="cs-panel text-center text-secondary py-5">No se encontraron pedidos con los criterios ingresados.</div>
<?php endif; ?>

<div class="d-grid gap-3">
<?php foreach ($pedidos as $p):
    $pid = (string)$p['_id'];
    $estado = pedido_estado($p);
    $cli = $clientes[(string)($p['usuario_id'] ?? '')] ?? ['nombre' => (string)($p['cliente']['nombre'] ?? 'Desconocido'), 'correo' => (string)($p['cliente']['correo'] ?? '')];
    $lineas = $detalles[$pid] ?? [];
?>
    <article class="pedido-card">
        <div class="d-flex flex-wrap justify-content-between gap-3 mb-2">
            <div>
                <h2 class="h5 mb-1">Pedido <?= e(pedido_numero($p)) ?> <?= estado_badge($estado) ?></h2>
                <p class="small text-secondary mb-0"><?= e(fecha_local($p['fecha'] ?? null)) ?> · <?= ($p['origen'] ?? '') === 'app_android' ? 'App Android' : 'Web' ?><?= !empty($p['pago']['referencia']) ? ' · Ref. ' . e($p['pago']['referencia']) : '' ?></p>
            </div>
            <div class="text-md-end">
                <p class="mb-0"><strong>Cliente:</strong> <?= e($cli['nombre']) ?></p>
                <?php if ($cli['correo']): ?><p class="small text-secondary mb-0"><?= e($cli['correo']) ?></p><?php endif; ?>
                <p class="h5 mb-0 mt-1"><?= e(dinero($p['total'] ?? 0)) ?></p>
            </div>
        </div>
        <details>
            <summary class="small fw-semibold mb-2" style="cursor:pointer"><?= count($lineas) ?> producto(s) — ver detalle<?= !empty($p['notas_admin']) ? ' · 📝 con notas' : '' ?></summary>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
                <table class="table table-sm align-middle tabla-apilable">
                    <thead><tr><th scope="col">Producto</th><th scope="col" class="text-center">Cantidad</th><th scope="col" class="text-end">Precio unitario</th><th scope="col" class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($lineas as $d): $c = (int)($d['cantidad'] ?? 0); $pu = (float)($d['precio_unitario'] ?? 0); ?>
                        <tr><td data-label="Producto"><?= e($d['nombre_producto'] ?? 'Producto') ?></td><td data-label="Cantidad" class="text-md-center"><?= $c ?></td>
                            <td data-label="Precio unitario" class="text-md-end"><?= e(dinero($pu)) ?></td><td data-label="Subtotal" class="text-md-end"><?= e(dinero($d['subtotal'] ?? $pu * $c)) ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </details>
        <div class="d-flex flex-wrap align-items-end gap-2 mt-2">
            <form method="post" class="d-flex flex-wrap gap-2 align-items-end" data-confirmar="¿Cambiar el estado del pedido <?= e(pedido_numero($p)) ?>?">
                <?= csrf_campo() ?>
                <input type="hidden" name="id" value="<?= e($pid) ?>">
                <input type="hidden" name="volver" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                <div>
                    <label class="form-label small mb-1" for="est-<?= e($pid) ?>">Cambiar estado</label>
                    <select id="est-<?= e($pid) ?>" name="estado" class="form-select form-select-sm">
                        <?php foreach (estados_pedido() as $es): ?><option <?= $estado === $es ? 'selected' : '' ?>><?= e($es) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn btn-sm btn-cs" data-cargando="Guardando…">Actualizar</button>
            </form>
            <a href="<?= e(url('admin_pedido.php', ['id' => $pid])) ?>" class="btn btn-sm btn-outline-cs ms-md-auto"><i class="bi bi-gear" aria-hidden="true"></i> Gestionar</a>
            <a href="<?= e(url('admin_pedido_pdf.php', ['id' => $pid])) ?>" class="btn btn-sm btn-outline-danger" target="_blank" rel="noopener" data-descarga="pdf"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Comprobante PDF</a>
        </div>
    </article>
<?php endforeach; ?>
</div>
<?php paginacion_html($pagina, $paginas, 'historial_pedidos_admin.php', $params); ?>
<p class="small text-secondary mt-3">Al pasar un pedido a «Cancelado» o «Pago rechazado» se devuelve automáticamente su inventario; si se reactiva, se vuelve a descontar (si hay existencias).</p>
<?php admin_fin(); ?>
