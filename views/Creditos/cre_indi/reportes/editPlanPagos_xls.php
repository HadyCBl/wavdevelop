<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
include '../../../../src/funcphp/func_gen.php';
require '../../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$cod_cuenta = isset($_GET['cod_cuenta']) ? $_GET['cod_cuenta'] : '';

if (empty($cod_cuenta)) {
    die("El parámetro 'cod_cuenta' es necesario.");
}

// Crear archivo Excel
$excel = new Spreadsheet();
$activa = $excel->getActiveSheet();
$activa->setTitle("Plan de pagos");

// Estilos de encabezados
$styleArray = [
    'font' => [
        'bold' => true,
        'color' => ['argb' => Color::COLOR_WHITE],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical' => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color' => ['argb' => '000000'],
        ],
    ],
    'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => ['argb' => '4F81BD'],
    ],
];

// Definir ancho de columnas
$activa->getColumnDimension("A")->setWidth(20); // ID
$activa->getColumnDimension("B")->setWidth(20); // Fecha
$activa->getColumnDimension("C")->setWidth(20); // Estado
$activa->getColumnDimension("D")->setWidth(20); // No. Cuota
$activa->getColumnDimension("E")->setWidth(20); // Capital
$activa->getColumnDimension("F")->setWidth(20); // Interes
$activa->getColumnDimension("G")->setWidth(20); // Otros Pagos
$activa->getColumnDimension("H")->setWidth(20); // Saldo Capital
$activa->getColumnDimension("I")->setWidth(30); // Capital Desembolsado
$activa->getColumnDimension("J")->setVisible(false);

// Datos generales en la columna K
$activa->getColumnDimension("K")->setWidth(40); // Nombre, Fecha de Nacimiento y DPI

// Aplicar estilos de encabezados
$activa->getStyle('A1:K1')->applyFromArray($styleArray);

// Encabezados de plan de pagos
$activa->setCellValue('A1', 'ID');
$activa->setCellValue('B1', 'Fecha');
$activa->setCellValue('C1', 'Estado');
$activa->setCellValue('D1', 'No. Cuota');
$activa->setCellValue('E1', 'Capital');
$activa->setCellValue('F1', 'Interes');
$activa->setCellValue('G1', 'Otros Pagos');
$activa->setCellValue('H1', 'Saldo Capital');
$activa->setCellValue('I1', 'Capital Desembolsado');
$activa->setCellValue('K1', 'Datos personales');

// Modificar consulta SQL para incluir datos personales
$query = "SELECT 
        pagos.Id_ppg AS id, 
        pagos.dfecven AS fecha, 
        pagos.Cestado AS estado, 
        pagos.cnrocuo AS nro_cuota, 
        pagos.ncapita AS capital, 
        pagos.nintere AS interes, 
        pagos.OtrosPagosPag AS otros_pagos, 
        pagos.SaldoCapital AS saldo_capital, 
        credi.NCapDes AS descripcion,
        tc.compl_name AS nombre,
        pagos.CCODCTA AS cuenta ,
        tc.date_birth AS fecha_nacimiento,
        tc.no_identifica AS DPI
    FROM Cre_ppg AS pagos 
    INNER JOIN cremcre_meta AS credi ON pagos.ccodcta = credi.CCODCTA 
    INNER JOIN tb_cliente tc ON tc.idcod_cliente = credi.CodCli
    WHERE credi.Cestado = 'F' AND credi.ccodcta = '$cod_cuenta'
";

$result = mysqli_query($conexion, $query);

if ($result && mysqli_num_rows($result) > 0) {
    $fila = 2; // Comenzar en la fila 2
    $row = mysqli_fetch_assoc($result);

    // Mostrar los datos generales solo una vez en la columna K
    $activa->setCellValue('K5', 'Nombre: ' . $row['nombre']);
    $activa->setCellValue('K7', 'Fecha de Nacimiento: ' . $row['fecha_nacimiento']);
    $activa->setCellValue('K9', 'DPI: ' . $row['DPI']);
    $activa->setCellValue('K11', 'Cuenta: ' . $row['cuenta']);
    $contador = 1; 
    do {
        // Llenar el plan de pagos con los datos de cada fila

        if ($row['estado'] == 'P') {
            // Aplicar color de fondo #82E0AA si es 'P'
            $activa->getStyle('C' . $fila)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => '82E0AA'],
                ],
            ]);
        } else {
            // Aplicar fondo blanco en caso contrario
            $activa->getStyle('C' . $fila)->applyFromArray([
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFFFFF'],
                ],
            ]);
        }
        


        $activa->setCellValue('A' . $fila, $contador);
        $activa->setCellValue('B' . $fila, $row['fecha']);
        $activa->setCellValue('C' . $fila, $row['estado']);
        $activa->setCellValue('D' . $fila, $row['nro_cuota']);
        $activa->setCellValue('E' . $fila, $row['capital']);
        $activa->setCellValue('F' . $fila, $row['interes']);
        $activa->setCellValue('G' . $fila, $row['otros_pagos']);
        $activa->setCellValue('H' . $fila, $row['saldo_capital']);
        $activa->setCellValue('I' . $fila, $row['descripcion']);
        $contador ++; 
        // Bordes para cada fila de plan de pagos
        $activa->getStyle('A' . $fila . ':I' . $fila)->applyFromArray([
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => '000000'],
                ],
            ],
        ]);

        // Formato de moneda para columnas de capital, interes, otros pagos y saldo capital
        $activa->getStyle('E' . $fila . ':H' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
        $fila++;
    } while ($row = mysqli_fetch_assoc($result));
} else {
    die("No se encontraron resultados para el código de cuenta: " . $cod_cuenta);
}

// Generar y descargar el archivo Excel
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment;filename="plan_de_pagos.xlsx"');
header('Cache-Control: max-age=0');

$writer = new Xlsx($excel);
$writer->save('php://output');
exit;

?>
