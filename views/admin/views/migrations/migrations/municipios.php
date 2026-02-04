<?php

use Micro\Helpers\Log;

require __DIR__ . '/../../../../../vendor/autoload.php';
require_once __DIR__ . '/../../../../../src/funcphp/func_gen.php';

$fileTmpPath = __DIR__ . '/uploads/municipios.json';

$database = new \App\DatabaseAdapter();

try {
    $database->openConnection();

    $verificacion = $database->getAllResults("SELECT count(*) as totaldepartamentos FROM tb_departamentos WHERE id_pais=1");
    if ($verificacion[0]['totaldepartamentos'] > 0) {
        throw new Exception("Ya existen departamentos/muncipios cargados para el país con ID 1 (México). Operación cancelada. Total departamentos: " . $verificacion[0]['totaldepartamentos']);
    }

    $jsonContent = file_get_contents($fileTmpPath);
    $datos = json_decode($jsonContent, true);

    Log::info("Cargando municipios desde JSON", ['count' => count($datos)]);

    //   "id_estado": 1,
    //   "id_municipio": 1,
    //   "estado": "Aguascalientes",
    //   "municipio": "Aguascalientes",
    //   "Cve_INEGI": 1001

    $idEstado = 0;
    $idRegistroEstado = 0;

    foreach ($datos as $item) {
        // $idEstado = (int)$item['id_estado'];

        if ($idEstado != $item['id_estado']) {
            $estado = $item['estado'];
            $tb_departamentos = [
                'nombre' => $item['estado'],
                'id_pais' => 1,
                'cod_crediref' => $item['id_estado'],
            ];
            $idRegistroEstado = $database->insert('tb_departamentos', $tb_departamentos);
            $idEstado = $item['id_estado'];
            echo "++++++++++++ Insertado estado: $estado con ID $idEstado <br>";
        }

        $tb_municipios = [
            'nombre' => $item['municipio'],
            'codigo' => $item['Cve_INEGI'],
            'cod_crediref' => $item['id_municipio'],
            'id_departamento' => $idRegistroEstado,
        ];

        $database->insert('tb_municipios', $tb_municipios);
        echo "Insertado municipio: {$item['municipio']} con ID {$item['id_municipio']} <br>";
    }


    echo json_encode(['status' => 'success']);
} catch (Exception $e) {
    Log::error("Error al cargar municipios desde JSON", ['message' => $e->getMessage()]);
    echo json_encode(['status' => 'error', 'message' => 'Error al cargar municipios: ' . $e->getMessage()]);
} finally {
    if ($database) {
        $database->closeConnection();
    }
}
