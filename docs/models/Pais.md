# Documentación de la Clase Pais

## Descripción

La clase `Pais` es un modelo que gestiona los datos de países en el sistema. Proporciona una interfaz para realizar operaciones CRUD con cache inteligente para optimizar el rendimiento. Puede ser utilizada tanto como objeto instanciado como a través de métodos estáticos.

## Namespace

```php
namespace Micro\Models;
```

## Dependencias

- `App\DatabaseAdapter` - Para conexiones a la base de datos
- `App\Generic\CacheManager` - Para gestión de cache
- `Micro\Helpers\Log` - Para logging de operaciones
- `Exception` - Para manejo de errores

## Estructura de la Tabla

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT(10) | Identificador único del país |
| nombre | VARCHAR(100) | Nombre completo del país |
| codigo | VARCHAR(10) | Código del país |
| abreviatura | VARCHAR(10) | Abreviatura del país |

## Propiedades

### Públicas
- `$id` - Identificador del país
- `$nombre` - Nombre del país
- `$codigo` - Código del país
- `$abreviatura` - Abreviatura del país

### Privadas
- `$db` - Instancia de DatabaseAdapter
- `static $cache` - Instancia compartida de CacheManager

## Constructor

```php
public function __construct(?int $id = null)
```

**Parámetros:**
- `$id` (opcional): ID del país a cargar

**Descripción:**
Inicializa la clase. Si se proporciona un ID, carga automáticamente los datos del país correspondiente.

**Ejemplo:**
```php
// Constructor vacío
$pais = new Pais();

// Constructor con ID
$pais = new Pais(5);
echo $pais->getNombre(); // Muestra el nombre del país con ID 5
```

## Métodos Estáticos

### obtenerNombre(int $id): ?string

Obtiene el nombre de un país por su ID.

**Parámetros:**
- `$id`: ID del país

**Retorna:** 
- `string|null`: Nombre del país o null si no existe

**Ejemplo:**
```php
$nombre = Pais::obtenerNombre(5);
echo $nombre; // "Colombia"
```

### obtenerCodigo(int $id): ?string

Obtiene el código de un país por su ID.

**Parámetros:**
- `$id`: ID del país

**Retorna:** 
- `string|null`: Código del país o null si no existe

**Ejemplo:**
```php
$codigo = Pais::obtenerCodigo(5);
echo $codigo; // "CO"
```

### obtenerAbreviatura(int $id): ?string

Obtiene la abreviatura de un país por su ID.

**Parámetros:**
- `$id`: ID del país

**Retorna:** 
- `string|null`: Abreviatura del país o null si no existe

**Ejemplo:**
```php
$abrev = Pais::obtenerAbreviatura(5);
echo $abrev; // "COL"
```

### obtenerTodos(): array

Obtiene todos los países de la base de datos.

**Retorna:** 
- `array`: Array con todos los países

**Cache:** 2 horas

**Ejemplo:**
```php
$paises = Pais::obtenerTodos();
foreach ($paises as $pais) {
    echo $pais['nombre'] . "\n";
}
```

### buscarPorNombre(string $nombre): array

Busca países por nombre usando LIKE.

**Parámetros:**
- `$nombre`: Texto a buscar en el nombre

**Retorna:** 
- `array`: Array con los países encontrados

**Cache:** 30 minutos

**Ejemplo:**
```php
$paises = Pais::buscarPorNombre("Unidos");
// Retorna países que contengan "Unidos" en el nombre
```

### obtenerPorCodigo(string $codigo): ?array

Obtiene un país por su código.

**Parámetros:**
- `$codigo`: Código del país

**Retorna:** 
- `array|null`: Datos del país o null si no existe

**Ejemplo:**
```php
$pais = Pais::obtenerPorCodigo("CO");
echo $pais['nombre']; // "Colombia"
```

### existe(int $id): bool

Verifica si existe un país con el ID especificado.

**Parámetros:**
- `$id`: ID del país

**Retorna:** 
- `bool`: true si existe, false si no

**Ejemplo:**
```php
if (Pais::existe(5)) {
    echo "El país existe";
}
```

### obtenerParaSelect(): array

Obtiene un array asociativo optimizado para elementos select HTML.

**Retorna:** 
- `array`: Array con formato [id => nombre]

**Ejemplo:**
```php
$opciones = Pais::obtenerParaSelect();
// [1 => "Argentina", 2 => "Brasil", ...]

// Uso en HTML
foreach ($opciones as $id => $nombre) {
    echo "<option value='{$id}'>{$nombre}</option>";
}
```

