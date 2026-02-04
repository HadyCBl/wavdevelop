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
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Micro\Generic\Utf8;

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
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = ($radios[3] == "anyasesor") ? " AND cremi.CodAnal =" . $selects[2] : "";
//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

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
    IFNULL(ppg_ult.dfecven, '-') AS fechacalult,
    IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
    IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
    IFNULL(kar.sum_KP, 0) AS cappag,
    IFNULL(kar.sum_interes, 0) AS intpag,
    IFNULL(kar.sum_MORA, 0) AS morpag,
    IFNULL(kar.sum_AHOPRG_OTR, 0) AS otrpag,
    IFNULL(kar.dfecpro_ult, '-') AS fechaultpag,
    IFNULL((SELECT (ncapita+nintere) FROM Cre_ppg WHERE ccodcta=cremi.CCODCTA LIMIT 1),0) AS moncuota,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso,
    IFNULL(grupo.NombreGrupo, ' ') AS NombreGrupo,
    cremi.TipoEnti,
    IFNULL(cremi.CCodGrupo, ' ') AS CCodGrupo,
    cremi.Cestado,
    tcred.descr as tipocredito,
    GROUP_CONCAT(tipgar.TiposGarantia SEPARATOR ', ') AS tipo_garantia,
    IFNULL(muni.nombre,'-') as municipio_reside,
    IFNULL(cli.PEP, '-') AS ES_PEP,
    IFNULL(cli.CPE, '-') AS ES_CEP
FROM cremcre_meta cremi
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal
LEFT JOIN {$db_name_general}.tb_destinocredito dest ON dest.id_DestinoCredito=cremi.Cdescre
LEFT JOIN {$db_name_general}.`tb_cre_periodos` creper ON creper.cod_msplus=cremi.NtipPerC
LEFT JOIN {$db_name_general}.`tb_sectoreseconomicos` sector ON sector.id_SectoresEconomicos=cremi.CSecEco
LEFT JOIN {$db_name_general}.`tb_ActiEcono` actividad ON actividad.id_ActiEcono=cremi.ActoEcono
LEFT JOIN {$db_name_general}.`tb_credito` tcred ON tcred.abre = cremi.CtipCre
LEFT JOIN tb_garantias_creditos garcre ON garcre.id_cremcre_meta = cremi.ccodcta
LEFT JOIN cli_garantia clgar ON garcre.id_garantia = clgar.idGarantia
LEFT JOIN tb_municipios muni ON muni.id = cli.id_muni_reside
LEFT JOIN {$db_name_general}.tb_tiposgarantia tipgar ON clgar.idTipoGa = tipgar.id_TiposGarantia
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
    SELECT ccodcta, SUM(KP) AS sum_KP, MAX(dfecpro) AS dfecpro_ult, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? {$filfondo} {$filagencia} {$filasesor} {$status} 
