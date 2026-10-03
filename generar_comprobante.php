<?php 
session_start();

require_once __DIR__ . "/vendor/autoload.php";
include("verificar_acceso.php");
verificarSesion("cliente");
include("conexion_mongo.php");

use MongoDB\BSON\ObjectId;

date_default_timezone_set("America/Bogota");

if (!isset($_SESSION["usuario"]) || !isset($_GET["id"])) {
    header("Location: tienda.php");
    exit;
}

$pedidoId = $_GET["id"];

if (!preg_match('/^[a-f\d]{24}$/i', $pedidoId)) {
    die("ID de pedido inválido.");
}

$usuario       = $_SESSION["usuario"];
$usuarioId     = $usuario["id"];
$nombreUsuario = $usuario["nombre"];

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

$cursor = $colDetalles->find(
    ["pedido_id" => new ObjectId($pedidoId)],
    ["typeMap" => ["root" => "array", "document" => "array"]]
);

$items = iterator_to_array($cursor, false);

require_once __DIR__ . "/vendor/tecnickcom/tcpdf/tcpdf.php";

ob_end_clean();

$pdf = new TCPDF();
$pdf->SetCreator("CERAMICENTRO");
$pdf->SetAuthor("CERAMICENTRO");
$pdf->SetTitle("Comprobante Pedido $pedidoId");

$pdf->SetMargins(15, 20, 15);
$pdf->AddPage();

$logo = __DIR__ . "/imagenes/logosinfondo.png";

if (file_exists($logo)) {
    $pdf->Image($logo, 15, 10, 40);
}

$pdf->Ln(30);

$pdf->SetFont("helvetica", "B", 18);
$pdf->Cell(0, 12, "Comprobante de Pedido", 0, 1, "C");

$pdf->SetFont("helvetica", "", 12);
$pdf->Cell(0, 8, "Cliente: " . $nombreUsuario, 0, 1);
$pdf->Cell(0, 8, "ID Pedido: " . $pedidoId, 0, 1);
$pdf->Cell(0, 8, "Fecha: " . $fecha, 0, 1);

$pdf->Ln(10);

$html = '
<style>
tbody tr:nth-child(odd){background-color:#f9f9f9;}
th{background-color:#eeeeee;}
</style>

<table border="1" cellpadding="6">
<thead>
<tr>
    <th width="40%"><b>Producto</b></th>
    <th width="15%"><b>Cantidad</b></th>
    <th width="20%"><b>Precio Unitario</b></th>
    <th width="25%"><b>Subtotal</b></th>
</tr>
</thead>
<tbody>
';

foreach ($items as $item) {
    $subtotal = $item["cantidad"] * $item["precio_unitario"];

    $html .= '
    <tr>
        <td>' . htmlspecialchars($item["nombre_producto"]) . '</td>
        <td align="center">' . $item["cantidad"] . '</td>
        <td align="right">$' . number_format($item["precio_unitario"], 2) . '</td>
        <td align="right">$' . number_format($subtotal, 2) . '</td>
    </tr>';
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
    <td colspan="3" align="right"><b>Total:</b></td>
    <td align="right"><b>$' . number_format($total, 2) . '</b></td>
</tr>
</tbody>
</table>
';

$pdf->writeHTML($html, true, false, false, false, "");

$pdf->Output("comprobante_$pedidoId.pdf", "I");
exit;
?>
