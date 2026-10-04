<?php
/**
 * POST /api/api_registro.php   {nombre, correo, password}
 * Respuesta: {success, message, usuario, token}
 */
require_once __DIR__ . '/_comun.php';
api_metodo('POST');

$d = api_datos();
$nombre = trim(preg_replace('/\s+/u', ' ', (string)($d['nombre'] ?? '')));
$correo = normalizar_correo((string)($d['correo'] ?? ''));
$password = (string)($d['password'] ?? '');

if ($nombre === '' || $correo === '' || $password === '') {
    api_error('validacion', 'Todos los campos son obligatorios.', 422);
}
if ($m = validar_nombre($nombre)) {
    api_error('validacion', $m, 422, ['campo' => 'nombre']);
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 120) {
    api_error('validacion', 'Escribe un correo electrónico válido.', 422, ['campo' => 'correo']);
}
if ($m = validar_clave_nueva($password)) {
    api_error('validacion', $m, 422, ['campo' => 'password']);
}
if (usuario_por_correo($correo, ['_id' => 1])) {
    api_error('correo_existente', 'Este correo ya está registrado. Inicia sesión.', 409, ['campo' => 'correo']);
}

$doc = [
    'nombre'     => $nombre,
    'correo'     => $correo,
    'contrasena' => password_hash($password, PASSWORD_DEFAULT),
    'rol'        => 'cliente',
    'createdAt'  => nowUTC(),
    'origen'     => 'app_android',
];
$doc['_id'] = mongo()->selectCollection('usuarios')->insertOne($doc)->getInsertedId();

json_respuesta([
    'success' => true,
    'message' => 'Registro exitoso.',
    'usuario' => api_usuario_publico($doc),
    'token'   => token_app_crear($doc['_id']),
], 201);
