<?php

require __DIR__ . '/../vendor/autoload.php';
include("../conexion_mongo.php");

use MongoDB\BSON\ObjectId;

date_default_timezone_set("America/Bogota");

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$usuarioId = $data["usuario_id"] ?? "";

if (!preg_match('/^[a-f\d]{24}$/i', $usuarioId)) {

    echo json_encode([
        "success" => false,
        "message" => "ID inválido"
    ]);

    exit;
}

try {

    $db = mongo();

    $colPedidos = $db->selectCollection("pedidos");
    $colDetalle = $db->selectCollection("pedido_detalle");
    $colProductos = $db->selectCollection("productos");

    $pedidosCursor = $colPedidos->find(
        [
            "usuario_id" => new ObjectId($usuarioId)
        ],
        [
            "sort" => ["fecha" => -1]
        ]
    );

    $resultado = [];

    foreach($pedidosCursor as $pedido){

        $pedidoId = (string)$pedido["_id"];

        $detallesCursor = $colDetalle->find([
            "pedido_id" => $pedido["_id"]
        ]);

        $productos = [];

        foreach($detallesCursor as $detalle){

            $imagen = "";

            if(isset($detalle["producto_id"])){

                $prod = $colProductos->findOne([
                    "_id" => $detalle["producto_id"]
                ]);

                if($prod && !empty($prod["imagen"])){

                    $imagen =
                    "http://172.20.10.4/tiendaonline_mongodb/"
                    . ltrim($prod["imagen"], "/");
                }
            }

            $productos[] = [

                "producto_id" =>
                isset($detalle["producto_id"])
                    ? (string)$detalle["producto_id"]
                    : "",

                "nombre" =>
                $detalle["nombre_producto"] ?? "",

                "cantidad" =>
                (int)($detalle["cantidad"] ?? 0),

                "precio" =>
                (float)($detalle["precio_unitario"] ?? 0),

                "subtotal" =>
                (float)($detalle["subtotal"] ?? 0),

                "imagen" =>
                $imagen
            ];
        }

        $fecha = "";

        if(isset($pedido["fecha"])){
            $fechaObj = $pedido["fecha"]->toDateTime();
            $fechaObj->setTimezone(new DateTimeZone("America/Bogota"));
            $fecha = $fechaObj->format("Y-m-d H:i");
        }

        $resultado[] = [

            "id" => $pedidoId,

            "fecha" => $fecha,

            "estado" =>
            $pedido["estado"] ?? "Pendiente",

            "total" =>
            (float)($pedido["total"] ?? 0),

            "productos" =>
            $productos
        ];
    }

    echo json_encode([
        "success" => true,
        "pedidos" => $resultado
    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e){

    echo json_encode([
        "success" => false,
        "message" => "Error del servidor",
        "error" => $e->getMessage()
    ]);
}
