<?php

namespace App\Generic\Models;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class TipoDocumentoTransaccion
{
    // Tipos predefinidos para ahorros y aportaciones (por letras)
    private static $tipos_ahorros_aportaciones = [
        'E' => 'EFECTIVO',
        'C' => 'CHEQUE',
        'D' => 'DEPÓSITOS A BANCOS',
        'T' => 'TRANSFERENCIAS',
        'V' => 'DEP. VINCULADO',
        'IN' => 'INTERÉS',
        'IP' => 'IPF'
    ];

    // Tipos predefinidos para créditos (por números)
    private static $tipos_creditos_pagos = [
        '1' => 'EFECTIVO',
        '2' => 'BOLETA DE BANCO',
        '3' => 'DEBITO DE AHORRO',
        '4' => 'CREF'
    ];

    private static $tipos_creditos_desembolsos = [
        '1' => 'EFECTIVO',
        '2' => 'CHEQUE',
        '3' => 'TRANSFERENCIAS',
    ];

    private static $tipos_otros_movimientos = [
        'efectivo' => 'EFECTIVO',
        'banco' => 'BANCO',
    ];

    // Módulos disponibles
    private static $modulos = [
        1 => 'AHORROS',
        2 => 'APORTACIONES',
        3 => 'CRÉDITOS',
        4 => 'OTROS MOVIMIENTOS'
    ];

