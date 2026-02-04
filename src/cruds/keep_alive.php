<?php
require_once(__DIR__ . '/../../vendor/autoload.php');
use Micro\Helpers\Log;

session_start();

// Verificar que el request sea válido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// Verificar contenido
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['action'])) {
    http_response_code(400);
    exit;
}

$response = [];

switch ($input['action']) {
    case 'check_session':
        if (isset($_SESSION['id'])) {
            $tiempoRestante = ini_get('session.gc_maxlifetime');
            if (isset($_SESSION['ultimo_acceso'])) {
                $tiempoTranscurrido = time() - $_SESSION['ultimo_acceso'];
                $tiempoRestante = max(0, $tiempoRestante - $tiempoTranscurrido);
                // $tiempoRestante = 1000;
                // Log::info("Time remaining calculated", [
                //     'user_id' => $_SESSION['id'],
                //     'maxlifetime' => ini_get('session.gc_maxlifetime'),
                //     'time_remaining' => $tiempoRestante,
                //     'last_activity' => $_SESSION['ultimo_acceso'],
                //     'current_time' => time()
                // ]);
            }
            
            $response = [
                'status' => 'active',
                'time_remaining' => $tiempoRestante,
                'user_id' => $_SESSION['id'],
                'message' => 'Sesión activa'
            ];
        } else {
            $response = [
                'status' => 'expired',
                'message' => 'Sesión expirada'
            ];
        }

        // Log::info("Session checked", [
        //     'user_id' => $_SESSION['id'] ?? null,
        //     'status' => $response['status'],
        //     'time_remaining' => $tiempoRestante ?? null,
        //     'last_activity' => $_SESSION['ultimo_acceso'] ?? null
        // ]);
        break;
        
    case 'extend_session':
        if (isset($_SESSION['id'])) {
            $_SESSION['ultimo_acceso'] = time();
            // Regenerar ID de sesión por seguridad
            session_regenerate_id(true);
            
            $response = [
                'status' => 'extended',
                'message' => 'Sesión extendida exitosamente'
            ];
        } else {
            $response = [
                'status' => 'expired',
                'message' => 'No se puede extender, sesión ya expirada'
            ];
        }
        break;
        
    default:
        $response = [
            'status' => 'error',
            'message' => 'Acción no válida'
        ];
}

header('Content-Type: application/json');
echo json_encode($response);
?>