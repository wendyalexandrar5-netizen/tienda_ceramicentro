<?php
/**
 * Núcleo compartido de CERAMISHOP.
 *
 * Todas las páginas lo cargan con:  require_once __DIR__ . '/includes/bootstrap.php';
 * Se encarga de: configuración, manejo de errores, HTTPS, cabeceras de seguridad,
 * sesión segura y funciones de ayuda (escape, CSRF, mensajes, formatos, rutas).
 *
 * Para endpoints JSON (API) definir antes:  define('CS_API', true);
 */

if (defined('CS_BOOTSTRAP')) {
    return;
}
define('CS_BOOTSTRAP', true);
define('CS_ROOT', dirname(__DIR__));

require_once CS_ROOT . '/vendor/autoload.php';

/* ================================================================
 * CONFIGURACIÓN
 * ================================================================ */

function cs_config_por_defecto(): array
{
    return [
        'entorno'        => 'desarrollo',
        'url_sitio'      => '',
        'forzar_https'   => false,
        'hsts'           => false,
        'mongo'          => ['uri' => 'mongodb://localhost:27017', 'base_de_datos' => 'ceramicentro_mongo'],
        'clave_app'      => '',
        // La contraseña SMTP nunca va en el código: se define en config/config.php
        'smtp'           => ['host' => 'smtp.gmail.com', 'puerto' => 587, 'usuario' => 'ceramicentro6@gmail.com', 'clave' => '', 'remitente' => '', 'destino' => ''],
        'empresa'        => [
            'nombre' => 'CERAMICENTRO', 'tienda' => 'CERAMISHOP', 'whatsapp' => '573134322830',
            'correo' => 'ceramicentro6@gmail.com', 'telefono' => '', 'direccion' => '', 'ciudad' => '', 'facebook' => '', 'instagram' => '',
        ],
        'analitica'      => ['ga4_id' => ''],
        'api'            => [
            'origenes'    => ['https://localhost', 'http://localhost', 'capacitor://localhost'],
            'modo_legado' => false,
            'dias_token'  => 30,
        ],
        // Proyecto académico: se muestra un aviso en el pie y en los textos legales
        'academico'      => ['activo' => true, 'institucion' => '', 'programa' => '', 'autores' => '', 'anio' => '2025'],
        'minutos_sesion' => 120,
        // false = Bootstrap, íconos y fuentes desde assets/vendor (funciona sin internet)
        'recursos_cdn'   => false,
        // Horas que un pedido sin pagar mantiene reservado el inventario antes de cancelarse solo
        'horas_reserva'  => 24,
    ];
}

/**
 * Lee un valor de configuración usando notación con puntos: config('mongo.uri').
 */
function config(string $clave, $defecto = null)
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = cs_config_por_defecto();
        $archivo = CS_ROOT . '/config/config.php';
        if (is_file($archivo)) {
            $local = require $archivo;
            if (is_array($local)) {
                $cfg = array_replace_recursive($cfg, $local);
            }
        }
        // Variables de entorno (útiles en servidores donde no se quiere un archivo con secretos)
        $env = [
            'CS_ENTORNO' => ['entorno'], 'CS_URL_SITIO' => ['url_sitio'],
            'CS_MONGO_URI' => ['mongo', 'uri'], 'CS_MONGO_DB' => ['mongo', 'base_de_datos'],
            'CS_CLAVE_APP' => ['clave_app'], 'CS_SMTP_USUARIO' => ['smtp', 'usuario'],
            'CS_SMTP_CLAVE' => ['smtp', 'clave'], 'CS_GA4_ID' => ['analitica', 'ga4_id'],
        ];
        foreach ($env as $var => $ruta) {
            $valor = getenv($var);
            if ($valor !== false && $valor !== '') {
                $ref = &$cfg;
                foreach ($ruta as $parte) {
                    $ref = &$ref[$parte];
                }
                $ref = $valor;
                unset($ref);
            }
        }
        if (getenv('CS_FORZAR_HTTPS') === '1') {
            $cfg['forzar_https'] = true;
        }
    }
    $valor = $cfg;
    foreach (explode('.', $clave) as $parte) {
        if (!is_array($valor) || !array_key_exists($parte, $valor)) {
            return $defecto;
        }
        $valor = $valor[$parte];
    }
    return $valor;
}

