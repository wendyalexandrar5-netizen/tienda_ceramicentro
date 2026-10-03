<?php
/** Editar nombre y descripción de una categoría. */
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');

$col = mongo()->selectCollection('categorias');
$id = oid((string)($_GET['id'] ?? ''));
$categoria = $id ? $col->findOne(['_id' => $id]) : null;
if (!$categoria) {
    flash('error', 'La categoría no existe.');
    redirigir('agregar_producto.php', ['tab' => 'categorias']);
}
$errores = [];
$nombre = (string)$categoria['nombre'];
$descripcion = (string)($categoria['descripcion'] ?? '');

if (es_post()) {
    csrf_verificar();
    $nombre = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
    $descripcion = trim((string)($_POST['descripcion'] ?? ''));
    if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 60) {
        $errores['nombre'] = 'El nombre debe tener entre 2 y 60 caracteres.';
    } elseif (mb_strlen($descripcion) > 300) {
        $errores['descripcion'] = 'La descripción puede tener máximo 300 caracteres.';
    } else {
        $otra = $col->findOne(['nombre' => $nombre, '_id' => ['$ne' => $id]], ['collation' => ['locale' => 'es', 'strength' => 2]]);
        if ($otra) {
            $errores['nombre'] = 'Ya existe otra categoría con ese nombre.';
        } else {
            $col->updateOne(['_id' => $id], ['$set' => ['nombre' => $nombre, 'descripcion' => $descripcion, 'actualizado_en' => nowUTC()]]);
            flash('success', 'Categoría actualizada correctamente.');
            redirigir('agregar_producto.php', ['tab' => 'categorias']);
        }
    }
}

admin_inicio('Editar categoría', 'agregar');
?>
<form method="post" class="cs-panel" style="max-width:640px" data-validar novalidate>
    <?= csrf_campo() ?>
    <div class="mb-3">
        <label class="form-label" for="nombre">Nombre</label>
        <input type="text" id="nombre" name="nombre" class="form-control<?= isset($errores['nombre']) ? ' is-invalid' : '' ?>" required minlength="2" maxlength="60" value="<?= e($nombre) ?>">
        <?php if (isset($errores['nombre'])): ?><div class="invalid-feedback"><?= e($errores['nombre']) ?></div><?php endif; ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="descripcion">Descripción <span class="fw-normal text-secondary">(opcional)</span></label>
        <textarea id="descripcion" name="descripcion" class="form-control<?= isset($errores['descripcion']) ? ' is-invalid' : '' ?>" rows="3" maxlength="300"><?= e($descripcion) ?></textarea>
        <?php if (isset($errores['descripcion'])): ?><div class="invalid-feedback"><?= e($errores['descripcion']) ?></div><?php endif; ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button class="btn btn-cs" type="submit" data-cargando="Guardando…">Guardar cambios</button>
        <a href="<?= e(url('agregar_producto.php', ['tab' => 'categorias'])) ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
</form>
<?php admin_fin(); ?>
