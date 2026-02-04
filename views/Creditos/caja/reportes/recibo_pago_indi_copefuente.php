<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
session_start();

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

    $fuente = "Arial";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 8);

    $pdf->CellFit(0, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(20);
    //NUMERO DE DOCUMENTO
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'NOMBRE DEL ASOCIADO:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 8);
    $pdf->CellFit($ancho_linea2 + 48, $tamanio_linea + 1, Utf8::decode(mb_strtoupper($registro[0][2], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);

    $pdf->SetFont($fuente, '', 8);
    //nuevo estilo para Copefuente
    //CUENTA
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 13, $tamanio_linea + 1, 'Cuenta', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 28, $tamanio_linea + 1, $registro[0][3], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea + 1, 'Fecha y Hora: ' . $hoy, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 + 160, $tamanio_linea + 1, 'DESCRIPCION', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    //BODY
    $pdf->SetFont($fuente, '', 8);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'INSCRIPCION', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, '-', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'CAPITAL', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][7], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'APORTACION', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, '-', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'INTERES', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][8], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'RETIRO DE APORTACION', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, '-', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'MORA', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][9], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'AHORRO CORRIENTE', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, '-', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'OTROS', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][10], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'RETIRO DE AHORRO CORRIENTE', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, '-', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'TOTAL PAGADO', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1,  $registro[0][11], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, 'INGRESOS VARIOS', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, '-', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'SALDO ACTUAL', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, 'Q', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][12], 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->CellFit($ancho_linea2 - 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->MultiCell(0, $tamanio_linea + 1, Utf8::decode(mb_strtoupper($registro[0][5], 'utf-8')), 0, 'L');

    $pdf->Ln(13);
    // $pdf->CellFit(0, $tamanio_linea + 1,  Utf8::decode(Utf8::decode('USUARIO:' . $usuario)), 0, 0, 'L', 0, '', 1, 0);
}
