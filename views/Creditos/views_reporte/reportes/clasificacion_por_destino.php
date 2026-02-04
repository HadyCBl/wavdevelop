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

use Complex\Functions;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Micro\Generic\Utf8;
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
if (isset($radios[3]) && $radios[3] == "anyasesor" && isset($selects[2]) && $selects[2] == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Asesor']);
    return;
}

$regionRadio = $radios[3] ?? null;
$regionId = isset($selects[2]) ? (int)$selects[2] : 0;
if ($regionRadio === "anyregion" && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    return;
}

$mostrarRegionCol = false;
$regionNombre = '';
$agenciaIdSeleccionada = (isset($radios[0]) && $radios[0] === 'anyofi' && isset($selects[0])) ? (int)$selects[0] : 0;


//*****************ARMANDO LA CONSULTA**************
$filtrofecha = $inputs[0];
$titlereport = " AL " . date("d-m-Y", strtotime($filtrofecha));

//FUENTE DE FONDOS
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";

//AGENCIA
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = (isset($radios[3]) && $radios[3] == "anyasesor") ? " AND cremi.CodAnal =" . $selects[2] : "";

//STATUS
$status = ($radios[2] == "allstatus") ? " " : (($radios[2] == "F") ? " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 " : " AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) <= 0");

