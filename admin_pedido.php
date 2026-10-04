<?php
/**
 * Gestión de un pedido (administrador): ver detalle completo, cambiar estado,
 * editar cantidades (solo si está "Pendiente de pago"), notas internas y
 * eliminar (solo pedidos cancelados o con pago rechazado, cuyo inventario ya fue devuelto).
 * Los pedidos pagados no se editan para no alterar ventas registradas.
 */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/pedidos.php';
requerir_sesion('administrador');

$pedido = pedido_por_id((string)($_GET['id'] ?? ($_POST['id'] ?? '')));
if (!$pedido) {
    flash('error', 'El pedido no existe o ya fue eliminado.');
    redirigir('historial_pedidos_admin.php');
}
$pedido = liberar_si_vencido($pedido);
$id = (string)$pedido['_id'];
$estado = pedido_estado($pedido);
$admin = 'admin: ' . (usuario_actual()['nombre'] ?? '');
$db = mongo();

if (es_post()) {
    csrf_verificar();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'estado') {
        $r = pedido_cambiar_estado($pedido, (string)($_POST['estado'] ?? ''), $admin);
        flash($r['ok'] ? 'success' : 'error', $r['mensaje']);
    }

    if ($accion === 'notas') {
        $notas = mb_substr(trim((string)($_POST['notas'] ?? '')), 0, 2000);
        $db->selectCollection('pedidos')->updateOne(['_id' => $pedido['_id']], ['$set' => ['notas_admin' => $notas, 'actualizado_en' => nowUTC()]]);
        flash('success', 'Notas internas guardadas.');
    }

    if ($accion === 'cantidades') {
        if ($estado !== ESTADO_PENDIENTE) {
            flash('warning', 'Solo se pueden modificar productos de pedidos «Pendiente de pago».');
        } else {
            $detalles = pedido_detalles($pedido);
            $nuevas = (array)($_POST['cantidad'] ?? []);
            $aumentos = [];
            $reducciones = [];
            $quedan = 0;
            foreach ($detalles as $d) {
                $did = (string)$d['_id'];
                $n = isset($nuevas[$did]) && ctype_digit((string)$nuevas[$did]) ? (int)$nuevas[$did] : (int)$d['cantidad'];
                $dif = $n - (int)$d['cantidad'];
                if ($n > 0) {
                    $quedan++;
                }
                if ($dif > 0) {
                    $aumentos[] = ['producto_id' => (string)$d['producto_id'], 'cantidad' => $dif, 'nombre' => $d['nombre_producto']];
                } elseif ($dif < 0) {
                    $reducciones[] = ['producto_id' => (string)$d['producto_id'], 'cantidad' => -$dif, 'nombre' => $d['nombre_producto']];
                }
            }
            if ($quedan === 0) {
                flash('warning', 'El pedido debe conservar al menos un producto. Si no se va a vender, cámbialo a «Cancelado».');
            } elseif (!$aumentos && !$reducciones) {
                flash('info', 'No se detectaron cambios en las cantidades.');
            } else {
                $r = $aumentos ? reservar_stock($aumentos) : ['ok' => true];
                if (!$r['ok']) {
                    flash('error', $r['mensaje']);
                } else {
                    devolver_stock($reducciones);
                    $cambios = [];
                    $total = 0.0;
                    foreach ($detalles as $d) {
                        $did = (string)$d['_id'];
                        $n = isset($nuevas[$did]) && ctype_digit((string)$nuevas[$did]) ? (int)$nuevas[$did] : (int)$d['cantidad'];
                        if ($n !== (int)$d['cantidad']) {
                            $cambios[] = $d['nombre_producto'] . ': ' . (int)$d['cantidad'] . ' → ' . $n;
                        }
                        if ($n === 0) {
                            $db->selectCollection('pedido_detalle')->deleteOne(['_id' => $d['_id']]);
                            continue;
                        }
                        $sub = round($d['precio_unitario'] * $n, 2);
                        $total += $sub;
                        $db->selectCollection('pedido_detalle')->updateOne(['_id' => $d['_id']], ['$set' => ['cantidad' => $n, 'subtotal' => $sub]]);
                    }
                    $db->selectCollection('pedidos')->updateOne(['_id' => $pedido['_id']], [
                        '$set' => ['total' => round($total, 2), 'actualizado_en' => nowUTC()],
                        '$push' => ['historial_estados' => ['estado' => ESTADO_PENDIENTE, 'fecha' => nowUTC(), 'por' => $admin, 'nota' => 'Productos modificados: ' . implode('; ', $cambios)]],
                    ]);
                    log_app('info', 'Pedido modificado por administrador', ['pedido' => $id, 'cambios' => $cambios]);
                    flash('success', 'Pedido actualizado. Nuevo total: ' . dinero($total) . '.');
                }
            }
        }
    }

    if ($accion === 'eliminar') {
        if (!in_array($estado, estados_sin_stock(), true)) {
            flash('warning', 'Solo se pueden eliminar pedidos cancelados o con pago rechazado. Cancélalo primero (el inventario se devuelve automáticamente).');
        } else {
            $db->selectCollection('pedido_detalle')->deleteMany(['pedido_id' => $pedido['_id']]);
            $db->selectCollection('pedidos')->deleteOne(['_id' => $pedido['_id']]);
            log_app('info', 'Pedido eliminado por administrador', ['pedido' => $id, 'numero' => pedido_numero($pedido), 'por' => usuario_actual()['id']]);
            flash('success', 'Pedido ' . pedido_numero($pedido) . ' eliminado.');
            redirigir('historial_pedidos_admin.php');
        }
    }
    redirigir('admin_pedido.php', ['id' => $id]);
}

