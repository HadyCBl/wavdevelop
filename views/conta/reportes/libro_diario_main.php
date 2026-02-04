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
ini_set('memory_limit', '1536M');
ini_set('max_execution_time', '3600');

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

require '../../../fpdf/fpdf.php';
$hoy = date("Y-m-d");

use Micro\Helpers\Log;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Helpers\Beneq;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
// use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
// $valida = $radios[1];
$tipo = $_POST["tipo"];

// Variables para aplicar los chunks
$pageSize = 500; // Registros por chunk
$chunkSize = 500; // Tamaño del chunk para la función printpdf
$page = isset($_POST['page']) ? (int)$_POST['page'] : 0; // Si usas AJAX
$offset = $page * $pageSize;

if ($radios[2] == "frango" && $inputs[0] > $inputs[1]) {
    echo json_encode(['status' => 0, 'mensaje' => 'Rango de fechas inválido']);
    return;
}

$condi = "";
// AGENCIA
if ($radios[3] == "anyofi") {
    $condi = $condi . " AND id_agencia2=" . $selects[0];
}

// FUENTE DE FONDOS
if ($radios[1] == "anyf") {
    $condi = $condi . " AND id_fuente_fondo=" . $selects[1];
}
$titlereport = " AL " . Date::toDMY($hoy);

// RANGO DE FECHAS
if ($radios[2] == "frango") {
    $condi = $condi . " AND feccnt BETWEEN '" . $inputs[0] . "' AND '" . $inputs[1] . "'";
    $titlereport = " DEL " . Date::toDMY($inputs[0]) . " AL " . Date::toDMY($inputs[1]);
}

// Obtener el número total de registros
$strquery_count = "SELECT COUNT(*) as total FROM ctb_diario_mov WHERE estado=1" . $condi;


