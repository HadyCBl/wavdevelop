<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

// include __DIR__ . '/../../../includes/Config/database.php';
// $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

require __DIR__ . '/../../../fpdf/fpdf.php';
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$database = new DatabaseAdapter();
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ VALIDACIONES +++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$fechaFinal = $inputs[0];

$tipocuentaArray = $datos[3][0] ?? ['0'];

$estado = $radios[0];

if ($fechaFinal > $hoy) {
    echo json_encode(['status' => 0, 'mensaje' => 'La fecha digitada no pueder ser mayor que la fecha actual']);
    return;
}

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++++ CONSULTAS EN LA BD +++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$where = ($estado == "0") ? " WHERE cta.estado IN ('A','B')" : " WHERE cta.estado = '" . $estado . "'";
$where .= (empty($tipocuentaArray) || in_array('0', $tipocuentaArray))
    ? " "
    : " AND tip.ccodtip IN ('" . implode("','", $tipocuentaArray) . "')";
$titlereport = " AL " . date("d-m-Y", strtotime($fechaFinal));

$strquery = "SELECT tip.nombre,cta.ccodaho, cli.short_name,calcular_saldo_aho_tipcuenta(cta.ccodaho,?) AS saldo, cta.tasa, 
                    cli.genero,cta.estado,cta.fecha_apertura,cli.idcod_cliente AS idcliente, IFNULL(cli.PEP, '-') AS PEP, 
                    IFNULL(cli.CPE, '-') AS CPE, ifnull(cli.profesion, '-') AS profesion,
                    (SELECT IFNULL(MAX(mov.dfecope),'-') FROM ahommov mov WHERE mov.ccodaho=cta.ccodaho AND mov.dfecope<=?) AS ultima_fecha_movimiento, IFNULL(concat(usu.nombre,' ',usu.apellido),'-') AS encargado
                FROM ahomcta cta 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                LEFT JOIN tb_usuario usu on usu.id_usu = cta.encargado
                INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)" . $where . " ORDER BY cta.ccodaho,cta.fecha_apertura";

$status = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($strquery, [$fechaFinal, $fechaFinal]);
    if (empty($result)) {
        throw new SoftException("No se encontraron registros");
    }

    $info = $database->getAllResults(
        "SELECT nom_agencia, nomb_comple, muni_lug, emai, tel_1, tel_2, nit, log_img 
            FROM {$db_name_general}.info_coperativa ins
            INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?",
        [$_SESSION['id_agencia']]
    );
    if (empty($info)) {
        throw new SoftException("Institucion asignada a la agencia no encontrada");
    }

    $status = true;
} catch (SoftException $se) {
    $mensaje = "Advertencia: " . $se->getMessage();
} catch (Exception $e) {
    $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
} finally {
    $database->closeConnection();
}


if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

switch ($tipo) {
    case 'xlsx';
        printxls($result, $titlereport, $idusuario);
        break;
    case 'pdf':
        printpdf($result, [$titlereport, $idusuario], $info);
        break;
    case 'show':
        showresults($result);
        break;
}

