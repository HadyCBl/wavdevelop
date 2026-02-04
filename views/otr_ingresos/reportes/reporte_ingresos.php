<?php

/**
 * Maneja la solicitud GET redirigiendo a una página 404.
 */

include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();

use Micro\Helpers\Log;
use Micro\Generic\Date;
use Micro\Generic\Utf8;
use Micro\Helpers\CSRFProtection;

/**
 * Recupera los datos POST enviados por el formulario.
 */
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];
$tipo = $_POST["tipo"];

/**
 * Valida el token CSRF.
 * Si el token es inválido, deniega la solicitud y devuelve un mensaje de error en formato JSON.
 */
$csrf = new CSRFProtection();

if (!isset($inputs[2]) || !($csrf->validateToken($inputs[2], false))) {
    $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
    $opResult = array(
        'status' => 0,
        'mensaje' => $errorcsrf
    );
    echo json_encode($opResult);
    return;
}

/**
 * Verifica si la sesión ha expirado.
 * Si la sesión ha expirado, devuelve un mensaje de error en formato JSON.
 */
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['status' => 0, 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente']);
    return;
}

/**
 * Recupera la información del usuario de la sesión.
 */
$idusuario = $_SESSION['id'];
$nombreusu = $_SESSION['nombre'] . ' ' . $_SESSION['apellido'];

/**
 * Incluye la configuración de la base de datos y funciones de utilidad.
 */
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';

/**
 * Carga las bibliotecas necesarias para la generación de PDF y manipulación de hojas de cálculo.
 * Establece la zona horaria predeterminada, el límite de memoria y el tiempo máximo de ejecución.
 */
require __DIR__ . '/../../../fpdf/fpdf.php';
// require __DIR__ . '/../../../vendor/autoload.php';
// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
ini_set('memory_limit', '4096M');
ini_set('max_execution_time', '3600');

use PhpOffice\PhpSpreadsheet\Calculation\MathTrig\Round;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

$tipoconsulta = 0;

/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++ VALIDACIONES PAPA' +++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
if (!validateDate($inputs[0], 'Y-m-d') || !validateDate($inputs[1], 'Y-m-d')) {
    echo json_encode(['mensaje' => 'Fecha inválida, ingrese una fecha correcta', 'status' => 0]);
    return;
}
if ($inputs[0] > $inputs[1]) {
    echo json_encode(['mensaje' => 'Rango de fechas Inválido', 'status' => 0]);
    return;
}


/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    +++++++++++++++++++++++++++++++++++++++ ARMANDO LA QUERY FINAL +++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
/**
 * @var string $fecinicio Fecha de inicio del reporte, obtenida de los inputs.
 * @var string $fecfin Fecha de fin del reporte, obtenida de los inputs.
 * @var string $where Condición adicional para la consulta SQL basada en la selección de agencia.
 * @var string $titlereport Título del reporte con formato de fechas.
 *
 * @var bool $showmensaje Indicador para mostrar mensaje de error al usuario final, dependiendo del tipo de error.
 * 
 * @throws Exception Si no se encuentran registros o si la institución asignada a la agencia no es encontrada.
 * 
 * @var array $result Resultados de la consulta principal.
 * @var array $info Información de la cooperativa y agencia.
 * @var bool $status Estado de la operación (true si es exitosa, false en caso contrario).
 * 
 * @var string $mensaje Mensaje de error a mostrar en caso de fallo.
 * @var int $codigoError Código de error generado para el log.
 * 
 * @var string $nomagencia Nombre de la agencia o indicación de todas las agencias.
 * @var string $texto_reporte Texto descriptivo del reporte generado.
 */
$fecinicio = $inputs[0];
$fecfin = $inputs[1];
$where = ($selects[0] == "0") ? "" : " AND usu.id_agencia=" . $selects[0];
$tipoMovimiento = $selects[1];
$where .= ($tipoMovimiento != "0") ? " AND tpm.id_otr_tipo_ingreso=" . $tipoMovimiento : "";

$titlereport = " DEL " . Date::toDmY($fecinicio) . " AL " . Date::toDmY($fecfin);


