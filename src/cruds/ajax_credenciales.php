<?php
// Evitar cualquier output antes del JSON
ob_start();

try {
    require_once __DIR__ . '/../../includes/BD_con/db_con.php';
    require_once __DIR__ . '/../../includes/BD_con/conexion_bank.php';

    $connBank = isset($virtual) ? $virtual : null;
    $connMain = isset($conexion) ? $conexion : null;
    
    if ($connBank) {
        mysqli_set_charset($connBank, 'utf8');
    }
    if ($connMain) {
        mysqli_set_charset($connMain, 'utf8');
    }

    $data = [];
    
    if ($connBank && $connMain) {
        $result = $connBank->query("SELECT id, email AS codcli, name AS usuario, created_at FROM users ORDER BY created_at DESC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                // Buscar el nombre del cliente
                $stmt = $connMain->prepare("SELECT short_name FROM tb_cliente WHERE idcod_cliente = ?");
                if ($stmt) {
                    $stmt->bind_param('s', $row['codcli']);
                    if ($stmt->execute()) {
                        $stmt->bind_result($short);
                        if ($stmt->fetch()) {
                            $row['nombre'] = $short ?? 'Cliente no encontrado';
                        } else {
                            $row['nombre'] = 'Cliente no encontrado';
                        }
                    } else {
                        $row['nombre'] = 'Error al consultar';
                    }
                    $stmt->close();
                } else {
                    $row['nombre'] = 'Error en consulta';
                }
                
                // Agregar botones de acción
                $row['acciones'] = '<div class="btn-group" role="group">
                    <button type="button" class="btn btn-sm btn-warning" onclick="editarCredencial(' . $row['id'] . ', \'' . htmlspecialchars($row['usuario']) . '\', \'' . htmlspecialchars($row['codcli']) . '\')">
                        <i class="fa-solid fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger" onclick="eliminarCredencial(' . $row['id'] . ', \'' . htmlspecialchars($row['usuario']) . '\')">
                        <i class="fa-solid fa-trash"></i>
                    </button>
                </div>';
                
                $data[] = $row;
            }
            $result->free();
        }
    }

    // Limpiar cualquier output previo
    ob_clean();
    
    // Responder con los datos recopilados
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    // Limpiar cualquier output previo
    ob_clean();
    
    // Responder con error en formato JSON
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Error interno del servidor'], JSON_UNESCAPED_UNICODE);
}

ob_end_flush();
