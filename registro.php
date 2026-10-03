<?php
require __DIR__ . '/vendor/autoload.php';
include("conexion_mongo.php");

$error = "";
$db = mongo();
$colUsuarios = $db->selectCollection('usuarios');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombre     = trim($_POST['nombre'] ?? '');
    $correo     = trim($_POST['correo'] ?? '');
    $contrasena = $_POST['contraseña'] ?? '';

    if ($nombre === '' || mb_strlen($nombre) < 3 || mb_strlen($nombre) > 50) {
        $error = "El nombre debe tener entre 3 y 50 caracteres.";
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = "Correo electrónico inválido.";
    } elseif (mb_strlen($contrasena) < 6) {
        $error = "La contraseña debe tener al menos 6 caracteres.";
    } else {
        $existe = $colUsuarios->findOne(
            ['correo' => $correo],
            ['projection' => ['_id' => 1], 'typeMap' => ['root' => 'array','document' => 'array','array' => 'array']]
        );

        if ($existe) {
            $error = "Este correo ya está registrado.";
        } else {
            $doc = [
                'nombre'     => $nombre,
                'correo'     => $correo,
                'contrasena' => password_hash($contrasena, PASSWORD_DEFAULT),
                'rol'        => 'cliente',
                'createdAt'  => nowUTC()
            ];

            try {
                $colUsuarios->insertOne($doc);
                header("Location: login.php");
                exit();
            } catch (MongoDB\Driver\Exception\BulkWriteException $e) {
                $error = "Error al registrar. Es posible que el correo ya exista.";
            } catch (Throwable $e) {
                $error = "Error al registrar. Intenta más tarde.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(to right, #fff0f0, #ffffff); font-family: 'Segoe UI', sans-serif; }
        .logo { width: 85px; height: auto; margin-bottom: 10px; border-radius: 10px; }
        .registro-container { max-width: 450px; margin: 60px auto; background-color: #ffffff; border-radius: 15px; box-shadow: 0 12px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .registro-header { background: linear-gradient(to right, #c62828, #b71c1c); padding: 25px; text-align: center; color: white; }
        .registro-header img { width: 80px; height: auto; margin-bottom: 10px; }
        .btn-red { background-color: #c62828; border: none; font-weight: bold; transition: background-color 0.3s ease; }
        .btn-red:hover { background-color: #a71515; }
        .error-message { color: #b00020; background-color: #ffebee; padding: 10px; border-radius: 10px; margin-bottom: 15px; text-align: center; }
        .footer-links { font-size: 0.9rem; text-align: center; margin-top: 20px; }
        .footer-links a { color: #c62828; text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
        footer { font-size: 0.9rem; color: #888; }
        .form-label { font-weight: 600; }
    </style>
</head>
<body>

<div class="registro-container">
    <div class="registro-header">
        <img src="imagenes/logo.jpeg" alt="Logo CERAMICENTRO" class="logo">
        <h2>Crea tu cuenta</h2>
    </div>

    <form method="post" class="p-4" novalidate>
        <?php if (!empty($error)) echo "<div class='error-message'>".htmlspecialchars($error)."</div>"; ?>

        <div class="mb-3">
            <label class="form-label">Nombre completo:</label>
            <input type="text" name="nombre" class="form-control" required minlength="3" maxlength="50" placeholder="Ej. Juan Pérez" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Correo electrónico:</label>
            <input type="email" name="correo" class="form-control" required placeholder="ejemplo@correo.com" value="<?= htmlspecialchars($_POST['correo'] ?? '') ?>">
        </div>

        <div class="mb-3">
            <label class="form-label">Contraseña:</label>
            <input type="password" name="contraseña" class="form-control" required minlength="6" placeholder="••••••••">
        </div>

        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-red text-white">📝 Registrarme</button>
        </div>

        <div class="footer-links">
            <p>¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a></p>
            <p><a href="index.html">Volver al inicio</a></p>
        </div>
    </form>
</div>

<hr><br>
<center><b><footer>© 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados</footer></b></center>
<br>
</body>
</html>
