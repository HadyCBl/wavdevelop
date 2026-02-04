# Clase Identificacion

## Descripción
La clase `Identificacion` maneja los tipos de identificación del sistema, proporcionando métodos para consultar, validar y formatear números de identificación. Incluye soporte completo para cache APCu y relaciones con países.

## Namespace
```php
namespace Micro\Models;
```

## Tabla de base de datos
- **Tabla**: `tb_identificaciones`
- **Campos**:
  - `id` (INT, AUTO_INCREMENT, PRIMARY KEY)
  - `codigo` (VARCHAR(20)) - Código único del tipo de identificación
  - `nombre` (VARCHAR(100)) - Nombre descriptivo del tipo
  - `id_pais` (INT, NULL) - ID del país asociado (NULL para tipos globales)
  - `mascara_regex` (VARCHAR(255), NULL) - Expresión regular para validación
  - `formato_display` (VARCHAR(100), NULL) - Formato para mostrar el número

## Propiedades

### Propiedades públicas
- `$id` - ID del tipo de identificación
- `$codigo` - Código único del tipo
- `$nombre` - Nombre descriptivo
- `$id_pais` - ID del país asociado
- `$mascara_regex` - Expresión regular para validación
- `$formato_display` - Formato de visualización

### Propiedades privadas
- `$db` - Instancia de DatabaseAdapter
- `$cache` - Instancia estática de CacheManager
- `$table` - Nombre de la tabla ('tb_identificaciones')
- `$paisInstancia` - Instancia relacionada de Pais

## Constructor

```php
public function __construct(?int $id = null)
```

- **Parámetros**: 
  - `$id` (opcional): ID del tipo de identificación a cargar
- **Funcionalidad**: Inicializa el cache y carga los datos si se proporciona un ID

## Métodos principales

### Métodos de consulta básicos

#### `obtenerPorId(int $id): ?array`
Obtiene un tipo de identificación por su ID.

```php
$identificacion = $instance->obtenerPorId(1);
// Retorna: ['id' => 1, 'codigo' => 'CC', 'nombre' => 'Cédula de Ciudadanía', ...]
```

#### `obtenerTodos(): array`
Obtiene todos los tipos de identificación ordenados por nombre.

```php
$tipos = Identificacion::obtenerTodos();
```

#### `obtenerPorPais(int $idPais): array`
Obtiene tipos de identificación específicos de un país.

```php
$tiposColombia = Identificacion::obtenerPorPais(48); // Colombia
```

#### `obtenerPorPaisConGlobales(int $idPais): array`
Obtiene tipos de identificación de un país **más** los tipos globales (id_pais = NULL).

```php
$tiposCompletos = Identificacion::obtenerPorPaisConGlobales(48);
// Incluye tipos específicos de Colombia + tipos globales como Pasaporte
```

#### `obtenerGlobales(): array`
Obtiene solo los tipos de identificación globales (id_pais = NULL).

```php
$tiposGlobales = Identificacion::obtenerGlobales();
// Retorna: Pasaporte, Documento extranjero, etc.
```

### Métodos de búsqueda

#### `obtenerPorCodigo(string $codigo): ?array`
Busca un tipo de identificación por su código.

```php
$cc = Identificacion::obtenerPorCodigo('CC');
$passport = Identificacion::obtenerPorCodigo('PS');
```

#### `buscarPorNombre(string $nombre): array`
Busca tipos de identificación que contengan el texto en el nombre.

```php
$resultados = Identificacion::buscarPorNombre('cédula');
```

#### `obtenerConCompletos(?int $idPais = null): array`
Obtiene tipos de identificación con información completa (JOIN con países).

```php
$completos = Identificacion::obtenerConCompletos();
$completosPais = Identificacion::obtenerConCompletos(48);
```

### Métodos estáticos de acceso rápido

#### `obtenerNombre(int $id): ?string`
```php
$nombre = Identificacion::obtenerNombre(1); // "Cédula de Ciudadanía"
```

#### `obtenerCodigo(int $id): ?string`
```php
$codigo = Identificacion::obtenerCodigo(1); // "CC"
```

