<?php
/** Exporta las estadísticas de ventas del año a Excel con gráficos. Solo administradores. */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/estadisticas.php';
require_once __DIR__ . '/includes/excel.php';
requerir_sesion('administrador');

use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

$anio = (int)($_GET['anio'] ?? date('Y'));
if ($anio < 2000 || $anio > (int)date('Y') + 1) {
    $anio = (int)date('Y');
}
$s = estadisticas_ventas($anio);
[$libro, $hoja] = excel_nuevo('Estadísticas');
$nombreHoja = "'Estadísticas'";

$f = excel_tabla($hoja, 'ESTADÍSTICAS DE VENTAS ' . $anio, [['titulo' => 'Indicador', 'ancho' => 34], ['titulo' => 'Valor', 'ancho' => 20]], [
    ['Ventas realizadas', $s['ventas']],
    ['Productos vendidos (unidades)', $s['unidades']],
    ['Ingresos totales (COP)', round($s['ingresos'], 2)],
    ['Valor promedio por pedido (COP)', round($s['ticket'], 2)],
], 1, 'Solo pedidos pagados, en preparación, enviados o entregados');
$hoja->getStyle('B7:B8')->getNumberFormat()->setFormatCode('"$"#,##0');

$iniTop = $f + 2;
$finTop = excel_tabla($hoja, 'Top 5 productos más vendidos', [['titulo' => 'Producto', 'ancho' => 34], ['titulo' => 'Cantidad', 'ancho' => 20, 'formato' => 'entero'], ['titulo' => 'Ingresos', 'ancho' => 18, 'formato' => 'dinero']],
    array_map(fn($t) => [$t['nombre'], $t['cantidad'], round($t['ingresos'], 2)], $s['top']), $iniTop);

$iniMes = $finTop + 2;
$filasMes = [];
foreach (meses_es() as $n => $m) {
    $filasMes[] = [$m, $s['pedidos_mes'][$n], round($s['mensual'][$n], 2)];
}
$finMes = excel_tabla($hoja, 'Ventas por mes ' . $anio, [['titulo' => 'Mes', 'ancho' => 34], ['titulo' => 'Pedidos', 'ancho' => 20, 'formato' => 'entero'], ['titulo' => 'Ingresos', 'ancho' => 18, 'formato' => 'dinero']], $filasMes, $iniMes);

$grafico = function (string $nombre, string $titulo, int $desde, int $hasta, string $colValores, string $pos1, string $pos2) use ($hoja, $nombreHoja) {
    $n = $hasta - $desde + 1;
    $series = new DataSeries(DataSeries::TYPE_BARCHART, DataSeries::GROUPING_CLUSTERED, [0], [],
        [new DataSeriesValues('String', "{$nombreHoja}!\$A\${$desde}:\$A\${$hasta}", null, $n)],
        [new DataSeriesValues('Number', "{$nombreHoja}!\${$colValores}\${$desde}:\${$colValores}\${$hasta}", null, $n)]);
    $series->setPlotDirection(DataSeries::DIRECTION_COL);
    $chart = new Chart($nombre, new Title($titulo), new Legend(Legend::POSITION_BOTTOM, null, false), new PlotArea(null, [$series]));
    $chart->setTopLeftPosition($pos1);
    $chart->setBottomRightPosition($pos2);
    $hoja->addChart($chart);
};
if ($s['top']) {
    $grafico('Top5', 'Top 5 productos (unidades)', $iniTop + 2, $finTop, 'B', 'E4', 'M20');
}
$grafico('Mensual', 'Ingresos por mes ' . $anio, $iniMes + 2, $finMes, 'C', 'E22', 'M40');

excel_enviar($libro, 'estadisticas_ventas_' . $anio, true);
