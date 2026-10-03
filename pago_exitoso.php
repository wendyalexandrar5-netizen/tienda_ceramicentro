<?php
session_start();
require __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente");

if (!isset($_SESSION["ultimo_pedido"])) {
    header("Location: tienda.php");
    exit;
}

$pedido = $_SESSION["ultimo_pedido"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Pago Exitoso - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background: #f9fafb; }
        .success-box {
            margin-top: 60px;
            background: #ffffff;
            padding: 40px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .check {
            font-size: 4rem;
            color: #4caf50;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="success-box">
        <div class="check">✔</div>
        <h1>¡Pago realizado con éxito!</h1>

        <p class="mt-3 fs-5">Tu pedido ha sido procesado correctamente.</p>

        <div class="mt-4">
            <h4>Resumen del pedido</h4>
            <p><strong>ID del Pedido:</strong> <?= $pedido["id"] ?></p>
            <p><strong>Fecha:</strong> <?= $pedido["fecha"] ?></p>
            <p><strong>Total:</strong> $<?= number_format($pedido["total"], 2) ?></p>
        </div>

<div class="mt-4 d-flex justify-content-center gap-3">
    <a href="mis_pedidos.php" class="btn btn-primary">Ver mis pedidos</a>
    <a href="generar_comprobante.php?id=<?= $pedido['id'] ?>" class="btn btn-warning">
        Descargar comprobante
    </a>
    <a href="tienda.php" class="btn btn-success">Volver a la tienda</a>
</div>

    </div>
</div>

<footer class="text-center mt-5 mb-4">
    © 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados
</footer>

</body>
</html>
