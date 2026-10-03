<?php
/** Elimina una categoría si no tiene productos asociados (POST con token CSRF). */
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');
if (!es_post()) {
    redirigir('agregar_producto.php', ['tab' => 'categorias']);
}
csrf_verificar();

$id = oid((string)($_POST['id'] ?? ''));
$col = mongo()->selectCollection('categorias');
$cat = $id ? $col->findOne(['_id' => $id]) : null;
if (!$cat) {
    flash('error', 'La categoría no existe.');
} elseif (mongo()->selectCollection('productos')->countDocuments(['categoria_id' => $id]) > 0) {
    flash('warning', 'No puedes eliminar «' . $cat['nombre'] . '» porque tiene productos asociados. Cámbialos de categoría primero.');
} else {
    $col->deleteOne(['_id' => $id]);
    flash('success', 'Categoría «' . $cat['nombre'] . '» eliminada correctamente.');
}
redirigir('agregar_producto.php', ['tab' => 'categorias']);