$detalles = pedido_detalles($pedido);
$cliente = $db->selectCollection('usuarios')->findOne(['_id' => $pedido['usuario_id'] ?? null], ['projection' => ['nombre' => 1, 'correo' => 1]]);
$total = (float)($pedido['total'] ?? 0);
$editable = $estado === ESTADO_PENDIENTE;

admin_inicio('Pedido ' . pedido_numero($pedido), 'pedidos', ['acciones' =>
    '<a href="' . e(url('historial_pedidos_admin.php')) . '" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a pedidos</a>'
    . '<a href="' . e(url('admin_pedido_pdf.php', ['id' => $id])) . '" class="btn btn-outline-danger" target="_blank" rel="noopener" data-descarga="pdf"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Comprobante PDF</a>']);
?>
<div class="row g-4">
    <div class="col-xl-8">
        <div class="cs-panel mb-4">
            <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                <div>
                    <p class="mb-1"><?= estado_badge($estado) ?></p>
                    <p class="text-secondary small mb-0"><?= e(fecha_local($pedido['fecha'] ?? null)) ?> · <?= ($pedido['origen'] ?? '') === 'app_android' ? 'App Android' : 'Web' ?></p>
                </div>
                <p class="h3 mb-0"><?= e(dinero($total)) ?></p>
            </div>
            <form method="post">
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="cantidades">
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
                    <table class="table align-middle tabla-apilable mb-2">
                        <thead><tr><th scope="col">Producto</th><th scope="col" class="text-center">Cantidad</th><th scope="col" class="text-end">Precio unitario</th><th scope="col" class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($detalles as $d): $did = (string)$d['_id']; ?>
                            <tr>
                                <td data-label="Producto"><div class="d-flex align-items-center gap-2"><?= imagen_html((string)($d['imagen'] ?? ''), '', ['class' => 'tabla-img', 'width' => 56, 'height' => 56]) ?><span><?= e($d['nombre_producto']) ?></span></div></td>
                                <td data-label="Cantidad" class="text-md-center">
                                    <?php if ($editable): ?>
                                    <label class="visually-hidden" for="c-<?= e($did) ?>">Cantidad de <?= e($d['nombre_producto']) ?></label>
                                    <input type="number" id="c-<?= e($did) ?>" name="cantidad[<?= e($did) ?>]" min="0" step="1" value="<?= (int)$d['cantidad'] ?>" class="form-control form-control-sm d-inline-block" style="width:90px">
                                    <?php else: ?><?= (int)$d['cantidad'] ?><?php endif; ?>
                                </td>
                                <td data-label="Precio unitario" class="text-md-end"><?= e(dinero($d['precio_unitario'])) ?></td>
                                <td data-label="Subtotal" class="text-md-end"><?= e(dinero($d['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($editable): ?>
                <p class="small text-secondary">Pon 0 para quitar un producto. Los precios se conservan como estaban al crear el pedido y el inventario se ajusta automáticamente.</p>
                <button type="submit" class="btn btn-cs btn-sm" data-cargando="Guardando…"><i class="bi bi-save" aria-hidden="true"></i> Guardar cantidades</button>
                <?php else: ?>
                <p class="small text-secondary mb-0"><i class="bi bi-lock" aria-hidden="true"></i> Los productos solo se pueden modificar mientras el pedido está «Pendiente de pago».</p>
                <?php endif; ?>
            </form>
        </div>

        <div class="cs-panel">
            <h2 class="cs-panel-titulo">Notas internas</h2>
            <form method="post">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="notas">
                <label class="visually-hidden" for="notas">Notas internas del pedido</label>
                <textarea id="notas" name="notas" class="form-control mb-2" rows="3" maxlength="2000" placeholder="Ej.: datos de entrega, observaciones del cliente… (solo las ve el equipo administrativo)"><?= e($pedido['notas_admin'] ?? '') ?></textarea>
                <button type="submit" class="btn btn-outline-cs btn-sm" data-cargando="Guardando…">Guardar notas</button>
            </form>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="cs-panel mb-4">
            <h2 class="cs-panel-titulo">Cliente</h2>
            <p class="mb-1 fw-semibold"><?= e($cliente['nombre'] ?? ($pedido['cliente']['nombre'] ?? 'Desconocido')) ?></p>
            <?php if ($c = ($cliente['correo'] ?? ($pedido['cliente']['correo'] ?? ''))): ?><p class="mb-0"><a href="mailto:<?= e($c) ?>"><?= e($c) ?></a></p><?php endif; ?>
        </div>
        <div class="cs-panel mb-4">
            <h2 class="cs-panel-titulo">Estado</h2>
            <form method="post" data-confirmar="¿Cambiar el estado del pedido?">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="estado">
                <label class="form-label small" for="estado">Nuevo estado</label>
                <div class="d-flex gap-2">
                    <select id="estado" name="estado" class="form-select">
                        <?php foreach (estados_pedido() as $es): ?><option <?= $estado === $es ? 'selected' : '' ?>><?= e($es) ?></option><?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-cs" data-cargando="…">Actualizar</button>
                </div>
            </form>
            <?php if ($vence = pedido_vence($pedido)): ?><p class="small text-secondary mt-2 mb-0">La reserva vence el <?= e(fecha_local($vence)) ?>.</p><?php endif; ?>
            <?php if (!empty($pedido['historial_estados'])): ?>
            <ul class="linea-tiempo mt-3">
                <?php foreach ($pedido['historial_estados'] as $h): ?>
                <li><strong><?= e($h['estado'] ?? '') ?></strong> · <span class="text-secondary"><?= e(fecha_local($h['fecha'] ?? null)) ?></span><br><span class="small text-secondary"><?= e($h['por'] ?? '') ?><?= !empty($h['nota']) ? ' — ' . e($h['nota']) : '' ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
        <div class="cs-panel mb-4">
            <h2 class="cs-panel-titulo">Pago</h2>
            <p class="small mb-1"><strong>Método:</strong> <?= e($pedido['metodo_pago'] ?? 'PSE (simulado)') ?></p>
            <?php foreach (['referencia' => 'Referencia', 'banco' => 'Banco (simulado)', 'resultado' => 'Resultado'] as $k => $t): if (!empty($pedido['pago'][$k])): ?>
            <p class="small mb-1"><strong><?= e($t) ?>:</strong> <?= e($pedido['pago'][$k]) ?></p>
            <?php endif; endforeach; ?>
        </div>
        <div class="cs-panel border border-danger-subtle">
            <h2 class="cs-panel-titulo">Eliminar pedido</h2>
            <?php if (in_array($estado, estados_sin_stock(), true)): ?>
            <form method="post" data-confirmar="¿Eliminar definitivamente el pedido <?= e(pedido_numero($pedido)) ?>? Esta acción no se puede deshacer.">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="eliminar">
                <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar pedido</button>
            </form>
            <?php else: ?>
            <p class="small text-secondary mb-0">Solo se pueden eliminar pedidos «Cancelado» o «Pago rechazado». Así las ventas registradas no se pierden por error.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php admin_fin(); ?>
