# Documentación de la Clase Departamento

## Descripción

La clase `Departamento` es un modelo que gestiona los datos de departamentos/estados en el sistema. Proporciona una interfaz para realizar operaciones CRUD con cache inteligente y relación automática con el país correspondiente. Puede ser utilizada tanto como objeto instanciado como a través de métodos estáticos, similar a Eloquent ORM.

## Namespace

```php
namespace Micro\Models;
```

## Dependencias

- `App\DatabaseAdapter` - Para conexiones a la base de datos
- `App\Generic\CacheManager` - Para gestión de cache
- `Micro\Helpers\Log` - Para logging de operaciones
- `Exception` - Para manejo de errores
- `Micro\Models\Pais` - Para la relación con países

## Estructura de la Tabla

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT(10) | Identificador único del departamento |
| nombre | VARCHAR(100) | Nombre completo del departamento |
| id_pais | INT(10) | ID del país al que pertenece (FK) |
| cod_crediref | VARCHAR(50) | Código de referencia crediticia |

## Propiedades

### Públicas
- `$id` - Identificador del departamento
- `$nombre` - Nombre del departamento
- `$id_pais` - ID del país relacionado
- `$cod_crediref` - Código Crediref

### Privadas
- `$db` - Instancia de DatabaseAdapter
- `static $cache` - Instancia compartida de CacheManager
- `$table` - Nombre de la tabla ('tb_departamentos')
- `$paisInstancia` - Instancia del país relacionado

## Constructor

```php
public function __construct(?int $id = null)
```

**Parámetros:**
- `$id` (opcional): ID del departamento a cargar

**Descripción:**
Inicializa la clase. Si se proporciona un ID, carga automáticamente los datos del departamento correspondiente.

**Ejemplo:**
```php
// Constructor vacío
$departamento = new Departamento();

// Constructor con ID
$departamento = new Departamento(5);
echo $departamento->getNombre(); // Muestra el nombre del departamento con ID 5
```

## Métodos Estáticos

### obtenerNombre(int $id): ?string

Obtiene el nombre de un departamento por su ID.

**Parámetros:**
- `$id`: ID del departamento

**Retorna:** 
- `string|null`: Nombre del departamento o null si no existe

**Ejemplo:**
```php
$nombre = Departamento::obtenerNombre(5);
echo $nombre; // "Antioquia"
```

### obtenerIdPais(int $id): ?int

Obtiene el ID del país de un departamento por su ID.

**Parámetros:**
- `$id`: ID del departamento

**Retorna:** 
- `int|null`: ID del país o null si no existe

**Ejemplo:**
```php
$idPais = Departamento::obtenerIdPais(5);
echo $idPais; // 1 (Colombia)
```

### obtenerCodigoCrediref(int $id): ?string

Obtiene el código Crediref de un departamento por su ID.

**Parámetros:**
- `$id`: ID del departamento

**Retorna:** 
- `string|null`: Código Crediref o null si no existe

**Ejemplo:**
```php
$codigo = Departamento::obtenerCodigoCrediref(5);
echo $codigo; // "05"
```

### obtenerTodos(): array

Obtiene todos los departamentos de la base de datos.

**Retorna:** 
- `array`: Array con todos los departamentos

**Cache:** 2 horas

**Ejemplo:**
```php
$departamentos = Departamento::obtenerTodos();
foreach ($departamentos as $departamento) {
    echo $departamento['nombre'] . "\n";
}
```

### obtenerPorPais(int $idPais): array

Obtiene todos los departamentos de un país específico.

**Parámetros:**
- `$idPais`: ID del país

**Retorna:** 
- `array`: Array con los departamentos del país

**Cache:** 2 horas

**Ejemplo:**
```php
$departamentosColombia = Departamento::obtenerPorPais(1);
foreach ($departamentosColombia as $departamento) {
    echo $departamento['nombre'] . "\n";
}
```

### buscarPorNombre(string $nombre): array

Busca departamentos por nombre usando LIKE.

**Parámetros:**
- `$nombre`: Texto a buscar en el nombre

**Retorna:** 
- `array`: Array con los departamentos encontrados

**Cache:** 30 minutos

**Ejemplo:**
```php
$departamentos = Departamento::buscarPorNombre("Anti");
// Retorna departamentos que contengan "Anti" en el nombre
```

### obtenerPorCodigoCrediref(string $codigo): ?array

Obtiene un departamento por su código Crediref.

**Parámetros:**
- `$codigo`: Código Crediref del departamento

**Retorna:** 
- `array|null`: Datos del departamento o null si no existe

**Ejemplo:**
```php
$departamento = Departamento::obtenerPorCodigoCrediref("05");
echo $departamento['nombre']; // "Antioquia"
```

