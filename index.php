<?php
/** Página de inicio pública de CERAMICENTRO (antes index.html). */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';

// Productos recientes y categorías desde MongoDB (si la base no responde, la portada sigue funcionando)
$recientes = [];
$categorias = [];
try {
    $recientes = productos_buscar(['orden' => 'recientes', 'por_pagina' => 4, 'solo_disponibles' => true])['items'];
    $categorias = categorias_todas();
} catch (Throwable $e) {
    log_app('aviso', 'Inicio sin datos de catálogo: ' . $e->getMessage());
}

$destacados = [
    ['imagenes/castillo.jpg', 'Baldosa castillo', 'Cerámica moderna con estilo rústico, perfecta para construcciones con diseños antiguos o lugares donde quieras apreciar un poco de elegancia.', 'castillo'],
    ['imagenes/espacato.jpg', 'Enchape espacato', 'Cerámica moderna con estilo clásico, perfecta para construcciones con diseños simples pero elegantes.', 'espacato'],
    ['img/c3.jpg', 'Piso madera', 'Pisos tipo madera, perfectos para lugares elegantes o campestres, ya que dan la apariencia de ser madera real.', 'piso madera'],
    ['img/c4.webp', 'Techo', 'Techo tipo PVC color gris-blanco, perfecto si quieres interiores iluminados y con un diseño hermoso en olas.', 'techo'],
];

layout_inicio([
    'titulo'      => 'Cerámica, baldosas, techos PVC y más',
    'descripcion' => 'CERAMICENTRO: cerámica, baldosas, enchapes, pisos, techos PVC, baños y materiales de construcción. Compra en línea en CERAMISHOP con pago PSE.',
    'canonical'   => 'index.php',
    'activo'      => 'inicio',
    'jsonld'      => [jsonld_organizacion(), jsonld_sitio_web()],
]);
?>
<section class="cs-hero">
    <div class="container">
        <h1>Ceramicentro</h1>
        <p>En Ceramicentro somos una empresa comprometida con la calidad, el diseño y la innovación en productos de cerámica, baldosas y techos PVC. Con años de experiencia en el sector, trabajamos para brindar soluciones prácticas y estéticas para tus espacios.</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?= e(url('productos.php')) ?>" class="btn btn-hero">Ver productos</a>
            <a href="<?= e(url('contacto.php')) ?>" class="btn btn-outline-light text-uppercase fw-semibold px-4">Información</a>
        </div>
    </div>
</section>

<section class="cs-seccion cs-seccion-gris" aria-labelledby="tituloDestacados">
    <img class="decoracion d-none d-md-block" src="<?= e(url('img/tornillos.png')) ?>" alt="" loading="lazy" onerror="this.remove()">
    <div class="container text-center position-relative">
        <h2 id="tituloDestacados" class="cs-titulo-seccion">Productos destacados</h2>
        <p class="cs-subtitulo">En Ceramicentro contamos con gran variedad de productos, pero hay algunos que merecen un apartado especial.</p>
        <div class="row g-4">
            <?php foreach ($destacados as [$img, $titulo, $texto, $busqueda]): ?>
            <div class="col-sm-6 col-lg-3">
                <article class="destacado">
                    <?= imagen_html($img, $titulo . ' de CERAMICENTRO', ['width' => 400, 'height' => 300]) ?>
                    <h3><?= e($titulo) ?></h3>
                    <p><?= e($texto) ?></p>
                    <a href="<?= e(url('productos.php', ['q' => $busqueda])) ?>" class="btn btn-outline-cs btn-sm mt-auto">Ver opciones<span class="visually-hidden"> de <?= e($titulo) ?></span></a>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<?php if ($categorias): ?>
<section class="cs-seccion pb-0" aria-labelledby="tituloCategorias">
    <div class="container text-center">
        <h2 id="tituloCategorias" class="h3 mb-3">Explora por categoría</h2>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <?php foreach ($categorias as $c): ?>
            <a class="chip-categoria" href="<?= e(url('productos.php', ['categoria' => $c['id']])) ?>"><?= e($c['nombre']) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($recientes): ?>
<section class="cs-seccion" aria-labelledby="tituloNuevos">
    <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
            <h2 id="tituloNuevos" class="h3 mb-0">Recién llegados</h2>
            <a href="<?= e(url('productos.php', ['orden' => 'recientes'])) ?>" class="fw-semibold">Ver todo el catálogo <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
        <div class="row g-4">
            <?php foreach ($recientes as $p): ?>
            <div class="col-sm-6 col-lg-3"><?php require __DIR__ . '/includes/tarjeta_producto.php'; ?></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="cs-seccion pt-0">
    <div class="container">
        <div class="cs-panel d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">
            <div>
                <h2 class="h4 mb-1">¿Necesitas asesoría para tu proyecto?</h2>
                <p class="mb-0 text-secondary">Escríbenos y te ayudamos a elegir los materiales ideales para tus espacios.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="<?= e(url('contacto.php')) ?>" class="btn btn-cs">Contáctanos</a>
                <?php if (!usuario_actual()): ?><a href="<?= e(url('registro.php')) ?>" class="btn btn-outline-cs">Crear cuenta</a><?php endif; ?>
            </div>
        </div>
    </div>
</section>
<?php layout_fin(); ?>