#### `obtenerMascaraRegex(int $id): ?string`
```php
$regex = Identificacion::obtenerMascaraRegex(1); // "^\d{8,10}$"
```

#### `obtenerFormatoDisplay(int $id): ?string`
```php
$formato = Identificacion::obtenerFormatoDisplay(1); // "###.###.###"
```

#### `obtenerIdPais(int $id): ?int`
```php
$idPais = Identificacion::obtenerIdPais(1); // 48 (Colombia)
```

#### `obtenerNombrePorCodigo(string $codigo): ?string`
```php
$nombre = Identificacion::obtenerNombrePorCodigo('CC'); // "Cédula de Ciudadanía"
```

### Métodos de validación y formateo

#### `validarNumero(string $numero): bool`
Valida un número de identificación usando la máscara regex del tipo.

```php
$identificacion = new Identificacion(1); // Cédula colombiana
$esValido = $identificacion->validarNumero('12345678'); // true/false
```

#### `validarNumeroPorId(int $idTipo, string $numero): bool`
Método estático para validar un número por ID del tipo.

```php
$esValido = Identificacion::validarNumeroPorId(1, '12345678');
```

#### `formatearNumero(string $numero): string`
Formatea un número usando el formato display del tipo.

```php
$identificacion = new Identificacion(1);
$formateado = $identificacion->formatearNumero('12345678'); // "12.345.678"
```

#### `formatearNumeroPorId(int $idTipo, string $numero): string`
Método estático para formatear un número por ID del tipo.

```php
$formateado = Identificacion::formatearNumeroPorId(1, '12345678');
```

### Métodos de utilidad

#### `existe(int $id): bool`
Verifica si existe un tipo de identificación por ID.

```php
$existe = Identificacion::existe(1); // true/false
```

#### `obtenerParaSelect(?int $idPais = null, bool $incluirGlobales = false): array`
Obtiene un array asociativo para elementos select de HTML.

```php
// Todos los tipos
$opciones = Identificacion::obtenerParaSelect();
// Resultado: [1 => 'Cédula de Ciudadanía', 2 => 'Pasaporte', ...]

// Solo tipos de un país
$opciones = Identificacion::obtenerParaSelect(48);

// Tipos de un país + globales
$opciones = Identificacion::obtenerParaSelect(48, true);
```

### Métodos de cache

#### `limpiarCache(): bool`
Limpia todo el cache de tipos de identificación.

```php
$resultado = Identificacion::limpiarCache();
```

#### `invalidarCache(int $id): void`
Invalida el cache de un tipo específico y sus relaciones.

```php
Identificacion::invalidarCache(1);
```

#### `invalidarCachePorPais(int $idPais): void`
Invalida el cache de todos los tipos de un país.

```php
Identificacion::invalidarCachePorPais(48);
```

#### `invalidarCacheGlobales(): void`
Invalida el cache de tipos globales y todos los cache "con_globales".

```php
Identificacion::invalidarCacheGlobales();
```

#### `obtenerEstadisticasCache(): array`
Obtiene estadísticas del cache APCu.

```php
$stats = Identificacion::obtenerEstadisticasCache();
```

### Getters

#### Getters básicos
```php
$identificacion = new Identificacion(1);

$id = $identificacion->getId();
$codigo = $identificacion->getCodigo();
$nombre = $identificacion->getNombre();
$idPais = $identificacion->getIdPais();
$regex = $identificacion->getMascaraRegex();
$formato = $identificacion->getFormatoDisplay();
```

### Métodos de relaciones

#### `getPais(): ?Pais`
Obtiene la instancia del país relacionado.

```php
$identificacion = new Identificacion(1);
$pais = $identificacion->getPais(); // Instancia de Pais o null
```

#### `getNombrePais(): ?string`
Obtiene el nombre del país relacionado.

```php
$nombrePais = $identificacion->getNombrePais(); // "Colombia" o null
```

#### Propiedades mágicas
```php
$identificacion = new Identificacion(1);
$pais = $identificacion->pais; // Acceso mágico a la relación
```

### Métodos de conversión

#### `toArray(bool $incluirRelaciones = false): array`
Convierte el objeto a array.

