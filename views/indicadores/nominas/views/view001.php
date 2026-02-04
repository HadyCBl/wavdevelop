<?php

use App\Generic\User;
use Micro\Helpers\Log;

include __DIR__ . '/../../../../includes/Config/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
    exit;
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
    exit;
}

require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

include __DIR__ . '/../../../../includes/BD_con/db_con.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
date_default_timezone_set('America/Guatemala');

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");

$condi = $_POST["condi"] ?? '';
$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];

// Obtener los clientes empleados para el select
$clientes_empleados = [];
try {
    $conexion = new mysqli($db_host, $db_user, $db_password, $db_name);

    if ($conexion->connect_error) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $sql = "
    SELECT 
        tc.idcod_cliente,
        tc.primer_name,
        tc.segundo_name,
        tc.tercer_name,
        tc.primer_last,
        tc.segundo_last,
        tc.casada_last,
        tc.short_name,
        tc.compl_name,
        tc.date_birth,
        tc.genero,
        tc.no_identifica,
        tc.profesion,
        tc.Direccion,
        tc.tel_no1,
        tc.email,
        tc.estado,
        tc.no_igss,
        CONCAT(
            COALESCE(tc.primer_name, ''),
            ' ',
            COALESCE(tc.segundo_name, ''),
            ' ',
            COALESCE(tc.tercer_name, ''),
            ' ',
            COALESCE(tc.primer_last, ''),
            ' ',
            COALESCE(tc.segundo_last, '')
        ) as nombre_completo
    FROM tb_cliente tc 
    INNER JOIN tb_cliente_atributo emp ON emp.id_cliente = tc.idcod_cliente 
        AND emp.id_atributo = 14
        AND emp.valor IS NOT NULL 
        AND emp.valor != ''
    WHERE UPPER(TRIM(emp.valor)) IN ('SI', 'SÍ', '1', 'TRUE', 'VERDADERO', 'YES')
    AND tc.estado = '1'
    ORDER BY tc.primer_name, tc.primer_last
    ";

    $result = $conexion->query($sql);

    if ($result) {
        while ($fila = $result->fetch_assoc()) {
            $nombre_completo = trim($fila['primer_name'] . ' ' .
                ($fila['segundo_name'] ? $fila['segundo_name'] . ' ' : '') .
                ($fila['tercer_name'] ? $fila['tercer_name'] . ' ' : '') .
                $fila['primer_last'] . ' ' .
                ($fila['segundo_last'] ? $fila['segundo_last'] : ''));

            $clientes_empleados[] = [
                'idcod_cliente' => $fila['idcod_cliente'],
                'nombre_completo' => $nombre_completo,
                'no_identifica' => $fila['no_identifica'],
                'genero' => $fila['genero'],
                'date_birth' => $fila['date_birth'],
                'profesion' => $fila['profesion'],
                'Direccion' => $fila['Direccion'],
                'tel_no1' => $fila['tel_no1'],
                'email' => $fila['email'],
                'no_igss' => $fila['no_igss'],
                'estado' => $fila['estado']
            ];
        }
        $result->free();
    }

    $conexion->close();
} catch (Exception $e) {
    $clientes_empleados = [];
}

