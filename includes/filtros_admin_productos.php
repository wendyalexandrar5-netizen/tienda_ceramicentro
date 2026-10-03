<?php
/** Filtros compartidos del listado de productos del administrador y su exportación. */
function filtros_admin_productos(): array
{
    $f = [
        'buscar'    => mb_substr(trim((string)($_GET['buscar'] ?? '')), 0, 80),
        'categoria' => es_object_id($_GET['categoria'] ?? '') ? (string)$_GET['categoria'] : '',
        'stock'     => in_array($_GET['stock'] ?? '', ['agotado', 'bajo', 'disponible'], true) ? $_GET['stock'] : '',
        'orden'     => in_array($_GET['orden'] ?? '', ['nombre', 'precio_asc', 'precio_desc', 'stock_asc', 'stock_desc', 'recientes'], true) ? $_GET['orden'] : 'nombre',
    ];
    $q = [];
    if ($f['buscar'] !== '') {
        $q['nombre'] = ['$regex' => regex_literal($f['buscar']), '$options' => 'i'];
    }
    if ($f['categoria'] !== '') {
        $q['categoria_id'] = oid($f['categoria']);
    }
    if ($f['stock'] === 'agotado') {
        $q['stock'] = ['$lte' => 0];
    } elseif ($f['stock'] === 'bajo') {
        $q['stock'] = ['$gt' => 0, '$lte' => 5];
    } elseif ($f['stock'] === 'disponible') {
        $q['stock'] = ['$gt' => 0];
    }
    $sorts = [
        'nombre' => ['nombre' => 1], 'precio_asc' => ['precio' => 1], 'precio_desc' => ['precio' => -1],
        'stock_asc' => ['stock' => 1, 'nombre' => 1], 'stock_desc' => ['stock' => -1, 'nombre' => 1], 'recientes' => ['_id' => -1],
    ];
    return [$f, $q, $sorts[$f['orden']]];
}
