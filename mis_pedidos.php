<?php
/** Historial de pedidos del cliente. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pedidos.php';
requerir_sesion('cliente');
liberar_pedidos_vencidos(); // devuelve el inventario de pedidos sin pagar cuya reserva venció

$uid = oid(usuario_actual()['id']);
$col = mongo()->selectCollection('pedidos');
$porPagina = 10;
$total = $col->countDocuments(['usuario_id' => $uid]);
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
$pedidos = iterator_to_array($col->find(['usuario_id' => $uid], ['sort' => ['fecha' => -1], 'skip' => ($pagina - 1) * $porPagina, 'limit' => $porPagina]), false);
$detalles = detalles_de_pedidos(array_map(fn($p) => $p['_id'], $pedidos));

layout_inicio(['titulo' => 'Mis pedidos', 'noindex' => true, 'activo' => 'pedidos']);
?>
<section class="container py-4">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
        <h1 class="mb-0"><i class="bi bi-box-seam" aria-hidden="true"></i> Mis pedidos</h1>
        <a href="<?= e(url('tienda.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left" aria-hidden="true"></i> Volver a la tienda</a>
    </div>

    <?php if (!$pedidos): ?>
    <div class="cs-panel text-center py-5">
        <i class="bi bi-bag display-4 text-secondary" aria-hidden="true"></i>
        <h2 class="h4 mt-3">Aún no has realizado pedidos</h2>
        <p class="text-secondary">Cuando compres, aquí verás el estado de cada pedido y podrás descargar tus comprobantes.</p>
        <a href="<?= e(url('tienda.php')) ?>" class="btn btn-cs">Empezar a comprar</a>
    </div>
    <?php else: ?>
    <div class="d-grid gap-3">
        <?php foreach ($pedidos as $p):
            $estado = pedido_estado($p);
            $lineas = $detalles[(string)$p['_id']] ?? [];
            $unidades = array_sum(array_map(fn($d) => (int)($d['cantidad'] ?? 0), $lineas));
        ?>
        <article class="pedido-card">
            <div class="d-flex flex-wrap justify-content-between gap-2">
                <div>
                    <h2 class="h5 mb-1">Pedido <?= e(pedido_numero($p)) ?></h2>
                    <p class="small text-secondary mb-2"><?= e(fecha_local($p['fecha'] ?? null)) ?> · <?= (int)$unidades ?> unidades<?= ($p['origen'] ?? '') === 'app_android' ? ' · desde la app' : '' ?></p>
                </div>
                <div class="text-sm-end">
                    <?= estado_badge($estado) ?>
                    <p class="h5 mt-2 mb-0"><?= e(dinero($p['total'] ?? 0)) ?></p>
                </div>
            </div>
            <?php if ($lineas): ?>
            <p class="small mb-2 text-secondary"><?= e(resumen(implode(', ', array_map(fn($d) => ($d['nombre_producto'] ?? 'Producto') . ' ×' . (int)($d['cantidad'] ?? 0), $lineas)), 160)) ?></p>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= e(url('ver_pedido.php', ['id' => (string)$p['_id']])) ?>" class="btn btn-sm btn-cs">Ver detalles</a>
                <?php if (in_array($estado, [ESTADO_PENDIENTE, ESTADO_RECHAZADO], true)): ?>
                <a href="<?= e(url('pago_pse.php', ['id' => (string)$p['_id']])) ?>" class="btn btn-sm btn-outline-cs"><i class="bi bi-bank" aria-hidden="true"></i> Completar pago</a>
                <?php elseif (in_array($estado, estados_venta(), true)): ?>
                <a href="<?= e(url('generar_comprobante.php', ['id' => (string)$p['_id']])) ?>" class="btn btn-sm btn-outline-secondary" target="_blank" rel="noopener" data-descarga="pdf"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Comprobante</a>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php paginacion_html($pagina, $paginas, 'mis_pedidos.php'); ?>
    <?php endif; ?>
</section>
<?php layout_fin(); ?>
