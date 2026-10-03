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

$correo = mb_strtolower(trim($data["correo"] ?? ""), "UTF-8");
$password = $data["password"] ?? "";

if ($correo === "" || $password === "") {
    echo json_encode([
        "success" => false,
        "message" => "Correo y contraseña son obligatorios."
    ]);
    exit;
}

try {

    $db = mongo();
    $colUsuarios = $db->selectCollection("usuarios");

    $usuario = $colUsuarios->findOne(
        ["correo" => $correo],
        [
            "projection" => [
                "_id" => 1,
                "nombre" => 1,
                "correo" => 1,
                "rol" => 1,
                "contraseña" => 1,
                "contrasena" => 1
            ],
            "typeMap" => [
                "root" => "array",
                "document" => "array",
                "array" => "array"
            ],
            "collation" => [
                "locale" => "es",
                "strength" => 2
            ]
        ]
    );

    if (!$usuario) {
        echo json_encode([
            "success" => false,
            "message" => "Usuario no encontrado."
        ]);
        exit;
    }

    $hash = "";

    if (isset($usuario["contraseña"])) {
        $hash = $usuario["contraseña"];
    } elseif (isset($usuario["contrasena"])) {
        $hash = $usuario["contrasena"];
    }

    if ($hash === "" || !password_verify($password, $hash)) {
        echo json_encode([
            "success" => false,
            "message" => "Contraseña incorrecta."
        ]);
        exit;
    }

    if (($usuario["rol"] ?? "") !== "cliente") {
        echo json_encode([
            "success" => false,
            "message" => "Esta app es solo para clientes."
        ]);
        exit;
    }

    echo json_encode([
        "success" => true,
        "message" => "Login correcto.",
        "usuario" => [
            "id" => (string)$usuario["_id"],
            "nombre" => $usuario["nombre"] ?? "",
            "correo" => $usuario["correo"] ?? $correo,
            "rol" => $usuario["rol"] ?? ""
        ]
    ], JSON_UNESCAPED_UNICODE);

} catch(Exception $e) {

    echo json_encode([
        "success" => false,
        "message" => "Error del servidor.",
        "error" => $e->getMessage()
    ]);
}
