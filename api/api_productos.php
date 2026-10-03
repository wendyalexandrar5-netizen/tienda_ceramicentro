<?php
/**
 * GET /api/api_productos.php[?categoria=ID&q=texto]
 * Respuesta (compatible con la app existente): arreglo de productos
 * [{id, nombre, descripcion, precio, stock, categoria, categoria_id, imagen}]
 */
require_once __DIR__ . '/_comun.php';
api_metodo('GET');

$filtro = [];
if (!empty($_GET['categoria']) && ($cat = oid((string)$_GET['categoria']))) {
    $filtro['categoria_id'] = $cat;
}
if (!empty($_GET['q'])) {
    $rx = regex_literal((string)$_GET['q']);
    $filtro['$or'] = [['nombre' => ['$regex' => $rx, '$options' => 'i']], ['descripcion' => ['$regex' => $rx, '$options' => 'i']]];
}

// Una sola consulta para todas las categorías (antes se hacía una por producto)
$categorias = [];
foreach (mongo()->selectCollection('categorias')->find([], ['projection' => ['nombre' => 1]]) as $c) {
    $categorias[(string)$c['_id']] = (string)($c['nombre'] ?? 'Sin categoría');
}

$resultado = [];
foreach (mongo()->selectCollection('productos')->find($filtro, ['sort' => ['_id' => -1]]) as $p) {
    $catId = isset($p['categoria_id']) ? (string)$p['categoria_id'] : '';
    $resultado[] = [
        'id'           => (string)$p['_id'],
        'nombre'       => (string)($p['nombre'] ?? ''),
        'descripcion'  => (string)($p['descripcion'] ?? ''),
        'precio'       => (float)($p['precio'] ?? 0),
        'stock'        => max(0, (int)($p['stock'] ?? 0)),
        'categoria'    => $categorias[$catId] ?? 'Sin categoría',
        'categoria_id' => $catId,
        'imagen'       => api_url_imagen((string)($p['imagen'] ?? '')),
    ];
}
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: public, max-age=30');
echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
