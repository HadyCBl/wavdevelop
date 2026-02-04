<?php
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
session_start();
include '../../../src/funcphp/func_gen.php';
include '../funciones/func_ctb.php';
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';
date_default_timezone_set('America/Guatemala');
require '../../../vendor/autoload.php';
$hoy = date("Y-m-d");

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    return;
}
if ($inputs[0] > $inputs[1]) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}

$fechaini = strtotime($inputs[0]);
$fechafin = strtotime($inputs[1]);
$mesini = date("m", $fechaini);
$anioini = date("Y", $fechaini);
$mesfin = date("m", $fechafin);
$aniofin = date("Y", $fechafin);

if ($anioini != $aniofin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Las fechas tienen que ser del mismo año']);
    return;
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++ CUENTAS PARAMETRIZADAS PARA LAS CUENTAS DE PATRIMONIO +++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT * FROM ctb_parametros_cuentas WHERE id_tipo=?;";
$response = executequery($query, [3], ['i'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$parametros = $response[0];
$flag = ((count($parametros)) > 0) ? true : false;
if (!$flag) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay cuentas configuradas para el calculo']);
    return;
}

// $cuentasactivo = array_filter($parametros, function ($var) {
//     return $var['id_tipo'] == 1;
// });
// $cuentaspasivo = array_filter($parametros, function ($var) {
//     return $var['id_tipo'] == 2;
// });
// $cuentascapital = array_filter($parametros, function ($var) {
//     return $var['id_tipo'] == 3;
// });


/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++ CONSULTA LOS DATOS SEGUN LAS FECHAS DADAS SIN TOMAR EN CUENTA LAS PARTIDAS DE APERTURA Y CIERRE +++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$valparams = [];
$typparams = [];
$key = 0;
$condi = "";
//AGENCIA
if ($radios[0] == "anyofi") {
    $condi = $condi . " AND id_agencia2=?";
    $valparams[$key] = $selects[0];
    $typparams[$key] = 'i';
    $key++;
}
$titlereport = " DEL " . date("d-m-Y", strtotime($inputs[0])) . " AL " . date("d-m-Y", strtotime($inputs[1]));

$valparams[$key] = $inputs[0];
$typparams[$key] = 's';
$valparams[$key + 1] = $inputs[1];
$typparams[$key + 1] = 's';

$query = "SELECT ccodcta,id_ctb_nomenclatura,cdescrip,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber 
            from ctb_diario_mov 
            WHERE estado=1 " . $condi . " AND (id_tipopol != 9 AND id_tipopol != 13) AND (feccnt BETWEEN ? AND ?) 
            AND SUBSTR(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipo=3) 
            GROUP BY ccodcta ORDER BY ccodcta";

$response = executequery($query, $valparams, $typparams, $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$ctbmovdata = $response[0];
$haydata = ((count($ctbmovdata)) > 0) ? true : false;
//COMPROBAR SI HAY REGISTROS (CONSULTAR SI ES NECESARIO O NO)
// if (!$flag) {
//     echo json_encode(['status' => 0, 'mensaje' => 'No hay datos en la fecha indicada']);
//     return;
// }
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++ CONSULTA PARTIDA DE APERTURA INGRESADA EN EL RANGO DE FECHA DEL BALANCE+++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

$inianio = $anioini . '-01-01';
$finanio = $anioini . '-01-30';
$dated = strtotime($inputs[0]);
$lastdate = strtotime(date("Y-m-t", $dated));
$lastday = date("d", $lastdate);

$qparapr = "SELECT ccodcta,id_ctb_nomenclatura,cdescrip,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber 
            from ctb_diario_mov
            WHERE estado=1 " . $condi . " AND id_tipopol = 9 AND SUBSTR(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipo=3) AND feccnt BETWEEN ? AND ? 
            GROUP BY ccodcta ORDER BY ccodcta";

$response = executequery($qparapr, $valparams, $typparams, $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$apertura = $response[0];
$flag = ((count($apertura)) > 0) ? true : false;
if (!$flag) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay partida de apertura']);
    return;
};

// $querys = mysqli_query($conexion, $qparapr);
// $apertura[] = [];
// $j = 0;
// while ($fil = mysqli_fetch_array($querys)) {
//     $apertura[$j] = $fil;
//     $j++;
// }
// //COMPROBAR SI HAY PARTIDA DE APERTURA
// if ($j == 0) {
//     echo json_encode(['status' => 0, 'mensaje' => 'No hay partida de apertura']);
//     return;
// }
/* echo json_encode(['status' => 0, 'mensaje' => $salinidata]);
return;  */

/*  +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++ CONSULTA DE REGISTROS ANTES DE LA FECHA QUE SE INGRESO SIN LA PARTIDA DE APERTURA +++++++
    +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

// $inianio = $anioini . '-01-01';
// $finanio = $anioini . '-01-30';
$dated = strtotime($inputs[0]);
$lastdate = strtotime(date("Y-m-t", $dated));
$lastday = date("d", $lastdate);

$valparams[$key] = $inianio;
$typparams[$key] = 's';
$valparams[$key + 1] = $inputs[0];
$typparams[$key + 1] = 's';

$query = "SELECT ccodcta,id_ctb_nomenclatura,cdescrip,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber 
            from ctb_diario_mov 
            WHERE estado=1 " . $condi . " AND id_tipopol != 9 AND id_tipopol != 13 
            AND SUBSTR(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas 
            WHERE id_tipo=3) AND (feccnt >=? AND feccnt < ?) 
            GROUP BY ccodcta ORDER BY ccodcta";
$response = executequery($query, $valparams, $typparams, $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$salinidata = $response[0];
$flag = ((count($salinidata)) > 0) ? true : false;
$hayanteriores = $flag;
$hayanteriores = false; //COMENTAR LA LINEA SI SE NECESITE TRAER LOS DATOS DE PARTIDAS ANTERIORES


/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++ CONSULTA DE TODAS LAS CUENTAS DE PATRIMONIO (5) ++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT id,ccodcta,cdescrip from ctb_nomenclatura 
        WHERE estado=? AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipo=3) 
        ORDER BY ccodcta";

$response = executequery($query, [1], ['i'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$nomenclatura = $response[0];
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++ INFORMACION DE LA INSTITUCION Y AGENCIA  +++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query = "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?";

$response = executequery($query, [$_SESSION['id_agencia']], ['i'], $conexion);
if (!$response[1]) {
    echo json_encode(['status' => 0, 'mensaje' => $response[0]]);
    return;
}
$info = $response[0];
$flag = ((count($info)) > 0) ? true : false;
if (!$flag) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}
//TIPO DE ARCHIVO A IMPRIMIR
switch ($tipo) {
    case 'xlsx';
        printxls($ctbmovdata, $titlereport, $apertura, $salinidata, $info, $hayanteriores, $haydata, $nomenclatura);
        break;
    case 'pdf':
        printpdf($ctbmovdata, $titlereport, $apertura, $salinidata, $info, $hayanteriores, $haydata, $nomenclatura);
        break;
}

//funcion para generar pdf
function printpdf($registro, $titlereport, $apertura, $salinidata, $info, $hayanteriores, $haydata, $cuentas)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
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
        public $datos;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos)
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
            $this->datos = $datos;
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
            $this->Cell(0, 5, 'ESTADO DE CAMBIO EN EL PATRIMONIO' . $this->datos, 0, 1, 'C', true);
            $this->Cell(0, 5, '(CIFRAS EN QUETZALES)', 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 32;

            $this->Cell($ancho_linea * 2, 5, 'DESCRIPCION', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'SALDO INICIAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'AUMENTOS', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'DISMINUCIONES', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'SALDO FINAL', 'B', 1, 'R');
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $titlereport);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4;
    $ancho_linea2 = 32;
    $pdf->SetFont($fuente, 'B', 11);
    $totalini = 0;
    $totalaumento = 0;
    $totaldismin = 0;
    $totalsaldofin = 0;
    //--------------------INICIO
    $pdf->CellFit(0, $tamanio_linea, 'PATRIMONIO', '', 1, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $f = 0;
    while ($f < count($cuentas)) {
        $id = $cuentas[$f]["id"];
        $cuenta = $cuentas[$f]["ccodcta"];
        $nombre = $cuentas[$f]["cdescrip"];
        $nivel = strlen($cuenta);

        //CALCULO1
        $salapertura = array_sum(array_column(calculo($apertura, $cuenta, $nivel), 'saldo'));
        $salanterior = ($hayanteriores) ? array_sum(array_column(calculo($salinidata, $cuenta, $nivel), 'saldo')) : 0;
        $saldo_ini = ($salapertura)  + $salanterior;
        $saldo_ini = abs($saldo_ini);
        $aumento = ($haydata) ? array_sum(array_column(calculo($registro, $cuenta, $nivel), 'haber')) : 0;
        $disminucion = ($haydata) ? array_sum(array_column(calculo($registro, $cuenta, $nivel), 'debe')) : 0;
        $saldofinal = $saldo_ini + $aumento - $disminucion;

        //CALCULO DE VERIFICACION
        $salapertura2 = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
        $salanterior2 = ($hayanteriores) ? array_sum(array_column(calculo2($salinidata, $cuenta), 'saldo')) : 0;
        $saldo_ini2 = ($salapertura2)  + $salanterior2;
        $saldo_ini2 = abs($saldo_ini2);
        $aumento2 = ($haydata) ? array_sum(array_column(calculo2($registro, $cuenta), 'haber')) : 0;
        $disminucion2 = ($haydata) ? array_sum(array_column(calculo2($registro, $cuenta), 'debe')) : 0;

        if ($saldo_ini != 0 || $aumento != 0 || $disminucion != 0 || $saldofinal != 0) {
            if ($nivel == 2 || $nivel == 3) {
                $pdf->Ln(2);
                $pdf->SetFont($fuente, 'B', 10);
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, Utf8::decode($nombre), '', 1, 'L', 0, '', 1, 0);
                $pdf->SetFont($fuente, '', 9);
            } else {
                if ($saldo_ini == $saldo_ini2 && $aumento == $aumento2 && $disminucion == $disminucion2) {
                    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, Utf8::decode($nombre), '', 0, 'L', 0, '', 1, 0);
                    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($saldo_ini, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($aumento, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($disminucion, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($saldofinal, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);

                    $totalini += $saldo_ini;
                    $totalaumento += $aumento;
                    $totaldismin += $disminucion;
                    $totalsaldofin += $saldofinal;
                }
            }
        }
        $f++;
    }



    $pdf->Ln(4);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell($ancho_linea2 * 2, $tamanio_linea, 'TOTAL GENERAL: ', '', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($totalini, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($totalaumento, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($totaldismin, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2, $tamanio_linea + 2, number_format($totalsaldofin, 2, '.', ','), 'BT', 1, 'R');

    $pdf->Ln(10);

    $pdf->SetFont($fuente, '', 9);
    $pdf->MultiCell(0, 4, strtoupper(Utf8::decode('El infrascrito Perito Contador, Maximiliano Calmo López, Autorizado por la Superintendencia de Administración Tributario (SAT) bajo el Número de Identificación Tributaria 73476773 CERTIFICA: el presente Estado Patrimonial practicado '.$titlereport.', por cierre del ejercicio fiscal tal como aparece en los datos expuestos en la presente.')));

    $pdf->Ln(20);
    $pdf->SetXY(10, $pdf->GetY());
    $pdf->CellFit(90, $tamanio_linea, '_________________________________', '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(90, $tamanio_linea, '_________________________________', '', 1, 'C', 0, '', 1, 0);
    $pdf->SetXY(10, $pdf->GetY());
    $pdf->CellFit(90, $tamanio_linea, Utf8::decode('Maximiliano Calmo López'), '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(90, $tamanio_linea, Utf8::decode('Efraín Baltazar Francisco'), '', 1, 'C', 0, '', 1, 0);
    $pdf->SetXY(10, $pdf->GetY());
    $pdf->CellFit(90, $tamanio_linea, 'PERITO CONTADOR', '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(90, $tamanio_linea, 'PRESIDENTE Y REP. LEGAL', '', 1, 'C', 0, '', 1, 0);

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
// function calculo($data, $cuenta, $nivel)
// {
//     return (array_filter($data, function ($var) use ($cuenta, $nivel) {
//         return (substr($var['ccodcta'], 0, $nivel)  == $cuenta);
//     }));
// }
// function calculo2($data, $cuenta)
// {
//     return (array_filter($data, function ($var) use ($cuenta) {
//         return ($var['ccodcta']  == $cuenta);
//     }));
// }
//funcion para generar archivo excel
function printxls($registro, $titlereport, $apertura, $salinidata, $info, $hayanteriores, $haydata, $cuentas)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Estado_patrimonio");
    $activa->getColumnDimension("A")->setWidth(50);
    $activa->getColumnDimension("B")->setWidth(25);
    $activa->getColumnDimension("C")->setWidth(25);
    $activa->getColumnDimension("D")->setWidth(25);
    $activa->getColumnDimension("E")->setWidth(25);

    $activa->setCellValue('A1', 'DESCRIPCION');
    $activa->setCellValue('B1', 'SALDO INICIAL');
    $activa->setCellValue('C1', 'AUMENTO');
    $activa->setCellValue('D1', 'DISMINUCION');
    $activa->setCellValue('E1', 'SALDO FINAL');
    $totalini = 0;
    $totalaumento = 0;
    $totaldismin = 0;
    $totalsaldofin = 0;
    $i = 2;
    $f = 0;
    while ($f < count($cuentas)) {
        $id = $cuentas[$f]["id"];
        $cuenta = $cuentas[$f]["ccodcta"];
        $nombre = $cuentas[$f]["cdescrip"];
        $nivel = strlen($cuenta);

        //CALCULO1
        $salapertura = array_sum(array_column(calculo($apertura, $cuenta, $nivel), 'saldo'));
        $salanterior = ($hayanteriores) ? array_sum(array_column(calculo($salinidata, $cuenta, $nivel), 'saldo')) : 0;
        $saldo_ini = ($salapertura)  + $salanterior;
        $saldo_ini = abs($saldo_ini);
        $aumento = ($haydata) ? array_sum(array_column(calculo($registro, $cuenta, $nivel), 'haber')) : 0;
        $disminucion = ($haydata) ? array_sum(array_column(calculo($registro, $cuenta, $nivel), 'debe')) : 0;
        $saldofinal = $saldo_ini + $aumento - $disminucion;

        //CALCULO DE VERIFICACION
        $salapertura2 = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
        $salanterior2 = ($hayanteriores) ? array_sum(array_column(calculo2($salinidata, $cuenta), 'saldo')) : 0;
        $saldo_ini2 = ($salapertura2)  + $salanterior2;
        $saldo_ini2 = abs($saldo_ini2);
        $aumento2 = ($haydata) ? array_sum(array_column(calculo2($registro, $cuenta), 'haber')) : 0;
        $disminucion2 = ($haydata) ? array_sum(array_column(calculo2($registro, $cuenta), 'debe')) : 0;

        if ($saldo_ini != 0 || $aumento != 0 || $disminucion != 0 || $saldofinal != 0) {
            if ($nivel == 2 || $nivel == 3) {
                $i++;
                $activa->setCellValue('A' . ($i), $nombre);
                $i++;
            } else {
                if ($saldo_ini == $saldo_ini2 && $aumento == $aumento2 && $disminucion == $disminucion2) {
                    $activa->setCellValue('A' . ($i), $nombre);
                    $activa->setCellValue('B' . ($i), $saldo_ini);
                    $activa->setCellValue('C' . ($i), $aumento);
                    $activa->setCellValue('D' . ($i), $disminucion);
                    $activa->setCellValueExplicit('E' . ($i), '=SUM(B' . $i . '+C' . $i . '-D' . $i . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

                    $totalini += $saldo_ini;
                    $totalaumento += $aumento;
                    $totaldismin += $disminucion;
                    $totalsaldofin += $saldofinal;
                    $i++;
                }
            }
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
        'namefile' => "Estado patrimonial",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
