<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include '../../../../src/funcphp/fun_ppg.php';
require '../../../../fpdf/WriteTag.php';
// require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';

use Luecano\NumeroALetras\NumeroALetras;

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];
$codcredito = $archivo[0];

//SE CARGAN LOS DATOS
$strquery = "SELECT cli.short_name AS nomcli,cli.idcod_cliente,cli.date_birth,cli.no_identifica AS dpi,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls AS fecdesem,cre.DFecVen, cre.CCODCTA AS ccodcta,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_reside LIMIT 1),'-') municipio,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_reside LIMIT 1),'-') departamento,
IFNULL((SELECT nombre FROM tb_municipios WHERE id=cli.id_muni_extiende LIMIT 1),'-') muniextiende,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=cli.depa_extiende LIMIT 1),'-') depaextiende
From cremcre_meta cre
INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
WHERE cre.CCODCTA='" . $codcredito . "'";

$query = mysqli_query($conexion, $strquery);
$data[] = [];
$j = 0;
$flag = false;
while ($fil = mysqli_fetch_array($query)) {
    $data[$j] = $fil;
    $flag = true;
    $j++;
}

if (!$flag) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se puede generar la autentica debido a que no se encontraron datos',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

//BUSCAR DATOS DE GARANTIAS
$strquery = "SELECT cg.descripcionGarantia AS idcliente, cg.direccion AS direccioncliente,
(IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id = cg.depa),'-')) AS nomdep,
(IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.codigo = cg.muni),'-')) AS nommun,
IFNULL((SELECT '1' AS marcado FROM tb_cliente tc WHERE tc.idcod_cliente = cg.descripcionGarantia),0) AS fiador
FROM cremcre_meta cm
INNER JOIN tb_garantias_creditos tgc ON cm.CCODCTA = tgc.id_cremcre_meta 
INNER JOIN cli_garantia cg ON tgc.id_garantia = cg.idGarantia 
WHERE cg.estado = '1' AND cm.CCODCTA = '$codcredito'";
$query = mysqli_query($conexion, $strquery);
$garantias[] = [];
$j = 0;
$flag2 = false;
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $garantias[$j] = $fila;
    $flag2 = true;
    $j++;
}

if (!$flag2) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se puede generar el contrato debido a que no se encontro al menos una garantía',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

//VERIFICAR SI EXISTE UN FIADOR Y UNA HIPOTECARIA
$val_fiador = array_search('1', array_column($garantias, 'fiador'));
// $val_hipo = array_search('0', array_column($garantias, 'fiador'));

if ($val_fiador<0) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No completa los requisitos para generar el contrato'.$val_fiador.'ddd',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

//BUSCAR DATOS DE GARANTIAS
$pos_fiador = array_search('1', array_column($garantias, 'fiador'));
$strquery = "SELECT tc.short_name AS nomcli, tc.date_birth AS fechacumple, tc.estado_civil AS estadocivil, tc.profesion AS profesion, tc.no_identifica AS dpi FROM tb_cliente tc WHERE tc.idcod_cliente = '" . $garantias[$pos_fiador]['idcliente'] . "'";
$query = mysqli_query($conexion, $strquery);
$clientefiador[] = [];
$j = 0;
$flag2 = false;
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $clientefiador[$j] = $fila;
    $flag2 = true;
    $j++;
}

if (!$flag2) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se puede generar el contrato debido a que no se encontro al menos una garantía',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

