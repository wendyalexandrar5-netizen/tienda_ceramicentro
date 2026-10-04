<?php
/** Crear una nueva contraseña a partir del enlace temporal enviado por correo. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/seguridad.php';

$token = (string)($_GET['token'] ?? ($_POST['token'] ?? ''));
$col = mongo()->selectCollection('recuperaciones_clave');
$solicitud = preg_match('/^[a-f0-9]{64}$/', $token)
    ? $col->findOne(['token_hash' => hash('sha256', $token), 'expira' => ['$gt' => nowUTC()]])
    : null;
$usuario = $solicitud ? mongo()->selectCollection('usuarios')->findOne(['_id' => $solicitud['usuario_id']]) : null;

if (!$solicitud || !$usuario) {
    mostrar_error(410, 'Enlace vencido o no válido', 'Este enlace para restablecer la contraseña ya se usó, venció (dura 1 hora) o no es válido. Solicita uno nuevo desde «¿Olvidaste tu contraseña?».');
}

$errores = [];
if (es_post()) {
    csrf_verificar();
    $nueva = (string)($_POST['nueva'] ?? '');
    if ($m = validar_clave_nueva($nueva)) {
        $errores['nueva'] = $m;
    } elseif ($nueva !== (string)($_POST['confirmar'] ?? '')) {
        $errores['confirmar'] = 'Las contraseñas no coinciden.';
    } else {
        // Un solo uso: se elimina la solicitud antes de cambiar la contraseña
        if ($col->deleteOne(['_id' => $solicitud['_id']])->getDeletedCount() !== 1) {
            mostrar_error(410, 'Enlace ya utilizado', 'Este enlace ya se usó. Si necesitas otro, solicítalo de nuevo.');
        }
        $campo = !empty($usuario['contrasena']) || empty($usuario['contraseña']) ? 'contrasena' : 'contraseña';
        mongo()->selectCollection('usuarios')->updateOne(['_id' => $usuario['_id']], ['$set' => [$campo => password_hash($nueva, PASSWORD_DEFAULT), 'actualizado_en' => nowUTC()]]);
        mongo()->selectCollection('tokens_app')->deleteMany(['usuario_id' => $usuario['_id']]);
        login_limpiar_intentos((string)$usuario['correo']);
        log_app('info', 'Contraseña restablecida', ['usuario' => (string)$usuario['_id']]);
        flash('success', 'Tu contraseña se cambió correctamente. Ya puedes iniciar sesión.');
        redirigir('login.php');
    }
}

layout_inicio(['titulo' => 'Nueva contraseña', 'noindex' => true, 'activo' => 'login']);
$inv = fn($c) => isset($errores[$c]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-' . $c : '';
$err = fn($c) => isset($errores[$c]) ? '<div class="invalid-feedback d-block" id="err-' . $c . '">' . e($errores[$c]) . '</div>' : '';
?>
<section class="container">
    <div class="cs-tarjeta-auth">
        <div class="auth-header">
            <h1>Nueva contraseña</h1>
            <p class="mb-0 small opacity-75">Cuenta: <?= e($usuario['correo'] ?? '') ?></p>
        </div>
        <form method="post" class="p-4" data-validar novalidate>
            <?= csrf_campo() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <div class="mb-3">
                <label class="form-label" for="nueva">Nueva contraseña</label>
                <div class="campo-clave">
                    <input type="password" id="nueva" name="nueva" class="form-control<?= $inv('nueva') ?>" required minlength="<?= CLAVE_MINIMO ?>" maxlength="72" autocomplete="new-password" pattern="(?=.*[A-Za-zÁÉÍÓÚáéíóúÑñ])(?=.*\d).{<?= CLAVE_MINIMO ?>,}" title="Mínimo <?= CLAVE_MINIMO ?> caracteres, combinando letras y números." autofocus>
                    <button type="button" class="btn-ver-clave" aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                </div>
                <div class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres, combinando letras y números.</div>
                <?= $err('nueva') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirmar">Confirmar contraseña</label>
                <input type="password" id="confirmar" name="confirmar" class="form-control<?= $inv('confirmar') ?>" required autocomplete="new-password" data-igual-a="#nueva">
                <?= $err('confirmar') ?>
            </div>
            <button type="submit" class="btn btn-cs btn-lg w-100" data-cargando="Guardando…"><i class="bi bi-shield-lock" aria-hidden="true"></i> Guardar nueva contraseña</button>
        </form>
    </div>
</section>
<?php layout_fin(); ?>