function es_produccion(): bool
{
    return config('entorno') === 'produccion';
}

/* ================================================================
 * REGISTRO DE ERRORES (logs/app.log) — nunca se muestran al usuario
 * ================================================================ */

function log_app(string $nivel, string $mensaje, array $contexto = []): void
{
    // Nunca registrar contraseñas ni tokens
    foreach (['password', 'contraseña', 'contrasena', 'clave', 'token'] as $sensible) {
        if (isset($contexto[$sensible])) {
            $contexto[$sensible] = '***';
        }
    }
    $linea = sprintf(
        "[%s] %s: %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($nivel),
        $mensaje,
        $contexto ? json_encode($contexto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : ''
    );
    $dir = CS_ROOT . '/logs';
    if (is_dir($dir) && is_writable($dir)) {
        @file_put_contents($dir . '/app.log', $linea, FILE_APPEND | LOCK_EX);
    } else {
        error_log(trim($linea));
    }
}

ini_set('display_errors', es_produccion() ? '0' : (PHP_SAPI === 'cli' ? '1' : '0'));
ini_set('log_errors', '1');
error_reporting(E_ALL);
date_default_timezone_set('America/Bogota');
mb_internal_encoding('UTF-8');

set_exception_handler(function (Throwable $e) {
    log_app('error', get_class($e) . ': ' . $e->getMessage(), [
        'archivo' => $e->getFile() . ':' . $e->getLine(),
        'url'     => $_SERVER['REQUEST_URI'] ?? '',
    ]);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $e . "\n");
        exit(1);
    }
    $esConexion = $e instanceof \MongoDB\Driver\Exception\ConnectionException
        || $e instanceof \MongoDB\Driver\Exception\ConnectionTimeoutException;
    if (defined('CS_API')) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        json_respuesta([
            'success' => false,
            'codigo'  => $esConexion ? 'servicio_no_disponible' : 'error_servidor',
            'message' => $esConexion
                ? 'El servicio no está disponible en este momento. Intenta de nuevo en unos minutos.'
                : 'Ocurrió un error en el servidor. Intenta de nuevo.',
        ], $esConexion ? 503 : 500);
    }
    mostrar_error(
        $esConexion ? 503 : 500,
        $esConexion ? 'Servicio no disponible' : 'Algo salió mal',
        $esConexion
            ? 'No pudimos conectarnos con el servidor de datos. Intenta de nuevo en unos minutos.'
            : 'Ocurrió un error inesperado. Ya quedó registrado para revisarlo. Intenta de nuevo.'
    );
});

/* ================================================================
 * RUTAS Y URLS
 * ================================================================ */

/** Carpeta web donde está instalada la tienda (ej. "/tiendaonline_mongodb" o ""). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    if (PHP_SAPI === 'cli' && empty($_SERVER['SCRIPT_NAME'])) {
        return $base = '';
    }
    $script  = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $archivo = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $raiz    = realpath(CS_ROOT) ?: CS_ROOT;
    $archivo = str_replace('\\', '/', $archivo);
    $raiz    = str_replace('\\', '/', $raiz);
    if ($archivo !== '' && strpos($archivo, $raiz) === 0) {
        $relativo = substr($archivo, strlen($raiz)); // ej. /api/api_login.php
        if ($relativo !== '' && substr($script, -strlen($relativo)) === $relativo) {
            return $base = rtrim(substr($script, 0, -strlen($relativo)), '/');
        }
    }
    return $base = rtrim(str_replace('\\', '/', dirname($script)), '/.');
}

/** URL relativa al sitio: url('tienda.php') => /tiendaonline_mongodb/tienda.php */
function url(string $ruta = '', array $params = []): string
{
    $u = base_path() . '/' . ltrim($ruta, '/');
    if ($params) {
        $params = array_filter($params, fn($v) => $v !== null && $v !== '');
        if ($params) {
            $u .= (strpos($u, '?') === false ? '?' : '&') . http_build_query($params);
        }
    }
    return $u;
}

function es_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    // Detrás de un proxy / balanceador que termina SSL
    return strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

