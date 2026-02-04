<?php

/**
 * VERIFICAR ESTADO DE LA APLICACIÓN
 */
// ========================================
// 1 VERIFICACIÓN DE DEPENDENCIAS
// ========================================
if (!file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    http_response_code(503);
    include __DIR__ . '/../../views/maintenance.php';
    exit('COD: 2103');
}

// ========================================
// 2 CARGAR AUTOLOADER
// ========================================
require __DIR__ . '/../../vendor/autoload.php';

// ========================================
// 3 CARGAR VARIABLES DE ENTORNO
// ========================================
if (file_exists(__DIR__ . '/../../.env')) {
    try {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
        $dotenv->load();
    } catch (Exception $e) {
        error_log("Error cargando .env: " . $e->getMessage());
    }
}

// ========================================
// 4 VERIFICAR MODO MANTENIMIENTO
// ========================================
$maintenanceMode = $_ENV['APP_MAINTENANCE'] ?? getenv('APP_MAINTENANCE') ?? 'false';
$maintenanceIPs = $_ENV['MAINTENANCE_ALLOWED_IPS'] ?? getenv('MAINTENANCE_ALLOWED_IPS') ?? '';

if ($maintenanceMode === 'true') {
    $allowedIPs = array_filter(array_map('trim', explode(',', $maintenanceIPs)));
    $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';

    // Permitir acceso solo a IPs autorizadas
    if (!in_array($clientIP, $allowedIPs)) {
        http_response_code(503);
        header('Retry-After: 3600'); // Reintentar en 1 hora
        include __DIR__ . '/../../views/maintenance.php';
        exit();
    }
}

// ========================================
// CONTINÚA LA APLICACIÓN NORMAL
// ========================================
/**
 * FIN VERIFICACION DE ESTADO DE LA APLICACION
 */


// require_once(__DIR__ . '/../../vendor/autoload.php');
// $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
// $dotenv->load();

use Micro\Helpers\Log;
use Micro\Generic\AppConfig;
use Micro\Generic\Asset;
use Micro\Generic\AssetVite;

/**
 * CONFIGURACION DE ILLUMINATE DATABASE (ELOQUENT ORM)
 * Carga opcional - solo si se necesita usar modelos Eloquent
 * Mantiene compatibilidad con DatabaseAdapter existente
 * comentado por el momento, 
 */
// if (file_exists(__DIR__ . '/../../app/config/eloquent.php')) {
//     require_once __DIR__ . '/../../app/config/eloquent.php';
// }

/**
 * CONFIGURACION DE ZONA HORARIA
 */
// Log::info('Setting application timezoneeee.');
setAppTimezone();
// Log::info('Timezone set to: ' . date_default_timezone_get());

/**
 * Configuraciones generales Instancia de la clase AppConfig
 */

$appConfigGeneral = new AppConfig();

/**
 * Configuracion de ruta base
 */

$host = $_ENV['HOST'];
define('BASE_URL', $host);

$isProduction = ($_ENV['APP_ENV'] ?? 'production') === 'production';
// Asset::setEnvironment($isProduction);
// Asset::setHostUrl($host);


/**
 * CONFIGURACION DE VITE
 */
AssetVite::setEnvironment($isProduction);
AssetVite::setHostUrl($host);

if (!$isProduction) {
    AssetVite::enableDevMode(true, 'http://localhost:5173');
}

/**
 * CONFIGURACIONES DE SESSION
 */

/**
 * CONFIGURACIONES DE SESSION FORZADAS
 */
if (session_status() === PHP_SESSION_NONE) {
    // Configuraciones más agresivas
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 0);
    ini_set('session.gc_probability', 1);  // 1% probabilidad
    ini_set('session.gc_divisor', 10);      // 1/10 = 10%
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);

    // Forzar limpieza de sesiones viejas
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.use_only_cookies', 1);

    // SameSite: Strict para aplicaciones Electron (máxima seguridad)
    // Strict = Cookies solo se envían en navegación same-site
    // Perfecto para apps desktop que no reciben enlaces externos
    ini_set('session.cookie_samesite', 'Strict');

    // Log::info(
    //     "Session not active, configuring session settings.",
    //     [
    //         'gc_maxlifetime' => ini_get('session.gc_maxlifetime'),
    //         'cookie_lifetime' => ini_get('session.cookie_lifetime'),
    //         'gc_probability' => ini_get('session.gc_probability'),
    //         'gc_divisor' => ini_get('session.gc_divisor'),
    //         'cookie_httponly' => ini_get('session.cookie_httponly'),
    //         'use_strict_mode' => ini_get('session.use_strict_mode'),
    //         'cookie_secure' => ini_get('session.cookie_secure'),
    //         'use_only_cookies' => ini_get('session.use_only_cookies'),
    //     ]
    // );
}
