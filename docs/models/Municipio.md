# Documentación de la Clase Municipio

## Descripción

La clase `Municipio` es un modelo que gestiona los datos de municipios/ciudades en el sistema. Proporciona una interfaz para realizar operaciones CRUD con cache inteligente y relaciones automáticas con departamento y país correspondientes. Puede ser utilizada tanto como objeto instanciado como a través de métodos estáticos, similar a Eloquent ORM.

## Namespace

```php
namespace Micro\Models;
```

## Dependencias

- `App\DatabaseAdapter` - Para conexiones a la base de datos
- `App\Generic\CacheManager` - Para gestión de cache
- `Micro\Helpers\Log` - Para logging de operaciones
- `Exception` - Para manejo de errores
- `Micro\Models\Departamento` - Para la relación con departamentos
- `Micro\Models\Pais` - Para la relación con países

## Estructura de la Tabla

| Campo | Tipo | Descripción |
|-------|------|-------------|
| id | INT(10) | Identificador único del municipio |
| nombre | VARCHAR(100) | Nombre completo del municipio |
| codigo | VARCHAR(50) | Código del municipio |
| cod_crediref | VARCHAR(50) | Código de referencia crediticia |
| id_departamento | INT(10) | ID del departamento al que pertenece (FK) |

## Propiedades

### Públicas
- `$id` - Identificador del municipio
- `$nombre` - Nombre del municipio
- `$codigo` - Código del municipio
- `$cod_crediref` - Código crediref
- `$id_departamento` - ID del departamento relacionado

### Privadas
- `$db` - Instancia de DatabaseAdapter
- `static $cache` - Instancia compartida de CacheManager
- `$table` - Nombre de la tabla ('tb_municipios')
- `$departamentoInstancia` - Instancia del departamento relacionado
- `$paisInstancia` - Instancia del país relacionado

## Constructor

```php
public function __construct(?int $id = null)
```

**Parámetros:**
- `$id` (opcional): ID del municipio a cargar

**Descripción:**
Inicializa la clase. Si se proporciona un ID, carga automáticamente los datos del municipio correspondiente.

**Ejemplo:**
```php
// Constructor vacío
$municipio = new Municipio();

// Constructor con ID
$municipio = new Municipio(5);
echo $municipio->getNombre(); // Muestra el nombre del municipio con ID 5
```

## Métodos Estáticos

### obtenerNombre(int $id): ?string

Obtiene el nombre de un municipio por su ID.

**Parámetros:**
- `$id`: ID del municipio

**Retorna:** 
- `string|null`: Nombre del municipio o null si no existe

**Ejemplo:**
```php
$nombre = Municipio::obtenerNombre(5);
echo $nombre; // "Medellín"
```

### obtenerCodigo(int $id): ?string

Obtiene el código de un municipio por su ID.

**Parámetros:**
- `$id`: ID del municipio

**Retorna:** 
- `string|null`: Código del municipio o null si no existe

**Ejemplo:**
```php
$codigo = Municipio::obtenerCodigo(5);
echo $codigo; // "05001"
```

### obtenerCodigoCrediref(int $id): ?string

Obtiene el código crediref de un municipio por su ID.

**Parámetros:**
- `$id`: ID del municipio

**Retorna:** 
- `string|null`: Código crediref o null si no existe

**Ejemplo:**
```php
$codigo = Municipio::obtenerCodigoCrediref(5);
echo $codigo; // "05001"
```

### obtenerIdDepartamento(int $id): ?int

Obtiene el ID del departamento de un municipio por su ID.

**Parámetros:**
- `$id`: ID del municipio

**Retorna:** 
- `int|null`: ID del departamento o null si no existe

**Ejemplo:**
```php
$idDepartamento = Municipio::obtenerIdDepartamento(5);
echo $idDepartamento; // 5 (Antioquia)
```

### obtenerTodos(): array

Obtiene todos los municipios de la base de datos.

**Retorna:** 
- `array`: Array con todos los municipios

**Cache:** 2 horas

**Ejemplo:**
```php
$municipios = Municipio::obtenerTodos();
foreach ($municipios as $municipio) {
    echo $municipio['nombre'] . "\n";
}
```

