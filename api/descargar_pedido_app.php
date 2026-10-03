<?php
require __DIR__ . '/../vendor/autoload.php';
include("../conexion_mongo.php");

use MongoDB\BSON\ObjectId;

date_default_timezone_set("America/Bogota");

if (!isset($_GET["id"], $_GET["usuario_id"])) {
    die("Datos incompletos.");
}

$pedidoId = $_GET["id"];
$usuarioId = $_GET["usuario_id"];

if (!preg_match('/^[a-f\d]{24}$/i', $pedidoId) || !preg_match('/^[a-f\d]{24}$/i', $usuarioId)) {
    die("ID inválido.");
}

$db = mongo();

$colPedidos = $db->selectCollection("pedidos");
$colDetalles = $db->selectCollection("pedido_detalle");

$pedido = $colPedidos->findOne([
    "_id" => new ObjectId($pedidoId),
    "usuario_id" => new ObjectId($usuarioId)
]);

if (!$pedido) {
    die("Pedido no encontrado.");
}

$fechaObj = $pedido["fecha"]->toDateTime();
$fechaObj->setTimezone(new DateTimeZone("America/Bogota"));
$fecha = $fechaObj->format("Y-m-d H:i");

$total = (float)$pedido["total"];
$subtotalSinIva = $total / 1.19;
$iva = $total - $subtotalSinIva;

$detalles = $colDetalles->find([
    "pedido_id" => new ObjectId($pedidoId)
]);

require_once __DIR__ . "/../vendor/tecnickcom/tcpdf/tcpdf.php";

$pdf = new TCPDF();
$pdf->SetCreator("CERAMISHOP");
$pdf->SetAuthor("CERAMISHOP");
$pdf->SetTitle("Comprobante Pedido $pedidoId");

$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();
$logo = __DIR__ . "/../imagenes/logosinfondo.png";

if (file_exists($logo)) {

    $pdf->Image(
        $logo,
        15,
        10,
        40
    );
}

$pdf->Ln(30);

$pdf->SetFont("helvetica", "B", 20);

$pdf->Cell(
    0,
    12,
    "Comprobante de Pedido",
    0,
    1,
    "C"
);

$pdf->SetFont("helvetica", "", 12);
$pdf->Cell(0, 8, "ID Pedido: " . $pedidoId, 0, 1);
$pdf->Cell(0, 8, "Fecha: " . $fecha, 0, 1);
$pdf->Cell(0, 8, "Estado: " . ($pedido["estado"] ?? "Pagado"), 0, 1);

$pdf->Ln(8);

$html = '
<table border="1" cellpadding="6">
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
    $html .= '
    <tr>
        <td>' . htmlspecialchars($item["nombre_producto"]) . '</td>
        <td align="center">' . $item["cantidad"] . '</td>
        <td align="right">$' . number_format($item["precio_unitario"], 2) . '</td>
        <td align="right">$' . number_format($item["subtotal"], 2) . '</td>
    </tr>';
}

$html .= '
<tr>
    <td colspan="3" align="right"><b>Subtotal sin IVA:</b></td>
    <td align="right">$' . number_format($subtotalSinIva, 2) . '</td>
</tr>
<tr>
    <td colspan="3" align="right"><b>IVA 19%:</b></td>
    <td align="right">$' . number_format($iva, 2) . '</td>
</tr>
<tr style="background-color:#eeeeee;">
    <td colspan="3" align="right"><b>Total:</b></td>
    <td align="right"><b>$' . number_format($total, 2) . '</b></td>
</tr>
</tbody>
</table>
';

$pdf->writeHTML($html, true, false, false, false, "");

ob_end_clean();
$pdf->Output("pedido_$pedidoId.pdf", "I");
exit;
