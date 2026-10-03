<?php
/** Registro de nuevos clientes. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/seguridad.php';

if (usuario_actual()) {
    redirigir(es_admin() ? 'panel_admin.php' : 'tienda.php');
}

$errores = [];
$nombre = '';
$correo = '';

if (es_post()) {
    csrf_verificar();
    $nombre = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
    $correo = normalizar_correo((string)($_POST['correo'] ?? ''));
    $clave  = (string)($_POST['contraseña'] ?? ($_POST['contrasena'] ?? ''));
    $clave2 = (string)($_POST['confirmar'] ?? $clave);

    if ($m = validar_nombre($nombre)) {
        $errores['nombre'] = $m;
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 120) {
        $errores['correo'] = 'Escribe un correo electrónico válido.';
    }
    if ($m = validar_clave_nueva($clave)) {
        $errores['clave'] = $m;
    } elseif ($clave !== $clave2) {
        $errores['confirmar'] = 'Las contraseñas no coinciden.';
    }
    if (empty($_POST['acepto'])) {
        $errores['acepto'] = 'Debes aceptar los Términos y la Política de privacidad.';
    }
    if (trim((string)($_POST['sitio_web'] ?? '')) !== '') {
        $errores['general'] = 'No se pudo completar el registro.'; // robot (campo trampa)
    }

    if (!$errores && usuario_por_correo($correo, ['_id' => 1])) {
        $errores['correo'] = 'Este correo ya está registrado. ¿Quieres iniciar sesión?';
    }

    if (!$errores) {
        $doc = [
            'nombre'     => $nombre,
            'correo'     => $correo,
            'contrasena' => password_hash($clave, PASSWORD_DEFAULT),
            'rol'        => 'cliente',
            'createdAt'  => nowUTC(),
            'origen'     => 'web',
        ];
        try {
            $res = mongo()->selectCollection('usuarios')->insertOne($doc);
            $doc['_id'] = $res->getInsertedId();
            sesion_login($doc);
            flash('success', '¡Bienvenido a CERAMISHOP, ' . explode(' ', $nombre)[0] . '! Tu cuenta fue creada.');
            redirigir('tienda.php');
        } catch (\MongoDB\Driver\Exception\BulkWriteException $e) {
            $errores['correo'] = 'Este correo ya está registrado.';
        } catch (Throwable $e) {
            log_app('error', 'Error en registro: ' . $e->getMessage());
            $errores['general'] = 'No pudimos crear tu cuenta en este momento. Intenta más tarde.';
        }
    }
}

layout_inicio([
    'titulo'      => 'Crear cuenta',
    'descripcion' => 'Crea tu cuenta gratis en CERAMISHOP y compra cerámica, baldosas, techos PVC y más en CERAMICENTRO.',
    'canonical'   => 'registro.php',
]);
$inv = fn($c) => isset($errores[$c]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-' . $c : '';
$err = fn($c) => isset($errores[$c]) ? '<div class="invalid-feedback d-block" id="err-' . $c . '">' . e($errores[$c]) . '</div>' : '';
?>
<section class="container">
    <div class="cs-tarjeta-auth">
        <div class="auth-header">
            <img src="<?= e(url('imagenes/logo.jpeg')) ?>" alt="Logo de CERAMICENTRO" width="80" height="80" onerror="this.remove()">
            <h1>Crea tu cuenta</h1>
            <p class="mb-0 small opacity-75">Compra en línea y sigue tus pedidos</p>
        </div>
        <form method="post" class="p-4" data-validar novalidate>
            <?= csrf_campo() ?>
            <?php if (!empty($errores['general'])): ?>
            <div class="alert alert-danger" role="alert"><?= e($errores['general']) ?></div>
            <?php elseif ($errores): ?>
            <div class="alert alert-warning" role="alert">Revisa los campos marcados en rojo.</div>
            <?php endif; ?>
            <div class="trampa" aria-hidden="true"><label for="sitio_web">No llenar</label><input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off"></div>

            <div class="mb-3">
                <label class="form-label" for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" class="form-control<?= $inv('nombre') ?>" required minlength="3" maxlength="50" autocomplete="name" placeholder="Ej. Juan Pérez" value="<?= e($nombre) ?>">
                <?= $err('nombre') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" class="form-control<?= $inv('correo') ?>" required maxlength="120" autocomplete="email" placeholder="ejemplo@correo.com" value="<?= e($correo) ?>">
                <?= $err('correo') ?>
                <?php if (isset($errores['correo']) && strpos($errores['correo'], 'registrado') !== false): ?>
                <a class="small" href="<?= e(url('login.php')) ?>">Ir a iniciar sesión</a>
                <?php endif; ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="clave">Contraseña</label>
                <div class="campo-clave">
                    <input type="password" id="clave" name="contraseña" class="form-control<?= $inv('clave') ?>" required minlength="<?= CLAVE_MINIMO ?>" maxlength="72" autocomplete="new-password" aria-describedby="ayudaClave" pattern="(?=.*[A-Za-zÁÉÍÓÚáéíóúÑñ])(?=.*\d).{<?= CLAVE_MINIMO ?>,}" title="Mínimo <?= CLAVE_MINIMO ?> caracteres, combinando letras y números.">
                    <button type="button" class="btn-ver-clave" aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                </div>
                <div id="ayudaClave" class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres, combinando letras y números.</div>
                <?= $err('clave') ?>
            </div>
            <div class="mb-3">
                <label class="form-label" for="confirmar">Confirmar contraseña</label>
                <input type="password" id="confirmar" name="confirmar" class="form-control<?= $inv('confirmar') ?>" required autocomplete="new-password" data-igual-a="#clave">
                <?= $err('confirmar') ?>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input<?= $inv('acepto') ?>" type="checkbox" id="acepto" name="acepto" value="1" required data-mensaje="Debes aceptar para crear tu cuenta." <?= !empty($_POST['acepto']) ? 'checked' : '' ?>>
                <label class="form-check-label small" for="acepto">Acepto los <a href="<?= e(url('tyc.php')) ?>" target="_blank" rel="noopener">Términos y condiciones</a> y la <a href="<?= e(url('politica_privacidad.php')) ?>" target="_blank" rel="noopener">Política de privacidad</a>.</label>
                <?= $err('acepto') ?>
            </div>
            <div class="d-grid mb-3">
                <button type="submit" class="btn btn-cs btn-lg" data-cargando="Creando tu cuenta…"><i class="bi bi-person-plus" aria-hidden="true"></i> Registrarme</button>
            </div>
            <div class="text-center small">
                <p class="mb-1">¿Ya tienes cuenta? <a href="<?= e(url('login.php')) ?>">Inicia sesión</a></p>
                <p class="mb-0"><a href="<?= e(url('index.php')) ?>">Volver al inicio</a></p>
            </div>
        </form>
    </div>
</section>
<?php layout_fin(); ?>
