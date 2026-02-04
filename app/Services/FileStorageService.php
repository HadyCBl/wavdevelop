<?php

namespace Micro\Services;

use App\Generic\Agencia;
use Exception;
use Micro\Generic\Auth;
use Micro\Helpers\Log;

/**
 * Servicio de almacenamiento de archivos
 * Maneja la subida, lectura y servicio de archivos de forma centralizada
 * Funciona tanto en entornos Docker como en instalaciones directas
 */
class FileStorageService
{
    /**
     * Obtener la ruta base de almacenamiento desde configuración
     */
    private static function getBasePath(): string
    {
        // Intentar obtener de variable de entorno primero
        $basePath = getenv('STORAGE_PATH');

        if ($basePath === false || empty($basePath)) {
            // Si no existe, usar ruta por defecto según si está en Docker o no
            if (file_exists('/.dockerenv')) {
                // Estamos en Docker - usar carpeta dentro del proyecto
                $basePath = '/var/www/html/storage/uploads';
            } else {
                // Instalación directa - usar ruta relativa al proyecto
                $folderInstitucion = (new Agencia(Auth::getAgencyId()))->institucion?->getFolderInstitucion();

                if ($folderInstitucion === null) {
                    throw new Exception("No se pudo determinar la institución para definir la ruta de almacenamiento de archivos.");
                }
                $basePath = __DIR__ . '/../../../imgcoope.microsystemplus.com/' . $folderInstitucion;
            }
        }

        return rtrim($basePath, '/');
    }

    /**
     * Crear directorio si no existe
     */
    private static function ensureDirectoryExists(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }

    /**
     * Subir archivo(s)
     * 
     * @param array $file Archivo de $_FILES (puede ser único o múltiple)
     * @param string $module Módulo/carpeta destino (ej: 'auxilios', 'creditos')
     * @param int|string $entityId ID de la entidad relacionada
     * @param callable|null $onSuccess Callback que se ejecuta por cada archivo exitoso: function($filepath, $originalName, $newName)
     * @return array Lista de archivos procesados con [success => bool, filepath => string, originalName => string, newName => string, error => string]
     */
    public static function uploadFiles(array $file, string $module, $entityId, ?callable $onSuccess = null): array
    {
        $results = [];
        $basePath = self::getBasePath();
        $uploadDir = $basePath . '/' . $module . '/' . $entityId . '/';

        // Crear directorio si no existe
        if (!self::ensureDirectoryExists($uploadDir)) {
            throw new Exception("No se pudo crear el directorio de destino: {$uploadDir}");
        }

        // Determinar si es un solo archivo o múltiples
        $count = is_array($file['name']) ? count($file['name']) : 1;

        for ($i = 0; $i < $count; $i++) {
            $fileName = is_array($file['name']) ? $file['name'][$i] : $file['name'];
            $tmpName = is_array($file['tmp_name']) ? $file['tmp_name'][$i] : $file['tmp_name'];
            $fileError = is_array($file['error']) ? $file['error'][$i] : $file['error'];

            // Saltar archivos vacíos (cuando no se selecciona nada en el input)
            if (empty($fileName)) {
                continue;
            }

            // Validar que fileError sea un entero
            if (!is_int($fileError)) {
                Log::error("Error de tipo en fileError", [
                    'fileError' => $fileError,
                    'type' => gettype($fileError),
                    'file_structure' => $file
                ]);
                $results[] = [
                    'success' => false,
                    'originalName' => $fileName,
                    'error' => 'Estructura de archivo inválida'
                ];
                continue;
            }

            // Verificar errores de carga
            if ($fileError !== UPLOAD_ERR_OK) {
                $results[] = [
                    'success' => false,
                    'originalName' => $fileName,
                    'error' => self::getUploadErrorMessage($fileError)
                ];
                continue;
            }

            // Generar nombre único
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            $newName = uniqid() . '.' . strtolower($extension);
            $fullPath = $uploadDir . $newName;

            // Mover archivo
            if (move_uploaded_file($tmpName, $fullPath)) {
                // Ruta relativa para guardar en BD (sin basePath)
                $relativePath = $module . '/' . $entityId . '/' . $newName;

                $result = [
                    'success' => true,
                    'filepath' => $relativePath,
                    'originalName' => $fileName,
                    'newName' => $newName,
                    'fullPath' => $fullPath
                ];

                $results[] = $result;

                // Ejecutar callback si existe
                if ($onSuccess !== null) {
                    call_user_func($onSuccess, $relativePath, $fileName, $newName);
                }

                Log::info("Archivo subido exitosamente", $result);
            } else {
                $results[] = [
                    'success' => false,
                    'originalName' => $fileName,
                    'error' => 'No se pudo mover el archivo al destino'
                ];

                Log::error("Error al subir archivo: {$fileName}");
            }
        }

        return $results;
    }

