<?php
ob_start();
require 'vendor/autoload.php';
include("conexion_mongo.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

$db = mongo();
$colUsuarios = $db->selectCollection("usuarios");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Usuarios");

$logo = new Drawing();
$logo->setName('Logo CERAMICENTRO');
$logo->setPath(__DIR__ . '/imagenes/logosinfondo.png');
$logo->setHeight(80);
$logo->setCoordinates('A1');
$logo->setWorksheet($sheet);

$sheet->mergeCells('B1:D2');
$sheet->setCellValue('B1', 'USUARIOS');
$sheet->getStyle('B1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('E53935');
$sheet->getStyle('B1')->getAlignment()->setHorizontal('center')->setVertical('center');

$encabezados = ['Nombre', 'Correo', 'Rol'];
$col = 'A';
foreach ($encabezados as $encabezado) {
    $sheet->setCellValue($col . '4', $encabezado);
    $col++;
}

$sheet->getStyle('A4:C4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A4:C4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E53935');

$usuarios = $colUsuarios->find([], ['sort' => ['nombre' => 1]]);

$fila = 5;
foreach ($usuarios as $u) {
    $sheet->setCellValue("A{$fila}", $u['nombre'] ?? '');
    $sheet->setCellValue("B{$fila}", $u['correo'] ?? '');
    $sheet->setCellValue("C{$fila}", $u['rol'] ?? '');
    $fila++;
}

$sheet->getStyle("A4:C" . ($fila - 1))
      ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

ob_end_clean();
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="usuarios_ceramicentro.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
?>
