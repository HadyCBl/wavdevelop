<?php
session_start();
include '../../../includes/BD_con/db_con.php';
include '../../../src/funcphp/func_gen.php';
mysqli_set_charset($conexion, 'utf8');
require '../../../fpdf/fpdf.php';

if (!isset($_SESSION['id_agencia'])) {
    die('Sesión expirada');
}

$codCliente = $_GET['cliente'] ?? null;

if (!$codCliente) {
    die('Código de cliente no especificado');
}

$id_usu = isset($_SESSION['id_usu']) ? $_SESSION['id_usu'] : 0;

// Obtener información de la institución
$queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop 
    WHERE ag.id_agencia=" . $_SESSION['id_agencia']);
$info = mysqli_fetch_assoc($queryins);

if (!$info) {
    die('Institución no encontrada');
}

// Obtener datos del cliente
$queryCliente = "SELECT 
    cl.idcod_cliente AS codcli,
    cl.compl_name AS nombre,
    cl.no_identifica AS dpi,
    cl.tel_no1 AS telefono,
    cl.email,
    cl.Direccion AS direccion,
    cl.profesion
FROM tb_cliente cl
WHERE cl.idcod_cliente = ?";

$stmt = $conexion->prepare($queryCliente);
$stmt->bind_param("s", $codCliente);
$stmt->execute();
$resultCliente = $stmt->get_result();
$cliente = $resultCliente->fetch_assoc();

if (!$cliente) {
    die('Cliente no encontrado');
}

// Obtener balance económico
$queryBalance = "SELECT 
    COALESCE(disponible, 0) AS disponible,
    COALESCE(cuenta_por_cobrar2, 0) AS cuenta_por_cobrar2,
    COALESCE(inventario, 0) AS inventario,
    COALESCE(activo_fijo, 0) AS activo_fijo,
    COALESCE(proveedores, 0) AS proveedores,
    COALESCE(otros_prestamos, 0) AS otros_prestamos,
    COALESCE(prest_instituciones, 0) AS prest_instituciones,
    COALESCE(patrimonio, 0) AS patrimonio,
    COALESCE(ventas, 0) AS ventas,
    COALESCE(cuenta_por_cobrar, 0) AS cuenta_por_cobrar,
    COALESCE(mercaderia, 0) AS mercaderia,
    COALESCE(negocio, 0) AS negocio,
    COALESCE(pago_creditos, 0) AS pago_creditos
FROM tb_cli_balance
WHERE ccodcli = ?
ORDER BY id DESC
LIMIT 1";

$stmt = $conexion->prepare($queryBalance);
$stmt->bind_param("s", $codCliente);
$stmt->execute();
$resultBalance = $stmt->get_result();
$balance = $resultBalance->fetch_assoc();

if (!$balance) {
    $balance = [
        'disponible' => 0, 'cuenta_por_cobrar2' => 0, 'inventario' => 0, 'activo_fijo' => 0,
        'proveedores' => 0, 'otros_prestamos' => 0, 'prest_instituciones' => 0, 'patrimonio' => 0,
        'ventas' => 0, 'cuenta_por_cobrar' => 0, 'mercaderia' => 0, 'negocio' => 0, 'pago_creditos' => 0
    ];
}

// Calcular valores intermedios
$disponible = floatval($balance['disponible']);
$cuentasCobrar = floatval($balance['cuenta_por_cobrar2']);
$inventario = floatval($balance['inventario']);
$activoFijo = floatval($balance['activo_fijo']);
$proveedores = floatval($balance['proveedores']);
$otrosPrestamos = floatval($balance['otros_prestamos']);
$prestInstituciones = floatval($balance['prest_instituciones']);
$patrimonio = floatval($balance['patrimonio']);
$ventas = floatval($balance['ventas']);
$recupCuentasCobrar = floatval($balance['cuenta_por_cobrar']);
$mercaderia = floatval($balance['mercaderia']);
$negocio = floatval($balance['negocio']);
$pagoCreditos = floatval($balance['pago_creditos']);

