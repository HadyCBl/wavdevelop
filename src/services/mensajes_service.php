<?php

/**
 * Servicio para gestionar los mensajes del sistema
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Generic\MensajesSistema;
use Micro\Helpers\Log;

// Asegurarse de que la solicitud sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('HTTP/1.1 405 Method Not Allowed');
    exit(json_encode([
        'success' => false,
        'mensaje' => 'Método no permitido'
    ]));
}

// Iniciar sesión si no está iniciada
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Verificar que el usuario esté autenticado
if (!isset($_SESSION['id'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit(json_encode([
        'success' => false,
        'mensaje' => 'Usuario no autenticado'
    ]));
}


$idUsuario = $_SESSION['id'];
$accion = $_POST['accion'] ?? '';

$mensajesSistema = new MensajesSistema();
$respuesta = ['success' => false, 'mensaje' => 'Acción no reconocida'];

switch ($accion) {
    case 'marcar_visto':
        // Marcar un mensaje específico como visto
        $idMensaje = isset($_POST['id_mensaje']) ? (int)$_POST['id_mensaje'] : 0;

        if ($idMensaje <= 0) {
            $respuesta = ['success' => false, 'mensaje' => 'ID de mensaje inválido'];
            break;
        }

        $resultado = $mensajesSistema->marcarComoVisto($idMensaje, $idUsuario);
        $respuesta = [
            'success' => $resultado,
            'mensaje' => $resultado ? 'Mensaje marcado como visto' : 'Error al marcar mensaje como visto'
        ];
        break;

    case 'marcar_todos_vistos':
        try {
            // Marcar todos los mensajes de un código como vistos
            $codigos = $_POST['otros'][0] ?? [];

            if (empty($codigos)) {
                $respuesta = ['success' => false, 'mensaje' => 'Código no especificado'];
                break;
            }
            foreach ($codigos as $codigo) {
                if (empty($codigo)) {
                    continue; // Saltar códigos vacíos
                }

                $mensajesSistema->marcarComoVisto($codigo, $idUsuario);
                Log::info("Marcar todos los mensajes como vistos para el código: $codigo", [
                    'idUsuario' => $idUsuario,
                    'codigo' => $codigo
                ]);
            }

            $respuesta = [
                'success' => true,
                'mensaje' => 'Todos los mensajes marcados como vistos'
            ];
        } catch (Exception $e) {
            Log::error("Error al marcar todos los mensajes como vistos", [
                'idUsuario' => $idUsuario,
                'error' => $e->getMessage()
            ]);
            $respuesta = [
                'success' => false,
                'mensaje' => 'Ocurrió un error al marcar los mensajes como vistos'
            ];
        }
        break;

    case 'get_mensajes':
        // Obtener todos los mensajes pendientes de un código
        try {
            $codigo = $_POST['otros'][0] ?? '';

            if (empty($codigo)) {
                $respuesta = ['success' => false, 'mensaje' => 'Código no especificado'];
                break;
            }

            $mensajes = $mensajesSistema->obtenerMensajesPendientes($codigo, $idUsuario);
            $htmlMensajes = $mensajesSistema->renderizarMensajes($mensajes);

            $respuesta = [
                'success' => true,
                'mensajesId' => array_map(function ($mensaje) {
                    return $mensaje['id'];
                }, $mensajes),
                'html' => $htmlMensajes
            ];
        } catch (Exception $e) {
            Log::error("Error al obtener mensajes pendientes", [
                'idUsuario' => $idUsuario,
                'codigo' => $codigo ?? null,
                'error' => $e->getMessage()
            ]);
            $respuesta = [
                'success' => false,
                'mensaje' => 'Ocurrió un error al obtener los mensajes pendientes'
            ];
        }
        break;
}

// Devolver respuesta en formato JSON
header('Content-Type: application/json');
echo json_encode($respuesta);
