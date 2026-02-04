# TelegramService - Servicio de Notificaciones

Servicio reutilizable para enviar notificaciones a Telegram desde cualquier parte del proyecto.

## 📋 Tabla de Contenidos

- [Instalación y Configuración](#instalación-y-configuración)
- [Uso Básico](#uso-básico)
- [Métodos Disponibles](#métodos-disponibles)
- [Mensajes con Formato](#mensajes-con-formato)
- [Notificaciones Pre-formateadas](#notificaciones-pre-formateadas)
- [Casos de Uso Reales](#casos-de-uso-reales)
- [Mejores Prácticas](#mejores-prácticas)

---

## Instalación y Configuración

### Requisitos

1. **Token del Bot de Telegram**: Obtén uno desde [@BotFather](https://t.me/BotFather)
2. **Chat ID**: ID del chat donde se enviarán las notificaciones

### Configuración en `.env`

```env
TELEGRAM_BOT_TOKEN=tu_bot_token_aqui
TELEGRAM_CHAT_ID=tu_chat_id_aqui
```

### Obtener el Chat ID

1. Envía un mensaje a tu bot
2. Visita: `https://api.telegram.org/bot<TU_TOKEN>/getUpdates`
3. Busca `"chat":{"id":123456789}`

---

## Uso Básico

### Inicialización

```php
use Micro\Services\TelegramService;

// Configuración automática desde .env
$telegram = new TelegramService();

// Configuración manual (sobrescribe .env)
$telegram = new TelegramService(
    'TU_BOT_TOKEN',
    'TU_CHAT_ID',
    15 // timeout en segundos (opcional, default: 10)
);
```

### Verificar Configuración

```php
if ($telegram->isConfigured()) {
    echo "✅ Telegram configurado correctamente";
} else {
    echo "❌ Telegram NO configurado. Revisa tu .env";
}
```

---

## Métodos Disponibles

### `send(string $message, ?string $chatId = null)`

Envía un mensaje simple de texto plano.

```php
$telegram->send("Hola desde PHP!");

// Con saltos de línea
$telegram->send("Primera línea\nSegunda línea\nTercera línea");
```

**Retorna:** `int|false` - ID del mensaje o `false` si falla

---

### `sendMarkdown(string $message, ?string $chatId = null)`

Envía un mensaje con formato Markdown.

```php
$telegram->sendMarkdown(
    "*Texto en negrita*\n" .
    "_Texto en cursiva_\n" .
    "`Código inline`\n" .
    "```\nBloque de código\n```"
);
```

**Retorna:** `int|false` - ID del mensaje o `false` si falla

---

### `sendHTML(string $message, ?string $chatId = null)`

Envía un mensaje con formato HTML.

```php
$telegram->sendHTML(
    "<b>Texto en negrita</b>\n" .
    "<i>Texto en cursiva</i>\n" .
    "<code>Código inline</code>\n" .
    "<a href='https://example.com'>Enlace</a>"
);
```

**Retorna:** `int|false` - ID del mensaje o `false` si falla

---

### `sendError(string $error, array $context = [])`

Envía un mensaje de error pre-formateado con contexto.

```php
// Error simple
$telegram->sendError("No se pudo conectar a la base de datos");

// Error con contexto
$telegram->sendError(
    "Error al procesar pago",
    [
        'domain' => $_SERVER['HTTP_HOST'],
        'usuario_id' => 1234,
        'monto' => '$100.00',
        'metodo_pago' => 'tarjeta'
    ]
);
```

**Formato del mensaje:**
```
❌ Error

Dominio: `example.com`
Fecha/Hora: `2026-01-28 10:30:00`
Error: `No se pudo conectar a la base de datos`
[... contexto adicional ...]
```

**Retorna:** `int|false` - ID del mensaje o `false` si falla

---

### `sendSuccess(string $title, array $details = [])`

Envía un mensaje de éxito pre-formateado con detalles.

```php
$telegram->sendSuccess(
    "Deployment Completado",
    [
        'rama' => 'main',
        'commits' => '5 nuevos commits',
        'archivos_modificados' => '12',
        'tiempo' => '3.2 segundos'
    ]
);
```

**Formato del mensaje:**
```
✅ Deployment Completado

Rama: `main`
Commits: `5 nuevos commits`
Archivos modificados: `12`
Tiempo: `3.2 segundos`
```

**Retorna:** `int|false` - ID del mensaje o `false` si falla

---

### `getBotInfo()`

Obtiene información sobre el bot de Telegram.

```php
$botInfo = $telegram->getBotInfo();
if ($botInfo) {
    echo "Username: " . $botInfo['username'];
    echo "ID: " . $botInfo['id'];
}
```

**Retorna:** `array|false` - Información del bot o `false` si falla

---

## Mensajes con Formato

### Markdown

#### Elementos básicos

```php
$telegram->sendMarkdown(
    "*Negrita* _Cursiva_ `Código` [Enlace](https://example.com)"
);
```

#### Mensaje estructurado

```php
$telegram->sendMarkdown(
    "*🚀 Deployment Exitoso*\n\n" .
    "*Servidor:* `produccion.example.com`\n" .
    "*Rama:* `main`\n" .
    "*Fecha:* `" . date('Y-m-d H:i:s') . "`\n" .
    "*Estado:* ✅ Completado"
);
```

#### Listas

```php
$telegram->sendMarkdown(
    "*📋 Tareas Completadas:*\n" .
    "• Base de datos actualizada\n" .
    "• Cache limpiado\n" .
    "• Servicios reiniciados\n" .
    "• Tests ejecutados ✅"
);
```

#### Bloques de código

```php
$telegram->sendMarkdown(
    "```php\n" .
    "function hello() {\n" .
    "    return 'Hello World';\n" .
    "}\n" .
    "```"
);
```

### HTML

```php
$telegram->sendHTML(
    "<b>Negrita</b> <i>Cursiva</i> <code>Código</code>\n" .
    "<a href='https://example.com'>Enlace</a>"
);
```

---

## Notificaciones Pre-formateadas

### Errores con Try-Catch

```php
try {
    // Tu código aquí
    procesarPago($datos);
} catch (Exception $e) {
    $telegram->sendError(
        $e->getMessage(),
        [
            'archivo' => $e->getFile(),
            'linea' => $e->getLine(),
            'usuario' => $_SESSION['usuario'] ?? 'Anónimo'
        ]
    );
}
```

### Notificaciones de Procesos Batch

```php
$items = ['item1', 'item2', 'item3'];
$procesados = 0;
$errores = 0;

foreach ($items as $item) {
    try {
        procesarItem($item);
        $procesados++;
    } catch (Exception $e) {
        $errores++;
    }
}

$telegram->sendSuccess(
    "Proceso Batch Completado",
    [
        'total_items' => count($items),
        'procesados' => $procesados,
        'errores' => $errores,
        'tasa_exito' => round(($procesados / count($items)) * 100, 2) . '%'
    ]
);
```

---

## Casos de Uso Reales

### 1. Notificar Nuevo Usuario

```php
function notificarNuevoUsuario($usuario) {
    $telegram = new TelegramService();
    $telegram->sendSuccess(
        "Nuevo Usuario Registrado",
        [
            'nombre' => $usuario['nombre'],
            'email' => $usuario['email'],
            'fecha' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A'
        ]
    );
}
```

### 2. Cambios en Configuración Crítica

```php
function notificarCambioConfiguracion($config, $valorAnterior, $valorNuevo) {
    $telegram = new TelegramService();
    $telegram->sendMarkdown(
        "*⚠️ Cambio en Configuración Crítica*\n\n" .
        "*Parámetro:* `{$config}`\n" .
        "*Valor Anterior:* `{$valorAnterior}`\n" .
        "*Valor Nuevo:* `{$valorNuevo}`\n" .
        "*Usuario:* `" . ($_SESSION['usuario'] ?? 'Sistema') . "`\n" .
        "*Fecha:* `" . date('Y-m-d H:i:s') . "`"
    );
}
```

### 3. Monitoreo de Recursos

```php
function monitorearRecursos() {
    $telegram = new TelegramService();
    
    $cpuUsage = sys_getloadavg()[0];
    $memoryUsage = memory_get_usage(true) / 1024 / 1024;
    
    if ($cpuUsage > 80 || $memoryUsage > 512) {
        $telegram->sendError(
            "Recursos del Servidor Críticos",
            [
                'cpu_load' => round($cpuUsage, 2) . '%',
                'memoria_mb' => round($memoryUsage, 2) . ' MB',
                'servidor' => php_uname('n')
            ]
        );
    }
}
```

### 4. Resultados de Tareas Programadas (Cron)

```php
function notificarTareaProgramada($nombreTarea, $resultado, $duracion) {
    $telegram = new TelegramService();
    
    if ($resultado['success']) {
        $telegram->sendSuccess(
            "Tarea Programada: {$nombreTarea}",
            [
                'duracion' => $duracion . ' segundos',
                'registros_procesados' => $resultado['registros'],
                'siguiente_ejecucion' => $resultado['proxima']
            ]
        );
    } else {
        $telegram->sendError(
            "Fallo en Tarea: {$nombreTarea}",
            [
                'error' => $resultado['error'],
                'duracion' => $duracion . ' segundos'
            ]
        );
    }
}
```

### 5. Notificación de Git Deployment

```php
$telegram = new TelegramService();

$telegram->sendMarkdown(
    "*🚀 Sincronización con Remoto 🚀*\n\n" .
    "*Dominio:* `" . $_SERVER['HTTP_HOST'] . "`\n" .
    "*Rama:* `main`\n" .
    "*Fecha/Hora:* `" . date('Y-m-d H:i:s') . "`\n" .
    "*Origen:* `GitLab Webhook`\n\n" .
    "*Resultados:*\n" .
    "Fetch: `✅ Exitoso`\n" .
    "Reset Hard: `✅ Exitoso`"
);
```

---

## Mejores Prácticas

### ✅ Hacer

1. **Usar métodos pre-formateados** cuando sea posible
   ```php
   // Preferir esto:
   $telegram->sendError("Error en BD", ['tabla' => 'usuarios']);
   
   // En lugar de:
   $telegram->sendMarkdown("*❌ Error*\n*Error:* `Error en BD`...");
   ```

2. **Incluir contexto relevante** en las notificaciones
   ```php
   $telegram->sendError($e->getMessage(), [
       'domain' => $_SERVER['HTTP_HOST'],
       'usuario' => $_SESSION['user_id'],
       'fecha' => date('Y-m-d H:i:s')
   ]);
   ```

3. **Usar backticks** para valores variables en Markdown
   ```php
   "*Usuario:* `{$username}`"  // ✅ Correcto
   "*Usuario:* {$username}"    // ❌ Sin formato
   ```

4. **Usar emojis** para mejor legibilidad
   ```php
   "✅ Éxito", "❌ Error", "⚠️ Advertencia", "🚀 Deployment", "📊 Reporte"
   ```

5. **Limitar tamaño de stack traces**
   ```php
   $trace = substr($e->getTraceAsString(), 0, 500) . '... (truncado)';
   ```

### ❌ No Hacer

1. **No enviar mensajes muy largos** (límite de Telegram: 4096 caracteres)
   ```php
   // Limitar mensajes largos
   $mensaje = substr($mensajeLargo, 0, 4000) . "... (truncado)";
   ```

2. **No enviar notificaciones en bucles intensivos**
   ```php
   // ❌ Mal: Notificar cada iteración
   foreach ($items as $item) {
       $telegram->send("Procesando {$item}");
   }
   
   // ✅ Bien: Notificar resumen al final
   $telegram->sendSuccess("Proceso completado", [
       'items_procesados' => count($items)
   ]);
   ```

3. **No exponer información sensible**
   ```php
   // ❌ Nunca enviar:
   - Contraseñas
   - Tokens de API
   - Números de tarjeta
   - Claves privadas
   ```

4. **No usar Markdown sin escapar caracteres especiales**
   ```php
   // ❌ Puede romper el formato
   $telegram->sendMarkdown("Usuario: {$user_with_underscores}");
   
   // ✅ Usar dentro de backticks
   $telegram->sendMarkdown("Usuario: `{$user_with_underscores}`");
   ```

### Escapar Caracteres Especiales en Markdown

Si necesitas usar texto del usuario en Markdown fuera de backticks:

```php
function escaparMarkdown($texto) {
    $caracteres = ['_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!'];
    foreach ($caracteres as $char) {
        $texto = str_replace($char, '\\' . $char, $texto);
    }
    return $texto;
}

// Usar
$nombreUsuario = "Usuario_con_guiones_bajos";
$telegram->sendMarkdown("Nuevo usuario: " . escaparMarkdown($nombreUsuario));
```

### Manejo de Errores Silencioso

El servicio maneja errores internamente y registra en logs. No lanza excepciones:

```php
// No necesitas try-catch para el servicio
$telegram->send("Mensaje"); // Retorna false si falla, no lanza excepción

// Pero puedes verificar el resultado
if ($telegram->send("Mensaje") === false) {
    Log::warning("No se pudo enviar notificación de Telegram");
}
```

---

## Ejemplos Avanzados

### Notificación Condicional por Entorno

```php
$telegram = new TelegramService();

// Solo notificar en producción
if (getenv('APP_ENV') === 'production') {
    $telegram->sendError("Error crítico detectado");
}
```

### Múltiples Canales

```php
// Bot configurado en .env
$telegram = new TelegramService();

// Enviar a canal principal
$telegram->send("Mensaje general");

// Enviar a canal de alertas específico
$telegram->send("Alerta crítica", 'CHAT_ID_ALERTAS');
```

### Notificación con Timeout Personalizado

```php
// Para operaciones que pueden tardar
$telegram = new TelegramService(null, null, 30); // 30 segundos timeout
$telegram->sendMarkdown($mensajeMuyLargo);
```

---

## Troubleshooting

### El mensaje no se envía

1. Verificar configuración en `.env`:
   ```php
   $telegram = new TelegramService();
   var_dump($telegram->isConfigured()); // debe ser true
   ```

2. Verificar información del bot:
   ```php
   $botInfo = $telegram->getBotInfo();
   print_r($botInfo);
   ```

3. Revisar logs en `logs/`:
   ```bash
   tail -f logs/app.log | grep "TelegramService"
   ```

### Formato Markdown roto

- Asegúrate de escapar caracteres especiales
- Usa backticks para texto del usuario: `` `{$variable}` ``
- Verifica que los bloques de código estén correctamente cerrados

### Timeout errors

- Incrementa el timeout en el constructor:
  ```php
  new TelegramService(null, null, 30); // 30 segundos
  ```

---

## Referencias

- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Markdown en Telegram](https://core.telegram.org/bots/api#markdown-style)
- [HTML en Telegram](https://core.telegram.org/bots/api#html-style)
- [Crear un Bot en Telegram](https://core.telegram.org/bots#6-botfather)

---

**Ubicación de la clase:** `app/Services/TelegramService.php`

**Namespace:** `Micro\Services\TelegramService`