    /**
     * Obtener ruta completa de un archivo
     * 
     * @param string $relativePath Ruta relativa guardada en BD
     * @return string Ruta absoluta del archivo
     */
    public static function getFullPath(string $relativePath): string
    {
        $basePath = self::getBasePath();
        return $basePath . '/' . ltrim($relativePath, '/');
    }

    /**
     * Verificar si un archivo existe
     */
    public static function exists(string $relativePath): bool
    {
        $fullPath = self::getFullPath($relativePath);
        return file_exists($fullPath);
    }

    /**
     * Servir archivo al navegador
     * 
     * @param string $relativePath Ruta relativa del archivo
     * @param bool $download Si true, fuerza descarga. Si false, muestra inline
     */
    public static function serveFile(string $relativePath, bool $download = false): void
    {
        $fullPath = self::getFullPath($relativePath);

        if (!file_exists($fullPath)) {
            header('HTTP/1.0 404 Not Found');
            echo 'Archivo no encontrado';
            return;
        }

        // Detectar tipo MIME
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fullPath);
        finfo_close($finfo);

        // Enviar headers
        header('Content-Type: ' . $mimeType);
        header('Content-Length: ' . filesize($fullPath));

        if ($download) {
            header('Content-Disposition: attachment; filename="' . basename($fullPath) . '"');
        } else {
            header('Content-Disposition: inline; filename="' . basename($fullPath) . '"');
        }

        // Cache headers (opcional)
        header('Cache-Control: public, max-age=31536000'); // 1 año

        // Servir archivo
        readfile($fullPath);
        exit;
    }

    /**
     * Eliminar archivo
     * 
     * @param string $relativePath Ruta relativa del archivo
     * @return bool True si se eliminó correctamente
     */
    public static function deleteFile(string $relativePath): bool
    {
        $fullPath = self::getFullPath($relativePath);

        if (file_exists($fullPath)) {
            return unlink($fullPath);
        }

        return false;
    }

    /**
     * Obtener mensaje de error de carga de archivo
     */
    private static function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                return 'El archivo excede el tamaño máximo permitido';
            case UPLOAD_ERR_PARTIAL:
                return 'El archivo se cargó parcialmente';
            case UPLOAD_ERR_NO_FILE:
                return 'No se cargó ningún archivo';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Falta la carpeta temporal';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Error al escribir el archivo en disco';
            case UPLOAD_ERR_EXTENSION:
                return 'Una extensión de PHP detuvo la carga';
            default:
                return 'Error desconocido al cargar el archivo';
        }
    }

    /**
     * Validar extensión de archivo
     * 
     * @param string $filename Nombre del archivo
     * @param array $allowedExtensions Extensiones permitidas
     * @return bool
     */
    public static function validateExtension(string $filename, array $allowedExtensions): bool
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, array_map('strtolower', $allowedExtensions));
    }

    /**
     * Validar tamaño de archivo
     * 
     * @param int $fileSize Tamaño del archivo en bytes
     * @param int $maxSize Tamaño máximo en bytes
     * @return bool
     */
    public static function validateSize(int $fileSize, int $maxSize): bool
    {
        return $fileSize <= $maxSize;
    }
}
