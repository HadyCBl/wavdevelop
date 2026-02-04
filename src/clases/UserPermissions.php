<?php

namespace App\Generic;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class UserPermissions
{
    private ?DatabaseAdapter $db = null;
    private ?int $userId = null;
    private array $cachedPermissions = [];
    private array $permissionsByClass = [];


    // Definición de módulos
    public const MODIFICAR_INTERES = 'MODIFICAR_INTERES';
    public const INGRESOS_DIARIOS = 'INGRESOS_DIARIOS';
    public const SEPARACION_DESEMBOLSOS = 'SEPARACION_DESEMBOLSOS';
    public const APERTURA_CAJA = 'APERTURA_CAJA';
    public const CIERRE_CAJA = 'CIERRE_CAJA';
    public const REPORTE_BALANCE_COMPROBACION = 'REPORTE_BALANCE_COMPROBACION';
    public const APERTURA_CUENTA_SECUNDARIA_AHORROS = 'APERTURA_CUENTA_SECUNDARIA_AHORROS';
    public const APERTURA_CUENTA_SECUNDARIA_APORTACIONES = 'APERTURA_CUENTA_SECUNDARIA_APORTACIONES';
    public const APROBAR_RECHAZAR_MOVIMIENTOS_CAJA = 'APROBAR_RECHAZAR_MOVIMIENTOS_CAJA';
    public const VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA = 'VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA';
    public const BALANCE_Y_ER='BALANCE_Y_ER';
    public const SALDOS_CARTERA = 'SALDOS_CARTERA';
    public const ASIGNAR_ENCARGADO_AHORROS = 'ASIGNAR_ENCARGADO_AHORROS';
    public const ASIGNAR_ENCARGADO_APORTACIONES = 'ASIGNAR_ENCARGADO_APORTACIONES';
    public const REPORTE_CREDITOS_DESEMBOLSADOS = 'REPORTE_CREDITOS_DESEMBOLSADOS';
    public const REIMPRESION_CHEQUES = 'REIMPRESION_CHEQUES';
    public const LIBRO_DIARIO = 'LIBRO_DIARIO';
    public const LIBRO_MAYOR = 'LIBRO_MAYOR';
    public const ESTADO_PATRIMONIO = 'ESTADO_PATRIMONIO';
    public const CONSOLIDACION_PARTIDAS = 'CONSOLIDACION_PARTIDAS';
    public const VER_PARTIDAS = 'VER_PARTIDAS';
    public const EDITAR_PARTIDAS = 'EDITAR_PARTIDAS';
    public const PERDON_MORA = 'PERDON_MORA';

    // Mapeo de módulos a clases
    private const MODULE_CLASS_MAP = [
        self::MODIFICAR_INTERES => 1,
        self::INGRESOS_DIARIOS => 2,
        self::SEPARACION_DESEMBOLSOS => 3,
        self::APERTURA_CAJA => 4,
        self::CIERRE_CAJA => 5,
        self::REPORTE_BALANCE_COMPROBACION => 6,
        self::APERTURA_CUENTA_SECUNDARIA_AHORROS => 7,
        self::APERTURA_CUENTA_SECUNDARIA_APORTACIONES => 8,
        self::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA => 9,
        self::VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA => 10,
        self::BALANCE_Y_ER => 11,
        self::SALDOS_CARTERA => 12,
        self::ASIGNAR_ENCARGADO_AHORROS => 13,
        self::ASIGNAR_ENCARGADO_APORTACIONES => 14,
        self::REPORTE_CREDITOS_DESEMBOLSADOS => 15,
        self::REIMPRESION_CHEQUES => 16,
        self::LIBRO_DIARIO => 17,
        self::LIBRO_MAYOR => 18,
        self::ESTADO_PATRIMONIO => 19,
        self::CONSOLIDACION_PARTIDAS => 20,
        self::VER_PARTIDAS => 21,
        self::EDITAR_PARTIDAS => 22,
        self::PERDON_MORA => 23,
    ];

