<?php
/** Vacía el carrito (POST con token CSRF). */
require_once __DIR__ . '/includes/tienda.php';
requerir_sesion('cliente');
if (!es_post()) {
    redirigir('ver_carrito.php');
}
csrf_verificar();
carrito_vaciar();
flash('success', 'Tu carrito quedó vacío.');
redirigir('ver_carrito.php');
