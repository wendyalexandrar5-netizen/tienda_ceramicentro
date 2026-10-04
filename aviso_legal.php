<?php
/** Aviso legal: titular del sitio, carácter académico, condiciones de uso y propiedad intelectual. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pagina_legal.php';
$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Aviso legal']];
layout_inicio([
    'titulo'      => 'Aviso legal',
    'descripcion' => 'Aviso legal de CERAMISHOP – CERAMICENTRO: responsable del sitio, carácter académico del proyecto, condiciones de uso y propiedad intelectual.',
    'canonical'   => 'aviso_legal.php',
    'migas'       => $migas,
    'jsonld'      => [jsonld_migas($migas)],
]);
$empresa = e(config('empresa.nombre', 'CERAMICENTRO'));
?>
<article class="container contenido-legal py-4">
    <h1>Aviso legal</h1>
    <p><strong>Última actualización:</strong> <?= e(fecha_vigencia_legal()) ?></p>
    <?php aviso_academico(); ?>

    <h2>1. Identificación</h2>
    <ul>
        <li><strong>Empresa caso de estudio:</strong> <?= $empresa ?></li>
        <li><strong>Nombre de la tienda:</strong> <?= e(config('empresa.tienda', 'CERAMISHOP')) ?> (sitio web y aplicación Android)</li>
        <?php if (config('academico.activo')): ?>
        <li><strong>Desarrollo:</strong> <?= e(config('academico.autores') ?: 'Equipo del proyecto académico') ?><?= config('academico.institucion') ? ' – ' . e(config('academico.institucion')) : '' ?></li>
        <?php endif; ?>
        <li><strong>Correo de contacto:</strong> <?= e(dato_empresa('correo', 'Disponible en el formulario de contacto')) ?></li>
        <li><strong>Dirección:</strong> <?= e(dato_empresa('direccion')) ?></li>
        <li><strong>Teléfono / WhatsApp:</strong> <?= e(dato_empresa('telefono', config('empresa.whatsapp') ? '+' . config('empresa.whatsapp') : 'No aplica')) ?></li>
    </ul>

    <h2>2. Objeto del sitio</h2>
    <p>El sitio permite conocer a <?= $empresa ?>, consultar su catálogo de productos y registrar pedidos en línea. <?php if (config('academico.activo')): ?>Al ser un proyecto académico universitario, su finalidad es formativa y demostrativa: los pedidos registrados no generan ventas, entregas ni cobros reales, y los precios e inventarios son de demostración.<?php endif; ?></p>

    <h2>3. Condiciones de uso</h2>
    <p>El uso del sitio implica la aceptación de los <a href="<?= e(url('tyc.php')) ?>">Términos y condiciones</a>. El usuario se compromete a hacer un uso adecuado de los contenidos y a no emplearlos para actividades ilícitas o que puedan dañar el sitio, sus datos o a otros usuarios.</p>

    <h2>4. Pagos</h2>
    <p>El módulo de pago PSE es un <strong>simulador</strong>: no está conectado con PSE, ACH Colombia ni con ninguna entidad financiera, no solicita datos bancarios y no realiza transacciones. Los nombres de bancos que aparecen en el simulador son ficticios.</p>

    <h2>5. Propiedad intelectual</h2>
    <p>La marca, el logotipo y las imágenes de productos pertenecen a <?= $empresa ?> o a sus proveedores, y se usan en este proyecto con fines académicos. El código fuente y el diseño del sitio son obra de los autores del proyecto. No se permite su reproducción con fines comerciales sin autorización.</p>

    <h2>6. Enlaces externos</h2>
    <p>El sitio puede incluir enlaces a servicios de terceros (por ejemplo, WhatsApp o Google Maps). No somos responsables de su contenido ni de sus políticas de privacidad.</p>

    <h2>7. Responsabilidad</h2>
    <p>Se procura que la información publicada sea correcta y que el sitio esté disponible, pero no se garantiza la ausencia de errores o interrupciones, especialmente por tratarse de un entorno académico de pruebas.</p>

    <h2>8. Legislación aplicable</h2>
    <p>Este aviso se rige por las leyes de la República de Colombia, en particular la Ley 527 de 1999 (comercio electrónico), la Ley 1480 de 2011 (Estatuto del Consumidor), la Ley 1581 de 2012 (protección de datos personales) y la Ley 23 de 1982 (derechos de autor).</p>
</article>
<?php layout_fin(); ?>