switch ($condi) {
   case 'createuser':
        $xtra = $_POST["xtra"] ?? '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creación de Empleados</title>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        .select2-container--default .select2-selection--single {
            height: 42px;
            border: 1px solid #d1d5db;
            border-radius: 0.375rem;
            background-color: white;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 42px;
            padding-left: 12px;
            color: #374151;
        }
        .conditional-field.hidden { display: none; }
        .info-bitacora {
            background-color: #f0f9ff;
            border-left: 4px solid #3b82f6;
            padding: 12px;
            margin-top: 16px;
            border-radius: 4px;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="px-6 py-4 lg:px-8 space-y-8 dark:bg-gray-900" id="contenedor-empleados">
        <!-- Formulario de creación -->
        <div class="bg-white rounded-2xl shadow-md overflow-hidden dark:bg-gray-800">
            <div class="px-6 py-4 bg-indigo-600 dark:bg-indigo-700">
                <h5 class="text-lg font-semibold text-white">
                    <i class="fas fa-user-plus mr-2"></i>Creación de Perfil de Empleado
                </h5>
            </div>
            <div class="p-6">
                <form id="form_empleado">
                    <input type="hidden" id="condi" name="condi" value="create_empleado">
                    <input type="hidden" id="file" name="file" value="view001">
                    
                    <!-- Sección 1: Cliente -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-user-check mr-2 text-indigo-500"></i>1. Selección de Cliente
                        </h6>
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="clienteSelect" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    <i class="fas fa-user-tie mr-1"></i>Cliente Empleado <span class="text-red-500">*</span>
                                </label>
                                <select id="clienteSelect" name="clienteSelect" class="cliente-select block w-full" required>
                                    <option value="">Seleccione un cliente empleado...</option>
                                    <?php if (!empty($clientes_empleados)): ?>
                                        <?php foreach ($clientes_empleados as $cliente): ?>
                                            <option value="<?php echo htmlspecialchars($cliente['idcod_cliente']); ?>"
                                                data-dpi="<?php echo htmlspecialchars($cliente['no_identifica'] ?? ''); ?>"
                                                data-genero="<?php echo htmlspecialchars($cliente['genero'] ?? ''); ?>"
                                                data-fecha-nac="<?php echo htmlspecialchars($cliente['date_birth'] ?? ''); ?>"
                                                data-profesion="<?php echo htmlspecialchars($cliente['profesion'] ?? ''); ?>"
                                                data-direccion="<?php echo htmlspecialchars($cliente['Direccion'] ?? ''); ?>"
                                                data-telefono="<?php echo htmlspecialchars($cliente['tel_no1'] ?? ''); ?>"
                                                data-email="<?php echo htmlspecialchars($cliente['email'] ?? ''); ?>"
                                                data-igss="<?php echo htmlspecialchars($cliente['no_igss'] ?? ''); ?>">
                                                <?php echo htmlspecialchars($cliente['nombre_completo']); ?>
                                                <?php if (!empty($cliente['no_identifica'])): ?>
                                                    | DPI: <?php echo htmlspecialchars($cliente['no_identifica']); ?>
                                                <?php endif; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option value="">No hay clientes empleados disponibles</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div id="infoClienteContainer" class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 hidden">
                                <div id="infoCliente" class="space-y-3 text-sm"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 2: Agencia y Departamento -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-building mr-2 text-indigo-500"></i>2. Ubicación Laboral
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="agencia" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Agencia/Sucursal <span class="text-red-500">*</span>
                                </label>
                                <select id="agencia" name="agencia" class="block w-full" required>
                                    <option value="">Cargando agencias...</option>
                                </select>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="departamento" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Departamento <span class="text-red-500">*</span>
                                </label>
                                <select id="departamento" name="departamento" class="block w-full" required>
                                    <option value="">Cargando departamentos...</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 3: Cuentas del Sistema -->
                    <div class="mb-8" id="seccion_cuentas" style="display: none;">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-piggy-bank mr-2 text-indigo-500"></i>3. Cuentas del Sistema
                        </h6>
                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 mb-4">
                            <div id="info_cuentas" class="text-sm text-gray-600 dark:text-gray-400">
                                <div class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando cuentas...</div>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="cuenta_ahorro" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Cuenta de Ahorro
                                </label>
                                <select id="cuenta_ahorro" name="cuenta_ahorro" class="block w-full">
                                    <option value="">Sin cuenta de ahorro</option>
                                </select>
                            </div>
                            <div>
                                <label for="cuenta_aportacion" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Cuenta de Aportación
                                </label>
                                <select id="cuenta_aportacion" name="cuenta_aportacion" class="block w-full">
                                    <option value="">Sin cuenta de aportación</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 4: Información Laboral Básica -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-briefcase mr-2 text-indigo-500"></i>4. Información Laboral
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="puesto" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Puesto <span class="text-red-500">*</span>
                                </label>
                                <input type="text" id="puesto" name="puesto" placeholder="Ej: Asistente Administrativo" class="block w-full" required>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="nivel" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Nivel Jerárquico
                                </label>
                                <select id="nivel" name="nivel" class="block w-full">
                                    <option value="OPERATIVO">Operativo</option>
                                    <option value="TECNICO">Técnico</option>
                                    <option value="SUPERVISOR">Supervisor</option>
                                    <option value="GERENTE">Gerente</option>
                                    <option value="DIRECTOR">Director</option>
                                    <option value="EJECUTIVO">Ejecutivo</option>
                                </select>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="fecha_ingreso" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Fecha de Ingreso <span class="text-red-500">*</span>
                                </label>
                                <input type="date" id="fecha_ingreso" name="fecha_ingreso" value="<?php echo $hoy; ?>" class="block w-full" required>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="tipo_contrato" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Tipo de Contrato
                                </label>
                                <select id="tipo_contrato" name="tipo_contrato" class="block w-full">
                                    <option value="INDEFINIDO">Indefinido</option>
                                    <option value="TEMPORAL">Temporal</option>
                                    <option value="PRUEBA">Prueba</option>
                                    <option value="POR_OBRA">Por Obra</option>
                                    <option value="OTRO">Otro</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6" id="fechas_contrato_container" style="display: none;">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="fecha_contrato" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Fecha de Contrato
                                </label>
                                <input type="date" id="fecha_contrato" name="fecha_contrato" value="<?php echo $hoy; ?>" class="block w-full">
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="fecha_fin_contrato" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Fecha Fin de Contrato
                                </label>
                                <input type="date" id="fecha_fin_contrato" name="fecha_fin_contrato" class="block w-full">
                            </div>
                        </div>
                    </div>

                    <!-- Sección 5: Compensación Salarial -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-money-bill-wave mr-2 text-indigo-500"></i>5. Compensación Salarial
                        </h6>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="sueldo_base" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Sueldo Base Mensual (Q) <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500">Q</span>
                                    </div>
                                    <input type="number" id="sueldo_base" name="sueldo_base" step="0.01" min="0" placeholder="0.00" 
                                           class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md" required oninput="calcularResumen()">
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="frecuencia_pago" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Frecuencia de Pago <span class="text-red-500">*</span>
                                </label>
                                <select id="frecuencia_pago" name="frecuencia_pago" class="block w-full" required onchange="calcularResumen()">
                                    <option value="">Cargando frecuencias...</option>
                                </select>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="tipo_moneda" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Tipo de Moneda
                                </label>
                                <select id="tipo_moneda" name="tipo_moneda" class="block w-full">
                                    <option value="GTQ" selected>Quetzales (GTQ)</option>
                                    <option value="USD">Dólares (USD)</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                                <label for="tipo_salario" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Tipo de Salario
                                </label>
                                <select id="tipo_salario" name="tipo_salario" class="block w-full" onchange="toggleComision()">
                                    <option value="FIJO" selected>Fijo</option>
                                    <option value="VARIABLE">Variable</option>
                                    <option value="MIXTO">Mixto</option>
                                </select>
                            </div>
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700" id="comision_container" style="display: none;">
                                <label for="porcentaje_comision" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Porcentaje de Comisión (%)
                                </label>
                                <div class="relative">
                                    <input type="number" id="porcentaje_comision" name="porcentaje_comision" step="0.01" min="0" max="100" 
                                           placeholder="0.00" class="block w-full pr-10 py-2 border border-gray-300 rounded-md">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500">%</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Resumen de Compensación -->
                        <div id="resumenCompensacion" class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4 mt-6">
                            <h6 class="text-sm font-medium text-indigo-700 dark:text-indigo-300 mb-3">
                                <i class="fas fa-calculator mr-1"></i>Resumen de Compensación
                            </h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Sueldo Base Mensual:</span>
                                        <span id="res_sueldo_base" class="text-sm font-medium text-gray-800 dark:text-gray-200">Q 0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Sueldo Base Diario:</span>
                                        <span id="res_sueldo_diario" class="text-sm font-medium text-gray-800 dark:text-gray-200">Q 0.00</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Bono 14 (Aprox.):</span>
                                        <span id="res_bono_14" class="text-sm font-medium text-gray-800 dark:text-gray-200">Q 0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Aguinaldo (Aprox.):</span>
                                        <span id="res_aguinaldo" class="text-sm font-medium text-gray-800 dark:text-gray-200">Q 0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 6: Beneficios de Ley -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-shield-alt mr-2 text-indigo-500"></i>6. Beneficios de Ley
                        </h6>
                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <!-- IGSS -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_igss" name="tiene_igss" class="h-4 w-4 text-green-600" checked>
                                    <label for="tiene_igss" class="text-sm font-medium text-gray-700 dark:text-gray-300">IGSS</label>
                                </div>
                                
                                <div class="md:col-span-2" id="numero_igss_container">
                                    <label for="numero_igss" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Número de IGSS
                                    </label>
                                    <input type="text" id="numero_igss" name="numero_igss" placeholder="Número de afiliación IGSS" class="block w-full">
                                </div>
                                
                                <!-- IRTRA -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_irtra" name="tiene_irtra" class="h-4 w-4 text-green-600" checked>
                                    <label for="tiene_irtra" class="text-sm font-medium text-gray-700 dark:text-gray-300">IRTRA</label>
                                </div>
                                
                                <!-- INTECAP -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_intecap" name="tiene_intecap" class="h-4 w-4 text-green-600" checked>
                                    <label for="tiene_intecap" class="text-sm font-medium text-gray-700 dark:text-gray-300">INTECAP</label>
                                </div>
                                
                                <!-- Bono 14 -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_bono14" name="tiene_bono14" class="h-4 w-4 text-green-600" checked onchange="calcularResumen()">
                                    <label for="tiene_bono14" class="text-sm font-medium text-gray-700 dark:text-gray-300">Bono 14</label>
                                </div>
                                
                                <!-- Aguinaldo -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_aguinaldo" name="tiene_aguinaldo" class="h-4 w-4 text-green-600" checked onchange="calcularResumen()">
                                    <label for="tiene_aguinaldo" class="text-sm font-medium text-gray-700 dark:text-gray-300">Aguinaldo</label>
                                </div>
                                
                                <!-- Vacaciones -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_vacaciones" name="tiene_vacaciones" class="h-4 w-4 text-green-600" checked>
                                    <label for="tiene_vacaciones" class="text-sm font-medium text-gray-700 dark:text-gray-300">Vacaciones</label>
                                </div>
                                
                                <!-- Indemnización -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_indemnizacion" name="tiene_indemnizacion" class="h-4 w-4 text-green-600" checked>
                                    <label for="tiene_indemnizacion" class="text-sm font-medium text-gray-700 dark:text-gray-300">Indemnización</label>
                                </div>
                                
                                <!-- Prestaciones -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_prestaciones" name="tiene_prestaciones" class="h-4 w-4 text-green-600" checked>
                                    <label for="tiene_prestaciones" class="text-sm font-medium text-gray-700 dark:text-gray-300">Prestaciones</label>
                                </div>
                                
                                <!-- Días de Vacaciones -->
                                <div class="md:col-span-3">
                                    <label for="dias_vacaciones" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Días de Vacaciones al Año
                                    </label>
                                    <input type="number" id="dias_vacaciones" name="dias_vacaciones" min="0" max="365" value="15" class="block w-full">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 7: Beneficios de Empresa -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-building mr-2 text-indigo-500"></i>7. Beneficios de la Empresa
                        </h6>
                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                <!-- Seguro Médico -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_seguro_medico" name="tiene_seguro_medico" class="h-4 w-4 text-blue-600">
                                    <label for="tiene_seguro_medico" class="text-sm font-medium text-gray-700 dark:text-gray-300">Seguro Médico</label>
                                </div>
                                
                                <!-- Plan de Pensiones -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_plan_pensiones" name="tiene_plan_pensiones" class="h-4 w-4 text-blue-600">
                                    <label for="tiene_plan_pensiones" class="text-sm font-medium text-gray-700 dark:text-gray-300">Plan de Pensiones</label>
                                </div>
                                
                                <!-- Bonos Productividad -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_bonos_productividad" name="tiene_bonos_productividad" class="h-4 w-4 text-blue-600">
                                    <label for="tiene_bonos_productividad" class="text-sm font-medium text-gray-700 dark:text-gray-300">Bonos Productividad</label>
                                </div>
                                
                                <!-- Capacitaciones -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_capacitaciones" name="tiene_capacitaciones" class="h-4 w-4 text-blue-600">
                                    <label for="tiene_capacitaciones" class="text-sm font-medium text-gray-700 dark:text-gray-300">Capacitaciones</label>
                                </div>
                                
                                <!-- Vale de Despensa -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_vale_despensa" name="tiene_vale_despensa" class="h-4 w-4 text-blue-600" onchange="toggleValeDespensa()">
                                    <label for="tiene_vale_despensa" class="text-sm font-medium text-gray-700 dark:text-gray-300">Vale de Despensa</label>
                                </div>
                                
                                <!-- Monto Vale Despensa -->
                                <div class="md:col-span-2" id="monto_vale_despensa_container" style="display: none;">
                                    <label for="monto_vale_despensa" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Monto Vale de Despensa (Mensual)
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">Q</span>
                                        </div>
                                        <input type="number" id="monto_vale_despensa" name="monto_vale_despensa" step="0.01" min="0" 
                                               placeholder="0.00" class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md">
                                    </div>
                                </div>
                                
                                <!-- Otros Beneficios -->
                                <div class="flex items-center space-x-2">
                                    <input type="checkbox" id="tiene_otros_beneficios" name="tiene_otros_beneficios" class="h-4 w-4 text-blue-600">
                                    <label for="tiene_otros_beneficios" class="text-sm font-medium text-gray-700 dark:text-gray-300">Otros Beneficios</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 8: Información Bancaria -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-university mr-2 text-indigo-500"></i>8. Información Bancaria
                        </h6>
                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="cuenta_bancaria" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Número de Cuenta
                                    </label>
                                    <input type="text" id="cuenta_bancaria" name="cuenta_bancaria" placeholder="Número de cuenta bancaria" class="block w-full">
                                </div>
                                <div>
                                    <label for="banco" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Banco
                                    </label>
                                    <input type="text" id="banco" name="banco" placeholder="Nombre del banco" class="block w-full">
                                </div>
                                <div>
                                    <label for="tipo_cuenta_bancaria" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Tipo de Cuenta
                                    </label>
                                    <select id="tipo_cuenta_bancaria" name="tipo_cuenta_bancaria" class="block w-full">
                                        <option value="">Seleccionar...</option>
                                        <option value="AHORRO">Ahorro</option>
                                        <option value="MONETARIA">Monetaria</option>
                                        <option value="NOMINA">Nómina</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 9: Horario de Trabajo -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-clock mr-2 text-indigo-500"></i>9. Horario de Trabajo
                        </h6>
                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="horario_entrada" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Hora de Entrada
                                    </label>
                                    <input type="time" id="horario_entrada" name="horario_entrada" value="08:00" class="block w-full">
                                </div>
                                <div>
                                    <label for="horario_salida" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Hora de Salida
                                    </label>
                                    <input type="time" id="horario_salida" name="horario_salida" value="17:00" class="block w-full">
                                </div>
                                <div>
                                    <label for="turno" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Turno
                                    </label>
                                    <select id="turno" name="turno" class="block w-full">
                                        <option value="MATUTINO" selected>Matutino</option>
                                        <option value="VESPERTINO">Vespertino</option>
                                        <option value="NOCTURNO">Nocturno</option>
                                        <option value="MIXTO">Mixto</option>
                                    </select>
                                </div>
                                <div>
                                    <label for="dias_trabajo" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Días de Trabajo
                                    </label>
                                    <input type="text" id="dias_trabajo" name="dias_trabajo" value="L-V" placeholder="Ej: L-V, L-S" class="block w-full">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sección 10: Observaciones -->
                    <div class="mb-8">
                        <h6 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4 border-b pb-2">
                            <i class="fas fa-sticky-note mr-2 text-indigo-500"></i>10. Observaciones
                        </h6>
                        <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700">
                            <textarea id="observaciones" name="observaciones" rows="4" 
                                      placeholder="Ingrese observaciones adicionales, condiciones especiales o detalles importantes..." 
                                      class="block w-full"></textarea>
                        </div>
                    </div>

                    <!-- Botones -->
                    <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
                        <button type="button" onclick="crearEmpleado()" class="inline-flex items-center px-8 py-3 bg-indigo-600 text-white rounded-md hover:bg-indigo-700 w-full sm:w-auto justify-center">
                            <i class="fa fa-user-plus mr-2"></i> Crear Perfil de Empleado
                        </button>
                        <button type="button" onclick="limpiarFormulario()" class="inline-flex items-center px-8 py-3 border border-gray-300 text-gray-700 rounded-md hover:bg-gray-50 w-full sm:w-auto justify-center">
                            <i class="fa fa-eraser mr-2"></i> Limpiar Formulario
                        </button>
                        <button type="button" onclick="history.back()" class="inline-flex items-center px-8 py-3 border border-red-300 text-red-700 rounded-md hover:bg-red-50 w-full sm:w-auto justify-center">
                            <i class="fa fa-ban mr-2"></i> Cancelar
                        </button>
                    </div>
                    
                    <!-- Bitácora de Auditoría -->
                    <div id="bitacora-creacion" class="info-bitacora mt-6 hidden">
                        <div class="flex items-center">
                            <i class="fas fa-history mr-2 text-blue-500"></i>
                            <span class="font-medium">Información de Auditoría:</span>
                        </div>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                            <div><span class="text-gray-600">Creado por:</span> <span id="bitacora-creador"></span></div>
                            <div><span class="text-gray-600">Fecha creación:</span> <span id="bitacora-fecha-creacion"></span></div>
                            <div><span class="text-gray-600">Última modificación:</span> <span id="bitacora-ultima-modificacion"></span></div>
                            <div><span class="text-gray-600">Modificado por:</span> <span id="bitacora-modificador"></span></div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabla: Empleados Registrados -->
        <div class="border border-gray-200 rounded-lg shadow-sm bg-white dark:bg-gray-800">
            <div class="p-6">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Empleados Registrados</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Lista de empleados con perfil completo</p>
                    </div>
                    <button onclick="cargarTablaEmpleados()" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                        <i class="fas fa-sync-alt mr-1"></i> Actualizar
                    </button>
                </div>

                <div class="overflow-x-auto">
                    <table id="tabla_empleados" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-100 dark:bg-gray-700">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">#</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Empleado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Puesto</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Departamento</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Agencia</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sueldo</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Estado</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                            <!-- Datos cargados por JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        console.log('=== INICIALIZANDO FORMULARIO EMPLEADO ===');
        
        $(document).ready(function() {
            console.log('1. Documento listo, inicializando...');
            
            // Inicializar Select2
            $('.cliente-select').select2({
                placeholder: "Seleccione o busque un cliente empleado...",
                allowClear: true,
                width: '100%'
            });
            
            // Eventos del cliente
            $('#clienteSelect').on('select2:select', function(e) {
                console.log('Cliente seleccionado:', $(this).val());
                const selectedOption = $(this).find('option:selected');
                mostrarInfoCliente(selectedOption);
                
                const idCliente = selectedOption.val();
                if (idCliente) {
                    console.log('Cargando cuentas para cliente:', idCliente);
                    cargarCuentasCliente(idCliente);
                    $('#seccion_cuentas').show();
                }
            });
            
            $('#clienteSelect').on('select2:clear', function() {
                console.log('Selección de cliente limpiada');
                ocultarInfoCliente();
                $('#seccion_cuentas').hide();
                $('#info_cuentas').html('<div class="text-center py-4">Seleccione un cliente para ver sus cuentas</div>');
                $('#cuenta_ahorro').html('<option value="">Sin cuenta de ahorro</option>');
                $('#cuenta_aportacion').html('<option value="">Sin cuenta de aportación</option>');
            });
            
            // Evento para tipo de contrato
            $('#tipo_contrato').change(function() {
                console.log('Tipo de contrato:', $(this).val());
                if ($(this).val() === 'TEMPORAL' || $(this).val() === 'POR_OBRA' || $(this).val() === 'PRUEBA') {
                    $('#fechas_contrato_container').show();
                } else {
                    $('#fechas_contrato_container').hide();
                }
            });
            
            // Cargar datos iniciales
            console.log('2. Cargando agencias...');
            cargarAgencias();
            
            console.log('3. Cargando departamentos...');
            cargarDepartamentos();
            
            console.log('4. Cargando frecuencias...');
            cargarFrecuencias();
            
            console.log('5. Cargando tabla de empleados...');
            cargarTablaEmpleados();
            
            // Inicializar cálculos
            calcularResumen();
            
            console.log('=== INICIALIZACIÓN COMPLETADA ===');
        });
        
        function toggleComision() {
            const tipoSalario = $('#tipo_salario').val();
            if (tipoSalario === 'VARIABLE' || tipoSalario === 'MIXTO') {
                $('#comision_container').show();
            } else {
                $('#comision_container').hide();
                $('#porcentaje_comision').val('0.00');
            }
        }
        
        function toggleValeDespensa() {
            if ($('#tiene_vale_despensa').is(':checked')) {
                $('#monto_vale_despensa_container').show();
            } else {
                $('#monto_vale_despensa_container').hide();
                $('#monto_vale_despensa').val('0.00');
            }
        }
        
        function cargarAgencias() {
            console.log('Función cargarAgencias() llamada');
            $.ajax({
                url: "../../../src/cruds/crud_empleado.php",
                type: "POST",
                data: { condi: "get_agencias_activas" },
                dataType: "json",
                beforeSend: function() {
                    console.log('Enviando solicitud de agencias...');
                    $('#agencia').html('<option value="">Cargando agencias...</option>');
                },
                success: function(response) {
                    console.log('Respuesta de agencias:', response);
                    if (response.status == 1 && response.data && response.data.length > 0) {
                        const select = $('#agencia');
                        select.empty();
                        select.append('<option value="">Seleccione agencia...</option>');
                        
                        response.data.forEach(function(agencia) {
                            select.append('<option value="' + agencia.id + '">' + agencia.nombre + ' (' + agencia.codigo_agencia + ')</option>');
                        });
                        console.log('Agencias cargadas:', response.data.length);
                    } else {
                        console.warn('No hay agencias disponibles');
                        $('#agencia').html('<option value="">No hay agencias disponibles</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al cargar agencias:', error);
                    $('#agencia').html('<option value="">Error al cargar agencias</option>');
                }
            });
        }
        
        function cargarDepartamentos() {
            console.log('Función cargarDepartamentos() llamada');
            $.ajax({
                url: "../../../src/cruds/crud_empleado.php",
                type: "POST",
                data: { condi: "get_departamentos_activos" },
                dataType: "json",
                beforeSend: function() {
                    console.log('Enviando solicitud de departamentos...');
                    $('#departamento').html('<option value="">Cargando departamentos...</option>');
                },
                success: function(response) {
                    console.log('Respuesta de departamentos:', response);
                    if (response.status == 1 && response.data && response.data.length > 0) {
                        const select = $('#departamento');
                        select.empty();
                        select.append('<option value="">Seleccione departamento...</option>');
                        
                        response.data.forEach(function(depto) {
                            select.append('<option value="' + depto.id + '">' + depto.nombre + ' (' + depto.codigo_departamento + ')</option>');
                        });
                        console.log('Departamentos cargados:', response.data.length);
                    } else {
                        console.warn('No hay departamentos disponibles');
                        $('#departamento').html('<option value="">No hay departamentos disponibles</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al cargar departamentos:', error);
                    $('#departamento').html('<option value="">Error al cargar departamentos</option>');
                }
            });
        }
        
        function cargarFrecuencias() {
            console.log('Función cargarFrecuencias() llamada');
            $.ajax({
                url: "../../../src/cruds/crud_empleado.php",
                type: "POST",
                data: { condi: "get_frecuencias_activas" },
                dataType: "json",
                beforeSend: function() {
                    console.log('Enviando solicitud de frecuencias...');
                    $('#frecuencia_pago').html('<option value="">Cargando frecuencias...</option>');
                },
                success: function(response) {
                    console.log('Respuesta de frecuencias:', response);
                    if (response.status == 1 && response.data && response.data.length > 0) {
                        const select = $('#frecuencia_pago');
                        select.empty();
                        select.append('<option value="">Seleccione frecuencia...</option>');
                        
                        response.data.forEach(function(frecuencia) {
                            let texto = frecuencia.nombre;
                            if (frecuencia.dias) texto += ' (' + frecuencia.dias + ' días)';
                            select.append('<option value="' + frecuencia.id + '">' + texto + '</option>');
                        });
                        console.log('Frecuencias cargadas:', response.data.length);
                    } else {
                        console.warn('No hay frecuencias disponibles');
                        $('#frecuencia_pago').html('<option value="">No hay frecuencias disponibles</option>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al cargar frecuencias:', error);
                    $('#frecuencia_pago').html('<option value="">Error al cargar frecuencias</option>');
                }
            });
        }
        
        function cargarCuentasCliente(idCliente) {
            console.log('Función cargarCuentasCliente() llamada con ID:', idCliente);
            $.ajax({
                url: "../../../src/cruds/crud_empleado.php",
                type: "POST",
                data: { 
                    condi: "get_cuentas_cliente",
                    id_cliente: idCliente
                },
                dataType: "json",
                beforeSend: function() {
                    console.log('Cargando cuentas del cliente...');
                    $('#info_cuentas').html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin mr-2"></i>Cargando cuentas...</div>');
                },
                success: function(response) {
                    console.log('Respuesta de cuentas:', response);
                    if (response.status == 1) {
                        actualizarSelectCuentas(response);
                        let infoHTML = '';
                        
                        if (response.count_total === 0) {
                            infoHTML = '<div class="text-center py-4 text-yellow-600">El cliente no tiene cuentas activas</div>';
                        } else {
                            infoHTML = '<div class="space-y-3">';
                            if (response.count_ahorro > 0) {
                                infoHTML += '<div><strong>Cuentas de Ahorro:</strong> ' + response.count_ahorro + '</div>';
                            }
                            if (response.count_aportacion > 0) {
                                infoHTML += '<div><strong>Cuentas de Aportación:</strong> ' + response.count_aportacion + '</div>';
                            }
                            infoHTML += '<div class="text-sm text-gray-500">Total: ' + response.count_total + ' cuentas</div>';
                            infoHTML += '</div>';
                        }
                        
                        $('#info_cuentas').html(infoHTML);
                        console.log('Cuentas procesadas correctamente');
                    } else {
                        console.error('Error en respuesta de cuentas:', response.message);
                        $('#info_cuentas').html('<div class="text-center py-4 text-red-600">' + response.message + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error al cargar cuentas:', error);
                    $('#info_cuentas').html('<div class="text-center py-4 text-red-600">Error al cargar las cuentas</div>');
                }
            });
        }
        
        function actualizarSelectCuentas(response) {
            console.log('Actualizando selects de cuentas...');
            
            // Cuentas de ahorro
            const selectAhorro = $('#cuenta_ahorro');
            selectAhorro.empty();
            selectAhorro.append('<option value="">Sin cuenta de ahorro</option>');
            
            if (response.count_ahorro > 0) {
                response.ahorro.forEach(cuenta => {
                    if (cuenta.estado === 'A') {
                        selectAhorro.append('<option value="' + cuenta.cuenta + '">' + cuenta.cuenta + ' - Q ' + 
                            parseFloat(cuenta.saldo || 0).toLocaleString('es-GT', {minimumFractionDigits: 2}) + '</option>');
                    }
                });
                console.log('Opciones de ahorro agregadas:', response.count_ahorro);
            }
            
            // Cuentas de aportación
            const selectAportacion = $('#cuenta_aportacion');
            selectAportacion.empty();
            selectAportacion.append('<option value="">Sin cuenta de aportación</option>');
            
            if (response.count_aportacion > 0) {
                response.aportacion.forEach(cuenta => {
                    if (cuenta.estado === 'A') {
                        selectAportacion.append('<option value="' + cuenta.cuenta + '">' + cuenta.cuenta + ' - Q ' + 
                            parseFloat(cuenta.saldo || 0).toLocaleString('es-GT', {minimumFractionDigits: 2}) + '</option>');
                    }
                });
                console.log('Opciones de aportación agregadas:', response.count_aportacion);
            }
        }
        
        function mostrarInfoCliente(selectedOption) {
            console.log('Mostrando información del cliente...');
            const idCliente = selectedOption.val();
            const nombre = selectedOption.text().split('|')[0].trim();
            const dpi = selectedOption.data('dpi');
            const igss = selectedOption.data('igss');
            
            let infoHTML = `
                <div class="space-y-2">
                    <div class="font-semibold text-gray-800 dark:text-gray-200">${nombre}</div>
                    <div class="text-xs text-gray-600 dark:text-gray-400">Código: ${idCliente}</div>
                    ${dpi ? '<div class="text-sm">DPI: ' + dpi + '</div>' : ''}
                    ${igss ? '<div class="text-sm text-green-600">IGSS: ' + igss + '</div>' : ''}
                </div>
            `;
            
            $('#infoCliente').html(infoHTML);
            $('#infoClienteContainer').removeClass('hidden');
            
            // Si tiene IGSS, llenar automáticamente el campo
            if (igss) {
                $('#numero_igss').val(igss);
            }
        }
        
        function ocultarInfoCliente() {
            $('#infoClienteContainer').addClass('hidden');
            $('#infoCliente').html('');
        }
        
        function limpiarSeleccionCliente() {
            $('#clienteSelect').val(null).trigger('change');
            ocultarInfoCliente();
        }
        
        function calcularResumen() {
            const sueldoBase = parseFloat($('#sueldo_base').val()) || 0;
            const sueldoDiario = sueldoBase / 30;
            const tieneBono14 = $('#tiene_bono14').is(':checked');
            const tieneAguinaldo = $('#tiene_aguinaldo').is(':checked');
            
            const bono14 = tieneBono14 ? sueldoBase : 0;
            const aguinaldo = tieneAguinaldo ? sueldoBase : 0;
            
            $('#res_sueldo_base').text('Q ' + formatCurrency(sueldoBase));
            $('#res_sueldo_diario').text('Q ' + formatCurrency(sueldoDiario));
            $('#res_bono_14').text('Q ' + formatCurrency(bono14));
            $('#res_aguinaldo').text('Q ' + formatCurrency(aguinaldo));
            
            if (sueldoBase > 0) {
                $('#resumenCompensacion').show();
            } else {
                $('#resumenCompensacion').hide();
            }
        }
        
        function formatCurrency(amount) {
            return amount.toLocaleString('es-GT', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }
        
        function cargarTablaEmpleados() {
            console.log('=== CARGANDO TABLA DE EMPLEADOS ===');
            
            // Limpiar tabla actual
            $('#tabla_empleados tbody').html(
                '<tr><td colspan="9" class="text-center py-4">' +
                '<i class="fas fa-spinner fa-spin mr-2"></i>Cargando empleados...</td></tr>'
            );
            
            $.ajax({
                url: "../../../src/cruds/crud_empleado.php",
                type: "POST",
                data: { condi: "table_empleados" },
                dataType: "json",
                beforeSend: function() {
                    console.log('Solicitando datos de empleados...');
                },
                success: function(response) {
                    console.log('Respuesta completa de empleados:', response);
                    
                    if (response.status == 1 && response.data && Array.isArray(response.data)) {
                        console.log('Número de empleados encontrados:', response.data.length);
                        
                        if (response.data.length === 0) {
                            $('#tabla_empleados tbody').html(
                                '<tr><td colspan="9" class="text-center py-4">No hay empleados registrados</td></tr>'
                            );
                            return;
                        }
                        
                        let html = '';
                        response.data.forEach(function(empleado, index) {
                            console.log('Procesando empleado ' + (index + 1) + ':', empleado);
                            
                            // Determinar badge de estado
                            let estadoBadge = '';
                            switch(empleado.estado) {
                                case 'ACTIVO':
                                case '1':
                                    estadoBadge = '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">Activo</span>';
                                    break;
                                case 'INACTIVO':
                                    estadoBadge = '<span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full">Inactivo</span>';
                                    break;
                                case 'VACACIONES':
                                    estadoBadge = '<span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full">Vacaciones</span>';
                                    break;
                                default:
                                    estadoBadge = '<span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full">' + empleado.estado + '</span>';
                            }
                            
                            html += `
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-center">${index + 1}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-mono">${empleado.codigo_empleado || 'N/A'}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    ${empleado.nombre_completo || 'N/A'}
                                    ${empleado.dpi ? '<br><small class="text-gray-500">DPI: ' + empleado.dpi + '</small>' : ''}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">${empleado.puesto || 'N/A'}</td>
                                <td class="px-6 py-4 whitespace-nowrap">${empleado.departamento || 'N/A'}</td>
                                <td class="px-6 py-4 whitespace-nowrap">${empleado.agencia || 'N/A'}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-medium">
                                    Q ${parseFloat(empleado.sueldo_base || 0).toLocaleString('es-GT', {minimumFractionDigits: 2})}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">${estadoBadge}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="flex space-x-2 justify-center">
                                        <button onclick="verDetalleEmpleado(${empleado.id})" class="text-indigo-600 hover:text-indigo-900" title="Ver detalle">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button onclick="editarEmpleado(${empleado.id})" class="text-green-600 hover:text-green-900" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button onclick="eliminarEmpleado(${empleado.id})" class="text-red-600 hover:text-red-900" title="Desactivar">
                                            <i class="fas fa-user-slash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>`;
                        });
                        
                        $('#tabla_empleados tbody').html(html);
                        console.log('Tabla actualizada con ' + response.data.length + ' registros');
                    } else {
                        console.error('Error en respuesta de empleados:', response.message);
                        $('#tabla_empleados tbody').html(
                            '<tr><td colspan="9" class="text-center py-4 text-red-600">' +
                            'Error: ' + (response.message || 'Datos inválidos') + '</td></tr>'
                        );
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error AJAX al cargar empleados:', error, xhr.responseText);
                    $('#tabla_empleados tbody').html(
                        '<tr><td colspan="9" class="text-center py-4 text-red-600">' +
                        'Error al cargar los datos. Verifique la conexión.</td></tr>'
                    );
                }
            });
        }
        
        function crearEmpleado() {
            console.log('=== INICIANDO CREACIÓN DE EMPLEADO ===');
            
            // Validaciones básicas
            const camposRequeridos = [
                { id: 'clienteSelect', nombre: 'Cliente empleado' },
                { id: 'agencia', nombre: 'Agencia' },
                { id: 'departamento', nombre: 'Departamento' },
                { id: 'puesto', nombre: 'Puesto' },
                { id: 'fecha_ingreso', nombre: 'Fecha de ingreso' },
                { id: 'sueldo_base', nombre: 'Sueldo base' },
                { id: 'frecuencia_pago', nombre: 'Frecuencia de pago' }
            ];
            
            let errores = [];
            camposRequeridos.forEach(campo => {
                const valor = $('#' + campo.id).val();
                if (!valor || valor === '') {
                    errores.push(campo.nombre);
                    $('#' + campo.id).focus();
                }
            });
            
            if (errores.length > 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Campos requeridos',
                    text: 'Por favor complete los siguientes campos: ' + errores.join(', ')
                });
                return;
            }
            
            const sueldoBase = parseFloat($('#sueldo_base').val());
            if (sueldoBase <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Sueldo inválido',
                    text: 'Por favor ingrese un sueldo base mayor a 0'
                });
                return;
            }
            
            // Preparar datos del formulario COMPLETO
            const formData = {
                condi: 'create_empleado',
                // Datos básicos
                clienteSelect: $('#clienteSelect').val(), 
                agencia: $('#agencia').val(),
                departamento: $('#departamento').val(),
                puesto: $('#puesto').val(),
                nivel: $('#nivel').val(),
                tipo_contrato: $('#tipo_contrato').val(),
                fecha_ingreso: $('#fecha_ingreso').val(),
                fecha_contrato: $('#fecha_contrato').val() || $('#fecha_ingreso').val(),
                fecha_fin_contrato: $('#fecha_fin_contrato').val() || null,
                // Compensación
                frecuencia_pago: $('#frecuencia_pago').val(),
                sueldo_base: sueldoBase,
                tipo_moneda: $('#tipo_moneda').val(),
                tipo_salario: $('#tipo_salario').val(),
                porcentaje_comision: $('#porcentaje_comision').val() || 0,
                // Beneficios de Ley
                tiene_bono14: $('#tiene_bono14').is(':checked') ? '1' : '0',
                tiene_aguinaldo: $('#tiene_aguinaldo').is(':checked') ? '1' : '0',
                tiene_indemnizacion: $('#tiene_indemnizacion').is(':checked') ? '1' : '0',
                tiene_prestaciones: $('#tiene_prestaciones').is(':checked') ? '1' : '0',
                tiene_igss: $('#tiene_igss').is(':checked') ? '1' : '0',
                numero_igss: $('#numero_igss').val() || null,
                tiene_irtra: $('#tiene_irtra').is(':checked') ? '1' : '0',
                tiene_intecap: $('#tiene_intecap').is(':checked') ? '1' : '0',
                tiene_vacaciones: $('#tiene_vacaciones').is(':checked') ? '1' : '0',
                dias_vacaciones: $('#dias_vacaciones').val() || 15,
                // Beneficios de Empresa
                tiene_seguro_medico: $('#tiene_seguro_medico').is(':checked') ? '1' : '0',
                tiene_plan_pensiones: $('#tiene_plan_pensiones').is(':checked') ? '1' : '0',
                tiene_bonos_productividad: $('#tiene_bonos_productividad').is(':checked') ? '1' : '0',
                tiene_capacitaciones: $('#tiene_capacitaciones').is(':checked') ? '1' : '0',
                tiene_vale_despensa: $('#tiene_vale_despensa').is(':checked') ? '1' : '0',
                monto_vale_despensa: $('#monto_vale_despensa').val() || 0,
                tiene_otros_beneficios: $('#tiene_otros_beneficios').is(':checked') ? '1' : '0',
                // Información Bancaria
                cuenta_bancaria: $('#cuenta_bancaria').val() || null,
                banco: $('#banco').val() || null,
                tipo_cuenta: $('#tipo_cuenta_bancaria').val() || null,
                // Horario
                horario_entrada: $('#horario_entrada').val() || '08:00:00',
                horario_salida: $('#horario_salida').val() || '17:00:00',
                turno: $('#turno').val() || 'MATUTINO',
                dias_trabajo: $('#dias_trabajo').val() || 'L-V',
                // Cuentas del sistema
                cuenta_ahorro: $('#cuenta_ahorro').val() || null,
                cuenta_aportacion: $('#cuenta_aportacion').val() || null,
                // Observaciones
                observaciones: $('#observaciones').val() || null
            };
            
            console.log('Datos a enviar:', formData);
            
            // Confirmación
            Swal.fire({
                title: '¿Crear perfil de empleado?',
                html: `
                <div class="text-left">
                    <p>¿Está seguro de crear el perfil de empleado?</p>
                    <ul class="text-sm text-gray-600 mt-2 space-y-1">
                        <li><strong>Cliente:</strong> ${$('#clienteSelect option:selected').text().split('|')[0].trim()}</li>
                        <li><strong>Puesto:</strong> ${$('#puesto').val()}</li>
                        <li><strong>Agencia:</strong> ${$('#agencia option:selected').text()}</li>
                        <li><strong>Sueldo:</strong> Q ${sueldoBase.toFixed(2)}</li>
                    </ul>
                </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Sí, crear',
                cancelButtonText: 'Cancelar',
                width: '500px'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log('Enviando datos al servidor...');
                    
$.ajax({
    url: "../../../src/cruds/crud_empleado.php",
    type: "POST",
    data: formData,
    dataType: "json", // ✅ AGREGADO: Auto-parsea la respuesta JSON
    beforeSend: function() {
        Swal.fire({
            title: 'Creando empleado...',
            text: 'Por favor espere',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });
    },
    success: function(response) { // ✅ CAMBIADO: de 'data' a 'response' para claridad
        console.log('Respuesta del servidor:', response);
        
        // ✅ ELIMINADO: El try/catch con JSON.parse ya no es necesario
        // porque dataType: "json" ya parsea automáticamente
        
        if (response.status == 1) {
            Swal.fire({
                icon: 'success',
                title: '¡Éxito!',
                html: response.message + '<br><br>Código: <strong>' + (response.codigo || 'N/A') + '</strong>', // ✅ CORREGIDO: response.codigo en lugar de response.data.codigo_empleado
                timer: 3000,
                showConfirmButton: false
            });
            
            setTimeout(() => {
                cargarTablaEmpleados();
                limpiarFormulario();
            }, 3000);
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: response.message || 'Error desconocido'
            });
        }
    },
    error: function(xhr, status, error) {
        console.error('Error AJAX:', status, error, xhr.responseText);
        
        // ✅ MEJORADO: Intenta obtener el mensaje de error del servidor
        let errorMessage = 'No se pudo conectar con el servidor';
        try {
            const errorResponse = JSON.parse(xhr.responseText);
            if (errorResponse.message) {
                errorMessage = errorResponse.message;
            }
        } catch (e) {
            // Si no se puede parsear, usa el mensaje genérico
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Error de conexión',
            text: errorMessage
        });
    }
});
                }
            });
        }
        
        function limpiarFormulario() {
            Swal.fire({
                title: '¿Limpiar formulario?',
                text: '¿Está seguro de limpiar todos los campos?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, limpiar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    $('#form_empleado')[0].reset();
                    $('#clienteSelect').val(null).trigger('change');
                    ocultarInfoCliente();
                    $('#seccion_cuentas').hide();
                    
                    // Restaurar valores por defecto
                    $('#fecha_ingreso').val('<?php echo $hoy; ?>');
                    $('#fecha_contrato').val('<?php echo $hoy; ?>');
                    $('#tipo_moneda').val('GTQ');
                    $('#nivel').val('OPERATIVO');
                    $('#tipo_contrato').val('INDEFINIDO');
                    $('#tipo_salario').val('FIJO');
                    $('#turno').val('MATUTINO');
                    $('#dias_trabajo').val('L-V');
                    $('#horario_entrada').val('08:00');
                    $('#horario_salida').val('17:00');
                    $('#dias_vacaciones').val('15');
                    
                    // Restaurar checks por defecto
                    $('#tiene_igss').prop('checked', true);
                    $('#tiene_irtra').prop('checked', true);
                    $('#tiene_intecap').prop('checked', true);
                    $('#tiene_bono14').prop('checked', true);
                    $('#tiene_aguinaldo').prop('checked', true);
                    $('#tiene_vacaciones').prop('checked', true);
                    $('#tiene_indemnizacion').prop('checked', true);
                    $('#tiene_prestaciones').prop('checked', true);
                    
                    // Ocultar campos condicionales
                    $('#fechas_contrato_container').hide();
                    $('#comision_container').hide();
                    $('#monto_vale_despensa_container').hide();
                    $('#resumenCompensacion').hide();
                    
                    // Recargar selects
                    cargarAgencias();
                    cargarDepartamentos();
                    cargarFrecuencias();
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Formulario limpiado',
                        text: 'Todos los campos han sido restablecidos',
                        timer: 1500,
                        showConfirmButton: false
                    });
                }
            });
        }
        
        function verDetalleEmpleado(id) {
            console.log('Ver detalle del empleado ID:', id);
            Swal.fire({
                title: 'Cargando información...',
                text: 'Por favor espere',
                icon: 'info',
                showConfirmButton: false,
                didOpen: () => Swal.showLoading()
            });
            
            $.ajax({
                url: "../../../src/cruds/crud_empleado.php",
                type: "POST",
                data: { condi: "get_empleado_by_id", id: id },
                dataType: "json",
                success: function(response) {
                    Swal.close();
                    console.log('Detalle del empleado:', response);
                    
                    if (response.status == 1) {
                        const e = response.data;
                        
                        // Formatear datos de bitácora
                        const fechaCreacion = e.created_at ? new Date(e.created_at).toLocaleString('es-GT') : 'N/A';
                        const fechaModificacion = e.updated_at ? new Date(e.updated_at).toLocaleString('es-GT') : 'N/A';
                        
                        let beneficiosHTML = '';
                        if (e.tiene_igss == 1 || e.tiene_igss === '1') {
                            beneficiosHTML += `<div><i class="fas fa-check text-green-500 mr-1"></i>IGSS: ${e.numero_igss || 'Sin número'}</div>`;
                        }
                        if (e.tiene_bono14 == 1 || e.tiene_bono14 === '1') {
                            beneficiosHTML += `<div><i class="fas fa-check text-green-500 mr-1"></i>Bono 14</div>`;
                        }
                        if (e.tiene_aguinaldo == 1 || e.tiene_aguinaldo === '1') {
                            beneficiosHTML += `<div><i class="fas fa-check text-green-500 mr-1"></i>Aguinaldo</div>`;
                        }
                        
                        Swal.fire({
                            title: 'Detalle del Empleado',
                            html: `
                            <div class="text-left space-y-4">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <div class="text-xs font-medium text-gray-500">Código</div>
                                        <div class="text-sm font-semibold">${e.codigo_empleado || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500">Estado</div>
                                        <div class="text-sm">${e.estado || 'N/A'}</div>
                                    </div>
                                    <div class="col-span-2">
                                        <div class="text-xs font-medium text-gray-500">Puesto</div>
                                        <div class="text-sm">${e.puesto || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500">Departamento</div>
                                        <div class="text-sm">${e.departamento_nombre || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500">Agencia</div>
                                        <div class="text-sm">${e.agencia_nombre || 'N/A'}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500">Sueldo Base</div>
                                        <div class="text-sm">Q ${parseFloat(e.sueldo_base_mensual || 0).toLocaleString('es-GT', {minimumFractionDigits: 2})}</div>
                                    </div>
                                    <div>
                                        <div class="text-xs font-medium text-gray-500">Tipo Salario</div>
                                        <div class="text-sm">${e.tipo_salario || 'FIJO'}</div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="text-xs font-medium text-gray-500 mb-1">Beneficios Principales</div>
                                    <div class="text-sm space-y-1">
                                        ${beneficiosHTML}
                                    </div>
                                </div>
                                
                                <div class="info-bitacora">
                                    <div class="flex items-center">
                                        <i class="fas fa-history mr-2 text-blue-500"></i>
                                        <span class="font-medium">Información de Auditoría:</span>
                                    </div>
                                    <div class="mt-2 grid grid-cols-2 gap-2 text-sm">
                                        <div><span class="text-gray-600">Creado:</span> ${fechaCreacion}</div>
                                        <div><span class="text-gray-600">Última modificación:</span> ${fechaModificacion}</div>
                                    </div>
                                </div>
                            </div>
                            `,
                            confirmButtonText: 'Cerrar',
                            width: '600px'
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Empleado no encontrado'
                        });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'No se pudo obtener la información'
                    });
                }
            });
        }
        
        function editarEmpleado(id) {
            console.log('Editar empleado ID:', id);
            // Esta función se implementará después
            Swal.fire({
                title: 'Funcionalidad en desarrollo',
                text: 'La edición de empleados estará disponible próximamente',
                icon: 'info'
            });
        }
        
        function eliminarEmpleado(id) {
            console.log('Eliminar empleado ID:', id);
            Swal.fire({
                title: '¿Desactivar empleado?',
                text: 'El empleado será marcado como INACTIVO',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, desactivar',
                cancelButtonText: 'Cancelar',
                input: 'text',
                inputLabel: 'Motivo (opcional):',
                inputPlaceholder: 'Ej: Renuncia, Despido...'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "../../../src/cruds/crud_empleado.php",
                        type: "POST",
                        data: {
                            condi: "delete_empleado",
                            id: id,
                            motivo: result.value || 'Desactivado por usuario'
                        },
                        dataType: "json",
                        beforeSend: function() {
                            Swal.fire({
                                title: 'Desactivando...',
                                text: 'Por favor espere',
                                allowOutsideClick: false,
                                didOpen: () => Swal.showLoading()
                            });
                        },
                        success: function(response) {
                            Swal.close();
                            console.log('Respuesta de eliminación:', response);
                            
                            if (response.status == 1) {
                                Swal.fire('¡Desactivado!', response.message, 'success');
                                cargarTablaEmpleados();
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.close();
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'No se pudo desactivar el empleado'
                            });
                        }
                    });
                }
            });
        }
    </script>
</body>
</html>
<?php
        break;

    case 'create_depart':
    ?>
        <div class="px-6 py-4 lg:px-8 space-y-8 dark:bg-gray-900">
            <!-- Card: Creación de Departamento -->
            <div class="bg-white rounded-2xl shadow-md overflow-hidden dark:bg-gray-800 dark:border-gray-700">
                <div class="px-6 py-4 bg-indigo-600 dark:bg-indigo-700">
                    <h5 class="text-lg font-semibold text-white">
                        <i class="fas fa-user-plus mr-2"></i>Creación de Departamento
                    </h5>
                </div>
                <div class="p-6">
                    <form id="form_departamento">
                        <!-- Campos ocultos -->
                        <input type="hidden" id="condi" name="condi" value="add_departamento">
                        <input type="hidden" id="file" name="file" value="view_departamento">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Código de Departamento -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="codigo_departamento" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Código de Departamento <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="codigo_departamento"
                                    name="codigo_departamento"
                                    placeholder="Ej: RH, IT, FIN"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                    required
                                    maxlength="20">
                                <small class="text-gray-500 dark:text-gray-400 mt-1 block">
                                    Código único en mayúsculas sin espacios
                                </small>
                            </div>

                            <!-- Nombre del Departamento -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Nombre del Departamento <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="nombre"
                                    name="nombre"
                                    placeholder="Ej: Recursos Humanos, Tecnología, Finanzas"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                    required
                                    maxlength="100">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Agencia -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="id_agencia" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Agencia Asignada
                                </label>
                                <select
                                    id="id_agencia"
                                    name="id_agencia"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                    <option value="">(Todas las agencias) --</option>
                                    <!-- Las opciones se cargarán via AJAX -->
                                </select>
                                <div id="loading_agencias" class="hidden mt-2">
                                    <div class="flex items-center text-sm text-gray-500">
                                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-teal-600 mr-2"></div>
                                        Cargando agencias...
                                    </div>
                                </div>
                            </div>

                            <!-- Número de Empleados -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="num_empleados" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Número de Empleados
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        id="num_empleados"
                                        name="num_empleados"
                                        min="0"
                                        value="0"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">empleados</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="mb-6">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Descripción del Departamento
                                </label>
                                <textarea
                                    id="descripcion"
                                    name="descripcion"
                                    rows="3"
                                    placeholder="Describa las funciones, responsabilidades y objetivos del departamento..."
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-teal-500 focus:border-teal-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"></textarea>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="mb-8">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Estado del Departamento <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            id="estado_activo"
                                            name="estado"
                                            value="ACTIVO"
                                            checked
                                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 dark:bg-gray-600 dark:border-gray-500">
                                        <label for="estado_activo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <i class="fas fa-check-circle mr-1"></i> Activo
                                            </span>
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            id="estado_inactivo"
                                            name="estado"
                                            value="INACTIVO"
                                            class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 dark:bg-gray-600 dark:border-gray-500">
                                        <label for="estado_inactivo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                <i class="fas fa-times-circle mr-1"></i> Inactivo
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div id="resumenDepartamento" class="bg-teal-50 dark:bg-teal-900/20 border border-teal-200 dark:border-teal-800 rounded-lg p-4 mb-6">
                            <h6 class="text-sm font-medium text-teal-700 dark:text-teal-300 mb-3">
                                <i class="fas fa-info-circle mr-1"></i>Resumen del Departamento
                            </h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Código:</span>
                                        <span id="res_codigo" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Nombre:</span>
                                        <span id="res_nombre" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Empleados:</span>
                                        <span id="res_empleados" class="text-sm font-medium text-gray-800 dark:text-gray-200">0</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Estado:</span>
                                        <span id="res_estado" class="text-sm font-medium text-gray-800 dark:text-gray-200">Activo</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
                            <button
                                type="button"
                                onclick="crearDepartamento()"
                                class="inline-flex items-center px-8 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:from-blue-700 dark:to-indigo-700 dark:hover:from-blue-800 dark:hover:to-indigo-800 w-full sm:w-auto justify-center">
                                <i class="fa fa-save mr-2"></i> Guardar Agencia
                            </button>
                            <button
                                type="button"
                                onclick="limpiarFormDepartamento()"
                                class="inline-flex items-center px-8 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-teal-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 w-full sm:w-auto justify-center">
                                <i class="fa fa-eraser mr-2"></i> Limpiar
                            </button>
                            <button
                                type="button"
                                onclick="salir()"
                                class="inline-flex items-center px-8 py-3 border border-red-300 text-base font-medium rounded-md shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-gray-800 dark:text-red-400 dark:border-red-600 dark:hover:bg-red-900/20 w-full sm:w-auto justify-center">
                                <i class="fa fa-ban mr-2"></i> Cancelar
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Tabla: Departamentos Registrados -->
            <div class="border border-gray-200 rounded-lg shadow-sm bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Departamentos Registrados</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Lista de todos los departamentos del sistema
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                onclick="cargarTablaDepartamentos()"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                title="Actualizar tabla">
                                <i class="fas fa-sync-alt mr-1"></i> Actualizar
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="tabla_departamentos" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Agencia</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Empleados</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Creado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                <!-- DataTable llenará esto -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            // Función para cargar las agencias via AJAX
            function cargarAgencias() {
                $('#loading_agencias').show();

                $.ajax({
                    url: "../../../src/cruds/crud_empleado.php",
                    type: "POST",
                    data: {
                        condi: "get_agencias_activas"
                    },
                    dataType: "json",
                    success: function(response) {
                        $('#loading_agencias').hide();

                        if (response.status == 1 && response.data) {
                            const select = $('#id_agencia');
                            select.empty();
                            select.append('<option value="">-- Corporativo (Todas las agencias) --</option>');

                            response.data.forEach(function(agencia) {
                                select.append(`<option value="${agencia.id}">${agencia.codigo_agencia} - ${agencia.nombre}</option>`);
                            });
                        } else {
                            $('#id_agencia').html('<option value="">Error al cargar agencias</option>');
                        }
                    },
                    error: function() {
                        $('#loading_agencias').hide();
                        $('#id_agencia').html('<option value="">Error de conexión</option>');
                    }
                });
            }

            // Función para actualizar resumen en tiempo real
            function actualizarResumenDepartamento() {
                const codigo = $('#codigo_departamento').val() || '-';
                const nombre = $('#nombre').val() || '-';
                const empleados = $('#num_empleados').val() || '0';
                const estado = $('input[name="estado"]:checked').val() || 'ACTIVO';

                $('#res_codigo').text(codigo);
                $('#res_nombre').text(nombre);
                $('#res_empleados').text(empleados);

                // Estado con color
                let estadoClass = estado === 'ACTIVO' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400';
                $('#res_estado').attr('class', 'text-sm font-medium ' + estadoClass).text(estado === 'ACTIVO' ? 'Activo' : 'Inactivo');

                // Mostrar u ocultar resumen
                if (codigo !== '-' || nombre !== '-') {
                    $('#resumenDepartamento').show();
                } else {
                    $('#resumenDepartamento').hide();
                }
            }

            // Función para cargar tabla de departamentos
            function cargarTablaDepartamentos() {
                // Limpiar tabla si existe
                if ($.fn.DataTable.isDataTable('#tabla_departamentos')) {
                    $('#tabla_departamentos').DataTable().destroy();
                    $('#tabla_departamentos tbody').empty();
                }

                // Inicializar DataTable
                const table = $('#tabla_departamentos').DataTable({
                    processing: true,
                    serverSide: false,
                    ajax: {
                        url: "../../../src/cruds/crud_empleado.php",
                        type: "POST",
                        data: {
                            condi: "list_departamentos"
                        },
                        dataSrc: function(json) {
                            console.log("Respuesta departamentos:", json);
                            if (json.status === 0) {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: json.message || 'Error al cargar departamentos'
                                });
                                return [];
                            }
                            return json.data || [];
                        },
                        error: function(xhr, status, error) {
                            console.error("Error AJAX:", error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de conexión',
                                text: 'No se pudieron cargar los departamentos'
                            });
                        }
                    },
                    columns: [{
                            data: null,
                            render: function(data, type, row, meta) {
                                return meta.row + 1;
                            },
                            className: "text-center"
                        },
                        {
                            data: "codigo_departamento",
                            render: function(data, type, row) {
                                if (type === 'display' && row.isEditing) {
                                    // Modo edición
                                    return `
                            <div class="relative">
                                <input type="text" 
                                       class="edit-input w-full px-2 py-1 border border-indigo-300 rounded text-sm font-mono font-bold text-indigo-700"
                                       value="${data || ''}"
                                       data-field="codigo_departamento"
                                       data-id="${row.id}">
                                <div class="text-xs text-red-500 mt-1 hidden edit-error" data-field="codigo_departamento"></div>
                            </div>
                        `;
                                }
                                // Modo visualización
                                return `<span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${data}</span>`;
                            }
                        },
                        {
                            data: "nombre",
                            render: function(data, type, row) {
                                if (type === 'display' && row.isEditing) {
                                    // Modo edición
                                    return `
                            <div class="relative">
                                <input type="text" 
                                       class="edit-input w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                       value="${data || ''}"
                                       data-field="nombre"
                                       data-id="${row.id}">
                                <div class="text-xs text-red-500 mt-1 hidden edit-error" data-field="nombre"></div>
                            </div>
                        `;
                                }
                                // Modo visualización
                                return `<span class="font-medium">${data}</span>`;
                            }
                        },
                        {
                            data: "id_agencia",
                            render: function(data, type, row) {
                                if (type === 'display' && row.isEditing) {
                                    // ID único para este select
                                    const selectId = `select_agencia_${row.id}`;

                                    // Inicializar con placeholder
                                    let html = `
                <div class="relative">
                    <select id="${selectId}" 
                            class="edit-input w-full px-2 py-1 border border-gray-300 rounded text-sm"
                            data-field="id_agencia"
                            data-id="${row.id}">
                        <option value="">-- Cargando agencias...</option>
                    </select>
                </div>
            `;

                                    // Cargar agencias dinámicamente
                                    setTimeout(() => {
                                        cargarAgenciasParaSelect(selectId, data, row.id);
                                    }, 100);

                                    return html;
                                }
                                // Modo visualización
                                if (data && data != 0) {
                                    // Intentar obtener el nombre de la agencia si está disponible
                                    if (row.agencia_nombre) {
                                        return `
                    <div class="flex flex-col items-start">
                        <span class="text-sm text-gray-600 dark:text-gray-400">${row.agencia_nombre}</span>
                        <span class="text-xs text-gray-500">ID: ${data}</span>
                    </div>
                `;
                                    }
                                    return `<span class="text-sm text-gray-600 dark:text-gray-400">Agencia #${data}</span>`;
                                }
                                return '<span class="text-gray-400">Corporativo</span>';
                            }
                        },
                        {
                            data: "num_empleados",
                            render: function(data, type, row) {
                                if (type === 'display' && row.isEditing) {
                                    // Modo edición
                                    return `
                            <div class="relative">
                                <input type="number" 
                                       class="edit-input w-full px-2 py-1 border border-blue-300 rounded text-sm text-center"
                                       value="${data || 0}"
                                       data-field="num_empleados"
                                       data-id="${row.id}"
                                       min="0">
                            </div>
                        `;
                                }
                                // Modo visualización
                                const count = data || 0;
                                return `<span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">${count}</span>`;
                            },
                            className: "text-center"
                        },
                        {
                            data: "estado",
                            render: function(data, type, row) {
                                if (type === 'display' && row.isEditing) {
                                    // Modo edición
                                    const estados = ['ACTIVO', 'INACTIVO'];
                                    let options = '';
                                    estados.forEach(estado => {
                                        const selected = data === estado ? 'selected' : '';
                                        options += `<option value="${estado}" ${selected}>${estado}</option>`;
                                    });

                                    return `
                            <select class="edit-input w-full px-2 py-1 border border-gray-300 rounded text-sm"
                                    data-field="estado"
                                    data-id="${row.id}">
                                ${options}
                            </select>
                        `;
                                }
                                // Modo visualización
                                if (data === 'ACTIVO') {
                                    return '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-200">Activo</span>';
                                }  else {
                                    return '<span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full dark:bg-red-900 dark:text-red-200">Inactivo</span>';
                                }
                            },
                            className: "text-center"
                        },
                        {
                            data: "created_at",
                            render: function(data) {
                                return data ? new Date(data).toLocaleDateString('es-GT') : '-';
                            },
                            className: "text-center"
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                if (row.isEditing) {
                                    // Botones en modo edición
                                    return `
                            <div class="flex space-x-2 justify-center">
                                <button onclick="guardarEdicionDepartamento(${row.id})" 
                                        class="inline-flex items-center px-3 py-1.5 bg-green-500 text-white border border-green-600 rounded-md hover:bg-green-600 transition-colors duration-200"
                                        title="Guardar cambios">
                                    <i class="fas fa-save text-sm mr-1.5"></i>
                                    <span class="text-xs font-medium">Guardar</span>
                                </button>
                                <button onclick="cancelarEdicionDepartamento(${row.id})" 
                                        class="inline-flex items-center px-3 py-1.5 bg-gray-500 text-white border border-gray-600 rounded-md hover:bg-gray-600 transition-colors duration-200"
                                        title="Cancelar edición">
                                    <i class="fas fa-times text-sm mr-1.5"></i>
                                    <span class="text-xs font-medium">Cancelar</span>
                                </button>
                            </div>
                        `;
                                } else {
                                    // Botones en modo normal
                                    return `
                            <div class="flex space-x-3 justify-center">
                                <button onclick="iniciarEdicionDepartamento(${row.id})" 
                                        class="inline-flex items-center px-3 py-1.5 bg-green-50 text-green-700 border border-green-200 rounded-md hover:bg-green-100 hover:border-green-300 dark:bg-green-900/30 dark:text-green-400 dark:border-green-800 dark:hover:bg-green-900/50 transition-colors duration-200"
                                        title="Editar departamento">
                                    <i class="fas fa-edit text-sm mr-1.5"></i>
                                    <span class="text-xs font-medium">Editar</span>
                                </button>
                                <button onclick="eliminarDepartamento(${row.id})" 
                                        class="inline-flex items-center px-3 py-1.5 bg-red-50 text-red-700 border border-red-200 rounded-md hover:bg-red-100 hover:border-red-300 dark:bg-red-900/30 dark:text-red-400 dark:border-red-800 dark:hover:bg-red-900/50 transition-colors duration-200"
                                        title="Eliminar departamento">
                                    <i class="fas fa-trash text-sm mr-1.5"></i>
                                    <span class="text-xs font-medium">Eliminar</span>
                                </button>
                            </div>
                        `;
                                }
                            },
                            orderable: false,
                            className: "text-center py-4"
                        }
                    ],
                    pageLength: 10,
                    responsive: true,
                    order: [
                        [1, 'asc']
                    ],
                    language: {
                        search: "Buscar:",
                        lengthMenu: "Mostrar _MENU_ registros",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ departamentos",
                        infoEmpty: "No hay departamentos registrados",
                        zeroRecords: "No se encontraron departamentos",
                        paginate: {
                            first: "Primero",
                            last: "Último",
                            next: "Siguiente",
                            previous: "Anterior"
                        }
                    },
                    createdRow: function(row, data, dataIndex) {
                        // Añadir atributo data-id a la fila
                        $(row).attr('data-id', data.id);
                    }
                });
            }


            let departamentoEditando = null;
            let datosOriginales = {};

            function iniciarEdicionDepartamento(id) {
                const table = $('#tabla_departamentos').DataTable();
                const datos = table.row(`[data-id="${id}"]`).data();

                if (!datos) {
                    Swal.fire('Error', 'No se encontraron los datos del departamento', 'error');
                    return;
                }

                // Guardar datos originales
                datosOriginales[id] = {
                    ...datos
                };

                // Activar modo edición
                datos.isEditing = true;
                departamentoEditando = id;

                // Actualizar la fila
                table.row(`[data-id="${id}"]`).data(datos).draw();

                // Enfocar el primer campo
                setTimeout(() => {
                    $(`[data-id="${id}"][data-field="codigo_departamento"]`).focus();
                }, 100);
            }

            function cargarAgenciasParaSelect(selectId, valorActual, rowId) {
                $.ajax({
                    url: "../../../src/cruds/crud_empleado.php",
                    type: "POST",
                    data: {
                        condi: "get_agencias_activas"
                    },
                    dataType: "json",
                    beforeSend: function() {
                        $(`#${selectId}`).html('<option value="">Cargando agencias...</option>');
                    },
                    success: function(response) {
                        if (response.status === 1 && response.data && response.data.length > 0) {
                            let options = '<option value="">-- Corporativo (sin agencia) --</option>';

                            response.data.forEach(agencia => {
                                const selected = (valorActual == agencia.id) ? 'selected' : '';
                                options += `<option value="${agencia.id}" ${selected}>${agencia.codigo_agencia} - ${agencia.nombre}</option>`;
                            });

                            $(`#${selectId}`).html(options);

                            // Actualizar contador de agencias
                            const count = response.count || response.data.length;
                            $(`tr[data-id="${rowId}"] [data-field="id_agencia"]`).after(`
                    <div class="text-xs text-blue-500 mt-1">
                        <i class="fas fa-building mr-1"></i>
                        ${count} agencia(s) disponible(s)
                    </div>
                `);

                        } else {
                            $(`#${selectId}`).html('<option value="">-- No hay agencias disponibles --</option>');
                            $(`tr[data-id="${rowId}"] [data-field="id_agencia"]`).after(`
                    <div class="text-xs text-yellow-500 mt-1">
                        <i class="fas fa-exclamation-triangle mr-1"></i>
                        No hay agencias activas en el sistema
                    </div>
                `);
                        }
                    },
                    error: function() {
                        $(`#${selectId}`).html('<option value="">-- Error al cargar --</option>');
                    }
                });
            }

            function obtenerNombreAgencia(idAgencia, callback) {
                if (!idAgencia || idAgencia == 0) {
                    callback(null);
                    return;
                }

                $.ajax({
                    url: "../../../src/cruds/crud_empleado.php",
                    type: "POST",
                    data: {
                        condi: "get_agencia_by_id",
                        id: idAgencia
                    },
                    dataType: "json",
                    success: function(response) {
                        if (response.status === 1 && response.data) {
                            callback(response.data.nombre);
                        } else {
                            callback(null);
                        }
                    },
                    error: function() {
                        callback(null);
                    }
                });
            }

            function cargarAgenciasEnFormularioCreacion() {
                const selectId = 'id_agencia_creacion';
                const loadingId = 'loading_agencias_creacion';

                // Mostrar loading
                $(`#${loadingId}`).removeClass('hidden');

                $.ajax({
                    url: "../../../src/cruds/crud_empleado.php",
                    type: "POST",
                    data: {
                        condi: "get_agencias_activas"
                    },
                    dataType: "json",
                    success: function(response) {
                        $(`#${loadingId}`).addClass('hidden');
                        const select = $(`#${selectId}`);
                        select.empty().append('<option value="">-- Corporativo (sin agencia) --</option>');

                        if (response.status === 1 && response.data && response.data.length > 0) {
                            response.data.forEach(agencia => {
                                select.append(`<option value="${agencia.id}">${agencia.codigo_agencia} - ${agencia.nombre}</option>`);
                            });

                            // Mostrar contador
                            if (!$('#contador_agencias_creacion').length) {
                                select.after(`
                        <div id="contador_agencias_creacion" class="mt-2 p-2 bg-teal-50 border border-teal-100 rounded text-xs text-teal-700 dark:bg-teal-900/30 dark:text-teal-300 dark:border-teal-800">
                            <i class="fas fa-building mr-1"></i>
                            ${response.data.length} agencia(s) disponible(s)
                        </div>
                    `);
                            } else {
                                $('#contador_agencias_creacion').html(`
                        <i class="fas fa-building mr-1"></i>
                        ${response.data.length} agencia(s) disponible(s)
                    `);
                            }

                        } else {
                            select.append('<option value="">-- No hay agencias disponibles --</option>');
                            if (!$('#contador_agencias_creacion').length) {
                                select.after(`
                        <div id="contador_agencias_creacion" class="mt-2 p-2 bg-yellow-50 border border-yellow-100 rounded text-xs text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-300 dark:border-yellow-800">
                            <i class="fas fa-exclamation-triangle mr-1"></i>
                            No hay agencias activas en el sistema
                        </div>
                    `);
                            }
                        }
                    },
                    error: function() {
                        $(`#${loadingId}`).addClass('hidden');
                        $(`#${selectId}`).html('<option value="">-- Error al cargar agencias --</option>');
                    }
                });
            }

            // Ejecutar cuando el DOM esté listo
            $(document).ready(function() {
                // Solo cargar si existe el formulario de creación
                if ($('#id_agencia_creacion').length) {
                    cargarAgenciasEnFormularioCreacion();
                }
            });

            // Ejecutar al cargar el formulario
            $(document).ready(function() {
                if ($('#id_agencia').length) {
                    cargarAgenciasEnFormularioCreacion();
                }
            });

            function cancelarEdicionDepartamento(id) {
                const table = $('#tabla_departamentos').DataTable();
                const datos = table.row(`[data-id="${id}"]`).data();

                if (datosOriginales[id]) {
                    // Restaurar datos originales
                    Object.assign(datos, datosOriginales[id]);
                    datos.isEditing = false;

                    // Actualizar la fila
                    table.row(`[data-id="${id}"]`).data(datos).draw();
                } else {
                    // Solo desactivar modo edición
                    datos.isEditing = false;
                    table.row(`[data-id="${id}"]`).data(datos).draw();
                }

                // Limpiar variables
                delete datosOriginales[id];
                if (departamentoEditando === id) {
                    departamentoEditando = null;
                }
            }

            function guardarEdicionDepartamento(id) {
                const table = $('#tabla_departamentos').DataTable();
                const fila = $(`tr[data-id="${id}"]`);

                // Obtener valores editados
                const datosEditados = {};
                let tieneErrores = false;

                // Validar cada campo
                fila.find('.edit-input').each(function() {
                    const field = $(this).data('field');
                    let value = $(this).val();

                    // Convertir tipos según el campo
                    if (field === 'id_agencia' || field === 'num_empleados') {
                        value = value ? parseInt(value) : null;
                    } else if (field === 'presupuesto_anual') {
                        value = value ? parseFloat(value) : null;
                    } else {
                        value = value.trim();
                    }

                    datosEditados[field] = value;

                    // Validaciones específicas
                    if (field === 'codigo_departamento') {
                        if (!value) {
                            mostrarError(id, field, 'El código es requerido');
                            tieneErrores = true;
                        } else if (!/^[A-Z0-9_]+$/.test(value)) {
                            mostrarError(id, field, 'Solo mayúsculas, números y guiones bajos');
                            tieneErrores = true;
                        } else {
                            ocultarError(id, field);
                        }
                    } else if (field === 'nombre') {
                        if (!value) {
                            mostrarError(id, field, 'El nombre es requerido');
                            tieneErrores = true;
                        } else {
                            ocultarError(id, field);
                        }
                    }
                });

                if (tieneErrores) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Campos inválidos',
                        text: 'Por favor corrige los errores en los campos marcados',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                // Mostrar confirmación
                Swal.fire({
                    title: '¿Guardar cambios?',
                    text: '¿Está seguro de actualizar este departamento?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, guardar',
                    cancelButtonText: 'Cancelar',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return new Promise((resolve, reject) => {
                            // Preparar datos para enviar
                            const postData = {
                                condi: 'update_departamento',
                                id: id,
                                codigo_departamento: datosEditados.codigo_departamento,
                                nombre_departamento: datosEditados.nombre,
                                descripcion: datosEditados.descripcion || '',
                                id_agencia: datosEditados.id_agencia || null,
                                presupuesto_anual: datosEditados.presupuesto_anual || null,
                                responsable_id: datosEditados.responsable_id || null,
                                num_empleados: datosEditados.num_empleados || 0,
                                color: datosEditados.color || '#3B82F6',
                                icono: datosEditados.icono || 'fas fa-building',
                                estado: datosEditados.estado || 'ACTIVO'
                            };

                            // Eliminar campos vacíos
                            Object.keys(postData).forEach(key => {
                                if (postData[key] === null || postData[key] === undefined) {
                                    delete postData[key];
                                }
                            });

                            $.ajax({
                                url: "../../../src/cruds/crud_empleado.php",
                                type: "POST",
                                data: postData,
                                dataType: "json",
                                success: function(response) {
                                    if (response.status === 1 || response.success === true) {
                                        resolve(response);
                                    } else {
                                        reject(new Error(response.message || 'Error al actualizar'));
                                    }
                                },
                                error: function(xhr, status, error) {
                                    reject(new Error('Error de conexión: ' + error));
                                }
                            });
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Actualizar datos en la tabla
                        const datos = table.row(`[data-id="${id}"]`).data();

                        // Actualizar con los nuevos valores
                        Object.assign(datos, datosEditados);
                        datos.isEditing = false;

                        // Actualizar la fila
                        table.row(`[data-id="${id}"]`).data(datos).draw();

                        // Limpiar variables
                        delete datosOriginales[id];
                        departamentoEditando = null;

                        Swal.fire({
                            icon: 'success',
                            title: '¡Éxito!',
                            text: result.value.message || 'Departamento actualizado exitosamente',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    }
                }).catch((error) => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: error.message || 'No se pudieron guardar los cambios'
                    });
                });
            }

            // Funciones auxiliares para mostrar/ocultar errores
            function mostrarError(id, field, mensaje) {
                $(`tr[data-id="${id}"] .edit-error[data-field="${field}"]`)
                    .text(mensaje)
                    .removeClass('hidden');
            }

            function ocultarError(id, field) {
                $(`tr[data-id="${id}"] .edit-error[data-field="${field}"]`)
                    .addClass('hidden');
            }

            // Función para crear departamento
            function crearDepartamento() {
                const codigo_departamento = $('#codigo_departamento').val().trim().toUpperCase();
                const nombre = $('#nombre').val().trim();
                const descripcion = $('#descripcion').val().trim();
                const id_agencia = $('#id_agencia').val() || '';
                const num_empleados = $('#num_empleados').val() || '0';
                const estado = $('input[name="estado"]:checked').val();

                // Validaciones básicas
                if (!codigo_departamento) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Código requerido',
                        text: 'Por favor ingrese un código para el departamento.'
                    });
                    $('#codigo_departamento').focus();
                    return;
                }

                if (!nombre) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Nombre requerido',
                        text: 'Por favor ingrese un nombre para el departamento.'
                    });
                    $('#nombre').focus();
                    return;
                }

                // Validar formato del código
                const codigoRegex = /^[A-Z0-9_]+$/;
                if (!codigoRegex.test(codigo_departamento)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Formato inválido',
                        text: 'El código debe contener solo letras mayúsculas, números y guiones bajos.'
                    });
                    $('#codigo_departamento').focus();
                    return;
                }

                // Preparar datos para enviar
                const formData = new FormData();
                formData.append('condi', 'add_departamento');
                formData.append('codigo_departamento', codigo_departamento);
                formData.append('nombre', nombre);
                formData.append('descripcion', descripcion);
                formData.append('id_agencia', id_agencia);
                formData.append('num_empleados', num_empleados);
                formData.append('estado', estado);

                // Confirmar creación
                Swal.fire({
                    title: '¿Crear departamento?',
                    html: `
            <div class="text-left">
                <p class="mb-3">¿Está seguro de crear el siguiente departamento?</p>
                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><strong>Código:</strong></div><div>${codigo_departamento}</div>
                        <div><strong>Nombre:</strong></div><div>${nombre}</div>
                        <div><strong>Tipo:</strong></div><div>${id_agencia ? 'Por Agencia' : 'Corporativo'}</div>
                        <div><strong>Empleados:</strong></div><div>${num_empleados}</div>
                        <div><strong>Estado:</strong></div><div>${estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}</div>
                    </div>
                </div>
            </div>
        `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, crear',
                    cancelButtonText: 'Cancelar',
                    width: '500px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Enviar datos via AJAX
                        $.ajax({
                            url: "../../../src/cruds/crud_empleado.php",
                            type: "POST",
                            data: formData,
                            dataType: "json",
                            contentType: false,
                            processData: false,
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            success: function(response) {
                                console.log("Respuesta recibida:", response);

                                if (response.status == 1 || response.success === true) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Éxito!',
                                        text: response.message || 'Departamento creado exitosamente',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });

                                    // Recargar tabla y limpiar formulario después de 2 segundos
                                    setTimeout(() => {
                                        cargarTablaDepartamentos();
                                        limpiarFormDepartamento();
                                    }, 2000);
                                } else {
                                    Swal.fire({
                                        icon: response.type || 'error',
                                        title: response.type === 'warning' ? 'Advertencia' : 'Error',
                                        text: response.message || 'Error desconocido'
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', {
                                    status: status,
                                    error: error,
                                    responseText: xhr.responseText,
                                    statusCode: xhr.status
                                });

                                let errorMessage = 'No se pudo conectar con el servidor.';

                                if (xhr.responseText) {
                                    try {
                                        const errorResponse = JSON.parse(xhr.responseText);
                                        errorMessage = errorResponse.message || errorResponse.error || xhr.responseText.substring(0, 100);
                                    } catch (e) {
                                        errorMessage = xhr.responseText.substring(0, 150);
                                    }
                                }

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de conexión',
                                    html: `<div class="text-left">
                            <p><strong>Error:</strong> ${error}</p>
                            <p><strong>Detalles:</strong> ${errorMessage}</p>
                        </div>`
                                });
                            },
                            complete: function() {
                                loaderefect(0);
                            }
                        });
                    }
                });
            }

            // Función para limpiar formulario
            function limpiarFormDepartamento() {
                Swal.fire({
                    title: '¿Limpiar formulario?',
                    text: '¿Está seguro de limpiar todos los campos del formulario?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, limpiar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#form_departamento')[0].reset();
                        $('#codigo_departamento').val('');
                        $('#nombre').val('');
                        $('#descripcion').val('');
                        $('#id_agencia').val('');
                        $('#num_empleados').val('0');
                        $('#estado_activo').prop('checked', true);

                        actualizarResumenDepartamento();

                        Swal.fire({
                            icon: 'success',
                            title: 'Formulario limpiado',
                            text: 'Todos los campos han sido restablecidos.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                });
            }

            // Función para editar departamento
            function editarDepartamento(id) {
                if (!id || id <= 0) {
                    Swal.fire('Error', 'ID de departamento inválido', 'error');
                    return;
                }

                loaderefect(1);

                $.ajax({
                    url: "../../../src/cruds/crud_empleado.php",
                    type: "POST",
                    data: {
                        condi: "get_departamento_by_id",
                        id: id
                    },
                    dataType: "json",
                    success: function(response) {
                        loaderefect(0);

                        if (response.status === 1 && response.data) {
                            const departamento = response.data;
                            mostrarModalEditar(departamento);
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: response.message || 'No se pudo cargar el departamento'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        loaderefect(0);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexión',
                            text: 'No se pudo cargar el departamento'
                        });
                    }
                });
            }

            // Función para eliminar departamento
            function eliminarDepartamento(id) {
                if (departamentoEditando === id) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Departamento en edición',
                        text: 'Termina o cancela la edición antes de eliminar',
                        timer: 3000,
                        showConfirmButton: false
                    });
                    return;
                }

                Swal.fire({
                    title: '¿Eliminar departamento?',
                    html: `
            <div class="text-left">
                <p class="mb-3">Esta acción eliminará el departamento del sistema.</p>
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    <i class="fas fa-exclamation-triangle text-yellow-500 mr-1"></i>
                    Esta acción no se puede deshacer.
                </p>
            </div>
        `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return $.ajax({
                            url: "../../../src/cruds/crud_empleado.php",
                            type: "POST",
                            data: {
                                condi: "delete_departamento",
                                id: id
                            },
                            dataType: "json"
                        }).then(function(response) {
                            if (response.status !== 1) {
                                throw new Error(response.message || 'Error al eliminar');
                            }
                            return response;
                        }).catch(function(error) {
                            Swal.showValidationMessage(`Error: ${error.message}`);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Eliminado!',
                            text: 'El departamento ha sido eliminado correctamente.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        cargarTablaDepartamentos();
                    }
                });
            }


            function cargarDepartamentos() {
    $.ajax({
        url: "../../../src/cruds/crud_empleado.php",
        type: "POST",
        data: { condi: "get_departamentos_activos" },
        dataType: "json",
        success: function(response) {
            if (response.status == 1) {
                const select = $('#departamento');
                select.empty();
                select.append('<option value="">Seleccione departamento...</option>');
                
                response.data.forEach(function(depto) {
                    select.append('<option value="' + depto.id + '">' + depto.nombre + '</option>');
                });
            }
        },
        error: function() {
            console.error("Error al cargar departamentos");
        }
    });
}

