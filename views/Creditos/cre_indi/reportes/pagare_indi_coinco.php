<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
require('../../../../fpdf/WriteTag.php');
require '../../../../vendor/autoload.php';
// include '../../../../src/funcphp/valida.php';
include '../../../../src/funcphp/func_gen.php';

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];

$strquery = "SELECT cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, cli.genero,cli.estado_civil,cli.Direccion, cli.depa_extiende, cli.muni_extiende,
cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.NIntApro,cre.CodAnal,concat(usu.nombre,' ',usu.apellido) nomanal,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,cre.DFecVen,
pro.id_fondo id_fondos,ff.descripcion,pro.porcentaje_mora,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_reside LIMIT 1),'-') municipio,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_reside LIMIT 1),'-') departamento,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_extiende LIMIT 1),'-') muniextiende,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_extiende LIMIT 1),'-') depaextiende,
IFNULL((SELECT SUM(nintere) FROM Cre_ppg WHERE ccodcta=cre.CCODCTA GROUP BY ccodcta),0) intcal
From cremcre_meta cre
INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
INNER JOIN cre_productos pro ON pro.id=cre.CCODPRD
INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo
INNER JOIN tb_usuario usu ON usu.id_usu=cre.CodAnal
WHERE cre.TipoEnti='INDI' AND cre.CESTADO='F' AND cre.CCODCTA='" . $archivo[0] . "'";



$query = mysqli_query($conexion, $strquery);
$registro[] = [];
$j = 0;
$flag = false;
while ($fil = mysqli_fetch_array($query)) {
    $registro[$j] = $fil;
    $flag = true;
    $j++;
}
//BUSCAR DATOS DE INSTITUCION
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=".$_SESSION['id_agencia']);
$info[] = [];
$j = 0;
$flag2 = false;
while ($fil = mysqli_fetch_array($queryins, MYSQLI_ASSOC)) {
    $info[$j] = $fil;
    $flag2 = true;
    $j++;
}

//COMPROBACION: SI SE ENCONTRARON REGISTROS
if ($flag == false ) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron datos',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

$queryfiador = "SELECT 
    clig.descripcionGarantia,
    clig.montoAvaluo,
    CASE 
        WHEN cli.idcod_cliente = clig.descripcionGarantia THEN cli.short_name
        ELSE clig.descripcionGarantia
    END AS descripcion_cliente,
    cli.no_identifica,
    IFNULL(
        (SELECT nombre 
         FROM tb_municipios 
         WHERE id = cli.id_muni_reside 
         LIMIT 1), 
        '-') AS municipio,
    IFNULL(
        (SELECT nombre 
         FROM tb_departamentos 
         WHERE id = cli.depa_reside 
         LIMIT 1), 
        '-') AS departamento,
    IFNULL(
        (SELECT nombre 
         FROM tb_municipios 
         WHERE id = cli.id_muni_extiende 
         LIMIT 1), 
        '-') AS muniextiende,
    IFNULL(
        (SELECT nombre 
         FROM tb_departamentos 
         WHERE id = cli.depa_extiende 
         LIMIT 1), 
        '-') AS depaextiende
    FROM tb_garantias_creditos tgc 
    INNER JOIN cli_garantia clig ON clig.idGarantia = tgc.id_garantia 
    LEFT JOIN tb_cliente cli ON cli.idcod_cliente = clig.descripcionGarantia
    WHERE tgc.id_cremcre_meta ='" . $archivo[0] . "'";

$query = mysqli_query($conexion, $queryfiador);
$fiadores[] = [];
$j = 0;
$flag = false;
while ($fil = mysqli_fetch_array($query)) {
    $fiadores[$j] = $fil;
    $flag = true;
    $j++;
}
//COMPROBACION: SI SE ENCONTRARON REGISTROS
if ($flag == false) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'El credito no tiene fiadores como Garantia',
        'dato' => $queryfiador
    );
    echo json_encode($opResult);
    return;
}
// **** VARIABLE DE LOS DATOS BASICOS DEL CREDITO ****
$dtCre = [array_sum(array_column($registro, 'MonSug')), $registro[0]['NIntApro'] / 12, round($registro[0]['porcentaje_mora'] / 12, 2)]; // MONTO, INT, MORA, 
// **** FUNCION PARA LLAMAR LOS DATOS DE LOS INTERGRANTES DEL GRUPO ****


