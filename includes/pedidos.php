<?php
/**
 * Lógica de pedidos compartida por la web y la API de la app Android.
 * Colecciones: pedidos, pedido_detalle, productos, contadores.
 *
 * Compatibilidad: los pedidos anteriores (estado "Pagado", sin número) siguen
 * funcionando; los campos nuevos (numero, pago, historial_estados, stock_devuelto,
 * cliente) son opcionales.
 */
require_once __DIR__ . '/bootstrap.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

const ESTADO_PENDIENTE  = 'Pendiente de pago';
const ESTADO_PAGADO     = 'Pagado';
const ESTADO_PREPARANDO = 'En preparación';
const ESTADO_ENVIADO    = 'Enviado';
const ESTADO_ENTREGADO  = 'Entregado';
const ESTADO_RECHAZADO  = 'Pago rechazado';
const ESTADO_CANCELADO  = 'Cancelado';

/** Estados que el administrador puede asignar, en orden lógico. */
function estados_pedido(): array
{
    return [ESTADO_PENDIENTE, ESTADO_PAGADO, ESTADO_PREPARANDO, ESTADO_ENVIADO, ESTADO_ENTREGADO, ESTADO_RECHAZADO, ESTADO_CANCELADO];
}

/** Estados que cuentan como venta efectiva en estadísticas. */
function estados_venta(): array
{
    return [ESTADO_PAGADO, ESTADO_PREPARANDO, ESTADO_ENVIADO, ESTADO_ENTREGADO];
}

/** Estados en los que el inventario ya fue devuelto (o nunca se descontó). */
function estados_sin_stock(): array
{
    return [ESTADO_RECHAZADO, ESTADO_CANCELADO];
}

/** Clase visual (badge) para cada estado. */
function estado_clase(string $estado): string
{
    return match ($estado) {
        ESTADO_PAGADO, ESTADO_ENTREGADO => 'estado-ok',
        ESTADO_PREPARANDO, ESTADO_ENVIADO => 'estado-proceso',
        ESTADO_PENDIENTE => 'estado-pendiente',
        ESTADO_RECHAZADO, ESTADO_CANCELADO => 'estado-error',
        default => 'estado-neutro',
    };
}

/** Explicación para el cliente de lo que significa cada estado. */
function estado_explicacion(string $estado): string
{
    return match ($estado) {
        ESTADO_PENDIENTE  => 'Tu pedido está reservado y espera la confirmación del pago.',
        ESTADO_PAGADO     => 'Recibimos tu pago. Pronto empezaremos a preparar tu pedido.',
        ESTADO_PREPARANDO => 'Estamos alistando los productos de tu pedido.',
        ESTADO_ENVIADO    => 'Tu pedido va en camino.',
        ESTADO_ENTREGADO  => 'Tu pedido fue entregado. ¡Gracias por tu compra!',
        ESTADO_RECHAZADO  => 'El pago no fue aprobado. Puedes intentarlo de nuevo.',
        ESTADO_CANCELADO  => 'Este pedido fue cancelado.',
        default           => '',
    };
}

function pedido_estado(array $pedido): string
{
    $e = trim((string)($pedido['estado'] ?? ''));
    return $e !== '' ? $e : ESTADO_PAGADO; // los pedidos antiguos se crearon como pagados
}

/** Número legible del pedido: CS-000123 (o código corto para pedidos antiguos). */
function pedido_numero(array $pedido): string
{
    if (!empty($pedido['numero'])) {
        return 'CS-' . str_pad((string)(int)$pedido['numero'], 6, '0', STR_PAD_LEFT);
    }
    return 'CS-' . strtoupper(substr((string)$pedido['_id'], -8));
}

function siguiente_numero_pedido(): ?int
{
    try {
        $doc = mongo()->selectCollection('contadores')->findOneAndUpdate(
            ['_id' => 'pedidos'],
            ['$inc' => ['seq' => 1]],
            ['upsert' => true, 'returnDocument' => \MongoDB\Operation\FindOneAndUpdate::RETURN_DOCUMENT_AFTER]
        );
        return isset($doc['seq']) ? (int)$doc['seq'] : null;
    } catch (Throwable $e) {
        log_app('aviso', 'No se pudo generar número de pedido', ['error' => $e->getMessage()]);
        return null;
    }
}

