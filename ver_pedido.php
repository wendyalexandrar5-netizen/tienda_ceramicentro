<?php
session_start();

require __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

if (!isset($_GET["id"]) || !preg_match('/^[a-f\d]{24}$/i', $_GET["id"])) {
    die("ID de pedido inválido.");
}

$pedidoId = $_GET["id"];

$db = mongo();
$colPedidos       = $db->selectCollection("pedidos");
$colPedidoDetalle = $db->selectCollection("pedido_detalle");
$colProductos     = $db->selectCollection("productos");

$pedido = $colPedidos->findOne(
    ["_id" => new ObjectId($pedidoId)],
    ["typeMap" => ["root" => "array", "document" => "array"]]
);

if (!$pedido) {
    die("Pedido no encontrado.");
}

$fecha = $pedido["fecha"]->toDateTime()->format("Y-m-d H:i");

$estado = "pagado";

$detallesCursor = $colPedidoDetalle->find(
    ["pedido_id" => new ObjectId($pedidoId)],
    ["typeMap" => ["root" => "array", "document" => "array"]]
);
$detalles = iterator_to_array($detallesCursor, false);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalles del Pedido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background-color:#f5f5f5; }
        .pedido-box {
            background:white; padding:25px; border-radius:12px;
            box-shadow:0 4px 12px rgba(0,0,0,0.1);
            margin-bottom:20px;
        }
        .estado {
            padding:6px 12px; border-radius:6px;
            color:white; font-weight:bold;
        }
        .pagado { background:#4caf50; }
        .producto-img {
            width:80px; height:80px; object-fit:cover; border-radius:10px;
        }
        .btn-op {
            margin-right: 8px;
        }
    </style>
</head>
<body>

<div class="container mt-4">

    <h2 class="mb-4">📄 Detalle del Pedido</h2>

    <div class="pedido-box">
        <p><strong>ID Pedido:</strong> <?= htmlspecialchars($pedidoId) ?></p>
        <p><strong>Fecha:</strong> <?= htmlspecialchars($fecha) ?></p>
        <p><strong>Total:</strong> $<?= number_format($pedido["total"], 2) ?></p>

        <p><strong>Estado:</strong>
            <span class="estado pagado">Pagado</span>
        </p>

        <a href="repetir_pedido.php?id=<?= $pedidoId ?>" class="btn btn-primary btn-op">
            🔁 Repetir pedido
        </a>

        <a href="descargar_pedido.php?id=<?= $pedidoId ?>" class="btn btn-danger btn-op" target="_blank">
            📄 Descargar PDF
        </a>
    </div>

    <h4 class="mb-3">🛒 Productos del Pedido</h4>

    <div class="table-responsive">
        <table class="table table-bordered shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Imagen</th>
                    <th>Producto</th>
                    <th>Cantidad</th>
                    <th>Precio Unitario</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($detalles as $item):

                    $prod = $colProductos->findOne(
                        ["_id" => new ObjectId($item["producto_id"])],
                        ["typeMap" => ["root" => "array", "document" => "array"]]
                    );

                    $img = $prod["imagen"] ?? "";
                ?>

                <tr>
                    <td>
                        <?php if ($img): ?>
                            <img src="<?= htmlspecialchars($img) ?>" class="producto-img">
                        <?php else: ?>
                            <span class="text-muted">Sin imagen</span>
                        <?php endif; ?>
                    </td>

                    <td><?= htmlspecialchars($item["nombre_producto"]) ?></td>
                    <td><?= $item["cantidad"] ?></td>
                    <td>$<?= number_format($item["precio_unitario"], 2) ?></td>
                    <td>$<?= number_format($item["subtotal"], 2) ?></td>
                </tr>

                <?php endforeach; ?>

            </tbody>
        </table>
    </div>

    <a href="mis_pedidos.php" class="btn btn-dark mt-3">⬅ Volver</a>

</div>

<footer class="text-center mt-4 mb-4">
    © 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados
</footer>

</body>
</html>
