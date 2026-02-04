<?php
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

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
// ini_set('memory_limit', '4096M');
// ini_set('max_execution_time', '3600');

use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$archivo = $datos[3];

$tipo = $_POST["tipo"];

$fecinicio = $inputs[0];
$fecfin = $inputs[1];
$estadoFactura = $selects[0];
$tipoFactura = $archivo[0];

if (!validateDate($fecinicio, 'Y-m-d') || !validateDate($fecfin, 'Y-m-d')) {
    echo json_encode(['status' => 0, 'mensaje' => 'Fecha inválida, ingrese una fecha correcta']);
    return;
}
//validar la fecha que no sea mayor al dia de hoy
if ($fecinicio > $hoy || $fecfin > $hoy) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha digitada no pueder ser mayor que la fecha actual']);
    return;
}
$condiciones = ($estadoFactura == '0') ? "" : " AND fac.estado=$estadoFactura";
$condiciones .= ($tipoFactura == '0') ? "" : " AND fac.tipo=$tipoFactura";

$titlereport = ($tipoFactura == '0') ? "" : (($tipoFactura == 1) ? "DE VENTAS" : "DE COMPRAS");

$strquery = "SELECT fac.id,fac.tipo,fac.fechahora_emision,fac.codigo_autorizacion,td.codigo,fac.serie,fac.no_autorizacion,em.nit AS nit_emisor,em.nombre AS nombre_emisor,
                        cod_establecimiento,nombre_comercial,rec.id_receptor,rec.nombre AS nombre_receptor, cert.nit AS nit_certificador,cert.nombre AS nombre_certificador,fac.estado,
                        IFNULL((SELECT abr FROM $db_name_general.tb_monedas WHERE id=fac.id_moneda),'-') AS id_moneda,
                        IFNULL((SELECT SUM(total) FROM cv_factura_items WHERE id_factura=fac.id),0) AS monto,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 1 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_iva,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 2 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_petroleo,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 3 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_tur_hos,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 4 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_tur_pas,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 5 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_timbre,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 6 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_bomberos,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 7 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_tasamuni,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 8 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_bebidas,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 9 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_tabaco,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 10 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_cemento,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 11 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_bebidasno,
                        IFNULL(SUM(CASE WHEN ig.id_cvimpuestostipo = 12 THEN fitim.monto_impuesto ELSE 0 END), 0) AS monto_portuaria
                    FROM cv_facturas fac
                    INNER JOIN cv_tiposdte td ON fac.id_tipo = td.id
                    INNER JOIN cv_receptor rec ON fac.id_receptor = rec.id
                    INNER JOIN cv_emisor em ON fac.id_emisor = em.id
                    INNER JOIN cv_certificador cert ON fac.id_certificador = cert.id
                    LEFT JOIN cv_factura_items fit ON fit.id_factura = fac.id
                    LEFT JOIN cv_facturaitems_impuestos fitim ON fitim.id_factura_items = fit.id
                    LEFT JOIN cv_impuestosunidadgravable ig ON ig.id = fitim.id_impuestos_unidadgravable
                    WHERE fac.estado IN (1, 2) AND DATE(fechahora_emision) BETWEEN ? AND ? $condiciones
                    GROUP BY fac.id, fac.fechahora_emision, fac.codigo_autorizacion, td.codigo, fac.serie, fac.no_autorizacion
                    ORDER BY fac.fechahora_emision, fac.id;";

//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$fecinicio, $fecfin]);
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

// echo json_encode(['status' => 0, 'mensaje' => $strquery]);
//     return;

