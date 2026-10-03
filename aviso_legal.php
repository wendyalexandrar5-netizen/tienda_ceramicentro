<?php
/**
 * Aviso legal.
 * IMPORTANTE: estructura preparada. CERAMICENTRO debe completar los datos marcados como
 * [PENDIENTE] y hacer revisar el texto por su asesor jurídico antes de publicarlo.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagina_legal.php';
$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Aviso legal']];
layout_inicio([
    'titulo'      => 'Aviso legal',
    'descripcion' => 'Aviso legal del sitio web CERAMISHOP de CERAMICENTRO: titular del sitio, condiciones de uso y propiedad intelectual.',
    'canonical'   => 'aviso_legal.php',
    'migas'       => $migas,
    'jsonld'      => [jsonld_migas($migas)],
]);
?>
<article class="container contenido-legal py-4">
    <h1>Aviso legal</h1>
    <?php pendiente_legal('Este documento es una estructura base. Complete los datos de la empresa y solicite la revisión de un asesor jurídico antes de considerarlo definitivo.'); ?>

    <h2>1. Titular del sitio web</h2>
    <ul>
        <li><strong>Razón social:</strong> <?= e(config('empresa.nombre', 'CERAMICENTRO')) ?></li>
        <li><strong>NIT:</strong> [PENDIENTE]</li>
        <li><strong>Domicilio:</strong> <?= e(config('empresa.direccion') ?: '[PENDIENTE]') ?></li>
        <li><strong>Correo de contacto:</strong> <?= e(config('empresa.correo') ?: '[PENDIENTE]') ?></li>
        <li><strong>Teléfono:</strong> <?= e(config('empresa.telefono') ?: '[PENDIENTE]') ?></li>
    </ul>

    <h2>2. Objeto</h2>
    <p>Este sitio web permite consultar el catálogo de productos de <?= e(config('empresa.nombre', 'CERAMICENTRO')) ?> y realizar pedidos en línea a través de la tienda CERAMISHOP.</p>

    <h2>3. Condiciones de uso</h2>
    <p>El uso del sitio implica la aceptación de los <a href="<?= e(url('tyc.php')) ?>">Términos y condiciones</a>. El usuario se compromete a hacer un uso adecuado de los contenidos y a no emplearlos para actividades ilícitas.</p>

    <h2>4. Propiedad intelectual</h2>
    <p>Los textos, imágenes, logotipos y diseños del sitio pertenecen a <?= e(config('empresa.nombre', 'CERAMICENTRO')) ?> o a sus proveedores. No está permitida su reproducción sin autorización.</p>

    <h2>5. Pagos</h2>
    <p>El módulo de pago PSE disponible actualmente en la tienda es un <strong>simulador</strong> con fines demostrativos: no procesa transacciones bancarias reales.</p>
    <?php pendiente_legal('Actualizar esta sección cuando se integre una pasarela de pagos real.'); ?>

    <h2>6. Responsabilidad</h2>
    <?php pendiente_legal('Definir con el asesor jurídico las limitaciones de responsabilidad aplicables.'); ?>

    <h2>7. Legislación aplicable</h2>
    <?php pendiente_legal('Indicar la legislación y jurisdicción aplicables según la asesoría jurídica de la empresa.'); ?>
</article>
<?php layout_fin(); ?>
