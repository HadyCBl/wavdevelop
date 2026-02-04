<?php

namespace Micro\Middleware;

use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;

/**
 * Middleware de Protección CSRF
 * Verifica el token CSRF en peticiones POST, PUT, PATCH, DELETE
 */
class CSRFMiddleware implements Middleware
{
    /**
     * Métodos HTTP que requieren validación CSRF
     */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Rutas excluidas de la validación CSRF
     * Útil para APIs externas, webhooks, etc.
     */
    private array $exceptions;

    /**
     * @param array $exceptions Rutas a excluir (ej: ['/api/webhook', '/api/public/*'])
     */
    public function __construct(array $exceptions = [])
    {
        $this->exceptions = $exceptions;
    }

    /**
     * @param array $request
     * @param callable $next
     * @return mixed
     */
    public function handle(array $request, callable $next)
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // Limpiar query string de la URI
        $uri = strtok($uri, '?');

        // Si el método no requiere CSRF, continuar
        if (!in_array($method, self::PROTECTED_METHODS)) {
            return $next($request);
        }

        // Verificar si la ruta está excluida
        if ($this->isExcluded($uri)) {
            // DEBUG: Log para verificar que se excluyó correctamente
            // error_log("CSRF: Ruta excluida - {$uri}");
            return $next($request);
        }

        // Validar token CSRF
        $csrf = new CSRFProtection();
        $token = $this->getTokenFromRequest();

        Log::info("CSRF token obtenido: " . ($token ? 'presente' : 'ausente'));

        if (!$csrf->validateToken($token, false)) {
            // DEBUG: Log para ver qué ruta falló
            // error_log("CSRF: Token inválido para {$uri}. Excepciones configuradas: " . json_encode($this->exceptions));
            Log::info("CSRF: Token inválido para {$uri}. Excepciones configuradas: " . json_encode($this->exceptions));

            http_response_code(403);
            header('Content-Type: application/json');
            $response = [
                'status' => 0,
                'error' => 'Token CSRF inválido',
                'message' => 'La petición no pudo ser verificada. Por favor, recargue la página e intente nuevamente.',
                'code' => 403
            ];

            // Agregar información de debug solo en desarrollo
            if (getenv('APP_ENV') !== 'production') {
                $response['debug'] = [
                    'uri' => $uri,
                    'method' => $method,
                    'has_token' => !empty($token),
                    'exceptions' => $this->exceptions
                ];
            }

            echo json_encode($response);
            exit;
        }

        // Token válido, continuar
        return $next($request);
    }

    /**
     * Verifica si una ruta está excluida de la validación CSRF
     * 
     * @param string $uri URI a verificar
     * @return bool
     */
    private function isExcluded(string $uri): bool
    {
        // Normalizar URI (quitar barra final si existe)
        $uri = rtrim($uri, '/');

        foreach ($this->exceptions as $exception) {
            // Normalizar excepción
            $exception = rtrim($exception, '/');

            // Si tiene wildcard, convertir a regex
            if (strpos($exception, '*') !== false) {
                // Escapar todo excepto el asterisco
                $pattern = str_replace('\\*', '.*', preg_quote($exception, '/'));

                // DEBUG
                // error_log("CSRF DEBUG: Comparando '{$uri}' con pattern '/^{$pattern}$/'");

                if (preg_match('/^' . $pattern . '$/', $uri)) {
                    return true;
                }
            } else {
                // Comparación exacta
                if ($uri === $exception) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Obtiene el token CSRF desde diferentes fuentes de la request
     * 
     * Busca el token en el siguiente orden:
     * 1. Header HTTP_X_CSRF_TOKEN
     * 2. $_POST['csrf_token'] (para POST tradicionales)
     * 3. Body de la request (php://input) para PUT/PATCH/DELETE
     * 
     * @return string El token CSRF o cadena vacía si no se encuentra
     */
    private function getTokenFromRequest(): string
    {
        // 1. Verificar en el header X-CSRF-TOKEN
        if (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // 2. Verificar en $_POST (funciona para POST tradicionales)
        if (!empty($_POST['csrf_token'])) {
            return $_POST['csrf_token'];
        }

        // 3. Verificar en el body de la request (para PUT/PATCH/DELETE)
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['PUT', 'PATCH', 'DELETE', 'POST'])) {
            $rawBody = file_get_contents('php://input');
            
            if (!empty($rawBody)) {
                // Intentar parsear como JSON
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                
                if (stripos($contentType, 'application/json') !== false) {
                    $data = json_decode($rawBody, true);
                    if (is_array($data) && !empty($data['csrf_token'])) {
                        return $data['csrf_token'];
                    }
                } else {
                    // Intentar parsear como form-urlencoded
                    parse_str($rawBody, $data);
                    if (!empty($data['csrf_token'])) {
                        return $data['csrf_token'];
                    }
                }
            }
        }

        return '';
    }
}
