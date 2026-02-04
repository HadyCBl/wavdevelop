<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
require('../../../../fpdf/WriteTag.php');
require '../../../../vendor/autoload.php';
// include '../../../../src/funcphp/valida.php';
include '../../../../src/funcphp/func_gen.php';

use Luecano\NumeroALetras\NumeroALetras;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];

$strquery = "SELECT cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, cli.genero,cli.estado_civil,cli.Direccion,
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
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
$flag2 = false;
while ($fil = mysqli_fetch_array($queryins, MYSQLI_ASSOC)) {
    $info[$j] = $fil;
    $flag2 = true;
    $j++;
}

//COMPROBACION: SI SE ENCONTRARON REGISTROS
if ($flag == false) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron datos',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

$queryfiador = "SELECT cli.*,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_reside LIMIT 1),'-') municipio,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_reside LIMIT 1),'-') departamento,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_extiende LIMIT 1),'-') muniextiende,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_extiende LIMIT 1),'-') depaextiende
FROM tb_garantias_creditos tgc
INNER JOIN cli_garantia clig ON clig.idGarantia=tgc.id_garantia
INNER JOIN tb_cliente cli ON cli.idcod_cliente=clig.descripcionGarantia
WHERE tgc.id_cremcre_meta='" . $archivo[0] . "'";

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

$txt .= parr1($dtCre, $vlrs, $registro, $fiadores);

$pdf->WriteTag(0, 5, $txt, 0, "J", 0, 7);
firmas($pdf, $registro, $fiadores);

$pdf->AddPage();
$txt2 = " ";

$txt2 .= reverso($vlrs, $registro, $fiadores);
$pdf->Ln(15);
$pdf->WriteTag(0, 5, $txt2, 0, "J", 0, 7);
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
    $fpdf->Image("../../../.." . $info[0]["log_img"], 160, 8, 40);

    $fpdf->SetFont('Arial', 'B', 8);
    $fpdf->Cell(0, 3, 'PAGARE LIBRE DE PROTESTO', 0, 1, 'C');
    $fpdf->SetFont('Arial', 'I', 10);
    $fpdf->ln(5);
    $fpdf->Cell(6, 4, ' ', 0, 0, 'L');
    $fpdf->Cell(0, 4, 'VALOR NOMINAL:    Q ' .   number_format($dtCre[0], 2), 0, 1, 'L');
    $fpdf->Cell(6, 4, ' ', 0, 0, 'L');
    $fpdf->Cell(0, 4, 'TASA DE INTERES: ' . $dtCre[1] . '%', 0, 1, 'L');
    $fpdf->Cell(6, 4, ' ', 0, 0, 'L');
    $fpdf->Cell(0, 3, 'TASA POR MORA:   ' .   $dtCre[2] . '%', 0, 1, 'L');
}