function cargarAgencias() {
    $.ajax({
        url: "../../../src/cruds/crud_empleado.php",
        type: "POST",
        data: { condi: "get_agencias_activas" },
        dataType: "json",
        success: function(response) {
            if (response.status == 1) {
                // Agregar campo oculto para id_agencia si no existe
                if ($('#id_agencia').length === 0) {
                    $('#form_empleado').append('<input type="hidden" id="id_agencia" name="id_agencia" value="' + response.data[0].id + '">');
                }
            }
        },
        error: function() {
            console.error("Error al cargar agencias");
        }
    });
}

function cargarFrecuencias() {
    $.ajax({
        url: "../../../src/cruds/crud_empleado.php",
        type: "POST",
        data: { condi: "get_frecuencias_activas" },
        dataType: "json",
        success: function(response) {
            if (response.status == 1) {
                const select = $('#frecuencia_pago');
                select.empty();
                select.append('<option value="">Seleccione frecuencia...</option>');
                
                response.data.forEach(function(frecuencia) {
                    select.append('<option value="' + frecuencia.id + '">' + frecuencia.nombre + ' (' + frecuencia.pagos_mes + ' pagos/mes)</option>');
                });
            }
        },
        error: function() {
            console.error("Error al cargar frecuencias");
        }
    });
}

