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

// include __DIR__ . '/../../../includes/Config/database.php';
// $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';

require __DIR__ . '/../../../fpdf/fpdf.php';
require __DIR__ . '/../../../vendor/autoload.php';

$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

// session_start();
// include '../../../includes/BD_con/db_con.php';
// include '../../../src/funcphp/func_gen.php';
// require '../../../fpdf/fpdf.php';
// require "../../../vendor/autoload.php";
// date_default_timezone_set('America/Guatemala');

use App\DatabaseAdapter;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Models\Departamento;
use Micro\Models\Municipio;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory;

$database = new DatabaseAdapter();

$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

$showmensaje = false;
try {
    /**
     * datos mandados
     * `fechaInicio`,`fechaFinal`],[],[`filter_fecha`],['<?= $accountCode; ?>'
     */


    $datos = array(
        'token_csrf' => $_POST['datosval'][0][0],
        'fechaInicio' => $_POST['datosval'][0][1],
        'fechaFinal' => $_POST['datosval'][0][2],
        'filterFecha' => $_POST['datosval'][2][0],
        'accountCode' => $_POST['datosval'][3][0]
    );

    $tipo = $_POST["tipo"];
    $rules = [
        'token_csrf' => 'required',
        'filterFecha' => 'required',
        'accountCode' => 'required|exists:aprcta,ccodaport',
    ];

    if ($datos['filterFecha'] == '2') {
        $rules['fechaInicio'] = 'required|date';
        $rules['fechaFinal'] = 'required|date';
    }

    $validator = Validator::make($datos, $rules);
    if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        $showmensaje = true;
        throw new Exception($firstError);
    }
    $database->openConnection();

    /**
     * CONSULTAS VARIAS
     */

    $datosCuenta = $database->getAllResults(
        "SELECT cli.short_name, cli.no_identifica,cli.no_tributaria,cli.depa_reside,cli.id_muni_reside,
            cli.Direccion,cli.tel_no1, cli.genero,cli.estado_civil, tip.nombre nombreProducto
            FROM aprcta cta 
            INNER JOIN aprtip tip ON tip.ccodtip= cta.ccodtip
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
            WHERE cta.ccodaport=?",
        [$datos['accountCode']]
    );
    if (empty($datosCuenta)) {
        $showmensaje = true;
        throw new Exception("Cuenta de aportacion no encontrada");
    }

    $query = "SELECT dfecope,correlativo,ctipope,cnumdoc,ctipdoc,monto,nrochq FROM aprmov 
                WHERE cestado=1 AND ccodaport = ?";
    $parameters = [$datos['accountCode']];

    if ($datos['filterFecha'] == '2') {
        $saldoAnterior = $database->getAllResults("SELECT 
                IFNULL(SUM(CASE WHEN ctipope = 'D' THEN monto ELSE 0 END),0) AS total_depositos,
                IFNULL(SUM(CASE WHEN ctipope = 'R' THEN monto ELSE 0 END),0) AS total_retiros
            FROM aprmov
            WHERE cestado = 1
            AND ccodaport = ?
            AND dfecope < ?;", [$datos['accountCode'], $datos['fechaInicio']]);

        $saldoAnteriorTotal = $saldoAnterior[0]['total_depositos'] - $saldoAnterior[0]['total_retiros'];
        $query .= " AND dfecope BETWEEN ? AND ?";
        $parameters[] = $datos['fechaInicio'];
        $parameters[] = $datos['fechaFinal'];
    }

    $query .= " ORDER BY dfecope,correlativo ASC;";

    $movimientos = $database->getAllResults($query, $parameters);

    $info = $database->getAllResults("SELECT nom_agencia,nomb_comple,muni_lug,emai,tel_1,tel_2,nit,log_img
                                FROM $db_name_general.info_coperativa ins
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
        printxls($info, $datosCuenta, $movimientos, $datos, isset($saldoAnteriorTotal) ? $saldoAnteriorTotal : 0);
        break;
    case 'pdf':
        printpdf($info, $datosCuenta, $movimientos, $datos, isset($saldoAnteriorTotal) ? $saldoAnteriorTotal : 0);
        break;
}

function printpdf($info, $datosCuenta, $movimientos, $datos, $saldoAnteriorTotal = 0)
{
    global $idusuario, $idagencia, $hoy2;

    // Extraer información de la institución
    $infoInst = $info[0];
    $institucion = decode_utf8($infoInst['nomb_comple']);
    $oficina = decode_utf8($infoInst['nom_agencia']);
    $direccionins = decode_utf8($infoInst['muni_lug']);
    $emailins = $infoInst['emai'];
    $telefonosins = $infoInst['tel_1'] . ' - ' . $infoInst['tel_2'];
    $nitins = $infoInst['nit'];
    $rutalogoins = __DIR__ . "/../../../" . $infoInst['log_img'];

    // Extraer información del cliente
    $cliente = $datosCuenta[0];
    $nombreCliente = decode_utf8($cliente['short_name']);
    $identificacion = $cliente['no_identifica'];
    $nit = $cliente['no_tributaria'];
    $direccion = decode_utf8($cliente['Direccion']);
    $telefono = $cliente['tel_no1'];
    $genero = $cliente['genero'];
    $estadoCivil = $cliente['estado_civil'];

    // Obtener nombres de departamento y municipio
    $departamento = Departamento::obtenerNombre($cliente['depa_reside']);
    $municipio = Municipio::obtenerNombre($cliente['id_muni_reside'] ?? 0);
    $domicilio = decode_utf8(karely($municipio)) . ', ' . decode_utf8(karely($departamento));

    // Clase PDF personalizada con diseño moderno
    class PDF extends FPDF
    {
        private $institucion;
        private $oficina;
        private $direccion;
        private $email;
        private $telefono;
        private $nit;
        private $rutaLogo;
        private $fechaHora;
        private $usuario;
        
        // Datos del cliente y cuenta
        private $accountCode;
        private $nombreProducto;
        private $textoFecha;
        private $nombreCliente;
        private $identificacion;
        private $nitCliente;
        private $telefCliente;
        private $genero;
        private $domicilio;
        private $estadoCivil;
        private $direccionCliente;

        public function __construct($institucion, $oficina, $direccion, $email, $telefono, $nit, $rutaLogo, $fechaHora, $usuario)
        {
            parent::__construct('P', 'mm', 'Letter');
            $this->institucion = $institucion;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefono = $telefono;
            $this->nit = $nit;
            $this->rutaLogo = $rutaLogo;
            $this->fechaHora = $fechaHora;
            $this->usuario = $usuario;
        }
        
        // Método para establecer datos del cliente
        public function setDatosCliente($accountCode, $nombreProducto, $textoFecha, $nombreCliente, $identificacion, $nitCliente, $telefCliente, $genero, $domicilio, $estadoCivil, $direccionCliente)
        {
            $this->accountCode = $accountCode;
            $this->nombreProducto = $nombreProducto;
            $this->textoFecha = $textoFecha;
            $this->nombreCliente = $nombreCliente;
            $this->identificacion = $identificacion;
            $this->nitCliente = $nitCliente;
            $this->telefCliente = $telefCliente;
            $this->genero = $genero;
            $this->domicilio = $domicilio;
            $this->estadoCivil = $estadoCivil;
            $this->direccionCliente = $direccionCliente;
        }

        function Header()
        {
            // Línea decorativa superior con degradado
            $this->SetFillColor(41, 128, 185);
            $this->Rect(0, 0, 216, 3, 'F');

            // Fondo suave para el encabezado
            $this->SetFillColor(236, 240, 241);
            $this->Rect(0, 3, 216, 50, 'F');

            // Fecha y usuario en esquina superior derecha con estilo
            $this->SetFont('Arial', '', 7);
            $this->SetTextColor(52, 73, 94);
            $this->SetXY(150, 6);
            $this->Cell(50, 3, 'Generado: ' . $this->fechaHora, 0, 1, 'R');
            $this->SetX(150);
            $this->Cell(50, 3, 'Usuario: ' . $this->usuario, 0, 1, 'R');

            // Logo de la institución con borde redondeado
            if (file_exists($this->rutaLogo)) {
                $this->Image($this->rutaLogo, 15, 12, 35);
            }

            // Información de la institución con diseño profesional
            $this->SetTextColor(41, 128, 185);
            $this->SetFont('Arial', 'B', 13);
            $this->SetXY(55, 14);
            $this->Cell(0, 5, $this->institucion, 0, 1, 'L');

            $this->SetTextColor(52, 73, 94);
            $this->SetFont('Arial', 'B', 10);
            $this->SetX(55);
            $this->Cell(0, 4, $this->oficina, 0, 1, 'L');

            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(127, 140, 141);
            $this->SetX(55);
            $this->Cell(0, 3.5, $this->direccion, 0, 1, 'L');

            $this->SetX(55);
            $this->Cell(0, 3.5, 'Email: ' . $this->email . ' | Tel: ' . $this->telefono, 0, 1, 'L');

            $this->SetX(55);
            $this->Cell(0, 3.5, 'NIT: ' . $this->nit, 0, 1, 'L');

            $this->Ln(3);
            
            // Si se han establecido datos del cliente, mostrarlos
            if ($this->accountCode) {
                // Caja para el número de cuenta
                $this->SetFillColor(41, 128, 185);
                $this->SetDrawColor(41, 128, 185);
                $this->SetTextColor(255, 255, 255);
                $this->SetFont('Arial', 'B', 11);
                $this->Cell(0, 8, $this->nombreProducto . ' ' . $this->accountCode, 0, 1, 'C', true);

                // Rango de fechas con estilo
                $this->SetFillColor(149, 165, 166);
                $this->SetFont('Arial', 'B', 9);
                $this->Cell(0, 6, $this->textoFecha, 0, 1, 'C', true);
                $this->Ln(4);

                // Sección de información del cliente
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor(41, 128, 185);
                $this->Cell(0, 5, 'INFORMACION DEL TITULAR', 0, 1, 'L');
                $this->SetTextColor(0, 0, 0);

                // Línea decorativa
                $this->SetDrawColor(41, 128, 185);
                $this->SetLineWidth(0.5);
                $yPos = $this->GetY();
                $this->Line(10, $yPos, 206, $yPos);
                $this->Ln(3);

                // Información del cliente
                $widhtsColumns = [25, 105, 25, 0];

                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[0], 6, 'Nombre:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[1], 6, $this->nombreCliente, 0, 1, 'L');

                // Identificación
                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[0], 6, 'Identificacion:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[1], 6, $this->identificacion, 0, 0, 'L');

                // NIT
                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[2], 6, 'NIT:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[3], 6, $this->nitCliente, 0, 1, 'L');

                // Teléfono / Sexo
                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[0], 6, 'Telefono:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[1], 6, $this->telefCliente, 0, 0, 'L');

                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[2], 6, 'Sexo:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[3], 6, $this->genero, 0, 1, 'L');

                // Domicilio / Estado civil
                $this->Ln(1);
                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[0], 6, 'Domicilio:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[1], 6, $this->domicilio, 0, 0, 'L');

                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[2], 6, 'Estado civil:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[3], 6, $this->estadoCivil, 0, 1, 'L');

                // Dirección
                $this->SetFont('Arial', 'B', 8);
                $this->SetTextColor(41, 128, 185);
                $this->Cell($widhtsColumns[0], 6, 'Direccion:', 0, 0, 'L');
                $this->SetFont('Arial', '', 8);
                $this->SetTextColor(52, 73, 94);
                $this->Cell($widhtsColumns[1], 6, $this->direccionCliente, 0, 1, 'L');

                $this->Ln(2);
                $this->SetTextColor(0, 0, 0);

                // Título de la sección de movimientos
                $this->SetFont('Arial', 'B', 10);
                $this->SetTextColor(41, 128, 185);
                $this->Cell(0, 5, 'DETALLE DE MOVIMIENTOS', 0, 1, 'L');
                $this->SetTextColor(0, 0, 0);

                // Línea decorativa
                $yPos = $this->GetY();
                $this->Line(10, $yPos, 206, $yPos);
                $this->Ln(2);

                // Encabezados de la tabla de movimientos
                $this->SetFont('Arial', 'B', 8);
                $this->SetFillColor(52, 73, 94);
                $this->SetTextColor(255, 255, 255);
                $this->SetDrawColor(52, 73, 94);

                $this->Cell(22, 7, 'Fecha', 1, 0, 'C', true);
                $this->Cell(12, 7, 'Num', 1, 0, 'C', true);
                $this->Cell(12, 7, 'Tipo', 1, 0, 'C', true);
                $this->Cell(22, 7, 'Documento', 1, 0, 'C', true);
                $this->Cell(12, 7, 'Doc', 1, 0, 'C', true);
                $this->Cell(32, 7, 'Creditos', 1, 0, 'C', true);
                $this->Cell(32, 7, 'Debitos', 1, 0, 'C', true);
                $this->Cell(20, 7, 'No. Cheque', 1, 0, 'C', true);
                $this->Cell(32, 7, 'Saldo', 1, 1, 'C', true);
                
                // Resetear colores para el contenido
                $this->SetTextColor(52, 73, 94);
                $this->SetDrawColor(189, 195, 199);
                $this->SetFont('Arial', '', 8);
            }
        }

        function Footer()
        {
            // Línea decorativa superior del pie
            $this->SetY(-15);
            $this->SetDrawColor(41, 128, 185);
            $this->SetLineWidth(0.5);
            $this->Line(10, $this->GetY(), 206, $this->GetY());

            // Fondo del pie
            $this->SetY(-14);
            $this->SetFillColor(236, 240, 241);
            $this->Rect(0, $this->GetY(), 216, 17, 'F');

            // Información del pie de página
            $this->SetY(-11);
            $this->SetFont('Arial', 'I', 7);
            $this->SetTextColor(127, 140, 141);
            // $this->Cell(0, 4, 'Este documento es un reporte automatizado del sistema de gestion', 0, 1, 'C');

            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(52, 73, 94);
            $this->Cell(0, 4, 'Pagina ' . $this->PageNo() . ' de {nb}', 0, 0, 'C');
        }

        // Método para crear cajas con diseño moderno
        function FancyBox($x, $y, $w, $h, $title, $content)
        {
            // Borde y sombra
            $this->SetFillColor(240, 240, 240);
            $this->Rect($x + 0.5, $y + 0.5, $w, $h, 'F');

            // Caja principal
            $this->SetFillColor(255, 255, 255);
            $this->SetDrawColor(189, 195, 199);
            $this->SetLineWidth(0.3);
            $this->Rect($x, $y, $w, $h, 'FD');

            // Título de la caja
            $this->SetFillColor(52, 152, 219);
            $this->Rect($x, $y, $w, 6, 'F');
            $this->SetFont('Arial', 'B', 8);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY($x + 2, $y + 1);
            $this->Cell($w - 4, 4, $title, 0, 0, 'L');

            // Contenido
            $this->SetTextColor(52, 73, 94);
            $this->SetFont('Arial', '', 8);
            $this->SetXY($x + 2, $y + 7);
            $this->MultiCell($w - 4, 4, $content);
        }
    }

    // Crear instancia del PDF
    $pdf = new PDF($institucion, $oficina, $direccionins, $emailins, $telefonosins, $nitins, $rutalogoins, $hoy2, $idusuario);
    
    // Preparar texto de fecha
    $textoFecha = ($datos['filterFecha'] == '2')
        ? 'Periodo: ' . date('d/m/Y', strtotime($datos['fechaInicio'])) . ' al ' . date('d/m/Y', strtotime($datos['fechaFinal']))
        : 'Periodo: Todas las fechas';
    
    // Establecer datos del cliente para que se muestren en el encabezado de cada página
    $pdf->setDatosCliente(
        $datos['accountCode'],
        Utf8::decode($cliente['nombreProducto']),
        $textoFecha,
        $nombreCliente,
        $identificacion,
        $nit,
        $telefono,
        $genero,
        $domicilio,
        $estadoCivil,
        $direccion
    );
    
    $pdf->AliasNbPages();
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 25);

    // Configurar estilos para los datos de la tabla
    $pdf->SetFont('Arial', '', 8);
    $pdf->SetTextColor(52, 73, 94);
    $pdf->SetDrawColor(189, 195, 199);
    $saldo = $saldoAnteriorTotal;
    $fill = false;

    // Mostrar saldo anterior si existe
    if ($datos['filterFecha'] == '2' && $saldoAnteriorTotal != 0) {
        $fechaAnterior = date('d/m/Y', strtotime($datos['fechaInicio'] . ' -1 day'));
        $pdf->SetFillColor(255, 243, 205);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(22, 6, $fechaAnterior, 1, 0, 'C', true);
        $pdf->Cell(12, 6, '', 1, 0, 'C', true);
        $pdf->Cell(12, 6, '', 1, 0, 'C', true);
        $pdf->Cell(22, 6, 'SALDO INICIAL', 1, 0, 'L', true);
        $pdf->Cell(12, 6, '', 1, 0, 'C', true);
        $pdf->Cell(32, 6, '', 1, 0, 'R', true);
        $pdf->Cell(32, 6, '', 1, 0, 'R', true);
        $pdf->Cell(20, 6, '', 1, 0, 'C', true);
        $pdf->Cell(32, 6, 'Q ' . number_format($saldo, 2, '.', ','), 1, 1, 'R', true);
        $pdf->SetFont('Arial', '', 8);
    }

    // Recorrer movimientos
    foreach ($movimientos as $mov) {
        $fecha = date('d/m/Y', strtotime($mov['dfecope']));
        $num = $mov['correlativo'];
        $tipope = $mov['ctipope'];
        $numdoc = $mov['cnumdoc'];
        $tipdoc = $mov['ctipdoc'];
        $monto = floatval($mov['monto']);
        $nrochq = $mov['nrochq'] ?? '';

        // Calcular saldo
        if ($tipope == 'D') {
            $saldo += $monto;
            $credito = 'Q ' . number_format($monto, 2, '.', ',');
            $debito = '';
            $colorFill = [232, 245, 233]; // Verde claro para depósitos
        } else {
            $saldo -= $monto;
            $credito = '';
            $debito = 'Q ' . number_format($monto, 2, '.', ',');
            $colorFill = [255, 235, 238]; // Rojo claro para retiros
        }

        // Alternar colores de fondo
        if ($fill) {
            $pdf->SetFillColor($colorFill[0], $colorFill[1], $colorFill[2]);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        // Imprimir fila
        $pdf->Cell(22, 6, $fecha, 1, 0, 'C', $fill);
        $pdf->Cell(12, 6, $num, 1, 0, 'C', $fill);
        $pdf->Cell(12, 6, $tipope, 1, 0, 'C', $fill);
        $pdf->Cell(22, 6, $numdoc, 1, 0, 'L', $fill);
        $pdf->Cell(12, 6, $tipdoc, 1, 0, 'C', $fill);
        $pdf->Cell(32, 6, $credito, 1, 0, 'R', $fill);
        $pdf->Cell(32, 6, $debito, 1, 0, 'R', $fill);
        $pdf->Cell(20, 6, $nrochq, 1, 0, 'C', $fill);
        $pdf->Cell(32, 6, 'Q ' . number_format($saldo, 2, '.', ','), 1, 1, 'R', $fill);

        $fill = !$fill;
    }

    // Fila de totales
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(164, 7, 'SALDO FINAL', 1, 0, 'R', true);
    $pdf->Cell(32, 7, 'Q ' . number_format($saldo, 2, '.', ','), 1, 1, 'R', true);

    // Generar salida
    ob_start();
    $pdf->Output('I', 'estado_cuenta_aportacion.pdf');
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'Reporte generado correctamente',
        'namefile' => 'Estado de cuenta de aportacion',
        'tipo' => 'pdf',
        'data' => 'data:application/pdf;base64,' . base64_encode($pdfData)
    );

    echo json_encode($opResult);
    exit;
}

