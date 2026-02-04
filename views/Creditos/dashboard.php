<?php
include __DIR__ . '/../../includes/Config/config.php';
session_start();
if (!isset($_SESSION['usu'])) {
    header('location: ' . BASE_URL);
    //session_unset();
    return;
}
/* SE AGREGA DE MANERA TEMPORAL */
include __DIR__ . '/../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
/* QUITAR CUANDO YA NO SE NECESITE */

$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
$idagencia = $_SESSION['id_agencia'];

include __DIR__ . '/../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');



require __DIR__ . '/../infoEnti/infoEnti.php';
$showmensaje = false;
try {
    $database->openConnection();
    $infoEnti = infoEntidad($idagencia, $database, $db_name_general);
    $estado = $infoEnti['estado'];
    $fecha_pago = $infoEnti['fecha_pago'];
    $status = 1;
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}


// Verificar la cookie de la última notificación
$show_notification = false;
if (!isset($_COOKIE['last_notificationtwo'])) {
    $show_notification = true;
    setcookie('last_notificationtwo', time(), time() + 86400, "/"); // 86400 segundos = 1 día
} else {
    $last_notificationtwo = $_COOKIE['last_notificationtwo'];
    if (time() - $last_notificationtwo > 86400) {
        $show_notification = true;
        setcookie('last_notificationtwo', time(), time() + 86400, "/");
    }
}
/**
 * 
 *SELECT  `CCODCTA`,  `CodCli`,  `CCODPRD`,  `CODAgencia`,  `CodAnal`,  `Cestado`,  
 *`DfecSol`,  `DFecAnal`,  `DFecApr`,  `DsmblsAproba`,  `DFecDsbls`,  `ActoEcono`, 
 *`CTipCon`,  `NMonCuo`,  `CtipCre`,  `NtipPerC`,  `peripagcap`,  `afectaInteres`, 
 *`Cdescre`,  `CAldea`,  `CodMuni`,  `NDiaGra`,  `Plazo`,  `NDesApr`,  `DFecVig`, 
 * `DFecVen`,  `fecincobrable`,  `NDesEje`,  `NCapPag`,  `AhoPrgPag`,  `NIntPag`,  
 *`NMorPag`,  `NCapDes`,  `CConDic`,  `NDiaAtr`,  `CCalif`,  `DUltPag`,  `CMarJud`, 
 * `CSecEco`,  `CCodGrupo`,  `NAhoPrg`,  `CCodAho`,  `Cpagare`,  `GarantiasCantidad`,  
 *`PlazoRefi`,  `DfecIngreRefina`,  `DfecVenciRefina`,  `ActaOp`,  `SoliCop`,  `CODFIA`,  
 *`id_pro_gas`,  `moduloafecta`,  `cntAho`,  `DfecPago`,  `MontoSol`,  `MonSug`,  `TipoEnti`, 
 * `CtipCuo`,  `cuotassolicita`,  `noPeriodo`,  `Dictamen`,  `NIntApro`,  `NCiclo`, LEFT(`crecimiento`, 256), 
 *LEFT(`recomendacion`, 256),  `fecha_operacion`,  `P_ahoCr`,  `TipDocDes`,  `id_rechazo_cred` FROM `
 *`cremcre_meta` ORDER BY `ActoEcono` DESC LIMIT 1000;

 */

//test de consultas para el dashboard

