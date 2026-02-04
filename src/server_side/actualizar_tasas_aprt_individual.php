<?php
session_start();
require_once '../../includes/BD_con/db_con.php';
header('Content-Type: application/json');

// Parámetros requeridos: cuenta (string), tasa (decimal), usuario (string)
if (!isset($_POST['cuenta'], $_POST['tasa'], $_POST['usuario'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Parámetros incompletos'
    ]);
    exit;
}

$cuenta  = $_POST['cuenta'];
$tasa    = floatval($_POST['tasa']);
$usuario = $_POST['usuario'];

try {
    // 1) Verificar que existe
    $stmt = mysqli_prepare($conexion, "SELECT tasa FROM aprcta WHERE ccodaport = ?");
    mysqli_stmt_bind_param($stmt, "s", $cuenta);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$row) {
        throw new Exception("La cuenta $cuenta no existe.");
    }

    // 2) Si ya tiene esa tasa
    if (abs($row['tasa'] - $tasa) < 0.0001) {
        echo json_encode([
            'success' => true,
            'message' => "La cuenta $cuenta ya tiene tasa {$row['tasa']}%."
        ]);
        exit;
    }

    // 3) Actualizar
    $stmt = mysqli_prepare($conexion, "
        UPDATE aprcta
           SET tasa      = ?,
               codigo_usu= ?,
               fecha_mod = NOW()
         WHERE ccodaport = ?
    ");
    mysqli_stmt_bind_param($stmt, "dss", $tasa, $usuario, $cuenta);

    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception("Error al actualizar tasa: " . mysqli_stmt_error($stmt));
    }

    if (mysqli_stmt_affected_rows($stmt) > 0) {
        echo json_encode([
            'success' => true,
            'message' => "Tasa de la cuenta $cuenta actualizada a $tasa%."
        ]);
    } else {
        throw new Exception("No se pudo actualizar la cuenta $cuenta.");
    }

    mysqli_stmt_close($stmt);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} finally {
    mysqli_close($conexion);
}
