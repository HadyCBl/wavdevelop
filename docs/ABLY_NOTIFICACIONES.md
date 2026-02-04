# 📡 Sistema de Notificaciones en Tiempo Real con Ably

> **Documentación completa del sistema de notificaciones en tiempo real para aplicaciones PHP con Vite**

## 📋 Tabla de Contenidos

1. [Introducción](#-introducción)
2. [Instalación](#-instalación)
3. [Configuración](#-configuración)
4. [Inicio Rápido](#-inicio-rápido)
5. [Backend PHP](#-backend-php)
6. [Frontend JavaScript](#-frontend-javascript)
7. [Casos de Uso](#-casos-de-uso)
8. [API Reference](#-api-reference)
9. [Troubleshooting](#-troubleshooting)

---

## 🎯 Introducción

El sistema de notificaciones utiliza **Ably** para comunicación en tiempo real entre backend PHP y frontend JavaScript, proporcionando:

### Características

✅ **Notificaciones en tiempo real** - Toast, alertas, actualizaciones  
✅ **Integración con huella digital** - Sistema biométrico existente  
✅ **Recarga automática de datos** - Actualiza tablas DataTable automáticamente  
✅ **Multi-canal** - Usuario, agencia, broadcast  
✅ **Reconexión automática** - Manejo robusto de errores  
✅ **Sistema de tokens** - Seguridad y permisos granulares  

### Arquitectura

```
┌──────────────────┐         ┌──────────────┐         ┌──────────────────┐
│   Backend PHP    │ ─────▶  │  Ably Cloud  │ ─────▶  │ Frontend JS      │
│                  │         │  (WebSocket) │         │                  │
│ AblyService      │         │              │         │ AblyNotification │
│ Notification     │         │              │         │ Helper           │
│ Service          │         │              │         │                  │
└──────────────────┘         └──────────────┘         └──────────────────┘
```

### Componentes

| Componente | Ubicación | Descripción |
|------------|-----------|-------------|
| **AblyService** | `App\Generic\AblyService` | Servicio PHP de bajo nivel |
| **NotificationService** | `Micro\Helpers\NotificationService` | Helper PHP simplificado |
| **AblyNotificationHelper** | `includes/js/AblyNotificationHelper.js` | Helper JavaScript |
| **Auto-init** | `includes/js/vite_another.js` | Inicialización automática con Vite |

---

## 📦 Instalación

### 1. Instalar Dependencias

```bash
cd www
npm install
```

El paquete `ably: ^2.4.0` ya está incluido en `package.json`.

### 2. Variables de Entorno

Agrega en `.env`:

```env
# REQUERIDO
ABLY_API_KEY=tu_api_key_completa
ABLY_CLIENT_KEY=tu_client_key

# OPCIONAL
ABLY_CHANNEL_PREFIX=app
ABLY_CHANNEL_HUELLA=huella
```

### 3. Obtener API Keys

1. Ve a [https://ably.com/dashboard](https://ably.com/dashboard)
2. Crea una cuenta o inicia sesión
3. Crea una nueva app
4. En "API Keys":
   - **Root Key** → Copia completa para `ABLY_API_KEY`
   - **New Key** → Crea con permisos `subscribe` y `history` para `ABLY_CLIENT_KEY`

---

## ⚙️ Configuración

### En tus Vistas PHP

Agrega en el `<head>` de tus páginas:

```php
<?php
use Micro\Helpers\NotificationService;

$notification = NotificationService::getInstance();
$ablyConfig = $notification->getClientConfig();
?>

<!-- Configuración de Ably -->
<script>
    window.ablyConfig = <?= json_encode($ablyConfig) ?>;
    window.currentUserId = <?= $_SESSION['id'] ?? 'null' ?>;
    window.currentAgencyId = <?= $_SESSION['id_agencia'] ?? 'null' ?>;
    window.ENVIRONMENT = '<?= ENVIRONMENT ?? 'production' ?>';
</script>
```

### Cargar Bundle de Vite

```php
<!-- Desarrollo -->
<?php if (ENVIRONMENT === 'development'): ?>
    <script type="module" src="http://localhost:5173/@vite/client"></script>
    <script type="module" src="http://localhost:5173/includes/js/vite_another.js"></script>
<?php else: ?>
    <!-- Producción -->
    <?php
    $manifest = json_decode(file_get_contents(__DIR__ . '/public/assets/vite-dist/manifest.json'), true);
    $entry = $manifest['includes/js/vite_another.js'];
    ?>
    <link rel="stylesheet" href="/public/assets/vite-dist/<?= $entry['css'][0] ?>">
    <script type="module" src="/public/assets/vite-dist/<?= $entry['file'] ?>"></script>
<?php endif; ?>
```

### Indicador de Estado (Opcional)

```html
<div class="connection-status">
    <span id="ably-status" class="px-3 py-1 rounded-full text-xs bg-gray-500 text-white">
        Inicializando...
    </span>
</div>
```

---

## 🚀 Inicio Rápido

### Backend: Enviar Notificación

```php
<?php
use Micro\Helpers\NotificationService;

$notification = NotificationService::getInstance();

// Notificación simple
$notification->success(
    userId: 123,
    message: 'Préstamo aprobado exitosamente'
);

// Con datos adicionales
$notification->success(
    userId: 123,
    message: 'Pago procesado',
    additionalData: [
        'amount' => 1500.00,
        'receiptId' => 'REC-001'
    ]
);
```

### Frontend: Ver Notificación

El sistema ya está inicializado automáticamente con Vite. Las notificaciones aparecerán como **toast con Toastr** (o SweetAlert2 si Toastr no está disponible).

### Verificar Estado

```javascript
// En la consola del navegador
console.log('Conectado:', ablyHelper.isConnected());
console.log('Config:', window.ablyConfig);

// Probar notificación manual
notificationHelper.showToast({
    type: 'success',
    message: 'Prueba exitosa'
});
```

---

## 🔧 Backend PHP

### NotificationService (Recomendado)

Helper de alto nivel para casos comunes.

#### Métodos Principales

```php
use Micro\Helpers\NotificationService;

$notification = NotificationService::getInstance();

// Notificaciones por tipo
$notification->success($userId, 'Operación exitosa');
$notification->error($userId, 'Error al procesar');
$notification->warning($userId, 'Acción requerida');
$notification->info($userId, 'Información importante');

// Actualización de datos (recarga tablas automáticamente)
$notification->dataUpdated(
    userId: $userId,
    entity: 'prestamo',      // Debe coincidir con tableMap en frontend
    entityId: 123,
    changes: ['status' => 'aprobado']
);

// Alertar a múltiples usuarios
$adminIds = [101, 102, 103];
$notification->alertUsers(
    userIds: $adminIds,
    message: 'Sistema en mantenimiento',
    severity: 'critical'
);

// Notificar a agencia completa
$notification->notifyAgency(
    agencyId: 5,
    message: 'Nueva política vigente'
);

// Broadcast global
$notification->broadcast(
    message: 'Mantenimiento programado',
    type: NotificationService::TYPE_WARNING
);
```

#### Constantes Disponibles

```php
// Tipos
NotificationService::TYPE_SUCCESS
NotificationService::TYPE_ERROR
NotificationService::TYPE_WARNING
NotificationService::TYPE_INFO

// Eventos
NotificationService::EVENT_USER_LOGGED_IN
NotificationService::EVENT_USER_LOGGED_OUT
NotificationService::EVENT_DATA_UPDATED
NotificationService::EVENT_NEW_MESSAGE
NotificationService::EVENT_TASK_COMPLETED
NotificationService::EVENT_ALERT
```

### AblyService (Bajo Nivel)

Para casos avanzados.

```php
use App\Generic\AblyService;

$ably = AblyService::getInstance();

if ($ably === null) {
    // Ably no está configurado
    return;
}

// Publicar mensaje personalizado
$ably->publish(
    channelName: 'custom:channel',
    eventName: 'custom_event',
    data: ['foo' => 'bar']
);

// Historial de canal
$history = $ably->getChannelHistory('app:user:123', limit: 20);

// Generar token temporal
$token = $ably->generateClientToken(
    capability: ['app:user:123' => ['subscribe', 'history']],
    ttl: 3600
);
```

---

## 💻 Frontend JavaScript

### Variables Globales Disponibles

Después de que Vite carga, tienes acceso a:

```javascript
// Helper de Ably
window.ablyHelper.isConnected()
window.ablyHelper.subscribeToUser(callback)
window.ablyHelper.getHistory('app:user:123')
window.ablyHelper.close()

// Helper de notificaciones
window.notificationHelper.showToast({ type: 'success', message: 'OK' })
window.notificationHelper.playSound()
window.notificationHelper.reloadDataTable('miTabla')
```

### Uso Manual (Si necesitas control personalizado)

```javascript
import AblyNotificationHelper, { NotificationHelper } from './includes/js/AblyNotificationHelper.js';

// Crear instancia
const ably = new AblyNotificationHelper({
    clientKey: window.ablyConfig.clientKey,
    userId: window.currentUserId,
    agencyId: window.currentAgencyId,
    debug: true
});

// Suscribirse a notificaciones del usuario
ably.subscribeToUser(
    // Notificaciones generales
    (data) => {
        NotificationHelper.showToast(data);
    },
    // Actualizaciones de datos
    (data) => {
        NotificationHelper.reloadDataTable(`${data.entity}Table`);
    },
    // Alertas
    (data) => {
        Swal.fire('Alerta', data.message, data.severity);
    }
);

// Suscribirse a broadcast
ably.subscribeToBroadcast((data) => {
    console.log('Broadcast:', data);
});

// Eventos de conexión
ably.on('connected', () => console.log('Conectado'));
ably.on('disconnected', () => console.log('Desconectado'));
```

### NotificationHelper API

```javascript
// Mostrar toast (Toastr por defecto)
NotificationHelper.showToast({
    type: 'success',      // success, error, warning, info
    message: 'Mensaje',
    duration: 5000,       // Opcional (ms)
    useSwal: false        // true para forzar SweetAlert2
});

// Reproducir sonido
NotificationHelper.playSound('/assets/sounds/notification.mp3');

// Recargar tabla DataTable
NotificationHelper.reloadDataTable('clientesTable');
```

### Configuración de Tablas Auto-recargables

Edita `includes/js/vite_another.js` alrededor de la línea 1835:

```javascript
const tableMap = {
    'cliente': 'clientesTable',
    'prestamo': 'prestamosTable',
    'pago': 'pagosTable',
    'caja': 'cajaTable',
    'ahorro': 'ahorrosTable',
    // Agregar tus tablas aquí
    'usuario': 'usuariosTable',
    'reporte': 'reportesTable'
};
```

Cuando envíes `dataUpdated` desde PHP con `entity: 'cliente'`, la tabla con ID `clientesTable` se recargará automáticamente.

---

## 📚 Casos de Uso

### Caso 1: Aprobar Préstamo

**Backend:**
```php
<?php
class PrestamoController
{
    public function aprobar($prestamoId, $userId)
    {
        $notification = NotificationService::getInstance();
        
        // Lógica de aprobación...
        $prestamo->estado = 'aprobado';
        $prestamo->save();
        
        // Notificar al cliente
        $notification->success(
            userId: $userId,
            message: "Préstamo #{$prestamoId} aprobado",
            additionalData: [
                'prestamoId' => $prestamoId,
                'monto' => $prestamo->monto
            ]
        );
        
        // Actualizar tabla en frontend
        $notification->dataUpdated(
            userId: $userId,
            entity: 'prestamo',
            entityId: $prestamoId,
            changes: ['estado' => 'aprobado']
        );
    }
}
```

**Frontend:**
El usuario verá automáticamente:
1. Toast de éxito "Préstamo #123 aprobado"
2. La tabla de préstamos se recargará automáticamente
3. Sonido de notificación (si está habilitado)

### Caso 2: Alerta a Administradores

```php
<?php
// Detectar fraude o actividad sospechosa
$adminIds = [101, 102, 103];

$notification->alertUsers(
    userIds: $adminIds,
    message: 'Actividad sospechosa detectada en la cuenta #456',
    severity: 'critical',
    additionalData: [
        'accountId' => 456,
        'reason' => 'Múltiples intentos de login fallidos'
    ]
);
```

### Caso 3: Mantenimiento Programado

```php
<?php
// Notificar a todos los usuarios
$notification->broadcast(
    message: 'El sistema estará en mantenimiento de 2:00 AM a 4:00 AM',
    type: NotificationService::TYPE_WARNING,
    additionalData: [
        'maintenanceStart' => '2026-01-15 02:00:00',
        'maintenanceEnd' => '2026-01-15 04:00:00'
    ]
);
```

### Caso 4: Actualización de Cliente

```php
<?php
class ClienteController
{
    public function actualizar($clienteId, $nuevosDatos)
    {
        $notification = NotificationService::getInstance();
        
        $cliente = Cliente::find($clienteId);
        $cliente->update($nuevosDatos);
        
        // Notificar actualización
        $notification->dataUpdated(
            userId: $cliente->id_usuario,
            entity: 'cliente',
            entityId: $clienteId,
            changes: $nuevosDatos
        );
    }
}
```

### Caso 5: Notificación con SweetAlert2

```php
<?php
// Forzar SweetAlert2 en lugar de Toastr
$notification->success(
    userId: $userId,
    message: 'Pago procesado correctamente',
    additionalData: [
        'useSwal' => true,  // ← Forzar SweetAlert2
        'amount' => 1500
    ]
);
```

---

## 🔍 API Reference

### Backend PHP

#### NotificationService

| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `getInstance()` | - | `NotificationService` | Obtiene instancia |
| `success($userId, $message, $data=[])` | int, string, array | bool | Notificación de éxito |
| `error($userId, $message, $data=[])` | int, string, array | bool | Notificación de error |
| `warning($userId, $message, $data=[])` | int, string, array | bool | Notificación de advertencia |
| `info($userId, $message, $data=[])` | int, string, array | bool | Notificación informativa |
| `dataUpdated($userId, $entity, $id, $changes=[])` | int, string, mixed, array | bool | Actualización de datos |
| `alertUsers($userIds, $message, $severity, $data=[])` | array, string, string, array | array | Alerta múltiple |
| `notifyAgency($agencyId, $message, $type, $data=[])` | int, string, string, array | bool | Notificación de agencia |
| `broadcast($message, $type, $data=[])` | string, string, array | bool | Broadcast global |
| `isEnabled()` | - | bool | Verifica si está habilitado |
| `getClientConfig()` | - | array | Config para frontend |

#### AblyService

| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `getInstance()` | - | `AblyService\|null` | Obtiene instancia |
| `notifyUser($userId, $event, $data)` | mixed, string, array | bool | Notificar usuario |
| `notifyUsers($userIds, $event, $data)` | array, string, array | array | Notificar múltiples |
| `notifyAgency($agencyId, $event, $data)` | mixed, string, array | bool | Notificar agencia |
| `broadcast($event, $data)` | string, array | bool | Broadcast |
| `publish($channel, $event, $data)` | string, string, mixed | bool | Publicar mensaje |
| `getChannelHistory($channel, $limit=10)` | string, int | array | Obtener historial |
| `generateClientToken($capability=[], $ttl=3600)` | array, int | array\|null | Generar token |
| `isEnabled()` | - | bool | Verifica estado |
| `getClientConfig()` | - | array | Config para cliente |

### Frontend JavaScript

#### AblyNotificationHelper

| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `constructor(config)` | object | - | Crea instancia |
| `subscribeToUser(onNotif, onData, onAlert)` | fn, fn, fn | Channel | Suscribir a usuario |
| `subscribeToAgency(callback)` | function | Channel | Suscribir a agencia |
| `subscribeToBroadcast(callback)` | function | Channel | Suscribir a broadcast |
| `subscribeToChannel(channel, event, callback)` | str, str, fn | Channel | Suscripción custom |
| `publish(channel, event, data)` | str, str, obj | Promise | Publicar mensaje |
| `getHistory(channel, options={})` | str, obj | Promise | Obtener historial |
| `unsubscribe(channel, event=null)` | str, str | void | Desuscribir |
| `unsubscribeAll()` | - | void | Desuscribir todos |
| `isConnected()` | - | boolean | Estado de conexión |
| `getConnectionState()` | - | string | Estado actual |
| `close()` | - | void | Cerrar conexión |
| `on(event, callback)` | str, fn | void | Escuchar evento |

#### NotificationHelper

| Método | Parámetros | Retorno | Descripción |
|--------|------------|---------|-------------|
| `showToast(data)` | object | void | Mostrar toast |
| `playSound(path='/assets/sounds/notification.mp3')` | string | void | Reproducir sonido |
| `reloadDataTable(tableId)` | string | void | Recargar tabla |

---

## 🎨 Personalización

### Mapeo de Entidades a Tablas

En `includes/js/vite_another.js`:

```javascript
const tableMap = {
    'cliente': 'clientesTable',
    'prestamo': 'prestamosTable',
    // Agregar más...
};
```

### Acciones Personalizadas por Tipo

En `includes/js/vite_another.js`:

```javascript
ably.subscribeToUser(
    (data) => {
        // Personalizar según tipo
        if (data.type === 'success') {
            // Animación especial
            confetti();
        }
        
        NotificationHelper.showToast(data);
    },
    // ...
);
```

### Deshabilitar Sonidos

```php
<?php
$_SESSION['enable_notifications_sound'] = false;
?>

<script>
window.ablyConfig.enableSound = false;
</script>
```

---

## 🚨 Troubleshooting

### Las notificaciones no aparecen

**Verificar:**

```javascript
console.log('Config:', window.ablyConfig);
console.log('User ID:', window.currentUserId);
console.log('Helper:', window.ablyHelper);
console.log('Conectado:', window.ablyHelper?.isConnected());
```

**Soluciones:**
1. Verifica que `ABLY_CLIENT_KEY` esté en `.env`
2. Verifica que `$_SESSION['id']` esté disponible
3. Verifica que Toastr o SweetAlert2 estén cargados
4. Revisa la consola de errores del navegador

### Error "Cannot find module 'ably'"

```bash
npm install
```

### Error en producción

```bash
npm run vite:clean
npm run vite:build
```

### Ably no se conecta

**Verificar en consola:**
```javascript
ablyHelper.getConnectionState() // debe ser 'connected'
```

**Causas comunes:**
- API Key incorrecta
- Problemas de red/firewall
- Permisos insuficientes del token

### Notificaciones duplicadas

Desuscribir antes de volver a suscribir:

```javascript
ablyHelper.unsubscribe('app:user:123');
ablyHelper.subscribeToUser(callback);
```

---

## 📁 Estructura de Archivos

```
www/
├── app/
│   ├── Generic/
│   │   └── AblyService.php              # Servicio PHP principal
│   └── Helpers/
│       └── NotificationService.php      # Helper PHP simplificado
├── includes/js/
│   ├── AblyNotificationHelper.js        # Helper JavaScript
│   └── vite_another.js                  # Auto-init (líneas 14-16, 1800+)
├── examples/
│   ├── ably-vite-example.php            # Ejemplo completo
│   └── notification_usage_examples.php  # Ejemplos PHP
├── snippets/
│   └── ably-config-snippet.php          # Snippet para copiar
├── docs/
│   └── ABLY_NOTIFICACIONES.md          # Esta documentación
└── package.json                         # Dependencia ably: ^2.4.0
```

---

## 🔐 Nomenclatura de Canales

| Tipo | Formato | Ejemplo | Uso |
|------|---------|---------|-----|
| Usuario | `{prefix}:user:{userId}` | `app:user:123` | Notificaciones personales |
| Agencia | `{prefix}:agency:{agencyId}` | `app:agency:5` | Notificaciones de agencia |
| Broadcast | `{prefix}:broadcast` | `app:broadcast` | Mensajes globales |
| Huella | `{channelHuella}_{deviceId}` | `huella_SRN12345` | Dispositivos biométricos |

---

## ✨ Próximos Pasos

1. **Configurar API keys** en `.env`
2. **Agregar snippet** de configuración en tus vistas PHP
3. **Enviar tu primera notificación** desde backend
4. **¡Ver la magia suceder!** 🎉

---

## 📞 Soporte

- **Documentación:** Este archivo
- **Ejemplos:** `examples/ably-vite-example.php`
- **Helper JS:** `includes/js/AblyNotificationHelper.js`
- **Dashboard Ably:** [https://ably.com/dashboard](https://ably.com/dashboard)

---

**Versión:** 2.0.0  
**Última actualización:** 10 de enero de 2026  
**Autor:** Sotecpro
