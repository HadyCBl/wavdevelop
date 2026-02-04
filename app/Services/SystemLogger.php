<?php

namespace Micro\Services;

use PDO;
use PDOException;
use Exception;
use App\DatabaseAdapter;
use Micro\Generic\Auth;
use Micro\Helpers\Log;

/**
 * SystemLogger - Servicio para registrar acciones de usuarios en el sistema
 * 
 * Maneja el registro de todas las acciones importantes realizadas por usuarios
 * en la tabla system_logs. Puede usar una conexión PDO existente o DatabaseAdapter.
 * 
 * @package App\Services
 */
class SystemLogger
{
    /**
     * @var PDO|null Conexión PDO compartida para múltiples logs
     */
    private static ?PDO $sharedConnection = null;

    /**
     * @var DatabaseAdapter|null Instancia de DatabaseAdapter compartida
     */
    private static ?DatabaseAdapter $sharedAdapter = null;

    /**
     * Tipos de acciones soportadas
     */
    public const ACTION_CREATE = 'CREATE';
    public const ACTION_UPDATE = 'UPDATE';
    public const ACTION_DELETE = 'DELETE';
    public const ACTION_LOGIN = 'LOGIN';
    public const ACTION_LOGOUT = 'LOGOUT';
    public const ACTION_GENERAR_REPORTE = 'GENERAR_REPORTE';
    public const ACTION_APROBAR = 'APROBAR';
    public const ACTION_RECHAZAR = 'RECHAZAR';
    public const ACTION_PAGAR = 'PAGAR';
    public const ACTION_EXPORT = 'EXPORT';
    public const ACTION_IMPORT = 'IMPORT';
    public const ACTION_VIEW = 'VIEW';
    public const ACTION_DOWNLOAD = 'DOWNLOAD';
    public const ACTION_UPLOAD = 'UPLOAD';

    /**
     * Establece una conexión PDO compartida para usar en todos los logs
     * 
     * @param PDO $connection Conexión PDO a usar
     * @return void
     */
    public static function setConnection(PDO $connection): void
    {
        self::$sharedConnection = $connection;
    }

    /**
     * Establece una instancia de DatabaseAdapter compartida
     * 
     * @param DatabaseAdapter $adapter Instancia de DatabaseAdapter
     * @return void
     */
    public static function setAdapter(DatabaseAdapter $adapter): void
    {
        self::$sharedAdapter = $adapter;
    }

