<?php
/**
 * Tarjeta de producto reutilizable (catálogo público, tienda y portada).
 * Requiere la variable $p (resultado de producto_vista()).
 * Si el visitante es cliente muestra el formulario "Agregar al carrito".
 */
$agotado = $p['stock'] <= 0;
$pocas   = !$agotado && $p['stock'] <= 5;
$urlProducto = url('producto.php', ['id' => $p['id']]);
?>
<article class="producto-card">
    <a class="producto-media" href="<?= e($urlProducto) ?>" tabindex="-1" aria-hidden="true">
        <?= imagen_html($p['imagen'], $p['nombre'] . ' – ' . $p['categoria'], ['width' => 400, 'height' => 300]) ?>
        <?php if ($agotado): ?><span class="etiqueta-stock etiqueta-agotado">Agotado</span>
        <?php elseif ($pocas): ?><span class="etiqueta-stock etiqueta-pocas">Últimas <?= (int)$p['stock'] ?> unidades</span><?php endif; ?>
    </a>
    <div class="card-body">
        <span class="producto-categoria"><?= e($p['categoria']) ?></span>
        <h3 class="producto-nombre"><a href="<?= e($urlProducto) ?>"><?= e($p['nombre']) ?></a></h3>
        <?php if ($p['descripcion'] !== ''): ?><p class="producto-desc"><?= e($p['descripcion']) ?></p><?php endif; ?>
        <p class="producto-precio mb-0"><?= e(dinero($p['precio'])) ?></p>
    </div>
    <div class="card-footer">
        <?php if (es_cliente()): ?>
            <?php if ($agotado): ?>
                <p class="text-danger fw-semibold small mb-0"><i class="bi bi-x-circle" aria-hidden="true"></i> Sin stock por ahora</p>
            <?php else: ?>
                <form method="post" action="<?= e(url('agregar_carrito.php')) ?>" class="form-agregar" data-ajax>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="producto_id" value="<?= e($p['id']) ?>">
                    <label class="visually-hidden" for="cant-<?= e($p['id']) ?>">Cantidad de <?= e($p['nombre']) ?></label>
                    <input type="number" id="cant-<?= e($p['id']) ?>" name="cantidad" value="1" min="1" max="<?= (int)$p['stock'] ?>" class="form-control input-cantidad" inputmode="numeric" required>
                    <button type="submit" class="btn btn-cs" data-cargando="Agregando…"><i class="bi bi-cart-plus" aria-hidden="true"></i> Agregar</button>
                </form>
            <?php endif; ?>
        <?php elseif (!es_admin()): ?>
            <a href="<?= e($urlProducto) ?>" class="btn btn-outline-cs w-100 position-relative" style="z-index:2">Ver detalle<span class="visually-hidden"> de <?= e($p['nombre']) ?></span></a>
        <?php endif; ?>
    </div>
</article>