$activoCirculante = $disponible + $cuentasCobrar + $inventario;
$activoTotal = $activoCirculante + $activoFijo;
$pasivoTotal = $proveedores + $otrosPrestamos + $prestInstituciones;
$utilidadNeta = ($ventas + $recupCuentasCobrar) - ($mercaderia + $negocio + $pagoCreditos);
$ventasAnuales = $ventas * 12;

// Calcular razones financieras
$razonCirculante = $pasivoTotal > 0 ? $activoCirculante / $pasivoTotal : 0;
$pruebaAcido = $pasivoTotal > 0 ? ($disponible + $cuentasCobrar) / $pasivoTotal : 0;
$capitalTrabajo = $activoCirculante - $pasivoTotal;
$apalancamiento = $patrimonio > 0 ? $pasivoTotal / $patrimonio : 0;
$rotacionCXC = $cuentasCobrar > 0 ? $ventasAnuales / $cuentasCobrar : 0;
$diasCXC = $rotacionCXC > 0 ? round(365 / $rotacionCXC) : 0;
$rotacionInventario = $inventario > 0 ? $mercaderia / $inventario : 0;
$diasInventario = $rotacionInventario > 0 ? round(365 / ($rotacionInventario * 12)) : 0;
$rotacionActivos = $activoTotal > 0 ? $ventasAnuales / $activoTotal : 0;
$rotacionActivosFijos = $activoFijo > 0 ? $ventasAnuales / $activoFijo : 0;
$rotacionCTN = $capitalTrabajo > 0 ? $ventasAnuales / $capitalTrabajo : 0;
$roe = $patrimonio > 0 ? ($utilidadNeta / $patrimonio) * 100 : 0;
$roa = $activoTotal > 0 ? ($utilidadNeta / $activoTotal) * 100 : 0;
$margenVentas = $ventasAnuales > 0 ? ($utilidadNeta / $ventasAnuales) * 100 : 0;

// Obtener cuentas de ahorro del cliente
$queryCuentasAhorro = "SELECT 
    cta.ccodaho,
    cta.nlibreta,
    cta.estado,
    cli.compl_name AS nombre_propietario,
    cta.cnomaho AS nombre_cuenta,
    cta.fecha_apertura,
    cta.tasa
FROM ahomcta cta
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
WHERE REPLACE(REPLACE(TRIM(cta.ccodcli), CHAR(13), ''), CHAR(10), '') = ?
ORDER BY cta.fecha_apertura DESC";

$stmtAhorro = $conexion->prepare($queryCuentasAhorro);
$stmtAhorro->bind_param("s", $codCliente);
$stmtAhorro->execute();
$resultAhorro = $stmtAhorro->get_result();
$cuentasAhorro = [];
$totalAhorro = 0;
while ($row = $resultAhorro->fetch_assoc()) {
    // Obtener saldo de cada cuenta
    $querySaldo = "SELECT calcular_saldo_aho_tipcuenta(?, CURDATE()) AS saldo";
    $stmtSaldo = $conexion->prepare($querySaldo);
    $stmtSaldo->bind_param("s", $row['ccodaho']);
    $stmtSaldo->execute();
    $resSaldo = $stmtSaldo->get_result();
    $saldoRow = $resSaldo->fetch_assoc();
    $row['saldo'] = floatval($saldoRow['saldo'] ?? 0);
    $totalAhorro += $row['saldo'];
    $cuentasAhorro[] = $row;
}

// Obtener cuentas de aportaciones del cliente
$queryCuentasAport = "SELECT 
    cta.ccodaport,
    cta.nlibreta,
    cta.estado,
    cli.compl_name AS nombre_propietario,
    cta.fecha_apertura,
    cta.tasa
FROM aprcta cta
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
WHERE REPLACE(REPLACE(TRIM(cta.ccodcli), CHAR(13), ''), CHAR(10), '') = ?
ORDER BY cta.fecha_apertura DESC";

