<?php
/** Comprobante PDF de cualquier pedido (solo administradores). */
require_once __DIR__ . '/includes/comprobante.php';
requerir_sesion('administrador');

$pedido = pedido_por_id((string)($_GET['id'] ?? ''));
if (!$pedido) {
    no_encontrado('El pedido no existe.');
}
$u = mongo()->selectCollection('usuarios')->findOne(['_id' => $pedido['usuario_id'] ?? null], ['projection' => ['nombre' => 1, 'correo' => 1]]);
enviar_comprobante_pdf($pedido, [
    'nombre' => (string)($u['nombre'] ?? ($pedido['cliente']['nombre'] ?? 'Cliente')),
    'correo' => (string)($u['correo'] ?? ($pedido['cliente']['correo'] ?? '')),
], 'Comprobante de Pedido', 'I');
