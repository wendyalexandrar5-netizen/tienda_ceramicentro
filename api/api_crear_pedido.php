<?php

require __DIR__ . '/../vendor/autoload.php';
include("../conexion_mongo.php");

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$usuario = $data["usuario"] ?? null;
$carrito = $data["carrito"] ?? [];

if (!$usuario || empty($usuario["id"]) || empty($carrito)) {
    echo json_encode([
        "success" => false,
        "message" => "Usuario o carrito inválido."
    ]);
    exit;
}

try {

    $db = mongo();

    $colProductos = $db->selectCollection("productos");
    $colPedidos = $db->selectCollection("pedidos");
    $colPedidoDetalle = $db->selectCollection("pedido_detalle");

    $usuarioId = $usuario["id"];

    if (!preg_match('/^[a-f\d]{24}$/i', $usuarioId)) {
        echo json_encode([
            "success" => false,
            "message" => "ID de usuario inválido."
        ]);
        exit;
    }

    $total = 0;

    foreach ($carrito as $item) {

        $productoId = $item["id"] ?? "";

        if (!preg_match('/^[a-f\d]{24}$/i', $productoId)) {
            echo json_encode([
                "success" => false,
                "message" => "ID de producto inválido."
            ]);
            exit;
        }

        $producto = $colProductos->findOne(
            ["_id" => new ObjectId($productoId)],
            ["typeMap" => ["root" => "array", "document" => "array"]]
        );

        if (!$producto) {
            echo json_encode([
                "success" => false,
                "message" => "Producto no encontrado."
            ]);
            exit;
        }

        $cantidad = (int)($item["cantidad"] ?? 1);

        if ($cantidad <= 0) {
            echo json_encode([
                "success" => false,
                "message" => "Cantidad inválida."
            ]);
            exit;
        }

        if ($cantidad > (int)$producto["stock"]) {
            echo json_encode([
                "success" => false,
                "message" => "Stock insuficiente para " . ($producto["nombre"] ?? "producto")
            ]);
            exit;
        }

        $total += ((float)$producto["precio"]) * $cantidad;
    }

    $pedidoInsert = $colPedidos->insertOne([
        "usuario_id" => new ObjectId($usuarioId),
        "fecha" => new UTCDateTime(),
        "total" => (float)$total,
        "estado" => "Pagado",
        "origen" => "app_android"
    ]);

    $pedidoId = $pedidoInsert->getInsertedId();

    foreach ($carrito as $item) {

        $productoId = $item["id"];
        $cantidad = (int)($item["cantidad"] ?? 1);

        $producto = $colProductos->findOne(
            ["_id" => new ObjectId($productoId)],
            ["typeMap" => ["root" => "array", "document" => "array"]]
        );

        $precioUnitario = (float)$producto["precio"];
        $subtotal = $precioUnitario * $cantidad;

        $colPedidoDetalle->insertOne([
            "pedido_id" => $pedidoId,
            "producto_id" => new ObjectId($productoId),
            "nombre_producto" => $producto["nombre"] ?? "",
            "cantidad" => $cantidad,
            "precio_unitario" => $precioUnitario,
            "subtotal" => (float)$subtotal
        ]);

        $colProductos->updateOne(
            ["_id" => new ObjectId($productoId)],
            ['$inc' => ["stock" => -$cantidad]]
        );
    }

    echo json_encode([
        "success" => true,
        "message" => "Pedido creado correctamente.",
        "pedido_id" => (string)$pedidoId,
        "total" => $total
    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Error del servidor.",
        "error" => $e->getMessage()
    ]);
}
