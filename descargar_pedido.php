<?php
/** Descarga el detalle del pedido en PDF. Solo el dueño del pedido puede descargarlo. */
require_once __DIR__ . '/includes/comprobante.php';
requerir_sesion('cliente');

$u = usuario_actual();
$pedido = pedido_de_usuario((string)($_GET['id'] ?? ''), $u['id']);
if (!$pedido) {
    no_encontrado('No encontramos ese pedido en tu cuenta.');
}
enviar_comprobante_pdf($pedido, ['nombre' => $u['nombre'], 'correo' => $u['correo']], 'Detalle del Pedido', 'D');