function crearEmpleado() {
    // Validaciones básicas
    const clienteSelect = $('#clienteSelect').val();
    const puesto = $('#puesto').val();
    const departamento = $('#departamento').val();
    const fecha_ingreso = $('#fecha_ingreso').val();
    const sueldo_base = $('#sueldo_base').val();
    const frecuencia_pago = $('#frecuencia_pago').val();
    const tipo_moneda = $('#tipo_moneda').val();
    const agencia = $('#agencia').val();

    // Validaciones...
    
    // Obtener datos del formulario
    const formData = {
        condi: 'create_empleado',
        id_cliente: clienteSelect,
        puesto: puesto,
        departamento: departamento,
        agencia: agencia,
        fecha_ingreso: fecha_ingreso,
        sueldo_base: sueldo_base,
        frecuencia_pago: frecuencia_pago,
        tipo_moneda: tipo_moneda,
        // Solo beneficios de ley (sin duplicados)
        beneficio_bono14: $('#beneficio_bono14').is(':checked') ? '1' : '0',
        beneficio_aguinaldo: $('#beneficio_aguinaldo').is(':checked') ? '1' : '0',
        beneficio_igss: $('#beneficio_igss').is(':checked') ? '1' : '0',
        beneficio_irtra: $('#beneficio_irtra').is(':checked') ? '1' : '0',
        beneficio_intecap: $('#beneficio_intecap').is(':checked') ? '1' : '0',
        beneficio_vacaciones: $('#beneficio_vacaciones').is(':checked') ? '1' : '0',
        // Beneficios de empresa
        beneficio_seguro: $('#beneficio_seguro').is(':checked') ? '1' : '0',
        beneficio_pension: $('#beneficio_pension').is(':checked') ? '1' : '0',
        beneficio_productividad: $('#beneficio_productividad').is(':checked') ? '1' : '0',
        beneficio_capacitacion: $('#beneficio_capacitacion').is(':checked') ? '1' : '0',
        beneficio_despensa: $('#beneficio_despensa').is(':checked') ? '1' : '0',
        beneficio_otros: $('#beneficio_otros').is(':checked') ? '1' : '0',
        // Otros campos
        indemnizacion: $('#indemnizacion').is(':checked') ? '1' : '0',
        prestaciones: $('#prestaciones').is(':checked') ? '1' : '0',
        observaciones: $('#observaciones').val()
    };
    
    // Resto del código AJAX para crear empleado...
}

