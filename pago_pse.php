<?php
/**
 * Paso 3 de la compra: SIMULADOR de pago PSE.
 * IMPORTANTE: no es una integración bancaria real. No se conecta con ningún banco,
 * no pide datos bancarios ni realiza cobros. Solo permite simular un pago aprobado
 * o rechazado para probar el flujo completo del pedido.
 */
require_once __DIR__ . '/includes/layout.php';
require_once __DIR__ . '/includes/pedidos.php';
require_once __DIR__ . '/includes/pasos_compra.php';
requerir_sesion('cliente');

$usuario = usuario_actual();
$pedido = pedido_de_usuario((string)($_GET['id'] ?? ''), $usuario['id']);
if (!$pedido) {
    no_encontrado('No encontramos ese pedido en tu cuenta.');
}
$pedido = liberar_si_vencido($pedido);
$id = (string)$pedido['_id'];
$estado = pedido_estado($pedido);

$bancos = ['Banco Simulado Andino', 'Banco Simulado del Café', 'Banco Simulado Caribe', 'Cooperativa Simulada'];

if (es_post()) {
    csrf_verificar();
    $accion = (string)($_POST['accion'] ?? '');

    if ($accion === 'pagar' && $estado === ESTADO_PENDIENTE) {
        $banco = in_array($_POST['banco'] ?? '', $bancos, true) ? $_POST['banco'] : '';
        $persona = in_array($_POST['persona'] ?? '', ['natural', 'juridica'], true) ? $_POST['persona'] : '';
        $resultado = ($_POST['resultado'] ?? '') === 'rechazado' ? 'rechazado' : 'aprobado';
        if ($banco === '' || $persona === '') {
            flash('error', 'Selecciona el tipo de persona y el banco para continuar.');
            redirigir('pago_pse.php', ['id' => $id]);
        }
        $pago = [
            'pago' => [
                'metodo'     => 'PSE (simulado)',
                'simulado'   => true,
                'banco'      => $banco,
                'persona'    => $persona,
                'resultado'  => $resultado,
                'referencia' => referencia_pago_simulada(),
                'fecha'      => nowUTC(),
            ],
        ];
        $r = pedido_cambiar_estado($pedido, $resultado === 'aprobado' ? ESTADO_PAGADO : ESTADO_RECHAZADO, 'cliente', $pago);
        if (!$r['ok']) {
            flash('error', $r['mensaje']);
            redirigir('pago_pse.php', ['id' => $id]);
        }
        if ($resultado === 'aprobado') {
            redirigir('pago_exitoso.php', ['id' => $id]);
        }
        flash('error', 'El pago fue rechazado (simulación). Los productos se liberaron; puedes intentarlo de nuevo.');
        redirigir('pago_pse.php', ['id' => $id]);
    }

    if ($accion === 'reintentar' && $estado === ESTADO_RECHAZADO) {
        $r = pedido_cambiar_estado($pedido, ESTADO_PENDIENTE, 'cliente');
        flash($r['ok'] ? 'info' : 'error', $r['ok'] ? 'Volvimos a reservar tus productos. Completa el pago.' : $r['mensaje']);
        redirigir($r['ok'] ? 'pago_pse.php' : 'ver_pedido.php', ['id' => $id]);
    }

    if ($accion === 'cancelar' && in_array($estado, [ESTADO_PENDIENTE, ESTADO_RECHAZADO], true)) {
        pedido_cambiar_estado($pedido, ESTADO_CANCELADO, 'cliente');
        flash('info', 'Cancelaste el pedido ' . pedido_numero($pedido) . '. No se realizó ningún cobro.');
        redirigir('mis_pedidos.php');
    }

    redirigir('ver_pedido.php', ['id' => $id]);
}

// Si el pedido ya no está pendiente, se muestra su estado
if (!in_array($estado, [ESTADO_PENDIENTE, ESTADO_RECHAZADO], true)) {
    redirigir($estado === ESTADO_PAGADO ? 'pago_exitoso.php' : 'ver_pedido.php', ['id' => $id]);
}

