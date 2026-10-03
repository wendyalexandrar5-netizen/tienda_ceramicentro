<?php
/**
 * Página de error con la identidad de CERAMISHOP.
 * Variables: $errorCodigo, $errorTitulo, $errorMensaje (definidas por mostrar_error()).
 * Nunca muestra detalles técnicos: esos quedan en logs/app.log.
 */
require_once __DIR__ . '/layout.php';

$errorCodigo  = $errorCodigo ?? 404;
$errorTitulo  = $errorTitulo ?? 'No encontramos lo que buscas';
$errorMensaje = $errorMensaje ?? 'La página que buscas no existe o fue movida.';

layout_inicio([
    'titulo'      => $errorCodigo === 404 ? 'Página no encontrada' : $errorTitulo,
    'descripcion' => $errorMensaje,
    'noindex'     => true,
    'body_class'  => 'pagina-error',
]);
?>
<section class="container py-5 my-lg-4">
    <div class="error-caja text-center mx-auto">
        <p class="error-codigo" aria-hidden="true"><?= (int)$errorCodigo ?></p>
        <h1 class="h2 fw-bold mb-3"><?= e($errorTitulo) ?></h1>
        <p class="lead text-secondary mb-4"><?= e($errorMensaje) ?></p>
        <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
            <a href="<?= e(url(es_cliente() ? 'tienda.php' : 'index.php')) ?>" class="btn btn-cs btn-lg"><i class="bi bi-house-door" aria-hidden="true"></i> Volver al inicio</a>
            <a href="<?= e(url('productos.php')) ?>" class="btn btn-outline-cs btn-lg"><i class="bi bi-grid" aria-hidden="true"></i> Ver productos</a>
        </div>
        <?php if ($errorCodigo === 404): ?>
        <form action="<?= e(url('productos.php')) ?>" method="get" class="error-busqueda mx-auto" role="search">
            <label for="buscarError" class="form-label">¿Buscabas un producto?</label>
            <div class="input-group">
                <input type="search" id="buscarError" name="q" class="form-control" placeholder="Ej. baldosa, techo PVC, lavamanos">
                <button class="btn btn-cs" type="submit"><i class="bi bi-search" aria-hidden="true"></i> Buscar</button>
            </div>
        </form>
        <?php endif; ?>
        <p class="small text-secondary mt-4 mb-0">
            También puedes ir a <a href="<?= e(url('nosotros.php')) ?>">Nosotros</a> o <a href="<?= e(url('contacto.php')) ?>">contactarnos</a>.
        </p>
    </div>
</section>
<?php layout_fin(); ?>