GROUP BY cremi.CCODCTA ORDER BY prod.id_fondo, cremi.TipoEnti, cremi.CCodGrupo, prod.id, cremi.DFecDsbls;";

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
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
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
            $this->Cell(0, 5, 'CLASIFICACION POR FRECUENCIAS DE PAGO ' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);

            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;
            
            $this->Cell($ancho_linea*2, 5, ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2 , 5, 'FRECUENCIAS', 'B', 0, 'L');
            $this->Cell($ancho_linea+10, 5, 'NO. DE CREDITOS', 'B', 0, 'C');
            $this->Cell($ancho_linea+5, 5, 'SALDO CAPITAL', 'B', 0, 'C');
            $this->Cell($ancho_linea+5, 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea+5, 5, 'CREDITOS MOROSOS', 'B', 0, 'R');
            $this->Cell($ancho_linea+15, 5, 'SALDO CAPITAL EN MORA', 'B', 0, 'R');
            $this->Cell($ancho_linea+5, 5, 'PORCENTAJE', 'B', 1, 'R');
            $this->Ln(1);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    // Agrupar datos por frecuencia
    $frecuencias = [];
    $total_creditos = count($registro);
    $total_saldo_capital = 0;
    $total_mora_capital = 0;
    
    foreach ($registro as $fila) {
        // Calcular saldos para este crédito
        $monto = $fila["NCapDes"];
        $cappag = $fila["cappag"];
        $capcalafec = $fila["capcalafec"];
        $intcal = $fila["intcal"];
        $intpag = $fila["intpag"];
        
        // SALDO DE CAPITAL A LA FECHA
        $salcap = ($monto - $cappag);
        $salcap = ($salcap > 0) ? $salcap : 0;
        
        // CAPITAL EN MORA A LA FECHA
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;
        
        $frecuencia = $fila['frecuencia'];
        
        if (!isset($frecuencias[$frecuencia])) {
            $frecuencias[$frecuencia] = [
                'frecuencia' => $frecuencia,
                'cantidad' => 0,
                'saldo_capital' => 0,
                'creditos_morosos' => 0,
                'saldo_mora' => 0
            ];
        }
        
        $frecuencias[$frecuencia]['cantidad']++;
        $frecuencias[$frecuencia]['saldo_capital'] += $salcap;
        $total_saldo_capital += $salcap;
        
        if ($capmora > 0) {
            $frecuencias[$frecuencia]['creditos_morosos']++;
            $frecuencias[$frecuencia]['saldo_mora'] += $capmora;
            $total_mora_capital += $capmora;
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 20;
    $pdf->SetFont($fuente, '', 7);

    // Imprimir datos agrupados por frecuencia
    foreach ($frecuencias as $frec) {
        $nombre = Utf8::decode($frec["frecuencia"]);  
        $cantidad = $frec["cantidad"];  
        $saldo_capital = $frec["saldo_capital"];  
        $creditos_morosos = $frec["creditos_morosos"];  
        $saldo_mora = $frec["saldo_mora"];  

        $pdf->CellFit($ancho_linea2*2, $tamanio_linea + 1, ' ', '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 , $tamanio_linea + 1, ' ' . ($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+10, $tamanio_linea + 1, number_format($cantidad, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($saldo_capital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        
        if ($total_creditos != 0) {
            $porcentaje_kap = ($cantidad / $total_creditos) * 100;
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, number_format($porcentaje_kap, 2, '.', ',') . '%', '', 0, 'C', 0, '', 1, 0);
        } else {
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'N/A', '', 0, 'C', 0, '', 1, 0);
        }

        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($creditos_morosos, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+15, $tamanio_linea + 1, number_format($saldo_mora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        
        if ($total_mora_capital != 0) {
            $porcentaje_mora = ($saldo_mora / $total_mora_capital) * 100;
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, number_format($porcentaje_mora, 2, '.', ',') . '%', '', 0, 'C', 0, '', 1, 0);
        } else {
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'N/A', '', 0, 'C', 0, '', 1, 0);
        }

        $pdf->Ln(4);  
    }

    // Totales
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 7);
    $pdf->Cell($ancho_linea2*2, $tamanio_linea, ' ', 'T', 0, 'L');
    $pdf->Cell($ancho_linea2 *2, $tamanio_linea , 'Número de frecuencias: ' . count($frecuencias), 'T', 0, 'L', 0, '', 1, 0);
    $pdf->Cell($ancho_linea2+10, $tamanio_linea, number_format($total_creditos, 0, '.', ','), 'T', 0, 'R');
    $pdf->CellFit($ancho_linea2+5, $tamanio_linea , number_format($total_saldo_capital, 2, '.', ',') , 'T', 0, 'R', 0, '', 1, 0);
    $pdf->Cell($ancho_linea2+5, $tamanio_linea, '100.00%', 'T', 0, 'C');
    $pdf->Cell($ancho_linea2+5, $tamanio_linea, number_format(array_sum(array_column($frecuencias, 'creditos_morosos')), 0, '.', ','), 'T', 0, 'R');
    $pdf->Cell($ancho_linea2+15, $tamanio_linea, number_format($total_mora_capital, 2, '.', ','), 'T', 0, 'R');
    $pdf->Cell($ancho_linea2+5, $tamanio_linea, '100.00%', 'T', 1, 'C');

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Clasificacion por Frecuencias",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $titlereport, $usuario)
{
    require '../../../../vendor/autoload.php';

    $hoy = date("Y-m-d H:i:s");
    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("ClasificacionFrecuencias");
    
    //insertarmos la fecha y usuario
    $activa->setCellValue("A1", $hoy);
    $activa->setCellValue("A2", $usuario);

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $activa->getStyle("A1:X1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $activa->getStyle("A2:X2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $activa->getStyle("A1:X1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A2:X2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $activa->setCellValue("A4", "REPORTE");
    $activa->setCellValue("A5", strtoupper("CLASIFICACION POR FRECUENCIAS " . $titlereport));

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CRÉDITO", "FONDO", "COD CLIENTE", "GENERO", "FECHA DE NACIMIENTO", "NOMBRE DEL CLIENTE", "DIRECCION", "MUNICIPIO DE RESIDENCIA", "TEL1", "TEL2", "OTORGAMIENTO", "VENCIMIENTO","FECHA DE ULTIMO PAGO", "MONTO OTORGADO", "MONTO CUOTA", "TOTAL INTERES A PAGAR", "SALDO CAPITAL", "SALDO INTERES", "SALDO MORA", "CAPITAL PAGADO", "INTERES PAGADO", "MORA PAGADO", "OTROS", "DIAS DE ATRASO", "SALDO CAP MAS INTERES", "MORA CAPITAL", "TASA INTERES", "TASA MORA", "PRODUCTO", "AGENCIA", "ASESOR", "TIPO CREDITO", "GRUPO", "ESTADO", "DESTINO", "DIA PAGO", "FRECUENCIA", "NO CUOTAS", "FALLAS", "SECTOR ECONOMICO", "ACTIVIDAD ECONOMICA", "TIPO DE CREDITO","TIPO DE GARANTIA","¿EL CLIENTE ES PEP?","¿EL CLIENTE EL CPE?"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle('A8:AZ8')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $activa->mergeCells('A1:X1');
    $activa->mergeCells('A2:X2');
    $activa->mergeCells('A4:X4');
    $activa->mergeCells('A5:X5');

    $fila = 0;
    $i = 9;
    while ($fila < count($registro)) {
        // Extraer datos del registro
        $cuenta = $registro[$fila]["CCODCTA"];
        $nombre = Utf8::decode($registro[$fila]["short_name"]);
        $genero = $registro[$fila]["genero"];
        $date_birth = $registro[$fila]["date_birth"];
        $direccion = Utf8::decode($registro[$fila]["direccion"]);
        $tel1 = $registro[$fila]["tel1"];
        $tel2 = $registro[$fila]["tel2"];
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
        $otrpag = $registro[$fila]["otrpag"];
        $diasatr = $registro[$fila]["atraso"];
        $idfondos = $registro[$fila]["id_fondos"];
        $nombrefondos = $registro[$fila]["nombre_fondo"];
        $idproducto = $registro[$fila]["id_producto"];
        $nameproducto = $registro[$fila]["nombre_producto"];
        $CODAgencia = $registro[$fila]["CODAgencia"];
        $analista = Utf8::decode($registro[$fila]["analista"]);
        $tipoenti = $registro[$fila]["TipoEnti"];
        $nomgrupo = $registro[$fila]["NombreGrupo"];
        $estado = $registro[$fila]["Cestado"];
        $destino = Utf8::decode($registro[$fila]["destino"]);
        $diapago = $registro[$fila]["fecpago"];
        $frec = $registro[$fila]["frecuencia"];
        $ncuotas = $registro[$fila]["numcuotas"];
        $tasa = $registro[$fila]["tasa"];
        $tasamora = $registro[$fila]["tasamora"];
        $moncuota = $registro[$fila]["moncuota"];
        $fallas = $registro[$fila]["fallas"];
        $sector = Utf8::decode($registro[$fila]["sectorEconomico"]);
        $actividad = Utf8::decode($registro[$fila]["actividadEconomica"]);
        $tipocredito = Utf8::decode($registro[$fila]["tipocredito"]);
        $tipogarantia = Utf8::decode($registro[$fila]["tipo_garantia"]);
        $pep = $registro[$fila]["ES_PEP"];
        $cpe = $registro[$fila]["ES_CEP"];
        $codcliente = $registro[$fila]["codcliente"];
        $municipio = Utf8::decode($registro[$fila]["municipio_reside"]);
        $fechaultpag = $registro[$fila]["fechaultpag"];
        $fechaultpag = ($fechaultpag == '-') ? "-" : date("d-m-Y", strtotime($fechaultpag));

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
        $activa->setCellValueExplicit('B' . $i, $nombrefondos, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('C' . $i, $codcliente, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('D' . $i, strtoupper($genero), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('E' . $i, $date_birth, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('F' . $i, strtoupper($nombre), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('G' . $i, $direccion, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('H' . $i, $municipio, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('I' . $i, $tel1, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('J' . $i, $tel2, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('K' . $i, $fechades, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('L' . $i, $fechaven, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('M' . $i, $fechaultpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('N' . $i, $monto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('O' . $i, $moncuota, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('P' . $i, $intcal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('Q' . $i, $salcap, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('R' . $i, $salint, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('S' . $i, 0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('T' . $i, $cappag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('U' . $i, $intpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('V' . $i, $morpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('W' . $i, $otrpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('X' . $i, $diasatr, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('Y' . $i, ($salcap + $salint), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('Z' . $i, $capmora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('AA' . $i, $tasa, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('AB' . $i, $tasamora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('AC' . $i, strtoupper($nameproducto), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AD' . $i, $CODAgencia, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AE' . $i, strtoupper($analista), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AF' . $i, $tipoenti, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AG' . $i, $nomgrupo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AH' . $i, $estado, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AI' . $i, $destino, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AJ' . $i, $diapago, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AK' . $i, $frec, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AL' . $i, $ncuotas, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AM' . $i, $fallas, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AN' . $i, $sector, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AO' . $i, $actividad, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AP' . $i, $tipocredito, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AQ' . $i, $tipogarantia, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AR' . $i, $pep, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('AS' . $i, $cpe, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        $activa->getStyle("A" . $i . ":AS" . $i)->getFont()->setName($fuente);

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
    $sum_tasa = array_sum(array_column($registro, "tasa"));
    $sum_tasamora = array_sum(array_column($registro, "tasamora"));
    $sum_moncuota = array_sum(array_column($registro, "moncuota"));

    //insertar fila de totales
    $activa->getStyle("A" . $i . ":AS" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValueExplicit('A' . $i, "Número de créditos: " . $fila, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    $activa->mergeCells("A" . $i . ":G" . $i);

    $activa->setCellValueExplicit('N' . $i, $sum_monto, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('O' . $i, $sum_moncuota, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('P' . $i, $sum_intcal, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('Q' . $i, $sum_salcap, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('R' . $i, $sum_salint, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('S' . $i, 0, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('T' . $i, $sum_cappag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('U' . $i, $sum_intpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('V' . $i, $sum_morpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('W' . $i, $sum_otrpag, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('Y' . $i, ($sum_salcap + $sum_salint), \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('Z' . $i, $sum_capmora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('AA' . $i, $sum_tasa, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('AB' . $i, $sum_tasamora, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    //colorear la fila de totales
    $activa->getStyle("A" . $i . ":AS" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');
    
    //alinear a la derecha los totales
    $columnas = range('A', 'AS');
    foreach ($columnas as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(TRUE);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Clasificacion por Frecuencias " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}