$stmtAport = $conexion->prepare($queryCuentasAport);
$stmtAport->bind_param("s", $codCliente);
$stmtAport->execute();
$resultAport = $stmtAport->get_result();
$cuentasAport = [];
$totalAport = 0;
while ($row = $resultAport->fetch_assoc()) {
    // Obtener saldo de cada cuenta
    $querySaldo = "SELECT calcular_saldo_apr_tipcuenta(?, CURDATE()) AS saldo";
    $stmtSaldo = $conexion->prepare($querySaldo);
    $stmtSaldo->bind_param("s", $row['ccodaport']);
    $stmtSaldo->execute();
    $resSaldo = $stmtSaldo->get_result();
    $saldoRow = $resSaldo->fetch_assoc();
    $row['saldo'] = floatval($saldoRow['saldo'] ?? 0);
    $totalAport += $row['saldo'];
    $cuentasAport[] = $row;
}

// Obtener créditos del cliente
$queryCreditos = "SELECT 
    cre.CCODCTA AS codigo_credito,
    tipc.Credito AS tipo_credito,
    cre.NCapDes AS monto_otorgado,
    cre.NIntApro AS tasa,
    cre.DFecDsbls AS fecha_desembolso,
    cre.noPeriodo AS plazo,
    CAST(
        CASE 
            WHEN (cre.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 
            THEN (cre.NCapDes - IFNULL(kar.sum_KP, 0)) 
            ELSE 0 
        END AS DECIMAL(15,2)
    ) AS saldo_capital,
    cre.Cestado AS estado
FROM cremcre_meta cre
LEFT JOIN $db_name_general.tb_credito tipc ON cre.CtipCre = tipc.abre
LEFT JOIN (
    SELECT 
        CCODCTA,
        SUM(KP) AS sum_KP
    FROM CREDKAR
    WHERE CESTADO != 'X' AND CTIPPAG = 'P'
    GROUP BY CCODCTA
) kar ON kar.CCODCTA = cre.CCODCTA
WHERE cre.CodCli = ?
    AND cre.Cestado IN ('F', 'G')
ORDER BY cre.DFecDsbls DESC";

$stmtCred = $conexion->prepare($queryCreditos);
$stmtCred->bind_param("s", $codCliente);
$stmtCred->execute();
$resultCred = $stmtCred->get_result();
$creditos = [];
$totalCredito = 0;
while ($row = $resultCred->fetch_assoc()) {
    $totalCredito += floatval($row['saldo_capital']);
    $creditos[] = $row;
}

// Crear PDF
class PDF extends FPDF
{
    public $institucion;
    public $oficina;
    public $direccion;
    public $telefono;
    public $email;
    public $nit;
    
    function __construct($orientation = 'P', $unit = 'mm', $size = 'A4')
    {
        parent::__construct($orientation, $unit, $size);
    }
    
    function setInstitucionInfo($institucion, $oficina, $direccion, $telefono, $email, $nit)
    {
        $this->institucion = $institucion;
        $this->oficina = $oficina;
        $this->direccion = $direccion;
        $this->telefono = $telefono;
        $this->email = $email;
        $this->nit = $nit;
    }
    
    function Header()
    {
        // Logo
        if (file_exists('../../../includes/img/logomicro.png')) {
            $this->Image('../../../includes/img/logomicro.png', 10, 6, 30);
        }
        
        // Información institución
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 5, decode_utf8($this->institucion), 0, 1, 'C');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, decode_utf8($this->direccion), 0, 1, 'C');
        $this->Cell(0, 4, 'Tel: ' . $this->telefono . ' | Email: ' . $this->email, 0, 1, 'C');
        $this->Cell(0, 4, 'NIT: ' . $this->nit, 0, 1, 'C');
        $this->Ln(3);
        
        // Título
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 6, decode_utf8('ANÁLISIS FINANCIERO INDIVIDUAL'), 0, 1, 'C');
        $this->Ln(2);
    }
    
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, decode_utf8('Página ') . $this->PageNo(), 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->setInstitucionInfo(
    decode_utf8($info['nomb_comple']),
    decode_utf8($info['nom_agencia']),
    decode_utf8($info['muni_lug']),
    $info['tel_1'],
    $info['emai'],
    $info['nit']
);