function editarEmpleado(id) {
    // Obtener datos del empleado
    $.ajax({
        url: "../../../src/cruds/crud_empleado.php",
        type: "POST",
        data: { 
            condi: "get_empleado_by_id",
            id: id
        },
        dataType: "json",
        success: function(response) {
            if (response.status == 1) {
                // Mostrar modal de edición
                mostrarModalEdicion(response.data);
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: response.message
                });
            }
        }
    });
}

function eliminarEmpleado(id) {
    Swal.fire({
        title: '¿Desactivar empleado?',
        text: 'El empleado será marcado como INACTIVO pero no se eliminará permanentemente.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Sí, desactivar',
        cancelButtonText: 'Cancelar',
        input: 'text',
        inputLabel: 'Motivo de desactivación:',
        inputPlaceholder: 'Opcional',
        inputValidator: (value) => {
            // No es requerido
        }
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: "../../../src/cruds/crud_empleado.php",
                type: "POST",
                data: {
                    condi: "delete_empleado",
                    id: id,
                    motivo: result.value || 'Desactivado por usuario'
                },
                dataType: "json",
                success: function(response) {
                    if (response.status == 1) {
                        Swal.fire(
                            '¡Desactivado!',
                            response.message,
                            'success'
                        );
                        cargarTablaEmpleados();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message
                        });
                    }
                }
            });
        }
    });
}

            // Inicializar cuando el documento esté listo
            $(document).ready(function() {
                // Cargar agencias via AJAX
                cargarAgencias();

                // Actualizar resumen en tiempo real
                $('#codigo_departamento, #nombre, #num_empleados, input[name="estado"]').on('input change', function() {
                    actualizarResumenDepartamento();
                });

                // Inicializar resumen
                actualizarResumenDepartamento();

                // Cargar tabla de departamentos
                cargarTablaDepartamentos();
                cargarDepartamentos();
                cargarAgencias();
                cargarFrecuencias();
            });
        </script>
        <style>
            /* Estilos para inputs de edición */
            .edit-input {
                transition: all 0.2s ease;
                font-size: 0.875rem;
                line-height: 1.25rem;
            }

           

            .edit-input[data-field="codigo_departamento"] {
                font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
                font-weight: bold;
            }

            .edit-error {
                animation: fadeIn 0.3s ease;
            }

            @keyframes fadeIn {
                from {
                    opacity: 0;
                    transform: translateY(-5px);
                }

                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Transición suave al cambiar entre modos */
            tr {
                transition: background-color 0.3s ease;
            }

            tr.editing-mode {
                background-color: rgba(99, 102, 241, 0.05) !important;
                box-shadow: inset 0 0 0 2px rgba(99, 102, 241, 0.2);
            }
        </style>
    <?php
        break;

    case 'create_agencia':
    ?>
        <div class="px-6 py-4 lg:px-8 space-y-8 dark:bg-gray-900">
            <!-- Card: Creación de Agencia -->
            <div class="bg-white rounded-2xl shadow-md overflow-hidden dark:bg-gray-800 dark:border-gray-700">
                <div class="px-6 py-4 bg-gradient-to-r from-blue-600 to-indigo-700 dark:from-blue-800 dark:to-indigo-900">
                    <h5 class="text-lg font-semibold text-white">
                        <i class="fas fa-building mr-2"></i>Crear Nueva Agencia
                    </h5>
                </div>
                <div class="p-6">
                    <form id="form_agencia">
                        <!-- Campos ocultos -->
                        <input type="hidden" id="condi" name="condi" value="add_agencia">
                        <input type="hidden" id="file" name="file" value="view_agencia">

                        <!-- Sección 1: Información Básica -->
                        <div class="mb-8">
                            <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                <i class="fas fa-info-circle mr-2 text-blue-500"></i>Información Básica
                            </h6>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Código de Agencia -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="codigo_agencia" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Código de Agencia <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="codigo_agencia"
                                        name="codigo_agencia"
                                        placeholder="Ej: AG001, SUC001"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        required
                                        maxlength="20">
                                    <small class="text-gray-500 dark:text-gray-400 mt-1 block">
                                        Código único en mayúsculas sin espacios
                                    </small>
                                </div>

                                <!-- Nombre -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="nombre_agencia" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Nombre de la Agencia <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        id="nombre_agencia"
                                        name="nombre_agencia"
                                        placeholder="Ej: Sucursal Central, Agencia Norte"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        required
                                        maxlength="100">
                                </div>
                            </div>
                        </div>

                        <!-- Sección 2: Ubicación -->
                        <div class="mb-8">
                            <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                <i class="fas fa-map-marker-alt mr-2 text-green-500"></i>Ubicación
                            </h6>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Dirección -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="direccion" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Dirección Completa <span class="text-red-500">*</span>
                                    </label>
                                    <textarea
                                        id="direccion"
                                        name="direccion"
                                        rows="2"
                                        placeholder="Ej: 7a Avenida 1-23, Zona 1"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        required></textarea>
                                </div>

                                <!-- Ciudad -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="ciudad" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Ciudad
                                    </label>
                                    <input
                                        type="text"
                                        id="ciudad"
                                        name="ciudad"
                                        placeholder="Ej: Ciudad de Guatemala"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        maxlength="50">
                                </div>

                                <!-- Departamento -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="departamento" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Departamento/Provincia
                                    </label>
                                    <input
                                        type="text"
                                        id="departamento"
                                        name="departamento"
                                        placeholder="Ej: Guatemala"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        maxlength="50">
                                </div>

                                <!-- Código Postal -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="codigo_postal" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Código Postal
                                    </label>
                                    <input
                                        type="text"
                                        id="codigo_postal"
                                        name="codigo_postal"
                                        placeholder="Ej: 01001"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        maxlength="10">
                                </div>
                            </div>
                        </div>

                        <!-- Sección 3: Contacto -->
                        <div class="mb-8">
                            <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                <i class="fas fa-address-book mr-2 text-purple-500"></i>Contacto
                            </h6>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Teléfono -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="telefono" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Teléfono
                                    </label>
                                    <div class="relative">
                                        <input
                                            type="text"
                                            id="telefono"
                                            name="telefono"
                                            placeholder="Ej: 12345678"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                            maxlength="20">
                                    </div>
                                </div>

                                <!-- Email -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="email" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Correo Electrónico
                                    </label>
                                    <div class="relative">
                                        <input
                                            type="email"
                                            id="email"
                                            name="email"
                                            placeholder="Ej: agencia@empresa.com"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                            maxlength="100">
                                    </div>
                                </div>

                                <!-- Responsable -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600 md:col-span-2">
                                    <label for="responsable" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Responsable
                                    </label>
                                    <input
                                        type="text"
                                        id="responsable"
                                        name="responsable"
                                        placeholder="Ej: Juan Pérez (Gerente)"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        maxlength="100">
                                </div>
                            </div>
                        </div>

                        <!-- Sección 4: Información Adicional -->
                        <div class="mb-8">
                            <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                <i class="fas fa-chart-line mr-2 text-yellow-500"></i>Información Adicional
                            </h6>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Presupuesto Anual -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="presupuesto_anual" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Presupuesto Anual (Q)
                                    </label>
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">Q</span>
                                        </div>
                                        <input
                                            type="number"
                                            id="presupuesto_anual"
                                            name="presupuesto_anual"
                                            min="0"
                                            step="0.01"
                                            placeholder="0.00"
                                            class="pl-10 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                    </div>
                                </div>

                                <!-- Número de Empleados -->
                                <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                    <label for="num_empleados" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                        Número de Empleados
                                    </label>
                                    <div class="relative">
                                        <input
                                            type="number"
                                            id="num_empleados"
                                            name="num_empleados"
                                            min="0"
                                            value="0"
                                            class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white">
                                        <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500">empleados</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 5: Estado -->
                        <div class="mb-8">
                            <h6 class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-4 pb-2 border-b border-gray-200 dark:border-gray-700">
                                <i class="fas fa-toggle-on mr-2 text-red-500"></i>Estado
                            </h6>

                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <div class="flex items-center space-x-6">
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            id="estado_activa"
                                            name="estado"
                                            value="ACTIVA"
                                            checked
                                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 dark:bg-gray-600 dark:border-gray-500">
                                        <label for="estado_activa" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <i class="fas fa-check-circle mr-1"></i> Activa
                                            </span>
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            id="estado_inactiva"
                                            name="estado"
                                            value="INACTIVA"
                                            class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 dark:bg-gray-600 dark:border-gray-500">
                                        <label for="estado_inactiva" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                <i class="fas fa-times-circle mr-1"></i> Inactiva
                                            </span>
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            id="estado_suspendida"
                                            name="estado"
                                            value="SUSPENDIDA"
                                            class="h-4 w-4 text-yellow-600 focus:ring-yellow-500 border-gray-300 dark:bg-gray-600 dark:border-gray-500">
                                        <label for="estado_suspendida" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                <i class="fas fa-pause-circle mr-1"></i> Suspendida
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div id="resumenAgencia" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                            <h6 class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-3">
                                <i class="fas fa-info-circle mr-1"></i>Resumen de la Agencia
                            </h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Código:</span>
                                        <span id="res_codigo" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Nombre:</span>
                                        <span id="res_nombre" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Ubicación:</span>
                                        <span id="res_ubicacion" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Contacto:</span>
                                        <span id="res_contacto" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Presupuesto:</span>
                                        <span id="res_presupuesto" class="text-sm font-medium text-gray-800 dark:text-gray-200">Q 0.00</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Estado:</span>
                                        <span id="res_estado" class="text-sm font-medium text-gray-800 dark:text-gray-200">Activa</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
                            <button
                                type="button"
                                onclick="crearAgencia()"
                                class="inline-flex items-center px-8 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:from-blue-700 dark:to-indigo-700 dark:hover:from-blue-800 dark:hover:to-indigo-800 w-full sm:w-auto justify-center">
                                <i class="fa fa-save mr-2"></i> Guardar Agencia
                            </button>
                            <button
                                type="button"
                                onclick="limpiarFormAgencia()"
                                class="inline-flex items-center px-8 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 w-full sm:w-auto justify-center">
                                <i class="fa fa-eraser mr-2"></i> Limpiar
                            </button>
                            <button
                                type="button"
                                onclick="salirAgencia()"
                                class="inline-flex items-center px-8 py-3 border border-red-300 text-base font-medium rounded-md shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-gray-800 dark:text-red-400 dark:border-red-600 dark:hover:bg-red-900/20 w-full sm:w-auto justify-center">
                                <i class="fa fa-ban mr-2"></i> Cancelar
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla: Agencias Registradas -->
            <div class="border border-gray-200 rounded-lg shadow-sm bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Agencias Registradas</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Lista de todas las agencias/sucursales del sistema
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                onclick="cargarTablaAgencias()"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                title="Actualizar tabla">
                                <i class="fas fa-sync-alt mr-1"></i> Actualizar
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="tabla_agencias" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Ubicación</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Contacto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Presupuesto</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Empleados</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Creado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Actualizar resumen en tiempo real
                $('#codigo_agencia, #nombre_agencia, #direccion, #ciudad, #telefono, #email, #presupuesto_anual, #num_empleados, input[name="estado"]').on('input change', function() {
                    actualizarResumenAgencia();
                });

                // Inicializar resumen
                actualizarResumenAgencia();

                // Cargar tabla de agencias
                cargarTablaAgencias();
            });

            function actualizarResumenAgencia() {
                const codigo = $('#codigo_agencia').val() || '-';
                const nombre = $('#nombre_agencia').val() || '-';
                const direccion = $('#direccion').val() || '-';
                const ciudad = $('#ciudad').val() || 'Sin ciudad';
                const telefono = $('#telefono').val() || 'Sin teléfono';
                const email = $('#email').val() || 'Sin email';
                const presupuesto = $('#presupuesto_anual').val() ? 'Q ' + parseFloat($('#presupuesto_anual').val()).toLocaleString('es-GT', {
                    minimumFractionDigits: 2
                }) : 'Q 0.00';
                const estado = $('input[name="estado"]:checked').val() || 'ACTIVA';

                $('#res_codigo').text(codigo);
                $('#res_nombre').text(nombre);
                $('#res_ubicacion').text(ciudad + ', ' + direccion.substring(0, 30) + (direccion.length > 30 ? '...' : ''));
                $('#res_contacto').text(telefono + ' / ' + email);
                $('#res_presupuesto').text(presupuesto);

                // Estado con color
                let estadoClass = '';
                switch (estado) {
                    case 'ACTIVA':
                        estadoClass = 'text-green-600 dark:text-green-400';
                        break;
                    case 'INACTIVA':
                        estadoClass = 'text-red-600 dark:text-red-400';
                        break;
                    case 'SUSPENDIDA':
                        estadoClass = 'text-yellow-600 dark:text-yellow-400';
                        break;
                }
                $('#res_estado').attr('class', 'text-sm font-medium ' + estadoClass).text(estado.charAt(0).toUpperCase() + estado.slice(1).toLowerCase());

                // Mostrar u ocultar resumen
                if (codigo !== '-' || nombre !== '-') {
                    $('#resumenAgencia').removeClass('hidden');
                } else {
                    $('#resumenAgencia').addClass('hidden');
                }
            }

            function cargarTablaAgencias() {
                if ($.fn.DataTable.isDataTable('#tabla_agencias')) {
                    $('#tabla_agencias').DataTable().destroy();
                }

                $('#tabla_agencias').DataTable({
                    ajax: {
                        url: "../../../src/cruds/crud_empleado.php",
                        type: "POST",
                        data: {
                            condi: "list_agencias"
                        },
                        dataSrc: function(json) {
                            if (json.error) {
                                Swal.fire({
                                    icon: 'error',
                                    title: '¡ERROR!',
                                    text: json.message
                                });
                                return [];
                            }
                            return json.data;
                        }
                    },
                    columns: [{
                            data: null,
                            render: function(data, type, row, meta) {
                                return meta.row + 1;
                            },
                            className: "text-center"
                        },
                        {
                            data: "codigo_agencia",
                            render: function(data) {
                                return `<span class="font-mono font-bold text-blue-600 dark:text-blue-400">${data}</span>`;
                            }
                        },
                        {
                            data: "nombre",
                            render: function(data) {
                                return `<span class="font-medium">${data}</span>`;
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                const ubicacion = [row.ciudad, row.direccion].filter(Boolean).join(', ');
                                return ubicacion ? `<span class="text-sm text-gray-600 dark:text-gray-400">${ubicacion.substring(0, 40)}${ubicacion.length > 40 ? '...' : ''}</span>` : '<span class="text-gray-400">-</span>';
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                const contacto = [row.telefono, row.email].filter(Boolean).join(' / ');
                                return contacto ? `<span class="text-sm">${contacto}</span>` : '<span class="text-gray-400">-</span>';
                            }
                        },
                        {
                            data: "presupuesto_anual",
                            render: function(data) {
                                if (!data || data == '0.00') return '<span class="text-gray-400">-</span>';
                                return `<span class="px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 rounded">Q ${parseFloat(data).toLocaleString('es-GT', {minimumFractionDigits: 2})}</span>`;
                            },
                            className: "text-center"
                        },
                        {
                            data: "num_empleados",
                            render: function(data) {
                                return data ? `<span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">${data} </span>` : '<span class="text-gray-400">0</span>';
                            },
                            className: "text-center"
                        },
                        {
                            data: "estado",
                            render: function(data) {
                                switch (data) {
                                    case 'ACTIVA':
                                        return '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-200">Activa</span>';
                                    case 'INACTIVA':
                                        return '<span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full dark:bg-red-900 dark:text-red-200">Inactiva</span>';
                                    case 'SUSPENDIDA':
                                        return '<span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded-full dark:bg-yellow-900 dark:text-yellow-200">Suspendida</span>';
                                    default:
                                        return '<span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded-full dark:bg-gray-700 dark:text-gray-300">Desconocido</span>';
                                }
                            },
                            className: "text-center"
                        },
                        {
                            data: "created_at",
                            render: function(data) {
                                return data ? new Date(data).toLocaleDateString('es-GT') : '';
                            },
                            className: "text-center"
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `
                        <div class="flex space-x-2 justify-center">
                            <button onclick="editarAgencia(${row.id})" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="eliminarAgencia(${row.id})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                            },
                            orderable: false,
                            className: "text-center"
                        }
                    ],

                    pageLength: 10,
                    responsive: true,
                    order: [
                        [1, 'asc']
                    ],
                    language: {
                        search: "Buscar:",
                        lengthMenu: "Mostrar _MENU_ registros",
                        info: "Mostrando _START_ a _END_ de _TOTAL_ agencias",
                        infoEmpty: "No hay agencias registradas",
                        zeroRecords: "No se encontraron agencias",
                        paginate: {
                            first: "Primero",
                            last: "Último",
                            next: "Siguiente",
                            previous: "Anterior"
                        }
                    }
                });
            }

            function crearAgencia() {
                const codigo_agencia = $('#codigo_agencia').val().trim().toUpperCase();
                const nombre_agencia = $('#nombre_agencia').val().trim();
                const direccion = $('#direccion').val().trim();
                const telefono = $('#telefono').val().trim();
                const email = $('#email').val().trim();
                const responsable = $('#responsable').val().trim();
                const ciudad = $('#ciudad').val().trim();
                const departamento = $('#departamento').val().trim();
                const codigo_postal = $('#codigo_postal').val().trim();
                const presupuesto_anual = $('#presupuesto_anual').val() || '0.00';
                const num_empleados = $('#num_empleados').val() || '0';
                const estado = $('input[name="estado"]:checked').val();

                // Validaciones básicas
                if (!codigo_agencia) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Código requerido',
                        text: 'Por favor ingrese un código para la agencia.'
                    });
                    $('#codigo_agencia').focus();
                    return;
                }

                if (!nombre_agencia) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Nombre requerido',
                        text: 'Por favor ingrese un nombre para la agencia.'
                    });
                    $('#nombre_agencia').focus();
                    return;
                }

                if (!direccion) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Dirección requerida',
                        text: 'Por favor ingrese una dirección para la agencia.'
                    });
                    $('#direccion').focus();
                    return;
                }

                // Validar formato del código
                const codigoRegex = /^[A-Z0-9_]+$/;
                if (!codigoRegex.test(codigo_agencia)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Formato inválido',
                        text: 'El código debe contener solo letras mayúsculas, números y guiones bajos.'
                    });
                    $('#codigo_agencia').focus();
                    return;
                }

                // Validar email si se proporciona
                if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Email inválido',
                        text: 'Por favor ingrese un correo electrónico válido.'
                    });
                    $('#email').focus();
                    return;
                }

                // Preparar datos para enviar
                const formData = new FormData();
                formData.append('condi', 'add_agencia');
                formData.append('codigo_agencia', codigo_agencia);
                formData.append('nombre_agencia', nombre_agencia);
                formData.append('direccion', direccion);
                formData.append('telefono', telefono);
                formData.append('email', email);
                formData.append('responsable', responsable);
                formData.append('ciudad', ciudad);
                formData.append('departamento', departamento);
                formData.append('codigo_postal', codigo_postal);
                formData.append('presupuesto_anual', presupuesto_anual);
                formData.append('num_empleados', num_empleados);
                formData.append('estado', estado);

                // Confirmar creación
                Swal.fire({
                    title: '¿Crear agencia?',
                    html: `
            <div class="text-left">
                <p class="mb-3">¿Está seguro de crear la siguiente agencia?</p>
                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><strong>Código:</strong></div><div>${codigo_agencia}</div>
                        <div><strong>Nombre:</strong></div><div>${nombre_agencia}</div>
                        <div><strong>Dirección:</strong></div><div>${direccion.substring(0, 50)}${direccion.length > 50 ? '...' : ''}</div>
                        <div><strong>Ciudad:</strong></div><div>${ciudad || 'No especificada'}</div>
                        <div><strong>Teléfono:</strong></div><div>${telefono || 'No especificado'}</div>
                        <div><strong>Email:</strong></div><div>${email || 'No especificado'}</div>
                        <div><strong>Presupuesto:</strong></div><div>Q ${parseFloat(presupuesto_anual).toLocaleString('es-GT', {minimumFractionDigits: 2})}</div>
                        <div><strong>Estado:</strong></div><div>${estado === 'ACTIVA' ? 'Activa' : estado === 'INACTIVA' ? 'Inactiva' : 'Suspendida'}</div>
                    </div>
                </div>
            </div>
        `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, crear',
                    cancelButtonText: 'Cancelar',
                    width: '600px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Enviar datos via AJAX
                        $.ajax({
                            url: "../../../src/cruds/crud_empleado.php",
                            type: "POST",
                            data: formData,
                            dataType: "json",
                            contentType: false,
                            processData: false,
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            success: function(response) {
                                console.log("Respuesta recibida:", response);

                                if (response.status == 1 || response.success === true) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Éxito!',
                                        text: response.message || 'Agencia creada exitosamente',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });

                                    // Recargar tabla y limpiar formulario después de 2 segundos
                                    setTimeout(() => {
                                        cargarTablaAgencias();
                                        limpiarFormAgencia();
                                    }, 2000);
                                } else {
                                    Swal.fire({
                                        icon: response.type || 'error',
                                        title: response.type === 'warning' ? 'Advertencia' : 'Error',
                                        text: response.message || 'Error desconocido'
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', {
                                    status: status,
                                    error: error,
                                    responseText: xhr.responseText,
                                    statusCode: xhr.status
                                });

                                let errorMessage = 'No se pudo conectar con el servidor.';

                                if (xhr.responseText) {
                                    try {
                                        const errorResponse = JSON.parse(xhr.responseText);
                                        errorMessage = errorResponse.message || errorResponse.error || xhr.responseText.substring(0, 100);
                                    } catch (e) {
                                        errorMessage = xhr.responseText.substring(0, 150);
                                    }
                                }

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de conexión',
                                    html: `<div class="text-left">
                            <p><strong>Error:</strong> ${error}</p>
                            <p><strong>Detalles:</strong> ${errorMessage}</p>
                        </div>`
                                });
                            },
                            complete: function() {
                                loaderefect(0);
                            }
                        });
                    }
                });
            }

            function limpiarFormAgencia() {
                Swal.fire({
                    title: '¿Limpiar formulario?',
                    text: '¿Está seguro de limpiar todos los campos del formulario?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, limpiar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#form_agencia')[0].reset();
                        $('#codigo_agencia').val('');
                        $('#nombre_agencia').val('');
                        $('#direccion').val('');
                        $('#telefono').val('');
                        $('#email').val('');
                        $('#responsable').val('');
                        $('#ciudad').val('');
                        $('#departamento').val('');
                        $('#codigo_postal').val('');
                        $('#presupuesto_anual').val('');
                        $('#num_empleados').val('0');
                        $('#estado_activa').prop('checked', true);

                        actualizarResumenAgencia();

                        Swal.fire({
                            icon: 'success',
                            title: 'Formulario limpiado',
                            text: 'Todos los campos han sido restablecidos.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                });
            }

            function editarAgencia(id) {
                Swal.fire({
                    title: 'Editar Agencia',
                    html: `
            <div class="text-center py-4">
                <i class="fas fa-edit text-4xl text-blue-500 mb-3"></i>
                <p class="text-gray-600 dark:text-gray-400">
                    Esta función está en desarrollo.<br>
                    Próximamente podrá editar las agencias.
                </p>
            </div>
        `,
                    icon: 'info',
                    confirmButtonText: 'Entendido'
                });
            }

            function eliminarAgencia(id) {
                Swal.fire({
                    title: '¿Eliminar agencia?',
                    text: 'Esta acción no se puede deshacer. ¿Está seguro?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return $.ajax({
                            url: "../../../src/cruds/crud_empleado.php",
                            type: "POST",
                            data: {
                                condi: "delete_agencia",
                                id: id
                            }
                        }).then(function(response) {
                            try {
                                const data = JSON.parse(response);
                                if (data.status != 1) {
                                    throw new Error(data.message || 'Error al eliminar');
                                }
                                return data;
                            } catch (e) {
                                throw new Error('Error al procesar respuesta');
                            }
                        }).catch(function(error) {
                            Swal.showValidationMessage(`Error: ${error.statusText || error.message}`);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: '¡Eliminado!',
                            text: 'La agencia ha sido eliminada correctamente.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        cargarTablaAgencias();
                    }
                });
            }

            function salirAgencia() {
                Swal.fire({
                    title: '¿Salir?',
                    text: '¿Está seguro de que desea salir? Los cambios no guardados se perderán.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, salir',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        history.back();
                    }
                });
            }
        </script>
    <?php
        break;
    case 'create_frecuencia':
    ?>
        <div class="px-6 py-4 lg:px-8 space-y-8 dark:bg-gray-900">
            <!-- Card: Creación de Frecuencia de Pago -->
            <div class="bg-white rounded-2xl shadow-md overflow-hidden dark:bg-gray-800 dark:border-gray-700">
                <div class="px-6 py-4 bg-indigo-600 dark:bg-indigo-700">
                    <h5 class="text-lg font-semibold text-white">
                        <i class="fas fa-calendar-alt mr-2"></i>Crear Frecuencia de Pago
                    </h5>
                </div>
                <div class="p-6">
                    <form id="form_frecuencia">
                        <!-- Campos ocultos -->
                        <input type="hidden" id="condi" name="condi" value="add_frecuencia">
                        <input type="hidden" id="file" name="file" value="view001">

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Código de Frecuencia -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="codigo_frecuencia" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Código de Frecuencia <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="codigo_frecuencia"
                                    name="codigo_frecuencia"
                                    placeholder="Ej: MENSUAL, QUINCENAL"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                    required
                                    maxlength="20">
                                <small class="text-gray-500 dark:text-gray-400 mt-1 block">
                                    Código único en mayúsculas sin espacios
                                </small>
                            </div>

                            <!-- Nombre -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="nombre" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Nombre Descriptivo <span class="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    id="nombre"
                                    name="nombre"
                                    placeholder="Ej: Mensual, Quincenal, Semanal"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                    required
                                    maxlength="50">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <!-- Días -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="dias" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Número de Días <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        id="dias"
                                        name="dias"
                                        min="1"
                                        max="365"
                                        value="30"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">días</span>
                                    </div>
                                </div>
                                <small class="text-gray-500 dark:text-gray-400 mt-1 block">
                                    Ej: 30 (mensual), 15 (quincenal), 7 (semanal)
                                </small>
                            </div>

                            <!-- Pagos por Mes -->
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="pagos_mes" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Pagos por Mes <span class="text-red-500">*</span>
                                </label>
                                <div class="relative">
                                    <input
                                        type="number"
                                        id="pagos_mes"
                                        name="pagos_mes"
                                        min="1"
                                        max="31"
                                        value="1"
                                        class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"
                                        required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">veces</span>
                                    </div>
                                </div>
                                <small class="text-gray-500 dark:text-gray-400 mt-1 block">
                                    Número de pagos que se realizan en un mes
                                </small>
                            </div>
                        </div>

                        <!-- Descripción -->
                        <div class="mb-6">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label for="descripcion" class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Descripción Detallada
                                </label>
                                <textarea
                                    id="descripcion"
                                    name="descripcion"
                                    rows="3"
                                    placeholder="Describa la frecuencia de pago, ej: 'Pago realizado una vez al mes, normalmente el último día hábil'"
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm dark:bg-gray-600 dark:border-gray-500 dark:text-white"></textarea>
                            </div>
                        </div>

                        <!-- Estado -->
                        <div class="mb-8">
                            <div class="bg-gray-50 rounded-lg p-4 dark:bg-gray-700 dark:border-gray-600">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-2">
                                    Estado de la Frecuencia <span class="text-red-500">*</span>
                                </label>
                                <div class="flex items-center space-x-4">
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            id="estado_activo"
                                            name="estado"
                                            value="ACTIVO"
                                            checked
                                            class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 dark:bg-gray-600 dark:border-gray-500">
                                        <label for="estado_activo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                <i class="fas fa-check-circle mr-1"></i> Activo
                                            </span>
                                        </label>
                                    </div>
                                    <div class="flex items-center">
                                        <input
                                            type="radio"
                                            id="estado_inactivo"
                                            name="estado"
                                            value="INACTIVO"
                                            class="h-4 w-4 text-red-600 focus:ring-red-500 border-gray-300 dark:bg-gray-600 dark:border-gray-500">
                                        <label for="estado_inactivo" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                <i class="fas fa-times-circle mr-1"></i> Inactivo
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Resumen -->
                        <div id="resumenFrecuencia" class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                            <h6 class="text-sm font-medium text-blue-700 dark:text-blue-300 mb-3">
                                <i class="fas fa-info-circle mr-1"></i>Resumen de la Frecuencia
                            </h6>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Código:</span>
                                        <span id="res_codigo" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Nombre:</span>
                                        <span id="res_nombre" class="text-sm font-medium text-gray-800 dark:text-gray-200">-</span>
                                    </div>
                                </div>
                                <div class="space-y-2">
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Período:</span>
                                        <span id="res_periodo" class="text-sm font-medium text-gray-800 dark:text-gray-200">- días</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">Pagos/Mes:</span>
                                        <span id="res_pagos_mes" class="text-sm font-medium text-gray-800 dark:text-gray-200">- veces</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Botones de Acción -->
                        <div class="flex flex-col sm:flex-row justify-center items-center space-y-4 sm:space-y-0 sm:space-x-6">
                            <button
                                type="button"
                                onclick="crearFrecuencia()"
                                class="inline-flex items-center px-8 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-gradient-to-r from-indigo-600 to-blue-600 hover:from-indigo-700 hover:to-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:from-indigo-700 dark:to-blue-700 dark:hover:from-indigo-800 dark:hover:to-blue-800 w-full sm:w-auto justify-center">
                                <i class="fa fa-save mr-2"></i> Guardar Frecuencia
                            </button>
                            <button
                                type="button"
                                onclick="limpiarFormFrecuencia()"
                                class="inline-flex items-center px-8 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700 w-full sm:w-auto justify-center">
                                <i class="fa fa-eraser mr-2"></i> Limpiar
                            </button>
                            <button
                                type="button"
                                onclick="salir()"
                                class="inline-flex items-center px-8 py-3 border border-red-300 text-base font-medium rounded-md shadow-sm text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 dark:bg-gray-800 dark:text-red-400 dark:border-red-600 dark:hover:bg-red-900/20 w-full sm:w-auto justify-center">
                                <i class="fa fa-ban mr-2"></i> Cancelar
                            </button>
                        </div>

                    </form>
                </div>
            </div>

            <!-- Tabla: Frecuencias Existentes -->
            <div class="border border-gray-200 rounded-lg shadow-sm bg-white dark:bg-gray-800 dark:border-gray-700">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Frecuencias de Pago Registradas</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                Lista de todas las frecuencias de pago disponibles en el sistema
                            </p>
                        </div>
                        <div class="flex space-x-2">
                            <button
                                onclick="cargarTablaFrecuencias()"
                                class="inline-flex items-center px-3 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700"
                                title="Actualizar tabla">
                                <i class="fas fa-sync-alt mr-1"></i> Actualizar
                            </button>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table id="tabla_frecuencias" class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-100 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Código</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Nombre</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Días</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Pagos/Mes</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Descripción</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Estado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Creado</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                // Actualizar resumen en tiempo real
                $('#codigo_frecuencia, #nombre, #dias, #pagos_mes').on('input', function() {
                    actualizarResumenFrecuencia();
                });

                // Inicializar resumen
                actualizarResumenFrecuencia();

                // Cargar tabla de frecuencias
                cargarTablaFrecuencias();
            });

            function actualizarResumenFrecuencia() {
                const codigo = $('#codigo_frecuencia').val() || '-';
                const nombre = $('#nombre').val() || '-';
                const dias = $('#dias').val() || '0';
                const pagosMes = $('#pagos_mes').val() || '0';

                $('#res_codigo').text(codigo);
                $('#res_nombre').text(nombre);
                $('#res_periodo').text(dias + ' días');
                $('#res_pagos_mes').text(pagosMes + ' veces');

                // Mostrar u ocultar resumen
                if (codigo !== '-' || nombre !== '-') {
                    $('#resumenFrecuencia').removeClass('hidden');
                } else {
                    $('#resumenFrecuencia').addClass('hidden');
                }
            }

            function cargarTablaFrecuencias() {
                if ($.fn.DataTable.isDataTable('#tabla_frecuencias')) {
                    $('#tabla_frecuencias').DataTable().destroy();
                }

                $('#tabla_frecuencias').DataTable({
                    ajax: {
                        url: "../../../src/cruds/crud_empleado.php",
                        type: "POST",
                        data: {
                            condi: "list_frecuencias"
                        },
                        dataSrc: function(json) {
                            if (json.error) {
                                Swal.fire({
                                    icon: 'error',
                                    title: '¡ERROR!',
                                    text: json.message
                                });
                                return [];
                            }
                            return json.data;
                        }
                    },
                    columns: [{
                            data: null,
                            render: function(data, type, row, meta) {
                                return meta.row + 1;
                            },
                            className: "text-center"
                        },
                        {
                            data: "codigo_frecuencia",
                            render: function(data) {
                                return `<span class="font-mono font-bold text-indigo-600 dark:text-indigo-400">${data}</span>`;
                            }
                        },
                        {
                            data: "nombre",
                            render: function(data) {
                                return `<span class="font-medium">${data}</span>`;
                            }
                        },
                        {
                            data: "dias",
                            render: function(data) {
                                return `<span class="px-2 py-1 bg-gray-100 dark:bg-gray-700 rounded text-center">${data} días</span>`;
                            },
                            className: "text-center"
                        },
                        {
                            data: "pagos_mes",
                            render: function(data) {
                                return `<span class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded">${data} veces</span>`;
                            },
                            className: "text-center"
                        },
                        {
                            data: "descripcion",
                            render: function(data) {
                                return data ? `<span class="text-sm text-gray-600 dark:text-gray-400">${data.substring(0, 50)}${data.length > 50 ? '...' : ''}</span>` : '<span class="text-gray-400">-</span>';
                            }
                        },
                        {
                            data: "estado",
                            render: function(data) {
                                if (data === 'ACTIVO') {
                                    return '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full dark:bg-green-900 dark:text-green-200">Activo</span>';
                                } else {
                                    return '<span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded-full dark:bg-red-900 dark:text-red-200">Inactivo</span>';
                                }
                            },
                            className: "text-center"
                        },
                        {
                            data: "created_at",
                            render: function(data) {
                                return data ? new Date(data).toLocaleDateString('es-GT') : '';
                            },
                            className: "text-center"
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                return `
                        <div class="flex space-x-2 justify-center">
                            <button onclick="editarFrecuencia(${row.id})" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300" title="Editar">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button onclick="eliminarFrecuencia(${row.id})" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                            },
                            orderable: false,
                            className: "text-center"
                        }
                    ],

                    pageLength: 10,
                    responsive: true,
                    order: [
                        [1, 'asc']
                    ]
                });
            }

            function crearFrecuencia() {
                const codigo_frecuencia = $('#codigo_frecuencia').val().trim().toUpperCase();
                const nombre = $('#nombre').val().trim();
                const dias = $('#dias').val();
                const pagos_mes = $('#pagos_mes').val();
                const descripcion = $('#descripcion').val().trim();
                const estado = $('input[name="estado"]:checked').val();

                // Validaciones (mantén las que ya tienes)
                if (!codigo_frecuencia) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Código requerido',
                        text: 'Por favor ingrese un código para la frecuencia.'
                    });
                    $('#codigo_frecuencia').focus();
                    return;
                }

                const codigoRegex = /^[A-Z0-9_]+$/;
                if (!codigoRegex.test(codigo_frecuencia)) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Formato inválido',
                        text: 'El código debe contener solo letras mayúsculas, números y guiones bajos.'
                    });
                    $('#codigo_frecuencia').focus();
                    return;
                }

                if (!nombre) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Nombre requerido',
                        text: 'Por favor ingrese un nombre para la frecuencia.'
                    });
                    $('#nombre').focus();
                    return;
                }

                if (!dias || dias < 1 || dias > 365) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Días inválidos',
                        text: 'Por favor ingrese un número de días válido (1-365).'
                    });
                    $('#dias').focus();
                    return;
                }

                if (!pagos_mes || pagos_mes < 1 || pagos_mes > 31) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Pagos inválidos',
                        text: 'Por favor ingrese un número de pagos por mes válido (1-31).'
                    });
                    $('#pagos_mes').focus();
                    return;
                }

                // Preparar datos para enviar
                const formData = new FormData();
                formData.append('condi', 'add_frecuencia');
                formData.append('codigo_frecuencia', codigo_frecuencia);
                formData.append('nombre', nombre);
                formData.append('dias', dias);
                formData.append('pagos_mes', pagos_mes);
                formData.append('descripcion', descripcion);
                formData.append('estado', estado);

                // Confirmar creación
                Swal.fire({
                    title: '¿Crear frecuencia de pago?',
                    html: `
            <div class="text-left">
                <p class="mb-3">¿Está seguro de crear la siguiente frecuencia de pago?</p>
                <div class="bg-gray-50 dark:bg-gray-700 p-3 rounded">
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><strong>Código:</strong></div><div>${codigo_frecuencia}</div>
                        <div><strong>Nombre:</strong></div><div>${nombre}</div>
                        <div><strong>Días:</strong></div><div>${dias} días</div>
                        <div><strong>Pagos/Mes:</strong></div><div>${pagos_mes} veces</div>
                        <div><strong>Estado:</strong></div><div>${estado === 'ACTIVO' ? 'Activo' : 'Inactivo'}</div>
                    </div>
                </div>
            </div>
        `,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, crear',
                    cancelButtonText: 'Cancelar',
                    width: '500px'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Enviar datos via AJAX
                        $.ajax({
                            url: "../../../src/cruds/crud_empleado.php",
                            type: "POST",
                            data: formData,
                            dataType: "json", // ESPECIFICA que esperas JSON
                            contentType: false, // Importante para FormData
                            processData: false, // Importante para FormData
                            beforeSend: function() {
                                loaderefect(1);
                            },
                            success: function(response) {
                                // ¡NO uses JSON.parse! jQuery ya lo hizo
                                console.log("Respuesta recibida:", response);

                                if (response.status == 1 || response.success === true) {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Éxito!',
                                        text: response.message || 'Frecuencia creada exitosamente',
                                        timer: 2000,
                                        showConfirmButton: false
                                    });

                                    // Recargar tabla y limpiar formulario después de 2 segundos
                                    setTimeout(() => {
                                        cargarTablaFrecuencias();
                                        limpiarFormFrecuencia();
                                    }, 2000);
                                } else {
                                    Swal.fire({
                                        icon: response.type || 'error',
                                        title: response.type === 'warning' ? 'Advertencia' : 'Error',
                                        text: response.message || 'Error desconocido'
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('AJAX Error:', {
                                    status: status,
                                    error: error,
                                    responseText: xhr.responseText,
                                    statusCode: xhr.status
                                });

                                // Si hay respuesta del servidor, intenta parsearla
                                let errorMessage = 'No se pudo conectar con el servidor.';

                                if (xhr.responseText) {
                                    try {
                                        const errorResponse = JSON.parse(xhr.responseText);
                                        errorMessage = errorResponse.message || errorResponse.error || xhr.responseText.substring(0, 100);
                                    } catch (e) {
                                        errorMessage = xhr.responseText.substring(0, 150);
                                    }
                                }

                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error de conexión',
                                    html: `<div class="text-left">
                            <p><strong>Error:</strong> ${error}</p>
                            <p><strong>Detalles:</strong> ${errorMessage}</p>
                        </div>`
                                });
                            },
                            complete: function() {
                                loaderefect(0);
                            }
                        });
                    }
                });
            }

            function limpiarFormFrecuencia() {
                Swal.fire({
                    title: '¿Limpiar formulario?',
                    text: '¿Está seguro de limpiar todos los campos del formulario?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, limpiar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $('#form_frecuencia')[0].reset();
                        $('#codigo_frecuencia').val('');
                        $('#nombre').val('');
                        $('#dias').val('30');
                        $('#pagos_mes').val('1');
                        $('#descripcion').val('');
                        $('#estado_activo').prop('checked', true);

                        actualizarResumenFrecuencia();

                        Swal.fire({
                            icon: 'success',
                            title: 'Formulario limpiado',
                            text: 'Todos los campos han sido restablecidos.',
                            timer: 1500,
                            showConfirmButton: false
                        });
                    }
                });
            }

            function editarFrecuencia(id) {
                // Aquí iría la lógica para cargar datos y editar
                Swal.fire({
                    title: 'Editar Frecuencia',
                    html: `
            <div class="text-center py-4">
                <i class="fas fa-edit text-4xl text-blue-500 mb-3"></i>
                <p class="text-gray-600 dark:text-gray-400">
                    Esta función está en desarrollo.<br>
                    Próximamente podrá editar las frecuencias de pago.
                </p>
            </div>
        `,
                    icon: 'info',
                    confirmButtonText: 'Entendido'
                });
            }

            function eliminarFrecuencia(id) {
                Swal.fire({
                    title: '¿Eliminar frecuencia?',
                    text: 'Esta acción no se puede deshacer. ¿Está seguro?',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar',
                    showLoaderOnConfirm: true,
                    preConfirm: () => {
                        return $.ajax({
                            url: "../../../src/cruds/crud_empleado.php",
                            type: "POST",
                            data: {
                                condi: "delete_frecuencia",
                                id: id
                            }
                        }).then(function(response) {
                            try {
                                const data = JSON.parse(response);
                                if (data.status != 1) {
                                    throw new Error(data.message || 'Error al eliminar');
                                }
                                return data;
                            } catch (e) {
                                throw new Error('Error al procesar respuesta');
                            }
                        }).catch(function(error) {
                            Swal.showValidationMessage(`Error: ${error.statusText || error.message}`);
                        });
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: '¡Eliminado!',
                            text: 'La frecuencia ha sido eliminada correctamente.',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        cargarTablaFrecuencias();
                    }
                });
            }

            function salir() {
                Swal.fire({
                    title: '¿Salir?',
                    text: '¿Está seguro de que desea salir? Los cambios no guardados se perderán.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Sí, salir',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        history.back();
                    }
                });
            }
        </script>
<?php
        break;




    default:
        echo "Condición no válida";
        break;
}