layout_inicio(['titulo' => 'Pago PSE (simulador)', 'noindex' => true, 'activo' => 'tienda']);
?>
<section class="container py-4">
    <h1 class="mb-3 visually-hidden">Pago del pedido <?= e(pedido_numero($pedido)) ?></h1>
    <?php pasos_compra(3); ?>
    <div class="pse-simulador">
        <p class="pse-aviso mb-3" role="note"><i class="bi bi-cone-striped" aria-hidden="true"></i> <strong>Simulador de PSE.</strong> Esta pantalla imita el proceso de pago para fines demostrativos. No se conecta con ningún banco, no pide claves ni datos bancarios y <strong>no realiza cobros reales</strong>.</p>

        <div class="pse-cabecera d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <p class="mb-0 small opacity-75">Pago a <?= e(config('empresa.nombre', 'CERAMICENTRO')) ?></p>
                <p class="h4 mb-0"><?= e(dinero($pedido['total'] ?? 0)) ?></p>
            </div>
            <div class="text-end">
                <p class="mb-0 small opacity-75">Pedido</p>
                <p class="fw-bold mb-0"><?= e(pedido_numero($pedido)) ?></p>
            </div>
        </div>
        <div class="pse-cuerpo">
            <?php if ($estado === ESTADO_RECHAZADO): ?>
                <div class="text-center">
                    <div class="exito-icono error-icono mb-3" aria-hidden="true"><i class="bi bi-x-lg"></i></div>
                    <h2 class="h4">Pago rechazado</h2>
                    <p class="text-secondary">El banco simulado no aprobó la transacción<?= !empty($pedido['pago']['referencia']) ? ' (ref. ' . e($pedido['pago']['referencia']) . ')' : '' ?>. No se realizó ningún cobro y los productos se liberaron.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-2">
                        <form method="post"><?= csrf_campo() ?><input type="hidden" name="accion" value="reintentar">
                            <button type="submit" class="btn btn-cs btn-lg" data-cargando="Reservando…"><i class="bi bi-arrow-repeat" aria-hidden="true"></i> Intentar de nuevo</button></form>
                        <form method="post" data-confirmar="¿Cancelar este pedido?"><?= csrf_campo() ?><input type="hidden" name="accion" value="cancelar">
                            <button type="submit" class="btn btn-outline-secondary btn-lg">Cancelar pedido</button></form>
                    </div>
                </div>
            <?php else: ?>
            <?php if ($vence = pedido_vence($pedido)): ?>
            <p class="small text-secondary"><i class="bi bi-clock" aria-hidden="true"></i> Tus productos están reservados hasta el <strong><?= e(fecha_local($vence)) ?></strong>. Si no completas el pago, el pedido se cancelará automáticamente.</p>
            <?php endif; ?>
            <form method="post" id="formPse" data-validar novalidate>
                <?= csrf_campo() ?>
                <input type="hidden" name="accion" value="pagar">
                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Tipo de persona</legend>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="form-check"><input class="form-check-input" type="radio" name="persona" id="pNatural" value="natural" required checked><label class="form-check-label" for="pNatural">Persona natural</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="persona" id="pJuridica" value="juridica" required><label class="form-check-label" for="pJuridica">Persona jurídica</label></div>
                    </div>
                </fieldset>
                <div class="mb-3">
                    <label class="form-label" for="banco">Banco (simulado)</label>
                    <select id="banco" name="banco" class="form-select" required data-mensaje="Selecciona un banco.">
                        <option value="">Selecciona tu banco…</option>
                        <?php foreach ($bancos as $b): ?><option><?= e($b) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <fieldset class="mb-4">
                    <legend class="form-label fs-6">Resultado que quieres simular</legend>
                    <div class="d-flex gap-3 flex-wrap">
                        <div class="form-check"><input class="form-check-input" type="radio" name="resultado" id="rOk" value="aprobado" checked><label class="form-check-label" for="rOk">Pago aprobado</label></div>
                        <div class="form-check"><input class="form-check-input" type="radio" name="resultado" id="rNo" value="rechazado"><label class="form-check-label" for="rNo">Pago rechazado</label></div>
                    </div>
                </fieldset>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-lg text-white" style="background:#0b3d91" data-cargando="Procesando pago…"><i class="bi bi-shield-lock" aria-hidden="true"></i> Pagar <?= e(dinero($pedido['total'] ?? 0)) ?></button>
                </div>
            </form>
            <div class="pse-procesando" id="pseProcesando" role="status" aria-live="assertive">
                <div class="spinner-border text-primary mb-3" style="width:3rem;height:3rem" aria-hidden="true"></div>
                <p class="h5">Procesando el pago simulado…</p>
                <p class="text-secondary small mb-0">No cierres ni recargues esta página.</p>
            </div>
            <form method="post" class="text-center mt-3" data-confirmar="¿Cancelar este pedido? Los productos se liberarán.">
                <?= csrf_campo() ?><input type="hidden" name="accion" value="cancelar">
                <button type="submit" class="btn btn-link text-secondary">Cancelar pedido</button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('formPse');
    if (!form) return;
    form.addEventListener('submit', function (ev) {
        if (ev.defaultPrevented) return;
        ev.preventDefault();
        form.hidden = true;
        document.getElementById('pseProcesando').classList.add('activo');
        setTimeout(function () { form.submit(); }, 1500);
    });
});
</script>
<?php layout_fin(['sin_whatsapp' => true]); ?>
