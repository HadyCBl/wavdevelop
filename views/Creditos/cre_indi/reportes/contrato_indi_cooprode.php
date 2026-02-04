
<?php
Session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
include '../../../../src/funcphp/fun_ppg.php';
require '../../../../fpdf/WriteTag.php';
// require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';

use Luecano\NumeroALetras\NumeroALetras;
use PhpOffice\PhpSpreadsheet\Cell\Cell;

//se recibe los datos
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$archivo = $datos[3]; 
$tipo = $_POST["tipo"];
$codcredito = $archivo[0];

// $datos = $_POST["datosval"];
// $inputs = $datos[0];
// $selects = $datos[1];
// $radios = $datos[2];
// $archivo = $datos[3];

//  $tipo ='pdf';
//  $codcredito ='0020020100000030';


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
//Obtiene el destino de credito 
$DESTINOCRED = '';

$strquery = "SELECT Cdescre FROM `cremcre_meta` WHERE CCODCTA = '$codcredito'";
$query = mysqli_query($conexion, $strquery);

if ($query) {
    $result = mysqli_fetch_assoc($query);

    if ($result) {
        $DESTCRED = $result['Cdescre'];

        $strquery_second = "SELECT DestinoCredito FROM $db_name_general.tb_destinocredito WHERE id_DestinoCredito = '$DESTCRED'";
        $query_second = mysqli_query($conexion, $strquery_second);

        if ($query_second) {
            $row_second = mysqli_fetch_assoc($query_second);

            if ($row_second) {
                $DESTINOCRED = $row_second['DestinoCredito'];
            } else {
                $DESTINOCRED = "No se encontró un valor para id_DestinoCredito = '$DESTCRED'";
            }
        } else {
            $DESTINOCRED = "Error en la segunda consulta: " . mysqli_error($conexion);
        }
    } else {
        $DESTCRED = "No se encontró un valor para CCODCTA = '$codcredito'";
    }
} else {
    $DESTCRED = "Error en la primera consulta: " . mysqli_error($conexion);
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
$val_fiador = array_key_exists('1', array_column($garantias, 'fiador'));
$val_hipo = array_key_exists('0', array_column($garantias, 'fiador'));


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


if (!$val_fiador) {
 //   echo "Valor : " . $codcredito. PHP_EOL;
    printpdf2($data,   $info,  $conexion, $DESTINOCRED);

    if (!$flag2) {
        $opResult['mensaje'] = 'No se puede generar el contrato debido a que no se encontró al menos una garantía';
    }

    echo json_encode($opResult);
    return;
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
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('CONTRATO MUTUO CON GARANTIA FIDUCIARIA'), 0, 0, 'C', 0, '', 1, 0);
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
     $tasapalabras = ($letra_dpi->toWords(((($datos[0]['tasaprod'])))));
    $morapalabras = ($letra_dpi->toWords(($datos[0]['mora'])));

  //  determinar si se imprime la segunda condicion
    $val_hipo=array_key_exists('0', array_column($garantias,'fiador'));




    //obtener el plan de pago
    $sumacapint = 0;
    $fecha_vence = '0000-00-00';
    if ($datos[0]['capitalppg'] == 'x' || $datos[0]['interesppg'] == 'x'|| $datos[0]['capitalppg'] != 'x' ||$datos[0]['interesppg'] != 'x'   ) {
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
    $texto = "<p>En el Municipio de Raxruhá Departamento de Alta Verapaz, del dia " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " (" . date('d-m-Y', strtotime($datos[0]['fecdesem'])) . "),<vb> NOSOTROS: </vb>por una parte, RIGOBERTO CORLETO GREEN, de cuarenta y seis años de edad, casado, Guatemalteco, Contador Público y Auditor con domicilillo en el Municipio de Raxruhá Departamento de Alta Verapaz, quien se identifica con su documento de identificación personal (DPI) código único de identificación (CUI) número dos mil seiscientos setenta y tres (espacio) Cincuenta mil seiscientos (espacio) un mil seiscientos trece (2673 50600 1613) Extendido por el Registro Nacional de las Personas de la Republica de Guatemala, actuó en mi calidad de Representante Legal y Presidente de la Consejo de Administración de la Cooperativa Integral de Ahorro y Crédito Pro-Desarrollo Responsabilidad Limitada, calidad que acredito con la inscripción en el Libro de Inscripciones, Ratificaciones y Revocatorias de Representantes Legales de Cooperativas, Folio Mil ciento setenta y dos (1,172), Registro Once mil ciento setenta y cuatro (11,174), autorizado el veintisiete de Julio del dos mil veintiuno por Paola Gricel Sic Granados, Registradora Auxiliar de Cooperativas, entidad en la que en adelante se le denominara simple e indistintamente Cooprode R.L. o Acreedor, por la otra parte comparece <vb>" . $datos[0]['nomcli'] . "</vb> de " . calcular_edad($datos[0]['fechacumple']) . " años de edad" . $estadocivil . mb_strtolower($datos[0]['profesion']) . ", con residencia en " . (ucwords($datos[0]['direccioncliente'])) . " del municipio de " . mb_strtolower(ucwords($datos[0]['nommun'])) . ", " . mb_strtolower(ucwords($datos[0]['nomdep'])) . ". quien se identifica con Documento Personal de Identificación (DPI), Código Unico de Identificación (CUI), Número " . $letra_dpi1 . " espacio " . $letra_dpi2 . " espacio " . $letra_dpi3 . " (" . $dpi_dividido[0] . " " . $dpi_dividido[1] . " " . $dpi_dividido[2] . "), extendida por el Registro Nacional de Las Personas (RENAP) de la República de Guatemala, a quien en el transcurso de este instrumento se le denominará indistintamente la parte fiadora, o el Fiador. Los comparecientes manifestamos ser de los datos de identificación antes consignados y hallarnos en el libre ejercicio de nuestros derechos civiles y que por el presente celebramos CONTRATO DE MUTUO CON GARANTIA FIDUCIARIA, contenido en las cláusulas siguientes. <vb>PRIMERA:</vb> Antecedentes, La Cooperativa Integral de Ahorro y Crédito Pro-Desarrollo Responsabilidad Limitada mediante resolución número <vb>AGE01CREDIAGRIP-0012-2023</vb>, el Comité de créditos por unanimidad resolvió conceder el crédito a <vb>" . $datos[0]['nomcli'] . "</vb> con fondos propios, por el monto, términos y condiciones que más adelante se estipulan.<vb> SEGUNDA: DE LA CONCESIÓN DEL CREDITO Y CONDUCIONES A LAS QUE QUEDA SUJETO:</vb> (A), los comparecientes expresamente convenimos que el crédito otorgado queda sujeto a las siguientes condiciones: a) DEL MONTO: El monto del crédito otorgado es de <vb>" . strtoupper($montodesembolsoletras) . " (Q. " . number_format($datos[0]['montoapro'], 2, '.', ',') . "),</vb>cantidad por la que desde ahora la parte deudora se reconoce liso y llano deudor y declara deber a Cooprode R.L.<vb>b) DESTINO: </vb> El monto del crédito otorgado será invertido exclusivamente en la compra de ganado para su reventa <vb> c) FORMA DE ENTREGA:  </vb> El monto del crédito será otorgado a la parte deudora, posteriormente a la formalización del mismo y presentación de la copia del presente documento de la siguiente manera, Directamente a " . $datos[0]['nomcli'] . " mediante Cheque no Negociable a nombre de la parte deudora para ser cobrado en cualquiera de las entidades donde Cooprode R.L. maneja sus fondos, por un monto de " . strtoupper($montodesembolsoletras) . " (Q. " . number_format($datos[0]['montoapro'], 2, '.', ',') . "), Cooprode R,L, podrá hacer las inspecciones que se consideren convenientes para verificar la correcta inversión de los fondos concedidos. Así mismo, Cooprode R.L, podrá abstenerse de hacer la entrega total o parcial de los fondos del crédito otorgado, suspenderla temporal o definitivamente si a su juicio la parte deudora no cumple a cabalidad con el plan de inversión, <vb> d) PLAZO Y FORMA DE PAGO: </vb>El plazo para el cumplimiento de la obligación es de " . $cuotaspalabras . " meses (" . $datos[0]['cuotas'] . ") contados a partir de la fecha del desembolso de los fondos. La parte Deudora pagara a Cooprode, R.L, el monto del crédito concedido mediante " . $cuotaspalabras . " cuotas (" . $datos[0]['cuotas'] . ")  niveladas mensuales, vencidas y consecutivas que incluyen capital e interés las que deben hacerse efectivas a partir del mes siguiente de la fecha de la entrega de los fondos de conformidad con la tabla de amortización emitida para el efecto. <vb> e) INTERESES:</vb>LA PARTE Deudora reconoce y pagará inicialmente una tasa de interés de un " . $tasapalabras . " POR CIENTO ANUAL (" . number_format(((12*$datos[0]['tasaprod'])/12), 2, ',', ' ') . "%) , pagadera sobre saldos deudores mensualmente y al vencimiento del plazo. Dicha tasa de interés será variable y Cooprode R.L, podrá modificarla de conformidad con lo establecido en la política crediticia con recursos propios vigente, en cuyo caso se modificarán las cuotas que la parte Deudora debe pagar mensualmente a Cooprode R.L, de manera que el capital adeudado más los intereses queden totalmente cancelados al vencimiento del plazo, <vb> f) INTERESES MORATORIOS:</vb> De no cancelarse las cuotas en la fecha señalada, Cooprode R.L, cobrará un recargo del UNO por ciento (1%) diarios sobre el capital e intereses en mora, el cual se hará efectivo al día siguiente de vencido el plazo para la cancelación de las cuotas, sin necesidad de cobro, requerimiento o notificación alguna a las partes.  <vb>g) GASTOS ADMINISTRATIVOS:</vb> La parte Deudora por este acto acepta que previo al momento del desembolso de los fondos del presente crédito, deberá cancelar una comisión por apertura de este de acuerdo con la tabla establecida por el Consejo de Administración de Cooprode R.L, o por el Órgano Administrativo correspondiente y que se encuentre vigente al momento del desembolso.  <vb>h) AUTORIZACION PARA DEBITAR DE CUENTAS: </vb> la parte Deudora expresamente autoriza a Cooprode R.L, para que dé cualesquiera de las cuentas abiertas a su nombre en Cooprode R.L, se debite la cantidad necesaria para pagar las amortizaciones a capital e intereses adeudados, efectuando para el efecto los débitos con cargo a la disponibilidad de dichas cuentas, no obstante, a lo anterior, la parte Deudora se obliga a efectuar los pagos en la forma y plazo estipulados en el presente contrato. <vb>i) LUGAR DE PAGO: </vb> Todo pago de capital e intereses lo realizara la parte Deudora en las fechas estipuladas en las Cajas Receptoras de las oficinas de Cooprode R.L, sin necesidad de cobro o requerimiento alguno. <vb>j) IMPUTACIÓN DE PAGO Y LIBERACIÓN DE GARANTÍA:</vb> La parte Deudor a y la parte Garante, en caso hubiere, manifiestan que: en caso este crédito sea de garantía mixta; o en caso tuviere más de una obligación con Cooprode R.L, renuncia a cualquier derecho de imputación de pagos y autoriza irrevocablemente a Cooprode R.L, para que cualquier pago que se realice en concepto de capital, interés, intereses moratorios, recargos por mora, comisiones o cualquier otro, Cooprode R.L, lo aplique en el siguiente orden: j.a) en caso de crédito con garantía mixta, primero para abonar a la parte del crédito garantizada con garantía fiduciaria y luego la parte del crédito garantizada con garantía mobiliaria j.b) en caso de más de una obligación a favor de Cooprode R. L,  primero a los créditos garantizados con garantía fiduciaria y luego a los créditos garantizados con garantía mobiliaria. Lo mismo aplicara para la liberación de garantías. En todo caso, Cooprode R.L, se reserva el derecho de variar el orden de imputación de pagos. <vb>TERCERA: GARANTIA FIDUCIARIA.</vb> " . $clientefiador[0]['nomcli'] . " como FIADOR ILIMITADO Y MANCOMUNADAMENTE SOLIDARIO de <vb>" . $datos[0]['nomcli'] . "</vb> por todas y cada una de las obligaciones asumidas por la parte Deudora de conformidad con el presente documento, fianza que subsistirá no solo por el plazo original del contrato sino también por el de las prórrogas, modificaciones y/o ampliaciones que puedan concederse sean cual fuere su duración, hasta el total cumplimiento de las obligaciones asumidas por la parte Deudora, sin necesidad de ningún aviso o suscripción de documento alguno y desde ahora acepto dichas prórrogas y declaro que la garantía fiduciaria que por este acto constituyo garantiza la totalidad del crédito otorgado, más los intereses, costas judiciales y gastos de cobranza que llegaren a causarse. Así mismo expresamente autorizo a Cooprode R.L. para que en caso la parte Deudora no pague las cuotas de capital e interés en las fechas establecidas, se debite de cualquiera de las cuentas de depósito que a mi nombre aparezcan abiertas en Cooprode R.L, las cantidades necesarias para cubrir los pagos que correspondan y cualquier otro gasto generado por la presente operación de crédito, hasta la total cancelación. <vb>CUARTA: DISPOSICIONES PROCESALES. a) </vb> La parte Deudora y la parte Fiadora aceptamos desde ya como buenas y exactas las cuentas que Cooprode R.L, nos presente acerca de este negocio y como líquido, exigible y de plazo vencido el saldo que se nos reclame y como título ejecutivo para ser demandados el presente documento privado; b) para los efectos del cumplimiento y ejecución del presente contrato la parte DEUDORA y la parte FIADORA, renunciamos al fuero de nuestro domicilio y nos sujetamos a la jurisdicción de los tribunales competentes del Departamento de Alta Verapaz o los que Cooprode R.L, elija y señalamos desde ahora para recibir notificaciones, citaciones y emplazamientos la siguiente dirección: l) La parte deudora Zona 0 Aldea 28 de Septiembre  del Municipio de Raxruhá Departamento de  Alta Verapaz; ll) La parte fiadora Zona 0 Barrio San José del Municipio de Raxruhá Departamento de  Alta Verapaz, obligándonos a comunicar por escrito de cualquier cambio que de ellas hiciéramos, en el entendido que si no hacemos tal comunicación, serán válidas y surtirán plenos efectos las citaciones, emplazamientos, notificaciones judiciales y extrajudiciales que en el lugar señalado se nos hagan. Aceptamos que el procedimiento de ejecución de la presente obligación se rija por las disposiciones legales que le sean aplicables. Si Cooprode R.L, designare depositarios o interventores, no estará obligada a presentar fianza ni garantía alguna, ni asume responsabilidad alguna por la actuación de los mismos quienes tampoco están obligados a presentar fianza o garantía con motivo de ejercicio de sus cargos. <vb>QUINTA: OTRAS CONDICIONES.</vb> La parte DEUDORA y la parte FIADORA declaramos que: a) conocemos íntegramente el contenido de la Resolución descrita en la parte primera de este contrato y que el mismo se suscribe amparado en las condiciones de dicha resolución; b) expresamente nos obligamos, en lo que a cada parte corresponde, a cumplir con todas las disposiciones de la resolución referida, incluyendo lo relativo a “OTRAS CONDICIONES”, las que pasan a formar parte integral del presente contrato; c) Que conocemos el contenido íntegro de la Resolución que dio origen al presente crédito y que la misma pasa a formar parte integral del presente contrato. d) La parte Deudora acepta expresamente que los honorarios y gastos por la formalización del presente crédito y los de su cancelación por cualquier medio corren exclusivamente a su cargo; e) DE LOS SEGUROS: e.a) La parte Deudora se obliga a contratar y endosar a favor de Cooprode R.L, el o los seguros que éste le indique, incluyendo pero no limitándose a un seguro de saldos deudores, los cuales deberán permanecer vigentes hasta la total cancelación del crédito; e.b) así mismo la parte Deudora en forma expresa faculta a Cooprode R.L, para que posteriormente a la suscripción del presente contrato, si fuera necesaria la contratación de un seguro adicional, especialmente aquellos que sean necesarios respecto a los niveles de riesgo que como Deudor represente proceda a la contratación inmediata de dicho seguro o seguros, según sea el caso, mientras exista un saldo a favor de la entidad acreedora. <vb>SEXTA: VENCIMIENTO ANTICIPADO DEL PLAZO. </vb> Las partes contratantes convenimos en que Cooprode R.L, podrá dar por vencido anticipadamente el plazo de este contrato y exigir el pago total del crédito sin más requisitos ni formalidad, si concurren las siguientes causas: a) Si la parte deudora dejara de pagar puntualmente una sola de las cuotas de capital e intereses, en la forma convenida en este contrato, b) si la parte deudora faltara al cumplimiento de cualquiera de las obligaciones de la resolución de aprobación del crédito, de las que asume en este documento o de las que específicamente determine la ley de Cooperativas o la Intendencia de Verificación Especial (IVE). c) si el dinero recibido a mutuo no se empleara para el destino consignado en este contrato, d) si contra los bienes de la parte Deudora o Fiadora se librara orden de anotación, demanda o embargo. e) Si durante la vigencia del plazo del presente crédito o de sus prórrogas se altera el estado patrimonial de la parte Deudora o de la parte Fiadora, que se ha tenido en cuenta para la autorización del crédito, al grado que a juicio de Cooprode R.L, se rebaje substancialmente su capacidad de cumplir sus obligaciones dentro del plazo del contrato. <vb>SEPTIMA: CESIÓN DE EL CREDITO.</vb> El presente crédito podrá ser cedido, negociado o enajenado de cualquier forma por parte de Cooprode R.L, sin necesidad de previo aviso o posterior notificación a la parte Deudora o a la parte Fiadora, a cuyo cargo corren los gastos que genere este negocio, su formalización y aquellos que ocasione su cobro judicial o extrajudicial, si fuere el caso. La ampliación al presente contrato que hiciere Cooprode R.L, de conformidad con lo que se estipula formara parte integrante del mismo. Asimismo, queda obligada la parte Deudora a que cuando previo a resolver una solicitud de reestructuración o prórroga se requiera de una inspección y/o avaluó, reconocerá o hará efectivo previamente a Cooprode R.L, los gastos que impliquen dichos trabajos. <vb>OCTAVA:</vb> Los otorgantes en las calidades con que actuamos manifestamos nuestra aceptación con todas y cada una de las cláusulas del presente documento. En fe de lo cual, y previa lectura del contenido integro de este contrato, enterados de su objeto, validez y demás efectos legales, lo aceptamos, ratificamos y firmamos en el mismo lugar y fecha antes indicado.</p>   " ; 
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);
    $texto = "<p>.</p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);

    //LUGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'FIRMA REPRESENTANTE LEGAL	', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'FIRMA PARTE DEUDORA', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 27, $tamanio_linea, 'FIRMA PARTE FIADORA', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(10);

    //PARTE 2 DEL DOCUMENTO
    $texto="<p></p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(89);

    //LUGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'FIRMA REPRESENTANTE LEGAL	', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'FIRMA PARTE DEUDORA', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 27, $tamanio_linea, 'FIRMA PARTE FIADORA', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(15);

    $pdf->SetFont($fuente, 'B', $tamañofuente +2);
    $texto="<p><vb>COOPRODE R.L.</vb></p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "C", 0, 0);
    $pdf->Ln(1);

//   ob_start();
    $pdf->Output();
    // $pdfData = ob_get_contents();
    // ob_end_clean();

    // $opResult = array(
    //     'status' => 1,
    //     'mensaje' => 'Comprobante generado correctamente',
    //     'namefile' => "Contrato-" . (($datos[0]['ccodcta'] == '' || $datos[0]['ccodcta'] == null) ? ' ' : ($datos[0]['ccodcta'])),
    //     'tipo' => "pdf",
    //     'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    // );
    // echo json_encode($opResult);
}



