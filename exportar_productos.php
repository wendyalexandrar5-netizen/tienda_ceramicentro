<?php
ob_start();
require 'vendor/autoload.php';
include("conexion_mongo.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

try {
    $db  = mongo();
    $colProductos = $db->selectCollection('productos');

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $logo = new Drawing();
    $logo->setName('Logo CERAMICENTRO');
    $logo->setPath(__DIR__ . '/imagenes/logosinfondo.png');
    $logo->setHeight(80);
    $logo->setCoordinates('A1');
    $logo->setWorksheet($sheet);

    $sheet->mergeCells('B1:C2');
    $sheet->setCellValue('B1', 'PRODUCTOS');
    $sheet->getStyle('B1')->getFont()->setBold(true)->setSize(20)->getColor()->setRGB('e53935');
    $sheet->getStyle('B1')->getAlignment()->setHorizontal('center')->setVertical('center');

    $encabezados = ['ID', 'Nombre', 'Precio', 'Stock'];
    $colLetra = 'A';
    foreach ($encabezados as $encabezado) {
        $sheet->setCellValue($colLetra . '4', $encabezado);
        $colLetra++;
    }

    $sheet->getStyle('A4:D4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $sheet->getStyle('A4:D4')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('e53935');
    $sheet->getStyle('A4:D4')->getAlignment()->setHorizontal('center');

    $cursor = $colProductos->find(
        [],
        [
            'sort' => ['nombre' => 1],
            'typeMap' => ['root' => 'array', 'document' => 'array']
        ]
    );

    $fila = 5;
    foreach ($cursor as $doc) {
        $sheet->setCellValue("A{$fila}", (string)$doc["_id"]);
        $sheet->setCellValue("B{$fila}", $doc["nombre"] ?? '');
        $sheet->setCellValue("C{$fila}", isset($doc["precio"]) ? (float)$doc["precio"] : 0);
        $sheet->setCellValue("D{$fila}", isset($doc["stock"]) ? (int)$doc["stock"] : 0);
        $fila++;
    }

    if ($fila > 5) {
        $sheet->getStyle("A4:D" . ($fila - 1))
              ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    $sheet->getDefaultColumnDimension()->setWidth(20);
    $sheet->getDefaultRowDimension()->setRowHeight(25);
    $sheet->getStyle("A4:D" . max(4, $fila - 1))->getAlignment()->setVertical('center');

    ob_end_clean();

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="productos_ceramicentro.xlsx"');
    header('Cache-Control: max-age=0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Throwable $e) {
    ob_end_clean();
    http_response_code(500);
    echo "Error exportando productos: " . htmlspecialchars($e->getMessage());
    exit;
}
?>
