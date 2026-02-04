<?php
// shell_exec('curl -X POST "https://api.telegram.org/bot7528087185:AAEFy3yE9EgCmjFBanNnmYg-vWqXx5jQYzk/setWebhook?url=https://adminms.sotecprostore.com/telegram/webhook"');
// require_once __DIR__ . '/../../vendor/autoload.php'; 
// $actualizador = new \App\Config\SystemUpdate();
// $actualizador->tasksPending();

//VERIFICAR SI TIENE EL PARAMETRO EN LA URL
if (!isset($_GET['test']) || $_GET['test'] !== 'soygay') {
    //mostrar dedo de en medio
    echo "🖕";
    return false;
}

// Configuración de memoria y tiempo de ejecución
ini_set('memory_limit', '1024M'); // Aumentar límite de memoria
ini_set('max_execution_time', 0); // Sin límite de tiempo
set_time_limit(0);

require_once(__DIR__ . '/../../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];  
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

use App\Generic\DatabaseBackupService;
use App\Generic\DatabaseServiceBackup;
use Micro\Helpers\Log;

$config = [
    'host' => $db_host,
    'username' => $db_user,
    'password' => $db_password,
    'database' => $db_name,
    'backup_path' => 'backups/' // Directorio donde se guardarán los backups
];

try {
    // Crear instancia del servicio
    $backupService = new DatabaseServiceBackup(
        // $backupService = new DatabaseBackupService(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database'],
        $config['backup_path']
    );

    $filename = "backup_" . $db_name . "_" . date("Ymd_His") . ".sql";

    Log::info("Iniciando backup de la base de datos '$db_name'.");

    // Configurar headers para descarga
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Desactivar output buffering del sistema para permitir streaming
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Usar el método de streaming directo para evitar problemas de memoria
    $backupService->streamFullBackup();

} catch (Exception $e) {
    // Limpiar cualquier output previo en caso de error
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Cambiar headers si aún no se han enviado
    if (!headers_sent()) {
        header('Content-Type: text/plain');
        header_remove('Content-Disposition');
    }
    
    echo "Error: " . $e->getMessage() . "\n";
    Log::error("Error during database backup: " . $e->getMessage());
}
