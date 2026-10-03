<?php
/**
 * Utilidades comunes de la API usada por la app Android (Capacitor).
 * - Respuestas JSON consistentes: {success, codigo, message, ...}
 * - CORS limitado a los orígenes configurados
 * - Autenticación por token (cabecera X-Auth-Token o Authorization: Bearer)
 * - Nunca expone mensajes internos ni trazas: los errores se registran en logs/app.log
 */
define('CS_API', true);
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/seguridad.php';

// CORS: solo orígenes permitidos (la app Capacitor usa https://localhost)
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origen !== '' && in_array($origen, (array)config('api.origenes', []), true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Vary: Origin');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Auth-Token');
    header('Access-Control-Max-Age: 600');
}
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/** Exige un método HTTP. */
function api_metodo(string ...$metodos): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', $metodos, true)) {
        header('Allow: ' . implode(', ', $metodos));
        api_error('metodo_no_permitido', 'Método no permitido.', 405);
    }
}

function api_error(string $codigo, string $mensaje, int $estado = 400, array $extra = []): void
{
    json_respuesta(['success' => false, 'codigo' => $codigo, 'message' => $mensaje] + $extra, $estado);
}

/** Cuerpo JSON de la petición (array vacío si no es válido). */
function api_datos(): array
{
    static $datos = null;
    if ($datos === null) {
        $crudo = file_get_contents('php://input', false, null, 0, 1024 * 512);
        $datos = json_decode((string)$crudo, true);
        if (!is_array($datos)) {
            $datos = $_POST ?: [];
        }
    }
    return $datos;
}

function api_token(): string
{
    $t = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
    if ($t === '') {
        $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
        if (stripos($auth, 'Bearer ') === 0) {
            $t = trim(substr($auth, 7));
        }
    }
    return (string)$t;
}

/**
 * Devuelve el usuario autenticado por token.
 * En "modo legado" (config api.modo_legado) acepta el id enviado por apps antiguas sin token.
 */
function api_usuario(?string $idLegado = null): array
{
    $token = api_token();
    if ($token !== '') {
        $u = token_app_usuario($token);
        if (!$u) {
            api_error('sesion_expirada', 'Tu sesión expiró. Inicia sesión de nuevo.', 401);
        }
        if (($u['rol'] ?? '') !== 'cliente') {
            api_error('no_autorizado', 'Esta cuenta no puede usar la app de clientes.', 403);
        }
        return $u;
    }
    if (config('api.modo_legado') && $idLegado && es_object_id($idLegado)) {
        $u = mongo()->selectCollection('usuarios')->findOne(['_id' => oid($idLegado), 'rol' => 'cliente'], ['projection' => ['nombre' => 1, 'correo' => 1, 'rol' => 1]]);
        if ($u) {
            return (array)$u;
        }
    }
    api_error('sesion_requerida', 'Debes iniciar sesión.', 401);
}

/** URL pública de una imagen de producto (se adapta al servidor: IP local o dominio). */
function api_url_imagen(string $ruta): string
{
    $ruta = imagen_ruta_segura_api($ruta);
    return $ruta === '' ? '' : url_absoluta(implode('/', array_map('rawurlencode', explode('/', $ruta))));
}

function imagen_ruta_segura_api(string $ruta): string
{
    $ruta = str_replace('\\', '/', trim($ruta));
    if ($ruta === '' || strpos($ruta, '..') !== false || preg_match('#^[a-z]+:#i', $ruta)) {
        return '';
    }
    return ltrim($ruta, '/');
}

/** Enlace firmado (válido 30 minutos) para descargar el comprobante PDF desde la app. */
function api_url_comprobante(string $pedidoId, string $usuarioId): string
{
    $exp = time() + 1800;
    return url_absoluta('api/descargar_pedido_app.php', [
        'id' => $pedidoId, 'usuario_id' => $usuarioId, 'exp' => $exp, 'sig' => firma_descarga($pedidoId, $usuarioId, $exp),
    ]);
}

function api_usuario_publico(array $u): array
{
    return [
        'id'     => (string)$u['_id'],
        'nombre' => (string)($u['nombre'] ?? ''),
        'correo' => (string)($u['correo'] ?? ''),
        'rol'    => (string)($u['rol'] ?? ''),
    ];
}
