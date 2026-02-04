# Sistema de Auditoría de Usuarios con MongoDB

## Descripción

Este sistema permite llevar un registro detallado de todas las acciones que realizan los usuarios en la aplicación usando MongoDB como base de datos NoSQL.

## Instalación

### 1. Instalar la dependencia de MongoDB

```bash
composer require mongodb/mongodb
```

### 2. Configurar variables de entorno

Copia las variables del archivo `.env.example` a tu archivo `.env` y configura según tu entorno:

```env
# ========================================
# AUDITORÍA DE USUARIOS (MongoDB)
# ========================================
# Activar registro de acciones: true | false
USER_ACTIVITY_LOG_ENABLED=true

# Configuración MongoDB
MONGODB_HOST=mongodb
MONGODB_PORT=27017
MONGODB_DATABASE=user_activities
MONGODB_USERNAME=admin
MONGODB_PASSWORD=secret123
MONGODB_AUTH_SOURCE=admin

# Nombre de la colección para actividades
MONGODB_COLLECTION=activities
```

### 3. Configurar Docker (opcional)

Si usas Docker, agrega MongoDB a tu `docker-compose.yml`:

```yaml
services:
  mongodb:
    image: mongo:7.0
    container_name: microsystem_mongodb
    restart: unless-stopped
    environment:
      MONGO_INITDB_ROOT_USERNAME: admin
      MONGO_INITDB_ROOT_PASSWORD: secret123
    ports:
      - "27017:27017"
    volumes:
      - ./mongodb_data:/data/db
    networks:
      - microsystem_network

  # También agrega mongo-express para administración visual (opcional)
  mongo-express:
    image: mongo-express:latest
    container_name: microsystem_mongo_express
    restart: unless-stopped
    ports:
      - "8081:8081"
    environment:
      ME_CONFIG_MONGODB_ADMINUSERNAME: admin
      ME_CONFIG_MONGODB_ADMINPASSWORD: secret123
      ME_CONFIG_MONGODB_URL: mongodb://admin:secret123@mongodb:27017/
    depends_on:
      - mongodb
    networks:
      - microsystem_network
```

## Uso Básico

### Importar la clase

```php
use Micro\Generic\UserActivityLogger;
```

### Registrar una actividad

```php
// Ejemplo simple
UserActivityLogger::log('login', 'auth');

// Con datos adicionales
UserActivityLogger::log(
    'create',                    // Acción
    'clientes',                  // Módulo
    [                           // Datos adicionales
        'cliente_id' => 123,
        'nombre' => 'Juan Pérez',
        'tipo' => 'persona_fisica'
    ]
);

// Especificando el usuario (útil en procesos automáticos)
UserActivityLogger::log(
    'delete',
    'creditos',
    ['credito_id' => 456],
    $userId  // ID del usuario
);
```

## Ejemplos de Uso por Módulo

### 1. Login/Logout

```php
// Login exitoso
if ($loginSuccessful) {
    UserActivityLogger::log('login', 'auth', [
        'username' => $username,
        'method' => 'password'
    ]);
}

// Login fallido
if ($loginFailed) {
    UserActivityLogger::log('login_failed', 'auth', [
        'username' => $username,
        'reason' => 'invalid_credentials'
    ]);
}

// Logout
UserActivityLogger::log('logout', 'auth');
```

### 2. CRUD de Clientes

```php
// Crear cliente
UserActivityLogger::log('create', 'clientes', [
    'cliente_id' => $clienteId,
    'nombre' => $nombre,
    'tipo_persona' => $tipoPersona
]);

// Actualizar cliente
UserActivityLogger::log('update', 'clientes', [
    'cliente_id' => $clienteId,
    'campos_modificados' => ['telefono', 'direccion'],
    'valores_anteriores' => $valoresAnteriores
]);

// Eliminar cliente
UserActivityLogger::log('delete', 'clientes', [
    'cliente_id' => $clienteId,
    'nombre' => $nombre
]);

// Ver cliente
UserActivityLogger::log('view', 'clientes', [
    'cliente_id' => $clienteId
]);
```

### 3. Gestión de Créditos

```php
// Aprobar crédito
UserActivityLogger::log('approve', 'creditos', [
    'credito_id' => $creditoId,
    'monto' => $monto,
    'cliente_id' => $clienteId
]);

// Rechazar crédito
UserActivityLogger::log('reject', 'creditos', [
    'credito_id' => $creditoId,
    'motivo' => $motivo
]);

// Registrar pago
UserActivityLogger::log('payment', 'creditos', [
    'credito_id' => $creditoId,
    'monto_pagado' => $montoPago,
    'forma_pago' => $formaPago
]);
```

### 4. Reportes

