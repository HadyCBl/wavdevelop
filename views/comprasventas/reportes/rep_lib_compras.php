<?php
include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
    exit;
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    exit;
}
$idusuario = $_SESSION['id'];

include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

require __DIR__ . '/../../../fpdf/fpdf.php';
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Helpers\Beneq;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Helpers\Log;
use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++ RECEPCION DE DATOS Y VALIDACIONES PAPA' +++++++++++++++++++++++++++++++++++
    + [`finicio`, `ffin`,`finicio_fel`, `ffin_fel`],[`tipoLinea`],[],['usarFechaRegistro') , usarFechaFEL'] ++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

$status = false;
try {

    $dataProcess = [
        'fecha_inicio' => $_POST['datosval'][0][0],
        'fecha_fin' => $_POST['datosval'][0][1],
        'fecha_inicio_fel' => $_POST['datosval'][0][2],
        'fecha_fin_fel' => $_POST['datosval'][0][3],
        'tipoLinea' => $_POST['datosval'][1][0],
        'usarFechaRegistro' => $_POST['datosval'][3][0],
        'usarFechaFEL' => $_POST['datosval'][3][1],
        'tipoReporte' => $_POST['tipo']
    ];
    // Log::info("Datos recibidos para reporte de libro de compras: " .    print_r($dataProcess, true));

    $rules = [
        'usarFechaRegistro' => 'required|boolean',
        'usarFechaFEL' => 'required|boolean',
        'fecha_inicio' => 'validate_if:usarFechaRegistro,true|required|date',
        'fecha_fin' => 'validate_if:usarFechaRegistro,true|required|date|after_or_equal:fecha_inicio',
        'fecha_inicio_fel' => 'validate_if:usarFechaFEL,true|required|date',
        'fecha_fin_fel' => 'validate_if:usarFechaFEL,true|required|date|after_or_equal:fecha_inicio_fel',
        'tipoLinea' => 'required|string|max_length:2',
        'tipoReporte' => 'required|in:pdf,xlsx'
    ];

    $validator = Validator::make($dataProcess, $rules);
    if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
    }

    $dataProcess["usarFechaRegistro"] = filter_var($dataProcess["usarFechaRegistro"], FILTER_VALIDATE_BOOLEAN);
    $dataProcess["usarFechaFEL"] = filter_var($dataProcess["usarFechaFEL"], FILTER_VALIDATE_BOOLEAN);


    if (!$dataProcess['usarFechaFEL'] && !$dataProcess['usarFechaRegistro']) {
        throw new SoftException("Debe seleccionar al menos un filtro de fecha (Registro o FEL).");
    }


    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL ++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

    $where = ($dataProcess['tipoLinea'] == 0) ? "" : " AND tip.tipoLinea='{$dataProcess['tipoLinea']}'";

    // Construir WHERE dinámico según los filtros activos
    $whereFechas = "";
    $params = [];
    $titlereport = "Libro de compras ";

    if ($dataProcess['usarFechaRegistro'] === true) {
        $whereFechas .= 'AND pag.fecha BETWEEN ? AND ? ';
        $params[] = $dataProcess['fecha_inicio'];
        $params[] = $dataProcess['fecha_fin'];
        $titlereport .= " - Registro: " . Date::toDMY($dataProcess['fecha_inicio']) . " al " . Date::toDMY($dataProcess['fecha_fin']);
    }
    if ($dataProcess['usarFechaFEL'] === true) {
        $whereFechas .= 'AND feldata.fecha BETWEEN ? AND ? ';
        $params[] = $dataProcess['fecha_inicio_fel'];
        $params[] = $dataProcess['fecha_fin_fel'];
        $titlereport .= " / FEL: " . Date::toDMY($dataProcess['fecha_inicio_fel']) . " al " . Date::toDMY($dataProcess['fecha_fin_fel']);
    }

    $query = "SELECT DISTINCT
                pag.id AS id_recibo,
                pag.fecha,
                pag.recibo,
                pag.descripcion,
                pag.cliente,
                mov.id AS idMovimiento,
                mov.id_fel,
                mov.id_otr_tipo_ingreso,
                tip.tipoLinea,
                tip.nombre_gasto,
                mov.monto AS total_monto,
                imp.id_tipo,
                imp.id AS idImpuesto,
                COALESCE(imp.monto, 0) AS monto_impuesto,
                CASE
                    WHEN imp.id_tipo = 8 THEN 'IVA 12%'
                    WHEN imp.id_tipo = 9 THEN 'Impuesto de Combustible'
                    WHEN imp.id_tipo IS NULL THEN 'Sin Impuesto'
                    ELSE 'Otro Impuesto'
                END AS tipo_impuesto,
                feldata.numero_dte,
                feldata.numero_serie,
                feldata.fecha AS fecha_fel,
                feldata.concepto AS descripcion_fel,
                COALESCE(emis.nit, feldata.nit) AS nit,
                feldata.nit AS nit_emisor,
                emis.nombre AS nombre_emisor
            FROM
                otr_pago pag
            INNER JOIN
                otr_pago_mov mov ON mov.id_otr_pago = pag.id
            INNER JOIN
                otr_tipo_ingreso tip ON tip.id = mov.id_otr_tipo_ingreso
            LEFT JOIN
                otr_pago_mov_impuestos imp ON imp.id_movimiento = mov.id
            LEFT JOIN
                cv_otros_movimientos feldata ON feldata.id = mov.id_fel
            LEFT JOIN
                cv_emisor emis ON emis.id = feldata.id_receptor
            WHERE
                pag.estado = 1
                AND tip.tipo = 2
                AND tip.estado = 1
                $whereFechas
                AND (mov.id_fel IS NOT NULL OR imp.id IS NOT NULL)
                $where
            ORDER BY
                pag.id, mov.id_fel, mov.id, imp.id;";


    $database->openConnection();

    $result = $database->getAllResults($query, $params);
    if (empty($result)) {
        throw new SoftException("No se encontraron registros con los datos proporcionados.");
    }

    // $paramsImpuestos = $database->selectColumns("ctb_parametros_general", ["id_ctb_nomenclatura"], "id_tipo=8");
    // if (empty($paramsImpuestos)) {
    //     throw new SoftException("No se encontró la nomenclatura para el IVA. Verifique la configuración de impuestos.");
    // }
    // $ivaNomenclatura = $paramsImpuestos[0]["id_ctb_nomenclatura"];

    // $paramsImpuestos = $database->selectColumns("ctb_parametros_general", ["id_ctb_nomenclatura"], "id_tipo=9");
    // if (empty($paramsImpuestos)) {
    //     // $showmensaje = true;
    //     // throw new Exception("No se encontró la nomenclatura para el Impuesto de Combustible. Verifique la configuración de impuestos.");
    // }
    // $combustibleNomenclatura = $paramsImpuestos[0]["id_ctb_nomenclatura"] ?? 0;

    // Consulta para información de la institución
    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);
    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institución asignada a la agencia no encontrada.");
    }

    $status = true;
} catch (SoftException $e) {
    $database->rollback();
    $mensaje = $e->getMessage();
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este código de error ($codigoError).";
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    exit;
}