//REGION (opcional)
$filregion = ($regionRadio === "anyregion" && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $regionId . ")"
    : "";

// Query basado en cartera_fondos.php pero agrupado por destino
$strquery = "SELECT 
    cremi.Cdescre,
    dest.DestinoCredito AS destino_credito,
    cremi.CCODCTA,
    cremi.NCapDes,
    -- Datos de subconsultas igual que cartera_fondos.php
    IFNULL(ppg.sum_nintere, 0) AS intcal,
    IFNULL(ppg_ult.sum_ncapita, 0) AS capcalafec,
    IFNULL(ppg_ult.sum_nintere, 0) AS intcalafec,
    IFNULL(kar.sum_KP, 0) AS cappag,
    IFNULL(kar.sum_interes, 0) AS intpag,
    IFNULL(kar.sum_MORA, 0) AS morpag,
    CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso
FROM cremcre_meta cremi
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
LEFT JOIN {$db_name_general}.tb_destinocredito dest ON dest.id_DestinoCredito = cremi.Cdescre
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven,
    COUNT(IF(cflag = 0 AND cflag IS NOT NULL, 1, NULL)) AS cflag, 
    SUM(nintere) AS sum_nintere 
    FROM Cre_ppg 
    GROUP BY ccodcta
) AS ppg ON ppg.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, MAX(dfecven) AS dfecven, 
    SUM(ncapita) AS sum_ncapita, 
    SUM(nintere) AS sum_nintere
    FROM Cre_ppg
    WHERE dfecven <= ?
    GROUP BY ccodcta
) AS ppg_ult ON ppg_ult.ccodcta = cremi.CCODCTA
LEFT JOIN (
    SELECT ccodcta, 
    SUM(KP) AS sum_KP, 
    MAX(dfecpro) AS dfecpro_ult, 
    SUM(interes) AS sum_interes, 
    SUM(MORA) AS sum_MORA, 
    SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G') AND cremi.DFecDsbls <= ? {$filfondo} {$filagencia} {$filasesor} {$filregion} {$status} 
ORDER BY dest.DestinoCredito, cremi.CCODCTA";
$showmensaje = false;
try {
    $database->openConnection();

    // Determinar región (solo si el reporte queda acotado a una sola región)
    if ($regionRadio === 'anyregion' && $regionId > 0) {
        $reg = $database->getAllResults('SELECT nombre FROM cre_regiones WHERE id = ? LIMIT 1', [$regionId]);
        if (!empty($reg) && isset($reg[0]['nombre'])) {
            $regionNombre = (string)$reg[0]['nombre'];
            $mostrarRegionCol = true;
        }
    } elseif ($agenciaIdSeleccionada > 0) {
        $reg = $database->getAllResults(
            'SELECT r.nombre FROM cre_regiones_agencias ra INNER JOIN cre_regiones r ON r.id = ra.id_region WHERE ra.id_agencia = ? ORDER BY r.estado DESC, r.nombre LIMIT 1',
            [$agenciaIdSeleccionada]
        );
        if (!empty($reg) && isset($reg[0]['nombre'])) {
            $regionNombre = (string)$reg[0]['nombre'];
            $mostrarRegionCol = true;
        }
    }
    
    // Parámetros para la consulta - igual que cartera_fondos.php (4 parámetros)
    $params = [$filtrofecha, $filtrofecha, $filtrofecha, $filtrofecha];
    
    $result = $database->getAllResults($strquery, $params);
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
    
    // Procesar resultados aplicando EXACTAMENTE los mismos cálculos que cartera_fondos.php
    // CRÍTICO: Calcular saldos individualmente por crédito, NO por SUM en SQL
    $data_por_destino = [];
    $no_creditos_global = 0;
    $total_kap = 0.0;
    $total_mora = 0.0;
    $total_Cmora = 0;
    $mora_global = 0;
    
    foreach ($result as $row) {
        // APLICAR EXACTAMENTE LA MISMA LÓGICA QUE cartera_fondos.php líneas 343-352
        $monto = (float)$row['NCapDes'];
        $intcal = (float)$row['intcal'];
        $capcalafec = (float)$row['capcalafec'];
        $cappag = (float)$row['cappag'];
        $intpag = (float)$row['intpag'];
        $morpag = (float)$row['morpag'];
        $atraso = (int)$row['atraso'];
        
        // SALDO DE CAPITAL A LA FECHA - EXACTO como cartera_fondos.php
        $salcap = ($monto - $cappag);
        $salcap = ($salcap > 0) ? $salcap : 0;

        // SALDO DE INTERES A LA FECHA - EXACTO como cartera_fondos.php
        $salint = ($intcal - $intpag);
        $salint = ($salint > 0) ? $salint : 0;

        // CAPITAL EN MORA A LA FECHA - EXACTO como cartera_fondos.php
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;
        
        // Determinar destino
        $destino = $row['destino_credito'] ?? 'SIN DESTINO';
        
        // Inicializar destino si no existe
        if (!isset($data_por_destino[$destino])) {
            $data_por_destino[$destino] = [
                'destino_credito' => $destino,
                'cantidad_creditos' => 0,
                'saldo_capital_calculado' => 0.0,
                'saldo_interes_calculado' => 0.0,
                'capital_mora_calculado' => 0.0,
                'cantidad_creditos_mora' => 0,
                'total_dias_atraso' => 0
            ];
        }
        
        // Acumular valores por destino - manteniendo precisión exacta
        $data_por_destino[$destino]['cantidad_creditos'] += 1;
        $data_por_destino[$destino]['saldo_capital_calculado'] += $salcap;
        $data_por_destino[$destino]['saldo_interes_calculado'] += $salint;
        $data_por_destino[$destino]['capital_mora_calculado'] += $capmora;
        
        if ($atraso > 0) {
            $data_por_destino[$destino]['cantidad_creditos_mora'] += 1;
            $data_por_destino[$destino]['total_dias_atraso'] += $atraso;
        }
        
        // Acumular totales globales con valores exactos
        $no_creditos_global += 1;
        $total_kap += $salcap;
        $total_mora += $capmora;
        if ($atraso > 0) {
            $total_Cmora += 1;
            $mora_global += $atraso;
        }
    }
    
    // Convertir a array para compatibilidad con funciones existentes
    $data = array_values($data_por_destino);
    
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
        printxls($data, $titlereport, $archivo[0], $mostrarRegionCol, $regionNombre);
        break;
    case 'pdf':
        printpdf($data, [$titlereport], $info,  $no_creditos_global,$mora_global,$total_kap,$total_mora,$total_Cmora, $mostrarRegionCol, $regionNombre );
        break;
}

//funcion para generar pdf
function printpdf($registro, $datos, $info, $no_creditos_global,$mora_global,$total_kap,$total_mora,$total_Cmora, $mostrarRegionCol = false, $regionNombre = '' )
{
    /*     $oficina = "Coban";
    $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    $direccionins = "Canton vipila zona 1";
    $emailins = "fape@gmail.com";
    $telefonosins = "502 43987876";
    $nitins = "1323244234";
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../../includes/img/fape.jpeg"; */


    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
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
        public $mostrarRegionCol;
        public $regionNombre;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $no_creditos_global, $mostrarRegionCol, $regionNombre)
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
            $this->mostrarRegionCol = (bool)$mostrarRegionCol;
            $this->regionNombre = (string)$regionNombre;
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
            $this->Cell(0, 5, 'CLASIFICACION POR DESTINO' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;
            $this->Cell($ancho_linea * 6 + 15, 5, ' ', '', 0, 'L');
            $this->Cell($ancho_linea * 3 - 2, 5, ' ',0, 1, 'C');

            $this->Cell($ancho_linea*2, 5, $this->mostrarRegionCol ? 'REGION' : ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2 , 5, 'DESTINO', 'B', 0, 'L');
            $this->Cell($ancho_linea+10, 5, 'NO. DE CREDITOS', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'SALDO CAPITAL', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea*2-10, 5, 'CREDITOS MOROSOS', 'B', 0, 'R');
            $this->Cell($ancho_linea*2 -5 , 5, 'CAPITAL EN MORA', 'B', 0, 'R');
            $this->Cell($ancho_linea , 5, 'PORCENTAJE', 'B', 0, 'R');
            $this->Cell($ancho_linea+10, 5, ' ', 'B', 0, 'L');
            $this->Cell($ancho_linea / 2, 5, ' ', 0, 1, 'R');
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos,  $no_creditos_global, $mostrarRegionCol, $regionNombre);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 20;
    $pdf->SetFont($fuente, '', 7);
    $aux = 0;
    $auxgrupo = 0;
    $fila = 0;
    // totales - mantener precisión exacta para contabilidad
    $sum_mora  = 0.0;
    $sum_cant = 0.0;

    while ($fila < count($registro)) {
        $nombre = Utf8::decode($registro[$fila]["destino_credito"]);  
        $cantidad = (int)$registro[$fila]["cantidad_creditos"];  
        $cantidad_Ncapdes = (float)$registro[$fila]["saldo_capital_calculado"];  // Mantener precisión exacta
        $cantidad_Mora = (int)$registro[$fila]["cantidad_creditos_mora"];  
        $cantidad_Mora_kap = (float)$registro[$fila]["capital_mora_calculado"];  // Mantener precisión exacta

        //TITULO GRUPO
        $pdf->CellFit($ancho_linea2*2, $tamanio_linea + 1, ($mostrarRegionCol ? Utf8::decode($regionNombre) : ' '), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2 , $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        // Mostrar con 2 decimales pero mantener precisión interna exacta
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad_Ncapdes, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_kap=($cantidad/$no_creditos_global)*100;
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($porcentaje_kap, 2, '.', ',') . ' %', 'R', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($cantidad_Mora, 0, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2+15, $tamanio_linea + 1, number_format($cantidad_Mora_kap, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $porcentaje_mora = ($total_mora > 0) ? ($cantidad_Mora_kap/$total_mora)*100 : 0;
        $pdf->CellFit($ancho_linea2+5, $tamanio_linea + 1, number_format($porcentaje_mora, 2, '.', ',') . ' %', 0, 0, 'C', 0, '', 1, 0);

        // Sumar valores exactos sin redondear
        $sum_cant += $cantidad_Ncapdes;
        $sum_mora += $cantidad_Mora;

        $pdf->Ln(4);  
        $fila++;

    }
    $pdf->Ln(2);
    $pdf->Cell($ancho_linea2*2, $tamanio_linea, ' ', 'T', 0, 'L');
    $pdf->Cell($ancho_linea2 *2, $tamanio_linea , 'Numero de destinos: ' . $fila, 'T', 0, 'L', 0, '', 1, 0);
    $pdf->Cell($ancho_linea2+5, $tamanio_linea, $no_creditos_global, 'T', 0, 'R');
    // Usar totales exactos calculados sin redondear internamente
    $pdf->CellFit($ancho_linea2+8, $tamanio_linea , number_format($total_kap, 2, '.', ',') , 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Cell($ancho_linea2-5, $tamanio_linea, ' ', 'T', 0, 'R');
    $pdf->Cell($ancho_linea2+12, $tamanio_linea, $total_Cmora, 'T', 0, 'R');

    $pdf->Cell($ancho_linea2+15, $tamanio_linea, number_format($total_mora, 2, '.', ','), 'T', 0, 'R');
    $pdf->Cell($ancho_linea2*2+10, $tamanio_linea, ' ', 'T', 0, 'R');


    /*FIN PRODUCTOS */
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Clasificación por destino",
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
function printxls($registro, $titlereport, $usuario, $mostrarRegionCol = false, $regionNombre = '')
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
    $activa->setCellValue("A5", strtoupper("CLASIFICACIÓN POR DESTINO " . $titlereport));

    //TITULO DE RECARGOS


    # Escribir encabezado de la tabla para resumen por destino
    $encabezado_tabla = ["DESTINO", "NO. DE CREDITOS", "SALDO CAPITAL", "CREDITOS EN MORA", "CAPITAL EN MORA"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    if ($mostrarRegionCol) {
        $encabezado_tabla[] = 'REGION';
    }
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle($mostrarRegionCol ? 'A8:F8' : 'A8:E8')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $activa->mergeCells($mostrarRegionCol ? 'A1:F1' : 'A1:E1');
    $activa->mergeCells($mostrarRegionCol ? 'A2:F2' : 'A2:E2');
    $activa->mergeCells($mostrarRegionCol ? 'A4:F4' : 'A4:E4');
    $activa->mergeCells($mostrarRegionCol ? 'A5:F5' : 'A5:E5');

    $fila = 0;
    $i = 9;
    while ($fila < count($registro)) {
        $destino = $registro[$fila]["destino_credito"] ?? "SIN DESTINO";
        $cantidad_registros = $registro[$fila]["cantidad_creditos"];
        $saldo_capital = $registro[$fila]["saldo_capital_calculado"];
        $cantidad_mora = $registro[$fila]["cantidad_creditos_mora"];
        $capital_mora = $registro[$fila]["capital_mora_calculado"];

        $activa->setCellValue('A' . $i, strtoupper($destino));
        $activa->setCellValue('B' . $i, $cantidad_registros);
        $activa->setCellValue('C' . $i, $saldo_capital);
        $activa->setCellValue('D' . $i, $cantidad_mora);
        $activa->setCellValue('E' . $i, $capital_mora);
        if ($mostrarRegionCol) {
            $activa->setCellValue('F' . $i, $regionNombre);
            $activa->getStyle("A" . $i . ":F" . $i)->getFont()->setName($fuente);
        } else {
            $activa->getStyle("A" . $i . ":E" . $i)->getFont()->setName($fuente);
        }

        $fila++;
        $i++;
    }
    //total de registros - mantener precisión exacta para contabilidad
    $sum_cantidad = array_sum(array_column($registro, "cantidad_creditos"));
    $sum_saldo_capital = array_sum(array_column($registro, "saldo_capital_calculado")); // Suma exacta sin redondear
    $sum_mora_cantidad = array_sum(array_column($registro, "cantidad_creditos_mora"));
    $sum_capital_mora = array_sum(array_column($registro, "capital_mora_calculado")); // Suma exacta sin redondear

    $activa->getStyle($mostrarRegionCol ? ("A" . $i . ":F" . $i) : ("A" . $i . ":E" . $i))->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    $activa->setCellValue('A' . $i, "TOTALES:");

    $activa->setCellValue('B' . $i, $sum_cantidad);
    // Mostrar valores exactos con precisión decimal completa
    $activa->setCellValue('C' . $i, $sum_saldo_capital);
    $activa->setCellValue('D' . $i, $sum_mora_cantidad);
    $activa->setCellValue('E' . $i, $sum_capital_mora);

    $activa->getStyle("A" . $i . ":E" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    $columnas = range('A', 'E');
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
        'namefile' => "Clasificación por destino " . $titlereport,
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}