$vlrs = ['PAGARE LIBRE DE PROTESTO', $info[0]["nomb_comple"] . ' (' . $info[0]["nomb_cor"] . ').'];

$pdf = new PDF_WriteTag();
// $pdf->SetMargins(8,8,8);
$pdf->SetFont('courier', '', 12);
$pdf->AddPage();

// Stylesheet
$pdf->SetStyle("p", "times", "N", 11, "0,0,0", 0);
$pdf->SetStyle("h1", "times", "N", 11, "0,0,0", 0);
$pdf->SetStyle("a", "times", "BU", 9, "0,0,0");
$pdf->SetStyle("pers", "times", "I", 0, "0,0,0");
$pdf->SetStyle("place", "arial", "U", 0, "0,0,0");
$pdf->SetStyle("vb", "times", "B", 0, "0,0,0");


$pdf->Ln(15);


// $pdf->SetLineWidth(0.1);
headermanual($pdf, $dtCre, $info);
$txt = "";

//$txt .= parr1($dtCre, $vlrs, $registro, $fiadores);

$txt .= Hoja1($dtCre, $vlrs, $registro, $fiadores);

$pdf->WriteTag(0, 5, $txt, 0, "J", 0, 7);
firmas($pdf, $registro, $fiadores);

ob_start();
$pdf->Output();
$pdfData = ob_get_contents();
ob_end_clean();

$opResult = array(
    'status' => 1,
    'mensaje' => 'Pagaré generado correctamente',
    'namefile' => "Pagare",
    'tipo' => "pdf",
    'data' => "data:application/pdf;base64," . base64_encode($pdfData)
);
echo json_encode($opResult);

//FUNCIONES
function headermanual($fpdf, $dtCre, $info)
{
    //$fpdf->Image("../../../.." . $info[0]["log_img"], 160, 8, 40);

    $fpdf->SetFont('Arial', 'B', 14);
    $fpdf->Cell(0, 3, 'PAGARE', 0, 1, 'C');
    $fpdf->SetFont('Arial', 'I', 10);
    //$fpdf->ln(5);
}

