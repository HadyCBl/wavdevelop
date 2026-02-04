<?php
include '../../../../includes/Config/database.php';
include '../../../../src/funcphp/func_gen.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
require '../../../../fpdf/fpdf.php';
date_default_timezone_set('America/Guatemala');
session_start();

use Luecano\NumeroALetras\NumeroALetras;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

//se recibe los datos
$datos = $_POST["datosval"];

//Informacion de datosval 
$inputs = $datos[0];
$archivo = $datos[3];

//Informacion de archivo 
$usuario = $_SESSION['id'];
// $usuario = $_SESSION['id'];
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

try {
    $database->openConnection();
    $result = $database->getAllResults("SELECT ck.CODKAR id,ck.DFECPRO AS fechadoc, CAST(ck.DFECSIS as Date) AS fechaaplica, cl.short_name AS nombre,cl.control_interno, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
	ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,pd.cod_producto AS producto, 
	(IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
	((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<=?)) AS saldo,cl.no_identifica AS dpi, ck.CNROCUO AS numoperacion, cl.Direccion AS direc
    FROM cremcre_meta cm
    INNER JOIN CREDKAR ck ON cm.CCODCTA=ck.CCODCTA
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
    INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo=ctf.id
    WHERE cm.CCODCTA=? AND ck.CNUMING=? AND ck.CESTADO!='X' AND ck.CTIPPAG='P'", [$numerocuota, $codigocredito, $cnuming]);

    if (empty($result)) {
        throw new Exception("No se encontraron registros en result.");
    }
    $resultdetalle = $database->getAllResults("SELECT cd.*,(SELECT tg.nombre_gasto FROM cre_tipogastos tg INNER JOIN cre_productos_gastos cgg ON cgg.id_tipo_deGasto=tg.id WHERE cgg.id=cd.id_concepto ) concepto FROM credkar_detalle cd WHERE cd.tipo='otro' AND cd.id_credkar IN 
                                (SELECT CODKAR FROM CREDKAR WHERE CCODCTA=? AND CNUMING=? AND CESTADO!='X' AND CTIPPAG='P');", [$codigocredito, $cnuming]);



    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    $status = 1;
} catch (Exception $e) {
    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

printpdf($usuario, $result, $resultdetalle, $info);

function printpdf($usuario, $registro, $resultdetalle, $info)
{
    $hoy = date("d-m-Y H:i:s");
    $institucion = utf8_decode($info[0]["nomb_comple"]);

    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends FPDF
    {
        // atributos de la clase
        public $institucion;

        public function __construct($institucion)
        {
            parent::__construct();
            $this->institucion = $institucion;
        }
    }

    $pdf = new PDF($institucion);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    recibo($pdf, $registro, $hoy, $usuario, $info, $resultdetalle);

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
// $mover_x "es para mover eje x"
function recibo($pdf, $registro, $hoy, $usuario, $info, $resultdetalle, $mover_x = 10)
{
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea2 = 30;
    $dimconcepto = 48;
    $dimq = 4;
    $dimcant = 24;
    $nomb_usuario = $_SESSION['nombre'] . " " . $_SESSION['apellido'];
    $pdf->SetFont($fuente, '', 7);

    $pdf->SetX($pdf->GetX() + $mover_x);

    $pdf->CellFit(0, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->Ln(40);
    //NUMERO DE DOCUMENTO
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea, 'Numero de pago: ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->SetFont($fuente, 'B', 7);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, $registro[0]['numoperacion'], 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->SetFont($fuente, '', 7);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, 'Documento No.', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 7);
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, $registro[0]["numdoc"], 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, $hoy, 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->Ln(3);

    //FECHA DOCTO Y FUENTES
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->SetFont($fuente, '', 7);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea, 'FEC. DOC. ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea, 'FEC. APLICA: ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->SetFont($fuente, 'B', 7);
    $pdf->CellFit($ancho_linea2 + 4, $tamanio_linea, mb_strtoupper($registro[0]["fuente"], 'utf-8'), 0, 0, 'C', 0, ' ', 1, 0);
    $pdf->SetFont($fuente, 'B', 7);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'CONCEPTO', 0, 0, 'C', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'CANTIDAD', 0, 0, 'C', 0, ' ', 1, 0);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(3);


    //FECHA DE APLICACION LINEA
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 + 4, $tamanio_linea, 'FECHA APLICA:' . date("d-m-Y", strtotime($registro[0]["fechaaplica"])), 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 9, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'CAPITAL', ' ', 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit(5, $tamanio_linea, 'Q', ' ', 0, 'C', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($registro[0]["capital"], 2), ' ', 0, 'R', 0, ' ', 1, 0);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(3);

    //FECHA APLICA Y CAPITAL
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 + 4, $tamanio_linea, 'NOMBRE:', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 9, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'INTERESES', ' ', 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit(5, $tamanio_linea, 'Q', ' ', 0, 'C', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($registro[0]["interes"], 2), ' ', 0, 'R', 0, ' ', 1, 0);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(3);

    //NOMBRE Y MORA
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, mb_convert_encoding(strtoupper($registro[0]["nombre"]), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 8, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'MORA', ' ', 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit(5, $tamanio_linea, 'Q', ' ', 0, 'C', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($registro[0]["mora"], 2), ' ', 0, 'R', 0, ' ', 1, 0);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    // $pdf->Ln(3);

    //***** */
    $gastoscredkar = $registro[0]["otros"];
    $fila = 0;
    // $pdf->SetFont($fuente, '', 7);
    if (!empty($resultdetalle)) {
        while ($fila < count($resultdetalle)) {
            $mongas = $resultdetalle[$fila]["monto"];
            $nomgas = $resultdetalle[$fila]["concepto"];
            $pdf->Ln(3);
            $pdf->SetX($pdf->GetX() + $mover_x);
            $pdf->CellFit($ancho_linea2 + 4, $tamanio_linea, " ", 0, 0, 'L', 0, ' ', 1, 0);
            $pdf->CellFit($ancho_linea2 + 9, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, ' ', 1, 0);
            $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, $nomgas, ' ', 0, 'L', 0, ' ', 1, 0);
            $pdf->CellFit(5, $tamanio_linea, 'Q', ' ', 0, 'C', 0, ' ', 1, 0);
            $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($mongas, 2), ' ', 0, 'R', 0, ' ', 1, 0);
            $gastoscredkar = $gastoscredkar - $mongas;
            $fila++;
        }
    }

    //por si no hubiera detalles pero sí hay descuentos zzzzzz
    // if ($gastoscredkar > 0) {
    $pdf->Ln(3);
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 + 28, $tamanio_linea, 'DPI: ' . $registro[0]["dpi"], 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea, ' ', 0, 0, ' ', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'OTROS', ' ', 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit(5, $tamanio_linea, 'Q', ' ', 0, 'C', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($gastoscredkar, 2), ' ', 0, 'R', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 - 1, $tamanio_linea, 'SALDO', ' ', 0, 'C', 0, ' ', 1, 0);
    $pdf->Ln(3);

    //CANTIDAD EN LETRAS
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'CUENTA: ' . $registro[0]["ccodcta"], 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 8, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea, 'TOTAL', '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea, 'Q', '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($registro[0]["total"], 2), ' ', 0, 'R', 0, ' ', 1, 0);
    $pdf->CellFit(1, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea, 'Q', '', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 6, $tamanio_linea, number_format($registro[0]["saldo"], 2), ' ', 0, 'R', 0, ' ', 1, 0);
    $pdf->Ln(3);

    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 * 2.8, $tamanio_linea, 'DIRECCION: ', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->Ln(3);

    $pdf->SetX($pdf->GetX() + $mover_x);
    $direccion = isset($registro[0]["direc"]) && !empty($registro[0]["direc"]) ? mb_convert_encoding($registro[0]["direc"], 'ISO-8859-1', 'UTF-8') : decode_utf8('-');
    $pdf->CellFit($ancho_linea2 * 2.8, $tamanio_linea, $direccion, 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->Ln(4);

    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 * 2.8, $tamanio_linea, 'Cantidad en letras:', 0, 0, 'L', 0, ' ', 1, 0);
    $pdf->Ln(3);

    //TOTAL EN LETRAS
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0]["total"]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->MultiCell(0, $tamanio_linea + 1, $format_monto->toMoney($decimal[0], 2, '', '') . $res . "/100", 0, 'L');

    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->MultiCell(0, $tamanio_linea + 1, mb_convert_encoding($registro[0]["concepto"], 'ISO-8859-1', 'UTF-8'), 0, 'L');

    $pdf->Ln(21);

    //USUARIO
    // $pdf->CellFit(0, $tamanio_linea + 1,  utf8_decode(utf8_decode('USUARIO:' . $usuario)), 0, 0, 'C', 0, '', 1, 0);
    // FIRMAS
    $pdf->SetX($pdf->GetX() + $mover_x);
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'F. ' . mb_convert_encoding(strtoupper($registro[0]["nombre"]), 'ISO-8859-1', 'UTF-8'), 'T', 0, 'C', 0, ' ', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'F. ' . mb_convert_encoding($nomb_usuario, 'ISO-8859-1', 'UTF-8'), 'T', 0, 'C', 0, ' ', 1, 0);
}
