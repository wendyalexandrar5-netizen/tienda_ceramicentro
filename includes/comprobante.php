<?php
/**
 * Generación del comprobante PDF de un pedido (TCPDF).
 * Usado por: generar_comprobante.php, descargar_pedido.php (cliente web),
 * api/descargar_pedido_app.php (app Android) y admin_pedido_pdf.php (administración).
 */
require_once __DIR__ . '/pedidos.php';

/**
 * @param array  $pedido   Documento del pedido
 * @param array  $cliente  ['nombre' => ..., 'correo' => ...]
 * @param string $titulo   "Comprobante de Pedido" | "Detalle del Pedido"
 * @param string $destino  'I' = ver en navegador, 'D' = descargar
 */
function enviar_comprobante_pdf(array $pedido, array $cliente, string $titulo = 'Comprobante de Pedido', string $destino = 'I'): void
{
    $detalles = pedido_detalles($pedido);
    $numero   = pedido_numero($pedido);
    $estado   = pedido_estado($pedido);
    $fecha    = fecha_local($pedido['fecha'] ?? null, 'd/m/Y h:i a');
    $total    = (float)($pedido['total'] ?? 0);
    // Los precios de la tienda incluyen IVA (19 %): se discrimina a partir del total.
    $base = $total / 1.19;
    $iva  = $total - $base;

    $empresa = (string)config('empresa.nombre', 'CERAMICENTRO');
    $tienda  = (string)config('empresa.tienda', 'CERAMISHOP');

    $pdf = new TCPDF('P', 'mm', 'LETTER', true, 'UTF-8', false);
    $pdf->SetCreator($tienda);
    $pdf->SetAuthor($empresa);
    $pdf->SetTitle($titulo . ' ' . $numero);
    $pdf->SetSubject('Pedido ' . $numero);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 18);
    $pdf->AddPage();

    // Encabezado con logo y datos del documento
    $logo = CS_ROOT . '/imagenes/logosinfondo.png';
    if (is_file($logo)) {
        $pdf->Image($logo, 15, 12, 34);
    }
    $pdf->SetTextColor(198, 40, 40);
    $pdf->SetFont('helvetica', 'B', 18);
    $pdf->SetXY(60, 14);
    $pdf->Cell(0, 8, $empresa, 0, 1, 'R');
    $pdf->SetTextColor(80, 80, 80);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetX(60);
    $pdf->Cell(0, 5, $tienda . ' · Tienda en línea', 0, 1, 'R');
    $contacto = array_filter([config('empresa.direccion'), config('empresa.telefono'), config('empresa.correo')]);
    if ($contacto) {
        $pdf->SetX(60);
        $pdf->Cell(0, 5, implode(' · ', $contacto), 0, 1, 'R');
    }

    $pdf->SetDrawColor(198, 40, 40);
    $pdf->SetLineWidth(0.6);
    $pdf->Line(15, 40, 201, 40);
    $pdf->SetY(44);

    $pdf->SetTextColor(33, 33, 33);
    $pdf->SetFont('helvetica', 'B', 15);
    $pdf->Cell(0, 8, $titulo, 0, 1, 'L');

    $fila = function (string $etiqueta, string $valor) use ($pdf) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 6, $etiqueta, 0, 0);
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 6, $valor, 0, 1);
    };
    $fila('Pedido N.º:', $numero);
    $fila('Fecha:', $fecha);
    $fila('Estado:', $estado);
    $fila('Método de pago:', (string)($pedido['metodo_pago'] ?? 'PSE (simulado)'));
    if (!empty($pedido['pago']['referencia'])) {
        $fila('Referencia de pago:', (string)$pedido['pago']['referencia']);
    }
    $fila('Cliente:', (string)($cliente['nombre'] ?? ''));
    if (!empty($cliente['correo'])) {
        $fila('Correo:', (string)$cliente['correo']);
    }
    $pdf->Ln(4);

    $h = fn($t) => htmlspecialchars((string)$t, ENT_QUOTES, 'UTF-8');
    $html = '<table border="0" cellpadding="6" cellspacing="0" style="font-size:9.5pt;">'
        . '<thead><tr style="background-color:#c62828;color:#ffffff;font-weight:bold;">'
        . '<th width="46%">Producto</th><th width="12%" align="center">Cant.</th>'
        . '<th width="20%" align="right">Precio unit.</th><th width="22%" align="right">Subtotal</th>'
        . '</tr></thead><tbody>';
    $i = 0;
    foreach ($detalles as $d) {
        $bg = ($i++ % 2) ? '#f7f7f7' : '#ffffff';
        $html .= '<tr style="background-color:' . $bg . ';">'
            . '<td width="46%">' . $h($d['nombre_producto']) . '</td>'
            . '<td width="12%" align="center">' . (int)$d['cantidad'] . '</td>'
            . '<td width="20%" align="right">' . $h(dinero($d['precio_unitario'])) . '</td>'
            . '<td width="22%" align="right">' . $h(dinero($d['subtotal'])) . '</td></tr>';
    }
    $html .= '<tr><td colspan="3" align="right" style="border-top:1px solid #cccccc;">Base (sin IVA)</td>'
        . '<td align="right" style="border-top:1px solid #cccccc;">' . $h(dinero($base)) . '</td></tr>'
        . '<tr><td colspan="3" align="right">IVA 19 %</td><td align="right">' . $h(dinero($iva)) . '</td></tr>'
        . '<tr style="background-color:#fdecea;font-weight:bold;"><td colspan="3" align="right">TOTAL</td>'
        . '<td align="right">' . $h(dinero($total)) . '</td></tr>'
        . '</tbody></table>';
    $pdf->writeHTML($html, true, false, false, false, '');

    $pdf->Ln(6);
    $pdf->SetFont('helvetica', 'I', 8.5);
    $pdf->SetTextColor(110, 110, 110);
    $pdf->MultiCell(0, 4.5,
        "Los precios incluyen IVA. Este documento es un comprobante del pedido y no reemplaza una factura electrónica.\n"
        . "El pago se registró mediante un simulador de PSE con fines demostrativos: no se realizó ninguna transacción bancaria real.\n"
        . 'Generado el ' . fecha_local(nowUTC(), 'd/m/Y h:i a') . ' · ' . $tienda,
        0, 'L');

    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $nombreArchivo = strtolower(preg_replace('/[^A-Za-z0-9]+/', '_', $titulo)) . '_' . $numero . '_' . fecha_local($pedido['fecha'] ?? null, 'Y-m-d') . '.pdf';
    header('Cache-Control: private, no-store');
    $pdf->Output($nombreArchivo, $destino === 'D' ? 'D' : 'I');
    exit;
}
