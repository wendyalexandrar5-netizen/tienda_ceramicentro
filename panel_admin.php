<?php
/** Panel de administración: dashboard con indicadores reales de MongoDB. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/pedidos.php';
requerir_sesion('administrador');
liberar_pedidos_vencidos(); // devuelve el inventario de pedidos sin pagar cuya reserva venció

$db = mongo();
$colPedidos = $db->selectCollection('pedidos');
$colProductos = $db->selectCollection('productos');

// Ventas efectivas (pedidos pagados o en proceso de entrega). Los pedidos antiguos sin estado cuentan como pagados.
$filtroVentas = ['$or' => [['estado' => ['$in' => estados_venta()]], ['estado' => ['$exists' => false]]]];
$agg = $colPedidos->aggregate([
    ['$match' => $filtroVentas],
    ['$group' => ['_id' => null, 'total' => ['$sum' => '$total'], 'n' => ['$sum' => 1]]],
])->toArray();
$ventasTotal = (float)($agg[0]['total'] ?? 0);
$ventasN = (int)($agg[0]['n'] ?? 0);

$inicioMes = new DateTime('first day of this month 00:00:00', new DateTimeZone('America/Bogota'));
$aggMes = $colPedidos->aggregate([
    ['$match' => $filtroVentas + ['fecha' => ['$gte' => new \MongoDB\BSON\UTCDateTime($inicioMes->getTimestamp() * 1000)]]],
    ['$group' => ['_id' => null, 'total' => ['$sum' => '$total'], 'n' => ['$sum' => 1]]],
])->toArray();
$ventasMes = (float)($aggMes[0]['total'] ?? 0);

$pendientes = $colPedidos->countDocuments(['estado' => ['$in' => [ESTADO_PENDIENTE, ESTADO_PAGADO, ESTADO_PREPARANDO]]]);
$totalProductos = $colProductos->countDocuments([]);
$agotados = $colProductos->countDocuments(['stock' => ['$lte' => 0]]);
$stockBajo = $colProductos->countDocuments(['stock' => ['$gt' => 0, '$lte' => 5]]);
$clientes = $db->selectCollection('usuarios')->countDocuments(['rol' => 'cliente']);
$mensajesNuevos = 0;
try {
    $mensajesNuevos = $db->selectCollection('mensajes_contacto')->countDocuments(['leido' => false]);
} catch (Throwable $e) {
}

$ultimos = iterator_to_array($colPedidos->find([], ['sort' => ['fecha' => -1], 'limit' => 6]), false);
$nombres = [];
$uids = array_values(array_filter(array_map(fn($p) => $p['usuario_id'] ?? null, $ultimos)));
if ($uids) {
    foreach ($db->selectCollection('usuarios')->find(['_id' => ['$in' => $uids]], ['projection' => ['nombre' => 1]]) as $u) {
        $nombres[(string)$u['_id']] = (string)($u['nombre'] ?? '');
    }
}
$bajos = iterator_to_array($colProductos->find(['stock' => ['$lte' => 5]], ['sort' => ['stock' => 1, 'nombre' => 1], 'limit' => 6, 'projection' => ['nombre' => 1, 'stock' => 1]]), false);

admin_inicio('Dashboard', 'dashboard', ['acciones' => '<a href="' . e(url('agregar_producto.php')) . '" class="btn btn-cs"><i class="bi bi-plus-circle" aria-hidden="true"></i> Añadir producto</a>']);
$kpis = [
    ['cash-coin', dinero($ventasTotal), 'Ventas totales (' . $ventasN . ' pedidos)', ''],
    ['calendar3', dinero($ventasMes), 'Ventas de este mes', ''],
    ['hourglass-split', $pendientes, 'Pedidos por gestionar', $pendientes ? 'kpi-alerta' : ''],
    ['box-seam', $totalProductos, 'Productos en catálogo', ''],
    ['exclamation-triangle', $agotados . ' / ' . $stockBajo, 'Agotados / stock bajo (≤5)', ($agotados || $stockBajo) ? 'kpi-alerta' : ''],
    ['people', $clientes, 'Clientes registrados', ''],
];
?>
<p class="text-secondary">Bienvenido, <?= e(usuario_actual()['nombre'] ?? 'Administrador') ?>. Este es el resumen de CERAMISHOP.</p>
<div class="row g-3 mb-4">
    <?php foreach ($kpis as [$icono, $valor, $etiqueta, $clase]): ?>
    <div class="col-sm-6 col-xl-4">
        <div class="kpi <?= e($clase) ?>">
            <div class="kpi-icono" aria-hidden="true"><i class="bi bi-<?= e($icono) ?>"></i></div>
            <div><div class="kpi-valor"><?= e($valor) ?></div><div class="kpi-etiqueta"><?= e($etiqueta) ?></div></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php if ($mensajesNuevos): ?>
<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <span><i class="bi bi-envelope" aria-hidden="true"></i> Tienes <?= (int)$mensajesNuevos ?> mensaje(s) de contacto sin leer.</span>
    <a class="btn btn-sm btn-outline-primary" href="<?= e(url('mensajes_contacto.php')) ?>">Ver mensajes</a>
</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-7">
        <div class="cs-panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="cs-panel-titulo mb-0">Últimos pedidos</h2>
                <a href="<?= e(url('historial_pedidos_admin.php')) ?>" class="small">Ver todos</a>
            </div>
            <?php if (!$ultimos): ?>
            <p class="text-secondary mb-0">Todavía no hay pedidos.</p>
            <?php else: ?>
            <div class="table-responsive" tabindex="0" role="region" aria-label="Tabla (desplazable horizontalmente)">
                <table class="table table-hover align-middle tabla-apilable mb-0">
                    <thead><tr><th scope="col">Pedido</th><th scope="col">Cliente</th><th scope="col">Fecha</th><th scope="col">Estado</th><th scope="col" class="text-end">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($ultimos as $p): ?>
                        <tr>
                            <td data-label="Pedido"><a href="<?= e(url('admin_pedido.php', ['id' => (string)$p['_id']])) ?>"><?= e(pedido_numero($p)) ?></a></td>
                            <td data-label="Cliente"><?= e($nombres[(string)($p['usuario_id'] ?? '')] ?? ($p['cliente']['nombre'] ?? 'Desconocido')) ?></td>
                            <td data-label="Fecha"><?= e(fecha_local($p['fecha'] ?? null, 'd/m/Y h:i a')) ?></td>
                            <td data-label="Estado"><?= estado_badge(pedido_estado($p)) ?></td>
                            <td data-label="Total" class="text-md-end fw-semibold"><?= e(dinero($p['total'] ?? 0)) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-xl-5">
        <div class="cs-panel h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="cs-panel-titulo mb-0">Inventario por reponer</h2>
                <a href="<?= e(url('inventario.php', ['filtro' => 'bajo'])) ?>" class="small">Ir a inventario</a>
            </div>
            <?php if (!$bajos): ?>
            <p class="text-secondary mb-0"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> Todos los productos tienen más de 5 unidades.</p>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($bajos as $b): $s = (int)($b['stock'] ?? 0); ?>
                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                    <a class="text-reset" href="<?= e(url('editar_producto.php', ['id' => (string)$b['_id']])) ?>"><?= e($b['nombre'] ?? '') ?></a>
                    <span class="<?= $s <= 0 ? 'stock-cero' : 'stock-bajo' ?>"><?= $s <= 0 ? 'Agotado' : $s . ' und.' ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<h2 class="h5 mt-5 mb-3">Accesos rápidos</h2>
<div class="row g-3">
    <?php foreach (admin_menu() as $clave => [$href, $icono, $texto]): if ($clave === 'dashboard') continue; ?>
    <div class="col-6 col-md-4 col-xl-3">
        <a class="kpi text-decoration-none text-reset" href="<?= e(url($href)) ?>">
            <div class="kpi-icono" aria-hidden="true"><i class="bi bi-<?= e($icono) ?>"></i></div>
            <div class="fw-semibold"><?= e($texto) ?></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php admin_fin(); ?>
