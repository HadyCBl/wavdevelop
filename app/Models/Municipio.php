<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use App\Generic\CacheManager;
use Micro\Helpers\Log;
use Exception;

class Municipio
{
    private $db;
    private static $cache;
    private $table = 'tb_municipios';

    // Propiedades del municipio
    public $id;
    public $nombre;
    public $codigo;
    public $cod_crediref;
    public $id_departamento;

    // Instancias de relaciones
    private ?Departamento $departamentoInstancia = null;
    private ?Pais $paisInstancia = null;

    /**
     * Constructor - puede inicializarse con ID o vacío
     */
    public function __construct(?int $id = null)
    {
        // Inicializar cache si no existe
        if (self::$cache === null) {
            self::$cache = new CacheManager('municipio_', 3600); // Cache por 1 hora
        }

        if ($id !== null) {
            $this->cargarPorId($id);
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
     * Carga los datos del municipio por ID
     */
    private function cargarPorId(int $id): void
    {
        $datos = $this->obtenerPorId($id);
        if ($datos) {
            $this->id = $datos['id'];
            $this->nombre = $datos['nombre'];
            $this->codigo = $datos['codigo'];
            $this->cod_crediref = $datos['cod_crediref'];
            $this->id_departamento = $datos['id_departamento'];
        } else {
            throw new Exception("Municipio con ID {$id} no encontrado");
        }
    }

    /**
     * Obtiene un municipio por ID (método de instancia)
     */
    public function obtenerPorId(int $id): ?array
    {
        // Intentar obtener del cache primero
        $cacheKey = "id_{$id}";
        $municipio = self::$cache->get($cacheKey);

        if ($municipio !== null) {
            Log::debug("Municipio obtenido del cache", ['id' => $id]);
            return $municipio;
        }

        // Si no está en cache, consultar BD
        $this->conectarDb();

        try {
            $municipio = $this->db->selectById($this->table, $id);

            if ($municipio) {
                // Guardar en cache
                self::$cache->set($cacheKey, $municipio);
                Log::debug("Municipio consultado en BD y guardado en cache", ['id' => $id]);
            }

            return $municipio ?: null;
        } catch (Exception $e) {
            Log::error("Error al obtener municipio por ID: " . $e->getMessage(), ['id' => $id]);
            throw $e;
        }
    }

    /**
     * Obtiene el nombre de un municipio por ID (método estático)
     */
    public static function obtenerNombre(int $id): ?string
    {
        $instance = new self();
        $municipio = $instance->obtenerPorId($id);
        return $municipio ? $municipio['nombre'] : null;
    }

    /**
     * Obtiene el código de un municipio por ID (método estático)
     */
    public static function obtenerCodigo(int $id): ?string
    {
        $instance = new self();
        $municipio = $instance->obtenerPorId($id);
        return $municipio ? $municipio['codigo'] : null;
    }

    /**
     * Obtiene el código crediref de un municipio por ID (método estático)
     */
    public static function obtenerCodigoCrediref(int $id): ?string
    {
        $instance = new self();
        $municipio = $instance->obtenerPorId($id);
        return $municipio ? $municipio['cod_crediref'] : null;
    }

    /**
     * Obtiene el ID del departamento de un municipio por ID (método estático)
     */
    public static function obtenerIdDepartamento(int $id): ?int
    {
        $instance = new self();
        $municipio = $instance->obtenerPorId($id);
        return $municipio ? (int)$municipio['id_departamento'] : null;
    }

    /**
     * Obtiene todos los municipios con cache
     */
    public static function obtenerTodos(): array
    {
        $instance = new self();

        // Intentar obtener del cache
        $cacheKey = "todos";
        $municipios = self::$cache->get($cacheKey);

        if ($municipios !== null) {
            Log::debug("Lista de municipios obtenida del cache");
            return $municipios;
        }

        // Si no está en cache, consultar BD
        $instance->conectarDb();

        try {
            $municipios = $instance->db->selectAll($instance->table);

            // Guardar en cache
            self::$cache->set($cacheKey, $municipios, 7200); // Cache por 2 horas para listas
            Log::debug("Lista de municipios consultada en BD y guardada en cache");

            return $municipios;
        } catch (Exception $e) {
            Log::error("Error al obtener todos los municipios: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene municipios por departamento
     */
    public static function obtenerPorDepartamento(int $idDepartamento): array
    {
        $instance = new self();

        $cacheKey = "departamento_{$idDepartamento}";
        $municipios = self::$cache->get($cacheKey);

        if ($municipios !== null) {
            Log::debug("Municipios por departamento obtenidos del cache", ['id_departamento' => $idDepartamento]);
            return $municipios;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} WHERE id_departamento = :id_departamento ORDER BY nombre";
            $params = ['id_departamento' => $idDepartamento];
            $municipios = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $municipios, 7200);
            Log::debug("Municipios por departamento consultados en BD y guardados en cache", ['id_departamento' => $idDepartamento]);

            return $municipios;
        } catch (Exception $e) {
            Log::error("Error al obtener municipios por departamento: " . $e->getMessage(), ['id_departamento' => $idDepartamento]);
            throw $e;
        }
    }

    /**
     * Obtiene municipios por país (a través del departamento)
     */
    public static function obtenerPorPais(int $idPais): array
    {
        $instance = new self();

        $cacheKey = "pais_{$idPais}";
        $municipios = self::$cache->get($cacheKey);

        if ($municipios !== null) {
            Log::debug("Municipios por país obtenidos del cache", ['id_pais' => $idPais]);
            return $municipios;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT m.* FROM {$instance->table} m 
                     INNER JOIN tb_departamentos d ON m.id_departamento = d.id 
                     WHERE d.id_pais = :id_pais 
                     ORDER BY d.nombre, m.nombre";
            $params = ['id_pais' => $idPais];
            $municipios = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $municipios, 7200);
            Log::debug("Municipios por país consultados en BD y guardados en cache", ['id_pais' => $idPais]);

            return $municipios;
        } catch (Exception $e) {
            Log::error("Error al obtener municipios por país: " . $e->getMessage(), ['id_pais' => $idPais]);
            throw $e;
        }
    }

    /**
     * Busca municipios por nombre (método estático)
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
            Log::error("Error al buscar municipios por nombre: " . $e->getMessage(), ['nombre' => $nombre]);
            throw $e;
        }
    }

    /**
     * Busca un municipio por código (método estático)
     */
    public static function obtenerPorCodigo(string $codigo): ?array
    {
        $instance = new self();

        $cacheKey = "codigo_" . strtoupper($codigo);
        $municipio = self::$cache->get($cacheKey);

        if ($municipio !== null) {
            return $municipio;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} WHERE codigo = :codigo LIMIT 1";
            $params = ['codigo' => strtoupper($codigo)];
            $resultado = $instance->db->selectNom($query, $params);
            $municipio = !empty($resultado) ? $resultado[0] : null;

            if ($municipio) {
                self::$cache->set($cacheKey, $municipio);
            }

            return $municipio;
        } catch (Exception $e) {
            Log::error("Error al obtener municipio por código: " . $e->getMessage(), ['codigo' => $codigo]);
            throw $e;
        }
    }

    /**
     * obtener nombre de municipio por codigo
     */
    public static function obtenerNombrePorCodigo(string $codigo): ?string
    {
        $municipio = self::obtenerPorCodigo($codigo);
        return $municipio ? $municipio['nombre'] : null;
    }

    /**
     * Busca un municipio por código crediref (método estático)
     */
    public static function obtenerPorCodigoCrediref(string $codigo): ?array
    {
        $instance = new self();

        $cacheKey = "crediref_" . strtoupper($codigo);
        $municipio = self::$cache->get($cacheKey);

        if ($municipio !== null) {
            return $municipio;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} WHERE cod_crediref = :codigo LIMIT 1";
            $params = ['codigo' => strtoupper($codigo)];
            $resultado = $instance->db->selectNom($query, $params);
            $municipio = !empty($resultado) ? $resultado[0] : null;

            if ($municipio) {
                self::$cache->set($cacheKey, $municipio);
            }

            return $municipio;
        } catch (Exception $e) {
            Log::error("Error al obtener municipio por código crediref: " . $e->getMessage(), ['codigo' => $codigo]);
            throw $e;
        }
    }

