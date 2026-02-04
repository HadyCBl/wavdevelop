<?php

namespace Micro\Generic;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

/**
 * Clase PermissionManager - Sistema de permisos con constantes y niveles
 * 
 * Uso:
 * $permisos = new PermissionManager($userId);
 * $permisos->getLevelAccess(PermissionManager::APERTURA_CAJA);
 * $permisos->isLevelOne(PermissionManager::APERTURA_CAJA);
 */
class PermissionManager
{
    private ?DatabaseAdapter $db = null;
    private int $userId;
    private array $cachedPermissions = [];
    private array $permissionsByClass = [];
    
    // Instancia estática para acceso rápido
    private static ?PermissionManager $instance = null;

    // ===== DEFINICIÓN DE PERMISOS COMO CONSTANTES =====
    public const MODIFICAR_INTERES = 'MODIFICAR_INTERES';
    public const INGRESOS_DIARIOS = 'INGRESOS_DIARIOS';
    public const SEPARACION_DESEMBOLSOS = 'SEPARACION_DESEMBOLSOS';
    public const APERTURA_CAJA = 'APERTURA_CAJA';
    public const CIERRE_CAJA = 'CIERRE_CAJA';
    public const CONSOLIDADO_BALCOMPROBACION = 'CONSOLIDADO_BALCOMPROBACION';
    public const APERTURA_CUENTA_SECUNDARIA_AHORROS = 'APERTURA_CUENTA_SECUNDARIA_AHORROS';
    public const APERTURA_CUENTA_SECUNDARIA_APORTACIONES = 'APERTURA_CUENTA_SECUNDARIA_APORTACIONES';
    public const APROBAR_RECHAZAR_MOVIMIENTOS_CAJA = 'APROBAR_RECHAZAR_MOVIMIENTOS_CAJA';
    public const VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA = 'VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA';
    public const BALANCE_Y_ER = 'BALANCE_Y_ER';
    public const SALDOS_CARTERA = 'SALDOS_CARTERA';
    public const ASIGNAR_ENCARGADOS_CUENTAS_AHORROS = 'ASIGNAR_ENCARGADOS_CUENTAS_AHORROS';
    public const ASIGNAR_ENCARGADOS_CUENTAS_APORTACIONES = 'ASIGNAR_ENCARGADOS_CUENTAS_APORTACIONES';
    public const REPORTE_CREDITOS_DESEMBOLSADOS = 'REPORTE_CREDITOS_DESEMBOLSADOS';
    public const REIMPRESION_CHEQUES = 'REIMPRESION_CHEQUES';
    public const LIBRO_DIARIO = 'LIBRO_DIARIO';
    public const LIBRO_MAYOR = 'LIBRO_MAYOR';
    public const ESTADO_PATRIMONIO = 'ESTADO_PATRIMONIO';
    public const CONSOLIDACION_PARTIDAS = 'CONSOLIDACION_PARTIDAS';
    public const VER_PARTIDAS = 'VER_PARTIDAS';
    public const EDITAR_PARTIDAS = 'EDITAR_PARTIDAS';
    public const PERDON_MORA = 'PERDON_MORA';
    public const USAR_OTROS_DOCS_AHORROS = 'USAR_OTROS_DOCS_AHORROS';
    public const USAR_OTROS_DOCS_APORTACIONES = 'USAR_OTROS_DOCS_APORTACIONES';
    public const USAR_OTROS_DOCS_CREDITOS = 'USAR_OTROS_DOCS_CREDITOS';
    public const ASIGNAR_USUARIOS_BANCOS = 'ASIGNAR_USUARIOS_BANCOS';
    public const VER_DASHBOARD_CLIENTES = 'VER_DASHBOARD_CLIENTES';
    public const VER_DASHBOARD_CREDITOS = 'VER_DASHBOARD_CREDITOS';
    public const VER_CREDITOS_CAJA = 'VER_CREDITOS_CAJA';
    public const REPORTE_MORA = 'REPORTE_MORA';
    public const REPORTE_PREPAGOS = 'REPORTE_PREPAGOS';
    public const VER_PLAZO_FIJO_VENCER = 'VER_PLAZO_FIJO_VENCER';
    public const VER_AUXILIO_POSTUMO_VENCER = 'VER_AUXILIO_POSTUMO_VENCER';