function printpdf2($datos,  $info,  $conexion, $DESTINOCRED )
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
    class PDF2 extends PDF_WriteTag
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
    $pdf = new PDF2($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
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
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('CONTRATO DE MUTUO SIN GARANTIA'), 0, 0, 'C', 0, '', 1, 0);
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
    //variable para estado civil
    $estadocivil = (isset($datos[0]['estadocivil'])) ? (", " . mb_strtolower($datos[0]['estadocivil']) . ", ") : (" ");
    $estadocivil2 = (isset($clientefiador[0]['estadocivil'])) ? (", " . mb_strtolower($clientefiador[0]['estadocivil']) . ", ") : (" ");
    //division de dpi
    $letra_dpi = new NumeroALetras();
    $dpi_dividido = dividir_dpi($datos[0]['dpi']);
    $letra_dpi1 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[0]))));
    $letra_dpi2 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[1]))));
    $letra_dpi3 = mb_strtolower($letra_dpi->toWords((($dpi_dividido[2]))));


    $montodesembolsoletras = mb_strtolower($letra_dpi->toMoney(($datos[0]['montoapro']), 2, 'quetzales', 'centavos'));
    $cuotaspalabras = mb_strtolower($letra_dpi->toWords(($datos[0]['cuotas'])));
     $tasapalabras = ($letra_dpi->toWords(((($datos[0]['tasaprod']*12)))));
    $morapalabras = ($letra_dpi->toWords(($datos[0]['mora'])));

  //  determinar si se imprime la segunda condicion




    //obtener el plan de pago
    $sumacapint = 0;
    $fecha_vence = '0000-00-00';
    if ($datos[0]['capitalppg'] == 'x' || $datos[0]['interesppg'] == 'x'|| $datos[0]['capitalppg'] != 'x' ||$datos[0]['interesppg'] != 'x'   ) {
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
    $fechaActual = date('d-m-Y');


    

    $pdf->SetFont($fuente, 'B', $tamañofuente - 1);
    $texto = "<p>En el Municipio de Raxruhá Departamento de Alta Verapaz, del dia " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " (" . date('d-m-Y', strtotime($datos[0]['fecdesem'])) . "),<vb> NOSOTROS: </vb>por una parte, RIGOBERTO CORLETO GREEN, de cuarenta y seis años de edad, casado, Guatemalteco, Contador Público y Auditor con domicilillo en el Municipio de Raxruhá Departamento de Alta Verapaz, quien se identifica con su documento de identificación personal (DPI) código único de identificación (CUI) número dos mil seiscientos setenta y tres (espacio) Cincuenta mil seiscientos (espacio) un mil seiscientos trece (2673 50600 1613) Extendido por el Registro Nacional de las Personas de la Republica de Guatemala, actuó en mi calidad de Representante Legal y Presidente de la Consejo de Administración de la Cooperativa Integral de Ahorro y Crédito Pro-Desarrollo Responsabilidad Limitada, calidad que acredito con la inscripción en el Libro de Inscripciones, Ratificaciones y Revocatorias de Representantes Legales de Cooperativas, Folio Mil ciento setenta y dos (1,172), Registro Once mil ciento setenta y cuatro (11,174), autorizado el " . $dia_desembolsoaux . " de " . mb_strtolower($meses[date("n", $fechadesembolso) - 1]) . " de " . $anodesembolsoaux . " (" . date('d-m-Y', strtotime($datos[0]['fecdesem'])) . " por Paola Gricel Sic Granados, Registradora Auxiliar de Cooperativas, entidad en la que en adelante se le denominara simple e indistintamente Cooprode R.L. o Acreedor, por la otra parte comparece, <vb>" . $datos[0]['nomcli'] . "</vb> de " . calcular_edad($datos[0]['fechacumple']) . " años de edad" . $estadocivil . mb_strtolower($datos[0]['profesion']) . ", con residencia en " . (ucwords($datos[0]['direccioncliente'])) . " del municipio de " . mb_strtolower(ucwords($datos[0]['nommun'])) . ", " . mb_strtolower(ucwords($datos[0]['nomdep'])) . ". quien se identifica con Documento Personal de Identificación (DPI), Código Unico de Identificación (CUI), Número " . $letra_dpi1 . " espacio " . $letra_dpi2 . " espacio " . $letra_dpi3 . " (" . $dpi_dividido[0] . " " . $dpi_dividido[1] . " " . $dpi_dividido[2] . "), extendida por el Registro Nacional de Las Personas (RENAP) de la República de Guatemala, a quien en el transcurso de este instrumento se le denominará indistintamente la parte fiadora, o el Fiador. Los comparecientes manifestamos ser de los datos de identificación antes consignados y hallarnos en el libre ejercicio de nuestros derechos civiles y que por el presente celebramos CONTRATO DE MUTUO SIN GARANTIA, contenido en las cláusulas siguientes. <vb>PRIMERA:</vb> Antecedentes, La Cooperativa Integral de Ahorro y Crédito Pro-Desarrollo Responsabilidad Limitada mediante resolución número <vb>AGE01CRMICRO-0004-2023,</vb> el Comité de créditos por unanimidad resolvió conceder el crédito a <vb>" . $datos[0]['nomcli'] . "</vb> con fondos propios, por el monto, términos y condiciones que más adelante se estipulan. <vb>SEGUDA</vb> DE LA CONCESIÓN DEL CREDITO Y CONDICIONES A LAS QUE QUEDA SUJETO(A), los comparecientes expresamente convenimos que el crédito otorgado queda sujeto a las siguientes condiciones: <vb>a) DEL MONTO: </vb> El monto del crédito otorgado es de <vb>" . strtoupper($montodesembolsoletras) . " EXACTOS (Q. " . number_format($datos[0]['montoapro'], 2, '.', ',') . "),</vb> cantidad por la que desde ahora la parte deudora se reconoce liso y llano deudor y declara deber a Cooprode R.L. <vb>b)DESTINO:</vb> El monto del crédito otorgado será invertido exclusivamente para  $DESTINOCRED <vb>c) FORMA DE ENTREGA: </vb> El monto del crédito será otorgado a la parte deudora, posteriormente a la formalización del mismo y presentación de la copia del presente documento de la siguiente manera, Directamente a <vb>". $datos[0]['nomcli'] . "</vb>mediante Cheque no Negociable a nombre de la parte deudora para ser cobrado en cualquiera de las entidades donde Cooprode R.L. maneja sus fondos, por un monto de  <vb>" . strtoupper($montodesembolsoletras) . " (Q. " . number_format($datos[0]['montoapro'], 2, '.', ',') . "),</vb> Cooprode R,L, podrá hacer las inspecciones que se consideren convenientes para verificar la correcta inversión de los fondos concedidos. Así mismo, Cooprode R.L, podrá abstenerse de hacer la entrega total o parcial de los fondos del crédito otorgado, suspenderla temporal o definitivamente si a su juicio la parte deudora no cumple a cabalidad con el plan de inversión, <vb>d) PLAZO Y FORMA DE PAGO: </vb>El plazo para el cumplimiento de la obligación es de " . $cuotaspalabras . " meses <vb>(" . $datos[0]['cuotas'] . ")</vb>contados a partir de la fecha del desembolso de los fondos. La parte Deudora pagara a Cooprode, R.L, el monto del crédito concedido mediante " . $cuotaspalabras . " <vb>(" . $datos[0]['cuotas'] . ")</vb> cuotas  niveladas mensuales, vencidas y consecutivas que incluyen capital e interés las que deben hacerse efectivas a partir del mes siguiente de la fecha de la entrega de los fondos de conformidad con la tabla de amortización emitida para el efecto. <vb>e) INTERESES: </vb> La parte deudora reconoce y pagará inicialmente una tasa de interés del " . $tasapalabras . " POR CIENTO ANUAL (" . number_format(((12*$datos[0]['tasaprod'])/12), 2, ',', ' ') . "%) pagadera sobre saldos deudores mensualmente y al vencimiento del plazo. Dicha tasa de interés será variable y Cooprode R.L, podrá modificarla de conformidad con lo establecido en la política crediticia con recursos propios vigente, en cuyo caso se modificarán las cuotas que la parte Deudora debe pagar mensualmente a Cooprode R.L, de manera que el capital adeudado más los intereses queden totalmente cancelados al vencimiento del plazo. <vb>f) INTERESES MORATORIOS: </vb>De no cancelarse las cuotas en la fecha señalada, Cooprode R.L, cobrará un recargo del UNO por ciento (1%) diarios sobre el capital e intereses en mora, el cual se hará efectivo al día siguiente de vencido el plazo para la cancelación de las cuotas, sin necesidad de cobro, requerimiento o notificación alguna a las partes. <vb>g) GASTOS ADMINISTRATIVOS: </vb>La parte Deudora por este acto acepta que previo al momento del desembolso de los fondos del presente crédito, deberá cancelar una comisión por apertura de este, de acuerdo con la tabla establecida por el Consejo de Administración de Cooprode R.L, o por el Órgano Administrativo correspondiente y que se encuentre vigente al momento del desembolso.<vb>h) AUTORIZACION PARA DEBITAR DE CUENTAS: </vb> la parte deudora expresamente autoriza a Cooprode R.L, para que dé cualesquiera de las cuentas abiertas a su nombre en Cooprode R.L, se debite la cantidad necesaria para pagar las amortizaciones a capital e intereses adeudados, efectuando para el efecto los débitos con cargo a la disponibilidad de dichas cuentas, no obstante, a lo anterior, la parte Deudora se obliga a efectuar los pagos en la forma y plazo estipulados en el presente contrato. <vb>i) LUGAR DE PAGO:</vb> Todo pago de capital e intereses lo realizara la parte Deudora en las fechas estipuladas en las Cajas Receptoras de las oficinas de Cooprode R.L, sin necesidad de cobro o requerimiento alguno.<vb>j) IMPUTACIÓN DE PAGO Y LIBERACIÓN DE GARANTÍA: </vb> La parte Deudora y la parte Garante, en caso hubiere, manifiestan que, en caso este crédito sea de garantía mixta; o en caso tuviere más de una obligación con Cooprode R.L, renuncia a cualquier derecho de imputación de pagos y autoriza irrevocablemente a Cooprode R.L, para que cualquier pago que se realice en concepto de capital, interés, intereses moratorios, recargos por mora, comisiones o cualquier otro, Cooprode R.L, lo aplique en el siguiente orden: j.a) en caso de crédito con garantía mixta, primero para abonar a la parte del crédito garantizada con garantía fiduciaria y luego la parte del crédito garantizada con garantía mobiliaria j.b) en caso de más de una obligación a favor de Cooprode R. L,  primero a los créditos garantizados con garantía fiduciaria y luego a los créditos garantizados con garantía mobiliaria. Lo mismo aplicara para la liberación de garantías. En todo caso, Cooprode R.L, se reserva el derecho de variar el orden de imputación de pagos. <vb>TERCERA: DISPOSICIONES PROCESALES. a)</vb> La parte Deudora acepta desde ya como buenas y exactas las cuentas que Cooprode R.L, me presente acerca de este negocio y como líquido, exigible y de plazo vencido el saldo que se nos reclame y como título ejecutivo para ser demandado el presente documento privado; b) para los efectos del cumplimiento y ejecución del presente contrato la parte DEUDORA, renuncia al fuero de su domicilio y me sujeto a la jurisdicción de los tribunales competentes del Departamento de Alta Verapaz o los que Cooprode R.L, elija y señalo desde ahora para recibir notificaciones, citaciones y emplazamientos la siguiente dirección: l) Zona 0 Barrio Santa María del Municipio de Raxruhá Departamento de  Alta Verapaz, me obligo a comunicar por escrito de cualquier cambio que de ella hiciera, en el entendido que si no hiciera tal comunicación, serán válidas y surtirán plenos efectos las citaciones, emplazamientos, notificaciones judiciales y extrajudiciales que en el lugar señalado se me hagan. Acepto que el procedimiento de ejecución de la presente obligación se rija por las disposiciones legales que le sean aplicables. Si Cooprode R.L, designare depositarios o interventores, no estará obligada a presentar fianza ni garantía alguna, ni asume responsabilidad alguna por la actuación de los mismos quienes tampoco están obligados a presentar fianza o garantía con motivo de ejercicio de sus cargos. <vb>CUARTA: OTRAS CONDICIONES.</vb>  La parte DEUDORA y la parte FIADORA si hubiera declaramos que: a) conocemos íntegramente el contenido de la Resolución descrita en la parte primera de este contrato y que el mismo se suscribe amparado en las condiciones de dicha resolución; b) expresamente nos obligamos, en lo que a cada parte corresponde, a cumplir con todas las disposiciones de la resolución referida, incluyendo lo relativo a “OTRAS CONDICIONES”, las que pasan a formar parte integral del presente contrato; c) Que conocemos el contenido íntegro de la Resolución que dio origen al presente crédito y que la misma pasa a formar parte integral del presente contrato. d) La parte Deudora acepta expresamente que los honorarios y gastos por la formalización del presente crédito y los de su cancelación por cualquier medio corren exclusivamente a su cargo; e) DE LOS SEGUROS: e.a) La parte Deudora se obliga a contratar y endosar a favor de Cooprode R.L, el o los seguros que éste le indique, incluyendo pero no limitándose a un seguro de saldos deudores, los cuales deberán permanecer vigentes hasta la total cancelación del crédito; e.b) así mismo la parte Deudora en forma expresa faculta a Cooprode R.L, para que posteriormente a la suscripción del presente contrato, si fuera necesaria la contratación de un seguro adicional, especialmente aquellos que sean necesarios respecto a los niveles de riesgo que como Deudor represente proceda a la contratación inmediata de dicho seguro o seguros, según sea el caso, mientras exista un saldo a favor de la entidad acreedora.<vb>QUINTA: VENCIMIENTO ANTICIPADO DEL PLAZO.</vb> Las partes contratantes convenimos en que Cooprode R.L, podrá dar por vencido anticipadamente el plazo de este contrato y exigir el pago total del crédito sin más requisitos ni formalidad, si concurren las siguientes causas: a) Si la parte deudora dejara de pagar puntualmente una sola de las cuotas de capital e intereses, en la forma convenida en este contrato, b) si la parte deudora faltara al cumplimiento de cualquiera de las obligaciones de la resolución de aprobación del crédito, de las que asume en este documento o de las que específicamente determine la ley de Cooperativas o la Intendencia de Verificación Especial (IVE). c) si el dinero recibido a mutuo no se empleara para el destino consignado en este contrato, d) si contra los bienes de la parte Deudora se librara orden de anotación, demanda o embargo. e) Si durante la vigencia del plazo del presente crédito o de sus prórrogas se altera el estado patrimonial de la parte Deudora, que se ha tenido en cuenta para la autorización del crédito, al grado que a juicio de Cooprode R.L, se rebaje substancialmente su capacidad de cumplir sus obligaciones dentro del plazo del contrato. <vb>SEXTA: CESIÓN DE EL CREDITO.</vb> . El presente crédito podrá ser cedido, negociado o enajenado de cualquier forma por parte de Cooprode R.L, sin necesidad de previo aviso o posterior notificación a la parte Deudora, a cuyo cargo corren los gastos que genere este negocio, su formalización y aquellos que ocasione su cobro judicial o extrajudicial, si fuere el caso. La ampliación al presente contrato que hiciere Cooprode R.L, de conformidad con lo que se estipula formara parte integrante del mismo. Asimismo, queda obligada la parte Deudora a que cuando previo a resolver una solicitud de reestructuración o prórroga se requiera de una inspección y/o avaluó, reconocerá o hará efectivo previamente a Cooprode R.L, los gastos que impliquen dichos trabajos.<vb>SEPTIMA:</vb> Los otorgantes en las calidades con que actuamos manifestamos nuestra aceptación con todas y cada una de las cláusulas del presente documento. En fe de lo cual, y previa lectura del contenido integro de este contrato, enterados de su objeto, validez y demás efectos legales, lo aceptamos, ratificamos y firmamos en el mismo lugar y fecha antes indicado.    </p> " ; 
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);
    $texto = "<p>.</p>"; 
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);


    //LUGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'FIRMA REPRESENTANTE LEGAL	', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 27, $tamanio_linea, 'FIRMA PARTE DEUDORA', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(5);

    //PARTE 2 DEL DOCUMENTO
    $texto="<p></p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(2);

  

    $pdf->SetFont($fuente, 'B', $tamañofuente +2);
    $texto="<p><vb>COOPRODE R.L.</vb></p>";
    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "C", 0, 0);
    $pdf->Ln(1);

//   ob_start();
    $pdf->Output();
    // $pdfData = ob_get_contents();
    // ob_end_clean();

    // $opResult = array(
    //     'status' => 1,
    //     'mensaje' => 'Comprobante generado correctamente',
    //     'namefile' => "Contrato-" . (($datos[0]['ccodcta'] == '' || $datos[0]['ccodcta'] == null) ? ' ' : ($datos[0]['ccodcta'])),
    //     'tipo' => "pdf",
    //     'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    // );
    // echo json_encode($opResult);
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
