<?php

namespace App\Generic;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class User
{
    private ?DatabaseAdapter $db = null;
    private int $idUsuario;
    private array $datosUsuario = []; // Caché para todos los campos cargados
    private bool $datosBasicosCargados = false;

    private array $camposBasicos = [
        'nombre', 'apellido', 'usu', 'id_agencia', 'Email'
    ];

    private ?Agencia $agenciaInstancia = null;

    public function __construct(int $idUsuario)
    {
        $this->idUsuario = $idUsuario;
    }

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(1);
            } catch (Exception $e) {
                Log::error("Error al conectar BD para User {$this->idUsuario}: " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
                throw new Exception("Error crítico al conectar BD para User.", 0, $e);
            }
        }
    }

    /**
     * Carga el conjunto definido de campos básicos del usuario.
     */
    private function cargarDatosBasicosUsuario(): void
    {
        if ($this->datosBasicosCargados) {
            return;
        }
        $this->conectarDb();
        try {
            // Log::info("Cargando datos básicos para User ID: {$this->idUsuario}");
            $userData = $this->db->selectColumns('tb_usuario', $this->camposBasicos, 'id_usu = ? AND estado = 1', [$this->idUsuario]);

            if (empty($userData)) {
                throw new Exception("Usuario con ID {$this->idUsuario} no encontrado o no está activo (al cargar datos básicos).");
            }
            // Fusionar los datos básicos cargados en el array principal de datos del usuario
            $this->datosUsuario = array_merge($this->datosUsuario, $userData[0]);
            $this->datosBasicosCargados = true;
        } catch (Exception $e) {
            Log::error("Error al cargar datos básicos para User {$this->idUsuario}: " . $e->getMessage());
            // No marcamos como cargados para permitir reintento o manejo de error
            throw new Exception("No se pudieron cargar los datos básicos del usuario {$this->idUsuario}.", 0, $e);
        }
    }

    /**
     * Obtiene un campo específico. Si es básico y no está cargado, carga todos los básicos.
     * Si es no básico y no está cargado, lo carga individualmente.
     */
    private function obtenerCampo(string $nombreCampo): mixed
    {
        // Si el campo es básico y los básicos no se han cargado, cargarlos.
        if (in_array($nombreCampo, $this->camposBasicos) && !$this->datosBasicosCargados) {
            $this->cargarDatosBasicosUsuario();
        }

        // Si el campo ya está en el array (ya sea básico o cargado individualmente)
        if (array_key_exists($nombreCampo, $this->datosUsuario)) {
            return $this->datosUsuario[$nombreCampo];
        }

        // Si el campo no es básico y no está cargado, cargarlo individualmente
        // (Esto es para campos que NO están en $this->camposBasicos)
        if (!in_array($nombreCampo, $this->camposBasicos)) {
            $this->conectarDb();
            try {
                // Log::info("Cargando campo individual '{$nombreCampo}' para User ID: {$this->idUsuario}");
                $resultado = $this->db->selectColumns('tb_usuario', [$nombreCampo], 'id_usu = ? AND estado = 1', [$this->idUsuario]);
                if (!empty($resultado) && isset($resultado[0][$nombreCampo])) {
                    $this->datosUsuario[$nombreCampo] = $resultado[0][$nombreCampo];
                    return $this->datosUsuario[$nombreCampo];
                } else {
                    $this->datosUsuario[$nombreCampo] = null; // Cachear null
                    // if (empty($resultado)) throw new Exception("Usuario con ID {$this->idUsuario} no encontrado al buscar '{$nombreCampo}'.");
                    return null;
                }
            } catch (Exception $e) {
                Log::error("Error al cargar campo individual '{$nombreCampo}' para User {$this->idUsuario}: " . $e->getMessage());
                $this->datosUsuario[$nombreCampo] = null;
                return null;
            }
        }
        // Si es un campo básico pero por alguna razón no se cargó (ej. error previo), devolver null
        return null;
    }


    public function getId(): int
    {
        return $this->idUsuario;
    }

    // Métodos para campos básicos
    public function getNombre(): ?string
    {
        return $this->obtenerCampo('nombre');
    }

    public function getApellido(): ?string
    {
        return $this->obtenerCampo('apellido');
    }

    public function getNombreUsuario(): ?string
    {
        return $this->obtenerCampo('usu');
    }

    public function getEstado(): ?int
    {
        $valor = $this->obtenerCampo('estado');
        return $valor !== null ? (int)$valor : null;
    }

     public function isActivo(): bool
    {
        return $this->getEstado() === 1;
    }

    public function getIdAgencia(): ?int
    {
        $valor = $this->obtenerCampo('id_agencia');
        return $valor !== null ? (int)$valor : null;
    }

    public function getEmail(): ?string
    {
        return $this->obtenerCampo('Email');
    }

    public function getIdRol(): ?int
    {
        $valor = $this->obtenerCampo('id_rol');
        return $valor !== null ? (int)$valor : null;
    }

    // Métodos para campos que podrían ser no básicos (o sí, la lógica de obtenerCampo lo maneja)
    public function getNombreCompleto(): ?string
    {
        // Estos campos serán cargados por obtenerCampo si son básicos o individualmente si no lo son
        $nombre = $this->getNombre();
        $apellido = $this->getApellido();
        if ($nombre !== null && $apellido !== null) {
            return trim($nombre . ' ' . $apellido);
        }
        return null;
    }

    public function getDPI(): ?string // Ejemplo de campo que podría no ser básico
    {
        return $this->obtenerCampo('dpi');
    }

    public function getPuesto(): ?string // Ejemplo de campo que podría no ser básico
    {
        return $this->obtenerCampo('puesto');
    }

    public function getFechaExpiracion(): ?string // Ejemplo de campo que podría no ser básico
    {
        return $this->obtenerCampo('exp_date');
    }

    public function getProfileImage(): ?string // Ejemplo de campo que podría no ser básico
    {
        return $this->obtenerCampo('profile');
    }

    // Relación con Agencia
    public function __get(string $name)
    {
        if ($name === 'agencia') {
            if ($this->agenciaInstancia === null) {
                $idAgencia = $this->getIdAgencia(); // Esto usará obtenerCampo
                if ($idAgencia !== null) {
                    // Log::info("Creando instancia de Agencia para User {$this->idUsuario} con ID Agencia {$idAgencia}");
                    $this->agenciaInstancia = new Agencia($idAgencia);
                } else {
                    // Log::warning("User {$this->idUsuario} no tiene un id_agencia para cargar la relación Agencia.");
                    return null;
                }
            }
            return $this->agenciaInstancia;
        }
        // Log::warning("Acceso a propiedad mágica no definida: {$name} en User {$this->idUsuario}");
        return null;
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}