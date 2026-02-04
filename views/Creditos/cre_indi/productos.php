<?php

// Parámetros recibidos vía GET.
$page = $_GET['page'] ?? 1;
$perPage = $_GET['perPage'] ?? 10;
include __DIR__ . '/../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../../src/funcphp/func_gen.php';
$selectColumns = ['id', 'nombre', 'descripcion', 'monto_maximo', 'tasa_interes', 'dias_calculo', 'id_tipo_periodo', 'diasCorridos'];

$showmensaje = false;
try {
    $database->openConnection();
    $productos = $database->selectColumns('cre_productos', $selectColumns, "estado=1");
    if (empty($productos)) {
        $showmensaje = true;
        throw new Exception("No se encontraron productos activos");
    }

    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}

function getPaginatedProducts($page, $perPage = 10) {
    global $productos;
    $start = ($page - 1) * $perPage;
    return array_slice($productos, $start, $perPage);
}



header('Content-Type: application/json');
echo json_encode(getPaginatedProducts($page, $perPage));
