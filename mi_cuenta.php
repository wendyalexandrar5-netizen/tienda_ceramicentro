<?php
/** Gestión de cuenta del cliente: actualizar nombre y cambiar contraseña. */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/seguridad.php';
requerir_sesion(['cliente', 'administrador']);

$u = usuario_actual();
$col = mongo()->selectCollection('usuarios');
$doc = $col->findOne(['_id' => oid($u['id'])]);
if (!$doc) {
    sesion_cerrar();
    iniciar_sesion();
    flash('warning', 'Tu cuenta ya no existe. Si crees que es un error, contáctanos.');
    redirigir('login.php');
}
$errores = [];

if (es_post()) {
    csrf_verificar();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'perfil') {
        $nombre = trim(preg_replace('/\s+/u', ' ', (string)($_POST['nombre'] ?? '')));
        if ($m = validar_nombre($nombre)) {
            $errores['nombre'] = $m;
        } else {
            $col->updateOne(['_id' => $doc['_id']], ['$set' => ['nombre' => $nombre, 'actualizado_en' => nowUTC()]]);
            $_SESSION['usuario']['nombre'] = $nombre;
            flash('success', 'Tus datos se actualizaron correctamente.');
            redirigir('mi_cuenta.php');
        }
    } elseif ($accion === 'clave') {
        $actual = (string)($_POST['actual'] ?? '');
        $nueva  = (string)($_POST['nueva'] ?? '');
        $conf   = (string)($_POST['confirmar'] ?? '');
        if (!verificar_clave_usuario((array)$doc, $actual)) {
            $errores['actual'] = 'La contraseña actual no es correcta.';
        } elseif ($m = validar_clave_nueva($nueva)) {
            $errores['nueva'] = $m;
        } elseif ($nueva !== $conf) {
            $errores['confirmar'] = 'Las contraseñas no coinciden.';
        } else {
            // Se guarda en el campo usado por la cuenta (compatibilidad web / app)
            $campo = !empty($doc['contrasena']) ? 'contrasena' : 'contraseña';
            $col->updateOne(['_id' => $doc['_id']], ['$set' => [$campo => password_hash($nueva, PASSWORD_DEFAULT), 'actualizado_en' => nowUTC()]]);
            // Cierra las sesiones abiertas en la app por seguridad
            mongo()->selectCollection('tokens_app')->deleteMany(['usuario_id' => $doc['_id']]);
            session_regenerate_id(true);
            flash('success', 'Tu contraseña se cambió correctamente.');
            redirigir('mi_cuenta.php');
        }
    }
}

$totalPedidos = mongo()->selectCollection('pedidos')->countDocuments(['usuario_id' => $doc['_id']]);
layout_inicio(['titulo' => 'Mi cuenta', 'noindex' => true, 'activo' => 'cuenta']);
$inv = fn($c) => isset($errores[$c]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-' . $c : '';
$err = fn($c) => isset($errores[$c]) ? '<div class="invalid-feedback d-block" id="err-' . $c . '">' . e($errores[$c]) . '</div>' : '';
?>
<section class="container py-4">
    <h1 class="mb-4"><i class="bi bi-person-gear" aria-hidden="true"></i> Mi cuenta</h1>
    <div class="row g-4">
        <div class="col-lg-4">
            <div class="cs-panel text-center">
                <div class="exito-icono mb-3" style="background:var(--cs-rosa);color:var(--cs-rojo)" aria-hidden="true"><i class="bi bi-person"></i></div>
                <h2 class="h5 mb-1"><?= e($doc['nombre'] ?? '') ?></h2>
                <p class="text-secondary mb-2"><?= e($doc['correo'] ?? '') ?></p>
                <span class="estado-badge estado-ok"><?= e(ucfirst((string)($doc['rol'] ?? 'cliente'))) ?></span>
                <?php if (!empty($doc['createdAt'])): ?><p class="small text-secondary mt-3 mb-0">Cliente desde <?= e(fecha_local($doc['createdAt'], 'd/m/Y')) ?></p><?php endif; ?>
                <?php if (es_cliente()): ?>
                <hr>
                <a href="<?= e(url('mis_pedidos.php')) ?>" class="btn btn-outline-cs w-100 mb-2"><i class="bi bi-box-seam" aria-hidden="true"></i> Mis pedidos (<?= (int)$totalPedidos ?>)</a>
                <?php endif; ?>
                <a href="<?= e(url('logout.php')) ?>" class="btn btn-outline-secondary w-100"><i class="bi bi-box-arrow-right" aria-hidden="true"></i> Cerrar sesión</a>
            </div>
        </div>
        <div class="col-lg-8">
            <div class="cs-panel mb-4">
                <h2 class="cs-panel-titulo">Datos personales</h2>
                <form method="post" data-validar novalidate>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="perfil">
                    <div class="mb-3">
                        <label class="form-label" for="nombre">Nombre completo</label>
                        <input type="text" id="nombre" name="nombre" class="form-control<?= $inv('nombre') ?>" required minlength="3" maxlength="50" autocomplete="name" value="<?= e($_POST['nombre'] ?? $doc['nombre'] ?? '') ?>">
                        <?= $err('nombre') ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="correoFijo">Correo electrónico</label>
                        <input type="email" id="correoFijo" class="form-control" value="<?= e($doc['correo'] ?? '') ?>" readonly aria-describedby="ayudaCorreo">
                        <div id="ayudaCorreo" class="form-text">El correo identifica tu cuenta. Para cambiarlo, contáctanos.</div>
                    </div>
                    <button type="submit" class="btn btn-cs" data-cargando="Guardando…"><i class="bi bi-check2" aria-hidden="true"></i> Guardar cambios</button>
                </form>
            </div>
            <div class="cs-panel">
                <h2 class="cs-panel-titulo">Cambiar contraseña</h2>
                <form method="post" data-validar novalidate autocomplete="off">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="clave">
                    <div class="mb-3">
                        <label class="form-label" for="actual">Contraseña actual</label>
                        <input type="password" id="actual" name="actual" class="form-control<?= $inv('actual') ?>" required autocomplete="current-password">
                        <?= $err('actual') ?>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label" for="nueva">Nueva contraseña</label>
                            <div class="campo-clave">
                                <input type="password" id="nueva" name="nueva" class="form-control<?= $inv('nueva') ?>" required minlength="<?= CLAVE_MINIMO ?>" maxlength="72" autocomplete="new-password" pattern="(?=.*[A-Za-zÁÉÍÓÚáéíóúÑñ])(?=.*\d).{<?= CLAVE_MINIMO ?>,}" title="Mínimo <?= CLAVE_MINIMO ?> caracteres, combinando letras y números.">
                                <button type="button" class="btn-ver-clave" aria-label="Mostrar contraseña" aria-pressed="false"><i class="bi bi-eye" aria-hidden="true"></i></button>
                            </div>
                            <div class="form-text">Mínimo <?= CLAVE_MINIMO ?> caracteres con letras y números.</div>
                            <?= $err('nueva') ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="confirmar">Confirmar nueva contraseña</label>
                            <input type="password" id="confirmar" name="confirmar" class="form-control<?= $inv('confirmar') ?>" required autocomplete="new-password" data-igual-a="#nueva">
                            <?= $err('confirmar') ?>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-cs" data-cargando="Actualizando…"><i class="bi bi-shield-lock" aria-hidden="true"></i> Cambiar contraseña</button>
                </form>
            </div>
        </div>
    </div>
</section>
<?php layout_fin(); ?>
