<?php

namespace Micro\Controllers;

use App\DatabaseAdapter;
use Micro\Generic\Auth;
use Micro\Generic\PermissionManager;
use Exception;

/**
 * Controlador base principal
 * 
 * Similar a Laravel Controller, provee funcionalidades comunes para todos los controladores
 * Todos los controladores deben heredar de esta clase
 */
abstract class BaseController
{
    protected DatabaseAdapter $database;
    protected array $session;
    protected ?PermissionManager $permissions = null;

    public function __construct(DatabaseAdapter $database, array $session = [])
    {
        $this->database = $database;
        $this->session = $session;
        
        // Inicializar PermissionManager si hay usuario autenticado
        $userId = Auth::getUserId();
        if ($userId) {
            $this->permissions = new PermissionManager($userId);
        }
    }

    /**
     * Obtiene datos POST de la petición
     * 
     * @param string $key Clave del dato
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    protected function post(string $key, $default = null)
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Obtiene datos GET de la petición
     * 
     * @param string $key Clave del dato
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    protected function get(string $key, $default = null)
    {
        return $_GET[$key] ?? $default;
    }

    /**
     * Obtiene datos PUT/PATCH de la petición
     * Los datos pueden venir en varios formatos
     * 
     * @param string $key Clave del dato
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    protected function input(string $key, $default = null)
    {
        // Intentar primero desde $_POST
        if (isset($_POST[$key])) {
            return $_POST[$key];
        }

        // Si es PUT/PATCH, leer desde php://input
        static $inputData = null;
        if ($inputData === null) {
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

            if (strpos($contentType, 'application/json') !== false) {
                $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
            } else {
                parse_str(file_get_contents('php://input'), $inputData);
            }
        }

        return $inputData[$key] ?? $default;
    }

    /**
     * Obtiene todos los datos de la petición (POST, PUT, PATCH)
     * 
     * @return array
     */
    protected function all(): array
    {
        // Para PUT/PATCH/DELETE, leer php://input
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        if (in_array($method, ['PUT', 'PATCH', 'DELETE'])) {
            static $inputData = null;
            if ($inputData === null) {
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

                if (strpos($contentType, 'application/json') !== false) {
                    $inputData = json_decode(file_get_contents('php://input'), true) ?? [];
                } else {
                    parse_str(file_get_contents('php://input'), $inputData);
                }
            }
            return $inputData;
        }

        // Para POST, usar $_POST
        return $_POST;
    }

    /**
     * Valida que existan los parámetros requeridos
     * 
     * @param array $required Array de nombres de parámetros requeridos
     * @throws Exception Si falta algún parámetro
     */
    protected function validate(array $required): void
    {
        $data = $this->all();
        $missing = [];

        foreach ($required as $param) {
            if (!isset($data[$param]) || $data[$param] === '') {
                $missing[] = $param;
            }
        }

        if (!empty($missing)) {
            throw new Exception('Parámetros requeridos faltantes: ' . implode(', ', $missing));
        }
    }

    /**
     * Obtiene el ID del usuario de la sesión
     * Usa Auth para mantener compatibilidad futura con cambios en el sistema de sesiones
     * 
     * @return int|string|null
     */
    protected function getUserId(): int|string|null
    {
        return Auth::getUserId();
    }

    /**
     * Obtiene el nombre del usuario de la sesión
     * 
     * @return string|null
     */
    protected function getUserName(): ?string
    {
        return Auth::getUserName();
    }

    /**
     * Obtiene el ID de la agencia de la sesión
     * 
     * @return int|null
     */
    protected function getAgencyId(): ?int
    {
        return Auth::getAgencyId();
    }

    /**
     * Obtiene el ID de la institución de la sesión
     * 
     * @return int|null
     */
    protected function getInstitutionId(): ?int
    {
        $value = Auth::get('id_institucion');
        return is_int($value) ? $value : null;
    }

    /**
     * Verifica si el usuario está autenticado
     * 
     * @return bool
     */
    protected function isAuthenticated(): bool
    {
        return Auth::getUserId() !== null;
    }

    /**
     * Obtiene la instancia de PermissionManager
     * 
     * @return PermissionManager|null
     */
    protected function getPermissions(): ?PermissionManager
    {
        return $this->permissions;
    }

    /**
     * Obtiene un valor específico de la sesión a través de Auth
     * 
     * @param string $key Clave del valor
     * @param mixed $default Valor por defecto
     * @return mixed
     */
    protected function getSessionValue(string $key, mixed $default = null): mixed
    {
        return Auth::get($key, $default);
    }

