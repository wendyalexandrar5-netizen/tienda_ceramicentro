<?php
/** Filtros del historial de productos (pantalla y exportación). */
function filtros_historial(): array
{
    $f = [
        'fecha_desde' => (string)($_GET['fecha_desde'] ?? ''),
        'fecha_hasta' => (string)($_GET['fecha_hasta'] ?? ''),
        'accion'      => in_array($_GET['accion'] ?? '', ['agrego', 'edito', 'elimino'], true) ? $_GET['accion'] : '',
        'buscar'      => mb_substr(trim((string)($_GET['buscar'] ?? '')), 0, 80),
    ];
    $q = [];
    $desde = fecha_filtro($f['fecha_desde']);
    $hasta = fecha_filtro($f['fecha_hasta'], true);
    if ($desde) {
        $q['fecha']['$gte'] = $desde;
    } else {
        $f['fecha_desde'] = '';
    }
    if ($hasta) {
        $q['fecha']['$lte'] = $hasta;
    } else {
        $f['fecha_hasta'] = '';
    }
    if ($f['accion'] !== '') {
        $q['accion'] = $f['accion'];
    }
    if ($f['buscar'] !== '') {
        $rx = regex_literal($f['buscar']);
        $q['$or'] = [
            ['producto_nombre' => ['$regex' => $rx, '$options' => 'i']],
            ['nombre_admin' => ['$regex' => $rx, '$options' => 'i']],
            ['cambios' => ['$regex' => $rx, '$options' => 'i']],
        ];
    }
    return [$f, $q];
}

function accion_texto(string $a): string
{
    return ['agrego' => 'Agregó', 'edito' => 'Editó', 'elimino' => 'Eliminó'][$a] ?? ucfirst($a);
}