### obtenerPorDepartamento(int $idDepartamento): array

Obtiene todos los municipios de un departamento específico.

**Parámetros:**
- `$idDepartamento`: ID del departamento

**Retorna:** 
- `array`: Array con los municipios del departamento

**Cache:** 2 horas

**Ejemplo:**
```php
$municipiosAntioquia = Municipio::obtenerPorDepartamento(5);
foreach ($municipiosAntioquia as $municipio) {
    echo $municipio['nombre'] . "\n";
}
```

### obtenerPorPais(int $idPais): array

Obtiene todos los municipios de un país específico (a través del departamento).

**Parámetros:**
- `$idPais`: ID del país

**Retorna:** 
- `array`: Array con los municipios del país

**Cache:** 2 horas

**Ejemplo:**
```php
$municipiosColombia = Municipio::obtenerPorPais(1);
foreach ($municipiosColombia as $municipio) {
    echo $municipio['nombre'] . "\n";
}
```

### buscarPorNombre(string $nombre): array

Busca municipios por nombre usando LIKE.

**Parámetros:**
- `$nombre`: Texto a buscar en el nombre

**Retorna:** 
- `array`: Array con los municipios encontrados

**Cache:** 30 minutos

**Ejemplo:**
```php
$municipios = Municipio::buscarPorNombre("Medel");
// Retorna municipios que contengan "Medel" en el nombre
```

### obtenerPorCodigo(string $codigo): ?array

Obtiene un municipio por su código.

**Parámetros:**
- `$codigo`: Código del municipio

**Retorna:** 
- `array|null`: Datos del municipio o null si no existe

**Ejemplo:**
```php
$municipio = Municipio::obtenerPorCodigo("05001");
echo $municipio['nombre']; // "Medellín"
```

### obtenerNombrePorCodigo(string $codigo): ?string

Obtiene el nombre de un municipio a partir de su código.

**Parámetros:**
- `$codigo`: Código único del municipio.

**Retorna:** 
- `string|null`: Nombre del municipio si existe, o null si no se encuentra.

**Ejemplo:**
```php
$nombre = Municipio::obtenerNombrePorCodigo("05001");
echo $nombre; // "Medellín"
```

### obtenerPorCodigoCrediref(string $codigo): ?array

Obtiene un municipio por su código crediref.

**Parámetros:**
- `$codigo`: Código crediref del municipio

**Retorna:** 
- `array|null`: Datos del municipio o null si no existe

**Ejemplo:**
```php
$municipio = Municipio::obtenerPorCodigoCrediref("05001");
echo $municipio['nombre']; // "Medellín"
```

### obtenerConCompletos(?int $idPais = null, ?int $idDepartamento = null): array

Obtiene municipios con información completa del departamento y país (JOIN).

**Parámetros:**
- `$idPais` (opcional): ID del país para filtrar
- `$idDepartamento` (opcional): ID del departamento para filtrar

**Retorna:** 
- `array`: Array con municipios y datos relacionados

**Cache:** 2 horas

**Ejemplo:**
```php
// Todos los municipios con información completa
$municipiosCompletos = Municipio::obtenerConCompletos();

// Solo municipios de Colombia
$municipiosColombia = Municipio::obtenerConCompletos(1);

// Solo municipios de Antioquia
$municipiosAntioquia = Municipio::obtenerConCompletos(null, 5);

// Municipios de Antioquia, Colombia
$municipiosEspecificos = Municipio::obtenerConCompletos(1, 5);

foreach ($municipiosCompletos as $mun) {
    echo "{$mun['nombre']} - {$mun['nombre_departamento']} - {$mun['nombre_pais']}\n";
}
```

### existe(int $id): bool

Verifica si existe un municipio con el ID especificado.

**Parámetros:**
- `$id`: ID del municipio

**Retorna:** 
- `bool`: true si existe, false si no

**Ejemplo:**
```php
if (Municipio::existe(5)) {
    echo "El municipio existe";
}
```

### obtenerParaSelect(?int $idDepartamento = null, ?int $idPais = null): array

