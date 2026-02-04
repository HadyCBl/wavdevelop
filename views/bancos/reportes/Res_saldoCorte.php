<?php
session_start();
include '../../../src/funcphp/func_gen.php';
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

require '../../../fpdf/fpdf.php';
require '../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Fecha actual (para otros usos)
$hoy = date("Y-m-d");

// Verificar que la sesión tenga el ID de la agencia
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode([
        'status'  => 0,
        'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente'
    ]);
    return;
}

//===================================================
// 1. RECIBIR DATOS POR POST
//===================================================
$datos   = $_POST["datosval"];
$inputs  = $datos[0];    // [0]: Fecha inicio, [1]: Fecha de corte, [2]: (opcional) id_ctb_nomenclatura
$selects = $datos[1];    // Por ejemplo, código de oficina y fuente de fondos
$radios  = $datos[2];    // Filtros: cuenta, agencia, fondos
$tipo    = $_POST["tipo"]; // Tipo de salida: pdf, xlsx, show

// Validar que el rango de fechas sea correcto
if ($inputs[0] > $inputs[1]) {
    echo json_encode([
        'status'  => 0,
        'mensaje' => 'Rango de fechas inválido'
    ]);
    return;
}

//===================================================
// 2. CONFIGURACIÓN DEL REPORTE
//===================================================
$fechaCorte  = $inputs[1]; // Se usa como fecha de corte
$titlereport = " A FECHA " . date("d-m-Y", strtotime($fechaCorte));

// Armar condiciones según filtros
$condi = "";
if ($radios[2] == "anyofi") {
    $condi .= " AND id_agencia=" . $selects[0];
}
if ($radios[1] == "anyf") {
    $condi .= " AND id_fuente_fondo=" . $selects[1];
}
if ($radios[0] == "anycuen") {
    $condi .= " AND id_ctb_nomenclatura=" . $inputs[2];
}
// Limitar movimientos hasta la fecha de corte
$condi .= " AND fecdoc <= '$fechaCorte'";

//===================================================
// 3. CONSULTA SQL: RESUMEN DE SALDOS (LIBRO)
//===================================================
$strquery = "
    SELECT 
        nom.id AS id_ctb_nomenclatura,
        nom.ccodcta,
        nom.cdescrip,
        SUM(cmov.debe) AS total_debe,
        SUM(cmov.haber) AS total_haber,
        SUM(cmov.debe - cmov.haber) AS saldo
    FROM ctb_diario_mov cmov
    INNER JOIN ctb_nomenclatura nom ON nom.id = cmov.id_ctb_nomenclatura
    INNER JOIN ctb_bancos ban ON ban.id_nomenclatura = nom.id AND ban.estado = 1
    WHERE cmov.estado = 1
      AND cmov.id_tipopol != 9
      $condi
    GROUP BY nom.id, nom.ccodcta, nom.cdescrip
    ORDER BY nom.ccodcta;
";

$query = mysqli_query($conexion, $strquery);
$resumenData = array();
while ($fila = mysqli_fetch_array($query)) {
    $resumenData[] = $fila;
}

if (count($resumenData) == 0) {
    echo json_encode([
        'status'  => 0,
        'mensaje' => 'No hay datos'
    ]);
    return;
}

//===================================================
// 4. OBTENER INFORMACIÓN DE LA INSTITUCIÓN
//===================================================
$queryins = mysqli_query($conexion, "
    SELECT * 
    FROM $db_name_general.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop 
    WHERE ag.id_agencia = " . $_SESSION['id_agencia']
);
$info = array();
while ($fila = mysqli_fetch_array($queryins)) {
    $info[] = $fila;
}
if (count($info) == 0) {
    echo json_encode([
        'status'  => 0,
        'mensaje' => 'Institución asignada a la agencia no encontrada'
    ]);
    return;
}

//===================================================
// 5. SELECCIONAR TIPO DE SALIDA (PDF, XLSX o SHOW)
//===================================================
switch ($tipo) {
    case 'xlsx':
        printxls($resumenData, $titlereport, $info);
        break;
    case 'pdf':
        printpdf($resumenData, $titlereport, $info);
        break;
    case 'show':
        showresults($resumenData);
        break;
}

//===================================================
// Función: Mostrar resultados en JSON (opcional)
//===================================================
function showresults($registro) {
    $valores = array();
    foreach ($registro as $fila) {
        $fila["total_debe"]  = number_format($fila["total_debe"], 2, '.', ',');
        $fila["total_haber"] = number_format($fila["total_haber"], 2, '.', ',');
        $fila["saldo"]       = number_format($fila["saldo"], 2, '.', ',');
        $valores[] = $fila;
    }
    $keys        = ["ccodcta", "cdescrip", "total_debe", "total_haber", "saldo"];
    $encabezados = ["CUENTA", "NOMBRE", "TOTAL DEBE", "TOTAL HABER", "SALDO"];
    $opResult    = array(
        'status'      => 1,
        'mensaje'     => 'Reporte generado correctamente',
        'data'        => $valores,
        'keys'        => $keys,
        'encabezados' => $encabezados,
    );
    echo json_encode($opResult);
    return;
}

