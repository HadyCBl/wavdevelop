<?php

namespace Creditos\Utilidades;

use App\DatabaseAdapter;
use App\Generic\CacheManager;
use Exception;

class DashboardCreditosData
{
    private DatabaseAdapter $db;
    private CacheManager $cache;
    private int $ttl;

    public function __construct(int $ttl = 43200)
    {
        $this->db = new DatabaseAdapter();
        $this->cache = new CacheManager('dashboard_creditos_', $ttl);
        $this->ttl = $ttl;
        $this->db->openConnection();
    }

    public function getDato(string $key, string $sql): mixed
    {
        $cacheKey = $key;
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
        $result = $this->db->executeQuery($sql)->fetch(\PDO::FETCH_ASSOC);
        $this->cache->set($cacheKey, $result, $this->ttl);
        return $result;
    }

    public function getDashboardData(): array
    {
        $date = date('Y-m-d');
        $date_lastyear = date('Y-12-31', strtotime($date . ' - 1 year'));

        $queries = [
            'actual' => "SELECT SUM(cremi.NCapDes)-SUM(sum_KP) AS creditos_activos_actual
                            FROM cremcre_meta cremi
                            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
                            LEFT JOIN (
                                SELECT ccodcta, SUM(KP) AS sum_KP
                                FROM CREDKAR
                                WHERE dfecpro <= '$date' AND cestado != 'X' AND ctippag = 'P'
                                GROUP BY ccodcta
                            ) AS kar ON kar.ccodcta = cremi.CCODCTA
                            WHERE (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') 
                            AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0  
                            AND cremi.DFecDsbls <= '$date'",
            'anterior' => "SELECT SUM(cremi.NCapDes) AS creditos_activos_anterior
                            FROM cremcre_meta cremi
                            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
                            LEFT JOIN (
                                SELECT ccodcta, SUM(KP) AS sum_KP
                                FROM CREDKAR
                                WHERE dfecpro <= '$date_lastyear' AND cestado != 'X' AND ctippag = 'P'
                                GROUP BY ccodcta
                            ) AS kar ON kar.ccodcta = cremi.CCODCTA
                            WHERE (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G') 
                            AND (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0  
                            AND cremi.DFecDsbls <= '$date_lastyear'",
            'vencimientos' => "SELECT COUNT(*) AS proximos_vencimientos
                            FROM cremcre_meta cremi
                            WHERE cremi.Cestado = 'F' 
                            AND cremi.DFecVen BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY);",
            'cartera_riesgo' => "SELECT 
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
                            FROM cremcre_meta cremi
                            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                            INNER JOIN tb_usuario usu ON usu.id_usu = cremi.CodAnal 
                            LEFT JOIN (
                                SELECT ccodcta, SUM(KP) AS sum_KP
                                FROM CREDKAR
                                WHERE dfecpro <= '$date' AND cestado != 'X' AND ctippag = 'P'
                                GROUP BY ccodcta
                            ) AS kar ON kar.ccodcta = cremi.CCODCTA
                            WHERE (cremi.CESTADO = 'F' OR cremi.CESTADO = 'G')
                            AND cremi.DFecDsbls <= '$date'",
            'mes_actual' => "SELECT SUM(ppg.nintere) AS ganancias_mes_actual
                                FROM Cre_ppg ppg
                                INNER JOIN cremcre_meta cremi ON ppg.ccodcta = cremi.CCODCTA
                                WHERE cremi.Cestado = 'F' 
                                AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE()) 
                                AND MONTH(cremi.DFecDsbls) = MONTH(CURDATE());",
            'mes_anterior' => "SELECT SUM(ppg.nintere) AS ganancias_mes_anterior
                            FROM Cre_ppg ppg
                            INNER JOIN cremcre_meta cremi ON ppg.ccodcta = cremi.CCODCTA
                            WHERE cremi.Cestado = 'F' 
                            AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE() - INTERVAL 1 MONTH) 
                            AND MONTH(cremi.DFecDsbls) = MONTH(CURDATE() - INTERVAL 1 MONTH);",
            'ano_actual' => "SELECT SUM(GREATEST(0, cremi.NCapDes - IFNULL(kar.sum_KP, 0))) AS ganancias_ano_actual
                            FROM cremcre_meta cremi
                            INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli 
                            LEFT JOIN (
                                SELECT ccodcta, SUM(KP) AS sum_KP
                                FROM CREDKAR
                                WHERE dfecpro <= '$date' AND cestado != 'X' AND ctippag = 'P'
                                GROUP BY ccodcta
                            ) AS kar ON kar.ccodcta = cremi.CCODCTA
                            WHERE cremi.Cestado = 'F' 
                            AND YEAR(cremi.DFecDsbls) = YEAR(CURDATE())",
            'creditos_grupales' => "SELECT COUNT(DISTINCT cremi.CCODCTA) AS creditos_grupales_activos,
                            COUNT(DISTINCT cremi.CcodGrupo) AS grupos_activos
                            FROM cremcre_meta cremi
                            WHERE cremi.Cestado = 'F' 
                            AND cremi.tipoEnti = 'GRUP';",
        ];

        $results = [];
        foreach ($queries as $key => $sql) {
            $results[$key] = $this->getDato($key, $sql);
        }

        // Calcula los porcentajes
        $porcentaje_cambio = ($results['anterior']['creditos_activos_anterior'] ?? 0) > 0 ?
            (($results['actual']['creditos_activos_actual'] ?? 0) - ($results['anterior']['creditos_activos_anterior'] ?? 0)) / ($results['anterior']['creditos_activos_anterior'] ?? 1) * 100
            : 100;

        return [
            'creditos_activos_actual' => $results['actual']['creditos_activos_actual'] ?? 0,
            'creditos_activos_anterior' => $results['anterior']['creditos_activos_anterior'] ?? 0,
            'proximos_vencimientos' => $results['vencimientos']['proximos_vencimientos'] ?? 0,
            'cartera_en_riesgo' => $results['cartera_riesgo']['cartera_en_riesgo'] ?? 0,
            'ganancias_mes_actual' => $results['mes_actual']['ganancias_mes_actual'] ?? 0,
            'ganancias_mes_anterior' => $results['mes_anterior']['ganancias_mes_anterior'] ?? 0,
            'ganancias_ano_actual' => $results['ano_actual']['ganancias_ano_actual'] ?? 0,
            'creditos_grupales_activos' => $results['creditos_grupales']['creditos_grupales_activos'] ?? 0,
            'grupos_activos' => $results['creditos_grupales']['grupos_activos'] ?? 0,
            'porcentaje_cambio' => $porcentaje_cambio,
        ];
    }

    public function clearCache(): void
    {
        $this->cache->clear();
    }

    public function __destruct()
    {
        $this->db->closeConnection();
    }
}
