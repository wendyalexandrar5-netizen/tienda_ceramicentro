<?php
/** POST /api/api_logout.php  (con token) -> invalida el token de la app. */
require_once __DIR__ . '/_comun.php';
api_metodo('POST');
$t = api_token();
if ($t !== '') {
    token_app_revocar($t);
}
json_respuesta(['success' => true, 'message' => 'Sesión cerrada.']);