//===================================================
// Función: Generar PDF (Resumen de Saldos de Cuentas de Bancos)
//===================================================
function printpdf($registro, $titlereport, $info) {
    // Datos de la institución
    $oficina      = decode_utf8($info[0]["nom_agencia"]);
    $institucion  = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins     = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins       = $info[0]["nit"];
    $rutalogomicro= "../../../includes/img/logomicro.png";
    $rutalogoins  = "../../.." . $info[0]["log_img"];

    // Clase PDF personalizada (Extiende de FPDF)
    class PDF extends FPDF {
        public $institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos;
        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos) {
            parent::__construct('L', 'mm', 'A4');
            $this->institucion = $institucion;
            $this->pathlogo    = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina     = $oficina;
            $this->direccion   = $direccion;
            $this->email       = $email;
            $this->telefono    = $telefono;
            $this->nit         = $nit;
            $this->datos       = $datos;
        }
        // Encabezado
        function Header() {
            $fuente = "Courier";
            $hoy    = date("Y-m-d H:i:s");
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Image($this->pathlogoins, 10, 13, 33);
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->Ln(10);
            $this->SetFont($fuente, 'B', 10);
            $this->SetFillColor(204,229,255);
            $this->Cell(0, 5, 'RESUMEN DE SALDOS BANCARIOS ' . $this->datos, 0, 1, 'C', true);
            // Encabezado de la tabla
            $this->SetFillColor(240,240,240);
            $this->SetFont($fuente, 'B', 8);
            $this->Cell(40, 6, 'CUENTA', 'B', 0, 'L', true);
            $this->Cell(60, 6, 'NOMBRE', 'B', 0, 'L', true);
            $this->Cell(5, 6, '', 'B', 0, 'L', true);
            $this->Cell(30, 6, 'TOTAL DEBE', 'B', 0, 'R', true);
            $this->Cell(30, 6, 'TOTAL HABER', 'B', 0, 'R', true);
            $this->Cell(30, 6, 'SALDO', 'B', 1, 'R', true);
        }
        // Pie de página
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Arial','I',8);
            $this->Cell(0,10, 'Página '.$this->PageNo().'/{nb}',0,0,'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $titlereport);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $pdf->SetFont($fuente, '', 9);

    // Variables para totales generales
    $sumDebe  = 0;
    $sumHaber = 0;
    $sumSaldo = 0;

    // Recorrer cada registro resumido y dibujar la fila correspondiente
    foreach ($registro as $fila) {
        $pdf->Cell(40, 6, $fila['ccodcta'], 0, 0, 'L');
        $pdf->Cell(60, 6, decode_utf8($fila['cdescrip']), 0, 0, 'L');
        $pdf->Cell(5, 6, '', 0, 0, 'L');
        $pdf->Cell(30, 6, number_format($fila['total_debe'], 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(30, 6, number_format($fila['total_haber'], 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(30, 6, number_format($fila['saldo'], 2, '.', ','), 0, 1, 'R');

        $sumDebe  += $fila['total_debe'];
        $sumHaber += $fila['total_haber'];
        $sumSaldo += $fila['saldo'];
    }

    // Mostrar totales generales al final del reporte
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(105, 6, 'TOTAL GENERAL:', 'T', 0, 'R');
    $pdf->Cell(30, 6, number_format($sumDebe, 2, '.', ','), 'T', 0, 'R');
    $pdf->Cell(30, 6, number_format($sumHaber, 2, '.', ','), 'T', 0, 'R');
    $pdf->Cell(30, 6, number_format($sumSaldo, 2, '.', ','), 'T', 1, 'R');

    // Salida del PDF
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();
    
    $opResult = array(
        'status'   => 1,
        'mensaje'  => 'Reporte generado correctamente',
        'namefile' => "Resumen Saldos Bancarios",
        'tipo'     => "pdf",
        'data'     => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//===================================================
// Función: Generar Excel (Resumen de Saldos Bancarios)
//===================================================
function printxls($registro, $titlereport, $info) {
    require '../../../vendor/autoload.php';
    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Resumen Bancos");

    // Definir ancho de columnas
    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(30);
    $activa->getColumnDimension("C")->setWidth(15);
    $activa->getColumnDimension("D")->setWidth(15);
    $activa->getColumnDimension("E")->setWidth(15);

    // Encabezado
    $activa->setCellValue('A1', 'CUENTA');
    $activa->setCellValue('B1', 'NOMBRE CUENTA');
    $activa->setCellValue('C1', 'TOTAL DEBE');
    $activa->setCellValue('D1', 'TOTAL HABER');
    $activa->setCellValue('E1', 'SALDO');

    $fila = 2;
    $sumDebe  = 0;
    $sumHaber = 0;
    $sumSaldo = 0;
    
    // Recorrer cada registro y agregar los datos
    foreach ($registro as $row) {
        $activa->setCellValue('A' . $fila, $row['ccodcta']);
        $activa->setCellValue('B' . $fila, $row['cdescrip']);
        $activa->setCellValue('C' . $fila, $row['total_debe']);
        $activa->setCellValue('D' . $fila, $row['total_haber']);
        $activa->setCellValue('E' . $fila, $row['saldo']);

        $sumDebe  += $row['total_debe'];
        $sumHaber += $row['total_haber'];
        $sumSaldo += $row['saldo'];
        $fila++;
    }

    // Totales generales en la última fila
    $activa->setCellValue('A' . $fila, 'TOTAL GENERAL:');
    $activa->setCellValue('C' . $fila, $sumDebe);
    $activa->setCellValue('D' . $fila, $sumHaber);
    $activa->setCellValue('E' . $fila, $sumSaldo);
    
    // Autoajustar el ancho de columnas
    foreach (range('A','E') as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(true);
    }
    
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();
    
    $opResult = array(
        'status'   => 1,
        'mensaje'  => 'Reporte generado correctamente',
        'namefile' => "Resumen Saldos Bancarios",
        'tipo'     => "xlsx",
        'data'     => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
?>
