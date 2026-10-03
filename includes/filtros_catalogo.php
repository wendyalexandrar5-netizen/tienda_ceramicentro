<?php
/** Lee y normaliza los filtros del catálogo desde la URL. */
function filtros_catalogo(): array
{
    $categoria = (string)($_GET['categoria'] ?? '');
    $orden = (string)($_GET['orden'] ?? 'nombre');
    // Compatibilidad con el buscador anterior (?buscar=)
    $q = (string)($_GET['q'] ?? ($_GET['buscar'] ?? ''));
    return [
        'q'           => mb_substr(trim($q), 0, 80),
        'categoria'   => es_object_id($categoria) ? $categoria : '',
        'orden'       => in_array($orden, ['nombre', 'precio_asc', 'precio_desc', 'recientes'], true) ? $orden : 'nombre',
        'disponibles' => !empty($_GET['disponibles']),
        'pagina'      => max(1, (int)($_GET['pagina'] ?? 1)),
    ];
}

function buscar_con_filtros(array $f): array
{
    return productos_buscar([
        'q' => $f['q'], 'categoria' => $f['categoria'], 'orden' => $f['orden'],
        'solo_disponibles' => $f['disponibles'], 'pagina' => $f['pagina'], 'por_pagina' => 24,
    ]);
}
