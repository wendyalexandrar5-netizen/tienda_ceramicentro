<?php
/**
 * GET /api/descargar_pedido_app.php?id=&usuario_id=&exp=&sig=
 * Comprobante PDF para la app. El enlace está firmado y vence (lo genera la API),
 * así nadie puede descargar pedidos de otros cambiando los parámetros.
 */
define('CS_SIN_SESION', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/seguridad.php';
require_once __DIR__ . '/../includes/comprobante.php';

$pedidoId  = (string)($_GET['id'] ?? '');
$usuarioId = (string)($_GET['usuario_id'] ?? '');
$exp = (int)($_GET['exp'] ?? 0);
$sig = (string)($_GET['sig'] ?? '');

if (!es_object_id($pedidoId) || !es_object_id($usuarioId)) {
    mostrar_error(400, 'Enlace no válido', 'El enlace del comprobante está incompleto. Vuelve a abrirlo desde la app.');
}
$firmaValida = $sig !== '' && $exp >= time() && hash_equals(firma_descarga($pedidoId, $usuarioId, $exp), $sig);
if (!$firmaValida && !(config('api.modo_legado') && $sig === '')) {
    mostrar_error(403, 'Enlace vencido', 'Este enlace del comprobante venció o no es válido. Ábrelo de nuevo desde «Mis pedidos» en la app.');
}

$pedido = pedido_de_usuario($pedidoId, $usuarioId);
if (!$pedido) {
    no_encontrado('No encontramos ese pedido.');
}
$u = mongo()->selectCollection('usuarios')->findOne(['_id' => $pedido['usuario_id']], ['projection' => ['nombre' => 1, 'correo' => 1]]);
enviar_comprobante_pdf($pedido, ['nombre' => (string)($u['nombre'] ?? ''), 'correo' => (string)($u['correo'] ?? '')], 'Comprobante de Pedido', 'I');
