<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
    exit;
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
    return;
}

require __DIR__ . '/../../../fpdf/fpdf.php';

$hoy2 = date("Y-m-d H:i:s");
$hoy  = date("Y-m-d");

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Helpers\Beneq;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;


$status = false;
try {
    $database = new DatabaseAdapter();
    $db_name_general = $_ENV['DDBB_NAME_GENERAL'];

    //+++++++
    /**
     * ['finicio', 'ffin'],['codofi', 'codusu'],['ragencia'],[checkedValues, $('#tipcuenta').val()],
     */

    $data = [
        // 'token_csrf' => $_POST['inputs'][$csrf->getTokenName()] ?? '',
        'fecha_inicio' => $_POST['datosval'][0][0] ?? null,
        'fecha_fin' => $_POST['datosval'][0][1] ?? null,
        'codofi' => trim($_POST['datosval'][1][0] ?? '0'),
        'codusu' => trim($_POST['datosval'][1][1] ?? '0'),
        'radio_agencia' => trim($_POST['datosval'][2][0] ?? ''),
        'tiposTransaccion' => $_POST['datosval'][3][0] ?? ['all'],
        'tiposCuenta' => $_POST['datosval'][3][1] ?? ['0'],
        'tipo' => $_POST['tipo'] ?? ''
    ];

    // Log::debug("post", $_POST);
    // Log::debug("data", $data);

    $rules = [
        // 'token_csrf' => 'required',
        'fecha_inicio' => 'required|date',
        'fecha_fin' => 'required|date',
        'radio_agencia' => 'required|string',
        'tiposTransaccion' => 'required',
        'tiposCuenta' => 'required',
        'tipo' => 'required|string|in:pdf,xlsx,show'
    ];

    if ($data['radio_agencia'] == 'anyofi') {
        $rules['codofi'] = 'required|integer|min:1|exists:tb_agencia,id_agencia';
    }
    if ($data['radio_agencia'] == 'anyuser') {
        $rules['codusu'] = 'required|integer|min:1|exists:tb_usuario,id_usu';
    }

    $messages = [
        'codofi.required' => 'Seleccione una agencia',
        'codusu.required' => 'Seleccione un usuario',
    ];

    // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

    $validator = Validator::make($data, $rules, $messages);
    if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
    }

    if ($data['fecha_inicio'] > $data['fecha_fin']) {
        throw new SoftException("Rango de fechas inválido");
    }

    if (!is_array($data['tiposTransaccion'])) {
        throw new SoftException("Tipo de transacción inválido");
    }

    $where = (empty($data['tiposCuenta']) || in_array('0', $data['tiposCuenta']))
        ? " "
        : " AND tip.ccodtip IN ('" . implode("','", $data['tiposCuenta']) . "')";

    // Inicializo $params con los placeholders de fecha
    $params = [
        $data['fecha_inicio'],
        $data['fecha_fin']
    ];

    // Si filtran por transacciones distintas de "all"
    if (!in_array('all', $data['tiposTransaccion'])) {
        $transMap = [
            "lib" => "(crazon LIKE '%CAMBIO LIBRETA%' OR crazon LIKE '%SALDO INI%')",
            "int" => "(ctipope='D' AND crazon LIKE '%INTERES%')",
            "dep" => "(ctipope='D' AND crazon NOT LIKE '%INTERES%' AND crazon NOT LIKE '%SALDO INI%')",
            "isr" => "(ctipope='R' AND (crazon LIKE '%INTERES%' OR crazon = 'IPF'))",
            "ret" => "(ctipope='R' AND crazon NOT LIKE '%INTERES%' AND crazon NOT LIKE '%CAMBIO LIBRETA%')"
        ];
        $where .= " AND (";
        foreach ($data['tiposTransaccion'] as $i => $t) {
            $where .= $transMap[$t];
            if ($i < count($data['tiposTransaccion']) - 1) {
                $where .= " OR ";
            }
        }
        $where .= ") ";
    }

    $where .= ($data['radio_agencia']  == "allofi") ? "" : (($data['radio_agencia']  == "anyofi") ? " AND usu.id_agencia=" . $data['codofi'] : " AND usu.id_usu=" . $data['codusu']);

    $titlereport = "DEL " . Date::toDMY($data['fecha_inicio']) . " AL " . Date::toDMY($data['fecha_fin']);

    $query = "SELECT 
      aho.ccodaho,
      cl.short_name,
      mov.dfecope,
      mov.cnumdoc,
      mov.ctipdoc,
      mov.ctipope,
      mov.monto,
      mov.crazon,
      tip.nombre       AS tipocuenta,
      ag.nom_agencia   AS agencia
    FROM tb_cliente cl
    INNER JOIN ahomcta aho ON cl.idcod_cliente = aho.ccodcli
    INNER JOIN ahomtip tip ON tip.ccodtip = SUBSTR(aho.ccodaho,7,2)
    INNER JOIN ahommov mov ON mov.ccodaho = aho.ccodaho
    INNER JOIN tb_usuario usu ON mov.codusu = usu.id_usu
    INNER JOIN tb_agencia ag ON ag.id_agencia = usu.id_agencia
    WHERE mov.cestado = 1
      AND mov.dfecope BETWEEN ? AND ?
      {$where}
    ORDER BY mov.dfecope, mov.ccodaho
