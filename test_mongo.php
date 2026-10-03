<?php
require __DIR__ . '/vendor/autoload.php';

try {
    $cliente = new MongoDB\Client("mongodb://localhost:27017");

    $db = $cliente->ceramicentro_mongo;


    $resultado = $db->productos->insertOne([
        "nombre" => "Producto de prueba",
        "precio" => 12000,
        "stock" => 15
    ]);

    echo "<h2 style='color:green'>✅ Conexión exitosa con MongoDB</h2>";
    echo "Documento insertado con ID: " . $resultado->getInsertedId();
} catch (Exception $e) {
    echo "<h2 style='color:red'>❌ Error de conexión:</h2> " . $e->getMessage();
}
