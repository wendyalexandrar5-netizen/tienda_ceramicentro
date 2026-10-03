<?php 
require __DIR__ . '/vendor/autoload.php';
include("verificar_acceso.php");
verificarSesion('administrador');
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

function isValidObjectId($id){
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id);
}

$db = mongo();
$colProductos  = $db->selectCollection('productos');
$colCategorias = $db->selectCollection('categorias');
$colHist       = $db->selectCollection('historial_productos');

if (!isset($_GET['id']) || !isValidObjectId($_GET['id'])) {
    die("ID inválido.");
}

$id = new ObjectId($_GET['id']);
$producto = $colProductos->findOne(['_id' => $id]);
if (!$producto) {
    die("Producto no encontrado.");
}

$catCursor = $colCategorias->find([], ["sort" => ["nombre" => 1]]);
$categorias = iterator_to_array($catCursor, false);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre      = trim($_POST["nombre"] ?? '');
    $descripcion = trim($_POST["descripcion"] ?? '');
    $precio      = $_POST["precio"] ?? null;
    $stock       = $_POST["stock"] ?? null;
    $categoriaId = $_POST["categoria"] ?? '';

    $imagen_ruta = $producto['imagen'] ?? '';

    $errores = [];

    if ($nombre === '') $errores[] = "El nombre es obligatorio.";
    if ($descripcion === '') $errores[] = "La descripción es obligatoria.";
    if (!is_numeric($precio) || $precio <= 0) $errores[] = "Precio inválido.";
    if (!ctype_digit((string)$stock) || $stock < 0) $errores[] = "Stock inválido.";
    if (!isValidObjectId($categoriaId)) $errores[] = "Categoría inválida.";

    if (!empty($_FILES["imagen"]["name"])) {

        $ext = strtolower(pathinfo($_FILES["imagen"]["name"], PATHINFO_EXTENSION));
        $permitidas = ["jpg", "jpeg", "png", "gif", "webp"];

        if (!in_array($ext, $permitidas)) {
            $errores[] = "Formato de imagen no permitido.";
        } else {
            $nuevoNombre = time() . "_" . uniqid() . "." . $ext;
            $rutaNueva = "imagenes/" . $nuevoNombre;

            if (move_uploaded_file($_FILES["imagen"]["tmp_name"], $rutaNueva)) {
                $imagen_ruta = $rutaNueva;
            } else {
                $errores[] = "Error subiendo la imagen.";
            }
        }
    }

    if (empty($errores)) {

        $cambios = [];

        if ($producto["nombre"] !== $nombre)
            $cambios[] = "Nombre: {$producto['nombre']} → $nombre";

        if ((string)$producto["descripcion"] !== $descripcion)
            $cambios[] = "Descripción actualizada";

        if ((float)$producto["precio"] != (float)$precio)
            $cambios[] = "Precio: {$producto['precio']} → $precio";

        if ((int)$producto["stock"] != (int)$stock)
            $cambios[] = "Stock: {$producto['stock']} → $stock";

        if ((string)$producto["categoria_id"] !== $categoriaId) {
            $catAnt = $colCategorias->findOne(['_id' => $producto['categoria_id']])['nombre'] ?? 'N/A';
            $catNueva = $colCategorias->findOne(['_id' => new ObjectId($categoriaId)])['nombre'] ?? 'N/A';
            $cambios[] = "Categoría: $catAnt → $catNueva";
        }

        if ($producto["imagen"] !== $imagen_ruta)
            $cambios[] = "Imagen actualizada";

        if (empty($cambios)) {
            $error = "No se detectaron cambios.";
        } else {

            $colProductos->updateOne(
                ['_id' => $id],
                ['$set' => [
                    "nombre"        => $nombre,
                    "descripcion"   => $descripcion,
                    "precio"        => (float)$precio,
                    "stock"         => (int)$stock,
                    "categoria_id"  => new ObjectId($categoriaId),
                    "imagen"        => $imagen_ruta,
                    "actualizado_en"=> new UTCDateTime()
                ]]
            );

            $adminId = $_SESSION["usuario"]["id"] ?? null;
            $adminOid = (isValidObjectId($adminId)) ? new ObjectId($adminId) : null;
            $nombre_admin = $_SESSION["usuario"]["nombre"] ?? '';
            $producto_nom = $producto["nombre"] ?? $nombre;

            $colHist->insertOne([
                "id_admin"        => $adminOid,
                "nombre_admin"    => $nombre_admin,
                "id_producto"     => $id,
                "producto_nombre" => $producto_nom,
                "accion"          => "edito",
                "cambios"         => $cambios,
                "fecha"           => new UTCDateTime()
            ]);

            header("Location: agregar_producto.php?mensaje=Producto actualizado");
            exit;
        }
    } else {
        $error = implode("<br>", $errores);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Producto</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
<div class="container mt-4">

    <h2>Editar Producto</h2>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="card p-4 shadow-sm">

        <label class="form-label mt-2">Nombre:</label>
        <input type="text" name="nombre" class="form-control"
               value="<?= htmlspecialchars($producto['nombre']) ?>" required>

        <label class="form-label mt-2">Descripción:</label>
        <textarea name="descripcion" class="form-control" rows="3"><?= htmlspecialchars($producto['descripcion']) ?></textarea>

        <label class="form-label mt-2">Precio:</label>
        <input type="number" step="0.01" name="precio" class="form-control"
               value="<?= htmlspecialchars($producto['precio']) ?>" required>

        <label class="form-label mt-2">Stock:</label>
        <input type="number" name="stock" class="form-control"
               value="<?= htmlspecialchars($producto['stock']) ?>" required>

        <label class="form-label mt-2">Categoría:</label>
        <select name="categoria" class="form-select" required>
            <?php foreach ($categorias as $cat): ?>
                <option value="<?= $cat['_id'] ?>"
                    <?= ((string)$producto["categoria_id"] === (string)$cat["_id"]) ? "selected" : "" ?>>
                    <?= htmlspecialchars($cat["nombre"]) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <label class="form-label mt-3">Imagen Actual:</label><br>
        <img src="<?= htmlspecialchars($producto['imagen']) ?>" width="180" class="mb-3 rounded">

        <label class="form-label">¿Subir nueva imagen?</label>
        <input type="file" name="imagen" class="form-control">

        <button class="btn btn-primary mt-3">Guardar Cambios</button>
        <a href="agregar_producto.php" class="btn btn-danger mt-3">Cancelar</a>
    </form>

</div>

<hr><br>
<center><b><footer>© 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados</footer></b></center>
<br>

</body>
</html>
