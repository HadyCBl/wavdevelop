<?php
use Micro\Generic\AblyService;
use Micro\Helpers\Log;
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/Config/database.php';
// Log::info('Petición a AblyService para obtener configuración de app desktop', ['request' => $_GET]);
try {
    // Obtener configuración de AblyService
    $ablyConfig = AblyService::getInstance()->getAppDesktopConfig();
    // Log::info('Configuración obtenida', ['config' => $ablyConfig]);

    if (!isset($ablyConfig['clientKey']) || !isset($ablyConfig['channelPrefix'])) {
        throw new Exception('Configuración incompleta');
    }

    $config = [
        'clientKey' => $ablyConfig['clientKey'],
        'channelPrefix' => $ablyConfig['channelPrefix'],
    ];

    // Log::info('Configuración de AblyService obtenida correctamente', ['config' => $config]);

    // Validar parámetro
    $param = $_GET['param'] ?? '';

    if (!array_key_exists($param, $config)) {
        http_response_code(400);
        echo json_encode(['error' => 'Parámetro no válido']);
        exit;
    }

    // Registrar el acceso al parámetro solicitado
    // Log::info('Acceso al parámetro de configuración', ['param' => $param, 'value' => $config[$param]]);

    // Devolver el valor solicitado
    echo json_encode(['value' => $config[$param]]);
} catch (Exception $e) {
    Log::error('Error al obtener configuración de AblyService', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode(['error' => 'Error en el servidor', 'message' => $e->getMessage()]);
    exit;
}