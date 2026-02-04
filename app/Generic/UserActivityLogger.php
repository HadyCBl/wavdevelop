<?php

namespace Micro\Generic;

use Micro\Helpers\Log;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\BSON\UTCDateTime;
use Exception;

/**
 * Clase para registrar las acciones de los usuarios en MongoDB
 * 
 * Esta clase maneja el registro de actividades de usuarios incluyendo:
 * - Acciones realizadas
 * - IP del usuario
 * - User Agent
 * - Datos adicionales contextuales
 * - Timestamps
 * 
 * @package Micro\Generic
 */
class UserActivityLogger
{
    /** @var Client|null Cliente de MongoDB */
    private static ?Client $mongoClient = null;
    
    /** @var Collection|null Colección de actividades */
    private static ?Collection $collection = null;
    
    /** @var bool Indica si el logger está habilitado */
    private static bool $enabled = false;
    
    /** @var bool Indica si ya se inicializó */
    private static bool $initialized = false;

    /**
     * Inicializa la conexión a MongoDB
     * 
     * @return void
     */
    private static function initialize(): void
    {
        if (self::$initialized) {
            return;
        }

        // Verificar si el logging está habilitado
        self::$enabled = filter_var(
            $_ENV['USER_ACTIVITY_LOG_ENABLED'] ?? false, 
            FILTER_VALIDATE_BOOLEAN
        );

        if (!self::$enabled) {
            self::$initialized = true;
            return;
        }

        try {
            // Construir la URI de conexión
            $host = $_ENV['MONGODB_HOST'] ?? 'localhost';
            $port = $_ENV['MONGODB_PORT'] ?? '27017';
            $username = $_ENV['MONGODB_USERNAME'] ?? '';
            $password = $_ENV['MONGODB_PASSWORD'] ?? '';
            $authSource = $_ENV['MONGODB_AUTH_SOURCE'] ?? 'admin';
            
            $uri = 'mongodb://';
            
            if (!empty($username) && !empty($password)) {
                $uri .= urlencode($username) . ':' . urlencode($password) . '@';
            }
            
            $uri .= $host . ':' . $port;
            
            if (!empty($username)) {
                $uri .= '/?authSource=' . $authSource;
            }

            // Conectar a MongoDB
            self::$mongoClient = new Client($uri);
            
            // Seleccionar base de datos y colección
            $database = $_ENV['MONGODB_DATABASE'] ?? 'user_activities';
            $collectionName = $_ENV['MONGODB_COLLECTION'] ?? 'activities';
            
            self::$collection = self::$mongoClient->selectCollection($database, $collectionName);
            
            // Crear índices para mejorar las consultas
            self::createIndexes();
            
        } catch (Exception $e) {
            // En caso de error, deshabilitamos el logger para no romper la aplicación
            self::$enabled = false;
            // error_log("Error inicializando UserActivityLogger: " . $e->getMessage());
            Log::error("Error inicializando UserActivityLogger: " . $e->getMessage());
        }

        self::$initialized = true;
    }

    /**
     * Crea los índices necesarios en la colección
     * 
     * @return void
     */
    private static function createIndexes(): void
    {
        if (!self::$collection) {
            return;
        }

        try {
            // Índice por usuario y fecha
            self::$collection->createIndex(['user_id' => 1, 'timestamp' => -1]);
            
            // Índice por acción
            self::$collection->createIndex(['action' => 1]);
            
            // Índice por fecha
            self::$collection->createIndex(['timestamp' => -1]);
            
            // Índice por módulo
            self::$collection->createIndex(['module' => 1]);
            
        } catch (Exception $e) {
            // error_log("Error creando índices en UserActivityLogger: " . $e->getMessage());
            Log::error("Error creando índices en UserActivityLogger: " . $e->getMessage());
        }
    }