switch ($dataProcess['tipoReporte']) {
    case 'xlsx';
        printxls($result, $titlereport);
        break;
    case 'pdf':
        printpdf($result, $titlereport, $info);
        break;
}

//funcion para generar pdf
function printpdf($registro, $titlereport, $info)
{
    $oficina = Utf8::decode($info[0]["nom_agencia"]);
    $institucion = Utf8::decode($info[0]["nomb_comple"]);
    $direccionins = Utf8::decode($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    class PDF extends FPDF
    {
        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $datos;
        public $isSecondPage = false;

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

        function Header()
        {
            $fuente = "Arial";
            $hoy = date("d/m/Y H:i:s");

            $widthAll = 345;

            // Línea superior decorativa
            $this->SetDrawColor(41, 128, 185);
            $this->SetLineWidth(0.8);
            $this->Line(10, 8, $widthAll, 8);

            // Fecha y hora en esquina superior derecha
            $this->SetFont($fuente, 'I', 7);
            $this->SetTextColor(100, 100, 100);
            $this->SetXY(10, 10);
            $this->Cell(0, 3, 'Fecha de impresion: ' . $hoy, 0, 1, 'R');

            // Logo institucional
            $this->Image($this->pathlogoins, 12, 15, 35);

            // Información de la institución con mejor formato
            $this->SetTextColor(0, 0, 0);
            $this->SetFont($fuente, 'B', 11);
            $this->SetY(15);
            $this->Cell(0, 5, $this->institucion, 0, 1, 'C');

            $this->SetFont($fuente, '', 8);
            // $this->SetX(50);
            $this->Cell(0, 4, $this->oficina, 0, 1, 'C');

            // $this->SetX(50);
            $this->Cell(0, 4, $this->direccion, 0, 1, 'C');

            // $this->SetX(50);
            $this->Cell(0, 4, 'Tel: ' . $this->telefono . ' | Email: ' . $this->email, 0, 1, 'C');

            $this->SetFont($fuente, 'B', 8);
            // $this->SetX(50);
            $this->Cell(0, 4, 'NIT: ' . $this->nit, 0, 1, 'C');

            // Línea separadora con color
            $this->SetDrawColor(41, 128, 185);
            $this->SetLineWidth(0.5);
            $this->Line(10, 39, $widthAll, 39);

            $this->Ln(6);

            // Título del reporte con fondo de color
            $this->SetFillColor(41, 128, 185);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont($fuente, 'B', 11);
            $this->Cell(0, 6, Utf8::decode($this->datos), 0, 1, 'C', true);

            $this->Ln(3);

            // Encabezado de la tabla con colores profesionales
            $this->SetFillColor(52, 73, 94);
            $this->SetTextColor(255, 255, 255);
            $this->SetFont($fuente, 'B', 7);
            $this->SetDrawColor(200, 200, 200);

            $widthCells = [6, 17, 20, 17, 65, 23, 73, 20, 20, 20, 19, 19, 20];

            $this->Cell($widthCells[0], 6, 'No.', 1, 0, 'C', true);
            $this->Cell($widthCells[1], 6, 'Fecha', 1, 0, 'C', true);
            $this->Cell($widthCells[2], 6, 'Serie', 1, 0, 'C', true);
            $this->Cell($widthCells[3], 6, 'NIT', 1, 0, 'C', true);
            $this->Cell($widthCells[4], 6, 'Proveedor', 1, 0, 'C', true);
            $this->Cell($widthCells[5], 6, 'DTE', 1, 0, 'C', true);
            $this->Cell($widthCells[6], 6, 'Descripcion', 1, 0, 'C', true);
            $this->Cell($widthCells[7], 6, 'Compras GR', 1, 0, 'C', true);
            $this->Cell($widthCells[8], 6, 'Compras EX', 1, 0, 'C', true);
            $this->Cell($widthCells[9], 6, 'Servicios GR', 1, 0, 'C', true);
            $this->Cell($widthCells[10], 6, 'IVA', 1, 0, 'C', true);
            $this->Cell($widthCells[11], 6, 'IDP', 1, 0, 'C', true);
            $this->Cell($widthCells[12], 6, 'TOTAL', 1, 1, 'C', true);

            $this->SetTextColor(0, 0, 0);
            $this->SetDrawColor(220, 220, 220);
        }

        function Footer()
        {
            // Línea decorativa superior
            $this->SetY(-18);
            $this->SetDrawColor(41, 128, 185);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY(), 345, $this->GetY());

            $this->Ln(2);

            // Información del footer
            $this->SetFont('Arial', 'I', 7);
            $this->SetTextColor(100, 100, 100);
            // $this->Cell(90, 4, 'Microsystem+', 0, 0, 'L');
            $this->Cell(0, 4, 'Pagina ' . $this->PageNo() . '/{nb}', 0, 0, 'C');

            // Logo Microsystem pequeño
            // if (file_exists($this->pathlogo)) {
            //     $this->Image($this->pathlogo, 260, $this->GetY() - 2, 20);
            // }

            // $this->Ln(4);
            // $this->SetFont('Arial', 'I', 6);
            // $this->Cell(0, 3, 'Documento generado electronicamente', 0, 0, 'C');
        }
    }

    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $titlereport);
    $pdf->AliasNbPages();
    $pdf->AddPage('L', 'Legal'); // Landscape, tamaño legal (más grande que carta)
    $fuente = "Arial";
    $tamanio_linea = 4;

    $pdf->SetFont($fuente, '', 7);

    // Llamar a la función para agrupar los datos
    $facturasAgrupadas = agruparPorFactura($registro);

    $widthCells = [6, 17, 20, 17, 65, 23, 73, 20, 20, 20, 19, 19, 20];
    $contador = 0;

    // Variables para los totales generales
    $totalComprasGR = 0;
    $totalComprasEX = 0;
    $totalServicios = 0;
    $totalIVA = 0;
    $totalCombustible = 0;
    $totalGeneral = 0;

    // Alternar colores de fila
    $fill = false;

    foreach ($facturasAgrupadas as $factura) {
        $contador++;

        // Calcular totales por factura
        $comprasGR = 0;
        $comprasEX = 0;
        $servicios = 0;
        $iva = 0;
        $combustible = 0;

        foreach ($factura['movimientos'] as $movimiento) {
            if ($movimiento['tipoLinea'] === 'B') {
                // Si tiene IVA es gravada, si no es exenta
                if ($movimiento['impuestos']['iva'] > 0) {
                    $comprasGR += $movimiento['total_monto'];
                } else {
                    $comprasEX += $movimiento['total_monto'];
                }
            } elseif ($movimiento['tipoLinea'] === 'S') {
                $servicios += $movimiento['total_monto'];
            }

            $iva += $movimiento['impuestos']['iva'];
            $combustible += $movimiento['impuestos']['combustible'];
        }

        $total = $comprasGR + $comprasEX + $servicios + $iva + $combustible;

        // Sumar a los totales generales
        $totalComprasGR += $comprasGR;
        $totalComprasEX += $comprasEX;
        $totalServicios += $servicios;
        $totalIVA += $iva;
        $totalCombustible += $combustible;
        $totalGeneral += $total;

        // Establecer color de fondo alternado
        if ($fill) {
            $pdf->SetFillColor(245, 245, 245);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        // Imprimir una línea por factura con bordes sutiles
        $pdf->CellFit($widthCells[0], $tamanio_linea, $contador, 'LR', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[1], $tamanio_linea, Date::toDMY($factura['fecha']), 'LR', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[2], $tamanio_linea, Beneq::karely($factura['numero_serie'] ?? ''), 'LR', 0, 'L', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[3], $tamanio_linea, Beneq::karely($factura['nit'] ?? ''), 'LR', 0, 'L', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[4], $tamanio_linea, Utf8::decode(Beneq::karely($factura['nombre_emisor'] ?? '')), 'LR', 0, 'L', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[5], $tamanio_linea, Beneq::karely($factura['numero_dte'] ?? ''), 'LR', 0, 'C', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[6], $tamanio_linea, Utf8::decode(Beneq::karely($factura['descripcion'])), 'LR', 0, 'L', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[7], $tamanio_linea, Moneda::formato($comprasGR), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[8], $tamanio_linea, Moneda::formato($comprasEX), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[9], $tamanio_linea, Moneda::formato($servicios), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[10], $tamanio_linea, Moneda::formato($iva), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[11], $tamanio_linea, Moneda::formato($combustible), 'LR', 0, 'R', $fill, '', 1, 0);
        $pdf->CellFit($widthCells[12], $tamanio_linea, Moneda::formato($total), 'LR', 1, 'R', $fill, '', 1, 0);
        $fill = !$fill;
    }

    // Línea de cierre de la tabla
    $pdf->Cell(array_sum($widthCells), 0, '', 'T', 1);

    $pdf->Ln(2);

    // Imprimir los totales generales con diseño destacado
    $pdf->SetFont($fuente, 'B', 8);
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);

    $pdf->CellFit($widthCells[0] + $widthCells[1] + $widthCells[2] + $widthCells[3] + $widthCells[4] + $widthCells[5] + $widthCells[6], 5, "TOTALES GENERALES", 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[7], 5, 'Q ' . number_format($totalComprasGR, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[8], 5, 'Q ' . number_format($totalComprasEX, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[9], 5, 'Q ' . number_format($totalServicios, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[10], 5, 'Q ' . number_format($totalIVA, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[11], 5, 'Q ' . number_format($totalCombustible, 2, '.', ','), 1, 0, 'R', true, '', 1, 0);
    $pdf->CellFit($widthCells[12], 5, 'Q ' . number_format($totalGeneral, 2, '.', ','), 1, 1, 'R', true, '', 1, 0);

    // Línea decorativa final
    $pdf->Ln(3);
    $pdf->SetDrawColor(41, 128, 185);
    $pdf->SetLineWidth(0.5);
    $pdf->Line(10, $pdf->GetY(), 345, $pdf->GetY());

    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro_Compras",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
}

function agruparPorFactura($registros)
{
    $facturasAgrupadas = [];
    $contadorSinFEL = 0;

    foreach ($registros as $fila) {
        $idRecibo = $fila['id_recibo'];
        $idFel = $fila['id_fel'];
        $idMovimiento = $fila['idMovimiento'];

        // Crear clave única para agrupar
        // Si tiene FEL, agrupa por id_fel
        // Si no tiene FEL, agrupa todos los items sin FEL del mismo recibo
        if ($idFel !== null) {
            $claveFactura = 'FEL_' . $idFel;
        } else {
            $claveFactura = 'SINFEL_' . $idRecibo;
        }

        // Inicializar la factura si no existe
        if (!isset($facturasAgrupadas[$claveFactura])) {

            $descripcion = (!empty($fila['descripcion_fel']) && $fila['descripcion_fel'] != '') ? $fila['descripcion_fel'] : $fila['descripcion'];

            $facturasAgrupadas[$claveFactura] = [
                'id_recibo' => $idRecibo,
                'id_fel' => $idFel,
                'fecha' => $fila['fecha'],
                'fecha_fel' => $fila['fecha_fel'] ?? $fila['fecha'],
                'recibo' => $fila['recibo'],
                'descripcion' => $descripcion,
                'cliente' => $fila['cliente'],
                'numero_dte' => $fila['numero_dte'] ?? '',
                'numero_serie' => $fila['numero_serie'] ?? '',
                'nit' => $fila['nit'] ?? '',
                'nombre_emisor' => $fila['nombre_emisor'] ?? $fila['cliente'],
                'es_fel' => ($idFel !== null),
                'movimientos' => []
            ];
        }

        // Inicializar el movimiento si no existe
        if (!isset($facturasAgrupadas[$claveFactura]['movimientos'][$idMovimiento])) {
            $facturasAgrupadas[$claveFactura]['movimientos'][$idMovimiento] = [
                'id_movimiento' => $idMovimiento,
                'tipoLinea' => $fila['tipoLinea'],
                'nombre_gasto' => $fila['nombre_gasto'],
                'total_monto' => $fila['total_monto'],
                'impuestos' => [
                    'iva' => 0,
                    'combustible' => 0,
                    'otros' => 0
                ]
            ];
        }

        // Clasificar el impuesto por id_tipo
        if ($fila['id_tipo'] !== null) {
            switch ($fila['id_tipo']) {
                case 8: // IVA 12%
                    $facturasAgrupadas[$claveFactura]['movimientos'][$idMovimiento]['impuestos']['iva'] += $fila['monto_impuesto'];
                    break;
                case 9: // Impuesto de Combustible
                    $facturasAgrupadas[$claveFactura]['movimientos'][$idMovimiento]['impuestos']['combustible'] += $fila['monto_impuesto'];
                    break;
                default: // Otros impuestos
                    $facturasAgrupadas[$claveFactura]['movimientos'][$idMovimiento]['impuestos']['otros'] += $fila['monto_impuesto'];
                    break;
            }
        }
    }

    return $facturasAgrupadas;
}

function printxls($registro, $titlereport)
{
    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    $tamanioTabla = 11;

    $excel = new Spreadsheet();
    $activa = $excel->getActiveSheet();
    $activa->setTitle("Compras");

    // Título del reporte
    $activa->setCellValue("A4", "REPORTE");
    $activa->setCellValue("A5", strtoupper($titlereport));

    $activa->getStyle("A4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $activa->getStyle("A5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $activa->mergeCells('A4:N4');
    $activa->mergeCells('A5:N5');

    // Encabezado de la tabla
    $encabezado_tabla = [
        "No.",
        "Fecha",
        "Doc.",
        "Serie",
        "NIT",
        "Proveedor",
        "DTE",
        "Descripcion",
        "Compras GR",
        "Compras EX",
        "Servicios GR",
        "IVA",
        "Imp Combustible",
        "TOTAL"
    ];
    $activa->fromArray($encabezado_tabla, null, 'A8')->getStyle('A8:N8')->getFont()->setName($fuente)->setBold(true);

    // Agrupar los datos por factura
    $facturasAgrupadas = agruparPorFactura($registro);

    $contador = 0;
    $i = 9; // Fila inicial para los datos

    // Variables para los totales
    $totalComprasGR = 0;
    $totalComprasEX = 0;
    $totalServicios = 0;
    $totalIVA = 0;
    $totalCombustible = 0;
    $totalGeneral = 0;

    foreach ($facturasAgrupadas as $factura) {
        $contador++;

        // Calcular totales por factura
        $comprasGR = 0;
        $comprasEX = 0;
        $servicios = 0;
        $iva = 0;
        $combustible = 0;

        foreach ($factura['movimientos'] as $movimiento) {
            if ($movimiento['tipoLinea'] === 'B') {
                // Si tiene IVA es gravada, si no es exenta
                if ($movimiento['impuestos']['iva'] > 0) {
                    $comprasGR += $movimiento['total_monto'];
                } else {
                    $comprasEX += $movimiento['total_monto'];
                }
            } elseif ($movimiento['tipoLinea'] === 'S') {
                $servicios += $movimiento['total_monto'];
            }

            $iva += $movimiento['impuestos']['iva'];
            $combustible += $movimiento['impuestos']['combustible'];
        }

        $total = $comprasGR + $comprasEX + $servicios + $iva + $combustible;

        // Sumar a los totales generales
        $totalComprasGR += $comprasGR;
        $totalComprasEX += $comprasEX;
        $totalServicios += $servicios;
        $totalIVA += $iva;
        $totalCombustible += $combustible;
        $totalGeneral += $total;

        // Imprimir una línea por factura en Excel
        $activa->setCellValue('A' . $i, $contador);
        $activa->setCellValue('B' . $i, date("d-m-Y", strtotime($factura['fecha'])));
        $activa->setCellValueExplicit('C' . $i, (string)$factura['recibo'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('D' . $i, $factura['numero_serie'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValueExplicit('E' . $i, $factura['nit'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('F' . $i, $factura['nombre_emisor'] ?? '');
        $activa->setCellValueExplicit('G' . $i, $factura['numero_dte'] ?? '', \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $activa->setCellValue('H' . $i, $factura['descripcion']);
        $activa->setCellValueExplicit('I' . $i, $comprasGR, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('J' . $i, $comprasEX, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('K' . $i, $servicios, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('L' . $i, $iva, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('M' . $i, $combustible, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
        $activa->setCellValueExplicit('N' . $i, $total, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

        $i++;
    }

    // Imprimir los totales al final
    $activa->setCellValue('D' . $i, "TOTALES:");
    $activa->setCellValueExplicit('I' . $i, $totalComprasGR, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('J' . $i, $totalComprasEX, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('K' . $i, $totalServicios, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('L' . $i, $totalIVA, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('M' . $i, $totalCombustible, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
    $activa->setCellValueExplicit('N' . $i, $totalGeneral, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);

    // Estilo de las celdas
    $activa->getStyle("A8:N" . $i)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
    $activa->getStyle("A8:N" . $i)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
    $activa->getStyle("A8:N" . $i)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);
    $activa->getStyle("A8:N" . $i)->getFont()->setName($fuente)->setSize($tamanioTabla);

    $activa->getStyle("A8:N" . $i)->getAlignment()->setWrapText(true);

    // Ajustar el ancho de las columnas automáticamente
    foreach (range('A', 'N') as $columna) {
        $activa->getColumnDimension($columna)->setAutoSize(true);
    }

    // Generar el archivo Excel
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    // Enviar el archivo como respuesta
    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "Libro Compras",
        'tipo' => "vnd.ms-excel",
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
    exit;
}
function obtenerContador($restart = false)
{
    static $contador = 0;
    $contador = ($restart == false) ? $contador + 1 : $restart;
    return $contador;
}
function obtenerLetra($columna)
{
    $letra = '';
    $columna--; // Decrementar la columna para que coincida con el índice de las letras del abecedario (empezando desde 0)

    while ($columna >= 0) {
        $letra = chr($columna % 26 + 65) . $letra; // Convertir el índice de columna a letra de Excel
        $columna = intval($columna / 26) - 1;
    }

    return $letra;
}
