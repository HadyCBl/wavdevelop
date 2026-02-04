<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../../fpdf/fpdf.php';
require __DIR__ . '/../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../includes/Config/PermissionHandler.php';
// require_once __DIR__ . '/../../../../includes/Config/Configuracion.php';

use App\Configuracion;
use Micro\Generic\Utf8;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$usuario = $_SESSION["id"];
$idagencia = $_SESSION["id_agencia"];

$datos = $_POST["datosval"];
$inputs = $datos[0];
$tipo = $_POST["tipo"];

if (!isset($datos[3])) {
    $opResult = array('status' => 0, 'mensaje' => "Seleccione al menos un usuario");
    echo json_encode($opResult);
    return;
}
$archivo = $datos[3];
$users = $archivo[0];
$fechareporte = $inputs[0];
//reportes([[`fecha`],[],[],[checkedValues]], tipo, `consolidacion_caja`, download, 0, 'dfecope', 'monto', 2, 'Montos', 0);

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

$where = " AND tcac.id_usuario IN (" . implode(",", $users) . ")";

// $opResult = array('status' => 0, 'mensaje' => $queryfinal);
// echo json_encode($opResult);
// return;

$showmensaje = false;
try {
    $database->openConnection();

    $configuracion = new Configuracion($database);
    $valor = $configuracion->getValById(1);
    $camposFechas = ($valor === '1')
        ? ["a.created_at", "b.created_at", "c.created_at", "d.created_at", "ck.DFECSIS", "ckk.DFECSIS", "ck2.DFECSIS", "op.created_at", "op.created_at", "ck.DFECSIS", "ckk.DFECSIS", "ck2.DFECSIS"]
        : ["a.dfecope", "b.dfecope", "c.dfecope", "d.dfecope", "ck.DFECPRO", "ckk.DFECPRO", "ck2.DFECPRO", "op.fecha", "op.fecha", "ck.DFECPRO", "ckk.DFECPRO", "ck2.DFECPRO"];

    $queryfinal = "SELECT age.id_agencia id,SUM(saldo_inicial) saldo_inicial,SUM(saldo_final) saldo_final,fecha_apertura,fecha_cierre, tu.nombre AS nombres, tu.apellido AS apellidos, tu.usu AS usuario, age.nom_agencia,CAST(tcac.created_at AS TIME) AS hora_apertura, CAST(tcac.updated_at AS TIME) AS hora_cierre,
        SUM((SELECT IFNULL(SUM(a.monto) ,0) FROM ahommov a WHERE a.ctipdoc='E' AND a.cestado!=2 AND a.ctipope = 'D' AND DATE({$camposFechas[0]}) = tcac.fecha_apertura AND a.created_by = tcac.id_usuario)) AS ingresos_ahorros,
        SUM((SELECT IFNULL(SUM(b.monto) ,0) FROM ahommov b WHERE b.ctipdoc='E' AND b.cestado!=2 AND b.ctipope = 'R' AND DATE({$camposFechas[1]}) = tcac.fecha_apertura AND b.created_by = tcac.id_usuario)) AS egresos_ahorros,
        SUM((SELECT IFNULL(SUM(c.monto) ,0)+IFNULL(SUM(c.cuota_ingreso) ,0) FROM aprmov c WHERE c.ctipdoc='E' AND c.cestado!=2 AND c.ctipope = 'D' AND DATE({$camposFechas[2]}) = tcac.fecha_apertura AND c.created_by = tcac.id_usuario)) AS ingresos_aportaciones,
        SUM((SELECT IFNULL(SUM(d.monto) ,0) FROM aprmov d WHERE d.ctipdoc='E' AND d.cestado!=2 AND d.ctipope = 'R' AND DATE({$camposFechas[3]}) = tcac.fecha_apertura AND d.created_by = tcac.id_usuario)) AS egresos_aportaciones,
        SUM((SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='1' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[4]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario)) AS desembolsos_creditos,
        SUM((SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='1' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[5]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario)) AS desembolsos_creditos2,
        SUM((SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='1' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[6]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario)) AS pagos_creditos,
        (SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '1' AND DATE({$camposFechas[7]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_ingresos,
        (SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '2' AND DATE({$camposFechas[8]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_egresos,
        (SELECT IFNULL(SUM(haber),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (10,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS egresosbancos,
        (SELECT IFNULL(SUM(debe),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (7,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS ingresosbancos,
        SUM((SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=1 AND id_caja = tcac.id)) AS ingresos_caja,    
        SUM((SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=2 AND id_caja = tcac.id)) AS egresos_caja,
        SUM((SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='2' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[9]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario)) AS desembolsos_creditos_bancos,
        SUM((SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='2' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[10]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario)) AS desembolsos_creditos2_bancos,
        SUM((SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='2' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[11]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario)) AS pagos_creditos_bancos

        FROM tb_caja_apertura_cierre tcac 
		  INNER JOIN tb_usuario tu ON tcac.id_usuario = tu.id_usu 
		  INNER JOIN tb_agencia age ON age.id_agencia=tu.id_agencia
        WHERE ((tcac.fecha_apertura=? AND  (tcac.estado='2' OR tcac.estado='1'))) $where GROUP BY tcac.id_usuario ORDER BY tcac.fecha_apertura ASC";


    $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (4,14) AND id_usuario=? AND estado=1", [$idusuario]);
    $accessHandler = new PermissionHandler($permisos, 4);

    $nameColumn = ($accessHandler->isLow()) ? "desembolsos_creditos" : "desembolsos_creditos2";

    $movBancos = new PermissionHandler($permisos, 14);

    $result = $database->getAllResults($queryfinal, [$fechareporte]);
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

foreach ($result as $key => $value) {
    $result[$key]['pagos_creditos'] = ($movBancos->isLow()) ? $result[$key]['pagos_creditos'] : ($result[$key]['pagos_creditos_bancos'] + $result[$key]['pagos_creditos']);
    $result[$key][$nameColumn] = ($movBancos->isLow()) ? $result[$key][$nameColumn] : ($result[$key]['desembolsos_creditos_bancos'] + $result[$key][$nameColumn]);
    $result[$key]['egresosbancos'] = ($movBancos->isLow()) ? $result[$key]['egresosbancos'] : ($result[$key]['egresosbancos'] + $result[$key]['pagos_creditos_bancos']);
}

switch ($tipo) {
    case 'xlsx';
        printxls($result, $nameColumn);
        break;
    case 'pdf':
        printpdf($result, $info, $nameColumn);
        break;
    case 'show':
        //showresults($data);
        break;
}
// $opResult = array('status' => 0, 'mensaje' => "HOLISI");
// echo json_encode($opResult);
// return;

function printpdf($datos, $info, $nameColumn)
{
    $sumaingresos = (array_sum(array_column($datos, 'ingresos_ahorros')) +
        array_sum(array_column($datos, 'ingresos_aportaciones')) +
        array_sum(array_column($datos, 'pagos_creditos')) +
        array_sum(array_column($datos, 'ingresosbancos')) +
        array_sum(array_column($datos, 'ingresos_caja')) +
        array_sum(array_column($datos, 'otros_ingresos')));
    // $sumaegresos = ($datos[0]['egresos_ahorros'] + $datos[0]['egresos_aportaciones'] + $datos[0][$nameColumn] + $datos[0]['otros_egresos']);
    // $saldomov = (($datos[0]['sumaingresos']) - ($datos[0]['sumaegresos']));
    $sumaegresos = (array_sum(array_column($datos, 'egresos_ahorros')) +
        array_sum(array_column($datos, 'egresos_aportaciones')) +
        array_sum(array_column($datos, $nameColumn)) +
        array_sum(array_column($datos, 'egresosbancos')) +
        array_sum(array_column($datos, 'egresos_caja')) +
        array_sum(array_column($datos, 'otros_egresos')));

    $saldomov = ($sumaingresos - $sumaegresos);
    $saldoinicial = array_sum(array_column($datos, 'saldo_inicial'));
    $saldofinal = $saldoinicial + $saldomov;
    // $datos[0]['saldofinal'] = $datos[0]['saldo_inicial'] + $datos[0]['saldomov'];

    //INICIO PARA FORMAR EL REPORTE
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    ;
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

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit)
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
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 8);
            //$this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 10, 33);
            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(1);
            $this->Cell(0, 2, $_SESSION['id'], 0, 1, 'R');
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(3);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea = 30;

    //CUERPO DEL REPORTE
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->Cell(0, $tamanio_linea, ("RESUMEN DE MOVIMIENTOS DE CAJA"), 0, 0, 'C');
    $pdf->Ln(3);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(5);
    // $pdf->CellFit($ancho_linea - 14, $tamanio_linea, ($params[1] != 0) ? 'CAJERO: ' : 'AGENCIA: ', 0, 0, 'L', 0, '', 1, 0);
    // $pdf->SetFont($fuente, '', 9);
    // $pdf->CellFit($ancho_linea + 79, $tamanio_linea, ($params[1] != 0) ? ($datos[0]['nombres'] . ' ' . $datos[0]['apellidos']) : $datos[0]['nom_agencia'], 0, 0, 'L', 0, '', 1, 0);
    // $pdf->SetFont($fuente, 'B', 9);
    // $pdf->CellFit($ancho_linea - 10, $tamanio_linea, 'USUARIO:', 0, 0, 'R', 0, '', 1, 0);
    // $pdf->SetFont($fuente, '', 9);
    // $pdf->CellFit($ancho_linea + 15, $tamanio_linea, (($datos[0]['usuario'] == '' || $datos[0]['usuario'] == null) ? ' ' : Utf8::decode($datos[0]['usuario'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit(20, $tamanio_linea, 'FECHA:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(0, $tamanio_linea, (($datos[0]['fecha_apertura'] == '' || $datos[0]['fecha_apertura'] == null) ? ' ' : (date('d-m-Y', strtotime($datos[0]['fecha_apertura'])))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);

    $pdf->Ln(6);

    $nomusers = array_column($datos, 'nombres');
    $apeusers = array_column($datos, 'apellidos');

    $usersnom = array_map(function ($nombre, $apellido) {
        return ['nombres' => $nombre, 'apellidos' => $apellido];
    }, $nomusers, $apeusers);

    // $pdf->SetFont($fuente, '', 9);
    $nombresap = "";
    foreach ($usersnom as $key => $nomi) {
        $nombresap .= decode_utf8($nomi['nombres'] . ' ' . $nomi['apellidos']);
        $nombresap .= ($key !== array_key_last($usersnom)) ? ', ' : '';
    }

    $pdf->MultiCell(0, $tamanio_linea, "CAJAS: " . $nombresap, 'B', 'L');

    $pdf->Ln(5);

    //SALDO INICIAL
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 125, $tamanio_linea, '1. SALDO INICIAL DEL SISTEMA', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, 'Q ' . number_format((float) $saldoinicial, 2, '.', ''), 1, 0, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(6);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea, 'Saldo inicial', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, 'Q ' . number_format((float) $saldoinicial, 2, '.', ''), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(7);

    //SALDO FINAL
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 125, $tamanio_linea, '2. MOVIMIENTOS EN EL SISTEMA', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format($saldomov, 2, '.', '')), 1, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);
    //Ingresos
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea, 'Ingresos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, 'Q ' . number_format($sumaingresos, 2, '.', ''), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Ahorros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'ingresos_ahorros')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Aportaciones', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'ingresos_aportaciones')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, Utf8::decode('Pagos créditos'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'pagos_creditos')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Otros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'otros_ingresos')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Cheques a caja', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'ingresosbancos')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Incrementos de caja', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'ingresos_caja')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    //Egresos
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea, 'Egresos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format($sumaegresos, 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Ahorros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'egresos_ahorros')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Aportaciones', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'egresos_aportaciones')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, Utf8::decode('Desembolso créditos'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, $nameColumn)), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Otros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'otros_egresos')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, decode_utf8('Depósitos a bancos'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'egresosbancos')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Decrementos de caja', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float) array_sum(array_column($datos, 'egresos_caja')), 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    //LINEA SEPARADORA
    $pdf->Ln(4);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 125, $tamanio_linea, 'SALDO FINAL', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format($saldofinal, 2, '.', '')), 0, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(4);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->Ln(6);

    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell(0, $tamanio_linea, 'OBSERVACIONES', 0, 0, 'L');
    $pdf->Ln(8);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 0, 'C');
    $pdf->Ln(7);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 0, 'C');
    $pdf->Ln(7);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 0, 'C');

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "ConsolidacionCajas",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}


