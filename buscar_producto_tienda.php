<?php 
session_start();

require __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

$db = mongo();
$colProductos = $db->selectCollection("productos");

$productos = [];

if (isset($_GET['buscar']) && !empty(trim($_GET['buscar']))) {

    $buscar = trim($_GET['buscar']);

    $cursor = $colProductos->find(
        ["nombre" => ['$regex' => $buscar, '$options' => 'i']],
        ["sort" => ["nombre" => 1], "typeMap" => ["root" => "array", "document" => "array"]]
    );

    $productos = iterator_to_array($cursor, false);
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['producto_id'], $_POST['cantidad'])) {

    $productoId = $_POST['producto_id'];
    $cantidad   = intval($_POST['cantidad']);

    if (!preg_match('/^[a-f\d]{24}$/i', $productoId)) {
        header("Location: ver_carrito.php?error=ID inválido");
        exit;
    }

    $producto = $colProductos->findOne(
        ["_id" => new ObjectId($productoId)],
        ["typeMap" => ["root" => "array", "document" => "array"]]
    );

    if ($producto) {

        if (isset($_SESSION['carrito'][$productoId])) {
            $_SESSION['carrito'][$productoId]['cantidad'] += $cantidad;
        } else {
            $_SESSION['carrito'][$producto_id] = [
    'nombre'   => $producto['nombre'],
    'precio'   => $producto['precio'],
    'cantidad' => $cantidad,
    'imagen'   => $producto['imagen'] ?? ''
];
        }
    }

    header("Location: ver_carrito.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Buscar Productos - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color: #f9fafb; }
        .header {
            background-color: #c62828; color: white;
            padding: 20px 30px; border-radius: 10px;
            margin-bottom: 30px;
        }
        .form-search {
            display: flex; justify-content: center; gap: 10px;
        }
        .cantidad-input { width: 80px; }
        .btn-volver {
            background-color: #e53935; color: white; border: none;
            padding: 10px 15px; border-radius: 10px;
        }
        .btn-volver:hover { background-color: #b71c1c; }
        footer { font-size: .9rem; color: #888; }
    </style>
</head>

<body>

<div class="container">
    <div class="header mb-4 shadow-sm">
        <h1>Buscar Productos</h1>
        <p>Busca los productos que deseas comprar.</p>
    </div>

    <form method="get" class="form-search mb-4">
        <input type="text" name="buscar" class="form-control" placeholder="Nombre del producto"
               value="<?= htmlspecialchars($_GET['buscar'] ?? '') ?>" required>
        <button type="submit" class="btn btn-primary">Buscar</button>
    </form>

    <?php if (!empty($productos)): ?>
        <h3 class="mb-4">Resultados:</h3>

        <table class="table table-bordered shadow-sm">
            <thead class="table-light">
                <tr>
                    <th>Imagen</th>
                    <th>Nombre</th>
                    <th>Precio</th>
                    <th>Cantidad</th>
                    <th>Agregar al carrito</th>
                </tr>
            </thead>
            <tbody>

                <?php foreach ($productos as $p): ?>
                    <tr>
                        <td>
                            <?php if (!empty($p['imagen'])): ?>
                                <img src="<?= htmlspecialchars($p['imagen']) ?>"
                                     style="width:70px; height:70px; object-fit:cover; border-radius:10px;">
                            <?php else: ?>
                                <span class="text-muted">Sin imagen</span>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($p['nombre']) ?></td>
                        <td>$<?= number_format($p['precio'], 2) ?></td>

                        <td>
                            <form method="post" action="" class="d-flex">
                                <input type="hidden" name="producto_id" value="<?= $p['_id'] ?>">
                                <input type="number" name="cantidad"
                                       min="1" max="<?= intval($p['stock']) ?>"
                                       value="1" class="form-control cantidad-input me-2" required>
                        </td>

                        <td>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-cart-plus"></i> Agregar
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>

            </tbody>
        </table>

    <?php elseif (isset($_GET['buscar'])): ?>
        <div class="alert alert-info">No se encontraron productos.</div>
    <?php endif; ?>

    <a href="tienda.php" class="btn btn-volver mt-4">
        <i class="bi bi-arrow-left-circle"></i> Volver
    </a>
</div>

<footer class="text-center mt-5 mb-3">
    <b>© 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados</b>
</footer>

</body>
</html>
