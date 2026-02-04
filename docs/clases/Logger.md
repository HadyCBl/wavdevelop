# Clase Logger

La clase `Logger` proporciona funcionalidad para registrar mensajes de log en archivos con diferentes niveles de severidad.

## Ubicación
```php
namespace App;
// filepath: includes/Config/Logger.php
```

## Propiedades

| Propiedad | Tipo | Descripción |
|-----------|------|-------------|
| `$logPath` | `string` | Ruta donde se almacenarán los archivos de log |

## Constructor

```php
public function __construct($logPath = __DIR__ . '/../../logs')
```

Inicializa el logger y crea el directorio de logs si no existe.

| Parámetro | Tipo | Descripción | Por defecto |
|-----------|------|-------------|-------------|
| `$logPath` | `string` | Ruta donde se guardarán los logs | `__DIR__ . '/../../logs'` |

## Métodos

### log()

```php
public function log($level, $message, array $context = [])
```

Método principal para registrar mensajes en el log.

| Parámetro | Tipo | Descripción |
|-----------|------|-------------|
| `$level` | `string` | Nivel del log (INFO, ERROR, etc.) |
| `$message` | `string` | Mensaje a registrar |
| `$context` | `array` | Datos adicionales (opcional) |

### Métodos de Nivel

La clase proporciona métodos convenientes para diferentes niveles de log:

```php
public function info($message, array $context = [])
public function warning($message, array $context = [])
public function error($message, array $context = [])
public function debug($message, array $context = [])
```

## Formato del Log

Los mensajes se guardan con el siguiente formato:
```
[YYYY-MM-DD HH:mm:ss] LEVEL: Message {"context":"data"}
```

## Ejemplo de Uso

```php
// Crear instancia del logger
$logger = new Logger();

// Registrar diferentes tipos de mensajes
$logger->info('Inicio del sistema');
$logger->error('Error en el sistema', ['error' => 'Error de conexión']);
$logger->debug('Depuración', ['data' => $datos]);
$logger->warning('Advertencia', ['warning' => 'Archivo no encontrado']);

// Ejemplo con contexto
$logger->info('Procesando usuario', [
    'usuario_id' => $id,
    'accion' => 'login'
]);
```

## Estructura de Archivos

Los logs se guardan en archivos diarios con el formato:
```
/logs/log-YYYY-MM-DD.log
```

## Notas
- Los archivos de log se crean automáticamente por fecha
- El contexto se serializa en formato JSON
- Los permisos del directorio de logs se establecen en 0777
- Cada entrada incluye timestamp y nivel de severidad