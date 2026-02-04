<?php
session_start();
// FORMATO PARA LOS PAGARES DE FAPE, UNFOCADFO PRINCIPALMENTE EN LOS GRUPOS o bancos comunales de mujeres de confianza 
// TODAS LAS LIBRERIAS 
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
require('../../../../fpdf/WriteTag.php');
require '../../../../vendor/autoload.php';
// include '../../../../src/funcphp/valida.php';
include '../../../../src/funcphp/func_gen.php';

use Luecano\NumeroALetras\NumeroALetras;
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];

$strquery = "SELECT gru.*, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, cli.genero,cli.estado_civil,cli.Direccion,
cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.NIntApro,cre.CodAnal,concat(usu.nombre,' ',usu.apellido) nomanal,cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,cre.DFecVen,
pro.id_fondo id_fondos,ff.descripcion,pro.porcentaje_mora,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_reside LIMIT 1),'-') municipio,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_reside LIMIT 1),'-') departamento,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_extiende LIMIT 1),'-') muniextiende,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_extiende LIMIT 1),'-') depaextiende,
IFNULL((SELECT SUM(nintere) FROM Cre_ppg WHERE ccodcta=cre.CCODCTA GROUP BY ccodcta),0) intcal
From cremcre_meta cre
INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
INNER JOIN cre_productos pro ON pro.id=cre.CCODPRD
INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo
INNER JOIN tb_usuario usu ON usu.id_usu=cre.CodAnal
WHERE cre.TipoEnti='GRUP' AND cre.CESTADO='F' AND cre.CCodGrupo='" . $archivo[0] . "'  AND cre.NCiclo=" . $archivo[1] . " ORDER BY cre.CCODCTA";

$query = mysqli_query($conexion, $strquery);
$registro[] = [];
$j = 0;
$flag = false;
while ($fil = mysqli_fetch_array($query)) {
    $registro[$j] = $fil;
    $flag = true;
    $j++;
}
//COMPROBACION: SI SE ENCONTRARON REGISTROS
if ($flag == false) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron cuentas vigentes del grupo en el ciclo indicado',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}
//query institucion
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins)) {
    $info[$j] = $fil;
    $j++;
}

if ($j == 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Institucion asignada a la agencia no encontrada']);
    return;
}
$oficina = decode_utf8($info[0]["nom_agencia"]);
$institucion = ($info[0]["nomb_comple"]);
$instcorto = decode_utf8($info[0]["nomb_cor"]);
$direccionins = decode_utf8($info[0]["muni_lug"]);
$emailins = $info[0]["emai"];
$telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
$nitins = $info[0]["nit"];
$rutalogomicro = "../../../../includes/img/logomicro.png";
$rutalogoins = "../../../.." . $info[0]["log_img"];

//VARIABLES Y FUNCIONES **************************
$codgrp = $archivo[0];
$ciclo = $archivo[1];
// **** VARIABLE DE LOS DATOS BASICOS DEL CREDITO ****
$dtCre = [array_sum(array_column($registro, 'MonSug')), $registro[0]['NIntApro'] / 12, round($registro[0]['porcentaje_mora'] / 12, 2)]; // MONTO, INT, MORA, 
// **** FUNCION PARA LLAMAR LOS DATOS DE LOS INTERGRANTES DEL GRUPO ****


$vlrs = ['PAGARE LIBRE DE PROTESTO', $institucion . '(' . $instcorto . ')'];
//$vlrs = ['PAGARE LIBRE DE PROTESTO', 'FUNDACIÓN DE ASISTENCIA PARA LA PEQUEÑA EMPRESA (FAPE).'];

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
headermanual($pdf, $dtCre);
$txt = " ";
//$pdf->WriteTag(0,10,$txt,0,"J",0,7);

$txt .= parr1($dtCre, $vlrs, $registro);

$pdf->WriteTag(0, 5, $txt, 0, "J", 0, 7);
firmas($pdf, $registro);

$pdf->AddPage();
$txt2 = " ";

