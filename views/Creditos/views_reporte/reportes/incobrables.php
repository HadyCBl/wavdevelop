<?php
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

include '../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//[[`ffin`],[`codofi`,`fondoid`],[`ragencia`,`rfondos`],[ $idusuario; ]]
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

$idusuario = $_SESSION['id'];


if ($radios[1] == "anyf" && $selects[1] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar fuente de fondos']);
    return;
}

//REGION (nuevo filtro al final del payload)
$regionRadio = $radios[2] ?? 'allregion';
$regionId = (int)($selects[2] ?? 0);

if ($regionRadio == 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una región válida']);
    return;
}

//*****************ARMANDO LA CONSULTA**************
$condi = "";
//RANGO DE FECHAS
$filtrofecha = $hoy;
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";

//REGION
$filtroregion = "";
if ($regionRadio == 'anyregion' && $regionId > 0) {
    $filtroregion = " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $regionId . ")";
}

$strquery="SELECT 
    cremi.CODAgencia,
    CONCAT(usu.nombre, ' ', usu.apellido) AS analista,
    cremi.CCODCTA,
    cremi.NtipPerC,
    prod.id_fondo AS id_fondos,
    ffon.descripcion AS nombre_fondo,
    prod.id AS id_producto,
    prod.descripcion AS nombre_producto,
    prod.tasa_interes AS tasa,
    prod.porcentaje_mora AS tasamora,
    cli.short_name,
    cli.date_birth,
    cli.genero,
    cli.estado_civil,
    cremi.DFecDsbls,
    cremi.MonSug,
    cremi.NCapDes, 
    IFNULL(ppg.dfecven, 0) AS fechaven,
    IFNULL(ppg.sum_nintere, 0) AS intcal,
    IFNULL(ppg_ult.dfecven, 0) AS fechacalult,
    IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
    IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
    IFNULL(kar.sum_KP, 0) AS cappag,
    IFNULL(kar.sum_interes, 0) AS intpag,
    IFNULL(kar.sum_MORA, 0) AS morpag,
    IFNULL(kar.sum_AHOPRG_OTR, 0) AS otrpag,
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
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven, SUM(nintere) AS sum_nintere
    FROM Cre_ppg
    GROUP BY ccodcta
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
WHERE (cremi.CESTADO='I') " . $filfondo . $filagencia . $filtroregion . " 
ORDER BY prod.id_fondo, cremi.TipoEnti, cremi.CCodGrupo, prod.id, cremi.DFecDsbls;";


//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$filtrofecha, $filtrofecha, $filtrofecha]);

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
//FIN TRY

