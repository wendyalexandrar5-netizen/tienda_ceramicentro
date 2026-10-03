<?php
/** Registrar un nuevo administrador (solo administradores). */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/seguridad.php';
requerir_sesion('administrador');

$errores = [];
$nombre = '';
$correo = '';
if (es_post()) {
    csrf_verificar();
    $nombre = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
    $correo = normalizar_correo((string)($_POST['correo'] ?? ''));
    $clave = (string)($_POST['contraseña'] ?? ($_POST['contrasena'] ?? ''));
    if ($m = validar_nombre($nombre)) {
        $errores['nombre'] = $m;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores['correo'] = 'Correo inválido.';
    } elseif (usuario_por_correo($correo, ['_id' => 1])) {
        $errores['correo'] = 'El correo electrónico ya está registrado. Si es un cliente, cambia su rol desde «Usuarios».';
    }
    if ($m = validar_clave_nueva($clave)) {
        $errores['clave'] = $m;
    } elseif ($clave !== (string)($_POST['confirmar'] ?? '')) {
        $errores['confirmar'] = 'Las contraseñas no coinciden.';
    }
    if (!$errores) {
        mongo()->selectCollection('usuarios')->insertOne([
            'nombre' => $nombre, 'correo' => $correo, 'contrasena' => password_hash($clave, PASSWORD_DEFAULT),
            'rol' => 'administrador', 'createdAt' => nowUTC(), 'creado_por' => oid(usuario_actual()['id']),
        ]);
        log_app('info', 'Administrador creado', ['correo' => $correo, 'por' => usuario_actual()['id']]);
        flash('success', 'Administrador ' . $nombre . ' registrado correctamente.');
        redirigir('ver_usuarios.php', ['rol' => 'administrador']);
    }
}

admin_inicio('Registrar administrador', 'admins');
$inv = fn($c) => isset($errores[$c]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-' . $c : '';
$err = fn($c) => isset($errores[$c]) ? '<div class="invalid-feedback d-block" id="err-' . $c . '">' . e($errores[$c]) . '</div>' : '';
?>
<form method="post" class="cs-panel" style="max-width:560px" data-validar novalidate autocomplete="off">
    <?= csrf_campo() ?>
    <p class="text-secondary small">Los administradores tienen acceso completo al panel. Crea cuentas solo para personas autorizadas.</p>
    <div class="mb-3">
        <label class="form-label" for="nombre">Nombre completo</label>
        <input type="text" id="nombre" name="nombre" class="form-control<?= $inv('nombre') ?>" required minlength="3" maxlength="50" value="<?= e($nombre) ?>">
        <?= $err('nombre') ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="correo">Correo</label>
        <input type="email" id="correo" name="correo" class="form-control<?= $inv('correo') ?>" required maxlength="120" value="<?= e($correo) ?>">
        <?= $err('correo') ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="clave">Contraseña</label>
        <div class="campo-clave">
            <input type="password" id="clave" name="contraseña" class="form-control<?= $inv('clave') ?>" required minlength="<?= CLAVE_MINIMO ?>" maxlength="72" autocomplete="new-password" pattern="(?=.*[A-Za-zÁÉÍÓÚáéíóúÑñ])(?=.*\d).{<?= CLAVE_MINIMO ?>,}" title="Mínimo <?= CLAVE_MINIMO ?> caracteres, combinando letras y números.">
            <button type="button" class="btn-ver-clave" aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
        </div>
        <div class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres con letras y números.</div>
        <?= $err('clave') ?>
    </div>
    <div class="mb-3">
        <label class="form-label" for="confirmar">Confirmar contraseña</label>
        <input type="password" id="confirmar" name="confirmar" class="form-control<?= $inv('confirmar') ?>" required autocomplete="new-password" data-igual-a="#clave">
        <?= $err('confirmar') ?>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <button type="submit" class="btn btn-cs" data-cargando="Registrando…"><i class="bi bi-person-plus" aria-hidden="true"></i> Registrar</button>
        <a href="<?= e(url('ver_usuarios.php')) ?>" class="btn btn-outline-secondary">Volver</a>
    </div>
</form>
<?php admin_fin(); ?>
