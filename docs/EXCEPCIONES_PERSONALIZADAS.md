# Manejo de Excepciones Personalizadas

## Descripción

Se han creado dos tipos de excepciones personalizadas para manejar errores de manera más elegante y segura:

### 1. `SoftException`
Excepción para errores **controlados** que son seguros de mostrar al usuario.

### 2. `SystemException`
Excepción para errores **técnicos** que deben registrarse en logs y mostrar un código de error al usuario.

---

## Ubicación de los archivos

```
www/app/Exceptions/
├── SoftException.php
└── SystemException.php
```

---

## Cómo usar

### Importar las clases

```php

use Micro\Exceptions\SoftException;
use Micro\Exceptions\SystemException;
```

### Ejemplo básico

```php
try {
    // Validación de negocio - Error controlado
    if (empty($result)) {
        throw new SoftException("No se encontró el registro especificado");
    }
    
    // Error de sistema - Error no controlado
    if (!$database->connect()) {
        throw new SystemException("Error al conectar con la base de datos: " . $database->getError());
    }
    
    // logica aqui
    
} catch (SoftException $e) {
    // Mostrar mensaje directamente al usuario
    $mensaje = "Error: " . $e->getMessage();
    $status = 0;
    
} catch (SystemException $e) {
    // Registrar en logs y mostrar código de error
    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error: Intente nuevamente, o reporte este código de error ($codigoError)";
    $status = 0;
    
} catch (Exception $e) {
    // Cualquier otra excepción no controlada
    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error inesperado: Reporte este código ($codigoError)";
    $status = 0;
}
```

---

## Casos de uso

### SoftException (Errores controlados)

Usa esta excepción cuando:
- Validaciones de negocio fallan
- El usuario ingresó datos inválidos
- No se encontró un registro esperado
- El registro ya está en un estado que impide la operación
- Permisos insuficientes

**Ejemplos:**

```php
// Validación de datos
if ($monto <= 0) {
    throw new SoftException("El monto debe ser mayor a cero");
}

// Registro no encontrado
if (empty($cliente)) {
    throw new SoftException("Cliente no encontrado");
}

// Estado inválido
if ($cierre['estado'] == 2) {
    throw new SoftException("El cierre ya fue realizado, no se puede modificar");
}

// Duplicados
if ($exists) {
    throw new SoftException("El número de documento ya existe");
}

// Validación de campo requerido
if (empty($cuenta_banco)) {
    throw new SoftException("Seleccione una cuenta de banco");
}
```

### SystemException (Errores técnicos)

Usa esta excepción cuando:
- Falla la conexión a base de datos
- Error al ejecutar una query
- Error al escribir archivos
- Servicios externos no responden
- Configuración incorrecta del sistema

**Ejemplos:**

```php
// Error de base de datos
if (!$database->openConnection()) {
    throw new SystemException("No se pudo establecer conexión con la base de datos");
}

// Error en query
if (!$result = $database->execute($query)) {
    throw new SystemException("Error al ejecutar query: " . $database->getError());
}

// Error de archivo
if (!file_put_contents($file, $content)) {
    throw new SystemException("No se pudo escribir el archivo: $file");
}

// Error de servicio externo
if ($apiResponse->status !== 200) {
    throw new SystemException("API externa falló: " . $apiResponse->error);
}
```

---

## Comparación: Antes vs Después

### ❌ Antes (usando $showmensaje)

```php
$showmensaje = false;
try {
    if (empty($result)) {
        $showmensaje = true;
        throw new Exception("No se encontró el registro");
    }
    
    // operación...
    
} catch (Exception $e) {
    if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    }
    $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Código ($codigoError)";
}
```

### ✅ Después (usando excepciones personalizadas)

```php
try {
    if (empty($result)) {
        throw new SoftException("No se encontró el registro");
    }
    
    // operación...
    
} catch (SoftException $e) {
    $mensaje = "Error: " . $e->getMessage();
} catch (Exception $e) {
    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    $mensaje = "Error: Código ($codigoError)";
}
```

---

## Ventajas de este enfoque

1. **Código más limpio**: No necesitas la variable `$showmensaje`
2. **Más expresivo**: El tipo de excepción indica claramente la intención
3. **Mejor manejo**: Puedes capturar diferentes tipos de excepciones
4. **Type hinting**: PHP puede ayudarte con autocompletado y validación
5. **Escalable**: Puedes crear más tipos de excepciones específicas
6. **Estándar**: Sigue las mejores prácticas de PHP

---

## Extensiones futuras

Puedes crear excepciones más específicas heredando de estas:

```php
// Excepciones específicas de validación
class ValidationException extends SoftException {}

// Excepciones de base de datos
class DatabaseException extends SystemException {}

// Excepciones de permisos
class PermissionDeniedException extends SoftException {}

// Uso:
throw new ValidationException("El email no es válido");
throw new DatabaseException("Error en transacción: " . $details);
throw new PermissionDeniedException("No tiene acceso a este módulo");
```

---

## Migración gradual

No necesitas cambiar todo el código de una vez. Puedes:

1. Importar las clases en los archivos que vayas modificando
2. Reemplazar gradualmente el patrón `$showmensaje`
3. Mantener ambos enfoques funcionando hasta completar la migración

---

## Notas importantes

- **Siempre** captura `SoftException` antes que `Exception`
- Los mensajes de `SoftException` deben ser claros y útiles para el usuario
- Los mensajes de `SystemException` pueden incluir detalles técnicos (van al log)
- En el bloque `catch` general, trata como error de sistema por seguridad
