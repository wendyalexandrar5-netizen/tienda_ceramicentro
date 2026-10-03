<?php
/** GET /api/api_categorias.php -> {success, categorias:[{id,nombre,descripcion}]} */
require_once __DIR__ . '/_comun.php';
api_metodo('GET');

$categorias = [];
foreach (mongo()->selectCollection('categorias')->find([], ['sort' => ['nombre' => 1]]) as $cat) {
    $categorias[] = [
        'id'          => (string)$cat['_id'],
        'nombre'      => (string)($cat['nombre'] ?? 'Sin nombre'),
        'descripcion' => (string)($cat['descripcion'] ?? ''),
    ];
}
header('Cache-Control: public, max-age=60');
json_respuesta(['success' => true, 'categorias' => $categorias]);
