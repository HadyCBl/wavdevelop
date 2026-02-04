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
$datos = $_POST["datosval"];
$inputs = $datos[3];
$boleta_banco = "";
$nombre_banco = "";

$idgrupo = $inputs[0];
$cnuming = $inputs[1];
$ciclo = $inputs[2];
$usuario = $_SESSION['nombre'] . " " . $_SESSION['apellido'];



//Informacion de archivo 
// $usuario = $archivo[0];
// $codigocredito = $archivo[1];
// $ciclo = $archivo[2];
// $cnuming = $archivo[3];

printpdf($usuario, $idgrupo, $ciclo, $cnuming, $conexion, $db_name_general);

function printpdf($usuario, $codigocredito, $ciclo, $cnuming, $conexion, $db_name_general)
{
    // $consulta = "SELECT ck.DFECPRO AS fechadoc, CAST(ck.DFECSIS as Date) AS fechaaplica, cl.short_name AS nombre, cm.CCODCTA AS ccodcta, ck.CNUMING AS numdoc, ck.CCONCEP AS concepto, 
    // ctf.descripcion AS fuente, ck.KP AS capital, ck.INTERES AS interes, ck.MORA AS mora, (IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS otros,
    // (IFNULL(ck.KP,0) + IFNULL(ck.INTERES,0) + IFNULL(ck.MORA,0) + IFNULL(ck.AHOPRG,0) + IFNULL(ck.OTR,0)) AS total,
    // ((SELECT IFNULL(SUM(ck2.NMONTO),0) AS montocapital FROM CREDKAR ck2 WHERE ck2.CTIPPAG='D' AND ck2.CCODCTA=cm.CCODCTA)-(SELECT IFNULL(SUM(ck3.KP),0) AS totalpagado FROM CREDKAR ck3 WHERE ck3.CTIPPAG='P' AND ck3.CESTADO!='X' AND ck3.CCODCTA=cm.CCODCTA AND ck3.CNROCUO<='" . $numerocuota . "')) AS saldo
    // FROM cremcre_meta cm
    // INNER JOIN CREDKAR ck ON cm.CCODCTA=ck.CCODCTA
    // INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    // INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
    // INNER JOIN ctb_fuente_fondos ctf ON pd.id_fondo=ctf.id
    // WHERE cm.CCODCTA='" . $codigocredito . "' AND ck.CNUMING='" . $cnuming . "' AND ck.CESTADO!='X' AND ck.CTIPPAG='P'";


    $consulta = "SELECT gru.NombreGrupo,gru.direc,gru.codigo_grupo,crem.CCodGrupo,crem.NCiclo,crem.noPeriodo, cli.short_name,crem.CodCli,crem.CodAnal,crem.DFecApr,crem.DFecVen,crem.MonSug, cred.*,
    ((SELECT IFNULL(SUM(KP),0) FROM CREDKAR WHERE CTIPPAG='P' AND CESTADO!='X' AND CCODCTA=cred.CCODCTA AND CNROCUO<=cred.CNROCUO)) AS cappagadoant 
    FROM CREDKAR cred 
    INNER JOIN cremcre_meta crem ON crem.CCODCTA=cred.CCODCTA
    INNER JOIN tb_cliente cli ON cli.idcod_cliente=crem.CodCli
    INNER JOIN tb_grupo gru ON gru.id_grupos=crem.CCodGrupo
    WHERE cred.CESTADO!='X' AND cred.CNUMING='" . $cnuming . "' AND crem.CCodGrupo=" . $codigocredito . " AND crem.NCiclo=" . $ciclo . "";

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

    $sql = mysqli_query($conexion, "SELECT tb_bancos.nombre, CREDKAR.boletabanco 
                                    FROM CREDKAR
                                    INNER JOIN tb_bancos ON CREDKAR.CCODBANCO = tb_bancos.id
                                    WHERE CREDKAR.CNUMING = '" . $cnuming . "'
                                    ORDER BY CREDKAR.CODKAR DESC
                                    LIMIT 1;");


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
    if ($sql) {
        if (mysqli_num_rows($sql) > 0) {
            $nndatos = mysqli_fetch_assoc($sql);

            $nombre_banco = $nndatos['nombre'];   // Nombre del banco
            $boleta_banco = $nndatos['boletabanco']; // Boleta de banco
            recibo2($pdf, $registro, $usuario, $info, $cnuming, $nombre_banco, $boleta_banco);
        } else {
            recibo3($pdf, $registro, $usuario, $info, $cnuming);
        }
    } else {
        recibo3($pdf, $registro, $usuario, $info, $cnuming);
    }


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

function recibo($pdf, $registro, $hoy, $usuario, $info, $cnuming)
{
    $sum_montos = array_sum(array_column($registro, "MonSug"));
    $sum_cappag = array_sum(array_column($registro, "KP"));
    $sum_intpag = array_sum(array_column($registro, "INTERES"));
    $sum_morpag = array_sum(array_column($registro, "MORA"));
    $cappagadoant = array_sum(array_column($registro, "cappagadoant"));
    $sum_otro = array_sum(array_column($registro, "OTR"));
    $sum_total = array_sum(array_column($registro, "NMONTO"));
    $saldo = $sum_montos - $cappagadoant;

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
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, $cnuming, 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, $hoy, 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Ln(5);

    //FECHA DOCTO Y FUENTES
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, 'FECHA DOCTO.', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2 - 7, $tamanio_linea + 1, date("d-m-Y", strtotime($registro[0]['DFECPRO'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea2 - 27, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 26, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CONCEPTO', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CANTIDAD', 1, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, 'FECHA APLICA:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 23, $tamanio_linea + 1, date("d-m-Y", strtotime($registro[0]['DFECPRO'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'CAPITAL', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $sum_cappag, 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //FECHA APLICA Y CAPITAL
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'NOMBRE:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'INTERESES', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $sum_intpag, 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //NOMBRE Y MORA
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, Utf8::decode(mb_strtoupper($registro[0]['NombreGrupo'], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'MORA', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $sum_morpag, 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    //PRESTAMO Y OTROS
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea + 1, 'CICLO:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 28, $tamanio_linea + 1, $registro[0]['NCiclo'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'OTROS', 'L-R', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $sum_otro, 'R', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 1, $tamanio_linea + 2, 'SALDO', 'L-R-T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    //CANTIDAD EN LETRAS
    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, 'Cantidad en letras:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 23, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 + 5, $tamanio_linea + 2, 'TOTAL', 'L-R-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 2, $sum_total, 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(1, $tamanio_linea + 1, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, $tamanio_linea + 2, 'Q', 'L-B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 6, $tamanio_linea + 2, $saldo, 'R-B', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    //TOTAL EN LETRAS
    $format_monto = new NumeroALetras();
    $decimal = explode(".", $sum_total);
    $res = ($decimal[1] == 0) ? 0 : $decimal[1];
    $pdf->MultiCell(0, $tamanio_linea + 1, Utf8::decode($format_monto->toMoney($decimal[0], 2, '', '')) . $res . "/100", 0, 'L');
    $pdf->Ln(5);
    $pdf->MultiCell(0, $tamanio_linea + 1, Utf8::decode("PAGO DE CRÉDITO A NOMBRE DE " . strtoupper($registro[0]['NombreGrupo']) . " NUMERO DE RECIBO " . $cnuming), 0, 'L');
    $pdf->Ln(6);

    //USUARIO
    $pdf->CellFit(0, $tamanio_linea + 1, 'USUARIO:' . Utf8::decode(Utf8::decode($usuario)), 0, 0, 'C', 0, '', 1, 0);
}

function recibo2($pdf, $registro, $usuario, $info, $cnuming, $nombre_banco, $boleta_banco)
{
    //NUEVOS 
    /* 
    */
    $sum_montos = array_sum(array_column($registro, "MonSug"));
    $sum_cappag = array_sum(array_column($registro, "KP"));
    $sum_intpag = array_sum(array_column($registro, "INTERES"));
    $sum_morpag = array_sum(array_column($registro, "MORA"));
    $cappagadoant = array_sum(array_column($registro, "cappagadoant"));
    $sum_otro = array_sum(array_column($registro, "OTR"));
    $sum_total = array_sum(array_column($registro, "NMONTO"));
    $saldo = $sum_montos - $cappagadoant;

    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 30;
    $pdf->SetFont($fuente, '', 14);
    //GENERAR FECHA
    $Dia = date("d");
    $Mes = date("m");
    $Aa = date("Y");

    $sum_total = number_format($sum_total, 2, '.', ',');
    $sum_cappag = number_format($sum_cappag, 2, '.', ',');
    $sum_intpag = number_format($sum_intpag, 2, '.', ',');
    $sum_otro = number_format($sum_otro, 2, '.', ',');
    $sum_morpag = number_format($sum_morpag, 2, '.', ',');

    // $pdf->CellFit(0, $tamanio_linea + 1, ' ', 1, 0, 'L', 0, '', 1, 0);
    //var montoFormateado = Number(datos[0][7]).toLocaleString('es-MX');

    $pdf->SetXY(180, 0);
    $pdf->CellFit(50, 38, (($sum_total)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(145, 5);
    $pdf->CellFit(50, 50, (($Dia)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(168, 5);
    $pdf->CellFit(50, 50, (($Mes)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(190, 5);
    $pdf->CellFit(50, 50, (($Aa)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(50, 17);
    $pdf->CellFit(50, 50, (mb_strtoupper($registro[0]['NombreGrupo'], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(50, 25);
    $pdf->CellFit(50, 50, (($boleta_banco)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(50, 34);
    $pdf->CellFit(50, 50, (($nombre_banco)), 0, 0, 'L', 0, '', 1, 0);

    $pdf->SetXY(170, 52);
    $pdf->CellFit(50, 50, (($sum_cappag)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 61);
    $pdf->CellFit(50, 50, (($sum_intpag)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 70);
    $pdf->CellFit(50, 50, (($sum_morpag)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 79);
    $pdf->CellFit(50, 50, (($sum_otro)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 88);
    $pdf->CellFit(50, 50, (($sum_total)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(50, 107);
    $pdf->CellFit(50, 50, (($usuario)), 0, 0, 'L', 0, '', 1, 0);
}

function recibo3($pdf, $registro, $usuario, $info, $cnuming)
{
    //NUEVOS 
    /* 
    */
    $sum_montos = array_sum(array_column($registro, "MonSug"));
    $sum_cappag = array_sum(array_column($registro, "KP"));
    $sum_intpag = array_sum(array_column($registro, "INTERES"));
    $sum_morpag = array_sum(array_column($registro, "MORA"));
    $cappagadoant = array_sum(array_column($registro, "cappagadoant"));
    $sum_otro = array_sum(array_column($registro, "OTR"));
    $sum_total = array_sum(array_column($registro, "NMONTO"));
    $saldo = $sum_montos - $cappagadoant;

    $fuente = "Courier";
    $pdf->SetFont($fuente, '', 14);
    //GENERAR FECHA
    $Dia = date("d");
    $Mes = date("m");
    $Aa = date("Y");

    $sum_total = number_format($sum_total, 2, '.', ',');
    $sum_cappag = number_format($sum_cappag, 2, '.', ',');
    $sum_intpag = number_format($sum_intpag, 2, '.', ',');
    $sum_otro = number_format($sum_otro, 2, '.', ',');
    $sum_morpag = number_format($sum_morpag, 2, '.', ',');

    // $pdf->CellFit(0, $tamanio_linea + 1, ' ', 1, 0, 'L', 0, '', 1, 0);
    //var montoFormateado = Number(datos[0][7]).toLocaleString('es-MX');

    $pdf->SetXY(180, 0);
    $pdf->CellFit(50, 38,  (($sum_total)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(145, 5);
    $pdf->CellFit(50, 50, (($Dia)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(168, 5);
    $pdf->CellFit(50, 50, (($Mes)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(190, 5);
    $pdf->CellFit(50, 50, (($Aa)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(50, 17);
    $pdf->CellFit(50, 50, (mb_strtoupper($registro[0]['NombreGrupo'], 'utf-8')), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(50, 25);
    $pdf->CellFit(50, 50, ' --- EFECTIVO ---', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(50, 34);
    $pdf->CellFit(50, 50, ' --- EFECTIVO ---', 0, 0, 'L', 0, '', 1, 0);

    $pdf->SetXY(170, 52);
    $pdf->CellFit(50, 50,  (($sum_cappag)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 61);
    $pdf->CellFit(50, 50,  (($sum_intpag)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 70);
    $pdf->CellFit(50, 50,  (($sum_morpag)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 79);
    $pdf->CellFit(50, 50,  (($sum_otro)), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetXY(170, 88);
    $pdf->CellFit(50, 50,  (($sum_total)), 0, 0, 'L', 0, '', 1, 0);

    $pdf->SetXY(50, 107);
    $pdf->CellFit(50, 50,  (($usuario)), 0, 0, 'L', 0, '', 1, 0);
}
