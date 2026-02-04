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
$strquery = "SELECT cm.CCODCTA AS ccodcta, cm.DFecDsbls AS fecdesem, cm.TipoEnti AS formcredito, dest.DestinoCredito AS destinocred, cm.MonSug AS montoapro, cm.noPeriodo AS cuotas, tbp.nombre, cm.Dictamen AS dictamen,
    cl.idcod_cliente AS codcli, cl.short_name AS nomcli, cl.Direccion AS direccioncliente, cl.date_birth AS fechacumple, cl.estado_civil AS estadocivil, cl.profesion AS profesion, cl.no_identifica AS dpi,
    CONCAT(us.nombre,' ', us.apellido) AS analista,
    pr.id AS codprod, pr.nombre AS nomprod, pr.descripcion AS descprod, cm.NIntApro AS tasaprod, pr.porcentaje_mora AS mora,
    ff.descripcion AS nomfondo,
    (IFNULL((SELECT ppg2.ncapita FROM Cre_ppg ppg2 WHERE ppg2.ccodcta=cm.CCODCTA ORDER BY ppg2.dfecven ASC LIMIT 1),'x')) AS capitalppg,
    (IFNULL((SELECT ppg3.nintere FROM Cre_ppg ppg3 WHERE ppg3.ccodcta=cm.CCODCTA ORDER BY ppg3.dfecven ASC LIMIT 1),'x')) AS interesppg,
    (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id = cl.depa_reside),'-')) AS nomdep,
    (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id = cl.id_muni_reside),'-')) AS nommun
    FROM cremcre_meta cm
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu
    INNER JOIN cre_productos pr ON cm.CCODPRD=pr.id
    INNER JOIN ctb_fuente_fondos ff ON pr.id_fondo=ff.id
    INNER JOIN $db_name_general.tb_destinocredito dest ON cm.Cdescre=dest.id_DestinoCredito
    INNER JOIN $db_name_general.tb_periodo tbp ON cm.NtipPerC=tbp.periodo
    WHERE (cm.Cestado='F' OR cm.Cestado='E' OR cm.Cestado='D') AND cm.CCODCTA='$codcredito'
    GROUP BY tbp.periodo";
$query = mysqli_query($conexion, $strquery);
$data[] = [];
$j = 0;
$flag = false;
$codcli = "";
$codprod = "";
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $data[$j] = $fila;
    $codcli = $fila['codcli'];
    $codprod = $fila['codprod'];
    $flag = true;
    $j++;
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
$val_fiador=array_key_exists('1', array_column($garantias,'fiador'));
$val_hipo=array_key_exists('0', array_column($garantias,'fiador'));

if (!$val_fiador) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No completa los requisitos para generar el contrato',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

//BUSCAR DATOS DE GARANTIAS
$strquery = "SELECT tc.short_name AS nomcli, tc.date_birth AS fechacumple, tc.estado_civil AS estadocivil, tc.profesion AS profesion, tc.no_identifica AS dpi FROM tb_cliente tc WHERE tc.idcod_cliente = '" . $garantias[0]['idcliente'] . "'";
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

//BUSCAR GASTOS
$strquery = "SELECT cpg.id, cpg.id_producto, cpg.id_tipo_deGasto AS tipgasto, cpg.tipo_deCobro AS tipcobro, cpg.tipo_deMonto AS tipmonto, cpg.calculox AS calc, cpg.monto AS monto, ctg.nombre_gasto FROM cre_productos_gastos cpg 
INNER JOIN cre_tipogastos ctg ON cpg.id_tipo_deGasto=ctg.id
WHERE cpg.estado=1 AND ctg.estado=1 AND cpg.tipo_deCobro='1' AND cpg.id_producto='$codprod'";
$query = mysqli_query($conexion, $strquery);
$gastos[] = [];
$j = 0;
$flag3 = false;
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $gastos[$j] = $fila;
    $flag3 = true;
    $j++;
}

//BUSCAR DATOS DE INSTITUCION
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
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
        'mensaje' => 'No se encontraron datos, o no se cargaron algunos datos correctamente, intente nuevamente' . $flag . "f2" . $flag2 . "f3" . $flag3 . "f4" . $flag4,
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

