<?php
/** Elimina un producto (solo por POST con token CSRF) y registra el historial. */
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');
if (!es_post()) {
    flash('info', 'Para eliminar un producto usa el botón «Eliminar» del listado.');
    redirigir('ver_productos.php');
}
csrf_verificar();

$producto = producto_por_id((string)($_POST['id'] ?? ''));
if (!$producto) {
    flash('error', 'El producto no existe o ya fue eliminado.');
    redirigir('ver_productos.php');
}
$catNombre = 'Sin categoría';
if (!empty($producto['categoria_id'])) {
    $cat = mongo()->selectCollection('categorias')->findOne(['_id' => $producto['categoria_id']]);
    $catNombre = (string)($cat['nombre'] ?? 'Sin categoría');
}

$res = mongo()->selectCollection('productos')->deleteOne(['_id' => $producto['_id']]);
if ($res->getDeletedCount() !== 1) {
    flash('error', 'No se pudo eliminar el producto. Intenta de nuevo.');
    redirigir('ver_productos.php');
}

// Se conserva el archivo de imagen si algún pedido lo referencia (comprobantes / historial)
$img = imagen_ruta_segura((string)($producto['imagen'] ?? ''));
$enUso = $img !== '' && mongo()->selectCollection('pedido_detalle')->countDocuments(['imagen' => $img], ['limit' => 1]) > 0;
if ($img !== '' && !$enUso && strpos($img, 'imagenes/') === 0 && is_file(CS_ROOT . '/' . $img)) {
    @unlink(CS_ROOT . '/' . $img);
}

registrar_historial('elimino', $producto, [
    'Producto eliminado',
    'Precio: ' . dinero($producto['precio'] ?? 0),
    'Stock: ' . (int)($producto['stock'] ?? 0),
    'Categoría: ' . $catNombre,
], $catNombre, null);

flash('success', 'Producto «' . ($producto['nombre'] ?? '') . '» eliminado correctamente.');
redirigir('ver_productos.php');
