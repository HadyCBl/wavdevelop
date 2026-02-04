<?php
session_start();
require_once '../../includes/BD_con/db_con.php';
header('Content-Type: application/json');

// Parámetros requeridos: cuentas (array), tasa (decimal), usuario (string)
if (!isset($_POST['cuentas'], $_POST['tasa'], $_POST['usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros incompletos'
    ]);
    exit;
}

$cuentas = $_POST['cuentas'];
$tasa    = floatval($_POST['tasa']);
$usuario = $_POST['usuario'];

try {
    mysqli_begin_transaction($conexion);
    $actualizadas = 0;
    $errores      = [];

    $check_sql = "SELECT tasa FROM aprcta WHERE ccodaport = ?";
    $upd_sql   = "
        UPDATE aprcta
           SET tasa      = ?,
               codigo_usu= ?,
               fecha_mod = NOW()
         WHERE ccodaport = ?
    ";

    foreach ($cuentas as $cuenta) {
        // 1) Verificar existencia
        $stmt = mysqli_prepare($conexion, $check_sql);
        mysqli_stmt_bind_param($stmt, "s", $cuenta);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$row) {
            $errores[] = "Cuenta $cuenta no encontrada";
            continue;
        }

        // 2) Actualizar tasa
        $stmt = mysqli_prepare($conexion, $upd_sql);
        mysqli_stmt_bind_param($stmt, "dss", $tasa, $usuario, $cuenta);
        if (!mysqli_stmt_execute($stmt)) {
            $errores[] = "Error al actualizar $cuenta: " . mysqli_stmt_error($stmt);
            mysqli_stmt_close($stmt);
            continue;
        }

        if (mysqli_stmt_affected_rows($stmt) > 0) {
            $actualizadas++;
        } else {
            $errores[] = "La cuenta $cuenta ya tenía esa tasa";
        }
        mysqli_stmt_close($stmt);
    }

    if ($actualizadas > 0) {
        mysqli_commit($conexion);
        $msg = "Se actualizaron $actualizadas cuenta(s) exitosamente.";
        if ($errores) {
            $msg .= " Advertencias: " . implode('; ', $errores);
        }
        echo json_encode([
            'success' => true,
            'message' => $msg,
            'updated' => $actualizadas,
            'errors'  => $errores
        ]);
    } else {
        throw new Exception(
            $errores
                ? "No se actualizó ninguna cuenta: " . implode('; ', $errores)
                : "No había cuentas para actualizar"
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
