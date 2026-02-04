<?php

namespace Micro\Helpers;

class Log
{
    protected static $logger;

    protected static function getLogger()
    {
        if (!self::$logger) {
            self::$logger = new Logger(__DIR__ . '/../../logs');
        }

        return self::$logger;
    }

    public static function info($message, array $context = [])
    {
        return self::getLogger()->info($message, $context);
    }

    public static function error($message, array $context = [])
    {
        return self::getLogger()->error($message, $context);
    }

    public static function debug($message, array $context = [])
    {
        return self::getLogger()->debug($message, $context);
    }
    public static function warning($message, array $context = [])
    {
        return self::getLogger()->warning($message, $context);
    }

    /**
     * Registra un error con código único para mostrar al usuario
     * Útil para producción donde se necesita un código de referencia
     * 
     * @param string $message Mensaje de error
     * @param string $file1 Archivo principal donde ocurrió el error
     * @param int $line1 Línea del archivo principal
     * @param string $file2 Archivo secundario (opcional)
     * @param int $line2 Línea del archivo secundario (opcional)
     * @param array $context Contexto adicional (opcional)
     * @return string Código de error generado (ej: ERR-A3F2B8C1)
     */
    public static function errorWithCode($message, $file1 = '', $line1 = 0, $file2 = '', $line2 = 0, array $context = [])
    {
        return self::getLogger()->errorWithCode($message, $file1, $line1, $file2, $line2, $context);
    }
}
