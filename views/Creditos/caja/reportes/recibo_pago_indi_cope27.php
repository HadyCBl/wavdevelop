<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
session_start();

use Luecano\NumeroALetras\NumeroALetras;

date_default_timezone_set('America/Guatemala');
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
    (ROUND((IFNULL(cm.NCapDes,0)),2)-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<='" . $numerocuota . "')) AS saldo,
    IFNULL(ROUND((SELECT ROUND(IFNULL(SUM(nintere),0),2) FROM Cre_ppg WHERE ccodcta = cm.CCODCTA)-
    (SELECT ROUND(IFNULL(SUM(c.INTERES),0),2) FROM CREDKAR c WHERE c.CTIPPAG = 'P' AND  c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X' AND c.CNROCUO<='" . $numerocuota . "'),2),0)  AS saldointeres 
    FROM cremcre_meta cm
    INNER JOIN CREDKAR ck ON cm.CCODCTA=ck.CCODCTA
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
    INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo=ctf.id
    WHERE cm.CCODCTA='" . $codigocredito . "' AND ck.CNUMING='" . $cnuming . "' AND ck.CESTADO!='X' AND ck.CTIPPAG='P'";
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
    $saldocapital = round($registro[0][12], 2);
    $saldointeres = round($registro[0][13], 2);
    $saldo_anterior = $saldocapital + $registro[0][7];
    $saldointeres = ($saldointeres > 0) ? $saldointeres : 0;

    $fuente = "Courier";
    $tamanio_linea = 3.5;
    $pdf->SetFont($fuente, '', 10);
    $pdf->Ln(12);
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'Doc No.: ' . $registro[0][4] . ' | FECHA DOCTO.: ' . date("d-m-Y", strtotime($registro[0][0])), 0, 'L');
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'FECHA APLICA: ' . date("d-m-Y", strtotime($registro[0][1])), 0, 'L');
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'NOMBRE: ' . mb_convert_encoding(mb_strtoupper($registro[0][2], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 'L');
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'CUENTA: ' . $registro[0][3], 0, 'L');

    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'Pago Recibido: Q ' . $registro[0][11], 0, 'L');

    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'SALDO ANTERIOR: Q ' . $saldo_anterior, 0, 'L');

    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'CAPITAL: Q ' . $registro[0][7], 0, 'L');
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'INTERESES: Q ' . $registro[0][8], 0, 'L');
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'MORA: Q ' . $registro[0][9], 0, 'L');
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'OTROS: Q ' . $registro[0][10], 0, 'L');

    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'SALDO ACTUAL: Q ' . $saldocapital, 0, 'L');

    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, 'Operador: ' . mb_convert_encoding($usuario, 'ISO-8859-1', 'UTF-8'), 0, 'L');

    

    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, mb_convert_encoding($format_monto->toMoney($decimal[0], 2, '', '') . $res . "/100", 'ISO-8859-1', 'UTF-8'), 0, 'L');
    $pdf->SetX(65);
    $pdf->MultiCell(0, $tamanio_linea, mb_convert_encoding(mb_strtoupper($registro[0][5], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 'L');
}