$txt2 .= reverso($vlrs, $registro);
$pdf->Ln(15);
$pdf->WriteTag(0, 5, $txt2, 0, "J", 0, 7);
firmas($pdf, $registro);

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
function headermanual($fpdf, $dtCre)
{
    $fpdf->Image('../../../../includes/img/fape.jpeg', 160, 8, 40, 0, 'jpeg');

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

function parr1($datacre, $vlrs, $registro)
{
    $fechainicio = date("d-m-Y", strtotime($registro[0]["DfecPago"]));
    $fechafin = date("d-m-Y", strtotime($registro[0]["DFecVen"]));
    $format_monto = new NumeroALetras();
    $montoletra = $format_monto->toMoney($datacre[0], 2, 'QUETZALES', 'CENTAVOS');
    $datos = "<p>" . decode_utf8('En la ciudad de Guatemala, el día ') . fechletras($registro[0]['DFecDsbls']) . ', Nosotras: ';
    $i = 0;
    $nombresdetalles = '';
    while ($i < count($registro)) {
        $nombresdetalles .= '<vb>' . strtoupper(decode_utf8($registro[$i]['short_name'])) . '</vb>,' . decode_utf8(' de ' . calcular_edad($registro[$i]['date_birth']) . ' años de edad, ') . $registro[$i]['estado_civil'] . ' con DPI ' . $registro[$i]['no_identifica'] . ' con residencia en ' . $registro[$i]['Direccion'] . ', ';
        $i++;
    }
    $datos .= $nombresdetalles;
    $datos .= 'todas Guatemaltecas con domicilio en ' . decode_utf8($registro[0]['municipio']) . ', del departamento de ' . decode_utf8($registro[0]['departamento']) . '.';
    $datos .= ' Por medio del presente <vb>' . $vlrs[0] . '</vb>, "nos comprometemos a cancelar de manera incondicional y libre de protesto", a la orden o endoso de <vb>' . decode_utf8($vlrs[1]) . '</vb>';
    $datos .= decode_utf8(' La suma de <vb>' . $montoletra . '</vb> más intereses ordinarios de forma mensual de acuerdo a la tasa convenida, iniciando el día <vb>(' . $fechainicio . ')</vb> así mismo como consecuencia la fecha de vencimiento es el día <vb>(' . $fechafin . ')</vb>');
    $datos .= decode_utf8(' En caso de mora el presente documento generará un interés moratorio del  (' . $datacre[2] . '%) mensual sobre saldos en todo caso, los intereses se calcularan sobre la base de que el año consta de trescientos sesenta y cinco días (365) y el mes calendario. ');
    $datos .= decode_utf8('El valor nominal más los interese se cancelarán sin necesidad de cobro ni requerimiento alguno mediante el número de amortizaciones que con anterioridad quedo estipulado. Las amortizaciones serán canceladas en la sede de la ');
    $datos .= '<vb>' . decode_utf8($vlrs[1] . '</vb> La cual nosotras: <vb>');
    $nombres = '';
    $i = 0;
    while ($i < count($registro)) {
        $nombres .= '' . strtoupper(decode_utf8($registro[$i]['short_name']))  . ', ';
        $i++;
    }
    $datos .= $nombres;
    $datos .= decode_utf8('</vb> declaramos conocer dentro del horario comprendido de lunes a viernes de ocho a diecisiete horas. En caso hubiera necesidad de incurrir en gastos de cobro ya sea por la vía extrajudicial, como judicial, nosotras: ');
    $datos .= '<vb>' . $nombres .  decode_utf8('</vb> asumimos la total responsabilidad del pago de los mismos comprometiéndonos a cancelar el monto para tal efecto se nos cobre, incluyendo honorarios de abogados, renunciando desde ya al fuero de mi domicilio y, nos sometemos de manera expresa al fuero tribunal competente del departamento de Guatemala, por su parte las codeudoras: ');
    $datos .= $nombresdetalles;
    $datos .= decode_utf8(' Manifestamos por el presente documento se constituye en fiadores solidarios mancomunados respectivamente la obligación que contrae el presente ' . $vlrs[0] . ': ');
    $datos .= '<vb>' . $nombres . decode_utf8('</vb> ofreciendo responder en forma individual, conjunta e inmediatamente renunciando desde ya al fuero de cualquier otro domicilio, y de manera expresa nos sometemos al tribunal competente del departamento de Guatemala.');
    $datos .= '</p>';
    return $datos;
}
function reverso($vlrs, $registro)
{
    $datos = "<p>" . decode_utf8('En la ciudad de Guatemala, el día ') . fechletras($registro[0]['DFecDsbls']) . ' como notario DOY FE. </p>';
    $datos .= "<p>" . decode_utf8('Que las cinco firmas que anteceden son autenticas porque fueron puestas en mi presencia por las personas presentes, ') . ' ';
    $i = 0;
    $nombresdetalles = '';
    while ($i < count($registro)) {
        $nombresdetalles .= '<vb>' . strtoupper(decode_utf8($registro[$i]['short_name'])) . '</vb>, con DPI ' . $registro[$i]['no_identifica'] . ' extendido en ' . decode_utf8($registro[$i]['muniextiende'])  . ' ' . decode_utf8($registro[$i]['depaextiende'])  . ', ';
        $i++;
    }
    $datos .= $nombresdetalles;

    $datos .= decode_utf8('b) que las firmas relacionadas que estan contenidas en el presente pagaré por las señoras: ') . $nombresdetalles;
    $datos .= decode_utf8('. A favor de la ' . $vlrs[1] . ', compareciendo en el mismo en calidad de fiador solidario y mancomunado las señoras: <vb>');

    $nombres = '';
    $i = 0;
    while ($i < count($registro)) {
        $nombres .= '' . strtoupper(decode_utf8($registro[$i]['short_name'])) . ', ';
        $i++;
    }
    $datos .= $nombres;
    $datos .= decode_utf8('</vb> c) Firman la presente acta de legalización para constancia juntamente con el infrascrito notario que de todo lo relacionado DA FE. ');
    $datos .= '</p>';
    return $datos;
}

function firmas($fpdf, $registro)
{
    $fpdf->SetFont('courier', '', 9);
    $fpdf->ln(25);
    $i = 0;
    $cont = 1;
    while ($i < count($registro)) {
        $fpdf->Cell(51, 6, strtoupper(decode_utf8($registro[$i]['short_name'])), 'T', 0, 'C');
        $fpdf->Cell(15, 6, ' ', '', 0);
        if ($cont > 2) {
            $fpdf->ln(25);
            $cont = 0;
        }
        $i++;
        $cont++;
    }
}
