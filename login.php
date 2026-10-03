<?php
/** Inicio de sesión de clientes y administradores. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/seguridad.php';

// Si ya tiene sesión, enviarlo a su área
if (es_admin()) {
    redirigir('panel_admin.php');
}
if (es_cliente()) {
    redirigir('tienda.php');
}

$error = '';
$correo = '';
if (isset($_GET['volver'])) {
    $_SESSION['volver_a'] = (string)$_GET['volver'];
}

if (es_post()) {
    csrf_verificar();
    $correo = normalizar_correo((string)($_POST['correo'] ?? ''));
    $clave  = (string)($_POST['contraseña'] ?? ($_POST['contrasena'] ?? ''));

    if ($correo === '' || $clave === '') {
        $error = 'Escribe tu correo y tu contraseña.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } elseif ($min = login_bloqueado($correo)) {
        $error = 'Demasiados intentos fallidos. Por seguridad, espera ' . $min . ' minuto' . ($min === 1 ? '' : 's') . ' e intenta de nuevo.';
    } else {
        $usuario = usuario_por_correo($correo, ['_id' => 1, 'nombre' => 1, 'correo' => 1, 'rol' => 1, 'contraseña' => 1, 'contrasena' => 1, 'activo' => 1]);
        if ($usuario && verificar_clave_usuario($usuario, $clave)) {
            if (($usuario['activo'] ?? true) === false) {
                $error = 'Tu cuenta está desactivada. Comunícate con CERAMICENTRO.';
            } elseif (!in_array($usuario['rol'] ?? '', ['administrador', 'cliente'], true)) {
                $error = 'Tu cuenta no tiene un rol válido. Comunícate con CERAMICENTRO.';
                log_app('aviso', 'Usuario con rol no reconocido', ['id' => (string)$usuario['_id']]);
            } else {
                login_limpiar_intentos($correo);
                $volver = $_SESSION['volver_a'] ?? '';
                sesion_login($usuario);
                if ($usuario['rol'] === 'administrador') {
                    redirigir('panel_admin.php');
                }
                flash('success', '¡Hola, ' . explode(' ', trim((string)$usuario['nombre']))[0] . '! Qué bueno verte.');
                header('Location: ' . destino_seguro($volver, 'tienda.php'), true, 303);
                exit;
            }
        } else {
            login_registrar_fallo($correo);
            // Mensaje genérico: no revela si el correo existe
            $error = 'Correo o contraseña incorrectos.';
        }
    }
}

layout_inicio([
    'titulo'      => 'Iniciar sesión',
    'descripcion' => 'Inicia sesión en CERAMISHOP para comprar en línea, revisar tus pedidos y descargar tus comprobantes.',
    'canonical'   => 'login.php',
    'activo'      => 'login',
]);
?>
<section class="container">
    <div class="cs-tarjeta-auth">
        <div class="auth-header">
            <img src="<?= e(url('imagenes/logo.jpeg')) ?>" alt="Logo de CERAMICENTRO" width="80" height="80" onerror="this.remove()">
            <h1>Bienvenido a CERAMICENTRO</h1>
            <p class="mb-0 small opacity-75">Inicia sesión para continuar</p>
        </div>
        <form method="post" class="p-4" data-validar novalidate>
            <?= csrf_campo() ?>
            <?php if ($error): ?>
            <div class="alert alert-danger d-flex gap-2" role="alert"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i><div><?= e($error) ?></div></div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label" for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" class="form-control" placeholder="usuario@correo.com" required maxlength="120" autocomplete="email" value="<?= e($correo) ?>" <?= $correo === '' ? 'autofocus' : '' ?>>
            </div>
            <div class="mb-3">
                <label class="form-label" for="clave">Contraseña</label>
                <div class="campo-clave">
                    <input type="password" id="clave" name="contraseña" class="form-control" placeholder="••••••••" required autocomplete="current-password" <?= $correo !== '' ? 'autofocus' : '' ?>>
                    <button type="button" class="btn-ver-clave" aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                </div>
            </div>
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-cs btn-lg" data-cargando="Ingresando…"><i class="bi bi-box-arrow-in-right" aria-hidden="true"></i> Ingresar</button>
            </div>
            <div class="text-center small">
                <p class="mb-1">¿No tienes cuenta? <a href="<?= e(url('registro.php')) ?>">Regístrate aquí</a></p>
                <p class="mb-0"><a href="<?= e(url('index.php')) ?>">Volver al inicio</a></p>
            </div>
        </form>
    </div>
</section>
<?php layout_fin(); ?>
