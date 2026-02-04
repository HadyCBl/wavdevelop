<?php
// get_dashboard_data.php


include __DIR__ . '/../../includes/Config/config.php';
// include __DIR__ . '/../../includes/BD_con/db_con.php';
// include __DIR__ . '/../../includes/Config/database.php';

session_start();
if (!isset($_SESSION['usu'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

use Creditos\Utilidades\DashboardCreditosData;

// $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
date_default_timezone_set('America/Guatemala');

$dashboard = new DashboardCreditosData();

try {
    $data = $dashboard->getDashboardData();

    // $dashboard->clearCache();
    header('Content-Type: application/json');
    echo json_encode($data);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    // $database->closeConnection();
}
