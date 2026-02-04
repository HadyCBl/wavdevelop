<?php

namespace App\Generic;

/**
 * Clase para procesar archivos y generar rutas, URLs y vistas previas
 * compatible con imágenes y PDFs
 */
class FileProcessor
{
    /**
     * Ruta base para los archivos
     * @var string
     */
    private $basePath;

    /**
     * Constructor
     * @param string $basePath Ruta base donde se almacenan los archivos
     */
    public function __construct($basePath = null)
    {
        if ($basePath === null) {
            // Si no se proporciona una ruta base, usar la raíz del proyecto
            $this->basePath = dirname(dirname(__DIR__));
        } else {
            $this->basePath = $basePath;
        }
    }

    /**
     * Obtiene la ruta completa de un archivo
     * @param string $relativePath Ruta relativa del archivo
     * @return string Ruta completa del archivo
     */
    public function getFullPath($relativePath)
    {
        if (empty($relativePath)) {
            return false;
        }

        // Eliminar barras iniciales para evitar duplicados
        $relativePath = ltrim($relativePath, '/');
        
        return $this->basePath . '/' . $relativePath;
    }

    /**
     * Verifica si un archivo existe
     * @param string $relativePath Ruta relativa del archivo
     * @return bool True si el archivo existe, false si no
     */
    public function fileExists($relativePath)
    {
        $fullPath = $this->getFullPath($relativePath);
        return ($fullPath && is_file($fullPath));
    }

    /**
     * Obtiene el tipo MIME de un archivo
     * @param string $relativePath Ruta relativa del archivo
     * @return string|false Tipo MIME del archivo o false si no se puede determinar
     */
    public function getMimeType($relativePath)
    {
        $fullPath = $this->getFullPath($relativePath);
        
        if (!$fullPath || !is_file($fullPath)) {
            return false;
        }

        // Verificar si es PDF por extensión
        $extension = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            return 'application/pdf';
        }

        // Para imágenes y otros tipos, usar getimagesize
        $imgInfo = @getimagesize($fullPath);
        if ($imgInfo && isset($imgInfo['mime'])) {
            return $imgInfo['mime'];
        }

        // Si no se puede determinar con getimagesize, usar mime_content_type
        if (function_exists('mime_content_type')) {
            return mime_content_type($fullPath);
        }

        // Si todo falla, hacer una estimación basada en la extensión
        $mimeTypes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    /**
     * Verifica si un archivo es una imagen
     * @param string $relativePath Ruta relativa del archivo
     * @return bool True si es una imagen, false si no
     */
    public function isImage($relativePath)
    {
        $mimeType = $this->getMimeType($relativePath);
        return $mimeType && strpos($mimeType, 'image/') === 0;
    }

    /**
     * Verifica si un archivo es un PDF
     * @param string $relativePath Ruta relativa del archivo
     * @return bool True si es un PDF, false si no
     */
    public function isPdf($relativePath)
    {
        $mimeType = $this->getMimeType($relativePath);
        return $mimeType === 'application/pdf';
    }

    /**
     * Genera un Data URI para un archivo
     * @param string $relativePath Ruta relativa del archivo
     * @return string|false Data URI del archivo o false si no se puede generar
     */
    public function getDataUri($relativePath)
    {
        $fullPath = $this->getFullPath($relativePath);
        
        if (!$fullPath || !is_file($fullPath)) {
            return false;
        }

        $mimeType = $this->getMimeType($relativePath);
        if (!$mimeType) {
            return false;
        }

        $fileContent = @file_get_contents($fullPath);
        if ($fileContent === false) {
            return false;
        }

        $base64Content = base64_encode($fileContent);
        return 'data:' . $mimeType . ';base64,' . $base64Content;
    }

