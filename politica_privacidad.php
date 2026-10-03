<?php
/**
 * Política de privacidad / tratamiento de datos personales.
 * IMPORTANTE: estructura preparada con la información que el sistema realmente recopila.
 * CERAMICENTRO debe completar los campos [PENDIENTE] y validarla con su asesor jurídico.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagina_legal.php';
$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Política de privacidad']];
layout_inicio([
    'titulo'      => 'Política de privacidad',
    'descripcion' => 'Política de privacidad y tratamiento de datos personales de CERAMISHOP – CERAMICENTRO: qué datos recopilamos, para qué y cómo ejercer tus derechos.',
    'canonical'   => 'politica_privacidad.php',
    'migas'       => $migas,
    'jsonld'      => [jsonld_migas($migas)],
]);
?>
<article class="container contenido-legal py-4">
    <h1>Política de privacidad</h1>
    <?php pendiente_legal('Estructura base. Debe completarse y revisarse por un asesor jurídico (en Colombia aplica el régimen de protección de datos personales, Ley 1581 de 2012 y normas que la reglamentan).'); ?>

    <h2>1. Responsable del tratamiento</h2>
    <p><?= e(config('empresa.nombre', 'CERAMICENTRO')) ?> · NIT [PENDIENTE] · Domicilio: <?= e(config('empresa.direccion') ?: '[PENDIENTE]') ?> · Correo: <?= e(config('empresa.correo') ?: '[PENDIENTE]') ?></p>

    <h2>2. Datos que recopilamos</h2>
    <p>La tienda solo solicita los datos necesarios para funcionar:</p>
    <ul>
        <li><strong>Cuenta:</strong> nombre, correo electrónico y contraseña (la contraseña se guarda cifrada y nadie puede leerla).</li>
        <li><strong>Pedidos:</strong> productos, cantidades, valores, fecha y estado de cada pedido.</li>
        <li><strong>Contacto:</strong> nombre, correo, asunto y mensaje enviados desde el formulario de contacto.</li>
        <li><strong>Datos técnicos:</strong> cookie de sesión necesaria para mantener tu sesión y tu carrito. Con tu permiso, estadísticas anónimas de navegación (ver <a href="<?= e(url('politica_cookies.php')) ?>">Política de cookies</a>).</li>
    </ul>
    <p>El simulador de pago PSE <strong>no solicita ni almacena</strong> datos bancarios.</p>

    <h2>3. Finalidades</h2>
    <ul>
        <li>Gestionar tu cuenta y tus pedidos.</li>
        <li>Generar comprobantes de compra.</li>
        <li>Responder tus mensajes y solicitudes.</li>
    </ul>
    <?php pendiente_legal('Confirmar o ampliar las finalidades (por ejemplo, envíos, facturación o comunicaciones comerciales con autorización previa).'); ?>

    <h2>4. Derechos del titular</h2>
    <p>Puedes conocer, actualizar, rectificar y solicitar la supresión de tus datos. Desde <a href="<?= e(url('mi_cuenta.php')) ?>">Mi cuenta</a> puedes actualizar tu nombre y contraseña.</p>
    <?php pendiente_legal('Indicar el canal oficial, el procedimiento y los plazos para atender consultas y reclamos.'); ?>

    <h2>5. Seguridad</h2>
    <p>Aplicamos medidas como contraseñas cifradas, control de acceso por roles y conexión cifrada (HTTPS) en producción.</p>

    <h2>6. Vigencia</h2>
    <?php pendiente_legal('Indicar fecha de entrada en vigencia y periodo de conservación de los datos.'); ?>
</article>
<?php layout_fin(); ?>
