<?php
function filtros_usuarios(): array
{
    $f = [
        'buscar' => mb_substr(trim((string)($_GET['buscar'] ?? '')), 0, 80),
        'rol'    => in_array($_GET['rol'] ?? '', ['cliente', 'administrador'], true) ? $_GET['rol'] : '',
    ];
    $q = [];
    if ($f['buscar'] !== '') {
        $rx = regex_literal($f['buscar']);
        $q['$or'] = [['nombre' => ['$regex' => $rx, '$options' => 'i']], ['correo' => ['$regex' => $rx, '$options' => 'i']]];
    }
    if ($f['rol'] !== '') {
        $q['rol'] = $f['rol'];
    }
    return [$f, $q];
}