$status = false;
try {
    $database->openConnection();
    $totalRecords = $database->getAllResults($strquery_count)[0]['total'];
    $ctbmovdata = [];
    $chunkIndex = 0;

    while ($chunkIndex * $pageSize < $totalRecords) {
        $offset = $chunkIndex * $pageSize;
        $strquery = "SELECT id_ctb_diario, fuente_fondo_des, numcom, numdoc, ccodcta, cdescrip, debe, haber, glosa, feccnt
                        from ctb_diario_mov 
                        WHERE estado=1" . $condi . " LIMIT $pageSize OFFSET $offset";
        $chunkData = $database->getAllResults($strquery);
        $ctbmovdata = array_merge($ctbmovdata, $chunkData);
        $chunkIndex++;
    }
    if (empty($ctbmovdata)) {
        throw new SoftException("No se encontraron registros");
    }

    // Contar el número de pólizas activas
    $strquery_count = "SELECT COUNT(DISTINCT numcom) as num_polizas FROM ctb_diario_mov WHERE estado=1" . $condi;
    $query_count = $database->getAllResults($strquery_count);
    $num_polizas = $query_count[0]['num_polizas'];

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

switch ($tipo) {
    case 'xlsx';
        printxls($ctbmovdata, $num_polizas, $chunkSize);
        break;
    case 'pdf':
        printpdf($ctbmovdata, [$titlereport], $info, $num_polizas, $chunkSize);
        break;
}


// Función para generar PDF
function printpdf($registro, $datos, $info, $num_polizas, $chunkSize = 500)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    // Clase PDF extendida de FPDF
    class PDF extends FPDF
    {
        // Atributos de la clase
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
        }

        // Cabecera de página
        function Header()
        {
            $fuente = "Courier";
            $hoy = date("Y-m-d H:i:s");
            // Fecha y usuario que generó el reporte
            $this->SetFont($fuente, '', 7);
            $this->Cell(0, 2, $hoy, 0, 1, 'R');
            // Logo de la agencia
            $this->Image($this->pathlogoins, 10, 13, 33);

            // Tipo de letra para el encabezado
            $this->SetFont($fuente, 'B', 9);
            // Título
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            // Salto de línea
            $this->Ln(10);

            $this->SetFont($fuente, 'B', 10);
            // Título del reporte
            $this->SetFillColor(204, 229, 255);
            $this->Cell(0, 5, 'LIBRO DIARIO LEGAL' . $this->datos[0], 0, 1, 'C', true);
            // Color de encabezado de lista
            $this->SetFillColor(555, 255, 204);
            // Títulos de encabezado de tabla
            $ancho_linea = 47;

            $widths = [48, 69, 37, 37];

            $this->Cell($widths[0], 5, 'CODIGO', 'B', 0, 'L');
            $this->Cell($widths[1], 5, 'NOMBRE', 'B', 0, 'L');
            $this->Cell($widths[2], 5, 'DEBE', 'B', 0, 'R');
            $this->Cell($widths[3], 5, 'HABER', 'B', 1, 'R');
            $this->Ln(4);
        }

        // Pie de página
        function Footer()
        {
            // Posición: a 1 cm del final
            $this->SetY(-15);
            // Logo
            // $this->Image($this->pathlogo, 175, 279, 28);
            // Arial italic 8
            $this->SetFont('Arial', 'I', 8);
            // Número de página
            $this->Cell(0, 10, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $datos);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = "Courier";
    $tamanio_linea = 3;
    $ancho_linea2 = 47;
    $pdf->SetFont($fuente, '', 8);
    $fila = 0;
    // $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $header = true;
    $footer = false;

    $widths = [48, 69, 37, 37];

    // Procesar los datos en chunks
    $totalRecords = count($registro);
    $chunkIndex = 0;

    while ($chunkIndex * $chunkSize < $totalRecords) {
        $chunk = array_slice($registro, $chunkIndex * $chunkSize, $chunkSize);

        foreach ($chunk as $fila => $data) {
            $partida = $data["numcom"];
            $fecha = Date::toDMY($data["feccnt"]);
            $numdoc = $data["numdoc"];
            $glosa = $data["glosa"];
            $codcta = $data["ccodcta"];
            $nomcuenta = Utf8::decode($data["cdescrip"]);
            $debe = $data["debe"];
            $haber = $data["haber"];
            $idnumcom = $data["id_ctb_diario"];

            if ($header) {
                $pdf->SetFont($fuente, 'B', 8);
                $pdf->CellFit($ancho_linea2 + 16, $tamanio_linea + 1, 'Partida No.: ' . $partida, 'B', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2 + 15, $tamanio_linea + 1, 'Fecha: ' . $fecha, 'B', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($ancho_linea2 + 16, $tamanio_linea + 1, 'Doc.:' . $numdoc, 'B', 1, 'L', 0, '', 1, 0);
                $header = false;
            }
            $pdf->SetFont($fuente, '', 8);
            $pdf->CellFit($widths[0], $tamanio_linea + 1, $codcta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($widths[1], $tamanio_linea + 1, $nomcuenta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($widths[2], $tamanio_linea + 1, number_format($debe, 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($widths[3], $tamanio_linea + 1, number_format($haber, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            $sumd = $sumd + $debe;
            $sumh = $sumh + $haber;
            $sumtd = $sumtd + $debe;
            $sumth = $sumth + $haber;

            if ($fila != array_key_last($chunk)) {
                if ($idnumcom != $chunk[$fila + 1]["id_ctb_diario"]) {
                    $header = true;
                    $footer = true;
                }
            } else {
                $footer = true;
            }

            if ($footer) {
                $widthGlosa = $widths[0] + $widths[1];
                $hTotales = ($tamanio_linea + 1) * 2 + 1;
                
                // IMPORTANTE: Establecer la fuente ANTES de calcular la altura
                $pdf->SetFont($fuente, 'I', 8);
                $hGlosa = Beneq::getMultiCellHeight($pdf, $widthGlosa, 4, Utf8::decode($glosa), 2);
                
                $hBloque = max($hGlosa, $hTotales);
                $limiteY = $pdf->GetPageHeight() - 17;

                // Log::info("Verificando espacio para glosa y totales", [
                //     'partida' => $partida,
                //     'currentY' => $pdf->GetY(),
                //     'hBloque' => $hBloque,
                //     'limiteY' => $limiteY,
                //     'hGlosa' => $hGlosa,
                //     'hTotales' => $hTotales,
                //     'limitePage' => $pdf->GetPageHeight(),
                //     'glosaLength' => strlen($glosa),
                // ]);

                // Verificar si cabe el bloque completo (glosa + totales + margen)
                if ($pdf->GetY() + $hBloque + 4 > $limiteY) {
                    // Log::info("Agregando nueva pagina antes de imprimir glosa y totales", [
                    //     'partida' => $partida,
                    //     'currentY' => $pdf->GetY(),
                    //     'hBloque' => $hBloque,
                    //     'limiteY' => $limiteY,
                    // ]);
                    $pdf->AddPage();
                }

                // Obtener coordenadas DESPUÉS de verificar nueva página
                $x = $pdf->GetX();
                $y = $pdf->GetY();

                // Imprimir glosa (ya tenemos la fuente establecida)
                $pdf->MultiCell($widthGlosa, 4, Utf8::decode($glosa));

                // Obtener la altura real que ocupó el MultiCell
                $yAfterGlosa = $pdf->GetY();
                $realGlosaHeight = $yAfterGlosa - $y;

                // Posicionar para los totales al lado de la glosa
                $pdf->SetXY($x + $widthGlosa, $y);
                
                $pdf->SetFont($fuente, '', 8);
                $pdf->Cell($widths[2], $tamanio_linea + 2, number_format($sumd, 2), 'BT', 0, 'R');
                $pdf->Cell($widths[3], $tamanio_linea + 2, number_format($sumh, 2), 'BT', 1, 'R');

                $pdf->SetX($x + $widthGlosa);
                $pdf->Cell($widths[2] + $widths[3], 1, '', 'B', 1);

                // Usar la altura real de la glosa para posicionar después
                $pdf->SetY(max($yAfterGlosa, $y + $hTotales) + 2);

                $sumd = 0;
                $sumh = 0;
                $footer = false;
                $pdf->Ln(1);
            }
        }
        $chunkIndex++;
    }

    $pdf->Cell($ancho_linea2 * 2 + 21, $tamanio_linea, 'TOTAL GENERAL: ', '', 0, 'R');
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, number_format($sumtd, 2, '.', ','), 'BT', 0, 'R');
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, number_format($sumth, 2, '.', ','), 'BT', 1, 'R');
    $pdf->Cell($ancho_linea2 * 2 + 21, $tamanio_linea, ' ', '', 0, 'R');
    $pdf->Cell($ancho_linea2 * 2 - 20, $tamanio_linea / 4, ' ', 'B', 1, 'R');

    // Agregar el número de pólizas al final del reporte
    $pdf->SetFont($fuente, 'B', 8); // Ajusta la fuente y el tamaño para que coincidan con el resto del documento
    $pdf->Ln(10); // Añade un salto de línea para separar visualmente esta sección del resto del contenido
    $pdf->Cell($ancho_linea2 * 2 + 21, $tamanio_linea, ' ', '', 0, 'R'); // Añade una celda vacía para alinear el texto
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, 'No. Polizas:', 'BT', 0, 'R'); // Añade el texto
    $pdf->Cell($ancho_linea2 - 10, $tamanio_linea + 2, $num_polizas, 'BT', 1, 'R'); // Añade el número de pólizas

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $paginas = $pdf->PageNo();

    $opResult = array(
        'status' => 1,
        'pages' => $paginas,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Diario",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );

    // SE OBLIGA A DESCARGA DIRECTA CUANDO LA CANTIDAD DE PAGINAS SUPERA O ES IGUAL A 500
    if ($paginas >= 500) {
        $opResult["download"] = 1;
    }
    echo json_encode($opResult);
}

//funcion para generar archivo excel
function printxls($registro, $num_polizas, $chunkSize = 500)
{
    require '../../../vendor/autoload.php';

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("LibroDiario");

    $activa->getColumnDimension("A")->setWidth(15);
    $activa->getColumnDimension("B")->setWidth(15);
    $activa->getColumnDimension("C")->setWidth(15);
    $activa->getColumnDimension("D")->setWidth(15);
    $activa->getColumnDimension("E")->setWidth(25);
    $activa->getColumnDimension("F")->setWidth(20);
    $activa->getColumnDimension("G")->setWidth(15);
    $activa->getColumnDimension("H")->setWidth(15);
    // $activa->getColumnDimension("I")->setWidth(15);

    $activa->setCellValue('A1', 'PARTIDA');
    $activa->setCellValue('B1', 'FECHA');
    $activa->setCellValue('C1', 'DOCUMENTO');
    $activa->setCellValue('D1', 'CUENTA');
    $activa->setCellValue('E1', 'NOMBRE CUENTA');
    $activa->setCellValue('F1', 'FONDO');
    $activa->setCellValue('G1', 'DEBE');
    $activa->setCellValue('H1', 'HABER');
    $aux = 0;
    $sumd = 0;
    $sumh = 0;
    $sumtd = 0;
    $sumth = 0;
    $fila = 0;
    $header = true;
    $footer = false;
    $i = 2;

    // Procesar los datos en chunks
    $totalRecords = count($registro);
    $chunkIndex = 0;

    while ($chunkIndex * $chunkSize < $totalRecords) {
        $chunk = array_slice($registro, $chunkIndex * $chunkSize, $chunkSize);

        foreach ($chunk as $fila => $data) {
            $partida = $data["numcom"];
            $fecha = ($data["feccnt"]);
            $numdoc = $data["numdoc"];
            $glosa = $data["glosa"];
            $codcta = $data["ccodcta"];
            $nomcuenta = $data["cdescrip"];
            $debe = $data["debe"];
            $haber = $data["haber"];
            $idnumcom = $data["id_ctb_diario"];
            $fondo = $data["fuente_fondo_des"];

            if ($header) {
                $activa->getStyle('A' . $i . ':C' . $i)->getFont()->setBold(true);
                $activa->setCellValueExplicit('A' . $i, $partida, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $activa->setCellValue('B' . $i, $fecha);
                $activa->setCellValueExplicit('C' . $i, $numdoc, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                $header = false;
            }

            $activa->setCellValueExplicit('D' . $i, $codcta, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            $activa->setCellValue('E' . $i, $nomcuenta);
            $activa->setCellValue('F' . $i, $fondo);
            $activa->setCellValue('G' . $i, $debe);
            $activa->setCellValue('H' . $i, $haber);
            //$activa->setCellValue('H' . $i, $idfondo);
            $sumd += $debe;
            $sumh += $haber;
            $sumtd += $debe;
            $sumth += $haber;

            if ($fila != array_key_last($chunk)) {
                if ($idnumcom != $chunk[$fila + 1]["id_ctb_diario"]) {
                    $header = true;
                    $footer = true;
                }
            } else {
                $footer = true;
            }
            $i++;
            if ($footer) {
                $activa->mergeCells('A' . $i . ':F' . $i);
                $activa->getStyle('A' . $i . ':H' . $i)->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setARGB('CCCCCC');
                $activa->getStyle('A' . $i . ':H' . $i)->getFont()->setBold(true);
                $activa->setCellValue('A' . $i, $glosa);
                $activa->setCellValue('G' . $i, $sumd);
                $activa->setCellValue('H' . $i, $sumh);
                $sumd = 0;
                $sumh = 0;
                $footer = false;
                $i++;
                $i++;
            }
        }
        $chunkIndex++;
    }

    $i++;
    $activa->setCellValue('G' . $i, $sumtd);
    $activa->setCellValue('H' . $i, $sumth);

    // Agregar el número de pólizas al final del reporte
    $i++;
    $activa->setCellValue('A' . $i, 'Número de Pólizas Activas:');
    $activa->setCellValue('B' . $i, $num_polizas);

    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Diario",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
