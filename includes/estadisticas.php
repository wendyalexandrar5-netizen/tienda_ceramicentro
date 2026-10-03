<?php
/** Cálculo de estadísticas de ventas (pantalla, Excel y PDF). Solo cuenta ventas efectivas. */
require_once __DIR__ . '/pedidos.php';

function estadisticas_ventas(int $anio): array
{
    $db = mongo();
    $tz = new DateTimeZone('America/Bogota');
    $ini = new DateTime("$anio-01-01 00:00:00", $tz);
    $fin = new DateTime(($anio + 1) . '-01-01 00:00:00', $tz);
    $matchVentas = ['$or' => [['estado' => ['$in' => estados_venta()]], ['estado' => ['$exists' => false]]]];
    $matchAnio = $matchVentas + ['fecha' => [
        '$gte' => new \MongoDB\BSON\UTCDateTime($ini->getTimestamp() * 1000),
        '$lt'  => new \MongoDB\BSON\UTCDateTime($fin->getTimestamp() * 1000),
    ]];

    $tot = $db->selectCollection('pedidos')->aggregate([
        ['$match' => $matchAnio],
        ['$group' => ['_id' => null, 'ventas' => ['$sum' => 1], 'ingresos' => ['$sum' => '$total']]],
    ])->toArray();

    $mensual = array_fill(1, 12, 0.0);
    $pedidosMes = array_fill(1, 12, 0);
    foreach ($db->selectCollection('pedidos')->aggregate([
        ['$match' => $matchAnio],
        ['$group' => ['_id' => ['$month' => ['date' => '$fecha', 'timezone' => 'America/Bogota']], 'total' => ['$sum' => '$total'], 'n' => ['$sum' => 1]]],
    ]) as $m) {
        $mes = (int)$m['_id'];
        if ($mes >= 1 && $mes <= 12) {
            $mensual[$mes] = (float)$m['total'];
            $pedidosMes[$mes] = (int)$m['n'];
        }
    }

    // Detalle de productos vendidos en el año (unidades y top 5)
    $ids = [];
    foreach ($db->selectCollection('pedidos')->find($matchAnio, ['projection' => ['_id' => 1]]) as $p) {
        $ids[] = $p['_id'];
    }
    $unidades = 0;
    $top = [];
    if ($ids) {
        $u = $db->selectCollection('pedido_detalle')->aggregate([
            ['$match' => ['pedido_id' => ['$in' => $ids]]],
            ['$group' => ['_id' => null, 'u' => ['$sum' => '$cantidad']]],
        ])->toArray();
        $unidades = (int)($u[0]['u'] ?? 0);
        foreach ($db->selectCollection('pedido_detalle')->aggregate([
            ['$match' => ['pedido_id' => ['$in' => $ids]]],
            ['$group' => ['_id' => '$nombre_producto', 'cantidad' => ['$sum' => '$cantidad'], 'ingresos' => ['$sum' => '$subtotal']]],
            ['$sort' => ['cantidad' => -1]],
            ['$limit' => 5],
        ]) as $t) {
            $top[] = ['nombre' => (string)($t['_id'] ?? 'Sin nombre'), 'cantidad' => (int)$t['cantidad'], 'ingresos' => (float)$t['ingresos']];
        }
    }

    $porEstado = [];
    foreach ($db->selectCollection('pedidos')->aggregate([
        ['$match' => ['fecha' => $matchAnio['fecha']]],
        ['$group' => ['_id' => '$estado', 'n' => ['$sum' => 1], 'total' => ['$sum' => '$total']]],
    ]) as $e) {
        $nombre = ($e['_id'] ?? '') !== '' && $e['_id'] !== null ? (string)$e['_id'] : ESTADO_PAGADO;
        $porEstado[$nombre] = [
            'n' => ($porEstado[$nombre]['n'] ?? 0) + (int)$e['n'],
            'total' => ($porEstado[$nombre]['total'] ?? 0) + (float)$e['total'],
        ];
    }

    $ventas = (int)($tot[0]['ventas'] ?? 0);
    $ingresos = (float)($tot[0]['ingresos'] ?? 0);
    return [
        'anio' => $anio, 'ventas' => $ventas, 'ingresos' => $ingresos, 'unidades' => $unidades,
        'ticket' => $ventas ? $ingresos / $ventas : 0, 'mensual' => $mensual, 'pedidos_mes' => $pedidosMes,
        'top' => $top, 'por_estado' => $porEstado,
    ];
}

/** Años con pedidos registrados (para el selector). */
function anios_con_pedidos(): array
{
    $anios = [(int)date('Y')];
    $primero = mongo()->selectCollection('pedidos')->findOne([], ['sort' => ['fecha' => 1], 'projection' => ['fecha' => 1]]);
    if ($primero && !empty($primero['fecha'])) {
        $desde = (int)fecha_local($primero['fecha'], 'Y');
        for ($a = $desde; $a <= (int)date('Y'); $a++) {
            $anios[] = $a;
        }
    }
    $anios = array_unique($anios);
    rsort($anios);
    return $anios;
}

function meses_es(): array
{
    return [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
}
