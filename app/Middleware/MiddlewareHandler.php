<?php

namespace Micro\Middleware;

/**
 * Manejador de Middlewares
 * Ejecuta una pila de middlewares en orden
 */
class MiddlewareHandler
{
    /**
     * @var array Array de middlewares
     */
    private array $middlewares = [];
    
    /**
     * Agrega un middleware a la pila
     * 
     * @param Middleware $middleware
     * @return self
     */
    public function add(Middleware $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }
    
    /**
     * Ejecuta todos los middlewares y luego el controlador final
     * 
     * @param array $request Datos de la petición
     * @param callable $controller Controlador final a ejecutar
     * @return mixed
     */
    public function run(array $request, callable $controller)
    {
        // Crear la cadena de middlewares (de atrás hacia adelante)
        $next = $controller;
        
        // Recorrer middlewares en orden inverso para crear la cadena
        foreach (array_reverse($this->middlewares) as $middleware) {
            $next = function ($request) use ($middleware, $next) {
                return $middleware->handle($request, $next);
            };
        }
        
        // Ejecutar la cadena completa
        return $next($request);
    }
    
    /**
     * Crea un manejador con middlewares globales
     * 
     * @param array $middlewares Array de instancias de Middleware
     * @return self
     */
    public static function createWithMiddlewares(array $middlewares): self
    {
        $handler = new self();
        
        foreach ($middlewares as $middleware) {
            if ($middleware instanceof Middleware) {
                $handler->add($middleware);
            }
        }
        
        return $handler;
    }
}