    /**
     * Obtiene información de un archivo incluyendo dimensiones para imágenes
     * @param string $relativePath Ruta relativa del archivo
     * @return array|false Información del archivo o false si no se puede obtener
     */
    public function getFileInfo($relativePath)
    {
        $fullPath = $this->getFullPath($relativePath);
        
        if (!$fullPath || !is_file($fullPath)) {
            return false;
        }

        $info = [
            'path' => $fullPath,
            'filename' => basename($fullPath),
            'extension' => strtolower(pathinfo($fullPath, PATHINFO_EXTENSION)),
            'size' => filesize($fullPath),
            'mime' => $this->getMimeType($relativePath),
            'data_uri' => $this->getDataUri($relativePath),
        ];

        // Para imágenes, obtener dimensiones
        if ($this->isImage($relativePath)) {
            $imageInfo = getimagesize($fullPath);
            if ($imageInfo) {
                $info['width'] = $imageInfo[0];
                $info['height'] = $imageInfo[1];
            }
        }

        return $info;
    }

    /**
     * Genera HTML para mostrar una vista previa del archivo
     * @param string $relativePath Ruta relativa del archivo
     * @param array $options Opciones adicionales (clases CSS, tamaño, etc.)
     * @return string HTML con la vista previa del archivo
     */
    public function getPreviewHtml($relativePath, $options = [])
    {
        // Opciones por defecto
        $defaultOptions = [
            'max_width' => '100%',
            'max_height' => '200px',
            'img_class' => 'img-thumbnail',
            'pdf_icon_size' => '48px',
            'pdf_icon_class' => 'text-danger',
            'download_btn_text' => 'Descargar',
            'view_btn_text' => 'Ver PDF',
            'placeholder_text' => 'No hay archivo',
            'show_filename' => true
        ];
        
        $options = array_merge($defaultOptions, $options);
        
        if (!$this->fileExists($relativePath)) {
            // Placeholder para cuando no hay archivo
            return '<div class="text-center p-3 border rounded">
                <i class="fa fa-upload text-muted" style="font-size:' . $options['pdf_icon_size'] . ';"></i>
                <p class="mt-2 mb-0 text-muted">' . $options['placeholder_text'] . '</p>
            </div>';
        }
        
        $fileInfo = $this->getFileInfo($relativePath);
        $fileName = htmlspecialchars($fileInfo['filename']);
        $dataUri = $fileInfo['data_uri'];
        
        if ($this->isImage($relativePath)) {
            // Vista previa para imágenes
            $html = '<img class="' . $options['img_class'] . '" style="max-width:' . $options['max_width'] . '; max-height:' . $options['max_height'] . ';" src="' . $dataUri . '" alt="' . $fileName . '" />';
            
            if ($options['show_filename']) {
                $html .= '<p class="mt-2 mb-0">' . $fileName . '</p>';
            }
            
            return $html;
        } elseif ($this->isPdf($relativePath)) {
            // Vista previa para PDFs
            $html = '<div class="pdf-preview p-3 border rounded" style="max-width:' . $options['max_width'] . ';">
                <i class="fa fa-file-pdf ' . $options['pdf_icon_class'] . '" style="font-size:' . $options['pdf_icon_size'] . ';"></i>';
                
            if ($options['show_filename']) {
                $html .= '<p class="mt-2 mb-0">' . $fileName . '</p>';
            }
            
            $html .= '<div class="mt-2">
                    <a href="' . $dataUri . '" class="btn btn-sm btn-outline-primary" download="' . $fileName . '">
                        <i class="fa fa-download"></i> ' . $options['download_btn_text'] . '
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="previewPDF(\'' . $dataUri . '\')">
                        <i class="fa fa-eye"></i> ' . $options['view_btn_text'] . '
                    </button>
                </div>
            </div>';
            
            return $html;
        } else {
            // Otros tipos de archivos
            return '<div class="file-preview p-3 border rounded" style="max-width:' . $options['max_width'] . ';">
                <i class="fa fa-file text-primary" style="font-size:' . $options['pdf_icon_size'] . ';"></i>
                <p class="mt-2 mb-0">' . $fileName . '</p>
                <a href="' . $dataUri . '" class="btn btn-sm btn-outline-primary mt-2" download="' . $fileName . '">
                    <i class="fa fa-download"></i> ' . $options['download_btn_text'] . '
                </a>
            </div>';
        }
    }

    public function deleteFile($relativePath)
    {
        $fullPath = $this->getFullPath($relativePath);
        
        if (!$fullPath || !is_file($fullPath)) {
            return false;
        }

        // Intentar eliminar el archivo
        return unlink($fullPath);
    }
}
