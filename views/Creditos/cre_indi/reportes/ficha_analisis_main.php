<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
require '../../../../fpdf/fpdf.php';
require '../../../../vendor/autoload.php';
require __DIR__ . "/../../../../src/funcphp/func_gen.php";

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
$strquery = "SELECT cl.idcod_cliente AS id, cl.idcod_cliente AS codcli, cl.no_identifica AS dpi, cl.no_tributaria AS nit, cl.short_name AS nombre, cl.date_birth AS fechacumple, cl.estado_civil AS civil,  
    cl.Direccion AS direccion, 
    (IFNULL((SELECT dep.nombre FROM tb_departamentos dep WHERE dep.id=cl.depa_reside),'-')) AS departamento, 
    (IFNULL((SELECT mun.nombre FROM tb_municipios mun WHERE mun.id=cl.id_muni_reside),'-')) AS municipio, 
    cl.aldea_reside AS dirresidencia, cl.tel_no1 AS telcliente1, cl.tel_no2 AS telcliente2, cl.profesion AS profesion,
    CONCAT(usu.nombre,' ', usu.apellido) AS analista, 
    (IFNULL((SELECT ing.fecha_labor FROM tb_ingresos ing WHERE ing.Tipo_ingreso=1 AND ing.id_cliente=cl.idcod_cliente LIMIT 1),'-')) AS fechanegocio, 
    (IFNULL((SELECT ing.direc_negocio FROM tb_ingresos ing WHERE ing.Tipo_ingreso=1 AND ing.id_cliente=cl.idcod_cliente LIMIT 1),'-')) AS direcnegocio, 
    (IFNULL((SELECT dep2.nombre FROM tb_ingresos ing INNER JOIN tb_departamentos dep2 ON ing.depa_negocio=dep2.id WHERE ing.Tipo_ingreso=1 AND ing.id_cliente=cl.idcod_cliente LIMIT 1),'-')) AS depnegocio, 
    (IFNULL((SELECT mun2.nombre FROM tb_ingresos ing INNER JOIN tb_municipios mun2 ON ing.muni_negocio=mun2.codigo WHERE ing.Tipo_ingreso=1 AND ing.id_cliente=cl.idcod_cliente LIMIT 1),'-')) AS muninegocio,
    cm.CCODCTA AS ccodcta, cm.MontoSol AS montosol, cm.MonSug AS montoapro, (SELECT IFNULL(MAX(cm2.NCiclo),0)+1 AS ciclo FROM cremcre_meta cm2 WHERE cm2.CodCli=cm.CodCli AND cm2.TipoEnti='INDI' AND (cm2.Cestado='F' OR cm2.Cestado='G')) AS ciclo,
    cm.DfecPago AS primerpago, cm.DFecDsbls AS fechadesembolso, cm.noPeriodo AS cuotas, cm.Dictamen AS dictamen, tbc.Credito AS tipcredito, tbp.nombre AS tipperiodo,
    dest.DestinoCredito AS destinocred, sect.SectoresEconomicos AS sececonomico, act.Titulo AS acteconomico,
    pd.cod_producto AS codprod, pd.nombre AS nomprod, pd.descripcion AS descprod, cm.NIntApro AS intprod, pd.monto_maximo AS monprod , ff.descripcion AS fondoprod, 
    IF(cm.Cestado='A', 'SOLICITADO', IF(cm.Cestado='D', 'ANALIZADO', IF(cm.Cestado='E', 'APROBADO', 'DESEMBOLSADO'))) AS estado, DATE(cm.DfecSol) AS fecsol,  DATE(cm.DFecAnal) AS fecanal, DATE(cm.DFecApr) AS fecapro
    FROM cremcre_meta cm
    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
    INNER JOIN tb_usuario usu ON cm.CodAnal=usu.id_usu
    INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id
    INNER JOIN ctb_fuente_fondos ff ON pd.id_fondo=ff.id
    INNER JOIN $db_name_general.tb_destinocredito dest ON cm.Cdescre=dest.id_DestinoCredito
    INNER JOIN $db_name_general.tb_sectoreseconomicos sect ON cm.CSecEco=sect.id_SectoresEconomicos
    INNER JOIN $db_name_general.tb_ActiEcono act ON cm.ActoEcono=act.id_ActiEcono
    INNER JOIN $db_name_general.tb_credito tbc ON cm.CtipCre=tbc.abre
    INNER JOIN $db_name_general.tb_periodo tbp ON cm.NtipPerC=tbp.periodo
    WHERE cm.CCODCTA='$codcredito'
    GROUP BY tbp.periodo";