switch ($tipo) {
    case 'xlsx';
        printxls($result, [$titlereport, $idusuario]);
        break;
    case 'pdf':
        printpdf($result, [$titlereport, $idusuario], $info);
        break;
    case 'show':
        showresults($data);
        break;
}
function showresults($registro)
{
    $valores[] = [];
    $i = 0;
    foreach ($registro as $fila) {
        $fila["DFecDsbls"] = date("d-m-Y", strtotime($fila["DFecDsbls"]));
        $fila["fechaultpag"] = ($fila["fechaultpag"] != "0") ? date("d-m-Y", strtotime($fila["fechaultpag"])) : "-";
        $fila["fechaven"] = ($fila["fechaven"] != "0") ? date("d-m-Y", strtotime($fila["fechaven"])) : "-";

        $monto = $fila["NCapDes"];
        $intcal = $fila["intcal"];
        $capcalafec = $fila["capcalafec"];
        $intcalafec = $fila["intcalafec"];
        $cappag = $fila["cappag"];
        $intpag = $fila["intpag"];

        //SALDO DE CAPITAL A LA FECHA
        $salcap = ($monto - $cappag);
        $salcap = ($salcap > 0) ? $salcap : 0;
        $fila["salcap"] = number_format($salcap, 2);

        //SALDO DE INTERES A LA FECHA
        $salint = ($intcal - $intpag);
        $salint = ($salint > 0) ? $salint : 0;
        $fila["salint"] = number_format($salint, 2);

        //CAPITAL EN MORA A LA FECHA
        $capmora = $capcalafec - $cappag;
        $capmora = ($capmora > 0) ? $capmora : 0;
        $fila["capenmora"] = number_format($capmora, 2);

        //INTERES ATRASADO A LA FECHA
        $intatrasado = $intcalafec - $intpag;
        $intatrasado = ($intatrasado > 0) ? $intatrasado : 0;
        $fila["intatrasado"] = number_format($intatrasado, 2);

        $valores[$i] = $fila;
        $i++;
    }

    $keys = ["nom_agencia", "analista", "CCODCTA", "short_name", "DFecDsbls", "fechaven", "NCapDes", "salcap", "capenmora", "intatrasado", "intmora", "atraso"];
    $encabezados = ["Agencia", "Asesor", "Cuenta", "Nombre Cliente", "Fec. Inicio", "Fec. Vence", "Monto", "Saldo kp", "Kp. Mora", "Int. Corr.", "Mora", "Días"];

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'data' => $valores,
        'keys' => $keys,
        'encabezados' => $encabezados,
    );
    echo json_encode($opResult);
    return;
}
//funciones de cada reporte
function printpdf($registro, $datos, $info)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];
    $usuario = $datos[0];

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
        public $usuario;
        public $valida;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $usuario)
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
            $this->usuario = $usuario;
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
            $this->Cell(0, 5, 'FACTURAS ' . $this->datos[0], 0, 1, 'C', true);
            $this->Ln(2);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 7);
            $ancho_linea = 20;

            $this->Cell($ancho_linea - 5, 5, 'Fecha', 'B', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, 'Autorizacion', 'B', 0, 'L');
            $this->Cell($ancho_linea / 2, 5, 'TIPO', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'SERIE', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'NO. DTE', 'B', 0, 'C');
            $this->Cell($ancho_linea - 5, 5, 'NIT EMISOR', 'B', 0, 'C');
            $this->Cell($ancho_linea * 2, 5, 'NOMBRE EMISOR', 'B', 0, 'C');
            // $this->Cell($ancho_linea * 2, 5, 'ESTABLECIMIENTO', 'B', 0, 'C');
            $this->Cell($ancho_linea - 5, 5, 'ID RECEPTOR', 'B', 0, 'C');
            $this->Cell($ancho_linea * 3, 5, 'NOMBRE RECEPTOR', 'B', 0, 'C');
            // $this->Cell($ancho_linea, 5, 'NIT CERTIFICADOR', 'B', 0, 'C');
            // $this->Cell($ancho_linea, 5, 'NOMBRE CERTIFICADOR', 'B', 0, 'C');
            $this->Cell($ancho_linea, 5, 'MONTO', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'IVA', 'B', 1, 'R');
            $this->Ln(5);
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos, $usuario);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 20;
    $pdf->SetFont($fuente, '', 7);

    $fila = 0;
    foreach ($registro as $fila) {
        $id = $fila["id"];
        $fechahora_emision = (date('d-m-Y', strtotime($fila["fechahora_emision"])));
        $codigo_autorizacion = $fila["codigo_autorizacion"];
        $codigo = $fila["codigo"];
        $serie = $fila["serie"];
        $no_autorizacion = $fila["no_autorizacion"];
        $nit_emisor = $fila["nit_emisor"];
        $nombre_emisor = $fila["nombre_emisor"];
        $cod_establecimiento = $fila["cod_establecimiento"];
        $nombre_comercial = $fila["nombre_comercial"];
        $id_receptor = $fila["id_receptor"];
        $nombre_receptor = Utf8::decode($fila["nombre_receptor"]);
        $nit_certificador = $fila["nit_certificador"];
        $nombre_certificador = $fila["nombre_certificador"];
        $id_moneda = $fila["id_moneda"];
        $monto = $fila["monto"];
        $monto_impuesto = $fila["monto_iva"];

        $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, $fechahora_emision, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, $codigo_autorizacion, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea, $codigo, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $serie, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $no_autorizacion, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, $nit_emisor, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, $nombre_emisor, 0, 0, 'C', 0, '', 1, 0);
        // $pdf->CellFit($ancho_linea2, $tamanio_linea, $cod_establecimiento, 0, 0, 'C', 0, '', 1, 0);
        // $pdf->CellFit($ancho_linea2*2, $tamanio_linea, $nombre_comercial, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $id_receptor, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 3, $tamanio_linea, $nombre_receptor, 0, 0, 'C', 0, '', 1, 0);
        // $pdf->CellFit($ancho_linea2, $tamanio_linea, $nit_certificador, 0, 0, 'C', 0, '', 1, 0);
        // $pdf->CellFit($ancho_linea2, $tamanio_linea, $nombre_certificador, 0, 0, 'C', 0, '', 1, 0);
        // $pdf->CellFit($ancho_linea2, $tamanio_linea, $id_moneda, 0, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $monto, 0, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $monto_impuesto, 0, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(5);
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 7);
    $nofacturas = count($registro);
    $monto_total = array_sum(array_column($registro, 'monto'));
    $impuesto_total = array_sum(array_column($registro, 'monto_impuesto'));

    $pdf->CellFit($ancho_linea2 * 12, $tamanio_linea + 1, 'Numero de facturas: ' . $nofacturas, 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($monto_total, 5, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($impuesto_total, 5, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Facturas",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
function printxls($registro, $data)
{
    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    // $tamanioEncabezado = 14;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Facturas");

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $activa->getStyle("A1:AC1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);


    # Escribir encabezado de la tabla
    $encabezado_tabla = ["Fecha de emisión", "Numero de autorizacion", "Tipo de DTE", "Serie", "Numero de DTE", "NIT del Emisor", "Nombre Emisor", 
    "Cod Establecimiento", "Nombre del Establecimiento", "ID del receptor", "Nombre completo del receptor", "NIT del certificador", 
    "Nombre completo del certificador", "Moneda", "Estado", "Monto (Gran Total)", "Tipo Movimiento", "IVA","Petróleo","Turismo Hospedaje",
    "Turismo Pasajes","Timbre de prensa","Bomberos","Tasa municipal","Bebidas alcoholicas","Tabaco","Cemento","Bebidas alcoholicas","Tarifa portuaria"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle('A1:AC1')->getFont()->setName($fuente)->setBold(true);

    $i = 2;
    // VARIABLES DE SUMA TOTALES
    foreach ($registro as $fila) {
        $id = $fila["id"];
        $fechahora_emision = $fila["fechahora_emision"];
        $codigo_autorizacion = $fila["codigo_autorizacion"];
        $codigo = $fila["codigo"];
        $serie = $fila["serie"];
        $no_autorizacion = $fila["no_autorizacion"];
        $nit_emisor = $fila["nit_emisor"];
        $nombre_emisor = $fila["nombre_emisor"];
        $cod_establecimiento = $fila["cod_establecimiento"];
        $nombre_comercial = $fila["nombre_comercial"];
        $id_receptor = $fila["id_receptor"];
        $nombre_receptor = ($fila["nombre_receptor"]);
        $nit_certificador = $fila["nit_certificador"];
        $nombre_certificador = $fila["nombre_certificador"];
        $id_moneda = $fila["id_moneda"];
        $monto = $fila["monto"];
        $monto_iva = $fila["monto_iva"];
        $monto_petroleo = $fila["monto_petroleo"];
        $monto_tur_hos = $fila["monto_tur_hos"];
        $monto_tur_pas = $fila["monto_tur_pas"];
        $monto_timbre = $fila["monto_timbre"];
        $monto_bomberos = $fila["monto_bomberos"];
        $monto_tasamuni = $fila["monto_tasamuni"];
        $monto_bebidas = $fila["monto_bebidas"];
        $monto_tabaco = $fila["monto_tabaco"];
        $monto_cemento = $fila["monto_cemento"];
        $monto_bebidasno = $fila["monto_bebidasno"];
        $monto_portuaria = $fila["monto_portuaria"];
        $estado = ($fila["estado"] == 1) ? "Vigente" : "Anulado";
        $tipofac = ($fila["tipo"] == 1) ? "Venta" : "Compra";


        $activa->setCellValue('A' . $i, strtoupper($fechahora_emision));
        $activa->setCellValueExplicit('B' . $i, $codigo_autorizacion, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('C' . $i, $codigo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('D' . $i, $serie, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('E' . $i, $no_autorizacion, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('F' . $i, $nit_emisor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('G' . $i, $nombre_emisor);
        $activa->setCellValue('H' . $i, $cod_establecimiento);
        $activa->setCellValue('I' . $i, $nombre_comercial);
        $activa->setCellValueExplicit('J' . $i, $id_receptor, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('K' . $i, $nombre_receptor);
        $activa->setCellValueExplicit('L' . $i, $nit_certificador, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('M' . $i, $nombre_certificador);
        $activa->setCellValue('N' . $i, $id_moneda);
        $activa->setCellValue('O' . $i, $estado);
        $activa->setCellValue('P' . $i, $monto);
        $activa->setCellValue('Q' . $i, $tipofac);
        $activa->setCellValue('R' . $i, $monto_iva);
        $activa->setCellValue('S' . $i, $monto_petroleo);
        $activa->setCellValue('T' . $i, $monto_tur_hos);
        $activa->setCellValue('U' . $i, $monto_tur_pas);
        $activa->setCellValue('V' . $i, $monto_timbre);
        $activa->setCellValue('W' . $i, $monto_bomberos);
        $activa->setCellValue('X' . $i, $monto_tasamuni);
        $activa->setCellValue('Y' . $i, $monto_bebidas);
        $activa->setCellValue('Z' . $i, $monto_tabaco);
        $activa->setCellValue('AA' . $i, $monto_cemento);
        $activa->setCellValue('AB' . $i, $monto_bebidasno);
        $activa->setCellValue('AC' . $i, $monto_portuaria);
        $i++;
    }

    $i++;
    //hacer pequeño las letras del encabezado de titulo
    $activa->getStyle("A2:AC" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente);

    // $activa->getStyle("A" . $i . ":R" . $i)->getFont()->setSize($tamanioTabla)->setName($fuente)->setBold(true);
    // $activa->setCellValueExplicit('A' . $i, "Número de créditos: " . $fila, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
    // $activa->mergeCells("A" . $i . ":H" . $i);

    // $activa->getStyle("L" . $i)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
    // $activa->getStyle("M" . $i)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
    // $activa->getStyle("N" . $i)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
    // $activa->getStyle("O" . $i)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
    // $activa->getStyle("P" . $i)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
    // $activa->getStyle("Q" . $i)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);

    // $activa->setCellValue('L' . $i, $sum_montos);
    // $activa->setCellValue('M' . $i, $sum_salcap);
    // $activa->setCellValue('N' . $i, $sum_capmora);
    // $activa->setCellValue('O' . $i, $sumintatrasado);
    // $activa->setCellValue('P' . $i, $sumintmora);
    // $activa->setCellValue('Q' . $i, ($sum_capmora + $sumintatrasado + $sumintmora));

    // $activa->getStyle("A" . $i . ":R" . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFF00');

    $columnas = range('A', 'R');
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
        'namefile' => "facturas",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
