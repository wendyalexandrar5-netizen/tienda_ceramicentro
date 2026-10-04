<?php
/**
 * Recuperación de contraseña: envía un enlace temporal (1 hora, un solo uso) al correo.
 * Si el correo SMTP no está configurado y el sitio está en modo "desarrollo"
 * (por ejemplo, demostración local sin hosting), el enlace se muestra en pantalla.
 * En "produccion" nunca se muestra: solo se envía por correo.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/seguridad.php';
require_once __DIR__ . '/includes/correo.php';

if (usuario_actual()) {
    redirigir(es_admin() ? 'panel_admin.php' : 'mi_cuenta.php');
}

$enviado = false;
$enlaceDemo = '';
$error = '';
$correo = '';

if (es_post()) {
    csrf_verificar();
    $correo = normalizar_correo((string)($_POST['correo'] ?? ''));
    $solicitudes = array_filter((array)($_SESSION['recuperaciones'] ?? []), fn($t) => $t > time() - 3600);
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'Escribe un correo electrónico válido.';
    } elseif (count($solicitudes) >= 3) {
        $error = 'Ya solicitaste varios enlaces. Espera una hora o revisa tu correo (incluida la carpeta de spam).';
    } elseif (trim((string)($_POST['sitio_web'] ?? '')) === '') {
        $solicitudes[] = time();
        $_SESSION['recuperaciones'] = $solicitudes;
        $u = usuario_por_correo($correo, ['_id' => 1, 'nombre' => 1, 'correo' => 1, 'activo' => 1]);
        if ($u && ($u['activo'] ?? true) !== false) {
            $token = bin2hex(random_bytes(32));
            $col = mongo()->selectCollection('recuperaciones_clave');
            $col->deleteMany(['usuario_id' => $u['_id']]);
            $col->insertOne([
                'token_hash' => hash('sha256', $token),
                'usuario_id' => $u['_id'],
                'creado'     => nowUTC(),
                'expira'     => new \MongoDB\BSON\UTCDateTime((time() + 3600) * 1000),
            ]);
            $enlace = url_absoluta('restablecer_clave.php', ['token' => $token]);
            $nombre = explode(' ', trim((string)$u['nombre']))[0];
            $ok = enviar_correo((string)$u['correo'], (string)$u['nombre'], 'Restablece tu contraseña de CERAMISHOP',
                '<p>Hola ' . e($nombre) . ',</p><p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en CERAMISHOP.</p>'
                . '<p><a href="' . e($enlace) . '" style="background:#c62828;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none">Crear una nueva contraseña</a></p>'
                . '<p>El enlace vence en 1 hora y solo se puede usar una vez. Si no solicitaste este cambio, ignora este mensaje: tu contraseña sigue igual.</p>',
                "Hola $nombre,\n\nPara crear una nueva contraseña abre este enlace (vence en 1 hora):\n$enlace\n\nSi no solicitaste el cambio, ignora este mensaje.");
            if (!$ok && !es_produccion()) {
                $enlaceDemo = $enlace;
            }
            log_app('info', 'Solicitud de recuperación de contraseña', ['usuario' => (string)$u['_id'], 'correo_enviado' => $ok]);
        }
        // Mismo mensaje exista o no la cuenta (no revela qué correos están registrados)
        $enviado = true;
    }
}

layout_inicio(['titulo' => 'Recuperar contraseña', 'descripcion' => 'Recupera el acceso a tu cuenta de CERAMISHOP.', 'noindex' => true, 'activo' => 'login']);
?>
<section class="container">
    <div class="cs-tarjeta-auth">
        <div class="auth-header">
            <h1>Recuperar contraseña</h1>
            <p class="mb-0 small opacity-75">Te enviaremos un enlace para crear una nueva</p>
        </div>
        <div class="p-4">
            <?php if ($enviado): ?>
                <div class="alert alert-success" role="status">
                    <i class="bi bi-envelope-check" aria-hidden="true"></i>
                    Si <strong><?= e($correo) ?></strong> está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa también la carpeta de spam. El enlace vence en 1 hora.
                </div>
                <?php if ($enlaceDemo): ?>
                <div class="alert alert-warning small" role="note">
                    <strong>Modo desarrollo / demostración:</strong> el envío de correos no está configurado, así que el enlace se muestra aquí.
                    En producción (<code>'entorno' => 'produccion'</code>) solo se envía por correo.<br>
                    <a href="<?= e($enlaceDemo) ?>" class="fw-semibold">Abrir enlace para restablecer la contraseña</a>
                </div>
                <?php endif; ?>
                <a href="<?= e(url('login.php')) ?>" class="btn btn-cs w-100">Volver a iniciar sesión</a>
            <?php else: ?>
                <?php if ($error): ?><div class="alert alert-danger" role="alert"><?= e($error) ?></div><?php endif; ?>
                <form method="post" data-validar novalidate>
                    <?= csrf_campo() ?>
                    <div class="trampa" aria-hidden="true"><label for="sitio_web">No llenar</label><input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off"></div>
                    <div class="mb-3">
                        <label class="form-label" for="correo">Correo electrónico de tu cuenta</label>
                        <input type="email" id="correo" name="correo" class="form-control" required maxlength="120" autocomplete="email" value="<?= e($correo) ?>" autofocus>
                    </div>
                    <button type="submit" class="btn btn-cs btn-lg w-100" data-cargando="Enviando…"><i class="bi bi-send" aria-hidden="true"></i> Enviar enlace</button>
                </form>
                <p class="text-center small mt-3 mb-0"><a href="<?= e(url('login.php')) ?>">Volver a iniciar sesión</a></p>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php layout_fin(); ?>
