<?php

/**
 * Este script maneja la generación de un recibo de pago individual para GuatePresta - ADFISA.
 * 
 * - Verifica si la solicitud es de tipo GET y redirige a una página 404 si es así.
 * 
 * Variables:
 * - $datos: Datos recibidos por POST.
 * - $inputs: Información de los datos recibidos.
 * - $archivo: Información del archivo recibido.
 * - $usuario: Usuario que realiza la solicitud.
 * - $codigocredito: Código del crédito.
 * - $numerocuota: Número de cuota.
 * - $cnuming: Número de ingreso(RECIBO, BOLETA).
 * - $consulta: Consulta SQL para obtener información del crédito y del cliente.
 * - $showmensaje: Bandera para mostrar mensajes de error.
 * - $info: Información de la institución y agencia.
 * - $status: Estado de la operación.
 * - $mensaje: Mensaje de error o éxito.
 * - $codigoError: Código de error generado.
 * - $opResult: Resultado de la operación en formato JSON.
 * 
 * Funciones utilizadas:
 * - $database->openConnection(): Abre una conexión a la base de datos de tipo PDO (CLASE DISPONIBLE EN EL ARCHIVO database.php).
 * - $database->getAllResults($consulta, $params): Ejecuta una consulta SQL y devuelve los resultados.
 * - logerrores($mensaje, $archivo, $linea, $archivoError, $lineaError): Registra errores en un archivo de log. (DISPONIBLE EN EL ARCHIVO func_gen.php).
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

include '../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include '../../../../src/funcphp/func_gen.php';

require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use Luecano\NumeroALetras\NumeroALetras;

//se recibe los datos
$datos = $_POST["datosval"];

//Informacion de datosval 
$inputs = $datos[0];
$archivo = $datos[3];

//Informacion de archivo 
$usuario = $_SESSION['id'];
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

$consulta = "SELECT ck.DFECPRO AS fechadoc, CAST(ck.DFECSIS as Date) AS fechaaplica, cl.short_name AS nombre, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
  ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
  (IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
  ((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<=?)) AS saldo, cl.no_identifica AS dpi,
    FormPago,DFECBANCO,
    IFNULL((SELECT nombre FROM tb_bancos where id=CBANCO),'-') CBANCO,
    IFNULL((SELECT numcuenta FROM ctb_bancos where id=CCODBANCO),'-') CCODBANCO ,boletabanco 
  FROM cremcre_meta cm
  INNER JOIN CREDKAR ck ON cm.CCODCTA=ck.CCODCTA
  INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
  INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
  INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo=ctf.id
  WHERE cm.CCODCTA=? AND ck.CNUMING=? AND ck.CESTADO!='X' AND ck.CTIPPAG='P'";
//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($consulta, [$numerocuota, $codigocredito, $cnuming]);
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

printpdf($usuario, $result, $info);

/**
 * Genera un comprobante en formato PDF y lo devuelve como una respuesta JSON.
 *
 * @param string $usuario El nombre del usuario que genera el comprobante.
 * @param array $registro Los datos del registro que se incluirán en el comprobante, contiene toda la informacion del recibo de pago de credito.
 * @param array $info Información adicional que puede servir, (informacion de la institucion en cuestion).
 *
 * @return void
 */
