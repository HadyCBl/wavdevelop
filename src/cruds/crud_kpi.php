<?php

use Micro\Helpers\Log;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);


include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];
switch ($condi) {

    case 'add_eject':
        if (isset($_POST['usuario']) && isset($_POST['rol']) && isset($_POST['salario'])) {
            $usuario = $_POST['usuario'];
            $rol = $_POST['rol'];
            $salario = $_POST['salario'];

            $stmt = $conexion->prepare("SELECT COUNT(*) FROM tb_ejecutivos WHERE rol = ? AND id_usuario = ?");
            $stmt->bind_param("ii", $rol, $usuario);
            $stmt->execute();
            $stmt->bind_result($count);
            $stmt->fetch();
            $stmt->close();

            if ($count > 0) {
                $response = array("message" => "Ya existe un usuario con este rol.", "status" => 0);
            } else {
                //  inserción
                $stmt = $conexion->prepare("INSERT INTO tb_ejecutivos (rol, id_usuario, salario) VALUES (?, ?, ?)");
                $stmt->bind_param("iid", $rol, $usuario, $salario);
                if ($stmt->execute()) {
                    $response = array("message" => "¡El registro fue exitoso!", "status" => 1);
                } else {
                    $response = array("message" => "Hubo un problema al registrar.", "status" => 0);
                }
                $stmt->close();
            }
        } else {
            // faltAN parámetros
            $response = array("message" => "Faltan datos.", "status" => 0);
        }

        echo json_encode($response);
        mysqli_close($conexion);
        break;

    case 'table_ejecutivos':
        try {
            if ($conexion->connect_error) {
                throw new Exception("Error de conexión: " . $conexion->connect_error);
            }
            $query = "SELECT te.id, tu.nombre, tu.apellido, te.salario
                      FROM tb_ejecutivos te
                      INNER JOIN tb_usuario tu ON te.id_usuario = tu.id_usu";
            $result = $conexion->query($query);

            $data = array();
            while ($row = $result->fetch_assoc()) {
                $data[] = array(
                    "id" => $row["id"],
                    "nombre" => $row["nombre"],
                    "apellido" => $row["apellido"],
                    "salario" => number_format($row["salario"], 2, '.', ',')
                );
            }
            echo json_encode(array("data" => $data));
        } catch (Exception $e) {
            echo json_encode(array("error" => true, "message" => $e->getMessage()));
        } finally {
            $conexion->close();
        }
        break;
    // En crud_kpi.php
    case 'add_poa':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        // $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];
        $idEjecutivo = $archivo[0];
        $anio = $archivo[1];
        $datosProyeccion = $archivo[2];

        // list($csrftoken, $nombre, $descripcion, $minimo, $maximo) = $inputs;

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        // if (!($csrf->validateToken($inputs['csrf_token'], false))) {
        //     $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
        //     $opResult = array(
        //         'message' => $errorcsrf,
        //         'status' => 0,
        //         "reprint" => 1,
        //         "timer" => 3000
        //     );
        //     echo json_encode($opResult);
        //     return;
        // }
        $validar = validacionescampos([
            [$idEjecutivo, "0", 'No se proporciono ningun ejecutivo', 1],
            [$anio, "0", 'No se proporcionó el año de la proyeccion', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([
                'message' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();
            $poaExistente = $database->selectColumns('kpi_poa', ['year'], "year=? AND id_ejecutivo=?", [$anio, $idEjecutivo]);

            if (!empty($poaExistente)) {
                $showmensaje = true;
                throw new Exception("Ya existe un registro de proyección para el año $anio y el ejecutivo seleccionado.");
            }

            $userExistente = $database->selectColumns('tb_usuario', ['id_usu'], "id_usu=?", [$idEjecutivo]);
            if (empty($userExistente)) {
                $showmensaje = true;
                throw new Exception("El ejecutivo seleccionado no existe.");
            }

            $database->beginTransaction();

            $headerKpi = [
                'year' => $anio,
                'id_ejecutivo' => $idEjecutivo,
                'estado' => 1,
                'created_at' => $hoy2,
                'created_by' => $idusuario,
            ];
            $idHeaderPoa = $database->insert("kpi_poa_header", $headerKpi);

            // 0 mes,
            // 1 cartera_creditos,
            // 2 clientes,
            // 3 grupos,
            // 4 colocaciones,
            // 5 cancel
            if ($datosProyeccion) {
                foreach ($datosProyeccion as $key => $data) {
                    $kpi_poa = [
                        // 'year' => $anio,
                        'id_poa' => $idHeaderPoa,
                        'mes' => $data['mes'] + 1,
                        // 'id_ejecutivo' => $idEjecutivo,
                        'cartera_creditos' => $data['cartera_creditos'],
                        'clientes' => $data['clientes'],
                        'cancel' => $data['cancel'],
                        'grupos' => $data['grupos'],
                        'colocaciones' => $data['colocaciones'],
                    ];
                    $database->insert("kpi_poa", $kpi_poa);
                }
            }

            $database->commit();
            $status = 1;
            $mensaje = "Los datos se han guardado correctamente.";
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);

        break;
    case 'add_poaAnt':
        header('Content-Type: application/json'); // Se agrega para forzar respuesta JSON
        $usuario = isset($_POST['usuario']) ? $_POST['usuario'] : null;
        $anio = isset($_POST['anio']) ? $_POST['anio'] : null;
        $cartera = isset($_POST['cartera']) ? $_POST['cartera'] : null;
        $clientes = isset($_POST['clientes']) ? $_POST['clientes'] : null;
        $grupos = isset($_POST['grupos']) ? $_POST['grupos'] : null;
        $colocaciones = isset($_POST['colocaciones']) ? $_POST['colocaciones'] : null;
        $mes_cal = isset($_POST['mes_cal']) ? $_POST['mes_cal'] : null;

        // Validar campos numéricos y decimales
        if (!is_numeric($anio) || !is_numeric($cartera) || !is_numeric($clientes) || !is_numeric($grupos) || !is_numeric($colocaciones) || !is_numeric($mes_cal)) {
            $response = array("message" => "Todos los campos deben contener valores numéricos válidos, incluyendo decimales correctos.", "status" => 0);
            echo json_encode($response);
            exit();
        }

        // Verificación de duplicación 
        $query_check2 = "SELECT id_ejecutivo FROM kpi_poa WHERE id_ejecutivo = '$usuario' AND year = '$anio'";
        $result = mysqli_query($conexion, $query_check2);

        if (mysqli_num_rows($result) > 0) {
            $response = array("message" => "DATOS DUPLICADOS.", "status" => 0);
            echo json_encode($response);
            exit();
        }

        // Verificar existencia de registro con año 1 menor y mismo usuario
        $anio_anterior = $anio - 1;
        $query_check = "SELECT COUNT(*) AS existe FROM kpi_poa_aux WHERE id_ejecutivo = '$usuario' AND year = '$anio_anterior'";
        $result_check = mysqli_query($conexion, $query_check);

        if (!$result_check) {
            $response = array("message" => "Error del registros con el año anterior.", "status" => 0);
            echo json_encode($response);
            exit();
        }

        $data_check = mysqli_fetch_assoc($result_check);

        // Permitir la inserción incluso si no hay datos del año anterior
        $cartera_aux = 0;
        $clientes_aux = 0;
        $grupos_aux = 0;

        if ($data_check['existe'] > 0) {
            // Consultar valores acumulativos previos en kpi_poa_aux
            $query_aux_values = "SELECT SUM(cartera_creditos) AS cartera_aux, SUM(clientes) AS clientes_aux, SUM(grupos) AS grupos_aux 
                    FROM kpi_poa_aux WHERE id_ejecutivo = '$usuario' AND year = '$anio_anterior'";
            $result_aux_values = mysqli_query($conexion, $query_aux_values);

            if (!$result_aux_values) {
                $response = array("message" => "Error del servidor al consultar datos acumulativos.", "status" => 0);
                echo json_encode($response);
                exit();
            }

            $aux_values = mysqli_fetch_assoc($result_aux_values);

            $cartera_aux = $aux_values['cartera_aux'] ?? 0;
            $clientes_aux = $aux_values['clientes_aux'] ?? 0;
            $grupos_aux = $aux_values['grupos_aux'] ?? 0;
        }

        // Cálculos mensuales
        $cartera_mensual = $cartera / $mes_cal;
        $clientes_mensual = $clientes / $mes_cal;
        $grupos_mensual = $grupos / $mes_cal;

        // Inicializar acumulativos
        $cartera_acumulada = $cartera_aux;
        $clientes_acumulados = $clientes_aux;
        $grupos_acumulados = $grupos_aux;

        // Insertar registros por mes
        for ($mes = 1; $mes <= 12; $mes++) {
            $cartera_acumulada += $cartera_mensual;
            $clientes_acumulados += $clientes_mensual;
            $grupos_acumulados += $grupos_mensual;

            // Inserción en la tabla kpi_poa
            $query_insert = "INSERT INTO kpi_poa (year, mes, id_ejecutivo, cartera_creditos, clientes, grupos, colocaciones)
                VALUES ('$anio', '$mes', '$usuario', '$cartera_acumulada', '$clientes_acumulados', '$grupos_acumulados', '$colocaciones')";

            $result_insert = mysqli_query($conexion, $query_insert);

            if (!$result_insert) {
                $response = array("message" => "Error al insertar en la tabla kpi_poa para el mes $mes.", "status" => 0);
                echo json_encode($response);
                mysqli_close($conexion);
                exit();
            }

            if ($mes == 12) {
                $query_insert_aux = "INSERT INTO kpi_poa_aux (year, mes, id_ejecutivo, cartera_creditos, clientes, grupos, colocaciones)
                    VALUES ('$anio', '$mes', '$usuario', '$cartera_acumulada', '$clientes_acumulados', '$grupos_acumulados', '$colocaciones')";

                $result_insert_aux = mysqli_query($conexion, $query_insert_aux);

                if (!$result_insert_aux) {
                    $response = array("message" => "Error al insertar en la tabla kpi_poa_aux para el mes 12.", "status" => 0);
                    echo json_encode($response);
                    mysqli_close($conexion);
                    exit();
                }
            }
        }

        $response = array("message" => "Registros creados correctamente.", "status" => 1);
        echo json_encode($response);
        mysqli_close($conexion);
        break;


    case 'table_eje_poa':
        $response = [];

        $result = $conexion->query("SHOW TABLES LIKE 'kpi_poa'");
        if ($result->num_rows == 0) {
            // Enviar error si no existe la tabla
            $response = [
                "error" => true,
                "message" => "Datos no encontrados error[CR3473.]"
            ];
        } else {
            // Realizar la consulta
            $query = "SELECT 
                    tp.id,
                    tp.year,
                    tp.id_ejecutivo,
                    FORMAT(tp.cartera_creditos, 0) AS cartera_creditos_for, 
                    FORMAT(tp.clientes, 0) AS clientes_for, 
                    FORMAT(tp.grupos, 0) AS grupos_for, 
                    FORMAT(tp.colocaciones, 0) AS colocaciones_for, 
                    CONCAT(tu.nombre, ' ', tu.apellido) AS nombre_comp
                FROM kpi_poa tp
                INNER JOIN tb_usuario tu ON tp.id_ejecutivo = tu.id_usu;";

            $result = $conexion->query($query);

            if ($result->num_rows > 0) {
                while ($fila = $result->fetch_assoc()) {
                    $response[] = $fila;
                }
            } else {
                $response = [
                    "error" => true,
                    "message" => "No hay ejecutivos registrados."
                ];
            }
        }
        // Cerrar conexión y enviar datos JSON
        $conexion->close();
        echo json_encode(["data" => $response]);
        break;
        break;
    case 'table_ejecutivos':
        if ($conexion->connect_error) {
            die("Error de conexión: " . $conexion->connect_error);
        }
        $query = "SELECT te.id, tu.nombre, tu.apellido, te.salario
                          FROM tb_ejecutivos te
                          INNER JOIN tb_usuario tu ON te.id_usuario = tu.id_usu";
        $result = $conexion->query($query);

        $data = array();
        while ($row = $result->fetch_assoc()) {
            $data[] = array(
                "id" => $row["id"],
                "nombre" => $row["nombre"],
                "apellido" => $row["apellido"],
                "salario" => number_format($row["salario"], 2, '.', ',')
            );
        }
        $conexion->close();
        echo json_encode(array("data" => $data));
        break;

    case 'table_eje_poa':
        $response = [];

        $result = $conexion->query("SHOW TABLES LIKE 'kpi_poa'");
        if ($result->num_rows == 0) {
            // Enviar error si no existe la tabla
            $response = [
                "error" => true,
                "message" => "Datos no encontrados error[CR3473.]"
            ];
        } else {
            // Realizar la consulta
            $query = "SELECT 
                            tp.id,
                            tp.year,
                            tp.id_ejecutivo,
                            FORMAT(tp.cartera_creditos, 0) AS cartera_creditos_for, 
                            FORMAT(tp.clientes, 0) AS clientes_for, 
                            FORMAT(tp.grupos, 0) AS grupos_for, 
                            FORMAT(tp.colocaciones, 0) AS colocaciones_for, 
                            CONCAT(tu.nombre, ' ', tu.apellido) AS nombre_comp
                        FROM kpi_poa tp
                        INNER JOIN tb_usuario tu ON tp.id_ejecutivo = tu.id_usu;";

            $result = $conexion->query($query);

            if ($result->num_rows > 0) {
                while ($fila = $result->fetch_assoc()) {
                    $response[] = $fila;
                }
            } else {
                $response = [
                    "error" => true,
                    "message" => "No hay ejecutivos registrados."
                ];
            }
        }
        // Cerrar conexión y enviar datos JSON
        $conexion->close();
        echo json_encode(["data" => $response]);
        break;
    case 'consult_poa1':
        Log::info("datos", $_POST);
        if (isset($_POST['ejecutivo'], $_POST['anio'], $_POST['mes'])) {
            $ejecutivoid = $_POST['ejecutivo'];
            $anio = $_POST['anio'];
            $mes = $_POST['mes'];

            // Mapeo de meses
            $meses = [
                1 => 'Enero',
                2 => 'Febrero',
                3 => 'Marzo',
                4 => 'Abril',
                5 => 'Mayo',
                6 => 'Junio',
                7 => 'Julio',
                8 => 'Agosto',
                9 => 'Septiembre',
                10 => 'Octubre',
                11 => 'Noviembre',
                12 => 'Diciembre'
            ];

            // Validar que el mes esté dentro del rango válido
            if (!isset($meses[$mes])) {
                echo json_encode(['status' => 'error', 'message' => 'El mes proporcionado no es válido.']);
                exit;
            }

            // Consulta
            $stmt = $conexion->prepare("SELECT
                                                            tp.id,
                                                            tp.mes,
                                                            tp.year,
                                                            tp.id_ejecutivo,
                                                            tp.cartera_creditos,
                                                            tp.clientes,
                                                            tp.grupos,
                                                            tp.colocaciones,
                                                            CONCAT(tu.nombre, ' ', tu.apellido) AS nombre_comp
                                                        FROM kpi_poa tp
                                                        INNER JOIN tb_usuario tu ON tp.id_ejecutivo = tu.id_usu
                                                        WHERE tp.id_ejecutivo = ? AND tp.year = ? AND tp.mes = ?");
            $stmt->bind_param("iii", $ejecutivoid, $anio, $mes);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $datos = [];
                while ($fila = $result->fetch_assoc()) {
                    // Mapear el mes a su nombre correspondiente
                    $fila['mes'] = $meses[$fila['mes']];
                    $fila['anio'] = $anio ?? '0000';

                    // Formatear los valores numéricos
                    $fila['cartera_creditos_for'] = number_format($fila['cartera_creditos'], 0);
                    $fila['clientes_for'] = number_format($fila['clientes'], 0);
                    $fila['grupos_for'] = number_format($fila['grupos'], 0);
                    $fila['colocaciones_for'] = number_format($fila['colocaciones'], 0);

                    $datos[] = $fila;
                }
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se encontraron registros para el ejecutivo, año y mes especificados.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos en la solicitud.']);
        }
        break;
    case 'consult_poa_agen':
        // Validar que los parámetros necesarios estén presentes
        if (isset($_POST['ejecutivo'], $_POST['anio'], $_POST['mes'], $_POST['codofi'])) {
            $ejecutivoid = $_POST['ejecutivo'];
            $anio = $_POST['anio'];
            $mes = $_POST['mes'];
            $agencia = $_POST['codofi'];

            // Validar que el mes esté dentro del rango válido (1 a 12)
            if ($mes < 1 || $mes > 12) {
                echo json_encode(['status' => 'error', 'message' => 'El mes proporcionado no es válido.']);
                exit;
            }

            // Consulta
            $stmt = $conexion->prepare("SELECT
                                    tp.id,
                                    tp.mes,
                                    tp.year,
                                    tp.id_ejecutivo,
                                    tp.cartera_creditos,
                                    tp.clientes,
                                    tp.grupos,
                                    tp.colocaciones,
                                    'flag1' AS flag1,
                                    tu.id_agencia,
                                    CONCAT(tu.nombre, ' ', tu.apellido) AS nombre_comp
                                FROM kpi_poa tp
                                INNER JOIN tb_usuario tu ON tp.id_ejecutivo = tu.id_usu
                                WHERE tu.id_agencia = ? AND tp.year = ? AND tp.mes = ?");

            $stmt->bind_param("iii", $agencia, $anio, $mes);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $datos = [];
                while ($fila = $result->fetch_assoc()) {
                    //año no sea null????
                    $fila['anio'] = $anio;

                    // Formatear los valores numéricos
                    $fila['cartera_creditos_for'] = number_format($fila['cartera_creditos'], 0);
                    $fila['clientes_for'] = number_format($fila['clientes'], 0);
                    $fila['grupos_for'] = number_format($fila['grupos'], 0);
                    $fila['colocaciones_for'] = number_format($fila['colocaciones'], 0);
                    $fila['flag1'] = ($fila['flag1']);
                    $datos[] = $fila;
                }
                echo json_encode(['status' => 'success', 'data' => $datos]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'No se encontraron registros para la agencia, año y mes especificados.']);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos en la solicitud.']);
        }
        break;
    //fin del metodo
    //inicio del metodo para que lea y mansd los datos de la tabla kpi_poa


    case 'consult_cump_indi':
        if (isset($_POST['ejecutivo'], $_POST['anio'], $_POST['mes'])) {
            $ejecutivoid = $_POST['ejecutivo'];
            $anio = $_POST['anio'];
            $mes = $_POST['mes'];

            // Calcular el primer y último día del mes seleccionado
            $primer_dia_mes = "$anio-$mes-01";
            $ultimo_dia_mes = date("Y-m-t", strtotime($primer_dia_mes));

            $stmt = $conexion->prepare("SELECT 
                                            YEAR(cremi.DFecDsbls) AS anio,
                                            MONTH(cremi.DFecDsbls) AS mes,
                                            kp.mes AS mes_si, 
                                            kp.cartera_creditos AS cartera_kpi,
                                            kp.clientes AS clientes_kpi,
                                            kp.grupos AS grupos_kpi,
                                            kp.colocaciones AS colocaciones_kpi,
                                            (
                                                SELECT COUNT(*) 
                                                FROM tb_cliente tc_inner
                                                WHERE 
                                                    MONTH(tc_inner.fecha_alta) = MONTH(cremi.DFecDsbls) 
                                                    AND YEAR(tc_inner.fecha_alta) = YEAR(cremi.DFecDsbls)
                                                    AND tc_inner.created_by = ?
                                            ) AS clientes_real,
                                            (
                                                SELECT COUNT(*) 
                                                FROM tb_grupo tg_inner
                                                WHERE 
                                                    MONTH(tg_inner.created_at) = MONTH(cremi.DFecDsbls)
                                                    AND YEAR(tg_inner.created_at) = YEAR(cremi.DFecDsbls)
                                                    AND tg_inner.created_by = ?
                                            ) AS grupos_real,
                                            CONCAT(YEAR(cremi.DFecDsbls), '-', LPAD(MONTH(cremi.DFecDsbls), 2, '0')) AS periodo,
                                            cremi.CodAnal AS codanal,
                                            cremi.CODAgencia,
                                            SUM(cremi.NCapDes) AS total_desembolsado,
                                            SUM(IFNULL(ck.KP, 0)) AS total_pagado,
                                            SUM(cremi.NCapDes - IFNULL(ck.KP, 0)) AS saldo_actual,
                                            CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(?, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) AS atraso
                                        FROM 
                                            cremcre_meta cremi
                                        LEFT JOIN CREDKAR ck ON ck.CCODCTA = cremi.CCODCTA
                                        LEFT JOIN kpi_poa kp ON MONTH(cremi.DFecDsbls) = kp.mes AND YEAR(cremi.DFecDsbls) = kp.year AND cremi.CodAnal = kp.id_ejecutivo
                                        WHERE 
                                            cremi.CodAnal = ?
                                            AND cremi.DFecDsbls BETWEEN ? AND ?
                                        GROUP BY 
                                            YEAR(cremi.DFecDsbls),
                                            MONTH(cremi.DFecDsbls)
                                        ORDER BY 
                                            MONTH(cremi.DFecDsbls),
                                            cremi.CodAnal,
                                            YEAR(cremi.DFecDsbls);
                                    ");
            // Asignar parámetros a los placeholders
            $stmt->bind_param("iiisss", $ejecutivoid, $ejecutivoid, $ejecutivoid, $ejecutivoid, $primer_dia_mes, $ultimo_dia_mes);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $datos = [];
                    while ($fila = $result->fetch_assoc()) {
                        // Calcular cumplimiento de metas
                        $cumplimiento = ($fila['total_desembolsado'] / $fila['cartera_kpi']) * 100;
                        $tasa_recuperacion = ($fila['total_pagado'] / $fila['total_desembolsado']) * 100;
                        $cartera_en_riesgo = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;
                        $crecimiento = (($fila['total_desembolsado'] - $fila['saldo_actual']) / $fila['saldo_actual']) * 100;
                        $tasa_mora = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;

                        $datos[] = [
                            'anio' => $fila['anio'],
                            'mes' => $fila['mes'],
                            'periodo' => $fila['periodo'],
                            'codanal' => $fila['codanal'],
                            'codagencia' => $fila['CODAgencia'],
                            'total_desembolsado' => number_format($fila['total_desembolsado'], 2, '.', ','),
                            'total_pagado' => number_format($fila['total_pagado'], 2, '.', ','),
                            'saldo_actual' => number_format($fila['saldo_actual'], 2, '.', ','),
                            'colocaciones_kpi' => number_format($fila['colocaciones_kpi'], 2, '.', ','),
                            'cartera_kpi' => number_format($fila['cartera_kpi'], 2, '.', ','),
                            'clientes_kpi' => $fila['clientes_kpi'],
                            'grupos_kpi' => $fila['grupos_kpi'],
                            'clientes_real' => $fila['clientes_real'],
                            'grupos_real' => $fila['grupos_real'],
                            'cumplimiento' => number_format($cumplimiento, 2, '.', ','),
                            'tasa_recuperacion' => number_format($tasa_recuperacion, 2, '.', ','),
                            'cartera_en_riesgo' => number_format($cartera_en_riesgo, 2, '.', ','),
                            'crecimiento' => number_format($crecimiento, 2, '.', ','),
                            'tasa_mora' => number_format($tasa_mora, 2, '.', ',')
                        ];
                    }
                    echo json_encode(['status' => 'success', 'data' => $datos]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'No se encontraron registros.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar la consulta: ' . $stmt->error]);
            }

            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos.']);
        }
        break;

    case 'consult_cump_agen':
        if (isset($_POST['codofi'], $_POST['anio'])) {
            $codofi = $_POST['codofi'];
            $anio = $_POST['anio'];

            $stmt = $conexion->prepare("SELECT 
                                        YEAR(cremi.DFecDsbls) AS anio,
                                        MONTH(cremi.DFecDsbls) AS mes,
                                        kp.mes AS mes_si, 
                                        kp.cartera_creditos AS cartera_kpi,
                                        kp.clientes AS clientes_kpi,
                                        kp.grupos AS grupos_kpi,
                                        kp.colocaciones AS colocaciones_kpi,
                                        tc.created_by AS clientes_cli,
                                        (
                                            SELECT COUNT(*) 
                                            FROM tb_cliente tc_inner
                                            WHERE 
                                                MONTH(tc_inner.fecha_alta) = MONTH(cremi.DFecDsbls) 
                                                AND YEAR(tc_inner.fecha_alta) = YEAR(cremi.DFecDsbls)
                                                AND tc_inner.created_by = ?
                                        ) AS clientes_real,
                                        (
                                            SELECT COUNT(*) 
                                            FROM tb_grupo tg_inner
                                            WHERE 
                                                MONTH(tg_inner.created_at) = MONTH(cremi.DFecDsbls)
                                                AND YEAR(tg_inner.created_at) = YEAR(cremi.DFecDsbls)
                                                AND tg_inner.created_by = ?
                                        ) AS grupos_real,
                                        CONCAT(YEAR(cremi.DFecDsbls), '-', LPAD(MONTH(cremi.DFecDsbls), 2, '0')) AS periodo,
                                        cremi.CodAnal AS codanal,
                                        cremi.CODAgencia,
                                        SUM(cremi.NCapDes) AS total_desembolsado,
                                        SUM(IFNULL(ck.KP, 0)) AS total_pagado,
                                        SUM(cremi.NCapDes - IFNULL(ck.KP, 0)) AS saldo_actual
                                    FROM 
                                        cremcre_meta cremi
                                    LEFT JOIN CREDKAR ck ON ck.CCODCTA = cremi.CCODCTA
                                    LEFT JOIN kpi_poa kp ON MONTH(cremi.DFecDsbls) = kp.mes
                                    LEFT JOIN tb_cliente tc ON MONTH(cremi.DFecDsbls) = MONTH(tc.fecha_alta)
                                    LEFT JOIN tb_grupo tg ON MONTH(cremi.DFecDsbls) = MONTH(tg.created_at)
                                    WHERE 
                                        cremi.CodAnal = ?
                                        AND YEAR(cremi.DFecDsbls) = ?
                                    GROUP BY 
                                        YEAR(cremi.DFecDsbls),
                                        MONTH(cremi.DFecDsbls)
                                    ORDER BY 
                                        MONTH(cremi.DFecDsbls),
                                        cremi.CodAnal,
                                        YEAR(cremi.DFecDsbls);
                                    ");

            // Asignar parámetros a los placeholders
            $stmt->bind_param("iiii", $codofi, $codofi, $codofi, $anio);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $datos = [];
                    while ($fila = $result->fetch_assoc()) {
                        // Calcular cumplimiento de metas
                        $cumplimiento = ($fila['total_desembolsado'] / $fila['cartera_kpi']) * 100;
                        $tasa_recuperacion = ($fila['total_pagado'] / $fila['total_desembolsado']) * 100;
                        $cartera_en_riesgo = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;
                        $crecimiento = (($fila['total_desembolsado'] - $fila['saldo_actual']) / $fila['saldo_actual']) * 100;
                        $tasa_mora = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;

                        $datos[] = [
                            'anio' => $fila['anio'],
                            'mes' => $fila['mes'],
                            'periodo' => $fila['periodo'],
                            'codanal' => $fila['codanal'],
                            'codagencia' => $fila['CODAgencia'],
                            'total_desembolsado' => number_format($fila['total_desembolsado'], 2, '.', ','),
                            'total_pagado' => number_format($fila['total_pagado'], 2, '.', ','),
                            'saldo_actual' => number_format($fila['saldo_actual'], 2, '.', ','),
                            'colocaciones_kpi' => number_format($fila['colocaciones_kpi'], 2, '.', ','),
                            'cartera_kpi' => number_format($fila['cartera_kpi'], 2, '.', ','),
                            'clientes_kpi' => $fila['clientes_kpi'],
                            'grupos_kpi' => $fila['grupos_kpi'],
                            'clientes_real' => $fila['clientes_real'],
                            'grupos_real' => $fila['grupos_real'],
                            'cumplimiento' => number_format($cumplimiento, 2, '.', ','),
                            'tasa_recuperacion' => number_format($tasa_recuperacion, 2, '.', ','),
                            'cartera_en_riesgo' => number_format($cartera_en_riesgo, 2, '.', ','),
                            'crecimiento' => number_format($crecimiento, 2, '.', ','),
                            'tasa_mora' => number_format($tasa_mora, 2, '.', ',')
                        ];
                    }
                    echo json_encode(['status' => 'success', 'data' => $datos]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'No se encontraron registros.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar la consulta: ' . $stmt->error]);
            }

            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos.']);
        }
        break;

    // En crud_kpi.php
    case 'fetch_poa_details':
        $usuario = filter_var($_POST['usuario'] ?? null, FILTER_SANITIZE_NUMBER_INT);
        $anio = filter_var($_POST['anio'] ?? null, FILTER_SANITIZE_NUMBER_INT);
        $anio_ant = $anio - 1;

        if (!$usuario || !$anio) {
            echo json_encode(["status" => "error", "message" => "Faltan parámetros."]);
            exit;
        }

        // Consulta para el título (kpi_poa_aux)
        $queryR = "SELECT id, mes, cartera_creditos, clientes, grupos, colocaciones, cancel, year
                                               FROM kpi_poa_aux
                                               WHERE id_ejecutivo = ? AND year IN (?, ?)";
        $stmtR = $conexion->prepare($queryR);

        if (!$stmtR) {
            echo json_encode(["status" => "error", "message" => "Error en la consulta SQL."]);
            exit;
        }

        $stmtR->bind_param("iii", $usuario, $anio, $anio_ant);
        $stmtR->execute();
        $resultR = $stmtR->get_result();
        $titulos = $resultR->fetch_all(MYSQLI_ASSOC);

        // Consulta para los detalles (kpi_poa)
        $sql = "SELECT id, mes, cartera_creditos, clientes, grupos, colocaciones, cancel
                                            FROM kpi_poa
                                            WHERE id_ejecutivo = ? AND year = ?";
        $stmt = $conexion->prepare($sql);

        if (!$stmt) {
            echo json_encode(["status" => "error", "message" => "Error en la consulta SQL parametrizada."]);
            exit;
        }

        $stmt->bind_param("ii", $usuario, $anio);
        $stmt->execute();
        $result = $stmt->get_result();
        $detalles = $result->fetch_all(MYSQLI_ASSOC);

        if (!empty($titulos) || !empty($detalles)) {
            echo json_encode([
                "status" => "success",
                "titulos" => $titulos,
                "detalles" => $detalles
            ]);
        } else {
            echo json_encode(["status" => "error", "message" => "No se encontraron datos."]);
        }
        exit;

    case 'update_data_poa':
        if (isset($_POST['datos'])) {
            $datos = $_POST['datos'];
            $errores = [];

            foreach ($datos as $dato) {
                $id = $dato['id'];
                $cartera_creditos = $dato['cartera_creditos'];
                $clientes = $dato['clientes'];
                $grupos = $dato['grupos'];
                $colocaciones = $dato['colocaciones'];
                $cancel = $dato['cancel'];

                $query = "UPDATE kpi_poa SET cartera_creditos = ?, clientes = ?, grupos = ?, colocaciones = ?, cancel = ? WHERE id = ?";
                $stmt = $conexion->prepare($query);
                $stmt->bind_param("diiiii", $cartera_creditos, $clientes, $grupos, $colocaciones, $cancel, $id);

                if (!$stmt->execute()) {
                    $errores[] = "Error al actualizar el registro con ID $id.";
                }
                $stmt->close();
            }

            if (empty($errores)) {
                echo json_encode(['status' => 'success', 'message' => 'Datos actualizados correctamente.']);
            } else {
                echo json_encode(['status' => 'error', 'message' => implode(", ", $errores)]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos.']);
        }
        break;
    //intento de traer datos de la tabla kpi_poa
    //para el resumen de kpi
    case 'consult_resumen':
        if (isset($_POST['ejecutivo'], $_POST['anio'], $_POST['mes'])) {
            $ejecutivoid = $_POST['ejecutivo'];
            $anio = $_POST['anio'];
            $mes = $_POST['mes'];

            // Consulta para obtener los datos de resumen por ejecutivo
            $stmt = $conexion->prepare("SELECT 
                    YEAR(cremi.DFecDsbls) AS anio,
                    MONTH(cremi.DFecDsbls) AS mes,
                    kp.mes AS mes_si, 
                    kp.cartera_creditos AS cartera_kpi,
                    kp.clientes AS clientes_kpi,
                    kp.grupos AS grupos_kpi,
                    kp.colocaciones AS colocaciones_kpi,
                    tc.created_by AS clientes_cli,
                    (
                        SELECT COUNT(*) 
                        FROM tb_cliente tc_inner
                        WHERE 
                            MONTH(tc_inner.fecha_alta) = MONTH(cremi.DFecDsbls) 
                            AND YEAR(tc_inner.fecha_alta) = YEAR(cremi.DFecDsbls)
                            AND tc_inner.created_by = ?
                    ) AS clientes_real,
                    (
                        SELECT COUNT(*) 
                        FROM tb_grupo tg_inner
                        WHERE 
                            MONTH(tg_inner.created_at) = MONTH(cremi.DFecDsbls)
                            AND YEAR(tg_inner.created_at) = YEAR(cremi.DFecDsbls)
                            AND tg_inner.created_by = ?
                    ) AS grupos_real,
                    CONCAT(YEAR(cremi.DFecDsbls), '-', LPAD(MONTH(cremi.DFecDsbls), 2, '0')) AS periodo,
                    cremi.CodAnal AS codanal,
                    cremi.CODAgencia,
                    SUM(cremi.NCapDes) AS total_desembolsado,
                    SUM(IFNULL(ck.KP, 0)) AS total_pagado,
                    SUM(cremi.NCapDes - IFNULL(ck.KP, 0)) AS saldo_actual
                FROM 
                    cremcre_meta cremi
                LEFT JOIN CREDKAR ck ON ck.CCODCTA = cremi.CCODCTA
                LEFT JOIN kpi_poa kp ON MONTH(cremi.DFecDsbls) = kp.mes
                LEFT JOIN tb_cliente tc ON MONTH(cremi.DFecDsbls) = MONTH(tc.fecha_alta)
                LEFT JOIN tb_grupo tg ON MONTH(cremi.DFecDsbls) = MONTH(tg.created_at)
                WHERE 
                    cremi.CodAnal = ?
                    AND YEAR(cremi.DFecDsbls) = ?
                GROUP BY 
                    YEAR(cremi.DFecDsbls),
                    MONTH(cremi.DFecDsbls)
                ORDER BY 
                    MONTH(cremi.DFecDsbls),
                    cremi.CodAnal,
                    YEAR(cremi.DFecDsbls);
                ");

            // Asignar parámetros a los placeholders
            $stmt->bind_param("iiii", $ejecutivoid, $ejecutivoid, $ejecutivoid, $anio);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $datos = [];
                    while ($fila = $result->fetch_assoc()) {
                        // Calcular cumplimiento de metas
                        $cumplimiento = ($fila['total_desembolsado'] / $fila['cartera_kpi']) * 100;
                        $tasa_recuperacion = ($fila['total_pagado'] / $fila['total_desembolsado']) * 100;
                        $cartera_en_riesgo = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;
                        $crecimiento = (($fila['total_desembolsado'] - $fila['saldo_actual']) / $fila['saldo_actual']) * 100;
                        $tasa_mora = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;

                        $datos[] = [
                            'anio' => $fila['anio'],
                            'mes' => $fila['mes'],
                            'periodo' => $fila['periodo'],
                            'codanal' => $fila['codanal'],
                            'codagencia' => $fila['CODAgencia'],
                            'total_desembolsado' => number_format($fila['total_desembolsado'], 2, '.', ','),
                            'total_pagado' => number_format($fila['total_pagado'], 2, '.', ','),
                            'saldo_actual' => number_format($fila['saldo_actual'], 2, '.', ','),
                            'colocaciones_kpi' => number_format($fila['colocaciones_kpi'], 2, '.', ','),
                            'cartera_kpi' => number_format($fila['cartera_kpi'], 2, '.', ','),
                            'clientes_kpi' => $fila['clientes_kpi'],
                            'grupos_kpi' => $fila['grupos_kpi'],
                            'clientes_real' => $fila['clientes_real'],
                            'grupos_real' => $fila['grupos_real'],
                            'cumplimiento' => number_format($cumplimiento, 2, '.', ','),
                            'tasa_recuperacion' => number_format($tasa_recuperacion, 2, '.', ','),
                            'cartera_en_riesgo' => number_format($cartera_en_riesgo, 2, '.', ','),
                            'crecimiento' => number_format($crecimiento, 2, '.', ','),
                            'tasa_mora' => number_format($tasa_mora, 2, '.', ',')
                        ];
                    }
                    echo json_encode(['status' => 'success', 'data' => $datos]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'No se encontraron registros.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar la consulta: ' . $stmt->error]);
            }
            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos.']);
        }

        break;

    case 'consult_resumen_agencia':
        if (isset($_POST['codofi'], $_POST['anio'], $_POST['mes'])) {
            $codofi = $_POST['codofi'];
            $anio = $_POST['anio'];
            $mes = $_POST['mes'];

            // Consulta para obtener los datos de resumen por agencia
            $stmt = $conexion->prepare("SELECT 
                    YEAR(cremi.DFecDsbls) AS anio,
                    MONTH(cremi.DFecDsbls) AS mes,
                    kp.mes AS mes_si, 
                    kp.cartera_creditos AS cartera_kpi,
                    kp.clientes AS clientes_kpi,
                    kp.grupos AS grupos_kpi,
                    kp.colocaciones AS colocaciones_kpi,
                    tc.created_by AS clientes_cli,
                    (
                        SELECT COUNT(*) 
                        FROM tb_cliente tc_inner
                        WHERE 
                            MONTH(tc_inner.fecha_alta) = MONTH(cremi.DFecDsbls) 
                            AND YEAR(tc_inner.fecha_alta) = YEAR(cremi.DFecDsbls)
                            AND tc_inner.codofi = ?
                    ) AS clientes_real,
                    (
                        SELECT COUNT(*) 
                        FROM tb_grupo tg_inner
                        WHERE 
                            MONTH(tg_inner.created_at) = MONTH(cremi.DFecDsbls)
                            AND YEAR(tg_inner.created_at) = YEAR(cremi.DFecDsbls)
                            AND tg_inner.codofi = ?
                    ) AS grupos_real,
                    CONCAT(YEAR(cremi.DFecDsbls), '-', LPAD(MONTH(cremi.DFecDsbls), 2, '0')) AS periodo,
                    cremi.CodAnal AS codanal,
                    cremi.CODAgencia,
                    SUM(cremi.NCapDes) AS total_desembolsado,
                    SUM(IFNULL(ck.KP, 0)) AS total_pagado,
                    SUM(cremi.NCapDes - IFNULL(ck.KP, 0)) AS saldo_actual
                FROM 
                    cremcre_meta cremi
                LEFT JOIN CREDKAR ck ON ck.CCODCTA = cremi.CCODCTA
                LEFT JOIN kpi_poa kp ON MONTH(cremi.DFecDsbls) = kp.mes
                LEFT JOIN tb_cliente tc ON MONTH(cremi.DFecDsbls) = MONTH(tc.fecha_alta)
                LEFT JOIN tb_grupo tg ON MONTH(cremi.DFecDsbls) = MONTH(tg.created_at)
                WHERE 
                    cremi.CODAgencia = ?
                    AND YEAR(cremi.DFecDsbls) = ?
                GROUP BY 
                    YEAR(cremi.DFecDsbls),
                    MONTH(cremi.DFecDsbls)
                ORDER BY 
                    MONTH(cremi.DFecDsbls),
                    cremi.CODAgencia,
                    YEAR(cremi.DFecDsbls);
                ");

            // Asignar parámetros a los placeholders
            $stmt->bind_param("iiii", $codofi, $codofi, $codofi, $anio);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result->num_rows > 0) {
                    $datos = [];
                    while ($fila = $result->fetch_assoc()) {
                        // Calcular cumplimiento de metas
                        $cumplimiento = ($fila['total_desembolsado'] / $fila['cartera_kpi']) * 100;
                        $tasa_recuperacion = ($fila['total_pagado'] / $fila['total_desembolsado']) * 100;
                        $cartera_en_riesgo = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;
                        $crecimiento = (($fila['total_desembolsado'] - $fila['saldo_actual']) / $fila['saldo_actual']) * 100;
                        $tasa_mora = ($fila['saldo_actual'] / $fila['total_desembolsado']) * 100;

                        $datos[] = [
                            'anio' => $fila['anio'],
                            'mes' => $fila['mes'],
                            'periodo' => $fila['periodo'],
                            'codanal' => $fila['codanal'],
                            'codagencia' => $fila['CODAgencia'],
                            'total_desembolsado' => number_format($fila['total_desembolsado'], 2, '.', ','),
                            'total_pagado' => number_format($fila['total_pagado'], 2, '.', ','),
                            'saldo_actual' => number_format($fila['saldo_actual'], 2, '.', ','),
                            'colocaciones_kpi' => number_format($fila['colocaciones_kpi'], 2, '.', ','),
                            'cartera_kpi' => number_format($fila['cartera_kpi'], 2, '.', ','),
                            'clientes_kpi' => $fila['clientes_kpi'],
                            'grupos_kpi' => $fila['grupos_kpi'],
                            'clientes_real' => $fila['clientes_real'],
                            'grupos_real' => $fila['grupos_real'],
                            'cumplimiento' => number_format($cumplimiento, 2, '.', ','),
                            'tasa_recuperacion' => number_format($tasa_recuperacion, 2, '.', ','),
                            'cartera_en_riesgo' => number_format($cartera_en_riesgo, 2, '.', ','),
                            'crecimiento' => number_format($crecimiento, 2, '.', ','),
                            'tasa_mora' => number_format($tasa_mora, 2, '.', ',')
                        ];
                    }
                    echo json_encode(['status' => 'success', 'data' => $datos]);
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'No se encontraron registros.']);
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Error al ejecutar la consulta: ' . $stmt->error]);
            }

            $stmt->close();
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Parámetros incompletos.']);
        }
        break;

    case 'consultaPoa':

        break;

    case 'ccategory':
        //'<?= $csrf->getTokenName()','nombre','descripcion','minimo','maximo'],[],[],'ccategory','0',['category']
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];

        // list($csrftoken, $nombre, $descripcion, $minimo, $maximo) = $inputs;

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        if (!($csrf->validateToken($inputs['csrf_token'], false))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                'message' => $errorcsrf,
                'status' => 0,
                "reprint" => 1,
                "timer" => 3000
            );
            echo json_encode($opResult);
            return;
        }
        $validar = validacionescampos([
            [$inputs['nombre'], "", 'El campo de nombre es obligatorio', 1],
            [$inputs['descripcion'], "", 'Debe digitar una descripcion', 1],
            [$inputs['minimo'], "", 'Digite un monto minimo para la categoria', 1],
            [$inputs['maximo'], "", 'Digite un monto maximo para la categoria', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([
                'message' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();
            $category = $database->selectColumns('kpi_categorys', ['nombre'], "nombre=? AND estado=1", [$inputs['nombre']]);

            if (!empty($category)) {
                $showmensaje = true;
                throw new Exception("Ya existe una categoría con el nombre de " . $inputs['nombre']);
            }
            $database->beginTransaction();
            $datos = array(
                "nombre" => $inputs['nombre'],
                "descripcion" => $inputs['descripcion'],
                "monto_minimo" => $inputs['minimo'],
                "monto_maximo" => $inputs['maximo'],
                "estado" => 1,
                "created_by" => $idusuario,
                "created_at" => $hoy2,
            );
            $database->insert("kpi_categorys", $datos);

            $database->commit();
            $status = 1;
            $mensaje = "Categoría creada exitosamente";
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;
    case 'ucategory':
        //'<?= $csrf->getTokenName()','nombre','descripcion','minimo','maximo'],[],[],'ccategory','0',[id]
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        $csrftoken = $inputs['csrf_token'];
        $nombre = $inputs['nombre'];
        $descripcion = $inputs['descripcion'];
        $minimo = $inputs['minimo'];
        $maximo = $inputs['maximo'];


        // list($csrftoken, $nombre, $descripcion, $minimo, $maximo) = $inputs;
        list($encryptedID) = $archivo;

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        if (!($csrf->validateToken($csrftoken, false))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                'message' => $errorcsrf,
                'status' => 0,
                "reprint" => 1,
                "timer" => 3000
            );
            echo json_encode($opResult);
            return;
        }
        $validar = validacionescampos([
            [$nombre, "", 'El campo de nombre es obligatorio', 1],
            [$descripcion, "", 'Debe digitar una descripcion', 1],
            [$minimo, "", 'Digite un monto minimo para la categoria', 1],
            [$maximo, "", 'Digite un monto maximo para la categoria', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([
                'message' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }
        $decryptedID = $secureID->decrypt($encryptedID);
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();

            $category = $database->selectColumns('kpi_categorys', ['nombre'], "id=?", [$decryptedID]);
            if (empty($category)) {
                $showmensaje = true;
                throw new Exception("No se logró encontrar la categoría a actualizar");
            }

            $category = $database->selectColumns('kpi_categorys', ['nombre'], "nombre=? AND id!=?", [$nombre, $decryptedID]);
            if (!empty($category)) {
                $showmensaje = true;
                throw new Exception("Ya existe una categoría con el nombre de $nombre");
            }

            $database->beginTransaction();
            $datos = array(
                "nombre" => $nombre,
                "descripcion" => $descripcion,
                "monto_minimo" => $minimo,
                "monto_maximo" => $maximo,
                "updated_by" => $idusuario,
                "updated_at" => $hoy2,
            );
            $database->update("kpi_categorys", $datos, "id=?", [$decryptedID]);

            $database->commit();
            $status = 1;
            $mensaje = "Categoría actualizada correctamente";
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;

    case 'dcategory':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        Log::info("Eliminando categoria de kpi", $_POST);
        $encryptedID = $_POST["archivo"][0];
        $decryptedID = $secureID->decrypt($encryptedID);
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();
            $category = $database->selectColumns('kpi_categorys', ['nombre'], "id=?", [$decryptedID]);
            if (empty($category)) {
                $showmensaje = true;
                throw new Exception("No se logró encontrar la categoría a actualizar");
            }

            $database->beginTransaction();
            $datos = array(
                "estado" => 0,
                "deleted_by" => $idusuario,
                "deleted_at" => $hoy2,
            );

            $database->update("kpi_categorys", $datos, "id=?", [$decryptedID]);

            $database->commit();
            $status = 1;
            $mensaje = "Categoría eliminada correctamente";
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            'message' => $mensaje,
            'status' => $status
        ]);
        break;
}
