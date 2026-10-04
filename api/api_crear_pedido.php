<?php
/**
 * POST /api/api_crear_pedido.php  (requiere token)
 * Cuerpo: {carrito:[{id, cantidad}], token_cliente?, pago?:{banco, persona}, usuario?:{id} (solo apps antiguas)}
 *
 * La app muestra el simulador de PSE y solo llama a este endpoint cuando el pago
 * simulado se aprueba; por eso el pedido se registra como "Pagado" (igual que antes).
 * Los precios y el inventario SIEMPRE se toman de MongoDB, nunca del celular.
 */
require_once __DIR__ . '/_comun.php';
require_once __DIR__ . '/../includes/pedidos.php';
api_metodo('POST');

$d = api_datos();
$usuario = api_usuario((string)($d['usuario']['id'] ?? ''));
$carrito = $d['carrito'] ?? [];
if (!is_array($carrito) || !$carrito) {
    api_error('carrito_vacio', 'Tu carrito está vacío.', 422);
}
if (count($carrito) > 100) {
    api_error('validacion', 'El carrito tiene demasiados productos.', 422);
}

// Agrupa cantidades por producto (la app podía repetir el mismo producto)
$items = [];
foreach ($carrito as $item) {
    $id = (string)($item['id'] ?? '');
    $cantidad = (int)($item['cantidad'] ?? 0);
    if (!es_object_id($id)) {
        api_error('producto_invalido', 'Hay un producto inválido en el carrito.', 422);
    }
    if ($cantidad <= 0) {
        api_error('cantidad_invalida', 'La cantidad de un producto no es válida.', 422, ['producto_id' => $id]);
    }
    $items[$id] = ($items[$id] ?? 0) + $cantidad;
}

$bancos = ['Banco Simulado Andino', 'Banco Simulado del Café', 'Banco Simulado Caribe', 'Cooperativa Simulada'];
$pagoApp = is_array($d['pago'] ?? null) ? $d['pago'] : [];
$pago = [
    'metodo'     => 'PSE (simulado)',
    'simulado'   => true,
    'banco'      => in_array($pagoApp['banco'] ?? '', $bancos, true) ? $pagoApp['banco'] : 'No indicado',
    'persona'    => in_array($pagoApp['persona'] ?? '', ['natural', 'juridica'], true) ? $pagoApp['persona'] : 'natural',
    'resultado'  => 'aprobado',
    'referencia' => referencia_pago_simulada(),
    'fecha'      => nowUTC(),
];

$r = crear_pedido((string)$usuario['_id'], $items, [
    'origen'        => 'app_android',
    'estado'        => ESTADO_PAGADO,
    'pago'          => $pago,
    'token_cliente' => (string)($d['token_cliente'] ?? ''),
]);

if (!$r['ok']) {
    $estado = in_array($r['codigo'] ?? '', ['sin_stock', 'producto_no_encontrado'], true) ? 409 : (($r['codigo'] ?? '') === 'error_servidor' ? 500 : 422);
    api_error($r['codigo'] ?? 'error', $r['mensaje'], $estado, ['producto_id' => $r['producto_id'] ?? null]);
}

$p = $r['pedido'];
json_respuesta([
    'success'         => true,
    'message'         => !empty($r['duplicado']) ? 'Este pedido ya estaba registrado.' : 'Pedido creado correctamente.',
    'pedido_id'       => (string)$p['_id'],
    'numero'          => pedido_numero($p),
    'total'           => (float)$p['total'],
    'estado'          => pedido_estado($p),
    'fecha'           => fecha_local($p['fecha'] ?? null, 'Y-m-d H:i'),
    'referencia'      => (string)($p['pago']['referencia'] ?? ''),
    'comprobante_url' => api_url_comprobante((string)$p['_id'], (string)$usuario['_id']),
], !empty($r['duplicado']) ? 200 : 201);
