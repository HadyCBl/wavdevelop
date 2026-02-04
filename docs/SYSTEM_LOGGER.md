# SystemLogger - Documentación

Servicio para registrar acciones de usuarios en el sistema mediante la tabla `system_logs`.

## Características

✅ **Métodos estáticos** - Fácil de usar desde cualquier parte del código  
✅ **Conexión flexible** - Usa DatabaseAdapter por defecto o conexión PDO externa  
✅ **Captura automática** - IP, User Agent y User ID desde sesión  
✅ **Métodos específicos** - Para CREATE, UPDATE, DELETE, LOGIN, etc.  
✅ **Datos JSON** - Almacena old_data y new_data automáticamente  
✅ **Seguro** - No falla operaciones principales si hay error en log  

## Instalación

La clase está lista para usar. Solo asegúrate de que la tabla `system_logs` esté creada.

## Uso Básico

### 1. Sin especificar conexión (usa DatabaseAdapter automáticamente)

```php
use App\Services\SystemLogger;

// Log genérico - usa DatabaseAdapter internamente
SystemLogger::log(
    SystemLogger::ACTION_CREATE,
    'clientes',
    123,
    'Cliente Juan Pérez creado'
);
```

### 2. Con conexión PDO existente

```php
// Si ya tienes una conexión PDO
$pdo = new PDO($dsn, $user, $password);

SystemLogger::log(
    SystemLogger::ACTION_UPDATE,
    'usuarios',
    45,
    'Actualizó perfil',
    ['nombre' => 'Juan'],
    ['nombre' => 'Juan Carlos'],
    null,
    $pdo  // Pasar conexión
);
```

### 3. Establecer conexión compartida (recomendado para múltiples logs)

```php
// Opción A: Establecer conexión PDO compartida
$pdo = new PDO($dsn, $user, $password);
SystemLogger::setConnection($pdo);

// Opción B: Establecer DatabaseAdapter compartido
$adapter = new \App\DatabaseAdapter();
SystemLogger::setAdapter($adapter);

// Usar múltiples veces sin pasar conexión
SystemLogger::create('productos', 100, ['nombre' => 'Producto A']);
SystemLogger::update('productos', 100, ['precio' => 50], ['precio' => 55]);
SystemLogger::delete('productos', 100, ['nombre' => 'Producto A']);
```

## Métodos Específicos

### CREATE - Registrar Creación

```php
SystemLogger::create(
    'auxilios',           // Entidad
    $auxilioId,           // ID creado
    $nuevoAuxilio,        // Datos del nuevo registro
    'Auxilio póstumo creado para Juan Pérez'  // Descripción opcional
);
```

### UPDATE - Registrar Actualización

```php
SystemLogger::update(
    'seguros_cuentas',    // Entidad
    $cuentaId,            // ID actualizado
    $datosAnteriores,     // Datos antes del cambio
    $datosNuevos,         // Datos después del cambio
    'Renovación de cuenta actualizada'
);
```

### DELETE - Registrar Eliminación

```php
SystemLogger::delete(
    'beneficiarios',      // Entidad
    $beneficiarioId,      // ID eliminado
    $datosBeneficiario,   // Datos del registro eliminado
    'Beneficiario removido de la cuenta'
);
```

### LOGIN - Registrar Inicio de Sesión

```php
// Login exitoso
SystemLogger::login($userId, true);

// Login fallido
SystemLogger::login($userId, false, 'Contraseña incorrecta');
```

### LOGOUT - Registrar Cierre de Sesión

```php
SystemLogger::logout($userId);
```

### APPROVE - Registrar Aprobación

```php
SystemLogger::approve(
    'auxilios',
    $auxilioId,
    ['monto_aprobado' => 5000, 'notas' => 'Aprobado por gerencia'],
    'Auxilio aprobado'
);
```

### REJECT - Registrar Rechazo

```php
SystemLogger::reject(
    'auxilios',
    $auxilioId,
    ['motivo' => 'Documentación incompleta'],
    'Auxilio rechazado'
);
```

### PAYMENT - Registrar Pago

```php
SystemLogger::payment(
    'auxilios',
    $auxilioId,
    [
        'monto' => 5000,
        'forma_pago' => 'cheque',
        'numero_cheque' => '123456'
    ],
    'Pago de auxilio registrado'
);
```

