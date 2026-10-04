<?php
/** Política de cookies: describe las cookies que el sitio realmente usa. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagina_legal.php';
$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Política de cookies']];
layout_inicio([
    'titulo'      => 'Política de cookies',
    'descripcion' => 'Qué cookies usa CERAMISHOP, para qué sirven y cómo puedes configurarlas.',
    'canonical'   => 'politica_cookies.php',
    'migas'       => $migas,
    'jsonld'      => [jsonld_migas($migas)],
]);
?>
<article class="container contenido-legal py-4">
    <h1>Política de cookies</h1>
    <p><strong>Última actualización:</strong> <?= e(fecha_vigencia_legal()) ?></p>
    <?php aviso_academico(); ?>
    <p>Las cookies son pequeños archivos que el navegador guarda para recordar información entre páginas.</p>

    <h2>Cookies necesarias (siempre activas)</h2>
    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
        <table class="table table-sm bg-white">
            <thead><tr><th scope="col">Nombre</th><th scope="col">Para qué sirve</th><th scope="col">Duración</th></tr></thead>
            <tbody>
                <tr><td><code>PHPSESSID</code></td><td>Mantener tu sesión iniciada y tu carrito de compras.</td><td>Hasta cerrar el navegador</td></tr>
                <tr><td><code>cs_consentimiento</code></td><td>Recordar tu elección sobre las cookies.</td><td>6 meses</td></tr>
            </tbody>
        </table>
    </div>

    <h2>Cookies de analítica (opcionales)</h2>
    <p>Solo si las aceptas y si la tienda tiene la analítica activada, usamos Google Analytics para conocer de forma estadística qué páginas y productos se visitan. No enviamos tu nombre, correo ni datos de pago.</p>
    <p>
        <button type="button" class="btn btn-outline-cs" data-cookies-configurar>Cambiar mis preferencias de cookies</button>
    </p>
    <h2>Cómo desactivarlas</h2>
    <p>Puedes cambiar tu elección en cualquier momento con el botón de arriba o desde «Configurar cookies» en el pie de página. También puedes borrar las cookies desde la configuración de tu navegador; si borras la cookie de sesión, tendrás que volver a iniciar sesión.</p>
    <h2>Otros servicios</h2>
    <p>La página de contacto muestra un mapa de Google Maps, que puede usar sus propias cookies según la <a href="https://policies.google.com/privacy?hl=es" target="_blank" rel="noopener">política de privacidad de Google</a>. El botón de WhatsApp solo abre el chat cuando lo pulsas.</p>
</article>
<?php layout_fin(); ?>
