<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
// include __DIR__ . '/../../../../src/funcphp/func_gen.php';
require __DIR__ . '/../../../../fpdf/WriteTag.php';
require_once __DIR__ . '/../../../../vendor/autoload.php';

// include __DIR__ . '/../../../../includes/BD_con/db_con.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';

use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

// mysqli_set_charset($conexion, 'utf8');
// mysqli_set_charset($general, 'utf8');

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$datos = $_POST["datosval"];
$inputs = $datos[0];
$archivo = $datos[3];
$codcuenta = $archivo[0];

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$query1 = "SELECT cm.CCODCTA AS ccodcta, cm.DFecDsbls AS fecdesem, cm.TipoEnti AS formcredito, dest.DestinoCredito AS destinocred, cm.MontoSol AS montosol, cm.MonSug AS montoapro, cm.noPeriodo AS cuotas, tbp.nombre, tbc.Credito AS tipperiodo, cm.Dictamen AS dictamen,
            cl.idcod_cliente AS codcli, cl.short_name AS nomcli, 
            (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id=cl.depa_reside),'--')) AS nomdep, 
            (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id=cl.id_muni_reside),'--')) AS nommun, 
            cl.Direccion AS direccioncliente, cl.date_birth AS fecnacimiento, cl.no_identifica AS dpi, cl.estado_civil AS estcivil, 
            CONCAT((IFNULL((SELECT mun2.nombre FROM tb_municipios mun2 WHERE mun2.id=cl.id_muni_nacio),'--')),', ',(IFNULL((SELECT dep2.nombre FROM tb_departamentos dep2 WHERE dep2.id=cl.depa_nacio),'--'))) AS dirorigen,
            CONCAT((IFNULL((SELECT mun3.nombre FROM tb_municipios mun3 WHERE mun3.id=cl.id_muni_reside),'--')),', ',(IFNULL((SELECT dep3.nombre FROM tb_departamentos dep3 WHERE dep3.id=cl.depa_reside),'--')),', ',(IFNULL((cl.aldea_reside),'--'))) AS direside,
            cl.profesion AS profesion, cl.tel_no1 AS telefono, CONCAT((IFNULL(cl.Nomb_Ref1, 'NA')),', ',(IFNULL( cl.Tel_Ref1, 'NA'))) AS ref1, CONCAT((IFNULL(cl.Nomb_Ref2, 'NA')),', ',(IFNULL( cl.Tel_Ref2, 'NA'))) AS ref2, CONCAT((IFNULL(cl.Nomb_Ref3, 'NA')),', ',(IFNULL( cl.Tel_Ref3, 'NA'))) AS ref3, cl.no_tributaria AS nit,
            CONCAT(us.nombre,' ', us.apellido) AS analista,
            pr.id AS codprod, pr.nombre AS nomprod, pr.descripcion AS descprod, cm.NIntApro AS tasaprod, pr.porcentaje_mora AS mora, pr.tipo_mora AS tipmora, pr.tipo_calculo AS tipocalculo,
            ff.descripcion AS nomfondo, tbc.Credito AS formpago,
            (IFNULL((SELECT ppg2.ncapita FROM Cre_ppg ppg2 WHERE ppg2.ccodcta=cm.CCODCTA ORDER BY ppg2.dfecven ASC LIMIT 1),'x')) AS capitalppg,
            (IFNULL((SELECT ppg3.nintere FROM Cre_ppg ppg3 WHERE ppg3.ccodcta=cm.CCODCTA ORDER BY ppg3.dfecven ASC LIMIT 1),'x')) AS interesppg
            FROM cremcre_meta cm
            INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
            INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu
            INNER JOIN cre_productos pr ON cm.CCODPRD=pr.id
            INNER JOIN ctb_fuente_fondos ff ON pr.id_fondo=ff.id
            INNER JOIN $db_name_general.tb_destinocredito dest ON cm.Cdescre=dest.id_DestinoCredito
            INNER JOIN $db_name_general.tb_periodo tbp ON cm.NtipPerC=tbp.periodo
            INNER JOIN $db_name_general.tb_credito tbc ON cm.CtipCre=tbc.abre
            WHERE cm.Cestado in ('F','D','E') AND cm.CCODCTA=?
            GROUP BY tbp.periodo";