$pdf->AddPage();
$pdf->SetFont('Arial', '', 10);

// Información del Cliente
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, decode_utf8('INFORMACIÓN DEL CLIENTE'), 0, 1, 'L');
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(40, 5, decode_utf8('Código:'), 1, 0);
$pdf->Cell(150, 5, $cliente['codcli'], 1, 1);
$pdf->Cell(40, 5, 'Nombre:', 1, 0);
$pdf->Cell(150, 5, decode_utf8($cliente['nombre']), 1, 1);
$pdf->Cell(40, 5, 'DPI:', 1, 0);
$pdf->Cell(70, 5, $cliente['dpi'], 1, 0);
$pdf->Cell(30, 5, decode_utf8('Teléfono:'), 1, 0);
$pdf->Cell(50, 5, $cliente['telefono'], 1, 1);
$pdf->Cell(40, 5, decode_utf8('Dirección:'), 1, 0);
$pdf->Cell(150, 5, decode_utf8($cliente['direccion']), 1, 1);
$pdf->Ln(5);

// Resumen de Productos Financieros
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetFillColor(52, 152, 219);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 6, decode_utf8('RESUMEN DE PRODUCTOS FINANCIEROS'), 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(2);

// Cuentas de Ahorro
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(46, 204, 113);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 5, decode_utf8('CUENTAS DE AHORRO'), 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

