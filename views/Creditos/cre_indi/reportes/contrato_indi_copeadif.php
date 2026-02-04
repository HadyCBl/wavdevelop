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
$fiador_exit = array_search('1', array_column($garantias, 'tipogarantia'));

if ($fiador_exit === false) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No existe un fiador, el formato requiere por lo menos un fiador.',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

   //BUSCAR DATOS DE GARANTIAS
$strquery = "SELECT tc.short_name AS nomcli, tc.date_birth AS fechacumple, tc.estado_civil AS estadocivil, tc.profesion AS profesion, tc.no_identifica AS dpi FROM tb_cliente tc WHERE tc.idcod_cliente = '" . $garantias[$fiador_exit]['idcliente'] . "'";
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
// $opResult = array(
//     'status' => 0,
//     'mensaje' => 'IS SUCEFUL '.$codcredito,
//     'dato' => $strquery
// );
// echo json_encode($opResult);
// return;
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
            $this->Cell(0, 3, 'TEL: ' . $this->telefono, 0, 1, 'C');
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
    $pdf->SetFont($fuente, 'B', $tamañofuente + 3);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('RECONOCIMIENTO DE DEUDA CON GARANTÍA FIDUCIARIA EN DOCUMENTO PRIVADO POR '), 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea+100, $tamanio_linea, decode_utf8(' Q' . number_format($datos[0]['montoapro'], 2, '.', ',') ), 0, 0, 'L', 0, '', 1, 0);

    $pdf->SetFont($fuente, 'I', $tamañofuente + 3);
    $pdf->CellFit($ancho_linea+20, $tamanio_linea, decode_utf8(' Contrato No. ' . $datos[0]['dictamen']), 0, 0, 'L', 0, '', 1, 0);


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
    $letra_dpi = new NumeroALetras();
    $dpi_dividido = dividir_dpi($datos[0]['dpi']);
    $letra_dpi1 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[0]))));
    $letra_dpi2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[1]))));
    $letra_dpi3 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[2]))));

    $dpi_fiador = ($clientefiador[0]['dpi']);
    $dpi_dividido2 = dividir_dpi($clientefiador[0]['dpi']);
    $letra_dpi1_2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido2[0]))));
    $letra_dpi2_2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido2[1]))));
    $letra_dpi3_2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido2[2]))));


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
    // $primerapartecontratoaux = mb_strtolower($letra_dpi->toWords_plus((($primerapartecontrato[0]))));
    //posicion de hipotecario
    $pos_hipo = array_search('2', array_column($garantias, 'tipogarantia'));
    $edadletrasaux2 = mb_strtolower($edadletras->toWords((calcular_edad($clientefiador[0]['fechacumple']))));
    $estadocivil2 = (isset($clientefiador[0]['estadocivil'])) ? (", " . mb_strtolower($clientefiador[0]['estadocivil']) . ", ") : (" ");
    $dpi_dividido2 = dividir_dpi($clientefiador[0]['dpi']);
    $clifiado = strtoupper($clientefiador[0]['nomcli']);
    
    $textosegunda="";
    $pos_fiador=array_search('1', array_column($garantias,'fiador'));

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
    $pdf->SetFont($fuente, 'B', $tamañofuente );
    $texto = "<p> Yo <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",</vb>  de " . mb_strtolower($edadletrasaux) . " años de edad" . $estadocivil . $nacionalidad . "con dirección en  " . (ucwords($datos[0]['direccioncliente'])) . " del municipio de " . ucwords(mb_strtolower($datos[0]['nommun'])) . ", Departamento de " . ucwords(mb_strtolower($datos[0]['nomdep'])) . "; quien se identifica con el documento personal de identificación; CUI: " . $letra_dpi1 . ", " . $letra_dpi2 . ", " . $letra_dpi3 . ", extendido por el Registro Nacional de las Personas -RENAP- del municipio de " . ucwords(mb_strtolower($datos[0]['nommunextiende'])) . ", departamento de " . ucwords(mb_strtolower($datos[0]['nomdepextiende'])) . "; en el libre goce y ejercicio de mis derechos civiles, verbalmente y en idioma español, por este medio hago constar que me reconozco liso y llano deudora por la suma de  <vb>" . mb_strtoupper($montodesembolsoletras) . " (Q " . number_format($datos[0]['montoapro'], 2, '.', ',') . ")de la COOPERATIVA INTEGRAL DE AHORRO Y CRÉDITO ALIANZA PARA EL DESARROLLO E INVERSIÓN FONDO RURAL RESPONSABILIDAD LIMITADA</vb>, suma de dinero que recibi el día de hoy, en moneda de curso legal, en calidad de mutuo y que cancelaré bajo las siguientes clausulas:<vb>PRIMERA CONDICIONES A)</vb>Yo <vb>" . mb_strtoupper($datos[0]['nomcli']) . ",QUE SE ME HA OTORGADO EL PRESENTE CREDITO EN CALIDAD DE MUTUO, COMO ASOCIADO </vb> de la <vb>Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada. </vb>Préstamo que fue aprobado en el acta número veintidós guion dos veintiuno (22-2021) Punto Tercero, de fecha treinta de Marzo del año dos Mil veintiuno, según Resolución del Comité gerencial de créditos de la Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada; con FONDOS-PROPIOS ADIF del Banco BANRURAL, por el monto de (Q " . number_format($datos[0]['montoapro']) . ") <vb>B). PLAZO: </vb>El plazo del crédito es de <vb>" . $cuotaspalabras . "</vb> meses contados a partir de la fecha de la realización del presente contrato. <vb>C). INTERESES:</vb> Sobre el capital mutuado, se pagará el Cinco por ciento (5%) anual por concepto de intereses, dieciocho por ciento (18%) anual por concepto gastos de operación; y el veintidós por ciento (22%) anual por comisión manejo de cuenta, siendo una tasa fija. <vb>D). AMORTIZACIONES: </vb> Se efectuarán DOS pagos mensuales de capital de <vb>UN QUETZAL EXACTOS (Q.1.00) Y al tercer mes, se cancelará el total del capital más un mes de intereses.</vb> <vb>E). OTRAS CUOTAS:</vb> En concepto de cuotas extraordinaria por atraso en pagos, se pagará sesenta quetzales (Q. 60.00) mensual sobre el capital no pagado, a partir del tercer día del vencimiento de las cuotas; y después del vencimiento del presente contrato, sea por vencer el plazo o por vencimiento anticipado, se pagará noventa quetzales (Q.90.00) mensual, a partir de la última operación o pago realizado. <vb>F). LUGAR Y FORMADE PAGO: </vb> El pago del capital, intereses y otras cuotas por atrasos en pagos, se harán en las oficinas de la Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada, sin necesidad de cobros o requerimiento alguno en horas hábiles, en moneda del curso legal, en las fechas de pago convenidos. <vb>G)</vb> En garantía al fiel y estricto cumplimiento de la presente obligación dejo en garantía mis bienes presentes y futuros, así también la garantizo con fiador solidario y mancomunado, siendo  <vb>" . $clifiado . "</vb> de " . calcular_edad($clientefiador[0]['fechacumple']) . " años de edad" . $estadocivil2 . " con residencia en " . (ucwords($garantias[$pos_fiador]['direccioncliente'])) . " del municipio de " . mb_strtolower(ucwords($garantias[$pos_fiador]['nommun'])) . ", " . mb_strtolower(ucwords($garantias[$pos_fiador]['nomdep'])) . ". quien se identifica con Documento Personal de Identificación DPI, Código Unico de Identificación CUI, Número " . $letra_dpi1_2 . " espacio " . $letra_dpi2_2 . " espacio " . $letra_dpi3_2 . " (" . $dpi_dividido2[0] . " " . $dpi_dividido2[1] . " " . $dpi_dividido2[2] . "), extendido por el Registro Nacional de las Personas -RENAP- quien se constituye como fiador principal de la Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada. <vb>H). FUERO DE DOMICILIO: NOSOTROS:</vb> " . mb_strtoupper($datos[0]['nomcli']) . " Y " . $clifiado . " RENUNCIAMOS EXPRESAMENTE AL FUERO DE NUESTRO DOMICILIO Y NOS SOMETEMOS EXPRESAMENTE A LOS TRIBUNALES DE JUSTICIA QUE ELIJA LA MENCIONADA ASOCIACION, PAGANDO COSTAS JUDICIALES RELEVANDO A LA ACREEDORA DE LA OBLIGACION DE PRESENTAR FIANZAS EN LOS CASOS EN QUE LA LEY SEÑALE,SIENDO TITULO EJECUTIVO EL PRESENTE CONTRATO. Además " . mb_strtoupper($datos[0]['nomcli']) . " Y " . $clifiado . " , señalamos desde ya como lugar para recibir notificaciones nuestra casa de habitación ubicada en  " . (ucwords($datos[0]['direccioncliente'])) . " del municipio de " . ucwords(mb_strtolower($datos[0]['nommun'])) . ", Departamento de " . ucwords(mb_strtolower($datos[0]['nomdep'])) . " , toda vez que somos ampliamente conocidos en dicho lugar y que allí se nos tengan por válidas y bien hechas las notificaciones, citaciones y emplazamientos que del contrato realizado con el acreedor se deriven. <vb>H). CLAUSULA RESOLUTIVA: </vb> Por el incumplimiento de una cuota o amortización de capital e intereses se tiene por vencido anticipadamente el presente contrato, por lo que la Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada, puede proceder al cobro de la totalidad del capital y los intereses estipulados en el presente contrato por vía judicial. <vb>I). LOS CARGOS E INTERESES</vb>, podrán variar durante la vigencia dl presente contrato, pero en todo caso la Junta Directiva de la Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada, dará aviso con un mes de anticipación al cambio de intereses y recargos. <vb>SEGUNDA: </vb> Yo, " . mb_strtoupper($datos[0]['nomcli']) . " me comprometo de manera expresa a permitir el acceso a los personeros de la Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada, para supervisar la correcta inversión de crédito, el cual debe ser utilizado para el destino solicitado, ya que en caso contrario, la mencionada Cooperativa dará por vencido el plazo y procederá a recuperar el crédito inmediatamente, por cualesquiera de las vías, judiciales o extrajudiciales y todos los gastos judiciales o extrajudiciales corren a cargo de la deudora.<vb> TERCERA: </vb>Manifiestan de forma expresa los comparecientes que el incumplimiento de las cláusulas y estipulaciones anteriores de este contrato por parte de la <vb>DEUDORA Y FIADORA</vb>, dará derecho a la Cooperativa Integral de Ahorro y Crédito Alianza para el Desarrollo e Inversión Fondo Rural Responsabilidad Limitada a dar por rescindido el contrato de mutuo que se realiza en este instrumento; y a cobrar judicialmente el saldo que hubiere en dicha fecha más costas judiciales o extrajudiciales. <vb>CUARTA: DE LA ACEPTACIÓN: </vb> Previa lectura integra a lo escrito, bien enterados de su contenido, objeto, validez y demás efectos legales correspondientes, lo acepto, ratifico y firmo, así también lo acepta, ratifica y firma mi fiador solidario y mancomunado en aceptación, " . $clifiado . " quien se encuentra bien enterado del cargo recaído a su persona. En la Ciudad de Totonicapán el día " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " (" . date('d-m-Y', strtotime($datos[0]['fecdesem'])) . ").</p>";
    
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);

    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, mb_strtoupper($datos[0]['nomcli']), 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, $clifiado, 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(3);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'DPI: ' . mb_strtoupper($datos[0]['dpi'], 'UTF-8'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'DPI: ' . $dpi_fiador, 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(3);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'Deudor (a)' , 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'Fiador (a)' ,0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(10);

    //PARTE 2 DEL DOCUMENTO
    $texto = "<p>  </p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(80);

    // LIGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, mb_strtoupper($datos[0]['nomcli']), 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, $clifiado, 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(3);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'DPI: ' . mb_strtoupper($datos[0]['dpi'], 'UTF-8'), 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'DPI: ' . $dpi_fiador, 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(3);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'Deudor (a)' , 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, 'Fiador (a)' ,0, 0, 'C', 0, '', 1, 0);
  
    $pdf->Ln(15);
    $pdf->SetFont($fuente, 'B', $tamañofuente + 5);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('ANTE MI'), 0, 0, 'C', 0, '', 1, 0);

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