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

    //se imrpime 2 veces 
    recibo($pdf, $registro, $hoy, $usuario, $info);
    $pdf->Ln(20);
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
    $oficina = utf8_decode($info[0]["nom_agencia"]);
    $direccionins = utf8_decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];

    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 9);

    // $pdf->CellFit(0, $tamanio_linea + 1, ' ', 1, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    //NUMERO DE DOCUMENTO
    $pdf->CellFit($ancho_linea2 + 70, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'Documento No.', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, $registro[0][4], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, $hoy, 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Ln(5);

    //FECHA DOCTO Y FUENTES


    $pdf->SetFont($fuente, 'B', 10);
    //FONDOS
    $pdf->CellFit($ancho_linea2 * 3 - 2, $tamanio_linea + 1, mb_strtoupper($registro[0][6], 'utf-8'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    //DESCRP
    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'CONCEPTO', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, 'CANTIDAD', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'FECHA APLICA:', 0, 0, 'L', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 + 23, $tamanio_linea + 1, date("d-m-Y", strtotime($registro[0][1])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CAPITAL', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][7], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //FECHA APLICA Y CAPITAL
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'NOMBRE:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'INTERESES', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][8], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //NOMBRE Y MORA
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, mb_convert_encoding(mb_strtoupper($registro[0][2], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'MORA', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][9], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //PRESTAMO Y OTROS
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, 'CUENTA:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 28, $tamanio_linea + 1, $registro[0][3], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'OTROS', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][10], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 1, $tamanio_linea + 2, 'SALDO', 'L-R-T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    //CANTIDAD EN LETRAS
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'Cantidad en letras:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'TOTAL', 'L-R-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][11], 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 6, $tamanio_linea + 2, $registro[0][12], 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    //TOTAL EN LETRAS
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->MultiCell(0, $tamanio_linea + 1, mb_convert_encoding($format_monto->toMoney($decimal[0], 2, '', ''), 'ISO-8859-1', 'UTF-8') . $res . "/100", 0, 'L');
    $pdf->Ln(5);
    $pdf->MultiCell(0, $tamanio_linea + 1, mb_convert_encoding(mb_strtoupper($registro[0][5], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 'L');
    $pdf->Ln(6);

    //USUARIO
    $pdf->CellFit(90, $tamanio_linea + 1, 'USUARIO:' . mb_convert_encoding($usuario, 'ISO-8859-1', 'UTF-8'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(-8);
    $pdf->CellFit(80, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(50, $tamanio_linea + 1, ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(10, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(50, $tamanio_linea + 1, ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(4);
    $pdf->CellFit(80, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(50, $tamanio_linea + 1, 'Cajero', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(10, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(50, $tamanio_linea + 1, 'Cliente', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(6);
}
