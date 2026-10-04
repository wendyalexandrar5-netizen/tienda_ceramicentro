<?php
/**
 * Listado de productos con búsqueda, filtro por categoría, orden y paginación.
 * Lo usan productos.php (público) y tienda.php (clientes).
 * Requiere: $rutaCatalogo (string) y $catalogo (resultado de productos_buscar) y $filtros.
 */
$categorias = categorias_todas();
$hayFiltros = $filtros['q'] !== '' || $filtros['categoria'] !== '' || $filtros['orden'] !== 'nombre' || $filtros['disponibles'];
?>
<form method="get" action="<?= e(url($rutaCatalogo)) ?>" class="filtros-catalogo mb-4" role="search" aria-label="Buscar y filtrar productos" data-sin-bloqueo>
    <div class="row g-2 align-items-end">
        <div class="col-12 col-lg-4">
            <label for="filtroQ" class="form-label small mb-1">Buscar</label>
            <div class="input-group">
                <span class="input-group-text bg-white"><i class="bi bi-search" aria-hidden="true"></i></span>
                <input type="search" id="filtroQ" name="q" class="form-control" maxlength="80" placeholder="Nombre o descripción" value="<?= e($filtros['q']) ?>">
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <label for="filtroCategoria" class="form-label small mb-1">Categoría</label>
            <select id="filtroCategoria" name="categoria" class="form-select" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                <option value="">Todas las categorías</option>
                <?php foreach ($categorias as $c): ?>
                <option value="<?= e($c['id']) ?>" <?= $filtros['categoria'] === $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-lg-2">
            <label for="filtroOrden" class="form-label small mb-1">Ordenar por</label>
            <select id="filtroOrden" name="orden" class="form-select" onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                <?php foreach (['nombre' => 'Nombre (A-Z)', 'precio_asc' => 'Menor precio', 'precio_desc' => 'Mayor precio', 'recientes' => 'Más recientes'] as $k => $t): ?>
                <option value="<?= e($k) ?>" <?= $filtros['orden'] === $k ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-12 col-sm-6 col-lg-2">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="filtroDisp" name="disponibles" value="1" <?= $filtros['disponibles'] ? 'checked' : '' ?> onchange="this.form.requestSubmit ? this.form.requestSubmit() : this.form.submit()">
                <label class="form-check-label small" for="filtroDisp">Solo con existencias</label>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-lg-1 d-grid">
            <button type="submit" class="btn btn-cs">Buscar</button>
        </div>
    </div>
</form>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <p class="mb-0 text-secondary" role="status">
        <?php if ($catalogo['total'] === 0): ?>
            No hay productos con estos criterios.
        <?php else: ?>
            <?= (int)$catalogo['total'] ?> producto<?= $catalogo['total'] === 1 ? '' : 's' ?>
            <?= $filtros['q'] !== '' ? 'para «' . e($filtros['q']) . '»' : '' ?>
            <?php if ($catalogo['paginas'] > 1): ?> · página <?= (int)$catalogo['pagina'] ?> de <?= (int)$catalogo['paginas'] ?><?php endif; ?>
        <?php endif; ?>
    </p>
    <?php if ($hayFiltros): ?>
    <a href="<?= e(url($rutaCatalogo)) ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-x-circle" aria-hidden="true"></i> Limpiar filtros</a>
    <?php endif; ?>
</div>

<?php if ($catalogo['items']): ?>
<div class="row g-4">
    <?php foreach ($catalogo['items'] as $p): ?>
    <div class="col-sm-6 col-lg-4 col-xl-3"><?php require __DIR__ . '/tarjeta_producto.php'; ?></div>
    <?php endforeach; ?>
</div>
<?php
paginacion_html($catalogo['pagina'], $catalogo['paginas'], $rutaCatalogo, [
    'q' => $filtros['q'], 'categoria' => $filtros['categoria'],
    'orden' => $filtros['orden'] !== 'nombre' ? $filtros['orden'] : '', 'disponibles' => $filtros['disponibles'] ? '1' : '',
]);
?>
<?php else: ?>
<div class="cs-panel text-center py-5">
    <i class="bi bi-search display-5 text-secondary" aria-hidden="true"></i>
    <h2 class="h5 mt-3">No encontramos productos</h2>
    <p class="text-secondary">Prueba con otra palabra, quita los filtros o escríbenos y te ayudamos a encontrarlo.</p>
    <div class="d-flex flex-wrap justify-content-center gap-2">
        <a href="<?= e(url($rutaCatalogo)) ?>" class="btn btn-cs">Ver todos los productos</a>
        <a href="<?= e(url('contacto.php')) ?>" class="btn btn-outline-cs">Contáctanos</a>
    </div>
</div>
<?php endif; ?>