/** Devuelve stock a los productos (reversión). */
function devolver_stock(array $lineas): void
{
    $col = mongo()->selectCollection('productos');
    foreach ($lineas as $l) {
        if (!empty($l['producto_id']) && (int)$l['cantidad'] > 0) {
            $col->updateOne(['_id' => oid((string)$l['producto_id'])], ['$inc' => ['stock' => (int)$l['cantidad']]]);
        }
    }
}

/**
 * Reserva stock de forma atómica (solo descuenta si hay suficiente).
 * Si algún producto falla, revierte todo lo reservado.
 * @param array $lineas [['producto_id'=>string,'cantidad'=>int,'nombre'=>string], ...]
 * @return array{ok:bool,mensaje?:string,producto_id?:string}
 */
function reservar_stock(array $lineas): array
{
    $col = mongo()->selectCollection('productos');
    $hechas = [];
    foreach ($lineas as $l) {
        $r = $col->updateOne(
            ['_id' => oid((string)$l['producto_id']), 'stock' => ['$gte' => (int)$l['cantidad']]],
            ['$inc' => ['stock' => -(int)$l['cantidad']]]
        );
        if ($r->getModifiedCount() !== 1) {
            devolver_stock($hechas);
            return ['ok' => false, 'producto_id' => (string)$l['producto_id'],
                'mensaje' => 'No hay suficiente inventario de "' . ($l['nombre'] ?? 'un producto') . '". Revisa las cantidades.'];
        }
        $hechas[] = $l;
    }
    return ['ok' => true];
}

/**
 * Crea un pedido validando productos, precios (siempre desde la base de datos) y stock.
 *
 * @param string $usuarioId  ObjectId del cliente
 * @param array  $items      [producto_id => cantidad]
 * @param array  $opc        origen ('web'|'app_android'), estado, pago (array), token_cliente (idempotencia)
 * @return array{ok:bool,mensaje:string,codigo?:string,pedido?:array}
 */