function Hoja1($datacre, $vlrs, $registro, $fiadores ){
    $dia = date("d");
    $mes = date("m");
    $aa = date("Y");
    //FECHA DESEMBOLSO
    $fecha = $registro[0]["DFecDsbls"];
    $dd = substr($fecha, 8, 2);
    $aaa = substr($fecha, 0, 4); 
    $mm = (int)substr($fecha, 5, 2);
    $meses = [
        '1' => 'Enero', '2' => 'Febrero', '3' => 'Marzo', '4' => 'Abril',
        '5' => 'Mayo', '6' => 'Junio', '7' => 'Julio', '8' => 'Agosto',
        '9' => 'Septiembre', '10' => 'Octubre', '11' => 'Noviembre', '12' => 'Diciembre'
    ];
    $cant = ['1' => 'Uno', '2' => 'Dos', '3' => 'Tres', '4' => 'Cuatro',
        '5' => 'Cinco', '6' => 'Seis', '7' => 'Siete', '8' => 'Ocho',
        '9' => 'Nueve', '10' => 'Diez', '11' => 'Once', '12' => 'Doce'];
    $fechainicio = date("d-m-Y", strtotime($registro[0]["DfecPago"]));
    $fechafin = date("d-m-Y", strtotime($registro[0]["DFecVen"]));
    $format_monto = new NumeroALetras();
    $montoletra = $format_monto->toMoney($datacre[0], 2, 'QUETZALES', 'CENTAVOS');
    $montoletrainteres = $format_monto->toMoney($registro[0]['intcal'], 2, 'QUETZALES', 'CENTAVOS');


    $datos = decode_utf8('<p> En la aldea Chicojl del municipio de San Pedro Carchá, Alta Verapaz, el día <vb>');
    $datos .= $dia . ' de ' . $meses[$mes] . ' de ' . $aa . ',</vb> yo <vb>' . decode_utf8(mb_strtoupper($registro[0]['short_name'])) . '</vb>, ';
    $datos .= 'con Domicilio en ' . decode_utf8(ucwords(strtolower($registro[0]['Direccion'] . ', ' . $registro[0]['municipio'] . ', ' . $registro[0]['departamento']))) . decode_utf8('. Me identifico con el número de CUI <vb>') . $registro[0]['no_identifica'];
    $datos .= '</vb> dado en el RENAP, ' . decode_utf8(ucwords(strtolower($registro[0]['muniextiende'] . ', ' . $registro[0]['depaextiende'])));
    $datos .= decode_utf8('. Actuó en nombre propio, en adelante denominado como deudor, mediante este pagaré yo <vb>') . decode_utf8(mb_strtoupper($registro[0]['short_name']));
    $datos .= decode_utf8('</vb> declaro que DEBO Y PROMETO PAGAR INCONDICIONALMENTE LIBRE DE PROTESTO, A LA ORDEN de la <vb> COOPERATIVA INTEGRAL DE AHORRO Y CREDITO, RESPONSABILIDAD LIMITADA, LAJ KALEB' . "'" . 'AAL, COINCO, R.L. </vb> No. NIT: 110750039, de manera indistinta la cantidad de <vb>') . $montoletra;
    $datos .= ' (Q.' . number_format($datacre[0], 2) . decode_utf8(')</vb>  bajo las condiciones siguientes: I) <vb> PLAZO </vb> El plazo del presente documento será de <vb>');
    $datos .= $cant[$registro[0]['noPeriodo']] . ' meses contados a partir del ' . $dd . ' de ' . $meses[$mm] . ' de ' . $aaa;
    $datos .= decode_utf8('.</vb> II) <vb> LUGAR Y FORMA DE PAGO </vb>mediante <vb> una amortización de; (Q. ') . $registro[0]['intcal'] .') ' . $montoletrainteres . ', ';
    $datos .= decode_utf8('por un concepto pago total capital, de ') . $cant[$registro[0]['noPeriodo']] . decode_utf8(' cuotas de pagos mismo que deberá pagar en las fechas establecidas del pago de interés antes mencionado</vb>, cada una y que deberán cancelarse según el plan de pagos adjunto a este documento, sin necesidad de cobro o requerimiento alguno,<vb> en caso de atrasos se cobrara el ');
    $datos .= decode_utf8('1% diario sobre la cuota vencida.</vb> III) <vb>SIN PROTESTO </vb>queda exento de protesto, es decir el mismo se otorga sin protesto, sin gastos y otro equivalente, y de toda diligencia de presentación, protesto por falta de aceptación o aviso de falta de pago o de rechazo quedan por este apto voluntario y expresamente renunciado.<vb> IV) VENCIMIENTO </vb>el plazo de este pagare podrá darse por vencido, y el beneficiario podrá exigir su pago junto con otros intereses y otros gastos causados a) por falta de pago de amortización. En todos los casos el deudor reconoce como título ejecutivo suficiente para ejercer las acciones correspondientes, renunciando a cualquier excepción que pretende atacar la eficacia del título y su derecho de exigir la constitución de garantía de a cualquier ');
    $datos .= decode_utf8('cesionario, cedente, beneficiario, depositario, interventor, personero y mandatario de la parte ejecutante. Hago constar que ni la gestión extrajudicial en manera alguna, implican renuncia total o parcial al derecho del tenedor de demandar judicialmente de las obligaciones respectivas. Para el caso de cualquier acción judicial derivada del presente título de crédito el librador y cualquier otro obligado de este pagaré quedan sometidos indistintamente a la competencia de los tribunales del departamento de Alta Verapaz, renunciando al derecho de apelar del derecho de embargo, sentencia de remate y cualquier otra evidencia apelable que se dictara en el juicio ejecutivo correspondiente y sus incidentes y de cualquier manera, expresamente renuncia al fuero de su domicilio a las excepciones de orden y exclusión y se someten a los tribunales de justicia de Guatemala que elija el tenedor del título. En caso de juicio servirá de base el avalúo monto del adeudo o la primera postura u opción del tenedor tanto el librador o cualquier otro obligado de este pagare señalan como lugar para recibir notificaciones, citaciones, emplazamiento y diligencias judiciales y extrajudiciales con Domicilio en ');
    $datos .= decode_utf8(ucwords(strtolower($registro[0]['Direccion'] . ', ' . $registro[0]['municipio'] . ', ' . $registro[0]['departamento'])));
    $datos .= decode_utf8(' <vb> V) DE LA GARANTIA </vb> se declara que en garantía de ') . decode_utf8(mb_strtoupper($registro[0]['short_name'])) . ',';
    $datos .= ' CUI ' . $registro[0]['no_identifica'] . decode_utf8(' la siguiente garantía: ');
    $i=0;
    $fiadoresdetalles = '';
    while ($i < count($fiadores)) {
        $valorAMostrar = $fiadores[$i]['descripcion_cliente'];
        $fiadoresdetalles .= strtoupper(decode_utf8($valorAMostrar)) . ' valuado o equivalente a, Q.' . number_format($fiadores[$i]['montoAvaluo'], 2);
        $i++;
    }
    $datos .= $fiadoresdetalles;
    $datos .= decode_utf8(' en el caso de incumplimiento de la obligación adquirida se compromete al saneamiento de la ley.<vb> VI) OTROS</vb> a) la falta de ejercicio por el tenedor de este pagaré, del cualquiera de sus derechos derivados del mismo en cualquier instancia, no constituirá ninguna renuncia a cualquiera otra instancia. b) la causa del presente pagaré es el préstamo de dinero a la Cooperativa Integral de Ahorro y Crédito, Laj K' . "'" . 'aleb' . "'" . 'aal, Coinco R.L., con sede en la aldea Chicojl, San Pedro Carcha, Alta Verapaz, me realizó; y,<vb> VII) ACEPTACIÓN </vb>no habiendo nada más que hacer constar suscribimos el presente pagaré en señal de aceptación.');
    $datos .= '</p>';
    return $datos;
}