switch ($tipo) {
    case 'xlsx';
        printxls($result, $titlereport, $idusuario);
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
            $this->Cell(0, 5, 'CARTERA INCOBRABLE ' . $this->datos[0], 0, 1, 'C', true);
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
        $fechaven = ($fechaven != "0") ? date("d-m-Y", strtotime($fechaven)) : "-";
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
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea + 1, $diasatr, '', 1, 'R', 0, '', 1, 0);
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
        "atraso" => array_column($registro, "atraso"), "salcapital" => array_column($registro, "salcapital"), "capmora" => array_column($registro, "capmora"),
        "generos" => array_column($registro, "genero"), "idprod" => array_column($registro, "idprod"), "nombre_producto" => array_column($registro, "nombre_producto")
    ];
    $datosresumen[0] = resumen($clasdias, "atraso", 1, 30);
    $datosresumen[1] = resumen($clasdias, "atraso", 31, 60);
    $datosresumen[2] = resumen($clasdias, "atraso", 61, 90);
    $datosresumen[3] = resumen($clasdias, "atraso", 91, 180);
    $datosresumen[4] = resumen($clasdias, "atraso", 181, 10000);
    $datosresumen[5] = resumen($clasdias, "atraso", 0, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, 'Saldo: ', 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($sum_salcap, 2, '.', ','), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(5, 5, ' ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3 - 5, 5, 'Saldo: ', 'B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($sum_salcap, 2, '.', ','), 'B', 1, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);

    $pdf->CellFit($ancho_linea2 * 3, 5, 'Al Dia: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[5][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, 'Al Dia: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[5][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '1 a 30 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[0][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '1 a 30 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[0][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '31 a 60 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[1][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '31 a 60 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[1][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '61 a 90 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[2][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '61 a 90 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[2][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);


    $pdf->CellFit($ancho_linea2 * 3, 5, '91 a 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[3][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '91 a 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[3][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 * 3, 5, '+ de 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[4][0], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, '+ de 180 Dias: ', '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, 5, number_format($datosresumen[4][1], 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
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
        'namefile' => "Cartera incobrable",
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
    $activa->setTitle("CarteraIncobrable");
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
    $activa->setCellValue("A5", strtoupper("CARTERA INCOBRABLE " . $titlereport));

    //TITULO DE RECARGOS

    //titulo de recargos
    $activa->getStyle("A7:X7")->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->getStyle("A7:X7")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->setCellValue("M7", "RECUPERACIONES");

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CRÉDITO", "FONDO", "GENERO", "FECHA DE NACIMIENTO", "NOMBRE DEL CLIENTE", "OTORGAMIENTO", "VENCIMIENTO", "MONTO OTORGADO", "TOTAL INTERES A PAGAR", "SALDO CAPITAL", "SALDO INTERES", "SALDO MORA", "CAPITAL PAGADO", "INTERES PAGADO", "MORA PAGADO", "OTROS", "DIAS DE ATRASO", "SALDO CAP MAS INTERES", "MORA CAPITAL", "TASA INTERES", "TASA MORA", "PRODUCTO", "AGENCIA", "ASESOR", "TIPO CREDITO", "GRUPO", "ESTADO"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle('A8:X8')->getFont()->setName($fuente)->setBold(true);

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
        $nombre =  $registro[$fila]["short_name"];
        $genero =  $registro[$fila]["genero"];
        $date_birth =  $registro[$fila]["date_birth"];
        $fechades = date("d-m-Y", strtotime($registro[$fila]["DFecDsbls"]));
        $fechaven = $registro[$fila]["fechaven"];
        $fechaven = ($fechaven != "0") ? date("d-m-Y", strtotime($fechaven)) : "-";
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
        $estado = ($estado == "I") ? "INCOBRABLE" : " ";

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


        $activa->setCellValueExplicit('A' . $i, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('B' . $i, $nombrefondos);
        $activa->setCellValue('C' . $i, strtoupper($genero));
        $activa->setCellValue('D' . $i, $date_birth);
        $activa->setCellValue('E' . $i, strtoupper($nombre));
        $activa->setCellValue('F' . $i, $fechades);
        $activa->setCellValue('G' . $i, $fechaven);
        $activa->setCellValueExplicit('H' . $i, $monto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('I' . $i, $intcal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('J' . $i, $salcap, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('K' . $i, $salint, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('L' . $i, 0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('M' . $i, $cappag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('N' . $i, $intpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('O' . $i, $morpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('P' . $i, $otrpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('Q' . $i, $diasatr, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('R' . $i, ($salcap + $salint), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('S' . $i, $capmora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('T' . $i, $tasa, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('U' . $i, $tasamora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('V' . $i, strtoupper($nameproducto), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('W' . $i, $CODAgencia, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('X' . $i, strtoupper($analista), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('Y' . $i, $tipoenti, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('Z' . $i, $nomgrupo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AA' . $i, $estado, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $activa->getStyle("A" . $i . ":X" . $i)->getFont()->setName($fuente);

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

    $activa->getStyle("A" . $i . ":X" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValueExplicit('A' . $i, "Número de créditos: " . $fila, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->mergeCells("A" . $i . ":G" . $i);

    $activa->setCellValueExplicit('H' . $i, $sum_monto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('I' . $i, $sum_intcal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('J' . $i, $sum_salcap, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('K' . $i, $sum_salint, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('L' . $i, 0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('M' . $i, $sum_cappag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('N' . $i, $sum_intpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('O' . $i, $sum_morpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('P' . $i, $sum_otrpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    $activa->setCellValueExplicit('R' . $i, ($sum_salcap + $sum_salint), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('S' . $i, $sum_capmora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('T' . $i, $sum_tasa, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('U' . $i, $sum_tasamora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    $activa->getStyle("A" . $i . ":X" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    $activa->getColumnDimension('A')->setAutoSize(TRUE);
    $activa->getColumnDimension('B')->setAutoSize(TRUE);
    $activa->getColumnDimension('C')->setAutoSize(TRUE);
    $activa->getColumnDimension('D')->setAutoSize(TRUE);
    $activa->getColumnDimension('E')->setAutoSize(TRUE);
    $activa->getColumnDimension('F')->setAutoSize(TRUE);
    $activa->getColumnDimension('G')->setAutoSize(TRUE);
    $activa->getColumnDimension('H')->setAutoSize(TRUE);
    $activa->getColumnDimension('I')->setAutoSize(TRUE);
    $activa->getColumnDimension('J')->setAutoSize(TRUE);
    $activa->getColumnDimension('K')->setAutoSize(TRUE);
    $activa->getColumnDimension('L')->setAutoSize(TRUE);
    $activa->getColumnDimension('M')->setAutoSize(TRUE);
    $activa->getColumnDimension('N')->setAutoSize(TRUE);
    $activa->getColumnDimension('O')->setAutoSize(TRUE);
    $activa->getColumnDimension('P')->setAutoSize(TRUE);
    $activa->getColumnDimension('Q')->setAutoSize(TRUE);
    $activa->getColumnDimension('R')->setAutoSize(TRUE);
    $activa->getColumnDimension('S')->setAutoSize(TRUE);
    $activa->getColumnDimension('T')->setAutoSize(TRUE);
    $activa->getColumnDimension('U')->setAutoSize(TRUE);
    $activa->getColumnDimension('V')->setAutoSize(TRUE);
    $activa->getColumnDimension('W')->setAutoSize(TRUE);
    $activa->getColumnDimension('X')->setAutoSize(TRUE);
    $activa->getColumnDimension('AA')->setAutoSize(TRUE);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Cartera incobrable " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