    /**
     * Registra una actividad del usuario
     * 
     * @param string $action Acción realizada (ej: 'login', 'create', 'update', 'delete')
     * @param string|null $module Módulo o sección (ej: 'clientes', 'creditos', 'admin')
     * @param array $data Datos adicionales de contexto
     * @param int|string|null $userId ID del usuario (si no se proporciona, se intenta obtener de la sesión)
     * @return bool True si se registró correctamente, false en caso contrario
     */
    public static function log(
        string $action,
        ?string $module = null,
        array $data = [],
        $userId = null
    ): bool {
        // Inicializar si no se ha hecho
        if (!self::$initialized) {
            self::initialize();
        }

        // Si el logger no está habilitado, retornar true sin hacer nada
        if (!self::$enabled || !self::$collection) {
            return true;
        }

        try {
            // Obtener el user_id de la sesión si no se proporciona
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }

            // Obtener información de la petición
            $ip = self::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

            // Preparar el documento
            $document = [
                'user_id' => $userId,
                'action' => $action,
                'module' => $module,
                'ip_address' => $ip,
                'user_agent' => $userAgent,
                'request_uri' => $requestUri,
                'request_method' => $requestMethod,
                'data' => $data,
                'timestamp' => new UTCDateTime(),
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Agregar información del usuario si está disponible en sesión
            if (isset($_SESSION['username'])) {
                $document['username'] = $_SESSION['username'];
            }
            if (isset($_SESSION['user_type'])) {
                $document['user_type'] = $_SESSION['user_type'];
            }

            // Insertar el documento
            self::$collection->insertOne($document);
            
            return true;
            
        } catch (Exception $e) {
            // error_log("Error registrando actividad en UserActivityLogger: " . $e->getMessage());
            Log::error("Error registrando actividad en UserActivityLogger: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene la IP real del cliente
     * 
     * @return string IP del cliente
     */
    private static function getClientIp(): string
    {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ipKeys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
    }

    /**
     * Obtiene las actividades de un usuario
     * 
     * @param int|string $userId ID del usuario
     * @param int $limit Límite de registros
     * @param int $skip Registros a saltar (paginación)
     * @param array $filters Filtros adicionales
     * @return array Array de actividades
     */
    public static function getUserActivities(
        $userId,
        int $limit = 100,
        int $skip = 0,
        array $filters = []
    ): array {
        if (!self::$initialized) {
            self::initialize();
        }

        if (!self::$enabled || !self::$collection) {
            return [];
        }

        try {
            $query = array_merge(['user_id' => $userId], $filters);
            
            $cursor = self::$collection->find(
                $query,
                [
                    'sort' => ['timestamp' => -1],
                    'limit' => $limit,
                    'skip' => $skip
                ]
            );

            return $cursor->toArray();
            
        } catch (Exception $e) {
            // error_log("Error obteniendo actividades: " . $e->getMessage());
            Log::error("Error obteniendo actividades: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene actividades por acción
     * 
     * @param string $action Acción a buscar
     * @param int $limit Límite de registros
     * @param array $filters Filtros adicionales
     * @return array Array de actividades
     */
    public static function getByAction(
        string $action,
        int $limit = 100,
        array $filters = []
    ): array {
        if (!self::$initialized) {
            self::initialize();
        }

        if (!self::$enabled || !self::$collection) {
            return [];
        }

        try {
            $query = array_merge(['action' => $action], $filters);
            
            $cursor = self::$collection->find(
                $query,
                [
                    'sort' => ['timestamp' => -1],
                    'limit' => $limit
                ]
            );

            return $cursor->toArray();
            
        } catch (Exception $e) {
            // error_log("Error obteniendo actividades por acción: " . $e->getMessage());
            Log::error("Error obteniendo actividades por acción: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene actividades por módulo
     * 
     * @param string $module Módulo a buscar
     * @param int $limit Límite de registros
     * @param array $filters Filtros adicionales
     * @return array Array de actividades
     */
    public static function getByModule(
        string $module,
        int $limit = 100,
        array $filters = []
    ): array {
        if (!self::$initialized) {
            self::initialize();
        }

        if (!self::$enabled || !self::$collection) {
            return [];
        }

        try {
            $query = array_merge(['module' => $module], $filters);
            
            $cursor = self::$collection->find(
                $query,
                [
                    'sort' => ['timestamp' => -1],
                    'limit' => $limit
                ]
            );

            return $cursor->toArray();
            
        } catch (Exception $e) {
            // error_log("Error obteniendo actividades por módulo: " . $e->getMessage());
            Log::error("Error obteniendo actividades por módulo: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene actividades en un rango de fechas
     * 
     * @param string $startDate Fecha inicial (Y-m-d H:i:s)
     * @param string $endDate Fecha final (Y-m-d H:i:s)
     * @param int $limit Límite de registros
     * @param array $filters Filtros adicionales
     * @return array Array de actividades
     */
    public static function getByDateRange(
        string $startDate,
        string $endDate,
        int $limit = 1000,
        array $filters = []
    ): array {
        if (!self::$initialized) {
            self::initialize();
        }

        if (!self::$enabled || !self::$collection) {
            return [];
        }

        try {
            $start = new UTCDateTime(strtotime($startDate) * 1000);
            $end = new UTCDateTime(strtotime($endDate) * 1000);
            
            $query = array_merge(
                [
                    'timestamp' => [
                        '$gte' => $start,
                        '$lte' => $end
                    ]
                ],
                $filters
            );
            
            $cursor = self::$collection->find(
                $query,
                [
                    'sort' => ['timestamp' => -1],
                    'limit' => $limit
                ]
            );

            return $cursor->toArray();
            
        } catch (Exception $e) {
            // error_log("Error obteniendo actividades por rango de fechas: " . $e->getMessage());
            Log::error("Error obteniendo actividades por rango de fechas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Cuenta las actividades según filtros
     * 
     * @param array $filters Filtros a aplicar
     * @return int Cantidad de documentos
     */
    public static function count(array $filters = []): int
    {
        if (!self::$initialized) {
            self::initialize();
        }

        if (!self::$enabled || !self::$collection) {
            return 0;
        }

        try {
            return self::$collection->countDocuments($filters);
        } catch (Exception $e) {
            // error_log("Error contando actividades: " . $e->getMessage());
            Log::error("Error contando actividades: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Obtiene estadísticas de uso
     * 
     * @param int|string|null $userId ID del usuario (opcional)
     * @param string|null $startDate Fecha inicial (opcional)
     * @param string|null $endDate Fecha final (opcional)
     * @return array Estadísticas
     */
    public static function getStatistics(
        $userId = null,
        ?string $startDate = null,
        ?string $endDate = null
    ): array {
        if (!self::$initialized) {
            self::initialize();
        }

        if (!self::$enabled || !self::$collection) {
            return [];
        }

        try {
            $match = [];
            
            if ($userId !== null) {
                $match['user_id'] = $userId;
            }
            
            if ($startDate && $endDate) {
                $match['timestamp'] = [
                    '$gte' => new UTCDateTime(strtotime($startDate) * 1000),
                    '$lte' => new UTCDateTime(strtotime($endDate) * 1000)
                ];
            }

            $pipeline = [];
            
            if (!empty($match)) {
                $pipeline[] = ['$match' => $match];
            }

            $pipeline[] = [
                '$group' => [
                    '_id' => [
                        'action' => '$action',
                        'module' => '$module'
                    ],
                    'count' => ['$sum' => 1]
                ]
            ];

            $pipeline[] = [
                '$sort' => ['count' => -1]
            ];

            $result = self::$collection->aggregate($pipeline);
            
            return $result->toArray();
            
        } catch (Exception $e) {
            // error_log("Error obteniendo estadísticas: " . $e->getMessage());
            Log::error("Error obteniendo estadísticas: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Elimina actividades antiguas
     * 
     * @param int $daysToKeep Días a mantener (por defecto 90 días)
     * @return int Cantidad de documentos eliminados
     */
    public static function purgeOldActivities(int $daysToKeep = 90): int
    {
        if (!self::$initialized) {
            self::initialize();
        }

        if (!self::$enabled || !self::$collection) {
            return 0;
        }

        try {
            $cutoffDate = new UTCDateTime(strtotime("-{$daysToKeep} days") * 1000);
            
            $result = self::$collection->deleteMany([
                'timestamp' => ['$lt' => $cutoffDate]
            ]);

            return $result->getDeletedCount();
            
        } catch (Exception $e) {
            // error_log("Error purgando actividades antiguas: " . $e->getMessage());
            Log::error("Error purgando actividades antiguas: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Verifica si el logger está habilitado
     * 
     * @return bool
     */
    public static function isEnabled(): bool
    {
        if (!self::$initialized) {
            self::initialize();
        }
        
        return self::$enabled;
    }

    /**
     * Cierra la conexión a MongoDB
     * 
     * @return void
     */
    public static function close(): void
    {
        self::$mongoClient = null;
        self::$collection = null;
        self::$initialized = false;
    }
}