    /**
     * Respuesta JSON genérica
     * 
     * @param array $data Datos a enviar
     * @param int $statusCode Código HTTP
     */
    protected function json(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data);
    }

    /**
     * Respuesta JSON exitosa
     * 
     * @param mixed $data Datos a enviar
     * @param string $message Mensaje opcional
     */
    protected function success($data = null, string $message = ''): void
    {
        $response = ['status' => 1];

        if ($message) {
            $response['message'] = $message;
        }

        if ($data !== null) {
            if (is_array($data)) {
                $response = array_merge($response, $data);
            } else {
                $response['data'] = $data;
            }
        }

        $this->json($response, 200);
    }

    /**
     * Respuesta JSON de error
     * 
     * @param string $message Mensaje de error
     * @param int $code Código HTTP (default: 400)
     */
    protected function error(string $message, int $code = 400): void
    {
        $this->json([
            'status' => 0,
            'error' => $message,
            'message' => $message
        ], $code);
    }

    /**
     * Respuesta exitosa con auto-recarga de vista
     * 
     * Incluye información para que el frontend recargue automáticamente una vista
     * después de una operación exitosa (crear, actualizar, eliminar)
     * 
     * @param string $message Mensaje de éxito
     * @param string $reloadRoute Ruta de la vista a recargar (ej: '/api/seguros/servicios/index')
     * @param string $reloadTarget Selector del contenedor donde recargar (default: '#cuadro')
     * @param array $reloadData Datos adicionales para la recarga
     * @param mixed $extraData Datos adicionales en la respuesta
     * 
     * @example
     * // En un controlador después de crear/actualizar:
     * $this->successWithReload(
     *     'Servicio creado exitosamente',
     *     '/api/seguros/servicios/index',
     *     '#cuadro'
     * );
     */
    protected function successWithReload(
        string $message,
        string $reloadRoute,
        string $reloadTarget = '#cuadro',
        array $reloadData = [],
        $extraData = null
    ): void {
        $response = [
            'status' => 1,
            'message' => $message,
            'reload' => [
                'route' => $reloadRoute,
                'target' => $reloadTarget,
                'data' => $reloadData,
                'condi' => $reloadData['condi'] ?? ''
            ]
        ];

        if ($extraData !== null) {
            $response['data'] = $extraData;
        }

        $this->json($response, 200);
    }

    /**
     * Sanitiza una cadena para evitar XSS
     * 
     * @param string $value Valor a sanitizar
     * @return string
     */
    protected function sanitize(string $value): string
    {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Obtiene múltiples valores de la petición de una vez
     * 
     * @param array $keys Array de claves a obtener
     * @return array Array asociativo con los valores
     */
    protected function only(array $keys): array
    {
        $data = $this->all();
        $values = [];

        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $values[$key] = $data[$key];
            }
        }

        return $values;
    }

    /**
     * Verifica si la petición es AJAX
     * 
     * @return bool
     */
    protected function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Obtiene el método HTTP de la petición
     * 
     * @return string
     */
    protected function method(): string
    {
        return $_SERVER['REQUEST_METHOD'] ?? 'GET';
    }

    /**
     * Log de auditoría genérico
     * 
     * @param string $action Acción realizada
     * @param string $entity Entidad afectada
     * @param mixed $entityId ID de la entidad
     * @param array $changes Cambios realizados
     */
    protected function logAudit(string $action, string $entity, $entityId, array $changes = []): void
    {
        error_log(sprintf(
            "[AUDIT] User: %s | Action: %s | Entity: %s | ID: %s | Changes: %s | IP: %s",
            $this->getUserId() ?? 'unknown',
            $action,
            $entity,
            $entityId,
            json_encode($changes),
            $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ));

        // TODO: Guardar en tabla de auditoría si existe
    }

    // ==================== MÉTODOS PARA VISTAS ====================

    protected array $viewData = [];

    /**
     * Renderiza una vista y retorna el HTML
     * Soporta tanto archivos .latte (Latte Template Engine) como .php (PHP puro)
     * 
     * @param string $viewFile Ruta relativa de la vista (ej: 'creditos/lista')
     * @param array $data Datos a pasar a la vista
     * @param bool $useLatte Forzar uso de Latte (default: auto-detecta por extensión)
     * @return string HTML renderizado
     */
    protected function renderView(string $viewFile, array $data = [], ?bool $useLatte = null): string
    {
        $viewsPath = __DIR__ . '/../../views/';

        // Detectar automáticamente si usar Latte o PHP
        $lattePath = $viewsPath . $viewFile . '.latte';
        $phpPath = $viewsPath . $viewFile . '.php';

        // Auto-detectar si no se especifica
        if ($useLatte === null) {
            $useLatte = file_exists($lattePath);
        }

        // Si se fuerza Latte o existe archivo .latte, usar Latte
        if ($useLatte) {
            return $this->renderLatte($viewFile, $data);
        }

        // Fallback a PHP tradicional
        return $this->renderPhp($viewFile, $data);
    }

    /**
     * Renderiza una vista usando Latte Template Engine
     * 
     * @param string $viewFile Ruta relativa de la vista
     * @param array $data Datos a pasar a la vista
     * @return string HTML renderizado
     */
    private function renderLatte(string $viewFile, array $data = []): string
    {
        static $latte = null;

        // Inicializar Latte una sola vez
        if ($latte === null) {
            $latte = new \Latte\Engine;

            // Configurar directorio de caché
            $cacheDir = __DIR__ . '/../../cache/latte';
            if (!is_dir($cacheDir)) {
                mkdir($cacheDir, 0755, true);
            }
            $latte->setTempDirectory($cacheDir);

            // Modo de desarrollo (desactivar en producción)
            // if (defined('APP_ENV') && APP_ENV === 'development') {
            //     $latte->setAutoRefresh(true);
            // }

            $isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
            if (!$isProduction) {
                $latte->setAutoRefresh(true);
            }
        }

        $viewsPath = __DIR__ . '/../../views/';
        $fullPath = $viewsPath . $viewFile . '.latte';

        if (!file_exists($fullPath)) {
            throw new Exception("Vista Latte no encontrada: {$fullPath}");
        }

        // Combinar datos de la vista con los datos pasados
        $templateData = array_merge($this->viewData, $data);

        // Renderizar y retornar
        return $latte->renderToString($fullPath, $templateData);
    }

    /**
     * Renderiza una vista usando PHP tradicional
     * 
     * @param string $viewFile Ruta relativa de la vista
     * @param array $data Datos a pasar a la vista
     * @return string HTML renderizado
     */
    private function renderPhp(string $viewFile, array $data = []): string
    {
        // Extraer variables para la vista
        extract(array_merge($this->viewData, $data));

        // Iniciar buffer de salida
        ob_start();

        try {
            // Construir ruta completa de la vista
            $fullPath = __DIR__ . '/../../views/' . $viewFile . '.php';

            if (!file_exists($fullPath)) {
                throw new Exception("Vista PHP no encontrada: {$fullPath}");
            }

            // Incluir la vista
            require $fullPath;

            // Obtener contenido del buffer
            $content = ob_get_clean();

            return $content;
        } catch (Exception $e) {
            ob_end_clean();
            throw $e;
        }
    }

    /**
     * Respuesta JSON exitosa con HTML (para vistas)
     * 
     * @param string $html HTML renderizado
     * @param array $additionalData Datos adicionales a incluir en la respuesta
     */
    protected function view(string $html, array $additionalData = []): void
    {
        $response = array_merge([
            'status' => 1,
            'html' => $html
        ], $additionalData);

        $this->json($response, 200);
    }

    /**
     * Respuesta JSON con messagecontrol (compatible con sistema legacy)
     * 
     * @param string $message Mensaje a mostrar
     * @param string $type Tipo de mensaje (error, success, warning)
     */
    protected function messageControl(string $message, string $type = 'error'): void
    {
        http_response_code($type === 'error' ? 400 : 200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => $type === 'success' ? 1 : 0,
            'messagecontrol' => $message,
            'mensaje' => $message,
            'type' => $type
        ]);
    }

    /**
     * Asigna una variable para la vista
     * 
     * @param string $key Nombre de la variable
     * @param mixed $value Valor
     */
    protected function assign(string $key, $value): void
    {
        $this->viewData[$key] = $value;
    }

    /**
     * Asigna múltiples variables a la vista
     * 
     * @param array $data Array asociativo de variables
     */
    protected function assignMultiple(array $data): void
    {
        $this->viewData = array_merge($this->viewData, $data);
    }

    // ==================== MÉTODOS PARA CRUD ====================

    /**
     * Respuesta exitosa para creación (201)
     * 
     * @param string $message Mensaje de éxito
     * @param array $data Datos adicionales
     */
    protected function created(string $message, array $data = []): void
    {
        $response = array_merge([
            'status' => 1,
            'message' => $message
        ], $data);

        $this->json($response, 201);
    }

    /**
     * Respuesta exitosa para actualización (200)
     * 
     * @param string $message Mensaje de éxito
     * @param array $data Datos adicionales
     */
    protected function updated(string $message, array $data = []): void
    {
        $response = array_merge([
            'status' => 1,
            'message' => $message
        ], $data);

        $this->json($response, 200);
    }

    /**
     * Respuesta exitosa para eliminación (200)
     * 
     * @param string $message Mensaje de éxito
     */
    protected function deleted(string $message = 'Registro eliminado exitosamente'): void
    {
        $this->json([
            'status' => 1,
            'message' => $message
        ], 200);
    }

    /**
     * Respuesta de recurso no encontrado (404)
     * 
     * @param string $message Mensaje de error
     */
    protected function notFound(string $message = 'Recurso no encontrado'): void
    {
        $this->error($message, 404);
    }

    /**
     * Respuesta de validación fallida (422)
     * 
     * @param string $message Mensaje de error
     * @param array $errors Errores de validación específicos
     */
    protected function validationError(string $message, array $errors = []): void
    {
        $response = [
            'status' => 0,
            'message' => $message,
            'error' => $message
        ];

        if (!empty($errors)) {
            $response['errors'] = $errors;
        }

        $this->json($response, 422);
    }

    /**
     * Verifica si el registro existe en una tabla
     * 
     * @param string $table Nombre de la tabla
     * @param string $idColumn Nombre de la columna ID
     * @param mixed $id Valor del ID
     * @return bool
     */
    protected function exists(string $table, string $idColumn, $id): bool
    {
        $query = "SELECT COUNT(*) as count FROM {$table} WHERE {$idColumn} = ?";
        $result = $this->database->getAllResults($query, [$id]);

        return !empty($result) && $result[0]['count'] > 0;
    }

    /**
     * Obtiene un registro o falla con 404
     * 
     * @param string $table Nombre de la tabla
     * @param string $idColumn Nombre de la columna ID
     * @param mixed $id Valor del ID
     * @param string $errorMessage Mensaje de error si no existe
     * @return array Registro encontrado
     */
    protected function findOrFail(string $table, string $idColumn, $id, string $errorMessage = 'Registro no encontrado'): array
    {
        $query = "SELECT * FROM {$table} WHERE {$idColumn} = ?";
        $result = $this->database->getAllResults($query, [$id]);

        if (empty($result)) {
            $this->notFound($errorMessage);
            exit;
        }

        return $result[0];
    }

    /**
     * Construye cláusula WHERE para soft deletes
     * 
     * @param bool $includeSoftDeleted Si incluir registros eliminados
     * @return string
     */
    protected function softDeleteWhere(bool $includeSoftDeleted = false): string
    {
        return $includeSoftDeleted ? '' : ' AND (estado = 1 OR estado IS NULL)';
    }

    /**
     * Ejecuta un soft delete
     * 
     * @param string $table Nombre de la tabla
     * @param string $idColumn Nombre de la columna ID
     * @param mixed $id Valor del ID
     * @return bool
     */
    protected function softDelete(string $table, string $idColumn, $id): bool
    {
        $query = "UPDATE {$table} SET estado = 0, fecha_eliminado = NOW() WHERE {$idColumn} = ?";
        $this->database->executeQuery($query, [$id]);
        return true;
    }

    /**
     * Ejecuta un hard delete
     * 
     * @param string $table Nombre de la tabla
     * @param string $idColumn Nombre de la columna ID  
     * @param mixed $id Valor del ID
     * @return bool
     */
    protected function hardDelete(string $table, string $idColumn, $id): bool
    {
        $query = "DELETE FROM {$table} WHERE {$idColumn} = ?";
        $this->database->executeQuery($query, [$id]);
        return true;
    }

    /**
     * Paginación simple
     * 
     * @param int $total Total de registros
     * @param int $perPage Registros por página
     * @param int $currentPage Página actual
     * @return array Información de paginación
     */
    protected function paginate(int $total, int $perPage = 15, int $currentPage = 1): array
    {
        $totalPages = ceil($total / $perPage);
        $offset = ($currentPage - 1) * $perPage;

        return [
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $currentPage,
            'total_pages' => $totalPages,
            'offset' => $offset,
            'has_more' => $currentPage < $totalPages
        ];
    }
}
