<?php

namespace App\Generic;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class Agencia
{
    private ?DatabaseAdapter $db = null;
    private int $idAgencia;
    private array $datosAgencia = []; // Caché para todos los campos cargados
    private bool $datosBasicosCargados = false;

    private array $camposBasicos = [
        'nom_agencia',
        'cod_agenc',
        'id_institucion'
    ];

    private ?Institucion $institucionInstancia = null;

    public function __construct(int $idAgencia)
    {
        $this->idAgencia = $idAgencia;
    }

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(1); // tb_agencia está en la BD principal
            } catch (Exception $e) {
                Log::error("Error al conectar BD para Agencia {$this->idAgencia}: " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
                throw new Exception("Error crítico al conectar BD para Agencia.", 0, $e);
            }
        }
    }

    /**
     * Carga el conjunto definido de campos básicos de la agencia.
     */
    private function cargarDatosBasicosAgencia(): void
    {
        if ($this->datosBasicosCargados) {
            return;
        }
        $this->conectarDb();
        try {
            // Log::info("Cargando datos básicos para Agencia ID: {$this->idAgencia}");
            $agenciaData = $this->db->selectColumns('tb_agencia', $this->camposBasicos, 'id_agencia = ?', [$this->idAgencia]);

            if (empty($agenciaData)) {
                throw new Exception("Agencia con ID {$this->idAgencia} no encontrada (al cargar datos básicos).");
            }
            $this->datosAgencia = array_merge($this->datosAgencia, $agenciaData[0]);
            $this->datosBasicosCargados = true;
        } catch (Exception $e) {
            Log::error("Error al cargar datos básicos para Agencia {$this->idAgencia}: " . $e->getMessage());
            throw new Exception("No se pudieron cargar los datos básicos de la Agencia {$this->idAgencia}.", 0, $e);
        }
    }

    /**
     * Obtiene un campo específico. Si es básico y no está cargado, carga todos los básicos.
     * Si es no básico y no está cargado, lo carga individualmente.
     */
    private function obtenerCampo(string $nombreCampo): mixed
    {
        if (in_array($nombreCampo, $this->camposBasicos) && !$this->datosBasicosCargados) {
            $this->cargarDatosBasicosAgencia();
        }

        if (array_key_exists($nombreCampo, $this->datosAgencia)) {
            return $this->datosAgencia[$nombreCampo];
        }

        if (!in_array($nombreCampo, $this->camposBasicos)) {
            $this->conectarDb();
            try {
                // Log::info("Cargando campo individual '{$nombreCampo}' para Agencia ID: {$this->idAgencia}");
                $resultado = $this->db->selectColumns('tb_agencia', [$nombreCampo], 'id_agencia = ?', [$this->idAgencia]);
                if (!empty($resultado) && isset($resultado[0][$nombreCampo])) {
                    $this->datosAgencia[$nombreCampo] = $resultado[0][$nombreCampo];
                    return $this->datosAgencia[$nombreCampo];
                } else {
                    $this->datosAgencia[$nombreCampo] = null;
                    return null;
                }
            } catch (Exception $e) {
                Log::error("Error al cargar campo individual '{$nombreCampo}' para Agencia {$this->idAgencia}: " . $e->getMessage());
                $this->datosAgencia[$nombreCampo] = null;
                return null;
            }
        }
        return null;
    }

    public function getId(): int
    {
        return $this->idAgencia;
    }

    public function getNombreAgencia(): ?string
    {
        return $this->obtenerCampo('nom_agencia');
    }

    public function getCodigoAgencia(): ?string // Asumiendo que 'cod_agenc' es un campo básico
    {
        return $this->obtenerCampo('cod_agenc');
    }

    public function getIdInstitucion(): ?int
    {
        $valor = $this->obtenerCampo('id_institucion'); // 'id_institucion' ahora es básico
        return $valor !== null ? (int)$valor : null;
    }

    // Ejemplo de un campo que podría NO ser básico:
    // public function getTelefonoAgencia(): ?string
    // {
    //     return $this->obtenerCampo('telefono_agencia'); // Se cargaría individualmente si no está en $camposBasicos
    // }

    public function __get(string $name)
    {
        if ($name === 'institucion') {
            if ($this->institucionInstancia === null) {
                $idInstitucion = $this->getIdInstitucion(); // Esto usará obtenerCampo
                if ($idInstitucion !== null) {
                    // Log::info("Creando instancia de Institucion para Agencia {$this->idAgencia} con ID Institucion {$idInstitucion}");
                    $this->institucionInstancia = new Institucion($idInstitucion);
                } else {
                    Log::warning("Agencia {$this->idAgencia} no tiene un id_institucion para cargar la relación Institucion.");
                    return null;
                }
            }
            return $this->institucionInstancia;
        }
        // Log::warning("Acceso a propiedad mágica no definida: {$name} en Agencia {$this->idAgencia}");
        return null;
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}
