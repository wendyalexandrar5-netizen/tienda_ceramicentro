<?php
/** Exporta pedidos y su detalle a Excel (respeta los filtros). Solo administradores. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/pedidos.php';
require_once __DIR__ . '/includes/excel.php';
require_once __DIR__ . '/includes/filtros_pedidos.php';
requerir_sesion('administrador');

[$f, $q] = filtros_pedidos();
$pedidos = iterator_to_array(mongo()->selectCollection('pedidos')->find($q, ['sort' => ['fecha' => -1], 'limit' => 10000]), false);
$clientes = clientes_de_pedidos($pedidos);
$detalles = detalles_de_pedidos(array_map(fn($p) => $p['_id'], $pedidos));

$filasPedidos = [];
$filasDetalle = [];
foreach ($pedidos as $p) {
    $cli = $clientes[(string)($p['usuario_id'] ?? '')] ?? ['nombre' => (string)($p['cliente']['nombre'] ?? ''), 'correo' => (string)($p['cliente']['correo'] ?? '')];
    $num = pedido_numero($p);
    $lineas = $detalles[(string)$p['_id']] ?? [];
    $filasPedidos[] = [
        $num, fecha_local($p['fecha'] ?? null, 'Y-m-d H:i'), $cli['nombre'], $cli['correo'], pedido_estado($p),
        ($p['origen'] ?? '') === 'app_android' ? 'App Android' : 'Web', (string)($p['pago']['referencia'] ?? ''),
        array_sum(array_map(fn($d) => (int)($d['cantidad'] ?? 0), $lineas)), (float)($p['total'] ?? 0),
    ];
    foreach ($lineas as $d) {
        $filasDetalle[] = [$num, (string)($d['nombre_producto'] ?? ''), (int)($d['cantidad'] ?? 0), (float)($d['precio_unitario'] ?? 0), (float)($d['subtotal'] ?? 0)];
    }
}
[$libro, $hoja] = excel_nuevo('Pedidos');
excel_tabla($hoja, 'PEDIDOS', [
    ['titulo' => 'N.º pedido', 'ancho' => 14], ['titulo' => 'Fecha', 'ancho' => 18], ['titulo' => 'Cliente', 'ancho' => 26],
    ['titulo' => 'Correo', 'ancho' => 28], ['titulo' => 'Estado', 'ancho' => 16], ['titulo' => 'Origen', 'ancho' => 12],
    ['titulo' => 'Referencia pago', 'ancho' => 20], ['titulo' => 'Unidades', 'ancho' => 10, 'formato' => 'entero'],
    ['titulo' => 'Total (COP)', 'ancho' => 16, 'formato' => 'dinero'],
], $filasPedidos, 1, count($filasPedidos) . ' pedidos');
$hoja2 = $libro->createSheet();
$hoja2->setTitle('Detalle');
excel_tabla($hoja2, 'DETALLE DE PEDIDOS', [
    ['titulo' => 'N.º pedido', 'ancho' => 14], ['titulo' => 'Producto', 'ancho' => 40], ['titulo' => 'Cantidad', 'ancho' => 10, 'formato' => 'entero'],
    ['titulo' => 'Precio unitario', 'ancho' => 16, 'formato' => 'dinero'], ['titulo' => 'Subtotal', 'ancho' => 16, 'formato' => 'dinero'],
], $filasDetalle);
excel_enviar($libro, 'pedidos_ceramicentro');
