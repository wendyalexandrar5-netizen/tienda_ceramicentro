<?php
require __DIR__ . '/vendor/autoload.php';
include("verificar_acceso.php");
verificarSesion("administrador");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function isValidObjectId($id){
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id);
}

if (!isset($_GET["id"]) || !isValidObjectId($_GET["id"])) {
    header("Location: agregar_productos.php?mensaje=ID inválido");
    exit;
}

$id = new ObjectId($_GET["id"]);

$db = mongo();
$colProductos  = $db->selectCollection("productos");
$colCategorias = $db->selectCollection("categorias");
$colHistorial  = $db->selectCollection("historial_productos");

$producto = $colProductos->findOne(["_id" => $id]);

if (!$producto) {
    header("Location: agregar_productos.php?mensaje=Producto no encontrado");
    exit;
}

$categoriaNombre = "N/A";

if (isset($producto["categoria_id"]) && $producto["categoria_id"] instanceof ObjectId) {
    $cat = $colCategorias->findOne(["_id" => $producto["categoria_id"]]);
    if ($cat) {
        $categoriaNombre = $cat["nombre"] ?? "N/D";
    }
}

$res = $colProductos->deleteOne(["_id" => $id]);

if ($res->getDeletedCount() !== 1) {
    header("Location: agregar_productos.php?mensaje=Error al eliminar producto");
    exit;
}

if (!empty($producto["imagen"]) && file_exists($producto["imagen"])) {
    @unlink($producto["imagen"]);
}

$adminId = $_SESSION["usuario"]["id"] ?? null;
$adminOid = (isValidObjectId($adminId)) ? new ObjectId($adminId) : null;
$nombreAdmin = $_SESSION["usuario"]["nombre"] ?? "";

$productoNombre = $producto["nombre"] ?? "Producto sin nombre";

$cambios = [
    "Producto eliminado",
    "Precio: " . ($producto["precio"] ?? "N/D"),
    "Stock: " . ($producto["stock"] ?? "N/D"),
    "Categoría: " . $categoriaNombre
];

$colHistorial->insertOne([
    "id_admin"        => $adminOid,
    "nombre_admin"    => $nombreAdmin,
    "id_producto"     => $id,
    "producto_nombre" => $productoNombre,
    "accion"          => "elimino",
    "cambios"         => $cambios,
    "categoria_anterior" => $categoriaNombre,
    "categoria_nueva"    => null,
    "fecha"           => new UTCDateTime()
]);

header("Location: agregar_producto.php?mensaje=Producto eliminado correctamente");
exit;
