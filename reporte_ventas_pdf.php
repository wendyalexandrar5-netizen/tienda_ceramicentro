<?php
/** Reporte de ventas del año en PDF (administradores). */
require_once __DIR__ . '/includes/admin.php';
require_once __DIR__ . '/includes/estadisticas.php';
requerir_sesion('administrador');

$anio = (int)($_GET['anio'] ?? date('Y'));
if ($anio < 2000 || $anio > (int)date('Y') + 1) {
    $anio = (int)date('Y');
}
$s = estadisticas_ventas($anio);
$h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');

$pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
$pdf->SetCreator('CERAMISHOP');
$pdf->SetAuthor((string)config('empresa.nombre', 'CERAMICENTRO'));
$pdf->SetTitle('Reporte de ventas ' . $anio);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->AddPage();
$logo = CS_ROOT . '/imagenes/logosinfondo.png';
if (is_file($logo)) {
    $pdf->Image($logo, 15, 12, 30);
}
$pdf->SetFont('helvetica', 'B', 18);
$pdf->SetTextColor(198, 40, 40);
$pdf->SetXY(50, 15);
$pdf->Cell(0, 9, 'Reporte de ventas ' . $anio, 0, 1, 'R');
$pdf->SetFont('helvetica', '', 9);
$pdf->SetTextColor(90, 90, 90);
$pdf->SetX(50);
$pdf->Cell(0, 5, 'CERAMISHOP · Generado el ' . date('d/m/Y h:i a') . ' por ' . (usuario_actual()['nombre'] ?? ''), 0, 1, 'R');
$pdf->SetY(42);
$pdf->SetTextColor(33, 33, 33);

$html = '<h3 style="color:#b71c1c;">Resumen</h3><table cellpadding="5" border="0" style="font-size:10pt;">'
    . '<tr style="background-color:#f7f7f7;"><td width="60%">Ventas realizadas</td><td width="40%" align="right"><b>' . (int)$s['ventas'] . '</b></td></tr>'
    . '<tr><td>Productos vendidos (unidades)</td><td align="right"><b>' . number_format($s['unidades'], 0, ',', '.') . '</b></td></tr>'
    . '<tr style="background-color:#f7f7f7;"><td>Ingresos totales</td><td align="right"><b>' . $h(dinero($s['ingresos'])) . '</b></td></tr>'
    . '<tr><td>Valor promedio por pedido</td><td align="right"><b>' . $h(dinero($s['ticket'])) . '</b></td></tr></table>'
    . '<p style="font-size:8pt;color:#777;">Se cuentan pedidos pagados, en preparación, enviados o entregados.</p>';

$html .= '<h3 style="color:#b71c1c;">Ventas por mes</h3><table cellpadding="4" border="0" style="font-size:9.5pt;">'
    . '<tr style="background-color:#c62828;color:#fff;font-weight:bold;"><td width="40%">Mes</td><td width="25%" align="right">Pedidos</td><td width="35%" align="right">Ingresos</td></tr>';
$i = 0;
foreach (meses_es() as $n => $m) {
    $html .= '<tr style="background-color:' . (($i++ % 2) ? '#f7f7f7' : '#ffffff') . ';"><td>' . $h($m) . '</td><td align="right">' . (int)$s['pedidos_mes'][$n] . '</td><td align="right">' . $h(dinero($s['mensual'][$n])) . '</td></tr>';
}
$html .= '</table>';

$html .= '<h3 style="color:#b71c1c;">Top 5 productos más vendidos</h3>';
if ($s['top']) {
    $html .= '<table cellpadding="4" border="0" style="font-size:9.5pt;"><tr style="background-color:#c62828;color:#fff;font-weight:bold;"><td width="55%">Producto</td><td width="15%" align="right">Unidades</td><td width="30%" align="right">Ingresos</td></tr>';
    foreach ($s['top'] as $t) {
        $html .= '<tr><td>' . $h($t['nombre']) . '</td><td align="right">' . (int)$t['cantidad'] . '</td><td align="right">' . $h(dinero($t['ingresos'])) . '</td></tr>';
    }
    $html .= '</table>';
} else {
    $html .= '<p>Sin ventas registradas en ' . $anio . '.</p>';
}
if ($s['por_estado']) {
    $html .= '<h3 style="color:#b71c1c;">Pedidos por estado</h3><table cellpadding="4" style="font-size:9.5pt;">';
    foreach ($s['por_estado'] as $estado => $d) {
        $html .= '<tr><td width="55%">' . $h($estado) . '</td><td width="15%" align="right">' . (int)$d['n'] . '</td><td width="30%" align="right">' . $h(dinero($d['total'])) . '</td></tr>';
    }
    $html .= '</table>';
}
$pdf->writeHTML($html, true, false, true, false, '');
while (ob_get_level() > 0) {
    ob_end_clean();
}
$pdf->Output('reporte_ventas_' . $anio . '_' . date('Y-m-d') . '.pdf', 'D');
exit;
