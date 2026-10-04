<?php
/**
 * Agrega un producto al carrito.
 * Responde JSON si la petición es AJAX; si no, redirige (funciona sin JavaScript).
 */
require_once __DIR__ . '/includes/tienda.php';

if (!es_post()) {
    redirigir('tienda.php');
}
if (!es_cliente()) {
    if (es_ajax()) {
        json_respuesta(['success' => false, 'codigo' => 'sesion', 'message' => 'Inicia sesión para comprar.', 'redirigir' => url('login.php')], 401);
    }
    requerir_sesion('cliente');
}
csrf_verificar();

$productoId = (string)($_POST['producto_id'] ?? '');
$cantidad = (int)($_POST['cantidad'] ?? 1);

if (!es_object_id($productoId)) {
    $r = ['ok' => false, 'mensaje' => 'El producto no es válido.'];
} elseif ($cantidad < 1 || $cantidad > 10000) {
    $r = ['ok' => false, 'mensaje' => 'Escribe una cantidad válida (mínimo 1).'];
} else {
    $r = carrito_agregar($productoId, $cantidad);
}

if (es_ajax()) {
    $p = $r['ok'] ? ($_SESSION['carrito'][$productoId] ?? null) : null;
    json_respuesta([
        'success' => $r['ok'],
        'codigo'  => $r['ok'] ? 'ok' : 'sin_stock',
        'message' => $r['mensaje'],
        'carrito' => carrito_contar(),
        'evento'  => $p ? ['currency' => 'COP', 'value' => $p['precio'] * $cantidad, 'items' => [['item_id' => $productoId, 'item_name' => $p['nombre'], 'price' => $p['precio'], 'quantity' => $cantidad]]] : null,
    ], $r['ok'] ? 200 : 422);
}

flash($r['ok'] ? 'success' : 'warning', $r['mensaje']);
if (($_POST['volver'] ?? '') === 'producto') {
    redirigir('producto.php', ['id' => $productoId]);
}
redirigir('tienda.php');
