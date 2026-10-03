<?php
session_start();
include("verificar_acceso.php");
verificarSesion("cliente");

$carrito = $_SESSION['carrito'] ?? [];
$total = 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Carrito de Compras - CERAMICENTRO</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background-color: #f9fafb; }
        .carrito-header {
            background-color: #c62828; color: white;
            padding: 20px; border-radius: 10px; margin-bottom: 30px;
        }
        .producto-img {
            width: 70px; height: 70px; object-fit: cover;
            border-radius: 10px; border: 1px solid #ddd;
        }
        .btn-rojo { background-color: #c62828; color: white; }
        .btn-rojo:hover { background-color: #b71c1c; color: white; }
        footer { font-size: .9rem; color: #888; }
    </style>
</head>
<body>

<div class="container mt-5">

    <div class="carrito-header shadow-sm mb-4">
        <h2 class="mb-0">🛒 Tu Carrito de Compras</h2>
    </div>

    <?php if (empty($carrito)): ?>
        <div class="alert alert-info text-center">
            Tu carrito está vacío.<br>
            <a href="tienda.php" class="btn btn-primary mt-3">Ir a la tienda</a>
        </div>

    <?php else: ?>

        <div class="table-responsive">
            <table class="table table-bordered align-middle shadow-sm">
                <thead class="table-light">
                    <tr>
                        <th>Imagen</th>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($carrito as $id => $item): 
                        $subtotal = $item['precio'] * $item['cantidad'];
                        $total += $subtotal;
                    ?>
                    <tr>
                        <td>
                            <?php if (!empty($item["imagen"])): ?>
                                <img src="<?= htmlspecialchars($item['imagen']) ?>" class="producto-img">
                            <?php else: ?>
                                <span class="text-muted">Sin imagen</span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($item['nombre']) ?></td>

                        <td>$<?= number_format($item['precio'], 2) ?></td>

                        <td><?= $item['cantidad'] ?></td>

                        <td><strong>$<?= number_format($subtotal, 2) ?></strong></td>

                        <td>
                            <a href="eliminar_del_carrito.php?id=<?= $id ?>"
                               class="btn btn-outline-danger btn-sm"
                               onclick="return confirm('¿Quitar este producto del carrito?');">
                                ❌ Quitar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>

            </table>
        </div>

        <h4 class="mt-4">
            Total a pagar: <strong>$<?= number_format($total, 2) ?></strong>
        </h4>

        <div class="mt-4 d-flex gap-3">
            <a href="tienda.php" class="btn btn-light">🏪 Seguir comprando</a>
            <a href="vaciar_carrito.php" class="btn btn-rojo">Vaciar Carrito</a>
            <a href="realizar_pedido.php" class="btn btn-primary">Finalizar Compra</a>
        </div>

    <?php endif; ?>

</div>

<footer class="text-center mt-4 mb-4">
    © 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados
</footer>

</body>
</html>