$query2 = "SELECT cl.idcod_cliente AS codcli, gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc, 
                gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
                IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS nomcli,
                IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS direccioncli,
                IFNULL((SELECT '1' AS marcado FROM tb_garantias_creditos tgc WHERE tgc.id_cremcre_meta=? AND tgc.id_garantia=gr.idGarantia),0) AS marcado,
                IFNULL((SELECT SUM(cli.montoGravamen) AS totalgravamen FROM tb_garantias_creditos tgc INNER JOIN cli_garantia cli ON cli.idGarantia=tgc.id_garantia WHERE tgc.id_cremcre_meta=? AND cli.estado=1),0) AS totalgravamen
                FROM tb_cliente cl
                INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
                INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc
                WHERE cl.estado='1' AND gr.estado=1 AND cl.idcod_cliente=?";

//BUSCAR GASTOS
$query3 = "SELECT cpg.id, cpg.id_producto, cpg.id_tipo_deGasto AS tipgasto, cpg.tipo_deCobro AS tipcobro, cpg.tipo_deMonto AS tipmonto, cpg.calculox AS calc, cpg.monto AS monto, ctg.nombre_gasto 
                FROM cre_productos_gastos cpg 
                INNER JOIN cre_tipogastos ctg ON cpg.id_tipo_deGasto=ctg.id
                WHERE cpg.estado=1 AND ctg.estado=1 AND cpg.tipo_deCobro='1' AND cpg.id_producto=?";

