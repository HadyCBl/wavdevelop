<?php

/**
 * Este script maneja la generación de un informe de arqueo de caja.
 * 
 * @package MicrosystemPlus
 * @subpackage Views
 * @category Reports
 * @file arqueo_caja.php
 * 
 */
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

$datos = $_POST["datosval"];
$archivo = $datos[3];
$usuario = $_SESSION["id"];
$idagencia = $_SESSION["id_agencia"];
$tipo = $_POST["tipo"];
$codregistro = $archivo[0];
$fechareporte = ($codregistro == 0) ? $archivo[1] : "0000-00-00";


/**
 * Este script realiza una consulta a la base de datos para obtener información sobre los movimientos en caja de usuario, agencia o en una fecha especifica.
 * 
 * Variables:
 * - $queryfinal: Consulta que se ejecutará, dependiendo del valor de $codregistro: si es 0 significa que no se mando ningun id de arqueo de caja, se tomara como consolidado por fecha.
 * - $parametros: Parámetros para la consulta SQL, dependiendo del valor de $codregistro.
 * - $showmensaje: Bandera para mostrar mensajes de error personalizados.
 * 
 * Proceso:
 * 1. Abre una conexión a la base de datos.
 * 2. Verifica los permisos del usuario para separar los desembolsos en caja de cada analista.
 * 3. Ejecuta la consulta principal ($queryfinal) con los parámetros ($parametros).
 * 4. Si no se encuentran resultados, lanza una excepción.
 * 5. Obtiene información adicional sobre la agencia y la institución.
 * 6. Si no se encuentra la información de la agencia, lanza una excepción.
 * 7. Cierra la conexión a la base de datos.
 * 
 * Manejo de errores:
 * - Si ocurre una excepción, se registra el error y se muestra un mensaje apropiado al usuario.
 * - Si $showmensaje es verdadero, se muestra el mensaje de la excepción.
 * - Si $showmensaje es falso, se muestra un mensaje genérico con un código de error.
 */

