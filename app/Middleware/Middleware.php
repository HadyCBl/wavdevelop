<?php

namespace Micro\Middleware;

/**
 * Interfaz para todos los middlewares
 */
interface Middleware
{
    /**
     * Procesa la petición antes de llegar al controlador
     * 
     * @param array $request Datos de la petición (GET, POST, etc.)
     * @param callable $next Siguiente middleware o controlador
     * @return mixed
     */
    public function handle(array $request, callable $next);
}
