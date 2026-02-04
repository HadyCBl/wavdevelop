<?php

/**
 * Este archivo genera un estado de resultados segun el tipo solicitado.
 * 
 * - Verifica si la solicitud es de tipo GET y redirige a una página 404.
 * - Inicia una sesión y verifica si la sesión ha expirado.
 * - Incluye archivos de configuración y funciones necesarias.
 * - Configura la zona horaria a 'America/Guatemala'.
 * - Utiliza las bibliotecas FPDF y PhpSpreadsheet para la generación de documentos.
 * 
 * Dependencias:
 * - Configuración: config.php, database.php
 * - Funciones: func_gen.php, func_ctb.php
 * - Librerías: FPDF, PhpSpreadsheet
 * 
 * Variables:
 * - $idusuario: ID del usuario obtenido de la sesión.
 * - $hoy2: Fecha y hora actual en formato "Y-m-d H:i:s".
 * - $hoy: Fecha actual en formato "Y-m-d".
 */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
include __DIR__ . '/../funciones/func_ctb.php';

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use Micro\Helpers\Log;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

//[`finicio`,`ffin`],[`codofi`],[`ragencia`]
list($fechaini, $fechafin) = $inputs;
list($codofi) = $selects;
list($ragencia) = $radios;

//FECHA
if (!validateDate($fechaini, 'Y-m-d') || !validateDate($fechafin, 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    return;
}
if ($fechaini > $fechafin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}

// $fechaini = strtotime($fechaini);
// $fechafin = strtotime($fechafin);
// $mesini = date("m", $fechaini);
// $anioini = date("Y", $fechaini);
// $mesfin = date("m", $fechafin);
// $aniofin = date("Y", $fechafin);

if ((date("Y", strtotime($fechaini))) != (date("Y", strtotime($fechafin)))) {
    echo json_encode(['status' => 0, 'mensaje' => 'Las fechas tienen que ser del mismo año']);
    return;
}

$titlereport = " DEL " . setdatefrench($fechaini) . " AL " . setdatefrench($fechafin);

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++ CONSULTANDO EN LA BD XDXD ++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/**
 * POR SI MISMO
 */

$showmensaje = false;
try {
    $database->openConnection();
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++ CONSULTA LOS DATOS SEGUN LAS FECHAS DADAS SIN TOMAR EN CUENTA LAS PARTIDAS DE APERTURA Y CIERRE +++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $condi =  ($ragencia == "anyofi") ? " AND id_agencia2=? " : "";
    $parameters = [$fechaini, $fechafin];

    if ($ragencia == "anyofi") {
        $parameters[] = $codofi;
    }
    $query = "SELECT ccodcta,id_ctb_nomenclatura,cdescrip,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber 
                    from ctb_diario_mov 
                    WHERE estado=1 AND (id_tipopol != 9 AND id_tipopol != 13) AND (feccnt BETWEEN ? AND ?) $condi
                    AND SUBSTR(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipo>=1 AND id_tipo<=5) 
                    GROUP BY ccodcta ORDER BY ccodcta";

    $ctbmovdata = $database->getAllResults($query, $parameters);
    if (empty($ctbmovdata)) {
        $showmensaje = true;
        throw new Exception("No hay datos en la fecha indicada");
    }
    // $haydata = ((count($ctbmovdata)) > 0) ? true : false;
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++ CONSULTA PARTIDA DE APERTURA INGRESADA EN LA FECHA DEL BALANCE +++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $query = "SELECT ccodcta,id_ctb_nomenclatura,cdescrip,SUM(debe)-SUM(haber) saldo,SUM(debe) debe,SUM(haber) haber 
                from ctb_diario_mov 
                WHERE estado=1 AND id_tipopol = 9 AND (feccnt BETWEEN ? AND ?) $condi GROUP BY ccodcta ORDER BY ccodcta";

    $apertura = $database->getAllResults($query, $parameters);
    if (empty($apertura)) {
        $showmensaje = true;
        throw new Exception("No hay partida de apertura en la fecha indicada");
    }
    // Log::info("APERTURA: " . json_encode($apertura));
    // $flag = ((count($apertura)) > 0) ? true : false;

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++ CONSULTA DE TODAS LAS CUENTAS DE ACTIVO, PASIVO, PATRIMONIO, INGRESOS Y EGRESOS ++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $query = "SELECT * from ctb_nomenclatura 
                WHERE estado=1 AND substr(ccodcta,1,1) IN (SELECT clase FROM ctb_parametros_cuentas WHERE id_tipo>=1 AND id_tipo<=5)
                ORDER BY ccodcta";
    $nomenclatura = $database->getAllResults($query);
    if (empty($nomenclatura)) {
        $showmensaje = true;
        throw new Exception("No se encontraron cuentas contables");
    }

    if (count(array_filter(array_column($nomenclatura, 'categoria_flujo'), function ($var) {
        return ($var > 0);
    })) < 1) {
        $showmensaje = true;
        throw new Exception("No hay cuentas parametrizadas para el calculo del Flujo de Efectivo, Favor parametrizar las cuentas y volver a intentar");
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++ CUENTAS PARAMETRIZADAS PAL BALANCE Y ER ++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $parametros = $database->selectColumns("ctb_parametros_cuentas", ['id_tipo', 'clase'], "id_tipo>=1 AND id_tipo<=5");
    if (empty($parametros)) {
        $showmensaje = true;
        throw new Exception("No hay cuentas parametrizadas para el calculo de balances y ER");
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
         ++++++++++++++++++++++++++++++++++++++++ INFO INSTITUCION ++++++++++++++++++++++++++++++++++++++++++++++++
         ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                 INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

//TIPO DE ARCHIVO A IMPRIMIR
switch ($tipo) {
    case 'xlsx';
        printxls($ctbmovdata, $titlereport, $apertura, $info, $nomenclatura, $parametros);
        break;
    case 'pdf':
        printpdf($ctbmovdata, $titlereport, $apertura, $info, $nomenclatura, $parametros);
        // printpdf($ctbmovdata, $titlereport, $apertura, $salinidata, $info, $hayanteriores, $haydata, $nomenclatura, $parametros, $cuentasingreso, $cuentasegreso);
        break;
}


//funcion para generar pdf
function printpdf($registro, $titlereport, $apertura, $info, $nomenclatura, $parametros)
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
            $this->Cell(0, 5, 'FLUJO DE EFECTIVO' . $this->datos, 0, 1, 'C', true);
            $this->Cell(0, 5, '(CIFRAS EN QUETZALES)', 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 32;

            $this->Cell($ancho_linea * 2, 5, 'DESCRIPCION', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'SALDO INICIAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'SALDO FINAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'DIFERENCIAS', 'B', 0, 'R');
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

    $efectivo_inicio = 0;
    /**
     * NOTA: NATURALEZA DE LAS CUENTAS
     *      + las cuentas de activo funcionan asi: DEBE - HABER
     *      + las cuentas de pasivo y patrimonio funcionan asi: HABER - DEBE
     * 
     *      + las cuentas de ingresos funcionan asi: HABER - DEBE
     *      + las cuentas de egresos funcionan asi: DEBE - HABER
     */
    $cuentasAnormales = array_filter($parametros, function ($var) {
        return ($var['id_tipo'] == 2 || $var['id_tipo'] == 3 || $var['id_tipo'] == 4);
    });

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++ PARTE 1: GASTOS QUE NO REQUIRIERON DE EFECTIVO +++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $pdf->CellFit(0, $tamanio_linea, '1.    GASTOS QUE NO REQUIRIERON DE EFECTIVO', '', 1, 'L', 0, '', 1, 0);
    $pdf->Ln(2);
    $i = 0;
    $sumadif = 0;

    $pdf->SetFont($fuente, '', 11);
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = decode_utf8($nomenclatura[$i]['cdescrip']);
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 1 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));

            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;

            if ($salapertura != 0 || $salfecha != 0) {
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, $nombrecuenta, '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salapertura, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salfecha, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($diferencia, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            }
        }
        $i++;
    }
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, number_format($sumadif, 2, '.', ','), 'B', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(4);

    $variacion1 = ($sumadif);

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++ PARTE 2: EFECTIVOS GENERADOS POR ACTIVIDADES DE OPERACION ++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $pdf->CellFit(0, $tamanio_linea, '2.    FLUJO DE EFECTIVOS POR ACTIVIDADES DE OPERACION', '', 1, 'L', 0, '', 1, 0);
    $pdf->Ln(2);
    $pdf->SetFont($fuente, '', 11);
    $i = 0;
    $sumadif = 0;
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = decode_utf8($nomenclatura[$i]['cdescrip']);
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 2 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));

            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;

            if ($salapertura != 0 || $salfecha != 0) {
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, $nombrecuenta, '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salapertura, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salfecha, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($diferencia, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            }
        }
        $i++;
    }
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, number_format($sumadif, 2, '.', ','), 'B', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(4);

    $variacion2 = ($sumadif);

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++ PARTE 3: FLUJO DE EFECTIVOS POR ACTIVIDADES DE INVERSION +++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $pdf->CellFit(0, $tamanio_linea, '3.    FLUJO DE EFECTIVOS POR ACTIVIDADES DE INVERSION', '', 1, 'L', 0, '', 1, 0);
    $pdf->Ln(2);
    $pdf->SetFont($fuente, '', 11);
    $i = 0;
    $sumadif = 0;
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = decode_utf8($nomenclatura[$i]['cdescrip']);
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 3 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));


            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;

            if ($salapertura != 0 || $salfecha != 0) {
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, $nombrecuenta, '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salapertura, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salfecha, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($diferencia, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            }
        }
        $i++;
    }
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, number_format($sumadif, 2, '.', ','), 'B', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(4);

    $variacion3 = ($sumadif);
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++ PARTE 4: FLUJO DE EFECTIVOS POR ACTIVIDADES DE FINANCIAMENTO +++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $pdf->CellFit(0, $tamanio_linea, '4.    FLUJO DE EFECTIVOS POR ACTIVIDADES DE FINANCIAMIENTO', '', 1, 'L', 0, '', 1, 0);
    $pdf->Ln(2);
    $pdf->SetFont($fuente, '', 11);
    $i = 0;
    $sumadif = 0;
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = decode_utf8($nomenclatura[$i]['cdescrip']);
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 4 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));

            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;

            if ($salapertura != 0 || $salfecha != 0) {
                $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, $nombrecuenta, '', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salapertura, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($salfecha, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($diferencia, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            }
        }
        $i++;
    }
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, number_format($sumadif, 2, '.', ','), 'B', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(2);

    $variacion4 = ($sumadif);

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++ PARTE 5: EFECTIVO AL INICIO DEL AÑO, SUMA DE CAJAS Y BANCOS PARAMETRIZADOS +++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $efectivoInicio = 0;
    $efectivoFinal = 0;

    foreach ($nomenclatura as $cuenta) {
        if ($cuenta['categoria_flujo'] == 5 && $cuenta['tipo'] == "D") {
            // Log::info("CUENTA: " . json_encode($cuenta));
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta['ccodcta']), 'saldo'));
            $saldoActual = array_sum(array_column(calculo2($registro, $cuenta['ccodcta']), 'saldo'));
            $efectivoInicio += $salapertura;
            $efectivoFinal += $saldoActual + $salapertura;

            $diferencia = $saldoActual - $salapertura;
            $sumadif += $diferencia;
        }
    }

    $variacion5 = ($efectivoFinal - $efectivoInicio);

    $pdf->CellFit($ancho_linea2 * 5, $tamanio_linea, 'AUMENTO O DISMINUCION EN EFECTIVO', '', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format(($variacion1 + $variacion2 + $variacion3 + $variacion4 + $variacion5), 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(2);

    $pdf->CellFit($ancho_linea2 * 5, $tamanio_linea, decode_utf8('EFECTIVO AL INICIO DEL AÑO'), '', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($efectivoInicio, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(2);

    $pdf->CellFit($ancho_linea2 * 5, $tamanio_linea, 'EFECTIVO AL FINAL DEL PROCESO', '', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($efectivoFinal, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(2);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Flujo de efectivo",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
//funcion para generar archivo excel
function printxls($registro, $titlereport, $apertura, $info, $nomenclatura, $parametros)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Flujo de efectivo");
    $activa->getColumnDimension("A")->setWidth(50);
    $activa->getColumnDimension("B")->setWidth(25);
    $activa->getColumnDimension("C")->setWidth(25);
    $activa->getColumnDimension("D")->setWidth(25);
    $activa->getColumnDimension("E")->setWidth(25);

    $activa->setCellValue('A1', 'DESCRIPCION');
    $activa->setCellValue('B1', 'SALDO INICIAL');
    $activa->setCellValue('C1', 'SALDO FINAL');
    $activa->setCellValue('D1', 'DIFERENCIA');
    $activa->setCellValue('E1', 'SALDO FINAL');

    /**
     * NOTA: NATURALEZA DE LAS CUENTAS
     *      + las cuentas de activo funcionan asi: DEBE - HABER
     *      + las cuentas de pasivo y patrimonio funcionan asi: HABER - DEBE
     * 
     *      + las cuentas de ingresos funcionan asi: HABER - DEBE
     *      + las cuentas de egresos funcionan asi: DEBE - HABER
     */
    $cuentasAnormales = array_filter($parametros, function ($var) {
        return ($var['id_tipo'] == 2 || $var['id_tipo'] == 3 || $var['id_tipo'] == 4);
    });

    $linea = 3;

    $aumento_disminucion = 0;
    $efectivo_inicio = 0;

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++ PARTE 1: GASTOS QUE NO REQUIRIERON DE EFECTIVO +++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $linea += 2;
    $activa->setCellValue('A' . $linea, 'GASTOS QUE NO REQUIRIERON DE EFECTIVO');
    $linea++;

    $ini = $linea;
    $i = 0;
    $sumadif = 0;
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = $nomenclatura[$i]['cdescrip'];
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 1 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));

            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;
            if ($salapertura != 0 || $salfecha != 0) {
                $activa->setCellValue('A' . $linea, $nombrecuenta);
                $activa->setCellValueExplicit('B' . $linea, $salapertura, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('C' . $linea, $salfecha, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('D' . ($linea), '=C' . $linea . '-B' . $linea . '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
                $linea++;
            }
        }
        $i++;
    }
    $activa->setCellValueExplicit('E' . ($linea), '=SUM(D' . $ini . ':D' . $linea . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

    $variacion1 = ($sumadif);
    //=SUMA(D6:D8)
    $aumento_disminucion += ($sumadif);
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++ PARTE 2: EFECTIVOS GENERADOS POR ACTIVIDADES DE OPERACION ++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $linea += 2;
    $activa->setCellValue('A' . $linea, 'FLUJO DE EFECTIVOS POR ACTIVIDADES DE OPERACION');
    $linea++;
    $ini = $linea;

    $i = 0;
    $sumadif = 0;
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = $nomenclatura[$i]['cdescrip'];
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 2 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));

            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;

            if ($salapertura != 0 || $salfecha != 0) {
                $activa->setCellValue('A' . $linea, $nombrecuenta);
                $activa->setCellValueExplicit('B' . $linea, $salapertura, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('C' . $linea, $salfecha, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('D' . ($linea), '=C' . $linea . '-B' . $linea . '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
                $linea++;
            }
        }
        $i++;
    }
    $activa->setCellValueExplicit('E' . ($linea), '=SUM(D' . $ini . ':D' . $linea . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

    $variacion2 = ($sumadif);
    $aumento_disminucion += ($sumadif);
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++ PARTE 3: FLUJO DE EFECTIVOS POR ACTIVIDADES DE INVERSION +++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $linea += 2;
    $activa->setCellValue('A' . $linea, 'FLUJO DE EFECTIVOS POR ACTIVIDADES DE INVERSION');
    $linea++;
    $ini = $linea;

    $i = 0;
    $sumadif = 0;
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = $nomenclatura[$i]['cdescrip'];
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 3 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));

            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));
            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;

            if ($salapertura != 0 || $salfecha != 0) {
                $activa->setCellValue('A' . $linea, $nombrecuenta);
                $activa->setCellValueExplicit('B' . $linea, $salapertura, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('C' . $linea, $salfecha, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('D' . ($linea), '=C' . $linea . '-B' . $linea . '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
                $linea++;
            }
        }
        $i++;
    }
    $activa->setCellValueExplicit('E' . ($linea), '=SUM(D' . $ini . ':D' . $linea . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
    $variacion3 = ($sumadif);
    $aumento_disminucion += ($sumadif);
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++ PARTE 4: FLUJO DE EFECTIVOS POR ACTIVIDADES DE FINANCIAMENTO +++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $linea += 2;
    $activa->setCellValue('A' . $linea, 'FLUJO DE EFECTIVOS POR ACTIVIDADES DE FINANCIAMIENTO');
    $linea++;
    $ini = $linea;

    $i = 0;
    $sumadif = 0;
    while ($i < count($nomenclatura)) {
        $id = $nomenclatura[$i]['id'];
        $cuenta = $nomenclatura[$i]['ccodcta'];
        $nombrecuenta = $nomenclatura[$i]['cdescrip'];
        $tipo = $nomenclatura[$i]['tipo'];
        $categoria = $nomenclatura[$i]['categoria_flujo'];
        if ($categoria == 4 && $tipo == "D") {
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta), 'saldo'));
            $salfecha = array_sum(array_column(calculo2($registro, $cuenta), 'saldo'));

            $clase = substr($cuenta, 0, 1);
            $verify = array_search($clase, array_column($cuentasAnormales, 'clase'));
            if ($verify !== false) {
                $salapertura = $salapertura * (-1);
                $salfecha = $salfecha * (-1);
            }

            $salfecha = $salapertura + $salfecha;
            $diferencia = $salfecha - $salapertura;
            $sumadif += $diferencia;
            $efectivo_inicio += $salapertura;

            if ($salapertura != 0 || $salfecha != 0) {
                $activa->setCellValue('A' . $linea, $nombrecuenta);
                $activa->setCellValueExplicit('B' . $linea, $salapertura, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('C' . $linea, $salfecha, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                $activa->setCellValueExplicit('D' . ($linea), '=C' . $linea . '-B' . $linea . '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);
                $linea++;
            }
        }
        $i++;
    }
    $activa->setCellValueExplicit('E' . ($linea), '=SUM(D' . $ini . ':D' . $linea . ')', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_FORMULA);

    $variacion4 = ($sumadif);

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++ PARTE 5: EFECTIVO AL INICIO DEL AÑO, SUMA DE CAJAS Y BANCOS PARAMETRIZADOS +++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $efectivoInicio = 0;
    $efectivoFinal = 0;

    foreach ($nomenclatura as $cuenta) {
        if ($cuenta['categoria_flujo'] == 5 && $cuenta['tipo'] == "D") {
            // Log::info("CUENTA: " . json_encode($cuenta));
            $salapertura = array_sum(array_column(calculo2($apertura, $cuenta['ccodcta']), 'saldo'));
            $saldoActual = array_sum(array_column(calculo2($registro, $cuenta['ccodcta']), 'saldo'));
            $efectivoInicio += $salapertura;
            $efectivoFinal += $saldoActual + $salapertura;

            $diferencia = $saldoActual - $salapertura;
            $sumadif += $diferencia;
        }
    }

    $variacion5 = ($efectivoFinal - $efectivoInicio);


    $aumento_disminucion += ($sumadif);
    $linea += 2;
    $linaumendis = $linea;
    $activa->setCellValue('A' . $linea, 'AUMENTO O DISMINUCION EN EFECTIVO');
    $activa->setCellValueExplicit('E' . ($linea), ($variacion1 + $variacion2 + $variacion3 + $variacion4 + $variacion5), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $linea++;


    $efectivo_inicio = $efectivo_inicio * (-1);
    $linea += 2;
    $linefini = $linea;
    $activa->setCellValue('A' . $linea, 'EFECTIVO AL INICIO DEL AÑO');
    $activa->setCellValueExplicit('E' . ($linea), $efectivoInicio, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $linea++;


    $linea += 2;
    $activa->setCellValue('A' . $linea, 'EFECTIVO AL FINAL DEL PROCESO');
    $activa->setCellValueExplicit('E' . ($linea), $efectivoFinal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $linea++;

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Flujo de efectivo",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
