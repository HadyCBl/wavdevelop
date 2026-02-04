<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
session_start();
include '../../../src/funcphp/func_gen.php';
include '../funciones/func_ctb.php';
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';

require '../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy = date("Y-m-d");

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ INFO DE LA INSTITUCION +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$query = "SELECT * FROM " . $db_name_general . ".info_coperativa ins INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?";
$response = executequery($query, [$_SESSION['id_agencia']], ['i'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$info = $response[0];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++ INICIO DE VALIDACIONES ++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d') || !validateDate($inputs[2], 'Y-m-d') || !validateDate($inputs[3], 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    return;
}
if ($inputs[0] > $inputs[1]) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido para el Balance 1']);
    return;
}
if ($inputs[2] > $inputs[3]) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido para el Balance 2']);
    return;
}
if ($inputs[0] == $inputs[2] && $inputs[1] == $inputs[3]) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha de los dos balances son iguales']);
    return;
}
if ($inputs[1] > $hoy || $inputs[3] > $hoy) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha final de balance no puede ser mayor a la de hoy']);
    return;
}

$anioini1 = date("Y", strtotime($inputs[0]));
$aniofin1 = date("Y", strtotime($inputs[1]));
$mesini1 = date("m", strtotime($inputs[0]));
$mesfin1 = date("m",  strtotime($inputs[1]));
if ($anioini1 != $aniofin1) {
    echo json_encode(['status' => 0, 'mensaje' => 'El rango de fechas del Primer Balance tienen que ser del mismo año']);
    return;
}