function printxls($datos, $nameColumn)
{
    $sumaingresos = (array_sum(array_column($datos, 'ingresos_ahorros')) +
        array_sum(array_column($datos, 'ingresos_aportaciones')) +
        array_sum(array_column($datos, 'pagos_creditos')) +
        array_sum(array_column($datos, 'ingresosbancos')) +
        array_sum(array_column($datos, 'ingresos_caja')) +
        array_sum(array_column($datos, 'otros_ingresos')));
    // $sumaegresos = ($datos[0]['egresos_ahorros'] + $datos[0]['egresos_aportaciones'] + $datos[0][$nameColumn] + $datos[0]['otros_egresos']);
    // $saldomov = (($datos[0]['sumaingresos']) - ($datos[0]['sumaegresos']));
    $sumaegresos = (array_sum(array_column($datos, 'egresos_ahorros')) +
        array_sum(array_column($datos, 'egresos_aportaciones')) +
        array_sum(array_column($datos, $nameColumn)) +
        array_sum(array_column($datos, 'egresosbancos')) +
        array_sum(array_column($datos, 'egresos_caja')) +
        array_sum(array_column($datos, 'otros_egresos')));

    $saldoinicial = (array_sum(array_column($datos, 'saldo_inicial')) === null || array_sum(array_column($datos, 'saldo_inicial')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'saldo_inicial')), 2);
    $ahorros = (array_sum(array_column($datos, 'ingresos_ahorros')) === null || array_sum(array_column($datos, 'ingresos_ahorros')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'ingresos_ahorros')), 2);
    $aportaciones = (array_sum(array_column($datos, 'ingresos_aportaciones')) === null || array_sum(array_column($datos, 'ingresos_aportaciones')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'ingresos_aportaciones')), 2);
    $creditos = (array_sum(array_column($datos, 'pagos_creditos')) === null || array_sum(array_column($datos, 'pagos_creditos')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'pagos_creditos')), 2);
    $otros = (array_sum(array_column($datos, 'otros_ingresos')) === null || array_sum(array_column($datos, 'otros_ingresos')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'otros_ingresos')), 2);
    $cheques = moneda(karely(array_sum(array_column($datos, 'ingresosbancos'))));
    $ingresos_caja = moneda($datos[0]['ingresos_caja']);


    $egresos_ahorro = (array_sum(array_column($datos, 'egresos_ahorros')) === null || array_sum(array_column($datos, 'egresos_ahorros')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'egresos_ahorros')), 2);
    $egresos_aportaciones = (array_sum(array_column($datos, 'egresos_aportaciones')) === null || array_sum(array_column($datos, 'egresos_aportaciones')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'egresos_aportaciones')), 2);
    $egresos_creditos = (array_sum(array_column($datos, $nameColumn)) === null || array_sum(array_column($datos, $nameColumn)) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, $nameColumn)), 2);
    $egresos_otros = (array_sum(array_column($datos, 'otros_egresos')) === null || array_sum(array_column($datos, 'otros_egresos')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'otros_egresos')), 2);
    $egresos_cheques = moneda(karely(array_sum(array_column($datos, 'egresosbancos'))));
    $egresos_caja = moneda($datos[0]['egresos_caja']);

    $egresos_total = (array_sum(array_column($datos, 'sumaegresos')) === null || array_sum(array_column($datos, 'sumaegresos')) === '') ? ' ' : 'Q ' . number_format((float) array_sum(array_column($datos, 'sumaegresos')), 2);
    $fecha_rep = (($datos[0]['fecha_apertura'] == '' || $datos[0]['fecha_apertura'] == null) ? ' ' : (date('d-m-Y', strtotime($datos[0]['fecha_apertura']))));
    $fuente = "Tahoma";

    $sumaingresos = empty($sumaingresos) ? 0 : $sumaingresos;
    $sumaegresos = empty($sumaegresos) ? 0 : $sumaegresos;

    $saldomov = $sumaingresos - $sumaegresos;
    $saldoinicial2 = array_sum(array_column($datos, 'saldo_inicial'));
    $saldofinal = $saldoinicial2 + $saldomov;

    $nomusers = array_column($datos, 'nombres');
    $apeusers = array_column($datos, 'apellidos');

    $usersnom = array_map(function ($nombre, $apellido) {
        return ['nombres' => $nombre, 'apellidos' => $apellido];
    }, $nomusers, $apeusers);
    $nombresap = "";
    foreach ($usersnom as $key => $nomi) {
        $nombresap .= decode_utf8($nomi['nombres'] . ' ' . $nomi['apellidos']);
        $nombresap .= ($key !== array_key_last($usersnom)) ? ', ' : '';
    }

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
    $activa->setTitle("Arqueo");

    $activa->setCellValue('B2', 'Fecha');
    $activa->setCellValue('C2', $fecha_rep);
    $activa->setCellValue('D2', 'Cajas');
    $activa->setCellValue('E2', $nombresap);

    $activa->setCellValue('A4', '#');
    $activa->setCellValue('B4', 'Descripcion');
    $activa->setCellValue('C4', 'Ingresos');
    $activa->setCellValue('D4', 'Egresos');

    $activa->setCellValue('A5', '0');
    $activa->setCellValue('A6', '1');
    $activa->setCellValue('A7', '2');
    $activa->setCellValue('A8', '3');
    $activa->setCellValue('A9', '4');
    $activa->setCellValue('A10', '5');
    $activa->setCellValue('A11', '6');

    $activa->setCellValue('A12', '1');
    $activa->setCellValue('A13', '2');
    $activa->setCellValue('A14', '3');
    $activa->setCellValue('A15', '4');
    $activa->setCellValue('A16', '5');
    $activa->setCellValue('A17', '6');

    $activa->setCellValue("B" . getContador(5), 'Saldo inicial');
    $activa->setCellValue("B" . getContador(), 'Ahorro');
    $activa->setCellValue("B" . getContador(), 'Aportaciones');
    $activa->setCellValue("B" . getContador(), 'Cheque a caja ');
    $activa->setCellValue("B" . getContador(), 'Pagos creditos');
    $activa->setCellValue("B" . getContador(), 'Otros');
    $activa->setCellValue("B" . getContador(), 'Incrementos de caja');

    $activa->setCellValue("B" . getContador(), 'Ahorro');
    $activa->setCellValue("B" . getContador(), 'Aportaciones');
    $activa->setCellValue("B" . getContador(), 'Deposito a bancos');
    $activa->setCellValue("B" . getContador(), 'Desembolso de Creditos');
    $activa->setCellValue("B" . getContador(), 'Otros');
    $activa->setCellValue("B" . getContador(), 'Decrementos de caja');


    $activa->setCellValue("B" . getContador(), 'Total');
    $activa->setCellValue("B" . getContador() + 1, 'Total Arqueo de Caja');


    //INGRESOS
    $activa->setCellValue("C" . getContador(5), $saldoinicial2);
    $activa->setCellValue("C" . getContador(), $ahorros);
    $activa->setCellValue("C" . getContador(), $aportaciones);
    $activa->setCellValue("C" . getContador(), $cheques);
    $activa->setCellValue("C" . getContador(), $creditos);

    $activa->setCellValue("C" . getContador(), $otros);
    $activa->setCellValue("C" . getContador(), $ingresos_caja);

    //egresos
    $activa->setCellValue("D" . getContador(), $egresos_ahorro);
    $activa->setCellValue("D" . getContador(), $egresos_aportaciones);
    $activa->setCellValue("D" . getContador(), $egresos_cheques);
    $activa->setCellValue("D" . getContador(), $egresos_creditos);
    $activa->setCellValue("D" . getContador(), $egresos_otros);
    $activa->setCellValue("D" . getContador(), $egresos_caja);


    $rowTotales = getContador();

    $activa->setCellValue("C" . $rowTotales, $sumaingresos + $saldoinicial2);
    $activa->setCellValue("D" . $rowTotales, $sumaegresos);

    $rowSaldofinal = getContador() + 1;

    $activa->setCellValue("C" . $rowSaldofinal, $saldofinal);

    // Establece ancho 
    $colorHex = 'FF90EE90';
    $activa->getColumnDimension('B')->setWidth(26); // B
    $activa->getColumnDimension('C')->setWidth(30); // C
    $activa->getColumnDimension('D')->setWidth(25); // D
    $activa->getColumnDimension('E')->setWidth(25); // E
    $activa->getColumnDimension('F')->setWidth(25); // F
    $activa->getColumnDimension('G')->setWidth(30); // G
    $activa->getColumnDimension('H')->setWidth(15); // H
    $activa->getColumnDimension('I')->setWidth(20); // I

    $activa->getStyle('C5')->getNumberFormat()->setFormatCode('"Q"#,##0.00');
    $activa->getStyle('D5')->getNumberFormat()->setFormatCode('"Q"#,##0.00');
    $activa->getStyle('C6')->getNumberFormat()->setFormatCode('"Q"#,##0.00');

    // Establece fuente
    $activa->getStyle('B2:I2')->getFont()->setName($fuente)->setSize(9);
    $activa->getStyle('B2')->getFill()->setFillType(Fill::FILL_SOLID);

    $activa->getStyle('B2')->getFill()->getStartColor()->setARGB('FFCCE5FF'); //creo que no funka xdd
    $activa->getStyle('F5')->getFill()->getStartColor()->setARGB('FFCCE5FF'); //creo que no funka xdd
    $activa->getStyle('F6')->getFill()->getStartColor()->setARGB('FFCCE5FF');
    $activa->getStyle('D2')->getFill()->setFillType(Fill::FILL_SOLID);
    $activa->getStyle('D2')->getFill()->getStartColor()->setARGB('FFCCE5FF');
    $activa->getStyle('F2')->getFill()->setFillType(Fill::FILL_SOLID);
    $activa->getStyle('F2')->getFill()->getStartColor()->setARGB('FFCCE5FF');
    $activa->getStyle('H2')->getFill()->setFillType(Fill::FILL_SOLID);
    $activa->getStyle('H2')->getFill()->getStartColor()->setARGB('FFCCE5FF');
    $activa->getStyle('A4:D4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $activa->getStyle('A4:D4')->getFont()->setBold(true)->setSize(11);
    $activa->getStyle('A4:D4')->getFill()->setFillType(Fill::FILL_SOLID);
    $activa->getStyle('A4:D4')->getFill()->getStartColor()->setARGB('FFBDD7EE');
    $activa->getStyle('C18')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorHex);
    $activa->getStyle('C18')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('B18')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorHex);
    $activa->getStyle('B18')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('D18')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorHex);
    $activa->getStyle('D18')->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    // bordes 
    $activa->getStyle('A4:D4')->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

    // Guardar el archivo XLSX
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Arqueo de caja",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
