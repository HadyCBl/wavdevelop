<?php
/**
 * PRIMERA VERSION CON PDO CONTROLADO
 */
namespace App\Generic;

use Exception;
use PDO;
use PDOException;

class DatabaseBackupService
{
    private $host;
    private $username;
    private $password;
    private $database;
    private $pdo;
    private $backupPath;

    public function __construct($host, $username, $password, $database, $backupPath = 'backups/')
    {
        $this->host = $host;
        $this->username = $username;
        $this->password = $password;
        $this->database = $database;
        $this->backupPath = $backupPath;

        // Crear directorio de backups si no existe
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }

        $this->connect();
    }

    private function connect()
    {
        try {
            $this->pdo = new PDO(
                "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            throw new Exception("Error de conexión: " . $e->getMessage());
        }
    }

    public function createFullBackup($filename = null)
    {
        if ($filename === null) {
            $filename = $this->database . '_backup_' . date('Y-m-d_H-i-s') . '.sql';
        }

        $backupFile = $this->backupPath . $filename;
        $sql = '';

        try {
            // Header del backup
            $sql .= $this->getBackupHeader();

            // Backup de tablas y datos
            $sql .= $this->backupTables();

            // Backup de views
            $sql .= $this->backupViews();

            // Backup de funciones
            $sql .= $this->backupFunctions();

            // Backup de procedimientos
            $sql .= $this->backupProcedures();

            // Backup de triggers
            $sql .= $this->backupTriggers();

            // Footer del backup
            $sql .= $this->getBackupFooter();

            // Escribir archivo
            if (file_put_contents($backupFile, $sql) === false) {
                throw new Exception("Error al escribir el archivo de backup");
            }

            return [
                'success' => true,
                'filename' => $filename,
                'path' => $backupFile,
                'size' => filesize($backupFile)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function getBackupHeader()
    {
        return "-- =============================================\n" .
            "-- Backup completo de la base de datos: {$this->database}\n" .
            "-- Fecha: " . date('Y-m-d H:i:s') . "\n" .
            "-- Generado por: DatabaseBackupService\n" .
            "-- =============================================\n\n" .
            "SET FOREIGN_KEY_CHECKS=0;\n" .
            "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n" .
            "SET time_zone = \"+00:00\";\n\n" .
            "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n" .
            "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n" .
            "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n" .
            "/*!40101 SET NAMES utf8mb4 */;\n\n";
    }

    private function getBackupFooter()
    {
        return "\nSET FOREIGN_KEY_CHECKS=1;\n" .
            "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n" .
            "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n" .
            "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n";
    }

    private function backupTables()
    {
        $sql = "-- =============================================\n";
        $sql .= "-- ESTRUCTURA Y DATOS DE TABLAS\n";
        $sql .= "-- =============================================\n\n";

        // Obtener todas las tablas
        $stmt = $this->pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tables as $table) {
            $sql .= "-- Tabla: $table\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";

            // Obtener estructura de la tabla
            $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
            $row = $stmt->fetch();
            $sql .= $row['Create Table'] . ";\n\n";

            // Obtener datos de la tabla
            $stmt = $this->pdo->query("SELECT * FROM `$table`");
            $rows = $stmt->fetchAll();

            if (!empty($rows)) {
                $sql .= "-- Datos para la tabla `$table`\n";
                $sql .= "INSERT INTO `$table` VALUES\n";

                $insertRows = [];
                foreach ($rows as $row) {
                    $values = array_map([$this, 'escapeValue'], array_values($row));
                    $insertRows[] = "(" . implode(", ", $values) . ")";
                }

                $sql .= implode(",\n", $insertRows) . ";\n\n";
            }
        }

        return $sql;
    }

    private function backupViews()
    {
        $sql = "-- =============================================\n";
        $sql .= "-- VISTAS\n";
        $sql .= "-- =============================================\n\n";

        try {
            $stmt = $this->pdo->query("SELECT TABLE_NAME FROM information_schema.VIEWS WHERE TABLE_SCHEMA = '{$this->database}'");
            $views = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($views as $view) {
                $sql .= "-- Vista: $view\n";
                $sql .= "DROP VIEW IF EXISTS `$view`;\n";

                $stmt = $this->pdo->query("SHOW CREATE VIEW `$view`");
                $row = $stmt->fetch();
                $sql .= $row['Create View'] . ";\n\n";
            }
        } catch (Exception $e) {
            $sql .= "-- Error al obtener vistas: " . $e->getMessage() . "\n\n";
        }

        return $sql;
    }

    private function backupFunctions()
    {
        $sql = "-- =============================================\n";
        $sql .= "-- FUNCIONES\n";
        $sql .= "-- =============================================\n\n";

        try {
            $stmt = $this->pdo->query("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '{$this->database}' AND ROUTINE_TYPE = 'FUNCTION'");
            $functions = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($functions as $function) {
                $sql .= "-- Función: $function\n";
                $sql .= "DROP FUNCTION IF EXISTS `$function`;\n";
                $sql .= "DELIMITER ;;\n";

                $stmt = $this->pdo->query("SHOW CREATE FUNCTION `$function`");
                $row = $stmt->fetch();
                $sql .= $row['Create Function'] . ";;\n";
                $sql .= "DELIMITER ;\n\n";
            }
        } catch (Exception $e) {
            $sql .= "-- Error al obtener funciones: " . $e->getMessage() . "\n\n";
        }

        return $sql;
    }

    private function backupProcedures()
    {
        $sql = "-- =============================================\n";
        $sql .= "-- PROCEDIMIENTOS ALMACENADOS\n";
        $sql .= "-- =============================================\n\n";

        try {
            $stmt = $this->pdo->query("SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA = '{$this->database}' AND ROUTINE_TYPE = 'PROCEDURE'");
            $procedures = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($procedures as $procedure) {
                $sql .= "-- Procedimiento: $procedure\n";
                $sql .= "DROP PROCEDURE IF EXISTS `$procedure`;\n";
                $sql .= "DELIMITER ;;\n";

                $stmt = $this->pdo->query("SHOW CREATE PROCEDURE `$procedure`");
                $row = $stmt->fetch();
                $sql .= $row['Create Procedure'] . ";;\n";
                $sql .= "DELIMITER ;\n\n";
            }
        } catch (Exception $e) {
            $sql .= "-- Error al obtener procedimientos: " . $e->getMessage() . "\n\n";
        }

        return $sql;
    }

    private function backupTriggers()
    {
        $sql = "-- =============================================\n";
        $sql .= "-- TRIGGERS\n";
        $sql .= "-- =============================================\n\n";

        try {
            $stmt = $this->pdo->query("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA = '{$this->database}'");
            $triggers = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($triggers as $trigger) {
                $sql .= "-- Trigger: $trigger\n";
                $sql .= "DROP TRIGGER IF EXISTS `$trigger`;\n";
                $sql .= "DELIMITER ;;\n";

                $stmt = $this->pdo->query("SHOW CREATE TRIGGER `$trigger`");
                $row = $stmt->fetch();
                $sql .= $row['SQL Original Statement'] . ";;\n";
                $sql .= "DELIMITER ;\n\n";
            }
        } catch (Exception $e) {
            $sql .= "-- Error al obtener triggers: " . $e->getMessage() . "\n\n";
        }

        return $sql;
    }

    private function escapeValue($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_numeric($value)) {
            return $value;
        }

        return $this->pdo->quote($value);
    }

    public function listBackups()
    {
        $backups = [];
        $files = glob($this->backupPath . '*.sql');

        foreach ($files as $file) {
            $backups[] = [
                'filename' => basename($file),
                'size' => filesize($file),
                'created' => date('Y-m-d H:i:s', filemtime($file))
            ];
        }

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

    public function compressBackup($filename)
    {
        $file = $this->backupPath . $filename;
        $compressedFile = $file . '.gz';

        if (file_exists($file)) {
            $data = file_get_contents($file);
            $compressed = gzencode($data, 9);

            if (file_put_contents($compressedFile, $compressed) !== false) {
                unlink($file); // Eliminar archivo original
                return [
                    'success' => true,
                    'filename' => basename($compressedFile),
                    'size' => filesize($compressedFile)
                ];
            }
        }

        return ['success' => false, 'error' => 'No se pudo comprimir el archivo'];
    }
}
