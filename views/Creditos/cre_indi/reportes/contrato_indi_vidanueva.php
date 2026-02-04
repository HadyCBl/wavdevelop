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

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3];
$tipo = $_POST["tipo"];
$codcredito = $archivo[0];

// //SE CARGAN LOS DATOS
$strquery = "SELECT cm.CCODCTA AS ccodcta, cm.DFecDsbls AS fecdesem, cm.TipoEnti AS formcredito, dest.DestinoCredito AS destinocred, cm.NCapDes AS montodesem, cm.noPeriodo AS cuotas, tbp.nombre frecuencia, cm.Dictamen AS dictamen,
    cl.idcod_cliente AS codcli, cl.short_name AS nomcli,cl.no_identifica numdpi, cl.Direccion AS direccioncliente,cl.date_birth,cl.estado_civil,cl.profesion,
    cm.NIntApro AS tasaprod,
    (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id = cl.depa_reside),'-')) AS nomdep,
    (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id = cl.id_muni_reside),'-')) AS nommun,
    (IFNULL((SELECT sum(ncapita)+sum(nintere) FROM Cre_ppg WHERE ccodcta = cm.CCODCTA),0)) AS moncuota,
    (IFNULL((SELECT sum(OTR) FROM CREDKAR WHERE CCODCTA = cm.CCODCTA AND CTIPPAG='D' AND CESTADO!='X'),0)) AS mongasto
    FROM cremcre_meta cm
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN $db_name_general.tb_destinocredito dest ON cm.Cdescre=dest.id_DestinoCredito
    INNER JOIN $db_name_general.tb_periodo tbp ON cm.NtipPerC=tbp.periodo
    WHERE cm.Cestado='F' AND cm.CCODCTA='$codcredito' GROUP BY CCODCTA";
$query = mysqli_query($conexion, $strquery);
$data[] = [];
$j = 0;
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $data[$j] = $fila;
    $j++;
}

// //BUSCAR DATOS DE GARANTIAS
$strquery = "SELECT clig.* FROM tb_garantias_creditos tgc
    INNER JOIN cli_garantia clig ON clig.idGarantia=tgc.id_garantia
    WHERE tgc.id_cremcre_meta='$codcredito' AND clig.idTipoGa IN (1,3,12);";
$query = mysqli_query($conexion, $strquery);
$j = 0;
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $garantias[$j] = $fila;
    $j++;
}

if (!isset($garantias)) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron garantias vinculadas con el crédito'
        // 'mensaje' => $strquery
    );
    echo json_encode($opResult);
    return;
}

$indexprendaria = array_search(3, array_column($garantias, 'idTipoGa'));
$indicepersonal = array_search(1, array_column($garantias, 'idTipoGa'));

if (is_bool($indexprendaria)) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No hay garantias prendarias'
    );
    echo json_encode($opResult);
    return;
}

$gprendaria = $garantias[$indexprendaria];

if (!is_bool($indicepersonal)) {
    $strquery = "SELECT cl.*,
    (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id = cl.depa_reside),'-')) AS nomdep,
    (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id = cl.id_muni_reside),'-')) AS nommun
    FROM tb_cliente cl WHERE cl.idcod_cliente='" . $garantias[$indicepersonal]['descripcionGarantia'] . "';";
    $query = mysqli_query($conexion, $strquery);
    // $datosfiador[] = [];
    $j = 0;
    while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
        $datosfiador[$j] = $fila;
        $j++;
    }
}
$gfiador = (!isset($datosfiador)) ? null : $datosfiador[0];

// if (!isset($datosfiador)) {
//     $opResult = array(
//         'status' => 0,
//         'mensaje' => 'No se encontró el fiador'
//     );
//     echo json_encode($opResult);
//     return;
// }


// //BUSCAR DATOS DE INSTITUCION
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
while ($fil = mysqli_fetch_array($queryins, MYSQLI_ASSOC)) {
    $info[$j] = $fil;
    $j++;
}

