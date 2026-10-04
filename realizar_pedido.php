<?php
/**
 * Paso 2 de la compra: confirmar el pedido.
 * GET  -> muestra el resumen para que el cliente revise productos, cantidades y total.
 * POST -> crea el pedido "Pendiente de pago", reserva el inventario y lleva al simulador PSE.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/pedidos.php';
require_once __DIR__ . '/includes/pasos_compra.php';
requerir_sesion('cliente');
liberar_pedidos_vencidos(); // devuelve el inventario de pedidos sin pagar cuya reserva venció

$usuario = usuario_actual();
$resumen = carrito_resumen();

if (!$resumen['items']) {
    flash('info', 'Tu carrito está vacío. Agrega productos para continuar.');
    redirigir('tienda.php');
}
if (carrito_bloqueado($resumen)) {
    flash('warning', 'Hay productos sin inventario suficiente. Revisa tu carrito antes de continuar.');
    redirigir('ver_carrito.php');
}

if (es_post()) {
    csrf_verificar();
    $items = [];
    foreach ($resumen['items'] as $i) {
        $items[$i['id']] = $i['cantidad'];
    }
    $r = crear_pedido($usuario['id'], $items, [
        'origen'        => 'web',
        'estado'        => ESTADO_PENDIENTE,
        'token_cliente' => (string)($_POST['token_compra'] ?? ''),
    ]);
    if (!$r['ok']) {
        flash('error', $r['mensaje']);
        redirigir('ver_carrito.php');
    }
    $pedido = $r['pedido'];
    carrito_vaciar();
    unset($_SESSION['token_compra']);
    $_SESSION['ultimo_pedido'] = ['id' => (string)$pedido['_id']]; // compatibilidad con pago_exitoso.php
    redirigir('pago_pse.php', ['id' => (string)$pedido['_id']]);
}

// Token único de esta compra: evita pedidos duplicados por doble clic o recarga
if (empty($_SESSION['token_compra'])) {
    $_SESSION['token_compra'] = bin2hex(random_bytes(12));
}

layout_inicio(['titulo' => 'Confirmar pedido', 'noindex' => true, 'activo' => 'tienda']);
$itemsAnalitica = array_map(fn($i) => ['item_id' => $i['id'], 'item_name' => $i['nombre'], 'price' => $i['precio'], 'quantity' => $i['cantidad']], $resumen['items']);
?>
<section class="container py-4">
    <h1 class="mb-3">Confirmar pedido</h1>
    <?php pasos_compra(2); ?>

    <?php foreach ($resumen['problemas'] as $pr): ?>
    <div class="alert alert-info" role="status"><i class="bi bi-info-circle" aria-hidden="true"></i> <?= e($pr['mensaje']) ?></div>
    <?php endforeach; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="cs-panel">
                <h2 class="cs-panel-titulo">Productos que vas a comprar</h2>
                <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
                    <table class="table align-middle tabla-apilable mb-0">
                        <caption class="visually-hidden">Detalle del pedido</caption>
                        <thead><tr><th scope="col">Producto</th><th scope="col" class="text-center">Cantidad</th><th scope="col" class="text-end">Precio unitario</th><th scope="col" class="text-end">Subtotal</th></tr></thead>
                        <tbody>
                        <?php foreach ($resumen['items'] as $i): ?>
                            <tr>
                                <td data-label="Producto"><div class="d-flex align-items-center gap-2 text-start"><?= imagen_html($i['imagen'], '', ['class' => 'tabla-img', 'width' => 56, 'height' => 56]) ?><span><?= e($i['nombre']) ?></span></div></td>
                                <td data-label="Cantidad" class="text-md-center"><?= (int)$i['cantidad'] ?></td>
                                <td data-label="Precio unitario" class="text-md-end"><?= e(dinero($i['precio'])) ?></td>
                                <td data-label="Subtotal" class="text-md-end fw-semibold"><?= e(dinero($i['subtotal'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?= e(url('ver_carrito.php')) ?>" class="small d-inline-block mt-3"><i class="bi bi-pencil" aria-hidden="true"></i> Modificar carrito</a>
            </div>
            <div class="cs-panel mt-4">
                <h2 class="cs-panel-titulo">Datos del comprador</h2>
                <p class="mb-1"><strong>Nombre:</strong> <?= e($usuario['nombre']) ?></p>
                <p class="mb-0"><strong>Correo:</strong> <?= e($usuario['correo']) ?></p>
            </div>
        </div>
        <div class="col-lg-4">
            <section class="cs-panel resumen-compra">
                <h2 class="cs-panel-titulo">Total del pedido</h2>
                <p class="d-flex justify-content-between mb-1"><span>Base (sin IVA)</span><span><?= e(dinero($resumen['total'] / 1.19)) ?></span></p>
                <p class="d-flex justify-content-between mb-1"><span>IVA 19 %</span><span><?= e(dinero($resumen['total'] - $resumen['total'] / 1.19)) ?></span></p>
                <hr>
                <p class="d-flex justify-content-between align-items-baseline"><span class="fw-semibold">Total</span><span class="total"><?= e(dinero($resumen['total'])) ?></span></p>
                <form method="post">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="token_compra" value="<?= e($_SESSION['token_compra']) ?>">
                    <button type="submit" class="btn btn-cs btn-lg w-100" data-cargando="Registrando pedido…"><i class="bi bi-bank" aria-hidden="true"></i> Confirmar y pagar con PSE</button>
                </form>
                <p class="small text-secondary mt-3 mb-0">Al confirmar, reservamos los productos y pasas al <strong>simulador de PSE</strong>. Es una simulación: no se realiza ningún cobro real ni se piden datos bancarios.</p>
            </section>
        </div>
    </div>
</section>
<?php layout_fin(['eventos' => [['begin_checkout', ['currency' => 'COP', 'value' => $resumen['total'], 'items' => $itemsAnalitica]]]]); ?>
