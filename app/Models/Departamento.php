<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use App\Generic\CacheManager;
use Micro\Helpers\Log;
use Exception;

class Departamento
{
    private $db;
    private static $cache;
    private $table = 'tb_departamentos';

    // Propiedades del departamento
    public $id;
    public $nombre;
    public $id_pais;
    public $cod_crediref;

    // Instancia del país relacionado
    private ?Pais $paisInstancia = null;

    /**
     * Constructor - puede inicializarse con ID o vacío
     * Valida y convierte el ID a entero antes de cargar
     */
    public function __construct($id = null)
    {
        // Inicializar cache si no existe
        if (self::$cache === null) {
            self::$cache = new CacheManager('departamento_', 3600);
        }

        if ($id !== null) {
            // Validar y convertir a entero
            $idValidado = filter_var($id, FILTER_VALIDATE_INT);

            if ($idValidado === false || $idValidado <= 0) {
                Log::warning("ID de departamento inválido proporcionado al constructor", [
                    'id_recibido' => $id,
                    'tipo' => gettype($id)
                ]);
                throw new Exception("ID de departamento debe ser un entero positivo");
            }

            $this->cargarPorId($idValidado);
        }
    }

    /**
     * Conecta a la base de datos
     */
    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection();
            } catch (Exception $e) {
                Log::error("Error al conectar BD : " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
                throw new Exception("Error crítico al conectar BD.", 0, $e);
            }
        }
    }

    /**
     * Carga los datos del departamento por ID
     */
    private function cargarPorId(int $id): void
    {
        $datos = $this->obtenerPorId($id);
        if ($datos) {
            $this->id = $datos['id'];
            $this->nombre = $datos['nombre'];
            $this->id_pais = $datos['id_pais'];
            $this->cod_crediref = $datos['cod_crediref'];
        } else {
            throw new Exception("Departamento con ID {$id} no encontrado");
            // Log::warning("Departamento con ID {$id} no encontrado");
            // $this->id = null;
            // $this->nombre = null;
            // $this->id_pais = null;
            // $this->cod_crediref = null;
        }
    }

    /**
     * Obtiene un departamento por ID (método de instancia)
     */
    public function obtenerPorId($id): ?array
    {
        // Intentar obtener del cache primero
        $cacheKey = "id_{$id}";
        $departamento = self::$cache->get($cacheKey);

        if ($departamento !== null) {
            // Log::debug("Departamento obtenido del cache", ['id' => $id]);
            return $departamento;
        }

        // Si no está en cache, consultar BD
        $this->conectarDb();

        try {
            $departamento = $this->db->selectById($this->table, $id);

            if ($departamento) {
                // Guardar en cache
                self::$cache->set($cacheKey, $departamento);
                // Log::debug("Departamento consultado en BD y guardado en cache", ['id' => $id]);
            }

            return $departamento ?: null;
        } catch (Exception $e) {
            Log::error("Error al obtener departamento por ID: " . $e->getMessage(), ['id' => $id]);
            throw $e;
        }
    }

    /**
     * Obtiene el nombre de un departamento por ID (método estático)
     */
    public static function obtenerNombre($id): ?string
    {
        $instance = new self();
        $departamento = $instance->obtenerPorId($id);
        return $departamento ? $departamento['nombre'] : null;
    }

    /**
     * Obtiene el ID del país de un departamento por ID (método estático)
     */
    public static function obtenerIdPais(int $id): ?int
    {
        $instance = new self();
        $departamento = $instance->obtenerPorId($id);
        return $departamento ? (int)$departamento['id_pais'] : null;
    }

    /**
     * Obtiene el código crediref de un departamento por ID (método estático)
     */
    public static function obtenerCodigoCrediref(int $id): ?string
    {
        $instance = new self();
        $departamento = $instance->obtenerPorId($id);
        return $departamento ? $departamento['cod_crediref'] : null;
    }

    /**
     * Obtiene todos los departamentos con cache
     */
    public static function obtenerTodos(): array
    {
        $instance = new self();

        // Intentar obtener del cache
        $cacheKey = "todos";
        $departamentos = self::$cache->get($cacheKey);

        if ($departamentos !== null) {
            // Log::debug("Lista de departamentos obtenida del cache");
            return $departamentos;
        }

        // Si no está en cache, consultar BD
        $instance->conectarDb();

        try {
            $departamentos = $instance->db->selectAll($instance->table);

            // Guardar en cache
            self::$cache->set($cacheKey, $departamentos, 7200); // Cache por 2 horas para listas
            // Log::debug("Lista de departamentos consultada en BD y guardada en cache");

            return $departamentos;
        } catch (Exception $e) {
            Log::error("Error al obtener todos los departamentos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene departamentos por país
     */
    public static function obtenerPorPais(int $idPais): array
    {
        $instance = new self();

        $cacheKey = "pais_{$idPais}";
        $departamentos = self::$cache->get($cacheKey);

        if ($departamentos !== null) {
            // Log::debug("Departamentos por país obtenidos del cache", ['id_pais' => $idPais]);
            return $departamentos;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} WHERE id_pais = :id_pais ORDER BY nombre";
            $params = ['id_pais' => $idPais];
            $departamentos = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $departamentos, 7200);
            // Log::debug("Departamentos por país consultados en BD y guardados en cache", ['id_pais' => $idPais]);

            return $departamentos;
        } catch (Exception $e) {
            Log::error("Error al obtener departamentos por país: " . $e->getMessage(), ['id_pais' => $idPais]);
            throw $e;
        }
    }

    /**
     * Busca departamentos por nombre (método estático)
     */
    public static function buscarPorNombre(string $nombre): array
    {
        $instance = new self();
        $instance->conectarDb();

        $cacheKey = "buscar_" . md5(strtolower($nombre));
        $resultado = self::$cache->get($cacheKey);

        if ($resultado !== null) {
            return $resultado;
        }

        try {
            $query = "SELECT * FROM {$instance->table} WHERE nombre LIKE :nombre ORDER BY nombre";
            $params = ['nombre' => "%{$nombre}%"];
            $resultado = $instance->db->selectNom($query, $params);

            // Cache por 30 minutos para búsquedas
            self::$cache->set($cacheKey, $resultado, 1800);

            return $resultado;
        } catch (Exception $e) {
            Log::error("Error al buscar departamentos por nombre: " . $e->getMessage(), ['nombre' => $nombre]);
            throw $e;
        }
    }

    /**
     * Busca un departamento por código crediref (método estático)
     */
    public static function obtenerPorCodigoCrediref(string $codigo): ?array
    {
        $instance = new self();

        $cacheKey = "codigo_" . strtoupper($codigo);
        $departamento = self::$cache->get($cacheKey);

        if ($departamento !== null) {
            return $departamento;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} WHERE cod_crediref = :codigo LIMIT 1";
            $params = ['codigo' => strtoupper($codigo)];
            $resultado = $instance->db->selectNom($query, $params);
            $departamento = !empty($resultado) ? $resultado[0] : null;

            if ($departamento) {
                self::$cache->set($cacheKey, $departamento);
            }

            return $departamento;
        } catch (Exception $e) {
            Log::error("Error al obtener departamento por código: " . $e->getMessage(), ['codigo' => $codigo]);
            throw $e;
        }
    }

    /**
     * Limpia el cache de departamentos
     */
    public static function limpiarCache(): bool
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('departamento_', 3600);
        }

        return self::$cache->clear();
    }

    /**
     * Invalida el cache de un departamento específico
     */
    public static function invalidarCache(int $id): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('departamento_', 3600);
        }

        self::$cache->delete("id_{$id}");
        self::$cache->delete("todos"); // También invalida la lista completa

        // Invalidar también cache por país si es posible
        $instance = new self();
        $departamento = $instance->obtenerPorId($id);
        if ($departamento && isset($departamento['id_pais'])) {
            self::$cache->delete("pais_{$departamento['id_pais']}");
        }
    }

    /**
     * Invalida el cache de departamentos por país
     */
    public static function invalidarCachePorPais(int $idPais): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('departamento_', 3600);
        }

        self::$cache->delete("pais_{$idPais}");
        self::$cache->delete("todos");
    }

    /**
     * Obtiene estadísticas del cache
     */
    public static function obtenerEstadisticasCache(): array
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('departamento_', 3600);
        }

        return self::$cache->getStats();
    }

    /**
     * Valida si existe un departamento por ID
     */
    public static function existe(int $id): bool
    {
        $instance = new self();
        $departamento = $instance->obtenerPorId($id);
        return $departamento !== null;
    }

    /**
     * Obtiene un array asociativo para selects HTML
     */
    public static function obtenerParaSelect(?int $idPais = null): array
    {
        if ($idPais !== null) {
            $departamentos = self::obtenerPorPais($idPais);
        } else {
            $departamentos = self::obtenerTodos();
        }

        $opciones = [];

        foreach ($departamentos as $departamento) {
            $opciones[$departamento['id']] = $departamento['nombre'];
        }

        return $opciones;
    }

    /**
     * Obtiene departamentos con información del país (JOIN)
     */
    public static function obtenerConPais(?int $idPais = null): array
    {
        $instance = new self();
        $instance->conectarDb();

        $cacheKey = $idPais ? "con_pais_{$idPais}" : "con_pais_todos";
        $resultado = self::$cache->get($cacheKey);

        if ($resultado !== null) {
            return $resultado;
        }

        try {
            $query = "SELECT d.*, p.nombre as nombre_pais, p.codigo as codigo_pais, p.abreviatura as abreviatura_pais 
                     FROM {$instance->table} d 
                     INNER JOIN tb_paises p ON d.id_pais = p.id";

            $params = [];
            if ($idPais !== null) {
                $query .= " WHERE d.id_pais = :id_pais";
                $params['id_pais'] = $idPais;
            }

            $query .= " ORDER BY p.nombre, d.nombre";

            $resultado = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $resultado, 7200);

            return $resultado;
        } catch (Exception $e) {
            Log::error("Error al obtener departamentos con país: " . $e->getMessage(), ['id_pais' => $idPais]);
            throw $e;
        }
    }

    /**
     * Getters para las propiedades del objeto
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function getIdPais(): ?int
    {
        return $this->id_pais;
    }

    public function getCodigoCrediref(): ?string
    {
        return $this->cod_crediref;
    }

    /**
     * Propiedad mágica para acceder al país relacionado (similar a Eloquent)
     */
    public function __get(string $name)
    {
        if ($name === 'pais') {
            if ($this->paisInstancia === null && $this->id_pais !== null) {
                // Log::debug("Creando instancia de Pais para Departamento {$this->id} con ID Pais {$this->id_pais}");
                $this->paisInstancia = new Pais($this->id_pais);
            }
            return $this->paisInstancia;
        }

        Log::warning("Acceso a propiedad mágica no definida: {$name} en Departamento {$this->id}");
        return null;
    }

    /**
     * Método para acceder al país de forma explícita
     */
    public function getPais(): ?Pais
    {
        return $this->__get('pais');
    }

    /**
     * Obtiene el nombre del país relacionado
     */
    public function getNombrePais(): ?string
    {
        $pais = $this->getPais();
        return $pais ? $pais->getNombre() : null;
    }

    /**
     * Convierte el objeto a array
     */
    public function toArray(bool $incluirPais = false): array
    {
        $array = [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'id_pais' => $this->id_pais,
            'cod_crediref' => $this->cod_crediref
        ];

        if ($incluirPais && $this->id_pais !== null) {
            $pais = $this->getPais();
            $array['pais'] = $pais ? $pais->toArray() : null;
        }

        return $array;
    }

    /**
     * Convierte el objeto a JSON
     */
    public function toJson(bool $incluirPais = false): string
    {
        return json_encode($this->toArray($incluirPais), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Destructor para cerrar conexión
     */
    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}
