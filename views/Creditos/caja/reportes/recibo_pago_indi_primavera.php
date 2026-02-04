<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
session_start();
date_default_timezone_set('America/Guatemala');

use Luecano\NumeroALetras\NumeroALetras;
use Micro\Generic\Utf8;

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
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general);

function printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general)
{
    $consulta = "SELECT ck.DFECPRO AS fechadoc, CAST(ck.DFECSIS as Date) AS fechaaplica, cl.short_name AS nombre, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
	ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
	(IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
	((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<='" . $numerocuota . "')) AS saldo
    FROM cremcre_meta cm
    INNER JOIN CREDKAR ck ON cm.CCODCTA=ck.CCODCTA
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
    INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo=ctf.id
    WHERE cm.CCODCTA='" . $codigocredito . "' AND ck.CNUMING='" . $cnuming . "'";
    $datos = mysqli_query($conexion, $consulta);
    $aux = mysqli_error($conexion);
    if ($aux) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'Fallo en la consulta de los datos',
            'dato' => $datos
        );
        echo json_encode($opResult);
        return;
    }
    if (!$datos) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'No se logro consultar los datos',
            'dato' => $datos
        );
        echo json_encode($opResult);
    }
    $registro[] = [];
    $j = 0;
    $flag = false;
    while ($fila = mysqli_fetch_array($datos)) {
        $registro[$j] = $fila;
        $flag = true;
        $j++;
    }
    //COMPROBACION: SI SE ENCONTRARON REGISTROS
    if ($flag == false) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'No se encontraron datos',
            'dato' => $datos
        );
        echo json_encode($opResult);
        return;
    }
    //FIN COMPROBACION
    $queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
    $info[] = [];
    $j = 0;
    while ($fil = mysqli_fetch_array($queryins)) {
        $info[$j] = $fil;
        $j++;
    }
    $hoy = date("d-m-Y H:i:s");
    $institucion = Utf8::decode($info[0]["nomb_comple"]);

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
    $pdf->SetCompression(true);
    $pdf->AddFont('Calibri', '', 'calibri.php');
    $pdf->AddFont('Calibri', 'B', 'calibrib.php');
    reciboprimavera($pdf, $registro, $hoy, $usuario, $info);

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

function reciboprimavera($pdf, $registro, $hoy, $usuario, $info)
{
    $fuente = "Calibri";
    $tamanio_linea = 4;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 + 130, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit(0, $tamanio_linea + 1, ' ', 1, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(22);


    $pdf->CellFit($ancho_linea2 + 60, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'Documento No...', 'L-B-T', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, $registro[0][4], 'T-R-B', 0, 'L', 0, '', 1, 0);
    //  $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, $hoy, 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->Ln(6);


    //FECHA DOCTO Y FUENTES
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, 'FECHA DOCTO.', 'L-R-T', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'FECHA APLICA:', 'L-R-T', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 10);
    //FONDOS
    $pdf->CellFit($ancho_linea2 + 1, $tamanio_linea + 1, mb_strtoupper($registro[0][6], 'utf-8'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    //DESCRP
    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'CONCEPTO', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'CANTIDAD', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(4);
    //FECHA DOCTO
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, date("d-m-Y", strtotime($registro[0][0])), 'L-B-R', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    //FECHA APLI
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, date("d-m-Y", strtotime($registro[0][1])), 'L-B-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 3, $tamanio_linea + 2, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CAPITAL', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][7], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //FECHA APLICA Y CAPITAL
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 2, 'ASOCIADO:', 'L-T-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'INTERESES', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][8], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, 'B', 11);
    //NOMBRE Y MORA
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, mb_convert_encoding(mb_strtoupper($registro[0][2], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 'L-B-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'MORA', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][9], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //PRESTAMO Y OTROS
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 2, 'PRESTAMO:', 'L-T-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'OTROS', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][10], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 1, $tamanio_linea + 2, 'SALDO', 'L-R-T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(6);

    //CANTIDAD EN LETRAS
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 2, $registro[0][3], 'L-B-R', 0, 'L', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'TOTAL', 'L-R-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][11], 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 1);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 - 6, $tamanio_linea + 2, $registro[0][12], 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(7);

    //TOTAL EN LETRAS
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->CellFit($ancho_linea2 + 160, $tamanio_linea + 1, 'CANTIDAD EN LETRAS:', 'L-T-R', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(4);
    $pdf->MultiCell(0, $tamanio_linea + 1, mb_convert_encoding($format_monto->toMoney($decimal[0], 2, '', '') , 'ISO-8859-1', 'UTF-8') . $res . "/100", 'L-B-R', 'L');
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Ln(1);
    $pdf->MultiCell(0, $tamanio_linea + 1, mb_convert_encoding(mb_strtoupper(trim($registro[0][5]), 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 'L');
    $pdf->Ln(13);
    //USUARIO
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea, 'FIRMA Y SELLO DEL OPERADOR', 'T', 0, 'L', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);

    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit(0, $tamanio_linea + 2, mb_convert_encoding(mb_strtoupper('USUARIO:' . $usuario, 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'C', 0, '', 1, 0);
}
