<?php
/** Carrito de compras del cliente. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/pasos_compra.php';
requerir_sesion('cliente');

$resumen = carrito_resumen();
$bloqueado = carrito_bloqueado($resumen);
layout_inicio(['titulo' => 'Mi carrito', 'noindex' => true, 'activo' => 'tienda']);
?>
<section class="container py-4">
    <h1 class="mb-3"><i class="bi bi-cart3" aria-hidden="true"></i> Tu carrito de compras</h1>
    <?php pasos_compra(1); ?>

    <?php if (!$resumen['items'] && !$resumen['problemas']): ?>
    <div class="cs-panel text-center py-5">
        <i class="bi bi-cart-x display-4 text-secondary" aria-hidden="true"></i>
        <h2 class="h4 mt-3">Tu carrito está vacío</h2>
        <p class="text-secondary">Explora el catálogo y agrega los productos que necesitas.</p>
        <a href="<?= e(url('tienda.php')) ?>" class="btn btn-cs btn-lg">Ir a la tienda</a>
    </div>
    <?php else: ?>

    <?php if ($resumen['problemas']): ?>
    <div class="alert alert-warning" role="alert">
        <p class="fw-semibold mb-1"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i> Revisa tu carrito:</p>
        <ul class="mb-0">
            <?php foreach ($resumen['problemas'] as $pr): ?><li><?= e($pr['mensaje']) ?></li><?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="cs-panel">
                <?php foreach ($resumen['items'] as $item): ?>
                <div class="carrito-item">
                    <?= imagen_html($item['imagen'], $item['nombre'], ['width' => 84, 'height' => 84]) ?>
                    <div>
                        <h2 class="h6 mb-1"><a href="<?= e(url('producto.php', ['id' => $item['id']])) ?>" class="text-reset text-decoration-none"><?= e($item['nombre']) ?></a></h2>
                        <p class="small text-secondary mb-1"><?= e(dinero($item['precio'])) ?> c/u · <?= (int)$item['stock'] ?> disponibles</p>
                        <p class="fw-bold mb-0">Subtotal: <?= e(dinero($item['subtotal'])) ?></p>
                    </div>
                    <div class="acciones">
                        <form method="post" action="<?= e(url('actualizar_carrito.php')) ?>" class="d-flex gap-1 align-items-center">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="producto_id" value="<?= e($item['id']) ?>">
                            <label class="visually-hidden" for="cant-<?= e($item['id']) ?>">Cantidad de <?= e($item['nombre']) ?></label>
                            <input type="number" id="cant-<?= e($item['id']) ?>" name="cantidad" class="form-control form-control-sm input-cantidad" min="1" max="<?= max(1, (int)$item['stock']) ?>" value="<?= (int)$item['cantidad'] ?>" inputmode="numeric" required>
                            <button type="submit" class="btn btn-sm btn-outline-secondary" data-cargando="…" aria-label="Actualizar cantidad de <?= e($item['nombre']) ?>"><i class="bi bi-arrow-repeat" aria-hidden="true"></i></button>
                        </form>
                        <form method="post" action="<?= e(url('eliminar_del_carrito.php')) ?>" data-confirmar="¿Quitar «<?= e($item['nombre']) ?>» del carrito?">
                            <?= csrf_campo() ?>
                            <input type="hidden" name="id" value="<?= e($item['id']) ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" aria-label="Quitar <?= e($item['nombre']) ?> del carrito"><i class="bi bi-trash" aria-hidden="true"></i> <span class="d-none d-sm-inline">Quitar</span></button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php foreach ($resumen['problemas'] as $pr): if ($pr['tipo'] === 'no_existe'): ?>
                <form method="post" action="<?= e(url('eliminar_del_carrito.php')) ?>" class="mt-2">
                    <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e($pr['id']) ?>">
                    <button class="btn btn-sm btn-outline-danger" type="submit">Quitar producto no disponible</button>
                </form>
                <?php endif; endforeach; ?>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
                <a href="<?= e(url('tienda.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-shop" aria-hidden="true"></i> Seguir comprando</a>
                <form method="post" action="<?= e(url('vaciar_carrito.php')) ?>" data-confirmar="¿Seguro que quieres vaciar todo el carrito?">
                    <?= csrf_campo() ?>
                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash3" aria-hidden="true"></i> Vaciar carrito</button>
                </form>
            </div>
        </div>
        <div class="col-lg-4">
            <aside class="cs-panel resumen-compra" aria-labelledby="tituloResumen">
                <h2 id="tituloResumen" class="cs-panel-titulo">Resumen</h2>
                <dl class="d-flex justify-content-between mb-2"><dt class="fw-normal">Productos</dt><dd class="mb-0"><?= (int)$resumen['unidades'] ?> unidades</dd></dl>
                <dl class="d-flex justify-content-between mb-2 small text-secondary"><dt class="fw-normal">IVA incluido (19 %)</dt><dd class="mb-0"><?= e(dinero($resumen['total'] - $resumen['total'] / 1.19)) ?></dd></dl>
                <hr>
                <p class="d-flex justify-content-between align-items-baseline mb-3"><span class="fw-semibold">Total a pagar</span><span class="total"><?= e(dinero($resumen['total'])) ?></span></p>
                <?php if ($bloqueado): ?>
                <button class="btn btn-cs btn-lg w-100" disabled aria-describedby="avisoBloqueo">Finalizar compra</button>
                <p id="avisoBloqueo" class="small text-danger mt-2 mb-0">Corrige los avisos del carrito para continuar.</p>
                <?php else: ?>
                <a href="<?= e(url('realizar_pedido.php')) ?>" class="btn btn-cs btn-lg w-100" data-evento="begin_checkout_click"><i class="bi bi-lock" aria-hidden="true"></i> Finalizar compra</a>
                <?php endif; ?>
                <p class="small text-secondary mt-3 mb-0"><i class="bi bi-shield-check" aria-hidden="true"></i> El pago se realiza con un <strong>simulador de PSE</strong>: no se cobra dinero real.</p>
            </aside>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php layout_fin(); ?>