```php
$array = $identificacion->toArray();
$arrayConPais = $identificacion->toArray(true); // Incluye datos del país
```

#### `toJson(bool $incluirRelaciones = false): string`
Convierte el objeto a JSON.

```php
$json = $identificacion->toJson();
$jsonConPais = $identificacion->toJson(true);
```

## Ejemplos de uso

### Uso básico
```php
// Crear instancia
$cc = new Identificacion(1);
echo $cc->getNombre(); // "Cédula de Ciudadanía"

// Obtener por código
$tipo = Identificacion::obtenerPorCodigo('CC');
if ($tipo) {
    echo $tipo['nombre'];
}
```

### Validación de números
```php
// Validar cédula colombiana
$esValida = Identificacion::validarNumeroPorId(1, '12345678');

if ($esValida) {
    $numeroFormateado = Identificacion::formatearNumeroPorId(1, '12345678');
    echo "Número válido: {$numeroFormateado}";
}
```

### Para formularios HTML
```php
// Select con tipos de un país específico
$tiposColombia = Identificacion::obtenerParaSelect(48);

// Select con tipos de un país + tipos globales
$tiposCompletos = Identificacion::obtenerParaSelect(48, true);

// Select con todos los tipos
$todosTipos = Identificacion::obtenerParaSelect();
```

### Gestión por país
```php
// Obtener solo tipos específicos de Colombia
$tiposColombia = Identificacion::obtenerPorPais(48);

// Obtener tipos de Colombia + tipos globales (como Pasaporte)
$tiposCompletos = Identificacion::obtenerPorPaisConGlobales(48);

// Obtener solo tipos globales
$tiposGlobales = Identificacion::obtenerGlobales();
```

### Trabajar con relaciones
```php
$identificacion = new Identificacion(1);

// Obtener país relacionado
$pais = $identificacion->getPais();
if ($pais) {
    echo "País: " . $pais->getNombre();
    echo "Código país: " . $pais->getCodigo();
}

// O usando propiedades mágicas
echo "País: " . $identificacion->pais->nombre;
```

### Búsquedas avanzadas
```php
// Buscar por nombre
$resultados = Identificacion::buscarPorNombre('cédula');

// Obtener con información completa (JOIN)
$completos = Identificacion::obtenerConCompletos();
foreach ($completos as $tipo) {
    echo "{$tipo['nombre']} - {$tipo['nombre_pais']}";
}
```

## Cache

La clase implementa cache APCu con las siguientes claves:
- `identificacion_id_{id}` - Cache individual por ID
- `identificacion_todos` - Cache de todos los tipos
- `identificacion_pais_{idPais}` - Cache por país específico
- `identificacion_pais_con_globales_{idPais}` - Cache por país + globales
- `identificacion_globales` - Cache de tipos globales
- `identificacion_codigo_{codigo}` - Cache por código
- `identificacion_buscar_{hash}` - Cache de búsquedas
- `identificacion_completos` - Cache de consultas con JOIN

### Tiempos de cache
- **Consultas individuales**: 1 hora (3600 segundos)
- **Listas y consultas múltiples**: 2 horas (7200 segundos)
- **Búsquedas**: 30 minutos (1800 segundos)

## Logging

La clase registra eventos importantes:
- **Debug**: Accesos a cache, consultas a BD, creación de relaciones
- **Warning**: Acceso a propiedades mágicas no definidas
- **Error**: Errores de BD, conexiones fallidas, tipos no encontrados

## Consideraciones

1. **Tipos globales**: Los tipos con `id_pais = NULL` son considerados globales (ej: Pasaporte)
2. **Validación**: Si no hay `mascara_regex`, se considera válido cualquier valor
3. **Formateo**: Si no hay `formato_display`, se retorna el número sin formatear
4. **Cache inteligente**: Se invalida automáticamente cuando se modifica un registro
5. **Relaciones lazy**: Los países relacionados se cargan solo cuando se acceden

## Errores comunes

1. **Tipo no encontrado**: Se lanza excepción si se intenta crear instancia con ID inexistente
2. **Error de conexión BD**: Se propaga la excepción del DatabaseAdapter
3. **Regex inválida**: El método `validarNumero()` podría fallar con regex malformadas