$query = "SELECT 
    tp.id AS idfact, 
    tp.recibo AS recibo, 
    tp.cliente AS cli, 
    tp.fecha AS fecha, 
    IF(tpi.tipo = 1, 'INGRESO', 'EGRESO') AS tipomov, 
    tp.descripcion AS descripcion, 
    tpi.nombre_gasto AS detalle, 
    tpm.monto AS monto_movimiento,
    opmi.monto AS monto_impuesto,
    CASE 
        WHEN opmi.id_tipo = 8 THEN 'IVA 12%'
        WHEN opmi.id_tipo = 9 THEN 'Impuesto de Combustible'
        ELSE 'Otro Impuesto'
    END AS tipo_impuesto,
    usu.nombre, 
    usu.apellido, 
    ofi.nom_agencia,tpm.id idMovimiento,tipoadicional
FROM otr_pago tp
INNER JOIN otr_pago_mov tpm ON tpm.id_otr_pago = tp.id 
INNER JOIN otr_tipo_ingreso tpi ON tpm.id_otr_tipo_ingreso = tpi.id 
LEFT JOIN otr_pago_mov_impuestos opmi ON tpm.id = opmi.id_movimiento
INNER JOIN tb_usuario usu ON (
    (tp.created_by = usu.id_usu AND tp.tipoadicional != 3) 
    OR 
    (tp.tipoadicional = 3 AND tp.cliente = usu.id_usu)
)
INNER JOIN tb_agencia ofi ON ofi.id_agencia = usu.id_agencia
WHERE tpi.tipo = ? AND (tp.fecha BETWEEN ? AND ?) AND tp.estado = 1 $where 
ORDER BY tp.id, opmi.id";

$tipo_ingreso = ($radios[0] == 1) ? "INGRESOS" : "EGRESOS";

$showmensaje = false;
try {
    $database->openConnection();
    $result = $database->getAllResults($query, [$radios[0], $fecinicio, $fecfin]);

    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontraron registros.");
    }

    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=?", [$_SESSION['id_agencia']]);

    if (empty($info)) {
        $showmensaje = true;
        throw new Exception("Institucion asignada a la agencia no encontrada");
    }
    $status = true;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = false;
} finally {
    $database->closeConnection();
}

if (!$status) {
    $opResult = array('status' => 0, 'mensaje' => $mensaje);
    echo json_encode($opResult);
    return;
}

$nomagencia = ($selects[0] == 0) ? " DE TODAS LAS AGENCIAS " : " DE LA AGENCIA: " . strtoupper($result[0]['nom_agencia']);
$texto_reporte = "REPORTE DE " . $tipo_ingreso . " DEL " .  setdatefrench($inputs[0]) . " AL " . setdatefrench($inputs[1]) . $nomagencia;

$movimientos = [];
foreach ($result as $key => $row) {
    $idFactura = $row['idfact'];
    $idMovimiento = $row['idMovimiento'];

    // Si la factura no existe en el array, inicialízala
    if (!isset($movimientos[$idFactura])) {
        $movimientos[$idFactura] = [
            'idfact' => $idFactura,
            'recibo' => $row['recibo'],
            'cliente' => ($row['cli'] == "") ? " " : $row['cli'],
            'fecha' => $row['fecha'],
            'tipomov' => $row['tipomov'],
            'descripcion' => $row['descripcion'],
            'tipoadicional' => $row['tipoadicional'],
            // 'detalle' => $row['detalle'],
            'nombreAgencia' => $row['nom_agencia'],
            'nombreUsuario' => $row['nombre'] . ' ' . $row['apellido'],
            'movimientos' => [] // Aquí se almacenarán los movimientos de la factura
        ];
    }

    // Si el movimiento no existe en el array de la factura, inicialízalo
    if (!isset($movimientos[$idFactura]['movimientos'][$idMovimiento])) {
        $movimientos[$idFactura]['movimientos'][$idMovimiento] = [
            'idMovimiento' => $idMovimiento,
            'detalle' => $row['detalle'],
            'monto_movimiento' => $row['monto_movimiento'],
            'impuestos' => [] // Aquí se almacenarán los impuestos del movimiento
        ];
    }

    // Si hay un impuesto asociado, agrégalo al array de impuestos del movimiento
    if (!empty($row['monto_impuesto'])) {
        $movimientos[$idFactura]['movimientos'][$idMovimiento]['impuestos'][] = [
            'tipo_impuesto' => $row['tipo_impuesto'],
            'monto_impuesto' => $row['monto_impuesto']
        ];
    }
}

