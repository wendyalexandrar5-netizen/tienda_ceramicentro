<?php
require __DIR__ . '/vendor/autoload.php';
include("verificar_acceso.php");
verificarSesion("administrador");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

function isValidObjectId($id) {
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id);
}

if (!isset($_GET['id']) || !isValidObjectId($_GET['id'])) {
    header("Location: agregar_producto.php?mensaje=ID inválido de categoría");
    exit;
}

$id = new ObjectId($_GET['id']);

$db = mongo();
$colCategorias = $db->selectCollection("categorias");
$colProductos  = $db->selectCollection("productos");

$productosUsanEstaCategoria = $colProductos->countDocuments(["categoria_id" => $id]);

if ($productosUsanEstaCategoria > 0) {
    header("Location: agregar_producto.php?mensaje=No puedes eliminar esta categoría porque está asociada a productos");
    exit;
}

$colCategorias->deleteOne(["_id" => $id]);

header("Location: agregar_producto.php?mensaje=Categoría eliminada correctamente");
exit;