/** URL absoluta (para sitemap, Open Graph, JSON-LD, API). */
function url_absoluta(string $ruta = '', array $params = []): string
{
    $sitio = rtrim((string)config('url_sitio', ''), '/');
    if ($sitio === '') {
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        // Evitar inyección de cabecera Host con caracteres extraños
        if (!preg_match('/^[a-z0-9.\-:\[\]]+$/i', $host)) {
            $host = 'localhost';
        }
        $sitio = (es_https() ? 'https' : 'http') . '://' . $host . base_path();
    }
    $u = $sitio . '/' . ltrim($ruta, '/');
    if ($params) {
        $u .= '?' . http_build_query($params);
    }
    return $u;
}

/** Recurso estático con versión para invalidar caché cuando cambia. */
function asset(string $ruta): string
{
    $archivo = CS_ROOT . '/' . ltrim($ruta, '/');
    $v = is_file($archivo) ? substr((string)filemtime($archivo), -6) : '1';
    return url($ruta) . '?v=' . $v;
}

function redirigir(string $ruta, array $params = []): void
{
    $destino = preg_match('#^https?://#i', $ruta) ? $ruta : url($ruta, $params);
    header('Location: ' . $destino, true, 303);
    exit;
}

/* ================================================================
 * HTTPS Y CABECERAS DE SEGURIDAD
 * ================================================================ */

