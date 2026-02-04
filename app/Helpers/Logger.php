<?php

namespace Micro\Helpers;

class Logger
{
    protected $logPath;

    public function __construct($logPath = __DIR__ . '/../../logs')
    {
        $this->logPath = rtrim($logPath, '/');

        // Crear carpeta de logs si no existe
        if (!is_dir($this->logPath)) {
            mkdir($this->logPath, 0777, true);
        }
    }

    public function log($level, $message, array $context = [])
    {
        $date = date('Y-m-d H:i:s');
        $contextString = !empty($context) ? json_encode($context) : '';
        $formattedMessage = "[$date] $level: $message $contextString" . PHP_EOL;

        $fileName = $this->logPath . '/log-' . date('Y-m-d') . '.log';

        file_put_contents($fileName, $formattedMessage, FILE_APPEND);
    }

    // Métodos rápidos como en Laravel
    public function info($message, array $context = [])
    {
        $this->log('INFO', $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->log('WARNING', $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->log('ERROR', $message, $context);
    }

    public function debug($message, array $context = [])
    {
        $this->log('DEBUG', $message, $context);
    }

    /**
     * Registra un error con código único para mostrar al usuario
     * 
     * @param string $message Mensaje de error
     * @param string $file1 Archivo principal donde ocurrió el error
     * @param int $line1 Línea del archivo principal
     * @param string $file2 Archivo secundario (opcional)
     * @param int $line2 Línea del archivo secundario (opcional)
     * @param array $context Contexto adicional (opcional)
     * @return string Código de error generado
     */
    public function errorWithCode($message, $file1 = '', $line1 = 0, $file2 = '', $line2 = 0, array $context = [])
    {
        // Generar código único de error
        $errorCode = 'ERR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        // Construir mensaje detallado
        $date = date('Y-m-d H:i:s');
        $detailedMessage = "[$date] ERROR [Código: $errorCode]";
        
        if ($file1) {
            $detailedMessage .= " - Archivo: $file1";
            if ($line1) {
                $detailedMessage .= " Línea: $line1";
            }
        }
        
        if ($file2) {
            $detailedMessage .= " | Archivo secundario: $file2";
            if ($line2) {
                $detailedMessage .= " Línea: $line2";
            }
        }
        
        $detailedMessage .= " - $message";
        
        if (!empty($context)) {
            $detailedMessage .= ' ' . json_encode($context);
        }
        
        $detailedMessage .= PHP_EOL;
        
        // Guardar en archivo de errores específico
        $fileName = $this->logPath . '/errores.log';
        
        // Crear archivo si no existe
        if (!file_exists($fileName)) {
            touch($fileName);
            chmod($fileName, 0666);
        }
        
        file_put_contents($fileName, $detailedMessage, FILE_APPEND);
        
        // También registrar en el log diario
        $this->error("[Código: $errorCode] $message", array_merge($context, [
            'file1' => $file1,
            'line1' => $line1,
            'file2' => $file2,
            'line2' => $line2
        ]));
        
        return $errorCode;
    }
}
