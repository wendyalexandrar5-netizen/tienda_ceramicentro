<?php
require __DIR__ . '/vendor/autoload.php';
include("verificar_acceso.php");
verificarSesion('administrador');
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function isValidObjectId($id) {
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id);
}

$db = mongo();
$colProductos   = $db->selectCollection("productos");
$colCategorias  = $db->selectCollection("categorias");
$colHistorial   = $db->selectCollection("historial_productos");

$mensaje = $_GET['mensaje'] ?? null;
$ok      = $_GET['ok'] ?? null;
$error   = null;

$catCursor  = $colCategorias->find([], ["sort" => ["nombre" => 1]]);
$categorias = iterator_to_array($catCursor, false);

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "agregar_categoria") {
    $nombreCat = trim($_POST["nombre_categoria"] ?? '');

    if ($nombreCat === '') {
        $error = "El nombre de la categoría no puede estar vacío.";
    } else {
        $existe = $colCategorias->findOne(["nombre" => $nombreCat]);
        if ($existe) {
            $error = "La categoría ya existe.";
        } else {
            $colCategorias->insertOne([
                "nombre" => $nombreCat
            ]);
            header("Location: agregar_producto.php?mensaje=Categoría agregada correctamente");
            exit;
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["accion"] ?? "") === "agregar_producto") {

    $nombre       = trim($_POST["nombre"] ?? "");
    $descripcion  = trim($_POST["descripcion"] ?? "");
    $precio       = $_POST["precio"] ?? null;
    $stock        = $_POST["stock"] ?? null;
    $categoria_id = $_POST["categoria"] ?? "";

    if ($nombre === '') {
        $error = "El nombre es obligatorio.";
    } elseif ($descripcion === '') {
        $error = "La descripción es obligatoria.";
    } elseif (!is_numeric($precio) || $precio <= 0) {
        $error = "El precio debe ser un número positivo.";
    } elseif (!ctype_digit((string)$stock) || $stock < 0) {
        $error = "El stock debe ser un número entero no negativo.";
    } elseif (!isValidObjectId($categoria_id)) {
        $error = "La categoría seleccionada no es válida.";
    } elseif (!isset($_FILES["imagen"]) || $_FILES["imagen"]["error"] !== UPLOAD_ERR_OK) {
        $error = "Debes subir una imagen del producto.";
    }

    if (!isset($error)) {

        $ext = strtolower(pathinfo($_FILES["imagen"]["name"], PATHINFO_EXTENSION));
        $permitidas = ["jpg", "jpeg", "png", "gif", "webp"];

        if (!in_array($ext, $permitidas)) {
            $error = "Formato de imagen NO permitido.";
        } else {

            $imagenNombre = time() . "_" . uniqid() . "." . $ext;
            $rutaFinal = "imagenes/" . $imagenNombre;

            if (!is_dir("imagenes")) {
                mkdir("imagenes", 0775, true);
            }

            if (!move_uploaded_file($_FILES["imagen"]["tmp_name"], $rutaFinal)) {
                $error = "Error al subir la imagen.";
            } else {

                $insert = $colProductos->insertOne([
                    "nombre"        => $nombre,
                    "descripcion"   => $descripcion,
                    "precio"        => (float)$precio,
                    "stock"         => (int)$stock,
                    "categoria_id"  => new ObjectId($categoria_id),
                    "imagen"        => $rutaFinal,
                    "createdAt"     => new UTCDateTime(),
                    "actualizado_en"=> new UTCDateTime()
                ]);

                $productoId = $insert->getInsertedId();

                $adminIdStr = $_SESSION["usuario"]["id"] ?? null;
                $adminOid   = (isValidObjectId($adminIdStr)) ? new ObjectId($adminIdStr) : null;

                $catDoc = $colCategorias->findOne(["_id" => new ObjectId($categoria_id)]);
                $nombreCategoria = $catDoc["nombre"] ?? "Desconocida";

                $cambios = [
                    "Producto agregado",
                    "Precio inicial: $precio",
                    "Stock inicial: $stock",
                    "Categoría: $nombreCategoria",
                    "Imagen asignada"
                ];

                $colHistorial->insertOne([
                    "id_admin"        => $adminOid,
                    "nombre_admin"    => $_SESSION["usuario"]["nombre"] ?? "",
                    "id_producto"     => $productoId,
                    "producto_nombre" => $nombre,
                    "accion"          => "agrego",
                    "cambios"         => $cambios,  // ARRAY ✔
                    "fecha"           => new UTCDateTime()
                ]);

                header("Location: agregar_producto.php?ok=1");
                exit;
            }
        }
    }
}

$prodCursor = $colProductos->find([], ["sort" => ["nombre" => 1]]);
$productos  = iterator_to_array($prodCursor, false);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Gestión de Productos y Categorías - CERAMICENTRO</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f4f6f9;
        }

        .header {
            background: linear-gradient(135deg, #b71c1c, #e53935);
            color: white;
            padding: 25px;
            text-align: center;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .panel-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .section-title {
            font-weight: bold;
            margin-bottom: 20px;
            color: #b71c1c;
        }

        .producto-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
        }

        .nav-tabs .nav-link {
            font-weight: bold;
            color: #b71c1c;
        }

        .nav-tabs .nav-link.active {
            background-color: #b71c1c;
            color: white;
            border-color: #b71c1c;
        }

        .btn-volver {
            background: #e53935;
            color: #fff;
            border: none;
            padding: 10px 18px;
            border-radius: 10px;
            text-decoration: none;
        }

        .btn-volver:hover {
            background: #b71c1c;
            color: #fff;
        }

        .table th {
            vertical-align: middle;
        }

        .table td {
            vertical-align: middle;
        }
    </style>
</head>

<body>

<div class="header">
    <h1>Panel de Gestión</h1>
    <p class="mb-0">Productos y Categorías - CERAMISHOP</p>
</div>

<div class="container mb-5">

    <?php if ($ok): ?>
        <div class="alert alert-success">Producto agregado correctamente.</div>
    <?php endif; ?>

    <?php if ($mensaje): ?>
        <div class="alert alert-info"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <ul class="nav nav-tabs mb-4" id="gestionTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active"
                    id="productos-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#productos"
                    type="button"
                    role="tab">
                🛒 Productos
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button class="nav-link"
                    id="categorias-tab"
                    data-bs-toggle="tab"
                    data-bs-target="#categorias"
                    type="button"
                    role="tab">
                🏷️ Categorías
            </button>
        </li>
    </ul>

    <div class="tab-content">

        <!-- PRODUCTOS -->
        <div class="tab-pane fade show active" id="productos" role="tabpanel">

            <div class="panel-card">
                <h3 class="section-title">➕ Agregar Producto</h3>

                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="accion" value="agregar_producto">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre:</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Precio:</label>
                            <input type="number" name="precio" step="0.01" class="form-control" required>
                        </div>

                        <div class="col-md-3 mb-3">
                            <label class="form-label">Stock:</label>
                            <input type="number" name="stock" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Descripción:</label>
                        <textarea name="descripcion" class="form-control" rows="3" required></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Categoría:</label>
                            <select name="categoria" class="form-select" required>
                                <option value="">Selecciona una categoría</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= $cat['_id'] ?>">
                                        <?= htmlspecialchars($cat["nombre"]) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Imagen:</label>
                            <input type="file" name="imagen" class="form-control" accept="image/*" required>
                        </div>
                    </div>

                    <button class="btn btn-primary">
                        Guardar Producto
                    </button>
                </form>
            </div>

            <div class="panel-card">
                <h3 class="section-title">📦 Productos Registrados</h3>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover shadow-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Imagen</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Precio</th>
                                <th>Stock</th>
                                <th>Categoría</th>
                                <th style="width:170px;">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php foreach ($productos as $p): ?>

                            <?php
                            $catNombre = "Sin categoría";

                            if (isset($p["categoria_id"]) && $p["categoria_id"] instanceof ObjectId) {
                                foreach ($categorias as $c) {
                                    if ((string)$c["_id"] === (string)$p["categoria_id"]) {
                                        $catNombre = $c["nombre"] ?? "Sin categoría";
                                        break;
                                    }
                                }
                            }
                            ?>

                            <tr>
                                <td>
                                    <?php if (!empty($p["imagen"])): ?>
                                        <img src="<?= htmlspecialchars($p["imagen"]) ?>"
                                             class="producto-img"
                                             alt="Producto">
                                    <?php endif; ?>
                                </td>

                                <td><?= htmlspecialchars($p["nombre"] ?? '') ?></td>

                                <td><?= htmlspecialchars($p["descripcion"] ?? '') ?></td>

                                <td>$<?= number_format((float)($p["precio"] ?? 0), 2) ?></td>

                                <td><?= (int)($p["stock"] ?? 0) ?></td>

                                <td><?= htmlspecialchars($catNombre) ?></td>

                                <td>
                                    <a href="editar_producto.php?id=<?= $p['_id'] ?>"
                                       class="btn btn-warning btn-sm">
                                        Editar
                                    </a>

                                    <a href="eliminar_producto.php?id=<?= $p['_id'] ?>"
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('¿Eliminar este producto?');">
                                        Eliminar
                                    </a>
                                </td>
                            </tr>

                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

        <!-- CATEGORÍAS -->
        <div class="tab-pane fade" id="categorias" role="tabpanel">

            <div class="panel-card">
                <h3 class="section-title">➕ Agregar Categoría</h3>

                <form method="post">
                    <input type="hidden" name="accion" value="agregar_categoria">

                    <div class="mb-3">
                        <label class="form-label">Nueva categoría:</label>
                        <input type="text" name="nombre_categoria" class="form-control" required>
                    </div>

                    <button class="btn btn-success">
                        Agregar Categoría
                    </button>
                </form>
            </div>

            <div class="panel-card">
                <h3 class="section-title">🏷️ Categorías Registradas</h3>

                <div class="table-responsive">
                    <table class="table table-bordered table-hover shadow-sm">
                        <thead class="table-light">
                            <tr>
                                <th>Nombre</th>
                                <th style="width:170px;">Acciones</th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($categorias as $cat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($cat["nombre"]) ?></td>

                                    <td>
                                        <a href="categoria_editar.php?id=<?= $cat['_id'] ?>"
                                           class="btn btn-warning btn-sm">
                                            Editar
                                        </a>

                                        <a href="categoria_eliminar.php?id=<?= $cat['_id'] ?>"
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('¿Eliminar categoría?');">
                                            Eliminar
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>

                    </table>
                </div>
            </div>

        </div>

    </div>

    <a href="panel_admin.php" class="btn-volver mt-3 d-inline-block">
        Volver
    </a>

</div>

<footer class="text-center mb-4 text-muted">
    <strong>© 2025 CERAMISHOP</strong> - Todos los derechos reservados
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