    /**
     * Registra una acción usando DatabaseAdapter
     * 
     * @param DatabaseAdapter $adapter Instancia de DatabaseAdapter
     * @param array $data Datos a insertar
     * @return bool
     */
    private static function insertWithAdapter(DatabaseAdapter $adapter, array $data): bool
    {
        try {
            $adapter->openConnection();
            
            $result = $adapter->insert('system_logs', $data);
            
            $adapter->closeConnection();
            
            return $result !== false;
        } catch (Exception $e) {
            // error_log("SystemLogger Error con DatabaseAdapter: " . $e->getMessage());
            Log::error("SystemLogger Error con DatabaseAdapter: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene o crea una instancia de DatabaseAdapter
     * 
     * @return DatabaseAdapter|null
     */
    private static function getAdapter(): ?DatabaseAdapter
    {
        if (self::$sharedAdapter !== null) {
            return self::$sharedAdapter;
        }

        try {
            return new DatabaseAdapter();
        } catch (Exception $e) {
            // error_log("SystemLogger Error al crear DatabaseAdapter: " . $e->getMessage());
            Log::error("SystemLogger Error al crear DatabaseAdapter: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Registra una acción en el sistema
     * 
     * @param string $action Tipo de acción (usar constantes ACTION_*)
     * @param string|null $entity Tabla o módulo afectado
     * @param int|null $entityId ID del registro afectado
     * @param string|null $description Descripción legible de la acción
     * @param array|null $oldData Datos anteriores (se convertirán a JSON)
     * @param array|null $newData Datos nuevos (se convertirán a JSON)
     * @param int|null $userId ID del usuario que ejecuta la acción
     * @param PDO|null $connection Conexión PDO personalizada (opcional)
     * @return bool True si se registró exitosamente
     */
    public static function log(
        string $action,
        ?string $entity = null,
        ?int $entityId = null,
        ?string $description = null,
        ?array $oldData = null,
        ?array $newData = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        try {
            // Obtener información del usuario si no se proporciona
            if ($userId === null) {
                $userId = Auth::getUserId();
                // Convertir a int si es string numérico
                if (is_string($userId) && is_numeric($userId)) {
                    $userId = (int)$userId;
                } elseif (!is_int($userId)) {
                    $userId = null;
                }
            }

            // Capturar IP y User Agent
            $ipAddress = self::getClientIp();
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

            // Convertir arrays a JSON si existen
            $oldDataJson = $oldData ? json_encode($oldData, JSON_UNESCAPED_UNICODE) : null;
            $newDataJson = $newData ? json_encode($newData, JSON_UNESCAPED_UNICODE) : null;

            // Datos a insertar
            $data = [
                'user_id' => $userId,
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'description' => $description,
                'old_data' => $oldDataJson,
                'new_data' => $newDataJson,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => date('Y-m-d H:i:s')
            ];

            // Si se proporciona una conexión PDO personalizada, usarla
            if ($connection !== null) {
                return self::insertWithPDO($connection, $data);
            }

            // Si hay una conexión compartida, usarla
            if (self::$sharedConnection !== null) {
                return self::insertWithPDO(self::$sharedConnection, $data);
            }

            // Usar DatabaseAdapter como método por defecto
            $adapter = self::getAdapter();
            if ($adapter === null) {
                return false;
            }

            return self::insertWithAdapter($adapter, $data);
            
        } catch (Exception $e) {
            // Log silencioso - no queremos que falle la operación principal
            // error_log("SystemLogger Error: " . $e->getMessage());
            Log::error("SystemLogger Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Inserta un log usando conexión PDO directa
     * 
     * @param PDO $pdo Conexión PDO
     * @param array $data Datos a insertar
     * @return bool
     */
    private static function insertWithPDO(PDO $pdo, array $data): bool
    {
        try {
            $sql = "INSERT INTO system_logs (
                user_id,
                action,
                entity,
                entity_id,
                description,
                old_data,
                new_data,
                ip_address,
                user_agent,
                created_at
            ) VALUES (
                :user_id,
                :action,
                :entity,
                :entity_id,
                :description,
                :old_data,
                :new_data,
                :ip_address,
                :user_agent,
                :created_at
            )";

            $stmt = $pdo->prepare($sql);
            
            return $stmt->execute([
                ':user_id' => $data['user_id'],
                ':action' => $data['action'],
                ':entity' => $data['entity'],
                ':entity_id' => $data['entity_id'],
                ':description' => $data['description'],
                ':old_data' => $data['old_data'],
                ':new_data' => $data['new_data'],
                ':ip_address' => $data['ip_address'],
                ':user_agent' => $data['user_agent'],
                ':created_at' => $data['created_at'],
            ]);
        } catch (Exception $e) {
            // error_log("SystemLogger Error insertWithPDO: " . $e->getMessage());
            Log::error("SystemLogger Error insertWithPDO: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Registra una acción de creación
     * 
     * @param string $entity Tabla o módulo
     * @param int $entityId ID del registro creado
     * @param array $newData Datos del nuevo registro
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function create(
        string $entity,
        int $entityId,
        array $newData,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Creó registro en {$entity} con ID {$entityId}";
        
        return self::log(
            self::ACTION_CREATE,
            $entity,
            $entityId,
            $desc,
            null,
            $newData,
            $userId,
            $connection
        );
    }

    /**
     * Registra una acción de actualización
     * 
     * @param string $entity Tabla o módulo
     * @param int $entityId ID del registro actualizado
     * @param array $oldData Datos anteriores
     * @param array $newData Datos nuevos
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function update(
        string $entity,
        int $entityId,
        array $oldData,
        array $newData,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Actualizó registro en {$entity} con ID {$entityId}";
        
        return self::log(
            self::ACTION_UPDATE,
            $entity,
            $entityId,
            $desc,
            $oldData,
            $newData,
            $userId,
            $connection
        );
    }

    /**
     * Registra una acción de eliminación
     * 
     * @param string $entity Tabla o módulo
     * @param int $entityId ID del registro eliminado
     * @param array $oldData Datos del registro eliminado
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function delete(
        string $entity,
        int $entityId,
        array $oldData,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Eliminó registro en {$entity} con ID {$entityId}";
        
        return self::log(
            self::ACTION_DELETE,
            $entity,
            $entityId,
            $desc,
            $oldData,
            null,
            $userId,
            $connection
        );
    }

    /**
     * Registra un inicio de sesión
     * 
     * @param int $userId ID del usuario que inicia sesión
     * @param bool $success Si el login fue exitoso
     * @param string|null $description Descripción adicional
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function login(
        int $userId,
        bool $success = true,
        ?string $description = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? ($success ? "Inicio de sesión exitoso" : "Intento de inicio de sesión fallido");
        
        return self::log(
            self::ACTION_LOGIN,
            'auth',
            $userId,
            $desc,
            null,
            ['success' => $success, 'timestamp' => date('Y-m-d H:i:s')],
            $userId,
            $connection
        );
    }

    /**
     * Registra un cierre de sesión
     * 
     * @param int $userId ID del usuario
     * @param string|null $description Descripción adicional
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function logout(
        int $userId,
        ?string $description = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Cierre de sesión";
        
        return self::log(
            self::ACTION_LOGOUT,
            'auth',
            $userId,
            $desc,
            null,
            ['timestamp' => date('Y-m-d H:i:s')],
            $userId,
            $connection
        );
    }

    /**
     * Registra la generación de un reporte
     * 
     * @param string $reportName Nombre del reporte
     * @param array|null $params Parámetros utilizados
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function generateReport(
        string $reportName,
        ?array $params = null,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Generó reporte: {$reportName}";
        
        return self::log(
            self::ACTION_GENERAR_REPORTE,
            'reportes',
            null,
            $desc,
            null,
            ['report_name' => $reportName, 'params' => $params],
            $userId,
            $connection
        );
    }

    /**
     * Registra una aprobación
     * 
     * @param string $entity Entidad aprobada
     * @param int $entityId ID del registro aprobado
     * @param array|null $data Datos de la aprobación
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function approve(
        string $entity,
        int $entityId,
        ?array $data = null,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Aprobó registro en {$entity} con ID {$entityId}";
        
        return self::log(
            self::ACTION_APROBAR,
            $entity,
            $entityId,
            $desc,
            null,
            $data,
            $userId,
            $connection
        );
    }

    /**
     * Registra un rechazo
     * 
     * @param string $entity Entidad rechazada
     * @param int $entityId ID del registro rechazado
     * @param array|null $data Datos del rechazo (motivo, etc)
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function reject(
        string $entity,
        int $entityId,
        ?array $data = null,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Rechazó registro en {$entity} con ID {$entityId}";
        
        return self::log(
            self::ACTION_RECHAZAR,
            $entity,
            $entityId,
            $desc,
            null,
            $data,
            $userId,
            $connection
        );
    }

    /**
     * Registra un pago
     * 
     * @param string $entity Entidad del pago
     * @param int $entityId ID del registro de pago
     * @param array $paymentData Datos del pago (monto, método, etc)
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function payment(
        string $entity,
        int $entityId,
        array $paymentData,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Registró pago en {$entity} con ID {$entityId}";
        
        return self::log(
            self::ACTION_PAGAR,
            $entity,
            $entityId,
            $desc,
            null,
            $paymentData,
            $userId,
            $connection
        );
    }

    /**
     * Registra una exportación de datos
     * 
     * @param string $module Módulo exportado
     * @param string $format Formato de exportación (CSV, Excel, PDF, etc)
     * @param array|null $filters Filtros aplicados
     * @param string|null $description Descripción adicional
     * @param int|null $userId ID del usuario
     * @param PDO|null $connection Conexión PDO
     * @return bool
     */
    public static function export(
        string $module,
        string $format,
        ?array $filters = null,
        ?string $description = null,
        ?int $userId = null,
        ?PDO $connection = null
    ): bool {
        $desc = $description ?? "Exportó datos de {$module} a formato {$format}";
        
        return self::log(
            self::ACTION_EXPORT,
            $module,
            null,
            $desc,
            null,
            ['format' => $format, 'filters' => $filters],
            $userId,
            $connection
        );
    }

    /**
     * Obtiene la IP real del cliente
     * 
     * @return string|null
     */
    private static function getClientIp(): ?string
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
                $ip = $_SERVER[$key];
                
                // Si hay múltiples IPs (proxy), tomar la primera
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validar que sea una IP válida
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return null;
    }

    /**
     * Obtiene los últimos logs de un usuario
     * 
     * @param int $userId ID del usuario
     * @param int $limit Cantidad de registros a obtener
     * @param PDO|null $connection Conexión PDO
     * @return array
     */
    public static function getUserLogs(int $userId, int $limit = 50, ?PDO $connection = null): array
    {
        try {
            // Si hay conexión PDO (personalizada o compartida)
            if ($connection !== null || self::$sharedConnection !== null) {
                $pdo = $connection ?? self::$sharedConnection;
                
                $sql = "SELECT * FROM system_logs 
                        WHERE user_id = :user_id 
                        ORDER BY created_at DESC 
                        LIMIT :limit";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll();
            }

            // Usar DatabaseAdapter
            $adapter = self::getAdapter();
            if ($adapter === null) {
                return [];
            }

            $adapter->openConnection();
            
            $result = $adapter->selectColumns(
                'system_logs',
                ['*'],
                'user_id = ? ORDER BY created_at DESC LIMIT ' . (int)$limit,
                [$userId]
            );
            
            $adapter->closeConnection();
            
            return $result ?: [];
            
        } catch (Exception $e) {
            // error_log("SystemLogger Error al obtener logs: " . $e->getMessage());
            Log::error("SystemLogger Error al obtener logs: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Obtiene logs de una entidad específica
     * 
     * @param string $entity Nombre de la entidad
     * @param int|null $entityId ID específico (opcional)
     * @param int $limit Cantidad de registros
     * @param PDO|null $connection Conexión PDO
     * @return array
     */
    public static function getEntityLogs(
        string $entity,
        ?int $entityId = null,
        int $limit = 50,
        ?PDO $connection = null
    ): array {
        try {
            // Si hay conexión PDO (personalizada o compartida)
            if ($connection !== null || self::$sharedConnection !== null) {
                $pdo = $connection ?? self::$sharedConnection;
                
                $sql = "SELECT * FROM system_logs WHERE entity = :entity";
                
                if ($entityId !== null) {
                    $sql .= " AND entity_id = :entity_id";
                }
                
                $sql .= " ORDER BY created_at DESC LIMIT :limit";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':entity', $entity, PDO::PARAM_STR);
                
                if ($entityId !== null) {
                    $stmt->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
                }
                
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->fetchAll();
            }

            // Usar DatabaseAdapter
            $adapter = self::getAdapter();
            if ($adapter === null) {
                return [];
            }

            $adapter->openConnection();
            
            $condition = 'entity = ?';
            $params = [$entity];
            
            if ($entityId !== null) {
                $condition .= ' AND entity_id = ?';
                $params[] = $entityId;
            }
            
            $condition .= ' ORDER BY created_at DESC LIMIT ' . (int)$limit;
            
            $result = $adapter->selectColumns(
                'system_logs',
                ['*'],
                $condition,
                $params
            );
            
            $adapter->closeConnection();
            
            return $result ?: [];
            
        } catch (Exception $e) {
            // error_log("SystemLogger Error al obtener logs de entidad: " . $e->getMessage());
            Log::error("SystemLogger Error al obtener logs de entidad: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Limpia logs antiguos (útil para mantenimiento)
     * 
     * @param int $daysToKeep Días de logs a mantener
     * @param PDO|null $connection Conexión PDO
     * @return int Cantidad de registros eliminados
     */
    public static function cleanOldLogs(int $daysToKeep = 365, ?PDO $connection = null): int
    {
        try {
            // Si hay conexión PDO (personalizada o compartida)
            if ($connection !== null || self::$sharedConnection !== null) {
                $pdo = $connection ?? self::$sharedConnection;
                
                $sql = "DELETE FROM system_logs 
                        WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':days', $daysToKeep, PDO::PARAM_INT);
                $stmt->execute();
                
                return $stmt->rowCount();
            }

            // Usar DatabaseAdapter
            $adapter = self::getAdapter();
            if ($adapter === null) {
                return 0;
            }

            $adapter->openConnection();
            
            $fechaLimite = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
            
            $result = $adapter->delete(
                'system_logs',
                'created_at < ?',
                [$fechaLimite]
            );
            
            $adapter->closeConnection();
            
            return $result ? 1 : 0;
            
        } catch (Exception $e) {
            // error_log("SystemLogger Error al limpiar logs: " . $e->getMessage());
            Log::error("SystemLogger Error al limpiar logs: " . $e->getMessage());
            return 0;
        }
    }
}
