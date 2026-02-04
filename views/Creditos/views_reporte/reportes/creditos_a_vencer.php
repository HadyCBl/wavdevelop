<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

$tipoconsulta = 0;

// validar la Fecha
// if ($inputs[0] != $hoy) {
//     echo json_encode(['status' => 0, 'mensaje' => 'La fecha inicial debe ser igual a la de hoy']);
//     return;
// }

// if ($inputs[1] < $hoy) {
//     echo json_encode(['status' => 0, 'mensaje' => 'La fecha final no debe ser menor que la fecha inicial']);
//     return;
// }

// Filtro de región
$regionRadio = $radios[1] ?? 'allregion';
$regionId = (int)($selects[1] ?? 0);

if ($regionRadio == 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Debe seleccionar una región válida']);
    return;
}

$filtroregion = "";
if ($regionRadio == 'anyregion' && $regionId > 0) {
    $filtroregion = " AND cm.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $regionId . ")";
}

//consulta para credito individual
$filters = "";
if ($radios[0] == '1') {
    $filters = " AND TipoEnti='INDI'";
}
if ($radios[0] == '2') {
    $filters = " AND TipoEnti='GRUP'";
}
if ($selects[0] != '0') {
    $filters .= " AND CodAnal=" . $selects[0];
}

$consulta = "SELECT concat(us.nombre,' ',us.apellido) nomusu, ag.id_agencia,ag.nom_agencia,ff.descripcion, pd.tasa_interes, cm.CCODCTA AS cuenta, cl.idcod_cliente AS cliente,
cl.short_name AS nombre, cm.MonSug AS monapro,cm.NCapDes mondes,cm.DFecDsbls,cm.noPeriodo,cm.NtipPerC formpago,ff.id idfondo,cm.CodAnal idanal,
((cm.NCapDes) - (SELECT IFNULL(SUM(KP),0) FROM CREDKAR WHERE ccodcta=cm.CCODCTA AND CTIPPAG='P' AND CESTADO!='X')) AS saldo, cm.DFecVen,
IFNULL((SELECT DFECPRO  FROM CREDKAR WHERE ccodcta=cm.CCODCTA AND CTIPPAG='P' AND CESTADO!='X' ORDER BY DFECPRO DESC LIMIT 1),'' ) AS ultpag,
IFNULL((SELECT NombreGrupo from tb_grupo where id_grupos=cm.CCodGrupo),' ') NombreGrupo, cm.CCodGrupo,cm.TipoEnti
FROM cremcre_meta cm 
INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente 
INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu 
INNER JOIN tb_agencia ag ON ag.id_agencia= us.id_agencia
INNER JOIN cre_productos pd ON cm.CCODPRD = pd.id 
INNER JOIN ctb_fuente_fondos ff ON ff.id=pd.id_fondo
WHERE cm.Cestado='F' AND (cm.DFecVen BETWEEN '" . $inputs[0] . "' AND '" . $inputs[1] . "') " . $filters . $filtroregion . " ORDER BY ff.id,ag.id_agencia,us.id_usu,cm.CCodGrupo,cm.CCODCTA";

$texto_reporte = "";
$texto_reporte = "REPORTE DE CREDITO A VENCER ENTRE LA FECHA " . $inputs[0] . " AL " . $inputs[1];

/*  echo json_encode(['status' => 0, 'mensaje' => $consulta]);
 return; */

//SE LEEN LOS datos
$valores[] = [];
$columnas[] = [];
$data[] = [];
$sumacapmora[] = [];
$sumamora[] = [];
$cuotaaux[] = [];
$j = 0;
$resultado = mysqli_query($conexion, $consulta);
while ($fila = mysqli_fetch_array($resultado, MYSQLI_ASSOC)) {
    $valores[$j] = $fila;
    $j++;
}
//fin de separacion
if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'No hay datos para mostrar en el reporte']);
    return;
}

$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}
//se manda a impresion
switch ($tipo) {
    case 'xlsx':
        printxls($valores, [$texto_reporte, $_SESSION['id'], $hoy, $conexion]);
        break;
    case 'pdf':
        printpdf($valores, [$texto_reporte, $_SESSION['id'], $hoy, $conexion], $info);
        break;
}

//FUNCION PARA GENERAR EL REPORTE EN PDF
/**
 * Convierte texto UTF-8 a ISO-8859-1 para compatibilidad con FPDF
 * @param string|null $value El valor a convertir
 * @return string El valor convertido o cadena vacía si es null
 */
function fpdf_text($value) {
    if ($value === null) {
        return '';
    }
    
    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');
    }
    
    if (function_exists('iconv')) {
        return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $value);
    }
    
    return $value;
}

