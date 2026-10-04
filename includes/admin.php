<?php
/** Funciones compartidas del panel administrativo. */
require_once __DIR__ . '/layout.php';
require_once __DIR__ . '/tienda.php';

/**
 * Registra un cambio de producto en historial_productos (auditoría).
 * @param string $accion agrego | edito | elimino
 */
function registrar_historial(string $accion, array $producto, array $cambios, ?string $catAnterior = null, ?string $catNueva = null): void
{
    $u = usuario_actual();
    $doc = [
        'id_admin'        => oid($u['id'] ?? ''),
        'nombre_admin'    => (string)($u['nombre'] ?? ''),
        'id_producto'     => $producto['_id'] ?? null,
        'producto_nombre' => (string)($producto['nombre'] ?? ''),
        'accion'          => $accion,
        'cambios'         => array_values($cambios),
        'fecha'           => nowUTC(),
    ];
    if ($catAnterior !== null || $catNueva !== null) {
        $doc['categoria_anterior'] = $catAnterior;
        $doc['categoria_nueva'] = $catNueva;
    }
    try {
        mongo()->selectCollection('historial_productos')->insertOne($doc);
    } catch (Throwable $e) {
        log_app('error', 'No se pudo registrar historial: ' . $e->getMessage());
    }
}

/** Botones de exportación con indicador de carga. */
function boton_exportar(string $ruta, array $params = [], string $texto = 'Exportar a Excel', string $tipo = 'excel'): string
{
    $icono = $tipo === 'pdf' ? 'file-earmark-pdf' : 'file-earmark-excel';
    $clase = $tipo === 'pdf' ? 'btn-outline-danger' : 'btn-success';
    return '<a href="' . e(url($ruta, $params)) . '" class="btn ' . $clase . '" data-descarga="' . e($tipo) . '"><i class="bi bi-' . $icono . '" aria-hidden="true"></i> ' . e($texto) . '</a>';
}

/** Convierte "AAAA-MM-DD" (hora Colombia) en UTCDateTime al inicio o fin del día. */
function fecha_filtro(string $fecha, bool $finDelDia = false): ?\MongoDB\BSON\UTCDateTime
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
        return null;
    }
    try {
        $dt = new DateTime($fecha . ($finDelDia ? ' 23:59:59' : ' 00:00:00'), new DateTimeZone('America/Bogota'));
    } catch (Throwable $e) {
        return null;
    }
    return new \MongoDB\BSON\UTCDateTime($dt->getTimestamp() * 1000 + ($finDelDia ? 999 : 0));
}
