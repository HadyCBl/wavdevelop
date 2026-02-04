<?php
session_start();
require_once '../../includes/BD_con/db_con.php';

header('Content-Type: application/json');

// Validar parámetros requeridos
if (!isset($_POST['cuentas']) || !isset($_POST['accion']) || !isset($_POST['usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros incompletos'
    ]);
    exit;
}

$cuentas = $_POST['cuentas'];
$accion = $_POST['accion'];
$usuario = $_POST['usuario'];
$nuevo_estado = ($accion === 'activar') ? 'A' : 'B';

try {
    mysqli_begin_transaction($conexion);
    $cuentas_actualizadas = 0;
    $errores = [];

    foreach ($cuentas as $cuenta) {
        // Verificar estado actual y saldo
        $check_query = "SELECT COALESCE(monobj, 0) as saldo, estado 
                       FROM ahomcta 
                       WHERE ccodaho = ?";
        
        $stmt = mysqli_prepare($conexion, $check_query);
        if (!$stmt) {
            throw new Exception("Error al preparar consulta de verificación: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt, "s", $cuenta);
        
        if (!mysqli_stmt_execute($stmt)) {
            mysqli_stmt_close($stmt);
            throw new Exception("Error al verificar cuenta $cuenta: " . mysqli_error($conexion));
        }
        
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        // Validar que la cuenta existe
        if (!$row) {
            $errores[] = "La cuenta $cuenta no existe";
            continue;
        }
        
        // Validaciones
        if ($row['saldo'] > 0) {
            $errores[] = "La cuenta $cuenta tiene saldo mayor a 0";
            continue;
        }

        if ($row['estado'] == $nuevo_estado) {
            $estado_texto = ($nuevo_estado == 'A') ? 'activa' : 'inactiva';
            $errores[] = "La cuenta $cuenta ya está $estado_texto";
            continue;
        }

        if (!in_array($row['estado'], ['A', 'B'])) {
            $errores[] = "La cuenta $cuenta tiene un estado inválido";
            continue;
        }

        // Actualizar estado - Query simplificado
        $update_query = "UPDATE ahomcta 
                        SET estado = ?
                        WHERE ccodaho = ? 
                        AND COALESCE(monobj, 0) = 0";
                 
        $stmt = mysqli_prepare($conexion, $update_query);
        if (!$stmt) {
            throw new Exception("Error al preparar actualización: " . mysqli_error($conexion));
        }
        
        mysqli_stmt_bind_param($stmt, "ss", $nuevo_estado, $cuenta);
        
        if (!mysqli_stmt_execute($stmt)) {
            $error = mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            $errores[] = "Error al actualizar cuenta $cuenta: $error";
            continue;
        }
        
        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $cuentas_actualizadas++;
        } else {
            $errores[] = "La cuenta $cuenta no pudo ser actualizada o ya está en el estado deseado";
        }
        
        mysqli_stmt_close($stmt);
    }

    // Procesar resultados
    if ($cuentas_actualizadas > 0) {
        mysqli_commit($conexion);
        $mensaje = sprintf(
            "Se %s %d cuenta(s) exitosamente.",
            ($accion === 'activar' ? 'activaron' : 'desactivaron'),
            $cuentas_actualizadas
        );

        if (count($errores) > 0) {
            $mensaje .= "\n\nAdvertencias:\n" . implode("\n", $errores);
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'updated' => $cuentas_actualizadas,
            'errors' => $errores
        ]);
    } else {
        throw new Exception(
            count($errores) > 0 
                ? "No se pudo actualizar ninguna cuenta:\n" . implode("\n", $errores)
                : "No se encontraron cuentas para actualizar"
        );
    }

} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    mysqli_close($conexion);
}