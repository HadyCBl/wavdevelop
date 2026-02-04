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
// require __DIR__ . '/../../../fpdf/WriteTag.php';
require __DIR__ . '/../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];

$consulta = "SELECT tp.recibo AS recibo, tp.cliente AS cli, tp.fecha AS fecha, IF(tpi.tipo = 1, 'INGRESO', 'EGRESO') AS tipomov, tpi.nombre_gasto AS detalle, 
                    tpm.monto AS monto, tp.agencia AS agencia, descripcion, 
                    IFNULL((SELECT idcod_cliente from tb_cliente where short_name = tp.cliente),' ') as codcli
                    FROM otr_pago_mov tpm 
                    INNER JOIN otr_tipo_ingreso tpi ON tpm.id_otr_tipo_ingreso=tpi.id 
                    INNER JOIN otr_pago tp ON tpm.id_otr_pago=tp.id WHERE tp.id=?";
//INIT TRY
$showmensaje = false;
try {
    $database->openConnection();
    $data = $database->getAllResults($consulta, [$archivo[0]]);
    if (empty($data)) {
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

switch ($tipo) {
    case 'xlsx';
        // printxls($data, $titlereport, $archivo[0]);
        break;
    case 'pdf':
        printpdf($data, $info, $idusuario);
        // printpdf2($data, $info, $idusuario);
        break;
}
function printpdf($registro, $info, $id_usu)
{
    $hoy = date("Y-m-d H:i:s");

    $pdf = new FPDF();
    $pdf->AliasNbPages();
    $pdf->AddPage();

    if($registro[0]["tipomov"]=="INGRESO"){
        reciboingreso($pdf, $registro, $hoy, $id_usu, $info);
    }
    else{
        reciboegreso($pdf, $registro, $hoy, $id_usu, $info);
    }

    

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "ReciboNo-" . $registro[0]['recibo'],
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
function reciboingreso($pdf, $registro, $hoy, $usuario, $info)
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
     * Genera un recibo de ingresos-egresos en formato PDF utilizando la librería FPDF.
     * 
     * Variables:
     * - $fuente: Define la fuente utilizada en el PDF.
     * - $marginLeft: Margen izquierdo del documento.
     * - $marginTop: Margen superior del documento.
     * - $hlinea: Altura de las líneas en el PDF.
     * - $wlinea: Ancho de las celdas en el PDF.
     * - $fontSize: Tamaño de la fuente.
     * 
     * Funciones y métodos utilizados:
     * - $pdf->ln($marginTop): Añade un salto de línea con el margen superior especificado.
     * - $pdf->SetLeftMargin($marginLeft): Establece el margen izquierdo del documento.
     * - $pdf->SetFont($fuente, '', 7): Establece la fuente y tamaño de la fuente.
     * - $pdf->CellFit(...): Crea una celda con ajuste de texto.
     * - $pdf->MultiCell(...): Crea una celda que puede contener múltiples líneas de texto.
     * 
     * Descripción del contenido:
     * - Se establece la fecha actual en la esquina superior derecha.
     * - Se añaden los datos del cliente, tipo de movimiento y número de recibo.
     * - Se añaden la fecha y dirección del cliente.
     * - Se listan los detalles de los egresos con sus respectivos montos.
     * - Se calcula y muestra el total de los egresos.
     * - Se convierte el monto total a letras y se muestra.
     * - Se añade una descripción adicional y los datos del usuario que genera el recibo.
     * 
     * Clases y métodos externos:
     * - decode_utf8(): Decodifica una cadena UTF-8 (DISPONIBLE EN EL ARCHIVO func_gen.php).
     * - setdatefrench(): Formatea una fecha al estilo francés, dia-mes-año. (DISPONIBLE EN EL ARCHIVO func_gen.php)
     * - NumeroALetras: Clase que convierte números a letras. (DISPONIBLE EN LA LIBRERÍA NumeroALetras)
     */
    $fuente = "Courier";

    $marginLeft = 40;
    $marginTop = -8;
    $hlinea = 6;
    $wlinea = 30;
    $fontSize = 10;
    
    $pdf->ln($marginTop);
    $pdf->SetLeftMargin($marginLeft);
    
    $pdf->SetFont($fuente, '',7);
    $pdf->CellFit(0, $hlinea, $hoy, 0, 0, 'R', 0, '', 1, 0);
    $pdf->ln($hlinea * 4 + 3);
    
    $pdf->SetFont($fuente, 'B', $fontSize);
    $total = round((array_sum(array_column($registro, 'monto'))), 2);
    $pdf->CellFit(0, $hlinea, number_format($total, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->ln($hlinea);

    $pdf->SetFont($fuente, '',8);
    $pdf->CellFit($wlinea, $hlinea, $registro[0]['recibo'], 0, 1, 'R', 0, '', 1, 0);
    $pdf->ln($hlinea);

    $pdf->SetFont($fuente, '', $fontSize);
    $pdf->CellFit(6,0, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($wlinea + 20, $hlinea, $registro[0]['codcli'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, decode_utf8($registro[0]['cli']), 0, 0, 'L', 0, '', 1, 0);
    $pdf->ln($hlinea + 3);

    $pdf->SetFont($fuente, '', $fontSize);
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $total);
    $res = (!isset($decimal[1])) ? 0 : (($decimal[1] == 0) ? 0 : $decimal[1]);
    $pdf->MultiCell(0, $hlinea, decode_utf8($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 'L');
    $pdf->ln($hlinea * 5);

    $pdf->MultiCell(0, $hlinea, decode_utf8($registro[0]['descripcion']), 0, 'L');
    $pdf->ln($hlinea);

    $pdf->MultiCell(0, $hlinea, 'X', 0, 'L');
    $pdf->ln($hlinea-3);

        //FECHA
        setlocale(LC_TIME, 'es.UTF-8'); // Configurar localización
        $dia = date('d');                 // Día con dos dígitos
        $mes = strftime('%B');            // Nombre completo del mes en localización configurada
        $anio = date('y');                // Últimos dos dígitos del año
        // Imprimir en las posiciones específicas del PDF
        $pdf->CellFit($wlinea*2+10, $hlinea, $dia, 0, 0, 'R', 0, '', 1, 0); // DIA
        $pdf->CellFit(18,0, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($wlinea+10, $hlinea, ucfirst($mes), 0, 0, 'R', 0, '', 1, 0); // MES con mayúscula inicial
        $pdf->CellFit(3,0, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($wlinea, $hlinea, $anio, 0, 0, 'R', 0, '', 1, 0); // AÑO
        $pdf->ln($hlinea * 3);
    // $pdf->ln($hlinea * 6);
    // $pdf->CellFit($wlinea * 2, $hlinea, "FECHA: " . setdatefrench($registro[0]['fecha']), 0, 0, 'L', 0, '', 1, 0);
    // $pdf->CellFit(0, $hlinea, "DIRECCION: " . $info[0]['muni_lug'], 0, 0, 'R', 0, '', 1, 0);
    // $pdf->ln($hlinea * 2);

    // $pdf->SetFont($fuente, 'B', $fontSize);
    // $pdf->CellFit($wlinea * 2, $hlinea, decode_utf8('DESCRIPCIÓN'), 0, 0, 'C', 0, '', 1, 0);
    // $pdf->ln($hlinea);

    // $pdf->SetFont($fuente, '', $fontSize);
    // foreach ($registro as $key => $value) {
    //     $pdf->CellFit($wlinea * 2, $hlinea, decode_utf8($value['detalle']), 0, 0, 'L', 0, '', 1, 0);
    //     $pdf->CellFit($wlinea * 2, $hlinea, number_format(round($value['monto'], 2), 2), 0, 0, 'R', 0, '', 1, 0);
    //     $pdf->ln($hlinea);
    // }
    
    $pdf->SetFont($fuente, '', 7);
    $pdf->CellFit(0, $hlinea, 'ID:' . $usuario . ' USUARIO:' . decode_utf8($_SESSION['nombre']) . ' ' . decode_utf8($_SESSION['apellido']), 0, 0, 'R', 0, '', 1, 0);
}

function reciboegreso($pdf, $registro, $hoy, $usuario, $info)
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
     * Genera un recibo de ingresos-egresos en formato PDF utilizando la librería FPDF.
     * 
     * Variables:
     * - $fuente: Define la fuente utilizada en el PDF.
     * - $marginLeft: Margen izquierdo del documento.
     * - $marginTop: Margen superior del documento.
     * - $hlinea: Altura de las líneas en el PDF.
     * - $wlinea: Ancho de las celdas en el PDF.
     * - $fontSize: Tamaño de la fuente.
     * 
     * Funciones y métodos utilizados:
     * - $pdf->ln($marginTop): Añade un salto de línea con el margen superior especificado.
     * - $pdf->SetLeftMargin($marginLeft): Establece el margen izquierdo del documento.
     * - $pdf->SetFont($fuente, '', 7): Establece la fuente y tamaño de la fuente.
     * - $pdf->CellFit(...): Crea una celda con ajuste de texto.
     * - $pdf->MultiCell(...): Crea una celda que puede contener múltiples líneas de texto.
     * 
     * Descripción del contenido:
     * - Se establece la fecha actual en la esquina superior derecha.
     * - Se añaden los datos del cliente, tipo de movimiento y número de recibo.
     * - Se añaden la fecha y dirección del cliente.
     * - Se listan los detalles de los egresos con sus respectivos montos.
     * - Se calcula y muestra el total de los egresos.
     * - Se convierte el monto total a letras y se muestra.
     * - Se añade una descripción adicional y los datos del usuario que genera el recibo.
     * 
     * Clases y métodos externos:
     * - decode_utf8(): Decodifica una cadena UTF-8 (DISPONIBLE EN EL ARCHIVO func_gen.php).
     * - setdatefrench(): Formatea una fecha al estilo francés, dia-mes-año. (DISPONIBLE EN EL ARCHIVO func_gen.php)
     * - NumeroALetras: Clase que convierte números a letras. (DISPONIBLE EN LA LIBRERÍA NumeroALetras)
     */
    $fuente = "Courier";

    $marginLeft = 40;
    $marginTop = -8;
    $hlinea = 6;
    $wlinea = 30;
    $fontSize = 10;
    
    $pdf->ln($marginTop);
    $pdf->SetLeftMargin($marginLeft);
    
    $pdf->SetFont($fuente, '',7);
    $pdf->CellFit(0, $hlinea, $hoy, 0, 0, 'R', 0, '', 1, 0);
    $pdf->ln($hlinea);
    $pdf->CellFit(0,0, 'ID:' . $usuario . ' USUARIO:' . decode_utf8($_SESSION['nombre']) . ' ' . decode_utf8($_SESSION['apellido']), 0, 0, 'R', 0, '', 1, 0);
    $pdf->ln($hlinea * 3+3);
    
    $pdf->SetFont($fuente, 'B', $fontSize);
    $total = round((array_sum(array_column($registro, 'monto'))), 2);
    $pdf->CellFit(0, $hlinea, number_format($total, 2), 0, 0, 'R', 0, '', 1, 0);
    $pdf->ln($hlinea);

    $pdf->SetFont($fuente, '',8);
    $pdf->CellFit($wlinea, $hlinea, $registro[0]['recibo'], 0, 1, 'R', 0, '', 1, 0);
    $pdf->ln($hlinea);

    $pdf->SetFont($fuente, '', $fontSize);
    $pdf->CellFit(6,0, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($wlinea + 20, $hlinea, $registro[0]['codcli'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(0, $hlinea, decode_utf8($registro[0]['cli']), 0, 0, 'L', 0, '', 1, 0);
    $pdf->ln($hlinea * 2);

    $pdf->SetFont($fuente, '', $fontSize);
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $total);
    $res = (!isset($decimal[1])) ? 0 : (($decimal[1] == 0) ? 0 : $decimal[1]);
    $pdf->MultiCell(0, $hlinea, decode_utf8($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 'L');
    $pdf->ln($hlinea * 3);

    $pdf->CellFit(68,0, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->MultiCell(0, $hlinea, decode_utf8($registro[0]['descripcion']), 0, 'L');
    $pdf->ln($hlinea);

    $pdf->SetY(100);

    $pdf->MultiCell(0, $hlinea, 'X', 0, 'L');
    $pdf->ln($hlinea-3);

        //FECHA
        setlocale(LC_TIME, 'es.UTF-8'); // Configurar localización
        $dia = date('d');                 // Día con dos dígitos
        $mes = strftime('%B');            // Nombre completo del mes en localización configurada
        $anio = date('y');                // Últimos dos dígitos del año
        // Imprimir en las posiciones específicas del PDF
        $pdf->CellFit($wlinea*2+10, $hlinea, $dia, 0, 0, 'R', 0, '', 1, 0); // DIA
        $pdf->CellFit(18,0, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($wlinea+10, $hlinea, ucfirst($mes), 0, 0, 'R', 0, '', 1, 0); // MES con mayúscula inicial
        $pdf->CellFit(3,0, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($wlinea, $hlinea, $anio, 0, 0, 'R', 0, '', 1, 0); // AÑO
        $pdf->ln($hlinea * 3);
    // $pdf->ln($hlinea * 6);
    // $pdf->CellFit($wlinea * 2, $hlinea, "FECHA: " . setdatefrench($registro[0]['fecha']), 0, 0, 'L', 0, '', 1, 0);
    // $pdf->CellFit(0, $hlinea, "DIRECCION: " . $info[0]['muni_lug'], 0, 0, 'R', 0, '', 1, 0);
    // $pdf->ln($hlinea * 2);

    // $pdf->SetFont($fuente, 'B', $fontSize);
    // $pdf->CellFit($wlinea * 2, $hlinea, decode_utf8('DESCRIPCIÓN'), 0, 0, 'C', 0, '', 1, 0);
    // $pdf->ln($hlinea);

    // $pdf->SetFont($fuente, '', $fontSize);
    // foreach ($registro as $key => $value) {
    //     $pdf->CellFit($wlinea * 2, $hlinea, decode_utf8($value['detalle']), 0, 0, 'L', 0, '', 1, 0);
    //     $pdf->CellFit($wlinea * 2, $hlinea, number_format(round($value['monto'], 2), 2), 0, 0, 'R', 0, '', 1, 0);
    //     $pdf->ln($hlinea);
    // }
}