try {
    $database->openConnection();

    // Inicializar variables
    $creditos_activos_actual = 0;
    $creditos_activos_anterior = 0;
    $proximos_vencimientos = 0;
    $cartera_en_riesgo = 0;
    $data_creditos_recientes = [];
    $ganancias_mes_actual = 0;
    $ganancias_mes_anterior = 0;
    $ganancias_ano_actual = 0;
    $ganancias_ano_anterior = 0;
    $porcentaje_cambio = 0;
    $porcentaje_cambio_anual = 0;
    $creditos_grupales_activos = 0;
    $grupos_activos = 0;
    $date = date('Y-m-d');
    $date_lastyear = date('Y-12-31', strtotime($date . ' - 1 year'));

    // Consulta SQL para obtener los créditos activos del año actual hasta la fecha de corte
    $sql_actual = "
        SELECT 
            SUM(cremi.NCapDes)-SUM(sum_KP) AS creditos_activos_actual
        FROM 
            cremcre_meta cremi
        INNER JOIN 
            tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
        INNER JOIN 
            cre_productos prod ON prod.id = cremi.CCODPRD 
        INNER JOIN 
            ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
        INNER JOIN 
            tb_usuario usu ON usu.id_usu = cremi.CodAnal 
        LEFT JOIN (
            SELECT 
                ccodcta, SUM(KP) AS sum_KP
            FROM 
                CREDKAR
            WHERE 
                dfecpro <= '$date' 
                AND cestado != 'X' 
                AND ctippag = 'P'
            GROUP BY 
                ccodcta
        ) AS kar ON kar.ccodcta = cremi.CCODCTA
        WHERE 
            (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') 
            AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0  
            AND cremi.DFecDsbls <= '$date'
    ";

    // Consulta SQL para obtener los créditos activos del año pasado hasta el 31 de diciembre
    $sql_anterior = "
        SELECT 
            SUM(cremi.NCapDes) AS creditos_activos_anterior
        FROM 
            cremcre_meta cremi
        INNER JOIN 
            tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
        INNER JOIN 
            cre_productos prod ON prod.id = cremi.CCODPRD 
        INNER JOIN 
            ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
        INNER JOIN 
            tb_usuario usu ON usu.id_usu = cremi.CodAnal 
        LEFT JOIN (
            SELECT 
                ccodcta, SUM(KP) AS sum_KP
            FROM 
                CREDKAR
            WHERE 
                dfecpro <= '$date_lastyear' 
                AND cestado != 'X' 
                AND ctippag = 'P'
            GROUP BY 
                ccodcta
        ) AS kar ON kar.ccodcta = cremi.CCODCTA
        WHERE 
            (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') 
            AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0  
            AND cremi.DFecDsbls <= '$date_lastyear'
    ";

    // Consulta SQL para obtener los vencimientos en los próximos 7 días
    $sql_vencimientos = "
        SELECT 
            COUNT(*) AS proximos_vencimientos
        FROM 
            cremcre_meta cremi
        WHERE 
            cremi.Cestado = 'F' 
            AND cremi.DFecVen BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY);
    ";

    // Consulta SQL para obtener la cartera en riesgo
    $sql_cartera_riesgo = "
    SELECT 
        SUM(CASE 
            WHEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso('$date', cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) > 30 
            THEN GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
            ELSE 0 
        END) AS cartera_en_riesgo,
        SUM(CASE 
            WHEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso('$date', cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) = 0 
            THEN GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
            ELSE 0 
        END) AS al_dia,
        SUM(CASE 
            WHEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso('$date', cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) BETWEEN 1 AND 30 
            THEN GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
            ELSE 0 
        END) AS de_1_a_30_dias
    FROM 
        cremcre_meta cremi
    INNER JOIN 
        tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
    INNER JOIN 
        cre_productos prod ON prod.id = cremi.CCODPRD 
    INNER JOIN 
        ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
    INNER JOIN 
        tb_usuario usu ON usu.id_usu = cremi.CodAnal 
    LEFT JOIN (
        SELECT 
            ccodcta, SUM(KP) AS sum_KP
        FROM 
            CREDKAR
        WHERE 
            dfecpro <= '$date' 
            AND cestado != 'X' 
            AND ctippag = 'P'
        GROUP BY 
            ccodcta
    ) AS kar ON kar.ccodcta = cremi.CCODCTA
    WHERE 
        (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G')
        AND cremi.DFecDsbls <= '$date'
";
    // Consulta SQL para obtener los 4 créditos más recientes
    $sql_creditos_recientes = "
     SELECT 
         CONCAT(cli.primer_name, ' ', cli.primer_last) AS nombre_cliente,
         CASE 
             WHEN cremi.Cestado IN ('A', 'D') THEN cremi.Montosol
             WHEN cremi.Cestado IN ('E', 'F') THEN cremi.MonSug
         END AS monto,
         cremi.Cestado AS estado
     FROM 
         cremcre_meta cremi
     INNER JOIN 
         tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
     WHERE 
         cremi.Cestado IN ('A', 'D', 'E', 'F') -- Filtra solo los estados requeridos
     ORDER BY 
         cremi.DFecDsbls DESC
     LIMIT 4;
    ";

    // Consulta SQL para obtener las ganancias del mes actual
    $sql_mes_actual = "
    SELECT 
        SUM(ppg.nintere) AS ganancias_mes_actual
    FROM 
        Cre_ppg ppg
    INNER JOIN 
        cremcre_meta cremi ON ppg.ccodcta = cremi.CCODCTA
    WHERE 
        cremi.Cestado = 'F' 
        AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE()) 
        AND MONTH(cremi.DFecDsbls) = MONTH(CURDATE());
";

    // Consulta SQL para obtener las ganancias del mes anterior
    $sql_mes_anterior = "
    SELECT 
        SUM(ppg.nintere) AS ganancias_mes_anterior
    FROM 
        Cre_ppg ppg
    INNER JOIN 
        cremcre_meta cremi ON ppg.ccodcta = cremi.CCODCTA
    WHERE 
        cremi.Cestado = 'F' 
        AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE() - INTERVAL 1 MONTH) 
        AND MONTH(cremi.DFecDsbls) = MONTH(CURDATE() - INTERVAL 1 MONTH);
";
    $sql_ano_actual = "
    SELECT 
        SUM(GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0))) AS ganancias_ano_actual
    FROM 
        cremcre_meta cremi
    INNER JOIN 
        tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
    INNER JOIN 
        cre_productos prod ON prod.id = cremi.CCODPRD 
    INNER JOIN 
        ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
    INNER JOIN 
        tb_usuario usu ON usu.id_usu = cremi.CodAnal 
    LEFT JOIN (
        SELECT 
            ccodcta, SUM(KP) AS sum_KP
        FROM 
            CREDKAR
        WHERE 
            dfecpro <= '$date' 
            AND cestado != 'X' 
            AND ctippag = 'P'
        GROUP BY 
            ccodcta
    ) AS kar ON kar.ccodcta = cremi.CCODCTA
    WHERE 
        cremi.Cestado = 'F' 
        AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE())
