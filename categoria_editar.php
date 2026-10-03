<?php
require __DIR__ . '/vendor/autoload.php';
include("verificar_acceso.php");
verificarSesion("administrador");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

function isValidObjectId($id) {
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id);
}

if (!isset($_GET['id']) || !isValidObjectId($_GET['id'])) {
    header("Location: agregar_producto.php?mensaje=ID de categoría inválido");
    exit;
}

$id = new ObjectId($_GET['id']);

$db = mongo();
$colCategorias = $db->selectCollection("categorias");

$categoria = $colCategorias->findOne(["_id" => $id]);

if (!$categoria) {
    header("Location: agregar_producto.php?mensaje=Categoría no encontrada");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nuevoNombre = trim($_POST["nombre"] ?? '');

    if ($nuevoNombre === '') {
        $error = "El nombre no puede estar vacío.";
    } else {

        $colCategorias->updateOne(
            ["_id" => $id],
            ['$set' => ["nombre" => $nuevoNombre]]
        );

        header("Location: agregar_producto.php?mensaje=Categoría actualizada correctamente");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Editar Categoría</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">

<div class="container mt-4">

    <h2>Editar Categoría</h2>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" class="card p-4 shadow-sm">

        <label class="form-label">Nuevo nombre:</label>
        <input type="text" name="nombre" class="form-control mb-3"
               value="<?= htmlspecialchars($categoria['nombre']) ?>" required>

        <button class="btn btn-primary">Guardar Cambios</button>
        <a href="agregar_producto.php" class="btn btn-secondary mt-2">Cancelar</a>

    </form>

</div>

</body>
</html>
