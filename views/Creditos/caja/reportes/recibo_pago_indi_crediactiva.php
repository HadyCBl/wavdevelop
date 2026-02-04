<?php

/**
 * Este script maneja la generación de un recibo de pago individual para Crediactiva.
 * 
 * - Verifica si la solicitud es de tipo GET y redirige a una página 404 si es así.
 * - Realiza una consulta a la base de datos para obtener información del crédito y del cliente. ($registro)
 * - Realiza otra consulta para obtener información de la institución asignada a la agencia. ($info)
 * 
 * Variables:
 * - $idusuario: ID del usuario de la sesión.
 * - $datos: Datos enviados por POST.
 * - $inputs, $archivo: Datos específicos del POST.
 * - $usuario, $codigocredito, $numerocuota, $cnuming: Información del archivo.
 * - $consulta: Consulta SQL para obtener información del crédito.
 * - $showmensaje: Bandera para mostrar mensajes de error.
 * - $status: Estado de la operación.
 * - $mensaje: Mensaje de error.
 * - $codigoError: Código de error para el registro de errores.
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

use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];

$inputs = $datos[0];
$archivo = $datos[3];

$usuario = $_SESSION['id'];
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

$consulta = "SELECT ck.DFECPRO AS fechadoc, ck.DFECSIS  AS fechaaplica, cl.short_name AS nombre, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
  ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
  (IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,ck.CNROCUO AS noCuota,
  cm.noPeriodo AS noCuotas,
  ((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<=?)) AS saldo, cl.no_identifica AS dpi
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
    $registro = $database->getAllResults($consulta, [$numerocuota, $codigocredito, $cnuming]);
    if (empty($registro)) {
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

printpdf($registro, $info, $usuario, $codigocredito, $numerocuota, $cnuming, $hoy);

function printpdf($registro, $info, $usuario, $codigocredito, $numerocuota, $cnuming, $hoy)
{

    /**
     * Genera un comprobante en formato PDF y lo devuelve como una respuesta JSON.
     *
     * Este script crea un documento PDF utilizando la librería FPDF, genera un recibo
     * y lo codifica en base64 para ser enviado como parte de una respuesta JSON. Se imprimen dos copias del recibo.
     *
     * @param FPDF $pdf Instancia de la clase FPDF utilizada para generar el PDF.
     * @param array $registro Datos del registro que se incluirán en el recibo.
     * @param string $hoy Fecha actual que se incluirá en el recibo.
     * @param string $usuario Nombre del usuario que genera el recibo.
     * @param array $info Información de la institucion que se incluirá en el recibo.
     *
     * @return void Este script no retorna un valor directamente, sino que imprime una respuesta JSON.
     *
     * La respuesta JSON contiene:
     * - 'status': Estado de la operación (1 para éxito).
     * - 'mensaje': Mensaje indicando que el comprobante se generó correctamente.
     * - 'namefile': Nombre del archivo generado.
     * - 'tipo': Tipo de archivo generado (pdf).
     * - 'data': Datos del archivo PDF codificados en base64.
     */
    $pdf = new FPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    recibo($pdf, $registro, $hoy, $usuario, $info);
    $pdf->Ln(20);

    $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());

    $pdf->Ln(10);
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
     * Genera un recibo en formato PDF.
     *
     * Este script configura el documento PDF con varios detalles como el logo,
     * información de la empresa, número de documento, fecha y detalles del pago
     * incluyendo capital, intereses y otros cargos. También incluye el monto total
     * en palabras y el concepto del pago.
     *
     * Variables:
     * - $rutalogoins: Ruta de la imagen del logo de la institucion.
     * - $fuente: Fuente utilizada en el PDF.
     * - $tamanio_linea: Altura de la línea.
     * - $ancho_linea2: Ancho de las líneas.
     * - $info: Array que contiene la información de la empresa.
     * - $registro: Array que contiene los detalles del pago.
     * - $hoy: Fecha actual.
     * - $usuario: Nombre del usuario que genera el recibo.
     *
     * Funciones utilizadas:
     * - decode_utf8(): Decodifica una cadena codificada en UTF-8 (DISPONIBLE EN EL ARCHIVO func_gen.php).
     * - setdatefrench(): Formatea una fecha en formato francés (DISPONIBLE EN EL ARCHIVO func_gen.php).
     * - NumeroALetras::toMoney(): Convierte un número a su representación textual en español.
     *
     * Métodos de PDF:
     * - SetFont(): Establece la fuente para el PDF.
     * - Ln(): Mueve el cursor a la siguiente línea.
     * - GetX(), GetY(): Obtiene la posición actual del cursor.
     * - Image(): Inserta una imagen en el PDF.
     * - CellFit(): Crea una celda con texto que se ajusta dentro de la celda.
     * - MultiCell(): Crea una celda que puede contener múltiples líneas de texto.
     * - firmas(): Agrega firmas al PDF(recibe la cantidad de firmas, y el titulo que tendran).
     */
    $rutalogoins = "../../../.." . $info[0]["log_img"];
    $rutalogoAdg = "../../../../includes/img/asoADG.png";

    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 9);

    $pdf->Ln(4);
    $x = $pdf->GetX();
    $y = $pdf->GetY();

    $pdf->Image($rutalogoins, $x, $y, 33);
    $pdf->Image($rutalogoAdg, 170, $y, 33);

    $pdf->CellFit(0, 3, decode_utf8("ASOCIACIÓN DE DESARROLLO GUATEMALTECO"), 0, 1, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, 3, decode_utf8("CREDIACTIVA"), 0, 1, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, 3, 'Email: ' . "adgtecpan@gmail.com", 0, 1, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, 3, 'Tel: ' . '78403800' . $info[0]["tel_2"], 0, 1, 'C', 0, '', 1, 0);
    $pdf->CellFit(0, 3, 'NIT: ' . '10097110-5', 'B', 1, 'C', 0, '', 1, 0);


    //NUMERO DE DOCUMENTO
    $pdf->Ln(7);
    $pdf->CellFit($ancho_linea2 + 70, $tamanio_linea + 1, 'Cuota ' . $registro[0]['noCuota'] . '/' . $registro[0]['noCuotas'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'Documento No.', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, $registro[0]['numdoc'], 0, 0, 'L', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, setdatefrench($hoy), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Ln(5);

    //FECHA DOCTO Y FUENTES
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1,  ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 10);
    //FONDOS
    $pdf->CellFit($ancho_linea2 + 1, $tamanio_linea + 1, mb_strtoupper($registro[0]['fuente'], 'utf-8'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    //DESCRP
    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'CONCEPTO', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'CANTIDAD', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'FECHA: ', 0, 0, 'L', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 + 23, $tamanio_linea + 1, setdatefrench($registro[0]['fechadoc']), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CAPITAL', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0]['capital'], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //FECHA APLICA Y CAPITAL
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'NOMBRE:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'INTERESES', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0]['interes'], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //NOMBRE Y MORA
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, decode_utf8(mb_strtoupper($registro[0]['nombre'], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'MORA', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0]['mora'], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //PRESTAMO Y OTROS
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, 'CUENTA:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 28, $tamanio_linea + 1, $registro[0]['ccodcta'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'OTROS', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0]['otros'], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 1, $tamanio_linea + 2, 'SALDO', 'L-R-T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    //CANTIDAD EN LETRAS
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'Cantidad en letras:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'TOTAL', 'L-R-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0]['total'], 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 6, $tamanio_linea + 2, $registro[0]['saldo'], 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    //TOTAL EN LETRAS
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0]['total']);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->MultiCell(0, $tamanio_linea + 1, decode_utf8($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 'L');
    $pdf->Ln(5);
    $pdf->MultiCell(0, $tamanio_linea + 1, decode_utf8(mb_strtoupper($registro[0]['concepto'], 'utf-8')), 0, 'L');

    $pdf->SetFont($fuente, '', 7);
    $pdf->CellFit(0, $tamanio_linea + 1, $registro[0]['fechaaplica'], 0, 0, 'L', 0, '', 1, 0);

    $pdf->firmas(2, [$usuario, $registro[0]['nombre']]);

    $pdf->Ln(10);
}
