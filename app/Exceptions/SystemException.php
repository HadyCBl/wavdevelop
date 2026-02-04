<?php

namespace Micro\Exceptions;

use Exception;

/**
 * Excepción del sistema que NO debe mostrarse al usuario
 * 
 * Esta excepción se usa para errores técnicos o inesperados que
 * deben registrarse en logs y mostrar un código de error genérico al usuario.
 * 
 * Ejemplo:
 * throw new SystemException("Error de conexión a base de datos: " . $details);
 */
class SystemException extends Exception
{
    public function __construct(string $message, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Indica si el mensaje debe mostrarse al usuario
     */
    public function isUserFriendly(): bool
    {
        return false;
    }
}
