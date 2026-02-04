<?php
require '../../../../vendor/autoload.php';
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

$meses = array("enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre");

use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];

$sqlCre_Cli = "SELECT cm.CCODCTA, tc.idcod_cliente cli1, tc.short_name deudor1, tc.no_identifica dpi1,
cg.descripcionGarantia cli2, (SELECT short_name FROM tb_cliente tcF WHERE tcF.idcod_cliente = cg.descripcionGarantia) fiador, 
(SELECT no_identifica FROM tb_cliente tcF WHERE tcF.idcod_cliente = cg.descripcionGarantia) dpi2,
cm.MonSug monto ,cm.noPeriodo plazo , cm.CtipCre tipoCredito, cm.NtipPerC tipoPeriodo , cm.DfecPago pago1, cm.DFecVen ultimoPago,
(SELECT ncapita FROM Cre_ppg WHERE ccodcta = cm.CCODCTA AND ncapita LIMIT 1) cuota 
FROM cremcre_meta cm 
INNER JOIN tb_cliente tc ON tc.idcod_cliente  = cm.CodCli 
INNER JOIN tb_garantias_creditos tgc  ON tgc.id_cremcre_meta = cm.CCODCTA 
INNER JOIN cli_garantia cg ON cg.idGarantia = tgc.id_garantia
INNER JOIN Cre_ppg cp ON cp.ccodcta = cm.CCODCTA 
WHERE cm.Cestado = 'F' AND cg.idTipoDoc = 1 AND cm.CCODCTA = '$archivo[0]' AND cm.CCODCTA LIMIT 1";

$rst = $conexion->query($sqlCre_Cli);
if (!$rst) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'Error intentar mas tarde. 000',
    );
    echo json_encode($opResult);
    return;
}

if ($rst->num_rows > 0) {
    $infoCredito =  $rst->fetch_assoc();
} else {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'El credito no cuenta con un fiador como garantia',
    );
    echo json_encode($opResult);
    return;
}

//$deudor =  dataCliente($infoCredito['']);
//$fiador =  dataCliente($infoCredito['']);
//BUSCAR DATOS DE INSTITUCION
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
$flag2 = false;
while ($fil = mysqli_fetch_array($queryins, MYSQLI_ASSOC)) {
    $info[$j] = $fil;
    $flag2 = true;
    $j++;
}

// **** VARIABLE DE LOS DATOS BASICOS DEL CREDITO ****
// $dtCre = [array_sum(array_column($registro, 'MonSug')), $registro[0]['NIntApro'] / 12, round($registro[0]['porcentaje_mora'] / 12, 2)
// , $registro[0]['idcod_cliente'],  $registro[0]['municipio']  ]; // MONTO, INT, MORA, 
// **** FUNCION PARA LLAMAR LOS DATOS DE LOS INTERGRANTES DEL GRUPO ****

$vlrs = ['PAGARE LIBRE DE PROTESTO', $info[0]["nomb_comple"] . ' (' . $info[0]["nomb_cor"] . ').'];

$pdf = new PDF_WriteTag();
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
headermanual($pdf, $dtCre = [], $info);

$txt = "";
$txt .= deudores($infoCredito, $conexion, $db_name_general);

$pdf->WriteTag(0, 8, $txt, 0, "J", 0, 7);
firmas($pdf, $infoCredito['deudor1'], $infoCredito['fiador']);

/*****************************************************
 *****PARA AGREGAR UNA PAGINA 
 *****************************************************/
//$pdf->AddPage();

// $txt2 = "";
// $txt2 .= reverso($vlrs, $registro, $fiadores);

//$pdf->Ln(15);
//$pdf->WriteTag(0, 5, $txt2, 0, "J", 0, 7);

//firmas($pdf, $registro, $fiadores);

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
    $fpdf->Image("../../../.." . $info[0]["log_img"], 160, 8, 40);
    $fpdf->SetFont('Arial', 'B', 11);
    $fpdf->SetTextColor(220, 50, 50);
    $fpdf->Cell(0, 3, 'PAGARE LIBRE DE PROTESTO', 0, 1, 'C');
    $fpdf->SetTextColor(0, 0, 0);

    $fpdf->SetFont('Arial', 'I', 9);
    $fpdf->ln(5);
    //$fpdf->Cell(130, 4, 'VALOR NOMINAL:    Q '.number_format($dtCre[0], 2), 0, 0, 'L');
    //$fpdf->Cell(0, 4, 'COD. CLIENTE:  '.$dtCre[3], 0, 1, 'L');
    //$fpdf->Cell(130, 4, 'TASA DE INTERES: '.number_format($dtCre[1],2).'%', 0, 0, 'L');
    // $fpdf->Cell(0, 4, 'MUNICIPIO:     '.decode_utf8($dtCre[4]), 0, 1, 'L');
    // $fpdf->Cell(130, 4, 'TASA POR MORA:   '.$dtCre[2].'%', 0, 0, 'L');
    // $fpdf->Cell(0, 4, 'BUENO POR :    ', 0, 1, 'L');
}

