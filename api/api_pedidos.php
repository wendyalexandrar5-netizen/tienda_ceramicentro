<?php
/**
 * POST /api/api_pedidos.php  (requiere token)   {usuario_id? (solo apps antiguas)}
 * Respuesta: {success, pedidos:[{id, numero, fecha, estado, total, comprobante_url, productos:[...]}]}
 * Solo devuelve pedidos del usuario autenticado.
 */
require_once __DIR__ . '/_comun.php';
require_once __DIR__ . '/../includes/pedidos.php';
api_metodo('POST', 'GET');

$d = api_datos();
$usuario = api_usuario((string)($d['usuario_id'] ?? ''));
$uid = $usuario['_id'];

$pedidos = iterator_to_array(mongo()->selectCollection('pedidos')->find(['usuario_id' => $uid], ['sort' => ['fecha' => -1], 'limit' => 100]), false);
$detalles = detalles_de_pedidos(array_map(fn($p) => $p['_id'], $pedidos));

// Imágenes actuales de los productos en una sola consulta
$ids = [];
foreach ($detalles as $lineas) {
    foreach ($lineas as $l) {
        if (!empty($l['producto_id'])) {
            $ids[(string)$l['producto_id']] = $l['producto_id'];
        }
    }
}
$imagenes = [];
if ($ids) {
    foreach (mongo()->selectCollection('productos')->find(['_id' => ['$in' => array_values($ids)]], ['projection' => ['imagen' => 1]]) as $pr) {
        $imagenes[(string)$pr['_id']] = (string)($pr['imagen'] ?? '');
    }
}

$resultado = [];
foreach ($pedidos as $p) {
    $productos = [];
    foreach ($detalles[(string)$p['_id']] ?? [] as $l) {
        $pid = isset($l['producto_id']) ? (string)$l['producto_id'] : '';
        $cantidad = (int)($l['cantidad'] ?? 0);
        $precio = (float)($l['precio_unitario'] ?? 0);
        $productos[] = [
            'producto_id' => $pid,
            'nombre'      => (string)($l['nombre_producto'] ?? ''),
            'cantidad'    => $cantidad,
            'precio'      => $precio,
            'subtotal'    => (float)($l['subtotal'] ?? $precio * $cantidad),
            'imagen'      => api_url_imagen((string)($l['imagen'] ?? ($imagenes[$pid] ?? ''))),
        ];
    }
    $resultado[] = [
        'id'              => (string)$p['_id'],
        'numero'          => pedido_numero($p),
        'fecha'           => fecha_local($p['fecha'] ?? null, 'Y-m-d H:i'),
        'estado'          => pedido_estado($p),
        'explicacion'     => estado_explicacion(pedido_estado($p)),
        'total'           => (float)($p['total'] ?? 0),
        'referencia'      => (string)($p['pago']['referencia'] ?? ''),
        'origen'          => (string)($p['origen'] ?? 'web'),
        'comprobante_url' => api_url_comprobante((string)$p['_id'], (string)$uid),
        'productos'       => $productos,
    ];
}

json_respuesta(['success' => true, 'pedidos' => $resultado]);
