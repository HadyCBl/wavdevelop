<?php

namespace App\Generic;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class DocumentManager
{
    private ?DatabaseAdapter $db = null;
    private $table = 'tb_configuraciones_documentos';

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(1); // Asumiendo que la tabla está en la BD principal
            } catch (Exception $e) {
                Log::error(
                    "Error al conectar BD para DocumentManager: " . $e->getMessage(),
                    ['file' => $e->getFile(), 'line' => $e->getLine()]
                );
                throw new Exception("Error crítico al conectar BD para DocumentManager.", 0, $e);
            }
        }
    }
    public function peekNextCorrelative($params = [])
    {
        return $this->getNextCorrelative($params);
    }
    /**
     * Obtiene el siguiente correlativo según los parámetros dados, siguiendo esta jerarquía:
     * 1. Configuración específica (módulo + tipo + usuario + agencia)
     * 2. Configuración por módulo + tipo + agencia
     * 3. Configuración por módulo + tipo + usuario
     * 4. Configuración por módulo + tipo
     * 5. Configuración por módulo
     * 6. Configuración general (todos NULL)
     *
     * @param array $params Parámetros para buscar la configuración
     * @return string Siguiente correlativo
     * @throws Exception Si no encuentra ninguna configuración aplicable
     */
    public function getNextCorrelative($params = [])
    {
        $this->conectarDb();

        try {
            // Definir las combinaciones de búsqueda en orden de prioridad
            $searches = [
                // 1. Específica: módulo + tipo + usuario + agencia
                [
                    'where' => 'id_modulo = :id_modulo AND tipo = :tipo AND usuario_id = :usuario_id AND agencia_id = :agencia_id AND deleted_at IS NULL',
                    'params' => ['id_modulo', 'tipo', 'usuario_id', 'agencia_id']
                ],
                // 2. módulo + tipo + agencia
                [
                    'where' => 'id_modulo = :id_modulo AND tipo = :tipo AND agencia_id = :agencia_id AND usuario_id IS NULL AND deleted_at IS NULL',
                    'params' => ['id_modulo', 'tipo', 'agencia_id']
                ],
                // 3. módulo + tipo + usuario
                [
                    'where' => 'id_modulo = :id_modulo AND tipo = :tipo AND usuario_id = :usuario_id AND agencia_id IS NULL AND deleted_at IS NULL',
                    'params' => ['id_modulo', 'tipo', 'usuario_id']
                ],
                // 4. módulo + tipo
                [
                    'where' => 'id_modulo = :id_modulo AND tipo = :tipo AND usuario_id IS NULL AND agencia_id IS NULL AND deleted_at IS NULL',
                    'params' => ['id_modulo', 'tipo']
                ],
                // 5. módulo
                [
                    'where' => 'id_modulo = :id_modulo AND tipo IS NULL AND usuario_id IS NULL AND agencia_id IS NULL AND deleted_at IS NULL',
                    'params' => ['id_modulo']
                ],
                // 6. General
                [
                    'where' => 'id_modulo IS NULL AND tipo IS NULL AND usuario_id IS NULL AND agencia_id IS NULL AND deleted_at IS NULL',
                    'params' => []
                ],
            ];

            foreach ($searches as $search) {
                // Verificar si existen los parámetros requeridos para esta búsqueda
                $hasAllParams = true;
                $queryParams = [];
                foreach ($search['params'] as $param) {
                    if (!isset($params[$param])) {
                        $hasAllParams = false;
                        break;
                    }
                    $queryParams[":" . $param] = $params[$param];
                }
                if (!$hasAllParams && count($search['params']) > 0) {
                    continue;
                }

                $query = "SELECT id, valor_actual FROM {$this->table} WHERE {$search['where']} LIMIT 1";
                $config = $this->db->getSingleResult($query, $queryParams);
                if ($config) {
                    $nextValue = $this->incrementCorrelative($config['valor_actual']);
                    // Actualizar el valor en la base de datos (TEMPORAL)
                    $this->db->update(
                        $this->table,
                        ['valor_actual' => $nextValue, 'updated_at' => date('Y-m-d H:i:s')],
                        "id = ?",
                        [$config['id']]
                    );
                    // FIN TEMPORAL
                    // Retornar el siguiente valor y el ID de configuración
                    return [
                        'valor' => $nextValue,
                        'config_id' => $config['id']
                    ];
                }
            }
            return [
                'valor' => '',
                'config_id' => 0
            ];
        } catch (Exception $e) {
            Log::error("Error al obtener correlativo: " . $e->getMessage());
            throw new Exception("Error al obtener correlativo: " . $e->getMessage());
        }
    }

    /**
     * Incrementa el valor del correlativo
     */
    private function incrementCorrelative($currentValue)
    {
        // Separar prefijo y número
        preg_match('/([A-Za-z-]*)(\d+)/', $currentValue, $matches);

        $prefix = $matches[1] ?? '';
        $number = $matches[2] ?? '0';

        // Incrementar el número
        $nextNumber = intval($number) + 1;

        // Mantener el mismo formato de longitud con ceros a la izquierda
        $paddedNumber = str_pad($nextNumber, strlen($number), '0', STR_PAD_LEFT);

        return $prefix . $paddedNumber;
    }

    /**
     * Crea una nueva configuración de correlativo
     */
    public function createConfiguration($data)
    {
        $this->conectarDb();

        try {
            $required = ['valor_actual', 'created_by'];
            foreach ($required as $field) {
                if (!isset($data[$field])) {
                    throw new Exception("El campo {$field} es requerido");
                }
            }

            $data['created_at'] = date('Y-m-d H:i:s');
            return $this->db->insert($this->table, $data);
        } catch (Exception $e) {
            Log::error("Error al crear configuración: " . $e->getMessage());
            throw new Exception("Error al crear configuración de documento.", 0, $e);
        }
    }

    /**
     * Actualiza una configuración existente
     */
    public function updateConfiguration($id, $data)
    {
        $this->conectarDb();

        try {
            $data['updated_at'] = date('Y-m-d H:i:s');
            return $this->db->update(
                $this->table,
                $data,
                "id = :id",
                [':id' => $id]
            );
        } catch (Exception $e) {
            Log::error("Error al actualizar configuración ID {$id}: " . $e->getMessage());
            throw new Exception("Error al actualizar configuración de documento.", 0, $e);
        }
    }

    /**
     * Elimina lógicamente una configuración
     */
    public function deleteConfiguration($id, $deletedBy)
    {
        $this->conectarDb();

        try {
            return $this->db->update(
                $this->table,
                [
                    'deleted_by' => $deletedBy,
                    'deleted_at' => date('Y-m-d H:i:s')
                ],
                "id = :id",
                [':id' => $id]
            );
        } catch (Exception $e) {
            Log::error("Error al eliminar configuración ID {$id}: " . $e->getMessage());
            throw new Exception("Error al eliminar configuración de documento.", 0, $e);
        }
    }

    /**
     * Valida si existe una configuración similar
     */
    public function validateDuplicateConfig($params)
    {
        $conditions = [];
        $queryParams = [];

        if (isset($params['id_modulo'])) {
            $conditions[] = "(id_modulo = :id_modulo OR id_modulo IS NULL)";
            $queryParams[':id_modulo'] = $params['id_modulo'];
        }

        if (isset($params['tipo'])) {
            $conditions[] = "(tipo = :tipo OR tipo IS NULL)";
            $queryParams[':tipo'] = $params['tipo'];
        }

        if (isset($params['usuario_id'])) {
            $conditions[] = "(usuario_id = :usuario_id OR usuario_id IS NULL)";
            $queryParams[':usuario_id'] = $params['usuario_id'];
        }

        if (isset($params['agencia_id'])) {
            $conditions[] = "(agencia_id = :agencia_id OR agencia_id IS NULL)";
            $queryParams[':agencia_id'] = $params['agencia_id'];
        }

        $conditions[] = "deleted_at IS NULL";

        if (isset($params['id'])) {
            $conditions[] = "id != :id";
            $queryParams[':id'] = $params['id'];
        }

        $whereClause = implode(" AND ", $conditions);
        $query = "SELECT COUNT(*) FROM {$this->table} WHERE {$whereClause}";

        return $this->db->selectEspecial($query, $queryParams, 0) > 0;
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}

// // Ejemplo de cómo usar la clase
// try {
//     // Inicializar el manejador de documentos
//     $documentManager = new DocumentManager($database);

//     // Obtener siguiente correlativo para un pago de crédito
//     $correlativoPago = $documentManager->getNextCorrelative([
//         'id_modulo' => 3, // Módulo de créditos
//         'tipo' => 'INGRESO',
//         'agencia_id' => 1,
//         'usuario_id' => 5
//     ]);

//     // Crear una nueva configuración
//     $nuevaConfig = $documentManager->createConfiguration([
//         'id_modulo' => 2,
//         'tipo' => 'EGRESO',
//         'valor_actual' => 'RET-00001',
//         'agencia_id' => 1,
//         'created_by' => 1
//     ]);

//     // Actualizar una configuración
//     $documentManager->updateConfiguration(1, [
//         'valor_actual' => 'DEP-00100',
//         'updated_by' => 1
//     ]);

//     // Eliminar una configuración
//     $documentManager->deleteConfiguration(1, 1);

// } catch (Exception $e) {
//     echo "Error: " . $e->getMessage();
// }