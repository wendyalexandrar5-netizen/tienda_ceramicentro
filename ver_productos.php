<?php
session_start();
include("verificar_acceso.php");
verificarSesion('administrador');
include("conexion_mongo.php");

if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit;
}

function esc($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

$db  = mongo();
$colProductos  = $db->selectCollection('productos');
$colCategorias = $db->selectCollection('categorias');

$busqueda  = trim($_GET['buscar'] ?? '');

$filtro = [];
if ($busqueda !== '') {
    $filtro['nombre'] = ['$regex' => $busqueda, '$options' => 'i'];
}

$cursor = $colProductos->find(
    $filtro,
    [
        'sort'    => ['nombre' => 1],
        'typeMap' => ['root'=>'array','document'=>'array','array'=>'array']
    ]
);
$productos = iterator_to_array($cursor, false);

$categoriasCursor = $colCategorias->find([], ['sort' => ['nombre' => 1]]);
$categorias = [];

foreach ($categoriasCursor as $cat) {
    $categorias[(string)$cat['_id']] = $cat['nombre'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Productos - CERAMICENTRO</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body { background-color:#f4f4f4; font-family:'Segoe UI',sans-serif; }

        .producto-card{
            border-radius:15px;
            padding:20px;
            background:#fff;
            box-shadow:0 5px 15px rgba(0,0,0,.1);
            text-align:center;
            transition:.25s;
        }
        .producto-card:hover{
            transform:translateY(-5px);
            box-shadow:0 8px 20px rgba(0,0,0,.15);
        }
        .producto-img{
            width:100%;
            height:200px;
            object-fit:cover;
            border-radius:12px;
        }
        .btn-volver{
            position:fixed;
            top:20px;
            left:20px;
            background:#e53935;
            color:#fff;
            border:none;
            padding:10px 15px;
            border-radius:10px;
            z-index:999;
        }
        .btn-volver:hover{
            background:#b71c1c;
            color:#fff;
        }
        .form-search{
            display:flex;
            justify-content:center;
            margin-bottom:30px;
            gap:10px;
        }
        footer { font-size:.9rem; color:#888; }
    </style>
</head>

<body>

<a href="panel_admin.php" class="btn btn-volver">
    <i class="bi bi-arrow-left-circle"></i> Volver
</a>

<div class="container mt-5">

    <h1 class="text-center mb-4">Gestión de Productos</h1>

    <form method="get" class="form-search">
        <input type="text" name="buscar" class="form-control" placeholder="Buscar por nombre..."
               value="<?= esc($_GET['buscar'] ?? '') ?>">

        <button type="submit" class="btn btn-primary">
            <i class="bi bi-search"></i> Buscar
        </button>

        <?php if (($busqueda ?? '') !== ''): ?>
            <a href="ver_productos.php" class="btn btn-outline-secondary">
                <i class="bi bi-x-circle"></i> Limpiar
            </a>
        <?php endif; ?>
    </form>

    <div class="text-end mb-3">
        <a href="exportar_productos.php" class="btn btn-success">
            <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
        </a>
    </div>

    <div class="row row-cols-1 row-cols-md-3 g-4">

        <?php foreach ($productos as $p): 
            $idCat = isset($p['categoria_id']) ? (string)$p['categoria_id'] : '';
            $categoria = $categorias[$idCat] ?? "Sin categoría";
        ?>

            <div class="col">
                <div class="producto-card">

                    <img src="<?= esc($p['imagen']) ?>" class="producto-img" alt="Imagen producto">

                    <h4 class="mt-3"><?= esc($p['nombre']) ?></h4>

                    <p><strong>Precio:</strong> $<?= number_format($p['precio'], 2) ?></p>

                    <p><strong>Stock:</strong> <?= esc($p['stock']) ?></p>

                    <p class="text-muted">
                        <strong>Categoría:</strong> <?= esc($categoria) ?>
                    </p>

                </div>
            </div>

        <?php endforeach; ?>

    </div>
</div>

<footer class="text-center mt-5">
    © 2025 <strong>CERAMICENTRO</strong> — Todos los derechos reservados
</footer>

</body>
</html>
