<?php
require __DIR__ . '/vendor/autoload.php';
include("conexion_mongo.php");
session_start();

$db = mongo();
$colUsuarios = $db->selectCollection('usuarios');

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $correoInput = trim($_POST['correo'] ?? '');
    $correoNorm   = mb_strtolower($correoInput, 'UTF-8');
    $contrasena   = $_POST['contraseña'] ?? '';

    $usuario = $colUsuarios->findOne(
        ['correo' => $correoNorm],
        [
            'projection' => [
                '_id'        => 1,
                'nombre'     => 1,
                'rol'        => 1,
                'contraseña' => 1,
                'contrasena' => 1
            ],
            'typeMap' => ['root'=>'array','document'=>'array','array'=>'array'],
            'collation' => ['locale' => 'es', 'strength' => 2] // insensible a may/min
        ]
    );


    if ($usuario) {
        $hash = '';
        if (isset($usuario['contraseña']) && is_string($usuario['contraseña'])) {
            $hash = $usuario['contraseña'];
        } elseif (isset($usuario['contrasena']) && is_string($usuario['contrasena'])) {
            $hash = $usuario['contrasena'];
        }

        if ($hash !== '' && password_verify($contrasena, $hash)) {
            $_SESSION['usuario'] = [
                'id'     => (string)$usuario['_id'],
                'nombre' => $usuario['nombre'] ?? '',
                'correo' => $correoNorm,
                'rol'    => $usuario['rol'] ?? ''
            ];

            if (($_SESSION['usuario']['rol'] ?? '') === 'administrador') {
                header("Location: panel_admin.php"); exit;
            }
            if (($_SESSION['usuario']['rol'] ?? '') === 'cliente') {
                header("Location: tienda.php"); exit;
            }

            $error = "Rol no reconocido.";
        } else {
            $error = "Contraseña incorrecta.";
        }
    } else {
        $error = "Usuario no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>✨ Iniciar Sesión | CERAMICENTRO ✨</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(to right, #ffe6e6, #ffffff); font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .login-container { max-width: 420px; margin: 60px auto; background-color: #fff; border-radius: 18px; box-shadow: 0 12px 25px rgba(0,0,0,0.1); overflow: hidden; }
        .login-header { background: linear-gradient(to right, #c62828, #b71c1c); color: white; padding: 25px; text-align: center; }
        .logo { width: 80px; height: auto; margin-bottom: 10px; border-radius: 10px; }
        .login-header h2 { margin: 0; font-weight: bold; font-size: 1.8rem; }
        .form-label { font-weight: 600; }
        .btn-red { background-color: #c62828; border: none; font-weight: bold; transition: background-color 0.3s ease; }
        .btn-red:hover { background-color: #a71515; }
        .error-message { color: #b00020; background-color: #ffebee; padding: 10px; border-radius: 10px; margin-top: 10px; text-align: center; font-weight: 500; }
        .footer-links { font-size: 0.9rem; text-align: center; margin-top: 20px; }
        .footer-links a { color: #c62828; text-decoration: none; }
        .footer-links a:hover { text-decoration: underline; }
        footer { font-size: 0.9rem; color: #888; }
    </style>
</head>
<body>

<div class="login-container">
    <div class="login-header">
        <img src="imagenes/logo.jpeg" alt="Logo CERAMICENTRO" class="logo">
        <h2>Bienvenido a CERAMICENTRO</h2>
    </div>

    <form method="post" class="p-4">
        <?php if (isset($error)) echo "<div class='error-message'>".htmlspecialchars($error)."</div>"; ?>

        <div class="mb-3">
            <label class="form-label">Correo electrónico:</label>
            <input type="email" name="correo" class="form-control" placeholder="usuario@correo.com" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Contraseña:</label>
            <input type="password" name="contraseña" class="form-control" placeholder="••••••••" required>
        </div>
        <div class="d-grid mb-3">
            <button type="submit" class="btn btn-red text-white">🔐 Ingresar</button>
        </div>

        <div class="footer-links">
            <p>¿No tienes cuenta? <a href="registro.php">Regístrate aquí</a></p>
            <p><a href="index.html">Volver al inicio</a></p>
        </div>
    </form>
</div>
<hr>
<br>
<center><b><footer>© 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados</footer></b></center>
<br>
</body>
</html>