printpdf($data, $garantias, $clientefiador, $gastos, $info, $flag3, $conexion);

function printpdf($datos, $garantias, $clientefiador, $gastos, $info, $flag3, $conexion)
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
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 8);
            //$this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 10, 33);

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 7);

            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            $this->Ln(1);
            $this->Cell(0, 2, $_SESSION['id'], 0, 1, 'R');

            // Logo de la agencia
            // $this->Image($this->pathlogoins, 10, 13, 33);

            // Título
            $this->SetFont($fuente, 'B', 9);
            $this->Cell(0, 3, utf8_decode(mb_strtoupper($this->institucion)), 0, 1, 'C');
            $this->Cell(0, 3, utf8_decode(mb_strtoupper($this->direccion)), 0, 1, 'C');
            $this->Cell(0, 3, 'TEl: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
            $this->Cell(0, 3, 'e-mail: ' . $this->email, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(3);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea = 30;
    $tamañofuente = 10;

    $hoy = date('Y-m-d');
    $vlrs = [$info[0]["nomb_comple"] . ' (' . $info[0]["nomb_cor"] . ').', '(' . $info[0]["nomb_cor"] . ')', $info[0]["nomb_cor"], 'créditos'];
    $fechahoy = fechaletras($hoy);

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
    $pdf->SetFont($fuente, 'B', $tamañofuente + 5);
    $pdf->CellFit(0, $tamanio_linea, utf8_decode('CONTRATO DE PRÉSTAMO'), 0, 0, 'C', 0, '', 1, 0);
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
    //variables para la edad del cliente
    $edadletras = new NumeroALetras();
    $edadletrasaux = mb_strtolower($edadletras->toWords((calcular_edad($datos[0]['fechacumple']))));
    $edadletrasaux2 = mb_strtolower($edadletras->toWords((calcular_edad($clientefiador[0]['fechacumple']))));
    //variable para estado civil
    $estadocivil = (isset($datos[0]['estadocivil'])) ? (", " . mb_strtolower($datos[0]['estadocivil']) . ", ") : (" ");
    $estadocivil2 = (isset($clientefiador[0]['estadocivil'])) ? (", " . mb_strtolower($clientefiador[0]['estadocivil']) . ", ") : (" ");
    //division de dpi
    $letra_dpi = new NumeroALetras();
    $dpi_dividido = dividir_dpi($datos[0]['dpi']);
    $letra_dpi1 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[0]))));
    $letra_dpi2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[1]))));
    $letra_dpi3 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[2]))));

    $dpi_dividido2 = dividir_dpi($clientefiador[0]['dpi']);
    $letra_dpi1_2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido2[0]))));
    $letra_dpi2_2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido2[1]))));
    $letra_dpi3_2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido2[2]))));

    $montodesembolsoletras = mb_strtolower($letra_dpi->toMoney(($datos[0]['montoapro']), 2, 'quetzales', 'centavos'));
    $cuotaspalabras = mb_strtolower($letra_dpi->toWords(($datos[0]['cuotas'])));

    $tasapalabras = ($letra_dpi->toWords((round(($datos[0]['tasaprod']/12),2))));
    $morapalabras = ($letra_dpi->toWords(($datos[0]['mora'])));
    //determinar si se imprime la segunda condicion
    $val_hipo=array_key_exists('0', array_column($garantias,'fiador'));




    //obtener el plan de pago
    $sumacapint = 0;
    $fecha_vence = '0000-00-00';
    if ($datos[0]['capitalppg'] == 'x' || $datos[0]['interesppg'] == 'x') {
        //LLAMAR A LA FUNCION PARA CAPINT
        $datos_sum = creppg_temporal($datos[0]['ccodcta'], $conexion);
        $sumacapint = $datos_sum[0]['nintpag'] + $datos_sum[0]['ncappag'];
        $fecha_vence = $datos_sum[(count($datos_sum) - 1)]['dfecven'];
        $fecha_vence2 = strtotime($fecha_vence);
        $dia_vence = mb_strtolower($dia_desembolso->toWords((date("d", $fecha_vence2))), 'utf-8');
        $ano_vence = mb_strtolower($anodesembolso->toWords((date("Y", $fecha_vence2))), 'utf-8');
    } else {
        $sumacapint = $datos[0]['capitalppg'] + $datos[0]['interesppg'];
    }

    $textosegunda="";
    $pos_fiador=array_search('1', array_column($garantias,'fiador'));
    if ($val_hipo) {
        $pos_hipo=array_search('0', array_column($garantias,'fiador'));
        //TEXTO DE LA SEGUNDA CONDICION
        $textosegunda=" <vb>SEGUNDA: GARANTÍA DE LA OBLIGACIÓN,</vb> EL DEUDOR, expresamente manifiesta que en garantía del cumplimiento de la obligación que contraen mediante este documento, dejan en <vb>garantía</vb> ".mb_strtolower($garantias[$pos_hipo]['idcliente'])." ubicado en Jurisdicción del Municipio de " . mb_strtolower(ucwords($garantias[$pos_hipo]['nommun'])) . ", " . mb_strtolower(ucwords($garantias[$pos_hipo]['nomdep'])) . ". EL DEUDOR hace constar de manera expresa, que sobre el bien inmueble descrito en esta cláusula, no existen gravámenes, anotaciones o limitaciones que pueden afectar los derechos del ACREEDOR. <vb>TERCERA:</vb> El Presidente y Representante Legal de la Cooperativa en la calidad con que actúa acepta expresamente la <vb>garantía</vb> que se hace a favor de su representada, dicho bien y documento queda en el goce y disfrute de EL DEUDOR la que no puede ser grabada ni enajenada sin ningún motivo alguno, salvo que se haya efectuado el último pago de la deuda. ";
    }

    $tercera=($val_hipo) ? "<vb>CUARTA:</vb>" : "<vb>SEGUNDA:</vb>";
    $cuarta=($val_hipo) ? "<vb>QUINTA:</vb>" : "<vb>TERCERA:</vb>";

    $pdf->SetFont($fuente, 'B', $tamañofuente - 1);
    $texto = "<p>En la " . ucwords($info[0]["muni_lug"]) . ", Municipio de Ixcán, Departamento de El Quiché, el día " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " (" . date('d-m-Y', strtotime($datos[0]['fecdesem'])) . "), <vb>NOSOTROS:</vb> por una parte los señores del Consejo Administración de la Cooperativa Integral de Ahorro y Crédito Primavera, Responsabilidad Limitada. En específico el señor <vb>Belisario Pérez Calmo</vb> de 39 años de edad, soltero, guatemalteco, perito contador, de este domicilio, con Documento Personal de Identificación (DPI), con Código Único de Identificación (CUI), número dos mil trescientos cincuenta y siete espacio cero seis mil doscientos cuarenta y ocho espacio un mil cuatrocientos veinte (2357 06248 1420), extendida por el Registro Nacional de Las Personas (RENAP), de la república de Guatemala, actúa en su calidad de <vb>Presidente y Representante Legal de la Cooperativa Integral de Ahorro y Crédito Primavera, Responsabilidad Limitada, quien podrá identificarse como \"COOPRIM, RL\"</vb> Calidad que acredita con la certificación del acta <vb>número cero tres guíon dos mil veinte y dos (03-2022),</vb> con fecha catorce de marzo de dos mil veinte y dos que aparece inscrita en el Registro Nacional de las Cooperativas (INACOP), Registro número once mil setecientos setenta y siete (11,777) y, quien en el curso del presente documento se denomina <vb><place>EL ACREEDOR;</place></vb> por la otra parte el o la señor(a) <vb>" . $datos[0]['nomcli'] . "</vb> de " . calcular_edad($datos[0]['fechacumple']) . " años de edad" . $estadocivil . mb_strtolower($datos[0]['profesion']) . ", con residencia en " . (ucwords($datos[0]['direccioncliente'])) . " del municipio de " . mb_strtolower(ucwords($datos[0]['nommun'])) . ", " . mb_strtolower(ucwords($datos[0]['nomdep'])) . ". quien se identifica con Documento Personal de Identificación (DPI), Código Unico de Identificación (CUI), Número " . $letra_dpi1 . " espacio " . $letra_dpi2 . " espacio " . $letra_dpi3 . " (" . $dpi_dividido[0] . " " . $dpi_dividido[1] . " " . $dpi_dividido[2] . "), extendida por el Registro Nacional de Las Personas (RENAP) de la República de Guatemala, a quien en este documento se denomina <vb><place>DEUDOR.</place></vb> así mismo comparece el o la señor(a) <vb>" . $clientefiador[0]['nomcli'] . "</vb> de " . calcular_edad($clientefiador[0]['fechacumple']) . " años de edad" . $estadocivil2 . mb_strtolower($clientefiador[0]['profesion']) . ", con residencia en " . (ucwords($garantias[$pos_fiador]['direccioncliente'])) . " del municipio de " . mb_strtolower(ucwords($garantias[$pos_fiador]['nommun'])) . ", " . mb_strtolower(ucwords($garantias[$pos_fiador]['nomdep'])) . ". quien se identifica con Documento Personal de Identificación DPI, Código Unico de Identificación CUI, Número " . $letra_dpi1_2 . " espacio " . $letra_dpi2_2 . " espacio " . $letra_dpi3_2 . " (" . $dpi_dividido2[0] . " " . $dpi_dividido2[1] . " " . $dpi_dividido2[2] . "), extendido por el Registro Nacional de las Personas RENAP, de la República de Guatemala, quien en el presente documento se denominará <vb><place>FIADOR.</place></vb> Los signatarios aseguramos: <vb>a)</vb> Ser de los datos de identificación personal anteriormente consignados; <vb>b)</vb> Hallarnos en el libre ejercicio de nuestros derechos civiles; y, <vb>c)</vb> que por el presente instrumento convenimos en celebrar este <vb>CONTRATO DE RECONOCIMIENTO DE DEUDA EN DOCUMENTO PRIVADO CON LEGALIZACIÓN DE FIRMAS,</vb> de conformidad con las siguientes clausulas. <vb>PRIMERA:</vb> Yo <vb>Belisario Pérez Calmo</vb> en la calidad con que actúo, en estos momentos otorgo un préstamo, según solicitud presentado a la Cooperativa Integral de Ahorro y Crédito Primavera Responsabilidad Limitada \"COOPRIM, RL\" por la cantidad de <vb>" . ucfirst($montodesembolsoletras) . " (Q. " . number_format($datos[0]['montoapro'], 2, '.', ',') . "),</vb> el presente <vb>CONTRATO</vb> se acoplará a las siguientes estipulaciones que a continuación se detallan; <vb>a) PLAZO:</vb> por un periodo de " . $cuotaspalabras . " meses (" . $datos[0]['cuotas'] . "), contados a partir de la fecha de desembolso del capital el día " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " (" . date('d-m-Y', strtotime($datos[0]['fecdesem'])) . ") y vence el día " . $dia_vence . " de " . mb_strtolower($meses[date("n", $fecha_vence2) - 1]) . " de " . $ano_vence . "(" . date('d-m-Y', strtotime($fecha_vence)) . "), pudiendo hacer efectivo el pago antes del término del plazo si así fuera la posibilidad del obligado o prorrogarse el plazo, previo acuerdo entre ambas partes; <vb>b) INTERÉS:</vb> La tasa de interés que devengará la presente DEUDA en este contrato será un " . $tasapalabras . " POR CIENTO MENSUAL (" . number_format((($datos[0]['tasaprod'])/12), 2, ',', ' ') . "%); <vb>a) LUGAR DE PAGO:</vb> EL DEUDOR se comprometen a realizar los pagos en efectivo a las oficinas de la Cooperativa Integral de Ahorro y Crédito Primavera, Responsabilidad Limitada. Que le es ampliamente conocida, ubicada en la Comunidad Primavera del Ixcán, Ixcán, El Quiché, o depósito a la cuenta bancaria del Banco Banrural del EL ACREEDOR \"COOPRIM, RL.\"; <vb>a) FORMA DE PAGO:</vb> EL DEUDOR se comprometen a realizar los pagos de los intereses mensualmente y el capital será en un solo pago al finalizar el plazo del presente contrato; <vb>a) INTERESES MORATORIOS:</vb> Por el cumplimiento de pago a la fecha pactada sufrirá un interés moratorio del " . $morapalabras . " POR CIENTO MENSUAL (" . ($datos[0]['mora']) . "\%) adicional a los intereses normales, a partir del cuarto día de la fecha del vencimiento del pago, calculado sobre el saldo de capital más total de intereses vencidos y por los días atrasados en contrato vigente y vencido. <vb>f) CESIÓN DEL CRÉDITO:</vb> Este crédito no es cedible o negociable. ".$textosegunda.$tercera." <vb>INCUMPLIMIENTO:</vb> EL DEUDOR acepta expresamente las condiciones estipuladas en este contrato, las cuales conoce perfectamente y por el incumplimiento del mismo, EL ACREEDOR podrá dar por vencido el plazo y procede a la ejecución judicial de la cantidad adeudada, intereses, mora y costas judiciales si se llegara a causar a la Cooperativa a través de su Representante Legal, para hacer valer sus intereses ".$cuarta." Ambos requirentes aceptamos el contenido del presente instrumento, estamos enterados de su contenido, objetivo, validez y demás efectos, lo aceptamos, ratificamos y firmamos.</p>";
    $pdf->WriteTag(0, 4, utf8_decode($texto), 0, "J", 0, 0);
    $pdf->Ln(19);

    //LUGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'Acreedor', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'Deudor', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 27, $tamanio_linea, 'Fiador', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(10);

    //PARTE 2 DEL DOCUMENTO
    $texto="<p>En la comunidad Primavera del Ixcán, Municipio de Ixcán, del Departamento de El Quiché, el día " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . ", Como Notaria: <vb>Doy Fe: a)</vb> Que las firmas que anteceden son <vb>AUTENTICAS</vb> por haber sido puesta en mi presencia por los señores <vb>Belisario Pérez Calmo,</vb> quien se identifica con Documento Personal de Identificación, Código Único de Identificación, Número dos mil trescientos cincuenta y siete espacio cero seis mil doscientos cuarenta y ocho espacio un mil cuatrocientos veinte (2357 06248 1420), extendida por el Registro Nacional de Las Personas de la República de Guatemala, <vb>" . $datos[0]['nomcli'] . "</vb> quien se identifica con Documento Personal de Identificación DPI, Código Unico de Identificación, Número " . $letra_dpi1 . " espacio " . $letra_dpi2 . " espacio " . $letra_dpi3 . " (" . $dpi_dividido[0] . " " . $dpi_dividido[1] . " " . $dpi_dividido[2] . "), y <vb>" . $clientefiador[0]['nomcli'] . "</vb> quien se identifica con Documento Personal de Identificación, Código Unico de Identificación, Número " . $letra_dpi1_2 . " espacio " . $letra_dpi2_2 . " espacio " . $letra_dpi3_2 . " (" . $dpi_dividido2[0] . " " . $dpi_dividido2[1] . " " . $dpi_dividido2[2] . "), ambos documentos extendido por el Registro Nacional de las Personas de la República de Guatemala, <vb>b)</vb> Los signatarios firman nuevamente el acta de legalización de firma.</p>";
    $pdf->WriteTag(0, 4, utf8_decode($texto), 0, "J", 0, 0);
    $pdf->Ln(19);

    //LUGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'Acreedor', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'Deudor', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 27, $tamanio_linea, 'Fiador', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(15);

    $texto="<p><vb>POR MÍ Y ANTE MÍ:</vb></p>";
    $pdf->WriteTag(0, 4, utf8_decode($texto), 0, "J", 0, 0);
    $pdf->Ln(1);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Contrato-" . (($datos[0]['ccodcta'] == '' || $datos[0]['ccodcta'] == null) ? ' ' : ($datos[0]['ccodcta'])),
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
