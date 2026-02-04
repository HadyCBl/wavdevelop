<?php

namespace Micro\Exceptions;

use Exception;

/**
 * Excepción controlada que puede mostrarse al usuario
 * 
 * Esta excepción se usa cuando el error es esperado y el mensaje
 * es seguro para mostrar directamente al usuario final.
 * 
 * Ejemplo:
 * throw new SoftException("El cierre ya fue realizado");
 */
class SoftException extends Exception
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
        return true;
    }
}
