<?php
/** Añadir productos y gestionar categorías (crear / editar / eliminar). */
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');

$db = mongo();
$colProductos  = $db->selectCollection('productos');
$colCategorias = $db->selectCollection('categorias');

$errores = [];
$tab = ($_GET['tab'] ?? '') === 'categorias' ? 'categorias' : 'productos';
$datos = ['nombre' => '', 'descripcion' => '', 'precio' => '', 'stock' => '', 'categoria' => ''];

// Compatibilidad con mensajes enviados por URL desde versiones anteriores
if (!empty($_GET['mensaje'])) {
    flash('info', mb_substr((string)$_GET['mensaje'], 0, 200));
    redirigir('agregar_producto.php');
}

if (es_post()) {
    csrf_verificar();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'agregar_categoria') {
        $tab = 'categorias';
        $nombreCat = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre_categoria'] ?? '')));
        $descCat = trim((string)($_POST['descripcion_categoria'] ?? ''));
        if (mb_strlen($nombreCat) < 2 || mb_strlen($nombreCat) > 60) {
            $errores['nombre_categoria'] = 'El nombre de la categoría debe tener entre 2 y 60 caracteres.';
        } elseif (mb_strlen($descCat) > 300) {
            $errores['descripcion_categoria'] = 'La descripción puede tener máximo 300 caracteres.';
        } elseif ($colCategorias->findOne(['nombre' => $nombreCat], ['collation' => ['locale' => 'es', 'strength' => 2]])) {
            $errores['nombre_categoria'] = 'Ya existe una categoría con ese nombre.';
        } else {
            $doc = ['nombre' => $nombreCat, 'createdAt' => nowUTC()];
            if ($descCat !== '') {
                $doc['descripcion'] = $descCat;
            }
            $colCategorias->insertOne($doc);
            flash('success', 'Categoría «' . $nombreCat . '» agregada correctamente.');
            redirigir('agregar_producto.php', ['tab' => 'categorias']);
        }
    }

    if ($accion === 'agregar_producto') {
        foreach ($datos as $k => $_) {
            $datos[$k] = trim((string)($_POST[$k] ?? ''));
        }
        $precio = str_replace(',', '.', $datos['precio']);
        if (mb_strlen($datos['nombre']) < 2 || mb_strlen($datos['nombre']) > 120) {
            $errores['nombre'] = 'El nombre es obligatorio (2 a 120 caracteres).';
        }
        if (mb_strlen($datos['descripcion']) < 5 || mb_strlen($datos['descripcion']) > 2000) {
            $errores['descripcion'] = 'La descripción es obligatoria (5 a 2000 caracteres).';
        }
        if (!is_numeric($precio) || (float)$precio <= 0 || (float)$precio > 1000000000) {
            $errores['precio'] = 'El precio debe ser un número mayor que 0.';
        }
        if (!ctype_digit($datos['stock']) || (int)$datos['stock'] > 1000000) {
            $errores['stock'] = 'El stock debe ser un número entero igual o mayor que 0.';
        }
        $cat = oid($datos['categoria']);
        $catDoc = $cat ? $colCategorias->findOne(['_id' => $cat]) : null;
        if (!$catDoc) {
            $errores['categoria'] = 'Selecciona una categoría válida.';
        }
        if (!$errores) {
            $img = guardar_imagen_subida($_FILES['imagen'] ?? []);
            if (!$img['ok']) {
                $errores['imagen'] = $img['error'];
            }
        }
        if (!$errores) {
            $doc = [
                'nombre'         => $datos['nombre'],
                'descripcion'    => $datos['descripcion'],
                'precio'         => round((float)$precio, 2),
                'stock'          => (int)$datos['stock'],
                'categoria_id'   => $cat,
                'imagen'         => $img['ruta'],
                'createdAt'      => nowUTC(),
                'actualizado_en' => nowUTC(),
            ];
            $doc['_id'] = $colProductos->insertOne($doc)->getInsertedId();
            registrar_historial('agrego', $doc, [
                'Producto agregado',
                'Precio inicial: ' . dinero($doc['precio']),
                'Stock inicial: ' . $doc['stock'],
                'Categoría: ' . ($catDoc['nombre'] ?? 'Desconocida'),
                'Imagen asignada',
            ], null, (string)($catDoc['nombre'] ?? ''));
            flash('success', 'Producto «' . $doc['nombre'] . '» agregado correctamente.');
            redirigir('agregar_producto.php');
        }
    }
}

