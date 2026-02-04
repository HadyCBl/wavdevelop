<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include '../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//[[`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[ $idusuario; ]]
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];


if (!validateDate($inputs[0], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}

if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar fuente de fondos']);
    return;
}
if ($radios[3] == "anyasesor" && $selects[2] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Asesor']);
    return;
}

//*****************ARMANDO LA CONSULTA**************
$condi = "";
//RANGO DE FECHAS
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = ($radios[3] == "anyasesor") ? " AND cremi.CodAnal =" . $selects[2] : "";
//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//-----------------------------
$strquery = "SELECT 
    cremi.CODAgencia,
    CONCAT(usu.nombre, ' ', usu.apellido) AS analista,
    cremi.CCODCTA,
    cremi.NtipPerC,
    prod.id_fondo AS id_fondos,
    ffon.descripcion AS nombre_fondo,
    prod.id AS id_producto,
    prod.descripcion AS nombre_producto,
    cremi.NintApro AS tasa,
    prod.porcentaje_mora AS tasamora,
    cli.short_name,cremi.CodCli codcliente,
    cli.date_birth,
    IFNULL(cli.genero,'X') genero,
    cli.estado_civil,cli.Direccion direccion,cli.tel_no1 tel1,cli.tel_no2 tel2,
    cremi.DFecDsbls,
    cremi.MonSug,
    cremi.NCapDes, cremi.DfecPago fecpago,dest.DestinoCredito destino,creper.descripcion frecuencia, 
    cremi.noPeriodo numcuotas,
    IFNULL(sector.SectoresEconomicos, '-') AS sectorEconomico,
    IFNULL(actividad.Titulo, '-') AS actividadEconomica,
    IFNULL(ppg.dfecven, '-') AS fechaven,
    IFNULL(ppg.cflag, '') AS fallas,
    IFNULL(ppg.sum_nintere, 0) AS intcal,
    IFNULL(ppg_ult.dfecven, 0) AS fechacalult,
    IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
    IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
    IFNULL(kar.sum_KP, 0) AS cappag,
    IFNULL(kar.sum_interes, 0) AS intpag,
    IFNULL(kar.sum_MORA, 0) AS morpag,
    IFNULL(kar.sum_AHOPRG_OTR, 0) AS otrpag,
    IFNULL((SELECT (ncapita+nintere) FROM Cre_ppg WHERE ccodcta=cremi.CCODCTA LIMIT 1),0) AS moncuota,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso,
    IFNULL(grupo.NombreGrupo, ' ') AS NombreGrupo,
    cremi.TipoEnti,
    IFNULL(cremi.CCodGrupo, ' ') AS CCodGrupo,
    cremi.Cestado 
FROM cremcre_meta cremi 
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
LEFT JOIN $db_name_general.tb_destinocredito dest ON dest.id_DestinoCredito=cremi.Cdescre
LEFT JOIN $db_name_general.`tb_cre_periodos` creper ON creper.cod_msplus=cremi.NtipPerC
LEFT JOIN $db_name_general.`tb_sectoreseconomicos` sector ON sector.id_SectoresEconomicos=cremi.CSecEco
LEFT JOIN $db_name_general.`tb_ActiEcono` actividad ON actividad.id_ActiEcono=cremi.ActoEcono
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven,
    COUNT(IF(cflag = 0 AND cflag IS NOT NULL, 1, NULL)) AS cflag, 
    SUM(nintere) AS sum_nintere 
    FROM 
        Cre_ppg 
        GROUP BY 
            ccodcta
) AS ppg ON ppg.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(ncapita) AS sum_ncapita, SUM(nintere) AS sum_nintere
    FROM Cre_ppg
    WHERE dfecven <= ?
    GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, SUM(KP) AS sum_KP, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? " . $filfondo . $filagencia . $filasesor . $status . " 
ORDER BY prod.id_fondo, cremi.TipoEnti, cremi.CCodGrupo, prod.id, cremi.DFecDsbls;";

$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha]);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

// echo json_encode(['status' => 0, 'mensaje' => $strquery]);
// return;