```php
// Generación de reportes
UserActivityLogger::log('generate_report', 'reportes', [
    'tipo_reporte' => 'cartera_vencida',
    'formato' => 'pdf',
    'filtros' => $filtros
]);

// Exportación de datos
UserActivityLogger::log('export', 'reportes', [
    'tipo' => 'excel',
    'modulo' => 'clientes',
    'registros_exportados' => $cantidadRegistros
]);
```

### 5. Configuración del Sistema

```php
// Cambios de configuración
UserActivityLogger::log('config_change', 'admin', [
    'configuracion' => 'tasas_interes',
    'valor_anterior' => $valorAnterior,
    'valor_nuevo' => $valorNuevo
]);

// Gestión de usuarios
UserActivityLogger::log('user_create', 'admin', [
    'nuevo_usuario_id' => $nuevoUserId,
    'rol' => $rol
]);
```

## Consultar Actividades

### Obtener actividades de un usuario

```php
// Últimas 50 actividades del usuario actual
$actividades = UserActivityLogger::getUserActivities(
    $_SESSION['user_id'],
    50  // límite
);

// Con paginación
$actividades = UserActivityLogger::getUserActivities(
    $userId,
    20,    // límite
    40,    // skip (página 3: 20 * 2)
    ['module' => 'clientes']  // filtro adicional
);
```

### Obtener actividades por acción

```php
// Todos los inicios de sesión
$logins = UserActivityLogger::getByAction('login', 100);

// Todas las eliminaciones
$deletes = UserActivityLogger::getByAction('delete', 50);
```

### Obtener actividades por módulo

```php
// Todas las actividades del módulo de créditos
$creditosActivities = UserActivityLogger::getByModule('creditos', 100);
```

### Obtener actividades por rango de fechas

```php
// Actividades de la última semana
$startDate = date('Y-m-d H:i:s', strtotime('-7 days'));
$endDate = date('Y-m-d H:i:s');

$actividades = UserActivityLogger::getByDateRange(
    $startDate,
    $endDate,
    500  // límite
);

// Con filtros adicionales
$actividades = UserActivityLogger::getByDateRange(
    $startDate,
    $endDate,
    500,
    ['user_id' => $userId, 'module' => 'creditos']
);
```

### Contar actividades

```php
// Contar todas las actividades
$total = UserActivityLogger::count();

// Contar con filtros
$loginCount = UserActivityLogger::count(['action' => 'login']);
$userCount = UserActivityLogger::count(['user_id' => $userId]);
```

### Obtener estadísticas

```php
// Estadísticas generales
$stats = UserActivityLogger::getStatistics();

// Estadísticas de un usuario específico
$stats = UserActivityLogger::getStatistics($userId);

// Estadísticas en un rango de fechas
$stats = UserActivityLogger::getStatistics(
    null,  // todos los usuarios
    '2024-01-01 00:00:00',
    '2024-12-31 23:59:59'
);

// Resultado ejemplo:
// [
//     ['_id' => ['action' => 'login', 'module' => 'auth'], 'count' => 150],
//     ['_id' => ['action' => 'create', 'module' => 'clientes'], 'count' => 45],
//     ...
// ]
```

## Mantenimiento

### Purgar actividades antiguas

```php
// Eliminar actividades de más de 90 días
$eliminados = UserActivityLogger::purgeOldActivities(90);
echo "Se eliminaron {$eliminados} registros";

// Eliminar actividades de más de 30 días
$eliminados = UserActivityLogger::purgeOldActivities(30);
```

Puedes crear un cron job para ejecutar esto periódicamente:

```php
// scripts/purge_activities.php
<?php
require_once __DIR__ . '/../app/bootstrap.php';

use Micro\Generic\UserActivityLogger;

$daysToKeep = 90;
$deleted = UserActivityLogger::purgeOldActivities($daysToKeep);

echo date('Y-m-d H:i:s') . " - Registros eliminados: {$deleted}\n";
```

## Verificar si está habilitado

```php
if (UserActivityLogger::isEnabled()) {
    echo "El sistema de auditoría está activo";
} else {
    echo "El sistema de auditoría está deshabilitado";
}
```

## Crear una Página de Auditoría