function deudores($data, $conexion,$db_name_general)
{
    $fecha_actual = date("Y-m-d");
    $infoDeudor = dataCliente($data['cli1'], $conexion, $db_name_general);
    $fiador = dataCliente($data['cli2'], $conexion, $db_name_general);

    $meses = array("enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre");
    $letra_dpi = new NumeroALetras();
    $dpi_dividido = dividir_dpi(str_replace(' ', '', $data['dpi1']));
    $letra_dpi1 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido[0]))));
    $letra_dpi2 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido[1]))));
    $letra_dpi3 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido[2]))));

    $dpi_dividido1 = dividir_dpi(str_replace(' ', '', $data['dpi2']));
    $letra_dpi11 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido1[0]))));
    $letra_dpi22 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido1[1]))));
    $letra_dpi33 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido1[2]))));

    $monto =  mb_strtolower($letra_dpi->toWords(intval(trim($data['monto']))));
    $deciMonto = decimal($data['monto']);

    $fecha1 = spr_fecha($data['pago1']);
    $fecha2 = spr_fecha($data['ultimoPago']);
    $fecha_actual = spr_fecha($fecha_actual);

    $datos = decode_utf8("<p><vb>Nosotros: a) " . $data['deudor1'] . "</vb>, me identifico con el Documento Personal de Identificación (DPI), con Código Único de Identificación (CUI) número " . $letra_dpi1 . ", " . $letra_dpi2 . ", " . $letra_dpi3 . " (" . $dpi_dividido[0] . " " . $dpi_dividido[1] . " " . $dpi_dividido[2] . ") extendido por el Registro Nacional de las Personas, de la República de Guatemala, Centroamérica; y");

    $datos .= decode_utf8("<vb> b) " . $data['fiador'] . "</vb>, me identifico con el Documento Personal de Identificación (DPI), con Código Único de Identificación (CUI) número " . $letra_dpi11 . ", " . $letra_dpi22 . ", " . $letra_dpi33 . " (" . $dpi_dividido1[0] . " " . $dpi_dividido1[1] . " " . $dpi_dividido1[2] . "), extendido por el Registro Nacional de las Personas, de la República de Guatemala, Centroamérica, a quienes en lo sucesivo se nos podrá denominar indistintamente como <vb>LOS DEUDORES</vb>.");

    $datos .= decode_utf8(" NOSOTROS, <vb>LOS DEUDORES,</vb> actuamos de manera mancomunada y solidaria, y por medio de este TÍTULO DE CRÉDITO <vb>PROMETEMOS PAGAR INCONDICIONALMENTE a la entidad ASOCIACIÓN VIDA NUEVA,</vb> la suma de " . $monto . " quetzales " . (($deciMonto == 0) ? "exactos" : "con " . $deciMonto) . " (Q " . $data['monto'] . "). Dicha suma de dinero será cancelada en " . numero_Letras($data['plazo']) . " (" . $data['plazo'] . ") amortizaciones <vb>" . tipoPeriodo($data['tipoPeriodo']) . "</vb> niveladas, sucesivas y consecutivas de");

    $datos .= decode_utf8(" " . numero_Letras($data['cuota']) . " quetzales " . ((decimal($data['cuota']) == 0) ? "exactos" : "con " . decimal($data['cuota'])) . " centavos" . " (Q " . $data['cuota'] . "), iniciándose el pago de la primera cuota a partir del día " . numero_Letras($fecha1['dia']) . " (" . $fecha1['dia'] . ") <vb>de " . $meses[intval(ltrim($fecha1['mes'], 0)) - 1] . "</vb> del año " . numero_Letras($fecha1['anio']) . " (" . $fecha1['anio'] . ") y finalizando el día " . numero_Letras($fecha2['dia']) . " (" . $fecha2['dia'] . ") <vb>de " . $meses[intval(ltrim($fecha2['mes'], 0)) - 1] . "</vb> del año " . numero_Letras($fecha2['anio']) . " (" . $fecha2['anio'] . ").");

    $datos .= decode_utf8(" El pago de las amortizaciones, se efectuará libre de impuestos, descuentos, retenciones o recargos presentes y futuros en cualquiera de las agencias del BANCO INTERNACIONAL, SOCIEDAD ANÓNIMA (INTERBANCO) ubicadas en todo el territorio de la República de Guatemala sin necesidad de cobro o requerimiento alguno.");

    $datos .= decode_utf8(" LOS DEUDORES deberán además presentarse a las oficinas de la entidad <vb>ASOCIACIÓN VIDA NUEVA</vb>, ubicadas en la veintinueve (29) calle, siete guion cuarenta y dos, (7-42), zona tres (3) del Municipio y departamento de Guatemala,");

    $datos .= decode_utf8(" para presentar la boleta de depósito en la que conste el pago de cada una de las amortizaciones " . tipoPeriodo($data['tipoPeriodo']) . " o enviarlas a través de la aplicación denominada WhatsApp a los números de teléfono institucionales siguientes:");

    $datos .= decode_utf8(" cuatro mil seiscientos noventa y cinco, siete mil trescientos treinta y cinco (4695 7335) y/o tres mil trescientos cuarenta y uno, nueve mil seiscientos cuarenta (3341 9640).");

    $datos .= decode_utf8(" Si <vb>LOS DEUDORES</vb> no realizan <place>el pago total de la cuota acumulada mensual,</place> dentro de los primeros cinco (5) días de cada mes, <vb>EL TENEDOR</vb> del presente Título de Crédito, cobrará a <vb>LOS DEUDORES</vb> en concepto de penalización por mora una tasa de interés variable del <vb>CINCO POR CIENTO (5%).</vb>");

    $datos .= decode_utf8("<vb> LOS DEUDORES</vb> reconocemos que, por el pago de esta penalización, no se entiende extinguida la obligación principal. Este recargo por mora, tendrá preferencia al pago de capital e intereses, en las amortizaciones realizadas al crédito. En caso de la falta de pago de dos de las cuotas, se dará por vencido el plazo y se podrá ejecutar la obligación contenida en el presente título de crédito. Asimismo, LOS DEUDORES renunciamos al fuero de nuestro domicilio, sometiéndonos expresamente a los tribunales que la entidad ASOCIACIÓN VIDA NUEVA, elija señalando como lugar para recibir notificaciones, emplazamientos, citaciones, avisos y/o diligencias en las siguientes direcciones:");

    $datos .= decode_utf8(" " . $infoDeudor['direc'] . ", municipio de " . $infoDeudor['mun'] . ", del departamento de " . $infoDeudor['dep'] . ", y " . $fiador['direc'] . ", municipio de " . $fiador['mun'] . " del departamento " . $fiador['dep'] . ", teniendo por válidas y bien hechas las notificaciones, comunicaciones y/o citaciones que en dicho lugar se nos  hicieren.");

    $datos .= decode_utf8(" Todos los gastos que directa o indirectamente ocasione el presente pagaré, serán por cuenta de <vb>LOS DEUDORES</vb>, incluyendo los de cobro judicial y/o extrajudicial, honorarios de abogado si fuere necesario, así como cualesquiera comisiones de cambio, impuestos, timbres fiscales, tasas y contribuciones, y cualquiera otros impuestos relacionados con este documento que existan actualmente o que se decreten en el futuro.");

    $datos .= decode_utf8(" Asimismo, en caso de acción judicial por parte del tenedor del presente pagaré, <vb>LOS DEUDORES</vb> renunciamos al derecho de exigir prestación de cualquier tipo de garantías y en caso de remate, servirá de base el monto del adeudo o la primera postura a opción del tenedor de este pagaré.");

    $datos .= decode_utf8("<vb>LOS DEUDORES aceptamos como buenas, líquidas y exigibles</vb> las cuentas que la tenedora de este pagaré presente. Este pagaré puede ser cedido, pignorado o enajenado parcial o totalmente y negociado en cualquier otra forma por su tenedor, sin necesidad de previo aviso ni posterior notificación a <vb>LOS DEUDORES.</vb>");

    $datos .= decode_utf8("</p>");

    $datos .= "<p></p>";

    $datos .= decode_utf8("<p> Guatemala, " . $fecha_actual['dia'] . " de " . $meses[intval(ltrim($fecha_actual['mes'], 0)) - 1] . " del año " . numero_Letras($fecha_actual['anio']) . ". </p>");

    $datos .= "<p></p>";

    return $datos;
}