function showresults($registro)
{
    $valores[] = [];
    $i = 0;
    foreach ($registro as $fila) {
        $fila["fecha_apertura"] = ($fila["fecha_apertura"] == "0000-00-00") ? "-" : date("d-m-Y", strtotime($fila["fecha_apertura"]));
        $fila["estado"] = ($fila["estado"] == "B") ? "Inactiva" : "Activa";
        $saldo = ($fila['saldo'] < 0) ? 0 : $fila['saldo'];
        $fila['saldo'] = $saldo;
        $valores[$i] = $fila;
        $i++;
    }

    $keys = ["ccodaho", "short_name", "genero", "estado", "fecha_apertura", "saldo", "tasa", "nombre"];
    $encabezados = ["CUENTA", "NOMBRE CLIENTE", "GENERO", "ESTADO", "APERTURA", "SALDO", "TASA", "TIPO"];

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

function printpdf($registro, $datos, $info)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins =  "../../.." . $info[0]["log_img"];

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
            parent::__construct('L', 'mm', 'Letter');
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
            $this->Cell(0, 2, $this->datos[1], 0, 1, 'R');
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
            $this->Cell(0, 5, 'REPORTE DE SALDO DE CUENTAS' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA

            $widths = [25, 12, 60, 20, 25, 10, 60,0];

            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 25;
            $this->Cell($widths[0], 5, 'CUENTA', 'T-B', 0, 'L');
            $this->Cell($widths[1], 5, 'ESTADO', 'T-B', 0, 'L'); //
            $this->Cell($widths[2], 5, 'NOMBRE DEL CLIENTE', 'T-B', 0, 'L');
            $this->Cell($widths[3], 5, 'APERTURA', 'T-B', 0, 'C');
            $this->Cell($widths[4], 5, 'SALDO', 'T-B', 0, 'C');
            $this->Cell($widths[5], 5, 'TASA', 'T-B', 0, 'C'); //
            $this->Cell($widths[6], 5, 'TIPO', 'T-B', 0, 'C'); //
            $this->Cell($widths[7], 5, 'ENCARGADO', 'T-B', 1, 'C'); //
            $this->Ln(3);
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
    $ancho_linea2 = 25;
    $pdf->SetFont($fuente, '', 8);

    $widths = [25, 12, 60, 20, 25, 10, 60, 0];
    $pdf->SetDrawColor(200, 200, 200); // Gris claro

    foreach ($registro as $key => $row) {
        $fecha_apertura = Date::isValid($row["fecha_apertura"]) ? Date::toDMY($row["fecha_apertura"]) : "-";
        $status = ($row["estado"] == "A") ? "Activa" : "Inactiva";
        $nombreTitular = Utf8::decode(mb_convert_case($row["short_name"], MB_CASE_TITLE, 'UTF-8'));

        $saldo = ($row['saldo'] < 0) ? 0 : $row['saldo'];
        $registro[$key]['saldo'] = $saldo;

        $pdf->CellFit($widths[0], $tamanio_linea + 1, $row['ccodaho'], 'B', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($widths[1], $tamanio_linea + 1, $status, 'B', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($widths[2], $tamanio_linea + 1, $nombreTitular, 'B', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($widths[3], $tamanio_linea + 1, $fecha_apertura, 'B', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($widths[4], $tamanio_linea + 1, Moneda::formato($saldo), 'B', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($widths[5], $tamanio_linea + 1, number_format($row['tasa'], 2, '.', ','), 'B', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($widths[6], $tamanio_linea + 1, Utf8::decode($row['nombre']), 'B', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($widths[7], $tamanio_linea + 1, Utf8::decode($row['encargado']), 'B', 1, 'L', 0, '', 1, 0);
    }

    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $widthsTotal = $widths[0] + $widths[1] + $widths[2] + $widths[3];
    $pdf->CellFit($widthsTotal, $tamanio_linea + 1, 'TOTAL: ' . count($registro), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($widths[4], $tamanio_linea + 1, number_format(array_sum(array_column($registro, "saldo")), 2, '.', ','), '', 0, 'R', 0, '', 1, 0);

    //resumen de saldo
    $femenino = [];
    $masculino = [];
    $no_definido = [];

    array_walk($registro, function ($item) use (&$femenino, &$masculino, &$no_definido) {
        switch ($item['genero']) {
            case 'F':
                $femenino[] = $item;
                break;
            case 'M':
                $masculino[] = $item;
                break;
            default:
                $no_definido[] = $item;
                break;
        }
    });

    $pdf->Ln(5);
    $pdf->CellFit($widthsTotal, $tamanio_linea, "MUJERES: " . count($femenino), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($widths[4], $tamanio_linea, number_format(array_sum(array_column($femenino, "saldo")), 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($widthsTotal, $tamanio_linea, "HOMBRES: " . count($masculino), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($widths[4], $tamanio_linea, number_format(array_sum(array_column($masculino, "saldo")), 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($widthsTotal, $tamanio_linea, "INDEFINIDOS: " . count($no_definido), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($widths[4], $tamanio_linea, number_format(array_sum(array_column($no_definido, "saldo")), 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Saldos de cuentas",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro)
{
    $fuente = "Courier";
    $excel = new Spreadsheet();
    $excel
        ->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Saldos por cuenta con fecha')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');

    $activa = $excel->getActiveSheet();
    $activa->setTitle("Saldos de cuentas");
    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CUENTA", "CODCLIENTE", "NOMBRE CLIENTE", "GENERO", "ESTADO", "APERTURA", "SALDO", "TASA", "TIPO", "PROFESION", "¿EL CLIENTE ES PEP?", "¿EL CLIENTE ES CPE?", "ULTIMO MOVIMIENTO", "ENCARGADO DE CUENTA"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle('A1:S1')->getFont()->setName($fuente)->setBold(true);
    $i = 2;

    foreach ($registro as $key => $row) {
        $fecha_apertura = Date::isValid($row["fecha_apertura"]) ? Date::toDMY($row["fecha_apertura"]) : "-";
        $status = ($row["estado"] == "A") ? "Activa" : "Inactiva";

        $saldo = ($row['saldo'] < 0) ? 0 : $row['saldo'];
        $registro[$key]['saldo'] = $saldo;

        $ultimoMov = Date::isValid($row["ultima_fecha_movimiento"]) ? Date::toDMY($row["ultima_fecha_movimiento"]) : "-";

        $activa->setCellValueExplicit('A' . $i, $row["ccodaho"], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('B' . $i, $row["idcliente"], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('C' . $i, strtoupper($row["short_name"]));
        $activa->setCellValue('D' . $i, ($row["genero"]));
        $activa->setCellValue('E' . $i, ($status));
        $activa->setCellValue('F' . $i, ($fecha_apertura));
        $activa->setCellValueExplicit('G' . $i, $saldo, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('H' . $i, $row["tasa"], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValue('I' . $i, $row["nombre"]);
        $activa->setCellValue('J' . $i, ($row["profesion"]));
        $activa->setCellValue('K' . $i, ($row["PEP"]));
        $activa->setCellValue('L' . $i, ($row["CPE"]));
        $activa->setCellValue('M' . $i, ($ultimoMov));
        $activa->setCellValue('N' . $i, ($row["encargado"]));

        $i++;
    }

    $activa->getStyle("A1:N" . $i)->getFont()->setName($fuente);
    $columnas = range('A', 'N'); // Genera un array con las letras de A a N
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
        'namefile' => "Reporte de saldos",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
