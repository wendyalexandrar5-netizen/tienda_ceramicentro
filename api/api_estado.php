<?php
/** GET /api/api_estado.php -> comprueba que el servidor y MongoDB respondan (lo usa la app). */
require_once __DIR__ . '/_comun.php';
api_metodo('GET');
try {
    mongo()->command(['ping' => 1]);
    json_respuesta(['success' => true, 'servidor' => 'ok', 'base_de_datos' => 'ok', 'hora' => date('c')]);
} catch (Throwable $e) {
    log_app('error', 'api_estado: MongoDB no responde: ' . $e->getMessage());
    api_error('servicio_no_disponible', 'El servidor está en línea, pero la base de datos no responde.', 503, ['servidor' => 'ok', 'base_de_datos' => 'error']);
}
