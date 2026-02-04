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
	((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<='" . $numerocuota . "')) AS saldo, cl.no_identifica AS dpi
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

    reciboCiacreho($pdf, $registro, $hoy, $usuario, $info);

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

function reciboCiacreho($pdf, $registro, $hoy, $usuario, $info)
{
    $pdf->AddFont('Calibri', '', 'calibri.php');
    $pdf->AddFont('Calibri', 'B', 'calibrib.php');
    $fuente = "Calibri";

    $tabular = 60;
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->ln(4);
    $pdf->SetFont($fuente, '', 9);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 + 50, $tamanio_linea, "  ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 3, $tamanio_linea + 10, Utf8::decode($registro[0][2]), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->ln(8);

    //CANTIDAD EN LETRAS
    $pdf->SetFont($fuente, 'B', 11);
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->Ln(0);
    $format_monto = new NumeroALetras();

    $pdf->CellFit($ancho_linea2 + 50, $tamanio_linea + 2, ' ', '', 0, 'L', 0, '', 1, 0); //TOTAL   
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 5, $registro[0][11], '', 0, 'L', 0, '', 1, 0); //TOTAL   
    $pdf->ln(6);
    //Tipo de fondo
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2 * 2 + 35, $tamanio_linea + 2, "  ", 0, 0, 'L', 0, '', 1, 0); //celda bacia 
    // $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, mb_strtoupper($registro[0][6], 'utf-8'), 0, 0, 'L', 0, '', 1, 0); //tipo de fondo
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(4, $tamanio_linea + 2, '  ', 0, 0, 'C', 0, '', 1, 0); //ESPACIO
    //$pdf->ln(5);//FORMATO DE CAP, INT, MORA, OTR,
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'CONCEPTO', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'CANTIDAD', 1, 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, '  ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->ln(5);

    //Fecha ***********
    $pdf->CellFit($ancho_linea2 * 2 + 5, $tamanio_linea + 2, "  ", 0, 0, 'L', 0, '', 1, 0); //celda vacia 
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    // $pdf->CellFit($ancho_linea2 - 10, $tamanio_linea + 2,date( "d-m-Y",strtotime([0][0])),  0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(4, $tamanio_linea + 2, '  ', 0, 0, 'C', 0, '', 1, 0); //ESPACIO
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'CAPITAL', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 2, $registro[0][7], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->ln(5);

    //No. de cuenta **********
    $pdf->CellFit($ancho_linea2 * 2 + 35, $tamanio_linea + 2, "  ", 0, 0, 'L', 0, '', 1, 0); //celda vacia 
    $pdf->SetFont($fuente, 'B', 11);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'CUENTA: ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(4, $tamanio_linea + 2, '  ', 0, 0, 'C', 0, '', 1, 0); //ESPACIO
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'INTERESES', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 2, $registro[0][8], 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->ln(5);

    //Cuenta************************
    $pdf->CellFit($ancho_linea2 * 2 + 39, $tamanio_linea + 2, "  ", 0, 0, 'L', 0, '', 1, 0); //celda vacia 
    $pdf->SetFont($fuente, 'B', 11);
    // $pdf->CellFit($ancho_linea2+4, $tamanio_linea + 2, $registro[0][3], 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    // $pdf->CellFit(4, $tamanio_linea + 2, '  ', 0, 0, 'C', 0, '', 1, 0); //ESPACIO
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'MORA', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 2, $registro[0][9], 'R', 0, 'R', 0, '', 1, 0);
    // $pdf->CellFit(1, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);

    if ($registro[0][10] < 1) {
        $pdf->CellFit(1, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(23, $tamanio_linea + 2, 'SALDO', 'L-R-T', 0, 'L', 0, '', 1, 0);
        $pdf->SetFont($fuente, '', 11);
    }
    $pdf->Ln(5);

    //OTROS
    if ($registro[0][10] > 0) {
        $pdf->CellFit($ancho_linea2 * 3 + 5 + 4, $tamanio_linea + 2, "  ", 0, 0, 'L', 0, '', 1, 0); //celda bacia 
        $pdf->SetFont($fuente, 'B', 11);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'OTROS', 'L-R', 0, 'L', 0, '', 1, 0);
        $pdf->SetFont($fuente, '', 11);
        $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 2, $registro[0][10], 'R', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(1, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->SetFont($fuente, 'B', 11);
        $pdf->CellFit(23, $tamanio_linea + 2, 'SALDO', 'L-R-T', 0, 'C', 0, '', 1, 0);
        $pdf->SetFont($fuente, '', 11);
        $pdf->Ln(5);
    }

    //*****
    $pdf->CellFit($ancho_linea2 * 2 + 5 + 34, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 11);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'TOTAL', 'L-R-B', 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 2, $registro[0][11], 'R-B', 0, 'R', 0, '', 1, 0); //TOTAL 

    $pdf->CellFit(1, $tamanio_linea + 2, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit(18, $tamanio_linea + 2, $registro[0][12], 'R-B', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(8);

    if ($registro[0][10] > 0) {
        // Si $registro[0][10] es mayor que 0
    } else {
        // Si $registro[0][10] NO es mayor que 0
        $pdf->Ln(2);
        $pdf->CellFit($ancho_linea2 * 3 + 10 + 4, $tamanio_linea + 2, "  ", 0, 1, 'L', 0, '', 1, 0); //celda vacía 
        $pdf->Ln(2);
    }

    //TOTAL EN LETRAS
    $pdf->SetFont($fuente, '', 11);
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->Ln(1);

    // $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 2, " ", 0, 0, 'L', 0, '', 1, 0); //celda vacía 


    $pdf->CellFit(0, $tamanio_linea + 2, mb_convert_encoding($format_monto->toMoney($decimal[0]), 'ISO-8859-1', 'UTF-8') . $res . "/100", 0, 1, 'L', 0, '', 1, 0);

    $pdf->CellFit(150, $tamanio_linea, (mb_convert_encoding(mb_strtoupper($registro[0][5], 'utf-8'), 'ISO-8859-1', 'UTF-8')), 0, 1, 'L', 0, '', 1, 0);
    $pdf->Ln(4);

    //CLIENTE
    $pdf->SetFont($fuente, '', 11);
    $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea + 2, " ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($tabular + 52, $tamanio_linea + 2, 'Cuenta: ' . $registro[0][3], 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(4);
    //DPI 
    // $pdf->CellFit($ancho_linea2 + 30, $tamanio_linea + 2, " ", 0, 0, 'L', 0, '', 1, 0);
    // $pdf->CellFit($tabular + 33, $tamanio_linea + 2, $registro[0][13], 0, 0, 'L', 0, '', 1, 0);
    // $pdf->Ln(1);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 * 3 + 40, $tamanio_linea + 2, "  ", 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea + 2, 'USUARIO: ' . $usuario, 0, 0, 'L', 0, '', 1, 0);
}
