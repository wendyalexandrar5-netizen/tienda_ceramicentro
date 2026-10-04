<?php
/** Detalle de un pedido del cliente (solo puede ver sus propios pedidos). */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/pedidos.php';
requerir_sesion('cliente');

$pedido = pedido_de_usuario((string)($_GET['id'] ?? ''), usuario_actual()['id']);
if (!$pedido) {
    no_encontrado('No encontramos ese pedido en tu cuenta. Revisa la lista de tus pedidos.');
}
$pedido = liberar_si_vencido($pedido);
$id = (string)$pedido['_id'];
$estado = pedido_estado($pedido);
$detalles = pedido_detalles($pedido);
$total = (float)($pedido['total'] ?? 0);

layout_inicio(['titulo' => 'Pedido ' . pedido_numero($pedido), 'noindex' => true, 'activo' => 'pedidos',
    'migas' => [['nombre' => 'Mis pedidos', 'url' => 'mis_pedidos.php'], ['nombre' => pedido_numero($pedido)]]]);
?>
<section class="container py-4">
    <div class="row g-4">
        <div class="col-lg-8">
            <div class="cs-panel">
                <div class="d-flex flex-wrap justify-content-between gap-2 mb-3">
                    <div>
                        <h1 class="h3 mb-1">Pedido <?= e(pedido_numero($pedido)) ?></h1>
                        <p class="text-secondary mb-0"><?= e(fecha_local($pedido['fecha'] ?? null)) ?></p>
                    </div>
                    <div><?= estado_badge($estado) ?></div>
                </div>
                <p class="mb-4"><?= e(!empty($pedido['cancelado_por_vencimiento']) ? 'Este pedido se canceló automáticamente porque el pago no se completó a tiempo. Los productos volvieron al inventario y no se realizó ningún cobro.' : estado_explicacion($estado)) ?></p>

                <h2 class="h6 fw-bold">Productos del pedido</h2>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
                    <table class="table align-middle tabla-apilable">
                        <thead><tr><th scope="col">Producto</th><th scope="col" class="text-center">Cantidad</th><th scope="col" class="text-end">Precio unitario</th><th scope="col" class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($detalles as $d): ?>
                            <tr>
                                <td data-label="Producto"><div class="d-flex align-items-center gap-2"><?= imagen_html((string)($d['imagen'] ?? ''), '', ['class' => 'tabla-img', 'width' => 56, 'height' => 56]) ?><span><?= e($d['nombre_producto']) ?></span></div></td>
                                <td data-label="Cantidad" class="text-md-center"><?= (int)$d['cantidad'] ?></td>
                                <td data-label="Precio unitario" class="text-md-end"><?= e(dinero($d['precio_unitario'])) ?></td>
                                <td data-label="Subtotal" class="text-md-end"><?= e(dinero($d['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr><td colspan="3" class="text-end d-none d-md-table-cell">Base (sin IVA)</td><td class="text-end" data-label="Base (sin IVA)"><?= e(dinero($total / 1.19)) ?></td></tr>
                            <tr><td colspan="3" class="text-end d-none d-md-table-cell">IVA 19 %</td><td class="text-end" data-label="IVA 19 %"><?= e(dinero($total - $total / 1.19)) ?></td></tr>
                            <tr class="fw-bold"><td colspan="3" class="text-end d-none d-md-table-cell">Total</td><td class="text-end" data-label="Total"><?= e(dinero($total)) ?></td></tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <section class="cs-panel mb-4">
                <h2 class="cs-panel-titulo">Acciones</h2>
                <div class="d-grid gap-2">
                    <?php if ($vence = pedido_vence($pedido)): ?><p class="small text-secondary mb-1">Reservado hasta <?= e(fecha_local($vence)) ?></p><?php endif; ?>
                    <?php if (in_array($estado, [ESTADO_PENDIENTE, ESTADO_RECHAZADO], true)): ?>
                    <a href="<?= e(url('pago_pse.php', ['id' => $id])) ?>" class="btn btn-cs"><i class="bi bi-bank" aria-hidden="true"></i> Completar pago</a>
                    <?php endif; ?>
                    <a href="<?= e(url('generar_comprobante.php', ['id' => $id])) ?>" class="btn btn-outline-cs" target="_blank" rel="noopener" data-descarga="pdf"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Comprobante PDF</a>
                    <a href="<?= e(url('descargar_pedido.php', ['id' => $id])) ?>" class="btn btn-outline-secondary" data-descarga="pdf"><i class="bi bi-download" aria-hidden="true"></i> Descargar detalle (PDF)</a>
                    <form method="post" action="<?= e(url('repetir_pedido.php')) ?>" class="d-grid">
                        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e($id) ?>">
                        <button type="submit" class="btn btn-outline-secondary" data-cargando="Agregando…"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Repetir pedido</button>
                    </form>
                    <a href="<?= e(url('mis_pedidos.php')) ?>" class="btn btn-link">⬅ Volver a mis pedidos</a>
                </div>
            </section>
            <section class="cs-panel">
                <h2 class="cs-panel-titulo">Pago y seguimiento</h2>
                <p class="small mb-1"><strong>Método:</strong> <?= e($pedido['metodo_pago'] ?? 'PSE (simulado)') ?></p>
                <?php if (!empty($pedido['pago']['referencia'])): ?><p class="small mb-1"><strong>Referencia:</strong> <?= e($pedido['pago']['referencia']) ?></p><?php endif; ?>
                <?php if (!empty($pedido['pago']['banco'])): ?><p class="small mb-3"><strong>Banco (simulado):</strong> <?= e($pedido['pago']['banco']) ?></p><?php endif; ?>
                <?php if (!empty($pedido['historial_estados'])): ?>
                <ul class="linea-tiempo mt-3">
                    <?php foreach ($pedido['historial_estados'] as $h): ?>
                    <li><strong><?= e($h['estado'] ?? '') ?></strong><br><span class="text-secondary"><?= e(fecha_local($h['fecha'] ?? null)) ?></span></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </section>
        </div>
    </div>
</section>
<?php layout_fin(); ?>
