<?php
include("verificar_acceso.php");
verificarSesion('administrador');

$nombre_admin = $_SESSION['usuario']['nombre'] ?? 'Administrador';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body{margin:0;padding:0;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;background:#f9fafb;color:#333;}
        .container-box{max-width:1100px;margin:50px auto;padding:30px;background:#fff;border-radius:20px;box-shadow:0 8px 20px rgba(0,0,0,.08);}
        .title{font-size:2rem;color:#b71c1c;margin:0 0 10px 0;text-align:center;font-weight:700}
        .subtitle{text-align:center;color:#555;margin-bottom:30px}
        .grid{
            display:grid;
            grid-template-columns:repeat(auto-fit,minmax(220px,1fr));
            gap:18px;
        }
        .tile{
            background:#e53935;color:#fff;border-radius:16px;padding:18px 16px;text-decoration:none;
            font-weight:600;display:flex;align-items:center;gap:12px;justify-content:center;
            box-shadow:0 6px 14px rgba(229,57,53,.2);transition:transform .2s ease, box-shadow .2s ease, background .2s ease;
        }
        .tile:hover{background:#b71c1c;transform:translateY(-2px);box-shadow:0 10px 18px rgba(183,28,28,.25);color:#fff}
        .tile i{font-size:1.25rem}
        footer{font-size:.9rem;color:#888}
        hr{border:none;border-top:1px solid #e5e7eb;margin:40px 0 10px}
    </style>
</head>
<body>

<div class="container-box">
    <h1 class="title">Bienvenido, <?= htmlspecialchars($nombre_admin, ENT_QUOTES, 'UTF-8'); ?> 👨‍💼</h1>
    <p class="subtitle">Este es tu panel de administración de CERAMICENTRO.</p>

    <div class="grid">
        <a class="tile" href="ver_productos.php"><i class="bi bi-box-seam"></i> Gestionar Productos</a>
        <a class="tile" href="agregar_producto.php"><i class="bi bi-plus-circle"></i> Añadir Producto</a>
        <a class="tile" href="crear_admin.php"><i class="bi bi-person-gear"></i> Añadir Administrador</a>
        <a class="tile" href="ver_usuarios.php"><i class="bi bi-people"></i> Ver Usuarios</a>
        <a class="tile" href="historial_productos.php"><i class="bi bi-clock-history"></i> Historial Modificaciones</a>
        <a class="tile" href="historial_pedidos_admin.php"><i class="bi bi-receipt"></i> Historial Pedidos</a>
        <a class="tile" href="estadisticas_ventas.php"><i class="bi bi-graph-up"></i> Estadística Ventas</a>
        <a class="tile" href="logout.php"><i class="bi bi-box-arrow-right"></i> Cerrar Sesión</a>
    </div>
</div>

<hr>
<br>
<center><b><footer>© 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados</footer></b></center>
<br>

</body>
</html>
