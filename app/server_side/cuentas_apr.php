<?php
require __DIR__ . '/../../vendor/autoload.php';

use Micro\Generic\BaseDataTableProcessor;

class ClientesDataTableProcessor extends BaseDataTableProcessor
{
    protected function getBaseQuery(): string
    {
        return "
            SELECT cta.ccodaport, cli.short_name, cli.no_identifica, tip.nombre nombreProducto, calcular_saldo_apr_tipcuenta(cta.ccodaport,'2025-11-18') saldo
                FROM aprcta cta 
                INNER JOIN aprtip tip ON tip.ccodtip= cta.ccodtip
                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                WHERE cta.estado IN ('A','B')
        ";
    }

    protected function getCountQuery(): string
    {
        return "
            SELECT COUNT(cta.ccodaport)
            FROM aprcta cta 
            INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
            WHERE cta.estado IN ('A','B')
        ";
    }

    protected function getColumns(): array
    {
        return ['ccodaport', 'short_name', 'no_identifica', 'nombreProducto'];
    }

    protected function getSearchable(): array
    {
        return [1, 1, 1, 1];
    }

    protected function getWhereExtra(): string
    {
        return "1=1";
    }
}

// Ejecutar
$processor = new ClientesDataTableProcessor();
$processor->process();