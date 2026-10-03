<?php 
include("verificar_acceso.php");
verificarSesion('administrador');

require __DIR__ . '/vendor/autoload.php';
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

$db = mongo();
$colPedidos   = $db->selectCollection("pedidos");
$colUsuarios  = $db->selectCollection("usuarios");
$colDetalles  = $db->selectCollection("pedido_detalle");
$colProductos = $db->selectCollection("productos");

$fecha_desde     = $_GET['desde']   ?? '';
$fecha_hasta     = $_GET['hasta']   ?? '';
$buscar_cliente  = $_GET['cliente'] ?? '';

$filterPedidos = [];

if (!empty($fecha_desde)) {
    $filterPedidos['fecha']['$gte'] = new UTCDateTime(strtotime($fecha_desde . " 00:00:00") * 1000);
}
if (!empty($fecha_hasta)) {
    $filterPedidos['fecha']['$lte'] = new UTCDateTime(strtotime($fecha_hasta . " 23:59:59") * 1000);
}

if (!empty($buscar_cliente)) {
    $regex = new MongoDB\BSON\Regex($buscar_cliente, 'i');
    $cursorUsers = $colUsuarios->find(
        ['nombre' => $regex],
        ['typeMap' => ['root' => 'array', 'document' => 'array']]
    );
    $usuariosEncontrados = iterator_to_array($cursorUsers, false);

    if (!empty($usuariosEncontrados)) {
        $idsUsuarios = [];
        foreach ($usuariosEncontrados as $u) {
            if (isset($u['_id'])) {
                $idsUsuarios[] = $u['_id'];
            }
        }
        if (!empty($idsUsuarios)) {
            $filterPedidos['usuario_id'] = ['$in' => $idsUsuarios];
        }
    } else {
        $pedidos = [];
        goto RENDER_HTML;
    }
}


$cursorPedidos = $colPedidos->find(
    $filterPedidos,
    [
        'sort'    => ['fecha' => -1],
        'typeMap' => ['root' => 'array', 'document' => 'array']
    ]
);

$pedidos = [];
$pedidoIds = [];
$usuarioIds = [];

foreach ($cursorPedidos as $p) {
    $idStr = (string)$p['_id'];
    $pedidos[$idStr] = [
        'fecha'          => $p['fecha']->toDateTime()->format('Y-m-d H:i:s'),
        'total'          => (float)($p['total'] ?? 0),
        'usuario_id'     => $p['usuario_id'] ?? null,
        'nombre_usuario' => '',
        'productos'      => []
    ];
    $pedidoIds[] = $p['_id'];
    if (!empty($p['usuario_id'])) {
        $usuarioIds[(string)$p['usuario_id']] = $p['usuario_id'];
    }
}

if (empty($pedidos)) {
    goto RENDER_HTML;
}


if (!empty($usuarioIds)) {
    $cursorUsuarios = $colUsuarios->find(
        ['_id' => ['$in' => array_values($usuarioIds)]],
        ['typeMap' => ['root' => 'array', 'document' => 'array']]
    );
    $mapUsuarios = [];
    foreach ($cursorUsuarios as $u) {
        $mapUsuarios[(string)$u['_id']] = $u['nombre'] ?? 'Sin nombre';
    }

    foreach ($pedidos as $idPedidoStr => &$info) {
        $uid = $info['usuario_id'];
        if ($uid instanceof ObjectId) {
            $uidStr = (string)$uid;
            $info['nombre_usuario'] = $mapUsuarios[$uidStr] ?? 'Desconocido';
        } else {
            $info['nombre_usuario'] = 'Desconocido';
        }
    }
    unset($info);
}

$cursorDetalles = $colDetalles->find(
    ['pedido_id' => ['$in' => $pedidoIds]],
    ['typeMap' => ['root' => 'array', 'document' => 'array']]
);

$detalles = iterator_to_array($cursorDetalles, false);

$productIds = [];
foreach ($detalles as $d) {
    if (!empty($d['producto_id']) && $d['producto_id'] instanceof ObjectId) {
        $productIds[(string)$d['producto_id']] = $d['producto_id'];
    }
}

$mapProductos = [];
if (!empty($productIds)) {
    $cursorProd = $colProductos->find(
        ['_id' => ['$in' => array_values($productIds)]],
        ['typeMap' => ['root' => 'array', 'document' => 'array']]
    );
    foreach ($cursorProd as $pr) {
        $mapProductos[(string)$pr['_id']] = [
            'nombre' => $pr['nombre'] ?? ($pr['descripcion'] ?? 'Producto'),
            'imagen' => $pr['imagen'] ?? ''
        ];
    }
}

