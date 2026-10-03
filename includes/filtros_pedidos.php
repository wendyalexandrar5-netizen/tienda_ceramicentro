<?php
/** Filtros del listado de pedidos del administrador (pantalla y exportación). */
function filtros_pedidos(): array
{
    $f = [
        'desde'   => (string)($_GET['desde'] ?? ''),
        'hasta'   => (string)($_GET['hasta'] ?? ''),
        'cliente' => mb_substr(trim((string)($_GET['cliente'] ?? '')), 0, 80),
        'estado'  => in_array($_GET['estado'] ?? '', estados_pedido(), true) ? $_GET['estado'] : '',
        'numero'  => strtoupper(preg_replace('/[^A-Za-z0-9-]/', '', (string)($_GET['numero'] ?? ''))),
        'origen'  => in_array($_GET['origen'] ?? '', ['web', 'app_android'], true) ? $_GET['origen'] : '',
    ];
    $q = [];
    if ($d = fecha_filtro($f['desde'])) {
        $q['fecha']['$gte'] = $d;
    } else {
        $f['desde'] = '';
    }
    if ($h = fecha_filtro($f['hasta'], true)) {
        $q['fecha']['$lte'] = $h;
    } else {
        $f['hasta'] = '';
    }
    if ($f['estado'] !== '') {
        // Los pedidos antiguos sin estado se consideran "Pagado"
        $q['$or'] = $f['estado'] === ESTADO_PAGADO
            ? [['estado' => ESTADO_PAGADO], ['estado' => ['$exists' => false]]]
            : [['estado' => $f['estado']]];
    }
    if ($f['origen'] !== '') {
        $q['origen'] = $f['origen'] === 'web' ? ['$ne' => 'app_android'] : 'app_android';
    }
    if ($f['numero'] !== '') {
        if (preg_match('/^(?:CS-)?0*(\d{1,6})$/', $f['numero'], $m)) {
            $q['numero'] = (int)$m[1];
        } elseif (preg_match('/^(?:CS-)?([A-F0-9]{8,24})$/', $f['numero'], $m)) {
            // Pedidos antiguos: código corto = últimos 8 caracteres del ID
            $q['$expr'] = ['$regexMatch' => ['input' => ['$toString' => '$_id'], 'regex' => strtolower($m[1]) . '$']];
        } else {
            $q['numero'] = -1;
        }
    }
    if ($f['cliente'] !== '') {
        $ids = [];
        $rx = regex_literal($f['cliente']);
        foreach (mongo()->selectCollection('usuarios')->find(['$or' => [
            ['nombre' => ['$regex' => $rx, '$options' => 'i']], ['correo' => ['$regex' => $rx, '$options' => 'i']],
        ]], ['projection' => ['_id' => 1], 'limit' => 500]) as $u) {
            $ids[] = $u['_id'];
        }
        $q['usuario_id'] = ['$in' => $ids];
    }
    return [$f, $q];
}

/** Nombres y correos de los clientes de una lista de pedidos (una sola consulta). */
function clientes_de_pedidos(array $pedidos): array
{
    $ids = [];
    foreach ($pedidos as $p) {
        if (!empty($p['usuario_id'])) {
            $ids[(string)$p['usuario_id']] = $p['usuario_id'];
        }
    }
    $mapa = [];
    if ($ids) {
        foreach (mongo()->selectCollection('usuarios')->find(['_id' => ['$in' => array_values($ids)]], ['projection' => ['nombre' => 1, 'correo' => 1]]) as $u) {
            $mapa[(string)$u['_id']] = ['nombre' => (string)($u['nombre'] ?? ''), 'correo' => (string)($u['correo'] ?? '')];
        }
    }
    return $mapa;
}
