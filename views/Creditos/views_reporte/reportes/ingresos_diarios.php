<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

require '../../../../fpdf/fpdf.php';
$hoy = date("Y-m-d");

use App\DatabaseAdapter;
use App\Generic\Models\TipoDocumentoTransaccion;
use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tiposGasto = [];
$pivotData = [];
$status = false;
try {
    $database = new DatabaseAdapter();
    $db_name_general = $_ENV['DDBB_NAME_GENERAL'];
    $data = [
        // 'token_csrf' => $_POST['inputs'][$csrf->getTokenName()] ?? '',
        'fecha_inicio' => $_POST['datosval'][0][0] ?? null,
        'fecha_fin' => $_POST['datosval'][0][1] ?? null,
        'codofi' => trim($_POST['datosval'][1][0] ?? ''),
        'fondoid' => trim($_POST['datosval'][1][1] ?? ''),
        'codusu' => trim($_POST['datosval'][1][2] ?? ''),
        'creAgenciaSelect' => trim($_POST['datosval'][1][3] ?? ''),
        'creUserSelect' => trim($_POST['datosval'][1][4] ?? ''),
        'radio_fondos' => trim($_POST['datosval'][2][0] ?? ''),
        'radio_agencia' => trim($_POST['datosval'][2][1] ?? ''),
        'radio_creditos' => trim($_POST['datosval'][2][2] ?? ''),
    ];

    $rules = [
        // 'token_csrf' => 'required',
        'fecha_inicio' => 'required|date',
        'fecha_fin' => 'required|date',
        'radio_fondos' => 'required|string',
        'radio_agencia' => 'required|string',
        'radio_creditos' => 'required|string',
    ];

    if ($data['radio_fondos'] == 'anyf') {
        $rules['fondoid'] = 'required|integer|min:1|exists:ctb_fuente_fondos,id';
    }
    if ($data['radio_agencia'] == 'anyofi') {
        $rules['codofi'] = 'required|integer|min:1|exists:tb_agencia,id_agencia';
    }
    if ($data['radio_agencia'] == 'anyuser') {
        $rules['codusu'] = 'required|integer|min:1|exists:tb_usuario,id_usu';
    }
    if ($data['radio_creditos'] == 'creAgencia') {
        $rules['creAgenciaSelect'] = 'required|integer|min:1|exists:tb_agencia,id_agencia';
    }
    if ($data['radio_creditos'] == 'creUser') {
        $rules['creUserSelect'] = 'required|integer|min:1|exists:tb_usuario,id_usu';
    }

    // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

    $validator = Validator::make($data, $rules);
    if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
    }

    $tipo = $_POST["tipo"];

    if ($data['fecha_inicio'] > $data['fecha_fin']) {
        throw new SoftException("Rango de fechas Inválido");
    }
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    // $fecinicio = $inputs[0];
    // $fecfin = $inputs[1];
    $where = ($data['radio_agencia']  == "allofi") ? "" : (($data['radio_agencia']  == "anyofi") ? " AND usu.id_agencia=" . $data['codofi'] : " AND usu.id_usu=" . $data['codusu']);
    $where .= ($data['radio_fondos'] == "anyf") ? " AND prod.id_fondo=" . $data['fondoid'] : "";
    $where .= ($data['radio_creditos'] == "creAgencia") ? " AND ofi2.id_agencia=" . $data['creAgenciaSelect'] : (($data['radio_creditos'] == "creUser") ? " AND crem.CodAnal=" . $data['creUserSelect'] : "");

    $titlereport = " DEL " . Date::toDMY($data['fecha_inicio']) . " AL " . Date::toDMY($data['fecha_fin']);

    $query = "SELECT kar.CODKAR,kar.CCODCTA,cli.short_name,cli.no_identifica dpi,cli.no_tributaria nit,kar.DFECPRO,kar.NMONTO,kar.CNUMING,kar.KP,kar.INTERES,kar.MORA,kar.AHOPRG,kar.OTR,
                IFNULL((SELECT NombreGrupo from tb_grupo where id_grupos=crem.CCodGrupo),' ') NombreGrupo,ofi.nom_agencia,CONCAT(usu.nombre,' ',usu.apellido) usuario ,CONCAT(usu2.nombre,' ',usu2.apellido) analista, ofi2.nom_agencia agenciaorigen,
                FormPago,DFECBANCO, DFECSIS,
                IFNULL((SELECT nombre FROM tb_bancos where id=CBANCO),'-') CBANCO,
                IFNULL((SELECT numcuenta FROM ctb_bancos where id=CCODBANCO),'-') CCODBANCO ,boletabanco 
                FROM CREDKAR kar 
                INNER JOIN cremcre_meta crem ON crem.CCODCTA=kar.CCODCTA 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli 
                INNER JOIN tb_usuario usu on usu.id_usu=kar.CCODUSU
                INNER JOIN tb_usuario usu2 on usu2.id_usu=crem.CodAnal
                INNER JOIN tb_agencia ofi on ofi.id_agencia=usu.id_agencia
                INNER JOIN tb_agencia ofi2 on ofi2.id_agencia=crem.CODAgencia
                INNER JOIN cre_productos prod on prod.id=crem.CCODPRD
                WHERE kar.CESTADO!='X' AND kar.CTIPPAG='P' " . $where . " AND (kar.DFECPRO BETWEEN ? AND ?) ORDER BY kar.DFECPRO,kar.CNUMING";

    //+++++++++++++++++++
    $database->openConnection();
    $result = $database->getAllResults($query, [$data['fecha_inicio'], $data['fecha_fin']]);

    if (empty($result)) {
        throw new SoftException("No se encontraron registros.");
    }

    $info = $database->getAllResults(
        "SELECT nom_agencia, nomb_comple, muni_lug, emai, tel_1, tel_2, nit, log_img 
            FROM {$db_name_general}.info_coperativa ins
            INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?",
        [$_SESSION['id_agencia']]
    );
    // $info = convert_to_utf8($info);
    if (empty($info)) {
        throw new SoftException("Institucion asignada a la agencia no encontrada");
    }

    // ------------------------------------------------------------------
    // Obtener detalle de pagos "Otros" por subcategoría
    // ------------------------------------------------------------------
    $codkars = array_column($result, 'CODKAR');
    if (!empty($codkars)) {
        $placeholders = implode(',', array_fill(0, count($codkars), '?'));
        $sqlOtros = "SELECT ck.CODKAR, tg.id AS id_tipogasto, tg.nombre_gasto, SUM(cd.monto) AS total
                      FROM CREDKAR ck
                      INNER JOIN credkar_detalle cd ON cd.id_credkar = ck.CODKAR
                      INNER JOIN cre_productos_gastos pg ON pg.id = cd.id_concepto
                      INNER JOIN cre_tipogastos tg ON tg.id = pg.id_tipo_deGasto
                      WHERE cd.tipo='otro' AND ck.CODKAR IN ($placeholders) AND ck.CTIPPAG='P' AND ck.CESTADO!='X'
                      GROUP BY ck.CODKAR, tg.id
                      ORDER BY tg.id";
        $detOtros = $database->getAllResults($sqlOtros, $codkars);
        $tiposSet = [];
        foreach ($detOtros as $rowo) {
            $idg = $rowo['id_tipogasto'];
            if (!isset($pivotData[$rowo['CODKAR']])) {
                $pivotData[$rowo['CODKAR']] = [];
            }
            $pivotData[$rowo['CODKAR']][$idg] = (float)$rowo['total'];
            if (!isset($tiposSet[$idg])) {
                $tiposSet[$idg] = $rowo['nombre_gasto'];
            }
        }
        foreach ($tiposSet as $id => $nom) {
            $tiposGasto[] = ['id' => $id, 'nombre' => $nom];
        }
        usort($tiposGasto, function ($a, $b) {
            return ($a['id'] < $b['id']) ? -1 : 1;
        });
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
//FIN TRY

switch ($tipo) {
    case 'xlsx';
        printxls($result, $tiposGasto, $pivotData);
        break;
    case 'pdf':
        printpdf($result, [$titlereport], $info);
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
        unset($fila['CODKAR']);
        unset($fila['dpi']);
        unset($fila['nit']);
        $formapago = $fila["FormPago"];
        $fila["DFECBANCO"] = ($formapago == '2') ? date("d-m-Y", strtotime($fila["DFECBANCO"])) : 'N/A';
        $fila["CBANCO"] = ($formapago == '2') ? $fila["CBANCO"] : 'N/A';
        $fila["CCODBANCO"] = ($formapago == '2') ? $fila["CCODBANCO"] : 'N/A';
        // $fila["FormPago"] = ($formapago == '2') ? 'BOLETA DE BANCO' :  (($formapago == '4') ? 'CREF' : 'EFECTIVO');
        $fila["FormPago"] = TipoDocumentoTransaccion::getDescripcion($formapago, 3);

        $fila["DFECPRO"] = date("d-m-Y", strtotime($fila["DFECPRO"]));
        $valores[$i] = $fila;
        $i++;
    }

    $keys = ["DFECPRO", "CCODCTA", "short_name", "NMONTO", "CNUMING", "nom_agencia", "usuario", "FormPago", "DFECBANCO", "CBANCO", "boletabanco"];
    $encabezados = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "TOTAL INGRESO", "NUMDOC", "AGENCIA", "USUARIO", "FORMA PAGO", "FECHA BOLETA", "BANCO", "NO BOLETA"];

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