    // ===== MAPEO DE PERMISOS CON SUS CLASES Y NIVELES DISPONIBLES =====
    private const PERMISSION_CLASS_MAP = [
        self::MODIFICAR_INTERES => ['class' => 'Permiso', 'levels' => [1]],
        self::INGRESOS_DIARIOS => ['class' => 'Permiso', 'levels' => [1, 2]],
        self::SEPARACION_DESEMBOLSOS => ['class' => 'Permiso', 'levels' => [1]],
        self::APERTURA_CAJA => ['class' => 'Permiso', 'levels' => [1, 2]],
        self::CIERRE_CAJA => ['class' => 'Permiso', 'levels' => [1, 2]],
        self::CONSOLIDADO_BALCOMPROBACION => ['class' => 'Permiso', 'levels' => [1]],
        self::APERTURA_CUENTA_SECUNDARIA_AHORROS => ['class' => 'Permiso', 'levels' => [1]],
        self::APERTURA_CUENTA_SECUNDARIA_APORTACIONES => ['class' => 'Permiso', 'levels' => [1]],
        self::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA => ['class' => 'Permiso', 'levels' => [1, 2]],
        self::VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA => ['class' => 'Permiso', 'levels' => [1]],
        self::BALANCE_Y_ER => ['class' => 'Permiso', 'levels' => [1]],
        self::SALDOS_CARTERA => ['class' => 'Permiso', 'levels' => [1, 2, 3]],
        self::ASIGNAR_ENCARGADOS_CUENTAS_AHORROS => ['class' => 'Permiso', 'levels' => [1]],
        self::ASIGNAR_ENCARGADOS_CUENTAS_APORTACIONES => ['class' => 'Permiso', 'levels' => [1]],
        self::REPORTE_CREDITOS_DESEMBOLSADOS => ['class' => 'Permiso', 'levels' => [1, 2, 3]],
        self::REIMPRESION_CHEQUES => ['class' => 'Permiso', 'levels' => [1]],
        self::LIBRO_DIARIO => ['class' => 'Permiso', 'levels' => [1]],
        self::LIBRO_MAYOR => ['class' => 'Permiso', 'levels' => [1]],
        self::ESTADO_PATRIMONIO => ['class' => 'Permiso', 'levels' => [1]],
        self::CONSOLIDACION_PARTIDAS => ['class' => 'Permiso', 'levels' => [1]],
        self::VER_PARTIDAS => ['class' => 'Permiso', 'levels' => [1]],
        self::EDITAR_PARTIDAS => ['class' => 'Permiso', 'levels' => [1, 2]],
        self::PERDON_MORA => ['class' => 'Permiso', 'levels' => [1, 2]],
        self::USAR_OTROS_DOCS_AHORROS => ['class' => 'Permiso', 'levels' => [1]],
        self::USAR_OTROS_DOCS_APORTACIONES => ['class' => 'Permiso', 'levels' => [1]],
        self::USAR_OTROS_DOCS_CREDITOS => ['class' => 'Permiso', 'levels' => [1]],
        self::ASIGNAR_USUARIOS_BANCOS => ['class' => 'Permiso', 'levels' => [1]],
        self::VER_DASHBOARD_CLIENTES => ['class' => 'Permiso', 'levels' => [1]],
        self::VER_DASHBOARD_CREDITOS => ['class' => 'Permiso', 'levels' => [1]],
        self::VER_CREDITOS_CAJA => ['class' => 'Permiso', 'levels' => [1, 2]],
        self::REPORTE_MORA => ['class' => 'Permiso', 'levels' => [1, 2, 3]],
        self::REPORTE_PREPAGOS => ['class' => 'Permiso', 'levels' => [1, 2, 3]],
        self::VER_PLAZO_FIJO_VENCER => ['class' => 'Permiso', 'levels' => [1]],
        self::VER_AUXILIO_POSTUMO_VENCER => ['class' => 'Permiso', 'levels' => [1]],
    ];

