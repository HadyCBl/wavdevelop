<?php
// get_dashboard_data.php

include __DIR__ . '/../../includes/Config/config.php';
include __DIR__ . '/../../includes/BD_con/db_con.php';
include __DIR__ . '/../../includes/Config/database.php';

session_start();
if (!isset($_SESSION['usu'])) {
    header('HTTP/1.1 401 Unauthorized');
    exit;
}

date_default_timezone_set('America/Guatemala');

try {
    // Inicializar y abrir la conexión a la base de datos
    $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
    $database->openConnection();

    // Fechas necesarias
    $date = date('Y-m-d');
    $date_lastyear = date('Y-12-31', strtotime($date . ' - 1 year'));

    /**
     * Ejecuta una consulta SQL preparada con parámetros y retorna el resultado.
     *
     * @param PDO    $conn   Conexión PDO.
     * @param string $sql    Consulta SQL con marcadores de posición.
     * @param array  $params Arreglo asociativo de parámetros.
     *
     * @return array         Resultado de la consulta.
     * @throws Exception     Si falla la ejecución.
     */
    function executeQuery(PDO $conn, string $sql, array $params = []): array {
        $stmt = $conn->prepare($sql);
        if (!$stmt->execute($params)) {
            throw new Exception("Error al ejecutar la consulta: $sql");
        }
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    // Definición de las consultas utilizando marcadores de posición donde sea posible.
    // Se agrupa la consulta de 'sum_KP' para evitar repetir la lógica, adaptando el parámetro de fecha.
    $subquery = "
        SELECT ccodcta, SUM(KP) AS sum_KP
        FROM CREDKAR
        WHERE dfecpro <= :fecha AND cestado != 'X' AND ctippag = 'P'
        GROUP BY ccodcta
    ";

    $queries = [
        'actual' => "
            SELECT SUM(cremi.NCapDes) - SUM(IFNULL(kar.sum_KP, 0)) AS creditos_activos_actual
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
            INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
            LEFT JOIN ( $subquery ) AS kar ON kar.ccodcta = cremi.CCODCTA
            WHERE (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') 
              AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0  
              AND cremi.DFecDsbls <= :fecha
        ",
        'anterior' => "
            SELECT SUM(cremi.NCapDes) AS creditos_activos_anterior
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
            INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
            LEFT JOIN (
                " . str_replace(':fecha', ':fecha_lastyear', $subquery) . "
            ) AS kar ON kar.ccodcta = cremi.CCODCTA
            WHERE (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') 
              AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0  
              AND cremi.DFecDsbls <= :fecha_lastyear
        ",
        'vencimientos' => "
            SELECT COUNT(*) AS proximos_vencimientos
            FROM cremcre_meta cremi
            WHERE cremi.Cestado = 'F' 
              AND cremi.DFecVen BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
        ",
        'cartera_riesgo' => "
            SELECT 
                SUM(CASE 
                    WHEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(:fecha, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) > 30 
                    THEN GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
                    ELSE 0 
                END) AS cartera_en_riesgo,
                SUM(CASE 
                    WHEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(:fecha, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) = 0 
                    THEN GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
                    ELSE 0 
                END) AS al_dia,
                SUM(CASE 
                    WHEN CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(cre_dias_atraso(:fecha, cremi.CCODCTA), '#', 1), '_', 1) AS SIGNED) BETWEEN 1 AND 30 
                    THEN GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
                    ELSE 0 
                END) AS de_1_a_30_dias
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
            INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
            LEFT JOIN ( $subquery ) AS kar ON kar.ccodcta = cremi.CCODCTA
            WHERE (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G')
              AND cremi.DFecDsbls <= :fecha
        ",
        'creditos_recientes' => "
            SELECT 
                CONCAT(cli.primer_name, ' ', cli.primer_last) AS nombre_cliente,
                CASE 
                    WHEN cremi.Cestado IN ('A', 'D') THEN cremi.Montosol
                    WHEN cremi.Cestado IN ('E', 'F') THEN cremi.MonSug
                END AS monto,
                cremi.Cestado AS estado
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
            WHERE cremi.Cestado IN ('A', 'D', 'E', 'F')
            ORDER BY cremi.DFecDsbls DESC
            LIMIT 4
        ",
        'mes_actual' => "
            SELECT SUM(ppg.nintere) AS ganancias_mes_actual
            FROM Cre_ppg ppg
            INNER JOIN cremcre_meta cremi ON ppg.ccodcta = cremi.CCODCTA
            WHERE cremi.Cestado = 'F' 
              AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE()) 
              AND MONTH(cremi.DFecDsbls) = MONTH(CURDATE())
        ",
        'mes_anterior' => "
            SELECT SUM(ppg.nintere) AS ganancias_mes_anterior
            FROM Cre_ppg ppg
            INNER JOIN cremcre_meta cremi ON ppg.ccodcta = cremi.CCODCTA
            WHERE cremi.Cestado = 'F' 
              AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE() - INTERVAL 1 MONTH) 
              AND MONTH(cremi.DFecDsbls) = MONTH(CURDATE() - INTERVAL 1 MONTH)
        ",
        'ano_actual' => "
            SELECT SUM(GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0))) AS ganancias_ano_actual
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
            INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
            LEFT JOIN ( $subquery ) AS kar ON kar.ccodcta = cremi.CCODCTA
            WHERE cremi.Cestado = 'F' 
              AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE())
        ",
        'ano_anterior' => "
            SELECT SUM(GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0))) AS ganancias_ano_anterior
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
            INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
            LEFT JOIN (
                " . str_replace(':fecha', ':fecha_lastyear', $subquery) . "
            ) AS kar ON kar.ccodcta = cremi.CCODCTA
            WHERE cremi.Cestado = 'F' 
              AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE() - INTERVAL 1 YEAR)
        ",
        'creditos_grupales' => "
            SELECT 
                COUNT(DISTINCT cremi.CCODCTA) AS creditos_grupales_activos,
                COUNT(DISTINCT cremi.CcodGrupo) AS grupos_activos
            FROM cremcre_meta cremi
            WHERE cremi.Cestado = 'F' 
              AND cremi.tipoEnti = 'GRUP'
        ",
        'grafica' => "
            SELECT DATE_FORMAT(cremi.DFecDsbls, '%Y-%m') AS mes,
                   SUM(GREATEST(0, cremi.NCapDes)) AS monto_desembolsado
            FROM cremcre_meta cremi
            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
            INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD 
            INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo 
            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
            WHERE (cremi.Cestado = 'F' OR cremi.Cestado = 'G')
              AND cremi.DFecDsbls >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY mes
            ORDER BY mes ASC
        ",
        'grafica_recuperaciones' => "
            SELECT DATE_FORMAT(kar.dfecpro, '%Y-%m') AS mes,
                   SUM(kar.KP) AS monto_recuperado
            FROM CREDKAR kar
            WHERE kar.ctippag = 'P'
              AND kar.cestado != 'X'
              AND kar.dfecpro >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
            GROUP BY mes
            ORDER BY mes ASC
        "
    ];

    // Ejecutar las consultas y pasar los parámetros necesarios
    $results = [];

    foreach ($queries as $key => $sql) {
        $params = [];
        // Para consultas que usan la fecha actual
        if (in_array($key, ['actual', 'cartera_riesgo', 'ano_actual'])) {
            $params[':fecha'] = $date;
        }
        // Para consultas que usan la fecha del año anterior
        if (in_array($key, ['anterior', 'ano_anterior'])) {
            $params[':fecha_lastyear'] = $date_lastyear;
        }
        // Ejecutar la consulta
        $results[$key] = executeQuery($conn, $sql, $params);
    }

    // Cálculos adicionales (porcentajes)
    $creditos_activos_anterior = (float) ($results['anterior']['creditos_activos_anterior'] ?? 0);
    $creditos_activos_actual   = (float) ($results['actual']['creditos_activos_actual'] ?? 0);
    $porcentaje_cambio = $creditos_activos_anterior > 0 ?
        (($creditos_activos_actual - $creditos_activos_anterior) / $creditos_activos_anterior) * 100 : 100;

    $ganancias_ano_anterior = (float) ($results['ano_anterior']['ganancias_ano_anterior'] ?? 0);
    $ganancias_ano_actual   = (float) ($results['ano_actual']['ganancias_ano_actual'] ?? 0);
    $porcentaje_cambio_anual = $ganancias_ano_anterior > 0 ?
        (($ganancias_ano_actual - $ganancias_ano_anterior) / $ganancias_ano_anterior) * 100 : 100;

    // Preparar la salida final
    $output = [
        'creditos_activos_actual'  => $creditos_activos_actual,
        'creditos_activos_anterior'=> $creditos_activos_anterior,
        'proximos_vencimientos'    => $results['vencimientos']['proximos_vencimientos'] ?? 0,
        'cartera_en_riesgo'        => $results['cartera_riesgo']['cartera_en_riesgo'] ?? 0,
        'creditos_recientes'       => $results['creditos_recientes'] ?? [],
        'ganancias_mes_actual'     => $results['mes_actual']['ganancias_mes_actual'] ?? 0,
        'ganancias_mes_anterior'   => $results['mes_anterior']['ganancias_mes_anterior'] ?? 0,
        'ganancias_ano_actual'     => $ganancias_ano_actual,
        'ganancias_ano_anterior'   => $ganancias_ano_anterior,
        'creditos_grupales_activos'=> $results['creditos_grupales']['creditos_grupales_activos'] ?? 0,
        'grupos_activos'           => $results['creditos_grupales']['grupos_activos'] ?? 0,
        'porcentaje_cambio'        => $porcentaje_cambio,
        'porcentaje_cambio_anual'  => $porcentaje_cambio_anual,
        'grafica'                  => $results['grafica'] ?? [],
        'grafica_recuperaciones'   => $results['grafica_recuperaciones'] ?? []
    ];

    header('Content-Type: application/json');
    echo json_encode($output);
    
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => $e->getMessage()]);
} finally {
    $database->closeConnection();
}
?>
