<?php
require __DIR__ . '/vendor/autoload.php';
require 'conexion_mongo.php';

use MongoDB\BSON\UTCDateTime;

session_start();

if (!isset($_SESSION['usuario']) || $_SESSION['usuario']['rol'] !== 'administrador') {
    header("Location: login.php");
    exit();
}

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nombre = trim($_POST['nombre'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $passwordPlano = $_POST['contraseña'] ?? $_POST['contrasena'] ?? '';

    if (mb_strlen($nombre) < 3) {
        $error = "El nombre debe tener al menos 3 caracteres.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo inválido.";
    } elseif (strlen($passwordPlano) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        try {
            $db = mongo();
            $usuarios = $db->selectCollection('usuarios');

            $existe = $usuarios->countDocuments(['correo' => $correo], ['limit' => 1]);

            if ($existe > 0) {
                $error = "El correo electrónico ya está registrado.";
            } else {
                $hash = password_hash($passwordPlano, PASSWORD_DEFAULT);

                $doc = [
                    'nombre'     => $nombre,
                    'correo'     => $correo,
                    'contrasena' => $hash,
                    'rol'        => 'administrador',
                    'createdAt'  => new UTCDateTime((int)(microtime(true) * 1000))
                ];

                $usuarios->insertOne($doc);

                header("Location: ver_usuarios.php?registrado=1");
                exit;
            }

        } catch (Throwable $e) {
            $error = "Error al registrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Registrar Administrador - CERAMICENTRO</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f4f6f9; font-family: 'Segoe UI', Roboto, sans-serif; }
    .container {
      max-width: 520px; background: #fff; margin: 60px auto; padding: 40px 35px;
      border-radius: 20px; box-shadow: 0 20px 40px rgba(0,0,0,0.07);
    }
    h2 { text-align:center; color:#c62828; font-weight:600; margin-bottom:30px; }
    .form-control { border-radius:10px; }
    .btn-primary { background:#e53935; border:none; width:100%; padding:12px; border-radius:12px; font-weight:bold; }
    .btn-primary:hover { background:#c62828; }
    .btn-volver { display:inline-block; background:#e53935; color:#fff; padding:10px 16px; border-radius:12px; margin-top:25px; text-decoration:none; }
    .btn-volver:hover { background:#c62828; }
  </style>
</head>
<body>
<div class="container">
  <h2>Registrar Administrador</h2>

  <?php if (!empty($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div class="mb-3">
      <label class="form-label">Nombre completo</label>
      <input type="text" name="nombre" class="form-control" required minlength="3">
    </div>

    <div class="mb-3">
      <label class="form-label">Correo</label>
      <input type="email" name="correo" class="form-control" required>
    </div>

    <div class="mb-3">
      <label class="form-label">Contraseña</label>
      <input type="password" name="contraseña" class="form-control" required minlength="6">
    </div>

    <button type="submit" class="btn btn-primary">Registrar</button>
  </form>

  <a href="panel_admin.php" class="btn-volver mt-3">Volver</a>
</div>
</body>
</html>