    // ===== MAPEO DE IDs ÚNICOS PARA CADA PERMISO-NIVEL =====
    private const PERMISSION_LEVEL_IDS = [
        // MODIFICAR_INTERES
        self::MODIFICAR_INTERES . '_1' => 1,

        // INGRESOS_DIARIOS  
        self::INGRESOS_DIARIOS . '_1' => 3,
        self::INGRESOS_DIARIOS . '_2' => 2,

        // SEPARACION_DESEMBOLSOS
        self::SEPARACION_DESEMBOLSOS . '_1' => 4,

        // APERTURA_CAJA
        self::APERTURA_CAJA . '_1' => 5,
        self::APERTURA_CAJA . '_2' => 6,

        // CIERRE_CAJA
        self::CIERRE_CAJA . '_1' => 7,
        self::CIERRE_CAJA . '_2' => 8,

        // CONSOLIDADO_BALCOMPROBACION
        self::CONSOLIDADO_BALCOMPROBACION . '_1' => 9,

        // APERTURA_CUENTA_SECUNDARIA_AHORROS
        self::APERTURA_CUENTA_SECUNDARIA_AHORROS . '_1' => 10,
        // APERTURA_CUENTA_SECUNDARIA_APORTACIONES
        self::APERTURA_CUENTA_SECUNDARIA_APORTACIONES . '_1' => 11,

        // APROBAR_RECHAZAR_MOVIMIENTOS_CAJA
        self::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA . '_1' => 13,
        self::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA . '_2' => 12,

        // VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA
        self::VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA . '_1' => 14,

        // BALANCE_Y_ER
        self::BALANCE_Y_ER . '_1' => 15,

        // SALDOS_CARTERA
        self::SALDOS_CARTERA . '_1' => 16,
        self::SALDOS_CARTERA . '_2' => 40,
        self::SALDOS_CARTERA . '_3' => 17,

        // ASIGNAR_ENCARGADOS_CUENTAS_AHORROS
        self::ASIGNAR_ENCARGADOS_CUENTAS_AHORROS . '_1' => 18,
        // ASIGNAR_ENCARGADOS_CUENTAS_APORTACIONES
        self::ASIGNAR_ENCARGADOS_CUENTAS_APORTACIONES . '_1' => 19,
        // REPORTE_CREDITOS_DESEMBOLSADOS
        self::REPORTE_CREDITOS_DESEMBOLSADOS . '_1' => 20,
        self::REPORTE_CREDITOS_DESEMBOLSADOS . '_2' => 41,
        self::REPORTE_CREDITOS_DESEMBOLSADOS . '_3' => 21,

        // REIMPRESION_CHEQUES
        self::REIMPRESION_CHEQUES . '_1' => 22,
        // LIBRO_DIARIO
        self::LIBRO_DIARIO . '_1' => 23,

        // LIBRO_MAYOR
        self::LIBRO_MAYOR . '_1' => 24,

        // ESTADO_PATRIMONIO
        self::ESTADO_PATRIMONIO . '_1' => 25,

        // CONSOLIDACION_PARTIDAS
        self::CONSOLIDACION_PARTIDAS . '_1' => 26,

        // VER_PARTIDAS
        self::VER_PARTIDAS . '_1' => 27,

        // EDITAR_PARTIDAS
        self::EDITAR_PARTIDAS . '_1' => 28,
        self::EDITAR_PARTIDAS . '_2' => 29,

        // PERDON_MORA
        self::PERDON_MORA . '_1' => 30,
        self::PERDON_MORA . '_2' => 31,

        // USAR_OTROS_DOCS_AHORROS
        self::USAR_OTROS_DOCS_AHORROS . '_1' => 32,
        // USAR_OTROS_DOCS_APORTACIONES
        self::USAR_OTROS_DOCS_APORTACIONES . '_1' => 33,
        // USAR_OTROS_DOCS_CREDITOS
        self::USAR_OTROS_DOCS_CREDITOS . '_1' => 34,

        // ASIGNAR_USUARIOS_BANCOS
        self::ASIGNAR_USUARIOS_BANCOS . '_1' => 35,
        // VER_DASHBOARD_CLIENTES
        self::VER_DASHBOARD_CLIENTES . '_1' => 36,
        // VER_DASHBOARD_CREDITOS
        self::VER_DASHBOARD_CREDITOS . '_1' => 37,
        // VER_CREDITOS_CAJA
        self::VER_CREDITOS_CAJA . '_1' => 38,
        self::VER_CREDITOS_CAJA . '_2' => 39,

        // REPORTE_MORA
        self::REPORTE_MORA . '_1' => 42,
        self::REPORTE_MORA . '_2' => 43,
        self::REPORTE_MORA . '_3' => 44,
        // REPORTE_PREPAGOS
        self::REPORTE_PREPAGOS . '_1' => 45,
        self::REPORTE_PREPAGOS . '_2' => 46,
        self::REPORTE_PREPAGOS . '_3' => 47,

        // VER PLAZO FIJO
        self::VER_PLAZO_FIJO_VENCER . '_1' => 48,
        // VER AUXILIO POSTUMO
        self::VER_AUXILIO_POSTUMO_VENCER . '_1' => 49,
    ];

