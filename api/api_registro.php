<?php

require __DIR__ . '/../vendor/autoload.php';
include("../conexion_mongo.php");

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") {
    http_response_code(200);
    exit;
}

$data = json_decode(file_get_contents("php://input"), true);

$nombre = trim($data["nombre"] ?? "");
$correo = mb_strtolower(trim($data["correo"] ?? ""), "UTF-8");
$password = $data["password"] ?? "";

if($nombre === "" || $correo === "" || $password === ""){
    echo json_encode([
        "success" => false,
        "message" => "Todos los campos son obligatorios."
    ]);
    exit;
}

if(strlen($password) < 6){
    echo json_encode([
        "success" => false,
        "message" => "La contraseña debe tener mínimo 6 caracteres."
    ]);
    exit;
}

try{

    $db = mongo();
    $colUsuarios = $db->selectCollection("usuarios");

    $existe = $colUsuarios->findOne(["correo" => $correo]);

    if($existe){
        echo json_encode([
            "success" => false,
            "message" => "Este correo ya está registrado."
        ]);
        exit;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $insert = $colUsuarios->insertOne([
        "nombre" => $nombre,
        "correo" => $correo,
        "contraseña" => $hash,
        "rol" => "cliente"
    ]);

    echo json_encode([
        "success" => true,
        "message" => "Registro exitoso.",
        "usuario" => [
            "id" => (string)$insert->getInsertedId(),
            "nombre" => $nombre,
            "correo" => $correo,
            "rol" => "cliente"
        ]
    ], JSON_UNESCAPED_UNICODE);

}catch(Exception $e){

    echo json_encode([
        "success" => false,
        "message" => "Error del servidor.",
        "error" => $e->getMessage()
    ]);
}