### existe(int $id): bool

Verifica si existe un departamento con el ID especificado.

**Parámetros:**
- `$id`: ID del departamento

**Retorna:** 
- `bool`: true si existe, false si no

**Ejemplo:**
```php
if (Departamento::existe(5)) {
    echo "El departamento existe";
}
```

### obtenerParaSelect(?int $idPais = null): array

Obtiene un array asociativo optimizado para elementos select HTML.

**Parámetros:**
- `$idPais` (opcional): ID del país para filtrar

**Retorna:** 
- `array`: Array con formato [id => nombre]

**Ejemplo:**
```php
// Todos los departamentos
$opciones = Departamento::obtenerParaSelect();

// Solo departamentos de Colombia
$opcionesColombia = Departamento::obtenerParaSelect(1);

// Uso en HTML
foreach ($opciones as $id => $nombre) {
    echo "<option value='{$id}'>{$nombre}</option>";
}
```

### obtenerConPais(?int $idPais = null): array

Obtiene departamentos con información del país incluida (JOIN).

**Parámetros:**
- `$idPais` (opcional): ID del país para filtrar

**Retorna:** 
- `array`: Array con departamentos y datos del país

**Cache:** 2 horas

**Ejemplo:**
```php
$departamentosConPais = Departamento::obtenerConPais();
foreach ($departamentosConPais as $dep) {
    echo $dep['nombre'] . " - " . $dep['nombre_pais'] . "\n";
}
```

## Métodos de Cache

### limpiarCache(): bool

Limpia todo el cache relacionado con departamentos.

**Retorna:** 
- `bool`: true si se limpió correctamente

**Ejemplo:**
```php
if (Departamento::limpiarCache()) {
    echo "Cache limpiado exitosamente";
}
```

### invalidarCache(int $id): void

Invalida el cache de un departamento específico.

**Parámetros:**
- `$id`: ID del departamento cuyo cache se quiere invalidar

**Ejemplo:**
```php
Departamento::invalidarCache(5);
// Invalida el cache del departamento con ID 5
```

### invalidarCachePorPais(int $idPais): void

Invalida el cache de todos los departamentos de un país.

**Parámetros:**
- `$idPais`: ID del país cuyo cache se quiere invalidar

**Ejemplo:**
```php
Departamento::invalidarCachePorPais(1);
// Invalida el cache de todos los departamentos de Colombia
```

### obtenerEstadisticasCache(): array

Obtiene estadísticas del uso del cache.

**Retorna:** 
- `array`: Estadísticas del cache

**Ejemplo:**
```php
$stats = Departamento::obtenerEstadisticasCache();
echo "Hit rate: " . $stats['hit_rate'] . "%";
```

## Métodos de Instancia

### obtenerPorId(int $id): ?array

Obtiene los datos de un departamento por ID (método de instancia).

**Parámetros:**
- `$id`: ID del departamento

**Retorna:** 
- `array|null`: Datos del departamento o null si no existe

### Getters

#### getId(): ?int
Retorna el ID del departamento cargado.

#### getNombre(): ?string
Retorna el nombre del departamento cargado.

#### getIdPais(): ?int
Retorna el ID del país del departamento cargado.

#### getCodigoCrediref(): ?string
Retorna el código Crediref del departamento cargado.

### Métodos de Relación

#### getPais(): ?Pais
Obtiene la instancia del país relacionado.

**Ejemplo:**
```php
$departamento = new Departamento(5);
$pais = $departamento->getPais();
echo $pais->getNombre(); // "Colombia"
```

#### getNombrePais(): ?string
Obtiene directamente el nombre del país relacionado.

**Ejemplo:**
```php
$departamento = new Departamento(5);
echo $departamento->getNombrePais(); // "Colombia"
```

### Acceso Mágico al País (estilo Eloquent)

#### $departamento->pais
Acceso directo al país mediante propiedad mágica.

**Ejemplo:**
```php
$departamento = new Departamento(5);

// Acceso mágico (como Eloquent)
echo $departamento->pais->getNombre(); // "Colombia"
echo $departamento->pais->getCodigo(); // "CO"

// También funciona el método explícito
echo $departamento->getPais()->getNombre(); // "Colombia"
```

### Métodos de Conversión

#### toArray(bool $incluirPais = false): array
Convierte el objeto a array, opcionalmente incluyendo datos del país.

**Parámetros:**
- `$incluirPais`: Si incluir datos del país relacionado

**Ejemplo:**
```php
$departamento = new Departamento(5);

// Sin país
$array = $departamento->toArray();
// ['id' => 5, 'nombre' => 'Antioquia', 'id_pais' => 1, 'cod_crediref' => '05']

// Con país
$arrayCompleto = $departamento->toArray(true);
// Incluye: 'pais' => ['id' => 1, 'nombre' => 'Colombia', ...]
```