$anioini2 = date("Y", strtotime($inputs[2]));
$aniofin2 = date("Y", strtotime($inputs[3]));
$mesini2 = date("m", strtotime($inputs[2]));
$mesfin2 = date("m",  strtotime($inputs[3]));
if ($anioini2 != $aniofin2) {
    echo json_encode(['status' => 0, 'mensaje' => 'El rango de fechas del Segundo Balance tienen que ser del mismo año']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++ CONSULTA FINAL PRIMER BALANCE+++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT ccodcta,id_ctb_nomenclatura,SUM(debe) sumdeb, SUM(haber) sumhab from ctb_diario_mov 
            WHERE estado=1 AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE  (id_tipo>=1 AND id_tipo<=3) || id_tipo=6) 
            AND feccnt BETWEEN ? AND ? GROUP BY ccodcta ORDER BY ccodcta";
$response = executequery($query, [$inputs[0], $inputs[1]], ['s', 's'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$balance1 = $response[0];
if (count($balance1) == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos para el primer Balance']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++ CONSULTA FINAL SEGUNDO BALANCE +++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT ccodcta,id_ctb_nomenclatura,SUM(debe) sumdeb, SUM(haber) sumhab from ctb_diario_mov 
            WHERE estado=1 AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE  (id_tipo>=1 AND id_tipo<=3) || id_tipo=6) 
            AND feccnt BETWEEN ? AND ? GROUP BY ccodcta ORDER BY ccodcta";
$response = executequery($query, [$inputs[2], $inputs[3]], ['s', 's'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$balance2 = $response[0];
if (count($balance2) == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos para el segundo Balance']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++ CONSULTA CUENTAS CONTABLES ++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT id,ccodcta,cdescrip from ctb_nomenclatura 
            WHERE estado=? AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE  (id_tipo>=1 AND id_tipo<=3) || id_tipo=6) ORDER BY ccodcta";
$response = executequery($query, [1], ['i'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$cuentas = $response[0];
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++ CUENTAS PARAMETRIZADAS PAL BALANCE ++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT * FROM ctb_parametros_cuentas WHERE  (id_tipo>=? AND id_tipo<=3) || id_tipo=6;";
$response = executequery($query, [1], ['i'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$parametros = $response[0];
$flag = ((count($parametros)) > 0) ? true : false;
if (!$flag) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay cuentas configuradas para el calculo del balance']);
    return;
}

$cuentasactivo = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 1;
});
$reguladorasactivo = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 6;
});
$cuentaspasivo = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 2;
});
$cuentascapital = array_filter($parametros, function ($var) {
    return $var['id_tipo'] == 3;
});

//TIPO DE ARCHIVO A IMPRIMIR
switch ($tipo) {
    case 'xlsx';
        printxls($balance1, $balance2, $info, $cuentas, $inputs, $parametros, $cuentasactivo, $cuentaspasivo, $cuentascapital);
        break;
    case 'pdf':
        printpdf($balance1, $balance2, $info, $cuentas, $inputs, $parametros, $cuentasactivo, $cuentaspasivo, $cuentascapital);
        break;
}

//funcion para generar pdf
function printpdf($balance1, $balance2, $info, $cuentas, $inputs, $parametros, $cuentasactivo, $cuentaspasivo, $cuentascapital)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        //atributos de la clase
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $rango;
        public $tipocuenta;
        public $saldoant;
        public $inputs;
        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $inputs)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->inputs = $inputs;
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 13, 33);

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 9);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(10);

            $this->SetFont($fuente, 'B', 10);
            //TITULO DE REPORTE
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'BALANCE COMPARATIVO ', 0, 1, 'C', true);
            $this->Cell(0, 5, '(CIFRAS EN QUETZALES)', 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 39;

            $this->Cell($ancho_linea, 5, 'CUENTAS', '', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, 'DESCRIPCION', '', 0, 'L');
            $this->Cell($ancho_linea, 5, date("d-m", strtotime($this->inputs[0])) . ' <=> ' . date("d-m", strtotime($this->inputs[1])), '', 0, 'C');
            $this->Cell($ancho_linea, 5, date("d-m", strtotime($this->inputs[2])) . ' <=> ' . date("d-m", strtotime($this->inputs[3])), '', 0, 'C');
            $this->Cell($ancho_linea, 5, 'RESULTADO', '', 0, 'R');
            $this->Cell($ancho_linea, 5, 'VARIACION', '', 1, 'R');

            $this->Cell($ancho_linea * 3, 5, ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, '(' . date("Y", strtotime($this->inputs[0])) . ')', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, '(' . date("Y", strtotime($this->inputs[2])) . ')', 'B', 0, 'C');
            $this->Cell($ancho_linea * 2, 5, ' ', 'B', 1, 'R');
            $this->Ln(2);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo 
            // $this->Image($this->pathlogo, 175, 279, 28);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $inputs);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4;
    $ancho_linea2 = 39;
    $pdf->SetFont($fuente, '', 9);

    $f = 0;
    while ($f < count($cuentas)) {
        $idcuenta = $cuentas[$f]["id"];
        $cuenta = $cuentas[$f]["ccodcta"];
        $nombre = $cuentas[$f]["cdescrip"];
        $nivel = strlen($cuenta);
        $clase = substr($cuenta, 0, 1);

        $style = ($nivel == 1) ? 'B' : '';
        $opcion = ($nivel == 1) ? 1 : 2;
        $account = ($nivel == 1) ? $cuenta : $idcuenta;
        $debe1 = opcalcular($balance1, $account, 'sumdeb', $nivel, $opcion);
        $haber1 = opcalcular($balance1, $account, 'sumhab', $nivel, $opcion);

        // $pdf->CellFit($ancho_linea2, $tamanio_linea, $debe1, '', 0, 'R', 0, '', 1, 0);
        // $pdf->Ln(3);

        $result = array_search($clase, array_column($cuentasactivo, 'clase'));
        if ($result !== false) {
            $saldo1 = $debe1 - $haber1;
        } else {
            $saldo1 =  $haber1 - $debe1;
        }

        //$saldo1 = ($clase <= 2) ? $debe1 - $haber1 : $haber1 - $debe1;
        // $saldo1 = $debe1 - $haber1;

        $debe2 = opcalcular($balance2, $account, 'sumdeb', $nivel, $opcion);
        $haber2 = opcalcular($balance2, $account, 'sumhab', $nivel, $opcion);

        $result = array_search($clase, array_column($cuentasactivo, 'clase'));
        if ($result !== false) {
            $saldo2 = $debe2 - $haber2;
        } else {
            $saldo2 =  $haber2 - $debe2;
        }
        // $saldo2 = $debe2 - $haber2;
        //$saldo2 = ($clase <= 2) ? $debe2 - $haber2 : $haber2 - $debe2;
        $resultado = $saldo2 - $saldo1;
        $variacion = ($saldo1 == 0) ? $resultado : ($resultado * 100 / $saldo1);

        $pdf->SetFont($fuente, $style, 9);
        if ($saldo1 != 0 || $saldo2 != 0 || $nivel == 1) {
            if ($nivel == 1) $pdf->Ln(3);
            $pdf->CellFit($ancho_linea2, $tamanio_linea, $cuenta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, Utf8::decode($nombre), '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($saldo1, 2), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($saldo2, 2), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($resultado, 2), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($variacion, 2), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit(4, $tamanio_linea, ($saldo1 == 0) ? ' ' : '%', '', 0, 'R', 0, '', 1, 0);
            $pdf->Ln(4);
        }

        $pdf->SetFont($fuente, '', 8);
        $f++;
    }

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Balance de Comprobacion",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
// function calculo($data, $cuenta, $nivel, $column)
// {
//     return array_sum(array_column(array_filter($data, function ($var) use ($cuenta, $nivel) {
//         return (substr($var['ccodcta'], 0, $nivel)  == $cuenta);
//     }), $column));
// }
// function calculo2($data, $cuenta, $column)
// {
//     $index = array_search($cuenta, array_column($data, 'id_ctb_nomenclatura'));
//     return ($index != false) ? ($data[$index][$column]) : 0;
// }
function opcalcular($data, $cuenta, $column, $nivel, $opcion)
{
    if ($opcion == 1) return calculo3($data, $cuenta, $nivel, $column);
    if ($opcion == 2) return calculo4($data, $cuenta, $column);
}


//funcion para generar archivo excel
function printxls($balance1, $balance2, $info, $cuentas,$inputs, $parametros, $cuentasactivo, $cuentaspasivo, $cuentascapital)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Balance_Comparativo");
    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(65);
    $activa->getColumnDimension("C")->setWidth(20);
    $activa->getColumnDimension("D")->setWidth(20);
    $activa->getColumnDimension("E")->setWidth(20);
    $activa->getColumnDimension("F")->setWidth(20);

    $activa->setCellValue('A1', 'CUENTA');
    $activa->setCellValue('B1', 'NOMBRE CUENTA');
    $activa->setCellValue('C1', 'BALANCE 1');
    $activa->setCellValue('D1', 'BALANCE 2');
    $activa->setCellValue('E1', 'RESULTADO');
    $activa->setCellValue('F1', '% VARIACION');
    $activa->getStyle('A1:F1')->getFont()->setBold(true);

    $f = 0;
    $i = 2;
    while ($f < count($cuentas)) {
        $idcuenta = $cuentas[$f]["id"];
        $cuenta = trim($cuentas[$f]["ccodcta"]);
        $nombre = $cuentas[$f]["cdescrip"];
        $nivel = strlen($cuenta);
        $clase = substr($cuenta, 0, 1);

        $style = ($nivel == 1) ? 'B' : '';
        $opcion = ($nivel == 1) ? 1 : 2;
        $account = ($nivel == 1) ? $cuenta : $idcuenta;

        $debe1 = opcalcular($balance1, $account, 'sumdeb', $nivel, $opcion);
        $haber1 = opcalcular($balance1, $account, 'sumhab', $nivel, $opcion);

        $debe2 = opcalcular($balance2, $account, 'sumdeb', $nivel, $opcion);
        $haber2 = opcalcular($balance2, $account, 'sumhab', $nivel, $opcion);

        $result = array_search($clase, array_column($cuentasactivo, 'clase'));
        if ($result!==false) {
            $saldo1 = $debe1 - $haber1;
            $saldo2 = $debe2 - $haber2;
        } else {
            $saldo1 =  $haber1 - $debe1;
            $saldo2 =  $haber2 - $debe2;
        }

        $resultado = $saldo2 - $saldo1;
        $variacion = ($saldo1 == 0) ? $resultado : ($resultado * 100 / $saldo1);

        if ($saldo1 != 0 || $saldo2 != 0 || $nivel == 1) {
            if ($nivel == 1) $i++;
            $activa->setCellValueExplicit('A' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('B' . $i, $nombre);
            $activa->setCellValue('C' . $i, round($saldo1, 2));
            $activa->setCellValue('D' . $i, round($saldo2, 2));
            $activa->setCellValue('E' . $i, round($resultado, 2));
            $activa->setCellValue('F' . $i, round($variacion, 2));
            if ($nivel == 1) $activa->getStyle('A' . $i . ':F' . $i)->getFont()->setBold(true);
            $i++;
        }

        $f++;
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Balance Comparativo",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
