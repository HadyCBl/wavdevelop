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

$strquery = "SELECT cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, cli.genero,cli.estado_civil,cli.Direccion, cli.profesion, pro.tasa_interes, cre.NCapDes,
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
$queryins = mysqli_query($conexion, "SELECT ins.*, ag.*,
IFNULL((SELECT nombre FROM tb_municipios WHERE codigo=ag.municipio LIMIT 1),'-') municipioagencia,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=ag.departamento LIMIT 1),'-') departamentoagencia
FROM $db_name_general.info_coperativa ins
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
//BUSCAR DATOS DE PLANES DE PLAGO
$querycreppg = "SELECT * FROM Cre_ppg cp WHERE cp.ccodcta = '" . $archivo[0] . "'";
$query = mysqli_query($conexion, $querycreppg);
$creppg[] = [];
$j = 0;
$flag = false;
while ($fil = mysqli_fetch_array($query)) {
    $creppg[$j] = $fil;
    $creppg[$j]['totalcuota'] = $creppg[$j]['ncapita'] + $creppg[$j]['nintere'] + $creppg[$j]['OtrosPagos'];
    $flag = true;
    $j++;
}
//COMPROBACION: SI SE ENCONTRARON REGISTROS
if ($flag == false) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'El credito no se le encontro su plan de pago',
        'dato' => $querycreppg
    );
    echo json_encode($opResult);
    return;
}

// **** VARIABLE DE LOS DATOS BASICOS DEL CREDITO ****
$dtCre = [array_sum(array_column($registro, 'MonSug')), $registro[0]['NIntApro'] / 12, round($registro[0]['porcentaje_mora'] / 12, 2)]; // MONTO, INT, MORA, 
// **** FUNCION PARA LLAMAR LOS DATOS DE LOS INTERGRANTES DEL GRUPO ****


$vlrs = ['PAGARE LIBRE DE PROTESTO', $info[0]["nomb_comple"] . ' (' . $info[0]["nomb_cor"] . ').'];
class PDF extends PDF_WriteTag
{
    public function __construct()
    {
        parent::__construct();
    }

