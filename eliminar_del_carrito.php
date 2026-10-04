<?php
/** Quita un producto del carrito (POST con token CSRF). */
require_once __DIR__ . '/includes/tienda.php';
requerir_sesion('cliente');
if (!es_post()) {
    redirigir('ver_carrito.php');
}
csrf_verificar();
$id = (string)($_POST['id'] ?? '');
if (isset($_SESSION['carrito'][$id])) {
    $nombre = $_SESSION['carrito'][$id]['nombre'] ?? 'El producto';
    carrito_quitar($id);
    flash('success', '«' . $nombre . '» se quitó del carrito.');
}
redirigir('ver_carrito.php');
