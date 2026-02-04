<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
session_start();
include '../../../../src/funcphp/func_gen.php';

use Micro\Helpers\Log;
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
// $usuario = $archivo[0];
$usuario = $_SESSION['id'];
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

// Log::info("Generando recibo de pago individual", [
//     'usuario' => $usuario,
//     'codigocredito' => $codigocredito,
//     'numerocuota' => $numerocuota,
//     'cnuming' => $cnuming
// ]);

printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general);

function printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general)
{
    $consulta = "SELECT cl.idcod_cliente ,cl.Direccion, ck.DFECPRO AS fechadoc, CAST(ck.DFECSIS as Date) AS fechaaplica, cl.short_name AS nombre, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
	ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
	(IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
	((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA AND ck2.CESTADO!='X')-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<='" . $numerocuota . "')) AS saldo
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
    $institucion = decode_utf8($info[0]["nomb_comple"]);

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
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];
    $direccioncliente = decode_utf8($registro[0][1]);
    $saldocapital = round($registro[0][14], 2);
    $saldoanterior = $saldo_anterior = $saldocapital  + $registro[0][9];

    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 9);

    
    $pdf->Ln(15);

    $shift = 20;
    $baseX = $pdf->GetX() + $shift;

    $pdf->SetFont($fuente, '', 7);

    $pdf->SetX($baseX + 100);
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, $registro[0][5], 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(8);


    $pdf->SetFont($fuente, '', 9);
    // primera línea: nombre y número de documento
    $pdf->SetX($baseX);
    $pdf->CellFit($ancho_linea2 + 75, $tamanio_linea + 1, decode_utf8(mb_strtoupper($registro[0][4], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetX($baseX + 122); // ajustar separación entre columnas
    $pdf->CellFit($ancho_linea2 + 128, $tamanio_linea + 1, $registro[0][0], 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    // segunda línea: dirección y fecha
    $pdf->SetX($baseX);
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, $direccioncliente, 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetX($baseX + 115); // ajustar separación para la fecha
    $pdf->CellFit($ancho_linea2 + 23, $tamanio_linea + 1, date("d-m-Y", strtotime($registro[0][3])), 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(8);
    $pdf->SetX($baseX + 95);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $saldoanterior, '', 0, 'R', 0, '', 1, 0); // saldpo anterior
    $pdf->Ln(6);
    $pdf->SetX($baseX + 95);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][9], '', 0, 'R', 0 , '', 1, 0); // abono
    $pdf->Ln(6);
    $pdf->SetX($baseX + 95);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $saldocapital, '', 0, 'R', 0, '', 1, 0); // saldo actual

    
    $pdf->Ln(6);
    $pdf->SetX($baseX + 25);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][9], '', 0, 'R', 0, '', 1, 0); // capital
    $pdf->Ln(6);
    $pdf->SetX($baseX + 25);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][10], '', 0, 'R', 0, '', 1, 0); // intereses
    $pdf->Ln(6);
    $pdf->SetX($baseX + 25);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][12], '', 0, 'R', 0, '', 1, 0); // otros
    $pdf->Ln(6);
    $pdf->SetX($baseX + 25);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][11], '', 0, 'R', 0, '', 1, 0); // mora
    
    $pdf->Ln(39);

    $pdf->SetX($baseX + 25);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $registro[0][13], '', 0, 'R', 0, '', 1, 0);

    

    


    $pdf->Ln(6);

}

