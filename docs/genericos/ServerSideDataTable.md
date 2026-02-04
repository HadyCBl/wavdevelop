# ServerSideDataTable

Clase moderna para manejo de DataTables con procesamiento del lado del servidor. Soporta consultas SQL personalizadas sin necesidad de crear vistas en la base de datos.

## 📋 Tabla de Contenidos

- [Características](#características)
- [Instalación](#instalación)
- [Uso Básico](#uso-básico)
- [Métodos Disponibles](#métodos-disponibles)
- [Ejemplos Completos](#ejemplos-completos)
- [Migración desde TableData](#migración-desde-tabledata)
- [Mejores Prácticas](#mejores-prácticas)
- [Solución de Problemas](#solución-de-problemas)

## 🚀 Características

- ✅ **Sin vistas SQL** - Trabaja directamente con queries personalizadas
- ✅ **Soporte completo de DataTables** - Paginación, ordenamiento, búsqueda
- ✅ **Consultas complejas** - JOINs, subconsultas, funciones SQL
- ✅ **Parámetros seguros** - Prepared statements con PDO
- ✅ **Búsqueda global e individual** - Por columna específica
- ✅ **Type hints modernos** - PHP 7.4+
- ✅ **Manejo de errores** - Exceptions y logging
- ✅ **Compatible con código legacy** - Método `processTable()`

## 📦 Instalación

La clase está ubicada en:
```
app/Generic/ServerSideDataTable.php
```

### Namespace
```php
use Micro\Generic\ServerSideDataTable;
```

### Requisitos
- PHP 7.4 o superior
- PDO MySQL
- Variables de entorno configuradas (`.env`)

## 🔧 Uso Básico

### 1. Con Tabla Simple (Compatible con versión anterior)

```php
<?php
// filepath: src/server_side/usuarios.php

require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();

$datatable->processTable(
    'tb_usuarios',              // Tabla
    'id_usuario',               // Columna índice
    ['id_usuario', 'nombre', 'email', 'fecha_registro'], // Columnas
    [1, 1, 1, 0],              // Buscables (1=sí, 0=no)
    "estado = '1'"             // WHERE adicional
);
```

### 2. Con Query Personalizada (Recomendado)

```php
<?php
// filepath: src/server_side/clientes_custom.php

require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();

// Query base con JOIN
$baseQuery = "
    SELECT 
        c.idcod_cliente as id,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido) as nombre,
        c.no_identifica as dpi,
        a.nom_agencia as agencia,
        DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') as fecha
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
";

// Query para contar totales
$countQuery = "
    SELECT COUNT(c.idcod_cliente)
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
";

// Configuración
$columns = ['id', 'nombre', 'dpi', 'agencia', 'fecha'];
$searchable = [1, 1, 1, 1, 0];  // fecha no es buscable
$whereExtra = "c.estado = '1'";

$datatable->processQuery(
    $baseQuery,
    $countQuery,
    $columns,
    $searchable,
    [],              // Parámetros (vacío por ahora)
    $whereExtra
);
```

## 📚 Métodos Disponibles

### `__construct(?array $config = null)`

Crea una nueva instancia de ServerSideDataTable.

**Parámetros:**
- `$config` (opcional): Array con configuración personalizada de conexión

**Ejemplo con configuración personalizada:**
```php
$datatable = new ServerSideDataTable([
    'host' => 'localhost',
    'database' => 'mi_base_datos',
    'user' => 'usuario',
    'password' => 'contraseña'
]);
```

### `processTable(string $table, string $indexColumn, array $columns, array $searchable, string $whereExtra = '1=1')`

Procesa una tabla o vista (compatible con versión anterior).

**Parámetros:**
- `$table`: Nombre de la tabla o vista
- `$indexColumn`: Columna para contar registros
- `$columns`: Array de nombres de columnas
- `$searchable`: Array indicando columnas buscables (1=sí, 0=no)
- `$whereExtra`: Condición WHERE adicional

**Ejemplo:**
```php
$datatable->processTable(
    'vs_productos',
    'id',
    ['id', 'codigo', 'nombre', 'precio', 'stock'],
    [1, 1, 1, 1, 1],
    "activo = 1 AND stock > 0"
);
```

### `processQuery(string $baseQuery, string $countQuery, array $columns, array $searchable, array $params = [], string $whereExtra = '1=1')`

Procesa una consulta SQL personalizada (método principal y recomendado).

**Parámetros:**
- `$baseQuery`: Query SELECT base (sin WHERE, ORDER BY, LIMIT)
- `$countQuery`: Query para contar registros totales
- `$columns`: Nombres de columnas para ordenamiento y búsqueda
- `$searchable`: Array indicando columnas buscables
- `$params`: Array de parámetros para binding seguro
- `$whereExtra`: Condición WHERE adicional

## 💡 Ejemplos Completos

### Ejemplo 1: Listado de Clientes con Agencias

```php
<?php
// filepath: src/server_side/clientes_activos.php

require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();

$baseQuery = "
    SELECT 
        c.idcod_cliente as id,
        CONCAT(c.primer_nombre, ' ', c.segundo_nombre, ' ', 
               c.primer_apellido, ' ', c.segundo_apellido) as nombre_completo,
        c.no_identifica as dpi,
        c.telefono,
        c.email,
        a.nom_agencia as agencia,
        d.nombre as departamento,
        DATE_FORMAT(c.fecha_registro, '%d/%m/%Y %H:%i') as fecha_registro
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
    LEFT JOIN tb_departamentos d ON c.depa_reside = d.id
";

$countQuery = "
    SELECT COUNT(c.idcod_cliente)
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
";

$columns = [
    'id',
    'nombre_completo',
    'dpi',
    'telefono',
    'email',
    'agencia',
    'departamento',
    'fecha_registro'
];

$searchable = [1, 1, 1, 1, 1, 1, 1, 0];

$whereExtra = "c.estado = '1'";

$datatable->processQuery($baseQuery, $countQuery, $columns, $searchable, [], $whereExtra);
```

**JavaScript correspondiente:**
```javascript
$('#tablaClientes').DataTable({
    "processing": true,
    "serverSide": true,
    "ajax": "src/server_side/clientes_activos.php",
    "columns": [
        { "data": 0, "title": "ID" },
        { "data": 1, "title": "Nombre Completo" },
        { "data": 2, "title": "DPI" },
        { "data": 3, "title": "Teléfono" },
        { "data": 4, "title": "Email" },
        { "data": 5, "title": "Agencia" },
        { "data": 6, "title": "Departamento" },
        { "data": 7, "title": "Fecha Registro" }
    ],
    "language": {
        "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Spanish.json"
    }
});
```

### Ejemplo 2: Clientes por Agencia (Con Parámetros)

```php
<?php
// filepath: src/server_side/clientes_por_agencia.php

require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

// Validar parámetro
$idAgencia = $_GET['agencia'] ?? null;

if ($idAgencia === null) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parámetro agencia requerido']);
    exit;
}

$datatable = new ServerSideDataTable();

$baseQuery = "
    SELECT 
        c.idcod_cliente as id,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido) as nombre,
        c.no_identifica as dpi,
        c.telefono,
        CASE 
            WHEN c.tipo_cliente = 'N' THEN 'Natural'
            WHEN c.tipo_cliente = 'J' THEN 'Jurídico'
            ELSE 'Otro'
        END as tipo,
        DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') as fecha
    FROM tb_cliente c
    WHERE c.agencia = :id_agencia
";

$countQuery = "
    SELECT COUNT(c.idcod_cliente)
    FROM tb_cliente c
    WHERE c.agencia = :id_agencia
";

$columns = ['id', 'nombre', 'dpi', 'telefono', 'tipo', 'fecha'];
$searchable = [1, 1, 1, 1, 1, 0];
$params = ['id_agencia' => $idAgencia];
$whereExtra = "c.estado = '1'";

$datatable->processQuery($baseQuery, $countQuery, $columns, $searchable, $params, $whereExtra);
```

**JavaScript correspondiente:**
```javascript
const agenciaId = 5; // Ejemplo

$('#tablaClientesAgencia').DataTable({
    "processing": true,
    "serverSide": true,
    "ajax": `src/server_side/clientes_por_agencia.php?agencia=${agenciaId}`,
    "columns": [
        { "data": 0, "title": "ID" },
        { "data": 1, "title": "Nombre" },
        { "data": 2, "title": "DPI" },
        { "data": 3, "title": "Teléfono" },
        { "data": 4, "title": "Tipo Cliente" },
        { "data": 5, "title": "Fecha Registro" }
    ]
});
```

### Ejemplo 3: Préstamos con Múltiples JOINs

```php
<?php
// filepath: src/server_side/prestamos_detalle.php

require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();

$baseQuery = "
    SELECT 
        p.idprestamo as id,
        p.cod_prestamo as codigo,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido) as cliente,
        tp.descripcion as tipo_prestamo,
        CONCAT('Q ', FORMAT(p.monto_solicitado, 2)) as monto,
        p.plazo_meses as plazo,
        CONCAT(FORMAT(p.tasa_interes, 2), '%') as tasa,
        ep.descripcion as estado,
        a.nom_agencia as agencia,
        DATE_FORMAT(p.fecha_solicitud, '%d/%m/%Y') as fecha_solicitud
    FROM tb_prestamos p
    INNER JOIN tb_cliente c ON p.id_cliente = c.idcod_cliente
    INNER JOIN tb_tipo_prestamo tp ON p.id_tipo_prestamo = tp.id
    INNER JOIN tb_estado_prestamo ep ON p.id_estado = ep.id
    INNER JOIN tb_agencia a ON p.id_agencia = a.id_agencia
";

$countQuery = "
    SELECT COUNT(p.idprestamo)
    FROM tb_prestamos p
    INNER JOIN tb_cliente c ON p.id_cliente = c.idcod_cliente
    INNER JOIN tb_tipo_prestamo tp ON p.id_tipo_prestamo = tp.id
    INNER JOIN tb_estado_prestamo ep ON p.id_estado = ep.id
    INNER JOIN tb_agencia a ON p.id_agencia = a.id_agencia
";

$columns = [
    'id',
    'codigo',
    'cliente',
    'tipo_prestamo',
    'monto',
    'plazo',
    'tasa',
    'estado',
    'agencia',
    'fecha_solicitud'
];

// Todas son buscables excepto plazo y tasa
$searchable = [1, 1, 1, 1, 1, 0, 0, 1, 1, 0];

// Solo préstamos activos
$whereExtra = "p.activo = 1";

$datatable->processQuery($baseQuery, $countQuery, $columns, $searchable, [], $whereExtra);
```

### Ejemplo 4: Reportes con Subconsultas

```php
<?php
// filepath: src/server_side/resumen_clientes.php

require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();

$baseQuery = "
    SELECT 
        c.idcod_cliente as id,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido) as cliente,
        a.nom_agencia as agencia,
        COALESCE(prestamos_activos.total, 0) as prestamos_activos,
        COALESCE(prestamos_activos.monto_total, 0) as monto_total_prestamos,
        COALESCE(pagos.total_pagado, 0) as total_pagado,
        DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') as fecha_registro
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
    LEFT JOIN (
        SELECT 
            id_cliente,
            COUNT(*) as total,
            SUM(monto_solicitado) as monto_total
        FROM tb_prestamos
        WHERE id_estado IN (1, 2) -- Activo o En proceso
        GROUP BY id_cliente
    ) prestamos_activos ON c.idcod_cliente = prestamos_activos.id_cliente
    LEFT JOIN (
        SELECT 
            p.id_cliente,
            SUM(pg.monto) as total_pagado
        FROM tb_prestamos p
        INNER JOIN tb_pagos pg ON p.idprestamo = pg.id_prestamo
        WHERE pg.estado = 'pagado'
        GROUP BY p.id_cliente
    ) pagos ON c.idcod_cliente = pagos.id_cliente
";

$countQuery = "
    SELECT COUNT(c.idcod_cliente)
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
";

$columns = [
    'id',
    'cliente',
    'agencia',
    'prestamos_activos',
    'monto_total_prestamos',
    'total_pagado',
    'fecha_registro'
];

$searchable = [1, 1, 1, 0, 0, 0, 0];
$whereExtra = "c.estado = '1'";

$datatable->processQuery($baseQuery, $countQuery, $columns, $searchable, [], $whereExtra);
```

### Ejemplo 5: Filtros Dinámicos Múltiples

```php
<?php
// filepath: src/server_side/clientes_filtros.php

require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();

// Obtener filtros
$idAgencia = $_GET['agencia'] ?? null;
$tipoCliente = $_GET['tipo'] ?? null;
$fechaDesde = $_GET['fecha_desde'] ?? null;
$fechaHasta = $_GET['fecha_hasta'] ?? null;

// Construir WHERE dinámico
$whereConditions = ["c.estado = '1'"];
$params = [];

if ($idAgencia !== null) {
    $whereConditions[] = "c.agencia = :id_agencia";
    $params['id_agencia'] = $idAgencia;
}

if ($tipoCliente !== null) {
    $whereConditions[] = "c.tipo_cliente = :tipo_cliente";
    $params['tipo_cliente'] = $tipoCliente;
}

if ($fechaDesde !== null) {
    $whereConditions[] = "c.fecha_registro >= :fecha_desde";
    $params['fecha_desde'] = $fechaDesde;
}

if ($fechaHasta !== null) {
    $whereConditions[] = "c.fecha_registro <= :fecha_hasta";
    $params['fecha_hasta'] = $fechaHasta . ' 23:59:59';
}

$whereExtra = implode(' AND ', $whereConditions);

$baseQuery = "
    SELECT 
        c.idcod_cliente as id,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido) as nombre,
        c.no_identifica as dpi,
        c.tipo_cliente as tipo,
        a.nom_agencia as agencia,
        DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') as fecha
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
";

// Importante: Agregar WHERE a countQuery también
$countQueryWhere = str_replace('c.estado', 'WHERE c.estado', $whereExtra);
$countQueryWhere = str_replace(' AND c.agencia', ' AND c.agencia', $countQueryWhere);

$countQuery = "
    SELECT COUNT(c.idcod_cliente)
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia
";

$columns = ['id', 'nombre', 'dpi', 'tipo', 'agencia', 'fecha'];
$searchable = [1, 1, 1, 1, 1, 0];

$datatable->processQuery($baseQuery, $countQuery, $columns, $searchable, $params, $whereExtra);
```

**JavaScript con filtros:**
```javascript
let tabla;

function cargarTabla() {
    const agencia = $('#filtroAgencia').val();
    const tipo = $('#filtroTipo').val();
    const fechaDesde = $('#filtroFechaDesde').val();
    const fechaHasta = $('#filtroFechaHasta').val();
    
    if (tabla) {
        tabla.destroy();
    }
    
    const params = new URLSearchParams();
    if (agencia) params.append('agencia', agencia);
    if (tipo) params.append('tipo', tipo);
    if (fechaDesde) params.append('fecha_desde', fechaDesde);
    if (fechaHasta) params.append('fecha_hasta', fechaHasta);
    
    tabla = $('#tablaClientes').DataTable({
        "processing": true,
        "serverSide": true,
        "ajax": `src/server_side/clientes_filtros.php?${params.toString()}`,
        "columns": [
            { "data": 0, "title": "ID" },
            { "data": 1, "title": "Nombre" },
            { "data": 2, "title": "DPI" },
            { "data": 3, "title": "Tipo" },
            { "data": 4, "title": "Agencia" },
            { "data": 5, "title": "Fecha" }
        ]
    });
}

// Cargar al inicio
cargarTabla();

// Recargar al cambiar filtros
$('#btnFiltrar').on('click', cargarTabla);
```

## 🔄 Migración desde TableData

### Código Antiguo (serversideplus.php)
```php
<?php
require 'serverside.php';
$table_data->get(
    'vs_clientes_all',
    'id',
    ['id', 'nombre', 'dpi', 'fecha'],
    [1, 1, 1, 0],
    "estado = '1'"
);
```

### Código Nuevo (Opción 1: Compatible)
```php
<?php
require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();
$datatable->processTable(
    'vs_clientes_all',
    'id',
    ['id', 'nombre', 'dpi', 'fecha'],
    [1, 1, 1, 0],
    "estado = '1'"
);
```

### Código Nuevo (Opción 2: Query Personalizada - Recomendado)
```php
<?php
require_once(__DIR__ . '/../../app/Generic/ServerSideDataTable.php');

use Micro\Generic\ServerSideDataTable;

$datatable = new ServerSideDataTable();

// Ahora no necesitas la vista, usa la query directamente
$baseQuery = "
    SELECT 
        c.idcod_cliente as id,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido) as nombre,
        c.no_identifica as dpi,
        DATE_FORMAT(c.fecha_registro, '%d/%m/%Y') as fecha
    FROM tb_cliente c
";

$countQuery = "SELECT COUNT(c.idcod_cliente) FROM tb_cliente c";

$datatable->processQuery(
    $baseQuery,
    $countQuery,
    ['id', 'nombre', 'dpi', 'fecha'],
    [1, 1, 1, 0],
    [],
    "c.estado = '1'"
);
```

## ✅ Mejores Prácticas

### 1. Nomenclatura de Columnas
```php
// ✅ BIEN: Usa alias claros
$baseQuery = "
    SELECT 
        c.idcod_cliente as id,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido) as nombre_completo
    FROM tb_cliente c
";

// ❌ MAL: Sin alias o alias confusos
$baseQuery = "
    SELECT 
        c.idcod_cliente,
        CONCAT(c.primer_nombre, ' ', c.primer_apellido)
    FROM tb_cliente c
";
```

### 2. Formato de Datos
```php
// ✅ BIEN: Formatea en SQL
$baseQuery = "
    SELECT 
        CONCAT('Q ', FORMAT(monto, 2)) as monto_formateado,
        DATE_FORMAT(fecha, '%d/%m/%Y %H:%i') as fecha_formateada,
        CASE 
            WHEN estado = 1 THEN 'Activo'
            WHEN estado = 0 THEN 'Inactivo'
        END as estado_texto
    FROM tabla
";
```

### 3. Parámetros Seguros
```php
// ✅ BIEN: Usa parámetros con binding
$params = ['id_usuario' => $userId];
$whereExtra = "creado_por = :id_usuario";

// ❌ MAL: Concatenación directa (SQL Injection)
$whereExtra = "creado_por = '$userId'";
```

### 4. Validación de Entrada
```php
// ✅ BIEN: Valida antes de usar
$idAgencia = filter_var($_GET['agencia'] ?? null, FILTER_VALIDATE_INT);

if ($idAgencia === false || $idAgencia <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'ID de agencia inválido']);
    exit;
}
```

### 5. Columnas Buscables Apropiadas
```php
// ✅ BIEN: Solo columnas de texto son buscables
$searchable = [
    1,  // id (texto)
    1,  // nombre (texto)
    1,  // email (texto)
    0,  // fecha (no buscable por texto)
    0,  // monto (no buscable por texto)
    1   // estado_texto (texto)
];
```

### 6. Optimización de Queries
```php
// ✅ BIEN: Usa índices en columnas de JOIN
$baseQuery = "
    SELECT 
        c.id, c.nombre
    FROM tb_cliente c
    INNER JOIN tb_agencia a ON c.agencia = a.id_agencia -- Ambas columnas deben tener índice
    WHERE c.estado = '1' -- Columna indexada
";
```

## 🐛 Solución de Problemas

### Error: "No data available in table"

**Causa:** La query no retorna datos o hay error en la sintaxis.

**Solución:**
```php
// 1. Prueba la query directamente en MySQL
// 2. Verifica que $whereExtra no esté causando conflictos
// 3. Revisa que los nombres de columnas coincidan

// Debug: Agrega esto temporalmente
error_log("Base Query: " . $baseQuery);
error_log("Count Query: " . $countQuery);
error_log("Where Extra: " . $whereExtra);
```

### Error: Columnas no ordenan correctamente

**Causa:** Los nombres en `$columns` no coinciden con los alias de la query.

**Solución:**
```php
// ✅ BIEN: Nombres coinciden
$baseQuery = "SELECT id, nombre, email FROM tabla";
$columns = ['id', 'nombre', 'email'];

// ❌ MAL: Nombres no coinciden
$baseQuery = "SELECT id, nombre as name, email FROM tabla";
$columns = ['id', 'nombre', 'email']; // 'nombre' no existe, es 'name'
```

### Error: Búsqueda no funciona

**Causa:** Columnas no marcadas como buscables o tipo de dato incompatible.

**Solución:**
```php
// Asegúrate que $searchable tenga 1 en las columnas de texto
$columns = ['id', 'nombre', 'fecha', 'monto'];
$searchable = [1, 1, 0, 0]; // Solo id y nombre son buscables
```

### Error: Parámetros no se aplican

**Causa:** Falta el prefijo `:` en el binding o no coincide el nombre.

**Solución:**
```php
// ✅ BIEN
$whereExtra = "agencia = :id_agencia";
$params = ['id_agencia' => 5];

// ❌ MAL
$whereExtra = "agencia = id_agencia"; // Falta :
$params = ['agencia' => 5]; // Nombre diferente
```

### Error: "SQL syntax error"

**Causa:** Comillas o nombres de tabla incorrectos.

**Solución:**
```php
// ✅ BIEN: Usa backticks para nombres de columnas/tablas
$baseQuery = "SELECT `id`, `nombre` FROM `tb_cliente`";

// Si tienes palabras reservadas como columnas
$baseQuery = "SELECT `order`, `date` FROM `tabla`";
```

## 📊 Comparación de Rendimiento

| Método | Vistas SQL | Query Directa | Ventajas |
|--------|------------|---------------|----------|
| **TableData (antiguo)** | ✅ | ❌ | Simple, pero inflexible |
| **ServerSideDataTable::processTable()** | ✅ | ❌ | Compatible, modernizado |
| **ServerSideDataTable::processQuery()** | ✅ | ✅ | Máxima flexibilidad |

## 📝 Checklist de Implementación

- [ ] Importar la clase ServerSideDataTable
- [ ] Definir query base con alias claros
- [ ] Definir query de conteo
- [ ] Listar columnas en el orden correcto
- [ ] Marcar columnas buscables apropiadamente
- [ ] Agregar parámetros seguros si es necesario
- [ ] Definir whereExtra si aplica
- [ ] Probar búsqueda global
- [ ] Probar ordenamiento por cada columna
- [ ] Probar paginación
- [ ] Verificar rendimiento con datos reales

## 🆘 Soporte

Para más información o reportar problemas:
- Documentación del proyecto
- Equipo de desarrollo MicroSystemPlus

---

**Versión:** 2.0  
**Última actualización:** Octubre 2025  
**Autor:** MicroSystemPlus Development Team