function parr1($datacre, $vlrs, $registro, $fiadores)
{
    $fechainicio = date("d-m-Y", strtotime($registro[0]["DfecPago"]));
    $fechafin = date("d-m-Y", strtotime($registro[0]["DFecVen"]));
    $format_monto = new NumeroALetras();
    $montoletra = $format_monto->toMoney($datacre[0], 2, 'QUETZALES', 'CENTAVOS');
    $datos = "<p>Yo ";
    $i = 0;
    $nombresdetalles = '';
    while ($i < count($registro)) {
        $nombresdetalles .= '<vb>' . strtoupper(decode_utf8($registro[$i]['short_name'])) . '</vb>,' . decode_utf8(' de ' . calcular_edad($registro[$i]['date_birth']) . ' años de edad, ') . $registro[$i]['estado_civil'] . ' con DPI ' . $registro[$i]['no_identifica'] . ' con residencia en ' . $registro[$i]['Direccion'] . ', ';
        $i++;
    }
    $datos .= $nombresdetalles;
    $datos .= '' . decode_utf8($registro[0]['municipio']) . ', ' . decode_utf8($registro[0]['departamento']) . '.';
    $datos .= ' Por medio del presente <vb>' . $vlrs[0] . '</vb>, "me comprometo a cancelar de manera incondicional y libre de protesto", a la orden o endoso de <vb>' . decode_utf8($vlrs[1]) . '</vb>';
    $datos .= decode_utf8(' La suma de <vb>' . $montoletra . '</vb> más intereses ordinarios de forma mensual de acuerdo a la tasa convenida, iniciando el día <vb>(' . $fechainicio . ')</vb> así mismo como consecuencia la fecha de vencimiento es el día <vb>(' . $fechafin . ')</vb>');
    $datos .= decode_utf8(' En caso de mora el presente documento generará un interés moratorio del  (' . $datacre[2] . '%) mensual sobre saldos en todo caso, los intereses se calcularan sobre la base de que el año consta de trescientos sesenta y cinco días (365) y el mes calendario. ');
    $datos .= decode_utf8('El valor nominal más los interese se cancelarán sin necesidad de cobro ni requerimiento alguno mediante el número de amortizaciones que con anterioridad quedo estipulado. Las amortizaciones serán canceladas en la sede de la ');
    $datos .= '<vb>' . decode_utf8($vlrs[1] . '</vb> La cual yo <vb>');
    $nombres = '';
    $i = 0;
    while ($i < count($registro)) {
        $nombres .= '' . strtoupper(decode_utf8($registro[$i]['short_name']))  . ', ';
        $i++;
    }

    $datos .= $nombres;
    $datos .= decode_utf8('</vb> declaro conocer dentro del horario comprendido de lunes a viernes de ocho a diecisiete horas. En caso hubiera necesidad de incurrir en gastos de cobro ya sea por la vía extrajudicial, como judicial, yo ');
    $datos .= '<vb>' . $nombres .  decode_utf8('</vb> asumo la total responsabilidad del pago de los mismos comprometiéndome a cancelar el monto para tal efecto se me cobre, incluyendo honorarios de abogados, renunciando desde ya al fuero de mi domicilio y, me someto de manera expresa al fuero tribunal competente del departamento de Guatemala, por su parte el (la) señor (a) codeudor (a) ');

    $fiadoresdetalles = '';
    $i = 0;
    while ($i < count($fiadores)) {
        $fiadoresdetalles .= '<vb>' . strtoupper(decode_utf8($fiadores[$i]['short_name'])) . '</vb>,' . decode_utf8(' de ' . calcular_edad($fiadores[$i]['date_birth']) . ' años de edad, ') . $fiadores[$i]['estado_civil'] . ' con DPI ' . $fiadores[$i]['no_identifica'];
        $fiadoresdetalles .= ' extendido en el municipio de ' . (decode_utf8($fiadores[$i]['muniextiende'])) . ' ' . (decode_utf8($fiadores[$i]['depaextiende'])) . ' con residencia en ' . decode_utf8($fiadores[$i]['Direccion']) . ', ' . decode_utf8($fiadores[$i]['municipio']) . ' ' . decode_utf8($fiadores[$i]['departamento']) . ' ';
        $i++;
    }
    $datos .= $fiadoresdetalles;
    $datos .= decode_utf8(' manifiesta que por el presente documento se constituye en fiador (a) solidario y mancomunado por la obligación que contrae el presente ' . $vlrs[0] . ': ');
    $datos .= '<vb>' . strtoupper(decode_utf8($fiadores[0]['short_name']))  . decode_utf8('</vb> ofreciendo responder en forma individual, conjunta e inmediatamente renunciando desde ya al fuero de cualquier otro domicilio, y de manera expresa se someten al tribunal competente del departamento de Guatemala.');
    $datos .= '</p>';
    return $datos;
}
function reverso($vlrs, $registro, $fiadores)
{
    $datos = "<p>" . decode_utf8('En la ciudad de Guatemala, el día ') . fechletras($registro[0]['DFecDsbls']) . ' como notario DOY FE. ';
    $datos .= "" . decode_utf8('a) que las dos firmas que anteceden son autenticas porque fueron puestas en mi presencia por las personas presentes, ') . ' ';
    $i = 0;
    $nombresdetalles = '';
    while ($i < count($registro)) {
        $nombresdetalles .= '<vb>' . strtoupper(decode_utf8($registro[$i]['short_name'])) . '</vb>, con DPI ' . $registro[$i]['no_identifica'] . ' extendido en ' . decode_utf8($registro[$i]['muniextiende'])  . ' ' . decode_utf8($registro[$i]['depaextiende'])  . ', ';
        $i++;
    }
    $datos .= $nombresdetalles;
    $datos .= decode_utf8(' asi mismo el (la) señor (a)  ');
    $fiadoresdetalles = '';
    $i = 0;
    while ($i < count($fiadores)) {
        $fiadoresdetalles .= '<vb>' . strtoupper(decode_utf8($fiadores[$i]['short_name'])) . '</vb>, con DPI ' . $fiadores[$i]['no_identifica'];
        $fiadoresdetalles .= ' extendido en el municipio de ' . decode_utf8($fiadores[$i]['muniextiende']) . ' ' . decode_utf8($fiadores[$i]['depaextiende']) . '';
        $i++;
    }
    $datos .= $fiadoresdetalles;

    $datos .= decode_utf8('. b) que las firmas relacionadas que estan contenidas en el presente pagaré por el (a) señor (a) ') . strtoupper(decode_utf8($registro[0]['short_name']));
    $datos .= decode_utf8('. A favor de la ' . $vlrs[1] . ', compareciendo en el mismo en calidad de fiador solidario y mancomunado el (a) señor (a) <vb>');
    $datos .= strtoupper(decode_utf8($fiadores[0]['short_name']));
    $datos .= decode_utf8('</vb> c) Firman la presente acta de legalización para constancia juntamente con el infrascrito notario que de todo lo relacionado DA FE. ');
    $datos .= '</p>';
    return $datos;
}

function firmas($fpdf, $registro, $fiadores)
{
    $fpdf->SetFont('courier', '', 9);
    $fpdf->ln(35);
    $fpdf->Cell(15, 6, ' ', '', 0);
    $fpdf->Cell(60, 6, strtoupper(decode_utf8($registro[0]['short_name'])), 'T', 0, 'C');
    $fpdf->Cell(15, 6, ' ', '', 0);
    $fpdf->Cell(60, 6, strtoupper(decode_utf8($fiadores[0]['short_name'])), 'T', 0, 'C');
}
