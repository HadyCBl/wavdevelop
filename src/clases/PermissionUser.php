<?php
namespace App\Generic;

use Micro\Helpers\Log;
use Exception;

class PermissionUser
{
    private $database;
    private $cacheManager;
    private $userId;
    private $dbNameGeneral;
    private array $userPermissions = [];
    private array $userModules = [];


    public function __construct($userId, $database = null, $dbNameGeneral = null)
    {
        $this->userId = $userId;
        $this->database = $database;
        $this->dbNameGeneral = $dbNameGeneral ?? 'jpxdcegu_bd_general_coopera';
        $this->cacheManager = new CacheManager('permisos_', 21600); // Cache por 6 horas
    }

    /**
     * Carga los permisos del usuario para un módulo específico
     */
    public function loadUserPermissions(string $rama, int $idModulo): array
    {
        $cacheKey = "user_{$this->userId}_rama_{$rama}_modulo_{$idModulo}";
        
        // Intentar obtener del caché
        //$permisos = $this->cacheManager->get($cacheKey);
        $permisos = null; // Deshabilitar caché temporalmente
        
        if ($permisos !== null) {
            $this->userPermissions[$rama][$idModulo] = $permisos;
            return [1, $permisos];
        }

        // Si no está en caché, consultar BD
        try {
            $permisos = $this->getPermisosFromDatabase($rama, $idModulo);
            
            // Guardar en caché y memoria
            $this->cacheManager->set($cacheKey, $permisos);
            $this->userPermissions[$rama][$idModulo] = $permisos;
            
            return [1, $permisos];
        } catch (Exception $e) {
            return [0, $e->getMessage()];
        }
    }

    /**
     * Carga los módulos disponibles para el usuario
     */
    public function loadUserModules(int $idAgencia, string $rama): array
    {
        $cacheKey = "modules_user_{$this->userId}_agencia_{$idAgencia}_rama_{$rama}";
        
        // Intentar obtener del caché
        $modulos = $this->cacheManager->get($cacheKey);
        
        if ($modulos !== null) {
            $this->userModules[$rama] = $modulos;
            return [1, $modulos];
        }

        // Si no está en caché, consultar BD
        try {
            $modulos = $this->getModulesFromDatabase($idAgencia, $rama);
            
            // Guardar en caché y memoria
            $this->cacheManager->set($cacheKey, $modulos);
            $this->userModules[$rama] = $modulos;
            
            return [1, $modulos];
        } catch (Exception $e) {
            return [0, $e->getMessage()];
        }
    }