function firmas($fpdf, $registro, $fiadores)
{
    $fpdf->SetFont('Arial', 'I', 10);
    $fpdf->ln(35);
    $fpdf->SetXY(40, 250);
    $fpdf->Cell(40, 6, decode_utf8('Rolando Mucu Caal'), 'T', 0, 'C');
    $fpdf->SetXY(40, 255);
    $fpdf->Cell(40, 6, 'DPI: 2228 69052 1609', '0', 0, 'C');
    
    $fpdf->SetXY(125, 250);
    $fpdf->Cell(40, 6, decode_utf8(ucwords(strtolower($registro[0]['short_name']))), 'T', 0, 'C');
    $fpdf->SetXY(125, 255);
    $fpdf->Cell(40, 6, 'DPI: ' . $registro[0]['no_identifica'], '0', 0, 'C');
    $fpdf->SetXY(125, 260);
    $fpdf->Cell(40, 6, 'DEUDOR', '0', 0, 'C');

    /*if (is_numeric($fiadores[0]['descripcionGarantia'])) {
        $fpdf->Cell(15, 6, ' ', '', 0);
        $fpdf->Cell(60, 6, strtoupper(decode_utf8($fiadores[0]['descripcion_cliente'])), 'T', 0, 'C');
    }  */   
}