//BUSCAR DATOS DE INSTITUCION
$queryins = mysqli_query($conexion, "SELECT ins.*, ag.*,
IFNULL((SELECT nombre FROM tb_municipios WHERE codigo=ag.municipio LIMIT 1),'-') municipioagencia,
IFNULL((SELECT nombre FROM tb_departamentos WHERE id=ag.departamento LIMIT 1),'-') departamentoagencia
FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
$flag4 = false;
while ($fil = mysqli_fetch_array($queryins, MYSQLI_ASSOC)) {
    $info[$j] = $fil;
    $flag4 = true;
    $j++;
}

//COMPROBACION: SI SE ENCONTRARON REGISTROS
if (!$flag || !$flag2 || !$flag4) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron datos, o no se cargaron algunos datos correctamente, intente nuevamente' . $flag . "f2" . $flag2  . "f4" . $flag4,
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

printpdf($data, $garantias, $clientefiador, $info, $conexion);

function printpdf($datos, $garantias, $clientefiador, $info, $conexion)
{

    //FIN COMPROBACION
    $oficina = ($info[0]["nom_agencia"]);
    $institucion = ($info[0]["nomb_comple"]);
    $direccionins = ($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '  ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../../includes/img/logomicro.png";
    $rutalogoins = "../../../.." . $info[0]["log_img"];
    //lo que se tiene que repetir en cada una de las hojas
    class PDF extends PDF_WriteTag
    {
        //atributos de la clase
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $rango;
        public $tipocuenta;
        public $saldoant;
        public $datos;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit)
        {
            parent::__construct();
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
        }

        // Cabecera de página
        function Header()
        {
            $this->SetFont('Arial', '', 10);
            // Número de página
            $this->Cell(0, 5,  $this->PageNo() . '/{nb}', 0, 0, 'R');
            // $this->Cell(0, 5, 'Pagina No.  ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
            $this->Ln(8);
        }

        // Pie de página
        function Footer()
        {

        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $fuente = "times";
    $tamanio_linea = 5;
    $ancho_linea = 30;
    $tamañofuente = 10;

    $hoy = date('Y-m-d');

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Stylesheet
    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("h1", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("a", $fuente, "BU", 10, "0,0,0");
    $pdf->SetStyle("pers", $fuente, "I", 0, "0,0,0");
    $pdf->SetStyle("place", $fuente, "U", 0, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", 0, "0,0,0");

    //TITULO DE CONTRATO
    $pdf->SetFont($fuente, 'B', $tamañofuente + 6);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('Autentica:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(8);

    $pdf->SetFont($fuente, 'B', $tamañofuente);
    //PRIMERA PARTE ESTATICA
    //variables para la fecha
    //fecha en letras
    $meses = array("ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE");
    $fechadesembolso = strtotime($datos[0]['fecdesem']);
    $dia_desembolso = new NumeroALetras();
    $dia_desembolsoaux = mb_strtolower($dia_desembolso->toWords((date("d", $fechadesembolso))), 'utf-8');
    $anodesembolso = new NumeroALetras();
    $anodesembolsoaux = mb_strtolower($anodesembolso->toWords((date("Y", $fechadesembolso))), 'utf-8');
    // division de dpi
    $letra_dpi = new NumeroALetras();
    $dpi_dividido = dividir_dpi(str_replace(' ', '', $datos[0]['dpi']));
    $letra_dpi1 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido[0]))));
    $letra_dpi2 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido[1]))));
    $letra_dpi3 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido[2]))));

    $dpi_dividido2 = dividir_dpi(str_replace(' ', '', $clientefiador[0]['dpi']));
    $letra_dpi1_2 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido2[0]))));
    $letra_dpi2_2 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido2[1]))));
    $letra_dpi3_2 = mb_strtolower($letra_dpi->toWords(intval(trim($dpi_dividido2[2]))));

    $inicio = (date("d", $fechadesembolso) == 1) ? ("al primer día del mes ".mb_strtolower($meses[date("m", $fechadesembolso) - 1], 'utf-8')). (' del año ') . ($anodesembolsoaux) : ("a los $dia_desembolsoaux días del mes ".mb_strtolower($meses[date("m", $fechadesembolso) - 1], 'utf-8'). (' del año ') . ($anodesembolsoaux));
    $muniagencia = ucfirst(mb_strtolower($info[0]['municipioagencia']));
    $depaagencia = ucfirst(mb_strtolower($info[0]['departamentoagencia']));


    $texto = "<p>En el municipio de " . ($muniagencia) . ", departamento de " . ($depaagencia) . ", " . $inicio . ", como Notario, <vb>DOY FE:</vb> que las firmas que anteceden son auténticas por haber sido puesta el día de hoy en mi presencia por el o la señor(a): <vb>" . mb_strtoupper($datos[0]['nomcli']) . ":</vb> quien se identifica con el Documento Personal de Identificación con Código Único de Identificación: " . $letra_dpi1 . ", " . $letra_dpi2 . ", " . $letra_dpi3 . "<vb> (" . $dpi_dividido[0] . " " . $dpi_dividido[1] . " " . $dpi_dividido[2] . "),</vb> extendido por el Registro Nacional de las Personas de la República de Guatemala, y: <vb>" . mb_strtoupper($clientefiador[0]['nomcli']) . "</vb> quien se identifica con el Documento Personal de Identificación con Código Único de Identificación." . ucfirst($letra_dpi1_2) . ", " . $letra_dpi2_2 . ", " . $letra_dpi3_2 . " <vb>(" . $dpi_dividido2[0] . " " . $dpi_dividido2[1] . " " . $dpi_dividido2[2] . "),</vb> extendido por el Registro Nacional de las Personas de la República de Guatemala quien firman la presente acta de legalización <vb>HAGO CONSTAR:</vb> Las firmas que se legalizan se encuentra al final de un pagaré libre de protesto a favor de <vb>CREDI APOYEMONOS, SOCIEDAD ANÓNIMA,</vb> de fecha " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " del " . $anodesembolsoaux . ".</p>";
    $pdf->WriteTag(0, 5, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(15);

    //cuadros
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
        $pdf->Cell(55, 5, ' ', 0, 0, 'R');
        $pdf->Cell(40, 5, ' ', "$lineaabajo" . "RL$lineaarriba", 0, 'C');
        $pdf->Ln(5);
        $lineaarriba = "";
        $lineaabajo = "";
    }

    //firmas
    $pdf->Ln(10);
    $pdf->CellFit(8, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, 5, 'F.', 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(72, 5, ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(20, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(5, 5, 'F.', 0, 0, 'R', 0, '', 1, 0);
    $pdf->CellFit(72, 5, ' ', 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont('Times', '', 9);
    $pdf->CellFit(8, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(77, 5, '(Deudor) ' . mb_strtoupper(decode_utf8($datos[0]['nomcli'])), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(20, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(77, 5, '(Fiador) ' . mb_strtoupper(decode_utf8($clientefiador[0]['nomcli'])), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont('Times', 'B', 11);
    $pdf->CellFit(8, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(77, 5, 'DPI ' . $datos[0]['dpi'], 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit(20, 5, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit(77, 5, 'DPI ' . $clientefiador[0]['dpi'], 0, 0, 'C', 0, '', 1, 0);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Autentica-" . ($datos[0]['ccodcta']),
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function fechaletras($date)
{
    $date = substr($date, 0, 10);
    $numeroDia = date('d', strtotime($date));
    $mes = date('F', strtotime($date));
    $anio = date('Y', strtotime($date));
    $meses_ES = array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
    $meses_EN = array("January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December");
    $nombreMes = str_replace($meses_EN, $meses_ES, $mes);
    return $numeroDia . " de " . $nombreMes . " de " . $anio;
}
function resumenpagos($clasdias, $column, $con1)
{
    $keys = array_keys(array_filter($clasdias[$column], function ($var) use ($con1) {
        return ($var == $con1);
    }));
    $fila = 0;
    $sum1 = 0;
    $sum2 = 0;
    while ($fila < count($keys)) {
        $f = $keys[$fila];
        $sum1 += ($clasdias["salcapital"][$f]);
        $sum2 += ($clasdias["capmora"][$f]);
        $fila++;
    }
    return [$sum1, $sum2, $fila];
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
