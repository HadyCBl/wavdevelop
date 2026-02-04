<?php

use Psr\Http\Message\ResponseInterface;

session_start();
include '../../includes/BD_con/db_con.php'; // conexión mysqli

date_default_timezone_set('America/Guatemala');
$hoy2 = date('Y-m-d H:i:s');

$accion   = $_POST['action']    ?? '';
$configId = $_POST['config_id'] ?? null;
$ajax     = isset($_POST['ajax']);

// Estructura de respuesta por defecto
$response = [
    'status' => 0,
    'msg'    => 'Acción no reconocida'
];

// Aseguramos que los campos puedan ser NULL si vienen vacíos
$id_modulo    = (isset($_POST['id_modulo'])    && $_POST['id_modulo']    !== '') ? $_POST['id_modulo']    : null;
$tipo         = (isset($_POST['tipo'])         && $_POST['tipo']         !== '') ? $_POST['tipo']         : null;
$valor_actual = isset($_POST['valor_actual']) ? $_POST['valor_actual'] : null;
$usuario_id   = (isset($_POST['usuario_id'])   && $_POST['usuario_id']   !== '') ? $_POST['usuario_id']   : null;
$agencia_id   = (isset($_POST['agencia_id'])   && $_POST['agencia_id']   !== '') ? $_POST['agencia_id']   : null;

switch ($accion) {
    case 'guardar':
        // Validación de duplicado exacto considerando valores NULL
        $sql    = "SELECT COUNT(*) FROM tb_configuraciones_documentos WHERE deleted_at IS NULL";
        $types  = "";
        $params = [];

        if ($id_modulo !== null) {
            $sql   .= " AND id_modulo = ?";
            $types .= "i";
            $params[] = $id_modulo;
        } else {
            $sql .= " AND id_modulo IS NULL";
        }

        if ($tipo !== null) {
            $sql   .= " AND tipo = ?";
            $types .= "s";
            $params[] = $tipo;
        } else {
            $sql .= " AND tipo IS NULL";
        }

        if ($usuario_id !== null) {
            $sql   .= " AND usuario_id = ?";
            $types .= "i";
            $params[] = $usuario_id;
        } else {
            $sql .= " AND usuario_id IS NULL";
        }

        if ($agencia_id !== null) {
            $sql   .= " AND agencia_id = ?";
            $types .= "i";
            $params[] = $agencia_id;
        } else {
            $sql .= " AND agencia_id IS NULL";
        }

        if ($configId) {
            $sql   .= " AND id != ?";
            $types .= "i";
            $params[] = $configId;
        }

        $stmt = $conexion->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $stmt->bind_result($existe);
        $stmt->fetch();
        $stmt->close();

        if ($existe > 0) {
            $response['msg'] = "Ya existe una configuración con estos parámetros";
            break;
        }

        if ($configId) {
            // UPDATE
            $stmt = $conexion->prepare("UPDATE tb_configuraciones_documentos 
                SET id_modulo = ?, tipo = ?, valor_actual = ?, usuario_id = ?, agencia_id = ?, updated_by = ? 
                WHERE id = ?");
            $stmt->bind_param("sssiiii", $id_modulo, $tipo, $valor_actual, $usuario_id, $agencia_id, $userId, $configId);
        } else {
            // INSERT
            $stmt = $conexion->prepare("INSERT INTO tb_configuraciones_documentos 
                (id_modulo, tipo, valor_actual, usuario_id, agencia_id, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssiii", $id_modulo, $tipo, $valor_actual, $usuario_id, $agencia_id, $userId);
        }

        if ($stmt->execute()) {
            $response['status'] = 1;
            $response['msg'] = $configId ? "Configuración actualizada correctamente" : "Configuración guardada correctamente";
        } else {
            $response['msg'] = "Error al guardar: " . $stmt->error;
        }
        $stmt->close();
        break;

    case 'eliminar':
        if ($configId) {
            $stmt = $conexion->prepare(
                "UPDATE tb_configuraciones_documentos 
                 SET deleted_by = ?, deleted_at = ? 
                 WHERE id = ?"
            );
        
            $stmt->bind_param("isi", $userId, $hoy2, $configId);
            if ($stmt->execute()) {
                $response['status'] = 1;
                $response['msg']    = "Configuración eliminada correctamente";
            } else {
                $response['msg'] = "Error al eliminar: " . $stmt->error;
            }
            $stmt->close();
        }
        break;

    default:
        $response['msg'] = "Acción no reconocida";
        break;
}

$conexion->close();

if ($ajax) {
    echo json_encode($response);
    exit;
}

$_SESSION['msg'] = $response['msg'];
header('Location: ../../views/admin/admin_superadmin.php');
exit;
