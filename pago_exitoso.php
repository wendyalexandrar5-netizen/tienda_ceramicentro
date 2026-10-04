<?php
/** Paso 4 de la compra: confirmación del pago (datos reales del pedido en MongoDB). */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/pedidos.php';
require_once __DIR__ . '/includes/pasos_compra.php';
requerir_sesion('cliente');

$id = (string)($_GET['id'] ?? ($_SESSION['ultimo_pedido']['id'] ?? ''));
$pedido = pedido_de_usuario($id, usuario_actual()['id']);
if (!$pedido) {
    flash('info', 'No hay un pago reciente para mostrar. Aquí están tus pedidos.');
    redirigir('mis_pedidos.php');
}
$estado = pedido_estado($pedido);
if ($estado === ESTADO_PENDIENTE || $estado === ESTADO_RECHAZADO) {
    redirigir('pago_pse.php', ['id' => (string)$pedido['_id']]);
}
$detalles = pedido_detalles($pedido);
$numero = pedido_numero($pedido);

// Evento de compra para analítica: solo una vez por pedido
$eventos = [];
$_SESSION['compras_medidas'] = $_SESSION['compras_medidas'] ?? [];
if (!in_array($id, $_SESSION['compras_medidas'], true)) {
    $_SESSION['compras_medidas'][] = $id;
    $eventos[] = ['purchase', [
        'transaction_id' => $numero, 'currency' => 'COP', 'value' => (float)$pedido['total'],
        'items' => array_map(fn($d) => ['item_id' => (string)$d['producto_id'], 'item_name' => $d['nombre_producto'], 'price' => $d['precio_unitario'], 'quantity' => $d['cantidad']], $detalles),
    ]];
}

layout_inicio(['titulo' => 'Pago confirmado', 'noindex' => true, 'activo' => 'pedidos']);
?>
<section class="container py-4">
    <?php pasos_compra(4); ?>
    <div class="cs-panel text-center mx-auto" style="max-width:760px">
        <div class="exito-icono mb-3" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
        <h1 class="h2">¡Pago realizado con éxito!</h1>
        <p class="text-secondary">Tu pedido fue registrado y el pago simulado fue aprobado.</p>

        <dl class="row text-start bg-light rounded-3 p-3 mx-0 my-4">
            <dt class="col-sm-5">Número de pedido</dt><dd class="col-sm-7 fw-bold"><?= e($numero) ?></dd>
            <dt class="col-sm-5">Fecha</dt><dd class="col-sm-7"><?= e(fecha_local($pedido['fecha'] ?? null)) ?></dd>
            <dt class="col-sm-5">Estado</dt><dd class="col-sm-7"><?= estado_badge($estado) ?></dd>
            <?php if (!empty($pedido['pago']['referencia'])): ?>
            <dt class="col-sm-5">Referencia de pago (simulada)</dt><dd class="col-sm-7"><?= e($pedido['pago']['referencia']) ?></dd>
            <?php endif; ?>
            <dt class="col-sm-5">Total pagado</dt><dd class="col-sm-7 fw-bold text-danger-emphasis"><?= e(dinero($pedido['total'] ?? 0)) ?></dd>
        </dl>

        <div class="text-start">
            <h2 class="h6 fw-bold">Productos</h2>
            <ul class="list-group list-group-flush mb-4">
                <?php foreach ($detalles as $d): ?>
                <li class="list-group-item d-flex justify-content-between gap-2 px-0">
                    <span><?= e($d['nombre_producto']) ?> <span class="text-secondary">× <?= (int)$d['cantidad'] ?></span></span>
                    <span class="fw-semibold"><?= e(dinero($d['subtotal'])) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <h2 class="h6 fw-bold">¿Qué sigue?</h2>
            <p class="text-secondary small"><?= e(estado_explicacion($estado)) ?> Puedes consultar el estado en cualquier momento desde «Mis pedidos» y descargar tu comprobante en PDF.</p>
        </div>

        <div class="d-flex flex-wrap justify-content-center gap-2 mt-3">
            <a href="<?= e(url('generar_comprobante.php', ['id' => (string)$pedido['_id']])) ?>" class="btn btn-cs" data-descarga="pdf" target="_blank" rel="noopener"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i> Descargar comprobante</a>
            <a href="<?= e(url('ver_pedido.php', ['id' => (string)$pedido['_id']])) ?>" class="btn btn-outline-cs"><i class="bi bi-receipt" aria-hidden="true"></i> Ver pedido</a>
            <a href="<?= e(url('mis_pedidos.php')) ?>" class="btn btn-outline-secondary">Mis pedidos</a>
            <a href="<?= e(url('tienda.php')) ?>" class="btn btn-outline-secondary">Volver a la tienda</a>
        </div>
    </div>
</section>
<?php layout_fin(['eventos' => $eventos]); ?>
