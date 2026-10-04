<?php
/** Tienda para clientes con sesión iniciada: catálogo con compra directa. */
require_once __DIR__ . '/includes/pedidos.php';
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/filtros_catalogo.php';
requerir_sesion('cliente');
liberar_pedidos_vencidos(); // devuelve el inventario de pedidos sin pagar cuya reserva venció

$filtros  = filtros_catalogo();
$catalogo = buscar_con_filtros($filtros);
$rutaCatalogo = 'tienda.php';
$nombre = explode(' ', trim(usuario_actual()['nombre'] ?? ''))[0] ?: 'cliente';

$eventos = [['view_item_list', ['item_list_name' => 'Tienda']]];
if ($filtros['q'] !== '') {
    $eventos[] = ['search', ['search_term' => $filtros['q']]];
}
layout_inicio(['titulo' => 'Tienda', 'noindex' => true, 'activo' => 'tienda']);
?>
<section class="container py-4">
    <div class="cs-panel d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4" style="background:linear-gradient(135deg,#c62828,#b71c1c);color:#fff">
        <div>
            <h1 class="h3 mb-1">Hola, <?= e($nombre) ?> 👋</h1>
            <p class="mb-0 opacity-75">Elige tus productos, agrégalos al carrito y paga con PSE (simulado).</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= e(url('ver_carrito.php')) ?>" class="btn btn-light"><i class="bi bi-cart3" aria-hidden="true"></i> Ver carrito <span class="badge text-bg-danger" data-carrito-contador <?= carrito_contar() ? '' : 'hidden' ?>><?= carrito_contar() ?></span></a>
            <a href="<?= e(url('mis_pedidos.php')) ?>" class="btn btn-outline-light"><i class="bi bi-box-seam" aria-hidden="true"></i> Mis pedidos</a>
        </div>
    </div>
    <h2 class="h4 mb-3">Productos disponibles</h2>
    <?php require __DIR__ . '/includes/catalogo.php'; ?>
</section>
<?php layout_fin(['eventos' => $eventos]); ?>