switch ($tipo) {
    case 'xlsx';
        printxls($result, $titlereport, $archivo[0]);
        break;
    case 'pdf':
        printpdf($result, [$titlereport], $info);
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info)
{

    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];

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
            $this->Cell(0, 5, 'CARTERA GENERAL' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;
            $this->Cell($ancho_linea * 6 + 15, 5, ' ', '', 0, 'L');
            $this->Cell($ancho_linea * 3 - 2, 5, 'RECUPERACIONES', 'TRL', 1, 'C');

            $this->Cell($ancho_linea, 5, 'CREDITO', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2 + 15, 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'OTORGAMIENTO', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'VENCIMIENTO', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'MONTO', 'B', 0, 'C'); //
            $this->Cell($ancho_linea, 5, 'CAPITAL', 'BL', 0, 'R');
            $this->Cell($ancho_linea, 5, 'INTERES', 'B', 0, 'R');
            $this->Cell($ancho_linea - 2, 5, 'MORA', 'BR', 0, 'R');
            $this->Cell($ancho_linea, 5, 'SAL. CAP.', 'B', 0, 'R'); //
            $this->Cell($ancho_linea, 5, 'SAL. INT.', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'SAL. K+IN', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'CAP.MORA', 'B', 0, 'C');
            $this->Cell($ancho_linea / 2, 5, 'ATRASO', 'B', 1, 'R'); //
            $this->Ln(1);
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 20;
    $pdf->SetFont($fuente, '', 7);
    $aux = 0;
    $auxgrupo = 0;
    $fila = 0;
    while ($fila < count($registro)) {
        $cuenta = $registro[$fila]["CCODCTA"];
        $nombre = decode_utf8($registro[$fila]["short_name"]);
        $genero =  $registro[$fila]["genero"];
        $fechades = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
        $fechaven = $registro[$fila]["fechaven"];
        $fechaven = ($fechaven == '-') ? "-" : date("d-m-Y", strtotime($fechaven));
        $monto = $registro[$fila]["NCapDes"];
        $intcal = $registro[$fila]["intcal"];
        $capcalafec = $registro[$fila]["capcalafec"];
        $intcalafec = $registro[$fila]["intcalafec"];
        $cappag = $registro[$fila]["cappag"];
        $intpag = $registro[$fila]["intpag"];
        $morpag = $registro[$fila]["morpag"];
        $diasatr = $registro[$fila]["atraso"];
        $idfondos = $registro[$fila]["id_fondos"];
        $nombrefondos = $registro[$fila]["nombre_fondo"];
        $idproducto = $registro[$fila]["id_producto"];
        $nameproducto = $registro[$fila]["nombre_producto"];
        $grupo = $registro[$fila]["NombreGrupo"];
        $codgrupo = ($registro[$fila]["CCodGrupo"] == NULL) ? ' ' : $registro[$fila]["CCodGrupo"];
        $tipoenti = $registro[$fila]["TipoEnti"];
        $fallas = $registro[$fila]["fallas"];

        //SALDO DE CAPITAL A LA FECHA
        $salcap = ($monto - $cappag);
        $salcap = ($salcap > 0) ? $salcap : 0;

        //SALDO DE INTERES A LA FECHA
        $salint = ($intcal - $intpag);
        $salint = ($salint > 0) ? $salint : 0;

        //CAPITAL EN MORA A LA FECHA
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;

        $registro[$fila]["salcapital"] = $salcap;
        $registro[$fila]["salintere"] = $salint;
        $registro[$fila]["capmora"] = $capmora;

        if ($idfondos != $aux) {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->Cell(0, 5, 'FUENTE DE FONDOS: ' . strtoupper($nombrefondos), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $aux = $idfondos;
            $auxgrupo = -1;
        }
        //TITULO FONDO
        /*         if ($idfondos != $auxfondo) {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 9);
            $pdf->Cell($ancho_linea * 2, 5, 'FUENTE DE FONDOS: ', '', 0, 'R');
            $pdf->Cell(0, 5, strtoupper($nombrefondos), '', 1, 'L');
            $pdf->SetFont($fuente, '', 8);
            $auxfondo = $idfondos;
            $auxgrupo = -1;
        } */
        //TITULO GRUPO
        if ($codgrupo != $auxgrupo) {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->Cell($ancho_linea2 * 2, 5, ($tipoenti == 'GRUP') ? 'GRUPO: ' : 'INDIVIDUALES ', '', 0, 'R');
            $pdf->Cell(0, 5, strtoupper($grupo), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxgrupo = $codgrupo;
        }
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 + 19, $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 2, $tamanio_linea + 1, $fechades, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 2, $tamanio_linea + 1, $fechaven, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($monto, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($cappag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($intpag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 2, $tamanio_linea + 1, number_format($morpag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($salcap, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($salint, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format(($salcap + $salint), 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($capmora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, ($diasatr ?? ' '), '', 1, 'R', 0, '', 1, 0);
        $fila++;
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 7);
    $sum_montos = array_sum(array_column($registro, "NCapDes"));
    $sum_cappag = array_sum(array_column($registro, "cappag"));
    $sum_intpag = array_sum(array_column($registro, "intpag"));
    $sum_morpag = array_sum(array_column($registro, "morpag"));
    $sum_salcap = array_sum(array_column($registro, "salcapital"));
    $sum_salint = array_sum(array_column($registro, "salintere"));
    $sum_capmora = array_sum(array_column($registro, "capmora"));

    $pdf->CellFit($ancho_linea2 * 5 + 15, $tamanio_linea + 1, 'Numero de Creditos: ' . $fila, 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_montos, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_cappag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_intpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 2, $tamanio_linea + 1, number_format($sum_morpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_salcap, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_salint, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_salcap + $sum_salint, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_capmora, 2, '.', ','), 'T', 1, 'R', 0, '', 1, 0);

    //RESUMEN DIAS
    //0, 'C', 0, '', 1, 0
    $pdf->Ln(4);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell($ancho_linea2 * 6, 5, 'MORA EN CAPITAL EN RIESGO ', '', 0, 'C');
    $pdf->Cell($ancho_linea2 * 6, 5, 'MORA EN CUOTA VENCIDA ', '', 1, 'C');

    $datosresumen[] = [];
    $clasdias = [
        "atraso" => array_column($registro, "atraso"),
        "salcapital" => array_column($registro, "salcapital"),
        "capmora" => array_column($registro, "capmora"),
        "generos" => array_column($registro, "genero"),
        "idprod" => array_column($registro, "idprod"),
        "nombre_producto" => array_column($registro, "nombre_producto")
    ];
    $datosresumen[0] = resumen($clasdias, "atraso", 1, 30);
    $datosresumen[1] = resumen($clasdias, "atraso", 31, 60);
    $datosresumen[2] = resumen($clasdias, "atraso", 61, 90);
    $datosresumen[3] = resumen($clasdias, "atraso", 91, 180);
    $datosresumen[4] = resumen($clasdias, "atraso", 181, 10000);
    $datosresumen[5] = resumen($clasdias, "atraso", 0, 0);
    $totalResumen = array_sum(array_column($datosresumen, 1));
    $totalGeneral = array_sum(array_column($datosresumen, 1));


    $porcentaje1 = ($totalGeneral > 0) ? ($datosresumen[0][1] / $totalGeneral * 100) : 0;
    $porcentaje2 = ($totalGeneral > 0) ? ($datosresumen[1][1] / $totalGeneral * 100) : 0;
    $porcentaje3 = ($totalGeneral > 0) ? ($datosresumen[2][1] / $totalGeneral * 100) : 0;
    $porcentaje4 = ($totalGeneral > 0) ? ($datosresumen[3][1] / $totalGeneral * 100) : 0;
    $porcentaje5 = ($totalGeneral > 0) ? ($datosresumen[4][1] / $totalGeneral * 100) : 0;
    $porcentaje6 = ($totalGeneral > 0) ? ($datosresumen[5][1] / $totalGeneral * 100) : 0;


    $pdf->CellFit($ancho_linea2 * 3, 5, 'Saldo: ', 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($sum_salcap, 2, '.', ','), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(5, 5, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3 - 5, 5, 'Saldo: ', 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($sum_salcap, 2, '.', ','), 'B', 1, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);

    $pdf->CellFit($ancho_linea2 * 3, 5, 'Al Dia: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[5][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, 'Al Dia: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[5][1], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, '(' . number_format($porcentaje6, 1, '.', ',') . '%)', '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '1 a 30 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[0][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '1 a 30 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[0][1], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, '(' . number_format($porcentaje1, 1, '.', ',') . '%)', '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '31 a 60 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[1][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '31 a 60 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[1][1], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, '(' . number_format($porcentaje2, 1, '.', ',') . '%)', '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '61 a 90 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[2][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '61 a 90 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[2][1], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, '(' . number_format($porcentaje3, 1, '.', ',') . '%)', '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '91 a 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[3][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '91 a 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[3][1], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, '(' . number_format($porcentaje4, 1, '.', ',') . '%)', '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '+ de 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[4][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '+ de 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[4][1], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, '(' . number_format($porcentaje5, 1, '.', ',') . '%)', '', 1, 'R', 0, '', 1, 0);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 * 3, 5, 'TOTAL: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format(array_sum(array_column($datosresumen, 0)), 2, '.', ','), 'TB', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, 'TOTAL: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format(array_sum(array_column($datosresumen, 1)), 2, '.', ','), 'TB', 1, 'R', 0, '', 1, 0);
    //FIN RESUMEN DIAS
    // $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
    /* SALDO INTERESES */
    $pdf->Ln(6);
    $pdf->Cell(0, 5, ' ', 'B', 0, 'R');
    $pdf->Ln(10);
    $pdf->CellFit($ancho_linea2 * 3, 5, 'SALDO INTERESES: ', 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($sum_salint, 2, '.', ','), 'B', 0, 'R', 0, '', 1, 0);
    /***FIN SALDO INTERESES */

    /* GENEROS*/
    $pdf->Cell(5, 5, ' ', 0, 0, 'R');
    $pdf->CellFit($ancho_linea2 * 2, 5, 'GENERO', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, 5, 'CANTIDAD', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, 'SALDO DE CAPITAL', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2 + 6, 5, 'MORA EN CUOTA VENCIDA', 'B', 1, 'C', 0, '', 1, 0);

    $datosresumen[6] = resumen($clasdias, "generos", "M", "M");
    $datosresumen[7] = resumen($clasdias, "generos", "F", "F");
    $datosresumen[8] = resumen($clasdias, "generos", "X", "X");
    $pdf->SetFont($fuente, '', 9);
    //hombres
    $pdf->Cell($ancho_linea2 * 6, 5, ' ', 0, 0, 'R');
    $pdf->Cell(5, 5, ' ', 0, 0, 'R');
    $pdf->CellFit($ancho_linea2 * 2, 5, 'MASCULINO: ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, 5, $datosresumen[6][2], '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5,  number_format($datosresumen[6][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2 + 6, 5,  number_format($datosresumen[6][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);

    //mujeres
    $pdf->Cell($ancho_linea2 * 6, 5, ' ', 0, 0, 'R');
    $pdf->Cell(5, 5, ' ', 0, 0, 'R');
    $pdf->CellFit($ancho_linea2 * 2, 5, 'FEMENINO: ', '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, 5, $datosresumen[7][2], '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5,  number_format($datosresumen[7][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5,  number_format($datosresumen[7][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    //INDEFINIDO
    $pdf->Cell($ancho_linea2 * 6, 5, ' ', 0, 0, 'R');
    $pdf->Cell(5, 5, ' ', 0, 0, 'R');
    $pdf->CellFit($ancho_linea2 * 2, 5, 'INDEFINIDO: ', '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, 5, $datosresumen[8][2], '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5,  number_format($datosresumen[8][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5,  number_format($datosresumen[8][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    /*FIN GENEROS*/

    /*PRODUCTOS */
    $pdf->Ln(3);
    $pdf->Cell(0, 5, ' ', 'B', 0, 'R');
    $pdf->Ln(10);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell($ancho_linea2 * 3.5, 5, ' ', 0, 0, 'R');
    $pdf->CellFit($ancho_linea2 * 2, 5, 'PRODUCTO', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, 5, 'CANTIDAD', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, 5, 'SALDO DE CAPITAL', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2 + 6, 5, 'MORA EN CUOTA VENCIDA', 'B', 1, 'C', 0, '', 1, 0);
    $aux = 9;
    $idproductos = array_unique($clasdias["nombre_producto"]);
    $pdf->SetFont($fuente, '', 9);
    foreach ($idproductos as $idp) {
        $datosresumen[$aux] = resumen($clasdias, "nombre_producto", $idp, $idp);
        $pdf->Cell($ancho_linea2 * 1.5, 5, ' ', 0, 0, 'R');
        $pdf->CellFit($ancho_linea2 * 4, 5, strtoupper(decode_utf8($idp)), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, 5, $datosresumen[$aux][2], '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, 5,  number_format($datosresumen[$aux][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 + 6, 5,  number_format($datosresumen[$aux][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        $aux++;
    }

    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cartera General",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
//FUNCIONES PARA DATOS DE RESUMEN
function resumen($clasdias, $column, $con1, $con2)
{
    $keys = array_keys(array_filter($clasdias[$column], function ($var) use ($con1, $con2) {
        return ($var >= $con1 && $var <= $con2);
    }));
    $fila = 0;
    $sum1 = 0;
    $sum2 = 0;
    while ($fila < count($keys)) {
        $f = $keys[$fila];
        $sum1 += ($clasdias["salcapital"][$f]);
        $sum2 += ($clasdias["capmora"][$f]);
        $fila++;
    }
    return [$sum1, $sum2, $fila];
}

//funcion para generar archivo excel
function printxls($registro, $titlereport, $usuario)
{
    require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    // $tamanioEncabezado = 14;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("CarteraGeneral");
    $activa->getColumnDimension("A")->setWidth(20);
    $activa->getColumnDimension("B")->setWidth(20);
    $activa->getColumnDimension("C")->setWidth(5);
    $activa->getColumnDimension("D")->setWidth(15);
    $activa->getColumnDimension("E")->setWidth(25);
    $activa->getColumnDimension("F")->setWidth(15);
    $activa->getColumnDimension("G")->setWidth(15);
    $activa->getColumnDimension("H")->setWidth(15);


    //insertarmos la fecha y usuario
    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $activa->getStyle("A1:X1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle("A2:X2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $activa->getStyle("A1:X1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A2:X2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer pequeño las letras del encabezado de titulo
    $activa->getStyle("A4:X4")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $activa->getStyle("A5:X5")->getFont()->setSize($tamanioTabla)->setName($fuente);
    //centrar los encabezado de la tabla
    $activa->getStyle("A4:X4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A5:X5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $activa->setCellValue("A4", "REPORTE");
    $activa->setCellValue("A5", strtoupper("CARTERA GENERAL " . $titlereport));

    //TITULO DE RECARGOS

    //titulo de recargos
    $activa->getStyle("A7:X7")->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->getStyle("A7:X7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->setCellValue("R7", "RECUPERACIONES");

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CRÉDITO", "FONDO", "COD CLIENTE", "GENERO", "FECHA DE NACIMIENTO", "NOMBRE DEL CLIENTE", "DIRECCION", "TEL1", "TEL2", "OTORGAMIENTO", "VENCIMIENTO", "MONTO OTORGADO", "MONTO CUOTA", "TOTAL INTERES A PAGAR", "SALDO CAPITAL", "SALDO INTERES", "SALDO MORA", "CAPITAL PAGADO", "INTERES PAGADO", "MORA PAGADO", "OTROS", "DIAS DE ATRASO", "SALDO CAP MAS INTERES", "MORA CAPITAL", "TASA INTERES", "TASA MORA", "PRODUCTO", "AGENCIA", "ASESOR", "TIPO CREDITO", "GRUPO", "ESTADO", "DESTINO", "DIA PAGO", "FRECUENCIA", "NO CUOTAS", "FALLAS", "Sector Economico", "Actividad Economica"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle('A8:AM8')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $activa->mergeCells('A1:X1');
    $activa->mergeCells('A2:X2');
    $activa->mergeCells('A4:X4');
    $activa->mergeCells('A5:X5');
    $activa->mergeCells('M7:O7');

    $fila = 0;
    $i = 9;
    while ($fila < count($registro)) {
        $cuenta = $registro[$fila]["CCODCTA"];
        $codcliente = $registro[$fila]["codcliente"];
        $nombre =  $registro[$fila]["short_name"];
        $direccion =  $registro[$fila]["direccion"];
        $tel1 =  $registro[$fila]["tel1"];
        $tel2 =  $registro[$fila]["tel2"];
        $genero =  $registro[$fila]["genero"];
        $date_birth =  $registro[$fila]["date_birth"];
        $fechades = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
        $fechaven = $registro[$fila]["fechaven"];
        $fechaven = ($fechaven == '-') ? "-" : date("d-m-Y", strtotime($fechaven));
        $monto = $registro[$fila]["NCapDes"];
        $intcal = $registro[$fila]["intcal"];
        $capcalafec = $registro[$fila]["capcalafec"];
        $intcalafec = $registro[$fila]["intcalafec"];
        $cappag = $registro[$fila]["cappag"];
        $intpag = $registro[$fila]["intpag"];
        $morpag = $registro[$fila]["morpag"];
        $diasatr = $registro[$fila]["atraso"];
        $idfondos = $registro[$fila]["id_fondos"];
        $nombrefondos = $registro[$fila]["nombre_fondo"];
        $idproducto = $registro[$fila]["id_producto"];
        $nameproducto = $registro[$fila]["nombre_producto"];
        $analista = $registro[$fila]["analista"];
        $CODAgencia = $registro[$fila]["CODAgencia"];
        $tasa = $registro[$fila]["tasa"];
        $tasamora = $registro[$fila]["tasamora"];
        $otrpag = $registro[$fila]["otrpag"];
        $tipoenti = $registro[$fila]["TipoEnti"];
        $nomgrupo = $registro[$fila]["NombreGrupo"];
        $estado = $registro[$fila]["Cestado"];
        $destino = $registro[$fila]["destino"];
        $frec = $registro[$fila]["frecuencia"];
        $ncuotas = $registro[$fila]["numcuotas"];
        $moncuota = $registro[$fila]["moncuota"];
        $diapago = date('d', strtotime($registro[$fila]["fecpago"]));
        $fallas = $registro[$fila]["fallas"];

        //SALDO DE CAPITAL A LA FECHA
        $salcap = ($monto - $cappag);
        $salcap = ($salcap > 0) ? $salcap : 0;
        $estado = ($salcap > 0) ? "VIGENTE" : "CANCELADO";

        //SALDO DE INTERES A LA FECHA
        $salint = ($intcal - $intpag);
        $salint = ($salint > 0) ? $salint : 0;

        //CAPITAL EN MORA A LA FECHA
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;

        $registro[$fila]["salcapital"] = $salcap;
        $registro[$fila]["salintere"] = $salint;
        $registro[$fila]["capmora"] = $capmora;

        $activa->setCellValueByColumnAndRow(obtenerContador(1), $i, $cuenta);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $nombrefondos);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $codcliente);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($genero));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $date_birth);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($nombre));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $direccion);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $tel1);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $tel2);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $fechades);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $fechaven);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $monto);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $moncuota);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $intcal);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $salcap);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $salint);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, 0);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $cappag);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $intpag);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $morpag);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $otrpag);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $diasatr);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, ($salcap + $salint));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $capmora);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $tasa);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $tasamora);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($nameproducto));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $CODAgencia);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, strtoupper($analista));
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $tipoenti);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $nomgrupo);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $estado);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $destino);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $diapago);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $frec);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $ncuotas);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $fallas);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $registro[$fila]["sectorEconomico"]);
        $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $registro[$fila]["actividadEconomica"]);

        $activa->getStyle("A" . $i . ":AZ" . $i)->getFont()->setName($fuente);

        $fila++;
        $i++;
    }
    //total de registros
    $sum_monto = array_sum(array_column($registro, "NCapDes"));
    $sum_intcal = array_sum(array_column($registro, "intcal"));
    $sum_cappag = array_sum(array_column($registro, "cappag"));
    $sum_intpag = array_sum(array_column($registro, "intpag"));
    $sum_morpag = array_sum(array_column($registro, "morpag"));
    $sum_salcap = array_sum(array_column($registro, "salcapital"));
    $sum_salint = array_sum(array_column($registro, "salintere"));
    $sum_capmora = array_sum(array_column($registro, "capmora"));
    $sum_otrpag = array_sum(array_column($registro, "otrpag"));
    $sum_capmora = array_sum(array_column($registro, "capmora"));
    $sum_tasa = array_sum(array_column($registro, "tasa"));
    $sum_tasamora = array_sum(array_column($registro, "tasamora"));

    $activa->getStyle("A" . $i . ":AM" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValueExplicit('A' . $i, "Número de créditos: " . $fila, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->mergeCells("A" . $i . ":G" . $i);

    $activa->setCellValueByColumnAndRow(obtenerContador(12), $i, $sum_monto);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, 0);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_intcal);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_salcap);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_salint);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, 0);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_cappag);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_intpag);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_morpag);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_otrpag);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, ' ');
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, ($sum_salcap + $sum_salint));
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_capmora);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_tasa);
    $activa->setCellValueByColumnAndRow(obtenerContador(), $i, $sum_tasamora);

    $activa->getStyle("A" . $i . ":AM" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    $columnas = range(1, 40);
    foreach ($columnas as $columna) {
        $letra = obtenerLetra($columna);
        $activa->getColumnDimension($letra)->setAutoSize(TRUE);

        // $activa->getColumnDimension($columna)->setAutoSize(TRUE);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cartera general " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
function obtenerContador($restart = false)
{
    static $contador = 0;
    $contador = ($restart == false) ? $contador + 1 : $restart;
    return $contador;
}
function obtenerLetra($columna)
{
    $letra = '';
    $columna--; // Decrementar la columna para que coincida con el índice de las letras del abecedario (empezando desde 0)

    while ($columna >= 0) {
        $letra = chr($columna % 26 + 65) . $letra; // Convertir el índice de columna a letra de Excel
        $columna = intval($columna / 26) - 1;
    }

    return $letra;
}
