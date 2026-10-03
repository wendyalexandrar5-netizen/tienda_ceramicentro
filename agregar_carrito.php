<?php
session_start();

require __DIR__ . "/vendor/autoload.php";
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: tienda.php");
    exit;
}

$producto_id = $_POST["producto_id"] ?? null;
$cantidad     = intval($_POST["cantidad"] ?? 1);

function isValidObjectId($id) {
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id);
}

if (!$producto_id || !isValidObjectId($producto_id)) {
    header("Location: tienda.php?error=ID inválido");
    exit;
}

$db  = mongo();
$col = $db->selectCollection("productos");

$producto = $col->findOne(
    ["_id" => new ObjectId($producto_id)],
    ["typeMap" => ["root" => "array", "document" => "array"]]
);

if (!$producto) {
    header("Location: tienda.php?error=Producto no encontrado");
    exit;
}

$stock = intval($producto["stock"] ?? 0);

if ($stock <= 0) {
    header("Location: tienda.php?error=Sin stock");
    exit;
}

if ($cantidad > $stock) {
    $cantidad = $stock;
}

if (!isset($_SESSION["carrito"])) {
    $_SESSION["carrito"] = [];
}

if (isset($_SESSION["carrito"][$producto_id])) {
    $_SESSION["carrito"][$producto_id]["cantidad"] += $cantidad;

    if ($_SESSION["carrito"][$producto_id]["cantidad"] > $stock) {
        $_SESSION["carrito"][$producto_id]["cantidad"] = $stock;
    }

} else {
    $_SESSION["carrito"][$producto_id] = [
        "nombre"   => $producto["nombre"],
        "precio"   => (float)$producto["precio"],
        "imagen"   => $producto["imagen"],
        "cantidad" => $cantidad
    ];
}

header("Location: tienda.php?ok=1");
exit;

?>