### GENERATE_REPORT - Registrar Generación de Reporte

```php
SystemLogger::generateReport(
    'Reporte de Auxilios Pagados',
    ['fecha_inicio' => '2026-01-01', 'fecha_fin' => '2026-01-31'],
    'Reporte mensual generado'
);
```

### EXPORT - Registrar Exportación

```php
SystemLogger::export(
    'clientes',
    'Excel',
    ['region' => 'Guatemala', 'estado' => 'activo'],
    'Exportación de clientes activos'
);
```

## Ejemplo Completo en un Controller

```php
<?php

namespace App\Controllers;

use App\Services\SystemLogger;

class AuxiliosController
{
    public function aprobar($request, $response)
    {
        $id = $request->getAttribute('id');
        $monto = $request->getParsedBody()['monto_aprobado'];
        
        // Obtener datos anteriores
        $auxilioAntes = $this->getAuxilio($id);
        
        // Actualizar
        $this->updateAuxilio($id, [
            'estado' => 'aprobado',
            'monto_aprobado' => $monto
        ]);
        
        // Registrar en logs
        SystemLogger::approve(
            'seguros_auxilios',
            $id,
            [
                'estado_anterior' => $auxilioAntes['estado'],
                'estado_nuevo' => 'aprobado',
                'monto_aprobado' => $monto
            ],
            "Auxilio #{$id} aprobado por Q{$monto}"
        );
        
        return $response->withJson(['success' => true]);
    }
    
    public function pagar($request, $response)
    {
        $id = $request->getAttribute('id');
        $data = $request->getParsedBody();
        
        // Registrar pago
        $pagoId = $this->registrarPago($id, $data);
        
        // Log del pago
        SystemLogger::payment(
            'seguros_auxilios',
            $id,
            [
                'monto' => $data['monto'],
                'forma_pago' => $data['forma_pago'],
                'fecha' => $data['fecha'],
                'numero_documento' => $data['numdoc'] ?? null
            ],
            "Pago de auxilio #{$id} registrado"
        );
        
        return $response->withJson(['success' => true, 'pago_id' => $pagoId]);
    }
}
```

## Ejemplo con Transacciones

```php
use App\Services\SystemLogger;
use App\DatabaseAdapter;

// Opción 1: Con PDO
try {
    $pdo->beginTransaction();
    
    // Establecer conexión compartida para la transacción
    SystemLogger::setConnection($pdo);
    
    // Operación 1
    $stmt = $pdo->prepare("INSERT INTO clientes ...");
    $stmt->execute($datos);
    $clienteId = $pdo->lastInsertId();
    
    SystemLogger::create('clientes', $clienteId, $datos);
    
    // Operación 2
    $stmt = $pdo->prepare("INSERT INTO cuentas ...");
    $stmt->execute($datosCuenta);
    $cuentaId = $pdo->lastInsertId();
    
    SystemLogger::create('cuentas', $cuentaId, $datosCuenta);
    
    $pdo->commit();
    
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}

// Opción 2: Con DatabaseAdapter
try {
    $adapter = new DatabaseAdapter();
    $adapter->openConnection();
    $adapter->beginTransaction();
    
    // Establecer adapter compartido
    SystemLogger::setAdapter($adapter);
    
    // Realizar operaciones
    $clienteId = $adapter->insert('clientes', $datos);
    SystemLogger::create('clientes', $clienteId, $datos);
    
    $cuentaId = $adapter->insert('cuentas', $datosCuenta);
    SystemLogger::create('cuentas', $cuentaId, $datosCuenta);
    
    $adapter->commit();
    $adapter->closeConnection();
    
} catch (Exception $e) {
    $adapter->rollback();
    $adapter->closeConnection();
    throw $e;
}
```

## Consultar Logs

### Obtener logs de un usuario

```php
$logs = SystemLogger::getUserLogs($userId, 100); // Últimos 100 logs

foreach ($logs as $log) {
    echo "{$log['action']} en {$log['entity']} - {$log['description']}\n";
}
```

### Obtener logs de una entidad

```php
// Todos los logs de auxilios
$logs = SystemLogger::getEntityLogs('seguros_auxilios');

// Logs de un auxilio específico
$logs = SystemLogger::getEntityLogs('seguros_auxilios', $auxilioId);
```

### Limpiar logs antiguos (mantenimiento)

