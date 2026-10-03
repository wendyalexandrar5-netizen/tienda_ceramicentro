<?php
/** Estadísticas de ventas por año (solo pedidos pagados / en proceso / entregados). */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/estadisticas.php';
requerir_sesion('administrador');

$anios = anios_con_pedidos();
$anio = (int)($_GET['anio'] ?? $anios[0]);
if (!in_array($anio, $anios, true)) {
    $anio = $anios[0];
}
$s = estadisticas_ventas($anio);
$meses = meses_es();

admin_inicio('Estadísticas de ventas', 'estadisticas', ['acciones' =>
    boton_exportar('exportar_estadisticas_excel.php', ['anio' => $anio]) . boton_exportar('reporte_ventas_pdf.php', ['anio' => $anio], 'Reporte PDF', 'pdf')]);
?>
<form method="get" class="d-flex flex-wrap align-items-end gap-2 mb-4" data-sin-bloqueo>
    <div>
        <label class="form-label small" for="anio">Año</label>
        <select id="anio" name="anio" class="form-select" onchange="this.form.submit()">
            <?php foreach ($anios as $a): ?><option <?= $a === $anio ? 'selected' : '' ?>><?= (int)$a ?></option><?php endforeach; ?>
        </select>
    </div>
    <noscript><button class="btn btn-cs" type="submit">Ver</button></noscript>
    <p class="small text-secondary mb-2">Solo se cuentan pedidos pagados, en preparación, enviados o entregados.</p>
</form>

<div class="row g-3 mb-4">
    <?php foreach ([
        ['receipt', $s['ventas'], 'Ventas realizadas'],
        ['box-seam', number_format($s['unidades'], 0, ',', '.'), 'Productos vendidos (unidades)'],
        ['cash-coin', dinero($s['ingresos']), 'Ingresos totales'],
        ['graph-up-arrow', dinero($s['ticket']), 'Valor promedio por pedido'],
    ] as [$icono, $valor, $etiqueta]): ?>
    <div class="col-sm-6 col-xl-3"><div class="kpi"><div class="kpi-icono" aria-hidden="true"><i class="bi bi-<?= e($icono) ?>"></i></div><div><div class="kpi-valor"><?= e($valor) ?></div><div class="kpi-etiqueta"><?= e($etiqueta) ?></div></div></div></div>
    <?php endforeach; ?>
</div>

<div class="row g-4">
    <div class="col-xl-8">
        <div class="cs-panel h-100">
            <h2 class="cs-panel-titulo">Ingresos mensuales <?= (int)$anio ?></h2>
            <div style="position:relative;min-height:280px"><canvas id="graficoVentas" role="img" aria-label="Gráfico de barras de ingresos mensuales de <?= (int)$anio ?>"></canvas></div>
            <details class="mt-3">
                <summary class="small fw-semibold">Ver datos en tabla</summary>
                <div class="table-responsive mt-2">
                    <table class="table table-sm">
                        <thead><tr><th scope="col">Mes</th><th scope="col" class="text-end">Pedidos</th><th scope="col" class="text-end">Ingresos</th></tr></thead>
                        <tbody><?php foreach ($meses as $n => $m): ?><tr><td><?= e($m) ?></td><td class="text-end"><?= (int)$s['pedidos_mes'][$n] ?></td><td class="text-end"><?= e(dinero($s['mensual'][$n])) ?></td></tr><?php endforeach; ?></tbody>
                    </table>
                </div>
            </details>
        </div>
    </div>
    <div class="col-xl-4">
        <div class="cs-panel mb-4">
            <h2 class="cs-panel-titulo">Top 5 productos más vendidos</h2>
            <?php if (!$s['top']): ?><p class="text-secondary mb-0">Sin ventas en <?= (int)$anio ?>.</p><?php else: ?>
            <ol class="list-group list-group-numbered list-group-flush">
                <?php foreach ($s['top'] as $t): ?>
                <li class="list-group-item d-flex justify-content-between align-items-start px-0">
                    <div class="ms-2 me-auto"><div class="fw-semibold"><?= e($t['nombre']) ?></div><span class="small text-secondary"><?= e(dinero($t['ingresos'])) ?></span></div>
                    <span class="badge rounded-pill text-bg-danger"><?= (int)$t['cantidad'] ?> und.</span>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php endif; ?>
        </div>
        <div class="cs-panel">
            <h2 class="cs-panel-titulo">Pedidos por estado</h2>
            <?php if (!$s['por_estado']): ?><p class="text-secondary mb-0">Sin pedidos en <?= (int)$anio ?>.</p><?php else: ?>
            <ul class="list-unstyled mb-0">
                <?php foreach ($s['por_estado'] as $estado => $d): ?>
                <li class="d-flex justify-content-between align-items-center py-1"><?= estado_badge($estado) ?><span><?= (int)$d['n'] ?> · <?= e(dinero($d['total'])) ?></span></li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    if (!window.Chart) { return; }
    new Chart(document.getElementById('graficoVentas'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_values($meses), JSON_UNESCAPED_UNICODE) ?>,
            datasets: [{ label: 'Ingresos (COP)', data: <?= json_encode(array_values($s['mensual'])) ?>, backgroundColor: '#c62828', borderRadius: 6 }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false }, tooltip: { callbacks: { label: function (c) { return ' $' + Math.round(c.parsed.y).toLocaleString('es-CO'); } } } },
            scales: { y: { beginAtZero: true, ticks: { callback: function (v) { return '$' + Number(v).toLocaleString('es-CO'); } } } }
        }
    });
});
</script>
<?php admin_fin(['scripts' => ['https://cdn.jsdelivr.net/npm/chart.js@4.4.6/dist/chart.umd.js']]); ?>
