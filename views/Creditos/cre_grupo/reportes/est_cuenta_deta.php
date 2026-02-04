<?php
include __DIR__ . '/../../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();

if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
require '../../../../fpdf/fpdf.php';
$hoy = date("Y-m-d");

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Helpers\Beneq;

$status = false;
try {
    $database = new DatabaseAdapter();
    $db_name_general = $_ENV['DDBB_NAME_GENERAL'];
    $data = [
        // 'token_csrf' => $_POST['inputs'][$csrf->getTokenName()] ?? '',
        'idGrupo' => $_POST['datosval'][3][0] ?? null,
        'ciclo' => $_POST['datosval'][3][1] ?? null,
        'tipoReporte' => $_POST['datosval'][3][2] ?? null,
    ];

    $rules = [
        // 'token_csrf' => 'required',
        'idGrupo' => 'required|numeric|min:1|exists:tb_grupo,id_grupos',
        'ciclo' => 'required|numeric|min:0',
        'tipoReporte' => 'required|in:1,2',
    ];

    $validator = Validator::make($data, $rules);
    if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $query = "SELECT gru.*,cli.url_img, cli.short_name,cli.idcod_cliente,cli.date_birth,cli.no_identifica, cli.genero,cli.tel_no1 as telefono,
        cre.CCODCTA,cre.Cestado,cre.NCiclo,cre.MontoSol,cre.NIntApro,cre.CodAnal,concat(usu.nombre,' ',usu.apellido) nomanal,
        cre.CCODPRD,cre.CtipCre,cre.NtipPerC,cre.DfecPago,cre.noPeriodo,cre.Dictamen,cre.MonSug,cre.DFecDsbls,cre.NCapDes,
        pro.id_fondo id_fondos,ff.descripcion,pro.nombre nnompro,
        IFNULL((SELECT SUM(nintere) FROM Cre_ppg WHERE ccodcta=cre.CCODCTA GROUP BY ccodcta),0) intcal,
        IFNULL((SELECT SUM(KP) FROM CREDKAR WHERE ccodcta=cre.CCODCTA AND cestado!='X' AND ctippag='P' GROUP BY ccodcta),0) cappag,
        IFNULL((SELECT SUM(interes) FROM CREDKAR WHERE ccodcta=cre.CCODCTA AND cestado!='X' AND ctippag='P' GROUP BY ccodcta),0) intpag,
        IFNULL((SELECT COUNT(*) FROM Cre_ppg WHERE ccodcta=cre.CCODCTA AND cestado='P') ,0) cuotas_pagadas,
        IFNULL((SELECT COUNT(*) FROM Cre_ppg WHERE ccodcta=cre.CCODCTA AND cestado='X') ,0) cuotas_pendientes
        From cremcre_meta cre
        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
        INNER JOIN tb_grupo gru ON gru.id_grupos=cre.CCodGrupo
        INNER JOIN cre_productos pro ON pro.id=cre.CCODPRD
        INNER JOIN ctb_fuente_fondos ff ON ff.id=pro.id_fondo
        INNER JOIN tb_usuario usu ON usu.id_usu=cre.CodAnal
        WHERE cre.TipoEnti='GRUP' AND (cre.CESTADO='F' OR cre.CESTADO='G') AND cre.CCodGrupo=?  AND cre.NCiclo=? ORDER BY cre.CCODCTA";

    //+++++++++++++++++++
    $database->openConnection();
    $result = $database->getAllResults($query, [$data['idGrupo'], $data['ciclo']]);

    if (empty($result)) {
        throw new SoftException("No se encontraron registros.");
    }

    $pagosquery = "SELECT crem.MonSug,cred.DFECPRO,cred.CNROCUO,SUM(cred.NMONTO) montototal,cred.CNUMING,cred.CCONCEP,SUM(cred.KP) capital,SUM(cred.INTERES) interes,SUM(cred.MORA) mora,SUM(cred.OTR) otros FROM CREDKAR cred 
                    INNER JOIN cremcre_meta crem ON crem.CCODCTA=cred.CCODCTA
                    INNER JOIN tb_grupo gru ON gru.id_grupos=crem.CCodGrupo
                    WHERE cred.CESTADO!='X' AND crem.TipoEnti='GRUP' AND crem.CCodGrupo=?  AND crem.NCiclo=? AND cred.CTIPPAG='P' 
                    GROUP BY cred.CNUMING,cred.CNROCUO ORDER BY cred.DFECPRO,cred.CNROCUO";
    $pagos = $database->getAllResults($pagosquery, [$data['idGrupo'], $data['ciclo']]);

    $info = $database->getAllResults(
        "SELECT nom_agencia, nomb_comple, muni_lug, emai, tel_1, tel_2, nit, log_img 
            FROM {$db_name_general}.info_coperativa ins
            INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?",
        [$_SESSION['id_agencia']]
    );

    if (empty($info)) {
        throw new SoftException("Institucion asignada a la agencia no encontrada");
    }

    $status = true;
} catch (SoftException $se) {
    $mensaje = "Advertencia: " . $se->getMessage();
} catch (Exception $e) {
    $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

printpdf($result, $info, $data['tipoReporte'], $pagos);

function printpdf($registro, $info, $tipo, $pagos)
{
    $oficina = "Coban";
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
        public $tipo;
        protected $DefOrientation;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos, $tipo)
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
            $this->datos = $datos;
            $this->tipo = $tipo;
            $this->DefOrientation = 'L';
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
            $this->SetFont($fuente, 'B', 9);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(5);

            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'ESTADO DE CUENTA GRUPAL', 0, 1, 'C');
            $this->Ln(2);
            //TITULOS DE ENCABEZADO DE TABLA
            $ancho_linea = 40;

            $this->Cell($ancho_linea, 7, 'NOMBRE DEL GRUPO:', '', 0, 'L');
            $this->Cell($ancho_linea * 2, 7, $this->datos[0]["NombreGrupo"], '', 0, 'L');

            $this->Cell($ancho_linea, 7, 'CODIGO DE GRUPO:', '', 0, 'L');
            $this->Cell($ancho_linea, 7, $this->datos[0]["codigo_grupo"], '', 0, 'L');

            $this->Cell($ancho_linea, 7, 'CICLO:', '', 0, 'L');
            $this->Cell($ancho_linea, 7,  $this->datos[0]["NCiclo"], '', 1, 'L');

            $this->Cell($ancho_linea, 7, 'FECHA DE APERTURA:', '', 0, 'L');
            $fechasol = date("d-m-Y", strtotime($this->datos[0]["DFecDsbls"]));
            $this->Cell($ancho_linea * 2, 7,  $fechasol, '', 0, 'L');

            $this->Cell($ancho_linea, 7, 'MESES:', '', 0, 'L');
            $this->Cell($ancho_linea, 7, $this->datos[0]["noPeriodo"], '', 0, 'L');

            $this->Cell($ancho_linea, 7, 'INTERES:', '', 0, 'L');
            $this->Cell($ancho_linea, 7, $this->datos[0]["NIntApro"], '', 1, 'L');


            $this->Cell($ancho_linea, 7, 'ASESOR:', '', 0, 'L');
            $this->Cell($ancho_linea * 2, 7, $this->datos[0]["nomanal"], '', 0, 'L');

            $this->Cell($ancho_linea, 7, 'MONTO TOTAL:', '', 0, 'L');
            $this->Cell($ancho_linea, 7, array_sum(array_column($this->datos, "MonSug")), '', 1, 'L');

            $this->Cell($ancho_linea, 7, 'LINEA DE CREDITO', '', 0, 'L');
            $this->Cell($ancho_linea * 2, 7, $this->datos[0]["nnompro"], '', 0, 'L');


            $this->Ln(5);

            $this->Cell(0, 5, 'COMPOSICION DEL GRUPO', 0, 1, 'C');
            $this->Ln(2);
            $men = count(array_filter(array_column($this->datos, "genero"), function ($var) {
                return ($var == "M");
            }));
            $women = count(array_filter(array_column($this->datos, "genero"), function ($var) {
                return ($var == "F");
            }));
            $this->Cell($ancho_linea * 2, 6, 'TOTAL DE CLIENTES: ' . count($this->datos), '', 0, 'L');

            $this->Cell($ancho_linea * 2, 6, 'HOMBRES: ' . $men, '', 0, 'L');

            $this->Cell($ancho_linea * 2, 6, 'MUJERES: ' . $women, '', 1, 'L');


            $ancho_linea = 28;
            if ($this->tipo == 1) {
                $withsCells = [5, 27, 65, 18, 20, 22, 20, 20, 20, 20, 20, 10, 10];
                $this->SetFont($fuente, 'B', 8);
                $this->Cell($withsCells[0], 6, '#', 'B', 0, 'L');
                $this->Cell($withsCells[1], 6, 'CODIGO CREDITO', 'B', 0, 'L');
                $this->Cell($withsCells[2], 6, 'NOMBRE DEL CLIENTE', 'B', 0, 'L');
                $this->Cell($withsCells[3], 6, 'TEL', 'B', 0, 'L');
                $this->Cell($withsCells[4], 6, 'IDENTIF.', 'B', 0, 'L');
                $this->Cell($withsCells[5], 6, 'MONTO', 'B', 0, 'C');
                $this->Cell($withsCells[6], 6, 'KP PAG.', 'B', 0, 'R');
                $this->Cell($withsCells[7], 6, 'INT GEN.', 'B', 0, 'R');
                $this->Cell($withsCells[8], 6, 'INT PAGADO', 'B', 0, 'R');
                $this->Cell($withsCells[9], 6, 'SALDO KP', 'B', 0, 'R');
                $this->Cell($withsCells[10], 6, 'SALDO INT', 'B', 0, 'R');
                $this->Cell($withsCells[11], 6, 'Pag.', 'B', 0, 'C');
                $this->Cell($withsCells[12], 6, 'Pend.', 'B', 1, 'C');
            }
            if ($this->tipo == 2) {
                $this->Cell(0, 5, 'HISTORICO DE ABONOS', 'T', 1, 'C');
                $this->Ln(2);
                $this->SetFont($fuente, 'B', 9);
                $ancho_linea = 32;
                $this->Cell($ancho_linea / 2, 6, 'CUOTA', 'B', 0, 'C');
                $this->Cell($ancho_linea, 6, 'FECHA', 'B', 0, 'C');
                $this->Cell($ancho_linea, 6, 'NO. RECIBO', 'B', 0, 'C');
                $this->Cell($ancho_linea, 6, 'PAGO', 'B', 0, 'R');
                $this->Cell($ancho_linea, 6, 'CAPITAL', 'B', 0, 'R');
                $this->Cell($ancho_linea, 6, 'INTERES', 'B', 0, 'R');
                $this->Cell($ancho_linea, 6, 'MORA', 'B', 0, 'R');
                $this->Cell($ancho_linea, 6, 'OTROS', 'B', 0, 'R');
                $this->Cell($ancho_linea, 6, 'SALDO', 'B', 1, 'R');
            }
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
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $registro, $tipo);
    $pdf->AliasNbPages();
    $pdf->AddPage();

    if ($tipo == 1) detallado($pdf, $registro);
    if ($tipo == 2 && count($pagos) > 0) consolidado($pdf, $registro, $pagos);

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

