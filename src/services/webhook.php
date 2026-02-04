<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // Para JWT y Dotenv

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Cargar variables de entorno
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

define('JWT_SECRET', $_ENV['JWT_SECRET'] ?? 'xxxx');  
define('JWT_ALGO', 'HS256');

header('Content-Type: application/json');

date_default_timezone_set('America/Guatemala');

class ApiAuth
{
    private static function getAuthHeader()
    {
        $headers = getallheaders();
        // Considerar variaciones en el nombre del header (Authorization, authorization)
        if (isset($headers['Authorization'])) {
            return str_replace('Bearer ', '', $headers['Authorization']);
        }
        if (isset($headers['authorization'])) { // Fallback para minúsculas
            return str_replace('Bearer ', '', $headers['authorization']);
        }
        return null;
    }

    public static function validateToken()
    {
        try {
            $token = self::getAuthHeader();
            if (!$token) {
                throw new Exception('No token provided');
            }

            JWT::$timestamp = time(); // Establecer el tiempo actual para la validación 'nbf' y 'iat'
            JWT::$leeway = 60; // Añadir una tolerancia de 60 segundos para 'nbf', 'iat' y 'exp'

            $decoded = JWT::decode($token, new Key(JWT_SECRET, JWT_ALGO));

            // Validar 'exp' (expiration time)
            if (isset($decoded->exp) && $decoded->exp < time()) {
                throw new Exception('Token expired');
            }
            
            // Validar 'iss' (issuer) si es necesario
            // if (!isset($decoded->iss) || $decoded->iss !== 'tu_emisor_esperado') {
            //     throw new Exception('Invalid token issuer');
            // }

            return true;
        } catch (Exception $e) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized', 'message' => $e->getMessage()]);
            exit; // Detener la ejecución después de enviar la respuesta de error
        }
    }

    public static function validateRequest()
    {
        // 1. Primero verificar si es un webhook de GitLab
        if (self::isGitLabWebhook()) {
            // Si es un webhook de GitLab válido, la autenticación es manejada por el token de GitLab
            return true;
        }

        // 2. Si no es GitLab, validar JWT (flujo normal para otras llamadas API)
        return self::validateToken(); // validateToken ya maneja la salida de error y exit
    }

    private static function isGitLabWebhook()
    {
        if (empty(JWT_SECRET)) {
            return false;
        }

        // Verificar headers específicos de GitLab
        $event = $_SERVER['HTTP_X_GITLAB_EVENT'] ?? null;
        $gitlabToken = $_SERVER['HTTP_X_GITLAB_TOKEN'] ?? null;

        if (!$event || !$gitlabToken) {
            return false;
        }

        if ($gitlabToken !== JWT_SECRET) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden', 'message' => 'Invalid GitLab token']);
            exit;
        }
        return true;
    }
}
ApiAuth::validateRequest();

$codigo = $_GET['codigo'] ?? '';

if (!$codigo) {
    http_response_code(400);
    echo json_encode(['error' => 'Bad Request', 'message' => 'Código no proporcionado']);
    exit;
}

// Es recomendable obtener la ruta del log desde una variable de entorno
$log_path = $_ENV['WEBHOOK_LOG_PATH'] ?? __DIR__.'/../../logs/errores.log'; 

if (!file_exists($log_path) || !is_readable($log_path)) {
    http_response_code(404);
    echo json_encode(['error' => 'Not Found', 'message' => 'Archivo de log no encontrado o no es legible. Ruta: ' . $log_path]);
    exit;
}

$resultados = [];
$lineas = file($log_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

if ($lineas === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Internal Server Error', 'message' => 'No se pudo leer el archivo de log.']);
    exit;
}

foreach ($lineas as $linea) {
    if (function_exists('str_contains')) {
        if (str_contains($linea, "Código: " . $codigo)) {
            $resultados[] = $linea; // Ya no se necesita trim si usas FILE_IGNORE_NEW_LINES
        }
    } else { // Fallback para PHP < 8
        if (strpos($linea, "Código: " . $codigo) !== false) {
            $resultados[] = $linea;
        }
    }
}
// if (empty($resultados)) {
//     http_response_code(404);
//     echo json_encode(['error' => 'Not Found', 'message' => 'No se encontraron resultados para el código proporcionado.']);
//     exit;
// }

echo json_encode([
    'codigo' => $codigo,
    'resultados' => $resultados
]);