    // ===== MAPEO INVERSO: ID => PERMISO + NIVEL =====
    private const ID_TO_PERMISSION_LEVEL = [
        1 => ['permission' => self::MODIFICAR_INTERES, 'level' => 1],
        2 => ['permission' => self::INGRESOS_DIARIOS, 'level' => 2],
        3 => ['permission' => self::INGRESOS_DIARIOS, 'level' => 1],
        4 => ['permission' => self::SEPARACION_DESEMBOLSOS, 'level' => 1],
        5 => ['permission' => self::APERTURA_CAJA, 'level' => 1],
        6 => ['permission' => self::APERTURA_CAJA, 'level' => 2],
        7 => ['permission' => self::CIERRE_CAJA, 'level' => 1],
        8 => ['permission' => self::CIERRE_CAJA, 'level'    => 2],
        9 => ['permission' => self::CONSOLIDADO_BALCOMPROBACION, 'level' => 1],
        10 => ['permission' => self::APERTURA_CUENTA_SECUNDARIA_AHORROS, 'level' => 1],
        11 => ['permission' => self::APERTURA_CUENTA_SECUNDARIA_APORTACIONES, 'level' => 1],
        12 => ['permission' => self::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA, 'level' => 2],
        13 => ['permission' => self::APROBAR_RECHAZAR_MOVIMIENTOS_CAJA, 'level' => 1],
        14 => ['permission' => self::VER_MOVIMIENTOS_BANCOS_EN_ARQUEO_CAJA, 'level' => 1],
        15 => ['permission' => self::BALANCE_Y_ER, 'level' => 1],
        16 => ['permission' => self::SALDOS_CARTERA, 'level' => 1],
        17 => ['permission' => self::SALDOS_CARTERA, 'level' => 3],
        18 => ['permission' => self::ASIGNAR_ENCARGADOS_CUENTAS_AHORROS, 'level' => 1],
        19 => ['permission' => self::ASIGNAR_ENCARGADOS_CUENTAS_APORTACIONES, 'level' => 1],
        20 => ['permission' => self::REPORTE_CREDITOS_DESEMBOLSADOS, 'level' => 1],
        21 => ['permission' => self::REPORTE_CREDITOS_DESEMBOLSADOS, 'level' => 3],
        22 => ['permission' => self::REIMPRESION_CHEQUES, 'level' => 1],
        23 => ['permission' => self::LIBRO_DIARIO, 'level' => 1],
        24 => ['permission' => self::LIBRO_MAYOR, 'level' => 1],
        25 => ['permission' => self::ESTADO_PATRIMONIO, 'level' => 1],
        26 => ['permission' => self::CONSOLIDACION_PARTIDAS, 'level' => 1],
        27 => ['permission' => self::VER_PARTIDAS, 'level' => 1],
        28 => ['permission' => self::EDITAR_PARTIDAS, 'level' => 1],
        29 => ['permission' => self::EDITAR_PARTIDAS, 'level' => 2],
        30 => ['permission' => self::PERDON_MORA, 'level' => 1],
        31 => ['permission' => self::PERDON_MORA, 'level' => 2],
        32 => ['permission' => self::USAR_OTROS_DOCS_AHORROS, 'level' => 1],
        33 => ['permission' => self::USAR_OTROS_DOCS_APORTACIONES, 'level' => 1],
        34 => ['permission' => self::USAR_OTROS_DOCS_CREDITOS, 'level' => 1],
        35 => ['permission' => self::ASIGNAR_USUARIOS_BANCOS, 'level' => 1],
        36 => ['permission' => self::VER_DASHBOARD_CLIENTES, 'level' => 1],
        37 => ['permission' => self::VER_DASHBOARD_CREDITOS, 'level' => 1],
        38 => ['permission' => self::VER_CREDITOS_CAJA, 'level' => 1],
        39 => ['permission' => self::VER_CREDITOS_CAJA, 'level' => 2],
        40 => ['permission' => self::SALDOS_CARTERA, 'level' => 2],
        41 => ['permission' => self::REPORTE_CREDITOS_DESEMBOLSADOS, 'level' => 2],
        42 => ['permission' => self::REPORTE_MORA, 'level' => 1],
        43 => ['permission' => self::REPORTE_MORA, 'level' => 2],
        44 => ['permission' => self::REPORTE_MORA, 'level' => 3],
        45 => ['permission' => self::REPORTE_PREPAGOS, 'level' => 1],
        46 => ['permission' => self::REPORTE_PREPAGOS, 'level' => 2],
        47 => ['permission' => self::REPORTE_PREPAGOS, 'level' => 3],
        48 => ['permission' => self::VER_PLAZO_FIJO_VENCER, 'level' => 1],
        49 => ['permission' => self::VER_AUXILIO_POSTUMO_VENCER, 'level' => 1],
    ];

