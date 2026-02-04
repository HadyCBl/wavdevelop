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
    cl.idcod_cliente AS codcli, cl.short_name AS nomcli, cl.Direccion AS direccioncliente,
    CONCAT(us.nombre,' ', us.apellido) AS analista,cm.NtipPerC,
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
            parent::__construct('P', 'mm', 'Letter');
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
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
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

    //NUMERO DE CREDITO Y ANALISTA
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('No. Crédito:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $datos[0]['ccodcta'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Responsable:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, (($datos[0]['analista'] == '' || $datos[0]['analista'] == null) ? ' ' : decode_utf8($datos[0]['analista'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(10);

    // <p>
    // <vb></vb>
    // </p>
    //<pers></pers>

    //PRIMER PARRAFO
    $pdf->SetFont($fuente, 'B', $tamañofuente - 1);
    $texto = "<p><pers><vb>EL COMITE DE CREDITO DE LA " . decode_utf8($info[0]["nomb_comple"]) . "" . $vlrs[1] . ", CON SEDE EN " . decode_utf8(strtoupper($info[0]["muni_lug"])) . ", DE LA CIUDAD DE GUATEMALA.</vb></pers>Con base al reglamento general de " . decode_utf8('créditos') . " de " . decode_utf8($vlrs[1]) . " y el dictamen No. " . (($datos[0]['dictamen'] == '' || $datos[0]['dictamen'] == null) ? ' ' : decode_utf8($datos[0]['dictamen'])) . " del departamento de " . decode_utf8('créditos') . " con fecha " . $fechahoy . "; el " . decode_utf8('cómite') . " de " . decode_utf8('créditos') . " con la facultad que le confiere.--------</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    //DATOS COMPLEMENTARIOS

    //TITULO
    $pdf->SetFont($fuente, 'IBU', $tamañofuente);
    $pdf->Cell(0, 3, 'RESUELVE', 0, 1, 'C');
    $pdf->Ln(3);

    //NOMBRE CLIENTE Y FONDO
    $pdf->SetFont($fuente, 'B', $tamañofuente - 1);
    $texto = "<p><pers>AUTORIZAR " . decode_utf8('CRÉDITO') . " A <vb>" . (($datos[0]['nomcli'] == '' || $datos[0]['nomcli'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['nomcli'], 'utf-8'))) . ",</vb> CON RECURSOS PROVENIENTES DEL FONDO: <vb>" . (($datos[0]['nomfondo'] == '' || $datos[0]['nomfondo'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nomfondo']))) . ".</vb></pers></p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(4);

    //MODALIDAD Y TIPO DE OPERACION
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('MODALIDAD'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, (($datos[0]['nomprod'] == '' || $datos[0]['nomprod'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nomprod']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(8);
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('TIPO DE OPERACIÓN'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $tipocredito = "CRÉDITO INDIVIDUAL";
    if ($datos[0]['formcredito'] != '' || $datos[0]['formcredito'] != null) {
        if ($datos[0]['formcredito'] != 'INDI') {
            $tipocredito = "CRÉDITO GRUPAL";
        }
    }
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, decode_utf8($tipocredito), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(8);

    //DESTINO
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('DESTINO'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, (($datos[0]['destinocred'] == '' || $datos[0]['destinocred'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['destinocred']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(8);

    //GARANTIAS
    $pdf->SetFont($fuente, 'BIU', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('GARANTÍAS'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    /* +++++++++++++++++++++++++++++++++ SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */
    //ENCABEZADO DE GARANTIAS
    $pdf->SetFillColor(204, 229, 255);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Tip. Garantia'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit($ancho_linea * 2, $tamanio_linea, ('Tip. Documento'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit($ancho_linea * 2.5, $tamanio_linea, decode_utf8('Dirección'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, ('Mon. Gravamen'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, '', 9);
    foreach ($garantias as $key => $garantia) {
        // if ($garantia['marcado'] == 1) {
        $direcciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"] == 17)) ? (($garantia['direccioncli'] == "") ? " " : $garantia['direccioncli']) : $garantia["direccion"];
        $descripciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"] == 17)) ? "NOMBRE: " . $garantia['nomcli'] : "DESCRIPCION: " . $garantia["descripcion"];
        $pdf->CellFit($ancho_linea, $tamanio_linea, $garantia["nomtipgar"] ?? " ", 0, 0, 'L', $garantia['marcado'], '', 0, 0);
        $pdf->CellFit($ancho_linea * 2, $tamanio_linea, $garantia["nomtipdoc"] ?? " ", 0, 0, 'C', $garantia['marcado'], '', 0, 0);
        $pdf->CellFit($ancho_linea * 2.5, $tamanio_linea, ($direcciongarantia == '' || $direcciongarantia == null) ? ' ' : $direcciongarantia, 0, 0, 'C', $garantia['marcado'], '', 0, 0);
        $pdf->CellFit(0, $tamanio_linea, number_format(($garantia["montogravamen"] ?? 0), 2), 0, 1, 'R', $garantia['marcado'], '', 0, 0);
        $pdf->MultiCell(0, $tamanio_linea, decode_utf8($descripciongarantia), 'B', 'L', $garantia['marcado']);
        $pdf->Ln(6);
        // }
    }
    /* +++++++++++++++++++++++++++++++ FIN SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */
    //UBICACION DE CLIENTE
    $pdf->Ln(3);
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('UBICACIÓN'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 130, $tamanio_linea, (($datos[0]['direccioncliente'] == '' || $datos[0]['direccioncliente'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['direccioncliente']))) . (($datos[0]['nommun'] == '' || $datos[0]['nommun'] == null) ? ' ' : ", " . decode_utf8(strtoupper($datos[0]['nommun']))) . (($datos[0]['nomdep'] == '' || $datos[0]['nomdep'] == null) ? ' ' : ", " . decode_utf8(strtoupper($datos[0]['nomdep']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(8);

    //FORMA DE ENTREGA

    $texto = "<p>FORMA DE ENTREGA: Posteriormente de firmado el respectivo contrato, pagare o escritura del " . decode_utf8('préstamo') . " a que se refiere la presente " . decode_utf8('resolución') . " se procedera " . decode_utf8('así:') . " <vb><pers>Directamente al solicitante en un sola entrega mediante documento legal de pago (cheque) a nombre de " . (($datos[0]['nomcli'] == '' || $datos[0]['nomcli'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['nomcli'], 'utf-8'))) . "</pers></vb> por la cantidad de Q. <vb>" . (($datos[0]['montoapro'] == '' || $datos[0]['montoapro'] == null) ? ' ' : decode_utf8(number_format($datos[0]['montoapro'], 2, '.', ','))) . ".</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(4);

    //TOTAL A DESEMBOLSAR
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea + 20, $tamanio_linea, decode_utf8('TOTAL A DESEMBOLSAR:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 110, $tamanio_linea, "Q " . (($datos[0]['montoapro'] == '' || $datos[0]['montoapro'] == null) ? ' ' : decode_utf8(number_format($datos[0]['montoapro'], 2, '.', ','))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(8);

    //PLAZO
    $texto = "<p>PLAZO: <vb><pers>" . (($datos[0]['cuotas'] == '' || $datos[0]['cuotas'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['cuotas']))) . " CUOTAS </pers></vb>contados a partir de la fecha del desembolso de los fondos del " . decode_utf8('crédito') . ".</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(4);

    //TASA DE INTERES
    $texto = "<p>TASA DE " . decode_utf8('INTERÉS: ') . "<vb><pers>" . (($datos[0]['tasaprod'] == '' || $datos[0]['tasaprod'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['tasaprod']))) . "% </pers></vb></p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(4);

    //RECARGO POR MORA
    $texto = "<p>RECARGO POR MORA: De no cancelarse los intereses en la fecha " . decode_utf8('señalada') . ", la <vb>" . decode_utf8($info[0]["nomb_comple"]) . "" . $vlrs[1] . "</vb>, cobrara un recargo anual sobre los intereses vencidos del <vb>" . (($datos[0]['mora'] == '' || $datos[0]['mora'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['mora']))) . "%</vb> sobre la tasa vigente los cuales deberan computarse a partir del primer dia respectivo del vencimiento.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(4);

    //FORMA DE RECUPERACION
    $texto = "<p>FORMA DE " . decode_utf8('RECUPERACIÓN: ') . "";
    //recuperar el primer pago o plazo
    $sumacapint = 0;
    if ($datos[0]['capitalppg'] == 'x' || $datos[0]['interesppg'] == 'x') {
        //LLAMAR A LA FUNCION PARA CAPINT
        $datos_sum = creppg_temporal($datos[0]['ccodcta'], $conexion);
        $sumacapint = $datos_sum[0]['nintpag'] + $datos_sum[0]['ncappag'];
    } else {
        $sumacapint = $datos[0]['capitalppg'] + $datos[0]['interesppg'];
        $datos_sum = creppg_get($datos[0]['ccodcta'], $conexion);
    }
    //CUOTAS PPG 
    $idcuotas = array_keys(array_unique(array_column($datos_sum, "cuota")));
    foreach ($idcuotas as $idp) {
        $condicion = $datos_sum[$idp]['cuota'];
        $cant = count(array_filter(array_column($datos_sum, 'cuota'), function ($var) use ($condicion) {
            return ($var == $condicion);
        }));
        $cuotatxt = ($cant > 1) ? ' CUOTAS ' : ' CUOTA ';
        $texto .= "<vb><pers>" . $cant . $cuotatxt . (($datos[0]['nombre'] == '' || $datos[0]['nombre'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nombre'])));
        $texto .= ($cant > 1) ? (($datos[0]['NtipPerC'] == "1D") ? 'S' : 'ES ') : ' ';
        $texto .= "</pers></vb> que incluye el capital e intereses por <vb>Q " . number_format(($condicion), 2, '.', ',') . "</vb>";
        $texto .= ($cant > 1) ? ' cada una, ' : ', ';
    }
    $texto .= " a partir de la fecha de desembolso de los fondos del " . decode_utf8('crédito') . ".</p>";
    //FIN CUOTAS PPG
    //$texto = "<p>FORMA DE " . decode_utf8('RECUPERACIÓN: ') . "<vb><pers>" . (($datos[0]['cuotas'] == '' || $datos[0]['cuotas'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['cuotas']))) . " CUOTAS " . (($datos[0]['nombre'] == '' || $datos[0]['nombre'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nombre']))) . "ES</pers></vb> que incluye el capital e intereses por <vb>Q " . ($sumacapint) . "</vb> cada una,  a partir de la fecha de desembolso de los fondos del " . decode_utf8('crédito') . ".</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(4);

    //COMISION DE FORMALIZACION
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('DESCUENTOS:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    //GASTOS DEL CRÉDITO
    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 5);
    $banderafor = false;
    if ($flag3) {
        for ($i = 0; $i < count($gastos); $i++) {
            $tipomonto = (($gastos[$i]['tipmonto'] == 1) ? "" : "%");
            if ($gastos[$i]['calc'] == 1) {
                //GASTO FIJO
                $texto = "<p>" . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " fijo(s) del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución ') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            } elseif ($gastos[$i]['calc'] == 2) {
                //GASTO POR PLAZO
                $texto = "<p>" . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " sobre el plazo del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución ') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            } elseif ($gastos[$i]['calc'] == 3) {
                $texto = "<p>" . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " sobre el plazo por el monto del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            } elseif ($gastos[$i]['calc'] == 4) {
                $texto = "<p>" . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " sobre el monto del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            }
        }
    }

    if ($banderafor) {
        $pdf->Ln(1);
    } else {
        $pdf->Ln(4);
    }

    //OTRAS CONDICIONES
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('OTRAS CONDICIONES:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    //condicion a
    $texto = "<p>a) El solicitante se compromete a presentar cualquier otra " . decode_utf8('información') . " que la " . decode_utf8($info[0]["nomb_comple"]) . "" . $vlrs[1] . ", requiera (contable, financiera, administrativa, social o legal) cuando se realicen las inspecciones de campo y se verifique la correcta " . decode_utf8('inversión') . " de los fondos de acuerdo al destino indicado.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(3);

    //condicion b
    $texto = "<p>b) La presente " . decode_utf8('resolución') . " tendra un plazo de SESENTA DIAS (60) " . decode_utf8('hábiles') . " para la " . decode_utf8('formalización') . " contados a partir de la fecha de " . decode_utf8('notificación') . " al solicitante.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(4);

    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 0);
    $texto = "<p>" . decode_utf8(strtoupper($info[0]["muni_lug"])) . ",CIUDAD DE GUATEMALA, GUATEMALA, " . strtoupper($fechahoy) . "</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(16);

    //LUGAR DE FIRMAS
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'Presidente', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 26, $tamanio_linea, 'Secretario', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 27, $tamanio_linea, 'Vocal', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(6);

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