// $opResult = array('status' => 0, 'mensaje' => "probando sonido", 'data' => $movimientos);
// echo json_encode($opResult);
// return;

switch ($tipo) {
    case 'xlsx':
        //printxls($result, [$texto_reporte, $nombreusu, $hoy, $nomestado,1]);
        break;
    case 'pdf':
        printpdf($movimientos, [$texto_reporte, $nombreusu, $hoy, $tipo_ingreso, $inputs[1]], $info);
        break;
}

//FUNCION PARA GENERAR EL REPORTE EN PDF
function printpdf($movimientos, $datos, $info)
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
        public $oficina;
        public $institucion;
        public $direccion;
        public $email;
        public $telefono;
        public $nit;
        public $pathlogo;
        public $pathlogoins;
        public $titulo;
        public $user;
        public $conexion;

        public function __construct($oficina, $institucion, $direccion, $email, $telefono, $nit, $pathlogo, $pathlogoins, $titulo, $user, $conexion)
        {
            parent::__construct();
            $this->oficina = $oficina;
            $this->institucion = $institucion;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->titulo = $titulo;
            $this->user = $user;
            $this->conexion = $conexion;
            // $this->DefOrientation = 'P';
        }

        // Cabecera de página
        function Header()
        {
            // Logo de la agencia
            if (file_exists($this->pathlogoins)) {
                $this->Image($this->pathlogoins, 12, 8, 28);
            }

            // Información del encabezado (derecha superior)
            $hoy = date("d/m/Y H:i");
            $this->SetFont('Arial', '', 7);
            $this->SetTextColor(80, 80, 80);
            $this->SetXY($this->GetPageWidth() - 55, 8);
            $this->Cell(45, 3, $hoy, 0, 1, 'R');
            $this->SetX($this->GetPageWidth() - 55);
            $this->Cell(45, 3, decode_utf8($this->user), 0, 1, 'R');

            // Información de la institución (centro)
            $this->SetY(10);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 4, $this->institucion, 0, 1, 'C');
            
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefono . ' | Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 0, 1, 'C');

            // Línea separadora simple
            $this->SetDrawColor(180, 180, 180);
            $this->SetLineWidth(0.3);
            $this->Line(10, 35, $this->GetPageWidth() - 10, 35);
            
            $this->Ln(3);

            // Título del reporte simple
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(0, 0, 0);
            $this->Cell(0, 5, 'REPORTE DETALLADO', 0, 1, 'C');
            $this->SetFont('Arial', '', 9);
            $this->Cell(0, 5, decode_utf8($this->titulo), 0, 1, 'C');
            
            $this->Ln(3);

            // Encabezados de columna simples
            $this->SetFont('Arial', 'B', 9);
            $this->SetTextColor(0, 0, 0);
            $this->SetDrawColor(150, 150, 150);

            $this->Cell(10, 5, 'No.', 'B', 0, 'C');
            $this->Cell(135, 5, decode_utf8('DESCRIPCIÓN'), 'B', 0, 'L');
            $this->Cell(35, 5, 'MONTO', 'B', 1, 'R');

            $this->SetDrawColor(220, 220, 220);
        }

        // Pie de página
        function Footer()
        {
            // Línea superior simple
            $this->SetY(-15);
            $this->SetDrawColor(180, 180, 180);
            $this->SetLineWidth(0.3);
            $this->Line(10, $this->GetY(), $this->GetPageWidth() - 10, $this->GetY());
            
            // Información del footer
            $this->SetY(-12);
            $this->SetFont('Arial', '', 7);
            $this->SetTextColor(80, 80, 80);
            
            // Página (centro)
            $this->Cell(0, 5, decode_utf8('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
            
            $this->SetTextColor(0, 0, 0);
        }
    }

    $fuente = "Arial";
    $tamanio_linea = 5;

    // Creación del objeto de la clase heredada
    $pdf = new PDF($oficina, $institucion, $direccionins, $emailins, $telefonosins, $nitins, $rutalogomicro, $rutalogoins, $datos[0], $datos[1], $datos[3]);
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 18);

    $contador = 0;
    $pdf->SetFont($fuente, '', 9);
    $pdf->SetDrawColor(220, 220, 220);

    foreach ($movimientos as $factura) {
        // Encabezado de la factura - estilo limpio
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        $pdf->Cell(45, 5, 'Recibo: ' . decode_utf8($factura['recibo']), 0, 0, 'L');
        $pdf->Cell(65, 5, (($factura['tipoadicional'] == 3) ? decode_utf8($factura['nombreUsuario']) : decode_utf8($factura['cliente'])), 0, 0, 'L');
        $pdf->Cell(35, 5, date("d/m/Y", strtotime($factura['fecha'])), 0, 0, 'C');
        $pdf->Cell(35, 5, decode_utf8($factura['nombreAgencia']), 0, 1, 'L');
        
        // Línea separadora debajo del encabezado
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line($pdf->GetX() + 10, $pdf->GetY(), $pdf->GetPageWidth() - 10, $pdf->GetY());
        $pdf->Ln(2);
        $pdf->SetDrawColor(220, 220, 220);

        // Movimientos de la factura
        uksort($factura['movimientos'], function ($a, $b) {
            return $a - $b;
        });
        
        $contador = 1;
        
        foreach ($factura['movimientos'] as $movimiento) {
            $pdf->SetFont($fuente, '', 9);
            $pdf->SetTextColor(0, 0, 0);
            
            $pdf->Cell(10, $tamanio_linea, $contador, 0, 0, 'C');
            $pdf->Cell(135, $tamanio_linea, decode_utf8($movimiento['detalle']), 0, 0, 'L');
            $pdf->Cell(35, $tamanio_linea, number_format(round($movimiento['monto_movimiento'], 2), 2), 0, 1, 'R');

            // Impuestos del movimiento - más sutiles
            foreach ($movimiento['impuestos'] as $impuesto) {
                $pdf->SetFont($fuente, '', 8);
                $pdf->SetTextColor(100, 100, 100);
                
                $pdf->Cell(10, 4, '', 0, 0, 'C');
                $pdf->Cell(135, 4, '   ' . decode_utf8($impuesto['tipo_impuesto']), 0, 0, 'L');
                $pdf->Cell(35, 4, number_format(round($impuesto['monto_impuesto'], 2), 2), 0, 1, 'R');
            }

            $contador++;
        }

        // Total de la factura
        $pdf->Ln(1);
        $pdf->SetFont($fuente, '', 8);
        $pdf->SetTextColor(60, 60, 60);
        $pdf->MultiCell(180, 4, 'Descripcion: ' . decode_utf8($factura['descripcion']), 0, 'L');
        
        $pdf->SetFont($fuente, 'B', 9);
        $pdf->SetTextColor(0, 0, 0);
        
        $totalFactura = array_reduce($factura['movimientos'], function ($carry, $mov) {
            return $carry + $mov['monto_movimiento'] + array_sum(array_column($mov['impuestos'], 'monto_impuesto'));
        }, 0);

        $pdf->Cell(145, 5, 'TOTAL', 'T', 0, 'R');
        $pdf->Cell(35, 5, number_format(round($totalFactura, 2), 2), 'T', 1, 'R');
        
        $pdf->Ln(4);
    }

    // Total general
    $pdf->Ln(2);
    $pdf->SetFont($fuente, 'B', 10);
    $pdf->SetTextColor(0, 0, 0);
    
    $totalGeneral = array_reduce($movimientos, function ($carry, $factura) {
        return $carry + array_reduce($factura['movimientos'], function ($carryMov, $mov) {
            return $carryMov + $mov['monto_movimiento'] + array_sum(array_column($mov['impuestos'], 'monto_impuesto'));
        }, 0);
    }, 0);

    // Línea superior del total
    $pdf->Line(10, $pdf->GetY(), $pdf->GetPageWidth() - 10, $pdf->GetY());
    $pdf->Ln(2);
    
    $pdf->Cell(90, 6, decode_utf8('Número de facturas: ') . count($movimientos), 0, 0, 'L');
    $pdf->Cell(90, 6, 'TOTAL GENERAL: ' . number_format($totalGeneral, 2), 0, 1, 'R');

    $pdf->SetTextColor(0, 0, 0);


    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "REPORTE_" . $datos[3] . "_AL" . $datos[4],
        'data' => "data:application/pdf;base64," . base64_encode($pdfData),
        'tipo' => "pdf"
    );
    echo json_encode($opResult);
}

