<?php
/** Comprobante PDF del pedido (se abre en el navegador). Solo el dueño del pedido puede verlo. */
require_once __DIR__ . '/includes/comprobante.php';
requerir_sesion('cliente');

$u = usuario_actual();
$pedido = pedido_de_usuario((string)($_GET['id'] ?? ''), $u['id']);
if (!$pedido) {
    no_encontrado('No encontramos ese pedido en tu cuenta.');
}
enviar_comprobante_pdf($pedido, ['nombre' => $u['nombre'], 'correo' => $u['correo']], 'Comprobante de Pedido', 'I');