```php
<?php
// views/admin/auditoria.php
require_once __DIR__ . '/../../app/bootstrap.php';

use Micro\Generic\UserActivityLogger;

// Verificar permisos de administrador
if (!isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
    header('Location: /');
    exit;
}

$page = $_GET['page'] ?? 1;
$limit = 50;
$skip = ($page - 1) * $limit;

// Obtener filtros
$userId = $_GET['user_id'] ?? null;
$action = $_GET['action'] ?? null;
$module = $_GET['module'] ?? null;

// Construir filtros
$filters = [];
if ($userId) $filters['user_id'] = $userId;
if ($action) $filters['action'] = $action;
if ($module) $filters['module'] = $module;

// Obtener actividades
$actividades = UserActivityLogger::getUserActivities(
    $userId,
    $limit,
    $skip,
    $filters
);

// Obtener total para paginación
$total = UserActivityLogger::count($filters);
$totalPages = ceil($total / $limit);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Auditoría de Actividades</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
</head>
<body>
    <h1>Registro de Actividades</h1>
    
    <form method="get" class="filtros">
        <input type="number" name="user_id" placeholder="ID Usuario" value="<?= htmlspecialchars($userId ?? '') ?>">
        <input type="text" name="action" placeholder="Acción" value="<?= htmlspecialchars($action ?? '') ?>">
        <input type="text" name="module" placeholder="Módulo" value="<?= htmlspecialchars($module ?? '') ?>">
        <button type="submit">Filtrar</button>
    </form>

    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Acción</th>
                <th>Módulo</th>
                <th>IP</th>
                <th>Detalles</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($actividades as $actividad): ?>
            <tr>
                <td><?= htmlspecialchars($actividad->created_at) ?></td>
                <td>
                    <?= htmlspecialchars($actividad->username ?? $actividad->user_id) ?>
                </td>
                <td><?= htmlspecialchars($actividad->action) ?></td>
                <td><?= htmlspecialchars($actividad->module ?? '-') ?></td>
                <td><?= htmlspecialchars($actividad->ip_address) ?></td>
                <td>
                    <button onclick="verDetalles(<?= htmlspecialchars(json_encode($actividad->data)) ?>)">
                        Ver
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="paginacion">
        <?php for ($i = 1; $i <= $totalPages; $i++): ?>
            <a href="?page=<?= $i ?><?= $userId ? '&user_id='.$userId : '' ?><?= $action ? '&action='.$action : '' ?><?= $module ? '&module='.$module : '' ?>" 
               class="<?= $i == $page ? 'active' : '' ?>">
                <?= $i ?>
            </a>
        <?php endfor; ?>
    </div>

    <script>
    function verDetalles(data) {
        alert(JSON.stringify(data, null, 2));
    }
    </script>
</body>
</html>
```

## Acciones Recomendadas

| Acción | Descripción |
|--------|-------------|
| `login` | Usuario inicia sesión |
| `logout` | Usuario cierra sesión |
| `login_failed` | Intento de login fallido |
| `create` | Crear un registro |
| `update` | Actualizar un registro |
| `delete` | Eliminar un registro |
| `view` | Ver detalles de un registro |
| `export` | Exportar datos |
| `import` | Importar datos |
| `approve` | Aprobar algo (crédito, solicitud, etc.) |
| `reject` | Rechazar algo |
| `payment` | Registrar un pago |
| `generate_report` | Generar un reporte |
| `config_change` | Cambio de configuración |
| `user_create` | Crear un usuario |
| `user_update` | Actualizar un usuario |
| `user_delete` | Eliminar un usuario |
| `password_reset` | Resetear contraseña |
| `permission_change` | Cambio de permisos |

## Notas Importantes

1. **Rendimiento**: Si el logger está deshabilitado (USER_ACTIVITY_LOG_ENABLED=false), no hay impacto en el rendimiento ya que la función retorna inmediatamente.

2. **Seguridad**: La clase captura automáticamente la IP real del usuario, incluso detrás de proxies o balanceadores de carga.

3. **Manejo de errores**: Si MongoDB no está disponible, la aplicación NO se romperá. Los errores se registran en el log de PHP pero la aplicación continúa funcionando.

4. **Información automática**: La clase captura automáticamente:
   - IP del usuario
   - User Agent
   - URI de la petición
   - Método HTTP
   - Usuario de sesión
   - Timestamp

5. **Índices**: La clase crea automáticamente índices en MongoDB para optimizar las consultas por usuario, fecha, acción y módulo.

## Troubleshooting

### La extensión MongoDB no está instalada

```bash
# En el contenedor Docker
docker exec -it microsystem_php bash
pecl install mongodb
echo "extension=mongodb.so" > /usr/local/etc/php/conf.d/mongodb.ini
```

O agrega al Dockerfile:

```dockerfile
RUN pecl install mongodb \
    && docker-php-ext-enable mongodb
```

### No se registran las actividades

1. Verifica que `USER_ACTIVITY_LOG_ENABLED=true` en el `.env`
2. Verifica la conexión a MongoDB
3. Revisa los logs de PHP para errores
4. Verifica las credenciales de MongoDB

### Ver los logs de MongoDB en Docker

```bash
docker logs microsystem_mongodb
```

## Soporte

Para más información sobre MongoDB PHP Library: https://www.mongodb.com/docs/php-library/current/