Obtiene un array asociativo optimizado para elementos select HTML.

**Parámetros:**
- `$idDepartamento` (opcional): ID del departamento para filtrar
- `$idPais` (opcional): ID del país para filtrar

**Retorna:** 
- `array`: Array con formato [id => nombre]

**Ejemplo:**
```php
// Todos los municipios
$opciones = Municipio::obtenerParaSelect();

// Solo municipios de Antioquia
$opcionesAntioquia = Municipio::obtenerParaSelect(5);

// Solo municipios de Colombia
$opcionesColombia = Municipio::obtenerParaSelect(null, 1);

// Uso en HTML
foreach ($opciones as $id => $nombre) {
    echo "<option value='{$id}'>{$nombre}</option>";
}
```

## Métodos de Cache

### limpiarCache(): bool

Limpia todo el cache relacionado con municipios.

**Retorna:** 
- `bool`: true si se limpió correctamente

**Ejemplo:**
```php
if (Municipio::limpiarCache()) {
    echo "Cache limpiado exitosamente";
}
```

### invalidarCache(int $id): void

Invalida el cache de un municipio específico y relacionados.

**Parámetros:**
- `$id`: ID del municipio cuyo cache se quiere invalidar

**Descripción:**
Invalida el cache del municipio específico, la lista general, el cache por departamento y por país.

**Ejemplo:**
```php
Municipio::invalidarCache(5);
// Invalida el cache del municipio con ID 5 y todos los relacionados
```

### invalidarCachePorDepartamento(int $idDepartamento): void

Invalida el cache de todos los municipios de un departamento.

**Parámetros:**
- `$idDepartamento`: ID del departamento cuyo cache se quiere invalidar

**Ejemplo:**
```php
Municipio::invalidarCachePorDepartamento(5);
// Invalida el cache de todos los municipios de Antioquia
```

### invalidarCachePorPais(int $idPais): void

Invalida el cache de todos los municipios de un país.

**Parámetros:**
- `$idPais`: ID del país cuyo cache se quiere invalidar

**Ejemplo:**
```php
Municipio::invalidarCachePorPais(1);
// Invalida el cache de todos los municipios de Colombia
```

### obtenerEstadisticasCache(): array

Obtiene estadísticas del uso del cache.

**Retorna:** 
- `array`: Estadísticas del cache

**Ejemplo:**
```php
$stats = Municipio::obtenerEstadisticasCache();
echo "Hit rate: " . $stats['hit_rate'] . "%";
```

## Métodos de Instancia

### obtenerPorId(int $id): ?array

Obtiene los datos de un municipio por ID (método de instancia).

**Parámetros:**
- `$id`: ID del municipio

**Retorna:** 
- `array|null`: Datos del municipio o null si no existe

### Getters

#### getId(): ?int
Retorna el ID del municipio cargado.

#### getNombre(): ?string
Retorna el nombre del municipio cargado.

#### getCodigo(): ?string
Retorna el código del municipio cargado.

#### getCodigoCrediref(): ?string
Retorna el código crediref del municipio cargado.

#### getIdDepartamento(): ?int
Retorna el ID del departamento del municipio cargado.

### Métodos de Relación

#### getDepartamento(): ?Departamento
Obtiene la instancia del departamento relacionado.

**Ejemplo:**
```php
$municipio = new Municipio(5);
$departamento = $municipio->getDepartamento();
echo $departamento->getNombre(); // "Antioquia"
```

#### getPais(): ?Pais
Obtiene la instancia del país relacionado (a través del departamento).

**Ejemplo:**
```php
$municipio = new Municipio(5);
$pais = $municipio->getPais();
echo $pais->getNombre(); // "Colombia"
```

#### getNombreDepartamento(): ?string
Obtiene directamente el nombre del departamento relacionado.

**Ejemplo:**
```php
$municipio = new Municipio(5);
echo $municipio->getNombreDepartamento(); // "Antioquia"
```

#### getNombrePais(): ?string
Obtiene directamente el nombre del país relacionado.

