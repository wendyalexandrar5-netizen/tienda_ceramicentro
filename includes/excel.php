<?php
/**
 * Exportaciones a Excel con presentación uniforme (logo, título, fecha, encabezados en rojo).
 * Usa PhpSpreadsheet.
 */
require_once __DIR__ . '/bootstrap.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * Escribe en la hoja un encabezado + tabla. Devuelve la última fila usada.
 * @param array $columnas [['titulo' => 'Nombre', 'ancho' => 30, 'formato' => 'dinero'|'entero'|null], ...]
 * @param array $filas    filas con valores en el mismo orden de columnas
 */
function excel_tabla(Worksheet $hoja, string $titulo, array $columnas, array $filas, int $filaInicio = 1, string $subtitulo = ''): int
{
    $n = count($columnas);
    $ultimaCol = Coordinate::stringFromColumnIndex(max(2, $n));

    if ($filaInicio === 1) {
        $logo = CS_ROOT . '/imagenes/logosinfondo.png';
        if (is_file($logo)) {
            $d = new Drawing();
            $d->setName('Logo CERAMICENTRO');
            $d->setPath($logo);
            $d->setHeight(60);
            $d->setCoordinates('A1');
            $d->setWorksheet($hoja);
        }
        $hoja->mergeCells("B1:{$ultimaCol}1");
        $hoja->setCellValue('B1', $titulo);
        $hoja->getStyle('B1')->getFont()->setBold(true)->setSize(18)->getColor()->setRGB('C62828');
        $hoja->getStyle('B1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $hoja->getRowDimension(1)->setRowHeight(36);
        $hoja->mergeCells("B2:{$ultimaCol}2");
        $hoja->setCellValue('B2', ($subtitulo !== '' ? $subtitulo . ' · ' : '') . 'Generado: ' . date('d/m/Y h:i a') . ' · CERAMISHOP');
        $hoja->getStyle('B2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('666666');
        $hoja->getStyle('B2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $filaInicio = 4;
    } else {
        $hoja->setCellValue("A{$filaInicio}", $titulo);
        $hoja->getStyle("A{$filaInicio}")->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('C62828');
        $filaInicio++;
    }

    foreach ($columnas as $i => $c) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $hoja->setCellValue($col . $filaInicio, $c['titulo']);
        $hoja->getColumnDimension($col)->setWidth($c['ancho'] ?? 18);
    }
    $rangoEnc = "A{$filaInicio}:" . Coordinate::stringFromColumnIndex($n) . $filaInicio;
    $hoja->getStyle($rangoEnc)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
    $hoja->getStyle($rangoEnc)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('C62828');
    $hoja->getStyle($rangoEnc)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);

    $fila = $filaInicio;
    foreach ($filas as $valores) {
        $fila++;
        foreach (array_values($valores) as $i => $v) {
            $col = Coordinate::stringFromColumnIndex($i + 1);
            $hoja->setCellValueExplicit($col . $fila, $v, is_int($v) || is_float($v)
                ? \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC
                : \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
    }
    if (!$filas) {
        $fila++;
        $hoja->setCellValue("A{$fila}", 'Sin registros');
        $hoja->getStyle("A{$fila}")->getFont()->setItalic(true);
    }
    foreach ($columnas as $i => $c) {
        $col = Coordinate::stringFromColumnIndex($i + 1);
        $rango = $col . ($filaInicio + 1) . ':' . $col . max($filaInicio + 1, $fila);
        if (($c['formato'] ?? '') === 'dinero') {
            $hoja->getStyle($rango)->getNumberFormat()->setFormatCode('"$"#,##0');
        } elseif (($c['formato'] ?? '') === 'entero') {
            $hoja->getStyle($rango)->getNumberFormat()->setFormatCode('#,##0');
        }
        if (!empty($c['ajustar'])) {
            $hoja->getStyle($rango)->getAlignment()->setWrapText(true);
        }
    }
    $hoja->getStyle("A{$filaInicio}:" . Coordinate::stringFromColumnIndex($n) . $fila)
        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('DDDDDD');
    $hoja->getStyle("A" . ($filaInicio + 1) . ":" . Coordinate::stringFromColumnIndex($n) . $fila)->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
    if ($filaInicio === 4) {
        $hoja->freezePane('A5');
        $hoja->setAutoFilter("A{$filaInicio}:" . Coordinate::stringFromColumnIndex($n) . $fila);
    }
    return $fila;
}

/** Envía el libro al navegador como descarga .xlsx */
function excel_enviar(Spreadsheet $libro, string $nombreBase, bool $graficos = false): void
{
    $libro->getProperties()->setCreator('CERAMISHOP')->setCompany('CERAMICENTRO')->setTitle($nombreBase);
    $libro->setActiveSheetIndex(0);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $archivo = preg_replace('/[^a-z0-9_\-]+/i', '_', $nombreBase) . '_' . date('Y-m-d') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $archivo . '"');
    header('Cache-Control: private, no-store');
    $w = new Xlsx($libro);
    if ($graficos) {
        $w->setIncludeCharts(true);
    }
    $w->save('php://output');
    exit;
}

function excel_nuevo(string $nombreHoja): array
{
    $libro = new Spreadsheet();
    $hoja = $libro->getActiveSheet();
    $hoja->setTitle(mb_substr($nombreHoja, 0, 31));
    return [$libro, $hoja];
}