function crear_pedido(string $usuarioId, array $items, array $opc = []): array
{
    $uid = oid($usuarioId);
    if (!$uid) {
        return ['ok' => false, 'codigo' => 'usuario_invalido', 'mensaje' => 'Usuario inválido.'];
    }
    $db = mongo();
    $colPedidos  = $db->selectCollection('pedidos');
    $colDetalle  = $db->selectCollection('pedido_detalle');
    $colProd     = $db->selectCollection('productos');

    // Evita pedidos duplicados por doble clic / reintentos de red
    $token = isset($opc['token_cliente']) ? substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$opc['token_cliente']), 0, 64) : '';
    if ($token !== '') {
        $previo = $colPedidos->findOne(['usuario_id' => $uid, 'token_cliente' => $token]);
        if ($previo) {
            return ['ok' => true, 'duplicado' => true, 'mensaje' => 'El pedido ya había sido registrado.', 'pedido' => (array)$previo];
        }
    }

    $lineas = [];
    $total = 0.0;
    foreach ($items as $pid => $cantidad) {
        $pid = (string)$pid;
        $cantidad = (int)$cantidad;
        if (!es_object_id($pid)) {
            return ['ok' => false, 'codigo' => 'producto_invalido', 'mensaje' => 'Hay un producto inválido en el carrito.'];
        }
        if ($cantidad <= 0 || $cantidad > 10000) {
            return ['ok' => false, 'codigo' => 'cantidad_invalida', 'mensaje' => 'La cantidad de un producto no es válida.'];
        }
        $p = $colProd->findOne(['_id' => new ObjectId($pid)]);
        if (!$p) {
            return ['ok' => false, 'codigo' => 'producto_no_encontrado', 'mensaje' => 'Uno de los productos ya no está disponible.', 'producto_id' => $pid];
        }
        if ((int)($p['stock'] ?? 0) < $cantidad) {
            return ['ok' => false, 'codigo' => 'sin_stock', 'producto_id' => $pid,
                'mensaje' => (int)($p['stock'] ?? 0) <= 0
                    ? '"' . $p['nombre'] . '" está agotado.'
                    : 'Solo quedan ' . (int)$p['stock'] . ' unidades de "' . $p['nombre'] . '".'];
        }
        $precio = (float)($p['precio'] ?? 0);
        $total += $precio * $cantidad;
        $lineas[] = [
            'producto_id' => $pid, 'cantidad' => $cantidad, 'nombre' => (string)($p['nombre'] ?? ''),
            'precio' => $precio, 'imagen' => (string)($p['imagen'] ?? ''),
        ];
    }
    if (!$lineas) {
        return ['ok' => false, 'codigo' => 'carrito_vacio', 'mensaje' => 'Tu carrito está vacío.'];
    }

    $reserva = reservar_stock($lineas);
    if (!$reserva['ok']) {
        return ['ok' => false, 'codigo' => 'sin_stock', 'mensaje' => $reserva['mensaje'], 'producto_id' => $reserva['producto_id'] ?? ''];
    }

    $estado = $opc['estado'] ?? ESTADO_PENDIENTE;
    $ahora = nowUTC();
    $usuario = $db->selectCollection('usuarios')->findOne(['_id' => $uid], ['projection' => ['nombre' => 1, 'correo' => 1]]);
    $pedido = [
        'usuario_id' => $uid,
        'fecha'      => $ahora,
        'total'      => round($total, 2),
        'estado'     => $estado,
        'origen'     => $opc['origen'] ?? 'web',
        'metodo_pago'=> 'PSE (simulado)',
        'cliente'    => ['nombre' => (string)($usuario['nombre'] ?? ''), 'correo' => (string)($usuario['correo'] ?? '')],
        'historial_estados' => [['estado' => $estado, 'fecha' => $ahora, 'por' => 'sistema']],
    ];
    if ($numero = siguiente_numero_pedido()) {
        $pedido['numero'] = $numero;
    }
    if ($token !== '') {
        $pedido['token_cliente'] = $token;
    }
    if (!empty($opc['pago'])) {
        $pedido['pago'] = $opc['pago'];
    }

    try {
        $res = $colPedidos->insertOne($pedido);
        $pedido['_id'] = $res->getInsertedId();
        $docs = [];
        foreach ($lineas as $l) {
            $docs[] = [
                'pedido_id'       => $pedido['_id'],
                'producto_id'     => new ObjectId($l['producto_id']),
                'nombre_producto' => $l['nombre'],
                'cantidad'        => $l['cantidad'],
                'precio_unitario' => $l['precio'],
                'subtotal'        => round($l['precio'] * $l['cantidad'], 2),
                'imagen'          => $l['imagen'],
            ];
        }
        $colDetalle->insertMany($docs);
    } catch (Throwable $e) {
        devolver_stock($lineas);
        if (!empty($pedido['_id'])) {
            $colPedidos->deleteOne(['_id' => $pedido['_id']]);
            $colDetalle->deleteMany(['pedido_id' => $pedido['_id']]);
        }
        log_app('error', 'Fallo al crear pedido: ' . $e->getMessage(), ['usuario' => $usuarioId]);
        return ['ok' => false, 'codigo' => 'error_servidor', 'mensaje' => 'No pudimos registrar el pedido. No se realizó ningún cobro. Intenta de nuevo.'];
    }

    return ['ok' => true, 'mensaje' => 'Pedido registrado.', 'pedido' => $pedido];
}

/** Obtiene un pedido verificando que pertenezca al usuario (null si no existe o es de otro). */
function pedido_de_usuario($pedidoId, $usuarioId): ?array
{
    $pid = oid((string)$pedidoId);
    $uid = oid((string)$usuarioId);
    if (!$pid || !$uid) {
        return null;
    }
    $p = mongo()->selectCollection('pedidos')->findOne(['_id' => $pid, 'usuario_id' => $uid]);
    return $p ? (array)$p : null;
}

function pedido_por_id($pedidoId): ?array
{
    $pid = oid((string)$pedidoId);
    if (!$pid) {
        return null;
    }
    $p = mongo()->selectCollection('pedidos')->findOne(['_id' => $pid]);
    return $p ? (array)$p : null;
}