$query = mysqli_query($conexion, $strquery);
$data[] = [];
$j = 0;
$flag = false;
$codcli = "";
while ($fila = mysqli_fetch_array($query, MYSQLI_ASSOC)) {
    $data[$j] = $fila;
    $codcli = $fila['codcli'];
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

//BUSCAR DATOS DE INSTITUCION
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
$info[] = [];
$j = 0;
$flag3 = false;
while ($fil = mysqli_fetch_array($queryins, MYSQLI_ASSOC)) {
    $info[$j] = $fil;
    $flag3 = true;
    $j++;
}

//COMPROBACION: SI SE ENCONTRARON REGISTROS
if (!$flag || !$flag2 || !$flag3) {
    $opResult = array(
        'status' => 0,
        'mensaje' => 'No se encontraron datos, o no se cargaron algunos datos correctamente, intente nuevamente',
        'dato' => $strquery
    );
    echo json_encode($opResult);
    return;
}

printpdf($data, $garantias, $info, $conexion);

function printpdf($datos, $garantias, $info, $conexion)
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
    class PDF extends FPDF
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

            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');
            // Salto de línea
            $this->Ln(h: 1);
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
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 4;
    $ancho_linea = 30;
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell(0, $tamanio_linea, decode_utf8("FICHA DE ANÁLISIS DE CRÉDITO INDIVIDUAL"), 0, 1, 'C');
    $pdf->Cell(0, $tamanio_linea-3, ' ', 'B', 1, 'C');
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(0, $tamanio_linea, "DATOS PERSONALES DEL CLIENTE", 0, 0, 'C');
    $pdf->Ln(5);

    //INGRESO DE DATOS PERSONALES
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Nombre:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['nombre'] == '' || $datos[0]['nombre'] == null) ? ' ' : decode_utf8($datos[0]['nombre'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Dirección:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['direccion'] == '' || $datos[0]['direccion'] == null) ? ' ' : decode_utf8($datos[0]['direccion'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'CUI:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['dpi'] == '' || $datos[0]['dpi'] == null) ? ' ' : $datos[0]['dpi']), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Municipio:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['municipio'] == '' || $datos[0]['municipio'] == null) ? ' ' : decode_utf8($datos[0]['municipio'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'NIT:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['nit'] == '' || $datos[0]['nit'] == null) ? ' ' : ($datos[0]['nit'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Departamento:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['departamento'] == '' || $datos[0]['departamento'] == null) ? ' ' : decode_utf8($datos[0]['departamento'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Cod. cliente:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, $datos[0]['codcli'], 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Analista:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['analista'] == '' || $datos[0]['analista'] == null) ? ' ' : decode_utf8($datos[0]['analista'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Fec. Nacimiento:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fechacumple'] == '' || $datos[0]['fechacumple'] == null || $datos[0]['fechacumple'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fechacumple']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Fec. Negocio:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fechanegocio'] == '-' || $datos[0]['fechanegocio'] == null || $datos[0]['fechanegocio'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fechanegocio']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Estado civil:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['civil'] == '' || $datos[0]['civil'] == null) ? ' ' : decode_utf8($datos[0]['civil'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Dir. Negocio:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['direcnegocio'] == '' || $datos[0]['direcnegocio'] == null) ? ' ' : decode_utf8($datos[0]['direcnegocio'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Teléfono 1:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['telcliente1'] == '' || $datos[0]['telcliente1'] == null) ? ' ' : ($datos[0]['telcliente1'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Municipio:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['muninegocio'] == '' || $datos[0]['muninegocio'] == null) ? ' ' : decode_utf8($datos[0]['muninegocio'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Teléfono 2:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['telcliente2'] == '' || $datos[0]['telcliente2'] == null) ? ' ' : ($datos[0]['telcliente2'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Departamento:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['depnegocio'] == '' || $datos[0]['depnegocio'] == null) ? ' ' : decode_utf8($datos[0]['depnegocio'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Profesión:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['profesion'] == '' || $datos[0]['profesion'] == null) ? ' ' : decode_utf8($datos[0]['profesion'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(2);

    //SECCION DE CREDITO SOLICITADO
    $pdf->Cell(0, $tamanio_linea, ' ', 'B', 1, 'C');
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell(0, $tamanio_linea, decode_utf8("DATOS DEL CRÉDITO EN ESTADO DE ANÁLISIS"), 0, 0, 'C');
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, decode_utf8('Código crédito:'), 0, 0, 'R', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 10, $tamanio_linea, (($datos[0]['ccodcta'] == '' || $datos[0]['ccodcta'] == null) ? ' ' : ($datos[0]['ccodcta'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 25, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(2);

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('DATOS DEL PRODUCTO'), 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Cod. Producto:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['codprod'] == '' || $datos[0]['codprod'] == null) ? ' ' : ($datos[0]['codprod'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Nombre:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['nomprod'] == '' || $datos[0]['nomprod'] == null) ? ' ' : decode_utf8($datos[0]['nomprod'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Descripción:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['descprod'] == '' || $datos[0]['descprod'] == null) ? ' ' : decode_utf8($datos[0]['descprod'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Interés anual:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['intprod'] == '' || $datos[0]['intprod'] == null) ? ' ' : decode_utf8($datos[0]['intprod']) . '%'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Mon. Máximo:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['monprod'] == '' || $datos[0]['monprod'] == null) ? ' ' : decode_utf8(number_format($datos[0]['monprod'], 2, '.', ','))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Fuente Fondo:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fondoprod'] == '' || $datos[0]['fondoprod'] == null) ? ' ' : decode_utf8($datos[0]['fondoprod'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('DATOS COMPLEMENTARIOS'), 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Mon. Solicitado:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['montosol'] == '' || $datos[0]['montosol'] == null) ? ' ' : (number_format($datos[0]['montosol'], 2, '.', ','))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Monto aprobado:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['montoapro'] == '' || $datos[0]['montoapro'] == null) ? ' ' : (number_format($datos[0]['montoapro'], 2, '.', ','))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Tip. Crédito:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['tipcredito'] == '' || $datos[0]['tipcredito'] == null) ? ' ' : decode_utf8($datos[0]['tipcredito'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Tip. Perido:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['tipperiodo'] == '' || $datos[0]['tipperiodo'] == null) ? ' ' : decode_utf8($datos[0]['tipperiodo'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Fec. Primer pago:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['primerpago'] == '' || $datos[0]['primerpago'] == null || $datos[0]['primerpago'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['primerpago']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Fec. Desembolso:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fechadesembolso'] == '' || $datos[0]['fechadesembolso'] == null || $datos[0]['fechadesembolso'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fechadesembolso']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('No. Cuotas:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['cuotas'] == '' || $datos[0]['cuotas'] == null) ? ' ' : ($datos[0]['cuotas'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Dictamen:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['dictamen'] == '' || $datos[0]['dictamen'] == null) ? ' ' : decode_utf8($datos[0]['dictamen'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Ciclo:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['ciclo'] == '' || $datos[0]['ciclo'] == null) ? ' ' : ($datos[0]['ciclo'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Destino:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['destinocred'] == '' || $datos[0]['destinocred'] == null) ? ' ' : decode_utf8($datos[0]['destinocred'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Sector Económico:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['sececonomico'] == '' || $datos[0]['sececonomico'] == null) ? ' ' : decode_utf8($datos[0]['sececonomico'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Act. Económica:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['acteconomico'] == '' || $datos[0]['acteconomico'] == null) ? ' ' : decode_utf8($datos[0]['acteconomico'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Estado:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['estado'] == '' || $datos[0]['estado'] == null) ? ' ' : decode_utf8($datos[0]['estado'])), 0, 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 20, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, 'Fec. Solicitud:', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fecsol'] == '' || $datos[0]['fecsol'] == null || $datos[0]['fecsol'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fecsol']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Fec. Análisis:'), 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 30, $tamanio_linea, (($datos[0]['fecanal'] == '' || $datos[0]['fecanal'] == null || $datos[0]['fecanal'] == '0000-00-00') ? ' ' : date("d-m-Y", strtotime($datos[0]['fecanal']))), 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);


    //GARANTIAS
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('GARANTIAS DEL CRÉDITO'), 'B', 0, 'L', 0, '', 1, 0);
    $pdf->Ln(5);
    /* +++++++++++++++++++++++++++++++++ SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */
    //ENCABEZADO DE GARANTIAS
    $pdf->SetFillColor(204, 229, 255);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea, $tamanio_linea, ('Tip. Garantia'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit($ancho_linea * 2, $tamanio_linea, ('Tip. Documento'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit($ancho_linea * 2.5, $tamanio_linea, decode_utf8('Dirección'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, ('Mon. Gravamen'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->Ln(5);
    $pdf->SetFont($fuente, '', 7);
    foreach ($garantias as $key => $garantia) {
        // if ($garantia['marcado'] == 1) {
        $direcciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"]==17)) ? (($garantia['direccioncli'] == "") ? " " : $garantia['direccioncli']) : $garantia["direccion"];
        $descripciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"]==17)) ? "NOMBRE: " . $garantia['nomcli'] : "DESCRIPCION: " . $garantia["descripcion"];
        $pdf->CellFit($ancho_linea, $tamanio_linea, $garantia["nomtipgar"] ?? " ", 0, 0, 'L', $garantia['marcado'], '', 0, 0);
        $pdf->CellFit($ancho_linea * 2, $tamanio_linea, $garantia["nomtipdoc"] ?? " ", 0, 0, 'C', $garantia['marcado'], '', 0, 0);
        $pdf->CellFit($ancho_linea * 2.5, $tamanio_linea, ($direcciongarantia == '' || $direcciongarantia == null) ? ' ' : $direcciongarantia, 0, 0, 'C', $garantia['marcado'], '', 0, 0);
        $pdf->CellFit(0, $tamanio_linea, number_format(($garantia["montogravamen"] ?? 0), 2), 0, 1, 'R', $garantia['marcado'], '', 0, 0);
        $pdf->MultiCell(0, $tamanio_linea, decode_utf8($descripciongarantia), 'B', 'L', $garantia['marcado']);
        $pdf->Ln(5);
        // }
    }
    /* +++++++++++++++++++++++++++++++ FIN SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */

    //ENCABEZADO
    // $pdf->SetFont($fuente, 'B', 8);
    // $pdf->CellFit($ancho_linea, $tamanio_linea, decode_utf8('Tip. Garantia'), 'B', 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea + 5, $tamanio_linea, decode_utf8('Tip. Documento'), 'B', 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea + 30, $tamanio_linea, decode_utf8('Descripción'), 'B', 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea + 10, $tamanio_linea, decode_utf8('Dirección'), 'B', 0, 'C', 0, '', 1, 0);
    // $pdf->CellFit($ancho_linea - 5, $tamanio_linea, decode_utf8('Mon. Gravamen'), 'B', 0, 'C', 0, '', 1, 0);
    // $pdf->Ln(6);

    // //CICLO PARA IMPRESION DE GARANTIAS
    // $pdf->SetFont($fuente, '', 8);
    // for ($i = 0; $i < count($garantias); $i++) {
    //     $pdf->SetFillColor(204, 229, 255);
    //     $resaltado = (($garantias[$i]['marcado'] == 1) ? 1 : 0);
    //     $x = $pdf->GetX();
    //     $xinicial = $pdf->GetX();
    //     $yfinal = 0;
    //     $yinicial = $pdf->GetY();
    //     //tipo garantia
    //     $pdf->CellFit($ancho_linea, $tamanio_linea, (($garantias[$i]['nomtipgar'] == '' || $garantias[$i]['nomtipgar'] == null) ? ' ' : decode_utf8($garantias[$i]['nomtipgar'])), 0, 0, 'L', $resaltado, '', 0, 0);
    //     $x = $x + ($ancho_linea);
    //     $yaux = $pdf->GetY();
    //     $yfinal = $pdf->GetY();

    //     if ($yfinal < $yaux) {
    //         $yfinal = $pdf->GetY();
    //     }
    //     $pdf->SetXY($x, $yinicial);
    //     $x = $pdf->GetX();

    //     //tipo documento
    //     $pdf->MultiCell($ancho_linea + 5, $tamanio_linea, (($garantias[$i]['nomtipdoc'] == '' || $garantias[$i]['nomtipdoc'] == null) ? ' ' : decode_utf8($garantias[$i]['nomtipdoc'])), 0, 'L', $resaltado);
    //     $x = $x + ($ancho_linea + 5);
    //     $yaux = $pdf->GetY();

    //     if ($yfinal < $yaux) {
    //         $yfinal = $pdf->GetY();
    //     }
    //     $pdf->SetXY($x, $yinicial);
    //     $x = $pdf->GetX();
    //     if ($garantias[$i]["idtipgar"] == 1 && $garantias[$i]["idtipdoc"] == 1) {
    //         //nomcliente cuando es fiador
    //         $pdf->MultiCell($ancho_linea + 30, $tamanio_linea, (($garantias[$i]['nomcli'] == '' || $garantias[$i]['nomcli'] == null) ? ' ' : decode_utf8($garantias[$i]['nomcli'])), 0, 'L', $resaltado);
    //         $x = $x + ($ancho_linea + 30);
    //         $yaux = $pdf->GetY();

    //         if ($yfinal < $yaux) {
    //             $yfinal = $pdf->GetY();
    //         }
    //         $pdf->SetXY($x, $yinicial);
    //         $x = $pdf->GetX();

    //         //direccion cuando es fiador
    //         $pdf->MultiCell($ancho_linea + 10, $tamanio_linea, (($garantias[$i]['direccioncli'] == '' || $garantias[$i]['direccioncli'] == null) ? ' ' : decode_utf8($garantias[$i]['direccioncli'])), 0, 'L', $resaltado);
    //         $x = $x + ($ancho_linea + 10);
    //         $yaux = $pdf->GetY();

    //         if ($yfinal < $yaux) {
    //             $yfinal = $pdf->GetY();
    //         }
    //         $pdf->SetXY($x, $yinicial);
    //         $x = $pdf->GetX();
    //     } else {
    //         //descripcion cuando no es fiador
    //         $pdf->MultiCell($ancho_linea + 30, $tamanio_linea, (($garantias[$i]['descripcion'] == '' || $garantias[$i]['descripcion'] == null) ? ' ' : decode_utf8($garantias[$i]['descripcion'])), 0, 'L', $resaltado);
    //         $x = $x + ($ancho_linea + 30);
    //         $yaux = $pdf->GetY();

    //         if ($yfinal < $yaux) {
    //             $yfinal = $pdf->GetY();
    //         }
    //         $pdf->SetXY($x, $yinicial);
    //         $x = $pdf->GetX();
    //         //direccion cuando
    //         $pdf->MultiCell($ancho_linea + 10, $tamanio_linea, (($garantias[$i]['direccion'] == '' || $garantias[$i]['direccion'] == null) ? ' ' : decode_utf8($garantias[$i]['direccion'])), 0, 'L', $resaltado);
    //         $x = $x + ($ancho_linea + 10);
    //         $yaux = $pdf->GetY();

    //         if ($yfinal < $yaux) {
    //             $yfinal = $pdf->GetY();
    //         }
    //         $pdf->SetXY($x, $yinicial);
    //         $x = $pdf->GetX();
    //     }
    //     $pdf->CellFit($ancho_linea - 5, $tamanio_linea, (($garantias[$i]['montogravamen'] == '' || $garantias[$i]['montogravamen'] == null) ? ' ' : (number_format($garantias[$i]['montogravamen'], 2, '.', ','))), 0, 0, 'R', $resaltado, '', 1, 0);
    //     //$pdf->Ln(6);
    //     $pdf->SetXY($xinicial, $yfinal + 1);
    // }
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->CellFit($ancho_linea + 134, $tamanio_linea, 'TOTAL', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 29, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 5, $tamanio_linea, (($garantias[0]['totalgravamen'] == '' || $garantias[0]['totalgravamen'] == null) ? ' ' : (number_format($garantias[0]['totalgravamen'], 2, '.', ','))), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea + 134, $tamanio_linea, ' ', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 29, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 5, $tamanio_linea, ' ', 'T', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(1);

    $pdf->CellFit($ancho_linea + 134, $tamanio_linea, ' ', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 29, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea - 5, $tamanio_linea, ' ', 'T', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(10);

    //FIRMAS DEL ANALISTA Y CLIENTE
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 55, $tamanio_linea, ' ', 'B', 0, 'L', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea + 22, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 55, $tamanio_linea, (($datos[0]['analista'] == '' || $datos[0]['analista'] == null) ? ' ' : strtoupper(decode_utf8($datos[0]['analista']))), 0, 0, 'C', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea + 22, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Ln(8);

    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 23, $tamanio_linea, ' ', 0, 0, 'L', 0, '', 1, 0);
    $pdf->SetFont($fuente, '', 9);
    $pdf->CellFit($ancho_linea + 55, $tamanio_linea, 'Analista', 0, 0, 'C', 0, '', 1, 0);
    $pdf->Ln(6);

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Comprobante generado correctamente',
        'namefile' => "FichaAnalisis-" . (($datos[0]['ccodcta'] == '' || $datos[0]['ccodcta'] == null) ? ' ' : ($datos[0]['ccodcta'])),
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}
