<?php

use App\DatabaseAdapter;
use Micro\Generic\Auth;
use Micro\Helpers\Log;
use Micro\Helpers\ControllerResolver;
use Micro\Middleware\MiddlewareHandler;
use Micro\Middleware\AuthMiddleware;
use Micro\Middleware\CSRFMiddleware;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Config/config.php';

/**
 * temporalmente, se puso aca lo de eloquent, quitar cuando se resuelva la version de php a 8.2
 */
if (file_exists(__DIR__ . '/../app/config/eloquent.php')) {
    require_once __DIR__ . '/../app/config/eloquent.php';
}


// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Configurar headers para API JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Manejar preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$httpMethod = $_SERVER['REQUEST_METHOD'];
$uri = $_SERVER['REQUEST_URI'];

// Limpiar query string
if (false !== $pos = strpos($uri, '?')) {
    $uri = substr($uri, 0, $pos);
}
$uri = rawurldecode($uri);

$dispatcher = FastRoute\simpleDispatcher(function($r) {
    $routes = require __DIR__ . '/routes.php';
    $routes($r);
});

// ==================== CONFIGURACIÓN DE MIDDLEWARES ====================

// Cargar configuración de middlewares desde archivo externo
$middlewareConfig = require __DIR__ . '/middleware-config.php';

$globalMiddlewares = $middlewareConfig['global'];
$protectedRoutes = $middlewareConfig['protected'];
$csrfExceptions = $middlewareConfig['csrf_exceptions'];

// ==================== FIN CONFIGURACIÓN ====================

$routeInfo = $dispatcher->dispatch($httpMethod, $uri);

switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND:
        http_response_code(404);
        echo json_encode(['error' => 'Ruta no encontrada', 'uri' => $uri]);
        break;
        
    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
        http_response_code(405);
        $allowedMethods = $routeInfo[1];
        echo json_encode(['error' => 'Método no permitido', 'allowed' => $allowedMethods]);
        break;
        
    case FastRoute\Dispatcher::FOUND:
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];

        // Log::info("Manejando solicitud API", ['method' => $httpMethod, 'uri' => $uri, 'handler' => $handler, 'vars' => $vars]);
        
        try {
            // ==================== APLICAR MIDDLEWARES ====================
            
            // Crear manejador de middlewares con los globales
            $middlewareHandler = MiddlewareHandler::createWithMiddlewares($globalMiddlewares);
            
            // Verificar si la ruta requiere autenticación
            $requiresAuth = false;
            foreach ($protectedRoutes as $pattern) {
                $regexPattern = str_replace('*', '.*', preg_quote($pattern, '/'));
                if (preg_match('/^' . $regexPattern . '$/', $uri)) {
                    $requiresAuth = true;
                    break;
                }
            }
            
            // Agregar middleware de autenticación si es necesario
            if ($requiresAuth) {
                $middlewareHandler->add(new AuthMiddleware());
            }
            
            // Agregar middleware de CSRF (con excepciones)
            $middlewareHandler->add(new CSRFMiddleware($csrfExceptions));
            
            // ==================== EJECUTAR CON MIDDLEWARES ====================
            
            // Definir el controlador final que se ejecutará después de los middlewares
            $finalController = function($request) use ($handler, $vars) {
                if (is_callable($handler)) {
                    return call_user_func_array($handler, $vars);
                } else {
                    // Handler es una cadena 'Controlador@metodo' o 'Namespace\Controlador@metodo'
                    list($class, $method) = explode('@', $handler);
                    
                    // Resolver namespace del controlador (estilo Laravel)
                    $fullClass = ControllerResolver::resolve($class, $request['SERVER']['REQUEST_URI']);
                    
                    if (!class_exists($fullClass)) {
                        Log::error("Controlador no encontrado", ['class' => $class, 'resolved' => $fullClass, 'uri' => $request['SERVER']['REQUEST_URI']]);
                        http_response_code(500);
                        echo json_encode([
                            'error' => "Controlador '{$class}' no encontrado",
                            'resolved_to' => $fullClass
                        ]);
                        exit;
                    }
                    
                    // Crear instancia del controlador con dependencias
                    $database = new DatabaseAdapter();
                    $controller = new $fullClass($database, Auth::all());
                    
                    if (!method_exists($controller, $method)) {
                        Log::error("Método no encontrado en controlador", ['class' => $fullClass, 'method' => $method]);
                        http_response_code(500);
                        echo json_encode(['error' => "Método {$method} no encontrado en {$fullClass}"]);
                        exit;
                    }
                    
                    return call_user_func_array([$controller, $method], $vars);
                }
            };
            
            // Ejecutar middlewares + controlador
            $request = [
                'GET' => $_GET,
                'POST' => $_POST,
                'SERVER' => $_SERVER,
                'vars' => $vars
            ];
            
            $middlewareHandler->run($request, $finalController);
            
        } catch (Exception $e) {
            Log::error("Error en API: " . $e->getMessage(), ['exception' => $e]);
            http_response_code(500);
            echo json_encode([
                'error' => 'Error interno del servidor',
                'mensaje' => $e->getMessage()
            ]);
        }
        break;
}