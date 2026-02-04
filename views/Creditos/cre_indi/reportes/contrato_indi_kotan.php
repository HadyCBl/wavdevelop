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
    cl.idcod_cliente AS codcli, cl.short_name AS nomcli, cl.Direccion AS direccioncliente, cl.date_birth AS cumplecli, cl.estado_civil AS estadocivilcli,
    cl.no_identifica AS dpicli,
    cl.genero AS generocli, cl.pais_nacio AS nacionalidadcli, cl.profesion AS profesioncli, cl.aldea_reside AS recidenciacli,
    CONCAT(us.nombre,' ', us.apellido) AS analista,cm.NtipPerC,
    pr.id AS codprod, pr.nombre AS nomprod, pr.descripcion AS descprod, cm.NIntApro AS tasaprod, pr.porcentaje_mora AS mora,
    ff.descripcion AS nomfondo,
    (IFNULL((SELECT ppg2.ncapita FROM Cre_ppg ppg2 WHERE ppg2.ccodcta=cm.CCODCTA ORDER BY ppg2.dfecven ASC LIMIT 1),'x')) AS capitalppg,
    (IFNULL((SELECT ppg3.nintere FROM Cre_ppg ppg3 WHERE ppg3.ccodcta=cm.CCODCTA ORDER BY ppg3.dfecven ASC LIMIT 1),'x')) AS interesppg,
    (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id = cl.depa_reside),'-')) AS nomdepcli,
    (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id = cl.id_muni_reside),'-')) AS nommuncli
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
    IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS nomcli,
    IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS direccioncli,
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
    $oficina = utf8_decode($info[0]["nom_agencia"]);
    $institucion = utf8_decode($info[0]["nomb_comple"]);
    $direccionins = utf8_decode($info[0]["muni_lug"]);
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
            parent::__construct('P', 'mm', 'Letter');
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            // $this->SetMargins(40, 20, 15);    // left, top, right
            // $this->SetAutoPageBreak(true, 30); // margen inferior
        }

        // Función que determina si la página es par o impar
        private function isEvenPage()
        {
            return ($this->PageNo() % 2 == 0);
        }

        private function adjustMarginsForCurrentPage()
        {
            if ($this->isEvenPage()) {
                // Páginas pares (2, 4, 6...)
                $this->lMargin = 15;  // margen izquierdo
                $this->tMargin = 25;  // margen superior  
                $this->rMargin = 40;  // margen derecho
            } else {
                // Páginas impares (1, 3, 5...)
                $this->lMargin = 40;  // margen izquierdo
                $this->tMargin = 20;  // margen superior
                $this->rMargin = 15;  // margen derecho
            }

            // Actualizar la posición X para reflejar el nuevo margen izquierdo
            $currentY = $this->GetY();
            $this->SetXY($this->lMargin, $currentY);
        }

        // Función que se ejecuta automáticamente al crear una nueva página
        function AddPage($orientation = '', $size = '', $rotation = 0)
        {
            // Llamar al AddPage original
            parent::AddPage($orientation, $size, $rotation);

            // Después de crear la página, ajustar márgenes según si es par o impar
            $this->adjustMarginsForCurrentPage();
        }

        // Override del método AcceptPageBreak para controlar el salto automático
        function AcceptPageBreak()
        {
            // Obtener el número de página antes del salto
            $pageBeforeBreak = $this->PageNo();

            // Permitir el salto de página
            $acceptBreak = parent::AcceptPageBreak();

            if ($acceptBreak) {
                // Verificar si realmente se creó una nueva página
                if ($this->PageNo() > $pageBeforeBreak) {
                    // Se creó una nueva página, ajustar márgenes inmediatamente
                    $this->adjustMarginsForCurrentPage();
                }
            }

            return $acceptBreak;
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            // // Logo de la agencia
            $this->Image($this->pathlogoins, 45, 18, 100);

            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 115, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'R');
            // Salto de línea
            $this->Ln(60);
        }

        // Pie de página
        function Footer()
        {
            // // Posición: a 1 cm del final
            // $this->SetY(-15);
            // $this->SetFont('Arial', 'I', 8);
            // // Número de página
            // $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea = 30;
    $tamañofuente = 8;

    $hoy = date('Y-m-d');
    $vlrs = [$info[0]["nomb_comple"] . ' (' . $info[0]["nomb_cor"] . ').', '(' . $info[0]["nomb_cor"] . ')', $info[0]["nomb_cor"], 'créditos'];
    $fechahoy = fechaletras($hoy);

    $pdf->AliasNbPages();
    $pdf->AddPage();

    // Stylesheet
    $pdf->SetStyle("p", $fuente, "N", $tamañofuente, "0,0,0", 0);
    $pdf->SetStyle("h1", $fuente, "N", $tamañofuente, "0,0,0", 0);
    $pdf->SetStyle("a", $fuente, "BU", $tamañofuente, "0,0,0");
    $pdf->SetStyle("pers", $fuente, "I", $tamañofuente, "0,0,0");
    $pdf->SetStyle("place", $fuente, "U", $tamañofuente, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", $tamañofuente, "0,0,0");

    // VARIABLES
    $edadCliente = calcularEdad($datos[0]['cumplecli']);
    $cumpleañoscli = ' de ' . numeroATexto($edadCliente) . ' años de edad, ';
    $estadocivilcli = $datos[0]['estadocivilcli'] . '(A), ';
    $generocli = $datos[0]['generocli'];
    $nacionalidadcli = $datos[0]['nacionalidadcli'];
    $nacli = ($nacionalidadcli == 'GT' && $generocli == 'M') ? 'guatemalteco' : (($nacionalidadcli == 'GT' && $generocli == 'F') ? 'guatemalteca' : (($nacionalidadcli != 'GT' && $generocli == 'M') ? 'extranjero' : 'extranjera'));
    $gencli = ($generocli == 'M') ? ' el señor ' : ' la señora ';
    $profcli = !empty(trim($datos[0]['profesioncli'] ?? '')) ? $datos[0]['profesioncli'] : 'profesión';
    $residenciacli = $datos[0]['recidenciacli'];
    $depacli = $datos[0]['nomdepcli'];
    $municli = $datos[0]['nommuncli'];

    // dpi formateado
    $dpicli = formatearDPI($datos[0]['dpicli']);
    $dpiclitexto = dpiATexto($datos[0]['dpicli']);


    // <p>
    // <vb></vb> NEGRITA
    // </p>
    //<pers></pers>

    // TEXTO
    $texto = decode_utf8('<p>NOSOTROS: <pers><vb>Sebastián Juan Baltazar,</vb></pers> de treinta y dos años de edad, casado, guatemalteco, comerciante, de este domicilio,  me identifico con el Documento Personal de identificación, con Código Único de Identificación número Dos mil ciento cuarenta y cinco Diecisiete mil ciento cuarenta y cuatro Mil trescientos cuarenta y cinco (2145 17144 1325) extendida por el Registro Nacional de las Personas de la República de Guatemala; señalo que comparezco y actúo en calidad de Presidente del Consejo de Administración y Representante Legal de la entidad denominada GRUPO KOTANH, SOCIEDAD ANONIMA, calidad que acredito con el razonamiento del acta emitido por el Registro Mercantil de fecha dieciocho de marzo de dos mil veinticuatro, inscrita bajo el número Setecientos treinta y cinco mil cuatrocientos sesenta y ocho (735,468), Folio Setecientos cuarenta y seis (746), Libro Ochocientos veintinueve (829) de Auxiliares de Comercio del Registro Mercantil de la República de Guatemala, quien en lo sucesivo se llamara <vb>"GRUPO KOTANH, S.A." o "el AGENTE";</vb> y  por la otra parte <vb>' . (($datos[0]['nomcli'] == '' || $datos[0]['nomcli'] == null) ? ' ' : utf8_decode(mb_strtoupper($datos[0]['nomcli'], 'utf-8'))) . '</vb>' .
        $cumpleañoscli . $estadocivilcli . $nacli . ', ' . $profcli . 'de este domicilio, y con residencia en ' . $residenciacli . ' del municipio de ' . $municli . ' departamento de ' . $depacli . ' me identifico con el Documento Personal de Identificación con Código Único de identificación  número ' .
        $dpiclitexto . ' (' . $dpicli . '), extendió por el Registro Nacional de las Personas de la República de Guatemala, quien en lo sucesivo me denominare <vb>"INVERSIONISTA". </vb>Los otorgantes manifestamos: A) Ser de las generales indicadas y encontrarnos en el libre ejercicio de nuestros derechos civiles. B) Que las representaciones que ejercitamos son suficientes de conformidad con la ley para la celebración de este contrato; y C) Que por el presente DOCUMENTO PRIVADO otorgamos <vb>CONTRATO DE INVERSION</vb> de conformidad con lo dispuesto en las siguientes cláusulas: <vb>PRIMERA:</vb> Yo Sebastián Juan Baltazar, en la calidad con que actuó manifiesto que mi representada <vb>GRUPO KOTANH, SOCIEDAD ANONIMA.</vb> Por el presente acto acepta la <vb>INVERSIÓN</vb> que desea hacer' .
        $gencli . '<vb>' . (($datos[0]['nomcli'] == '' || $datos[0]['nomcli'] == null) ? ' ' : utf8_decode(mb_strtoupper($datos[0]['nomcli'], 'utf-8'))) . '; SEGUNDA: OBJETO.</vb> Por el presente contrato de Inversión las partes manifestamos que estamos de acuerdo que <vb>KOTANH, S.A.,</vb> recibirá dinero del/la <vb>INVERSIONISTA,</vb> con el objeto de invertirlo por cuenta y riesgo de éstas, en el tiempo, forma y modo que se pactan más adelante, de manera sistemática y profesional la entidad <vb>KOTANH, S.A.</vb> inscrita, para poder después devolver al/a <vb>INVERSIONISTA</vb> el dinero y sus frutos si los hubiere, una vez liquidadas las inversiones; sin que exista un rendimiento mínimo garantizado al/a <vb>INVERSIONISTA</vb> y el <vb>AGENTE</vb> acuerdan que la remuneración que corresponde a éste último por la ejecución del presente contrato la podrá obtener el <vb>AGENTE</vb> directamente de las comisiones que cobre, por lo que el/la <vb>INVERSIONISTA</vb> no quedará obligada al pago de la comisión, salvo el caso en que el rendimiento de la inversión proveniente de este contrato fuera superior al porcentaje estipulado en él, en cuyo caso el <vb>AGENTE</vb> sí cobrará una comisión según lo estipulado. La inversión la realizará <vb>KOTHAN S. A.,</vb></p>');
    $pdf->WriteTag(0, 6, $texto, 0, "J", 0, 0);

    //DATOS COMPLEMENTARIOS

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

function calcularEdad($fechaNacimiento)
{
    try {
        $birthDate = new DateTime($fechaNacimiento);
        $currentDate = new DateTime();
        return $currentDate->diff($birthDate)->y;
    } catch (Exception $e) {
        return 0;
    }
}

function numeroATexto($numero)
{
    $unidades = [
        '',
        'uno',
        'dos',
        'tres',
        'cuatro',
        'cinco',
        'seis',
        'siete',
        'ocho',
        'nueve',
        'diez',
        'once',
        'doce',
        'trece',
        'catorce',
        'quince',
        'dieciséis',
        'diecisiete',
        'dieciocho',
        'diecinueve'
    ];

    $decenas = [
        '',
        '',
        'veinte',
        'treinta',
        'cuarenta',
        'cincuenta',
        'sesenta',
        'setenta',
        'ochenta',
        'noventa'
    ];

    $centenas = [
        '',
        'ciento',
        'doscientos',
        'trescientos',
        'cuatrocientos',
        'quinientos',
        'seiscientos',
        'setecientos',
        'ochocientos',
        'novecientos'
    ];

    if ($numero == 0) return 'cero';
    if ($numero < 20) return $unidades[$numero];
    if ($numero < 30) return $numero == 20 ? 'veinte' : 'veinti' . $unidades[$numero - 20];
    if ($numero < 100) return $decenas[intval($numero / 10)] . ($numero % 10 ? ' y ' . $unidades[$numero % 10] : '');
    if ($numero == 100) return 'cien';
    if ($numero < 1000) return $centenas[intval($numero / 100)] . ($numero % 100 ? ' ' . numeroATexto($numero % 100) : '');
    // Para miles
    if ($numero < 1000000) {
        $miles = intval($numero / 1000);
        $resto = $numero % 1000;
        $textoMiles = $miles == 1 ? 'mil' : numeroATexto($miles) . ' mil';
        return $resto > 0 ? $textoMiles . ' ' . numeroATexto($resto) : $textoMiles;
    }

    return (string)$numero;
}

// FUNCION PARA FORMATEAR DPI
function formatearDPI($dpi)
{
    // Asegurarse de que el DPI tenga 13 dígitos
    $dpi = str_pad($dpi, 13, '0', STR_PAD_LEFT);

    // Formato: XXXX XXXXX XXXX
    $parte1 = substr($dpi, 0, 4);   // Primeros 4 dígitos
    $parte2 = substr($dpi, 4, 5);   // Siguientes 5 dígitos
    $parte3 = substr($dpi, 9, 4);   // Últimos 4 dígitos

    return $parte1 . ' ' . $parte2 . ' ' . $parte3;
}

// FUNCION PARA CONVERTIR DPI A TEXTO
function dpiATexto($dpi)
{
    // Asegurarse de que el DPI tenga 13 dígitos
    $dpi = str_pad($dpi, 13, '0', STR_PAD_LEFT);

    // Dividir en las tres partes
    $parte1 = intval(substr($dpi, 0, 4));   // Primeros 4 dígitos
    $parte2 = intval(substr($dpi, 4, 5));   // Siguientes 5 dígitos
    $parte3 = intval(substr($dpi, 9, 4));   // Últimos 4 dígitos

    $texto1 = numeroATexto($parte1);
    $texto2 = numeroATexto($parte2);
    $texto3 = numeroATexto($parte3);

    return $texto1 . ', ' . $texto2 . ', ' . $texto3;
}