// $opResult = array('status' => 0, 'mensaje' => $query);
// echo json_encode($opResult);
// return;
$showmensaje = false;
try {
    $database->openConnection();
    $dataCredito = $database->getAllResults($query1, [$codcuenta]);
    if (empty($dataCredito)) {
        $showmensaje = true;
        throw new Exception("No se encontró la información del crédito");
    }

    $dataGarantia = $database->getAllResults($query2, [$codcuenta, $codcuenta, $dataCredito[0]['codcli']]);
    if (empty($dataGarantia)) {
        $showmensaje = true;
        throw new Exception("No se encontró la información de la garantía");
    }

    $dataGastos = $database->getAllResults($query3, [$dataCredito[0]['codprod']]);
    if (empty($dataGastos)) {
        // $showmensaje = true;
        // throw new Exception("No se encontró la información de los gastos");
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }

    if ($dataCredito[0]['capitalppg'] == 'x' || $dataCredito[0]['interesppg'] == 'x') {
        //LLAMAR A LA FUNCION PARA CAPINT
        $datos_sum = $utilidadesCreditos->creppg_temporal($dataCredito[0]['ccodcta'], $database, $db_name_general);
        $sumacapint = $datos_sum[0]['nintpag'] + $datos_sum[0]['ncappag'];
        // $datos_sum = creppg_temporal($datos[0]['ccodcta'], $conexion);
        // $sumacapint = $datos_sum[0]['nintpag'] + $datos_sum[0]['ncappag'];
    } else {
        $sumacapint = $dataCredito[0]['capitalppg'] + $dataCredito[0]['interesppg'];
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}


printpdf($dataCredito, $dataGarantia, $dataGastos, $info, $sumacapint);

function printpdf($datos, $garantias, $gastos, $info, $sumacapint)
{

    //FIN COMPROBACION
    $oficina = "Coban";
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
    $pdf->SetStyle("place", "arial", "U", 0, "0,0,0");
    $pdf->SetStyle("vb", $fuente, "B", 0, "0,0,0");

    //DICTAMEN DE CREDITO Y FONDOS
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, decode_utf8('Dictamen de crédito No.:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $datos[0]['dictamen'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, decode_utf8('Fondos Propios:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 60, $tamanio_linea, (($datos[0]['nomfondo'] == '' || $datos[0]['nomfondo'] == null) ? ' ' : decode_utf8($datos[0]['nomfondo'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(2);
    $pdf->Cell(0, 3, ' ', 'B', 1, 'C');
    $pdf->Ln(4);

    //DATOS DEL SOLICITANTE
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, '1. DATOS DEL SOLICITANTE:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Ln(6);
    //SUBINCISOS DEL SOLICITANTE
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('a) Código de crédito:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['ccodcta'] == '' || $datos[0]['ccodcta'] == null) ? ' ' : $datos[0]['ccodcta']), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('b) Nombre completo:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['nomcli'] == '' || $datos[0]['nomcli'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['nomcli'], 'utf-8'))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('c) Código de cliente:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['codcli'] == '' || $datos[0]['codcli'] == null) ? ' ' : decode_utf8($datos[0]['codcli'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('d) DPI:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['dpi'] == '' || $datos[0]['dpi'] == null) ? ' ' : decode_utf8($datos[0]['dpi'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('e) Fecha de nacimiento:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['fecnacimiento'] == '' || $datos[0]['fecnacimiento'] == null || $datos[0]['fecnacimiento'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fecnacimiento']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('f) Edad:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['fecnacimiento'] == '' || $datos[0]['fecnacimiento'] == null || $datos[0]['fecnacimiento'] == '0000-00-00') ? ' ' : (obtener_edad_segun_fecha($datos[0]['fecnacimiento']) . decode_utf8(' Años'))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('g) Estado civil:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['estcivil'] == '' || $datos[0]['estcivil'] == null) ? ' ' : decode_utf8($datos[0]['estcivil'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('h) Originario de:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['dirorigen'] == '' || $datos[0]['dirorigen'] == null) ? ' ' : decode_utf8($datos[0]['dirorigen'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('i) Residencia actual:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['direside'] == '' || $datos[0]['direside'] == null) ? ' ' : decode_utf8($datos[0]['direside'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('j) Profesión:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['profesion'] == '' || $datos[0]['profesion'] == null) ? ' ' : decode_utf8($datos[0]['profesion'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('k) Teléfono:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['telefono'] == '' || $datos[0]['telefono'] == null) ? ' ' : decode_utf8($datos[0]['telefono'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('l) Referencia familiar:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['ref1'] == '' || $datos[0]['ref1'] == null) ? ' ' : decode_utf8($datos[0]['ref1'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('m) Referencia comercial:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['ref2'] == '' || $datos[0]['ref2'] == null) ? ' ' : decode_utf8($datos[0]['ref2'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('m) Referencia bancaria:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['ref3'] == '' || $datos[0]['ref3'] == null) ? ' ' : decode_utf8($datos[0]['ref3'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('n) Identificación tributaria (NIT):'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['nit'] == '' || $datos[0]['nit'] == null) ? ' ' : decode_utf8($datos[0]['nit'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(7);

    // DESTINO DEL CREDITO
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('2) DESTINO DEL CRÉDTIO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['destinocred'] == '' || $datos[0]['destinocred'] == null) ? ' ' : decode_utf8($datos[0]['destinocred'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    // MONTO DEL PRESTAMOS
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('3) MONTO DEL PRÉSTAMO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['montoapro'] == '' || $datos[0]['montoapro'] == null) ? ' ' : 'Q ' . decode_utf8(number_format($datos[0]['montoapro'], 2, '.', ','))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    // FORMA DE DESEMBOLSO
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('4) FORMA DE DESEMBOLSO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, decode_utf8('Un desembolso después de la firma del contrato o escritura.'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    // <vb></vb>
    // FORMA DE PAGO
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('5) FORMA DE PAGO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $texto = "<p>Cuotas " . (($datos[0]['tipperiodo'] == '' || $datos[0]['tipperiodo'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['tipperiodo']))) . " " . (($datos[0]['nombre'] == '' || $datos[0]['nombre'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nombre']))) . "(ES) que incluyen capital e intereses por un valor de <vb>Q " . (number_format($sumacapint, 2, '.', ',')) . "</vb>.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    // TASA DE INTERES
    // (($datos[0]['intprod'] == '' || $datos[0]['intprod'] == null) ? ' ' : decode_utf8($datos[0]['intprod']) . '%')
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('6) TASA DE INTERÉS:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['tasaprod'] == '' || $datos[0]['tasaprod'] == null) ? ' ' : decode_utf8($datos[0]['tasaprod']) . '%'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    // PLAZO
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('7) PLAZO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['cuotas'] == '' || $datos[0]['cuotas'] == null) ? ' ' : decode_utf8($datos[0]['cuotas'])) . " CUOTAS " . (($datos[0]['nombre'] == '' || $datos[0]['nombre'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nombre']))) . "(ES) a partir del primer y " . decode_utf8('único') . " desembolso.", 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //GARANTIAS
    $pdf->SetFont($fuente, 'BU', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('8) GARANTÍAS'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    /* +++++++++++++++++++++++++++++++++ SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */
    //ENCABEZADO DE GARANTIAS
    $pdf->SetFillColor(204, 229, 255);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Tip. Garantia'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit($ancho_linea * 2.5, $tamanio_linea, ('Tip. Documento'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('Dirección'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, '', 9);
    foreach ($garantias as $key => $garantia) {
        if ($garantia['marcado'] == 1) {
            $direcciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"]==17)) ? $garantia['direccioncli'] : $garantia["direccion"];
            $descripciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"]==17)) ? "NOMBRE: " . $garantia['nomcli'] : "DESCRIPCION: " . $garantia["descripcion"];
            $pdf->CellFit($ancho_linea, $tamanio_linea, $garantia["nomtipgar"] ?? " ", 0, 0, 'L', 0, '', 0, 0);
            $pdf->CellFit($ancho_linea * 2.5, $tamanio_linea, $garantia["nomtipdoc"] ?? " ", 0, 0, 'C', 0, '', 0, 0);
            $pdf->CellFit(0, $tamanio_linea, (!empty($direcciongarantia) && $direcciongarantia !== null ? $direcciongarantia : " "), 0, 1, 'C', 0, '', 0, 0);
            $pdf->MultiCell(0, $tamanio_linea, decode_utf8($descripciongarantia), 'B', 'L', 0);
            $pdf->Ln(6);
        }
    }
    /* +++++++++++++++++++++++++++++++ FIN SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */

    // UBICACION DE CLIENTE
    $pdf->Ln(2);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('9) UBICACIÓN:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['direccioncliente'] == '' || $datos[0]['direccioncliente'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['direccioncliente']))) . (($datos[0]['nommun'] == '' || $datos[0]['nommun'] == null) ? ' ' : ", " . decode_utf8(strtoupper($datos[0]['nommun']))) . (($datos[0]['nomdep'] == '' || $datos[0]['nomdep'] == null) ? ' ' : ", " . decode_utf8(strtoupper($datos[0]['nomdep']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    //COMISION DE FORMALIZACION
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('10) DESCUENTOS:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    //GASTOS DEL CRÉDITO
    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 5);
    $banderafor = false;
    if (!empty($gastos)) {
        for ($i = 0; $i < count($gastos); $i++) {
            $tipomonto = (($gastos[$i]['tipmonto'] == 1) ? "" : "%");
            if ($gastos[$i]['calc'] == 1) {
                //GASTO FIJO
                $texto = "<p>10." . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " fijo(s) del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución ') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            } elseif ($gastos[$i]['calc'] == 2) {
                //GASTO POR PLAZO
                $texto = "<p>10." . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " sobre el plazo del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución ') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            } elseif ($gastos[$i]['calc'] == 3) {
                $texto = "<p>10." . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " sobre el plazo por el monto del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            } elseif ($gastos[$i]['calc'] == 4) {
                $texto = "<p>10." . ($i + 1) . ". " . (($gastos[$i]['nombre_gasto'] == '' || $gastos[$i]['nombre_gasto'] == null) ? ' ' : decode_utf8(strtoupper($gastos[$i]['nombre_gasto']))) . ": " . (($gastos[$i]['tipmonto'] == 1) ? "Q " : "") . ($gastos[$i]['monto']) . $tipomonto . " sobre el monto del " . decode_utf8('préstamo') . " a que se refiriere la presente " . decode_utf8('resolución') . ".</p>";
                $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
                $pdf->Ln(3);
                $banderafor = true;
            }
        }
    }

    if ($banderafor) {
        $pdf->Ln(1);
    } else {
        $pdf->Ln(2);
    }

    // CONDICIONES GENERALES
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 52, $tamanio_linea, decode_utf8('11) CONDICIONES GENERALES:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);
    //primera condicion
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetStyle("p", $fuente, "N", 10, "0,0,0", 0);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $texto = "<p>11.1. El atraso de una sola cuota de  <vb>" . decode_utf8($info[0]["nomb_comple"]) . "" . $vlrs[1] . "</vb> " . decode_utf8('podrá') . " dar por plazo vencido el contrato pagare o escritura y " . decode_utf8('solicitará') . " por la " . decode_utf8('vía') . " correspondiente el pago inmediato de los saldos del " . decode_utf8('crédito, así') . " como el pago de los intereses convenidos.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    // segunda condicion
    $mensajetipcalc = "";
    if ($datos[0]['tipocalculo'] == 1) {
        $mensajetipcalc = $datos[0]['mora'] . "% al total de la cuota vencida.";
    } elseif ($datos[0]['tipocalculo'] == 2) {
        $mensajetipcalc = $datos[0]['mora'] . "% al capital de la cuota vencida.";
    } elseif ($datos[0]['tipocalculo'] == 3) {
        $mensajetipcalc = $datos[0]['mora'] . "% al saldo de capital.";
    } elseif ($datos[0]['tipocalculo'] == 4) {
        $mensajetipcalc = "saldo de capital por un valor de Q. " . $datos[0]['mora'] . "";
    }
    if ($datos[0]['tipmora'] == 2) {
        $pdf->SetFont($fuente, '', $tamañofuente);
        $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
        $texto = "<p>11.2. El pago del " . decode_utf8('préstamo') . " se realizara en la oficinas de <vb>" . decode_utf8($info[0]["nomb_comple"]) . "" . $vlrs[1] . "</vb>  sin necesidad de cobro y un recargo al " . $mensajetipcalc . "</p>";
        $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
        $pdf->Ln(5);
    } elseif ($datos[0]['tipmora'] == 1) {
        $pdf->SetFont($fuente, '', $tamañofuente);
        $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
        $texto = "<p>11.2. Recargo por mora, si llegara a retrasar los pagos se realizara un recargo por mora del " . $mensajetipcalc . "</p>";
        $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
        $pdf->Ln(5);
    }

    //tercera condicion
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $texto = "<p> 11.3. En base al " . decode_utf8('análisis') . " efectuado a la " . decode_utf8('señor/a') . " <pers><vb>" . (($datos[0]['nomcli'] == '' || $datos[0]['nomcli'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['nomcli'], 'utf-8'))) . "</vb></pers> (deudor), posterior a la " . decode_utf8('obtención de la información') . " financiera, se " . decode_utf8('comprobó') . " que la diferencia entre sus ingresos y egreso, arroja un saldo solvente, " . decode_utf8('determinándose') . " que es mismo posee la capacidad suficiente para cubrir mediante cuotas mensuales el " . decode_utf8('crédtio') . " que solicita.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    // cuarta condicion
    $textdescgar = "";
    for ($i = 0; $i < count($garantias); $i++) {
        $resaltado = (($garantias[$i]['marcado'] == 1) ? 1 : 0);
        if ($resaltado == 1) {
            if ($i > 0) {
                $textdescgar .= ", ";
            }
            if ($garantias[$i]["idtipgar"] == 1 && $garantias[$i]["idtipdoc"] == 1) {
                $textdescgar .= (($garantias[$i]['nomcli'] == '' || $garantias[$i]['nomcli'] == null) ? ' ' : decode_utf8($garantias[$i]['nomcli'])) . "(fiador)";
            } else {
                $textdescgar .= (($garantias[$i]['descripcion'] == '' || $garantias[$i]['descripcion'] == null) ? ' ' : decode_utf8($garantias[$i]['descripcion']));
            }
        }
    }
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $texto = "<p> 11.4. Asimismo el financiamiento a obtener, " . decode_utf8('será') . " retornado mediante la " . decode_utf8('garantías') . " siguientes: " . $textdescgar . decode_utf8('; las cuales se mantendrán') . " vigentes hasta la " . decode_utf8('cancelación') . " total del financiamiento, " . decode_utf8('las cuales') . ", poseen la capacidad necesaria para crubrir cualquier saldo insoluto, que dejare la normal " . decode_utf8('recuperación') . " del mismo, tanto en capital como en intereses.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    //CONDICION APARTE DE GENERALES
    $pdf->SetFont($fuente, '', $tamañofuente);
    $texto = "<p> 12) Se aprueba la solicitud al " . decode_utf8('crédito') . " requerido, en virtud que dentro del reglamento <pers><vb>" . (($datos[0]['nomfondo'] == '' || $datos[0]['nomfondo'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nomfondo']))) . "</vb></pers> el mismo cumple con los requisitos exigidos para ser beneficiario a " . decode_utf8('través') . " del mismo, por lo que se eleva al respetable " . decode_utf8('comité de créditos para su aprobación') . " correspondiente.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    // CONCLUSION
    $pdf->SetFont($fuente, '', $tamañofuente);
    $texto = "<p> 13) En " . decode_utf8('conclusión el análisis') . " efectuado sobre la " . decode_utf8('información') . " financiera presentada por la empresaria/o: <pers><vb>" . (($datos[0]['nomcli'] == '' || $datos[0]['nomcli'] == null) ? ' ' : decode_utf8(mb_strtoupper($datos[0]['nomcli'], 'utf-8'))) . "</vb></pers> se eleva la solicitud de " . decode_utf8('préstamo al comité de créditos') . ", en las condiciones siguientes: </p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    //DETALLE CONCLUSION
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('a) MONTO DEL FINANCIAMIENTO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['montoapro'] == '' || $datos[0]['montoapro'] == null) ? ' ' : 'Q ' . decode_utf8(number_format($datos[0]['montoapro'], 2, '.', ','))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('b) TASA DE INTERÉS:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['tasaprod'] == '' || $datos[0]['tasaprod'] == null) ? ' ' : decode_utf8($datos[0]['tasaprod']) . '%'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea - 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 45, $tamanio_linea, decode_utf8('c) PLAZO AUTORIZADO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'BI', $tamañofuente);
    $pdf->CellFit($ancho_linea + 78, $tamanio_linea, (($datos[0]['cuotas'] == '' || $datos[0]['cuotas'] == null) ? ' ' : decode_utf8($datos[0]['cuotas'])) . " CUOTAS " . (($datos[0]['nombre'] == '' || $datos[0]['nombre'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nombre']))) . "(ES)", 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(7);

    // FINANCIAMIENTO
    $pdf->SetFont($fuente, '', $tamañofuente);
    $texto = "<p>14) Financiamiento a otorgarse con recursos del Fideicomiso fondo: <pers><vb>" . (($datos[0]['nomfondo'] == '' || $datos[0]['nomfondo'] == null) ? ' ' : decode_utf8(strtoupper($datos[0]['nomfondo']))) . "</vb></pers>.</p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(7);

    //FIRMAS
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, decode_utf8('APROBADO POR:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, decode_utf8('VISTO BUENO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(10);

    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, decode_utf8('ASESOR DE CRÉDITO:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, decode_utf8('GERENCIA:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(12);

    // OBSERVACIONES
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('OBSERVACIONES:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit($ancho_linea + 130, $tamanio_linea, ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit(0, $tamanio_linea, ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit(0, $tamanio_linea, ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->CellFit(0, $tamanio_linea, ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(9);

    // DIRECCION Y FECHA
    // <p>
    // <vb></vb>
    // </p>
    //<pers><vb></vb></pers>

    $pdf->SetStyle("p", $fuente, "N", 8, "0,0,0", 0);
    $texto = "<p>" . decode_utf8($info[0]["nomb_comple"]) . "" . $vlrs[1] . ", " . decode_utf8(strtoupper($info[0]["muni_lug"])) . ", GUATEMALA. <pers><vb>" . $fechahoy . "</vb></pers></p>";
    $pdf->WriteTag(0, 5, $texto, 0, "J", 0, 0);
    $pdf->Ln(5);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "Dictamen-" . (($datos[0]['ccodcta'] == '' || $datos[0]['ccodcta'] == null) ? ' ' : ($datos[0]['ccodcta'])),
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

function obtener_edad_segun_fecha($fecha_nacimiento)
{
    $nacimiento = new DateTime($fecha_nacimiento);
    $ahora = new DateTime(date("Y-m-d"));
    $diferencia = $ahora->diff($nacimiento);
    return $diferencia->format("%y");
}
