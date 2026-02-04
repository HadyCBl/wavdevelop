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
// Configuración de la base de datos
require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

use App\Generic\DatabaseBackupService;
use App\Generic\DatabaseServiceBackup;

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

    // Ejemplo 1: Crear un backup completo
    echo "Creando backup completo...\n";
    $result = $backupService->createFullBackup();

    if ($result['success']) {
        echo "✓ Backup creado exitosamente\n";
        echo "  Archivo: {$result['filename']}\n";
        echo "  Ruta: {$result['path']}\n";
        echo "  Tamaño: " . formatBytes($result['size']) . "\n";

        // Comprimir el backup
        echo "\nComprimiendo backup...\n";
        $compressResult = $backupService->compressBackup($result['filename']);

        if ($compressResult['success']) {
            echo "✓ Backup comprimido exitosamente\n";
            echo "  Archivo comprimido: {$compressResult['filename']}\n";
            echo "  Tamaño comprimido: " . formatBytes($compressResult['size']) . "\n";
        }
    } else {
        echo "✗ Error al crear backup: {$result['error']}\n";
    }

    // Ejemplo 2: Listar backups existentes
    // echo "\nListando backups existentes:\n";
    // $backups = $backupService->listBackups();

    // foreach ($backups as $backup) {
    //     echo "- {$backup['filename']} (" . formatBytes($backup['size']) . ") - {$backup['created']}\n";
    // }

    // // Ejemplo 3: Crear backup con nombre personalizado
    // echo "\nCreando backup con nombre personalizado...\n";
    // $customResult = $backupService->createFullBackup('backup_personalizado_' . date('Y-m-d') . '.sql');

    // if ($customResult['success']) {
    //     echo "✓ Backup personalizado creado: {$customResult['filename']}\n";
    // }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Función auxiliar para formatear bytes
function formatBytes($bytes, $precision = 2)
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB');

    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }

    return round($bytes, $precision) . ' ' . $units[$i];
}

// Ejemplo de uso con manejo de errores más robusto
function createScheduledBackup($config)
{
    try {
        $backupService = new DatabaseBackupService(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['backup_path']
        );

        // Crear backup
        $result = $backupService->createFullBackup();

        if ($result['success']) {
            // Comprimir
            $compressResult = $backupService->compressBackup($result['filename']);

            // Log del resultado
            $logMessage = date('Y-m-d H:i:s') . " - Backup creado: " . $result['filename'];

            if ($compressResult['success']) {
                $logMessage .= " (comprimido a {$compressResult['filename']})";
            }

            file_put_contents('backup_log.txt', $logMessage . "\n", FILE_APPEND);

            // Limpiar backups antiguos (mantener solo los últimos 7)
            cleanOldBackups($backupService, 7);

            return true;
        } else {
            // Log del error
            $errorMessage = date('Y-m-d H:i:s') . " - Error: " . $result['error'];
            file_put_contents('backup_log.txt', $errorMessage . "\n", FILE_APPEND);

            return false;
        }
    } catch (Exception $e) {
        $errorMessage = date('Y-m-d H:i:s') . " - Excepción: " . $e->getMessage();
        file_put_contents('backup_log.txt', $errorMessage . "\n", FILE_APPEND);

        return false;
    }
}

function cleanOldBackups($backupService, $keepCount)
{
    $backups = $backupService->listBackups();

    // Ordenar por fecha (más reciente primero)
    usort($backups, function ($a, $b) {
        return strtotime($b['created']) - strtotime($a['created']);
    });

    // Eliminar los backups más antiguos
    for ($i = $keepCount; $i < count($backups); $i++) {
        $backupService->deleteBackup($backups[$i]['filename']);
    }
}