```php
// Eliminar logs de más de 2 años
$eliminados = SystemLogger::cleanOldLogs(730);

echo "Se eliminaron {$eliminados} logs antiguos";
```

## Constantes de Acciones Disponibles

```php
SystemLogger::ACTION_CREATE          // 'CREATE'
SystemLogger::ACTION_UPDATE          // 'UPDATE'
SystemLogger::ACTION_DELETE          // 'DELETE'
SystemLogger::ACTION_LOGIN           // 'LOGIN'
SystemLogger::ACTION_LOGOUT          // 'LOGOUT'
SystemLogger::ACTION_GENERAR_REPORTE // 'GENERAR_REPORTE'
SystemLogger::ACTION_APROBAR         // 'APROBAR'
SystemLogger::ACTION_RECHAZAR        // 'RECHAZAR'
SystemLogger::ACTION_PAGAR           // 'PAGAR'
SystemLogger::ACTION_EXPORT          // 'EXPORT'
SystemLogger::ACTION_IMPORT          // 'IMPORT'
SystemLogger::ACTION_VIEW            // 'VIEW'
SystemLogger::ACTION_DOWNLOAD        // 'DOWNLOAD'
SystemLogger::ACTION_UPLOAD          // 'UPLOAD'
```

## Datos Capturados Automáticamente

- **user_id**: Se toma de `$_SESSION['id']` si no se especifica
- **ip_address**: IP real del cliente (considera proxies)
- **user_agent**: Navegador y sistema operativo
- **created_at**: Timestamp automático

## Orden de Prioridad de Conexión

El SystemLogger usa las conexiones en este orden de prioridad:

1. **Conexión PDO personalizada** - Si se pasa como parámetro en cada llamada
2. **Conexión PDO compartida** - Si se estableció con `setConnection()`
3. **DatabaseAdapter compartido** - Si se estableció con `setAdapter()`
4. **DatabaseAdapter nuevo** - Crea una instancia automáticamente (por defecto)

```php
// Prioridad 1: Conexión personalizada (mayor prioridad)
SystemLogger::create('clientes', 1, $data, null, null, $miPdo);

// Prioridad 2: Conexión compartida
SystemLogger::setConnection($pdo);
SystemLogger::create('clientes', 1, $data);

// Prioridad 3: Adapter compartido
SystemLogger::setAdapter($adapter);
SystemLogger::create('clientes', 1, $data);

// Prioridad 4: Adapter automático (menor prioridad, por defecto)
SystemLogger::create('clientes', 1, $data);
```

## Manejo de Errores

El logger está diseñado para NO interrumpir las operaciones principales:

```php
// Si el log falla, la operación principal continúa
$resultado = $this->crearCliente($datos);

// Este log puede fallar sin afectar el proceso
SystemLogger::create('clientes', $resultado['id'], $datos);

// El proceso continúa normalmente
```

Los errores se registran en el log de PHP (`error_log`).

## Buenas Prácticas

1. **Usar constantes** para las acciones en lugar de strings
2. **Establecer conexión compartida** cuando hagas múltiples logs
3. **Incluir descripciones claras** y legibles
4. **No registrar datos sensibles** (contraseñas, tokens, etc.)
5. **Usar transacciones** cuando el log es crítico para la operación
6. **Limpiar logs antiguos** periódicamente

## Integración con Middleware

```php
// Middleware para loggear todas las peticiones
class LogMiddleware
{
    public function __invoke($request, $response, $next)
    {
        $route = $request->getAttribute('route');
        $method = $request->getMethod();
        
        SystemLogger::log(
            SystemLogger::ACTION_VIEW,
            'api_request',
            null,
            "{$method} {$route->getPattern()}",
            null,
            [
                'method' => $method,
                'path' => $request->getUri()->getPath(),
                'params' => $request->getQueryParams()
            ]
        );
        
        return $next($request, $response);
    }
}
```

## Performance

- Los logs se insertan de forma asíncrona respecto a la operación principal
- No bloquea la respuesta al usuario
- Conexión PDO reutilizable para múltiples logs
- Índices recomendados en la tabla:

```sql
CREATE INDEX idx_user_created ON system_logs(user_id, created_at);
CREATE INDEX idx_entity_created ON system_logs(entity, entity_id, created_at);
CREATE INDEX idx_action_created ON system_logs(action, created_at);
```
