<?php

namespace App;
class Configuracion
{
    private $database;

    public function __construct($database)
    {
        $this->database = $database;
    }

    /**
     * Obtiene el valor de una configuración por su ID.
     *
     * @param int $idConfig El ID de la configuración.
     * @return string|null El valor de la configuración o null si no existe.
     */
    public function getValById($idConfig)
    {
        try {
            $resultado = $this->database->selectColumns("tb_configuraciones", ["valor"], "id_config = $idConfig");
            return (!empty($resultado) && isset($resultado[0]["valor"])) ? $resultado[0]["valor"] : null;
        } catch (\Exception $e) {
            // Lanzar una nueva excepción con un mensaje personalizado
            throw new \Exception("Error al obtener el valor de configuración: " . $e->getMessage());
        }
    }

    /**
     * Obtiene todas las configuraciones en formato clave-valor.
     *
     * @return array Un array asociativo de configuraciones.
     */
    public function obtenerTodas()
    {
        try {
            $resultados = $this->database->select("tb_configuraciones");
            $configuraciones = [];
            foreach ($resultados as $fila) {
            $configuraciones[$fila["id_config"]] = $fila["valor"];
            }
            return $configuraciones;
        } catch (\Exception $e) {
            // Lanzar una nueva excepción con un mensaje personalizado
            throw new \Exception("Error al obtener todas las configuraciones: " . $e->getMessage());
        }
    }
}
