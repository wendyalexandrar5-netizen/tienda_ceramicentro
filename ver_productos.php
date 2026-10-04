<?php
/** Gestión de productos: búsqueda, filtros, orden, paginación, edición y eliminación. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/filtros_admin_productos.php';
requerir_sesion('administrador');

[$f, $filtro, $sort] = filtros_admin_productos();
$col = mongo()->selectCollection('productos');
$porPagina = 25;
$total = $col->countDocuments($filtro);
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
$productos = iterator_to_array($col->find($filtro, ['sort' => $sort, 'skip' => ($pagina - 1) * $porPagina, 'limit' => $porPagina]), false);
$categorias = categorias_todas();
$mapa = categorias_mapa();
$params = array_filter($f, fn($v) => $v !== '' && $v !== 'nombre');

admin_inicio('Gestión de productos', 'productos', ['acciones' =>
    '<a href="' . e(url('agregar_producto.php')) . '" class="btn btn-cs"><i class="bi bi-plus-circle" aria-hidden="true"></i> Añadir producto</a>'
    . boton_exportar('exportar_productos.php', $params)]);
?>
<form method="get" class="cs-panel mb-4" role="search" data-sin-bloqueo>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small" for="buscar">Buscar por nombre</label>
            <input type="search" id="buscar" name="buscar" class="form-control" value="<?= e($f['buscar']) ?>" placeholder="Ej. baldosa">
        </div>
        <div class="col-6 col-md-3">
            <label class="form-label small" for="categoria">Categoría</label>
            <select id="categoria" name="categoria" class="form-select">
                <option value="">Todas</option>
                <?php foreach ($categorias as $c): ?><option value="<?= e($c['id']) ?>" <?= $f['categoria'] === $c['id'] ? 'selected' : '' ?>><?= e($c['nombre']) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="stock">Inventario</label>
            <select id="stock" name="stock" class="form-select">
                <option value="">Todos</option>
                <option value="disponible" <?= $f['stock'] === 'disponible' ? 'selected' : '' ?>>Con existencias</option>
                <option value="bajo" <?= $f['stock'] === 'bajo' ? 'selected' : '' ?>>Stock bajo (1-5)</option>
                <option value="agotado" <?= $f['stock'] === 'agotado' ? 'selected' : '' ?>>Agotados</option>
            </select>
        </div>
        <div class="col-6 col-md-2">
            <label class="form-label small" for="orden">Ordenar</label>
            <select id="orden" name="orden" class="form-select">
                <?php foreach (['nombre' => 'Nombre', 'precio_asc' => 'Precio ↑', 'precio_desc' => 'Precio ↓', 'stock_asc' => 'Stock ↑', 'stock_desc' => 'Stock ↓', 'recientes' => 'Recientes'] as $k => $t): ?>
                <option value="<?= e($k) ?>" <?= $f['orden'] === $k ? 'selected' : '' ?>><?= e($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-6 col-md-1 d-grid"><button class="btn btn-cs" type="submit" aria-label="Aplicar filtros"><i class="bi bi-search" aria-hidden="true"></i></button></div>
    </div>
    <?php if ($params): ?><a href="<?= e(url('ver_productos.php')) ?>" class="small d-inline-block mt-2"><i class="bi bi-x-circle" aria-hidden="true"></i> Limpiar filtros</a><?php endif; ?>
</form>

<p class="text-secondary small" role="status"><?= (int)$total ?> producto(s) encontrados<?= $paginas > 1 ? ' · página ' . $pagina . ' de ' . $paginas : '' ?></p>

<div class="cs-panel p-0 p-md-3">
    <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
        <table class="table table-hover align-middle tabla-apilable mb-0">
            <thead><tr><th scope="col">Imagen</th><th scope="col">Nombre</th><th scope="col">Categoría</th><th scope="col" class="text-end">Precio</th><th scope="col" class="text-end">Stock</th><th scope="col">Acciones</th></tr></thead>
            <tbody>
            <?php foreach ($productos as $p): $s = (int)($p['stock'] ?? 0); ?>
                <tr>
                    <td data-label="Imagen"><?= imagen_html((string)($p['imagen'] ?? ''), '', ['class' => 'tabla-img', 'width' => 56, 'height' => 56]) ?></td>
                    <td data-label="Nombre" class="fw-semibold"><?= e($p['nombre'] ?? '') ?></td>
                    <td data-label="Categoría"><?= e($mapa[(string)($p['categoria_id'] ?? '')] ?? 'Sin categoría') ?></td>
                    <td data-label="Precio" class="text-md-end"><?= e(dinero($p['precio'] ?? 0)) ?></td>
                    <td data-label="Stock" class="text-md-end <?= $s <= 0 ? 'stock-cero' : ($s <= 5 ? 'stock-bajo' : '') ?>"><?= $s <= 0 ? 'Agotado' : $s ?></td>
                    <td data-label="Acciones" class="celda-acciones">
                        <div class="d-flex flex-wrap gap-1 justify-content-end justify-content-md-start">
                            <a href="<?= e(url('editar_producto.php', ['id' => (string)$p['_id']])) ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
                            <form method="post" action="<?= e(url('eliminar_producto.php')) ?>" data-confirmar="¿Eliminar «<?= e($p['nombre'] ?? '') ?>»? Esta acción no se puede deshacer.">
                                <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e((string)$p['_id']) ?>">
                                <button type="submit" class="btn btn-danger btn-sm"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$productos): ?><tr><td colspan="6" class="text-center text-secondary py-4">No hay productos con estos filtros.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php paginacion_html($pagina, $paginas, 'ver_productos.php', $params); ?>
<?php admin_fin(); ?>
