<?php
session_start();

require __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

if (!isset($_GET["id"]) || !preg_match('/^[a-f\d]{24}$/i', $_GET["id"])) {
    header("Location: mis_pedidos.php");
    exit;
}

$pedidoId = $_GET["id"];

$db = mongo();
$colDetalles  = $db->selectCollection("pedido_detalle");
$colProductos = $db->selectCollection("productos");

$detallesCursor = $colDetalles->find(
    ["pedido_id" => new ObjectId($pedidoId)],
    ["typeMap" => ["root" => "array", "document" => "array"]]
);

$detalles = iterator_to_array($detallesCursor, false);

if (empty($detalles)) {
    header("Location: mis_pedidos.php?error=Pedido sin detalles");
    exit;
}

foreach ($detalles as $d) {

    $productoId = (string)$d["producto_id"];
    $cantidad   = (int)$d["cantidad"];

    $prod = $colProductos->findOne(
        ["_id" => new ObjectId($productoId)],
        ["typeMap" => ["root"=>"array","document"=>"array"]]
    );

    if (!$prod) {
        continue;
    }

    if (isset($_SESSION["carrito"][$productoId])) {
        $_SESSION["carrito"][$productoId]["cantidad"] += $cantidad;
    } else {
        $_SESSION["carrito"][$productoId] = [
            "nombre"   => $prod["nombre"],
            "precio"   => (float)$prod["precio"],
            "cantidad" => $cantidad,
            "imagen"   => $prod["imagen"] ?? ""
        ];
    }
}

header("Location: ver_carrito.php?ok=repetido");
exit;

?>

exit;
