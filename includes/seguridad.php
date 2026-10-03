<?php
/**
 * Funciones de seguridad compartidas por la web y la API:
 * límite de intentos de inicio de sesión, política de contraseñas,
 * búsqueda de usuarios por correo y tokens de la app.
 */
require_once __DIR__ . '/bootstrap.php';

const INTENTOS_MAXIMOS = 5;
const MINUTOS_BLOQUEO = 15;
const CLAVE_MINIMO = 8;

function normalizar_correo(string $correo): string
{
    return mb_strtolower(trim($correo), 'UTF-8');
}

/** Busca un usuario por correo sin distinguir mayúsculas (compatible con registros antiguos). */
function usuario_por_correo(string $correo, array $proyeccion = []): ?array
{
    $correo = normalizar_correo($correo);
    if ($correo === '') {
        return null;
    }
    $opc = ['collation' => ['locale' => 'es', 'strength' => 2]];
    if ($proyeccion) {
        $opc['projection'] = $proyeccion;
    }
    $u = mongo()->selectCollection('usuarios')->findOne(['correo' => $correo], $opc);
    return $u ? (array)$u : null;
}

/** Hash almacenado (los registros antiguos usan "contraseña" o "contrasena"). */
function usuario_hash(array $u): string
{
    foreach (['contrasena', 'contraseña'] as $campo) {
        if (!empty($u[$campo]) && is_string($u[$campo])) {
            return $u[$campo];
        }
    }
    return '';
}

/** Verifica la contraseña y actualiza el hash si el algoritmo cambió (sin perder compatibilidad). */
function verificar_clave_usuario(array $u, string $clave): bool
{
    $hash = usuario_hash($u);
    if ($hash === '' || !password_verify($clave, $hash)) {
        return false;
    }
    if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
        $campo = !empty($u['contrasena']) ? 'contrasena' : 'contraseña';
        mongo()->selectCollection('usuarios')->updateOne(['_id' => $u['_id']], ['$set' => [$campo => password_hash($clave, PASSWORD_DEFAULT)]]);
    }
    return true;
}

/** Reglas de contraseña para cuentas nuevas o cambios de contraseña. Devuelve el error o ''. */
function validar_clave_nueva(string $clave): string
{
    if (mb_strlen($clave) < CLAVE_MINIMO) {
        return 'La contraseña debe tener al menos ' . CLAVE_MINIMO . ' caracteres.';
    }
    if (mb_strlen($clave) > 72) {
        return 'La contraseña es demasiado larga (máximo 72 caracteres).';
    }
    if (!preg_match('/[A-Za-zÁÉÍÓÚáéíóúÑñ]/u', $clave) || !preg_match('/\d/', $clave)) {
        return 'La contraseña debe combinar letras y números.';
    }
    return '';
}

function validar_nombre(string $nombre): string
{
    $len = mb_strlen($nombre);
    if ($len < 3 || $len > 50) {
        return 'El nombre debe tener entre 3 y 50 caracteres.';
    }
    if (!preg_match("/^[\\p{L}\\p{M} .'-]+$/u", $nombre)) {
        return 'El nombre solo puede contener letras, espacios, puntos, apóstrofos y guiones.';
    }
    return '';
}

/* ---------- Límite de intentos de inicio de sesión ---------- */

function clave_intentos(string $correo): string
{
    return hash('sha256', ip_cliente() . '|' . normalizar_correo($correo));
}

/** Minutos restantes de bloqueo (0 si puede intentar). */
function login_bloqueado(string $correo): int
{
    try {
        $desde = new \MongoDB\BSON\UTCDateTime((time() - MINUTOS_BLOQUEO * 60) * 1000);
        $col = mongo()->selectCollection('intentos_login');
        $n = $col->countDocuments(['clave' => clave_intentos($correo), 'fecha' => ['$gte' => $desde]]);
        if ($n < INTENTOS_MAXIMOS) {
            return 0;
        }
        $ultimo = $col->findOne(['clave' => clave_intentos($correo)], ['sort' => ['fecha' => -1]]);
        $seg = MINUTOS_BLOQUEO * 60 - (time() - (int)($ultimo['fecha']->toDateTime()->getTimestamp()));
        return max(1, (int)ceil($seg / 60));
    } catch (Throwable $e) {
        log_app('aviso', 'No se pudo consultar intentos de login: ' . $e->getMessage());
        return 0;
    }
}

function login_registrar_fallo(string $correo): void
{
    try {
        mongo()->selectCollection('intentos_login')->insertOne(['clave' => clave_intentos($correo), 'fecha' => nowUTC()]);
    } catch (Throwable $e) {
        log_app('aviso', 'No se pudo registrar intento de login: ' . $e->getMessage());
    }
}

function login_limpiar_intentos(string $correo): void
{
    try {
        mongo()->selectCollection('intentos_login')->deleteMany(['clave' => clave_intentos($correo)]);
    } catch (Throwable $e) {
        // no crítico
    }
}

/** Destino seguro después del login (solo rutas internas del sitio). */
function destino_seguro(?string $destino, string $porDefecto): string
{
    $destino = (string)$destino;
    if ($destino === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|\\\\)#i', $destino) || strpos($destino, "\n") !== false) {
        return url($porDefecto);
    }
    $base = base_path();
    if ($base !== '' && strpos($destino, $base . '/') === 0) {
        return $destino;
    }
    return url(ltrim($destino, '/'));
}

/* ---------- Tokens de la app Android ---------- */

/** Crea un token de sesión para la app. Solo se guarda su hash en la base de datos. */
function token_app_crear($usuarioId): string
{
    $token = bin2hex(random_bytes(32));
    $dias = max(1, (int)config('api.dias_token', 30));
    mongo()->selectCollection('tokens_app')->insertOne([
        'token_hash' => hash('sha256', $token),
        'usuario_id' => $usuarioId instanceof \MongoDB\BSON\ObjectId ? $usuarioId : oid((string)$usuarioId),
        'creado'     => nowUTC(),
        'expira'     => new \MongoDB\BSON\UTCDateTime((time() + $dias * 86400) * 1000),
    ]);
    return $token;
}

/** Devuelve el usuario dueño del token o null si no es válido / expiró. */
function token_app_usuario(string $token): ?array
{
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        return null;
    }
    $t = mongo()->selectCollection('tokens_app')->findOne([
        'token_hash' => hash('sha256', $token),
        'expira'     => ['$gt' => nowUTC()],
    ]);
    if (!$t) {
        return null;
    }
    $u = mongo()->selectCollection('usuarios')->findOne(['_id' => $t['usuario_id']], ['projection' => ['nombre' => 1, 'correo' => 1, 'rol' => 1]]);
    return $u ? (array)$u : null;
}

function token_app_revocar(string $token): void
{
    mongo()->selectCollection('tokens_app')->deleteOne(['token_hash' => hash('sha256', $token)]);
}

/** Firma HMAC para enlaces de descarga de la app (válidos por tiempo limitado). */
function firma_descarga(string $pedidoId, string $usuarioId, int $expira): string
{
    $clave = (string)config('clave_app', '');
    if ($clave === '' || strpos($clave, 'CAMBIAR') === 0) {
        // Clave derivada de la instalación si no se configuró una (mejor que nada; configure clave_app)
        $clave = hash('sha256', CS_ROOT . php_uname() . (string)config('mongo.uri'));
    }
    return hash_hmac('sha256', $pedidoId . '|' . $usuarioId . '|' . $expira, $clave);
}
