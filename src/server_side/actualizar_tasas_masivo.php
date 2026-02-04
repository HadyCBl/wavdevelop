<?php
session_start();
require_once '../../includes/BD_con/db_con.php';
header('Content-Type: application/json');

// 1) Validar parámetros
if (!isset($_POST['cuentas'], $_POST['tasa'], $_POST['usuario'])) {
    echo json_encode(['success'=>false,'message'=>'Parámetros incompletos']);
    exit;
}

$cuentas = $_POST['cuentas'];
$tasa    = floatval($_POST['tasa']);
$usuario = $_POST['usuario'];

try {
    mysqli_begin_transaction($conexion);

    // Prepara: verificar y actualizar
    $stmtCheck  = mysqli_prepare($conexion, "SELECT tasa FROM ahomcta WHERE ccodaho = ?");
    $stmtUpdate = mysqli_prepare($conexion, "
        UPDATE ahomcta 
        SET tasa = ?, codigo_usu = ?, fecha_mod = NOW()
        WHERE ccodaho = ?
    ");

    $updated = 0;
    $errors  = [];

    foreach ($cuentas as $ccodaho) {
        // 2) Verificar existencia
        mysqli_stmt_bind_param($stmtCheck, "s", $ccodaho);
        mysqli_stmt_execute($stmtCheck);
        $res = mysqli_stmt_get_result($stmtCheck);
        $row = mysqli_fetch_assoc($res);

        if (!$row) {
            $errors[] = "Cuenta $ccodaho no existe";
            continue;
        }

        // 3) Si ya tiene esa tasa, saltar
        if (abs($row['tasa'] - $tasa) < 0.0001) {
            $errors[] = "Cuenta $ccodaho ya está en {$row['tasa']}%";
            continue;
        }

        // 4) Actualizar
        mysqli_stmt_bind_param($stmtUpdate, "dss", $tasa, $usuario, $ccodaho);
        if (!mysqli_stmt_execute($stmtUpdate)) {
            $errors[] = "Error actualizando $ccodaho: ".mysqli_stmt_error($stmtUpdate);
            continue;
        }
        if (mysqli_stmt_affected_rows($stmtUpdate) > 0) {
            $updated++;
        } else {
            $errors[] = "Cuenta $ccodaho no fue modificada";
        }
    }

    mysqli_stmt_close($stmtCheck);
    mysqli_stmt_close($stmtUpdate);

    if ($updated > 0) {
        mysqli_commit($conexion);
        $msg = "Se actualizó tasa en $updated cuenta(s).";
        if ($errors) {
            $msg .= "\nAdvertencias:\n".implode("\n",$errors);
        }
        echo json_encode(['success'=>true,'message'=>$msg,'updated'=>$updated,'errors'=>$errors]);
    } else {
        throw new Exception($errors
            ? "No se actualizó ninguna cuenta:\n".implode("\n",$errors)
            : "No hay cuentas para actualizar"
        );
    }

} catch (Exception $e) {
    mysqli_rollback($conexion);
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} finally {
    mysqli_close($conexion);
}
