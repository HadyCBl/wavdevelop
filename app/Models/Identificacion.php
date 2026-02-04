<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use App\Generic\CacheManager;
use Micro\Helpers\Log;
use Exception;

class Identificacion
{
    private $db;
    private static $cache;
    private $table = 'tb_identificaciones';

    // Propiedades del tipo de identificación
    public $id;
    public $codigo;
    public $nombre;
    public $id_pais;
    public $mascara_regex;
    public $formato_display;

    // Instancia de relación
    private ?Pais $paisInstancia = null;

    /**
     * Constructor - puede inicializarse con ID o vacío
     */
    public function __construct(?int $id = null)
    {
        // Inicializar cache si no existe
        if (self::$cache === null) {
            self::$cache = new CacheManager('identificacion_', 3600); // Cache por 1 hora
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
     * Carga los datos del tipo de identificación por ID
     */
    private function cargarPorId(int $id): void
    {
        $datos = $this->obtenerPorId($id);
        if ($datos) {
            $this->id = $datos['id'];
            $this->codigo = $datos['codigo'];
            $this->nombre = $datos['nombre'];
            $this->id_pais = $datos['id_pais'];
            $this->mascara_regex = $datos['mascara_regex'];
            $this->formato_display = $datos['formato_display'];
        } else {
            throw new Exception("Tipo de identificación con ID {$id} no encontrado");
        }
    }

    /**
     * Obtiene un tipo de identificación por ID (método de instancia)
     */
    public function obtenerPorId(int $id): ?array
    {
        // Intentar obtener del cache primero
        $cacheKey = "id_{$id}";
        $identificacion = self::$cache->get($cacheKey);

        if ($identificacion !== null) {
            Log::debug("Tipo de identificación obtenido del cache", ['id' => $id]);
            return $identificacion;
        }

        // Si no está en cache, consultar BD
        $this->conectarDb();

        try {
            $identificacion = $this->db->selectById($this->table, $id);

            if ($identificacion) {
                // Guardar en cache
                self::$cache->set($cacheKey, $identificacion);
                Log::debug("Tipo de identificación consultado en BD y guardado en cache", ['id' => $id]);
            }

            return $identificacion ?: null;
        } catch (Exception $e) {
            Log::error("Error al obtener tipo de identificación por ID: " . $e->getMessage(), ['id' => $id]);
            throw $e;
        }
    }

    /**
     * Obtiene el nombre de un tipo de identificación por ID (método estático)
     */
    public static function obtenerNombre(int $id): ?string
    {
        $instance = new self();
        $identificacion = $instance->obtenerPorId($id);
        return $identificacion ? $identificacion['nombre'] : null;
    }

    /**
     * Obtiene el código de un tipo de identificación por ID (método estático)
     */
    public static function obtenerCodigo(int $id): ?string
    {
        $instance = new self();
        $identificacion = $instance->obtenerPorId($id);
        return $identificacion ? $identificacion['codigo'] : null;
    }

    /**
     * Obtiene la máscara regex de un tipo de identificación por ID (método estático)
     */
    public static function obtenerMascaraRegex(int $id): ?string
    {
        $instance = new self();
        $identificacion = $instance->obtenerPorId($id);
        return $identificacion ? $identificacion['mascara_regex'] : null;
    }

    /**
     * Obtiene el formato de display de un tipo de identificación por ID (método estático)
     */
    public static function obtenerFormatoDisplay(int $id): ?string
    {
        $instance = new self();
        $identificacion = $instance->obtenerPorId($id);
        return $identificacion ? $identificacion['formato_display'] : null;
    }

    /**
     * Obtiene el ID del país de un tipo de identificación por ID (método estático)
     */
    public static function obtenerIdPais(int $id): ?int
    {
        $instance = new self();
        $identificacion = $instance->obtenerPorId($id);
        return $identificacion ? (int)$identificacion['id_pais'] : null;
    }

    /**
     * Obtiene todos los tipos de identificación con cache
     */
    public static function obtenerTodos(): array
    {
        $instance = new self();

        // Intentar obtener del cache
        $cacheKey = "todos";
        $identificaciones = self::$cache->get($cacheKey);

        if ($identificaciones !== null) {
            Log::debug("Lista de tipos de identificación obtenida del cache");
            return $identificaciones;
        }

        // Si no está en cache, consultar BD
        $instance->conectarDb();

        try {
            $identificaciones = $instance->db->selectAll($instance->table, 'nombre ASC');

            // Guardar en cache
            self::$cache->set($cacheKey, $identificaciones, 7200); // Cache por 2 horas para listas
            Log::debug("Lista de tipos de identificación consultada en BD y guardada en cache");

            return $identificaciones;
        } catch (Exception $e) {
            Log::error("Error al obtener todos los tipos de identificación: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Obtiene tipos de identificación por país
     */
    public static function obtenerPorPais(int $idPais): array
    {
        $instance = new self();

        $cacheKey = "pais_{$idPais}";
        $identificaciones = self::$cache->get($cacheKey);

        if ($identificaciones !== null) {
            Log::debug("Tipos de identificación por país obtenidos del cache", ['id_pais' => $idPais]);
            return $identificaciones;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} WHERE id_pais = :id_pais ORDER BY nombre";
            $params = ['id_pais' => $idPais];
            $identificaciones = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $identificaciones, 7200);
            Log::debug("Tipos de identificación por país consultados en BD y guardados en cache", ['id_pais' => $idPais]);

            return $identificaciones;
        } catch (Exception $e) {
            Log::error("Error al obtener tipos de identificación por país: " . $e->getMessage(), ['id_pais' => $idPais]);
            throw $e;
        }
    }

    /**
     * Obtiene tipos de identificación por país incluyendo los que no tienen país asignado (NULL)
     */
    public static function obtenerPorPaisConGlobales(int $idPais): array
    {
        $instance = new self();

        $cacheKey = "pais_con_globales_{$idPais}";
        $identificaciones = self::$cache->get($cacheKey);

        if ($identificaciones !== null) {
            Log::debug("Tipos de identificación por país (con globales) obtenidos del cache", ['id_pais' => $idPais]);
            return $identificaciones;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} 
                     WHERE id_pais = :id_pais OR id_pais IS NULL 
                     ORDER BY 
                         CASE WHEN id_pais IS NULL THEN 1 ELSE 0 END,
                         nombre";
            $params = ['id_pais' => $idPais];
            $identificaciones = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $identificaciones, 7200);
            Log::debug("Tipos de identificación por país (con globales) consultados en BD y guardados en cache", ['id_pais' => $idPais]);

            return $identificaciones;
        } catch (Exception $e) {
            Log::error("Error al obtener tipos de identificación por país con globales: " . $e->getMessage(), ['id_pais' => $idPais]);
            throw $e;
        }
    }

    /**
     * Obtiene tipos de identificación que no tienen país asignado (id_pais IS NULL)
     */
    public static function obtenerGlobales(): array
    {
        $instance = new self();

        $cacheKey = "globales";
        $identificaciones = self::$cache->get($cacheKey);

        if ($identificaciones !== null) {
            Log::debug("Tipos de identificación globales obtenidos del cache");
            return $identificaciones;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} 
                     WHERE id_pais IS NULL 
                     ORDER BY nombre";
            $identificaciones = $instance->db->selectNom($query, []);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $identificaciones, 7200);
            Log::debug("Tipos de identificación globales consultados en BD y guardados en cache");

            return $identificaciones;
        } catch (Exception $e) {
            Log::error("Error al obtener tipos de identificación globales: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca un tipo de identificación por código (método estático)
     */
    public static function obtenerPorCodigo(string $codigo): ?array
    {
        $instance = new self();

        $cacheKey = "codigo_" . strtoupper($codigo);
        $identificacion = self::$cache->get($cacheKey);

        if ($identificacion !== null) {
            return $identificacion;
        }

        $instance->conectarDb();

        try {
            $query = "SELECT * FROM {$instance->table} WHERE codigo = :codigo LIMIT 1";
            $params = ['codigo' => strtoupper($codigo)];
            $resultado = $instance->db->selectNom($query, $params);
            $identificacion = !empty($resultado) ? $resultado[0] : null;

            if ($identificacion) {
                self::$cache->set($cacheKey, $identificacion);
            }

            return $identificacion;
        } catch (Exception $e) {
            Log::error("Error al obtener tipo de identificación por código: " . $e->getMessage(), ['codigo' => $codigo]);
            throw $e;
        }
    }

    /**
     * Obtiene nombre de tipo de identificación por código
     */
    public static function obtenerNombrePorCodigo(string $codigo): ?string
    {
        $identificacion = self::obtenerPorCodigo($codigo);
        return $identificacion ? $identificacion['nombre'] : null;
    }

    /**
     * Busca tipos de identificación por nombre (método estático)
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
            Log::error("Error al buscar tipos de identificación por nombre: " . $e->getMessage(), ['nombre' => $nombre]);
            throw $e;
        }
    }

    /**
     * Obtiene tipos de identificación con información completa (JOIN con país)
     */
    public static function obtenerConCompletos(?int $idPais = null): array
    {
        $instance = new self();
        $instance->conectarDb();

        $cacheKey = "completos";
        if ($idPais) $cacheKey .= "_pais_{$idPais}";

        $resultado = self::$cache->get($cacheKey);

        if ($resultado !== null) {
            return $resultado;
        }

        try {
            $query = "SELECT i.*, 
                            p.nombre as nombre_pais, 
                            p.codigo as codigo_pais, 
                            p.abreviatura as abreviatura_pais
                     FROM {$instance->table} i 
                     INNER JOIN tb_paises p ON i.id_pais = p.id";

            $params = [];

            if ($idPais !== null) {
                $query .= " WHERE p.id = :id_pais";
                $params['id_pais'] = $idPais;
            }

            $query .= " ORDER BY p.nombre, i.nombre";

            $resultado = $instance->db->selectNom($query, $params);

            // Cache por 2 horas
            self::$cache->set($cacheKey, $resultado, 7200);

            return $resultado;
        } catch (Exception $e) {
            Log::error("Error al obtener tipos de identificación completos: " . $e->getMessage(), ['id_pais' => $idPais]);
            throw $e;
        }
    }

    /**
     * Valida un número de identificación usando la máscara regex
     */
    public function validarNumero(string $numero): bool
    {
        if (empty($this->mascara_regex)) {
            return true; // Si no hay regex, se considera válido
        }

        return preg_match($this->mascara_regex, $numero) === 1;
        // return preg_match('/' . $this->mascara_regex . '/', $numero) === 1;
    }

    /**
     * Valida un número de identificación por ID del tipo (método estático)
     */
    public static function validarNumeroPorId(int $idTipo, string $numero): bool
    {
        $instance = new self($idTipo);
        return $instance->validarNumero($numero);
    }

    /**
     * Formatea un número de identificación usando el formato display
     */
    public function formatearNumero(string $numero): string
    {
        if (empty($this->formato_display)) {
            return $numero; // Si no hay formato, retornar tal como está
        }

        // Implementar lógica de formateo basada en formato_display
        // Esto dependerá del formato específico que uses
        return $numero;
    }

    /**
     * Formatea un número de identificación por ID del tipo (método estático)
     */
    public static function formatearNumeroPorId(int $idTipo, string $numero): string
    {
        $instance = new self($idTipo);
        return $instance->formatearNumero($numero);
    }

    /**
     * Limpia el cache de tipos de identificación
     */
    public static function limpiarCache(): bool
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('identificacion_', 3600);
        }

        return self::$cache->clear();
    }

