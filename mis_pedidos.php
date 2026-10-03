<?php
session_start();

require __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

$usuario = $_SESSION["usuario"];
$usuarioId = $usuario["id"];

$db = mongo();
$colPedidos = $db->selectCollection("pedidos");

$cursor = $colPedidos->find(
    ["usuario_id" => new ObjectId($usuarioId)],
    ["sort" => ["fecha" => -1], "typeMap" => ["root" => "array", "document" => "array"]]
);

$pedidos = iterator_to_array($cursor, false);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Mis Pedidos - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background-color:#f9fafb; }
        .pedido-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .estado {
            padding: 5px 10px;
            border-radius: 6px;
            color: white;
            font-weight: bold;
        }
        .pagado { background: #4caf50; }
    </style>
</head>
<body>

<div class="container mt-4">
    <h2 class="mb-4">📦 Mis Pedidos</h2>

    <?php if (empty($pedidos)): ?>
        <div class="alert alert-info">Aún no has realizado pedidos.</div>
    <?php else: ?>

        <?php foreach ($pedidos as $p): 
            $fecha = $p["fecha"]->toDateTime()->format("Y-m-d H:i");

            $estadoMostrado = "pagado";
            $estadoClase = "pagado";
        ?>

        <div class="pedido-card">
            <h5><strong>ID Pedido:</strong> <?= (string)$p["_id"] ?></h5>
            <p><strong>Fecha:</strong> <?= $fecha ?></p>
            <p><strong>Total:</strong> $<?= number_format($p["total"], 2) ?></p>

            <p><strong>Estado:</strong>
                <span class="estado <?= $estadoClase ?>">
                    <?= ucfirst($estadoMostrado) ?>
                </span>
            </p>

            <a href="ver_pedido.php?id=<?= $p['_id'] ?>" class="btn btn-primary mt-2">
                Ver detalles
            </a>
        </div>

        <?php endforeach; ?>

    <?php endif; ?>

    <a href="tienda.php" class="btn btn-dark mt-3">volver</a>

</div>

<footer class="text-center mt-4 mb-4">
    © 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados
</footer>

</body>
</html>
