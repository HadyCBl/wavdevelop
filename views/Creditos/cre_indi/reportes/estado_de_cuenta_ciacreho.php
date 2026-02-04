<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../../fpdf/fpdf.php';
require __DIR__ . '/../../../../vendor/autoload.php';
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");


$datos = $_POST["datosval"];
// $inputs = $datos[0];
// $selects = $datos[1];
// $radios = $datos[2];
$archivo = $datos[3];
$codigoDeCuenta = $archivo[0];
$tipo = $_POST["tipo"];

$strquery = "SELECT cli.idcod_cliente,cli.short_name,cli.url_img,cli.tel_no1,cli.date_birth,cli.no_identifica,cli.Direccion,
                cr.CCODCTA,cr.CCODPRD,cr.Cestado,cr.DFecDsbls,cr.ActoEcono,cr.CtipCre,cr.NtipPerC,cr.Cdescre,cr.CSecEco,cr.CCodGrupo,cr.NCiclo,cr.TipDocDes,cr.noPeriodo,cr.MonSug,cr.NCapDes, cr.TipoEnti,cr.NIntApro,
                prod.nombre nnompro,prod.descripcion descriprod,prod.tasa_interes TasaInteres,
                ff.descripcion ffondo,CONCAT(usu.nombre,' ',usu.apellido) nomanal,
                IFNULL((SELECT dfecven FROM Cre_ppg WHERE ccodcta=cr.CCODCTA ORDER BY dfecven DESC LIMIT 1),0) fechaven,
                IFNULL((SELECT SUM(ncapita) FROM Cre_ppg WHERE dfecven<=CURDATE() AND ccodcta=cr.CCODCTA GROUP BY ccodcta),0) capcalafec,
                IFNULL((SELECT SUM(nintere) FROM Cre_ppg WHERE ccodcta=cr.CCODCTA GROUP BY ccodcta),0) intcalafec,
                IFNULL((SELECT SUM(nintpag) FROM Cre_ppg WHERE dfecven<=CURDATE() AND ccodcta=cr.CCODCTA AND cestado='X' GROUP BY ccodcta),0) intPendienteHoy,
                IFNULL((SELECT SUM(nmorpag) FROM Cre_ppg WHERE dfecven<=CURDATE() AND ccodcta=cr.CCODCTA AND cestado='X' GROUP BY ccodcta),0) morcal,
                cre_dias_atraso(CURDATE(),cr.CCODCTA) atraso,IFNULL(ac.Titulo,' ') actividad,IFNULL(dc.DestinoCredito,' ') destino,
                IFNULL(sec.SectoresEconomicos,' ') sector
                FROM cremcre_meta cr 
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cr.CodCli 
                INNER JOIN cre_productos prod ON prod.id=cr.CCODPRD
                INNER JOIN ctb_fuente_fondos ff ON ff.id=prod.id_fondo
                INNER JOIN tb_usuario usu ON usu.id_usu=cr.CodAnal
                LEFT JOIN $db_name_general.tb_ActiEcono ac ON ac.id_ActiEcono=cr.ActoEcono 
                LEFT JOIN $db_name_general.tb_sectoreseconomicos sec ON sec.id_SectoresEconomicos=cr.CSecEco
                LEFT JOIN $db_name_general.tb_destinocredito dc ON dc.id_DestinoCredito=cr.Cdescre 
                WHERE cr.CCODCTA=?";