    /**
     * Invalida el cache de un tipo de identificación específico
     */
    public static function invalidarCache(int $id): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('identificacion_', 3600);
        }

        self::$cache->delete("id_{$id}");
        self::$cache->delete("todos");

        // Invalidar también cache por país si es posible
        $instance = new self();
        $identificacion = $instance->obtenerPorId($id);
        if ($identificacion && isset($identificacion['id_pais'])) {
            $idPais = $identificacion['id_pais'];
            self::$cache->delete("pais_{$idPais}");
            self::$cache->delete("completos_pais_{$idPais}");
        }
    }

    /**
     * Invalida el cache de tipos de identificación por país
     */
    public static function invalidarCachePorPais(int $idPais): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('identificacion_', 3600);
        }

        self::$cache->delete("pais_{$idPais}");
        self::$cache->delete("pais_con_globales_{$idPais}");
        self::$cache->delete("completos_pais_{$idPais}");
        self::$cache->delete("globales");
        self::$cache->delete("todos");
    }

    /**
     * Invalida el cache de tipos de identificación globales
     */
    public static function invalidarCacheGlobales(): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('identificacion_', 3600);
        }

        self::$cache->delete("globales");
        self::$cache->delete("todos");

        // También invalidar todos los cache "con_globales" existentes
        // Esto es una aproximación, en producción podrías mantener una lista de países activos
        for ($i = 1; $i <= 300; $i++) { // Asumiendo máximo 300 países
            self::$cache->delete("pais_con_globales_{$i}");
        }
    }

    /**
     * Obtiene estadísticas del cache
     */
    public static function obtenerEstadisticasCache(): array
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('identificacion_', 3600);
        }

        return self::$cache->getStats();
    }

    /**
     * Valida si existe un tipo de identificación por ID
     */
    public static function existe(int $id): bool
    {
        $instance = new self();
        $identificacion = $instance->obtenerPorId($id);
        return $identificacion !== null;
    }

    /**
     * Obtiene un array asociativo para selects HTML
     */
    public static function obtenerParaSelect(?int $idPais = null, bool $incluirGlobales = false): array
    {
        if ($idPais !== null) {
            if ($incluirGlobales) {
                $identificaciones = self::obtenerPorPaisConGlobales($idPais);
            } else {
                $identificaciones = self::obtenerPorPais($idPais);
            }
        } else {
            $identificaciones = self::obtenerTodos();
        }

        $opciones = [];

        foreach ($identificaciones as $identificacion) {
            $opciones[$identificacion['id']] = $identificacion['nombre'];
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

    public function getCodigo(): ?string
    {
        return $this->codigo;
    }

    public function getNombre(): ?string
    {
        return $this->nombre;
    }

    public function getIdPais(): ?int
    {
        return $this->id_pais;
    }

    public function getMascaraRegex(): ?string
    {
        return $this->mascara_regex;
    }

    public function getFormatoDisplay(): ?string
    {
        return $this->formato_display;
    }

    /**
     * Propiedades mágicas para acceder a las relaciones (similar a Eloquent)
     */
    public function __get(string $name)
    {
        if ($name === 'pais') {
            if ($this->paisInstancia === null && $this->id_pais !== null) {
                Log::debug("Creando instancia de Pais para Identificacion {$this->id} con ID Pais {$this->id_pais}");
                $this->paisInstancia = new Pais($this->id_pais);
            }
            return $this->paisInstancia;
        }

        Log::warning("Acceso a propiedad mágica no definida: {$name} en Identificacion {$this->id}");
        return null;
    }

    /**
     * Método para acceder a la relación de forma explícita
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
    public function toArray(bool $incluirRelaciones = false): array
    {
        $array = [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'nombre' => $this->nombre,
            'id_pais' => $this->id_pais,
            'mascara_regex' => $this->mascara_regex,
            'formato_display' => $this->formato_display
        ];

        if ($incluirRelaciones && $this->id_pais !== null) {
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
