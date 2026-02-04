<?php
namespace App\Generic;

class MysqlDumpBackupService
{
    private $host;
    private $username;
    private $password;
    private $database;
    private $backupPath;
    private $mysqldumpPath;
    
    public function __construct($host, $username, $password, $database, $backupPath = 'backups/', $mysqldumpPath = 'mysqldump')
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->backupPath = rtrim($backupPath, '/') . '/';
        $this->mysqldumpPath = $mysqldumpPath;
        
        // Crear directorio si no existe
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    public function createFullBackup($filename = null)
    {
        if ($filename === null) {
            $filename = $this->database . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        $backupFile = $this->backupPath . $filename;
        
        // Comando mysqldump completo
        $command = sprintf(
            '%s --host=%s --user=%s --password=%s --single-transaction --routines --triggers --events --quick --lock-tables=false --databases %s > %s 2>&1',
            $this->mysqldumpPath,
            escapeshellarg($this->host),
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->database),
            escapeshellarg($backupFile)
        );
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $backupFile,
                'size' => filesize($backupFile)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Error en mysqldump ex: ' . implode('\n', $output)
            ];
        }
    }
    
    public function createStructureOnlyBackup($filename = null)
    {
        if ($filename === null) {
            $filename = $this->database . '_structure_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        $backupFile = $this->backupPath . $filename;
        
        // Solo estructura (sin datos)
        $command = sprintf(
            '%s --host=%s --user=%s --password=%s --no-data --routines --triggers --events --databases %s > %s 2>&1',
            $this->mysqldumpPath,
            escapeshellarg($this->host),
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->database),
            escapeshellarg($backupFile)
        );
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $backupFile,
                'size' => filesize($backupFile)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Error en mysqldump: ' . implode('\n', $output)
            ];
        }
    }
    
    public function createDataOnlyBackup($filename = null)
    {
        if ($filename === null) {
            $filename = $this->database . '_data_' . date('Y-m-d_H-i-s') . '.sql';
        }
        
        $backupFile = $this->backupPath . $filename;
        
        // Solo datos (sin estructura)
        $command = sprintf(
            '%s --host=%s --user=%s --password=%s --no-create-info --skip-triggers --databases %s > %s 2>&1',
            $this->mysqldumpPath,
            escapeshellarg($this->host),
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->database),
            escapeshellarg($backupFile)
        );
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $backupFile,
                'size' => filesize($backupFile)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Error en mysqldump: ' . implode('\n', $output)
            ];
        }
    }
    
    public function createCompressedBackup($filename = null)
    {
        if ($filename === null) {
            $filename = $this->database . '_backup_' . date('Y-m-d_H-i-s') . '.sql.gz';
        }
        
        $backupFile = $this->backupPath . $filename;
        
        // Backup comprimido directamente
        $command = sprintf(
            '%s --host=%s --user=%s --password=%s --single-transaction --routines --triggers --events --quick --lock-tables=false --databases %s | gzip > %s 2>&1',
            $this->mysqldumpPath,
            escapeshellarg($this->host),
            escapeshellarg($this->username),
            escapeshellarg($this->password),
            escapeshellarg($this->database),
            escapeshellarg($backupFile)
        );
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($backupFile)) {
            return [
                'success' => true,
                'filename' => $filename,
                'path' => $backupFile,
                'size' => filesize($backupFile)
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Error en mysqldump: ' . implode('\n', $output)
            ];
        }
    }
    
    public function restoreBackup($filename)
    {
        $backupFile = $this->backupPath . $filename;
        
        if (!file_exists($backupFile)) {
            return [
                'success' => false,
                'error' => 'El archivo de backup no existe'
            ];
        }
        
        // Comando para restaurar
        if (pathinfo($filename, PATHINFO_EXTENSION) === 'gz') {
            // Archivo comprimido
            $command = sprintf(
                'gunzip < %s | mysql --host=%s --user=%s --password=%s %s 2>&1',
                escapeshellarg($backupFile),
                escapeshellarg($this->host),
                escapeshellarg($this->username),
                escapeshellarg($this->password),
                escapeshellarg($this->database)
            );
        } else {
            // Archivo normal
            $command = sprintf(
                'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
                escapeshellarg($this->host),
                escapeshellarg($this->username),
                escapeshellarg($this->password),
                escapeshellarg($this->database),
                escapeshellarg($backupFile)
            );
        }
        
        $output = [];
        $returnCode = 0;
        
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            return [
                'success' => true,
                'message' => 'Backup restaurado exitosamente'
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Error al restaurar: ' . implode('\n', $output)
            ];
        }
    }
    
    public function listBackups()
    {
        $backups = [];
        $files = glob($this->backupPath . '*.sql*');
        
        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file)),
                'type' => pathinfo($file, PATHINFO_EXTENSION) === 'gz' ? 'compressed' : 'normal'
            ];
        }
        
        // Ordenar por fecha (más reciente primero)
        usort($backups, function($a, $b) {
            return strtotime($b['created']) - strtotime($a['created']);
        });
        
        return $backups;
    }
    
    public function deleteBackup($filename)
    {
        $file = $this->backupPath . $filename;
        
        if (file_exists($file)) {
            return unlink($file);
        }
        
        return false;
    }
    
    public function cleanOldBackups($keepCount = 7)
    {
        $backups = $this->listBackups();
        
        for ($i = $keepCount; $i < count($backups); $i++) {
            $this->deleteBackup($backups[$i]['filename']);
        }
        
        return count($backups) - $keepCount;
    }
    
    public function getBackupInfo($filename)
    {
        $file = $this->backupPath . $filename;
        
        if (!file_exists($file)) {
            return null;
        }
        
        $info = [
            'filename' => $filename,
            'size' => filesize($file),
            'created' => date('Y-m-d H:i:s', filemtime($file)),
            'type' => pathinfo($file, PATHINFO_EXTENSION) === 'gz' ? 'compressed' : 'normal'
        ];
        
        // Intentar obtener información del header del backup
        if ($info['type'] === 'compressed') {
            $handle = gzopen($file, 'r');
            $header = gzread($handle, 1024);
            gzclose($handle);
        } else {
            $header = file_get_contents($file, false, null, 0, 1024);
        }
        
        // Extraer información del header
        if (preg_match('/-- MySQL dump.*\n-- Host: ([^\s]+).*\n-- Generation Time: ([^\n]+)/', $header, $matches)) {
            $info['host'] = $matches[1];
            $info['generation_time'] = $matches[2];
        }
        
        return $info;
    }
}