";

    // Log::debug("Consulta para reporte de ingresos diarios", ['query' => $query, 'params' => $params]);

    $database->openConnection();
    $result = $database->getAllResults($query, $params);

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

    $status = 1;
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

// Salida según tipo
switch ($data['tipo']) {
    case 'xlsx':
        printxls($result);
        break;
    case 'pdf':
        printpdf($result, [$titlereport], $info);
        break;
    case 'show':
        showresults($result);
        break;
    default:
        echo json_encode(['status' => 0, 'mensaje' => 'Tipo de salida no válido']);
        break;
}

// ------------------------------------------------------------
// función para mostrar en pantalla
function showresults($registro)
{
    $valores = [];
    foreach ($registro as $i => $fila) {
        $fila["dfecope"] = date("d-m-Y", strtotime($fila["dfecope"]));
        $valores[$i] = $fila;
    }
    $keys       = ["dfecope", "ccodaho", "short_name", "cnumdoc", "ctipope", "ctipdoc", "monto", "crazon"];
    $encabezados = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "NUMDOC", "OPERACIÓN", "FORMA PAGO", "MONTO", "RAZÓN"];
    echo json_encode([
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'data' => $valores,
        'keys' => $keys,
        'encabezados' => $encabezados
    ]);
}

//funcion para generar pdf
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
            // $this->DefOrientation = 'L';
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
            $this->Cell(0, 5, 'LISTADO DE MOVIMIENTOS' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 25;
            $widths = [25, 65, 20, 24, 12, 22, 22];
            $this->Cell($widths[0], 5, 'CUENTA', 'B', 0, 'C');
            $this->Cell($widths[1], 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'C');
            $this->Cell($widths[2], 5, 'FECHA', 'B', 0, 'C');
            $this->Cell($widths[3], 5, 'DOC.', 'B', 0, 'C');
            $this->Cell($widths[4], 5, 'TIPDOC', 'B', 0, 'C');
            $this->Cell($widths[5], 5, 'DEPOSITO', 'B', 0, 'R');
            $this->Cell($widths[6], 5, 'RETIRO', 'B', 0, 'R');
            $this->Ln(8);
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
    $widths = [25, 65, 20, 24, 12, 22, 22];

    foreach ($registro as $key => $fil) {
        //["dfecope", "ccodaho", "short_name", "cnumdoc", "ctipope", "ctipdoc", "monto", "crazon"
        $cuenta = $fil["ccodaho"];
        $nombre = Utf8::decode($fil["short_name"]);
        $fecha = Date::toDMY($fil["dfecope"]);
        $numdoc = $fil["cnumdoc"];
        $tipdoc = $fil["ctipdoc"];
        $tipope = $fil["ctipope"];
        $deposito = ($tipope == "D") ? $fil["monto"] : 0;
        $retiro = ($tipope == "R") ? $fil["monto"] : 0;
        $registro[$key]["deposito"] = $deposito;
        $registro[$key]["retiro"] = $retiro;

        $pdf->CellFit($widths[0], $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($widths[1], $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($widths[2], $tamanio_linea + 1, $fecha, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($widths[3], $tamanio_linea + 1, Beneq::karely($numdoc), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($widths[4], $tamanio_linea + 1, $tipdoc, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($widths[5], $tamanio_linea + 1, number_format($deposito, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($widths[6], $tamanio_linea + 1, number_format($retiro, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
    }
    $sumadepositos = array_sum(array_column($registro, "deposito"));
    $sumaretiros = array_sum(array_column($registro, "retiro"));

    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->CellFit($widths[0] + $widths[1] + $widths[2] + $widths[3] + $widths[4], $tamanio_linea + 1, 'TOTAL INGRESOS: ' . count($registro), '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($widths[5], $tamanio_linea + 1, number_format($sumadepositos, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($widths[6], $tamanio_linea + 1, number_format($sumaretiros, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Listado de movimientos",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function printxls($registro)
{
    $fuente = "Courier";
    $excel = new Spreadsheet();
    $excel
        ->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Listado de Movimientos')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEMPLUS')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');


    $activa = $excel->getActiveSheet();
    $activa->setTitle("Listado del dia");
    # Escribir encabezado de la tabla
    $encabezado_tabla = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "DOCUMENTO", "TIPO DOC", "RAZON", "DEPOSITO", "RETIRO", "TIPO CUENTA", "AGENCIA"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor xd
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle('A1:S1')->getFont()->setName($fuente)->setBold(true);

    foreach ($registro as $key => $fil) {
        $cuenta = $fil["ccodaho"];
        $nombre = ($fil["short_name"]);
        $fecha = Date::toDMY($fil["dfecope"]);
        $numdoc = $fil["cnumdoc"];
        $tipdoc = $fil["ctipdoc"];
        $tipope = $fil["ctipope"];
        $razon = $fil["crazon"];
        $tipocuenta = $fil["tipocuenta"];
        $deposito = ($tipope == "D") ? $fil["monto"] : 0;
        $deposito = number_format($deposito, 2, '.', '');
        $agencia = $fil['agencia'];

        $retiro = ($tipope == "R") ? $fil["monto"] : 0;
        $retiro = number_format($retiro, 2, '.', '');

        $registro[$key]["deposito"] = $deposito;
        $registro[$key]["retiro"] = $retiro;

        $activa->setCellValue('A' . $key + 2, $fecha);
        $activa->setCellValueExplicit('B' . $key + 2, $cuenta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('C' . $key + 2, strtoupper($nombre));
        $activa->setCellValueExplicit('D' . $key + 2, $numdoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('E' . $key + 2, $tipdoc);
        $activa->setCellValue('F' . $key + 2, $razon);

        // $activa->getStyle('G' . $key + 2 . ':H' . $key + 2)->getNumberFormat()->setFormatCode(PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_CURRENCY_GT_SIMPLE);
        $activa->setCellValueExplicit('G' . $key + 2, $deposito, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('H' . $key + 2, $retiro, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValue('I' . $key + 2, $tipocuenta);
        $activa->setCellValue('J' . $key + 2, $agencia);
    }
    $activa->getStyle("A1:J" . $key + 2)->getFont()->setName($fuente);
    $columnas = range('A', 'J'); // Genera un array con las letras de A a O
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
        'namefile' => "Listado del día",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