    // ===== NOMBRES DESCRIPTIVOS DE LOS NIVELES POR PERMISO =====
    private const PERMISSION_LEVELS = [

        self::APERTURA_CAJA => [
            1 => 'Consultar estado',
            2 => 'Realizar apertura'
        ],
        self::CIERRE_CAJA => [
            1 => 'Ver movimientos',
            2 => 'Realizar cierre'
        ],

        self::USAR_OTROS_DOCS_AHORROS => [
            1 => 'Permitir uso de otros documentos en el modulo de ahorros'
        ],
        self::USAR_OTROS_DOCS_APORTACIONES => [
            1 => 'Permitir uso de otros documentos en el modulo de aportaciones'
        ],
        self::USAR_OTROS_DOCS_CREDITOS => [
            1 => 'Permitir uso de otros documentos en el modulo de créditos'
        ],

        self::ASIGNAR_USUARIOS_BANCOS => [
            1 => 'Asignar cheques a otros usuarios en la seccion de cheques'
        ],

        self::VER_DASHBOARD_CLIENTES => [
            1 => 'Ver dashboard de clientes'
        ],
        self::VER_DASHBOARD_CREDITOS => [
            1 => 'Ver dashboard de créditos'
        ],
        self::VER_CREDITOS_CAJA => [
            1 => 'ver creditos de la agencia en caja',
            2 => 'ver creditos general en caja'
        ],
        self::REPORTE_MORA => [
            1 => 'Reporte de mora nivel agencia',
            2 => 'Reporte de mora nivel region',
            3 => 'Reporte de mora nivel general'
        ],
        self::REPORTE_PREPAGOS => [
            1 => 'Reporte de visitas prepago nivel agencia',
            2 => 'Reporte de visitas prepago nivel region',
            3 => 'Reporte de visitas prepago nivel general'
        ],

        self::VER_PLAZO_FIJO_VENCER => [
            1 => 'Ver plazos fijos por vencer'
        ],

        self::VER_AUXILIO_POSTUMO_VENCER => [
            1 => 'Ver auxilios póstumos por vencer'
        ],
    ];

    /**
     * Obtiene el ID único para un permiso y nivel específicos
     */
    public static function getPermissionLevelId(string $permission, int $level): ?int
    {
        $key = $permission . '_' . $level;
        return self::PERMISSION_LEVEL_IDS[$key] ?? null;
    }

    /**
     * Obtiene el permiso y nivel a partir de un ID
     */
    public static function getPermissionFromId(int $id): ?array
    {
        return self::ID_TO_PERMISSION_LEVEL[$id] ?? null;
    }

