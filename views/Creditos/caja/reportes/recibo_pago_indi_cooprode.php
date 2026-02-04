<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
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

printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion);

function printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion)
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

    // $oficina = "Coban";
    $institucion = "Cooperativa Integral De Ahorro y credito Imperial";
    $hoy = date("d           m          Y ");
    // $direccionins = "Canton vipila zona 1";
    // $emailins = "fape@gmail.com";
    // $telefonosins = "502 43987876";
    // $nitins = "1323244234";
    // $rutalogomicro = "../../../../includes/img/logomicro.png";
    // $rutalogoins = "../../../../includes/img/fape.jpeg";
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

    recibo($pdf, $registro, $hoy, $usuario);

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

function recibo($pdf, $registro, $hoy, $usuario)
{
    $fuente = "Arial";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 11);
// req2 recibo de ingresso 


$pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);

$pdf->Ln(25);
$pdf->CellFit($ancho_linea2 +20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit($ancho_linea2 +30, $tamanio_linea + 1, $hoy, 0, 0, 'L', 0, '', 1, 0);
$pdf->Ln(8);
$pdf->SetFont($fuente, '', 12);
$pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit($ancho_linea2 + 77, $tamanio_linea + 1, mb_convert_encoding(mb_strtoupper($registro[0][2], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L', 0, '', 1, 0);
$pdf->Ln(14);


   //juego de pocicioones 
    $pdf->SetFont($fuente, '', 10);
    $pdf->Ln(5);
  //  $pdf->CellFit($ancho_linea2-10 , $tamanio_linea + 1, '  ', 0, 0, 'L', 0, '', 1, 0);
    $posicionYActual = $pdf->GetY();
    $pdf->MultiCell($ancho_linea2 + 40, $tamanio_linea * 2, mb_convert_encoding(mb_strtoupper($registro[0][5], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 'L');
    $pdf->SetY($posicionYActual);
    $pdf->Ln(1);
    
    //FECHA DOCTO Y FUENTES
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, '  ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 10);
    $pdf->CellFit($ancho_linea2 + 20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, ' ', 0, 0, 'C', 0, '', 1,0 );
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea + 1,' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CAPITAL', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][7], 0, 0, 0, 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //FECHA APLICA Y CAPITAL
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'INTERESES', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][8], 0, 0, 0, 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 0, 0, '', 1, 0);
    $pdf->Ln(5);

    //NOMBRE Y MORA
    $pdf->CellFit($ancho_linea2 + 45, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'MORA', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][9], 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //PRESTAMO Y OTROS
    $pdf->CellFit($ancho_linea2 +20, $tamanio_linea + 2, 'SALDO', 'L-R-T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 -5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);// prestamo  $registro[0][3]
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'OTROS', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][10], 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea2-4 , $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 6, $tamanio_linea + 2, $registro[0][12], 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 -5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
      


    $pdf->Ln(10);
       //TOTAL
       $pdf->CellFit($ancho_linea2 + 65, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, ' ', ' 0', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][11], 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(8);

    //TOTAL EN LETRAS
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->CellFit($ancho_linea2 , $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->MultiCell(0, $tamanio_linea + 1, mb_convert_encoding($format_monto->toMoney($decimal[0], 2, '', ''), 'ISO-8859-1', 'UTF-8') . $res . "/100", 0, 'L');
    $pdf->Ln(12);
    

    //USUARIO
    $pdf->CellFit($ancho_linea2 , $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2+50 , $tamanio_linea + 2, 'USUARIO:' . mb_convert_encoding($usuario, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C', 0, '', 1, 0);
}