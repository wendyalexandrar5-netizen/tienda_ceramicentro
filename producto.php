<?php
/** Detalle público de un producto (SEO: Open Graph + datos estructurados Product/Offer). */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';

$doc = producto_por_id((string)($_GET['id'] ?? ''));
if (!$doc) {
    no_encontrado('El producto que buscas no existe o ya no está disponible en nuestro catálogo.');
}
$p = producto_vista($doc);
$agotado = $p['stock'] <= 0;

$migas = [
    ['nombre' => 'Inicio', 'url' => 'index.php'],
    ['nombre' => 'Productos', 'url' => 'productos.php'],
];
if ($p['categoria_id'] !== '' && $p['categoria'] !== 'Sin categoría') {
    $migas[] = ['nombre' => $p['categoria'], 'url' => 'productos.php?categoria=' . $p['categoria_id']];
}
$migas[] = ['nombre' => $p['nombre']];

$descripcion = $p['descripcion'] !== ''
    ? $p['descripcion']
    : $p['nombre'] . ' disponible en CERAMICENTRO. Compra en línea en CERAMISHOP.';

$jsonProducto = [
    '@context' => 'https://schema.org',
    '@type'    => 'Product',
    'name'     => $p['nombre'],
    'image'    => [imagen_url_absoluta($p['imagen'])],
    'description' => $descripcion,
    'sku'      => $p['id'],
    'category' => $p['categoria'],
    'brand'    => ['@type' => 'Brand', 'name' => (string)config('empresa.nombre', 'CERAMICENTRO')],
    'offers'   => [
        '@type' => 'Offer',
        'url' => url_absoluta('producto.php', ['id' => $p['id']]),
        'priceCurrency' => 'COP',
        'price' => round($p['precio'], 2),
        'availability' => $agotado ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
        'itemCondition' => 'https://schema.org/NewCondition',
        'seller' => ['@id' => url_absoluta('#organizacion')],
    ],
];

// Productos relacionados de la misma categoría
$relacionados = [];
if ($p['categoria_id'] !== '') {
    foreach (productos_buscar(['categoria' => $p['categoria_id'], 'por_pagina' => 5, 'solo_disponibles' => true])['items'] as $r) {
        if ($r['id'] !== $p['id'] && count($relacionados) < 4) {
            $relacionados[] = $r;
        }
    }
}

$wa = whatsapp_url('Hola CERAMICENTRO, quiero información sobre: ' . $p['nombre']);

layout_inicio([
    'titulo'      => $p['nombre'],
    'descripcion' => $descripcion,
    'canonical'   => 'producto.php?id=' . $p['id'],
    'og_tipo'     => 'product',
    'og_imagen'   => imagen_url_absoluta($p['imagen']),
    'activo'      => 'productos',
    'migas'       => $migas,
    'jsonld'      => [$jsonProducto, jsonld_migas($migas)],
]);
?>
<section class="container py-4">
    <div class="row g-4 align-items-start">
        <div class="col-md-6">
            <div class="producto-detalle-img">
                <?= imagen_html($p['imagen'], $p['nombre'] . ' – ' . $p['categoria'], ['loading' => 'eager', 'fetchpriority' => 'high', 'width' => 800, 'height' => 800]) ?>
            </div>
        </div>
        <div class="col-md-6">
            <div class="producto-detalle-info">
                <a class="text-uppercase small fw-semibold text-decoration-none" href="<?= e(url('productos.php', ['categoria' => $p['categoria_id']])) ?>"><?= e($p['categoria']) ?></a>
                <h1 class="h2 mt-1"><?= e($p['nombre']) ?></h1>
                <p class="precio-grande mb-1"><?= e(dinero($p['precio'])) ?></p>
                <p class="small text-secondary mb-3">Precio en pesos colombianos (COP), IVA incluido.</p>

                <?php if ($agotado): ?>
                    <p class="estado-badge estado-error mb-3"><i class="bi bi-x-circle" aria-hidden="true"></i> Agotado</p>
                <?php elseif ($p['stock'] <= 5): ?>
                    <p class="estado-badge estado-pendiente mb-3">¡Últimas <?= (int)$p['stock'] ?> unidades!</p>
                <?php else: ?>
                    <p class="estado-badge estado-ok mb-3"><i class="bi bi-check-circle" aria-hidden="true"></i> Disponible (<?= (int)$p['stock'] ?> unidades)</p>
                <?php endif; ?>

                <?php if ($p['descripcion'] !== ''): ?>
                <h2 class="h6 fw-bold mt-2">Descripción</h2>
                <p class="text-secondary"><?= nl2br(e($p['descripcion'])) ?></p>
                <?php endif; ?>

                <?php if (es_cliente() && !$agotado): ?>
                <form method="post" action="<?= e(url('agregar_carrito.php')) ?>" class="form-agregar mt-3" data-ajax>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="producto_id" value="<?= e($p['id']) ?>">
                    <input type="hidden" name="volver" value="producto">
                    <label class="visually-hidden" for="cantidadDetalle">Cantidad</label>
                    <input type="number" id="cantidadDetalle" name="cantidad" value="1" min="1" max="<?= (int)$p['stock'] ?>" class="form-control input-cantidad" inputmode="numeric" required>
                    <button type="submit" class="btn btn-cs btn-lg" data-cargando="Agregando…"><i class="bi bi-cart-plus" aria-hidden="true"></i> Agregar al carrito</button>
                </form>
                <a href="<?= e(url('ver_carrito.php')) ?>" class="d-inline-block mt-2 small">Ir al carrito</a>
                <?php elseif (!usuario_actual()): ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a href="<?= e(url('login.php', ['volver' => 'producto.php?id=' . $p['id']])) ?>" class="btn btn-cs"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Inicia sesión para comprar</a>
                    <a href="<?= e(url('registro.php')) ?>" class="btn btn-outline-cs">Crear cuenta</a>
                </div>
                <?php endif; ?>

                <?php if ($wa): ?>
                <p class="mt-3 mb-0 small"><a href="<?= e($wa) ?>" target="_blank" rel="noopener" data-evento="contacto_whatsapp"><i class="bi bi-whatsapp" aria-hidden="true"></i> Pregunta por este producto en WhatsApp</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($relacionados): ?>
    <section class="mt-5" aria-labelledby="tituloRelacionados">
        <h2 id="tituloRelacionados" class="h4 mb-3">También te puede interesar</h2>
        <div class="row g-4">
            <?php foreach ($relacionados as $p): ?>
            <div class="col-sm-6 col-lg-3"><?php require __DIR__ . '/includes/tarjeta_producto.php'; ?></div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>
</section>
<?php
$v = producto_vista($doc);
layout_fin(['eventos' => [['view_item', ['currency' => 'COP', 'value' => $v['precio'], 'items' => [['item_id' => $v['id'], 'item_name' => $v['nombre'], 'item_category' => $v['categoria'], 'price' => $v['precio']]]]]]]);
