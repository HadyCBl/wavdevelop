<?php
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
date_default_timezone_set('America/Guatemala');
include '../../../../src/funcphp/func_gen.php';
session_start();

use Luecano\NumeroALetras\NumeroALetras;

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
//se recibe los datos
$datos = $_POST["datosval"];

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
    $sq = "SELECT cremcre_meta.CodCli, CREDKAR.FormPago, CREDKAR.CBANCO, CREDKAR.CCODBANCO, tb_bancos.abreviatura, ctb_bancos.numcuenta
    FROM cremcre_meta 
    INNER JOIN CREDKAR ON CREDKAR.CCODCTA = cremcre_meta.CCODCTA
    LEFT JOIN tb_bancos ON tb_bancos.id = CREDKAR.CBANCO
    LEFT JOIN ctb_bancos ON ctb_bancos.id = CREDKAR.CCODBANCO
    WHERE cremcre_meta.CCODCTA = '" . $codigocredito . "'
    ORDER BY CREDKAR.DFECSIS DESC
    LIMIT 1";
    $extras = mysqli_query($conexion, $sq);
    $adicional[] = [];
    $t = 0;
    while ($f = mysqli_fetch_array($extras)) {
        $adicional[$t] = $f;
        $t++;
    }

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

    recibo($pdf, $registro, $hoy, $usuario, $info, $adicional);

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

function recibo($pdf, $registro, $hoy, $usuario, $info, $adicional)
{
    // $oficina = utf8_decode($info[0]["nom_agencia"]);
    // $direccionins = utf8_decode($info[0]["muni_lug"]);
    // $emailins = $info[0]["emai"];
    // $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    // $nitins = $info[0]["nit"];
    // $rutalogomicro = "../../../../includes/img/logomicro.png";
    // $rutalogoins = "../../../.." . $info[0]["log_img"];

    $fuente = "Arial";
    $y = 4;
    $tamanio_linea = 6;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 14);
    $valor_formateado = number_format($registro[0][11], 2, '.', ',');
    $pdf->SetXY(170, $y + 24);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, $valor_formateado, 0, 0, 'L', 0, '', 1, 0); //IMPORTE
    $pdf->SetXY(33, $y + 44);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, $adicional[0][0], 0, 0, 'L', 0, '', 1, 0); // CODIGO CLIENTE
    $pdf->SetXY(95, $y + 44);
    $pdf->CellFit($ancho_linea2 * 3, $tamanio_linea, $registro[0][2], 0, 0, 'L', 0, '', 1, 0); // NOMBRE CLIENTE
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $registro[0][11]);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->SetXY(28, $y + 52);
    $pdf->CellFit($ancho_linea2 * 3, $tamanio_linea, decode_utf8($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 0, 'L', 0, '', 1, 0); // CANTIDAD EN LETRAS
    $pdf->SetXY(28, $y + 59);
    $pdf->SetFont($fuente, '', 10);
    $pdf->CellFit($ancho_linea2 * 5, $tamanio_linea, decode_utf8($registro[0][5]), 0, 0, 'L', 0, '', 1, 0); // CONCEPTO

    $pdf->SetFont($fuente, '', 14); //MARCAR TIPO
    // $pdf->SetXY(0,$y + 65);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea , '', 0, 0, 'L', 0, '', 1, 0); //Cuota de Ingreso
    if ($registro[0][7] > 0) {
        $pdf->SetXY(80, $y + 65);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, 'X', 0, 0, 'L', 0, '', 1, 0); // Abono a Capital
    }
    // $pdf->SetXY(0,$y + 72);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea , '', 0, 0, 'L', 0, '', 1, 0); //Aportaciones
    // $pdf->SetXY(80,$y + 72);
    //$pdf->CellFit($ancho_linea2, $tamanio_linea , '', 0, 0, 'L', 0, '', 1, 0); // Aportaciones Extraordinarias
    if ($registro[0][8] > 0) {
        $pdf->SetXY(0, $y + 80);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, 'X', 0, 0, 'L', 0, '', 1, 0); // Pago de interes sobre prestamos
    }
    //$pdf->SetXY(80,$y + 80);
    //$pdf->CellFit($ancho_linea2, $tamanio_linea , '', 0, 0, 'L', 0, '', 1, 0); // Cancelacios de prestamos
    // $pdf->SetXY(0,$y + 86);
    // $pdf->CellFit($ancho_linea2, $tamanio_linea , '', 0, 0, 'L', 0, '', 1, 0); // Ahorros
    if ($registro[0][9] > 0) {
        $pdf->SetXY(0, $y + 93);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, 'X', 0, 0, 'L', 0, '', 1, 0); // Otros  -  MORA
    }

    //METODO DE PAGO
    if ($adicional[0][1] == 1) {
        $pdf->SetXY(28, $y + 100);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, '    X     ', 0, 0, 'L', 0, '', 1, 0); // EFECTIVO
        $pdf->SetXY(85, $y + 100);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, '---------', 0, 0, 'L', 0, '', 1, 0); // Cheque
        $pdf->SetXY(160, $y + 100);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, '---------', 0, 0, 'L', 0, '', 1, 0); // Banco
    } else if ($adicional[0][1] == 2) {
        $pdf->SetXY(28, $y + 100);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, '---------', 0, 0, 'L', 0, '', 1, 0); // EFECTIVO
        $pdf->SetXY(85, $y + 100);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, '---------', 0, 0, 'L', 0, '', 1, 0); // Cheque
        $pdf->SetXY(160, $y + 100);
        $pdf->CellFit($ancho_linea2 + 10, $tamanio_linea, $adicional[0][4] . ' - ' . $adicional[0][5], 0, 0, 'L', 0, '', 1, 0); // Banco
    }

    //FECHA
    setlocale(LC_TIME, 'es_MX.UTF-8'); // Configurar localización
    $dia = date('d');                 // Día con dos dígitos
    $mes = strftime('%B');            // Nombre completo del mes en localización configurada
    $anio = date('y');                // Últimos dos dígitos del año

    // Imprimir en las posiciones específicas del PDF
    $pdf->SetXY(90, $y + 112);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, $dia, 0, 0, 'L', 0, '', 1, 0); // DIA
    $pdf->SetXY(140, $y + 112);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, ucfirst($mes), 0, 0, 'L', 0, '', 1, 0); // MES con mayúscula inicial
    $pdf->SetXY(195, $y + 112);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, $anio, 0, 0, 'L', 0, '', 1, 0); // AÑO


    //USUARIO
    // $pdf->CellFit(0, $tamanio_linea + 1,  utf8_decode(utf8_decode('USUARIO:' . $usuario)), 0, 0, 'C', 0, '', 1, 0);
}
