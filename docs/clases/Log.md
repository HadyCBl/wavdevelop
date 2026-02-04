# Clase Log (Facade)

Esta clase actúa como un Facade al estilo Laravel para proporcionar una interfaz estática simple para el sistema de logs.

## Ubicación
```php
namespace App;
// filepath: includes/Config/Log.php
```

## Propiedades

| Propiedad | Tipo | Descripción |
|-----------|------|-------------|
| `$logger` | `static Logger` | Instancia singleton del logger |

## Métodos

### getLogger()
```php
protected static function getLogger()
```
Método singleton que devuelve o crea una instancia del logger.

### Métodos de Logging

Todos los métodos siguientes son estáticos y proxy al logger subyacente:

```php
public static function info($message, array $context = [])
public static function warning($message, array $context = [])
public static function error($message, array $context = [])
public static function debug($message, array $context = [])
```

## Ejemplo de Uso

```php
use Micro\Helpers\Log;

// Logging simple
Log::info('Usuario logueado');

// Logging con contexto
Log::error('Error en transacción', [
    'usuario_id' => 123,
    'monto' => 1500.00,
    'error' => 'Saldo insuficiente'
]);

// Debug con datos
Log::debug('Depurando proceso', [
    'datos' => $datos,
    'parametros' => $params
]);

// Advertencias
Log::warning('Operación sospechosa', [
    'ip' => $ip_address,
    'intento' => $intentos
]);
```

## Ubicación de Logs

Los archivos de log se guardan en:
```
/logs/log-YYYY-MM-DD.log
```

## Formato del Log

Cada entrada del log sigue este formato:
```
[YYYY-MM-DD HH:mm:ss] LEVEL: Message {"context":"data"}
```

## Ventajas del Facade

1. Sintaxis más limpia y familiar para usuarios de Laravel
2. No necesita instanciación
3. Acceso global desde cualquier parte de la aplicación
4. Mantiene el patrón singleton para el logger

## Notas
- La clase utiliza internamente la clase `Logger` para el manejo real de los logs
- Los archivos se crean automáticamente por fecha
- Soporta contexto en formato JSON
- Thread-safe para entornos multi-hilo