if (PHP_SAPI !== 'cli') {
    $hostActual = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? ''));
    $esLocal = in_array($hostActual, ['localhost', '127.0.0.1', '::1'], true)
        || preg_match('/^(10|192\.168|172\.(1[6-9]|2\d|3[01]))\./', $hostActual);

    if (config('forzar_https') && !es_https() && !$esLocal) {
        header('Location: https://' . $hostActual . ($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
        exit;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    if (!defined('CS_API')) {
        header('X-Frame-Options: SAMEORIGIN');
    }
    if (es_https() && config('hsts')) {
        header('Strict-Transport-Security: max-age=31536000');
    }
}

/* ================================================================
 * SESIÓN SEGURA
 * ================================================================ */

function iniciar_sesion(): void
{
    if (session_status() !== PHP_SESSION_NONE || PHP_SAPI === 'cli') {
        return;
    }
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => (base_path() ?: '') . '/',
        'secure'   => es_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    // Cierre por inactividad
    $limite = max(5, (int)config('minutos_sesion', 120)) * 60;
    $ahora  = time();
    if (isset($_SESSION['usuario'], $_SESSION['ultima_actividad']) && ($ahora - $_SESSION['ultima_actividad']) > $limite) {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['sesion_expirada'] = true;
    }
    $_SESSION['ultima_actividad'] = $ahora;
}

if (!defined('CS_API') && !defined('CS_SIN_SESION')) {
    iniciar_sesion();
}

/** Inicia sesión de un usuario (previene fijación de sesión). */
function sesion_login(array $usuario): void
{
    session_regenerate_id(true);
    $carrito = $_SESSION['carrito'] ?? null;
    $_SESSION = [];
    $_SESSION['usuario'] = [
        'id'     => (string)$usuario['_id'],
        'nombre' => (string)($usuario['nombre'] ?? ''),
        'correo' => (string)($usuario['correo'] ?? ''),
        'rol'    => (string)($usuario['rol'] ?? ''),
    ];
    if ($carrito && ($usuario['rol'] ?? '') === 'cliente') {
        $_SESSION['carrito'] = $carrito;
    }
    $_SESSION['ultima_actividad'] = time();
}

function sesion_cerrar(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function usuario_actual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function es_cliente(): bool
{
    return (usuario_actual()['rol'] ?? '') === 'cliente';
}

function es_admin(): bool
{
    return (usuario_actual()['rol'] ?? '') === 'administrador';
}

/**
 * Exige sesión iniciada (y opcionalmente un rol). Compatible con verificarSesion().
 * @param string|array|null $roles
 */
function requerir_sesion($roles = null): void
{
    if (!usuario_actual()) {
        $_SESSION['volver_a'] = $_SERVER['REQUEST_URI'] ?? '';
        flash('info', 'Inicia sesión para continuar.');
        redirigir('login.php');
    }
    if ($roles === null) {
        return;
    }
    $roles = (array)$roles;
    if (!in_array(usuario_actual()['rol'] ?? '', $roles, true)) {
        redirigir('acceso_denegado.php');
    }
}

/* ================================================================
 * CSRF
 * ================================================================ */

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_campo(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_valido(): bool
{
    $enviado = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($enviado) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $enviado);
}

/** Corta la petición si el token CSRF no es válido. */
function csrf_verificar(): void
{
    if (!csrf_valido()) {
        log_app('aviso', 'Token CSRF inválido', ['url' => $_SERVER['REQUEST_URI'] ?? '']);
        if (es_ajax()) {
            json_respuesta(['success' => false, 'codigo' => 'csrf', 'message' => 'Tu sesión cambió o expiró. Recarga la página e intenta de nuevo.'], 419);
        }
        flash('error', 'Tu sesión cambió o expiró. Por favor intenta de nuevo.');
        $volver = $_SERVER['HTTP_REFERER'] ?? '';
        if ($volver && parse_url($volver, PHP_URL_HOST) === parse_url(url_absoluta(), PHP_URL_HOST)) {
            header('Location: ' . $volver, true, 303);
            exit;
        }
        redirigir('index.php');
    }
}

function es_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function es_ajax(): bool
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

/* ================================================================
 * MENSAJES FLASH
 * ================================================================ */

/** @param string $tipo success | error | info | warning */
function flash(string $tipo, string $mensaje): void
{
    $_SESSION['flash'][] = ['tipo' => $tipo, 'mensaje' => $mensaje];
}

function flash_obtener(): array
{
    $m = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $m;
}

/* ================================================================
 * UTILIDADES
 * ================================================================ */

function e($valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function es_object_id($id): bool
{
    return is_string($id) && preg_match('/^[a-f\d]{24}$/i', $id) === 1;
}

/** Devuelve un ObjectId o null si el texto no es válido. */
function oid($id): ?\MongoDB\BSON\ObjectId
{
    if ($id instanceof \MongoDB\BSON\ObjectId) {
        return $id;
    }
    return es_object_id($id) ? new \MongoDB\BSON\ObjectId($id) : null;
}

/** Formato de pesos colombianos: $ 1.234.500 (con decimales solo si existen). */
function dinero($valor): string
{
    $valor = (float)$valor;
    $dec = (abs($valor - round($valor)) > 0.004) ? 2 : 0;
    return '$' . number_format($valor, $dec, ',', '.');
}

/** Convierte una fecha de MongoDB a texto en hora de Colombia. */
function fecha_local($fecha, string $formato = 'd/m/Y h:i a'): string
{
    if ($fecha instanceof \MongoDB\BSON\UTCDateTime) {
        $dt = $fecha->toDateTime();
    } elseif ($fecha instanceof DateTimeInterface) {
        $dt = DateTime::createFromInterface($fecha);
    } elseif (is_string($fecha) && $fecha !== '') {
        try {
            $dt = new DateTime($fecha);
        } catch (Throwable $e) {
            return $fecha;
        }
    } else {
        return '—';
    }
    $dt->setTimezone(new DateTimeZone('America/Bogota'));
    return $dt->format($formato);
}

/** Recorta texto respetando palabras. */
function resumen(string $texto, int $max = 140): string
{
    $texto = trim(preg_replace('/\s+/u', ' ', $texto));
    if (mb_strlen($texto) <= $max) {
        return $texto;
    }
    $corte = mb_substr($texto, 0, $max);
    $espacio = mb_strrpos($corte, ' ');
    return rtrim(mb_substr($corte, 0, $espacio ?: $max), ' ,.;:') . '…';
}

/** Escapa texto para usarlo literalmente dentro de una expresión regular de MongoDB. */
function regex_literal(string $texto): string
{
    return preg_quote(mb_substr(trim($texto), 0, 80), '/');
}

function json_respuesta(array $datos, int $estado = 200): void
{
    if (!headers_sent()) {
        http_response_code($estado);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
    }
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Muestra una página de error amigable con el diseño del sitio y termina. */
function mostrar_error(int $codigo, string $titulo, string $mensaje): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($codigo);
    }
    $errorCodigo  = $codigo;
    $errorTitulo  = $titulo;
    $errorMensaje = $mensaje;
    require CS_ROOT . '/includes/pagina_error.php';
    exit;
}

function no_encontrado(string $mensaje = 'La página o el recurso que buscas no existe o fue movido.'): void
{
    mostrar_error(404, 'No encontramos lo que buscas', $mensaje);
}

/** IP del cliente (solo para límites de intentos; no se muestra ni se comparte). */
function ip_cliente(): string
{
    return (string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

require_once CS_ROOT . '/conexion_mongo.php';
