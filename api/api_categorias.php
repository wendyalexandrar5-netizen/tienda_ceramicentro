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

    $colCategorias = $db->selectCollection("categorias");

    $categoriasCursor = $colCategorias->find([], [
        "sort" => ["nombre" => 1]
    ]);

    $categorias = [];

    foreach($categoriasCursor as $cat){

        $categorias[] = [
            "id" => (string)$cat["_id"],
            "nombre" => $cat["nombre"] ?? "Sin nombre"
        ];
    }

    echo json_encode([
        "success" => true,
        "categorias" => $categorias
    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e){

    echo json_encode([
        "success" => false,
        "message" => "Error cargando categorías",
        "error" => $e->getMessage()
    ]);
}
