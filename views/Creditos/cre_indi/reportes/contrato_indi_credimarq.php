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

// $tipo = 'pdf';
// $codcredito = '0020020100000030';

//SE CARGAN LOS DATOS
$strquery = "SELECT cm.CCODCTA AS ccodcta, cm.DFecDsbls AS fecdesem, cm.TipoEnti AS formcredito, dest.DestinoCredito AS destinocred, cm.MonSug AS montoapro, cm.noPeriodo AS cuotas, tbp.nombre, cm.Dictamen AS dictamen,
    cl.idcod_cliente AS codcli, cl.short_name AS nomcli, cl.Direccion AS direccioncliente, cl.date_birth AS fechacumple, cl.estado_civil AS estadocivil, cl.profesion AS profesion, cl.no_identifica AS dpi, cl.nacionalidad AS nacionalidad, cm.TipDocDes AS tipodesembolso,
    CONCAT(us.nombre,' ', us.apellido) AS analista,
    pr.id AS codprod, pr.nombre AS nomprod, pr.descripcion AS descprod, cm.NIntApro AS tasaprod, pr.porcentaje_mora AS mora,
    ff.descripcion AS nomfondo,
    (IFNULL((SELECT ppg2.ncapita FROM Cre_ppg ppg2 WHERE ppg2.ccodcta=cm.CCODCTA ORDER BY ppg2.dfecven ASC LIMIT 1),'x')) AS capitalppg,
    (IFNULL((SELECT ppg3.nintere FROM Cre_ppg ppg3 WHERE ppg3.ccodcta=cm.CCODCTA ORDER BY ppg3.dfecven ASC LIMIT 1),'x')) AS interesppg,
    (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id = cl.depa_reside),'-')) AS nomdep,
    (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id = cl.id_muni_reside),'-')) AS nommun,
    (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id = cl.depa_extiende),'-')) AS nomdepextiende,
    (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id = cl.id_muni_extiende),'-')) AS nommunextiende,
    (IFNULL((SELECT DestinoCredito FROM $db_name_general.tb_destinocredito td WHERE td.id_DestinoCredito = cm.Cdescre),'-')) AS destinocredito
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
$strquery = "SELECT cg.descripcionGarantia AS idcliente, cg.direccion AS direccioncliente, cg.idTipoGa AS tipogarantia,
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
$val_hipo = array_search('2', array_column($garantias, 'tipogarantia'));
$pos_hipo = array_search('2', array_column($garantias, 'tipogarantia'));

if ($val_hipo === false) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No completa los requisitos para generar el contrato, falta garantía hipotecaria',
        'dato' => $val_hipo
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

