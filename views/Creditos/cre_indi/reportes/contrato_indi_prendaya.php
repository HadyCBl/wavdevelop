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

//SE CARGAN LOS DATOS
$strquery = "SELECT cm.CCODCTA AS ccodcta, cm.DFecDsbls AS fecdesem, cm.TipoEnti AS formcredito, dest.DestinoCredito AS destinocred, cm.MonSug AS montoapro, cm.noPeriodo AS cuotas, tbp.nombre, cm.Dictamen AS dictamen,
    cl.idcod_cliente AS codcli, cl.no_identifica As DPI,    cl.date_birth AS fechanac, cl.short_name AS nomcli, cl.Direccion AS direccioncliente,
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
$strquery = "SELECT cl.idcod_cliente AS codcli, gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc, 
    gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
    IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND tipc.idDoc=1),'x') AS nomcli,
    IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND tipc.idDoc=1),'x') AS direccioncli,
    IFNULL((SELECT '1' AS marcado FROM tb_garantias_creditos tgc WHERE tgc.id_cremcre_meta='$codcredito' AND tgc.id_garantia=gr.idGarantia),0) AS marcado,
    IFNULL((SELECT SUM(cli.montoGravamen) AS totalgravamen FROM tb_garantias_creditos tgc INNER JOIN cli_garantia cli ON cli.idGarantia=tgc.id_garantia WHERE tgc.id_cremcre_meta='$codcredito' AND cli.estado=1),0) AS totalgravamen
    FROM tb_cliente cl
    INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
    INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
    INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
    WHERE cl.estado='1' AND gr.estado=1 AND cl.idcod_cliente='$codcli'";
$query = mysqli_query($conexion, $strquery);
$garantias[] = [];
$j = 0;
$flag2 = false;
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $garantias[$j] = $fila;
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

printpdf($data, $garantias, $gastos, $info, $flag3, $conexion);

