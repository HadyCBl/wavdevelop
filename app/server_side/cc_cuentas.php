<?php
require __DIR__ . '/../../vendor/autoload.php';

use Micro\Helpers\Log;
use Micro\Generic\BaseDataTableProcessor;

class CuentasPorCobrarDataProcessor extends BaseDataTableProcessor
{
    protected function getBaseQuery(): string
    {
        // Obtener whereExtra dinámico
        $whereExtra = $this->buildWhereExtra();
        
        return "
            SELECT 
                cue.id,
                cli.short_name,
                cli.no_identifica,
                IFNULL((SELECT SUM(kp) FROM cc_kardex WHERE id_cuenta=cue.id AND tipo='D' AND estado='1' GROUP BY id_cuenta), 0) AS monto_inicial,
                cue.fecha_inicio,
                cue.estado 
            FROM cc_cuentas cue
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cue.id_cliente
            WHERE $whereExtra
        ";
    }

    protected function getCountQuery(): string
    {
        // Obtener whereExtra dinámico para el conteo
        $whereExtra = $this->buildWhereExtra();
        
        return "
            SELECT COUNT(cue.id)
            FROM cc_cuentas cue
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cue.id_cliente
            WHERE $whereExtra
        ";
    }

    protected function getColumns(): array
    {
        return ['id', 'short_name', 'no_identifica', 'monto_inicial', 'fecha_inicio', 'estado'];
    }

    protected function getSearchable(): array
    {
        return [1, 1, 1, 1, 1, 1];
    }

    private function buildWhereExtra(): string
    {
        $whereExtra = $_POST['whereExtra'] ?? $_GET['whereExtra'] ?? "";

         // Validar que no esté vacío
        if (empty(trim($whereExtra))) {
            return "cue.estado IN ('ACTIVA', 'CANCELADA')";
        }
        
        return $whereExtra;
    }

    // protected function getWhereExtra(): string
    // {
    //     // Ahora SÍ podemos usar alias porque se aplica ANTES de la subquery
    //     // El whereExtra se integra directamente en getBaseQuery() y getCountQuery()
    //     $whereExtra = $_POST['whereExtra'] ?? $_GET['whereExtra'] ?? "";
        
    //     // Log para debug (comentar en producción)
    //     // Log::debug("whereExtra recibido: " . $whereExtra);

    //     // Validar que no esté vacío
    //     if (empty(trim($whereExtra))) {
    //         return "cue.estado IN ('ACTIVA', 'CANCELADA')";
    //     }
        
    //     return $whereExtra;
    // }
}

// Ejecutar
$processor = new CuentasPorCobrarDataProcessor();
$processor->process();
