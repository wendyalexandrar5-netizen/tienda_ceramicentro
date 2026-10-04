<?php
/** Cambia la cantidad de un producto del carrito. */
require_once __DIR__ . '/includes/tienda.php';
requerir_sesion('cliente');
if (!es_post()) {
    redirigir('ver_carrito.php');
}
csrf_verificar();
$id = (string)($_POST['producto_id'] ?? '');
$r = es_object_id($id) ? carrito_actualizar($id, (int)($_POST['cantidad'] ?? 1)) : ['ok' => false, 'mensaje' => 'Producto inválido.'];
flash($r['ok'] ? 'success' : 'warning', $r['mensaje']);
redirigir('ver_carrito.php');
