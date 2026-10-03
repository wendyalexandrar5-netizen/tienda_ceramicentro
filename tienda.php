<?php
session_start();
require __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente"); 
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

$db = mongo();
$colProductos  = $db->selectCollection("productos");
$colCategorias = $db->selectCollection("categorias");

$usuario = $_SESSION['usuario'];
$nombre_usuario = $usuario['nombre'] ?? 'Usuario';

$categoriaFiltro = $_GET['categoria'] ?? '';

$filter = [];
if ($categoriaFiltro && preg_match('/^[a-f\d]{24}$/i', $categoriaFiltro)) {
    $filter['categoria_id'] = new ObjectId($categoriaFiltro);
}

$productos = $colProductos->find($filter, ['sort' => ['nombre' => 1]]);
$categorias = $colCategorias->find([], ['sort' => ['nombre' => 1]]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>CERAMICENTRO - Tienda</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body { background-color: #f9fafb; }
        .titulo-ceramicentro {
            font-size: 3.5rem; font-weight: bold; color: #d32f2f;
            text-align: center; margin-bottom: 20px;
            animation: fadeIn 2s ease-in-out, typing 4s steps(20, end);
            white-space: nowrap; overflow: hidden;
            border-right: 3px solid #d32f2f; max-width: 100%;
        }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        @keyframes typing { from { width: 0; } to { width: 20ch; } }

        .usuario-bar {
            background-color: #c62828; color: white;
            padding: 15px 25px; border-radius: 10px; margin-bottom: 20px;
        }
        .usuario-bar .btn { margin-left: 10px; }

        .card { border: none; border-radius: 15px; }
        .card-img-top {
            width: 160px; height: 160px; object-fit: cover;
            margin: 0 auto; display: block;
        }
        .card-footer { background-color: #f1f1f1; border-top: none; }

        .btn-floating {
            position: fixed; bottom: 25px; right: 25px;
            background-color: #c62828; color: white;
            border-radius: 50%; width: 55px; height: 55px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem; box-shadow: 0 4px 10px rgba(0,0,0,0.3);
            z-index: 999; transition: background-color .3s;
        }
        .btn-floating:hover { background-color: #b71c1c; }

        footer { font-size: 0.9rem; color: #777; }
    </style>
</head>
<body>

<div class="container mt-4">

    <div class="titulo-ceramicentro">CERAMICENTRO</div>

    <div class="usuario-bar d-flex justify-content-between align-items-center shadow-sm">
        <h4 class="mb-0">Bienvenido, <?= htmlspecialchars($nombre_usuario) ?> 👋</h4>
        <div>
            <a href="buscar_producto_tienda.php" class="btn btn-light">🔎 Buscar</a>
            <a href="ver_carrito.php" class="btn btn-light">🛒 Ver carrito</a>
            <a href="mis_pedidos.php" class="btn btn-light">📦 Mis Pedidos</a>
            <a href="logout.php" class="btn btn-dark">Cerrar sesión</a>
        </div>
    </div>

    <form class="mb-4">
        <label class="form-label"><strong>Filtrar por categoría:</strong></label>
        <select name="categoria" class="form-select" onchange="this.form.submit()">
            <option value="">Todas las categorías</option>

            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['_id'] ?>"
                    <?= ($categoriaFiltro == (string)$cat['_id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <h4 class="mb-4">Productos Disponibles</h4>

    <div class="row row-cols-1 row-cols-md-3 g-4">
        <?php foreach ($productos as $producto): ?>
            <div class="col">
                <div class="card h-100 shadow-sm">

                    <img src="<?= htmlspecialchars($producto['imagen']) ?>"
                         class="card-img-top" alt="Producto">

                    <div class="card-body">
                        <h5 class="card-title"><?= htmlspecialchars($producto['nombre']) ?></h5>

                        <p class="card-text">
                            <?= htmlspecialchars($producto['descripcion'] ?? '') ?>
                        </p>

                        <p class="card-text">
                            <strong>$<?= number_format($producto['precio'], 2) ?></strong>
                        </p>
                    </div>

                    <div class="card-footer">

                        <?php if ($producto['stock'] > 0): ?>
                            <form method="post" action="agregar_carrito.php"
                                  class="d-flex justify-content-between align-items-center">

                                <input type="hidden" name="producto_id"
                                       value="<?= (string)$producto['_id'] ?>">

                                <input type="number" name="cantidad" value="1"
                                       min="1" max="<?= $producto['stock'] ?>"
                                       class="form-control me-2" style="width: 80px;">

                                <button type="submit" class="btn btn-primary">Agregar</button>

                            </form>

                        <?php else: ?>
                            <p class="text-danger text-center"><strong>Sin stock</strong></p>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<a href="https://wa.me/573134322830" class="btn-floating">❔</a>

<footer class="text-center mt-4 mb-4">
    © 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados
</footer>

</body>
</html>
