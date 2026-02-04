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
                cue.fecha_inicio,
                cue.estado 
            FROM aux_cuentas cue
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
            FROM aux_cuentas cue
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cue.id_cliente
            WHERE $whereExtra
        ";
    }

    protected function getColumns(): array
    {
        return ['id', 'short_name', 'no_identifica', 'fecha_inicio', 'estado'];
    }

    protected function getSearchable(): array
    {
        return [1, 1, 1, 1, 1];
    }

    private function buildWhereExtra(): string
    {

        return "cue.estado IN ('vigente', 'cerrada')";
    }
}

// Ejecutar
$processor = new CuentasPorCobrarDataProcessor();
$processor->process();
