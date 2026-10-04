<?php
/** Exporta productos a Excel (respeta los filtros del listado). Solo administradores. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/excel.php';
require_once __DIR__ . '/includes/filtros_admin_productos.php';
requerir_sesion('administrador');

[$f, $filtro, $sort] = filtros_admin_productos();
$mapa = categorias_mapa();
$filas = [];
foreach (mongo()->selectCollection('productos')->find($filtro, ['sort' => $sort]) as $p) {
    $filas[] = [
        (string)$p['_id'], (string)($p['nombre'] ?? ''), $mapa[(string)($p['categoria_id'] ?? '')] ?? 'Sin categoría',
        (float)($p['precio'] ?? 0), (int)($p['stock'] ?? 0), resumen((string)($p['descripcion'] ?? ''), 300),
    ];
}
[$libro, $hoja] = excel_nuevo('Productos');
excel_tabla($hoja, 'PRODUCTOS', [
    ['titulo' => 'ID', 'ancho' => 27], ['titulo' => 'Nombre', 'ancho' => 40], ['titulo' => 'Categoría', 'ancho' => 22],
    ['titulo' => 'Precio (COP)', 'ancho' => 16, 'formato' => 'dinero'], ['titulo' => 'Stock', 'ancho' => 10, 'formato' => 'entero'],
    ['titulo' => 'Descripción', 'ancho' => 60, 'ajustar' => true],
], $filas, 1, count($filas) . ' productos');
excel_enviar($libro, 'productos_ceramicentro');
