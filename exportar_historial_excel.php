<?php
/** Exporta el historial de productos a Excel (respeta los filtros). Solo administradores. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/excel.php';
require_once __DIR__ . '/includes/filtros_historial.php';
requerir_sesion('administrador');

[$f, $q] = filtros_historial();
$filas = [];
foreach (mongo()->selectCollection('historial_productos')->find($q, ['sort' => ['fecha' => -1], 'limit' => 20000]) as $r) {
    $cambios = $r['cambios'] ?? '';
    $filas[] = [
        fecha_local($r['fecha'] ?? null, 'Y-m-d H:i'), (string)($r['nombre_admin'] ?? ''), accion_texto((string)($r['accion'] ?? '')),
        (string)($r['producto_nombre'] ?? ''), (string)($r['categoria_anterior'] ?? ''), (string)($r['categoria_nueva'] ?? ''),
        is_array($cambios) ? implode("\n", $cambios) : (string)$cambios,
    ];
}
$sub = trim(($f['fecha_desde'] ? 'Desde ' . $f['fecha_desde'] . ' ' : '') . ($f['fecha_hasta'] ? 'hasta ' . $f['fecha_hasta'] : ''));
[$libro, $hoja] = excel_nuevo('Historial');
excel_tabla($hoja, 'HISTORIAL DE PRODUCTOS', [
    ['titulo' => 'Fecha', 'ancho' => 18], ['titulo' => 'Administrador', 'ancho' => 24], ['titulo' => 'Acción', 'ancho' => 12],
    ['titulo' => 'Producto', 'ancho' => 36], ['titulo' => 'Categoría antes', 'ancho' => 20], ['titulo' => 'Categoría después', 'ancho' => 20],
    ['titulo' => 'Cambios', 'ancho' => 60, 'ajustar' => true],
], $filas, 1, $sub);
excel_enviar($libro, 'historial_productos');
