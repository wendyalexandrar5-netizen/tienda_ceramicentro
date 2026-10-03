<?php
/** Mensajes recibidos desde el formulario de contacto (respaldo en MongoDB). */
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');

$col = mongo()->selectCollection('mensajes_contacto');
if (es_post()) {
    csrf_verificar();
    $id = oid((string)($_POST['id'] ?? ''));
    if ($id && ($_POST['accion'] ?? '') === 'leido') {
        $col->updateOne(['_id' => $id], ['$set' => ['leido' => true]]);
    } elseif ($id && ($_POST['accion'] ?? '') === 'eliminar') {
        $col->deleteOne(['_id' => $id]);
        flash('success', 'Mensaje eliminado.');
    }
    redirigir('mensajes_contacto.php');
}
$porPagina = 20;
$total = $col->countDocuments([]);
$paginas = max(1, (int)ceil($total / $porPagina));
$pagina = min($paginas, max(1, (int)($_GET['pagina'] ?? 1)));
$mensajes = iterator_to_array($col->find([], ['sort' => ['fecha' => -1], 'skip' => ($pagina - 1) * $porPagina, 'limit' => $porPagina]), false);

admin_inicio('Mensajes de contacto', 'mensajes');
?>
<p class="text-secondary small">Todos los mensajes del formulario de contacto se guardan aquí, incluso si el envío por correo no está configurado o falla.</p>
<?php if (!$mensajes): ?>
<div class="cs-panel text-center text-secondary py-5">No hay mensajes todavía.</div>
<?php endif; ?>
<div class="d-grid gap-3">
<?php foreach ($mensajes as $m): $leido = !empty($m['leido']); ?>
    <article class="cs-panel <?= $leido ? '' : 'border-start border-4 border-danger' ?>">
        <div class="d-flex flex-wrap justify-content-between gap-2">
            <div>
                <h2 class="h6 mb-1"><?= $leido ? '' : '<span class="badge text-bg-danger me-1">Nuevo</span>' ?><?= e($m['asunto'] ?? '(sin asunto)') ?></h2>
                <p class="small text-secondary mb-2"><?= e($m['nombre'] ?? '') ?> · <a href="mailto:<?= e($m['correo'] ?? '') ?>"><?= e($m['correo'] ?? '') ?></a> · <?= e(fecha_local($m['fecha'] ?? null)) ?><?= !empty($m['enviado_correo']) ? ' · enviado por correo' : '' ?></p>
            </div>
            <div class="d-flex gap-1 align-items-start">
                <?php if (!$leido): ?>
                <form method="post"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= e((string)$m['_id']) ?>"><input type="hidden" name="accion" value="leido"><button class="btn btn-sm btn-outline-secondary" type="submit">Marcar como leído</button></form>
                <?php endif; ?>
                <form method="post" data-confirmar="¿Eliminar este mensaje?"><?= csrf_campo() ?><input type="hidden" name="id" value="<?= e((string)$m['_id']) ?>"><input type="hidden" name="accion" value="eliminar"><button class="btn btn-sm btn-outline-danger" type="submit" aria-label="Eliminar mensaje"><i class="bi bi-trash" aria-hidden="true"></i></button></form>
            </div>
        </div>
        <p class="mb-0" style="white-space:pre-line"><?= e($m['mensaje'] ?? '') ?></p>
    </article>
<?php endforeach; ?>
</div>
<?php paginacion_html($pagina, $paginas, 'mensajes_contacto.php'); ?>
<?php admin_fin(); ?>