/** Líneas del pedido con imagen (usa la guardada o la actual del producto). */
function pedido_detalles(array $pedido): array
{
    $db = mongo();
    $detalles = [];
    $faltanImagen = [];
    foreach ($db->selectCollection('pedido_detalle')->find(['pedido_id' => $pedido['_id']]) as $d) {
        $d = (array)$d;
        $d['cantidad'] = (int)($d['cantidad'] ?? 0);
        $d['precio_unitario'] = (float)($d['precio_unitario'] ?? 0);
        $d['subtotal'] = isset($d['subtotal']) ? (float)$d['subtotal'] : $d['precio_unitario'] * $d['cantidad'];
        $d['nombre_producto'] = (string)($d['nombre_producto'] ?? 'Producto');
        if (empty($d['imagen']) && !empty($d['producto_id'])) {
            $faltanImagen[(string)$d['producto_id']] = $d['producto_id'];
        }
        $detalles[] = $d;
    }
    if ($faltanImagen) {
        $imgs = [];
        foreach ($db->selectCollection('productos')->find(['_id' => ['$in' => array_values($faltanImagen)]], ['projection' => ['imagen' => 1]]) as $p) {
            $imgs[(string)$p['_id']] = (string)($p['imagen'] ?? '');
        }
        foreach ($detalles as &$d) {
            if (empty($d['imagen'])) {
                $d['imagen'] = $imgs[(string)($d['producto_id'] ?? '')] ?? '';
            }
        }
        unset($d);
    }
    return $detalles;
}

/**
 * Cambia el estado de un pedido registrando el historial.
 * Si pasa a Cancelado / Pago rechazado devuelve el inventario (una sola vez).
 * Si sale de esos estados vuelve a reservar inventario.
 * @return array{ok:bool,mensaje:string}
 */
function pedido_cambiar_estado(array $pedido, string $nuevo, string $por, array $extra = []): array
{
    if (!in_array($nuevo, estados_pedido(), true)) {
        return ['ok' => false, 'mensaje' => 'Estado no válido.'];
    }
    $actual = pedido_estado($pedido);
    if ($actual === $nuevo && !$extra) {
        return ['ok' => true, 'mensaje' => 'El pedido ya estaba en estado "' . $nuevo . '".'];
    }
    $col = mongo()->selectCollection('pedidos');
    $stockDevuelto = !empty($pedido['stock_devuelto']);
    $devolver = in_array($nuevo, estados_sin_stock(), true) && !$stockDevuelto;
    $reservar = !in_array($nuevo, estados_sin_stock(), true) && $stockDevuelto;
    $lineas = ($devolver || $reservar)
        ? array_map(fn($d) => ['producto_id' => (string)$d['producto_id'], 'cantidad' => (int)$d['cantidad'], 'nombre' => $d['nombre_producto']], pedido_detalles($pedido))
        : [];

    // Si hay que volver a descontar inventario, se intenta antes de cambiar el estado
    if ($reservar) {
        $r = reservar_stock($lineas);
        if (!$r['ok']) {
            return ['ok' => false, 'mensaje' => $r['mensaje']];
        }
    }

    // Cambio atómico: solo se aplica si el pedido sigue en el estado que se leyó.
    // Evita, por ejemplo, devolver el inventario dos veces si dos peticiones llegan a la vez.
    $set = $extra;
    $set['estado'] = $nuevo;
    $set['actualizado_en'] = nowUTC();
    if ($devolver || $reservar) {
        $set['stock_devuelto'] = $devolver;
    }
    $filtro = ['_id' => $pedido['_id']];
    $filtro += isset($pedido['estado']) ? ['estado' => $pedido['estado']] : ['estado' => ['$exists' => false]];
    $r = $col->updateOne($filtro, ['$set' => $set, '$push' => ['historial_estados' => ['estado' => $nuevo, 'fecha' => nowUTC(), 'por' => $por]]]);
    if ($r->getModifiedCount() !== 1) {
        if ($reservar) {
            devolver_stock($lineas);
        }
        return ['ok' => false, 'mensaje' => 'El pedido cambió mientras tanto. Recarga la página e intenta de nuevo.'];
    }
    if ($devolver) {
        devolver_stock($lineas);
    }
    return ['ok' => true, 'mensaje' => 'Estado actualizado a "' . $nuevo . '".'];
}