#### toJson(bool $incluirPais = false): string
Convierte el objeto a JSON, opcionalmente incluyendo datos del país.

**Ejemplo:**
```php
$departamento = new Departamento(5);
$json = $departamento->toJson(true); // Incluye datos del país
```

## Gestión de Cache

### Configuración
- **Prefijo:** `departamento_`
- **TTL por defecto:** 1 hora (3600 segundos)
- **TTL para listas:** 2 horas (7200 segundos)
- **TTL para búsquedas:** 30 minutos (1800 segundos)

### Claves de Cache
- `departamento_id_{id}` - Departamento individual por ID
- `departamento_todos` - Lista completa de departamentos
- `departamento_pais_{id_pais}` - Departamentos por país
- `departamento_codigo_{codigo}` - Departamento por código
- `departamento_buscar_{hash}` - Resultados de búsqueda
- `departamento_con_pais_{id_pais}` - Departamentos con datos de país

## Logging

La clase registra las siguientes operaciones:
- Conexiones exitosas y fallidas a la base de datos
- Aciertos y fallos del cache
- Errores en consultas
- Operaciones de invalidación de cache
- Creación de instancias de países relacionados

## Manejo de Errores

- **Exception**: Se lanza cuando no se puede conectar a la base de datos
- **Exception**: Se lanza cuando un departamento no se encuentra por ID en el constructor
- Los métodos estáticos retornan `null` en lugar de lanzar excepciones
- Los accesos a propiedades mágicas no definidas retornan `null` y se loggean

## Ejemplos de Uso Completos

### Uso Básico con Instancia
```php
try {
    $departamento = new Departamento(5);
    echo "Departamento: " . $departamento->getNombre();
    echo "País: " . $departamento->pais->getNombre(); // Acceso mágico
    echo "Código: " . $departamento->getCodigoCrediref();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Uso con Métodos Estáticos
```php
// Obtener información específica
$nombre = Departamento::obtenerNombre(5);
$idPais = Departamento::obtenerIdPais(5);

// Búsquedas y filtros
$departamentosColombia = Departamento::obtenerPorPais(1);
$departamentos = Departamento::buscarPorNombre("Antioquia");
$departamento = Departamento::obtenerPorCodigoCrediref("05");

// Para formularios
$opcionesColombia = Departamento::obtenerParaSelect(1);
$todasOpciones = Departamento::obtenerParaSelect();
```

### Trabajando con Relaciones
```php
$departamento = new Departamento(5);

// Diferentes formas de acceder al país
echo $departamento->pais->getNombre();           // Acceso mágico
echo $departamento->getPais()->getNombre();      // Método explícito
echo $departamento->getNombrePais();             // Método directo

// Convertir a array con relación
$datosCompletos = $departamento->toArray(true);
```

### Casos de Uso Avanzados
```php
// Departamentos con información del país (JOIN)
$departamentosConPais = Departamento::obtenerConPais();
foreach ($departamentosConPais as $dep) {
    echo "{$dep['nombre']} ({$dep['codigo_pais']}) - {$dep['nombre_pais']}\n";
}

// Gestión de cache por país
Departamento::invalidarCachePorPais(1); // Invalida cache de Colombia
$stats = Departamento::obtenerEstadisticasCache();

// Select dinámico por país
$paisSeleccionado = 1; // Colombia
$departamentosSelect = Departamento::obtenerParaSelect($paisSeleccionado);
```

## Consideraciones de Rendimiento

1. **Cache Inteligente**: Todos los métodos utilizan cache para evitar consultas repetitivas
2. **Cache por País**: Sistemas específicos para cachear departamentos por país
3. **Lazy Loading**: La instancia del país se carga solo cuando se accede
4. **Invalidación Inteligente**: Al invalidar un departamento, también se invalida el cache del país
5. **JOIN Optimizado**: Métodos específicos para obtener datos con relaciones en una sola consulta

## Relaciones

### Con País (Belongs To)
- Un departamento pertenece a un país
- Acceso mediante `$departamento->pais`
- Lazy loading automático
- Cache independiente para cada relación

## Notas Adicionales

- La clase es compatible con el patrón Eloquent para relaciones
- Todos los métodos son compatibles con valores null
- Los códigos se convierten automáticamente a mayúsculas
- La búsqueda por nombre es case-insensitive
- La invalidación de cache es inteligente y en cascada
- Se recomienda usar métodos estáticos para consultas puntuales
- Para operaciones múltiples sobre el mismo departamento, usar instancias
- El acceso mágico a propiedades se loggea para debugging
- La conexión a BD se cierra automáticamente en el destructor