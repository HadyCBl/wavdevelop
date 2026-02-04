<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use App\Generic\CacheManager;
use Micro\Helpers\Log;
use Exception;

class Pais
{
    private $db;
    private static $cache;
    private $table = 'tb_paises';
    
    // Propiedades del país
    public $id;
    public $nombre;
    public $codigo;
    public $abreviatura;
    
    /**
     * Constructor - puede inicializarse con ID o vacío
     */
    public function __construct(?int $id = null)
    {
        // Inicializar cache si no existe
        if (self::$cache === null) {
            self::$cache = new CacheManager('pais_', 3600); // Cache por 1 hora
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
     * Carga los datos del país por ID
     */
    private function cargarPorId(int $id): void
    {
        $datos = $this->obtenerPorId($id);
        if ($datos) {
            $this->id = $datos['id'];
            $this->nombre = $datos['nombre'];
            $this->codigo = $datos['codigo'];
            $this->abreviatura = $datos['abreviatura'];
        } else {
            throw new Exception("País con ID {$id} no encontrado");
        }
    }

    /**
     * Obtiene un país por ID (método de instancia)
     */
    public function obtenerPorId(int $id): ?array
    {
        // Intentar obtener del cache primero
        $cacheKey = "id_{$id}";
        $pais = self::$cache->get($cacheKey);
        
        if ($pais !== null) {
            Log::debug("País obtenido del cache", ['id' => $id]);
            return $pais;
        }

        // Si no está en cache, consultar BD
        $this->conectarDb();
        
        try {
            $pais = $this->db->selectById($this->table, $id);
            
            if ($pais) {
                // Guardar en cache
                self::$cache->set($cacheKey, $pais);
                Log::debug("País consultado en BD y guardado en cache", ['id' => $id]);
            }
            
            return $pais ?: null;
        } catch (Exception $e) {
            Log::error("Error al obtener país por ID: " . $e->getMessage(), ['id' => $id]);
            throw $e;
        }
    }

    /**
     * Obtiene el país completo por ID (método estático)
     */
    public static function getPaisCompleto(int $id): ?array
    {
        $instance = new self();
        return $instance->obtenerPorId($id);
    }

    /**
     * Obtiene el nombre de un país por ID (método estático)
     */
    public static function obtenerNombre(int $id): ?string
    {
        $instance = new self();
        $pais = $instance->obtenerPorId($id);
        return $pais ? $pais['nombre'] : null;
    }

    /**
     * Obtiene el código de un país por ID (método estático)
     */
    public static function obtenerCodigo(int $id): ?string
    {
        $instance = new self();
        $pais = $instance->obtenerPorId($id);
        return $pais ? $pais['codigo'] : null;
    }

    /**
     * Obtiene la abreviatura de un país por ID (método estático)
     */
    public static function obtenerAbreviatura(int $id): ?string
    {
        $instance = new self();
        $pais = $instance->obtenerPorId($id);
        return $pais ? $pais['abreviatura'] : null;
    }

    /**
     * Obtiene todos los países con cache
     */
    public static function obtenerTodos(): array
    {
        $instance = new self();
        
        // Intentar obtener del cache
        $cacheKey = "todos";
        $paises = self::$cache->get($cacheKey);
        
        if ($paises !== null) {
            Log::debug("Lista de países obtenida del cache");
            return $paises;
        }

        // Si no está en cache, consultar BD
        $instance->conectarDb();
        
        try {
            $paises = $instance->db->selectAll($instance->table);
            
            // Guardar en cache
            self::$cache->set($cacheKey, $paises, 7200); // Cache por 2 horas para listas
            Log::debug("Lista de países consultada en BD y guardada en cache");
            
            return $paises;
        } catch (Exception $e) {
            Log::error("Error al obtener todos los países: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Busca países por nombre (método estático)
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
            Log::error("Error al buscar países por nombre: " . $e->getMessage(), ['nombre' => $nombre]);
            throw $e;
        }
    }

    /**
     * Busca un país por código (método estático)
     */
    public static function obtenerPorCodigo(string $codigo): ?array
    {
        $instance = new self();
        
        $cacheKey = "codigo_" . strtoupper($codigo);
        $pais = self::$cache->get($cacheKey);
        
        if ($pais !== null) {
            return $pais;
        }

        $instance->conectarDb();
        
        try {
            $query = "SELECT * FROM {$instance->table} WHERE abreviatura = :codigo LIMIT 1";
            $params = ['codigo' => strtoupper($codigo)];
            $resultado = $instance->db->selectNom($query, $params);
            $pais = !empty($resultado) ? $resultado[0] : null;
            
            if ($pais) {
                self::$cache->set($cacheKey, $pais);
            }
            
            return $pais;
        } catch (Exception $e) {
            Log::error("Error al obtener país por código: " . $e->getMessage(), ['codigo' => $codigo]);
            throw $e;
        }
    }

    /**
     * Limpia el cache de países
     */
    public static function limpiarCache(): bool
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('pais_', 3600);
        }
        
        return self::$cache->clear();
    }

    /**
     * Invalida el cache de un país específico
     */
    public static function invalidarCache(int $id): void
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('pais_', 3600);
        }
        
        self::$cache->delete("id_{$id}");
        self::$cache->delete("todos"); // También invalida la lista completa
    }

    /**
     * Obtiene estadísticas del cache
     */
    public static function obtenerEstadisticasCache(): array
    {
        if (self::$cache === null) {
            self::$cache = new CacheManager('pais_', 3600);
        }
        
        return self::$cache->getStats();
    }

    /**
     * Valida si existe un país por ID
     */
    public static function existe(int $id): bool
    {
        $instance = new self();
        $pais = $instance->obtenerPorId($id);
        return $pais !== null;
    }

    /**
     * Obtiene un array asociativo para selects HTML
     */
    public static function obtenerParaSelect(): array
    {
        $paises = self::obtenerTodos();
        $opciones = [];
        
        foreach ($paises as $pais) {
            $opciones[$pais['id']] = $pais['nombre'];
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

    public function getAbreviatura(): ?string
    {
        return $this->abreviatura;
    }

    /**
     * Convierte el objeto a array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'codigo' => $this->codigo,
            'abreviatura' => $this->abreviatura
        ];
    }

    /**
     * Convierte el objeto a JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_UNICODE);
    }
}