/** Fecha límite de pago de un pedido pendiente (UTCDateTime) o null. */
function pedido_vence(array $pedido): ?\MongoDB\BSON\UTCDateTime
{
    if (pedido_estado($pedido) !== ESTADO_PENDIENTE || empty($pedido['fecha'])) {
        return null;
    }
    // Se cuenta desde la última vez que quedó pendiente (por ejemplo, al reintentar el pago)
    $desde = $pedido['fecha'];
    foreach ((array)($pedido['historial_estados'] ?? []) as $h) {
        if (($h['estado'] ?? '') === ESTADO_PENDIENTE && !empty($h['fecha']) && empty($h['nota'])) {
            $desde = $h['fecha'];
        }
    }
    $horas = max(1, (int)config('horas_reserva', 24));
    return new \MongoDB\BSON\UTCDateTime((int)(string)$desde + $horas * 3600 * 1000);
}

/** Cancela el pedido si su reserva venció. Devuelve el pedido actualizado. */
function liberar_si_vencido(array $pedido): array
{
    $vence = pedido_vence($pedido);
    if ($vence && (int)(string)$vence <= (int)(string)nowUTC()) {
        $r = pedido_cambiar_estado($pedido, ESTADO_CANCELADO, 'sistema: reserva vencida', ['cancelado_por_vencimiento' => true]);
        if ($r['ok']) {
            log_app('info', 'Pedido cancelado por reserva vencida', ['pedido' => (string)$pedido['_id']]);
        }
        return pedido_por_id((string)$pedido['_id']) ?? $pedido;
    }
    return $pedido;
}

/**
 * Cancela los pedidos "Pendiente de pago" cuya reserva venció y devuelve su inventario.
 * Se ejecuta como máximo cada 5 minutos (o siempre con $forzar). Devuelve cuántos canceló.
 */
function liberar_pedidos_vencidos(bool $forzar = false): int
{
    try {
        $db = mongo();
        $ahora = time();
        if (!$forzar) {
            $marca = $db->selectCollection('contadores')->findOneAndUpdate(
                ['_id' => 'limpieza_reservas', 'ultima' => ['$lt' => $ahora - 300]],
                ['$set' => ['ultima' => $ahora]]
            );
            if (!$marca) {
                if ($db->selectCollection('contadores')->countDocuments(['_id' => 'limpieza_reservas']) > 0) {
                    return 0; // ya se ejecutó hace menos de 5 minutos
                }
                $db->selectCollection('contadores')->updateOne(['_id' => 'limpieza_reservas'], ['$set' => ['ultima' => $ahora]], ['upsert' => true]);
            }
        }
        $limite = new \MongoDB\BSON\UTCDateTime(($ahora - max(1, (int)config('horas_reserva', 24)) * 3600) * 1000);
        $n = 0;
        // Candidatos: pendientes creados antes del límite (luego se valida con la fecha exacta)
        foreach ($db->selectCollection('pedidos')->find(['estado' => ESTADO_PENDIENTE, 'fecha' => ['$lt' => $limite]], ['limit' => 200]) as $p) {
            $antes = pedido_estado((array)$p);
            if (pedido_estado(liberar_si_vencido((array)$p)) !== $antes) {
                $n++;
            }
        }
        return $n;
    } catch (Throwable $e) {
        log_app('aviso', 'No se pudieron liberar reservas vencidas: ' . $e->getMessage());
        return 0;
    }
}

/** Referencia de pago simulada (no corresponde a ninguna transacción bancaria real). */
function referencia_pago_simulada(): string
{
    return 'SIM-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Datos de todas las líneas de varios pedidos (evita una consulta por pedido).
 * @return array<string,array> pedido_id => [detalles]
 */
function detalles_de_pedidos(array $pedidoIds): array
{
    $out = [];
    if (!$pedidoIds) {
        return $out;
    }
    foreach (mongo()->selectCollection('pedido_detalle')->find(['pedido_id' => ['$in' => array_values($pedidoIds)]]) as $d) {
        $out[(string)$d['pedido_id']][] = (array)$d;
    }
    return $out;
}