    // Niveles de acceso
    private const MEDIUM_LEVEL = 1;
    private const HIGH_LEVEL = 2;


    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->conectarDb();
        $this->cargarPermisos();
        $this->organizarPermisosPorClase();
    }

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(1);
            } catch (Exception $e) {
                Log::error("Error al conectar a la base de datos: " . $e->getMessage());
                throw new Exception("Error al conectar con la base de datos para permisos: " . $e->getMessage());
            }
        }
    }

    private function cargarPermisos(): void
    {
        try {
            $sql = "SELECT r.id, r.clase, r.nivel 
                    FROM tb_usuarios_restringido ur 
                    JOIN tb_restringido r ON ur.id_restringido = r.id 
                    WHERE ur.id_usuario = ? AND r.estado = 1";

            $this->cachedPermissions = $this->db->getAllResults($sql, [$this->userId]);
        } catch (Exception $e) {
            Log::error("Error al cargar permisos del usuario {$this->userId}: " . $e->getMessage());
            throw new Exception("Error al cargar permisos del usuario: " . $e->getMessage());
        }
    }

    private function organizarPermisosPorClase(): void
    {
        foreach ($this->cachedPermissions as $permiso) {
            $this->permissionsByClass[$permiso['clase']][] = [
                'id' => $permiso['id'],
                'nivel' => $permiso['nivel']
            ];
        }
    }

    public function hasPermissionId(int $permissionId): bool
    {
        return in_array($permissionId, array_column($this->cachedPermissions, 'id'));
    }

    public function hasPermissionClass(int $clase, ?int $nivel = null): bool
    {
        if (!isset($this->permissionsByClass[$clase])) {
            return false;
        }

        if ($nivel === null) {
            return true;
        }

        foreach ($this->permissionsByClass[$clase] as $permiso) {
            if ($permiso['nivel'] === $nivel) {
                return true;
            }
        }

        return false;
    }

    public function getHighestLevel(int $clase): int
    {
        if (!isset($this->permissionsByClass[$clase])) {
            return 0;
        }

        $niveles = array_column($this->permissionsByClass[$clase], 'nivel');
        return !empty($niveles) ? max($niveles) : 0;
    }




    /**
     * Obtiene el nivel de acceso para un módulo específico
     */
    public function getAccessLevel(string $module): string
    {
        if (!isset(self::MODULE_CLASS_MAP[$module])) {
            return 'low';
        }

        $clase = self::MODULE_CLASS_MAP[$module];
        $nivelMaximo = $this->getHighestLevel($clase);

        switch ($nivelMaximo) {
            case self::HIGH_LEVEL:
                return 'high';
            case self::MEDIUM_LEVEL:
                return 'medium';
            default:
                return 'low';
        }
    }

    /**
     * Verifica si tiene nivel bajo para un módulo específico
     */
    public function isLow(string $module): bool
    {
        return $this->getAccessLevel($module) === 'low';
    }

    /**
     * Verifica si tiene nivel medio para un módulo específico
     */
    public function isMedium(string $module): bool
    {
        return $this->getAccessLevel($module) === 'medium';
    }

    /**
     * Verifica si tiene nivel alto para un módulo específico
     */
    public function isHigh(string $module): bool
    {
        return $this->getAccessLevel($module) === 'high';
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}

// $permisos = new UserPermissions($userId);

// // Verificar permiso específico
// if ($permisos->hasPermissionId(5)) {
//     // Tiene permiso específico
// }

// // Verificar permiso por clase y nivel
// if ($permisos->hasPermissionClass(UserPermissions::CLASE_CAJA, UserPermissions::NIVEL_AGENCIA)) {
//     // Tiene permiso de caja nivel agencia
// }

// // Obtener nivel más alto para una clase
// $nivelMaximo = $permisos->getHighestLevel(UserPermissions::CLASE_PARTIDAS);

// // Verificar nivel de acceso general
// if ($permisos->isGeneralLevel()) {
//     // Tiene acceso a nivel general
// }

// // Obtener nivel de acceso (equivalente al antiguo)
// $nivel = $permisos->getAccessLevel(); // Retorna 'low', 'medium' o 'high'