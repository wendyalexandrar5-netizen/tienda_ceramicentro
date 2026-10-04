<?php
/**
 * Formulario de contacto.
 * - Las credenciales SMTP se leen de config/config.php (ya NO están en el código).
 * - Si el correo no está configurado o falla, el mensaje se guarda en MongoDB
 *   (colección mensajes_contacto) y se puede leer en el panel administrativo.
 * - Protección: CSRF, campo trampa anti-spam, tiempo mínimo y límite de envíos.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/correo.php';


$errores = [];
$datos = ['nombre' => '', 'correo' => '', 'asunto' => '', 'mensaje' => ''];

if (es_post()) {
    csrf_verificar();
    foreach ($datos as $k => $_) {
        $datos[$k] = trim((string)($_POST[$k] ?? ''));
    }
    $esSpam = trim((string)($_POST['sitio_web'] ?? '')) !== ''                        // campo trampa
        || (time() - (int)($_SESSION['contacto_form_t'] ?? 0)) < 3;                     // enviado demasiado rápido
    $envios = array_filter((array)($_SESSION['contacto_envios'] ?? []), fn($t) => $t > time() - 3600);

    if (mb_strlen($datos['nombre']) < 3 || mb_strlen($datos['nombre']) > 80) {
        $errores['nombre'] = 'Escribe tu nombre (entre 3 y 80 caracteres).';
    }
    if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || mb_strlen($datos['correo']) > 120) {
        $errores['correo'] = 'Escribe un correo válido, por ejemplo nombre@correo.com.';
    }
    if (mb_strlen($datos['asunto']) < 3 || mb_strlen($datos['asunto']) > 120) {
        $errores['asunto'] = 'Escribe un asunto (entre 3 y 120 caracteres).';
    }
    if (mb_strlen($datos['mensaje']) < 10 || mb_strlen($datos['mensaje']) > 3000) {
        $errores['mensaje'] = 'El mensaje debe tener entre 10 y 3000 caracteres.';
    }
    if (empty($_POST['tyc'])) {
        $errores['tyc'] = 'Debes aceptar los Términos y Condiciones.';
    }
    if (count($envios) >= 5) {
        $errores['general'] = 'Has enviado varios mensajes en poco tiempo. Intenta de nuevo más tarde o escríbenos por WhatsApp.';
    }

    if (!$errores) {
        if ($esSpam) {
            // Se responde igual que un envío correcto para no dar pistas a los robots
            log_app('aviso', 'Mensaje de contacto descartado como spam', ['ip' => ip_cliente()]);
        } else {
            // 1) Guardar siempre en MongoDB (respaldo, nunca se pierde el mensaje)
            $guardado = false;
            try {
                mongo()->selectCollection('mensajes_contacto')->insertOne($datos + ['fecha' => nowUTC(), 'leido' => false, 'enviado_correo' => false]);
                $guardado = true;
            } catch (Throwable $e) {
                log_app('error', 'No se pudo guardar mensaje de contacto: ' . $e->getMessage());
            }

            // 2) Enviar por correo si está configurado
            $destino = (string)(config('smtp.destino') ?: (config('smtp.remitente') ?: config('smtp.usuario')));
            $enviado = $destino !== '' && enviar_correo(
                $destino,
                'CERAMICENTRO',
                'Contacto web: ' . $datos['asunto'],
                '<h3>Nuevo mensaje desde el formulario de contacto</h3>'
                    . '<p><strong>Nombre:</strong> ' . e($datos['nombre']) . '</p>'
                    . '<p><strong>Correo:</strong> ' . e($datos['correo']) . '</p>'
                    . '<p><strong>Asunto:</strong> ' . e($datos['asunto']) . '</p>'
                    . '<p><strong>Mensaje:</strong><br>' . nl2br(e($datos['mensaje'])) . '</p>',
                "Nombre: {$datos['nombre']}\nCorreo: {$datos['correo']}\nAsunto: {$datos['asunto']}\n\n{$datos['mensaje']}",
                [$datos['correo'], $datos['nombre']]
            );
            if ($enviado && $guardado) {
                mongo()->selectCollection('mensajes_contacto')->updateOne(
                    ['correo' => $datos['correo'], 'asunto' => $datos['asunto'], 'enviado_correo' => false],
                    ['$set' => ['enviado_correo' => true]]
                );
            }

            if (!$guardado && !$enviado) {
                $errores['general'] = 'No pudimos enviar tu mensaje en este momento. Intenta de nuevo en unos minutos o escríbenos por WhatsApp.';
            }
        }

        if (!$errores) {
            $envios[] = time();
            $_SESSION['contacto_envios'] = $envios;
            flash('success', '¡Gracias por contactarnos, ' . $datos['nombre'] . '! Te responderemos pronto.');
            redirigir('contacto.php', ['enviado' => 1]);
        }
    }
}
$_SESSION['contacto_form_t'] = time();

$migas = [['nombre' => 'Inicio', 'url' => 'index.php'], ['nombre' => 'Contáctanos']];
layout_inicio([
    'titulo'      => 'Contáctanos',
    'descripcion' => 'Contacta a CERAMICENTRO: escríbenos por el formulario o por WhatsApp para recibir asesoría sobre cerámica, baldosas, techos PVC y materiales de construcción.',
    'canonical'   => 'contacto.php',
    'activo'      => 'contacto',
    'migas'       => $migas,
    'noindex'     => isset($_GET['enviado']),
    'jsonld'      => [jsonld_organizacion(), jsonld_migas($migas)],
]);
$campoError = function (string $campo) use ($errores) {
    return isset($errores[$campo]) ? ' is-invalid" aria-invalid="true" aria-describedby="err-' . $campo : '';
};
$wa = whatsapp_url('Hola CERAMICENTRO, quiero hacer una consulta.');
?>
<section class="container py-4">
    <div class="row g-4">
        <div class="col-lg-6">
            <div class="cs-panel">
                <h1 class="h2 text-center mb-1" style="color:#b71c1c"><i class="bi bi-envelope-fill" aria-hidden="true"></i> Contáctanos</h1>
                <p class="text-center text-secondary mb-4">Responderemos tu mensaje lo antes posible.</p>

                <?php if (!empty($errores['general'])): ?>
                <div class="alert alert-danger" role="alert"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> <?= e($errores['general']) ?></div>
                <?php elseif ($errores): ?>
                <div class="alert alert-warning" role="alert">Revisa los campos marcados.</div>
                <?php endif; ?>

                <form method="post" data-validar novalidate>
                    <?= csrf_campo() ?>
                    <div class="trampa" aria-hidden="true">
                        <label for="sitio_web">No llenar este campo</label>
                        <input type="text" id="sitio_web" name="sitio_web" tabindex="-1" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="nombre"><i class="bi bi-person-fill" aria-hidden="true"></i> Nombre</label>
                        <input type="text" id="nombre" name="nombre" class="form-control<?= $campoError('nombre') ?>" required minlength="3" maxlength="80" autocomplete="name" value="<?= e($datos['nombre']) ?>">
                        <?php if (isset($errores['nombre'])): ?><div class="invalid-feedback" id="err-nombre"><?= e($errores['nombre']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="correo"><i class="bi bi-envelope-at-fill" aria-hidden="true"></i> Correo electrónico</label>
                        <input type="email" id="correo" name="correo" class="form-control<?= $campoError('correo') ?>" required maxlength="120" autocomplete="email" value="<?= e($datos['correo']) ?>">
                        <?php if (isset($errores['correo'])): ?><div class="invalid-feedback" id="err-correo"><?= e($errores['correo']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="asunto"><i class="bi bi-pencil-square" aria-hidden="true"></i> Asunto</label>
                        <input type="text" id="asunto" name="asunto" class="form-control<?= $campoError('asunto') ?>" required minlength="3" maxlength="120" value="<?= e($datos['asunto']) ?>">
                        <?php if (isset($errores['asunto'])): ?><div class="invalid-feedback" id="err-asunto"><?= e($errores['asunto']) ?></div><?php endif; ?>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="mensaje"><i class="bi bi-chat-dots-fill" aria-hidden="true"></i> Mensaje</label>
                        <textarea id="mensaje" name="mensaje" class="form-control<?= $campoError('mensaje') ?>" rows="5" required minlength="10" maxlength="3000"><?= e($datos['mensaje']) ?></textarea>
                        <?php if (isset($errores['mensaje'])): ?><div class="invalid-feedback" id="err-mensaje"><?= e($errores['mensaje']) ?></div><?php endif; ?>
                    </div>
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input<?= $campoError('tyc') ?>" name="tyc" id="tyc" value="1" required data-mensaje="Debes aceptar los Términos y Condiciones." <?= !empty($_POST['tyc']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="tyc">
                            Acepto los <a href="<?= e(url('tyc.php')) ?>" target="_blank" rel="noopener">Términos y Condiciones</a> y la <a href="<?= e(url('politica_privacidad.php')) ?>" target="_blank" rel="noopener">Política de privacidad</a>
                        </label>
                        <?php if (isset($errores['tyc'])): ?><div class="invalid-feedback" id="err-tyc"><?= e($errores['tyc']) ?></div><?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between gap-2">
                        <button type="submit" class="btn btn-cs px-4" data-cargando="Enviando…"><i class="bi bi-send-fill" aria-hidden="true"></i> Enviar</button>
                        <a href="<?= e(url('index.php')) ?>" class="btn btn-outline-secondary px-4"><i class="bi bi-house-door-fill" aria-hidden="true"></i> Inicio</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="cs-panel p-2 mb-3">
                <iframe title="Ubicación de Ceramicentro en Google Maps" src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3860.4610157249585!2d-90.56737862489325!3d14.629752285860048!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8589a047510e49ef%3A0xe228ca2a0dae8c94!2sCeramicentro%20Roosevelt!5e0!3m2!1ses!2sco!4v1746085696388!5m2!1ses!2sco" style="border:0;width:100%;height:380px;border-radius:12px" allowfullscreen loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>
            <?php if ($wa): ?>
            <div class="cs-panel d-flex align-items-center gap-3">
                <i class="bi bi-whatsapp fs-1 text-success" aria-hidden="true"></i>
                <div class="flex-grow-1">
                    <h2 class="h6 mb-1">¿Prefieres WhatsApp?</h2>
                    <p class="small text-secondary mb-0">Escríbenos y te asesoramos directamente.</p>
                </div>
                <a href="<?= e($wa) ?>" class="btn btn-success" target="_blank" rel="noopener" data-evento="contacto_whatsapp">Abrir chat</a>
            </div>
            <?php endif; ?>
            <?php
            $redes = array_filter(['facebook' => config('empresa.facebook'), 'instagram' => config('empresa.instagram')]);
            if ($redes): ?>
            <div class="text-center mt-3 fs-3">
                <?php foreach ($redes as $red => $enlace): ?>
                <a href="<?= e($enlace) ?>" target="_blank" rel="noopener" class="mx-2" aria-label="<?= e(ucfirst($red)) ?> de CERAMICENTRO"><i class="bi bi-<?= e($red) ?>" aria-hidden="true"></i></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php layout_fin(); ?>
