<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';
include __DIR__ . '/../funciones/funciones.php';

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use Micro\Models\TipoPoliza;
use Micro\Helpers\Log;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Helpers\Beneq;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Calculation\TextData\Trim;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];

$tipo = $_POST["tipo"];

list($fecha_inicio, $fecha_fin) = $inputs;
$idCuentasBancosArrays = $datos[3][0] ?? ['0'];
$idAgenciasArray = $datos[3][1] ?? ['0'];
$idFuentesFondosArray = $datos[3][2] ?? ['0'];

// Log::info("Inicio de reporte libro bancos", [
//     'fecha_inicio' => $fecha_inicio,
//     'fecha_fin' => $fecha_fin,
//     'idCuentasBancosArrays' => $idCuentasBancosArrays,
//     'idAgenciasArray' => $idAgenciasArray,
//     'idFuentesFondosArray' => $idFuentesFondosArray
// ]);


if ($fecha_inicio > $fecha_fin) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}


$titlereport = " DEL " . setdatefrench($fecha_inicio) . " AL " . setdatefrench($fecha_fin);

$showmensaje = false;
try {
    $database->openConnection();
    $where = (empty($idAgenciasArray) || in_array('0', $idAgenciasArray))
        ? " "
        : " AND id_agencia IN ('" . implode("','", $idAgenciasArray) . "')";

    $where .= (empty($idFuentesFondosArray) || in_array('0', $idFuentesFondosArray))
        ? " "
        : " AND id_fuente_fondo IN ('" . implode("','", $idFuentesFondosArray) . "')";


    $whereOtro = (empty($idCuentasBancosArrays) || in_array('0', $idCuentasBancosArrays))
        ? 'estado=1'
        : "estado=1 AND id IN ('" . implode("','", $idCuentasBancosArrays) . "')";

    // Log::info("este es el whereotro $whereOtro");

    $idCuentasContables = $database->selectColumns('ctb_bancos', ['id_nomenclatura', 'id'], $whereOtro);
    if (empty($idCuentasContables)) {
        $showmensaje = true;
        throw new Exception("No se pudieron obtener las cuentas contables, verifique que estas existan o sigan activas");
    }
    $where .= " AND id_ctb_nomenclatura IN ('" . implode("','", array_column($idCuentasContables, 'id_nomenclatura')) . "')";

    // Log::info("este es el whereotro2 $where");


    $strquery = " SELECT dia.id id_ctb_diario, debe, haber, dia.glosa, dia.fecdoc,dia.numdoc,dia.id_ctb_tipopoliza, dia.numcom,mov.id_ctb_nomenclatura,
                            IFNULL(chq.nomchq,'-') AS nombrecheque,IFNULL(cbm.destino,'-') as destinoBanco,nom.ccodcta,nom.cdescrip,IFNULL(chq.numchq,'-') as numcheque
                        FROM ctb_diario dia 
                        INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                        INNER JOIN ctb_nomenclatura nom ON nom.id=mov.id_ctb_nomenclatura
                        LEFT JOIN ctb_chq chq ON chq.id_ctb_diario=dia.id
                        LEFT JOIN ctb_ban_mov cbm ON cbm.id_ctb_diario=dia.id
                        WHERE dia.estado=1 AND dia.id_ctb_tipopoliza!=9 AND fecdoc BETWEEN ? AND ? $where
                        ORDER BY id_ctb_nomenclatura,fecdoc,dia.id";



    // Log::info("Consulta de movimientos contables", [
    //     'fecha_inicio' => $fecha_inicio,
    //     'fecha_fin' => $fecha_fin,
    //     'where' => $where
    // ]);

    $ctbmovdata = $database->getAllResults($strquery, [$fecha_inicio, $fecha_fin]);
    if (empty($ctbmovdata)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros");
    }

    if (!empty($idCuentasContables)) {
        foreach ($idCuentasContables as $key => $cuenta) {
            $saldoInicial = calcularSaldoInicial($cuenta['id_nomenclatura'], $cuenta['id'], $fecha_inicio, $fecha_fin, $database);
            $idCuentasContables[$key]['saldo_inicial'] = $saldoInicial;
        }
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }

    // $tiposPoliza = TipoPoliza::getTiposPoliza();

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

switch ($tipo) {
    case 'xlsx':
        printxls($ctbmovdata, $titlereport, $info, $idCuentasContables);
        break;
    case 'pdf':
        printpdf($ctbmovdata, $titlereport, $info, $idCuentasContables);
        break;
}

function printpdf($registro, $titlereport, $info, $idCuentasContables)
{

    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];;
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

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

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefono, $nit, $datos)
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
            $this->DefOrientation = 'L';
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Arial";
            $hoy = date("Y-m-d H:i:s");

            // Línea superior decorativa
            $this->SetDrawColor(41, 98, 153);
            $this->SetLineWidth(0.5);
            $this->Line(10, 10, 287, 10);

            // Fecha y hora en formato elegante
            $this->SetFont($fuente, '', 7);
            $this->SetTextColor(100, 100, 100);
            $this->SetXY(10, 11);
            $this->Cell(0, 3, 'Generado: ' . date('d/m/Y H:i', strtotime($hoy)), 0, 1, 'R');

            // Logo de la agencia con marco sutil
            $this->Image($this->pathlogoins, 12, 15, 30);

            // Encabezado institucional elegante
            $this->SetTextColor(41, 98, 153);
            $this->SetFont($fuente, 'B', 12);
            $this->SetXY(10, 16);
            $this->Cell(0, 5, $this->institucion, 0, 1, 'C');

            $this->SetTextColor(80, 80, 80);
            $this->SetFont($fuente, '', 8);
            $this->Cell(0, 4, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 4, $this->email . '  |  Tel: ' . $this->telefono, 0, 1, 'C');
            $this->SetFont($fuente, 'B', 8);
            $this->Cell(0, 4, 'NIT: ' . $this->nit, 0, 1, 'C');

            // Línea separadora
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY() + 2, 287, $this->GetY() + 2);
            $this->Ln(4);

            // Título del reporte con estilo profesional
            $this->SetFillColor(41, 98, 153);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont($fuente, 'B', 11);
            $this->Cell(0, 7, 'LIBRO BANCOS ' . $this->datos, 0, 1, 'C', true);
            $this->Ln(3);

            // Encabezados de columnas con estilo elegante
            $this->SetFillColor(240, 244, 248);
            $this->SetTextColor(40, 40, 40);
            $this->SetDrawColor(200, 200, 200);
            $this->SetFont($fuente, 'B', 9);

            $widths = [21, 19, 120, 30, 28, 28, 28];
            $this->Cell($widths[0], 6, 'FECHA', 1, 0, 'C', true);
            $this->Cell($widths[1], 6, 'PARTIDA', 1, 0, 'C', true);
            $this->Cell($widths[2], 6, 'DESTINO', 1, 0, 'C', true);
            $this->Cell($widths[3], 6, 'NO. DOCUMENTO', 1, 0, 'C', true);
            $this->Cell($widths[4], 6, 'DEBE', 1, 0, 'C', true);
            $this->Cell($widths[5], 6, 'HABER', 1, 0, 'C', true);
            $this->Cell($widths[6], 6, 'SALDO', 1, 1, 'C', true);
            $this->Ln(2);
        }

        // Pie de página
        function Footer()
        {
            // Línea decorativa superior
            $this->SetY(-18);
            $this->SetDrawColor(200, 200, 200);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY(), 287, $this->GetY());

            // Número de página con estilo
            $this->SetY(-15);
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 10, Utf8::decode('Página ') . $this->PageNo() . ' de {nb}', 0, 0, 'C');

            // Línea inferior decorativa
            $this->SetY(-8);
            $this->SetDrawColor(41, 98, 153);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), 287, $this->GetY());
        }
    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $titlereport);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Arial";
    $tamanio_linea = 4;
    $ancho_linea2 = 26;
    $pdf->SetFont($fuente, '', 9);

    $fila = 0;
    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $saldo = 0;
    $sumtd = 0;
    $sumth = 0;
    $header = true;
    $footer = false;
    $fill = false;
    $widths = [21, 19, 120, 30, 28, 28, 28];
    while ($fila < count($registro)) {
        $partida = $registro[$fila]["numcom"];
        $fecha = date("d-m-Y", strtotime($registro[$fila]["fecdoc"]));
        $numdoc = $registro[$fila]["numdoc"];
        $glosa = decode_utf8(trim($registro[$fila]["glosa"]));
        $idcuenta = $registro[$fila]["id_ctb_nomenclatura"];
        $codcta = $registro[$fila]["ccodcta"];
        $nomcuenta = decode_utf8($registro[$fila]["cdescrip"]);
        $nomcheque = decode_utf8(($registro[$fila]["nombrecheque"] == '-') ? $registro[$fila]["destinoBanco"] : $registro[$fila]["nombrecheque"]);
        $debe = $registro[$fila]["debe"];
        $haber = $registro[$fila]["haber"];
        $idnumcom = $registro[$fila]["id_ctb_diario"];

        if ($header) {
            //ENCABEZADOS CUENTAS INDIVIDUALES CON ESTILO PROFESIONAL
            $pdf->SetFillColor(248, 249, 250);
            $pdf->SetTextColor(41, 98, 153);
            $pdf->SetDrawColor(200, 200, 200);
            $pdf->SetFont($fuente, 'B', 10);

            $saldo_inicial = 0;
            foreach ($idCuentasContables as $cuenta) {
                if ($cuenta['id_nomenclatura'] == $idcuenta) {
                    $saldo_inicial = $cuenta['saldo_inicial'];
                    break;
                }
            }
            $saldo = $saldo_inicial;

            $pdf->Cell($ancho_linea2 * 3, 6, 'Cuenta: ' . $codcta, 1, 0, 'L', true);
            $pdf->Cell($ancho_linea2 * 3, 6, 'Nombre: ' . $nomcuenta, 1, 0, 'L', true);
            $pdf->Cell($ancho_linea2 * 4 + 14, 6, 'Saldo Inicial: ' . moneda($saldo_inicial), 1, 1, 'R', true);
            $pdf->SetTextColor(40, 40, 40);
            $pdf->Ln(2);
            $header = false;
            $fill = false;
        }

        //DETALLES PARTIDAS INDIVIDUALES CON ALTERNADO DE COLORES
        $pdf->SetFont($fuente, '', 8.5);

        // Color alternado para filas
        if ($fill) {
            $pdf->SetFillColor(252, 253, 254);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $numdocShow = ($registro[$fila]["numcheque"] == '-') ? $numdoc : $registro[$fila]["numcheque"];

        $prefixDoc = ($debe > 0) ? TipoPoliza::getPrefixForBancoIngreso($registro[$fila]["id_ctb_tipopoliza"]) : TipoPoliza::getPrefixForBancoEgreso($registro[$fila]["id_ctb_tipopoliza"]);
        $tipoDocumento = ($registro[$fila]["numcheque"] !== '-') ? 'CH: ' : ($prefixDoc ? $prefixDoc . ': ' : '');

        $pdf->SetDrawColor(230, 230, 230);
        $pdf->CellFit($widths[0], $tamanio_linea, $fecha, 1, 0, 'C', true, '', 1, 0);
        $pdf->CellFit($widths[1], $tamanio_linea, $partida, 1, 0, 'C', true, '', 1, 0);
        $pdf->CellFit($widths[2], $tamanio_linea, ($nomcheque == '-') ? $nomcuenta : $nomcheque, 1, 0, 'L', true, '', 1, 0);
        $pdf->CellFit($widths[3], $tamanio_linea, Beneq::karely($tipoDocumento . $numdocShow), 1, 0, 'C', true, '', 1, 0);
        $pdf->CellFit($widths[4], $tamanio_linea, Moneda::formato($debe), 1, 0, 'R', true, '', 1, 0);
        $pdf->CellFit($widths[5], $tamanio_linea, Moneda::formato($haber), 1, 0, 'R', true, '', 1, 0);

        //SALDO 
        $sumd = $sumd + $debe;
        $sumh = $sumh + $haber;
        $saldo = $saldo + $debe - $haber;
        $pdf->CellFit($widths[6], $tamanio_linea, Moneda::formato($saldo), 1, 1, 'R', true, '', 1, 0);

        // Concepto en fila separada con fondo gris claro
        $y = $pdf->GetY();
        $x = $pdf->GetX();
        $pdf->SetX($x + $widths[0] + $widths[1]);
        $pdf->SetFont($fuente, 'I', 8);
        $pdf->SetFillColor(245, 247, 250);
        $pdf->SetDrawColor(230, 230, 230);
        $pdf->MultiCell($widths[2] + $widths[3], 3.5, "CONCEPTO: " . $glosa, 1, 'L', true);
        $pdf->SetFont($fuente, '', 8.5);
        $pdf->Ln(1);

        $fill = !$fill;

        $sumtd = $sumtd + $debe;
        $sumth = $sumth + $haber;

        if ($fila != array_key_last($registro)) {
            if ($idcuenta != $registro[$fila + 1]["id_ctb_nomenclatura"]) {
                $header = true;
                $footer = true;
            }
        } else {
            $footer = true;
        }
        if ($footer) {
            $pdf->Ln(2);
            // Línea separadora antes del total
            $pdf->SetDrawColor(41, 98, 153);
            $pdf->SetLineWidth(0.3);
            $pdf->Line(
                $pdf->GetX() + $widths[0] + $widths[1] + $widths[2] + $widths[3],
                $pdf->GetY(),
                $pdf->GetX() + array_sum($widths),
                $pdf->GetY()
            );
            $pdf->Ln(1);

            // Totales con estilo destacado
            $pdf->SetFillColor(41, 98, 153);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont($fuente, 'B', 9);
            $pdf->Cell($widths[0] + $widths[1] + $widths[2] + $widths[3], 6, 'SUBTOTALES', 1, 0, 'R', true);
            $pdf->Cell($widths[4], 6, Moneda::formato($sumd), 1, 0, 'R', true);
            $pdf->Cell($widths[5], 6, Moneda::formato($sumh), 1, 0, 'R', true);
            $pdf->Cell($widths[6], 6, Moneda::formato($saldo), 1, 1, 'R', true);

            $pdf->SetTextColor(40, 40, 40);
            $sumd = 0;
            $sumh = 0;
            $pdf->Ln(5);
            $footer = false;
            $fill = false;
        }
        $fila++;
    }
    // Total general con diseño destacado
    $pdf->Ln(3);
    $pdf->SetDrawColor(41, 98, 153);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(10, $pdf->GetY(), 287, $pdf->GetY());
    $pdf->Ln(2);

    $pdf->SetFillColor(230, 240, 250);
    $pdf->SetTextColor(41, 98, 153);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->Cell($widths[0] + $widths[1] + $widths[2] + $widths[3], 7, 'TOTAL GENERAL', 1, 0, 'R', true);
    $pdf->Cell($widths[4], 7, Moneda::formato($sumtd), 1, 0, 'R', true);
    $pdf->Cell($widths[5], 7, Moneda::formato($sumth), 1, 0, 'R', true);
    $pdf->Cell($widths[6], 7, Moneda::formato($saldo), 1, 1, 'R', true);

    // Información adicional
    $pdf->SetTextColor(40, 40, 40);
    $pdf->SetFont($fuente, '', 9);
    $pdf->Ln(8);

    $pdf->SetFillColor(248, 249, 250);
    $pdf->SetDrawColor(200, 200, 200);
    $num_polizas = count(array_unique(array_column($registro, 'numcom')));
    $pdf->Cell($ancho_linea2 * 5, 6, '', 0, 0, 'R');
    $pdf->Cell($ancho_linea2 * 2, 6, Utf8::decode('Total de Pólizas Procesadas:'), 1, 0, 'L', true);
    $pdf->SetFont($fuente, 'B', 9);
    $pdf->Cell($ancho_linea2, 6, $num_polizas, 1, 1, 'C', true);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Bancos",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $titlereport, $info, $idCuentasContables)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Libro Bancos");

    // Configuración de anchos de columna
    $activa->getColumnDimension("A")->setWidth(18);
    $activa->getColumnDimension("B")->setWidth(35);
    $activa->getColumnDimension("C")->setWidth(15);
    $activa->getColumnDimension("D")->setWidth(12);
    $activa->getColumnDimension("E")->setWidth(65);
    $activa->getColumnDimension("F")->setWidth(18);
    $activa->getColumnDimension("G")->setWidth(18);
    $activa->getColumnDimension("H")->setWidth(18);
    $activa->getColumnDimension("I")->setWidth(30);
    $activa->getColumnDimension("J")->setWidth(18);

    // Estilo del encabezado con colores corporativos
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
            'size' => 11
        ],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => '296299']
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
            'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ];

    // Encabezados de columnas
    $activa->setCellValue('A1', 'CUENTA');
    $activa->setCellValue('B1', 'NOMBRE CUENTA');
    $activa->setCellValue('C1', 'FECHA');
    $activa->setCellValue('D1', 'PARTIDA');
    $activa->setCellValue('E1', 'CONCEPTO');
    $activa->setCellValue('F1', 'DEBE');
    $activa->setCellValue('G1', 'HABER');
    $activa->setCellValue('H1', 'SALDO');
    $activa->setCellValue('I1', 'DESTINO');
    $activa->setCellValue('J1', 'NO. DOCUMENTO');

    // Aplicar estilo a encabezados
    $activa->getStyle('A1:J1')->applyFromArray($headerStyle);
    $activa->getRowDimension(1)->setRowHeight(25);

    $saldo = 0;
    $iniciom = 4;
    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $fila = 0;
    $i = 2;
    $header = true;
    $footer = false;
    while ($fila < count($registro)) {
        $partida = $registro[$fila]["numcom"];
        $fecha = date("d-m-Y", strtotime($registro[$fila]["fecdoc"]));
        $numdoc = $registro[$fila]["numdoc"];
        $numdoc = ($numdoc == "" || $numdoc == NULL) ? " " : $numdoc;
        $glosa = trim($registro[$fila]["glosa"]);
        $idcuenta = $registro[$fila]["id_ctb_nomenclatura"];
        $codcta = $registro[$fila]["ccodcta"];
        $nomcuenta = $registro[$fila]["cdescrip"];
        $debe = $registro[$fila]["debe"];
        $haber = $registro[$fila]["haber"];
        $nomchq = ($registro[$fila]["nombrecheque"] == '-') ? $registro[$fila]["destinoBanco"] : $registro[$fila]["nombrecheque"];
        $idnumcom = $registro[$fila]["id_ctb_diario"];
        $numdocShow = ($registro[$fila]["numcheque"] == '-') ? $numdoc : $registro[$fila]["numcheque"];

        if ($header) {
            $saldo_inicial = 0;
            foreach ($idCuentasContables as $cuenta) {
                if ($cuenta['id_nomenclatura'] == $idcuenta) {
                    $saldo_inicial = $cuenta['saldo_inicial'];
                    break;
                }
            }

            $saldo = $saldo_inicial;
            $i++;

            // Estilo para encabezado de cuenta
            $cuentaHeaderStyle = [
                'font' => ['bold' => true, 'size' => 11, 'color' => ['rgb' => '296299']],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'F8F9FA']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                        'color' => ['rgb' => 'C8C8C8']
                    ]
                ]
            ];

            $i++;
            $sumd = 0;
            $sumh = 0;
            $aux = $idcuenta;

            // Encabezado de cuenta con estilo
            $activa->getStyle('A' . $i . ':B' . $i)->applyFromArray($cuentaHeaderStyle);
            $activa->setCellValueExplicit('A' . $i, $codcta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('B' . $i, $nomcuenta);

            // Saldo inicial con estilo destacado
            $saldoStyle = [
                'font' => ['bold' => true, 'italic' => true],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E6F0FA']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                    ]
                ],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
            ];

            $activa->setCellValueExplicit('E' . ($i - 1), 'SALDO INICIAL:', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('H' . ($i - 1), $saldo_inicial);
            $activa->getStyle('E' . ($i - 1) . ':H' . ($i - 1))->applyFromArray($saldoStyle);
            $activa->getStyle('H' . ($i - 1))->getNumberFormat()->setFormatCode('#,##0.00');

            $iniciom = $i;
            $header = false;
        }

        // Estilo de fila de datos
        $dataStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'E0E0E0']
                ]
            ]
        ];

        $prefixDoc = ($debe > 0) ? TipoPoliza::getPrefixForBancoIngreso($registro[$fila]["id_ctb_tipopoliza"]) : TipoPoliza::getPrefixForBancoEgreso($registro[$fila]["id_ctb_tipopoliza"]);
        $tipoDocumento = ($registro[$fila]["numcheque"] !== '-') ? 'CH: ' : ($prefixDoc ? $prefixDoc . ': ' : '');

        $activa->setCellValue('C' . $i, $fecha);
        $activa->setCellValueExplicit('D' . $i, $partida, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('E' . $i, $glosa);
        $activa->setCellValue('F' . $i, $debe);
        $activa->setCellValue('G' . $i, $haber);
        $activa->setCellValue('I' . $i, $nomchq);
        $activa->setCellValueExplicit('J' . $i, $tipoDocumento . $numdocShow, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);

        // Aplicar formato numérico
        $activa->getStyle('F' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
        $activa->getStyle('G' . $i)->getNumberFormat()->setFormatCode('#,##0.00');

        //SALDO
        $sumd = $sumd + $debe;
        $sumh = $sumh + $haber;
        $saldo = $saldo + $debe - $haber;
        $activa->setCellValue('H' . $i, '=H' . ($i - 1) . '+ F' . $i . '-G' . $i);
        $activa->getStyle('H' . $i)->getNumberFormat()->setFormatCode('#,##0.00');

        // Aplicar bordes a toda la fila
        $activa->getStyle('A' . $i . ':J' . $i)->applyFromArray($dataStyle);

        // Alineación
        $activa->getStyle('C' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $activa->getStyle('D' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $activa->getStyle('F' . $i . ':H' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        if ($fila != array_key_last($registro)) {
            if ($idcuenta != $registro[$fila + 1]["id_ctb_nomenclatura"]) {
                $header = true;
                $footer = true;
            }
        } else {
            $footer = true;
        }
        if ($footer) {
            $i++;

            // Estilo de subtotales
            $subtotalStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '296299']
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN
                    ]
                ],
                'alignment' => ['horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT]
            ];

            $activa->setCellValue('E' . $i, 'SUBTOTALES');
            $activa->setCellValue('F' . $i, $sumd);
            $activa->setCellValue('G' . $i, $sumh);

            $activa->getStyle('E' . $i . ':G' . $i)->applyFromArray($subtotalStyle);
            $activa->getStyle('F' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
            $activa->getStyle('G' . $i)->getNumberFormat()->setFormatCode('#,##0.00');

            $footer = false;
        }

        $fila++;
        $i++;
    }

    $i += 2;

    // Estilo para información adicional
    $infoStyle = [
        'font' => ['bold' => true, 'size' => 10],
        'fill' => [
            'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'F8F9FA']
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                'color' => ['rgb' => '296299']
            ]
        ]
    ];

    $activa->setCellValue('A' . $i, 'Total de Pólizas Procesadas:');
    $num_polizas = count(array_unique(array_column($registro, 'numcom')));
    $activa->setCellValue('B' . $i, $num_polizas);

    $activa->getStyle('A' . $i . ':B' . $i)->applyFromArray($infoStyle);
    $activa->getStyle('B' . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);


    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Bancos",
        'tipo' => "xlsx",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