function printpdf($usuario, $registro, $info)
{

    $hoy = date("d-m-Y H:i:s");

    $pdf = new FPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    recibo($pdf, $registro, $hoy, $usuario, $info);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Comprobanteindividual",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function recibo($pdf, $registro, $hoy, $usuario, $info)
{

    /**
     * CellFit($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $scale = false, $force = true)
     *
     * @param float $w Ancho de la celda.
     * @param float $h Altura de la celda. Si es 0, se ajusta automáticamente.
     * @param string $txt Texto a mostrar en la celda.
     * @param int $border Indica si se debe dibujar un borde alrededor de la celda. 0 = sin borde, 1 = borde completo.
     * @param int $ln Indica la posición siguiente de la celda. 0 = a la derecha, 1 = al comienzo de la siguiente línea.
     * @param string $align Alineación del texto. Valores posibles: 'L' (izquierda), 'C' (centro), 'R' (derecha).
     * @param bool $fill Indica si se debe rellenar el fondo de la celda. false = sin relleno, true = con relleno.
     * @param string $link URL o identificador de enlace.
     * @param bool $scale Indica si se debe escalar el texto para que se ajuste al ancho de la celda. false = no escalar, true = escalar.
     * @param bool $force Indica si se debe forzar el ajuste del texto incluso si es más pequeño que el ancho de la celda. false = no forzar, true = forzar.
     */

    /**
     * Genera un recibo de pago en formato PDF utilizando los datos proporcionados.
     * 
     * Variables:
     * - $fuente: Fuente utilizada en el PDF.
     * - $marginLeft: Margen izquierdo del PDF.
     * - $marginTop: Margen superior del PDF.
     * - $hlinea: Altura de la línea en el PDF.
     * - $wlinea: Ancho de la línea en el PDF.
     * - $fontSize: Tamaño de la fuente en el PDF.
     * 
     * Funciones utilizadas:
     * - $pdf->ln($marginTop): Añade un salto de línea con el margen superior especificado.
     * - $pdf->SetLeftMargin($marginLeft): Establece el margen izquierdo del PDF.
     * - $pdf->SetFont($fuente, '', $fontSize): Establece la fuente y el tamaño de la fuente.
     * - $pdf->CellFit(...): Crea una celda ajustada con el contenido especificado. [leer documentacion de arriba]
     * 
     * Variables derivadas:
     * - $fechaArray: Array que contiene la fecha desglosada en día, mes y año.
     * - $dia: Día de la fecha del documento.
     * - $mes: Mes de la fecha del documento.
     * - $anio: Año de la fecha del documento.
     * - $formaPago: Forma de pago (BOLETA DE BANCO o EFECTIVO).
     * - $nameBanco: Nombre del banco o EFECTIVO.
     * - $numCuenta: Número de cuenta o EFECTIVO.
     * 
     * Datos del registro:
     * - $registro[0]['fechadoc']: Fecha del documento.
     * - $registro[0]['FormPago']: Forma de pago.
     * - $registro[0]['CBANCO']: id del banco de la tabla tb_bancos.
     * - $registro[0]['CCODBANCO']: id de la cuenta de bancos, de la tabla ctb_bancos.
     * - $registro[0]['total']: Total del pago.
     * - $registro[0]['nombre']: Nombre del cliente.
     * - $registro[0]['capital']: Capital pagado.
     * - $registro[0]['interes']: Interés pagado.
     * - $registro[0]['mora']: Mora pagada.
     * - $registro[0]['otros']: Otros cargos del pago.
     * 
     * Datos de sesión:
     * - $_SESSION["nombre"]: Nombre del usuario de la sesión.
     * - $_SESSION["apellido"]: Apellido del usuario de la sesión.
     */
    $fuente = "Courier";
    $marginLeft = 50;
    $marginTop = 8;

    $hlinea = 8;
    $wlinea = 30;

    $fontSize = 12;

    $pdf->ln($marginTop);
    $pdf->SetLeftMargin($marginLeft);

    $pdf->SetFont($fuente, '', $fontSize);

    $fechaArray = explode('-', $registro[0]['fechadoc']);
    $dia = $fechaArray[2];
    $mes = $fechaArray[1];
    $anio = $fechaArray[0];

    $formaPago = ($registro[0]['FormPago'] == '2') ? 'BOLETA DE BANCO' : 'EFECTIVO';
    $nameBanco = ($registro[0]['FormPago'] == '2') ? $registro[0]['CBANCO'] : 'EFECTIVO';
    $numCuenta = ($registro[0]['FormPago'] == '2') ? $registro[0]['CCODBANCO'] : 'EFECTIVO';
    $boletaBanco = ($registro[0]['FormPago'] == '2') ? $registro[0]['boletabanco'] : 'EFECTIVO';

    $pdf->CellFit(0, $hlinea, number_format($registro[0]['total'], 2), 0, 1, 'R', 0, '', 1, 0);

    $pdf->CellFit($wlinea * 3, $hlinea, " ", 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($wlinea - 10, $hlinea, $dia, 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($wlinea - 10, $hlinea, $mes, 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($wlinea - 10, $hlinea, $anio, 0, 1, 'R', 0, '', 1, 0);

    $pdf->ln($hlinea - 2);

    $pdf->CellFit(0, $hlinea, $registro[0]['nombre'], 0, 1, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, $boletaBanco, 0, 1, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, $nameBanco, 0, 1, 'L', 0, '', 1, 0);

    $pdf->ln($hlinea);

    $pdf->CellFit($wlinea * 4, $hlinea, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, $registro[0]["capital"], 0, 1, 'L', 0, '', 1, 0);

    $pdf->CellFit($wlinea * 4, $hlinea, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, $registro[0]["interes"], 0, 1, 'L', 0, '', 1, 0);

    $pdf->CellFit($wlinea * 4, $hlinea, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, $registro[0]["mora"], 0, 1, 'L', 0, '', 1, 0);

    $pdf->CellFit($wlinea * 4, $hlinea, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, $registro[0]["otros"], 0, 1, 'L', 0, '', 1, 0);

    $pdf->CellFit($wlinea * 4, $hlinea, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, $registro[0]["total"], 0, 1, 'L', 0, '', 1, 0);

    $pdf->ln($hlinea * 2);

    $pdf->CellFit($wlinea * 6, $hlinea, decode_utf8($_SESSION["nombre"] . " " . $_SESSION["apellido"]), 0, 0, 'L', 0, '', 1, 0);
}