## Métodos de Cache

### limpiarCache(): bool

Limpia todo el cache relacionado con países.

**Retorna:** 
- `bool`: true si se limpió correctamente

**Ejemplo:**
```php
if (Pais::limpiarCache()) {
    echo "Cache limpiado exitosamente";
}
```

### invalidarCache(int $id): void

Invalida el cache de un país específico.

**Parámetros:**
- `$id`: ID del país cuyo cache se quiere invalidar

**Ejemplo:**
```php
Pais::invalidarCache(5);
// Invalida el cache del país con ID 5
```

### obtenerEstadisticasCache(): array

Obtiene estadísticas del uso del cache.

**Retorna:** 
- `array`: Estadísticas del cache

**Ejemplo:**
```php
$stats = Pais::obtenerEstadisticasCache();
echo "Hit rate: " . $stats['hit_rate'] . "%";
```

## Métodos de Instancia

### obtenerPorId(int $id): ?array

Obtiene los datos de un país por ID (método de instancia).

**Parámetros:**
- `$id`: ID del país

**Retorna:** 
- `array|null`: Datos del país o null si no existe

### Getters

#### getId(): ?int
Retorna el ID del país cargado.

#### getNombre(): ?string
Retorna el nombre del país cargado.

#### getCodigo(): ?string
Retorna el código del país cargado.

#### getAbreviatura(): ?string
Retorna la abreviatura del país cargado.

### Métodos de Conversión

#### toArray(): array
Convierte el objeto a array.

**Ejemplo:**
```php
$pais = new Pais(5);
$array = $pais->toArray();
// ['id' => 5, 'nombre' => 'Colombia', 'codigo' => 'CO', 'abreviatura' => 'COL']
```

#### toJson(): string
Convierte el objeto a JSON.

**Ejemplo:**
```php
$pais = new Pais(5);
$json = $pais->toJson();
// '{"id":5,"nombre":"Colombia","codigo":"CO","abreviatura":"COL"}'
```

## Gestión de Cache

### Configuración
- **Prefijo:** `pais_`
- **TTL por defecto:** 1 hora (3600 segundos)
- **TTL para listas:** 2 horas (7200 segundos)
- **TTL para búsquedas:** 30 minutos (1800 segundos)

### Claves de Cache
- `pais_id_{id}` - País individual por ID
- `pais_todos` - Lista completa de países
- `pais_codigo_{codigo}` - País por código
- `pais_buscar_{hash}` - Resultados de búsqueda

## Logging

La clase registra las siguientes operaciones:
- Conexiones exitosas y fallidas a la base de datos
- Aciertos y fallos del cache
- Errores en consultas
- Operaciones de invalidación de cache

## Manejo de Errores

- **Exception**: Se lanza cuando no se puede conectar a la base de datos
- **Exception**: Se lanza cuando un país no se encuentra por ID en el constructor
- Los métodos estáticos retornan `null` en lugar de lanzar excepciones

## Ejemplos de Uso Completos

### Uso Básico con Instancia
```php
try {
    $pais = new Pais(5);
    echo "País: " . $pais->getNombre();
    echo "Código: " . $pais->getCodigo();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Uso con Métodos Estáticos
```php
// Obtener información específica
$nombre = Pais::obtenerNombre(5);
$existe = Pais::existe(5);

// Búsquedas
$paises = Pais::buscarPorNombre("America");
$pais = Pais::obtenerPorCodigo("US");

// Para formularios
$opciones = Pais::obtenerParaSelect();
```

### Gestión de Cache
```php
// Ver estadísticas
$stats = Pais::obtenerEstadisticasCache();
echo "Cache habilitado: " . ($stats['enabled'] ? 'Sí' : 'No');

// Limpiar cache específico
Pais::invalidarCache(5);

// Limpiar todo el cache
Pais::limpiarCache();
```

## Consideraciones de Rendimiento

1. **Cache Inteligente**: Todos los métodos utilizan cache para evitar consultas repetitivas
2. **Instancia Compartida**: El cache es compartido entre todas las instancias
3. **TTL Diferenciado**: Diferentes tipos de consultas tienen diferentes tiempos de vida
4. **Invalidación Selectiva**: Posibilidad de invalidar cache específico o completo

## Notas Adicionales

- La clase es thread-safe para el cache compartido
- Todos los métodos son compatibles con valores null
- Los códigos de país se convierten automáticamente a mayúsculas
- La búsqueda por nombre es case-insensitive
- Se recomienda usar métodos estáticos para consultas puntuales y instancias para operaciones múltiples sobre el mismo país