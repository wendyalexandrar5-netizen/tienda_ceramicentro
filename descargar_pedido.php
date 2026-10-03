<?php
session_start();

require_once __DIR__ . '/vendor/autoload.php';
include("verificar_acceso.php");
verificarSesion("cliente");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

date_default_timezone_set("America/Bogota");

if (!isset($_SESSION["usuario"])) {
    header("Location: login.php");
    exit;
}

if (!isset($_GET["id"]) || !preg_match('/^[a-f\d]{24}$/i', $_GET["id"])) {
    die("ID de pedido no válido.");
}

$pedidoId = $_GET["id"];

$usuario       = $_SESSION["usuario"];
$usuarioId     = $usuario["id"];
$usuarioNombre = $usuario["nombre"];

$db = mongo();

$colPedidos  = $db->selectCollection("pedidos");
$colDetalles = $db->selectCollection("pedido_detalle");

$pedido = $colPedidos->findOne(
    [
        "_id" => new ObjectId($pedidoId),
        "usuario_id" => new ObjectId($usuarioId)
    ],
    ["typeMap" => ["root" => "array", "document" => "array"]]
);

if (!$pedido) {
    die("❌ No se encontró el pedido.");
}

$fechaObj = $pedido["fecha"]->toDateTime();
$fechaObj->setTimezone(new DateTimeZone("America/Bogota"));
$fecha = $fechaObj->format("Y-m-d H:i");

$total = (float)$pedido["total"];
$subtotalSinIva = $total / 1.19;
$iva = $total - $subtotalSinIva;

$detalleCursor = $colDetalles->find(
    ["pedido_id" => new ObjectId($pedidoId)],
    ["typeMap" => ["root" => "array", "document" => "array"]]
);

$detalles = iterator_to_array($detalleCursor, false);

require_once __DIR__ . "/vendor/tecnickcom/tcpdf/tcpdf.php";

$pdf = new TCPDF();
$pdf->SetCreator('CERAMICENTRO');
$pdf->SetAuthor('CERAMICENTRO');
$pdf->SetTitle("Detalle del Pedido #$pedidoId");

$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

$logoPath = __DIR__ . "/imagenes/logosinfondo.png";

if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 10, 40);
}

$pdf->Ln(30);

$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 10, "Detalle del Pedido", 0, 1, 'C');

$pdf->SetFont('helvetica', '', 12);
$pdf->Cell(0, 8, "Cliente: " . $usuarioNombre, 0, 1);
$pdf->Cell(0, 8, "ID Pedido: " . $pedidoId, 0, 1);
$pdf->Cell(0, 8, "Fecha: " . $fecha, 0, 1);

$pdf->Ln(5);

$html = '
<table border="1" cellpadding="5" cellspacing="0">
    <thead>
        <tr style="background-color:#eeeeee;">
            <th><b>Producto</b></th>
            <th><b>Cantidad</b></th>
            <th><b>Precio Unitario</b></th>
            <th><b>Subtotal</b></th>
        </tr>
    </thead>
    <tbody>
';

foreach ($detalles as $item) {
    $subtotal = $item["subtotal"];

    $html .= '
        <tr>
            <td>' . htmlspecialchars($item["nombre_producto"]) . '</td>
            <td align="center">' . $item["cantidad"] . '</td>
            <td align="right">$' . number_format($item["precio_unitario"], 2) . '</td>
            <td align="right">$' . number_format($subtotal, 2) . '</td>
        </tr>
    ';
}

$html .= '
        <tr style="background-color:#f9f9f9;">
            <td colspan="3" align="right"><b>Subtotal sin IVA:</b></td>
            <td align="right">$' . number_format($subtotalSinIva, 2) . '</td>
        </tr>

        <tr style="background-color:#f9f9f9;">
            <td colspan="3" align="right"><b>IVA 19%:</b></td>
            <td align="right">$' . number_format($iva, 2) . '</td>
        </tr>

        <tr style="background-color:#eeeeee;">
            <td colspan="3" align="right"><b>Total del Pedido:</b></td>
            <td align="right"><b>$' . number_format($total, 2) . '</b></td>
        </tr>
';

$html .= '</tbody></table>';

$pdf->writeHTML($html, true, false, false, false, '');

ob_end_clean();

$pdf->Output("pedido_$pedidoId.pdf", 'I');
exit;
?>