**Ejemplo:**
```php
$municipio = new Municipio(5);
echo $municipio->getNombrePais(); // "Colombia"
```

### Acceso Mágico a Relaciones (estilo Eloquent)

#### $municipio->departamento
Acceso directo al departamento mediante propiedad mágica.

#### $municipio->pais
Acceso directo al país mediante propiedad mágica.

**Ejemplo:**
```php
$municipio = new Municipio(5);

// Acceso mágico (como Eloquent)
echo $municipio->departamento->getNombre(); // "Antioquia"
echo $municipio->pais->getNombre(); // "Colombia"
echo $municipio->pais->getCodigo(); // "CO"

// También funcionan los métodos explícitos
echo $municipio->getDepartamento()->getNombre(); // "Antioquia"
echo $municipio->getPais()->getNombre(); // "Colombia"

// Acceso en cadena
echo $municipio->departamento->pais->getNombre(); // "Colombia"
```

### Métodos de Conversión

#### toArray(bool $incluirRelaciones = false): array
Convierte el objeto a array, opcionalmente incluyendo datos de relaciones.

**Parámetros:**
- `$incluirRelaciones`: Si incluir datos del departamento y país relacionados

**Ejemplo:**
```php
$municipio = new Municipio(5);

// Sin relaciones
$array = $municipio->toArray();
// ['id' => 5, 'nombre' => 'Medellín', 'codigo' => '05001', ...]

// Con relaciones
$arrayCompleto = $municipio->toArray(true);
// Incluye: 'departamento' => [...], 'pais' => [...]
```

#### toJson(bool $incluirRelaciones = false): string
Convierte el objeto a JSON, opcionalmente incluyendo datos de relaciones.

**Ejemplo:**
```php
$municipio = new Municipio(5);
$json = $municipio->toJson(true); // Incluye datos de relaciones
```

## Gestión de Cache

### Configuración
- **Prefijo:** `municipio_`
- **TTL por defecto:** 1 hora (3600 segundos)
- **TTL para listas:** 2 horas (7200 segundos)
- **TTL para búsquedas:** 30 minutos (1800 segundos)

### Claves de Cache
- `municipio_id_{id}` - Municipio individual por ID
- `municipio_todos` - Lista completa de municipios
- `municipio_departamento_{id_departamento}` - Municipios por departamento
- `municipio_pais_{id_pais}` - Municipios por país
- `municipio_codigo_{codigo}` - Municipio por código
- `municipio_crediref_{codigo}` - Municipio por código crediref
- `municipio_buscar_{hash}` - Resultados de búsqueda
- `municipio_completos_{filtros}` - Municipios con datos completos

## Logging

La clase registra las siguientes operaciones:
- Conexiones exitosas y fallidas a la base de datos
- Aciertos y fallos del cache
- Errores en consultas
- Operaciones de invalidación de cache
- Creación de instancias de departamentos y países relacionados
- Accesos a propiedades mágicas no definidas

## Manejo de Errores

- **Exception**: Se lanza cuando no se puede conectar a la base de datos
- **Exception**: Se lanza cuando un municipio no se encuentra por ID en el constructor
- Los métodos estáticos retornan `null` en lugar de lanzar excepciones
- Los accesos a propiedades mágicas no definidas retornan `null` y se loggean

## Ejemplos de Uso Completos

### Uso Básico con Instancia
```php
try {
    $municipio = new Municipio(5);
    echo "Municipio: " . $municipio->getNombre();
    echo "Departamento: " . $municipio->departamento->getNombre(); // Acceso mágico
    echo "País: " . $municipio->pais->getNombre(); // Acceso mágico
    echo "Código: " . $municipio->getCodigo();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

### Uso con Métodos Estáticos
```php
// Obtener información específica
$nombre = Municipio::obtenerNombre(5);
$idDepartamento = Municipio::obtenerIdDepartamento(5);

// Búsquedas y filtros
$municipiosAntioquia = Municipio::obtenerPorDepartamento(5);
$municipiosColombia = Municipio::obtenerPorPais(1);
$municipios = Municipio::buscarPorNombre("Medellín");
$municipio = Municipio::obtenerPorCodigo("05001");

