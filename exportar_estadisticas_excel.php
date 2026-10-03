<?php
ob_start();
require 'vendor/autoload.php';
include("conexion_mongo.php");

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Chart\Chart;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;

$db = mongo();
$colPedidos  = $db->selectCollection("pedidos");
$colDetalle  = $db->selectCollection("pedido_detalle");
$colProductos = $db->selectCollection("productos");

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Estadísticas de Ventas");

$logo = new Drawing();
$logo->setName('Logo CERAMICENTRO');
$logo->setPath(__DIR__ . '/imagenes/logosinfondo.png');
$logo->setHeight(80);
$logo->setCoordinates('A1');
$logo->setWorksheet($sheet);

$sheet->mergeCells('B1:D2');
$sheet->setCellValue('B1', 'ESTADÍSTICAS DE VENTAS');
$sheet->getStyle('B1')->getFont()->setBold(true)->setSize(20)->getColor()->setRGB('e53935');
$sheet->getStyle('B1')->getAlignment()->setHorizontal('center')->setVertical('center');

$metricas = $colDetalle->aggregate([
    [
        '$lookup' => [
            'from' => 'pedidos',
            'localField' => 'pedido_id',
            'foreignField' => '_id',
            'as' => 'pedido'
        ]
    ],
    ['$unwind' => '$pedido'],
    [
        '$group' => [
            '_id' => null,
            'ventas' => ['$addToSet' => '$pedido_id'],
            'productos' => ['$sum' => '$cantidad'],
            'ganancias' => ['$sum' => '$subtotal']
        ]
    ]
])->toArray();

if (!empty($metricas)) {
    $m = $metricas[0];
    $totalVentas = count($m["ventas"]);
    $totalProductos = $m["productos"];
    $totalGanancias = $m["ganancias"];
} else {
    $totalVentas = 0;
    $totalProductos = 0;
    $totalGanancias = 0;
}

$sheet->setCellValue('A4', 'Estadística');
$sheet->setCellValue('B4', 'Valor');
$sheet->getStyle('A4:B4')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
$sheet->getStyle('A4:B4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('e53935');
$sheet->getStyle('A4:B4')->getAlignment()->setHorizontal('center');

$sheet->setCellValue('A5', 'Ventas Realizadas');
$sheet->setCellValue('B5', $totalVentas);
$sheet->setCellValue('A6', 'Productos Vendidos');
$sheet->setCellValue('B6', $totalProductos);
$sheet->setCellValue('A7', 'Ganancia Total');
$sheet->setCellValue('B7', $totalGanancias);

$sheet->setCellValue('A9', 'Top 5 Productos Más Vendidos');
$sheet->getStyle('A9')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('e53935');

$sheet->setCellValue("A10", "Producto");
$sheet->setCellValue("B10", "Cantidad");
$sheet->getStyle("A10:B10")->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");
$sheet->getStyle("A10:B10")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("e53935");
$sheet->getStyle("A10:B10")->getAlignment()->setHorizontal("center");

$top = $colDetalle->aggregate([
    [
        '$group' => [
            '_id' => '$producto_id',
            'total' => ['$sum' => '$cantidad']
        ]
    ],
    ['$sort' => ['total' => -1]],
    ['$limit' => 5],
    [
        '$lookup' => [
            'from' => 'productos',
            'localField' => '_id',
            'foreignField' => '_id',
            'as' => 'producto'
        ]
    ],
    ['$unwind' => '$producto']
])->toArray();

$fila = 11;
foreach ($top as $t) {
    $sheet->setCellValue("A{$fila}", $t["producto"]["nombre"]);
    $sheet->setCellValue("B{$fila}", $t["total"]);
    $fila++;
}
$ultimaFilaTop = $fila - 1;

$labelsTop = [new DataSeriesValues('String', "'Estadísticas de Ventas'!A11:A{$ultimaFilaTop}", null, 5)];
$valuesTop = [new DataSeriesValues('Number', "'Estadísticas de Ventas'!B11:B{$ultimaFilaTop}", null, 5)];

$seriesTop = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, count($valuesTop) - 1),
    [],
    $labelsTop,
    $valuesTop
);

$seriesTop->setPlotDirection(DataSeries::DIRECTION_COL);

$chartTop = new Chart(
    'Top5',
    new Title('Top 5 Productos Más Vendidos'),
    new Legend(),
    new PlotArea(null, [$seriesTop])
);

$chartTop->setTopLeftPosition("D10");
$chartTop->setBottomRightPosition("K25");
$sheet->addChart($chartTop);

$meses = [
    1=>"Enero",2=>"Febrero",3=>"Marzo",4=>"Abril",5=>"Mayo",6=>"Junio",
    7=>"Julio",8=>"Agosto",9=>"Septiembre",10=>"Octubre",11=>"Noviembre",12=>"Diciembre"
];

$inicioTabla = $fila + 2;
$sheet->setCellValue("A{$inicioTabla}", "Mes");
$sheet->setCellValue("B{$inicioTabla}", "Ganancia");

$sheet->getStyle("A{$inicioTabla}:B{$inicioTabla}")
    ->getFont()->setBold(true)->getColor()->setRGB("FFFFFF");

$sheet->getStyle("A{$inicioTabla}:B{$inicioTabla}")
    ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB("e53935");

$f = $inicioTabla + 1;

foreach ($meses as $num => $mesNombre) {

    $gananciaMes = $colDetalle->aggregate([
        [
            '$lookup' => [
                'from' => 'pedidos',
                'localField' => 'pedido_id',
                'foreignField' => '_id',
                'as' => 'pedido'
            ]
        ],
        ['$unwind' => '$pedido'],
        [
            '$match' => [
                '$expr' => [
                    '$eq' => [
                        ['$month' => '$pedido.fecha'], 
                        $num
                    ]
                ]
            ]
        ],
        [
            '$group' => [
                '_id' => null,
                'total' => ['$sum' => '$subtotal']
            ]
        ]
    ])->toArray();

    $sheet->setCellValue("A{$f}", $mesNombre);
    $sheet->setCellValue("B{$f}", $gananciaMes[0]["total"] ?? 0);

    $f++;
}
$ultimaFilaMes = $f - 1;

$labelsMes = [new DataSeriesValues('String', "'Estadísticas de Ventas'!A{$inicioTabla}:A{$ultimaFilaMes}", null, 12)];
$valuesMes = [new DataSeriesValues('Number', "'Estadísticas de Ventas'!B{$inicioTabla}:B{$ultimaFilaMes}", null, 12)];

$seriesMes = new DataSeries(
    DataSeries::TYPE_BARCHART,
    DataSeries::GROUPING_CLUSTERED,
    range(0, count($valuesMes) - 1),
    [],
    $labelsMes,
    $valuesMes
);

$seriesMes->setPlotDirection(DataSeries::DIRECTION_COL);

$chartMes = new Chart(
    'GananciasMensuales',
    new Title('Ganancias por Mes'),
    new Legend(),
    new PlotArea(null, [$seriesMes])
);

$chartMes->setTopLeftPosition("D27");
$chartMes->setBottomRightPosition("K45");
$sheet->addChart($chartMes);

ob_end_clean();

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="estadisticas_ceramicentro.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($spreadsheet);
$writer->setIncludeCharts(true);
$writer->save('php://output');

exit;
?>