";

    // Consulta SQL para obtener las ganancias del año anterior
    $sql_ano_anterior = "
    SELECT 
        SUM(GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0))) AS ganancias_ano_anterior
    FROM 
        cremcre_meta cremi
    INNER JOIN 
        tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
    INNER JOIN 
        cre_productos prod ON prod.id = cremi.CCODPRD 
    INNER JOIN 
        ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
    INNER JOIN 
        tb_usuario usu ON usu.id_usu = cremi.CodAnal 
    LEFT JOIN (
        SELECT 
            ccodcta, SUM(KP) AS sum_KP
        FROM 
            CREDKAR
        WHERE 
            dfecpro <= '$date_lastyear' 
            AND cestado != 'X' 
            AND ctippag = 'P'
        GROUP BY 
            ccodcta
    ) AS kar ON kar.ccodcta = cremi.CCODCTA
    WHERE 
        cremi.Cestado = 'F' 
        AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE() - INTERVAL 1 YEAR)
";

    // Consulta SQL para obtener la cantidad de créditos grupales activos
    $sql_creditos_grupales = "
        SELECT 
            COUNT(DISTINCT cremi.CCODCTA) AS creditos_grupales_activos,
            COUNT(DISTINCT cremi.CcodGrupo) AS grupos_activos
        FROM 
            cremcre_meta cremi
        WHERE 
            cremi.Cestado = 'F' 
            AND cremi.tipoEnti = 'GRUP';
    ";
    //esta solo  funciona para lo que son los desembolsos 
    $sql_grafica = "
    SELECT 
        DATE_FORMAT(cremi.DFecDsbls, '%Y-%m') AS mes,
        SUM(GREATEST(0, cremi.NCapDes)) AS monto_desembolsado
    FROM 
        cremcre_meta cremi
    INNER JOIN 
        tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
    INNER JOIN 
        cre_productos prod ON prod.id = cremi.CCODPRD 
    INNER JOIN 
        ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
    INNER JOIN 
        tb_usuario usu ON usu.id_usu = cremi.CodAnal 
    WHERE 
        (cremi.Cestado = 'F' OR cremi.Cestado = 'G')
        AND cremi.DFecDsbls >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY 
        mes
    ORDER BY 
        mes ASC
