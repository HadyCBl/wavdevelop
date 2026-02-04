<?php

namespace Micro\Generic;

use Exception;
use Micro\Exceptions\SoftException;
use Micro\Helpers\Log;

/**
 * Gestor centralizado de permisos del sistema basado en módulos
 * 
 * Maneja la jerarquía de permisos: Módulos -> Menús -> Submenús
 * - Los módulos son asignados a instituciones/cooperativas
 * - Los submenús son asignados a usuarios
 * - Un usuario solo ve un submenú si tiene permiso personal Y su institución tiene permiso al módulo padre
 */
class ModulePermissionManager
{
    private $database;
    private string $dbNameGeneral;

    public function __construct($database, string $dbNameGeneral)
    {
        $this->database = $database;
        $this->dbNameGeneral = $dbNameGeneral;
    }

    /**
     * Obtiene los módulos permitidos para una institución
     * 
     * @param int $idAgencia ID de la agencia
     * @param string $rama Rama del módulo (ej: 'I' para indicadores)
     * @return array{success: bool, data: array|null, message: string|null}
     */
    public function getInstitutionModules(int $idAgencia, string $rama): array
    {
        $query = "SELECT tbo.id, tbo.descripcion, tbo.icon, tbo.ruta, tbo.rama 
                  FROM {$this->dbNameGeneral}.tb_permisos_modulos tbps
                  INNER JOIN {$this->dbNameGeneral}.tb_modulos tbo ON tbps.id_modulo = tbo.id
                  WHERE tbo.estado = '1' AND tbps.estado = '1' 
                    AND tbps.id_cooperativa = (
                        SELECT ag1.id_institucion 
                        FROM tb_agencia ag1 
                        WHERE id_agencia = ? 
                        LIMIT 1
                    ) 
                    AND tbo.rama = ? 
                  GROUP BY tbo.id 
                  ORDER BY tbo.orden ASC";

        try {
            $result = $this->database->getAllResults($query, [$idAgencia, $rama]);

            if (empty($result)) {
                throw new SoftException("No tiene permiso a ningún Módulo del sistema");
            }

            return [
                'success' => true,
                'data' => $result,
                'message' => null
            ];
        } catch (SoftException $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            $codeError = Log::errorWithCode(
                "Error al obtener módulos de la institución",
                __FILE__,
                __LINE__,
                '',
                0,
                ['idAgencia' => $idAgencia, 'rama' => $rama, 'error' => $e->getMessage()]
            );
            return [
                'success' => false,
                'data' => null,
                'message' => "Error interno. Código: {$codeError}"
            ];
        }
    }