// if (!$flag2) {
//     $opResult = array(
//         'status' => 0,
//         'mensaje' => 'No se puede generar el contrato debido a que no se encontro al menos una garantía',
//         'dato' => $strquery
//     );
//     echo json_encode($opResult);
//     return;
// }

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
if (!$flag || !$flag4) {
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
            $this->Cell(0, 3, decode_utf8(mb_strtoupper($this->institucion)), 0, 1, 'C');
            $this->Cell(0, 3, decode_utf8(mb_strtoupper($this->direccion)), 0, 1, 'C');
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
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('CONTRATO DE MUTUO'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(8);

    $pdf->SetFont($fuente, 'B', $tamañofuente);
    //PRIMERA PARTE ESTATICA
    //variables para la fecha
    //fecha en letras
    $meses = array("ENERO", "FEBRERO", "MARZO", "ABRIL", "MAYO", "JUNIO", "JULIO", "AGOSTO", "SEPTIEMBRE", "OCTUBRE", "NOVIEMBRE", "DICIEMBRE");
    $fechadesembolso = strtotime($datos[0]['fecdesem']);
    $dia_desembolso = new NumeroALetras();
    $dia_desembolsoaux = mb_strtolower($dia_desembolso->toWords_plus((date("d", $fechadesembolso))), 'utf-8');
    $anodesembolso = new NumeroALetras();
    $anodesembolsoaux = mb_strtolower($anodesembolso->toWords_plus((date("Y", $fechadesembolso))), 'utf-8');
    //variables para la edad del cliente
    $edadletras = new NumeroALetras();
    $edadletrasaux = mb_strtolower($edadletras->toWords_plus((calcular_edad($datos[0]['fechacumple']))));
    //variable para estado civil
    $estadocivil = (isset($datos[0]['estadocivil'])) ? (", " . mb_strtolower($datos[0]['estadocivil']) . ", ") : (" ");
    //division de dpi
    $letra_dpi = new NumeroALetras();
    $dpi_dividido = dividir_dpi($datos[0]['dpi']);
    $letra_dpi1 = mb_strtolower($letra_dpi->toWords_plus((($dpi_dividido[0]))));
    $letra_dpi2 = mb_strtolower($letra_dpi->toWords_plus((($dpi_dividido[1]))));
    $letra_dpi3 = mb_strtolower($letra_dpi->toWords_plus((($dpi_dividido[2]))));

    $montodesembolsoletras = mb_strtolower($letra_dpi->toMoney(($datos[0]['montoapro']), 2, 'quetzales', 'centavos'));
    $cuotaspalabras = mb_strtolower($letra_dpi->toWords_plus(($datos[0]['cuotas'])));

    $tasapalabras = ($letra_dpi->toWords_plus((round(($datos[0]['tasaprod'] / 12), 2))));
    $tasapalabrasanual = ($letra_dpi->toWords_plus(($datos[0]['tasaprod'])));
    $morapalabras = ($letra_dpi->toWords_plus(($datos[0]['mora'])));
    $morapalabrasanual = ($letra_dpi->toWords_plus((($datos[0]['mora'] * 12))));
    //nacionalidad 
    $nacionalidad  = (isset($datos[0]['nacionalidad'])) ? (($datos[0]['nacionalidad'] == 'GT') ? 'guatemalteco(a), ' : 'extranjero(a), ') : "";
    //Variables numero de dictamen
    $primerapartecontrato = explode('-', $datos[0]['dictamen']);
    $primerapartecontratoaux = mb_strtolower($letra_dpi->toWords_plus((($primerapartecontrato[0]))));
    //posicion de hipotecario
    $pos_hipo = array_search('2', array_column($garantias, 'tipogarantia'));

    //obtener el plan de pago
    $sumacapint = 0;
    $fecha_vence = '0000-00-00';
    if ($datos[0]['capitalppg'] == 'x' || $datos[0]['interesppg'] == 'x' || $datos[0]['capitalppg'] != 'x' || $datos[0]['interesppg'] != 'x') {
        //LLAMAR A LA FUNCION PARA CAPINT
        $datos_sum = creppg_temporal($datos[0]['ccodcta'], $conexion);
        $sumacapint = $datos_sum[0]['nintpag'] + $datos_sum[0]['ncappag'];
        $fecha_vence = $datos_sum[(count($datos_sum) - 1)]['dfecven'];
        $fecha_vence2 = strtotime($fecha_vence);
        $dia_vence = mb_strtolower($dia_desembolso->toWords_plus((date("d", $fecha_vence2))), 'utf-8');
        $ano_vence = mb_strtolower($anodesembolso->toWords_plus((date("Y", $fecha_vence2))), 'utf-8');
    } else {
        $sumacapint = $datos[0]['capitalppg'] + $datos[0]['interesppg'];
    }

    //-------------------------------------------------------------------------------------------------------
    $pdf->SetFont($fuente, 'B', $tamañofuente - 1);
    $texto = "<p>CONTRATO No. " . ($datos[0]['dictamen']) . ": En el Municipio de Comitancillo, departamento de San Marcos, el " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " comparecen por una parte el señor: <vb>PEDRO ARILIO VELÁSQUEZ LÓPEZ</vb> de treinta y siete años de edad, casado, guatemalteco, Perito Contador, con domicilio en este departamento y residencia en caserío Las Flores, del municipio de Comitancillo, departamento de San Marcos;  quien se identifica con el documento personal de identificación DPI; Código Único de Identificación: mil novecientos noventa y seis, ochenta y nueve mil setecientos nueve, mil doscientos cuatro, extendido por el Registro Nacional de las Personas, del municipio de Comitancillo, departamento de San Marcos; quien comparece en calidad de REPRESENTANTE LEGAL de la entidad CREDIMARQ, SOCIEDAD ANÓNIMA, inscrita en el Registro Mercantil General de la República de Guatemala, bajo el Registro No. 160826 Folio 561 del Libro 487 Electrónico de Sociedades Mercantiles; siendo la representación suficiente de conformidad con la ley para la celebración del presente contrato a quien en éste documento se denominará el <vb>\"ACREEDOR\"</vb> y por otra parte comparece el(la) señor(a) <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",</vb> de " . mb_strtolower($edadletrasaux) . " años de edad" . $estadocivil . $nacionalidad . "con residencia en " . (ucwords($datos[0]['direccioncliente'])) . " del municipio de " . ucwords(mb_strtolower($datos[0]['nommun'])) . ", departamento de " . ucwords(mb_strtolower($datos[0]['nomdep'])) . "; quien se identifica con el documento personal de identificación; CUI: " . $letra_dpi1 . ", " . $letra_dpi2 . ", " . $letra_dpi3 . ", extendido por el Registro Nacional de las Personas, del municipio de " . ucwords(mb_strtolower($datos[0]['nommunextiende'])) . ", departamento de " . ucwords(mb_strtolower($datos[0]['nomdepextiende'])) . ", que en el curso de este contrato se denominará simplemente <vb>DEUDOR,</vb> los dos aseguramos ser de las generales expuestas anteriormente, y hallarnos en el libre ejercicio de nuestros derechos civiles y de palabra y por eso celebramos el siguiente <vb>CONTRATO DE MUTUO, CON GARANTÍA DE DERECHOS POSESORIOS,</vb> conforme a las cláusulas siguientes: <vb>PRIMERA:</vb> El señor <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",</vb> declara que por este acto se reconoce liso y llano <vb>DEUDOR</vb> de la entidad CREDIMARQ SOCIEDAD ANONIMA, <vb>POR LA CANTIDAD DE " . mb_strtoupper($montodesembolsoletras) . " (Q " . number_format($datos[0]['montoapro'], 2, '.', ',') . ")</vb> que ha recibido en calidad de mutuo que a continuación se indica. <vb>SEGUNDA:</vb> La entidad CREDIMARQ, SOCIEDAD ANÓNIMA; por medio de su representante legal, otorga al <vb>DEUDOR,</vb> el presente crédito;  haciendo constar que de conformidad al acta número " . $primerapartecontratoaux . ", de fecha " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " del libro respectivo de la entidad CREDIMARQ, SOCIEDAD ANÓNIMA, se autorizó  dicho crédito; con el visto bueno del Plan de Inversión de Crédito de la entidad CREDIMARQ, SOCIEDAD ANÓNIMA; el presente crédito estará  bajo las condiciones siguientes: <vb>1. MONTO:</vb> El que ya quedo consignado o sea la cantidad de <vb>" . mb_strtoupper($montodesembolsoletras) . " (Q " . number_format($datos[0]['montoapro'], 2, '.', ',') . ").</vb> <vb>2. DESTINO:</vb> La suma anteriormente indicada se destinará exclusivamente para " . $datos[0]['destinocredito'] . ". <vb>3. FORMA DE DESEMBOLSO:</vb> La entidad CREDIMARQ, SOCIEDAD ANÓNIMA dará el crédito en la forma siguiente: Un solo desembolso con cheque del Banco de Desarrollo Rural BANRURAL, a nombre de CREDIMARQ, SOCIEDAD ANÓNIMA, numero del cheque cero cero cero cero cero mil cuatrocientos sesenta. <vb>4. PROVENENCIA DE LOS FONDOS:</vb> La entidad otorga el presente crédito con fondos provenientes propios de CREDIMARQ, SOCIEDAD ANÓNIMA. <vb>5. PLAZO:</vb> El plazo del presente crédito será de " . $cuotaspalabras . " meses a partir del día de hoy. <vb>6. FORMA DE PAGO:</vb> El reintegro o pago del crédito por parte del deudor será en " . $cuotaspalabras . " amortizaciones mensuales, siendo la última amortización el " . $dia_vence . " de " . mb_strtolower($meses[date("n", $fecha_vence2) - 1]) . " de " . $ano_vence . "; haciendo constar al deudor que en caso de que se pueda reintegrar el capital y el interés de la cantidad mutuada la misma podrá hacerse antes del plazo estipulado mediante acuerdo interno con CREDIMARQ, SOCIEDAD ANÓNIMA; el interés que devengará el presente crédito será del " . $tasapalabrasanual . "% anual. Los pagos que se efectúen se aplicarán en el siguiente orden: A los recargos por mora el " . $morapalabrasanual . "% anual, si lo hubiere a capital, en caso de que exista casos fortuitos o actos de hecho, naturales se le permitirá al deudor un tiempo máximo que se establezca CREDIMARQ, SOCIEDAD ANÓNIMA, esta justificación deberá de ser acreditada. <vb>7. CÓMPUTO DE INTERESES Y RECARGOS:</vb> Para el cómputo de intereses y recargos el año será de trescientos sesenta y cinco días, los meses se tomarán por sus días reales. <vb>TERCERA:</vb> Por su parte el(la) señor(a) <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",</vb> de todos sus bienes presentes y futuros, específicamente de un bien inmueble que se encuentra ubicado en " . $garantias[$pos_hipo]['direccioncliente'] . ", del municipio de " . ucwords(mb_strtolower($garantias[$pos_hipo]['nommun'])) . ", departamento de " . ucwords(mb_strtolower($garantias[$pos_hipo]['nomdep'])) . ", con " . $garantias[$pos_hipo]['idcliente'] . ", documento que se tiene a la vista y que el mismo quedara en calidad de garantía, el bien inmueble se encuentra libre de gravámenes, anotaciones o de cualquier otra anotación que perjudiquen intereses de terceras personas; y de conformidad al artículo 1464 del Código Civil su obligación se garantiza con hipoteca sobre el bien inmueble antes identificado en caso de incumplimiento y transfiere la cosa pignorada o hipotecada sobre la deuda respectiva en caso de incumplimiento, con todas sus consecuencias y modalidades,  declarando así mismo que renuncia al fuero de su domicilio y se somete a los tribunales que CREDIMARQ, SOCIEDAD ANÓNIMA elija en caso de incumplimiento de la obligación contraída. <vb>- CUARTA: DISPOSICIONES GENERALES:</vb> El(La) señor(a) <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",</vb> expresamente se obliga a lo siguiente: <vb>a)</vb> Permitir el libre acceso de los personeros de CREDIMARQ, SOCIEDAD ANONIMA para supervisar el crédito invertido ya sea en visitas de rutina o en comisiones específicas que demanden el presente crédito. <vb>- b)</vb> La entidad CREDIMARQ, SOCIEDAD ANÓNIMA podrá supervisar las operaciones de este crédito con el objeto de constatar el buen uso de los recursos del préstamo. <vb>- c)</vb> Rendir en un tiempo prudencial la información que CREDIMARQ, SOCIEDAD ANÓNIMA pudiera requerir, relacionada con el préstamo otorgado. <vb>- d)</vb> Se conviene expresamente que todo pago tanto de capital como de intereses, serán efectuadas por la parte deudora sin necesidad de cobro o requerimiento alguno, en quetzales, moneda de curso legal en la oficina de CREDIMARQ, SOCIEDAD ANÓNIMA, la cual es conocida perfectamente por la parte deudora. <vb>- e)</vb> La entidad CREDIMARQ, SOCIEDAD ANÓNIMA podrá dar por vencido el plazo del préstamo y exigir el cumplimiento de la obligación en juicio ejecutivo, en los casos siguientes: <vb>I)</vb> Si la parte deudora diera a los fondos un destino diferente al pactado; <vb>II)</vb> Si incurriera en mora; <vb>III)</vb> Si se incumpliere cualquiera de las obligaciones que se asume en este contrato o violare las prohibiciones consignadas en el mismo. Y en estos casos desde ya acepta como buenas exactas de plazo vencido, líquidos y exigibles, las cantidades, que se  demanden. <vb>- QUINTA:</vb> El(La) señor(a) <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",</vb> declara que en forma expresa acepta el presente contrato a su favor  y renuncia al fuero de su domicilio y se sujeta a los tribunales que CREDIMARQ, SOCIEDAD ANÓNIMA elija en caso de incumplimiento al presente contrato de mutuo (préstamo) y señala como lugar para recibir notificaciones en su residencia situada en " . (ucwords($datos[0]['direccioncliente'])) . " del municipio de " . ucwords(mb_strtolower($datos[0]['nommun'])) . ", departamento de " . ucwords(mb_strtolower($datos[0]['nomdep'])) . ". <vb>SEXTA:</vb> Los comparecientes, declaran que después de estar informados de su contenido, valor, objetivo, costos legales, los términos consignados y en las calidades que comparecen, aceptan y firman el presente contrato.</p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);

    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'DEUDOR(A)', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'REPRESENTANTE LEGAL', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(10);

    //PARTE 2 DEL DOCUMENTO
    $texto = "<p><vb>ACTA DE LEGALIZACIÓN DE FIRMAS:</vb> En el municipio de Comitancillo, departamento de San Marcos, el día " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . ", el infrascrito notario da fe: Que las firmas que anteceden son <vb>AUTENTICAS,</vb> en virtud de haber sido puestas el día de hoy y en mi presencia por los señores: <vb>PEDRO ARILIO VELÁSQUEZ LÓPEZ,</vb> quien se identifica con el documento personal de identificación DPI; Código Único de Identificación: Un mil novecientos noventa y seis, ochenta y nueve mil setecientos nueve,  un mil doscientos cuatro, extendido por el Registro Nacional de las Personas, del municipio de Comitancillo, departamento de San Marcos; quien comparece en calidad de REPRESENTANTE LEGAL de la entidad CREDIMARQ, SOCIEDAD ANÓNIMA, así mismo el signatario <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",</vb> quien se identifica con el documento personal de identificación; CUI: " . $letra_dpi1 . ", " . $letra_dpi2 . ", " . $letra_dpi3 . ", extendido por el Registro Nacional de las Personas, del municipio " . ucwords(mb_strtolower($datos[0]['nommunextiende'])) . ", departamento de " . ucwords(mb_strtolower($datos[0]['nomdepextiende'])) . "; personas que firman nuevamente conmigo al final de la presente acta de legalización DOY FE.</p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);

    // LIGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'DEUDOR(A)', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'REPRESENTANTE LEGAL', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(15);

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