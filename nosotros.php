<?php
/** Página "Nosotros" (antes nosotros.html). */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';

$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Nosotros']];
layout_inicio([
    'titulo'      => 'Nosotros',
    'descripcion' => 'Conoce CERAMICENTRO: quiénes somos, nuestra misión y visión. Empresa comprometida con la calidad, el diseño y la innovación en cerámica, baldosas y techos PVC.',
    'canonical'   => 'nosotros.php',
    'activo'      => 'nosotros',
    'migas'       => $migas,
    'jsonld'      => [jsonld_organizacion(), jsonld_migas($migas)],
]);
$bloques = [
    ['img/quienessomos.png', '¿Quiénes somos?', 'En <strong>Ceramicentro</strong> somos una empresa comprometida con la calidad, el diseño y la innovación en productos de cerámica, baldosas y techos PVC. Con años de experiencia en el sector, trabajamos para brindar soluciones prácticas y estéticas para tus espacios.'],
    ['img/mision.png', 'Misión', 'Proveer productos de alta calidad en cerámica y materiales de construcción que mejoren la vida de nuestros clientes, brindando atención personalizada y garantizando satisfacción total en cada compra.'],
    ['img/vision.png', 'Visión', 'Ser líderes en el mercado nacional en distribución de productos de cerámica, reconocidos por nuestra innovación, confianza y excelencia en el servicio al cliente.'],
];
?>
<section class="container py-5">
    <h1 class="text-center text-uppercase mb-5" style="color:#c0392b">Nosotros</h1>
    <div class="row g-4 mb-5">
        <?php foreach ($bloques as [$img, $titulo, $texto]): ?>
        <div class="col-md-4">
            <article class="nosotros-card">
                <?= imagen_html($img, '', ['width' => 100, 'height' => 100]) ?>
                <h2><?= e($titulo) ?></h2>
                <p class="mb-0"><?= $texto /* texto fijo con formato */ ?></p>
            </article>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="text-center mx-auto" style="max-width:760px">
        <p class="lead">En Ceramicentro, cada cliente es parte de nuestra historia. Trabajamos con pasión, ética y compromiso para ofrecer materiales resistentes, estéticos y funcionales que embellecen hogares y espacios comerciales.</p>
        <div class="d-flex flex-wrap justify-content-center gap-2">
            <a href="<?= e(url('contacto.php')) ?>" class="btn btn-cs btn-lg text-uppercase">Contáctanos</a>
            <a href="<?= e(url('productos.php')) ?>" class="btn btn-outline-cs btn-lg">Ver productos</a>
        </div>
    </div>
</section>
<?php layout_fin(); ?>
