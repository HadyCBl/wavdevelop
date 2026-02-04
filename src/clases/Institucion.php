<?php

namespace App\Generic;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class Institucion
{
    private ?DatabaseAdapter $db = null;
    private int $idInstitucion;
    private array $datosInstitucion = []; // Caché para todos los campos cargados
    private bool $datosBasicosCargados = false;

    // Define qué campos se consideran "básicos" y se cargan juntos
    private array $camposBasicos = [
        'cod_coop',
        'nomb_comple',
        'nomb_cor' 
    ];

    public function __construct(int $idInstitucion)
    {
        $this->idInstitucion = $idInstitucion;
    }

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(2); // Conectar a DDBB_NAME_GENERAL
            } catch (Exception $e) {
                Log::error("Error al conectar BD para Institucion {$this->idInstitucion}: " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
                throw new Exception("Error crítico al conectar BD para Institucion.", 0, $e);
            }
        }
    }

    /**
     * Carga el conjunto definido de campos básicos de la institución.
     */
    private function cargarDatosBasicosInstitucion(): void
    {
        if ($this->datosBasicosCargados) {
            return;
        }
        $this->conectarDb();
        try {
            // Log::info("Cargando datos básicos para Institucion ID: {$this->idInstitucion}");
            $institucionData = $this->db->selectColumns('info_coperativa', $this->camposBasicos, 'id_cop = ?', [$this->idInstitucion]);

            if (empty($institucionData)) {
                throw new Exception("Institución con ID {$this->idInstitucion} no encontrada (al cargar datos básicos).");
            }
            $this->datosInstitucion = array_merge($this->datosInstitucion, $institucionData[0]);
            $this->datosBasicosCargados = true;
        } catch (Exception $e) {
            Log::error("Error al cargar datos básicos para Institucion {$this->idInstitucion}: " . $e->getMessage());
            throw new Exception("No se pudieron cargar los datos básicos de la Institución {$this->idInstitucion}.", 0, $e);
        }
    }

    /**
     * Obtiene un campo específico. Si es básico y no está cargado, carga todos los básicos.
     * Si es no básico y no está cargado, lo carga individualmente.
     */
    private function obtenerCampo(string $nombreCampo): mixed
    {
        if (in_array($nombreCampo, $this->camposBasicos) && !$this->datosBasicosCargados) {
            $this->cargarDatosBasicosInstitucion();
        }

        if (array_key_exists($nombreCampo, $this->datosInstitucion)) {
            return $this->datosInstitucion[$nombreCampo];
        }

        if (!in_array($nombreCampo, $this->camposBasicos)) {
            $this->conectarDb();
            try {
                // Log::info("Cargando campo individual '{$nombreCampo}' para Institucion ID: {$this->idInstitucion}");
                $resultado = $this->db->selectColumns('info_coperativa', [$nombreCampo], 'id_cop = ?', [$this->idInstitucion]);
                if (!empty($resultado) && isset($resultado[0][$nombreCampo])) {
                    $this->datosInstitucion[$nombreCampo] = $resultado[0][$nombreCampo];
                    return $this->datosInstitucion[$nombreCampo];
                } else {
                    $this->datosInstitucion[$nombreCampo] = null;
                    return null;
                }
            } catch (Exception $e) {
                Log::error("Error al cargar campo individual '{$nombreCampo}' para Institucion {$this->idInstitucion}: " . $e->getMessage());
                $this->datosInstitucion[$nombreCampo] = null;
                return null;
            }
        }
        return null;
    }

    public function getId(): int
    {
        return $this->idInstitucion;
    }

    public function getNombreCompletoInstitucion(): ?string
    {
        return $this->obtenerCampo('nomb_comple');
    }

    public function getNombreCortoInstitucion(): ?string
    {
        return $this->obtenerCampo('nomb_cor');
    }

    public function getCodigoCooperativa(): ?string // Asumiendo que 'cod_coop' es un campo básico
    {
        return $this->obtenerCampo('cod_coop');
    }

    public function getFolderInstitucion(): string
    {
        return $this->obtenerCampo('folder');
    }

    // Ejemplo de un campo que podría NO ser básico:
    // public function getDireccionInstitucion(): ?string
    // {
    //     return $this->obtenerCampo('direccion_institucion'); // Se cargaría individualmente
    // }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}