    private ?DatabaseAdapter $db = null;

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(1); // Conectar a la BD principal
            } catch (Exception $e) {
                Log::error("Error al conectar a la base de datos: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw new Exception("Error al conectar con la base de datos para configuraciones: " . $e->getMessage());
            }
        }
    }

    private function cerrarDb(): void
    {
        if ($this->db !== null) {
            try {
                $this->db->closeConnection();
                $this->db = null;
            } catch (Exception $e) {
                Log::error("Error al cerrar la conexión a la base de datos: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
            }
        }
    }

    /**
     * Obtiene la descripción del tipo de documento según el tipo y módulo
     * 
     * @param string|int $tipoDocumento El tipo de documento
     * @param int $modulo El módulo (1=Ahorros, 2=Aportaciones, 3=Créditos)
     * @return string|null La descripción del tipo de documento o null si no se encuentra
     */
    public static function getDescripcion($tipoDocumento, $modulo)
    {
        try {
            // Validar módulo
            if (!isset(self::$modulos[$modulo])) {
                return null;
            }

            switch ($modulo) {
                case 1: // Ahorros
                case 2: // Aportaciones
                    return self::getDescripcionAhorrosAportaciones($tipoDocumento);

                case 3: // Créditos
                    return self::getDescripcionCreditos($tipoDocumento);

                default:
                    return null;
            }
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Obtiene la descripción para ahorros y aportaciones
     * 
     * @param string|int $tipoDocumento El tipo de documento
     * @return string|null La descripción del tipo de documento
     */
    private static function getDescripcionAhorrosAportaciones($tipoDocumento)
    {
        // Verificar si es un tipo predefinido (letra)
        if (isset(self::$tipos_ahorros_aportaciones[$tipoDocumento])) {
            return self::$tipos_ahorros_aportaciones[$tipoDocumento];
        }

        // Si es numérico, buscar en la base de datos
        if (is_numeric($tipoDocumento)) {
            return self::getDescripcionPersonalizada($tipoDocumento);
        }

        return '--';
    }

    /**
     * Obtiene la descripción para créditos
     * 
     * @param string|int $tipoDocumento El tipo de documento
     * @return string|null La descripción del tipo de documento
     */
    private static function getDescripcionCreditos($tipoDocumento, $operacion = 'P')
    {
        $catalogoExistente = ($operacion === 'P') ? self::$tipos_creditos_pagos : self::$tipos_creditos_desembolsos;

        // Verificar si es un tipo predefinido
        if (isset($catalogoExistente[$tipoDocumento])) {
            return $catalogoExistente[$tipoDocumento];
        }

        // Log::info("Tipo de documento no predefinido: $tipoDocumento", [
        //     'operacion' => $operacion,
        // ]);

        // Si es un tipo personalizado con prefijo d_X
        if (is_string($tipoDocumento) && strpos($tipoDocumento, 'd_') === 0) {
            $id = str_replace('d_', '', $tipoDocumento);
            if (is_numeric($id)) {
                return self::getDescripcionPersonalizada($id);
            }
        }

        // Si es numérico y no está en los predefinidos, verificar si es personalizado
        if (is_numeric($tipoDocumento) && !isset($catalogoExistente[$tipoDocumento])) {
            return self::getDescripcionPersonalizada($tipoDocumento);
        }

        // return null;
        return "--";
    }

    /**
     * Obtiene la descripción de tipos personalizados desde la base de datos
     * 
     * @param int $id El ID del tipo de documento personalizado
     * @return string|null La descripción del tipo de documento
     */
    private static function getDescripcionPersonalizada($id)
    {
        try {
            $instance = new self();
            $instance->conectarDb();

            $result = $instance->db->selectColumns(
                'tb_documentos_transacciones',
                ['nombre'],
                'id=? AND estado=1',
                [$id]
            );

            $instance->cerrarDb();

            if (!empty($result)) {
                return strtoupper($result[0]['nombre']);
            }

            return '---';
        } catch (Exception $e) {
            Log::error("Error al obtener descripción personalizada: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            return '----';
        }
    }

    /**
     * Obtiene todos los tipos de documentos disponibles para un módulo
     * 
     * @param int $modulo El módulo (1=Ahorros, 2=Aportaciones, 3=Créditos)
     * @return array Array con los tipos de documentos disponibles
     */
    public static function getTiposDisponibles($modulo)
    {
        $tipos = [];

        try {
            switch ($modulo) {
                case 1: // Ahorros
                case 2: // Aportaciones
                    $tipos = self::$tipos_ahorros_aportaciones;

                    // Agregar tipos personalizados
                    $personalizados = self::getTiposPersonalizados($modulo);
                    foreach ($personalizados as $id => $nombre) {
                        $tipos[$id] = $nombre;
                    }
                    break;

                case 3: // Créditos
                    $tipos = self::$tipos_creditos_pagos;

                    // Agregar tipos personalizados
                    $personalizados = self::getTiposPersonalizados($modulo);
                    foreach ($personalizados as $id => $nombre) {
                        $tipos['d_' . $id] = $nombre;
                    }
                    break;
                case 4: // Otros Movimientos
                    $tipos = self::$tipos_otros_movimientos;
                    // Agregar tipos personalizados
                    $personalizados = self::getTiposPersonalizados($modulo);
                    foreach ($personalizados as $id => $nombre) {
                        $tipos[$id] = $nombre;
                    }
                    break;
            }
        } catch (Exception $e) {
            // En caso de error, retornar solo los tipos predefinidos
            switch ($modulo) {
                case 1:
                case 2:
                    return self::$tipos_ahorros_aportaciones;
                case 3:
                    return self::$tipos_creditos_pagos;
                case 4:
                    return self::$tipos_otros_movimientos;
                default:
                    return [];
            }
        }

        return $tipos;
    }

    /**
     * Obtiene los tipos personalizados desde la base de datos
     * 
     * @param int $modulo El módulo
     * @return array Array con los tipos personalizados
     */
    private static function getTiposPersonalizados($modulo)
    {
        $tipos = [];

        try {
            $instance = new self();
            $instance->conectarDb();

            $result = $instance->db->selectColumns(
                'tb_documentos_transacciones',
                ['id', 'nombre'],
                'id_modulo=? AND estado=1',
                [$modulo]
            );

            $instance->cerrarDb();

            foreach ($result as $row) {
                $tipos[$row['id']] = strtoupper($row['nombre']);
            }
        } catch (Exception $e) {
            // En caso de error, retornar array vacío
        }

        return $tipos;
    }

    /**
     * Verifica si un tipo de documento es válido para un módulo
     * 
     * @param string|int $tipoDocumento El tipo de documento
     * @param int $modulo El módulo
     * @return bool True si el tipo es válido, false en caso contrario
     */
    public static function esValido($tipoDocumento, $modulo)
    {
        return self::getDescripcion($tipoDocumento, $modulo) !== null;
    }

    /**
     * Obtiene el nombre del módulo
     * 
     * @param int $modulo El ID del módulo
     * @return string|null El nombre del módulo o null si no existe
     */
    public static function getNombreModulo($modulo)
    {
        return isset(self::$modulos[$modulo]) ? self::$modulos[$modulo] : null;
    }

    /**
     * Obtiene todos los módulos disponibles
     * 
     * @return array Array con todos los módulos
     */
    public static function getModulos()
    {
        return self::$modulos;
    }

    /**
     * Convierte un tipo de documento de créditos personalizado al formato con prefijo
     * 
     * @param int $id El ID del tipo de documento personalizado
     * @return string El tipo con prefijo d_X
     */
    public static function formatearTipoCreditoPersonalizado($id)
    {
        return 'd_' . $id;
    }

    /**
     * Extrae el ID de un tipo de documento de créditos con prefijo
     * 
     * @param string $tipoConPrefijo El tipo con prefijo d_X
     * @return int|null El ID extraído o null si no es válido
     */
    public static function extraerIdDeTipoCreditoPersonalizado($tipoConPrefijo)
    {
        if (is_string($tipoConPrefijo) && strpos($tipoConPrefijo, 'd_') === 0) {
            $id = str_replace('d_', '', $tipoConPrefijo);
            return is_numeric($id) ? (int)$id : null;
        }
        return null;
    }
}