if (count($cuentasAhorro) > 0) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(35, 5, decode_utf8('Código Cuenta'), 1, 0, 'C');
    $pdf->Cell(50, 5, 'Propietario', 1, 0, 'C');
    $pdf->Cell(30, 5, 'Apertura', 1, 0, 'C');
    $pdf->Cell(20, 5, 'Tasa %', 1, 0, 'C');
    $pdf->Cell(35, 5, 'Saldo', 1, 0, 'C');
    $pdf->Cell(20, 5, 'Estado', 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    foreach ($cuentasAhorro as $aho) {
        $nombrePropietario = $aho['nombre_propietario'] ?? 'Sin propietario';
        $nombrePropietario = substr($nombrePropietario, 0, 25);
        
        $pdf->Cell(35, 5, $aho['ccodaho'] ?? '', 1, 0);
        $pdf->Cell(50, 5, decode_utf8($nombrePropietario), 1, 0);
        $pdf->Cell(30, 5, date('d/m/Y', strtotime($aho['fecha_apertura'])), 1, 0, 'C');
        $pdf->Cell(20, 5, number_format($aho['tasa'] ?? 0, 2), 1, 0, 'C');
        $pdf->Cell(35, 5, 'Q ' . number_format($aho['saldo'] ?? 0, 2), 1, 0, 'R');
        $estadoText = $aho['estado'] == 'A' ? 'ACTIVA' : 'INACTIVA';
        $pdf->Cell(20, 5, $estadoText, 1, 1, 'C');
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(135, 5, 'TOTAL AHORROS:', 1, 0, 'R');
    $pdf->Cell(35, 5, 'Q ' . number_format($totalAhorro, 2), 1, 0, 'R');
    $pdf->Cell(20, 5, '', 1, 1);
} else {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetFillColor(255, 243, 205);
    $pdf->Cell(0, 5, decode_utf8('⚠ ALERTA: El cliente no posee cuentas de ahorro registradas'), 1, 1, 'C', true);
}
$pdf->Ln(3);

// Cuentas de Aportaciones
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(155, 89, 182);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 5, 'APORTACIONES', 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

if (count($cuentasAport) > 0) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(35, 5, decode_utf8('Código Cuenta'), 1, 0, 'C');
    $pdf->Cell(40, 5, decode_utf8('No. Libreta'), 1, 0, 'C');
    $pdf->Cell(30, 5, 'Apertura', 1, 0, 'C');
    $pdf->Cell(20, 5, 'Tasa %', 1, 0, 'C');
    $pdf->Cell(45, 5, 'Saldo', 1, 0, 'C');
    $pdf->Cell(20, 5, 'Estado', 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    foreach ($cuentasAport as $apr) {
        $pdf->Cell(35, 5, $apr['ccodaport'], 1, 0);
        $pdf->Cell(40, 5, $apr['nlibreta'], 1, 0);
        $pdf->Cell(30, 5, date('d/m/Y', strtotime($apr['fecha_apertura'])), 1, 0, 'C');
        $pdf->Cell(20, 5, number_format($apr['tasa'], 2), 1, 0, 'C');
        $pdf->Cell(45, 5, 'Q ' . number_format($apr['saldo'], 2), 1, 0, 'R');
        $estadoText = $apr['estado'] == 'A' ? 'ACTIVA' : 'INACTIVA';
        $pdf->Cell(20, 5, $estadoText, 1, 1, 'C');
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(125, 5, 'TOTAL APORTACIONES:', 1, 0, 'R');
    $pdf->Cell(45, 5, 'Q ' . number_format($totalAport, 2), 1, 0, 'R');
    $pdf->Cell(20, 5, '', 1, 1);
} else {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetFillColor(255, 243, 205);
    $pdf->Cell(0, 5, decode_utf8('⚠ ALERTA: El cliente no posee cuentas de aportaciones registradas'), 1, 1, 'C', true);
}
$pdf->Ln(3);

// Créditos
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(231, 76, 60);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 5, decode_utf8('CRÉDITOS'), 1, 1, 'L', true);
$pdf->SetTextColor(0, 0, 0);

if (count($creditos) > 0) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(30, 5, decode_utf8('Código'), 1, 0, 'C');
    $pdf->Cell(35, 5, 'Tipo', 1, 0, 'C');
    $pdf->Cell(30, 5, 'Desembolso', 1, 0, 'C');
    $pdf->Cell(25, 5, 'Monto', 1, 0, 'C');
    $pdf->Cell(25, 5, 'Saldo', 1, 0, 'C');
    $pdf->Cell(15, 5, 'Plazo', 1, 0, 'C');
    $pdf->Cell(15, 5, 'Tasa%', 1, 0, 'C');
    $pdf->Cell(15, 5, 'Estado', 1, 1, 'C');
    
    $pdf->SetFont('Arial', '', 8);
    foreach ($creditos as $cre) {
        $tipoCred = $cre['tipo_credito'] ?? 'Sin tipo';
        $tipoCred = substr($tipoCred, 0, 20);
        
        $pdf->Cell(30, 5, $cre['codigo_credito'] ?? '', 1, 0);
        $pdf->Cell(35, 5, decode_utf8($tipoCred), 1, 0);
        $pdf->Cell(30, 5, date('d/m/Y', strtotime($cre['fecha_desembolso'])), 1, 0, 'C');
        $pdf->Cell(25, 5, 'Q ' . number_format($cre['monto_otorgado'] ?? 0, 0), 1, 0, 'R');
        $pdf->Cell(25, 5, 'Q ' . number_format($cre['saldo_capital'] ?? 0, 0), 1, 0, 'R');
        $pdf->Cell(15, 5, $cre['plazo'] ?? '0', 1, 0, 'C');
        $pdf->Cell(15, 5, number_format($cre['tasa'] ?? 0, 1), 1, 0, 'C');
        $estadoText = ($cre['estado'] == 'F' ? 'Activo' :  'Cancelado');
        $pdf->Cell(15, 5, $estadoText, 1, 1, 'C');
    }
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell(120, 5, decode_utf8('TOTAL SALDO CRÉDITOS:'), 1, 0, 'R');
    $pdf->Cell(25, 5, 'Q ' . number_format($totalCredito, 2), 1, 0, 'R');
    $pdf->Cell(45, 5, '', 1, 1);
} else {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->SetFillColor(209, 242, 235);
    $pdf->Cell(0, 5, decode_utf8('✓ INFO: El cliente no posee créditos activos'), 1, 1, 'C', true);
}
$pdf->Ln(3);

