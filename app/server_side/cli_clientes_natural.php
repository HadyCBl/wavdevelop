<?php
require __DIR__ . '/../../vendor/autoload.php';

use Micro\Generic\BaseDataTableProcessor;

class ClientesNaturalDataTableProcessor extends BaseDataTableProcessor
{
    protected function getBaseQuery(): string
    {
        return "
            SELECT 
                cli.idcod_cliente as codigo_cliente, 
                cli.short_name as nombre,
                cli.no_identifica as identificacion,
                CASE
                    WHEN date_birth IS NOT NULL
                    THEN DATE_FORMAT(date_birth, '%d/%m/%Y')
                    ELSE '-'
                END AS fecha_nacimiento,
                CASE
                    WHEN fecha_actualizacion IS NOT NULL
                    THEN DATE_FORMAT(fecha_actualizacion, '%d/%m/%Y')
                    ELSE '-'
                END AS fecha_actualizacion
            FROM tb_cliente cli 
            WHERE cli.estado = 1 AND cli.id_tipoCliente = 'Natural'
        ";
    }

    protected function getCountQuery(): string
    {
        return "
            SELECT COUNT(cli.idcod_cliente)
            FROM tb_cliente cli
            WHERE cli.estado = 1
        ";
    }

    protected function getColumns(): array
    {
        return ['codigo_cliente', 'nombre', 'identificacion', 'fecha_nacimiento', 'fecha_actualizacion'];
    }

    protected function getSearchable(): array
    {
        return [1, 1, 1, 1, 1];
    }

    protected function getWhereExtra(): string
    {
        return "1=1";
    }
}

// Ejecutar
$processor = new ClientesNaturalDataTableProcessor();
$processor->process();