$categorias = iterator_to_array($colCategorias->find([], ['sort' => ['nombre' => 1]]), false);
$conteo = [];
foreach ($colProductos->aggregate([['$group' => ['_id' => '$categoria_id', 'n' => ['$sum' => 1]]]]) as $c) {
    $conteo[(string)$c['_id']] = (int)$c['n'];
}
$recientes = iterator_to_array($colProductos->find([], ['sort' => ['_id' => -1], 'limit' => 8]), false);
$mapaCat = [];
foreach ($categorias as $c) {
    $mapaCat[(string)$c['_id']] = (string)$c['nombre'];
}

admin_inicio('Productos y categorías', 'agregar');
$inv = fn($c) => isset($errores[$c]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-' . $c : '';
$err = fn($c) => isset($errores[$c]) ? '<div class="invalid-feedback d-block" id="err-' . $c . '">' . e($errores[$c]) . '</div>' : '';
?>
<?php if ($errores): ?>
<div class="alert alert-danger" role="alert"><i class="bi bi-exclamation-octagon" aria-hidden="true"></i> No se guardaron los cambios. Revisa los campos marcados.</div>
<?php endif; ?>

<ul class="nav nav-tabs admin-tabs mb-4" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $tab === 'productos' ? 'active' : '' ?>" id="productos-tab" data-bs-toggle="tab" data-bs-target="#productos" type="button" role="tab" aria-controls="productos" aria-selected="<?= $tab === 'productos' ? 'true' : 'false' ?>"><i class="bi bi-cart" aria-hidden="true"></i> Productos</button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $tab === 'categorias' ? 'active' : '' ?>" id="categorias-tab" data-bs-toggle="tab" data-bs-target="#categorias" type="button" role="tab" aria-controls="categorias" aria-selected="<?= $tab === 'categorias' ? 'true' : 'false' ?>"><i class="bi bi-tags" aria-hidden="true"></i> Categorías (<?= count($categorias) ?>)</button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade <?= $tab === 'productos' ? 'show active' : '' ?>" id="productos" role="tabpanel" aria-labelledby="productos-tab" tabindex="0">
        <div class="cs-panel mb-4">
            <h2 class="cs-panel-titulo"><i class="bi bi-plus-circle" aria-hidden="true"></i> Agregar producto</h2>
            <?php if (!$categorias): ?>
            <div class="alert alert-warning">Primero crea al menos una categoría en la pestaña «Categorías».</div>
            <?php endif; ?>
            <form method="post" enctype="multipart/form-data" data-validar novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="agregar_producto">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nombre">Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-control<?= $inv('nombre') ?>" required minlength="2" maxlength="120" value="<?= e($datos['nombre']) ?>">
                        <?= $err('nombre') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="precio">Precio (COP, IVA incluido)</label>
                        <input type="number" id="precio" name="precio" step="0.01" min="0.01" class="form-control<?= $inv('precio') ?>" required inputmode="decimal" value="<?= e($datos['precio']) ?>">
                        <?= $err('precio') ?>
                    </div>
                    <div class="col-6 col-md-3">
                        <label class="form-label" for="stock">Stock (unidades)</label>
                        <input type="number" id="stock" name="stock" min="0" step="1" class="form-control<?= $inv('stock') ?>" required inputmode="numeric" value="<?= e($datos['stock']) ?>">
                        <?= $err('stock') ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="descripcion">Descripción</label>
                        <textarea id="descripcion" name="descripcion" class="form-control<?= $inv('descripcion') ?>" rows="3" required minlength="5" maxlength="2000"><?= e($datos['descripcion']) ?></textarea>
                        <?= $err('descripcion') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="categoria">Categoría</label>
                        <select id="categoria" name="categoria" class="form-select<?= $inv('categoria') ?>" required data-mensaje="Selecciona una categoría.">
                            <option value="">Selecciona una categoría</option>
                            <?php foreach ($categorias as $cat): ?>
                            <option value="<?= e((string)$cat['_id']) ?>" <?= $datos['categoria'] === (string)$cat['_id'] ? 'selected' : '' ?>><?= e($cat['nombre']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('categoria') ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="imagen">Imagen</label>
                        <input type="file" id="imagen" name="imagen" class="form-control<?= $inv('imagen') ?>" accept="image/jpeg,image/png,image/webp,image/gif" required aria-describedby="ayudaImagen" data-mensaje="Selecciona una imagen del producto.">
                        <div id="ayudaImagen" class="form-text">JPG, PNG, WebP o GIF, máximo 8 MB. Se optimiza automáticamente.</div>
                        <?= $err('imagen') ?>
                    </div>
                </div>
                <button class="btn btn-cs mt-3" type="submit" data-cargando="Guardando producto…"><i class="bi bi-save" aria-hidden="true"></i> Guardar producto</button>
            </form>
        </div>

        <div class="cs-panel">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <h2 class="cs-panel-titulo mb-0">Agregados recientemente</h2>
                <a href="<?= e(url('ver_productos.php')) ?>" class="btn btn-sm btn-outline-cs">Ver todos los productos</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle tabla-apilable mb-0">
                    <thead><tr><th scope="col">Imagen</th><th scope="col">Nombre</th><th scope="col">Precio</th><th scope="col">Stock</th><th scope="col">Categoría</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($recientes as $p): ?>
                        <tr>
                            <td data-label="Imagen"><?= imagen_html((string)($p['imagen'] ?? ''), '', ['class' => 'tabla-img', 'width' => 56, 'height' => 56]) ?></td>
                            <td data-label="Nombre"><?= e($p['nombre'] ?? '') ?></td>
                            <td data-label="Precio"><?= e(dinero($p['precio'] ?? 0)) ?></td>
                            <td data-label="Stock"><?= (int)($p['stock'] ?? 0) ?></td>
                            <td data-label="Categoría"><?= e($mapaCat[(string)($p['categoria_id'] ?? '')] ?? 'Sin categoría') ?></td>
                            <td data-label="Acciones" class="celda-acciones"><a href="<?= e(url('editar_producto.php', ['id' => (string)$p['_id']])) ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade <?= $tab === 'categorias' ? 'show active' : '' ?>" id="categorias" role="tabpanel" aria-labelledby="categorias-tab" tabindex="0">
        <div class="cs-panel mb-4">
            <h2 class="cs-panel-titulo"><i class="bi bi-plus-circle" aria-hidden="true"></i> Agregar categoría</h2>
            <form method="post" action="<?= e(url('agregar_producto.php', ['tab' => 'categorias'])) ?>" data-validar novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="agregar_categoria">
                <div class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label" for="nombre_categoria">Nombre de la categoría</label>
                        <input type="text" id="nombre_categoria" name="nombre_categoria" class="form-control<?= $inv('nombre_categoria') ?>" required minlength="2" maxlength="60" value="<?= e($_POST['nombre_categoria'] ?? '') ?>">
                        <?= $err('nombre_categoria') ?>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label" for="descripcion_categoria">Descripción <span class="fw-normal text-secondary">(opcional, se muestra en el catálogo)</span></label>
                        <input type="text" id="descripcion_categoria" name="descripcion_categoria" class="form-control<?= $inv('descripcion_categoria') ?>" maxlength="300" value="<?= e($_POST['descripcion_categoria'] ?? '') ?>">
                        <?= $err('descripcion_categoria') ?>
                    </div>
                </div>
                <button class="btn btn-success mt-3" type="submit" data-cargando="Agregando…"><i class="bi bi-tag" aria-hidden="true"></i> Agregar categoría</button>
            </form>
        </div>
        <div class="cs-panel">
            <h2 class="cs-panel-titulo">Categorías registradas</h2>
            <div class="table-responsive">
                <table class="table table-hover align-middle tabla-apilable mb-0">
                    <thead><tr><th scope="col">Nombre</th><th scope="col">Descripción</th><th scope="col">Productos</th><th scope="col">Acciones</th></tr></thead>
                    <tbody>
                    <?php foreach ($categorias as $cat): $n = $conteo[(string)$cat['_id']] ?? 0; ?>
                        <tr>
                            <td data-label="Nombre" class="fw-semibold"><?= e($cat['nombre']) ?></td>
                            <td data-label="Descripción" class="small text-secondary"><?= e(resumen((string)($cat['descripcion'] ?? ''), 80)) ?: '—' ?></td>
                            <td data-label="Productos"><a href="<?= e(url('ver_productos.php', ['categoria' => (string)$cat['_id']])) ?>"><?= (int)$n ?></a></td>
                            <td data-label="Acciones" class="celda-acciones">
                                <div class="d-flex flex-wrap gap-1 justify-content-end justify-content-md-start">
                                    <a href="<?= e(url('categoria_editar.php', ['id' => (string)$cat['_id']])) ?>" class="btn btn-warning btn-sm"><i class="bi bi-pencil" aria-hidden="true"></i> Editar</a>
                                    <form method="post" action="<?= e(url('categoria_eliminar.php')) ?>" data-confirmar="¿Eliminar la categoría «<?= e($cat['nombre']) ?>»?">
                                        <?= csrf_campo() ?><input type="hidden" name="id" value="<?= e((string)$cat['_id']) ?>">
                                        <button type="submit" class="btn btn-danger btn-sm" <?= $n ? 'disabled title="No se puede eliminar: tiene productos asociados"' : '' ?>><i class="bi bi-trash" aria-hidden="true"></i> Eliminar</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$categorias): ?><tr><td colspan="4" class="text-center text-secondary">No hay categorías todavía.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php admin_fin(); ?>
