<?php
require __DIR__ . '/../../vendor/autoload.php';

use Micro\Helpers\Log;
use Micro\Generic\BaseDataTableProcessor;

class BeneficiariosDataProcessor extends BaseDataTableProcessor
{
    protected function getBaseQuery(): string
    {
        return "SELECT id, nombres, apellidos, identificacion, telefono, direccion FROM cli_beneficiarios WHERE estado = '1'";
    }

    protected function getCountQuery(): string
    {
        return "SELECT COUNT(id) FROM cli_beneficiarios WHERE estado = '1'";
    }

    protected function getColumns(): array
    {
        return ['id', 'nombres', 'apellidos', 'identificacion', 'telefono'];
    }

    protected function getSearchable(): array
    {
        return [1, 1, 1, 1, 1];
    }
}

// Ejecutar
$processor = new BeneficiariosDataProcessor();
$processor->process();
