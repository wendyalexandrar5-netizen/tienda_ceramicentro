<?php
/** Catálogo público de productos (antes productos.html, ahora con datos reales de MongoDB). */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/filtros_catalogo.php';

$filtros  = filtros_catalogo();
$catalogo = buscar_con_filtros($filtros);
$categoria = $filtros['categoria'] !== '' ? categoria_por_id($filtros['categoria']) : null;
$rutaCatalogo = 'productos.php';

if ($categoria) {
    $titulo = $categoria['nombre'];
    $descripcion = $categoria['descripcion'] !== ''
        ? $categoria['descripcion']
        : 'Encuentra ' . $categoria['nombre'] . ' en CERAMICENTRO. Revisa precios, disponibilidad y compra en línea en CERAMISHOP.';
} else {
    $titulo = 'Catálogo de productos';
    $descripcion = 'Catálogo de CERAMICENTRO: cerámica, baldosas, enchapes, pisos, techos PVC, baños, lavamanos, regaderas, estucos y más. Precios y disponibilidad en línea.';
}

$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Productos', 'url' => 'productos.php']];
if ($categoria) {
    $migas[] = ['nombre' => $categoria['nombre']];
}
$canonicalParams = array_filter(['categoria' => $filtros['categoria'], 'pagina' => $catalogo['pagina'] > 1 ? $catalogo['pagina'] : null]);
$canonical = 'productos.php' . ($canonicalParams ? '?' . http_build_query($canonicalParams) : '');

$lista = [];
foreach ($catalogo['items'] as $i => $p) {
    $lista[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => url_absoluta('producto.php', ['id' => $p['id']]), 'name' => $p['nombre']];
}

$eventos = [['view_item_list', ['item_list_name' => $titulo]]];
if ($filtros['q'] !== '') {
    $eventos[] = ['search', ['search_term' => $filtros['q']]];
}

layout_inicio([
    'titulo'      => $titulo . ($catalogo['pagina'] > 1 ? ' – página ' . $catalogo['pagina'] : ''),
    'descripcion' => $descripcion,
    'canonical'   => $canonical,
    // Los resultados de búsqueda y combinaciones de filtros no se indexan (evita contenido duplicado)
    'noindex'     => $filtros['q'] !== '' || $filtros['disponibles'] || $filtros['orden'] !== 'nombre',
    'activo'      => 'productos',
    'migas'       => $migas,
    'og_imagen'   => !empty($catalogo['items'][0]['imagen']) ? imagen_url_absoluta($catalogo['items'][0]['imagen']) : null,
    'jsonld'      => [jsonld_migas($migas), $lista ? ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $lista] : null],
]);
?>
<section class="container py-4">
    <header class="mb-4">
        <h1 class="mb-1"><?= e($categoria ? $categoria['nombre'] : 'Nuestros productos') ?></h1>
        <p class="text-secondary mb-0"><?= e($categoria && $categoria['descripcion'] !== '' ? $categoria['descripcion'] : 'Materiales de calidad para tus espacios: precios y existencias actualizados.') ?></p>
        <?php if (!usuario_actual()): ?>
        <p class="small mt-2 mb-0"><i class="bi bi-info-circle" aria-hidden="true"></i> Para comprar, <a href="<?= e(url('login.php')) ?>">inicia sesión</a> o <a href="<?= e(url('registro.php')) ?>">crea tu cuenta gratis</a>.</p>
        <?php endif; ?>
    </header>
    <?php require __DIR__ . '/includes/catalogo.php'; ?>
</section>
<?php layout_fin(['eventos' => $eventos]); ?>
