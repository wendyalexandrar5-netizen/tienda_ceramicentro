<?php
include("verificar_acceso.php");
verificarSesion('administrador');

require __DIR__ . '/vendor/autoload.php';
include("conexion_mongo.php");

use MongoDB\BSON\UTCDateTime;

$db = mongo();
$colPedidos       = $db->selectCollection('pedidos');
$colDetalle       = $db->selectCollection('pedido_detalle');

$pedidoIds = $colDetalle->distinct('pedido_id');
$total_ventas = count($pedidoIds);

$aggStats = $colDetalle->aggregate([
    [
        '$group' => [
            '_id'              => null,
            'total_productos'  => ['$sum' => '$cantidad'],
            'total_ganancias'  => ['$sum' => '$subtotal']
        ]
    ]
]);

$statsDoc = $aggStats->toArray();
if (!empty($statsDoc)) {
    $total_productos = (int)($statsDoc[0]['total_productos'] ?? 0);
    $total_ganancias = (float)($statsDoc[0]['total_ganancias'] ?? 0);
} else {
    $total_productos = 0;
    $total_ganancias = 0.0;
}

$ventas_mensuales = array_fill(0, 12, 0);

$aggMensual = $colDetalle->aggregate([
    [
        '$lookup' => [
            'from'         => 'pedidos',
            'localField'   => 'pedido_id',
            'foreignField' => '_id',
            'as'           => 'pedido'
        ]
    ],
    ['$unwind' => '$pedido'],
    [
        '$group' => [
            '_id'      => ['mes' => ['$month' => '$pedido.fecha']],
            'ganancia' => ['$sum' => '$subtotal']
        ]
    ]
]);

foreach ($aggMensual as $doc) {
    $mes = (int)($doc['_id']['mes'] ?? 0); // 1..12
    $ganancia = (float)($doc['ganancia'] ?? 0);
    if ($mes >= 1 && $mes <= 12) {
        $ventas_mensuales[$mes - 1] = $ganancia;
    }
}

$top_productos = [];
$aggTop = $colDetalle->aggregate([
    [
        '$group' => [
            '_id'            => '$nombre_producto',
            'total_vendidos' => ['$sum' => '$cantidad']
        ]
    ],
    ['$sort'  => ['total_vendidos' => -1]],
    ['$limit' => 5]
]);

foreach ($aggTop as $doc) {
    $top_productos[] = [
        'nombre'         => (string)($doc['_id'] ?? 'Sin nombre'),
        'total_vendidos' => (int)($doc['total_vendidos'] ?? 0)
    ];
}

$meses = ["Enero","Febrero","Marzo","Abril","Mayo","Junio","Julio","Agosto","Septiembre","Octubre","Noviembre","Diciembre"];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estadísticas de Ventas - CERAMICENTRO</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f3f4f6;
        }
        .header {
            background-color: #c62828;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h2 {
            margin-bottom: 5px;
            color: #333;
        }
        .btn-volver {
            background-color: #c62828;
            color: white;
        }
        footer {
            font-size: 0.9rem;
            color: #888;
            margin-top: 40px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1><i class="bi bi-graph-up"></i> Estadísticas de Ventas</h1>
</div>

<div class="container mt-5">
    <div class="row text-center mb-4">
        <div class="col-md-4">
            <div class="stat-card">
                <h2><?= $total_ventas ?></h2>
                <p>Ventas Realizadas</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <h2><?= $total_productos ?></h2>
                <p>Productos Vendidos</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="stat-card">
                <h2>$<?= number_format($total_ganancias, 2) ?></h2>
                <p>Ganancia Total</p>
            </div>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4 class="card-title">Ganancias Mensuales</h4>
            <canvas id="graficoVentas" height="100"></canvas>
        </div>
    </div>

    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <h4 class="card-title">Top 5 Productos Más Vendidos</h4>
            <table class="table table-bordered">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Producto</th>
                    <th>Cantidad Vendida</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($top_productos as $i => $prod): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><?= htmlspecialchars($prod['nombre']) ?></td>
                        <td><?= $prod['total_vendidos'] ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
            </table>
        </div>
    </div>

    <a href="exportar_estadisticas_excel.php" class="btn btn-success mb-3">
        <i class="bi bi-file-earmark-excel"></i> Exportar a Excel
    </a>
    <br>
    <a href="panel_admin.php" class="btn btn-volver">
        <i class="bi bi-arrow-left-circle"></i> Volver
    </a>
</div>

<script>
const ctx = document.getElementById('graficoVentas').getContext('2d');
const grafico = new Chart(ctx, {
    type: 'bar',
    data: {
        labels: <?= json_encode($meses) ?>,
        datasets: [{
            label: 'Ganancias ($)',
            data: <?= json_encode($ventas_mensuales) ?>,
            backgroundColor: '#e53935'
        }]
    },
    options: {
        responsive: true,
        scales: {
            y: { beginAtZero: true }
        }
    }
});
</script>

<hr>
<center><footer>© 2025 <strong>CERAMICENTRO</strong> - Todos los derechos reservados</footer></center>
<br>
</body>
</html>