// Resumen General de Productos
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(52, 73, 94);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(0, 6, 'RESUMEN GENERAL', 1, 1, 'C', true);
$pdf->SetTextColor(0, 0, 0);

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(95, 5, 'Total Ahorros:', 1, 0, 'R');
$pdf->Cell(95, 5, 'Q ' . number_format($totalAhorro, 2), 1, 1, 'R');
$pdf->Cell(95, 5, 'Total Aportaciones:', 1, 0, 'R');
$pdf->Cell(95, 5, 'Q ' . number_format($totalAport, 2), 1, 1, 'R');
$pdf->Cell(95, 5, decode_utf8('Total Saldo Créditos:'), 1, 0, 'R');
$pdf->Cell(95, 5, 'Q ' . number_format($totalCredito, 2), 1, 1, 'R');

$totalPatrimonio = $totalAhorro + $totalAport;
$relacionDeuda = $totalPatrimonio > 0 ? ($totalCredito / $totalPatrimonio) * 100 : 0;

$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(241, 196, 15);
$pdf->Cell(95, 5, decode_utf8('PATRIMONIO EN INSTITUCIÓN:'), 1, 0, 'R', true);
$pdf->Cell(95, 5, 'Q ' . number_format($totalPatrimonio, 2), 1, 1, 'R', true);

$pdf->SetFillColor(230, 126, 34);
$pdf->Cell(95, 5, decode_utf8('RELACIÓN DEUDA/PATRIMONIO:'), 1, 0, 'R', true);
$pdf->Cell(95, 5, number_format($relacionDeuda, 2) . '%', 1, 1, 'R', true);

// Alertas
$pdf->Ln(2);
$pdf->SetFont('Arial', 'B', 9);
if (count($cuentasAhorro) == 0) {
    $pdf->SetFillColor(255, 193, 7);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, decode_utf8('⚠ ADVERTENCIA: Cliente sin cuentas de ahorro'), 1, 1, 'L', true);
}
if (count($cuentasAport) == 0) {
    $pdf->SetFillColor(255, 193, 7);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(0, 5, decode_utf8('⚠ ADVERTENCIA: Cliente sin aportaciones'), 1, 1, 'L', true);
}
if ($relacionDeuda > 80 && count($creditos) > 0) {
    $pdf->SetFillColor(244, 67, 54);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(0, 5, decode_utf8('⚠ RIESGO ALTO: Relación deuda/patrimonio superior al 80%'), 1, 1, 'L', true);
}
$pdf->SetTextColor(0, 0, 0);

$pdf->AddPage();

// Estado Patrimonial
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'ESTADO PATRIMONIAL', 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 9);

// Tabla de dos columnas
$pdf->Cell(95, 5, 'ACTIVOS', 1, 0, 'C');
$pdf->Cell(95, 5, 'PASIVOS Y PATRIMONIO', 1, 1, 'C');

$pdf->SetFont('Arial', '', 9);

// Activos
$pdf->Cell(60, 5, 'Disponible', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($disponible, 2), 1, 0, 'R');

// Pasivos
$pdf->Cell(60, 5, 'Proveedores', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($proveedores, 2), 1, 1, 'R');

$pdf->Cell(60, 5, 'Cuentas por cobrar', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($cuentasCobrar, 2), 1, 0, 'R');
$pdf->Cell(60, 5, decode_utf8('Otros préstamos'), 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($otrosPrestamos, 2), 1, 1, 'R');

