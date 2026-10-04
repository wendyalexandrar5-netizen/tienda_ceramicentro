<?php
/** Agrega al carrito los productos de un pedido anterior (respetando el inventario actual). */
require_once __DIR__ . '/includes/tienda.php';
require_once __DIR__ . '/includes/pedidos.php';
requerir_sesion('cliente');
if (!es_post()) {
    redirigir('mis_pedidos.php');
}
csrf_verificar();

$pedido = pedido_de_usuario((string)($_POST['id'] ?? ''), usuario_actual()['id']);
if (!$pedido) {
    flash('error', 'No encontramos ese pedido en tu cuenta.');
    redirigir('mis_pedidos.php');
}
$agregados = 0;
$avisos = [];
foreach (pedido_detalles($pedido) as $d) {
    $r = carrito_agregar((string)$d['producto_id'], (int)$d['cantidad']);
    if ($r['ok']) {
        $agregados++;
        if (strpos($r['mensaje'], 'Solo hay') !== false) {
            $avisos[] = $r['mensaje'];
        }
    } else {
        $avisos[] = $r['mensaje'];
    }
}
if ($agregados) {
    flash('success', 'Agregamos ' . $agregados . ' producto' . ($agregados === 1 ? '' : 's') . ' del pedido ' . pedido_numero($pedido) . ' a tu carrito.');
}
foreach ($avisos as $a) {
    flash('warning', $a);
}
redirigir($agregados ? 'ver_carrito.php' : 'ver_pedido.php', $agregados ? [] : ['id' => (string)$pedido['_id']]);