function detallado($pdf, $registro)
{
    $fuente = "Courier";
    $tamanio_linea = 5;
    $pdf->SetFont($fuente, '', 8);

    $withsCells = [5, 27, 65, 18, 20, 22, 20, 20, 20, 20, 20, 10, 10];

    foreach ($registro as $key => $value) {
        $salcap = $value["NCapDes"] - $value["cappag"];
        $salint = $value["intcal"] - $value["intpag"];

        $registro[$key]["salcap"] = $salcap;
        $registro[$key]["salint"] = $salint;

        $pdf->CellFit($withsCells[0], $tamanio_linea, $key + 1, '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($withsCells[1], $tamanio_linea, $value["CCODCTA"], '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($withsCells[2], $tamanio_linea, Utf8::decode(mb_convert_case(($value["short_name"]), MB_CASE_TITLE, "UTF-8")), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($withsCells[3], $tamanio_linea, Beneq::karely($value["telefono"]), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($withsCells[4], $tamanio_linea, ($value["no_identifica"]), '', 0, 'L', 0, '', 1, 0);
        $pdf->CellFit($withsCells[5], $tamanio_linea, Moneda::formato($value["NCapDes"], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($withsCells[6], $tamanio_linea, Moneda::formato($value["cappag"], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($withsCells[7], $tamanio_linea, Moneda::formato($value["intcal"], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($withsCells[8], $tamanio_linea, Moneda::formato($value["intpag"], ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($withsCells[9], $tamanio_linea, Moneda::formato(($salcap > 0) ? $salcap : 0, ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($withsCells[10], $tamanio_linea, Moneda::formato(($salint > 0) ? $salint : 0, ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($withsCells[11], $tamanio_linea, $value["cuotas_pagadas"], '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($withsCells[12], $tamanio_linea, $value["cuotas_pendientes"], '', 1, 'C', 0, '', 1, 0);
    }

    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $sum_montos = array_sum(array_column($registro, "NCapDes"));
    $sum_cappag = array_sum(array_column($registro, "cappag"));
    $sum_intcal = array_sum(array_column($registro, "intcal"));
    $sum_intpag = array_sum(array_column($registro, "intpag"));
    $sum_salcap = array_sum(array_column($registro, "salcap"));
    $sum_salint = array_sum(array_column($registro, "salint"));

    $pdf->CellFit($withsCells[0] + $withsCells[1] + $withsCells[2] + $withsCells[3] + $withsCells[4], $tamanio_linea + 1, 'No. Clientes: ' . count($registro), 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($withsCells[5], $tamanio_linea + 1, Moneda::formato($sum_montos, ''), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($withsCells[6], $tamanio_linea + 1, Moneda::formato($sum_cappag, ''), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($withsCells[7], $tamanio_linea + 1, Moneda::formato($sum_intcal, ''), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($withsCells[8], $tamanio_linea + 1, Moneda::formato($sum_intpag, ''), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($withsCells[9], $tamanio_linea + 1, Moneda::formato($sum_salcap, ''), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($withsCells[10], $tamanio_linea + 1, Moneda::formato($sum_salint, ''), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($withsCells[11] + $withsCells[12], $tamanio_linea + 1, ' ', 'T', 1, 'C', 0, '', 1, 0);
}

function consolidado($pdf, $registro, $pagos)
{
    $fuente = "Courier";
    $tamanio_linea = 5;
    $ancho_linea2 = 32;
    $pdf->SetFont($fuente, '', 8);

    $sum_montos = array_sum(array_column($registro, "NCapDes"));
    $saldo = $sum_montos;
    $fila = 0;
    while ($fila < count($pagos)) {
        $fecha = date("d-m-Y", strtotime($pagos[$fila]["DFECPRO"]));
        $nocuo =  $pagos[$fila]["CNROCUO"];
        $montototal =  $pagos[$fila]["montototal"];
        $numdoc = $pagos[$fila]["CNUMING"];
        $cappag =  $pagos[$fila]["capital"];
        $intpag =  $pagos[$fila]["interes"];
        $morpag =  $pagos[$fila]["mora"];
        $otrospag =  $pagos[$fila]["otros"];
        $saldo = $saldo - $cappag;

        $saldo = ($saldo > 0) ? $saldo : 0;

        $pdf->CellFit($ancho_linea2 / 2, $tamanio_linea, $nocuo, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $fecha, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, $numdoc, '', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($montototal, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($cappag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($intpag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($morpag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($otrospag, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($ancho_linea2, $tamanio_linea, number_format($saldo, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
        $fila++;
    }
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 8);
    $sum_montos = array_sum(array_column($pagos, "montototal"));
    $sum_cappag = array_sum(array_column($pagos, "capital"));
    $sum_intpag = array_sum(array_column($pagos, "interes"));
    $sum_morpag = array_sum(array_column($pagos, "mora"));
    $sum_otrospag = array_sum(array_column($pagos, "otros"));

    $pdf->CellFit($ancho_linea2 * 2.5, $tamanio_linea + 1, ' ', 'T', 0, 'C', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_montos, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_cappag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_intpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_morpag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, number_format($sum_otrospag, 2, '.', ','), 'T', 0, 'R', 0, '', 1, 0);
    $pdf->CellFit($ancho_linea2, $tamanio_linea + 1, ' ', 'T', 1, 'R', 0, '', 1, 0);
}