";

    $sql_grafica_recuperaciones = "
    SELECT 
        DATE_FORMAT(kar.dfecpro, '%Y-%m') AS mes,
        SUM(kar.KP) AS monto_recuperado
    FROM 
        CREDKAR kar
    WHERE 
        kar.ctippag = 'P'
        AND kar.cestado != 'X'
        AND kar.dfecpro >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    GROUP BY 
        mes
    ORDER BY 
        mes ASC
";

    // Ejecutar las consultas
    $result_actual = $database->executeQuery($sql_actual);
    $result_anterior = $database->executeQuery($sql_anterior);
    $result_vencimientos = $database->executeQuery($sql_vencimientos);
    $result_cartera_riesgo = $database->executeQuery($sql_cartera_riesgo);
    $result_creditos_recientes = $database->executeQuery($sql_creditos_recientes);
    $result_mes_actual = $database->executeQuery($sql_mes_actual);
    $result_mes_anterior = $database->executeQuery($sql_mes_anterior);
    $result_ano_actual = $database->executeQuery($sql_ano_actual);
    $result_ano_anterior = $database->executeQuery($sql_ano_anterior);
    $result_creditos_grupales = $database->executeQuery($sql_creditos_grupales);
    // Ejecutar la nueva consulta para datos de la gráfica
    $result_grafica = $database->executeQuery($sql_grafica);
    // Ejecutar la nueva consulta para datos de la gráfica de recuperaciones
    $result_grafica_recuperaciones = $database->executeQuery($sql_grafica_recuperaciones);

    // Manejar los resultados de las consultas
    if ($result_actual && $result_anterior && $result_vencimientos && $result_cartera_riesgo && $result_creditos_recientes && $result_mes_actual && $result_mes_anterior && $result_ano_actual && $result_ano_anterior && $result_creditos_grupales && $result_grafica && $result_grafica_recuperaciones) {
        $data_actual = $result_actual->fetch(PDO::FETCH_ASSOC);
        $data_anterior = $result_anterior->fetch(PDO::FETCH_ASSOC);
        $data_vencimientos = $result_vencimientos->fetch(PDO::FETCH_ASSOC);
        $data_cartera_riesgo = $result_cartera_riesgo->fetch(PDO::FETCH_ASSOC);
        $data_creditos_recientes = $result_creditos_recientes->fetchAll(PDO::FETCH_ASSOC);
        $data_mes_actual = $result_mes_actual->fetch(PDO::FETCH_ASSOC);
        $data_mes_anterior = $result_mes_anterior->fetch(PDO::FETCH_ASSOC);
        $data_ano_actual = $result_ano_actual->fetch(PDO::FETCH_ASSOC);
        $data_ano_anterior = $result_ano_anterior->fetch(PDO::FETCH_ASSOC);
        $data_creditos_grupales = $result_creditos_grupales->fetch(PDO::FETCH_ASSOC);

        $creditos_activos_actual = $data_actual['creditos_activos_actual'] ?? 0;
        $creditos_activos_anterior = $data_anterior['creditos_activos_anterior'] ?? 0;
        $proximos_vencimientos = $data_vencimientos['proximos_vencimientos'] ?? 0;
        $cartera_en_riesgo = $data_cartera_riesgo['cartera_en_riesgo'] ?? 0;
        $ganancias_mes_actual = $data_mes_actual['ganancias_mes_actual'] ?? 0;
        $ganancias_mes_anterior = $data_mes_anterior['ganancias_mes_anterior'] ?? 0;
        $ganancias_ano_actual = $data_ano_actual['ganancias_ano_actual'] ?? 0;
        $ganancias_ano_anterior = $data_ano_anterior['ganancias_ano_anterior'] ?? 0;
        $creditos_grupales_activos = $data_creditos_grupales['creditos_grupales_activos'] ?? 0;
        $grupos_activos = $data_creditos_grupales['grupos_activos'] ?? 0;

        // Calcular el porcentaje de cambio mensual
        if ($creditos_activos_anterior > 0) {
            $porcentaje_cambio = (($creditos_activos_actual - $creditos_activos_anterior) / $creditos_activos_anterior) * 100;
        } else {
            $porcentaje_cambio = 100; // Si no hay datos del mes anterior, asumimos un 100% de incremento
        }

        // Calcular el porcentaje de cambio anual
        if ($ganancias_ano_anterior > 0) {
            $porcentaje_cambio_anual = (($ganancias_ano_actual - $ganancias_ano_anterior) / $ganancias_ano_anterior) * 100;
        } else {
            $porcentaje_cambio_anual = 100; // Si no hay datos del año anterior, asumimos un 100% de incremento
        }
        $data_grafica = $result_grafica->fetchAll(PDO::FETCH_ASSOC);
        $data_grafica_recuperaciones = $result_grafica_recuperaciones->fetchAll(PDO::FETCH_ASSOC);

        // Desembolsos
        $labels = [];
        $valDesembolsado = [];

        foreach ($data_grafica as $row) {
            $labels[] = $row['mes'];
            $valDesembolsado[] = $row['monto_desembolsado'];
        }

        // Recuperaciones
        $labels_recuperaciones = [];
        $valRecuperado = [];

        foreach ($data_grafica_recuperaciones as $row) {
            $labels_recuperaciones[] = $row['mes'];
            $valRecuperado[] = $row['monto_recuperado'];
        }

        // Convertir en JSON
        $labels_json = json_encode($labels);
        $valDesembolsado_json = json_encode($valDesembolsado);
        $labels_recuperaciones_json = json_encode($labels_recuperaciones);
        $valRecuperado_json = json_encode($valRecuperado);
    } else {
        throw new Exception("Error al ejecutar las consultas.");
    }
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    $status = 0;
} finally {
    $database->closeConnection();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modulo Creditos</title>
    <link rel="shortcut icon" type="image/x-icon" href="../../includes/img/favmicro.ico">
    <link rel="stylesheet" href="../../includes/css/style.css">
    <link rel="stylesheet" href="../../includes/css/styleCard.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <?php require_once __DIR__ . '/../../includes/incl.php'; ?>

</head>

<body class="<?= ($_SESSION['background'] == '1') ? 'dark' : ''; ?>">
    <?php
    require __DIR__ . '/../../src/menu/cre_menu.php';
    ?>
    <section class="home">
        <div class="container" style="max-width: none !important;">
            <div class="row">
                <div class="col d-flex justify-content-start">
                    <div class="text">MODULO CREDITOS</div>
                </div>
                <div class="col d-flex justify-content-end">
                    <div class="text"><?= $infoEnti['nomAge'] ?></div>
                </div>
            </div>
            <?php
            if ($show_notification) {
                $hoy = strtotime(date("Y-m-d"));
                // Calcula las fechas
                $fecha_pago_menos = date('Y-m-d', strtotime($fecha_pago . ' - 5 days'));
                $fecha_pago_mas = date('Y-m-d', strtotime($fecha_pago . ' + 5 days'));
                $fecha_pago_un_dia_antes = date('Y-m-d', strtotime($fecha_pago . ' - 1 day'));
                $fecha_pago_3 = date('Y-m-d', strtotime($fecha_pago . ' + 3 days'));
                $ultimo_dia_pago = date('Y-m-d', strtotime($fecha_pago_3 . ' + 1 day'));
                $fecha_pago_mas5 = date('Y-m-d', strtotime($fecha_pago . ' + 4 days'));
                // Verifica si hoy está dentro del rango  
                $fechanotify_mas5 = strtotime($fecha_pago_mas5);
                $fechanotify_menos = strtotime($fecha_pago_menos);
                $fechanotify_un_dia_antes = strtotime($fecha_pago_un_dia_antes);
                $fecha_pago_3_days = strtotime($fecha_pago_3);
                $case = '';
                // Determina el caso basado en las fechas
                // }
                if ($fechanotify_menos <= $hoy && $hoy <= strtotime($fecha_pago)) {
                    $case = 'dentro_rango_menos';
                }
                if ($hoy >= strtotime($fecha_pago . ' + 3 days') && $hoy <= strtotime($fecha_pago . ' + 4 days')) {
                    $case = 'dentro_rango_mas';
                }
                switch ($case) {
                    case 'dentro_rango_menos':
            ?>
                        <center>
                            <div class="container">
                                <div class="cardalert">
                                    <div class="header">
                                        <div class="image_advert">
                                            <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" fill="none">
                                                <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke-linejoin="round" stroke-linecap="round"></path>
                                            </svg>
                                        </div>
                                        <div class="content">
                                            <span class="title">AVISO</span>
                                            <p class="message">Este es un recordatorio cordial de que tu pago está programado para
                                                efectuarse el día
                                                <?php echo date('d F Y', strtotime($fecha_pago)); ?></p>
                                        </div>
                                        <div class="actions">
                                            <button class="desactivate btn btn-success" type="button" data-toggle="modal" data-target="#exampleModalCenter">
                                                <i class="fas fa-exclamation-triangle"></i> Ver detalles
                                            </button>
                                            <!-- Modal -->
                                            <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                <div class="modal-dialog modal-dialog-centered" role="document">
                                                    <div class="modal-content">
                                                        <div class="modal-header">
                                                            <h5 class="modal-title" id="exampleModalLongTitle">Advertencia de pago
                                                            </h5>
                                                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                <span aria-hidden="true">&times;</span>
                                                            </button>
                                                        </div>
                                                        <div class="modal-body">
                                                            "Si necesitas asistencia adicional o tienes alguna pregunta, no dudes en
                                                            contactarnos.
                                                            Saludos cordiales,
                                                            [SOTECPRO]"
                                                        </div>
                                                        <div class="modal-footer">
                                                            <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </center>
                        <?php
                        break;
                    case 'dentro_rango_mas':
                        if ($hoy == strtotime($fecha_pago_mas5)) {
                        ?>
                            <center>
                                <div class="container">
                                    <div class="cardalert">
                                        <div class="header">
                                            <div class="image">
                                                <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke-linejoin="round" stroke-linecap="round"></path>
                                                </svg>
                                            </div>
                                            <div class="content">
                                                <span class="title"> Ultimo aviso
                                                </span>
                                                <p class="message">Mañana es el último día para renovar tu suscripción. Si no lo haces,
                                                    tu cuenta se bloqueará automáticamente. </p>
                                            </div>
                                            <div class="actions">
                                                <button class="desactivate btn btn-danger" type="button" data-toggle="modal" data-target="#exampleModalCenter">
                                                    <i class="fas fa-exclamation-triangle"></i> Ver detalles
                                                </button>
                                                <!-- Modal -->
                                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">Alerta por falta de
                                                                    pago</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                "Mañana vence tu suscripción. Si no la renuevas, tu cuenta se bloqueará.
                                                                Recuerda que la restauración del sistema tomará aproximadamente 1 hora ,
                                                                (dias habiles).
                                                                Renueva ahora para evitar interrupciones en el servicio.
                                                                Para cualquier pregunta o ayuda, estamos aquí para ti.
                                                                [Sotecpro]"
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </center>
                        <?php
                        } else {
                        ?>
                            <center>
                                <div class="container">
                                    <div class="cardalert">
                                        <div class="header">
                                            <div class="image">
                                                <svg aria-hidden="true" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" fill="none">
                                                    <path d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" stroke-linejoin="round" stroke-linecap="round"></path>
                                                </svg>
                                            </div>
                                            <div class="content">
                                                <span class="title"> Estimado Usuario
                                                </span>
                                                <p class="message">parece que tienes una Factura pendiente, con fecha de pago del
                                                    <?php echo date('d F Y', strtotime($fecha_pago)); ?> .El sistema se bloqueará el
                                                    <?php echo date('d F Y', strtotime($fecha_pago_mas)); ?>. Favor de enviar la boleta
                                                    de pago</p>
                                            </div>
                                            <div class="actions">
                                                <button class="desactivate btn btn-danger" type="button" data-toggle="modal" data-target="#exampleModalCenter">
                                                    <i class="fas fa-coins"></i>
                                                </button>
                                                <!-- Modal -->
                                                <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog" aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
                                                    <div class="modal-dialog modal-dialog-centered" role="document">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title" id="exampleModalLongTitle">Alerta por falta de
                                                                    pago</h5>
                                                                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                                                    <span aria-hidden="true">&times;</span>
                                                                </button>
                                                            </div>
                                                            <div class="modal-body">
                                                                "Si considera que esta notificación es incorrecta, le recomendamos que
                                                                se comunique con nuestro equipo de soporte técnico. Alternativamente, le
                                                                instamos a tomar las medidas necesarias, ya que para la fecha
                                                                <?php echo date('d F Y', strtotime($fecha_pago_mas)); ?>, el sistema se
                                                                encontrará bloqueado."
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-warning" data-dismiss="modal">Close</button>

                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </center>
            <?php

                        }


                        break;

                    default:
                        // NO PASA NADA 
                        break;
                }
            }
            // }
            ?>
            <div id="cuadro">
                <div class="d-flex flex-column h-100">
                    <div class="flex-grow-1">
                        <div class="row align-items-center" style="max-width: none !important; height: calc(75vh) !important;">
                            <div class="row d-flex justify-content-center">
                                <div class="col-8">
                                    <div class="card">
                                        <div class="card-header text-center"> Bienvenido al módulo de creditos. </div>
                                        <div class="card-body d-flex justify-content-center">
                                            <div class="container mt-4">
                                                <!-- Header -->
                                                <div class="row mb-4">
                                                    <div class="col-md-3">
                                                        <div class="card text-center p-3">
                                                            <h5>Créditos Activos</h5>
                                                            <h3>Q<?= number_format($creditos_activos_actual, 2, '.', ',') ?></h3>
                                                            <p class="<?= $porcentaje_cambio >= 0 ? 'text-success' : 'text-danger' ?>">
                                                                <?= $porcentaje_cambio >= 0 ? '+' : '' ?><?= number_format($porcentaje_cambio, 2, '.', ',') ?>% vs mes anterior
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card text-center p-3">
                                                            <h5>Créditos Grupales</h5>
                                                            <h3><?= $creditos_grupales_activos ?></h3>
                                                            <p><?= $grupos_activos ?> grupos activos</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card text-center p-3">
                                                            <h5>Cartera en Riesgo</h5>
                                                            <h3>Q<?= number_format($cartera_en_riesgo, 2, '.', ',') ?></h3>
                                                            <p class="text-muted">Créditos con más de 30 días de atraso</p>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-3">
                                                        <div class="card text-center p-3">
                                                            <h5>Próximos Vencimientos</h5>
                                                            <h3><?= $proximos_vencimientos ?></h3>
                                                            <p class="text-muted">En los próximos 7 días</p>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Tabs -->
                                                <ul class="nav nav-tabs">
                                                    <li class="nav-item">
                                                        <a class="nav-link active" href="#vista-general" data-bs-toggle="tab">Vista General</a>
                                                    </li>
                                                </ul>

                                                <div class="tab-content mt-4">
                                                    <!-- Vista General -->
                                                    <div class="tab-pane fade show active" id="vista-general">
                                                        <div class="row">
                                                            <div class="col-md-8">
                                                                <div class="card p-3">
                                                                    <h5>Desembolsos vs Recuperaciones</h5>
                                                                    <canvas id="chart" style="height: 200px;"></canvas>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <div class="card p-3">
                                                                    <h5>Créditos Recientes</h5>
                                                                    <ul class="list-group">
                                                                        <?php foreach ($data_creditos_recientes as $credito): ?>
                                                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                                                <?= $credito['nombre_cliente'] ?> - Q<?= number_format($credito['monto'], 2, '.', ',') ?>
                                                                                <span class="badge <?= $credito['estado'] == 'O' ? 'bg-success' : ($credito['estado'] == 'E' ? 'bg-primary' : 'bg-warning text-dark') ?>">
                                                                                    <?= $credito['estado'] == 'O' ? 'Desembolsado' : ($credito['estado'] == 'E' ? 'Aprobado' : 'En Proceso') ?>
                                                                                </span>
                                                                            </li>
                                                                        <?php endforeach; ?>
                                                                    </ul>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                                <!-- Ganancias Obtenidas -->
                                                <div class="row mt-4">
                                                    <div class="col-md-12">
                                                        <div class="card p-3">
                                                            <h5>Intereses Proyectados en desembolsos</h5>
                                                            <div class="mb-2">
                                                                <span>Mes Actual</span>
                                                                <div class="progress">
                                                                    <div class="progress-bar bg-success" role="progressbar" style="width: <?= ($ganancias_mes_actual / $ganancias_ano_actual) * 100 ?>%;">Q<?= number_format($ganancias_mes_actual, 2, '.', ',') ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="mb-2">
                                                                <span>Mes Anterior</span>
                                                                <div class="progress">
                                                                    <div class="progress-bar bg-primary" role="progressbar" style="width: <?= ($ganancias_mes_anterior / $ganancias_ano_actual) * 100 ?>%;">Q<?= number_format($ganancias_mes_anterior, 2, '.', ',') ?></div>
                                                                </div>
                                                            </div>
                                                            <div class="mb-2">
                                                                <span>Año Actual</span>
                                                                <div class="progress">
                                                                    <div class="progress-bar bg-warning" role="progressbar" style="width: 100%;">Q<?= number_format($ganancias_ano_actual, 2, '.', ',') ?></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!-- ESPACION DE ALERTA DE APERTURAS Y CIERRES DE CAJA -->
    <?php $verificacion_ape_cr = verificar_apertura_cierre($_SESSION['id'], $conexion);
    print_r($verificacion_ape_cr);
    print_r($verificacion_ape_cr); ?>

    <div aria-live="polite" aria-atomic="true" class="position-relative">
        <div class="toast-container top-0 end-0 pe-3" style="padding-top: 4rem !important;">
            <!-- Then put toasts within -->
            <div class="toast <?= ($verificacion_ape_cr[0] < 6) ? 'fade show' : ''; ?>" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="toast-header">
                    <span class="btn btn-<?= ($verificacion_ape_cr[0] > 0) ? 'warning' : 'danger'; ?> btn-sm me-2"><i class="fa-solid fa-triangle-exclamation"></i></span>
                    <strong class="me-auto">
                        <?= ($verificacion_ape_cr[0] > 0) ? '¡Advertencia!' : '¡Error!'; ?>
                    </strong>
                    <small class="text-muted">
                        En este momento
                    </small>
                    <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Cerca"></button>
                </div>
                <div class="toast-body">
                    <span class="text-primary"><?= $verificacion_ape_cr[1]; ?></span>
                </div>
            </div>
        </div>
    </div>
    <!-- FIN DE ALERTA -->
    <div class="loader-container loading--show">
        <div class="loader"></div>
        <div class="loaderimg"></div>
        <div class="loader2"></div>
    </div>
    <!--incio del metodo para poder cargar los datos de forma dinamieca en la view-->
    <script>

    </script>
    <!--Test Script para poder hacer los calculos del coso ese del dashboard xd-->
    <script>
        // Desembolsos
        const chartLabels = <?php echo $labels_json ?? '[]'; ?>;
        const chartValuesDesembolsado = <?php echo $valDesembolsado_json ?? '[]'; ?>;

        // Recuperaciones
        const chartLabelsRecuperado = <?php echo $labels_recuperaciones_json ?? '[]'; ?>;
        const chartValuesRecuperado = <?php echo $valRecuperado_json ?? '[]'; ?>;

        // Para graficar ambas series en la misma Chart.js, 
        // deben compartir las etiquetas (chartLabels). 
        // Si tienen las mismas etiquetas, las puedes usar directamente.

        const ctx = document.getElementById('chart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                // Si confirmaste que ambos comparten el mismo eje de tiempo (mes),
                // usa chartLabels. De lo contrario, unifícalas antes.
                labels: chartLabels,
                datasets: [{
                        label: 'Desembolsos últimos 5 meses',
                        data: chartValuesDesembolsado,
                        borderColor: 'blue',
                        fill: false
                    },
                    {
                        label: 'Recuperaciones en el mismo periodo',
                        data: chartValuesRecuperado,
                        borderColor: 'green',
                        fill: false
                    }
                ]
            },
            options: {
                scales: {
                    y: {
                        ticks: {
                            callback: function(value) {
                                return 'Q' + value;
                            }
                        }
                    }
                }
            }
        });
    </script>

    <script src="../../includes/js/script.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <!-- <script type="text/javascript" src="../../includes/js/all.min.js"></script> -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

</body>

</html>
<?php

?>