<?php
session_start();
require_once '../../includes/BD_con/db_con.php';
header('Content-Type: application/json');

// 1) Validar parámetros
if (!isset($_POST['cuenta'], $_POST['tasa'], $_POST['usuario'])) {
    echo json_encode(['success'=>false,'message'=>'Parámetros incompletos']);
    exit;
}

$ccodaho = $_POST['cuenta'];
$tasa    = floatval($_POST['tasa']);
$usuario = $_POST['usuario'];

try {
    // 2) Verificar existencia y tasa actual
    $stmtCheck = mysqli_prepare($conexion, "SELECT tasa FROM ahomcta WHERE ccodaho = ?");
    mysqli_stmt_bind_param($stmtCheck, "s", $ccodaho);
    mysqli_stmt_execute($stmtCheck);
    $res = mysqli_stmt_get_result($stmtCheck);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmtCheck);

    if (!$row) {
        throw new Exception("Cuenta $ccodaho no existe.");
    }
    if (abs($row['tasa'] - $tasa) < 0.0001) {
        echo json_encode(['success'=>true,'message'=>"Tasa ya es {$row['tasa']}%."]);
        exit;
    }

    // 3) Actualizar tasa, usuario y fecha_mod
    $stmtUpd = mysqli_prepare($conexion, "
        UPDATE ahomcta 
        SET tasa = ?, codigo_usu = ?, fecha_mod = NOW()
        WHERE ccodaho = ?
    ");
    mysqli_stmt_bind_param($stmtUpd, "dss", $tasa, $usuario, $ccodaho);

    if (!mysqli_stmt_execute($stmtUpd)) {
        throw new Exception("Error al actualizar: ".mysqli_stmt_error($stmtUpd));
    }
    if (mysqli_stmt_affected_rows($stmtUpd) > 0) {
        echo json_encode(['success'=>true,'message'=>"Tasa de $ccodaho actualizada a $tasa%."]);
    } else {
        throw new Exception("No se pudo actualizar cuenta $ccodaho.");
    }
    mysqli_stmt_close($stmtUpd);

} catch (Exception $e) {
    echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
} finally {
    mysqli_close($conexion);
}