    /**
     * Obtiene los permisos de un usuario para un módulo específico
     * Incluye la jerarquía completa: menú -> submenú
     * 
     * @param int $idUser ID del usuario
     * @param string $rama Rama del módulo
     * @param int $idModulo ID del módulo
     * @return array{success: bool, data: array|null, message: string|null}
     */
    public function getUserModulePermissions(int $idUser, string $rama, int $idModulo): array
    {
        $query = "SELECT 
                    tbp.id_usuario,
                    tbs.id AS menu,
                    tbs.descripcion,
                    tbm.id AS opcion,
                    tbm.condi,
                    tbm.file,
                    tbm.caption
                  FROM tb_usuario tbu
                  INNER JOIN tb_permisos2 tbp ON tbu.id_usu = tbp.id_usuario
                  INNER JOIN {$this->dbNameGeneral}.tb_submenus tbm ON tbp.id_submenu = tbm.id
                  INNER JOIN {$this->dbNameGeneral}.tb_menus tbs ON tbm.id_menu = tbs.id
                  INNER JOIN {$this->dbNameGeneral}.tb_modulos tbo ON tbs.id_modulo = tbo.id
                  INNER JOIN {$this->dbNameGeneral}.tb_permisos_modulos tbps ON tbo.id = tbps.id_modulo
                  WHERE tbu.id_usu = ? 
                    AND tbo.estado = '1' 
                    AND tbs.estado = '1' 
                    AND tbm.estado = '1' 
                    AND tbps.estado = '1' 
                    AND tbps.id_cooperativa = (
                        SELECT ag1.id_institucion 
                        FROM tb_agencia ag1 
                        LIMIT 1
                    ) 
                    AND tbo.rama = ? 
                    AND tbo.id = ? 
                  ORDER BY tbo.orden, tbs.orden, tbs.id, tbm.orden ASC";

        try {
            $result = $this->database->getAllResults($query, [$idUser, $rama, $idModulo]);

            if (empty($result)) {
                throw new SoftException("No tiene ningún permiso otorgado a éste módulo");
            }

            return [
                'success' => true,
                'data' => $result,
                'message' => null
            ];
        } catch (SoftException $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage()
            ];
        } catch (Exception $e) {
            $codeError = Log::errorWithCode(
                "Error al obtener permisos del usuario para el módulo",
                __FILE__,
                __LINE__,
                '',
                0,
                ['idUser' => $idUser, 'rama' => $rama, 'idModulo' => $idModulo, 'error' => $e->getMessage()]
            );
            return [
                'success' => false,
                'data' => null,
                'message' => "Error interno. Código: {$codeError}"
            ];
        }
    }

    /**
     * Obtiene la estructura completa de permisos para un usuario
     * Agrupa los módulos con sus menús y submenús correspondientes
     * 
     * @param int $idUser ID del usuario
     * @param int $idAgencia ID de la agencia
     * @param string $rama Rama del módulo
     * @return array{success: bool, data: array|null, message: string|null}
     */
    public function getUserFullPermissions(int $idUser, int $idAgencia, string $rama): array
    {
        // Primero obtener los módulos permitidos para la institución
        $modulesResult = $this->getInstitutionModules($idAgencia, $rama);

        if (!$modulesResult['success']) {
            return $modulesResult;
        }

        $modules = $modulesResult['data'];
        $fullStructure = [];

        // Para cada módulo, obtener los permisos del usuario
        foreach ($modules as $module) {
            $permissionsResult = $this->getUserModulePermissions(
                $idUser,
                $rama,
                $module['id']
            );

            // Solo incluir módulos donde el usuario tiene al menos un permiso
            if ($permissionsResult['success']) {
                $fullStructure[] = [
                    'module' => $module,
                    'permissions' => $permissionsResult['data']
                ];
            }
        }

        if (empty($fullStructure)) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'No tiene permisos asignados en ningún módulo'
            ];
        }

        return [
            'success' => true,
            'data' => $fullStructure,
            'message' => null
        ];
    }

    /**
     * Agrupa los permisos planos en una estructura jerárquica menú -> submenús
     * 
     * @param array $flatPermissions Permisos planos del usuario
     * @return array Estructura agrupada por menús
     */
    public function groupPermissionsByMenu(array $flatPermissions): array
    {
        $grouped = [];

        foreach ($flatPermissions as $permission) {
            $menuId = $permission['menu'];
            $menuDescription = $permission['descripcion'];

            if (!isset($grouped[$menuId])) {
                $grouped[$menuId] = [
                    'menu' => $menuId,
                    'descripcion' => $menuDescription,
                    'submenus' => []
                ];
            }

            $grouped[$menuId]['submenus'][] = [
                'opcion' => $permission['opcion'],
                'condi' => $permission['condi'],
                'file' => $permission['file'],
                'caption' => $permission['caption']
            ];
        }

        return array_values($grouped);
    }

    /**
     * Verifica si un usuario tiene permiso a un submenú específico
     * También valida que la institución tenga permiso al módulo padre
     * 
     * @param int $idUser ID del usuario
     * @param int $idSubmenu ID del submenú
     * @return bool
     */
    public function userHasSubmenuPermission(int $idUser, int $idSubmenu): bool
    {
        $query = "SELECT COUNT(*) as tiene_permiso
                  FROM tb_usuario tbu
                  INNER JOIN tb_permisos2 tbp ON tbu.id_usu = tbp.id_usuario
                  INNER JOIN {$this->dbNameGeneral}.tb_submenus tbm ON tbp.id_submenu = tbm.id
                  INNER JOIN {$this->dbNameGeneral}.tb_menus tbs ON tbm.id_menu = tbs.id
                  INNER JOIN {$this->dbNameGeneral}.tb_modulos tbo ON tbs.id_modulo = tbo.id
                  INNER JOIN {$this->dbNameGeneral}.tb_permisos_modulos tbps ON tbo.id = tbps.id_modulo
                  WHERE tbu.id_usu = ? 
                    AND tbp.id_submenu = ?
                    AND tbo.estado = '1' 
                    AND tbs.estado = '1' 
                    AND tbm.estado = '1' 
                    AND tbps.estado = '1' 
                    AND tbps.id_cooperativa = (
                        SELECT ag1.id_institucion 
                        FROM tb_agencia ag1 
                        LIMIT 1
                    )";

        try {
            $result = $this->database->getSingleResult($query, [$idUser, $idSubmenu]);
            return isset($result['tiene_permiso']) && $result['tiene_permiso'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Verifica si una institución tiene permiso a un módulo
     * 
     * @param int $idAgencia ID de la agencia
     * @param int $idModulo ID del módulo
     * @return bool
     */
    public function institutionHasModulePermission(int $idAgencia, int $idModulo): bool
    {
        $query = "SELECT COUNT(*) as tiene_permiso
                  FROM {$this->dbNameGeneral}.tb_permisos_modulos tbps
                  WHERE tbps.id_modulo = ? 
                    AND tbps.estado = '1'
                    AND tbps.id_cooperativa = (
                        SELECT ag1.id_institucion 
                        FROM tb_agencia ag1 
                        WHERE id_agencia = ? 
                        LIMIT 1
                    )";

        try {
            $result = $this->database->getSingleResult($query, [$idModulo, $idAgencia]);
            return isset($result['tiene_permiso']) && $result['tiene_permiso'] > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene todos los submenús a los que un usuario tiene acceso
     * 
     * @param int $idUser ID del usuario
     * @return array Lista de IDs de submenús permitidos
     */
    public function getUserAllowedSubmenus(int $idUser): array
    {
        $query = "SELECT DISTINCT tbp.id_submenu
                  FROM tb_usuario tbu
                  INNER JOIN tb_permisos2 tbp ON tbu.id_usu = tbp.id_usuario
                  INNER JOIN {$this->dbNameGeneral}.tb_submenus tbm ON tbp.id_submenu = tbm.id
                  INNER JOIN {$this->dbNameGeneral}.tb_menus tbs ON tbm.id_menu = tbs.id
                  INNER JOIN {$this->dbNameGeneral}.tb_modulos tbo ON tbs.id_modulo = tbo.id
                  INNER JOIN {$this->dbNameGeneral}.tb_permisos_modulos tbps ON tbo.id = tbps.id_modulo
                  WHERE tbu.id_usu = ? 
                    AND tbo.estado = '1' 
                    AND tbs.estado = '1' 
                    AND tbm.estado = '1' 
                    AND tbps.estado = '1' 
                    AND tbps.id_cooperativa = (
                        SELECT ag1.id_institucion 
                        FROM tb_agencia ag1 
                        LIMIT 1
                    )";

        try {
            $results = $this->database->getAllResults($query, [$idUser]);
            return array_column($results, 'id_submenu');
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Método para compatibilidad con funciones legacy
     * Retorna en el formato antiguo [0/1, data/mensaje]
     * 
     * @param int $idAgencia
     * @param string $rama
     * @return array [0/1, data/mensaje]
     */
    public function getPermisosModules(int $idAgencia, string $rama): array
    {
        $result = $this->getInstitutionModules($idAgencia, $rama);

        if ($result['success']) {
            return [1, $result['data']];
        } else {
            return [0, $result['message']];
        }
    }

    /**
     * Método para compatibilidad con funciones legacy
     * Retorna en el formato antiguo [0/1, data/mensaje]
     * 
     * @param int $idUser
     * @param string $rama
     * @param int $idModulo
     * @return array [0/1, data/mensaje]
     */
    public function getPermisosUser(int $idUser, string $rama, int $idModulo): array
    {
        $result = $this->getUserModulePermissions($idUser, $rama, $idModulo);

        if ($result['success']) {
            return [1, $result['data']];
        } else {
            return [0, $result['message']];
        }
    }
}
