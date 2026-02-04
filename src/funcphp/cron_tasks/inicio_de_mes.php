<?php
//VERIFICAR SI TIENE EL PARAMETRO EN LA URL
if (!isset($_GET['test']) || $_GET['test'] !== 'soygay') {
    echo "🖕";
    return false;
}

ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');
include_once '../../../includes/BD_con/db_con.php';
date_default_timezone_set('America/Guatemala');

/*  +++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++ INICIO APERTURA DEL MES POR AGENCIAS +++++++ 
    +++++++++++++++++++++++++++++++++++++++++++++++++++++ */
$mesactual = date("m");
$anioactual = date("Y");
//COMPROBAR SI NO HAY MESES APERTURADAS
$consulta = mysqli_query($conexion, "SELECT * FROM ctb_meses ORDER BY anio,num_mes desc");
$mesesctb[] = [];
$agencias[] = [];
$i = 0;
while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
    $mesesctb[$i] = $fila;
    $i++;
}
//LISTADO DE TODAS LAS AGENCIAS DE LA INSTITUCION
$consulta2 = mysqli_query($conexion, "SELECT * FROM tb_agencia");
$i = 0;
while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
    $agencias[$i] = $fila;
    $i++;
}


//APERTURANDO MESES SI NO HAN SIDO APERTURADAS
$conexion->autocommit(false);
try {
    $i = 0;
    $flag = true;
    while ($i < count($agencias)) {
        $idofi = $agencias[$i]['id_agencia'];
        $fila = 0;
        while ($fila < count($mesesctb)) {
            $smes = $mesesctb[$fila]["num_mes"];
            $sagencia = $mesesctb[$fila]["id_agencia"];
            $sanio = $mesesctb[$fila]["anio"];

            if ($idofi == $sagencia && $mesactual == $smes && $sanio == $anioactual) {
                $flag = false;
            }
            $fila++;
        }

        if ($flag) {
            $res = $conexion->prepare("INSERT INTO `ctb_meses`(`id_agencia`,`num_mes`,`anio`,`cierre`,`open_at`) VALUES (?,?,?,1,?)");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo 'ERROR: ' . $aux;
                // echo json_encode([$aux, '0']);
                $conexion->rollback();
                return;
            }
            if (!$res) {
                echo 'Error en la Apertura';
                // echo json_encode(['Error en la Apertura', '0']);
                $conexion->rollback();
                return;
            }
            $res->bind_param('iiis', $idofi, $mesactual, $anioactual, $hoy2);
            $res->execute();
            echo 'AGENCIA ' . $idofi . ', MES ' . $mesactual . '_' . $anioactual . ' -> APERTURADA :)';
        } else {
            echo 'AGENCIA ' . $idofi . ', MES ' . $mesactual . '_' . $anioactual . ' -> YA EXISTE EL MES CONTABLE :)';
        }
        $flag = true;
        $i++;
    }
    if ($conexion->commit()) {
        // echo json_encode(['APERTURADAS LISTAS', '1']);
        echo 'APERTURADAS LISTAS';
    } else {
        // echo json_encode(['Error al ingresar: ', '0']);
        echo 'Error al ingresar: ';
        $conexion->rollback();
    }
} catch (Exception $e) {
    $conexion->rollback();
    // echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
    echo 'Error al ingresar: ' . $e->getMessage();
}
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++ FIN APERTURA DE MES ENTRANTE POR CADA AGENCIA EXISTENTE +++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
mysqli_close($conexion);
