<?php
/**
 * POST /api/api_login.php   {correo, password}
 * Respuesta: {success, message, usuario:{id,nombre,correo,rol}, token}
 */
require_once __DIR__ . '/_comun.php';
api_metodo('POST');

$d = api_datos();
$correo = normalizar_correo((string)($d['correo'] ?? ''));
$password = (string)($d['password'] ?? '');

if ($correo === '' || $password === '') {
    api_error('validacion', 'Correo y contraseña son obligatorios.', 422);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    api_error('validacion', 'El correo no tiene un formato válido.', 422);
}
if ($min = login_bloqueado($correo)) {
    api_error('demasiados_intentos', 'Demasiados intentos fallidos. Espera ' . $min . ' minuto(s) e intenta de nuevo.', 429);
}

$usuario = usuario_por_correo($correo, ['_id' => 1, 'nombre' => 1, 'correo' => 1, 'rol' => 1, 'contraseña' => 1, 'contrasena' => 1, 'activo' => 1]);
if (!$usuario || !verificar_clave_usuario($usuario, $password)) {
    login_registrar_fallo($correo);
    api_error('credenciales', 'Correo o contraseña incorrectos.', 401);
}
if (($usuario['activo'] ?? true) === false) {
    api_error('cuenta_inactiva', 'Tu cuenta está desactivada. Comunícate con CERAMICENTRO.', 403);
}
if (($usuario['rol'] ?? '') !== 'cliente') {
    api_error('no_autorizado', 'Esta app es solo para clientes. Los administradores usan el panel web.', 403);
}
login_limpiar_intentos($correo);

json_respuesta([
    'success' => true,
    'message' => 'Login correcto.',
    'usuario' => api_usuario_publico($usuario),
    'token'   => token_app_crear($usuario['_id']),
]);