$strquery2 = "SELECT cl.idcod_cliente AS codcli, gr.idGarantia AS idgar, tipgar.id_TiposGarantia AS idtipgar, tipgar.TiposGarantia AS nomtipgar, tipc.idDoc AS idtipdoc, tipc.NombreDoc AS nomtipdoc, 
                gr.descripcionGarantia AS descripcion, gr.direccion AS direccion, gr.montoGravamen AS montogravamen,
                IFNULL((SELECT cl2.no_identifica AS dpi FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS dpi,
                IFNULL((SELECT cl2.short_name AS nomcli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS nomcli,
                IFNULL((SELECT cl2.Direccion AS direccioncli FROM tb_cliente cl2 WHERE cl2.idcod_cliente=gr.descripcionGarantia AND tipgar.id_TiposGarantia=1 AND (tipc.idDoc=1 OR tipc.idDoc=17)),'x') AS direccioncli,
                IFNULL((SELECT '1' AS marcado FROM tb_garantias_creditos tgc WHERE tgc.id_cremcre_meta=? AND tgc.id_garantia=gr.idGarantia),0) AS marcado,
                IFNULL((SELECT SUM(cli.montoGravamen) AS totalgravamen FROM tb_garantias_creditos tgc INNER JOIN cli_garantia cli ON cli.idGarantia=tgc.id_garantia WHERE tgc.id_cremcre_meta=? AND cli.estado=1),0) AS totalgravamen
                FROM tb_cliente cl
                INNER JOIN cli_garantia gr ON cl.idcod_cliente=gr.idCliente
                INNER JOIN $db_name_general.tb_tiposgarantia tipgar ON gr.idTipoGa=tipgar.id_TiposGarantia
                INNER JOIN $db_name_general.tb_tiposdocumentosR tipc ON tipc.idDoc=gr.idTipoDoc  
                WHERE cl.estado='1' AND gr.estado=1 AND cl.idcod_cliente=?";

$showmensaje = false;
try {
    $database->openConnection();
    $registro = $database->getAllResults($strquery, [$codigoDeCuenta]);
    if (empty($registro)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

    foreach ($registro as $key4 => $row) {
        $todos = $row['atraso'];
        $filasaux = substr($todos, 0, -1);
        $filas = explode("#", $filasaux);
        for ($k = 0; $k < count($filas); $k++) {
            $registro[$key4]["atrasadas"][$k] = explode("_", $filas[$k]);
        }
    }

    $garantias = $database->getAllResults($strquery2, [$codigoDeCuenta, $codigoDeCuenta, $registro[0]['idcod_cliente']]);

    $pagosquery = "SELECT cred.DFECPRO,cred.CNROCUO,cred.NMONTO,cred.CNUMING,cred.CCONCEP,cred.KP,cred.INTERES,cred.MORA,cred.AHOPRG,cred.OTR,cred.CTIPPAG,cred.boletabanco FROM CREDKAR cred 
                    WHERE cred.CESTADO!='X' AND cred.CTIPPAG='P' AND cred.CCODCTA=? ORDER BY cred.DFECPRO,cred.CNROCUO";

    $pagos = $database->getAllResults($pagosquery, [$codigoDeCuenta]);

    $haypagos = !empty($pagos);

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++ CONSULTA DE CUOTA SIGUIENTE A PAGAR +++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $sumaCuotaSiguiente = 0;
    $isVencido = true;

    $cuotasPpg = "SELECT COALESCE(SUM(cpg.ncappag), 0) AS capital, COALESCE(SUM(cpg.nintpag), 0) AS interes, COALESCE(SUM(cpg.nmorpag), 0) AS mora, COALESCE(SUM(cpg.OtrosPagosPag), 0) AS otrospagos
                    FROM Cre_ppg cpg
                    WHERE cpg.cestado='X' AND cpg.ccodcta=? AND cpg.dfecven <= ?";

    $datoscreppg = $database->getAllResults($cuotasPpg, [$codigoDeCuenta, $hoy]);

    if (!empty($datoscreppg)) {
        $sumaCuotaSiguiente = $datoscreppg[0]['capital'] + $datoscreppg[0]['interes'] + $datoscreppg[0]['mora'] + $datoscreppg[0]['otrospagos'];
    }

    if ($sumaCuotaSiguiente <= 0) {
        /**
         * SI NO HAY CUOTAS VENCIDAS, TRAE LA PRIMERA CUOTA PENDIENTE POR PAGAR 
         */
        $isVencido = false;
        $cuotasPpg = "SELECT cpg.ncappag AS capital, cpg.nintpag AS interes, cpg.nmorpag AS mora, cpg.OtrosPagosPag AS otrospagos
                        FROM Cre_ppg cpg
                        WHERE cpg.cestado='X' AND cpg.ccodcta=? AND cpg.dfecven > ?
                        ORDER BY cpg.ccodcta, cpg.dfecven, cpg.cnrocuo Limit 1";

        $datoscreppg = $database->getAllResults($cuotasPpg, [$codigoDeCuenta, $hoy]);

        if (!empty($datoscreppg)) {
            $sumaCuotaSiguiente = $datoscreppg[0]['capital'] + $datoscreppg[0]['interes'] + $datoscreppg[0]['mora'] + $datoscreppg[0]['otrospagos'];
        }
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

if ($status == 0) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

printpdf($registro, $info, $pagos, $haypagos, $garantias, $sumaCuotaSiguiente, $isVencido);

function printpdf($registro, $info, $pagos, $haypagos, $garantias, $sumaCuotaSiguiente, $isVencido)
{
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = $info[0]["nomb_comple"];
    $direccionins = $info[0]["muni_lug"];
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
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
        public $pagos;
        public $sumaCuotaSiguiente;
        public $isVencido;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $pagos, $sumaCuotaSiguiente, $isVencido)
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
            $this->datos = $datos;
            $this->pagos = $pagos;
            $this->sumaCuotaSiguiente = $sumaCuotaSiguiente;
            $this->isVencido = $isVencido;
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            //fecha y usuario que genero el reporte
            $this->SetFont($fuente, '', 7);
            //$this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 10, 33);

            //tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 7);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(3);

            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'ESTADO DE CUENTA INDIVIDUAL', 0, 1, 'C');

            $ancho_linea = 40;
            $this->Cell($ancho_linea * 2, 7, 'CUENTA: ' . $this->datos[0]["CCODCTA"], 'B', 0, 'L');
            $this->Cell(0, 7, 'CLIENTE: ' . $this->datos[0]["idcod_cliente"] . ' - ' . (strtoupper(decode_utf8($this->datos[0]["short_name"]))), 'B', 1, 'L');
            $this->Ln(2);
            //TITULOS DE ENCABEZADO DE TABLA

            $this->Cell($ancho_linea, 5, 'ESTADO DEL CREDITO:', '', 0, 'L');
            $this->Cell($ancho_linea * 1.5, 5, ($this->datos[0]["Cestado"] == "F") ? 'VIGENTE' : 'CANCELADO', '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'EJECUTIVO:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, decode_utf8($this->datos[0]["nomanal"]), '', 1, 'L');


            $this->Cell($ancho_linea, 5, 'DIRECCION:', '', 0, 'L');
            $this->Cell($ancho_linea * 1.5, 5, decode_utf8($this->datos[0]["Direccion"]), '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'TELEFONO:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, $this->datos[0]["tel_no1"], '', 1, 'L');


            $this->Cell($ancho_linea, 5, 'DESTINO DEL CREDITO:', '', 0, 'L');
            $this->Cell($ancho_linea * 1.5, 5, decode_utf8($this->datos[0]["destino"]), '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'SECTOR ECONOMICO:', '', 0, 'L');
            $this->Cell($ancho_linea, 5,  decode_utf8($this->datos[0]["sector"]), '', 1, 'L');


            $this->Cell($ancho_linea, 5, 'ACTIVIDAD ECONOMICA:', '', 0, 'L');
            $this->Cell($ancho_linea * 1.5, 5,  decode_utf8($this->datos[0]["actividad"]), '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'FUENTE DE FONDOS:', '', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, $this->datos[0]["ffondo"], '', 1, 'L');

            $this->Cell($ancho_linea, 5, 'LINEA DE CREDITO: ', '', 0, 'L');
            $this->Cell($ancho_linea * 2, 5, $this->datos[0]["nnompro"], '', 1, 'L');

            $this->Cell($ancho_linea, 5, 'ENTIDAD: ', '', 0, 'L');
            $this->Cell($ancho_linea * 1.5, 5, ($this->datos[0]["TipoEnti"] == "GRUP") ? 'GRUPAL' : 'INDIVIDUAL', '', 1, 'L');

            $this->Ln(5);

            $this->Cell(0, 5, 'DATOS DEL CREDITO', 'T', 1, 'C');
            $ancho_linea = 30;
            $this->Cell($ancho_linea, 5, 'MONTO APROBADO:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, $this->datos[0]["MonSug"], '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'No.CUOTAS:', '', 0, 'L');
            $this->Cell($ancho_linea, 5,  $this->datos[0]["noPeriodo"], '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'OTORGAMIENTO:', '', 0, 'L');
            $fecha = date("d-m-Y", strtotime($this->datos[0]["DFecDsbls"]));
            $this->Cell($ancho_linea, 5, $fecha, '', 1, 'L');


            $this->Cell($ancho_linea, 5, 'TASA:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, $this->datos[0]["NIntApro"], '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'VENCIMIENTO:', '', 0, 'L');
            $fechaven = date("d-m-Y", strtotime($this->datos[0]["fechaven"]));
            $this->Cell($ancho_linea, 5, $fechaven, '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'CICLO:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, $this->datos[0]["NCiclo"], '', 1, 'L');

            $this->Cell($ancho_linea, 5, 'DIAS DE ATRASO:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, $this->datos[0]["atrasadas"][0][0], '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'MONTO DESEMBOLSADO:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, $this->datos[0]["NCapDes"], '', 1, 'L');

            $this->Ln(2);

            $this->Cell(0, 5, 'SALDOS DEL CREDITO', 'T', 1, 'C');

            $saldo = $this->datos[0]["NCapDes"] - array_sum(array_column($this->pagos, "KP"));
            $saldo = ($saldo > 0) ? round($saldo, 2)  : 0;

            $this->Cell($ancho_linea, 5, 'SALDO INTERES:', '', 0, 'L');
            $intapagar = $this->datos[0]["intcalafec"] - array_sum(array_column($this->pagos, "INTERES"));
            $intapagar = ($intapagar < 0) ? 0 : $intapagar;
            $intapagar = ($saldo > 0) ? round($intapagar, 2) : 0;
            $this->Cell($ancho_linea, 5, $intapagar, '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'MORA:', '', 0, 'L');
            $moracal = array_sum(array_column($this->datos, "morcal"));
            $this->Cell($ancho_linea, 5, $moracal, '', 0, 'L');

            $this->Cell($ancho_linea - 10, 5, 'KP EN MORA:', '', 0, 'L');
            $sum_capmora = array_sum(array_column($this->datos[0]["atrasadas"], 1));
            $this->Cell($ancho_linea / 2, 5, $sum_capmora, '', 0, 'L');

            $this->Cell($ancho_linea - 10, 5, 'Int. Pend.:', '', 0, 'L');
            $this->Cell($ancho_linea / 2, 5, ($saldo > 0) ? $this->datos[0]["intPendienteHoy"] : 0, '', 1, 'L');


            $this->Cell($ancho_linea, 5, 'TOTAL:', '', 0, 'L');
            $this->Cell($ancho_linea, 5, ($intapagar + $moracal + $sum_capmora), '', 0, 'L');

            $this->Cell($ancho_linea, 5, 'SALDO CAP:', '', 0, 'L');

            $this->Cell($ancho_linea, 5, $saldo, '', 0, 'L');

            $this->Cell($ancho_linea - 10, 5, 'SALDO KP+INT:', '', 0, 'L');
            $kpint = $intapagar + $saldo;
            $this->Cell($ancho_linea / 2, 5, round($kpint, 2), '', 0, 'L');

            $this->Cell($ancho_linea - 10, 5, ($this->isVencido) ? 'Cuota Pend.:' : 'Sig. Cuota:', '', 0, 'L');
            $this->Cell($ancho_linea / 2, 5, moneda(max($this->sumaCuotaSiguiente, 0)), '', 1, 'L');


            $this->Ln(2);

            $this->Cell(0, 5, 'HISTORICO DE MOVIMIENTOS', 'T', 1, 'C');
            $this->SetFont($fuente, 'B', 8);
            $ancho_linea = 20;
            $this->Cell($ancho_linea, 6, 'FECHA', 'B', 0, 'C');
            $this->Cell($ancho_linea / 1.5, 6, 'NO.CUO', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'NUMDOC', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'BOLETA', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'PAGO', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'CAPITAL', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'INTERES', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'MORA', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'OTROS', 'B', 0, 'C');
            $this->Cell($ancho_linea, 6, 'SALDO', 'B', 1, 'C');
            $this->Ln(2);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo 
            //$this->Image($this->pathlogo, 175, 279, 28);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $registro, $pagos, $sumaCuotaSiguiente, $isVencido);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    if ($haypagos) rpagos($pdf, $registro, $pagos);

    garantias($pdf, $garantias);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Estado de cuenta Grupal",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function rpagos($pdf, $registro, $pagos)
{
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea2 = 20;
    $pdf->SetFont($fuente, '', 8);

    $monto = $registro[0]["NCapDes"];
    $saldo = $monto;
    $fila = 0;
    while ($fila < count($pagos)) {
        $fecha = date("d-m-Y", strtotime($pagos[$fila]["DFECPRO"]));
        $nocuo =  $pagos[$fila]["CNROCUO"];

        $tippag =  $pagos[$fila]["CTIPPAG"];
        $montototal =  $pagos[$fila]["NMONTO"];
        $numdoc = ($tippag == "P") ? ($pagos[$fila]["CNUMING"] ?: ' ') : 'DESEMBOLSO';
        $boleta = ($tippag == "P" && !empty($pagos[$fila]["boletabanco"])) ? $pagos[$fila]["boletabanco"] : ' ';
        $cappag =  $pagos[$fila]["KP"];
        $intpag = ($tippag == "P") ? number_format($pagos[$fila]["INTERES"], 2, '.', ',') : ' ';
        $morpag = ($tippag == "P") ? number_format($pagos[$fila]["MORA"], 2, '.', ',') : ' ';
        $otrospag = $pagos[$fila]["AHOPRG"] + $pagos[$fila]["OTR"];
        $saldo = ($tippag == "P") ? $saldo - $cappag : $cappag;

        //$saldo = ($saldo > 0) ? $saldo : 0;

        $pdf->CellFit($ancho_linea2, $tamanio_linea, $fecha, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2 / 1.5, $tamanio_linea, $nocuo, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $numdoc, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $boleta, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($montototal, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($cappag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $intpag, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $morpag, '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($otrospag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($saldo, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        $fila++;
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $sum_montos = array_sum(array_column($pagos, "NMONTO"));
    $sum_cappag = array_sum(array_column($pagos, "KP"));
    $sum_intpag = array_sum(array_column($pagos, "INTERES"));
    $sum_morpag = array_sum(array_column($pagos, "MORA"));
    $sum_otrospag = array_sum(array_column($pagos, "OTR")) + array_sum(array_column($pagos, "AHOPRG"));

    $pdf->CellFit($ancho_linea2 + 53, $tamanio_linea + 1, ' ', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_montos, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_cappag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_intpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_morpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_otrospag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 'T', 1, 'R', 0, '', 1, 0);
    $pdf->Ln(6);
}

function garantias($pdf, $garantias)
{
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea2 = 22;
    $pdf->SetFont($fuente, '', 8);

    //SECCION PARA PONER LAS GARANTIAS
    $ancho_linea2 = 30;
    //GARANTIAS
    $pdf->SetFont($fuente, 'B', 7);
    $pdf->CellFit(0, $tamanio_linea, decode_utf8('GARANTIAS DEL CRÉDITO'), 'B', 0, 'C', 0, '', 1, 0);
    $pdf->Ln(6);

    /* +++++++++++++++++++++++++++++++++ SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */
    //ENCABEZADO DE GARANTIAS
    $pdf->SetFillColor(204, 229, 255);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->CellFit($ancho_linea2, $tamanio_linea, ('Tip. Garantia'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, ('Tip. Documento'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit($ancho_linea2 * 2.5, $tamanio_linea, decode_utf8('Dirección'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->CellFit(0, $tamanio_linea, ('Mon. Gravamen'), 'B', 0, 'C', 1, '', 1, 0);
    $pdf->Ln(6);
    $pdf->SetFont($fuente, '', 8);

    foreach ($garantias as $key => $garantia) {
        if (isset($garantia['marcado']) && $garantia['marcado'] == 1) {
            $direcciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"] == 17)) ? (($garantia['direccioncli'] == "") ? " " : $garantia['direccioncli']) : $garantia["direccion"];
            $descripciongarantia = ($garantia["idtipgar"] == 1 && ($garantia["idtipdoc"] == 1 || $garantia["idtipdoc"] == 17)) ? "NOMBRE: " . $garantia['nomcli'] : "DESCRIPCION: " . $garantia["descripcion"];
            $pdf->CellFit($ancho_linea2, $tamanio_linea, $garantia["nomtipgar"] ?? " ", 0, 0, 'L', 0, '', 0, 0);
            $pdf->CellFit($ancho_linea2 * 2, $tamanio_linea, $garantia["nomtipdoc"] ?? " ", 0, 0, 'C', 0, '', 0, 0);
            $pdf->CellFit($ancho_linea2 * 2.5, $tamanio_linea, karely($direcciongarantia), 0, 0, 'C', 0, '', 0, 0);
            $pdf->CellFit(0, $tamanio_linea, number_format(($garantia["montogravamen"] ?? 0), 2), 0, 1, 'R', 0, '', 0, 0);
            $pdf->MultiCell(0, $tamanio_linea, decode_utf8($descripciongarantia), 'B', 'L', 0);
            $pdf->Ln(6);
        }
    }
    /* +++++++++++++++++++++++++++++++ FIN SECCION GARANTIAS +++++++++++++++++++++++++++++++++ */

    $totalgravamen  = isset($garantias[0]['totalgravamen']) ? $garantias[0]['totalgravamen'] : 0;

    $pdf->SetFont($fuente, 'B', 8);
    $pdf->CellFit($ancho_linea2 + 134, $tamanio_linea, 'TOTAL', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 29, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, (($totalgravamen == '' || $totalgravamen == null) ? ' ' : (number_format($totalgravamen, 2, '.', ','))), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(6);

    $pdf->CellFit($ancho_linea2 + 134, $tamanio_linea, ' ', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 29, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, ' ', 'T', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(1);

    $pdf->CellFit($ancho_linea2 + 134, $tamanio_linea, ' ', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 29, $tamanio_linea, ' ', 0, 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2 - 5, $tamanio_linea, ' ', 'T', 0, 'R', 0, '', 1, 0);
    $pdf->Ln(2);
}