function printpdf($datos, $otros, $info)
{
    $oficina = fpdf_text($info[0]["nom_agencia"]);
    $institucion = fpdf_text($info[0]["nomb_comple"]);
    $direccionins = fpdf_text($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];

    class PDF extends FPDF
    {
        //atributos de la clase
        public $oficina;
        public $institucion;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $pathlogo;
        public $pathlogoins;
        public $titulo;
        public $user;
        public $conexion;

        public function __construct($oficina, $institucion, $direccion, $email, $telefono, $nit, $pathlogo, $pathlogoins, $titulo, $user, $conexion)
        {
            parent::__construct();
            $this->oficina = $oficina;
            $this->institucion = $institucion;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->titulo = $titulo;
            $this->user = $user;
            $this->conexion = $conexion;
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $tamanio_linea = 4; //altura de la linea/celda
            $ancho_linea = 28; //anchura de la linea/celda
            $ancho_linea2 = 20; //anchura de la linea/celda

            // ACA ES DONDE EMPIEZA LO DEL FORMATO DE REPORTE---------------------------------------------------
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont('Arial', '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(1);
            $this->Cell(0, 2, $this->user, 0, 1, 'R');

            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 13, 33);

            //tipo de letra para el encabezado
            $this->SetFont('Arial', '', 8);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
            // Salto de línea
            $this->Ln(3);

            $this->SetFont($fuente, '', 10);
            //SECCION DE DATOS DEL CLIENTE
            //TITULO DE REPORTE
            $this->SetFillColor(255, 255, 255);
            $this->Cell(0, 5, 'REPORTE', 0, 1, 'C', true);
            $this->Cell(0, 5,  $this->titulo, 0, 1, 'C', true);

            $this->Ln(5);
            //Fuente
            $this->SetFont($fuente, '', 8);
            //encabezado de tabla
            // $this->CellFit($ancho_linea + 130, $tamanio_linea + 1, " ", 0, 0, 'C', 0, '', 1, 0);
            // $this->CellFit($ancho_linea + 30, $tamanio_linea + 1, "RECARGOS", 1, 0, 'C', 0, '', 1, 0);
            // $this->CellFit($ancho_linea + 42, $tamanio_linea + 1, " ", 0, 0, 'C', 0, '', 1, 0);
            // $this->Ln(5);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, fpdf_text("CRÉDITO"), 'B', 0, 'L', 0, '', 1, 0);
            $this->CellFit($ancho_linea * 2, $tamanio_linea + 1, fpdf_text('NOMBRE DEL CLIENTE'), 'B', 0, 'L', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'APROBADO', 'B', 0, 'R', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'DESEMBOLSADO', 'B', 0, 'R', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'SALDO CAP', 'B', 0, 'R', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'OTORGAMIENTO', 'B', 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'VENCIMIENTO', 'B', 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'ULT. PAGO', 'B', 0, 'C', 0, '', 1, 0);
            $this->CellFit($ancho_linea, $tamanio_linea + 1, 'NO. CUOTAS', 'B', 0, 'C', 0, '', 1, 0);
            $this->Ln(7);
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

    $fuente = "Courier";
    $tamanio_linea = 4;
    $ancho_linea = 28;

    // Creación del objeto de la clase heredada
    $pdf = new PDF($oficina, $institucion, $direccionins, $emailins, $telefonosins, $nitins, $rutalogomicro, $rutalogoins, $otros[0], $otros[1], $otros[3]);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    //AQUI COLOCAR TODOS LO DATOS
    $auxfondo = -1;
    $auxagencia = -1;
    $auxanalista = -1;
    $auxgrupo = -1;

    $sumamonto = 0;
    $sumasaldo = 0;
    $sumamora = 0;
    $fila = 0;
    while ($fila < count($datos)) {
        $cuenta = $datos[$fila]["cuenta"];
        $nombre = strtoupper(fpdf_text($datos[$fila]["nombre"]));
        $monapr = $datos[$fila]["monapro"];
        $mondes = $datos[$fila]["mondes"];
        $saldo = $datos[$fila]["saldo"];
        $numcuota = $datos[$fila]["noPeriodo"];
        $nombrefondos  = $datos[$fila]["descripcion"];
        $nomagencia  = $datos[$fila]["nom_agencia"];
        $nomanal  = $datos[$fila]["nomusu"];
        $nomgrupo = $datos[$fila]["NombreGrupo"];
        $tipoenti = $datos[$fila]["TipoEnti"];
        $idgrupo = ($tipoenti == "GRUP") ? $datos[$fila]["CCodGrupo"] : 0;

        $fecdes =  date("d-m-Y", strtotime($datos[$fila]["DFecDsbls"]));
        $fecven = date("d-m-Y", strtotime($datos[$fila]["DFecVen"]));
        $ultpago = $datos[$fila]["ultpag"];
        $ultpago = ($ultpago == '') ? '-' : date("d-m-Y", strtotime($ultpago));
        $formpago = $datos[$fila]["formpago"];
        $idfondos = $datos[$fila]["idfondo"];
        $idagencia = $datos[$fila]["id_agencia"];
        $codanal = $datos[$fila]["idanal"];

        //TITULO FONDO
        if ($idfondos != $auxfondo) {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 9);
            $pdf->Cell($ancho_linea / 2, 5, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea * 1.5, 5, 'FUENTE DE FONDOS: ', '', 0, 'L');
            $pdf->Cell(0, 5, strtoupper($nombrefondos), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxfondo = $idfondos;
        }
        //TITULO AGENCIA
        if ($idagencia != $auxagencia) {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->Cell($ancho_linea, 5, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea, 5, 'AGENCIA : ', '', 0, 'L');
            $pdf->Cell(0, 5, strtoupper($nomagencia), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxagencia = $idagencia;
            $auxanalista = -1;
        }
        //TITULO EJECUTIVO
        if ($codanal != $auxanalista) {
            $pdf->SetFont($fuente, 'BI', 7);
            $pdf->Cell($ancho_linea, 5, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea, 5, 'EJECUTIVO : ', '', 0, 'L');
            $pdf->Cell(0, 5, strtoupper($nomanal), '', 1, 'L');
            $pdf->SetFont($fuente, '', 7);
            $auxanalista = $codanal;
            $auxgrupo = -1;
        }
        //TITULO GRUPO
        if ($idgrupo != $auxgrupo) {
            $pdf->Ln(2);
            $pdf->SetFont($fuente, 'B', 8);
            $pdf->Cell($ancho_linea, 5, ' ', '', 0, 'R');
            $pdf->Cell($ancho_linea, 5, ($tipoenti == 'GRUP') ? 'GRUPO: ' : 'INDIVIDUALES ', '', 0, 'L');
            $pdf->Cell(0, 5, strtoupper($nomgrupo), '', 1, 'L');
            $pdf->SetFont($fuente, '', 8);
            $auxgrupo = $idgrupo;
        }

        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $cuenta, 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea * 2, $tamanio_linea + 1, $nombre, 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($monapr, 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($mondes, 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($saldo, 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $fecdes, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $fecven, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $ultpago, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $numcuota, 0, 0, 'C', 0, '', 1, 0);

        $pdf->Ln(5);
        $fila++;
    }
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->Cell(0, 0, ' ', 1, 1, 'R');
    $pdf->CellFit($ancho_linea * 2, $tamanio_linea + 1, 'TOTAL GENERAL: ', 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, $fila, 0, 0, 'L', 0, '', 1, 0);

    $sumapro = array_sum(array_column($datos, "monapro"));
    $sumdesem = array_sum(array_column($datos, "mondes"));
    $sumsaldo = array_sum(array_column($datos, "saldo"));

    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumapro, 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumdesem, 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea, $tamanio_linea + 1, number_format($sumsaldo, 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "reporte_cred_vencer_" . $otros[2],
        'tipo' => 'pdf',
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)

    );
    echo json_encode($opResult);
}

function printxls($datos, $otros)
{
    $hoy = date("Y-m-d H:i:s");
    // $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    // $direccionins = "Canton vipila zona 1";
    // $emailins = "fape@gmail.com";
    // $telefonosins = "502 43987876";
    // $nitins = "1323244234";

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    // $tamanioEncabezado = 14;
    $tamanioTabla = 11;

    $spread = new Spreadsheet();
    $spread
        ->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Creditos a vencesr')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');
    //-----------RELACIONADO CON LAS PROPIEDADES DEL ARCHIVO----------------------------

    //-----------RELACIONADO CON EL ENCABEZADO----------------------------
    # Como ya hay una hoja por defecto, la obtenemos, no la creamos
    $hojaReporte = $spread->getActiveSheet();
    $hojaReporte->setTitle("Reporte de Creditos A Vencer");

    //insertarmos la fecha y usuario
    $hojaReporte->setCellValue("A1", $hoy);
    $hojaReporte->setCellValue("A2", $otros[1]);


    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $hojaReporte->getStyle("A1:O1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A2:O2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $hojaReporte->getStyle("A1:O1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A2:O2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer pequeño las letras del encabezado de titulo
    $hojaReporte->getStyle("A4:P4")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $hojaReporte->getStyle("A5:P5")->getFont()->setSize($tamanioTabla)->setName($fuente);
    //centrar los encabezado de la tabla
    $hojaReporte->getStyle("A4:P4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A5:P5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $hojaReporte->setCellValue("A4", "REPORTE");
    $hojaReporte->setCellValue("A5", strtoupper($otros[0]));

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["FONDOS", "AGENCIA", "EJECUTIVO", "NOMBRE ENTIDAD", "CODIGO CUENTA", "NOMBRE DEL CLIENTE", "MONTO APROBADO", "MONTO DESEMBOLSADO", "SALDO", "FECHA DESEMBOLSO", "FECHA VENCIMIENTO", "FECHA ULTIMO PAGO", "NUMERO DE CUOTAS"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $hojaReporte->fromArray($encabezado_tabla, null, 'A7')->getStyle('A7:P7')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $hojaReporte->mergeCells('A1:P1');
    $hojaReporte->mergeCells('A2:P2');
    $hojaReporte->mergeCells('A4:P4');
    $hojaReporte->mergeCells('A5:P5');

    //CARGAR LOS DATOS
    $sumamonto = 0;
    $sumasaldo = 0;
    $sumamora = 0;
    $fila = 0;
    $linea = 8;
    while ($fila < count($datos)) {
        // SELECT ag.cod_agenc ,pg.dfecven AS fecha, cm.CCODCTA AS cuenta, cl.idcod_cliente AS cliente, cl.short_name AS nombre, pg.SaldoCapital AS saldo, pg.nintmor AS mora, pg.NAhoProgra AS pag1, pg.OtrosPagos AS pag2, (pg.ncapita + pg.nintere) AS cuota, pg.ncapita AS capital, pg.nintere AS interes
        $hojaReporte->getStyle("A" . $linea)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        $cuenta = $datos[$fila]["cuenta"];
        $nombre = strtoupper(fpdf_text($datos[$fila]["nombre"]));
        $monapr = $datos[$fila]["monapro"];
        $mondes = $datos[$fila]["mondes"];
        $saldo = $datos[$fila]["saldo"];
        $numcuota = $datos[$fila]["noPeriodo"];
        $nombrefondos  = $datos[$fila]["descripcion"];
        $nomagencia  = $datos[$fila]["nom_agencia"];
        $nomanal  = $datos[$fila]["nomusu"];
        $nomgrupo = $datos[$fila]["NombreGrupo"];
        $tipoenti = $datos[$fila]["TipoEnti"];
        $idgrupo = ($tipoenti == "GRUP") ? $datos[$fila]["CCodGrupo"] : 0;

        $fecdes =  date("d-m-Y", strtotime($datos[$fila]["DFecDsbls"]));
        $fecven = date("d-m-Y", strtotime($datos[$fila]["DFecVen"]));
        $ultpago = $datos[$fila]["ultpag"];
        $ultpago = ($ultpago == '') ? '-' : date("d-m-Y", strtotime($ultpago));
        $formpago = $datos[$fila]["formpago"];
        $idfondos = $datos[$fila]["idfondo"];
        $idagencia = $datos[$fila]["id_agencia"];
        $codanal = $datos[$fila]["idanal"];

        $hojaReporte->setCellValueByColumnAndRow(1, $linea, $nombrefondos);
        $hojaReporte->setCellValueByColumnAndRow(2, $linea, $nomagencia);
        $hojaReporte->setCellValueByColumnAndRow(3, $linea, $nomanal);
        $hojaReporte->setCellValueByColumnAndRow(4, $linea, ($tipoenti == 'GRUP') ? $nomgrupo : 'INDIVIDUAL');
        $hojaReporte->setCellValueByColumnAndRow(5, $linea, $cuenta);
        $hojaReporte->setCellValueByColumnAndRow(6, $linea, $nombre);
        $hojaReporte->setCellValueByColumnAndRow(7, $linea, $monapr);
        $hojaReporte->setCellValueByColumnAndRow(8, $linea, $mondes);
        $hojaReporte->setCellValueByColumnAndRow(9, $linea, $saldo);
        $hojaReporte->setCellValueByColumnAndRow(10, $linea, $fecdes);
        $hojaReporte->setCellValueByColumnAndRow(11, $linea, $fecven);
        $hojaReporte->setCellValueByColumnAndRow(12, $linea, $ultpago);
        $hojaReporte->setCellValueByColumnAndRow(13, $linea, $numcuota);

        $hojaReporte->getStyle("A" . $linea . ":P" . $linea)->getFont()->setName($fuente);
        $fila++;
        $linea++;
    }

    //totales
    $hojaReporte->getColumnDimension('A')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('B')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('C')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('D')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('E')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('F')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('G')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('H')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('I')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('J')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('K')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('L')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('M')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('N')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('O')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('P')->setAutoSize(TRUE);

    //SECCION PARA DESCARGA EL ARCHIVO
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spread, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "reporte_cred_vencer_" . $otros[2],
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
}