function numero_Letras($data)
{
    $letras = new NumeroALetras();
    $auxLetras = mb_strtolower($letras->toWords(intval(trim($data))));
    return $auxLetras;
}

function decimal($numero)
{
    // Convertimos el número a una cadena de texto
    $numero_str = strval($numero);
    // Buscamos el índice del punto decimal
    $indice_punto = strpos($numero_str, '.');
    // Si no hay punto decimal, retornamos '0'
    if ($indice_punto === false) {
        return '0';
    }
    // Retornamos los caracteres después del punto decimal
    return substr($numero_str, $indice_punto + 1);
}

function tipoPeriodo($data)
{
    if ($data === "7D") {
        return "semanales";
    }
    if ($data === "14D") {
        return "catorcenales";
    }
    if ($data === "15D") {
        return "quicenales";
    }
    if ($data === "1M") {
        return "mensuales";
    }

    return " - - - ";
}

function dividir_dpi($numero)
{
    $longitudGrupo1 = 4;
    $longitudGrupo2 = 5;
    $longitudTotal = strlen($numero);
    // Verificar si el número tiene al menos una longitud de grupo
    if ($longitudTotal >= $longitudGrupo1) {
        // Obtener los grupos de dígitos
        $grupo1 = substr($numero, 0, $longitudGrupo1);
        $grupo2 = substr($numero, $longitudGrupo1, $longitudGrupo2);
        $grupo3 = substr($numero, $longitudGrupo1 + $longitudGrupo2);
        // Devolver los grupos como un array
        return array($grupo1, $grupo2, $grupo3);
    } else {
        // Devolver un mensaje de error si el número no tiene la longitud mínima necesaria
        return array(0, 0, 0);
    }
}

