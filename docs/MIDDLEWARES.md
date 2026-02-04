# Sistema de Middlewares - Guía Rápida

## 📦 Middlewares Disponibles

### 1. AuthMiddleware
Verifica que el usuario esté autenticado (usa `Auth::getUserId()`).

**Respuesta si falla:**
```json
{
    "status": 0,
    "error": "No autenticado",
    "message": "Debe iniciar sesión para acceder a este recurso",
    "code": 401
}
```

### 2. CSRFMiddleware
Valida el token CSRF en peticiones POST, PUT, PATCH, DELETE.

**Respuesta si falla:**
```json
{
    "status": 0,
    "error": "Token CSRF inválido",
    "message": "La petición no pudo ser verificada. Por favor, recargue la página e intente nuevamente.",
    "code": 403
}
```

## ⚙️ Configuración en api/index.php

### Rutas Protegidas (requieren autenticación)
```php
$protectedRoutes = [
    '/api/crud/*',           // Todos los CRUDs
    '/api/vistas/*',         // Todas las vistas
    '/api/reportes/*',       // Todos los reportes
    '/api/clientes/*',       // Rutas específicas
];
```

### Excepciones de CSRF
```php
$csrfExceptions = [
    '/api/health',           // Health check público
    '/api/webhook/*',        // Webhooks externos
    '/api/public/*',         // APIs públicas
];
```

## 🔧 Uso del Token CSRF

### En formularios HTML
```html
<form id="miForm" data-action="/api/clientes" data-method="POST">
    <!-- El token se puede agregar automáticamente -->
    <input type="hidden" name="csrf_token" value="<?= (new CSRFProtection())->getToken() ?>">
    
    <input name="nombre" required>
    <button type="submit">Enviar</button>
</form>
```

### En JavaScript (submitForm)
```javascript
// Opción 1: Token en el formulario (recomendado)
submitForm('#miForm', {
    // El token se envía automáticamente si está en el formulario
});

// Opción 2: Token en headers AJAX
$.ajax({
    url: '/api/clientes',
    method: 'POST',
    headers: {
        'X-CSRF-TOKEN': '<?= (new CSRFProtection())->getToken() ?>'
    },
    data: {...}
});
```

### Generar token globalmente (recomendado)
```javascript
// En tu layout principal, agregar token global
window.CSRF_TOKEN = '<?= (new CSRFProtection())->getToken() ?>';

// Configurar AJAX para incluir token automáticamente
$.ajaxSetup({
    headers: {
        'X-CSRF-TOKEN': window.CSRF_TOKEN
    }
});
```

## 🎯 Patrones de Rutas

- `/api/clientes` - Coincide exacto
- `/api/clientes/*` - Coincide con `/api/clientes/123`, `/api/clientes/crear`, etc.
- `/api/*/reportes` - Coincide con cualquier módulo: `/api/creditos/reportes`, `/api/ahorros/reportes`

## 🚀 Crear Middleware Personalizado

```php
<?php

namespace Micro\Middleware;

class MiMiddleware implements Middleware
{
    public function handle(array $request, callable $next)
    {
        // 1. Lógica ANTES del controlador
        if (!algunaValidacion()) {
            http_response_code(403);
            echo json_encode(['error' => 'Acceso denegado']);
            exit;
        }
        
        // 2. Continuar con la petición
        $result = $next($request);
        
        // 3. Lógica DESPUÉS del controlador (opcional)
        // Ejemplo: logging, modificar respuesta, etc.
        
        return $result;
    }
}
```

## 📝 Ejemplos de Uso

### Proteger una ruta específica
```php
// En api/index.php
$protectedRoutes = [
    '/api/admin/*',          // Solo admin
    '/api/configuracion/*',  // Solo configuración
];
```

### Excluir webhook de CSRF
```php
$csrfExceptions = [
    '/api/webhook/ably',
    '/api/webhook/payment-gateway',
];
```

### Middleware global para todas las rutas
```php
$globalMiddlewares = [
    new RateLimitMiddleware(),  // Limitar peticiones
    new LoggingMiddleware(),    // Log de todas las peticiones
];
```

## 🔍 Orden de Ejecución

```
Petición → Middlewares Globales → Auth (si aplica) → CSRF (si aplica) → Controlador
```

1. **Globales**: Se ejecutan SIEMPRE
2. **Auth**: Solo si la ruta coincide con `$protectedRoutes`
3. **CSRF**: Se ejecuta en POST/PUT/PATCH/DELETE, excepto rutas en `$csrfExceptions`
4. **Controlador**: Se ejecuta si todos los middlewares pasan

## 🛡️ Seguridad

- ✅ **Auth**: Previene acceso no autorizado
- ✅ **CSRF**: Previene ataques Cross-Site Request Forgery
- ✅ **Excepciones**: Permite APIs públicas y webhooks
- ✅ **Wildcards**: Protege módulos completos fácilmente

## 📌 Notas Importantes

1. El token CSRF se regenera automáticamente en cada validación (modo estricto)
2. Los middlewares se ejecutan en el orden: Globales → Auth → CSRF
3. Si un middleware falla, la ejecución se detiene y retorna error JSON
4. Las rutas usan pattern matching con wildcards (*)
