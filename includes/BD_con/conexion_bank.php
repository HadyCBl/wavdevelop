<?php
require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Usar las variables correctas para Banca Virtual
$vb_host = $_ENV['DB_VIRTUAL_HOST'] ?? '';
$vb_port = $_ENV['DB_VIRTUAL_PORT'] ?? '3306';
$vb_user = $_ENV['DB_VIRTUAL_USERNAME'] ?? '';
$vb_password = $_ENV['DB_VIRTUAL_PASSWORD'] ?? '';
$vb_name = $_ENV['DB_VIRTUAL_DATABASE'] ?? '';
$type_timezone = $_ENV['BANDERA_TIMEZONE'] ?? '';

try {
    $virtual = mysqli_connect($vb_host, $vb_user, $vb_password, $vb_name, $vb_port);
    if (mysqli_connect_errno()) {
        error_log('Error de conexión a base de datos virtual: ' . mysqli_connect_error());
        error_log('Intentando conectar a: ' . $vb_host . ':' . $vb_port . ' DB: ' . $vb_name . ' User: ' . $vb_user);
        $virtual = null;
    } else {
        mysqli_set_charset($virtual, 'utf8mb4');

        if ($type_timezone == '1') {
            $virtual->query("SET time_zone = 'America/Guatemala'");
        }
    }
} catch (Exception $e) {
    error_log('Excepción en conexión a base de datos virtual: ' . $e->getMessage());
    $virtual = null;
}
