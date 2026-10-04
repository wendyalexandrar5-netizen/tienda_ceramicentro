<?php
/** Términos y Condiciones de uso y de compra. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagina_legal.php';
$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Términos y condiciones']];
layout_inicio([
    'titulo'      => 'Términos y condiciones',
    'descripcion' => 'Términos y condiciones de uso del sitio web y la app CERAMISHOP de CERAMICENTRO: cuentas, pedidos, precios, pagos simulados y derechos del consumidor.',
    'canonical'   => 'tyc.php',
    'migas'       => $migas,
    'jsonld'      => [jsonld_migas($migas)],
]);
$empresa = e(config('empresa.nombre', 'CERAMICENTRO'));
?>
<article class="container contenido-legal py-4">
    <h1 class="text-danger-emphasis">Términos y Condiciones</h1>
    <p><strong>Última actualización:</strong> <?= e(fecha_vigencia_legal()) ?></p>
    <?php aviso_academico(); ?>

    <h2>1. Aceptación</h2>
    <p>Estos Términos y Condiciones regulan el uso del sitio web y de la aplicación móvil CERAMISHOP (en adelante, «la Tienda») de <strong><?= $empresa ?></strong>. Al navegar, crear una cuenta o registrar un pedido, aceptas estos términos, la <a href="<?= e(url('politica_privacidad.php')) ?>">Política de privacidad</a> y la <a href="<?= e(url('politica_cookies.php')) ?>">Política de cookies</a>. Si no estás de acuerdo, por favor no uses la Tienda.</p>

    <h2>2. Uso de la Tienda</h2>
    <ul>
        <li>La Tienda permite consultar el catálogo de cerámica, baldosas, enchapes, techos PVC, baños, grifería y materiales de construcción, y registrar pedidos.</li>
        <li>Te comprometes a usarla de forma lícita, a no intentar acceder a cuentas o áreas restringidas, a no alterar su funcionamiento y a no usarla con fines fraudulentos.</li>
        <li>Podemos suspender cuentas que incumplan estos términos.</li>
    </ul>

    <h2>3. Cuenta de usuario</h2>
    <ul>
        <li>Para comprar debes registrarte con datos veraces: nombre y correo electrónico.</li>
        <li>Eres responsable de mantener tu contraseña en secreto. Si sospechas un uso indebido, cámbiala desde <a href="<?= e(url('mi_cuenta.php')) ?>">Mi cuenta</a> o usa «¿Olvidaste tu contraseña?».</li>
        <li>La Tienda está dirigida a personas mayores de edad.</li>
    </ul>

    <h2>4. Productos, precios e inventario</h2>
    <ul>
        <li>Los precios se muestran en pesos colombianos (COP) e incluyen el IVA del 19 %.</li>
        <li>Las imágenes son ilustrativas; el color y la textura pueden variar levemente según la pantalla y el lote de fabricación.</li>
        <li>Los pedidos están sujetos a la disponibilidad de inventario. El precio que aplica es el vigente al momento de confirmar el pedido.</li>
    </ul>

    <h2>5. Pedidos y pago</h2>
    <ul>
        <li>Al confirmar un pedido, los productos quedan reservados por un tiempo limitado (<?= (int)config('horas_reserva', 24) ?> horas). Si el pago no se completa en ese plazo, el pedido se cancela automáticamente y los productos vuelven a estar disponibles.</li>
        <li><strong>El pago por PSE de la Tienda es un simulador.</strong> No se conecta con bancos, no solicita claves ni datos bancarios y no realiza cobros. Su objetivo es demostrar el proceso de compra.</li>
        <li>Cada pedido recibe un número (por ejemplo, CS-000123) y un comprobante en PDF disponible en «Mis pedidos». El comprobante no reemplaza una factura electrónica.</li>
        <li>Puedes consultar el estado de cada pedido (Pendiente de pago, Pagado, En preparación, Enviado, Entregado, Pago rechazado o Cancelado) en «Mis pedidos».</li>
    </ul>

    <h2>6. Entregas</h2>
    <p>Los tiempos y costos de entrega se informan antes de despacharse un pedido y pueden variar según la ciudad y el tipo de producto.<?php if (config('academico.activo')): ?> Al tratarse de un proyecto académico, actualmente no se realizan entregas.<?php endif; ?></p>

    <h2>7. Derecho de retracto, cambios y garantía</h2>
    <p>De acuerdo con el Estatuto del Consumidor (Ley 1480 de 2011), en las compras a distancia puedes ejercer el <strong>derecho de retracto dentro de los cinco (5) días hábiles</strong> siguientes a la entrega del producto, devolviéndolo en las mismas condiciones en que lo recibiste; el dinero se reintegra dentro de los treinta (30) días calendario siguientes. Los productos también cuentan con la garantía legal por calidad e idoneidad. Para cualquiera de estas solicitudes escríbenos desde el <a href="<?= e(url('contacto.php')) ?>">formulario de contacto</a>.</p>

    <h2>8. Propiedad intelectual</h2>
    <p>Los textos, imágenes, logotipos, diseños y el software de la Tienda pertenecen a <?= $empresa ?>, a sus proveedores o a los autores del proyecto, y están protegidos por las normas de derechos de autor (Ley 23 de 1982). No está permitida su reproducción con fines comerciales sin autorización.</p>

    <h2>9. Responsabilidad</h2>
    <p>Trabajamos para que la información de la Tienda sea correcta y que el servicio esté disponible, pero pueden presentarse errores o interrupciones. Si detectas un error en un precio o en una descripción, te informaremos antes de procesar el pedido y podrás cancelarlo sin costo.</p>

    <h2>10. Cambios a estos términos</h2>
    <p>Podemos actualizar estos términos. La versión vigente siempre estará publicada en esta página con su fecha de actualización.</p>

    <h2>11. Ley aplicable y contacto</h2>
    <p>Estos términos se rigen por las leyes de la República de Colombia, incluidas la Ley 527 de 1999 (comercio electrónico) y la Ley 1480 de 2011 (protección al consumidor). Para dudas, escríbenos desde el <a href="<?= e(url('contacto.php')) ?>">formulario de contacto</a><?php if ($c = config('empresa.correo')): ?> o al correo <a href="mailto:<?= e($c) ?>"><?= e($c) ?></a><?php endif; ?>.</p>

    <div class="mt-4 d-flex flex-wrap gap-2">
        <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left-circle" aria-hidden="true"></i> Volver al inicio</a>
        <a href="<?= e(url('contacto.php')) ?>" class="btn btn-cs">Ir a contacto</a>
    </div>
</article>
<?php layout_fin(); ?>
