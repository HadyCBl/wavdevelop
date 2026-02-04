<?php

namespace App;

use Exception;

include_once 'database.php';

class DatabaseAdapter extends \Database
{

    public function __construct() // Constructor sin parámetros
    {
        $requiredEnvVars = [
            'DDBB_HOST',
            'DDBB_NAME',
            'DDBB_USER',
            'DDBB_PASSWORD',
            'DDBB_NAME_GENERAL'
        ];

        $envValues = [];
        foreach ($requiredEnvVars as $var) {
            if (!isset($_ENV[$var])) {
                throw new Exception("Variable de entorno requerida '{$var}' no está definida para DatabaseAdapter.");
            }
            $envValues[$var] = $_ENV[$var];
        }

        parent::__construct(
            $envValues['DDBB_HOST'],
            $envValues['DDBB_NAME'],
            $envValues['DDBB_USER'],
            $envValues['DDBB_PASSWORD'],
            $envValues['DDBB_NAME_GENERAL']
        );
    }
}