foreach ($detalles as $d) {
    $idPedidoStr = (string)$d['pedido_id'];
    if (!isset($pedidos[$idPedidoStr])) {
        continue;
    }

    $idProdStr = isset($d['producto_id']) ? (string)$d['producto_id'] : '';
    $infoProd  = $mapProductos[$idProdStr] ?? null;

    $nombreProd = $infoProd['nombre'] ?? ($d['nombre_producto'] ?? 'Producto');
    $imagenProd = $infoProd['imagen'] ?? '';

    $cantidad       = (int)($d['cantidad'] ?? 0);
    $precioUnitario = (float)($d['precio_unitario'] ?? 0);

    $pedidos[$idPedidoStr]['productos'][] = [
        'nombre'          => $nombreProd,
        'imagen'          => $imagenProd,
        'cantidad'        => $cantidad,
        'precio_unitario' => $precioUnitario
    ];
}

RENDER_HTML:
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Pedidos - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: #f4f6f9;
            font-family: 'Segoe UI', sans-serif;
        }
        .container {
            max-width: 1000px;
            margin-top: 50px;
        }
        .pedido-card {
            background: #ffffff;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
            border-left: 6px solid #dc3545;
            transition: transform 0.2s;
        }
        .pedido-card:hover {
            transform: scale(1.01);
        }
        .pedido-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #e9ecef;
            padding-bottom: 10px;
        }
        .producto-img {
            width: 55px;
            height: 55px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .btn-volver {
            background-color: #e53935;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 10px;
            margin-top: 20px;
        }
        .btn-volver:hover {
            background-color: #b71c1c;
        }
        h2.title {
            font-weight: 700;
            color: #333;
            margin-bottom: 30px;
        }
        .filtros {
            background: #fff;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            margin-bottom: 40px;
        }
        footer {
            font-size: 0.9rem;
            color: #888;
        }
    </style>
</head>
<body>
<div class="container">
    <h2 class="text-center title">Historial de Pedidos</h2>

    <form method="get" class="filtros row g-3">
        <div class="col-md-4">
            <label class="form-label">Desde</label>
            <input type="date" name="desde" class="form-control" value="<?= htmlspecialchars($fecha_desde) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Hasta</label>
            <input type="date" name="hasta" class="form-control" value="<?= htmlspecialchars($fecha_hasta) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Buscar por cliente</label>
            <input type="text" name="cliente" class="form-control" placeholder="Nombre del cliente" value="<?= htmlspecialchars($buscar_cliente) ?>">
        </div>
        <div class="col-12 text-end">
            <button type="submit" class="btn btn-outline-danger">Filtrar</button>
            <a href="historial_pedidos_admin.php" class="btn btn-secondary">Restablecer</a>
        </div>
    </form>

    <?php if (empty($pedidos)): ?>
        <div class="alert alert-warning text-center">No se han encontrado pedidos con los criterios ingresados.</div>
    <?php else: ?>
        <?php foreach ($pedidos as $id => $pedido): ?>
            <div class="pedido-card">
                <div class="pedido-header">
                    <div>
                        <h5 class="mb-1">Pedido #<?= htmlspecialchars($id) ?></h5>
                        <small class="text-muted"><?= htmlspecialchars(date("d/m/Y H:i", strtotime($pedido['fecha']))) ?></small>
                    </div>
                    <div class="text-end">
                        <span class="fw-bold text-dark">Cliente:</span> <?= htmlspecialchars($pedido['nombre_usuario']) ?><br>
                        <span class="fw-bold text-dark">Total:</span> $<?= number_format($pedido['total'], 0, ',', '.') ?>
                    </div>
                </div>
                <table class="table table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Producto</th>
                            <th>Imagen</th>
                            <th>Cantidad</th>
                            <th>Precio Unitario</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pedido['productos'] as $prod): ?>
                            <tr>
                                <td><?= htmlspecialchars($prod['nombre']) ?></td>
                                <td>
                                    <?php if (!empty($prod['imagen'])): ?>
                                        <img src="<?= htmlspecialchars($prod['imagen']) ?>" class="producto-img" alt="Imagen">
                                    <?php else: ?>
                                        <span class="text-muted">Sin imagen</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= (int)$prod['cantidad'] ?></td>
                                <td>$<?= number_format($prod['precio_unitario'], 0, ',', '.') ?></td>
                                <td>$<?= number_format($prod['precio_unitario'] * $prod['cantidad'], 0, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
    <a href="panel_admin.php" class="btn btn-volver">
        <i class="bi bi-arrow-left-circle"></i> Volver
    </a>
</div>

<hr>
<br>
<center><b><footer>© 2025 <strong>CERAMISHOP</strong> - Todos los derechos reservados</footer></b></center>
<br>
</body>
</html>
