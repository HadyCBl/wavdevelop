<?php


return;
require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();


use App\Generic\MysqlDumpBackupService;
use Micro\Helpers\Log;

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];
// Configuración
$config = [
    'host' => $db_host,
    'username' => $db_user,
    'password' => $db_password,
    'database' => $db_name,
    'backup_path' => 'backups/',
    'mysqldump_path' => 'mysqldump' // o '/usr/bin/mysqldump' si está en otra ruta
];

Log::info("Configuración de backup: " . json_encode($config));

try {
    // Crear instancia del servicio
    $backup = new MysqlDumpBackupService(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database'],
        $config['backup_path'],
        $config['mysqldump_path']
    );
    
    echo "=== SERVICIO DE BACKUP CON MYSQLDUMP ===\n\n";
    
    // 1. Backup completo (recomendado)
    echo "1. Creando backup completo...\n";
    $result = $backup->createFullBackup();
    
    if ($result['success']) {
        echo "✓ Backup completo creado: {$result['filename']}\n";
        echo "  Tamaño: " . formatBytes($result['size']) . "\n\n";
    } else {
        echo "✗ Error: {$result['error']}\n\n";
    }
    
    // 2. Backup comprimido (ahorra espacio)
    // echo "2. Creando backup comprimido...\n";
    // $compressedResult = $backup->createCompressedBackup();
    
    // if ($compressedResult['success']) {
    //     echo "✓ Backup comprimido creado: {$compressedResult['filename']}\n";
    //     echo "  Tamaño: " . formatBytes($compressedResult['size']) . "\n\n";
    // } else {
    //     echo "✗ Error: {$compressedResult['error']}\n\n";
    // }
    
    // // 3. Solo estructura (para desarrollo)
    // echo "3. Creando backup solo estructura...\n";
    // $structureResult = $backup->createStructureOnlyBackup();
    
    // if ($structureResult['success']) {
    //     echo "✓ Backup de estructura creado: {$structureResult['filename']}\n";
    //     echo "  Tamaño: " . formatBytes($structureResult['size']) . "\n\n";
    // }
    
    // // 4. Solo datos
    // echo "4. Creando backup solo datos...\n";
    // $dataResult = $backup->createDataOnlyBackup();
    
    // if ($dataResult['success']) {
    //     echo "✓ Backup de datos creado: {$dataResult['filename']}\n";
    //     echo "  Tamaño: " . formatBytes($dataResult['size']) . "\n\n";
    // }
    
    // // 5. Listar todos los backups
    // echo "5. Listando backups existentes:\n";
    // $backups = $backup->listBackups();
    
    // foreach ($backups as $bkp) {
    //     echo "- {$bkp['filename']} ({$bkp['type']}) - " . formatBytes($bkp['size']) . " - {$bkp['created']}\n";
    // }
    // echo "\n";
    
    // // 6. Información detallada de un backup
    // if (!empty($backups)) {
    //     echo "6. Información del backup más reciente:\n";
    //     $info = $backup->getBackupInfo($backups[0]['filename']);
        
    //     if ($info) {
    //         echo "  Archivo: {$info['filename']}\n";
    //         echo "  Tamaño: " . formatBytes($info['size']) . "\n";
    //         echo "  Creado: {$info['created']}\n";
    //         echo "  Tipo: {$info['type']}\n";
    //         if (isset($info['host'])) echo "  Host: {$info['host']}\n";
    //         if (isset($info['generation_time'])) echo "  Generado: {$info['generation_time']}\n";
    //     }
    //     echo "\n";
    // }
    
    // // 7. Limpiar backups antiguos
    // echo "7. Limpiando backups antiguos (mantener últimos 5)...\n";
    // $deleted = $backup->cleanOldBackups(5);
    // echo "✓ {$deleted} backups antiguos eliminados\n\n";
    
    // 8. Ejemplo de restauración (comentado por seguridad)
    /*
    echo "8. Restaurando backup...\n";
    if (!empty($backups)) {
        $restoreResult = $backup->restoreBackup($backups[0]['filename']);
        
        if ($restoreResult['success']) {
            echo "✓ {$restoreResult['message']}\n";
        } else {
            echo "✗ {$restoreResult['error']}\n";
        }
    }
    */
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// Función para backups automáticos/programados
function scheduleBackup($config) {
    $backup = new MysqlDumpBackupService(
        $config['host'],
        $config['username'],
        $config['password'],
        $config['database'],
        $config['backup_path'],
        $config['mysqldump_path']
    );
    
    // Crear backup comprimido
    $result = $backup->createCompressedBackup();
    
    if ($result['success']) {
        // Log exitoso
        $logMessage = date('Y-m-d H:i:s') . " - Backup automático exitoso: {$result['filename']} (" . formatBytes($result['size']) . ")";
        file_put_contents('backup_schedule.log', $logMessage . "\n", FILE_APPEND);
        
        // Limpiar backups antiguos
        $backup->cleanOldBackups(7);
        
        return true;
    } else {
        // Log de error
        $logMessage = date('Y-m-d H:i:s') . " - Error en backup automático: {$result['error']}";
        file_put_contents('backup_schedule.log', $logMessage . "\n", FILE_APPEND);
        
        return false;
    }
}

// Script para cron job
/*
#!/bin/bash
# Agregar al crontab para backup diario a las 2 AM:
# 0 2 * * * /usr/bin/php /path/to/backup_script.php

$config = [
    'host' => 'localhost',
    'username' => 'backup_user',
    'password' => 'backup_pass',
    'database' => 'mi_base_datos',
    'backup_path' => '/backups/',
    'mysqldump_path' => '/usr/bin/mysqldump'
];

$success = scheduleBackup($config);
exit($success ? 0 : 1);
*/