// Para formularios
$opcionesAntioquia = Municipio::obtenerParaSelect(5);
$opcionesColombia = Municipio::obtenerParaSelect(null, 1);
$todasOpciones = Municipio::obtenerParaSelect();
```

### Trabajando con Relaciones
```php
$municipio = new Municipio(5);

// Diferentes formas de acceder a relaciones
echo $municipio->departamento->getNombre();        // Acceso mágico
echo $municipio->getDepartamento()->getNombre();   // Método explícito
echo $municipio->getNombreDepartamento();          // Método directo

echo $municipio->pais->getNombre();                // Acceso mágico
echo $municipio->getPais()->getNombre();           // Método explícito
echo $municipio->getNombrePais();                  // Método directo

// Acceso en cadena
echo $municipio->departamento->pais->getCodigo();  // "CO"

// Convertir a array con relaciones
$datosCompletos = $municipio->toArray(true);
```

### Casos de Uso Avanzados
```php
// Municipios con información completa (JOIN)
$municipiosCompletos = Municipio::obtenerConCompletos();
foreach ($municipiosCompletos as $mun) {
    echo "{$mun['nombre']} ({$mun['codigo']}) - {$mun['nombre_departamento']} - {$mun['nombre_pais']}\n";
}

// Filtros específicos
$municipiosAntioquia = Municipio::obtenerConCompletos(1, 5); // Colombia, Antioquia
$municipiosColombia = Municipio::obtenerConCompletos(1); // Solo Colombia

// Gestión de cache
Municipio::invalidarCachePorDepartamento(5); // Invalida cache de Antioquia
Municipio::invalidarCachePorPais(1); // Invalida cache de Colombia
$stats = Municipio::obtenerEstadisticasCache();

// Select dinámico en cascada
$paisSeleccionado = 1; // Colombia
$departamentoSeleccionado = 5; // Antioquia
$municipiosSelect = Municipio::obtenerParaSelect($departamentoSeleccionado);
```

### Formularios en Cascada
```php
// JavaScript para selects dependientes
$paises = Pais::obtenerParaSelect();
$departamentos = []; // Se cargan via AJAX
$municipios = []; // Se cargan via AJAX

// PHP para AJAX endpoints
// /api/departamentos/{idPais}
$departamentos = Departamento::obtenerParaSelect($_GET['idPais']);

// /api/municipios/{idDepartamento}
$municipios = Municipio::obtenerParaSelect($_GET['idDepartamento']);
```

## Consideraciones de Rendimiento

1. **Cache Inteligente**: Todos los métodos utilizan cache para evitar consultas repetitivas
2. **Cache Jerárquico**: Cache específico por país, departamento y municipio
3. **Lazy Loading**: Las instancias de relaciones se cargan solo cuando se accede
4. **Invalidación en Cascada**: Al invalidar un municipio, se invalidan caches relacionados
5. **JOIN Optimizado**: Métodos específicos para obtener datos con relaciones en una sola consulta
6. **Cache Compartido**: El cache es compartido entre todas las instancias

## Relaciones

### Con Departamento (Belongs To)
- Un municipio pertenece a un departamento
- Acceso mediante `$municipio->departamento`
- Lazy loading automático

### Con País (Belongs To Through)
- Un municipio pertenece a un país a través del departamento
- Acceso mediante `$municipio->pais`
- Lazy loading automático a través de la relación con departamento

## Notas Adicionales

- La clase es compatible con el patrón Eloquent para relaciones
- Todos los métodos son compatibles con valores null
- Los códigos se convierten automáticamente a mayúsculas
- La búsqueda por nombre es case-insensitive
- La invalidación de cache es inteligente y en cascada
- Se recomienda usar métodos estáticos para consultas puntuales
- Para operaciones múltiples sobre el mismo municipio, usar instancias
- El acceso mágico a propiedades se loggea para debugging
- La conexión a BD se cierra automáticamente en el destructor
- Las relaciones soportan acceso en cadena (ej: `$municipio->departamento->pais->getNombre()`)
- Ideal para formularios de selección en cascada (País → Departamento → Municipio)