    /**
     * Verifica si el usuario tiene un permiso específico
     */
    public function hasPermission(string $permission, string $rama = 'G', int $idModulo = 1): bool
    {
        // Cargar permisos si no están en memoria
        if (!isset($this->userPermissions[$rama][$idModulo])) {
            $result = $this->loadUserPermissions($rama, $idModulo);
            if ($result[0] == 0) {
                return false;
            }
        }

        $permisos = $this->userPermissions[$rama][$idModulo];
        
        // Buscar el permiso específico
        foreach ($permisos as $permiso) {
            if ($permiso['condi'] === $permission) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Genera el menú HTML basado en permisos
     */
    public function generateMenu(string $rama = 'G', int $idModulo = 1): string
    {
        $result = $this->loadUserPermissions($rama, $idModulo);
        if ($result[0] == 0) {
            return '<div class="alert alert-warning">No tiene permisos en este módulo</div>';
        }

        Log::info("permisos", $result);

        $permisos = $result[1];
        $menuHtml = '';
        $currentMenu = '';
        $currentMenuDescripcion = '';
        $menuItems = [];

        foreach ($permisos as $permiso) {
            if ($currentMenu !== $permiso['menu']) {
                // Cerrar menú anterior si existe
                if (!empty($menuItems)) {
                    $menuHtml .= $this->buildMenuDropdown($currentMenuDescripcion, $menuItems);
                    $menuItems = [];
                }
                $currentMenu = $permiso['menu'];
                $currentMenuDescripcion = $permiso['descripcion'];
            }

            $menuItems[] = [
                'caption' => $permiso['caption'],
                'condi' => $permiso['condi'],
                'file' => $permiso['file']
            ];
        }

        // Cerrar último menú
        if (!empty($menuItems)) {
            $menuHtml .= $this->buildMenuDropdown($currentMenuDescripcion, $menuItems);
        }

        return $menuHtml;
    }

    /**
     * Construye un dropdown de menú
     */
    private function buildMenuDropdown(string $menuTitle, array $items): string
    {
        $html = '<div class="btn-group me-1" role="group">';
        $html .= '<button class="btn btn-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">';
        $html .= htmlspecialchars($menuTitle) . ' <span class="caret"></span></button>';
        $html .= '<ul class="dropdown-menu">';

        foreach ($items as $item) {
            $html .= '<li><a class="dropdown-item" style="cursor: pointer;" ';
            $html .= 'onclick="printdiv(\'' . htmlspecialchars($item['condi']) . '\', \'#cuadro\', ';
            $html .= '\'' . htmlspecialchars($item['file']) . '\', \'0\')">';
            $html .= htmlspecialchars($item['caption']) . '</a></li>';
        }

        $html .= '</ul></div>';
        return $html;
    }

    /**
     * Limpia el caché de permisos del usuario
     */
    public function clearCache(): bool
    {
        $this->userPermissions = [];
        $this->userModules = [];
        
        // Limpiar caché específico del usuario
        $patterns = [
            "user_{$this->userId}_*",
            "modules_user_{$this->userId}_*"
        ];

        $cleared = true;
        $keys = $this->cacheManager->listKeys();
        
        foreach ($keys as $keyInfo) {
            $key = $keyInfo['key'];
            if (strpos($key, "user_{$this->userId}") === 0 || 
                strpos($key, "modules_user_{$this->userId}") === 0) {
                if (!$this->cacheManager->delete($key)) {
                    $cleared = false;
                }
            }
        }

        return $cleared;
    }

    /**
     * Obtiene permisos desde la base de datos (función original)
     */
    private function getPermisosFromDatabase(string $rama, int $idModulo): array
    {
        if ($this->database === null) {
            throw new Exception("Database connection not provided");
        }

        $query = "SELECT tbp.id_usuario, tbs.id AS menu, tbs.descripcion, tbm.id AS opcion, tbm.condi, tbm.`file`, tbm.caption 
                  FROM tb_usuario tbu
                  INNER JOIN tb_permisos2 tbp ON tbu.id_usu=tbp.id_usuario
                  INNER JOIN {$this->dbNameGeneral}.tb_submenus tbm ON tbp.id_submenu=tbm.id
                  INNER JOIN {$this->dbNameGeneral}.tb_menus tbs ON tbm.id_menu =tbs.id
                  INNER JOIN {$this->dbNameGeneral}.tb_modulos tbo ON tbs.id_modulo =tbo.id
                  INNER JOIN {$this->dbNameGeneral}.tb_permisos_modulos tbps ON tbo.id=tbps.id_modulo
                  WHERE tbu.id_usu=? AND tbo.estado='1' AND tbs.estado='1' AND tbm.estado='1' AND tbps.estado='1' 
                  AND tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1) 
                  AND tbo.rama=? AND tbo.id=? 
                  ORDER BY tbo.orden, tbs.orden, tbs.id, tbm.orden ASC";

        $result = $this->database->getAllResults($query, [$this->userId, $rama, $idModulo]);
        
        if (empty($result)) {
            throw new Exception("No tiene ningún permiso otorgado a éste módulo");
        }

        return $result;
    }

    /**
     * Obtiene módulos desde la base de datos (función original)
     */
    private function getModulesFromDatabase(int $idAgencia, string $rama): array
    {
        if ($this->database === null) {
            throw new Exception("Database connection not provided");
        }

        $query = "SELECT tbo.id,tbo.descripcion, tbo.icon, tbo.ruta, tbo.rama 
                  FROM {$this->dbNameGeneral}.tb_permisos_modulos tbps
                  INNER JOIN {$this->dbNameGeneral}.tb_modulos tbo ON tbps.id_modulo =tbo.id
                  WHERE tbo.estado='1' AND tbps.estado='1' 
                  AND tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 WHERE id_agencia=? LIMIT 1) 
                  AND tbo.rama=? 
                  GROUP BY tbo.id 
                  ORDER BY tbo.orden ASC";

        $result = $this->database->getAllResults($query, [$idAgencia, $rama]);
        
        if (empty($result)) {
            throw new Exception("No tiene permiso a ningún Módulo del sistema");
        }

        return $result;
    }

    /**
     * Obtiene la agencia del usuario (implementar según tu lógica)
     */
    private function getUserAgency(): int
    {
        // Implementar según tu lógica de sesión o base de datos
        return $_SESSION['id_agencia'] ?? 1;
    }

    /**
     * Getters
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getCacheManager(): CacheManager
    {
        return $this->cacheManager;
    }
}