    /**
     * Obtiene todos los IDs de permisos que tiene el usuario
     */
    public function getUserPermissionIds(): array
    {
        try {
            $sql = "SELECT ta.id_restringido 
                    FROM tb_autorizacion ta 
                    WHERE ta.id_usuario = :userId AND estado=1";

            $params = [':userId' => $this->userId];
            $results = $this->db->getAllResults($sql, $params);

            $ids = [];
            foreach ($results as $row) {
                $ids[] = (int)$row['id_restringido'];
            }

            return $ids;
        } catch (Exception $e) {
            Log::error("Error obteniendo IDs de permisos del usuario {$this->userId}: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica si el usuario tiene un permiso específico por ID
     */
    public function hasPermissionById(int $id): bool
    {
        $permissionData = self::getPermissionFromId($id);
        if (!$permissionData) {
            return false;
        }

        return $this->hasMinimumLevel($permissionData['permission'], $permissionData['level']);
    }

    /**
     * Obtiene información completa de un permiso por ID
     */
    public function getPermissionInfoById(int $id): ?array
    {
        $permissionData = self::getPermissionFromId($id);
        if (!$permissionData) {
            return null;
        }

        $info = $this->getPermissionInfo($permissionData['permission']);
        $info['id'] = $id;
        $info['required_level'] = $permissionData['level'];

        return $info;
    }

    /**
     * Asigna permisos al usuario usando IDs (para usar desde interfaces de administración)
     */
    public function assignPermissionsByIds(array $permissionIds): bool
    {
        try {
            // Eliminar permisos existentes
            $deleteSql = "DELETE FROM tb_autorizacion WHERE id_usuario = ?";
            $this->db->executeQuery($deleteSql, [$this->userId]);

            // Insertar nuevos permisos directamente por ID
            foreach ($permissionIds as $id) {
                // Verificar que el ID existe
                if (self::getPermissionFromId($id)) {
                    $data = [
                        'id_usuario' => $this->userId,
                        'id_restringido' => $id
                    ];
                    $this->db->insert('tb_autorizacion', $data);
                }
            }

            // Recargar permisos en memoria
            $this->loadUserPermissions();

            return true;
        } catch (Exception $e) {
            Log::error("Error asignando permisos por IDs: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todos los permisos disponibles con sus IDs
     */
    public static function getAllPermissionsWithIds(): array
    {
        $result = [];

        foreach (self::ID_TO_PERMISSION_LEVEL as $id => $data) {
            $permission = $data['permission'];
            $level = $data['level'];

            if (!isset($result[$permission])) {
                $result[$permission] = [
                    'permission' => $permission,
                    'levels' => []
                ];
            }

            $levelName = 'Nivel ' . $level;
            if (isset(self::PERMISSION_LEVELS[$permission][$level])) {
                $levelName = self::PERMISSION_LEVELS[$permission][$level];
            }

            $result[$permission]['levels'][$level] = [
                'id' => $id,
                'level' => $level,
                'name' => $levelName
            ];
        }

        return $result;
    }

    /**
     * Constructor
     */
    public function __construct(int $userId)
    {
        $this->userId = $userId;
        $this->db = new DatabaseAdapter();
        $this->db->openConnection(1);
        $this->loadUserPermissions();
        
        // Guardar como instancia estática
        self::$instance = $this;
    }
    
    /**
     * Obtiene la instancia actual de PermissionManager
     * Útil para acceso estático después de inicialización
     */
    public static function getInstance(): ?PermissionManager
    {
        return self::$instance;
    }
    
    /**
     * Inicializa la instancia estática con un usuario específico
     */
    public static function init(int $userId): PermissionManager
    {
        if (self::$instance === null || self::$instance->userId !== $userId) {
            self::$instance = new self($userId);
        }
        return self::$instance;
    }
    
    /**
     * Métodos estáticos que usan la instancia actual
     * Estos métodos requieren que se haya inicializado primero con init() o new PermissionManager()
     */
    
    /**
     * Verifica si el usuario tiene exactamente el nivel 1 (estático)
     */
    public static function checkLevelOne(string $permission): bool
    {
        if (self::$instance === null) {
            throw new Exception('PermissionManager no ha sido inicializado. Llama a PermissionManager::init($userId) primero.');
        }
        return self::$instance->isLevelOne($permission);
    }
    
    /**
     * Verifica si el usuario tiene exactamente el nivel 2 (estático)
     */
    public static function checkLevelTwo(string $permission): bool
    {
        if (self::$instance === null) {
            throw new Exception('PermissionManager no ha sido inicializado.');
        }
        return self::$instance->isLevelTwo($permission);
    }
    
    /**
     * Verifica si el usuario tiene exactamente el nivel 3 (estático)
     */
    public static function checkLevelThree(string $permission): bool
    {
        if (self::$instance === null) {
            throw new Exception('PermissionManager no ha sido inicializado.');
        }
        return self::$instance->isLevelThree($permission);
    }
    
    /**
     * Verifica si el usuario tiene acceso a un permiso (estático)
     */
    public static function check(string $permission): bool
    {
        if (self::$instance === null) {
            throw new Exception('PermissionManager no ha sido inicializado.');
        }
        return self::$instance->hasAccess($permission);
    }
    
    /**
     * Obtiene el nivel de acceso (estático)
     */
    public static function level(string $permission): int
    {
        if (self::$instance === null) {
            throw new Exception('PermissionManager no ha sido inicializado.');
        }
        return self::$instance->getLevelAccess($permission);
    }

    /**
     * Carga los permisos del usuario desde la base de datos
     */
    private function loadUserPermissions(): void
    {
        try {
            $sql = "SELECT ta.id_restringido 
                    FROM tb_autorizacion ta 
                    WHERE ta.id_usuario = :userId AND estado=1";

            $params = [':userId' => $this->userId];
            $results = $this->db->getAllResults($sql, $params);

            $this->cachedPermissions = [];
            $this->permissionsByClass = [];

            foreach ($results as $row) {
                $permissionId = (int)$row['id_restringido'];

                // Obtener permiso y nivel desde el ID
                $permissionData = self::getPermissionFromId($permissionId);
                if ($permissionData) {
                    $permission = $permissionData['permission'];
                    $level = $permissionData['level'];

                    // Mantener el nivel más alto para cada permiso
                    if (
                        !isset($this->cachedPermissions[$permission]) ||
                        $this->cachedPermissions[$permission] < $level
                    ) {
                        $this->cachedPermissions[$permission] = $level;
                    }

                    // Agrupar por clase
                    if (isset(self::PERMISSION_CLASS_MAP[$permission])) {
                        $class = self::PERMISSION_CLASS_MAP[$permission]['class'];
                        if (!isset($this->permissionsByClass[$class])) {
                            $this->permissionsByClass[$class] = [];
                        }
                        $this->permissionsByClass[$class][$permission] = $this->cachedPermissions[$permission];
                    }
                }
            }
        } catch (Exception $e) {
            Log::error("Error cargando permisos del usuario {$this->userId}: " . $e->getMessage());
            $this->cachedPermissions = [];
            $this->permissionsByClass = [];
        }
    }

    /**
     * Obtiene el nivel de acceso para un permiso específico
     * Retorna 0 si no tiene acceso, o el nivel (1, 2, 3) si tiene acceso
     */
    public function getLevelAccess(string $permission): int
    {
        return $this->cachedPermissions[$permission] ?? 0;
    }

    /**
     * Verifica si el usuario tiene exactamente el nivel 1 en un permiso
     */
    public function isLevelOne(string $permission): bool
    {
        return $this->getLevelAccess($permission) === 1;
    }

    /**
     * Verifica si el usuario tiene exactamente el nivel 2 en un permiso
     */
    public function isLevelTwo(string $permission): bool
    {
        return $this->getLevelAccess($permission) === 2;
    }

    /**
     * Verifica si el usuario tiene exactamente el nivel 3 en un permiso
     */
    public function isLevelThree(string $permission): bool
    {
        return $this->getLevelAccess($permission) === 3;
    }

    /**
     * Verifica si el usuario tiene cualquier nivel de acceso a un permiso
     */
    public function hasAccess(string $permission): bool
    {
        return $this->getLevelAccess($permission) > 0;
    }

    /**
     * Verifica si el usuario NO tiene ningún nivel de acceso a un permiso
     */
    public function hasNoAccess(string $permission): bool
    {
        return $this->getLevelAccess($permission) === 0;
    }

    /**
     * Verifica si el usuario tiene exactamente el nivel especificado
     */
    public function hasExactLevel(string $permission, int $level): bool
    {
        return $this->getLevelAccess($permission) === $level;
    }

    /**
     * Verifica si el usuario tiene al menos el nivel mínimo requerido
     */
    public function hasMinimumLevel(string $permission, int $minimumLevel): bool
    {
        return $this->getLevelAccess($permission) >= $minimumLevel;
    }

    /**
     * Obtiene información completa sobre un permiso
     */
    public function getPermissionInfo(string $permission): array
    {
        $userLevel = $this->getLevelAccess($permission);
        $config = self::PERMISSION_CLASS_MAP[$permission] ?? null;

        $info = [
            'permission' => $permission,
            'user_level' => $userLevel,
            'has_access' => $userLevel > 0,
            'available_levels' => $config['levels'] ?? [],
            'max_available_level' => !empty($config['levels']) ? max($config['levels']) : 0,
            'level_names' => []
        ];

        // Agregar nombres de niveles si están disponibles
        if (isset(self::PERMISSION_LEVELS[$permission])) {
            $levelNames = self::PERMISSION_LEVELS[$permission];
            foreach ($levelNames as $level => $name) {
                if ($userLevel >= $level) {
                    $info['level_names'][$level] = $name;
                }
            }
        }

        return $info;
    }

    /**
     * Verifica si el usuario tiene TODOS los permisos especificados
     */
    public function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasAccess($permission)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Verifica si el usuario tiene AL MENOS UNO de los permisos especificados
     */
    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasAccess($permission)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene todos los permisos del usuario con sus niveles
     */
    public function getAllUserPermissions(): array
    {
        $result = [];
        foreach ($this->cachedPermissions as $permission => $level) {
            $result[$permission] = [
                'level' => $level,
                'info' => $this->getPermissionInfo($permission)
            ];
        }
        return $result;
    }

    /**
     * Obtiene permisos agrupados por clase
     */
    public function getPermissionsByClass(): array
    {
        return $this->permissionsByClass;
    }

    /**
     * Verifica si el usuario es administrador (tiene nivel 3 en configuración del sistema)
     */
    // public function isAdmin(): bool
    // {
    //     return $this->isLevelThree(self::CONFIGURACION_SISTEMA);
    // }

    /**
     * Verifica si el usuario es supervisor (tiene nivel 2 o 3 en al menos un permiso)
     */
    public function isSupervisor(): bool
    {
        foreach ($this->cachedPermissions as $level) {
            if ($level >= 2) {
                return true;
            }
        }
        return false;
    }

    /**
     * Obtiene el nivel más alto que tiene el usuario en cualquier permiso
     */
    public function getHighestLevel(): int
    {
        return empty($this->cachedPermissions) ? 0 : max($this->cachedPermissions);
    }

    /**
     * Obtiene todos los permisos definidos en el sistema
     */
    public static function getAllAvailablePermissions(): array
    {
        return array_keys(self::PERMISSION_CLASS_MAP);
    }

    /**
     * Obtiene la configuración de un permiso
     */
    public static function getPermissionConfig(string $permission): ?array
    {
        return self::PERMISSION_CLASS_MAP[$permission] ?? null;
    }

    /**
     * Recarga los permisos del usuario desde la base de datos
     */
    public function refreshPermissions(): void
    {
        $this->loadUserPermissions();
    }

    /**
     * Obtiene el ID del usuario actual
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Verifica si el usuario puede realizar una acción específica
     * Útil para validaciones más complejas
     */
    public function canPerformAction(string $action, array $context = []): bool
    {
        switch ($action) {
            case 'manage_cash':
                return $this->isLevelTwo(self::APERTURA_CAJA) &&
                    $this->isLevelTwo(self::CIERRE_CAJA);

            case 'full_user_management':
                // return $this->isLevelThree(self::GESTION_USUARIOS);

            case 'financial_reports':
                // return $this->hasAnyPermission([
                //     self::REPORTES_FINANCIEROS,
                //     self::BALANCE_Y_ER,
                //     self::ESTADOS_FINANCIEROS
                // ]);

            case 'system_administration':
                // return $this->isLevelThree(self::CONFIGURACION_SISTEMA) ||
                //     $this->hasAccess(self::AUDITORIA_SISTEMA);

            default:
                return false;
        }
    }

    /**
     * Destructor - libera recursos
     */
    public function __destruct()
    {
        $this->db = null;
        $this->cachedPermissions = [];
        $this->permissionsByClass = [];
    }
}
