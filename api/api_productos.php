<?php

require __DIR__ . '/../vendor/autoload.php';
include("../conexion_mongo.php");

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

try {

    $db = mongo();

    $colProductos = $db->selectCollection("productos");
    $colCategorias = $db->selectCollection("categorias");

    $productos = $colProductos->find([], [
        "sort" => ["createdAt" => -1]
    ]);

    $resultado = [];

    foreach($productos as $p){

        $categoriaNombre = "Sin categoría";

        if(isset($p["categoria_id"])){

            $cat = $colCategorias->findOne([
                "_id" => $p["categoria_id"]
            ]);

            if($cat){
                $categoriaNombre = $cat["nombre"];
            }
        }

        $resultado[] = [

            "id" => (string)$p["_id"],

            "nombre" => $p["nombre"] ?? "",

            "descripcion" => $p["descripcion"] ?? "",

            "precio" => (float)($p["precio"] ?? 0),

            "stock" => (int)($p["stock"] ?? 0),

            "categoria" => $categoriaNombre,

            "imagen" => "http://172.20.10.4/tiendaonline_mongodb/" . ltrim(($p["imagen"] ?? ""), "/")
        ];
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);

} catch(Exception $e){

    echo json_encode([
        "error" => true,
        "mensaje" => $e->getMessage()
    ]);
}
