<?php
ob_start();
require 'vendor/autoload.php';
include("conexion_mongo.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use MongoDB\BSON\UTCDateTime;

$db = mongo();
$colHistorial = $db->selectCollection("historial_productos");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();

$logo = new Drawing();
$logo->setName('Logo CERAMICENTRO');
$logo->setPath(__DIR__ . '/imagenes/logosinfondo.png');
$logo->setHeight(80);
$logo->setCoordinates('A1');
$logo->setWorksheet($sheet);

$sheet->mergeCells('B1:D2');
$sheet->setCellValue('B1', 'HISTORIAL DE PRODUCTOS');
$sheet->getStyle('B1')->getFont()->setBold(true)->setSize(20)->getColor()->setRGB('e53935');
$sheet->getStyle('B1')->getAlignment()->setHorizontal('center')->setVertical('center');

$encabezados = ['ID', 'Administrador', 'Acción', 'Producto', 'Fecha'];
$col = 'A';

foreach ($encabezados as $e) {
    $sheet->setCellValue($col . '4', $e);
    $col++;
}

$sheet->getStyle('A4:E4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A4:E4')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setRGB('e53935');
$sheet->getStyle('A4:E4')->getAlignment()->setHorizontal('center');

$cursor = $colHistorial->find(
    [],
    [
        "sort" => ["fecha" => -1],
        "typeMap" => ["root" => "array", "document" => "array"]
    ]
);

$fila = 5;

foreach ($cursor as $row) {
    $fecha = ($row["fecha"] instanceof UTCDateTime)
        ? $row["fecha"]->toDateTime()->format("Y-m-d H:i")
        : "";

    $sheet->setCellValue("A{$fila}", (string)$row["_id"]);
    $sheet->setCellValue("B{$fila}", $row["nombre_admin"] ?? "");
    $sheet->setCellValue("C{$fila}", $row["accion"] ?? "");
    $sheet->setCellValue("D{$fila}", $row["producto_nombre"] ?? "");
    $sheet->setCellValue("E{$fila}", $fecha);

    $fila++;
}

$sheet->getStyle("A4:E" . ($fila - 1))
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

$sheet->getDefaultColumnDimension()->setWidth(20);
$sheet->getDefaultRowDimension()->setRowHeight(25);
$sheet->getStyle("A4:E" . max(4, $fila - 1))->getAlignment()->setVertical('center');

ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="historial_productos.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