$pdf->Cell(60, 5, 'Inventario', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($inventario, 2), 1, 0, 'R');
$pdf->Cell(60, 5, decode_utf8('Préstamos institucionales'), 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($prestInstituciones, 2), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(60, 5, 'Total Circulante', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($activoCirculante, 2), 1, 0, 'R');
$pdf->Cell(60, 5, 'Suma Pasivo', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($pasivoTotal, 2), 1, 1, 'R');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(60, 5, 'Activo Fijo', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($activoFijo, 2), 1, 0, 'R');
$pdf->Cell(60, 5, 'Patrimonio', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($patrimonio, 2), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(60, 5, 'TOTAL ACTIVO', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($activoTotal, 2), 1, 0, 'R');
$pdf->Cell(60, 5, 'PASIVO Y PATRIMONIO', 1, 0);
$pdf->Cell(35, 5, 'Q ' . number_format($pasivoTotal + $patrimonio, 2), 1, 1, 'R');

$pdf->Ln(5);

// Estado de Ingresos y Egresos
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'ESTADO DE INGRESOS Y EGRESOS', 0, 1, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 5, 'Concepto', 1, 0, 'C');
$pdf->Cell(50, 5, 'Mensual', 1, 0, 'C');
$pdf->Cell(50, 5, 'Anual', 1, 1, 'C');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(190, 5, 'INGRESOS', 1, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 5, 'Ventas', 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($ventas, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format($ventasAnuales, 2), 1, 1, 'R');

$pdf->Cell(90, 5, 'Recup. cuentas por cobrar', 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($recupCuentasCobrar, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format($recupCuentasCobrar * 12, 2), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 5, 'Total Ingresos', 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($ventas + $recupCuentasCobrar, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format(($ventas + $recupCuentasCobrar) * 12, 2), 1, 1, 'R');

$pdf->Cell(190, 5, 'EGRESOS', 1, 1, 'L');

$pdf->SetFont('Arial', '', 9);
$pdf->Cell(90, 5, decode_utf8('Compra de mercadería'), 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($mercaderia, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format($mercaderia * 12, 2), 1, 1, 'R');

$pdf->Cell(90, 5, 'Gastos del negocio', 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($negocio, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format($negocio * 12, 2), 1, 1, 'R');

$pdf->Cell(90, 5, decode_utf8('Pagos de créditos'), 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($pagoCreditos, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format($pagoCreditos * 12, 2), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(90, 5, 'Total Egresos', 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($mercaderia + $negocio + $pagoCreditos, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format(($mercaderia + $negocio + $pagoCreditos) * 12, 2), 1, 1, 'R');

$pdf->Cell(90, 5, 'DIF. INGRESOS - EGRESOS', 1, 0);
$pdf->Cell(50, 5, 'Q ' . number_format($utilidadNeta, 2), 1, 0, 'R');
$pdf->Cell(50, 5, 'Q ' . number_format($utilidadNeta * 12, 2), 1, 1, 'R');

$pdf->AddPage();

// Razones Financieras
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, 'RAZONES FINANCIERAS', 0, 1, 'L');
$pdf->Ln(2);

// Función para determinar estado
function getEstado($valor, $tipo) {
    switch ($tipo) {
        case 'razon_circulante':
            if ($valor >= 2) return 'BUENO';
            if ($valor >= 1) return 'REGULAR';
            return 'MALO';
        case 'prueba_acido':
            if ($valor >= 1) return 'EXCELENTE';
            if ($valor >= 0.7) return 'ACEPTABLE';
            return 'BAJO';
        case 'capital_trabajo':
            return $valor > 0 ? 'POSITIVO' : 'NEGATIVO';
        case 'apalancamiento':
            if ($valor < 1) return 'BAJO';
            if ($valor <= 2) return 'MODERADO';
            return 'ALTO';
        case 'roe':
        case 'roa':
        case 'margen':
            if ($valor > 15) return 'EXCELENTE';
            if ($valor >= 5) return 'ACEPTABLE';
            return 'BAJO';
        default:
            return '';
    }
}

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'INDICADORES DE LIQUIDEZ', 0, 1);
$pdf->SetFont('Arial', '', 9);

$pdf->Cell(60, 5, decode_utf8('Razón Circulante'), 1, 0);
$pdf->Cell(30, 5, number_format($razonCirculante, 2), 1, 0, 'C');
$pdf->Cell(100, 5, getEstado($razonCirculante, 'razon_circulante'), 1, 1);

$pdf->Cell(60, 5, decode_utf8('Prueba del Ácido'), 1, 0);
$pdf->Cell(30, 5, number_format($pruebaAcido, 2), 1, 0, 'C');
$pdf->Cell(100, 5, getEstado($pruebaAcido, 'prueba_acido'), 1, 1);

$pdf->Cell(60, 5, 'Capital de Trabajo', 1, 0);
$pdf->Cell(30, 5, 'Q ' . number_format($capitalTrabajo, 2), 1, 0, 'R');
$pdf->Cell(100, 5, getEstado($capitalTrabajo, 'capital_trabajo'), 1, 1);

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'INDICADORES DE SOLVENCIA', 0, 1);
$pdf->SetFont('Arial', '', 9);

$pdf->Cell(60, 5, 'Apalancamiento', 1, 0);
$pdf->Cell(30, 5, number_format($apalancamiento, 2), 1, 0, 'C');
$pdf->Cell(100, 5, getEstado($apalancamiento, 'apalancamiento'), 1, 1);

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'INDICADORES DE EFICIENCIA OPERATIVA', 0, 1);
$pdf->SetFont('Arial', '', 9);

$pdf->Cell(60, 5, decode_utf8('Rotación CXC'), 1, 0);
$pdf->Cell(30, 5, number_format($rotacionCXC, 2) . ' veces', 1, 0, 'C');
$pdf->Cell(100, 5, '', 1, 1);

$pdf->Cell(60, 5, decode_utf8('Días CXC'), 1, 0);
$pdf->Cell(30, 5, $diasCXC . ' dias', 1, 0, 'C');
$pdf->Cell(100, 5, '', 1, 1);

$pdf->Cell(60, 5, decode_utf8('Rotación Inventario'), 1, 0);
$pdf->Cell(30, 5, number_format($rotacionInventario, 2) . ' veces', 1, 0, 'C');
$pdf->Cell(100, 5, '', 1, 1);

$pdf->Cell(60, 5, decode_utf8('Días Inventario'), 1, 0);
$pdf->Cell(30, 5, $diasInventario . ' dias', 1, 0, 'C');
$pdf->Cell(100, 5, '', 1, 1);

$pdf->Cell(60, 5, decode_utf8('Rotación Activos Totales'), 1, 0);
$pdf->Cell(30, 5, number_format($rotacionActivos, 2) . ' veces', 1, 0, 'C');
$pdf->Cell(100, 5, '', 1, 1);

$pdf->Cell(60, 5, decode_utf8('Rotación Activos Fijos'), 1, 0);
$pdf->Cell(30, 5, number_format($rotacionActivosFijos, 2) . ' veces', 1, 0, 'C');
$pdf->Cell(100, 5, '', 1, 1);

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 5, 'INDICADORES DE RENTABILIDAD', 0, 1);
$pdf->SetFont('Arial', '', 9);

$pdf->Cell(60, 5, 'ROE (Rentabilidad/Capital)', 1, 0);
$pdf->Cell(30, 5, number_format($roe, 2) . '%', 1, 0, 'C');
$pdf->Cell(100, 5, getEstado($roe, 'roe'), 1, 1);

$pdf->Cell(60, 5, 'ROA (Rentabilidad/Activos)', 1, 0);
$pdf->Cell(30, 5, number_format($roa, 2) . '%', 1, 0, 'C');
$pdf->Cell(100, 5, getEstado($roa, 'roa'), 1, 1);

$pdf->Cell(60, 5, 'Margen de Ventas', 1, 0);
$pdf->Cell(30, 5, number_format($margenVentas, 2) . '%', 1, 0, 'C');
$pdf->Cell(100, 5, getEstado($margenVentas, 'margen'), 1, 1);

$pdf->Cell(60, 5, 'Margen Neto', 1, 0);
$pdf->Cell(30, 5, 'Q ' . number_format($utilidadNeta, 2), 1, 0, 'R');
$pdf->Cell(100, 5, number_format($margenVentas, 2) . '%', 1, 1);

$pdf->Output('I', 'Analisis_Financiero_' . $codCliente . '.pdf');