$showmensaje = false;
try {
    $database->openConnection();

    $configuracion = new Configuracion($database);
    $valor = $configuracion->getValById(1);
    $camposFechas = ($valor === '1')
        ? ["a.created_at", "b.created_at", "c.created_at", "d.created_at", "ck.DFECSIS", "ckk.DFECSIS", "ck2.DFECSIS", "op.created_at", "op.created_at", "ck.DFECSIS", "ckk.DFECSIS", "ck2.DFECSIS"]
        : ["a.dfecope", "b.dfecope", "c.dfecope", "d.dfecope", "ck.DFECPRO", "ckk.DFECPRO", "ck2.DFECPRO", "op.fecha", "op.fecha", "ck.DFECPRO", "ckk.DFECPRO", "ck2.DFECPRO"];

    $op1 = "SELECT tcac.*, tu.nombre AS nombres, tu.apellido AS apellidos, tu.usu AS usuario, CAST(tcac.created_at AS TIME) AS hora_apertura, CAST(tcac.updated_at AS TIME) AS hora_cierre,
        (SELECT IFNULL(SUM(a.monto) ,0) FROM ahommov a WHERE a.ctipdoc='E' AND a.cestado!=2 AND a.ctipope = 'D' AND DATE({$camposFechas[0]}) = tcac.fecha_apertura AND a.created_by = tcac.id_usuario) AS ingresos_ahorros,
        (SELECT IFNULL(SUM(b.monto) ,0) FROM ahommov b WHERE b.ctipdoc='E' AND b.cestado!=2 AND b.ctipope = 'R' AND DATE({$camposFechas[1]}) = tcac.fecha_apertura AND b.created_by = tcac.id_usuario) AS egresos_ahorros,
        (SELECT IFNULL(SUM(c.monto) ,0)+IFNULL(SUM(c.cuota_ingreso) ,0) FROM aprmov c WHERE c.ctipdoc='E' AND c.cestado!=2 AND c.ctipope = 'D' AND DATE({$camposFechas[2]}) = tcac.fecha_apertura AND c.created_by = tcac.id_usuario) AS ingresos_aportaciones,
        (SELECT IFNULL(SUM(d.monto) ,0) FROM aprmov d WHERE d.ctipdoc='E' AND d.cestado!=2 AND d.ctipope = 'R' AND DATE({$camposFechas[3]}) = tcac.fecha_apertura AND d.created_by = tcac.id_usuario) AS egresos_aportaciones,
        (SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='1' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[4]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos,
        (SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='1' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[5]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2,
        (SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='1' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[6]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos,
        (SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '1' AND DATE({$camposFechas[7]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_ingresos,
        (SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '2' AND DATE({$camposFechas[8]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_egresos,  
        (SELECT IFNULL(SUM(haber),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (10,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS egresosbancos,
                (SELECT IFNULL(SUM(debe),0) FROM ctb_mov 
                    WHERE id_ctb_diario IN (SELECT id FROM ctb_diario dia WHERE estado=1 AND dia.id_ctb_tipopoliza in (7,6) AND dia.id_tb_usu=tcac.id_usuario AND dia.feccnt=tcac.fecha_apertura) 
                    AND id_ctb_nomenclatura IN (SELECT id_nomenclatura_caja FROM tb_agencia INNER JOIN tb_usuario uss ON uss.id_usu WHERE uss.id_usu=tcac.id_usuario)) AS ingresosbancos,
        (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=1 AND id_caja = tcac.id) AS ingresos_caja,    
        (SELECT IFNULL(SUM(total),0) FROM tb_movimientos_caja WHERE estado=2 AND tipo=2 AND id_caja = tcac.id) AS egresos_caja,
        (SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='2' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[9]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario) AS desembolsos_creditos_bancos,
        (SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='2' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[10]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario) AS desembolsos_creditos2_bancos,
        (SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='2' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[11]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario) AS pagos_creditos_bancos

            FROM tb_caja_apertura_cierre tcac INNER JOIN tb_usuario tu ON tcac.id_usuario = tu.id_usu 
            WHERE tcac.id = ? AND (tcac.estado='2' OR tcac.estado='1')";

    $op2 = "SELECT age.id_agencia id,SUM(saldo_inicial) saldo_inicial,SUM(saldo_final) saldo_final,fecha_apertura,fecha_cierre, tu.nombre AS nombres, tu.apellido AS apellidos, tu.usu AS usuario, age.nom_agencia,CAST(tcac.created_at AS TIME) AS hora_apertura, CAST(tcac.updated_at AS TIME) AS hora_cierre,
        SUM((SELECT IFNULL(SUM(a.monto) ,0) FROM ahommov a WHERE a.ctipdoc='E' AND a.cestado!=2 AND a.ctipope = 'D' AND DATE({$camposFechas[0]}) = tcac.fecha_apertura AND a.created_by = tcac.id_usuario)) AS ingresos_ahorros,
        SUM((SELECT IFNULL(SUM(b.monto) ,0) FROM ahommov b WHERE b.ctipdoc='E' AND b.cestado!=2 AND b.ctipope = 'R' AND DATE({$camposFechas[1]}) = tcac.fecha_apertura AND b.created_by = tcac.id_usuario)) AS egresos_ahorros,
        SUM((SELECT IFNULL(SUM(c.monto) ,0)+IFNULL(SUM(c.cuota_ingreso) ,0) FROM aprmov c WHERE c.ctipdoc='E' AND c.cestado!=2 AND c.ctipope = 'D' AND DATE({$camposFechas[2]}) = tcac.fecha_apertura AND c.created_by = tcac.id_usuario)) AS ingresos_aportaciones,
        SUM((SELECT IFNULL(SUM(d.monto) ,0) FROM aprmov d WHERE d.ctipdoc='E' AND d.cestado!=2 AND d.ctipope = 'R' AND DATE({$camposFechas[3]}) = tcac.fecha_apertura AND d.created_by = tcac.id_usuario)) AS egresos_aportaciones,
        SUM((SELECT IFNULL(SUM(ck.KP) ,0) FROM CREDKAR ck WHERE ck.FormPago='1' AND ck.CTIPPAG = 'D' AND  DATE({$camposFechas[4]}) = tcac.fecha_apertura AND ck.CESTADO != 'X' AND ck.CCODUSU = tcac.id_usuario)) AS desembolsos_creditos,
        SUM((SELECT IFNULL(SUM(ckk.KP) ,0) FROM CREDKAR ckk INNER JOIN cremcre_meta mm2 ON mm2.CCODCTA=ckk.CCODCTA WHERE ckk.FormPago='1' AND ckk.CTIPPAG = 'D' AND  DATE({$camposFechas[5]}) = tcac.fecha_apertura AND ckk.CESTADO != 'X' AND mm2.CodAnal = tcac.id_usuario)) AS desembolsos_creditos2,
        SUM((SELECT IFNULL(SUM(ck2.NMONTO) ,0)  FROM CREDKAR ck2 WHERE ck2.FormPago='1' AND ck2.CTIPPAG = 'P' AND  DATE({$camposFechas[6]}) = tcac.fecha_apertura AND ck2.CESTADO != 'X' AND ck2.CCODUSU = tcac.id_usuario)) AS pagos_creditos,
        SUM((SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '1' AND DATE({$camposFechas[7]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_ingresos,
        SUM((SELECT IFNULL(SUM(opm.monto) ,0)  FROM otr_pago_mov opm INNER JOIN otr_pago op ON opm.id_otr_pago = op.id INNER JOIN otr_tipo_ingreso oti ON opm.id_otr_tipo_ingreso = oti.id WHERE op.formaPago='efectivo' AND op.estado = '1' AND opm.estado = '1' AND oti.estado = '1' AND oti.tipo = '2' AND DATE({$camposFechas[8]}) = tcac.fecha_apertura AND ((op.created_by = tcac.id_usuario AND op.tipoadicional!=3) OR (op.tipoadicional=3 AND op.cliente=tcac.id_usuario))) AS otros_egresos,
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
        WHERE ((tcac.fecha_apertura=? AND  (tcac.estado='2' OR tcac.estado='1'))) AND tu.id_agencia=? ORDER BY tcac.fecha_apertura ASC";
    $queryfinal = ($codregistro != 0) ? $op1 : $op2;
    $parametros = ($codregistro != 0) ? [$codregistro] : [$fechareporte, $idagencia];

    $permisos = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (4,14) AND id_usuario=? AND estado=1", [$idusuario]);
    $accessHandler = new PermissionHandler($permisos, 4);

    $nameColumn = ($accessHandler->isLow()) ? "desembolsos_creditos" : "desembolsos_creditos2";

    $result = $database->getAllResults($queryfinal, $parametros);
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

    // $permisos3 = $database->selectColumns("tb_autorizacion", ["id", "id_restringido"], "id_restringido IN (14) AND id_usuario=? AND estado=1", [$idusuario]);
    $movBancos = new PermissionHandler($permisos, 14);

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
        printxls($result, $info, [$idusuario, $codregistro], $nameColumn, $movBancos);
        break;
    case 'pdf':
        printpdf($result, $info, [$idusuario, $codregistro], $nameColumn, $movBancos);
        break;
    case 'show':
        //showresults($data);
        break;
}

function printpdf($datos, $info, $params, $nameColumn,$movBancos)
{

    $datos[0]['pagos_creditos'] = ($movBancos->isLow()) ? $datos[0]['pagos_creditos'] : ($datos[0]['pagos_creditos_bancos'] + $datos[0]['pagos_creditos']);
    $datos[0][$nameColumn] = ($movBancos->isLow()) ? $datos[0][$nameColumn] : ($datos[0]['desembolsos_creditos_bancos'] + $datos[0][$nameColumn]);
    $datos[0]['egresosbancos'] = ($movBancos->isLow()) ? $datos[0]['egresosbancos'] : ($datos[0]['egresosbancos'] + $datos[0]['pagos_creditos_bancos']);


    $datos[0]['sumaingresos'] = ($datos[0]['ingresos_ahorros'] + $datos[0]['ingresos_aportaciones'] + $datos[0]['pagos_creditos'] + $datos[0]['otros_ingresos'] + $datos[0]['ingresosbancos'] + $datos[0]['ingresos_caja']);
    $datos[0]['sumaegresos'] = ($datos[0]['egresos_ahorros'] + $datos[0]['egresos_aportaciones'] + $datos[0][$nameColumn] + $datos[0]['otros_egresos'] + $datos[0]['egresosbancos'] + $datos[0]['egresos_caja']);
    $datos[0]['saldomov'] = (($datos[0]['sumaingresos']) - ($datos[0]['sumaegresos']));

    // $datos[0]['sumasiguales'] = ($datos[0]['saldo_inicial'] - abs($datos[0]['saldofinal']));
    $datos[0]['saldofinal'] = $datos[0]['saldo_inicial'] + $datos[0]['saldomov'];

    //INICIO PARA FORMAR EL REPORTE
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
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
    $pdf->Cell(0, $tamanio_linea, decode_utf8("ARQUEO DE CAJA"), 0, 0, 'C');
    $pdf->Ln(3);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea - 14, $tamanio_linea, ($params[1] != 0) ? 'CAJERO: ' : 'AGENCIA: ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 79, $tamanio_linea, ($params[1] != 0) ? decode_utf8($datos[0]['nombres'] . ' ' . $datos[0]['apellidos']) : $datos[0]['nom_agencia'], 0, 0, 'L', 0, '', 1, 0);
    // $pdf->SetFont($fuente, 'B', 9);
    // $pdf->CellFit($ancho_linea - 10, $tamanio_linea, 'USUARIO:', 0, 0, 'R', 0, '', 1, 0);
    // $pdf->SetFont($fuente, '', 9);
    // $pdf->CellFit($ancho_linea + 15, $tamanio_linea, (($datos[0]['usuario'] == '' || $datos[0]['usuario'] == null) ? ' ' : decode_utf8($datos[0]['usuario'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, 'FECHA DE APERTURA:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 8, $tamanio_linea, (($datos[0]['fecha_apertura'] == '' || $datos[0]['fecha_apertura'] == null) ? ' ' : (date('d-m-Y', strtotime($datos[0]['fecha_apertura'])))) . ' ' . (($datos[0]['hora_apertura'] == '' || $datos[0]['hora_apertura'] == null) ? ' ' : decode_utf8($datos[0]['hora_apertura'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'FECHA DE CIERRE:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 7, $tamanio_linea, (($datos[0]['fecha_cierre'] == '' || $datos[0]['fecha_cierre'] == null) ? ' ' : (date('d-m-Y', strtotime($datos[0]['fecha_cierre'])))) . ' ' . (($datos[0]['hora_cierre'] == '' || $datos[0]['hora_cierre'] == null) ? ' ' : decode_utf8($datos[0]['hora_cierre'])), 0, 0, 'L', 0, '', 1, 0);
    if ($params[1] != 0) {
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->CellFit($ancho_linea - 6, $tamanio_linea, 'ARQUEO No.:', 0, 0, 'R', 0, '', 1, 0);
        $pdf->SetFont($fuente, '', 9);
        $pdf->CellFit($ancho_linea - 4, $tamanio_linea, 'A-0' . (($datos[0]['id'] == '' || $datos[0]['id'] == null) ? ' ' : ($datos[0]['id'])) . '0', 'B', 0, 'C', 0, '', 1, 0);
    }

    $pdf->Ln(4);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(5);

    //SALDO INICIAL
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 125, $tamanio_linea, '1. SALDO INICIAL DEL SISTEMA', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, (($datos[0]['saldo_inicial'] == '' || $datos[0]['saldo_inicial'] == null) ? ' ' : 'Q ' . number_format((float)$datos[0]['saldo_inicial'], 2, '.', '')), 1, 0, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(6);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea, 'Saldo inicial', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, (($datos[0]['saldo_inicial'] == '' || $datos[0]['saldo_inicial'] == null) ? ' ' : 'Q ' . number_format((float)$datos[0]['saldo_inicial'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(7);

    //SALDO FINAL
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 125, $tamanio_linea, '2. MOVIMIENTOS EN EL SISTEMA', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format($datos[0]['saldomov'], 2, '.', '')), 1, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);
    //Ingresos
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea, 'Ingresos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, (($datos[0]['sumaingresos'] == '' || $datos[0]['sumaingresos'] == null) ? ' ' : 'Q ' . number_format($datos[0]['sumaingresos'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Ahorros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['ingresos_ahorros'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Aportaciones', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['ingresos_aportaciones'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Cheques a caja ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['ingresosbancos'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, decode_utf8('Pagos créditos'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['pagos_creditos'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Otros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['otros_ingresos'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Incrementos de caja ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, moneda($datos[0]['ingresos_caja']), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    //Egresos
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 80, $tamanio_linea, 'Egresos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format($datos[0]['sumaegresos'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Ahorros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['egresos_ahorros'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Aportaciones', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['egresos_aportaciones'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Depositos a bancos', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['egresosbancos'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, decode_utf8('Desembolso créditos'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0][$nameColumn], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, 'Otros', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format((float)$datos[0]['otros_egresos'], 2, '.', '')), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea - 10, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 35, $tamanio_linea, ('Decrementos de caja'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, moneda($datos[0]['egresos_caja']), 'B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    //LINEA SEPARADORA
    $pdf->Ln(4);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea + 125, $tamanio_linea, 'TOTAL CAJA ARQUEO', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 5, $tamanio_linea, ('Q ' . number_format($datos[0]['saldofinal'], 2, '.', '')), 0, 0, 'R', 0, '', 1, 0);
    $pdf->Ln(4);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->Ln(6);

    //FIRMAS
    $pdf->Ln(2);
    $pdf->firmas(2, ['Cajero', 'Gerencia']);
    $pdf->Ln(25);

    //OBSERVACIONES
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell(0, $tamanio_linea, 'OBSERVACIONES', 0, 0, 'L');
    $pdf->Ln(8);
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 0, 'C');
    $pdf->Ln(7);
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
        'namefile' => "Arqueo_caja_" . $datos[0]['id'] . "0",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function printxls($datos, $info, $params, $nameColumn, $movBancos)
{
    /**
     * Este script genera un reporte de arqueo de caja en formato Excel utilizando la biblioteca PHPSpreadsheet.
     * 
     * Cálculos de ingresos, egresos y saldos:
     * - Calcula la suma de ingresos y egresos.
     * - Calcula el saldo de movimientos y el saldo final.
     * 
     * Configuración del archivo Excel:
     * - Crea una nueva hoja de cálculo y establece sus propiedades.
     * - Define el título de la hoja activa como "Arqueo".
     * 
     * Inserción de datos en la hoja de cálculo:
     * - Inserta información sobre el cajero o agencia, fechas y horas de apertura y cierre.
     * - Inserta los datos de ingresos y egresos en las celdas correspondientes.
     * - Calcula e inserta los totales de ingresos y egresos.
     * - Inserta el saldo final.
     * 
     * Formato y estilo de la hoja de cálculo:
     * - Establece el ancho de las columnas.
     * - Aplica formatos numéricos a las celdas de ingresos y egresos.
     * - Establece la fuente y el tamaño de la fuente para ciertas celdas.
     * - Aplica colores de fondo y bordes a las celdas para mejorar la legibilidad.
     * 
     * Movimientos hechos con bancos:
     * - Si el usuario tiene permisos para visualizar movimientos con bancos, inserta los datos correspondientes en las celdas.
     * 
     * Parámetros:
     * - $datos: Array con los datos de ingresos, egresos y saldos.
     * - $params: Array con parámetros adicionales (por ejemplo, identificador de cajero o agencia).
     * - $nameColumn: Nombre de la columna para los egresos de créditos (Aplica para desembolsos cuando los desembolsos se hacen solo en la central).
     * - $movBancos: Objeto que verifica si se tiene permisos para ver movimientos con bancos.
     * 
     * Funciones auxiliares:
     * - getContador(): Función que devuelve el número de fila actual para la inserción de datos.
     * - moneda(): Función que formatea un valor numérico como moneda.
     */
    $datos[0]['pagos_creditos'] = ($movBancos->isLow()) ? $datos[0]['pagos_creditos'] : ($datos[0]['pagos_creditos_bancos'] + $datos[0]['pagos_creditos']);
    $datos[0][$nameColumn] = ($movBancos->isLow()) ? $datos[0][$nameColumn] : ($datos[0]['desembolsos_creditos_bancos'] + $datos[0][$nameColumn]);
    $datos[0]['egresosbancos'] = ($movBancos->isLow()) ? $datos[0]['egresosbancos'] : ($datos[0]['egresosbancos'] + $datos[0]['pagos_creditos_bancos']);


    // Cálculos de ingresos, egresos y saldos
    $datos[0]['sumaingresos'] = ($datos[0]['ingresos_ahorros'] + $datos[0]['ingresos_aportaciones'] + $datos[0]['pagos_creditos'] + $datos[0]['otros_ingresos'] + $datos[0]['ingresosbancos'] + $datos[0]['ingresos_caja']);
    $datos[0]['sumaegresos'] = ($datos[0]['egresos_ahorros'] + $datos[0]['egresos_aportaciones'] + $datos[0][$nameColumn] + $datos[0]['otros_egresos'] + $datos[0]['egresosbancos'] + $datos[0]['egresos_caja']);
    $datos[0]['saldomov'] = ($datos[0]['sumaingresos'] - $datos[0]['sumaegresos']);
    $datos[0]['saldofinal'] = $datos[0]['saldo_inicial'] + $datos[0]['saldomov'];

    $fuente = "Tahoma";

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

    // Obtener la última fila ocupada y sumar 1 para insertar los nuevos datos (si quieres que siempre sea la fila 2, elimina el siguiente comentario)
    // $ultimaFila = $activa->getHighestRow() + 1;

    // Verificar que $params[1] esté definido antes de usarlo
    $descripcion = (isset($params[1]) && $params[1] != 0) ? 'CAJERO:' : 'AGENCIA:';
    $informacion = (isset($params[1]) && $params[1] != 0) ? decode_utf8($datos[0]['nombres'] . ' ' . $datos[0]['apellidos']) : $datos[0]['nom_agencia'];

    // Formatear las fechas y horas
    $fecha_apertura = (($datos[0]['fecha_apertura'] == '' || $datos[0]['fecha_apertura'] == null) ? ' ' : (date('d-m-Y', strtotime($datos[0]['fecha_apertura']))));
    $hora_apertura = (($datos[0]['hora_apertura'] == '' || $datos[0]['hora_apertura'] == null) ? ' ' : decode_utf8($datos[0]['hora_apertura']));
    $fecha_cierre = (($datos[0]['fecha_cierre'] == '' || $datos[0]['fecha_cierre'] == null) ? ' ' : (date('d-m-Y', strtotime($datos[0]['fecha_cierre']))));
    $hora_cierre = (($datos[0]['hora_cierre'] == '' || $datos[0]['hora_cierre'] == null) ? ' ' : decode_utf8($datos[0]['hora_cierre']));
    $arqueo_numero = ($params[1] != 0) ? 'A-0' . (($datos[0]['id'] == '' || $datos[0]['id'] == null) ? ' ' : ($datos[0]['id'])) . '0' : '';

    $saldoInicial = ($datos[0]['saldo_inicial'] == '' || $datos[0]['saldo_inicial'] == null) ? ' ' : 'Q ' . number_format((float)$datos[0]['saldo_inicial'], 2, '.', '');
    $saldo_saldofinal = (($datos[0]['saldofinal'] == '' || $datos[0]['saldofinal'] == null) ? ' ' : 'Q ' . number_format((float)$datos[0]['saldofinal'], 2, '.', ''));
    $movimientosdelsistema = ('Q ' . number_format($datos[0]['saldomov'], 2, '.', ''));

    $ahorros = ('Q ' . number_format((float)$datos[0]['ingresos_ahorros'], 2, '.', ''));

    $egresos_ahorro = ('Q ' . number_format((float)$datos[0]['egresos_ahorros'], 2, '.', ''));
    $aportaciones = ('Q ' . number_format((float)$datos[0]['ingresos_aportaciones'], 2, '.', ''));
    $egresos_aportaciones = ('Q ' . number_format((float)$datos[0]['egresos_aportaciones'], 2, '.', ''));
    $cheques = ('Q ' . number_format((float)$datos[0]['ingresosbancos'], 2, '.', ''));
    $egresos_cheques = ('Q ' . number_format((float)$datos[0]['egresosbancos'], 2, '.', ''));
    $creditos = ('Q ' . number_format((float)$datos[0]['pagos_creditos'], 2, '.', ''));
    $egresos_creditos = ('Q ' . number_format((float)$datos[0][$nameColumn], 2, '.', ''));
    $otros = ('Q ' . number_format((float)$datos[0]['otros_ingresos'], 2, '.', ''));
    $egresos_otros = ('Q ' . number_format((float)$datos[0]['otros_egresos'], 2, '.', ''));
    $ingresos_caja = moneda($datos[0]['ingresos_caja']);
    $egresos_caja = moneda($datos[0]['egresos_caja']);


    $total_ingresos = ($datos[0]['saldo_inicial'] ?? 0) + ($datos[0]['sumaingresos'] ?? 0);
    $total_ingresos =  'Q ' . number_format($total_ingresos, 2, '.', '');
    $total_egresos = ('Q ' . number_format($datos[0]['sumaegresos'], 2, '.', ''));
    /**
     * MOVIMIENTOS HECHOS CON BANCOS
     */
    $pagos_creditos_bancos = moneda($datos[0]['pagos_creditos_bancos'] ?? 0);
    $desembolsos_creditos_bancos = moneda($datos[0][$nameColumn . "_bancos"] ?? 0);

    // Insertar los datos en la fila 2
    $activa->setCellValue('B2', $descripcion);
    $activa->setCellValue('C2', $informacion);
    $activa->setCellValue('D2', 'FECHA DE APERTURA:');
    $activa->setCellValue('E2', $fecha_apertura . ' ' . $hora_apertura);
    $activa->setCellValue('F2', 'FECHA DE CIERRE:');
    $activa->setCellValue('G2', $fecha_cierre . ' ' . $hora_cierre);

    $activa->setCellValue('A4', '#');
    $activa->setCellValue('B4', 'Descripcion');
    $activa->setCellValue('C4', 'Ingresos');
    $activa->setCellValue('D4', 'Egresos');


    $activa->setCellValue('F5', 'SALDO INICIAL DEL SISTEMA');
    $activa->setCellValue('G5', $saldoInicial);

    $activa->setCellValue('F6', 'Movimientos en el sistema');
    $activa->setCellValue('G6', $movimientosdelsistema);

    $activa->setCellValue('A5', '0');
    $activa->setCellValue('A6', '1');
    $activa->setCellValue('A7', '2');
    $activa->setCellValue('A8', '3');
    $activa->setCellValue('A9', '4');
    $activa->setCellValue('A10', '5');
    $activa->setCellValue('A11', '1');
    $activa->setCellValue('A12', '2');
    $activa->setCellValue('A13', '3');
    $activa->setCellValue('A14', '4');
    $activa->setCellValue('A15', '5');

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
    $activa->setCellValue("C" . getContador(5), $saldoInicial);
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
    $activa->setCellValue("D" . getContador(),  $egresos_otros);
    $activa->setCellValue("D" . getContador(),  $egresos_caja);


    $rowTotales = getContador();

    $activa->setCellValue("C" . $rowTotales, $total_ingresos);
    $activa->setCellValue("D" . $rowTotales,  $total_egresos);

    $rowSaldofinal = getContador() + 1;

    $activa->setCellValue("C" . $rowSaldofinal, $saldo_saldofinal);

    /**
     * MOVIMIENTOS HECHOS CON BANCOS
     */
    if (!$movBancos->isLow()) {
        $filaNow1 = getContador(9);
        $activa->setCellValue("F" . $filaNow1, 'Pagos creditos con bancos');
        $activa->setCellValue("G" . $filaNow1, $pagos_creditos_bancos);
        $filaNow2 = getContador();
        $activa->setCellValue("F" . $filaNow2, 'Desembolsos creditos con bancos');
        $activa->setCellValue("G" . $filaNow2, $desembolsos_creditos_bancos);

        $activa->getStyle("F" . $filaNow1 . ":G" . $filaNow1)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
        $activa->getStyle("F" . $filaNow2 . ":G" . $filaNow2)->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN);
    }

    if ($params[1] != 0) {
        $activa->setCellValue('H2', 'ARQUEO No.:');
        $activa->setCellValue('I2', $arqueo_numero);
    }

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
    $activa->getStyle('C' . $rowTotales)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE699');
    $activa->getStyle('C' . $rowTotales)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('C' . $rowTotales)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE699');
    $activa->getStyle('C' . $rowTotales)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('B' . $rowTotales)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE699');
    $activa->getStyle('B' . $rowTotales)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('D' . $rowTotales)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFE699');
    $activa->getStyle('D' . $rowTotales)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('C' . $rowSaldofinal)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorHex);
    $activa->getStyle('C' . $rowSaldofinal)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('B' . $rowSaldofinal)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorHex);
    $activa->getStyle('B' . $rowSaldofinal)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
    $activa->getStyle('D' . $rowSaldofinal)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($colorHex);
    $activa->getStyle('D' . $rowSaldofinal)->getBorders()->getTop()->setBorderStyle(Border::BORDER_THIN);
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
