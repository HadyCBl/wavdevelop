<?php
function calculo($data, $cuenta, $nivel)
{
    return (array_filter($data, function ($var) use ($cuenta, $nivel) {
        return (substr($var['ccodcta'], 0, $nivel)  == $cuenta);
    }));
}
function calculo2($data, $cuenta)
{
    return (array_filter($data, function ($var) use ($cuenta) {
        return ($var['ccodcta']  == $cuenta);
    }));
}
function calculo3($data, $cuenta, $nivel, $column)
{
    return array_sum(array_column(array_filter($data, function ($var) use ($cuenta, $nivel) {
        return (substr($var['ccodcta'], 0, $nivel)  == $cuenta);
    }), $column));
}
function calculo4($data, $cuenta, $column)
{
    $index = array_search($cuenta, array_column($data, 'id_ctb_nomenclatura'));
    return ($index !== false) ? ($data[$index][$column]) : 0;
}
function meses($date)
{
    $fecha = strtotime($date);
    $mes = date("m", $fecha);

    $meses = [
        [1, "Enero"],
        [2, "Febrero"],
        [3, "Marzo"],
        [4, "Abril"],
        [5, "Mayo"],
        [6, "Junio"],
        [7, "Julio"],
        [8, "Agosto"],
        [9, "Septiembre"],
        [10, "Octubre"],
        [11, "Noviembre"],
        [12, "Diciembre"],
    ];
    $resultado = array_filter($meses, function ($var) use ($mes) {
        return ($var[0] < $mes);
    });
    return $resultado;
    // array("Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre");
}
function executequery($query, $params, $typparams, $conexion)
{
    $stmt = $conexion->prepare($query);
    $aux = mysqli_error($conexion);
    if ($aux) {
        return ['ERROR: ' . $aux, false];
    }
    $types = '';
    $bindParams = [];
    $bindParams[] = &$types;
    $i = 0;
    foreach ($params as &$param) {
        // $types .= 's';
        $types .= $typparams[$i];
        $bindParams[] = &$param;
        $i++;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    if (!$stmt->execute()) {
        return ["Error en la ejecución de la consulta: " . $stmt->error, false];
    }
    $data = [];
    $resultado = $stmt->get_result();
    $i = 0;
    while ($fila = $resultado->fetch_assoc()) {
        $data[$i] = $fila;
        $i++;
    }
    $stmt->close();
    return [$data, true];
}
function buscarDatoPorId($array, $id, $keybuscado, $keyretorno = false)
{
    foreach ($array as $key => $dato) {
        if ($dato[$keybuscado] === $id) {
            return ($keyretorno == false) ? $key : $dato[$keyretorno];
        }
    }
    // Retornar un valor predeterminado si no se encuentra el dato
    return false;
}

function filtroXIdCuenta($data, $idCuenta)
{
    return (array_filter($data, function ($var) use ($idCuenta) {
        return ($var['id_ctb_nomenclatura']  == $idCuenta);
    }));
}