    /**
     * Obtiene municipios con información completa (JOIN con departamento y país)
     */
    public static function obtenerConCompletos(?int $idPais = null, ?int $idDepartamento = null): array
    {
        $instance = new self();
        $instance->conectarDb();

        $cacheKey = "completos";
        if ($idPais) $cacheKey .= "_pais_{$idPais}";
        if ($idDepartamento) $cacheKey .= "_dept_{$idDepartamento}";

        $resultado = self::$cache->get($cacheKey);

        if ($resultado !== null) {
            return $resultado;
        }

        try {
            $query = "SELECT m.*, 
                            d.nombre as nombre_departamento, 
                            d.cod_credireferir as cod_departamento,
                            p.nombre as nombre_pais, 
                            p.codigo as codigo_pais, 
                            p.abreviatura as abreviatura_pais
                     FROM {$instance->table} m 
                     INNER JOIN tb_departamentos d ON m.id_departamento = d.id 
                     INNER JOIN tb_paises p ON d.id_pais = p.id";

            $params = [];
            $conditions = [];

            if ($idPais !== null) {
                $conditions[] = "p.id = :id_pais";
                $params['id_pais'] = $idPais;
            }

            if ($idDepartamento !== null) {
                $conditions[] = "d.id = :id_departamento";
                $params['id_departamento'] = $idDepartamento;
            }

            if (!empty($conditions)) {
                $query .= " WHERE " . implode(" AND ", $conditions);
            }

            $query .= " ORDER BY p.nombre, d.nombre, m.nombre";

            $resultado = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $resultado, 7200);

            return $resultado;
        } catch (Exception $e) {
            Log::error("Error al obtener municipios completos: " . $e->getMessage(), ['id_pais' => $idPais, 'id_departamento' => $idDepartamento]);
            throw $e;
        }
    }

    /**
     * Limpia el cache de municipios
     */
    public static function limpiarCache(): bool
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('municipio_', 3600);
        }

        return self::$cache->clear();
    }

    /**
     * Invalida el cache de un municipio específico
     */
    public static function invalidarCache(int $id): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('municipio_', 3600);
        }

        self::$cache->delete("id_{$id}");
        self::$cache->delete("todos");

        // Invalidar también cache por departamento si es posible
        $instance = new self();
        $municipio = $instance->obtenerPorId($id);
        if ($municipio && isset($municipio['id_departamento'])) {
            $idDepartamento = $municipio['id_departamento'];
            self::$cache->delete("departamento_{$idDepartamento}");

            // También invalidar por país usando método estático
            $idPais = Departamento::obtenerIdPais($idDepartamento);
            if ($idPais) {
                self::$cache->delete("pais_{$idPais}");
            }
        }
    }

    /**
     * Invalida el cache de municipios por departamento
     */
    public static function invalidarCachePorDepartamento(int $idDepartamento): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('municipio_', 3600);
        }

        self::$cache->delete("departamento_{$idDepartamento}");
        self::$cache->delete("todos");

        // También invalidar por país usando método estático
        $idPais = Departamento::obtenerIdPais($idDepartamento);
        if ($idPais) {
            self::$cache->delete("pais_{$idPais}");
        }
    }

    /**
     * Invalida el cache de municipios por país
     */
    public static function invalidarCachePorPais(int $idPais): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('municipio_', 3600);
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
            self::$cache = new CacheManager('municipio_', 3600);
        }

        return self::$cache->getStats();
    }

    /**
     * Valida si existe un municipio por ID
     */
    public static function existe(int $id): bool
    {
        $instance = new self();
        $municipio = $instance->obtenerPorId($id);
        return $municipio !== null;
    }

    /**
     * Obtiene un array asociativo para selects HTML
     */
    public static function obtenerParaSelect(?int $idDepartamento = null, ?int $idPais = null): array
    {
        if ($idDepartamento !== null) {
            $municipios = self::obtenerPorDepartamento($idDepartamento);
        } elseif ($idPais !== null) {
            $municipios = self::obtenerPorPais($idPais);
        } else {
            $municipios = self::obtenerTodos();
        }

        $opciones = [];

        foreach ($municipios as $municipio) {
            $opciones[$municipio['id']] = $municipio['nombre'];
        }

        return $opciones;
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

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function getCodigoCrediref(): ?string
    {
        return $this->cod_crediref;
    }

    public function getIdDepartamento(): ?int
    {
        return $this->id_departamento;
    }

    /**
     * Propiedades mágicas para acceder a las relaciones (similar a Eloquent)
     */
    public function __get(string $name)
    {
        if ($name === 'departamento') {
            if ($this->departamentoInstancia === null && $this->id_departamento !== null) {
                Log::debug("Creando instancia de Departamento para Municipio {$this->id} con ID Departamento {$this->id_departamento}");
                $this->departamentoInstancia = new Departamento($this->id_departamento);
            }
            return $this->departamentoInstancia;
        }

        if ($name === 'pais') {
            if ($this->paisInstancia === null && $this->id_departamento !== null) {
                $departamento = $this->getDepartamento();
                if ($departamento && $departamento->getIdPais()) {
                    Log::debug("Creando instancia de Pais para Municipio {$this->id} a través del Departamento");
                    $this->paisInstancia = new Pais($departamento->getIdPais());
                }
            }
            return $this->paisInstancia;
        }

        Log::warning("Acceso a propiedad mágica no definida: {$name} en Municipio {$this->id}");
        return null;
    }

    /**
     * Métodos para acceder a las relaciones de forma explícita
     */
    public function getDepartamento(): ?Departamento
    {
        return $this->__get('departamento');
    }

    public function getPais(): ?Pais
    {
        return $this->__get('pais');
    }

    /**
     * Obtiene el nombre del departamento relacionado
     */
    public function getNombreDepartamento(): ?string
    {
        $departamento = $this->getDepartamento();
        return $departamento ? $departamento->getNombre() : null;
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
    public function toArray(bool $incluirRelaciones = false): array
    {
        $array = [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'cod_crediref' => $this->cod_crediref,
            'id_departamento' => $this->id_departamento
        ];

        if ($incluirRelaciones && $this->id_departamento !== null) {
            $departamento = $this->getDepartamento();
            $array['departamento'] = $departamento ? $departamento->toArray() : null;

            $pais = $this->getPais();
            $array['pais'] = $pais ? $pais->toArray() : null;
        }

        return $array;
    }

    /**
     * Convierte el objeto a JSON
     */
    public function toJson(bool $incluirRelaciones = false): string
    {
        return json_encode($this->toArray($incluirRelaciones), JSON_UNESCAPED_UNICODE);
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