//COMPROBACION: SI SE ENCONTRARON REGISTROS
// if (!$flag || !$flag2 || !$flag4) {
//     $opResult = array(
//         'status' => 0,
//         'mensaje' => 'No se encontraron datos, o no se cargaron algunos datos correctamente, intente nuevamente' . $flag . "f2" . $flag2 . "f3" . $flag3 . "f4" . $flag4,
//         'dato' => $strquery
//     );
//     echo json_encode($opResult);
//     return;
// }

printpdf($info, $data, $gprendaria, $gfiador);

function printpdf($info, $data, $gprendaria, $gfiador)
{

    //FIN COMPROBACION
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
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

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 12);
            $this->Cell(0, 10, 'CONTRATO MUTUO CON GARANTIA', 'T', 1, 'C');
            $this->Cell(0, 10, 'PRENDARIA', 'B', 1, 'C');
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
    $tamanofuente = 10;
    $pdf->SetMargins(30, 25, 25);
    $pdf->SetFont($fuente, '', $tamanofuente);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    // Stylesheet

    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("h1", $fuente, "N", 10, "0,0,0", 0);
    $pdf->SetStyle("a", $fuente, "BU", 10, "0,0,0");
    $pdf->SetStyle("pers", $fuente, "I", 0, "0,0,0");
    $pdf->SetStyle("place", $fuente, "U", 0, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", 0, "0,0,0");

    //DATOS INSTITUCION REPRESENTANTE LEGAL
    $reprlegal = "EVELYN ROSEMARY ESQUIT CHÁVEZ DE SANTOS";
    $dpirepr = "3265521851601";
    $dpireprletra = dpiletra($dpirepr);
    $dpirepr = dpiformat($dpirepr);

    //DATOS CLIENTE
    $nombrecliente = $data[0]["nomcli"];
    $dpi = $data[0]["numdpi"];
    $dpiletras = dpiletra($dpi);
    $dpi = dpiformat($dpi);
    $direccion = $data[0]["direccioncliente"];
    $fechanacimiento = $data[0]["date_birth"];
    $edad = calcular_edad($fechanacimiento);
    $edadletra = numtoletras($edad);

    $estadocivil = $data[0]["estado_civil"];
    $profesion = $data[0]["profesion"];
    $depadomicilio = $data[0]["nomdep"];


    //DATOS CREDITO
    $destinocredito = $data[0]["destinocred"];
    $frecuencia = $data[0]["frecuencia"];
    $nocuotas = $data[0]["cuotas"];
    $nocuotasletra = numtoletras($nocuotas);
    $moncuotas = round($data[0]["moncuota"]);
    $decimal = explode(".", $moncuotas);
    $res = isset($decimal[1]) ? " con " . $decimal[1] . "/100" : "";
    $mondecimal = numtoletras($decimal[0]);
    $moncuotasletra = $mondecimal . " " . $res;

    $montogasto = round($data[0]["mongasto"]);
    $decimal = explode(".", $montogasto);
    $res = isset($decimal[1]) ? " con " . $decimal[1] . "/100" : "";
    $mondecimal = numtoletras($decimal[0]);
    $gastoletra = $mondecimal . " " . $res;

    $fechadesembolso = $data[0]["fecdesem"];
    $fechaletras = fechaletras($fechadesembolso);

    $monto = round($data[0]['montodesem'], 2);
    $decimal = explode(".", $monto);
    $res = isset($decimal[1]) ? " con " . $decimal[1] . "/100" : "";
    $mondecimal = numtoletras($decimal[0]);
    $montoletras = $mondecimal . " " . $res;
    $plazo = plazos($frecuencia);

    //GARANTIAS
    $descripciongarantia = $gprendaria["descripcionGarantia"];
    // $text = '“LA PARTE ACREEDORA”';
    // $text = convert_smart_quotes_to_regular($text);
    // $pdf->WriteTag(0, 5, $text, 0, "J", 0, 0);

    //PARTE INICIAL
    $texto = limpiar(introduccion($nombrecliente, $dpi, $dpiletras, $reprlegal, $dpireprletra, $dpirepr, $edadletra, $estadocivil, $profesion, $depadomicilio));
    $texto .= limpiar(puntos123($montoletras, $monto, $frecuencia, $destinocredito, $montogasto, $gastoletra));
    $texto .= limpiar(puntos45($direccion, $plazo, $nocuotasletra, $nocuotas, $moncuotas, $moncuotasletra, $descripciongarantia, $frecuencia));
    $texto .= limpiar(puntos6());
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(6);

    $pdf->firmas(2, [$reprlegal, $nombrecliente]);
    $pdf->Ln(25);

    //PARTE FINAL
    $texto = limpiar(puntofinal($nombrecliente, $reprlegal, $dpireprletra, $dpirepr, $fechaletras, $dpiletras, $dpi));
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(6);
    $pdf->firmas(2, [$reprlegal, $nombrecliente]);
    // $pdf->Ln(25);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Contrato-",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
function convert_smart_quotes_to_regular($string)
{
    return html_entity_decode(preg_replace('/[’"`]/', "\"", $string), ENT_QUOTES, 'UTF-8');
}
function introduccion($name, $dpi, $dpiletra, $reprlegal, $dpireprletra, $dpirepr, $edadletra, $estadocivil, $profesion, $depadomicilio)
{
    $data = decode_utf8('<p>En la ciudad de Guatemala departamento de Guatemala, NOSOTROS <vb>A) ' . $reprlegal . ', </vb>');
    $data .= decode_utf8('de cincuenta y cinco años de edad, casada, guatemalteca, administradora, con domicilio en el departamento de Guatemala, me identifico con el
    Documento Personal de Identificación (DPI) con Código Único de Identificación (CUI), ' . $dpireprletra . ' (' . $dpirepr . ') extendido por el Registro Nacional de las Personas ') .' -RENAP-'.decode_utf8( ' de la República de Guatemala, 
    Centroamérica, actúo en mi calidad de DIRECTOR EJECUTIVO Y REPRESENTANTE LEGAL de la entidad denominada "ASOCIACIÓN VIDA NUEVA", calidad que 
    acredito con la certificación del acta de nombramiento inscrita bajo la partida número  TRESCIENTOS SESENTA Y SEIS (366), folio TRESCIENTOS SESENTA 
    Y SEIS (366),  del libro CUARENTA Y NUEVE (49) del Sistema Único del Registro Electrónico de Personas Jurídicas. En lo sucesivo del presente 
    contrato se me podrá denominar indistintamente como "LA PARTE ACREEDORA"; y ');
    $data .= decode_utf8('<vb>B)' . $name . '</vb> , de ' . $edadletra . ' años, ' . $estadocivil . ', guatemalteco, ' . $profesion . ', con domicilio en el departamento de 
    ' . $depadomicilio . ', me identifico con el Documento Personal de Identificación (DPI) con Código Único de Identificación (CUI), número ' . $dpiletra . ', (' . $dpi . ') extendido por el Registro Nacional de las 
    Personas') .' -RENAP-'.decode_utf8( ' de la República de Guatemala Centroamérica, en lo sucesivo del presente acto se me podrá denominar indistintamente como 
    "LA PARTE DEUDORA". Los comparecientes aseguramos ser de los datos de identificación personal y calidad acreditada, así como hallarnos en el libre 
    ejercicio de nuestros derechos civiles, por lo que por el presente acto convenimos en celebrar CONTRATO DE MUTUO CON GARANTÍA PRENDARIA de 
    conformidad con las siguientes cláusulas: ');
    // $data .= '</p>';
    return $data;
}
function puntos123($montoletra, $monto, $frecuencia, $destinocredito, $montogasto, $gastoletra)
{
    $data = decode_utf8('<vb>PRIMERA.</vb> Por así convenirme yo, <vb>LA PARTE DEUDORA</vb> recibo para mi beneficio a título de MUTUO, de parte de la entidad ');
    $data .= decode_utf8('<vb>ASOCIACIÓN VIDA NUEVA</vb> la suma de ' . $montoletra . ',(' . $monto . '). <vb>SEGUNDA.</vb> Yo, <vb>LA PARTE DEUDORA, </vb>');
    $data .= decode_utf8('recibo la ya referida cantidad, con la obligación de invertirla única y exclusivamente para ' . $destinocredito . ', entendiendo que dentro de un plazo de DIEZ (10) días debo demostrar a <vb>LA PARTE ACREEDORA</vb> con la documentación respectiva que el dinero que 
    recibo en calidad de MUTUO se ha invertido en el proyecto anteriormente descrito.');
    $data .= decode_utf8('<vb>TERCERA: A) INTERESES:</vb>Ambas partes convenimos en el hecho que la suma de dinero entregada en calidad de mutuo, devengará un interés mensual 
    variable, el cual inicialmente se establece en DOS PUNTO CINCO POR CIENTO (2.5%) sobre el monto inicial, pagadero <vb>' . $frecuencia . '</vb>. La tasa fijada inicialmente 
    en ninguna forma podrá entenderse como tasa pactada o fija toda vez que la variabilidad de la misma podrá exceder a esta fijación inicial según le 
    comunique por escrito <vb>LA PARTE ACREEDORA</vb> a <vb>LA PARTE DEUDORA</vb> con por lo menos cinco (5) días calendario de anticipación a la fecha de pago. 
    <vb>B) DE LA MORA:</vb> Si <vb>LA PARTE DEUDORA</vb> no realiza el pago total de la cuota acumulada mensual, dentro de los primeros cinco (5) días de cada mes, 
    <vb>LA PARTE ACREEDORA</vb>, cobrará a <vb>LA PARTE DEUDORA</vb> en concepto de penalización por mora una tasa de interés variable del <vb>CINCO POR CIENTO (5%).  
    LA PARTE DEUDORA</vb> reconoce que, por el pago de esta penalización, no se entiende extinguida la obligación principal. Este recargo por mora, tendrá 
    preferencia al pago de capital e intereses, en las amortizaciones realizadas al crédito. <vb>C) GASTOS ADMINISTRATIVOS:</vb> Aunado a lo anterior, 
    <vb>LA PARTE DEUDORA</vb>, acepta expresamente que <vb>LA PARTE ACREEDORA</vb> cobre en forma directa y anticipada del monto del presente crédito la cantidad de 
    ' . $gastoletra . ' (Q ' . $montogasto . ') de gastos de administración, en concepto de apertura y manejo del mismo. Ambas partes acordamos que, en 
    caso de la falta de pago de cuatro de las cuotas consecutivas, se dará por vencido el plazo y se podrá demandar por la vía judicial a 
    <vb>LA PARTE DEUDORA</vb> para exigir el total cumplimiento del saldo adeudado, intereses, gastos y costos procesales. ');
    return $data;
}
function puntos45($direccioncliente, $plazo, $cuotas, $nocuotas, $moncuotas, $moncuotasletra, $descripciongarantia, $frecuencia)
{
    $data = decode_utf8('<vb>CUARTA: Yo, LA PARTE DEUDORA</vb> me obligo por este instrumento público a cancelar la suma mutuada en el plazo de 
    ' . $cuotas . ' ' . $plazo . ', que se contarán a partir de la presente fecha. La forma en la que será cancelada dicha suma es por medio de 
    ' . $cuotas . ' (' . $nocuotas . ') amortizaciones ' . $frecuencia . ', niveladas, sucesivas y consecutivas de ' . $moncuotasletra . ' (Q ' . $moncuotas . '), 
    cantidad que comprende, capital, intereses y gastos administrativos. El pago de las amortizaciones, capital, intereses e intereses moratorios, se 
    efectuará libre de impuestos, descuentos, retenciones o recargos presentes y futuros en cualquiera de las agencias del BANCO INTERNACIONAL, 
    SOCIEDAD ANÓNIMA (INTERBANCO) ubicadas en todo el territorio de la República de Guatemala sin necesidad de cobro o requerimiento alguno.');

    $data .= decode_utf8('<vb>LA PARTE DEUDORA </vb> deberá presentarse a las oficinas de la entidad <vb>ASOCIACIÓN VIDA NUEVA</vb>,');
    $data .= decode_utf8('ubicadas en la veintinueve (29) calle, siete guion cuarenta y dos, (7-42), zona tres (3) del Municipio y departamento de 
    Guatemala, para presentar la boleta de depósito o enviarlas a través de la aplicación denominada WhatsApp a los siguientes números de teléfono 
    institucionales cuatro mil seiscientos noventa y cinco, siete mil trescientos treinta y cinco (4695-7335) y/o cuatro mil cuatrocientos 
    noventa y dos, cinco mil ochocientos veinticinco (4492 5825). <vb>QUINTA.</vb> Que para los efectos del presente instrumento, yo, 
    <vb>LA PARTE DEUDORA</vb>, bajo juramento de decir la verdad y enterada de las penas relativas al delito de perjurio, manifiesto bajo juramento 
    que garantizo de manera 
    expresa el cumplimiento de la obligación que por este instrumento adquiero, con torno el cual declaro es de mi propiedad, mismo que puede 
    distinguirse por las siguientes características ' . $descripciongarantia . '. Al mismo tiempo, yo, <vb>LA PARTE DEUDORA</vb> renuncio 
    expresa y voluntariamente a todo derecho que la ley de la República de Guatemala me pueda conceder para oponerme o evadir el cumplimiento del 
    presente contrato de mutuo con garantía prendaria, a fin que la <vb>PARTE ACREEDORA</vb> pueda obtener el pago de la suma mutuada, más gastos de 
    tramitación, intereses y recargos de interés por mora, que la <vb>PARTE ACREEDORA</vb> presente para su reclamación; así mismo, renuncio al fuero de mi 
    domicilio y me someto al órgano jurisdiccional que LA <vb>PARTE ACREEDORA</vb> elija para demandar el cumplimiento del presente contrato, señalando 
    como lugar para recibir notificaciones, emplazamientos, citaciones, avisos y/o diligencias en la ' . $direccioncliente . ', teniendo por válidas 
    y bien hechas las notificaciones, comunicaciones y/o citaciones que en dicho lugar me hicieren, en caso no se comunique a la entidad 
    <vb>ASOCIACIÓN VIDA NUEVA</vb> del cambio de dicha dirección.');
    return $data;
}
function puntos6()
{
    $data = decode_utf8('<vb>SEXTA: </vb> La <vb>PARTE ACREEDORA, </vb>a través de su representante legal, <vb>ACEPTA</vb> expresamente la garantía prendaria 
    que en el presente acto hace <vb>LA PARTE DEUDORA</vb>, así como las condiciones establecidas en el presente contrato para el cumplimiento de la obligación 
    por ella adquirida y a su vez <vb>AUTORIZA</vb> que <vb>LA PARTE DEUDORA</vb>, conserve en su poder el bien pignorado, siendo éste última responsable en caso de 
    pérdida o deterioro del bien dado en garantía, quedando terminantemente prohibido a <vb>LA PARTE DEUDORA</vb> su venta o cesión hasta el total cumplimiento 
    de las obligaciones que por este contrato adquiere para con <vb>LA PARTE ACREEDORA</vb>. Los otorgantes aceptamos el contenido íntegro del presente 
    contrato, el cual es leído íntegramente por ambas partes y enteradas de su contenido, objeto,  validez y efectos legales, lo aceptamos, 
    ratificamos y firmamos, colocando <vb>LA PARTE DEUDORA</vb>, además de su firma, la impresión dactilar de su dedo pulgar de la mano derecha.</p>');
    return $data;
}
function puntofinal($nombrecliente, $reprlegal, $dpireprletra, $dpirepr, $fechaletras, $dpiletras, $dpi)
{
    $data = decode_utf8('<p>En la ciudad de Guatemala, el día ' . $fechaletras . ', como Notario 
    DOY FE que las firmas que anteceden son <vb>AUTÉNTICAS</vb>, por haber sido signadas el día de hoy en mi presencia por el/la señor(a) 
    <vb>' . $nombrecliente . '</vb>, quien se identifica con Documento Personal de Identificación con Código Único de Identificación (CUI), 
    número  ' . $dpiletras . ' (' . $dpi . ') 
    extendido por el Registro Nacional de las Personas ') .' -RENAP-'.decode_utf8( ' de la República de Guatemala, Centroamérica; y por la señora <vb>
    ' . $reprlegal . '</vb>, me identifico con el  Documento Personal de Identificación (DPI) con Código Único de Identificación (CUI), 
    número ' . $dpireprletra . ' (' . $dpirepr . ') extendido por el Registro Nacional de las Personas ') .' -RENAP-'.decode_utf8( ' de la República de');

    $data .= decode_utf8('Guatemala, Centroamérica, actúo en mi calidad de DIRECTOR EJECUTIVO Y REPRESENTANTE LEGAL de la entidad denominada 
    "ASOCIACIÓN VIDA NUEVA", calidad que acredito con la certificación del acta de nombramiento inscrita bajo la partida número  
    TRESCIENTOS SESENTA Y SEIS (366), folio TRESCIENTOS SESENTA Y SEIS (366), del libro UNO (1) del Sistema Único del Registro 
    Electrónico de Personas Jurídicas. Las firmas que se legalizan calzan al final de un <vb>CONTRATO DE MUTUO CON GARANTÍA PRENDARIA</vb> 
    celebrado en documento privado, en ésta misma fecha, contenido en dos hojas de papel bond tamaño Oficio impresas de ambos lados, 
    más la presente hoja útil en su lado anverso en la que consta el acta de legalización de firmas, las cuales numero, sello y firmo; 
    para su legalización, los signatarios firman nuevamente la presente acta junto con el infrascrito Notario, adhiriendo los timbres de ley. DOY FE.</p>');
    return $data;
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

function dpiletra($numdpi)
{
    $texto = preg_replace('/\s+/', '', $numdpi);
    $parte1 = substr($texto, 0, 4);
    $parte2 = substr($texto, 4, 5);
    $parte3 = substr($texto, 9, 4);
    $letra_dpi1 = numtoletras($parte1);
    $letra_dpi2 = numtoletras($parte2);
    $letra_dpi3 = numtoletras($parte3);
    $resultado = utf8_encode("{$letra_dpi1}, {$letra_dpi2}, {$letra_dpi3}");
    return $resultado;
}
function dpiformat($numdpi)
{
    $texto = preg_replace('/\s+/', '', $numdpi);
    $parte1 = substr($texto, 0, 4);
    $parte2 = substr($texto, 4, 5);
    $parte3 = substr($texto, 9, 4);
    $resultado = ("{$parte1} {$parte2} {$parte3}");
    return $resultado;
}
function numtoletras($numero)
{
    $letra = new NumeroALetras();
    $letra_d = mb_strtolower($letra->toWords(intval(trim($numero))));
    return $letra_d;
}
function plazos($plazos)
{
    $result = "Meses";
    switch ($plazos) {
        case 'Mensual':
            $result = "Meses";
            break;
        case 'Semanal':
            $result = "Semanas";
            break;
    }
    return $result;
}
function limpiar($text)
{
    // $strash = array("\r", "\n", "\t");
    // $texto_limpio = str_replace($strash, '', $text);
    $texto_limpio = preg_replace('/\s+/', ' ', $text);
    return $texto_limpio;
}