//funcion para generar pdf
function printpdf($registro, $datos, $info)
{

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
            $this->Cell(0, 5, 'INGRESOS POR CAJA' . $this->datos[0], 0, 1, 'C', true);
            //Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            //TITULOS DE ENCABEZADO DE TABLA
            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 25;
            $Wline = [25, 15, 10]; // se agrego negroy  width LINE
            $this->Cell($ancho_linea, 5, 'FECHA', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'CUENTA', 'B', 0, 'L'); //
            $this->Cell($ancho_linea * 2, 5, 'NOMBRE DEL CLIENTE', 'B', 0, 'L');
            $this->Cell($ancho_linea, 5, 'TOTAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'CAPITAL', 'B', 0, 'R');
            $this->Cell($ancho_linea, 5, 'INTERES', 'B', 0, 'R'); //
            $this->Cell($Wline[1], 5, 'MORA', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'OTROS', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'DOC.', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'AGENCIA', 'B', 0, 'R');
            $this->Cell($Wline[1], 5, 'ORIGEN', 'B', 0, 'R');
            $this->Cell($Wline[0], 5, 'FORMA PAGO', 'B', 1, 'R');
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
    $Wline = [25, 15, 10]; // se agrego negroy  width LINE
    $pdf->SetFont($fuente, '', 8);
    $fila = 0;
    $ttotal = 0;
    $tcapital = 0;
    $tinteres = 0;
    $tmora = 0;
    $totros = 0;
    while ($fila < count($registro)) {
        $id = $registro[$fila]["CODKAR"];
        $cuenta = $registro[$fila]["CCODCTA"];
        $nombre = Utf8::decode($registro[$fila]["short_name"]);
        $fecha = date("d-m-Y", strtotime($registro[$fila]["DFECPRO"]));
        $numdoc = $registro[$fila]["CNUMING"];
        $total = $registro[$fila]["NMONTO"];
        $capital = $registro[$fila]["KP"];
        $interes = $registro[$fila]["INTERES"];
        $mora = $registro[$fila]["MORA"];
        $ahorro = $registro[$fila]["AHOPRG"];
        $otro = $registro[$fila]["OTR"];
        $agencia = $registro[$fila]["nom_agencia"];
        //AGREGADO DEL NEGROY 
        $origen = $registro[$fila]["agenciaorigen"];

        $formapago = $registro[$fila]["FormPago"];
        $fechabanco = ($formapago == '2') ? $registro[$fila]["DFECBANCO"] : ' ';
        $nombrebanco = ($formapago == '2') ? $registro[$fila]["CBANCO"] : ' ';
        $cuentabanco = ($formapago == '2') ? $registro[$fila]["CCODBANCO"] : ' ';
        // $formapago = ($formapago == '2') ? 'BOLETA DE BANCO' :  (($formapago == '4') ? 'CREF' : 'EFECTIVO');
        $formapago = TipoDocumentoTransaccion::getDescripcion($formapago, 3);


        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $fecha, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $cuenta, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 1, strtoupper($nombre), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($total, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($capital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($interes, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format($mora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format(($ahorro + $otro), 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, (!empty($numdoc) ? $numdoc : '-'), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, $agencia, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[1], $tamanio_linea + 1, $origen, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($Wline[0], $tamanio_linea + 1, $formapago, '', 1, 'R', 0, '', 1, 0);
        $ttotal = $ttotal + $total;
        $tcapital = $tcapital + $capital;
        $tinteres = $tinteres + $interes;
        $tmora = $tmora + $mora;
        $totros = $totros + $ahorro + $otro;
        $fila++;
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->CellFit($ancho_linea2 * 4, $tamanio_linea + 1, 'TOTAL INGRESOS: ' . $fila, '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($ttotal, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($tcapital, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($tinteres, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format($tmora, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($Wline[1], $tamanio_linea + 1, number_format($totros, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);

    $pdf->firmas(2, ["ENTREGA", "RECIBE"], $fuente);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Ingresos Diarios",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $tiposGasto = [], $pivotData = [])
{
    require '../../../../vendor/autoload.php';
    $fuente = "Courier";
    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("IngresosDiarios");
    # Encabezado de la tabla
    $encabezadosFijos = ["FECHA", "CUENTA", "NOMBRE CLIENTE", "TOTAL INGRESO", "CAPITAL", "INTERES", "MORA"];
    $encabezadosOtros = [];
    foreach ($tiposGasto as $tg) {
        $encabezadosOtros[] = strtoupper($tg['nombre']);
    }
    // Columna para el resto de gastos no catalogados
    $encabezadosOtros[] = 'OTROS';
    $encabezadosFinales = ["NUMDOC", "DPI CLIENTE", "NIT CLIENTE", "ANALISTA", "GRUPO", "AGENCIA", "USUARIO", "FORMA PAGO", "FECHA BOLETA", "BANCO", "CUENTA BANCARIA", "FECHA OPERACION"];
    $encabezado_tabla = array_merge($encabezadosFijos, $encabezadosOtros, $encabezadosFinales);
    $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($encabezado_tabla));
    $activa->fromArray($encabezado_tabla, null, 'A1')->getStyle("A1:" . $lastCol . "1")->getFont()->setName($fuente)->setBold(true);

    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $fila = 0;
    $i = 2;
    while ($fila < count($registro)) {
        $id = $registro[$fila]["CODKAR"];
        $cuenta = $registro[$fila]["CCODCTA"];
        $nombre = (($registro[$fila]["short_name"]));
        $fecha = date("d-m-Y", strtotime($registro[$fila]["DFECPRO"]));
        $numdoc = $registro[$fila]["CNUMING"];
        $total = $registro[$fila]["NMONTO"];
        $capital = $registro[$fila]["KP"];
        $interes = $registro[$fila]["INTERES"];
        $mora = $registro[$fila]["MORA"];
        $ahorro = $registro[$fila]["AHOPRG"];
        $otro = $registro[$fila]["OTR"];
        $nomgrupo = $registro[$fila]["NombreGrupo"];
        $agencia = $registro[$fila]["nom_agencia"];
        $origen = $registro[$fila]["agenciaorigen"];
        $formPay = $registro[$fila]["FormPago"];
        $dpi = $registro[$fila]["dpi"];
        $nit = $registro[$fila]["nit"];
        $asesor = $registro[$fila]["analista"];
        $usuario = $registro[$fila]["usuario"];
        $fechaOperacionSistema = (Date::isValid($registro[$fila]["DFECSIS"], 'Y-m-d H:i:s')) ? date("d-m-Y H:i:s", strtotime($registro[$fila]["DFECSIS"])) : '';

        $formapago = $registro[$fila]["FormPago"];
        $fechabanco = ($formapago == '2') ? $registro[$fila]["DFECBANCO"] : ' ';
        $nombrebanco = ($formapago == '2') ? $registro[$fila]["CBANCO"] : ' ';
        $cuentabanco = ($formapago == '2') ? $registro[$fila]["CCODBANCO"] : ' ';
        // $formapago = ($formapago == '2') ? 'BOLETA DE BANCO' :  (($formapago == '4') ? 'CREF' : 'EFECTIVO');
        $formapago = TipoDocumentoTransaccion::getDescripcion($formapago, 3);

        $row = [
            $fecha,
            $cuenta,
            strtoupper($nombre),
            $total,
            $capital,
            $interes,
            $mora
        ];
        $detalleOtros = isset($pivotData[$id]) ? $pivotData[$id] : [];
        $sumaCatalogados = 0;
        foreach ($tiposGasto as $tg) {
            $idg = $tg['id'];
            $val = isset($detalleOtros[$idg]) ? $detalleOtros[$idg] : 0;
            $row[] = $val;
            $sumaCatalogados += $val;
        }
        $restanteOtros = $otro - $sumaCatalogados;
        $row[] = $restanteOtros;
        $row[] = $numdoc;
        $row[] = $dpi;
        $row[] = $nit;
        $row[] = $asesor;
        $row[] = $nomgrupo;
        $row[] = $agencia;
        $row[] = $usuario;
        $row[] = $formapago;
        $row[] = $fechabanco;
        $row[] = $nombrebanco;
        $row[] = $cuentabanco;
        $row[] = $fechaOperacionSistema;
        $activa->fromArray($row, null, 'A' . $i);

        // Columnas a formatear como texto para evitar notación científica

        $otrosCols = count($tiposGasto) + 1; // subcategorías + columna "OTROS"

        $baseFinal = 8 + $otrosCols; // columna NUMDOC en índice 1-base
        $textIndexes = [
            2,                  // CUENTA
            $baseFinal,         // NUMDOC
            $baseFinal + 1,     // DPI CLIENTE
            $baseFinal + 2,     // NIT CLIENTE
            $baseFinal + 10     // CUENTA BANCARIA
        ];
        foreach ($textIndexes as $colIdx) {
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx) . $i;
            $value = $activa->getCell($coord)->getValue();
            $activa->setCellValueExplicit($coord, (string)$value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        }
        $fila++;
        $i++;
    }

    $columnCount = count($encabezado_tabla);
    for ($c = 1; $c <= $columnCount; $c++) {
        $columna = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
        $activa->getColumnDimension($columna)->setAutoSize(true);
    }

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Ingresos Diarios",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