    // Cabecera de página
    function Header()
    {
        // Posición: a 1 cm del final
        // $this->SetY(10);
        // Arial italic 8
        $this->SetFont('Arial', '', 10);
        // Número de página
        $this->Cell(0, 5, 'Pagina No.  ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
        $this->Ln(6);
    }
}

$pdf = new PDF();
// $pdf->SetMargins(8,8,8);
$pdf->SetFont('courier', '', 12);
$pdf->AliasNbPages();
$pdf->AddPage();

// Stylesheet
$pdf->SetStyle("p", "times", "N", 10, "0,0,0", 0);
$pdf->SetStyle("h1", "times", "N", 10, "0,0,0", 0);
$pdf->SetStyle("a", "times", "BU", 9, "0,0,0");
$pdf->SetStyle("pers", "times", "I", 0, "0,0,0");
$pdf->SetStyle("place", "times", "U", 0, "0,0,0");
$pdf->SetStyle("vb", "times", "B", 10, "0,0,0");

// $pdf->Ln(10);
// $pdf->SetLineWidth(0.1);
headermanual($pdf, $dtCre, $info, $registro, $creppg);
$txt = "";
$txt .= parr1($dtCre, $vlrs, $registro, $fiadores, $info, $creppg);
$pdf->WriteTag(0, 5, $txt, 0, "J", 0, 7);
$pdf->Ln(2);
//ESPACIO PARA CUADRITOS
plandepagos($pdf, $registro, $creppg);

//SEGUNDA PARTE LO QUE LE SIGUE A LOS PLANES DE PAGO
$txt2 = "";
$txt2 .= parr2($registro);
$pdf->WriteTag(0, 5, $txt2, 0, "J", 0, 7);

//CUADRITOS
$pdf->Ln(3);
$pdf->WriteTag(0, 5, '<p>ACEPTO LIBRE DE PROTESTO</p>', 0, "J", 0, 7);
$pdf->Ln(1);
$lineaarriba = "";
$lineaabajo = "";
for ($i = 0; $i < 5; $i++) {
    if ($i == 0) {
        $lineaarriba = "T";
    }
    if ($i == 4) {
        $lineaabajo = "B";
    }
    $pdf->Cell(28, 5, ' ', 0, 0, 'R');
    $pdf->Cell(40, 5, ' ', "$lineaabajo" . "RL$lineaarriba", 0, 'C');
    $pdf->Cell(50, 5, ' ', 0, 0, 'R');
    $pdf->Cell(40, 5, ' ', "$lineaabajo" . "RL$lineaarriba", 0, 'C');
    $pdf->Ln(5);
    $lineaarriba = "";
    $lineaabajo = "";
}

//firmas
$pdf->Ln(8);
$pdf->CellFit(8, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit(5, 5, 'F.', 0, 0, 'R', 0, '', 1, 0);
$pdf->CellFit(72, 5, ' ', 'B', 0, 'C', 0, '', 1, 0);
$pdf->CellFit(20, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit(5, 5, 'F.', 0, 0, 'R', 0, '', 1, 0);
$pdf->CellFit(72, 5, ' ', 'B', 0, 'C', 0, '', 1, 0);
$pdf->Ln(6);
$pdf->SetFont('Times', '', 9);
$pdf->CellFit(8, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit(77, 5, '(Deudor) ' . mb_strtoupper(decode_utf8($registro[0]['short_name'])), 0, 0, 'C', 0, '', 1, 0);
$pdf->CellFit(20, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit(77, 5, '(Fiador) ' . mb_strtoupper(decode_utf8($fiadores[0]['short_name'])), 0, 0, 'C', 0, '', 1, 0);
$pdf->Ln(5);
$pdf->SetFont('Times', 'B', 11);
$pdf->CellFit(8, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit(77, 5, 'DPI ' . $registro[0]['no_identifica'], 0, 0, 'C', 0, '', 1, 0);
$pdf->CellFit(20, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
$pdf->CellFit(77, 5, 'DPI ' . $fiadores[0]['no_identifica'], 0, 0, 'C', 0, '', 1, 0);







// firmas($pdf, $registro, $fiadores);

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
function headermanual($fpdf, $dtCre, $info, $registro, $creppg)
{
    // $fpdf->Image("../../../.." . $info[0]["log_img"], 164, 16, 30);
    $sumacapconinteres=($registro[0]['NCapDes'])+(array_sum(array_column($creppg,'nintere')));
    $fpdf->SetFont('Arial', 'B', 12);
    $fpdf->Cell(0, 3, 'PAGARE LIBRE DE PROTESTO', 0, 1, 'C');
    $fpdf->ln(2);
    $fpdf->Cell(0, 3, 'Q.' . number_format($sumacapconinteres, 2), 0, 1, 'C');
    $fpdf->SetFont('Arial', 'I', 10);
    $fpdf->ln(4);
    $fpdf->SetFont('Arial', 'B', 12);
    $fpdf->Cell(183, 3, 'No. '.$registro[0]['Dictamen'], 0, 1, 'R');
}

function parr1($datacre, $vlrs, $registro, $fiadores, $info, $creppg)
{
    $fechainicio = date("d-m-Y", strtotime($registro[0]["DfecPago"]));
    $fechafin = date("d-m-Y", strtotime($registro[0]["DFecVen"]));
    $format_monto = new NumeroALetras();
    $sumacapconinteres=($datacre[0])+(array_sum(array_column($creppg,'nintere')));
    $montoletra = $format_monto->toMoney($sumacapconinteres, 2, 'QUETZALES', 'CENTAVOS');
    //fecha en letras
    $meses = array("ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE");
    $fechadesembolso = strtotime($registro[0]['DFecDsbls']);
    $dia_desembolso = new NumeroALetras();
    $dia_desembolsoaux = mb_strtolower($dia_desembolso->toWords((date("d", $fechadesembolso))), 'utf-8');
    $anodesembolso = new NumeroALetras();
    $anodesembolsoaux = mb_strtolower($anodesembolso->toWords((date("Y", $fechadesembolso))), 'utf-8');
    //DIRECCION AGENCIA
    $muniagencia = ucfirst(mb_strtolower($info[0]['municipioagencia']));
    $depaagencia = ucfirst(mb_strtolower($info[0]['departamentoagencia']));
    //edad en letras
    $edadletras = new NumeroALetras();
    //numero de cuotas
    $numerocuotas = new NumeroALetras();
    $numerocuotasaux = mb_strtoupper($numerocuotas->toWords($registro[0]["noPeriodo"]), 'utf-8');

    $datos = (date("d", $fechadesembolso) == 1) ? decode_utf8("<p>Al primer día del mes") : decode_utf8("<p>A los $dia_desembolsoaux días del mes");
    $datos .= " de " . mb_strtolower($meses[date("m", $fechadesembolso) - 1], 'utf-8') . decode_utf8(' del año ') . decode_utf8($anodesembolsoaux);
    $datos .= ", en el municipio de " . decode_utf8($muniagencia) . ", departamento de " . decode_utf8($depaagencia);
    $datos .= ", YO, ";
    $i = 0;
    $nombresdetalles = '';
    while ($i < count($registro)) {
        $edadletrasaux = $edadletras->toWords((calcular_edad($registro[$i]['date_birth'])));
        $nombresdetalles .= '<vb>' . strtoupper(decode_utf8($registro[$i]['short_name'])) . '</vb>,' . decode_utf8(' de <vb>' . $edadletrasaux . ' AÑOS DE EDAD</vb>, ACTIVIDAD: <vb>' . decode_utf8(mb_strtoupper($registro[$i]['profesion']))) . ',</vb> Guatemalteco (a), con residencia en: ' . decode_utf8(mb_strtoupper($registro[$i]['Direccion'])) . ', ' . decode_utf8(mb_strtoupper($registro[0]['municipio'])) . ', ' . decode_utf8(mb_strtoupper($registro[0]['departamento'])) . ', <vb>me identifico con el Documento Personal de ' . decode_utf8('Identificación') . ' -DPI- con ' . decode_utf8('código único de identificación -cui- ') . $registro[$i]['no_identifica'] . ' extendido por el Registro Nacional de las Personas de la ' . decode_utf8('República de Guatemala,</vb>');
        $i++;
    }
    $datos .= $nombresdetalles;
    $datos .= ' Por medio del Presente ' . decode_utf8('Título de Crédito consistente en Pagaré libre de protesto') . ', <vb>PROMETO PAGAR INCONDICIONALMENTE</vb> a la orden de  <vb>CREDI APOYEMONOS, SOCIEDAD '.decode_utf8("ANÓNIMA").'</vb>, con domicilio en SEPTIMA AVENIDA, CANTON VITZAL, DIEZ GUION CERO TRECE (10-013), ZONA CERO, MUNICIPIO DE  ' . decode_utf8(mb_strtoupper($info[0]['municipioagencia'])) . ', DEPARTAMENTO DE ' . decode_utf8(mb_strtoupper($info[0]['departamentoagencia'])) . ', La cantidad de: ' . mb_strtoupper($montoletra . ' EXACTOS****(Q' . number_format($sumacapconinteres, 2)) . ') Mediante ' . $numerocuotasaux . ' (' . $registro[0]["noPeriodo"] . ') cuotas, mismas que ' . decode_utf8('haré') . ' efectivas en las fechas que ' . decode_utf8('más') . ' adelante se detallan, las cuales ' . decode_utf8('serán') . ' depositadas en la cuenta monetaria ' . decode_utf8('número') . ': <place>782-001873-2</place> del banco <place>Industrial</place> a nombre de <vb>CREDI APOYEMONOS, SOCIEDAD '.decode_utf8("ANÓNIMA").'.</vb></p>';
    //HASTA ACA ES FUNCIONAL

    //VAN LOS PLANES DE PAGO
    return $datos;
}

function parr2($registro)
{
    $datos = "";
    //LO QUE SIGUE DESPUES DE LOS PLANES DE PAGO
    $datos .= '<p>' . decode_utf8('Las condiciones en que cumpliré con la obligación son las siguientes: ---------------------------------------');
    $datos .= '<place><vb>RENUNCIA AL FUERO DE SU DOMICILIO,</vb></place> el librador, <vb>renuncia en forma voluntaria al <place>fuero de sus respectivos domicilios y se somete a los tribunales que el tenedor de este ' . decode_utf8('pagaré') . ' elija.</place></vb> Acepto que la falta de pago de una sola cuota de las amortizaciones estipuladas, ' . decode_utf8('dará') . ' derecho al tenedor de este ' . decode_utf8('pagaré') . ' a dar por <vb>anticipado el plazo de la presente ' . decode_utf8('OBLIGACIÓN') . ' Y EXIGIR</vb> el pago del saldo adeudado y ' . decode_utf8('también si se dictare mandamiento de ejecución y/o embargo en mi contra. ESTE PAGARÉ SE EMITE LIBRE DE PROTESTO, LIBRE DE FORMALIDADES DE PRESENTACIÓN Y COBRO O REQUERIMIENTO. En caso de juicio, ni el tenedor de este pagaré ni los auxiliares que proponga deberán prestar garantía. En caso de remate servirá de base el avalúo o monto del adeudo o la primera postura a opción del tenedor de este pagaré. El deudor acepta como buenas, liquidas y exigibles las cuentas que el tenedor del pagaré presente, <vb>ASÍ COMO EL PRESENTE TITULO PERFECTO E INCONTESTABLE.</vb>') . '</p>';

    $datos .= '<p>UNICAMENTE SE PAGARA EL ' . $registro[0]['tasa_interes'] . '% DE INTERES ANUAL, SOBRE EL CAPITAL DE LA ' . decode_utf8('OBLIGACIÓN.') . ' POR INCUMPLIMIENTO de pago de cada una de las cuotas antes expresadas se pagara el ' . number_format($registro[0]['porcentaje_mora'], 2) . '% de intereses moratorios, ASI COMO EL 10.00% DE LOS COSTOS JUDICIALES QUE EL MISMO DERIVE AL MOMENTO DE SER EJECUTADO.</p>';
    return $datos;
}

function plandepagos($pdf, $registro, $creppg)
{
    $divperiodo = (($registro[0]['noPeriodo'] / 4));
    $parteentera = $divperiodo;
    $partedecimal = 0;
    $banderadecimal = false;
    if (is_float($divperiodo)) {
        $banderadecimal = true;
        $parteentera = (int)$divperiodo;
        $partedecimal = $divperiodo - $parteentera;
    }

    $pdf->SetFont('Times', 'B', 7);
    if ($parteentera == 0) {
        if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
            $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        }
        if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        }
        if (($partedecimal >= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        }
    } else {
        $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, 'No', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Total cuota', 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, 'Fecha de pago', 1, 0, 'C', 0, '', 1, 0);
    }

    $pdf->Ln(4);
    $pdf->SetFont('Times', '', 7);
    for ($i = 0; $i < $parteentera; $i++) {
        $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[$i]['totalcuota'])) ? 'Q ' . number_format($creppg[$i]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[$i]['dfecven'])) ? date('d-m-Y', strtotime($creppg[$i]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        if ($banderadecimal) {
            if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
                $i++;
            }
        }
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1 + ($parteentera)), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + $parteentera)]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + $parteentera)]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + $parteentera)]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + $parteentera)]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        if ($banderadecimal) {
            if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
                $i++;
            }
        }
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1 + ($parteentera * 2)), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 2))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + ($parteentera * 2))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 2))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + ($parteentera * 2))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        if ($banderadecimal) {
            if (($partedecimal >= 0.75)) {
                $i++;
            }
        }
        $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
        $pdf->CellFit(6, 4, ($i + 1 + ($parteentera * 3)), 1, 0, 'C', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 3))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + ($parteentera * 3))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
        $pdf->CellFit(17, 4, (isset($creppg[($i + ($parteentera * 3))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + ($parteentera * 3))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        $pdf->Ln(4);
        if ($banderadecimal) {
            if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
                $i--;
            }
            if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
                $i--;
            }
            if (($partedecimal >= 0.75)) {
                $i--;
            }
        }
    }
    if ($banderadecimal) {
        $i = $parteentera;
        if ($parteentera == 0) {
            $i = 0;
        }
        if ($partedecimal <= 0.25 || $partedecimal <= 0.50 || $partedecimal <= 0.75) {
            $pdf->CellFit(7, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, ($i + 1), 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i)]['totalcuota'])) ? 'Q ' . number_format($creppg[($i)]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i)]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i)]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        }
        if (($partedecimal > 0.25 && $partedecimal <= 0.50) || ($partedecimal > 0.50 && $partedecimal <= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, ($i + 2 + ($parteentera)), 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 1 + ($parteentera))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + 1 + ($parteentera))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 1 + ($parteentera))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + 1 + ($parteentera))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        }
        if (($partedecimal >= 0.75)) {
            $pdf->CellFit(5, 4, ' ', 0, 0, 'L', 0, '', 1, 0);
            $pdf->CellFit(6, 4, ($i + 3 + ($parteentera * 2)), 1, 0, 'C', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 2 + ($parteentera * 2))]['totalcuota'])) ? 'Q ' . number_format($creppg[($i + (2 + $parteentera * 2))]['totalcuota'], 2) : 'Q 00.00', 1, 0, 'R', 0, '', 1, 0);
            $pdf->CellFit(17, 4, (isset($creppg[($i + 2 + ($parteentera * 2))]['dfecven'])) ? date('d-m-Y', strtotime($creppg[($i + 2 + ($parteentera * 2))]['dfecven'])) : '00-00-0000', 1, 0, 'R', 0, '', 1, 0);
        }
        $pdf->Ln(4);
    }
}