function spr_fecha($fecha)
{
    // Separar la fecha en partes usando el delimitador "-"
    $partes_fecha = explode("-", $fecha);

    // Crear un array asociativo con las partes de la fecha
    $fecha_array = array(
        "anio" => $partes_fecha[0],
        "mes" => $partes_fecha[1],
        "dia" => $partes_fecha[2]
    );

    // Retornar el array asociativo
    return $fecha_array;
}

function dataCliente($codCli, $conexion, $db_name_general)
{
    $sql = "SELECT tc.Direccion direc ,dep.nombre dep ,m.nombre mun FROM tb_cliente tc 
    INNER JOIN tb_departamentos dep ON dep.id = tc.depa_reside
    INNER JOIN tb_municipios m ON m.id = tc.id_muni_reside
    WHERE tc.idcod_cliente = '$codCli'";

    $rst = $conexion->query($sql);
    if (!$rst) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'Error intentar mas tarde. 000'
        );
        echo json_encode($opResult);
        return;
    }
    return $rst->fetch_assoc();
}

function firmas($fpdf, $registro, $fiadores)
{
    $fpdf->SetFont('courier', '', 9);
    $fpdf->ln(35);
    $fpdf->Cell(15, 6, ' ', '', 0);
    $fpdf->SetX(17);

    $fpdf->Cell(60, 6, strtoupper(decode_utf8($registro)), 'T', 0, 'C');
    $x1 = $fpdf->Getx() + 3;
    $y = $fpdf->Gety() - 10;
    cuadro_huellas($fpdf, $x1, $y);

    $fpdf->Cell(15 + 15, 6, '', '', 0);

    $fpdf->Cell(60, 6, strtoupper(decode_utf8($fiadores)), 'T', 0, 'C');
    $x2 = $fpdf->Getx() + 3;
    cuadro_huellas($fpdf, $x2, $y);
}

function cuadro_huellas($fpdf, $x, $y)
{
    // Especificar posición para el cuadrado y dibujarlo
    //$x = 20;
    //$y = $fpdf->GetY(); // Obtener la posición Y actual
    $width = 20;
    $height = 25;
    $borderWidth = 0.3;
    $color = array(255, 255, 255); // Gris claro
    $fpdf->drawSquare($x, $y, $width, $height, 'rad', $borderWidth, $color);
}