function printxls($datos, $otros)
{
    $hoy = date("Y-m-d H:i:s");

    $fuente_encabezado = "Arial";
    $fuente = "Courier";
    $tamanioFecha = 9;
    // $tamanioEncabezado = 14;
    $tamanioTabla = 11;

    $spread = new Spreadsheet();
    $spread
        ->getProperties()
        ->setCreator("MICROSYSTEM")
        ->setLastModifiedBy('MICROSYSTEM')
        ->setTitle('Reporte')
        ->setSubject('Visitas prepago')
        ->setDescription('Este reporte fue generado por el sistema MICROSYSTEM')
        ->setKeywords('PHPSpreadsheet')
        ->setCategory('Excel');
    //-----------RELACIONADO CON LAS PROPIEDADES DEL ARCHIVO----------------------------

    //-----------RELACIONADO CON EL ENCABEZADO----------------------------
    # Como ya hay una hoja por defecto, la obtenemos, no la creamos
    $hojaReporte = $spread->getActiveSheet();
    $hojaReporte->setTitle("Reporte de desembolsos");

    //insertarmos la fecha y usuario
    $hojaReporte->setCellValue("A1", $hoy);
    $hojaReporte->setCellValue("A2", $otros[1]);

    //hacer pequeño las letras de la fecha, definir arial como tipo de letra
    $hojaReporte->getStyle("A1:H1")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    $hojaReporte->getStyle("A2:H2")->getFont()->setSize($tamanioFecha)->setName($fuente_encabezado);
    //centrar el texto de la fecha
    $hojaReporte->getStyle("A1:H1")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A2:H2")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    //hacer pequeño las letras del encabezado de titulo
    $hojaReporte->getStyle("A4:H4")->getFont()->setSize($tamanioTabla)->setName($fuente);
    $hojaReporte->getStyle("A5:H5")->getFont()->setSize($tamanioTabla)->setName($fuente);
    //centrar los encabezado de la tabla
    $hojaReporte->getStyle("A4:H4")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
    $hojaReporte->getStyle("A5:H5")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

    $hojaReporte->setCellValue("A4", "REPORTE");
    $hojaReporte->setCellValue("A5", strtoupper($otros[0]));

    # Escribir encabezado de la tabla
    $encabezado_tabla = ["CRÉDITO", "CLIENTE", "NOMBRE CLIENTE", "MONTO SOLICITADO", "MONTO APROBADO", "MONTO DESEMBOLSADO", "COMISION A COBRAR", "TIPO DE DOCUMENTO", "FECHA DE SOLICITUD", "FECHA DE DESEMBOLSO", "FECHA DE VENCIMIENTO", "RESPONSABLE"];
    # El último argumento es por defecto A1 pero lo pongo para que se explique mejor
    $hojaReporte->fromArray($encabezado_tabla, null, 'A7')->getStyle('A7:H7')->getFont()->setName($fuente)->setBold(true);

    //combinacion de celdas
    $hojaReporte->mergeCells('A1:H1');
    $hojaReporte->mergeCells('A2:H2');
    $hojaReporte->mergeCells('A4:H4');
    $hojaReporte->mergeCells('A5:H5');

    //CARGAR LOS DATOS
    $sumamonsol = 0;
    $sumamontoapro = 0;
    $sumamontodes = 0;
    $sumaacobrar = 0;
    $fila = 0;
    $linea = 8;
    while ($fila < count($datos)) {
        // SELECT ag.cod_agenc ,pg.dfecven AS fecha, cm.CCODCTA AS cuenta, cl.idcod_cliente AS cliente, cl.short_name AS nombre, pg.SaldoCapital AS saldo, pg.nintmor AS mora, pg.NAhoProgra AS pag1, pg.OtrosPagos AS pag2, (pg.ncapita + pg.nintere) AS cuota, pg.ncapita AS capital, pg.nintere AS interes
        $hojaReporte->getStyle("A" . $linea)->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        $cuenta = $datos[$fila]["cuenta"];
        $codigocliente = $datos[$fila]["codigocliente"];
        $nombre = strtoupper($datos[$fila]["nombre"]);
        $montosolicitado = $datos[$fila]["montosoli"];
        $montoaprobado = $datos[$fila]["montoaprobado"];
        $montodesembolsado = $datos[$fila]["montodesembolsado"]; //suma
        $comacobrar = $datos[$fila]["gastos"]; //sumar
        $tipo = $datos[$fila]["tipo"];

        $tipoenti = $datos[$fila]["TipoEnti"];
        $tipoenti = ($tipoenti == "GRUP") ? 'GRUPOS' : 'INDIVIDUAL';
        $nombrefondos = $datos[$fila]["fondesc"];
        $nomgrupo = ($tipoenti == "GRUPOS") ? $datos[$fila]["NombreGrupo"] : ' ';

        if ($datos[$fila]["tipo"] == "E") {
            $tipo = "EFECTIVO";
        }
        if ($datos[$fila]["tipo"] == "T") {
            $tipo = "TRANSFERENCIA";
        }
        if ($datos[$fila]["tipo"] == "C") {
            $tipo = "CHEQUE";
        }
        $fecsolicitud = $datos[$fila]["fecsolicitud"];
        $fecdesembolsado = $datos[$fila]["fecdesembolsado"];
        $fecvencimiento = $datos[$fila]["fecvencimiento"];
        $responsable = strtoupper($datos[$fila]["responsable"]);

        $sumamonsol = $sumamonsol + $montosolicitado;
        $sumamontoapro = $sumamontoapro + $montoaprobado;
        $sumamontodes = $sumamontodes + $montodesembolsado;
        $sumaacobrar = $sumaacobrar + $comacobrar;
        $hojaReporte->setCellValueByColumnAndRow(1, $linea, $cuenta);
        $hojaReporte->setCellValueByColumnAndRow(2, $linea, $codigocliente);
        $hojaReporte->setCellValueByColumnAndRow(3, $linea, $nombre);
        $hojaReporte->setCellValueByColumnAndRow(4, $linea, $montosolicitado);
        $hojaReporte->setCellValueByColumnAndRow(5, $linea, $montoaprobado);
        $hojaReporte->setCellValueByColumnAndRow(6, $linea, $montodesembolsado);
        $hojaReporte->setCellValueByColumnAndRow(7, $linea, $comacobrar);
        $hojaReporte->setCellValueByColumnAndRow(8, $linea, $tipo);
        $hojaReporte->setCellValueByColumnAndRow(9, $linea, $fecsolicitud);
        $hojaReporte->setCellValueByColumnAndRow(10, $linea, $fecdesembolsado);
        $hojaReporte->setCellValueByColumnAndRow(11, $linea, $fecvencimiento);
        $hojaReporte->setCellValueByColumnAndRow(12, $linea, $responsable);
        $hojaReporte->setCellValueByColumnAndRow(13, $linea, $nombrefondos);
        $hojaReporte->setCellValueByColumnAndRow(14, $linea, $tipoenti);
        $hojaReporte->setCellValueByColumnAndRow(15, $linea, $nomgrupo);

        $hojaReporte->getStyle("A" . $linea . ":M" . $linea)->getFont()->setName($fuente);
        $fila++;
        $linea++;
    }
    //totales
    $hojaReporte->setCellValueByColumnAndRow(2, $linea, "NUM. DE CREDITOS: " . $fila);
    $hojaReporte->setCellValueByColumnAndRow(3, $linea, $sumamontoapro);
    $hojaReporte->setCellValueByColumnAndRow(4, $linea, $sumamonsol);
    $hojaReporte->setCellValueByColumnAndRow(5, $linea, $sumamontodes);
    $hojaReporte->setCellValueByColumnAndRow(6, $linea, $sumaacobrar);
    $hojaReporte->getStyle("A" . $linea . ":P" . $linea)->getFont()->setName($fuente)->setBold(true);
    //totales
    $hojaReporte->getColumnDimension('A')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('B')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('C')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('D')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('E')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('F')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('G')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('H')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('I')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('J')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('K')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('L')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('M')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('N')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('O')->setAutoSize(TRUE);
    $hojaReporte->getColumnDimension('P')->setAutoSize(TRUE);

    //SECCION PARA DESCARGA EL ARCHIVO
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spread, 'Xlsx');
    $writer->save("php://output");
    $xlsData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => "CREDITOS_" . $otros[4] . "_" . $otros[2],
        'data' => "data:application/vnd.ms-excel;base64," . base64_encode($xlsData)
    );
    echo json_encode($opResult);
}
