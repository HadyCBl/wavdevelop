<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
session_start();

use Luecano\NumeroALetras\NumeroALetras;
use Micro\Generic\Utf8;

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
$usuario = $archivo[0];
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general);

function printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general)
{
    $consulta =
        "SELECT ck.DFECPRO AS fechadoc,
    CAST(ck.DFECSIS as Date) AS fechaaplica,
    cl.short_name AS nombre,
    cm.CCODCTA AS ccodcta,
    ck.CNUMING AS numdoc,
    ck.CCONCEP AS concepto,
	ctf.descripcion AS fuente,
    ck.KP AS capital,
    ck.INTERES AS interes,
    ck.MORA AS mora,
    (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
	(IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
	((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<='" . $numerocuota . "')) AS saldo,
    cl.Direccion
    FROM
    cremcre_meta cm
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
    //--REQ--CREDIPRENDAS--1--Impresion de 3 ejemplares del comprobante de pago
    // $pdf->Ln(20);
    // recibo($pdf, $registro, $hoy, $usuario, $info);
    // $pdf->Ln(20);
    // recibo($pdf, $registro, $hoy, $usuario, $info);

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
    // Establecer márgenes personalizados
    $pdf->SetMargins(15, 20, 20); // margen izquierdo, superior, derecho

    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, $hoy, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(32);

    $pdf->SetFont($fuente, '', 12);
    // Separar la fecha en día, mes y año
    // $fecha_aplica = date("d-m-Y", strtotime($registro[0][1]));
    // list($dia, $mes, $anio) = explode('-', $fecha_aplica);
    // $pdf->CellFit($ancho_linea2 + 48, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, $dia, 0, 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea2 - 15, $tamanio_linea + 1, $mes, 0, 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, $anio, 0, 1, 'C', 0, '', 1, 0);

    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 78, $tamanio_linea + 1, Utf8::decode(mb_strtoupper($registro[0][2], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, $registro[0][1], 0, 1, 'L', 0, '', 1, 0);

    $pdf->Ln(3);
    $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 1, ' ', 0, 0, 'C', 0, '', 1, 0);
    $direccion = isset($registro[0][13]) && !empty($registro[0][13]) ? Utf8::decode(mb_strtoupper($registro[0][13], 'utf-8')) : ' ';
    $pdf->CellFit($ancho_linea2 + 85, $tamanio_linea + 1, $direccion, 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, $registro[0][3], 0, 1, 'L', 0, '', 1, 0);
    $pdf->Ln(10);

    $pdf->SetFont($fuente, '', 12);
    //FECHA DOCTO Y FUENTES
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 28, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 1, 'L', 0, '', 1, 0);

    //FONDOS
    // $pdf->CellFit($ancho_linea2 + 1, $tamanio_linea + 1, mb_strtoupper($registro[0][6], 'utf-8'), 0, 0, 'L', 0, '', 1, 0);
    // $pdf->Ln(5);

    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'FECHA APLICA:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 50, $tamanio_linea + 1, date("d-m-Y", strtotime($registro[0][1])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CAPITAL', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, number_format($registro[0][7], 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //FECHA APLICA Y CAPITAL
    $pdf->CellFit($ancho_linea2 + 87, $tamanio_linea + 1, 'No. Documento: ' . $registro[0][4], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'INTERES', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, number_format($registro[0][8], 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //NOMBRE Y MORA
    $pdf->CellFit($ancho_linea2 + 87, $tamanio_linea + 1, 'PAGO A CUENTA: ' . $registro[0][3], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, ($registro[0][9] == 0 ? ' ' : 'MORA'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, ($registro[0][9] == 0 ? ' ' : 'Q'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, ($registro[0][9] == 0 ? ' ' : number_format($registro[0][9], 2, '.', ',')), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //PRESTAMO Y OTROS
    $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea + 1, 'MONTO EN LETRAS:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 27, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, ($registro[0][10] == 0 ? ' ' : 'OTROS'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, ($registro[0][10] == 0 ? ' ' : 'Q'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, ($registro[0][10] == 0 ? ' ' : number_format($registro[0][10], 2, '.', ',')), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->CellFit($ancho_linea2 - 29, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->MultiCell(170, $tamanio_linea + 1, Utf8::decode($format_monto->toMoney($decimal[0], 2, '', '')) . ' QUETZALES CON ' . $res . '/100', 0, 'L');
    $pdf->CellFit($ancho_linea2 + 87, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'TOTAL', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, number_format($registro[0][11], 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, 'B', 13);
    $pdf->CellFit($ancho_linea2 + 87, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'SALDO', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, number_format($registro[0][12], 2, '.', ','), 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, '', 10);
    $pdf->MultiCell(120, $tamanio_linea + 1, 'CONCEPTO: ' . Utf8::decode(mb_strtoupper($registro[0][5], 'utf-8')), 0, 'L');

    //USUARIO
    // $pdf->CellFit(0, $tamanio_linea + 1, 'USUARIO:' . $usuario, 0, 0, 'C', 0, '', 1, 0);
}