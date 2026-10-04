<?php
/**
 * Diagnóstico de conexión a MongoDB (solo administradores).
 * Antes insertaba un "Producto de prueba" en la colección productos; ahora solo consulta.
 */
require_once __DIR__ . '/includes/admin.php';
requerir_sesion('administrador');

$filas = [];
$ok = false;
try {
    $t = microtime(true);
    mongo()->command(['ping' => 1]);
    $ok = true;
    $ms = round((microtime(true) - $t) * 1000, 1);
    foreach (['usuarios', 'productos', 'categorias', 'pedidos', 'pedido_detalle', 'historial_productos', 'mensajes_contacto'] as $c) {
        $filas[] = [$c, mongo()->selectCollection($c)->countDocuments([])];
    }
} catch (Throwable $e) {
    log_app('error', 'Diagnóstico MongoDB: ' . $e->getMessage());
}
admin_inicio('Diagnóstico de MongoDB', '');
?>
<div class="cs-panel" style="max-width:640px">
    <?php if ($ok): ?>
    <p class="text-success fw-semibold"><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Conexión exitosa con MongoDB (<?= e($ms) ?> ms) · base de datos «<?= e(config('mongo.base_de_datos')) ?>»</p>
    <table class="table table-sm"><thead><tr><th scope="col">Colección</th><th scope="col" class="text-end">Documentos</th></tr></thead><tbody>
        <?php foreach ($filas as [$c, $n]): ?><tr><td><?= e($c) ?></td><td class="text-end"><?= (int)$n ?></td></tr><?php endforeach; ?>
    </tbody></table>
    <?php else: ?>
    <p class="text-danger fw-semibold"><i class="bi bi-x-circle-fill" aria-hidden="true"></i> No fue posible conectar con MongoDB. Revisa que el servicio esté iniciado y la configuración en config/config.php. El detalle quedó en logs/app.log.</p>
    <?php endif; ?>
</div>
<?php admin_fin(); ?>