function printpdf($datos, $garantias, $gastos, $info, $flag3, $conexion)
{

    //FIN COMPROBACION
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '  ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $legalins = "Santos Elizabeth Batz Tzul";
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
        public $legal;
        public $rango;
        public $tipocuenta;
        public $saldoant;
        public $datos;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $legal)
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
            $this->legal = $legal;
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
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
            // $this->Cell(0, 3, 'Representante legal: ' . $this->legal, 'B', 1, 'C');
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $legalins);
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea = 30;
    $tamañofuente = 8;

    $hoy = date('Y-m-d');
    $vlrs = [$info[0]["nomb_comple"] . ' (' . $info[0]["nomb_cor"] . ').', '(' . $info[0]["nomb_cor"] . ')', $info[0]["nomb_cor"], 'créditos'];
    $fechahoy = fechaletras($hoy);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    //variables
    $n_contrato = preg_replace('/0/', '', $datos[0]['ccodcta']);
    $no_DPI = $datos[0]['DPI'];
    $fechanac = $datos[0]['fechanac'];
    $nacfecha = fechaletras($fechanac);

    $ncliente = $datos[0]['nomcli'];
    $ndeuda = $datos[0]['nombre'];
    $fdeuda = $datos[0]['fecdesem'];
    $tprod = $datos[0]['tasaprod'];
    $garantia = $garantias[0]['nomtipgar'];
    $gara = !empty($garantias[0]['nomtipdoc']) ? $garantias[0]['nomtipdoc'] : '-';
    $descrip_gara = !empty($garantias[0]['descripcion']) ? $garantias[0]['descripcion'] : '-';
    $direccion_gara = !empty($garantias[0]['direccion']) ? $garantias[0]['direccion'] : '-';

    $tgara = $garantias[0]['totalgravamen'];
    $reprelegal = 'REPRESENTANTE LEGAL';
    $direccion_compl =
        (!empty($datos[0]['direccioncliente']) ? $datos[0]['direccioncliente'] : '-') . ' ' .
        (!empty($datos[0]['nomdep']) ? $datos[0]['nomdep'] : '-') . ' ' .
        (!empty($datos[0]['nommun']) ? $datos[0]['nommun'] : '-');



    // Stylesheet
    $pdf->SetStyle("p", $fuente, "N", 6, "0,0,0", 0);
    $pdf->SetStyle("h1", $fuente, "N", 6, "0,0,0", 0);
    $pdf->SetStyle("a", $fuente, "BU", 6, "0,0,0");
    $pdf->SetStyle("pers", $fuente, "I", 0, "0,0,0");
    $pdf->SetStyle("place", $fuente, "U", 0, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", 0, "0,0,0");

    //contenido
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->CellFit($ancho_linea + 15, $tamanio_linea, decode_utf8('CONTRATO NO:') . $n_contrato, 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', $tamañofuente);

    $texto = "<p>En Aldea Vásquez municipio de Totonicapán, departamento de Totonicapán,  $fechahoy  , el (la) señor(a) SANTOS ELIZABETH BATZ TZUL de 33 años, guatemalteco (a), empresario(a), con domicilio en la ciudad de Totonicapán. Me identifico con DPI número 2170 15077 0801 actuando como representante legal de CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima; y la contraparte el(la) Sr.(a) $ncliente , guatemalteco(a) con fecha de nacimiento $nacfecha y domicilio en $direccion_compl, me identifico con DPI número $no_DPI extendido por el Registro Nacional de las Personas de Guatemala, manifestamos ser de los datos anteriores, hallarnos en el libre ejercicio de nuestros derechos civiles y manifestamos que, por esté acto, otorgamos el contrato de Mutuo acuerdo con Garantía Prendaria con las clausulas siguientes: PRIMERA: MUTUO, Expone: El (La) Sr.(a) $ncliente que se compromete a devolver la cantidad pactada anteriormente descrita a CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad  Anónima, en las siguientes condiciones y formas: A) PLAZO: El período de pago de la deuda es $ndeuda contado a partir del $fdeuda y podrá renovarse con el acuerdo de las dos partes por períodos iguales. B) INTERES: La deuda pactada percibirá una tasa fija de $tprod a favor de CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC Sociedad Anónima. La suma del interés percibido deberá ser cancelado $ndeuda por el (la) Sr.(a) $ncliente C) FORMA DE PAGO: La deuda será pagada por el Sr.(a) $ncliente, mediante solo un pago al vencimiento del contrato. Todos los pagos los deberá efectuar el (la) Sr. (a) $ncliente CONTRATO NO. $n_contrato sin necesidad de cobro o requerimiento alguno en las agencias ya conocidas de CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC Sociedad Anónima. TERCERA: $garantia: A) En garantía del pago del capital adeudado, de los interés y todos los otros cargos y obligaciones que adquieren por virtud del presente contrato el (la) Sr.(a)  $ncliente constituye a favor de CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima sobre: $gara , $descrip_gara, $direccion_gara. Las cuales bajo juramento manifiesta que de su propiedad de igual forma haciéndole ver las responsabilidades y obligaciones que están en el artículo treinta del Código de Notariado Dto. 314 que sobre los bienes dados en garantía no pesan gravámenes, anotaciones, limitaciones, reclamaciones o litigios que perjudiquen los derechos de la entidad CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima, por su parte el (la) Sr.(a) en la calidad con que actúa, acepta expresamente la prenda que por éste acto constituye a favor de CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima. B) Bajo juramento declaro que fui enterado de las penas relativas al delito del perjurio, que son único y legítimo propietario de las prendas escritas anteriormente. C) Asimismo declaro que ente lugar y fecha la entidad CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima, se haga a cargo de las prendas en caso de incumplimiento de mi parte para cancelar dicha obligación, y en caso de que CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima embargue dicha obligación; de mi libre y espontánea voluntad vendo a dicha entidad los bienes anterior mente descritas por el precio de $tgara que al final el plazo del mutuo descrito no me presenté a pagar en la fecha y montos estipulados y que la cantidad CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima realice dicho embargo autorizándolo desde ya, mediante la presentación de la presente y comercialice la prenda dada en garantía. CUARTO: CONSIGNATARIO: Los involucrados manifiestan que nombran como depositario de los bienes empeñados a la entidad CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima; por su parte en la calidad con que actúa, acepta expresamente el nombramiento de su persona y acepta el cuidado de las prendas como consignatario de los bienes empeñados. Asimismo, el (la) Sr.(a) $ncliente, manifiesta que expresamente acepta el nombramiento de consígnate de los bienes dados en prenda. El (La) Sr.(a) $ncliente, Manifiesta su acuerdo para los bienes dados en garantía sean guardados, indistintamente en cualquiera de las agencias de CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima, autorizando el (la) Sr.(a) $ncliente, expresamente el traslado de dichos bienes según lo estime necesario CORPORACIÓN DE INVERSIONISTAS LOS HERMANOS PUAC, Sociedad Anónima. QUINTA: PACTOS ACCESORIOS: Para los efectos del cumplimiento de las obligaciones adquiridas el (la) Sr. (a) $ncliente expresamente: A) renuncia al fuero de su domicilio y se somete expresamente a los tribunales de la ciudad de Totonicapán; B) Acepta que todos los gastos que en virtud de este contrato se origina, sean de su propia cuenta; C) Señala que como lugar de recibir notificaciones residencia descrita anteriormente. SEXTA: CONFORMIDAD. Finalmente, los involucrados en las calidades en las que cada uno de nosotros actuamos manifestamos estar plenamente conformes con lo estipulado anteriormente. Leído lo escrito por las dos partes enterado de su contenido objeto y efectos legales, los ratificamos, aceptamos y firmamos.</p>";

    $texto2 = "<p>En Aldea Vásquez municipio de Totonicapán, departamento de Totonicapán, el $fechahoy, Yo, el infrascrito notario doy fe, que la firma que antecede y esta puesta al final del documento de esta misma fecha, denominado compra venta condicionada, contenida en esta misma hoja, es auténtica por haber sido puesta en mi presencia por el (la) Sr.(a) $ncliente, quien se identifica con DPI $no_DPI extendido por el Registro Nacional de las Personas de Guatemala que vuelve a firmar ante mí en constancia de la autenticidad. </p>";

    $pdf->WriteTag(0, 4, decode_utf8($texto), 0, "J", 0, 0);
    $pdf->Ln(19);

    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('___________________________________________'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8(' '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('___________________________________________'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('F.)' . $ncliente), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('                    '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('F.)' . $reprelegal), 0, 0, 'L', 0, '', 1, 0);


    $pdf->Ln(8);
    $pdf->WriteTag(0, 4, decode_utf8($texto2), 0, "J", 0, 0);
    $pdf->Ln(19);

    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8(' '), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('___________________________________________'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->CellFit($ancho_linea + 150, $tamanio_linea, decode_utf8('F.)' . $ncliente), 0, 0, 'C', 0, '', 1, 0);

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
