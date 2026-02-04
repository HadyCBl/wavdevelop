<?php

use Micro\Helpers\Log;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
    exit;
}

session_start();

if (!isset($_SESSION['id_agencia'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 0,
        'message' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente'
    ]);
    exit;
}

require_once __DIR__ . '/../../includes/Config/config.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

$condi = $_POST["condi"] ?? '';

switch ($condi) {
    case 'get_full_history':
        /**
         * Obtiene el historial completo de mensajes de un canal de Ably
         */
        $serial = $_POST['serial'] ?? '';
        $channel = $_POST['channel'] ?? '';
        
        $showmensaje = false;
        
        try {
            // Verificar configuración de Ably
            if (!isset($_ENV['ABLY_API_KEY'])) {
                throw new Exception("Ably no está configurado");
            }

            $ablyApiKey = $_ENV['ABLY_API_KEY'];
            
            // Crear instancia de Ably
            $ably = new \Ably\AblyRest($ablyApiKey);
            $ablyChannel = $ably->channel($channel);
            
            // Obtener historial completo (últimos 100 mensajes)
            $history = $ablyChannel->history([
                'limit' => 100,
                'direction' => 'backwards'
            ]);
            
            $messages = [];
            foreach ($history->items as $msg) {
                $messages[] = [
                    'name' => $msg->name,
                    'timestamp' => $msg->timestamp,
                    'data' => is_string($msg->data) 
                        ? json_decode($msg->data, true) 
                        : (is_object($msg->data) ? json_decode(json_encode($msg->data), true) : $msg->data)
                ];
            }
            
            Log::info("Historial de Ably obtenido", [
                'serial' => $serial,
                'channel' => $channel,
                'messages_count' => count($messages)
            ]);
            
            echo json_encode([
                'status' => 1,
                'message' => 'Historial obtenido correctamente',
                'messages' => $messages,
                'total' => count($messages)
            ]);
            
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error al obtener historial. Código: $codigoError";
            
            Log::error("Error obteniendo historial de Ably", [
                'error' => $e->getMessage(),
                'serial' => $serial,
                'channel' => $channel
            ]);
            
            echo json_encode([
                'status' => 0,
                'message' => $mensaje
            ]);
        }
        break;

    case 'test_channel_connection':
        /**
         * Prueba la conexión a un canal específico
         */
        $serial = $_POST['serial'] ?? '';
        
        try {
            if (!isset($_ENV['ABLY_API_KEY']) || !isset($_ENV['ABLY_CHANNEL_HUELLA'])) {
                throw new Exception("Ably no está configurado");
            }

            $channelPrefix = $_ENV['ABLY_CHANNEL_HUELLA'];
            $channelName = $channelPrefix . "_" . $serial;
            
            $ably = new \Ably\AblyRest($_ENV['ABLY_API_KEY']);
            $channel = $ably->channel($channelName);
            
            // Publicar mensaje de prueba
            $testMessage = [
                'tipo' => 'test',
                'timestamp' => time(),
                'mensaje' => 'Mensaje de prueba desde administrador'
            ];
            
            $channel->publish('test', $testMessage);
            
            Log::info("Mensaje de prueba enviado", [
                'channel' => $channelName,
                'serial' => $serial
            ]);
            
            echo json_encode([
                'status' => 1,
                'message' => 'Mensaje de prueba enviado correctamente',
                'channel' => $channelName
            ]);
            
        } catch (Exception $e) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            
            Log::error("Error en prueba de canal", [
                'error' => $e->getMessage(),
                'serial' => $serial
            ]);
            
            echo json_encode([
                'status' => 0,
                'message' => "Error en la prueba: " . $e->getMessage()
            ]);
        }
        break;

    default:
        echo json_encode([
            'status' => 0,
            'message' => 'Acción no válida'
        ]);
        break;
}
