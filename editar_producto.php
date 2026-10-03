<?php
/** Editar un producto (registra cada cambio en el historial). */
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');

$db = mongo();
$colProductos  = $db->selectCollection('productos');
$colCategorias = $db->selectCollection('categorias');

$producto = producto_por_id((string)($_GET['id'] ?? ''));
if (!$producto) {
    flash('error', 'El producto no existe o ya fue eliminado.');
    redirigir('ver_productos.php');
}
$id = $producto['_id'];
$categorias = iterator_to_array($colCategorias->find([], ['sort' => ['nombre' => 1]]), false);
$mapaCat = [];
foreach ($categorias as $c) {
    $mapaCat[(string)$c['_id']] = (string)$c['nombre'];
}
$errores = [];
$v = [
    'nombre' => (string)($producto['nombre'] ?? ''), 'descripcion' => (string)($producto['descripcion'] ?? ''),
    'precio' => (string)($producto['precio'] ?? ''), 'stock' => (string)($producto['stock'] ?? '0'),
    'categoria' => isset($producto['categoria_id']) ? (string)$producto['categoria_id'] : '',
];

if (es_post()) {
    csrf_verificar();
    foreach ($v as $k => $_) {
        $v[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $precio = str_replace(',', '.', $v['precio']);
    if (mb_strlen($v['nombre']) < 2 || mb_strlen($v['nombre']) > 120) {
        $errores['nombre'] = 'El nombre es obligatorio (2 a 120 caracteres).';
    }
    if (mb_strlen($v['descripcion']) < 5 || mb_strlen($v['descripcion']) > 2000) {
        $errores['descripcion'] = 'La descripción es obligatoria (5 a 2000 caracteres).';
    }
    if (!is_numeric($precio) || (float)$precio <= 0) {
        $errores['precio'] = 'El precio debe ser un número mayor que 0.';
    }
    if (!ctype_digit($v['stock'])) {
        $errores['stock'] = 'El stock debe ser un número entero igual o mayor que 0.';
    }
    if (!isset($mapaCat[$v['categoria']])) {
        $errores['categoria'] = 'Selecciona una categoría válida.';
    }
    $imagen = (string)($producto['imagen'] ?? '');
    if (!$errores && !empty($_FILES['imagen']['name'])) {
        $img = guardar_imagen_subida($_FILES['imagen']);
        if ($img['ok']) {
            $imagen = $img['ruta'];
        } else {
            $errores['imagen'] = $img['error'];
        }
    }

    if (!$errores) {
        $cambios = [];
        $catAnt = $mapaCat[(string)($producto['categoria_id'] ?? '')] ?? 'Sin categoría';
        $catNueva = $mapaCat[$v['categoria']];
        if (($producto['nombre'] ?? '') !== $v['nombre']) {
            $cambios[] = 'Nombre: ' . ($producto['nombre'] ?? '') . ' → ' . $v['nombre'];
        }
        if ((string)($producto['descripcion'] ?? '') !== $v['descripcion']) {
            $cambios[] = 'Descripción actualizada';
        }
        if (abs((float)($producto['precio'] ?? 0) - (float)$precio) > 0.004) {
            $cambios[] = 'Precio: ' . dinero($producto['precio'] ?? 0) . ' → ' . dinero($precio);
        }
        if ((int)($producto['stock'] ?? 0) !== (int)$v['stock']) {
            $cambios[] = 'Stock: ' . (int)($producto['stock'] ?? 0) . ' → ' . (int)$v['stock'];
        }
        if ((string)($producto['categoria_id'] ?? '') !== $v['categoria']) {
            $cambios[] = 'Categoría: ' . $catAnt . ' → ' . $catNueva;
        }
        if ((string)($producto['imagen'] ?? '') !== $imagen) {
            $cambios[] = 'Imagen actualizada';
        }

        if (!$cambios) {
            flash('info', 'No se detectaron cambios.');
            redirigir('editar_producto.php', ['id' => (string)$id]);
        }
        $colProductos->updateOne(['_id' => $id], ['$set' => [
            'nombre' => $v['nombre'], 'descripcion' => $v['descripcion'], 'precio' => round((float)$precio, 2),
            'stock' => (int)$v['stock'], 'categoria_id' => oid($v['categoria']), 'imagen' => $imagen, 'actualizado_en' => nowUTC(),
        ]]);
        registrar_historial('edito', ['_id' => $id, 'nombre' => $v['nombre']], $cambios,
            $catAnt !== $catNueva ? $catAnt : null, $catAnt !== $catNueva ? $catNueva : null);
        flash('success', 'Producto «' . $v['nombre'] . '» actualizado (' . count($cambios) . ' cambio' . (count($cambios) === 1 ? '' : 's') . ').');
        redirigir('ver_productos.php');
    }
}

admin_inicio('Editar producto', 'productos', ['acciones' => '<a class="btn btn-outline-secondary" href="' . e(url('producto.php', ['id' => (string)$id])) . '" target="_blank" rel="noopener"><i class="bi bi-eye" aria-hidden="true"></i> Ver en la tienda</a>']);
$inv = fn($c) => isset($errores[$c]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-' . $c : '';
$err = fn($c) => isset($errores[$c]) ? '<div class="invalid-feedback d-block" id="err-' . $c . '">' . e($errores[$c]) . '</div>' : '';
?>
<?php if ($errores): ?><div class="alert alert-danger" role="alert">No se guardaron los cambios. Revisa los campos marcados.</div><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-8">
        <form method="post" enctype="multipart/form-data" class="cs-panel" data-validar novalidate>
            <?= csrf_campo() ?>
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label" for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" class="form-control<?= $inv('nombre') ?>" required minlength="2" maxlength="120" value="<?= e($v['nombre']) ?>">
                    <?= $err('nombre') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" class="form-control<?= $inv('descripcion') ?>" rows="4" required minlength="5" maxlength="2000"><?= e($v['descripcion']) ?></textarea>
                    <?= $err('descripcion') ?>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label" for="precio">Precio (COP)</label>
                    <input type="number" step="0.01" min="0.01" id="precio" name="precio" class="form-control<?= $inv('precio') ?>" required value="<?= e($v['precio']) ?>">
                    <?= $err('precio') ?>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label" for="stock">Stock</label>
                    <input type="number" min="0" step="1" id="stock" name="stock" class="form-control<?= $inv('stock') ?>" required value="<?= e($v['stock']) ?>">
                    <?= $err('stock') ?>
                </div>
                <div class="col-md-5">
                    <label class="form-label" for="categoria">Categoría</label>
                    <select id="categoria" name="categoria" class="form-select<?= $inv('categoria') ?>" required>
                        <?php if (!isset($mapaCat[$v['categoria']])): ?><option value="">Selecciona una categoría</option><?php endif; ?>
                        <?php foreach ($categorias as $cat): ?>
                        <option value="<?= e((string)$cat['_id']) ?>" <?= $v['categoria'] === (string)$cat['_id'] ? 'selected' : '' ?>><?= e($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('categoria') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="imagen">Reemplazar imagen <span class="fw-normal text-secondary">(opcional)</span></label>
                    <input type="file" id="imagen" name="imagen" class="form-control<?= $inv('imagen') ?>" accept="image/jpeg,image/png,image/webp,image/gif">
                    <?= $err('imagen') ?>
                </div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-4">
                <button type="submit" class="btn btn-cs" data-cargando="Guardando…"><i class="bi bi-save" aria-hidden="true"></i> Guardar cambios</button>
                <a href="<?= e(url('ver_productos.php')) ?>" class="btn btn-outline-secondary">Cancelar</a>
            </div>
        </form>
    </div>
    <div class="col-lg-4">
        <div class="cs-panel">
            <p class="form-label">Imagen actual</p>
            <div class="producto-detalle-img mb-3"><?= imagen_html((string)($producto['imagen'] ?? ''), 'Imagen actual de ' . ($producto['nombre'] ?? 'producto'), ['width' => 400, 'height' => 400]) ?></div>
            <form method="post" action="<?= e(url('eliminar_producto.php')) ?>" data-confirmar="¿Eliminar definitivamente «<?= e($producto['nombre'] ?? '') ?>»? Los pedidos anteriores conservarán su información.">
                <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e((string)$id) ?>">
                <button type="submit" class="btn btn-outline-danger w-100"><i class="bi bi-trash" aria-hidden="true"></i> Eliminar producto</button>
            </form>
        </div>
    </div>
</div>
<?php admin_fin(); ?>