function printxls($info, $datosCuenta, $movimientos, $datos, $saldoAnteriorTotal = 0)
{
    global $idusuario, $idagencia, $hoy2;

    // Extraer información de la institución
    $infoInst = $info[0];
    $institucion = (karely($infoInst['nomb_comple']));
    $oficina = (karely($infoInst['nom_agencia']));
    $direccionins = (karely($infoInst['muni_lug']));
    $emailins = $infoInst['emai'];
    $telefonosins = $infoInst['tel_1'] . ' - ' . $infoInst['tel_2'];
    $nitins = $infoInst['nit'];

    // Extraer información del cliente
    $cliente = $datosCuenta[0];
    $nombreCliente = (karely($cliente['short_name']));
    $identificacion = $cliente['no_identifica'];
    $nit = $cliente['no_tributaria'];
    $direccion = (karely($cliente['Direccion']));
    $telefono = $cliente['tel_no1'];
    $genero = $cliente['genero'];
    $estadoCivil = $cliente['estado_civil'];

    // Obtener nombres de departamento y municipio
    $departamento = Departamento::obtenerNombre($cliente['depa_reside']);
    $municipio = Municipio::obtenerNombre($cliente['id_muni_reside'] ?? 0);
    $domicilio = (karely($municipio)) . ', ' . (karely($departamento));

    try {
        // Crear nuevo archivo de Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Configurar propiedades del documento
        $spreadsheet->getProperties()
            ->setCreator($institucion)
            ->setTitle('Estado de Cuenta de Aportacion')
            ->setSubject('Reporte de Movimientos')
            ->setDescription('Historial de cuenta de aportacion')
            ->setCategory('Reportes');

        // ENCABEZADO DEL DOCUMENTO
        $row = 1;

        // Título principal
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", $institucion);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        // Oficina
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", $oficina);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        // Dirección y contacto
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", $direccionins);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", "Email: {$emailins} | Tel: {$telefonosins} | NIT: {$nitins}");
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        $row++; // Línea en blanco

        // Título del reporte
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", "HISTORIAL DE CUENTA DE APORTACION");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF3498DB');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        // Número de cuenta
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", "Cuenta: {$datos['accountCode']}");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2980B9');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        // Período
        $textoFecha = ($datos['filterFecha'] == '2')
            ? 'Periodo: ' . date('d/m/Y', strtotime($datos['fechaInicio'])) . ' al ' . date('d/m/Y', strtotime($datos['fechaFinal']))
            : 'Periodo: Todas las fechas';
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", $textoFecha);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF95A5A6');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        $row++; // Línea en blanco

        // INFORMACIÓN DEL CLIENTE
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", "INFORMACION DEL CLIENTE");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8F8F5');
        $row++;

        // Datos del cliente
        $sheet->setCellValue("A{$row}", "Nombre:");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("B{$row}:I{$row}");
        $sheet->setCellValue("B{$row}", $nombreCliente);
        $row++;

        $sheet->setCellValue("A{$row}", "Identificacion:");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("B{$row}:D{$row}");
        // Identificación como texto
        $sheet->setCellValueExplicit("B{$row}", $identificacion, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle("B{$row}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);

        // NIT como texto
        $sheet->setCellValue("E{$row}", "NIT:");
        $sheet->getStyle("E{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("F{$row}:I{$row}");
        $sheet->setCellValueExplicit("F{$row}", $nit, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        $sheet->getStyle("F{$row}:I{$row}")->getNumberFormat()->setFormatCode(\PhpOffice\PhpSpreadsheet\Style\NumberFormat::FORMAT_TEXT);
        $row++;

        $sheet->setCellValue("A{$row}", "Domicilio:");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("B{$row}:I{$row}");
        $sheet->setCellValue("B{$row}", $domicilio);
        $row++;

        $sheet->setCellValue("A{$row}", "Direccion:");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("B{$row}:I{$row}");
        $sheet->setCellValue("B{$row}", $direccion);
        $row++;

        $sheet->setCellValue("A{$row}", "Telefono:");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("B{$row}:C{$row}");
        $sheet->setCellValue("B{$row}", $telefono);
        $sheet->setCellValue("D{$row}", "Sexo:");
        $sheet->getStyle("D{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("E{$row}", $genero);
        $sheet->setCellValue("F{$row}", "Estado civil:");
        $sheet->getStyle("F{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("G{$row}:I{$row}");
        $sheet->setCellValue("G{$row}", $estadoCivil);
        $row++;

        $row += 2; // Líneas en blanco

        // DETALLE DE MOVIMIENTOS
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", "DETALLE DE MOVIMIENTOS");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8F8F5');
        $row++;

        // Encabezados de la tabla
        $headers = ['Fecha', 'Num', 'Tipo', 'Documento', 'Doc', 'Creditos', 'Debitos', 'No. Cheque', 'Saldo'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("{$col}{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF34495E');
            $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }
        $row++;

        // Saldo inicial
        $saldo = $saldoAnteriorTotal;
        if ($datos['filterFecha'] == '2' && $saldoAnteriorTotal != 0) {
            $fechaAnterior = date('d/m/Y', strtotime($datos['fechaInicio'] . ' -1 day'));
            $sheet->setCellValue("A{$row}", $fechaAnterior);
            $sheet->setCellValue("D{$row}", "SALDO INICIAL");
            $sheet->setCellValue("I{$row}", $saldo);
            $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
            $sheet->getStyle("A{$row}:I{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFFFF3CD');
            $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true);
            $row++;
        }

        // Movimientos
        foreach ($movimientos as $mov) {
            $fecha = date('d/m/Y', strtotime($mov['dfecope']));
            $num = $mov['correlativo'];
            $tipope = $mov['ctipope'];
            $numdoc = $mov['cnumdoc'];
            $tipdoc = $mov['ctipdoc'];
            $monto = floatval($mov['monto']);
            $nrochq = $mov['nrochq'] ?? '';

            // Calcular saldo
            if ($tipope == 'D') {
                $saldo += $monto;
                $credito = $monto;
                $debito = '';
                $colorFondo = 'FFE8F5E9'; // Verde claro
            } else {
                $saldo -= $monto;
                $credito = '';
                $debito = $monto;
                $colorFondo = 'FFFFEBEE'; // Rojo claro
            }

            $sheet->setCellValue("A{$row}", $fecha);
            $sheet->setCellValue("B{$row}", $num);
            $sheet->setCellValue("C{$row}", $tipope);
            $sheet->setCellValue("D{$row}", $numdoc);
            $sheet->setCellValue("E{$row}", $tipdoc);
            $sheet->setCellValue("F{$row}", $credito);
            $sheet->setCellValue("G{$row}", $debito);
            $sheet->setCellValue("H{$row}", $nrochq);
            $sheet->setCellValue("I{$row}", $saldo);

            // Formato de moneda
            $sheet->getStyle("F{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
            $sheet->getStyle("G{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
            $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');

            // Color de fondo según tipo de movimiento
            $sheet->getStyle("A{$row}:I{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB($colorFondo);

            // Alineación
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("G{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("H{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

            $row++;
        }

        // Fila de totales
        $sheet->mergeCells("A{$row}:E{$row}");
        $sheet->setCellValue("A{$row}", "SALDO FINAL");
        $sheet->setCellValue("I{$row}", $saldo);
        $sheet->getStyle("A{$row}:I{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:I{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF34495E');
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("I{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("I{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);

        $row += 2;

        // Información de generación
        $sheet->mergeCells("A{$row}:I{$row}");
        $sheet->setCellValue("A{$row}", "Generado: {$hoy2} | Usuario: {$idusuario}");
        $sheet->getStyle("A{$row}")->getFont()->setSize(8)->setItalic(true);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Ajustar ancho de columnas automáticamente
        foreach (range('A', 'I') as $columnID) {
            $sheet->getColumnDimension($columnID)->setAutoSize(true);
        }

        // Bordes para toda la tabla
        $lastRow = $row - 2;
        $tableRange = "A1:I{$lastRow}";
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Generar archivo Excel
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');

        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $opResult = array(
            'status' => 1,
            'mensaje' => 'Reporte generado correctamente',
            'namefile' => 'Estado_Cuenta_Aportacion_' . $datos['accountCode'] . '_' . date('Ymd'),
            'tipo' => 'xlsx',
            'data' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode($xlsData)
        );

        echo json_encode($opResult);
        exit;
    } catch (Exception $e) {
        $opResult = array(
            'status' => 0,
            'mensaje' => 'Error al generar el archivo Excel: ' . $e->getMessage()
        );
        echo json_encode($opResult);
        exit;
    }
}
