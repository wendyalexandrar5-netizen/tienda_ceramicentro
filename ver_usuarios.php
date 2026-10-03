<?php
include("verificar_acceso.php");
verificarSesion('administrador');

require __DIR__ . '/vendor/autoload.php';
include("conexion_mongo.php");

$db = mongo();
$colUsuarios = $db->selectCollection('usuarios');

$cursor = $colUsuarios->find(
    [],
    [
        'sort' => ['nombre' => 1],
        'projection' => ['nombre' => 1, 'correo' => 1, 'rol' => 1],
        'typeMap' => ['root' => 'array', 'document' => 'array', 'array' => 'array']
    ]
);

$usuarios = iterator_to_array($cursor, false);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuarios Registrados - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .container { margin-top: 30px; }
        .btn-volver {
            top: 20px; left: 20px;
            background-color: #e53935; color: white; border: none;
            padding: 10px 15px; border-radius: 10px;
        }
        .btn-volver:hover { background-color: #b71c1c; color: white; }
        footer { font-size: 0.9rem; color: #888; }
    </style>
</head>
<body>
<div class="container">
    <center><h2>Lista de Usuarios Registrados</h2></center>

    <?php if (!empty($usuarios)): ?>
        <table class="table table-bordered table-striped mt-3">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Correo</th>
                    <th>Rol</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                    <tr>
                        <td><?= htmlspecialchars($u['nombre'] ?? '') ?></td>
                        <td><?= htmlspecialchars($u['correo'] ?? '') ?></td>
                        <td><?= htmlspecialchars($u['rol'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No hay usuarios registrados.</p>
    <?php endif; ?>

    <br>
    <a href="exportar_usuarios.php" class="btn btn-success">Exportar a Excel</a>
    <br><br>
    <a href="panel_admin.php" class="btn btn-volver">
        <i class="bi bi-arrow-left-circle"></i> Volver
    </a>
</div>

</body>
<hr>
<br>
<center><b><footer>© 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados</footer></b></center>
<br>
</html>
