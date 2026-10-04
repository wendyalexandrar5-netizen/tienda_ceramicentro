<?php
/** Política de tratamiento de datos personales (Ley 1581 de 2012). */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagina_legal.php';
$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Política de privacidad']];
layout_inicio([
    'titulo'      => 'Política de privacidad',
    'descripcion' => 'Política de tratamiento de datos personales de CERAMISHOP – CERAMICENTRO: qué datos recopilamos, para qué los usamos, cómo los protegemos y cómo ejercer tus derechos.',
    'canonical'   => 'politica_privacidad.php',
    'migas'       => $migas,
    'jsonld'      => [jsonld_migas($migas)],
]);
$empresa = e(config('empresa.nombre', 'CERAMICENTRO'));
?>
<article class="container contenido-legal py-4">
    <h1>Política de privacidad y tratamiento de datos personales</h1>
    <p><strong>Última actualización:</strong> <?= e(fecha_vigencia_legal()) ?></p>
    <?php aviso_academico(); ?>
    <p>Esta política explica cómo se tratan los datos personales en la Tienda CERAMISHOP, conforme a la Ley Estatutaria 1581 de 2012 y el Decreto 1377 de 2013 (incorporado en el Decreto 1074 de 2015).</p>

    <h2>1. Responsable del tratamiento</h2>
    <p><?= $empresa ?><?php if (config('academico.activo')): ?>, como caso de estudio, junto con el equipo del proyecto académico que administra el sitio<?php endif; ?>. Contacto: <a href="<?= e(url('contacto.php')) ?>">formulario de contacto</a><?php if ($c = config('empresa.correo')): ?> o <a href="mailto:<?= e($c) ?>"><?= e($c) ?></a><?php endif; ?>.</p>

    <h2>2. Datos que recopilamos</h2>
    <p>Solo pedimos los datos necesarios para que la Tienda funcione:</p>
    <ul>
        <li><strong>Datos de la cuenta:</strong> nombre completo, correo electrónico y contraseña. La contraseña se guarda cifrada (hash): nadie, ni siquiera los administradores, puede leerla.</li>
        <li><strong>Datos de pedidos:</strong> productos, cantidades, valores, fecha, estado del pedido y la referencia del pago simulado.</li>
        <li><strong>Mensajes de contacto:</strong> nombre, correo, asunto y mensaje.</li>
        <li><strong>Datos técnicos:</strong> una cookie de sesión para mantenerte conectado y guardar tu carrito y, si lo aceptas, estadísticas anónimas de navegación. Por seguridad, se registran intentos fallidos de inicio de sesión (asociados a la dirección IP de forma cifrada) durante un máximo de 24 horas.</li>
    </ul>
    <p>No recopilamos datos bancarios ni números de tarjeta: el simulador de PSE no los solicita. Tampoco tratamos datos sensibles ni datos de menores de edad.</p>

    <h2>3. Finalidades</h2>
    <ul>
        <li>Crear y administrar tu cuenta.</li>
        <li>Registrar tus pedidos, mostrar su estado y generar tus comprobantes.</li>
        <li>Responder tus mensajes, solicitudes y reclamos.</li>
        <li>Proteger la Tienda frente a accesos no autorizados.</li>
        <li>Obtener estadísticas generales de uso, sin identificar personas, si aceptas las cookies de analítica.</li>
    </ul>
    <p>No vendemos ni compartimos tus datos con terceros con fines comerciales, ni te enviaremos publicidad sin tu autorización.</p>

    <h2>4. Autorización</h2>
    <p>Al crear tu cuenta o enviar el formulario de contacto, marcas la casilla de aceptación con la que autorizas el tratamiento de tus datos para las finalidades indicadas.</p>

    <h2>5. Tus derechos</h2>
    <p>Como titular de los datos puedes:</p>
    <ul>
        <li>Conocer, actualizar y rectificar tus datos (puedes cambiar tu nombre y contraseña en <a href="<?= e(url('mi_cuenta.php')) ?>">Mi cuenta</a>).</li>
        <li>Solicitar prueba de la autorización otorgada.</li>
        <li>Ser informado sobre el uso que se ha dado a tus datos.</li>
        <li>Revocar la autorización y solicitar la eliminación de tus datos, cuando no exista un deber legal de conservarlos.</li>
        <li>Presentar quejas ante la Superintendencia de Industria y Comercio (SIC).</li>
    </ul>

    <h2>6. Cómo ejercer tus derechos</h2>
    <p>Envía tu solicitud desde el <a href="<?= e(url('contacto.php')) ?>">formulario de contacto</a> indicando tu nombre, el correo de tu cuenta y lo que solicitas. Las consultas se responden en un plazo máximo de diez (10) días hábiles y los reclamos en un máximo de quince (15) días hábiles, según la Ley 1581 de 2012.</p>

    <h2>7. Seguridad</h2>
    <p>Aplicamos medidas como contraseñas cifradas, control de acceso por roles, protección contra el envío fraudulento de formularios, límites de intentos de inicio de sesión, sesiones que expiran por inactividad y conexión cifrada (HTTPS) cuando el sitio se publica en un servidor.</p>

    <h2>8. Conservación</h2>
    <p>Los datos se conservan mientras tu cuenta esté activa o mientras sean necesarios para las finalidades descritas. <?php if (config('academico.activo')): ?>Al finalizar el proyecto académico, los datos de prueba podrán ser eliminados.<?php endif; ?></p>

    <h2>9. Vigencia</h2>
    <p>Esta política rige desde el <?= e(fecha_vigencia_legal()) ?>. Cualquier cambio se publicará en esta página.</p>
</article>
<?php layout_fin(); ?>
