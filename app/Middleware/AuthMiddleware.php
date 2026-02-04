<?php

namespace Micro\Middleware;

use Micro\Generic\Auth;

/**
 * Middleware de Autenticación
 * Verifica que el usuario esté autenticado
 */
class AuthMiddleware implements Middleware
{
    /**
     * @param array $request
     * @param callable $next
     * @return mixed
     */
    public function handle(array $request, callable $next)
    {
        // Verificar si el usuario está autenticado
        $userId = Auth::getUserId();
        
        if (!$userId) {
            // Si no está autenticado, retornar error JSON
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 0,
                'error' => 'No autenticado',
                'message' => 'Debe iniciar sesión para acceder a este recurso',
                'code' => 401
            ]);
            exit;
        }
        
        // Si está autenticado, continuar con la petición
        return $next($request);
    }
}
