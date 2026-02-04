<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
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
$codigocredito = $archivo[1];
$numerocuota = $archivo[2];
$cnuming = $archivo[3];

printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general);

function printpdf($usuario, $codigocredito, $numerocuota, $cnuming, $conexion, $db_name_general)
{
    $consulta = "SELECT 
    ck.DFECPRO AS fechadoc,
    CAST(ck.DFECSIS AS Date) AS fechaaplica,
    cl.short_name AS nombre,
    cm.CCODCTA AS ccodcta,
    ck.CNUMING AS numdoc,
    ck.CCONCEP AS concepto,
    ctf.descripcion AS fuente,
    ck.KP AS capital,
    ck.INTERES AS interes,
    ck.MORA AS mora,
    (IFNULL(ck.AHOPRG, 0) + IFNULL(ck.OTR, 0)) AS otros,
    (IFNULL(ck.KP, 0) + IFNULL(ck.INTERES, 0) + IFNULL(ck.MORA, 0) + IFNULL(ck.AHOPRG, 0) + IFNULL(ck.OTR, 0)) AS total,
    (
        (SELECT IFNULL(SUM(ck2.NMONTO), 0) 
         FROM CREDKAR ck2 
         WHERE ck2.CTIPPAG = 'D' 
           AND ck2.CCODCTA = cm.CCODCTA 
           AND ck2.CESTADO != 'X')
        -
        (SELECT IFNULL(SUM(ck3.KP), 0) 
         FROM CREDKAR ck3 
         WHERE ck3.CTIPPAG = 'P' 
           AND ck3.CESTADO != 'X' 
           AND ck3.CCODCTA = cm.CCODCTA 
           AND ck3.CNROCUO <='" . $numerocuota . "')
    ) AS saldo,
    CASE 
    WHEN ck.boletabanco IS NULL OR ck.boletabanco = '' THEN 'X' 
    ELSE ck.boletabanco 
    END AS boletabanco, 
    ck.CCODBANCO,
    ck.CBANCO,
    tbn.nombre
FROM 
    cremcre_meta cm
    INNER JOIN CREDKAR ck ON cm.CCODCTA = ck.CCODCTA
    INNER JOIN tb_cliente cl ON cm.CodCli = cl.idcod_cliente
    INNER JOIN cre_productos pd ON cm.CCODPRD = pd.id
    INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo = ctf.id
    LEFT JOIN tb_bancos tbn ON ck.CBANCO = tbn.id
WHERE 
    cm.CCODCTA ='" . $codigocredito . "'  
    AND ck.CNUMING ='" . $cnuming . "'
    AND ck.CESTADO != 'X' 
    AND ck.CTIPPAG = 'P'";
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
    $hoy = date("d  -  m  -  Y ");
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

    $fuente = "Arial";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 9);
    $nomb_usuario = $_SESSION['nombre'] . " " . $_SESSION['apellido'];

    $pdf->CellFit(0, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(25);
    //hoy
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $hoy, 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(10, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, mb_convert_encoding(mb_strtoupper($registro[0][2], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 0, 'L', 0, '', 1, 0);

    $pdf->Ln(10);
    $pdf->SetFont($fuente, '', 8);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    //TOTAL EN LETRAS
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->MultiCell(0, $tamanio_linea + 1, utf8_decode($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 'L');

    $pdf->Ln(1);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->MultiCell(0, $tamanio_linea + 1, mb_convert_encoding(mb_strtoupper($registro[0][5], 'utf-8'), 'ISO-8859-1', 'UTF-8'), 0, 'L');


    $pdf->Ln(5);
    $pdf->CellFit(5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, 'Q ' . $registro[0][11], 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    if ($registro[0][13] === 'X') {
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][13], 0, 0, 'L', 0, '', 1, 0);
    } else {
        $pdf->CellFit(20, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][13], 0, 0, 'L', 0, '', 1, 0);
    }

    $pdf->Ln(8);
    $pdf->CellFit(25, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    if (empty($registro[0][16])) {
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, '------', 0, 0, 'L', 0, '', 1, 0);
    } else {
        $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, $registro[0][16], 0, 0, 'L', 0, '', 1, 0);
    }
    $pdf->Ln(15);
    //USUARIO
    $pdf->CellFit(0, $tamanio_linea + 1, mb_convert_encoding('USUARIO:' . $nomb_usuario, 'ISO-8859-1', 'UTF-8'), 0, 0, 'C', 0, '', 1, 0);
}
