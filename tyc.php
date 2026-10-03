<?php
/** Términos y Condiciones (contenido original de tyc.html). */
require_once __DIR__ . '/includes/layout.php';
$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Términos y condiciones']];
layout_inicio([
    'titulo'      => 'Términos y condiciones',
    'descripcion' => 'Términos y condiciones de uso del sitio web y de compra en CERAMISHOP, la tienda en línea de CERAMICENTRO.',
    'canonical'   => 'tyc.php',
    'migas'       => $migas,
    'jsonld'      => [jsonld_migas($migas)],
]);
$correo = (string)config('empresa.correo', '');
?>
<article class="container contenido-legal py-4">
    <h1 class="text-danger-emphasis">Términos y Condiciones</h1>
    <p><strong>Última actualización:</strong> 1 de mayo de 2025</p>

    <h2>1. Aceptación de los Términos</h2>
    <p>Al acceder y utilizar el sitio web de <strong>Ceramicentro</strong>, aceptas estar sujeto a los siguientes Términos y Condiciones. Si no estás de acuerdo con ellos, por favor no utilices este sitio.</p>

    <h2>2. Uso del Sitio</h2>
    <p>Este sitio está destinado exclusivamente para la consulta y compra de productos ofrecidos por Ceramicentro. El usuario se compromete a no utilizar el sitio con fines fraudulentos o ilícitos.</p>

    <h2>3. Propiedad Intelectual</h2>
    <p>Todos los contenidos del sitio, incluyendo textos, imágenes, logos y diseños, son propiedad de Ceramicentro o de sus proveedores, y están protegidos por leyes de propiedad intelectual. Queda prohibida su reproducción sin autorización previa.</p>

    <h2>4. Registro y Protección de Datos</h2>
    <p>Al registrarte o hacer una compra, aceptas que tus datos sean tratados conforme a nuestra <a href="<?= e(url('politica_privacidad.php')) ?>">Política de Privacidad</a>. Nos comprometemos a proteger tu información personal y no compartirla con terceros sin tu consentimiento.</p>

    <h2>5. Condiciones de Compra</h2>
    <ul>
        <li>Los precios están en moneda local e incluyen impuestos.</li>
        <li>Los pedidos están sujetos a disponibilidad de inventario.</li>
        <li>Los tiempos de entrega son aproximados y pueden variar.</li>
        <li>Una vez confirmado el pago, no se aceptan cancelaciones ni devoluciones, salvo por defecto de fábrica.</li>
        <li>El pago por PSE de esta tienda funciona actualmente como <strong>simulador</strong>: no se realizan cobros bancarios reales.</li>
    </ul>

    <h2>6. Modificaciones</h2>
    <p>Ceramicentro se reserva el derecho de modificar estos Términos y Condiciones en cualquier momento. Los cambios serán efectivos una vez publicados en el sitio.</p>

    <h2>7. Contacto</h2>
    <p>Para cualquier duda relacionada con estos Términos y Condiciones, puedes escribirnos desde el <a href="<?= e(url('contacto.php')) ?>">formulario de contacto</a><?php if ($correo): ?> o al correo <a href="mailto:<?= e($correo) ?>"><?= e($correo) ?></a><?php endif; ?>.</p>

    <div class="mt-4 d-flex flex-wrap gap-2">
        <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-circle" aria-hidden="true"></i> Volver al inicio</a>
        <a href="<?= e(url('contacto.php')) ?>" class="btn btn-cs">Ir a contacto</a>
    </div>
</article>
<?php layout_fin(); ?>
