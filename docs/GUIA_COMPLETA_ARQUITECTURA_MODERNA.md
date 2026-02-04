# Guía Completa - Arquitectura Moderna con BaseController

**Sistema unificado de controladores, vistas y CRUD con JavaScript moderno**

---

## 📋 Tabla de Contenidos

1. [Introducción](#1-introducción)
2. [BaseController Unificado](#2-basecontroller-unificado)
3. [Controladores - Métodos Disponibles](#3-controladores---métodos-disponibles)
4. [Vistas - loadModuleView() y loadView()](#4-vistas---loadmoduleview-y-loadview)
5. [CRUD - submitForm()](#5-crud---submitform)
6. [Ejemplos Completos](#6-ejemplos-completos)
7. [Migración desde Legacy](#7-migración-desde-legacy)
8. [Estructura de Respuestas](#8-estructura-de-respuestas)
9. [Mejores Prácticas](#9-mejores-prácticas)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Introducción

### 🎯 Concepto Principal

Similar a Laravel, **UN SOLO controlador** puede:
- ✅ Renderizar vistas HTML
- ✅ Procesar operaciones CRUD (crear, actualizar, eliminar)
- ✅ Retornar JSON o HTML según la petición
- ✅ Todo centralizado en un solo lugar

### 📦 Estructura Actual

```
BaseController (padre único - TODO-EN-UNO)
    ├── Métodos de vistas (renderView, view, assign)
    ├── Métodos de CRUD (created, updated, deleted, findOrFail)
    ├── Métodos de petición (post, get, input, validate)
    └── Métodos de utilidad (sanitize, isAjax, paginate)

BaseViewController (DEPRECATED - solo compatibilidad)
BaseCrudController (DEPRECATED - solo compatibilidad)
```

### ✨ Ventajas del Sistema

1. **🔄 Un solo controlador** - Vistas y CRUD juntos
2. **📝 Código limpio** - Toda la lógica de un módulo en un lugar
3. **🎨 Flexible** - JSON o HTML según necesidad
4. **⚡ Auto-detección** - JavaScript detecta campos automáticamente
5. **🛡️ Validación automática** - HTML5 required + backend
6. **🔁 Migración gradual** - Fallback a sistema legacy
7. **🚀 RESTful** - Métodos HTTP estándar (GET, POST, PUT, DELETE)

---

## 2. BaseController Unificado

### Herencia Recomendada

```php
<?php

namespace App\Controllers;

use Exception;

/**
 * Todos los controladores heredan directamente de BaseController
 */
class MiController extends BaseController
{
    // ✅ Tienes acceso a TODOS los métodos:
    // - Vistas: renderView(), view(), assign()
    // - CRUD: created(), updated(), deleted(), findOrFail()
    // - Petición: post(), get(), input(), validate(), only()
    // - Utilidades: sanitize(), isAjax(), paginate(), logAudit()
}
```

### Compatibilidad Legacy

```php
// Estos siguen funcionando (pero son solo alias vacíos):
class MiController extends BaseViewController { }  // ✅ Funciona
class MiController extends BaseCrudController { }  // ✅ Funciona

// Recomendado - Usar directamente:
class MiController extends BaseController { }      // ⭐ Mejor
```

---

## 3. Controladores - Métodos Disponibles

### 📥 Entrada de Datos

```php
// Obtener datos según método HTTP
$nombre = $this->post('nombre', 'default');    // $_POST
$page = $this->get('page', 1);                 // $_GET
$email = $this->input('email');                // PUT/PATCH/DELETE

// Obtener todos los datos
$allData = $this->all();  // Array con todos los datos de la petición

// Filtrar solo campos específicos
$data = $this->only(['nombre', 'email', 'telefono']);
// Retorna: ['nombre' => 'Juan', 'email' => 'juan@mail.com', ...]

// Validar campos requeridos (lanza Exception si faltan)
$this->validate(['nombre', 'email']);
```

### 📤 Respuestas JSON

```php
// JSON genérico
$this->json(['key' => 'value'], 200);

// Éxito genérico
$this->success(['data' => $data], 'Operación exitosa');
// Response: {"status": 1, "message": "...", "data": {...}}

// Error genérico
$this->error('Mensaje de error', 400);
// Response: {"status": 0, "error": "...", "message": "..."}

// Respuestas especializadas CRUD
$this->created('Cliente creado', ['id' => 123]);      // 201 Created
$this->updated('Cliente actualizado');                // 200 OK
$this->deleted('Cliente eliminado');                  // 200 OK
$this->notFound('Cliente no encontrado');             // 404 Not Found
$this->validationError('Campos inválidos', $errors); // 422 Unprocessable
```

### 👁️ Renderizado de Vistas

```php
// Renderizar una vista y obtener HTML
$html = $this->renderView('clientes/index', [
    'clientes' => $clientes,
    'total' => 100
]);

// Respuesta JSON con HTML (para AJAX)
$this->view($html, ['extra' => 'data']);
// Response: {"status": 1, "html": "<div>...</div>", "extra": "data"}

// Asignar variables antes de renderizar
$this->assign('nombre', 'Juan');
$this->assignMultiple(['edad' => 30, 'ciudad' => 'Guatemala']);

// Mensaje de control (compatible con sistema legacy)
$this->messageControl('Operación exitosa', 'success');
```

### 🔍 Consultas Base de Datos

```php
// Verificar si existe un registro
$exists = $this->exists('tb_clientes', 'id_cliente', 123);
// Retorna: true/false

// Obtener registro o lanzar 404
$cliente = $this->findOrFail('tb_clientes', 'id_cliente', 123, 'Cliente no encontrado');
// Si no existe, envía 404 automáticamente y termina ejecución

// Soft delete (cambiar estado a 0)
$this->softDelete('tb_clientes', 'id_cliente', 123);

// Hard delete (eliminar permanentemente)
$this->hardDelete('tb_clientes', 'id_cliente', 123);

// Paginación
$pagination = $this->paginate($total, $perPage, $currentPage);
/* Retorna:
[
    'total' => 150,
    'per_page' => 15,
    'current_page' => 2,
    'total_pages' => 10,
    'offset' => 15,
    'has_more' => true
]
*/
```

### 🔐 Sesión y Autenticación

```php
$userId = $this->getUserId();              // ID del usuario logueado
$agencyId = $this->getAgencyId();          // ID de la agencia
$institutionId = $this->getInstitutionId(); // ID de la institución
$isAuth = $this->isAuthenticated();        // true/false
```

### 🛠️ Utilidades

```php
// Sanitizar entrada (prevenir XSS)
$safe = $this->sanitize($userInput);

// Verificar si es petición AJAX
if ($this->isAjax()) {
    $this->json(['data' => $data]);
} else {
    echo $html; // Página completa
}

// Obtener método HTTP actual
$method = $this->method(); // 'GET', 'POST', 'PUT', 'DELETE', etc.

// Log de auditoría
$this->logAudit('CREATE', 'tb_clientes', 123, ['nombre' => 'Juan']);
// Registra: usuario, acción, entidad, ID, cambios, IP
```

---

## 4. Vistas - loadModuleView() y loadView()

### Concepto - Migración de printdiv()

**ANTES (Legacy):**
```javascript
printdiv('L', '#contenedor', 'creditos/lista', {filtro: 'activos'});
```

**AHORA (Moderno):**
```javascript
loadModuleView('#contenedor', {
    route: '/api/vistas/creditos/lista',
    legacyDir: 'creditos/lista',  // fallback automático
    condi: 'L',
    data: {filtro: 'activos'}
});
```

### loadModuleView() - Función Principal

```javascript
/**
 * Carga vistas a través de controladores con fallback automático
 */
loadModuleView(selector, options)
```

**Parámetros:**
```javascript
{
    route: '/api/vistas/modulo/vista',    // Ruta del controlador (requerido)
    legacyDir: 'modulo/vista',            // Fallback a printdiv si falla
    condi: 'L',                           // Condición (L=listar, N=nuevo, E=editar)
    data: {},                             // Datos adicionales
    useLegacy: false,                     // Forzar uso de printdiv legacy
    onSuccess: (response) => {},          // Callback al éxito
    onError: (xhr) => {}                  // Callback al error
}
```

**Ejemplo completo:**
```javascript
loadModuleView('#contenedor', {
    route: '/api/vistas/clientes/lista',
    legacyDir: 'clientes/lista',
    condi: 'L',
    data: {estado: 'activos'},
    onSuccess: (response) => {
        console.log('Vista cargada:', response);
        // Inicializar componentes
        $('#tabla-clientes').DataTable();
    },
    onError: (xhr) => {
        console.error('Error al cargar vista');
    }
});
```

### loadView() - Función Simplificada

Auto-detecta si un módulo está migrado o usa legacy.

```javascript
loadView(selector, condi, module, view, data);
```

**Ejemplo:**
```javascript
// Define módulos migrados (una sola vez)
window.MIGRATED_MODULES = {
    creditos: {
        lista: {
            route: '/api/vistas/creditos/lista',
            legacy: 'creditos/lista'
        }
    }
};

// Uso simple
loadView('#contenedor', 'L', 'creditos', 'lista', {filtro: 'todos'});
// Si 'creditos' está en MIGRATED_MODULES, usa la ruta
// Si no, usa printdiv legacy automáticamente
```

### Controlador de Vista - Ejemplo

```php
<?php

namespace App\Controllers;

use Exception;

class ClienteViewController extends BaseController
{
    /**
     * Vista de lista de clientes
     * GET /api/vistas/clientes/lista
     */
    public function lista(): void
    {
        try {
            $this->database->openConnection();
            
            // Obtener parámetros
            $condi = $this->post('condi', 'L');
            $estado = $this->post('estado', 'activos');
            
            // Ejecutar lógica
            $clientes = $this->database->query(
                "SELECT * FROM tb_clientes WHERE estado = ?",
                [$estado]
            );
            
            // Renderizar vista
            $html = $this->renderView('clientes/lista', [
                'clientes' => $clientes,
                'condi' => $condi
            ]);
            
            // Enviar respuesta
            $this->view($html, ['total' => count($clientes)]);
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
}
```

### Rutas de Vistas

```php
// api/routes.php

$r->addGroup('/vistas', function($r) {
    $r->addGroup('/clientes', function($r) {
        $r->addRoute('POST', '/lista',      'ClienteViewController@lista');
        $r->addRoute('POST', '/formulario', 'ClienteViewController@formulario');
        $r->addRoute('POST', '/detalle',    'ClienteViewController@detalle');
    });
});
```

---

## 5. CRUD - submitForm()

### Concepto - Migración de obtiene()

**ANTES (Legacy - obtiene):**
```javascript
obtiene(
    ['nombre', 'apellido', 'dpi'],  // Listar inputs manualmente
    ['tipo_cliente'],               // Listar selects manualmente
    [],                             // Radios
    'C',                            // condi (C/U/D)
    null,                           // id
    'clientes',                     // archivo
    callback,
    '¿Confirmar?',
    'crud_clientes'
);
```

**AHORA (Moderno - submitForm):**
```html
<form id="formCliente" data-action="/api/clientes" data-method="POST">
    <input name="nombre" required>
    <input name="apellido" required>
    <input name="dpi" required>
    <select name="tipo_cliente" required>...</select>
</form>
```

```javascript
submitForm('#formCliente', {
    confirmMessage: '¿Crear cliente?',
    onSuccess: callback
});
```

### submitForm() - Función Principal

```javascript
/**
 * Envía formularios con auto-detección de campos
 */
submitForm(containerSelector, options)
```

**Parámetros:**
```javascript
{
    action: '/api/clientes',         // Endpoint (o usa data-action del elemento)
    method: 'POST',                  // HTTP method (o usa data-method)
                                     // Valores: GET, POST, PUT, PATCH, DELETE
    extraData: {},                   // Datos adicionales
    onSuccess: (response) => {},     // Callback éxito
    onError: (xhr) => {},            // Callback error
    confirmMessage: '¿Confirmar?',   // Mensaje de confirmación (false = no confirmar)
    successMessage: 'Guardado',      // Mensaje de éxito personalizado
    reloadAfter: true,               // Recargar página después del éxito
    afterSuccess: (response) => {}   // Acción después del éxito
}
```

### Auto-detección de Campos

`submitForm` recolecta automáticamente todos los campos dentro del contenedor:

```html
<!-- ✅ Inputs -->
<input name="nombre" required>
<input type="email" name="email" required>
<input type="number" name="edad" min="18">

<!-- ✅ Decimales con Cleave.js -->
<input class="decimal-cleave-zen" name="monto" required>

<!-- ✅ Selects -->
<select name="ciudad" required>
    <option value="1">Guatemala</option>
</select>

<!-- ✅ Textareas -->
<textarea name="observaciones"></textarea>

<!-- ✅ Checkboxes (envía true/false) -->
<input type="checkbox" name="activo" checked>

<!-- ✅ Radios (envía el seleccionado) -->
<input type="radio" name="genero" value="M"> Masculino
<input type="radio" name="genero" value="F"> Femenino
```

### Ejemplos de submitForm()

**Crear (POST):**
```html
<form id="formNuevo" data-action="/api/clientes" data-method="POST">
    <input name="nombre" required>
    <input name="cedula" required>
    <button type="button" onclick="crear()">Crear</button>
</form>

<script>
function crear() {
    submitForm('#formNuevo', {
        confirmMessage: '¿Crear cliente?',
        successMessage: 'Cliente creado',
        reloadAfter: true,
        onSuccess: (response) => {
            console.log('ID:', response.id);
        }
    });
}
</script>
```

**Actualizar (PUT):**
```html
<form id="formEditar" data-action="/api/clientes/123" data-method="PUT">
    <input name="nombre" value="Juan" required>
    <input name="telefono" value="1234-5678" required>
    <button type="button" onclick="actualizar()">Actualizar</button>
</form>

<script>
function actualizar() {
    submitForm('#formEditar', {
        confirmMessage: '¿Actualizar datos?',
        onSuccess: (response) => {
            Swal.fire('Actualizado', response.message, 'success');
        }
    });
}
</script>
```

**Eliminar (DELETE):**
```javascript
function eliminar(id) {
    submitForm('body', {  // No necesita formulario
        action: `/api/clientes/${id}`,
        method: 'DELETE',
        confirmMessage: '¿Eliminar este cliente?',
        successMessage: 'Cliente eliminado',
        reloadAfter: true
    });
}
```

### Controlador CRUD - Ejemplo

```php
<?php

namespace App\Controllers;

use Exception;

class ClienteController extends BaseController
{
    /**
     * Crear cliente
     * POST /api/clientes
     */
    public function store(): void
    {
        try {
            $this->database->openConnection();
            
            // Validar campos requeridos
            $this->validate(['nombre', 'cedula', 'telefono']);
            
            // Obtener solo campos necesarios
            $data = $this->only(['nombre', 'cedula', 'telefono', 'email']);
            
            // Sanitizar
            $data['nombre'] = $this->sanitize($data['nombre']);
            $data['cedula'] = $this->sanitize($data['cedula']);
            
            // Verificar duplicados
            $checkQuery = "SELECT COUNT(*) as count FROM tb_clientes WHERE cedula = ?";
            $result = $this->database->query($checkQuery, [$data['cedula']]);
            
            if ($result[0]['count'] > 0) {
                $this->validationError('La cédula ya está registrada');
                return;
            }
            
            // Insertar
            $query = "INSERT INTO tb_clientes (nombre, cedula, telefono, email, estado, fecha_registro, id_usuario_registro) 
                      VALUES (?, ?, ?, ?, 1, NOW(), ?)";
            
            $inserted = $this->database->execute($query, [
                $data['nombre'],
                $data['cedula'],
                $data['telefono'],
                $data['email'] ?? null,
                $this->getUserId()
            ]);
            
            if ($inserted) {
                $id = $this->database->getLastInsertId();
                $this->logAudit('CREATE', 'tb_clientes', $id, $data);
                
                $this->created('Cliente creado exitosamente', ['id' => $id]);
            } else {
                $this->error('Error al crear el cliente');
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
    
    /**
     * Actualizar cliente
     * PUT /api/clientes/{id}
     */
    public function update($id): void
    {
        try {
            $this->database->openConnection();
            
            // Verificar que existe
            $this->findOrFail('tb_clientes', 'id_cliente', $id);
            
            // Validar
            $this->validate(['nombre', 'telefono']);
            
            // Obtener datos
            $data = $this->only(['nombre', 'telefono', 'email']);
            
            // Actualizar
            $query = "UPDATE tb_clientes SET nombre = ?, telefono = ?, email = ?, 
                      fecha_modificacion = NOW(), id_usuario_modificacion = ? 
                      WHERE id_cliente = ?";
            
            $updated = $this->database->execute($query, [
                $this->sanitize($data['nombre']),
                $data['telefono'],
                $data['email'] ?? null,
                $this->getUserId(),
                $id
            ]);
            
            if ($updated) {
                $this->logAudit('UPDATE', 'tb_clientes', $id, $data);
                $this->updated('Cliente actualizado exitosamente');
            } else {
                $this->error('Error al actualizar');
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
    
    /**
     * Eliminar cliente
     * DELETE /api/clientes/{id}
     */
    public function destroy($id): void
    {
        try {
            $this->database->openConnection();
            
            $cliente = $this->findOrFail('tb_clientes', 'id_cliente', $id);
            
            // Soft delete
            if ($this->softDelete('tb_clientes', 'id_cliente', $id)) {
                $this->logAudit('DELETE', 'tb_clientes', $id, [
                    'nombre' => $cliente['nombre']
                ]);
                
                $this->deleted('Cliente eliminado exitosamente');
            } else {
                $this->error('Error al eliminar');
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
}
```

### Rutas CRUD RESTful

```php
// api/routes.php

// Rutas RESTful de clientes
$r->addRoute('GET',    '/clientes',      'ClienteController@index');   // Listar
$r->addRoute('POST',   '/clientes',      'ClienteController@store');   // Crear
$r->addRoute('GET',    '/clientes/{id}', 'ClienteController@show');    // Ver uno
$r->addRoute('PUT',    '/clientes/{id}', 'ClienteController@update');  // Actualizar
$r->addRoute('DELETE', '/clientes/{id}', 'ClienteController@destroy'); // Eliminar
```

---

## 6. Ejemplos Completos

### Ejemplo 1: Controlador Completo (Vistas + CRUD)

```php
<?php

namespace App\Controllers;

use Exception;

/**
 * Controlador completo de Clientes
 * Maneja vistas Y operaciones CRUD
 */
class ClienteController extends BaseController
{
    // ==================== VISTAS ====================
    
    /**
     * Vista de lista
     * GET /clientes
     */
    public function index(): void
    {
        try {
            $this->database->openConnection();
            
            $clientes = $this->database->query("SELECT * FROM tb_clientes WHERE estado = 1");
            
            // Si es AJAX, retorna JSON
            if ($this->isAjax()) {
                $this->success(['data' => $clientes]);
            } else {
                // Si no es AJAX, renderiza HTML completo
                $html = $this->renderView('clientes/index', ['clientes' => $clientes]);
                echo $html;
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
    
    /**
     * Vista de formulario crear
     * GET /clientes/create
     */
    public function create(): void
    {
        try {
            $this->database->openConnection();
            
            $tipos = $this->database->query("SELECT * FROM tb_tipos_cliente");
            
            $html = $this->renderView('clientes/create', ['tipos' => $tipos]);
            
            if ($this->isAjax()) {
                $this->view($html);
            } else {
                echo $html;
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
    
    /**
     * Vista de formulario editar
     * GET /clientes/{id}/edit
     */
    public function edit($id): void
    {
        try {
            $this->database->openConnection();
            
            $cliente = $this->findOrFail('tb_clientes', 'id_cliente', $id);
            $tipos = $this->database->query("SELECT * FROM tb_tipos_cliente");
            
            $html = $this->renderView('clientes/edit', [
                'cliente' => $cliente,
                'tipos' => $tipos
            ]);
            
            if ($this->isAjax()) {
                $this->view($html);
            } else {
                echo $html;
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
    
    // ==================== CRUD ====================
    
    /**
     * Crear cliente
     * POST /clientes
     */
    public function store(): void
    {
        try {
            $this->database->openConnection();
            
            $this->validate(['nombre', 'cedula', 'telefono']);
            $data = $this->only(['nombre', 'cedula', 'telefono', 'email', 'tipo_cliente']);
            $data['nombre'] = $this->sanitize($data['nombre']);
            
            $query = "INSERT INTO tb_clientes (nombre, cedula, telefono, email, tipo_cliente, estado, fecha_registro) 
                      VALUES (?, ?, ?, ?, ?, 1, NOW())";
            
            $inserted = $this->database->execute($query, [
                $data['nombre'],
                $data['cedula'],
                $data['telefono'],
                $data['email'] ?? null,
                $data['tipo_cliente'] ?? null
            ]);
            
            if ($inserted) {
                $id = $this->database->getLastInsertId();
                $this->logAudit('CREATE', 'tb_clientes', $id, $data);
                $this->created('Cliente creado exitosamente', ['id' => $id]);
            } else {
                $this->error('Error al crear');
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
    
    /**
     * Actualizar cliente
     * PUT /clientes/{id}
     */
    public function update($id): void
    {
        try {
            $this->database->openConnection();
            
            $this->findOrFail('tb_clientes', 'id_cliente', $id);
            $this->validate(['nombre', 'telefono']);
            $data = $this->only(['nombre', 'telefono', 'email']);
            
            $query = "UPDATE tb_clientes SET nombre = ?, telefono = ?, email = ? WHERE id_cliente = ?";
            $updated = $this->database->execute($query, [
                $this->sanitize($data['nombre']),
                $data['telefono'],
                $data['email'] ?? null,
                $id
            ]);
            
            if ($updated) {
                $this->logAudit('UPDATE', 'tb_clientes', $id, $data);
                $this->updated('Cliente actualizado');
            } else {
                $this->error('Error al actualizar');
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
    
    /**
     * Eliminar cliente
     * DELETE /clientes/{id}
     */
    public function destroy($id): void
    {
        try {
            $this->database->openConnection();
            
            $cliente = $this->findOrFail('tb_clientes', 'id_cliente', $id);
            
            if ($this->softDelete('tb_clientes', 'id_cliente', $id)) {
                $this->logAudit('DELETE', 'tb_clientes', $id, ['nombre' => $cliente['nombre']]);
                $this->deleted();
            } else {
                $this->error('Error al eliminar');
            }
            
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }
}
```

### Ejemplo 2: HTML + JavaScript Completo

```html
<!DOCTYPE html>
<html>
<head>
    <title>Gestión de Clientes</title>
</head>
<body>
    <!-- Contenedor de vistas dinámicas -->
    <div id="contenedor-principal"></div>
    
    <!-- Modal para formularios -->
    <dialog id="modalForm">
        <div id="contenedor-formulario"></div>
    </dialog>
    
    <script>
        // ==================== CARGA DE VISTAS ====================
        
        // Cargar lista de clientes al inicio
        function cargarLista() {
            loadModuleView('#contenedor-principal', {
                route: '/api/vistas/clientes/lista',
                legacyDir: 'clientes/lista',
                condi: 'L',
                onSuccess: (response) => {
                    console.log('Lista cargada');
                    // Inicializar DataTable u otros componentes
                    $('#tabla-clientes').DataTable();
                }
            });
        }
        
        // Cargar formulario de creación
        function abrirFormularioNuevo() {
            loadModuleView('#contenedor-formulario', {
                route: '/api/vistas/clientes/create',
                condi: 'N',
                onSuccess: (response) => {
                    document.getElementById('modalForm').showModal();
                }
            });
        }
        
        // Cargar formulario de edición
        function abrirFormularioEditar(idCliente) {
            loadModuleView('#contenedor-formulario', {
                route: '/api/vistas/clientes/edit',
                condi: 'E',
                data: { id: idCliente },
                onSuccess: (response) => {
                    document.getElementById('modalForm').showModal();
                }
            });
        }
        
        // ==================== OPERACIONES CRUD ====================
        
        // Crear cliente
        function guardarNuevo() {
            submitForm('#formNuevoCliente', {
                action: '/api/clientes',
                method: 'POST',
                confirmMessage: '¿Crear este cliente?',
                successMessage: 'Cliente creado exitosamente',
                onSuccess: (response) => {
                    console.log('ID creado:', response.id);
                    document.getElementById('modalForm').close();
                    cargarLista(); // Recargar lista
                }
            });
        }
        
        // Actualizar cliente
        function guardarEdicion(idCliente) {
            submitForm('#formEditarCliente', {
                action: `/api/clientes/${idCliente}`,
                method: 'PUT',
                confirmMessage: '¿Actualizar este cliente?',
                onSuccess: (response) => {
                    Swal.fire('Actualizado', response.message, 'success');
                    document.getElementById('modalForm').close();
                    cargarLista();
                }
            });
        }
        
        // Eliminar cliente
        function eliminarCliente(idCliente) {
            submitForm('body', {
                action: `/api/clientes/${idCliente}`,
                method: 'DELETE',
                confirmMessage: '¿Está seguro de eliminar este cliente?',
                successMessage: 'Cliente eliminado',
                onSuccess: () => {
                    cargarLista();
                }
            });
        }
        
        // Cargar lista al iniciar
        document.addEventListener('DOMContentLoaded', function() {
            cargarLista();
        });
    </script>
</body>
</html>
```

---

## 7. Migración desde Legacy

### Checklist de Migración

**Fase 1: Preparación**
- [ ] Crear controlador que extienda `BaseController`
- [ ] Registrar rutas en `api/routes.php`
- [ ] Implementar métodos necesarios

**Fase 2: HTML**
- [ ] Agregar `data-action` a formularios
- [ ] Agregar `data-method` (POST, PUT, DELETE)
- [ ] Verificar que campos tengan `name`
- [ ] Agregar `required` a campos obligatorios

**Fase 3: JavaScript**
- [ ] Reemplazar `printdiv()` por `loadModuleView()`
- [ ] Reemplazar `obtiene()` por `submitForm()`
- [ ] Configurar callbacks

**Fase 4: Pruebas**
- [ ] Probar cargar vistas
- [ ] Probar crear registros
- [ ] Probar actualizar registros
- [ ] Probar eliminar registros
- [ ] Verificar validaciones

### Comparación Legacy vs Moderno

| Característica | Legacy | Moderno |
|----------------|--------|---------|
| **Vistas** | `printdiv()` | `loadModuleView()` |
| **CRUD** | `obtiene()` | `submitForm()` |
| **Listar campos** | Manual | Auto-detecta |
| **Endpoint** | Hardcoded | `data-action` |
| **Método HTTP** | Parámetro `condi` | `data-method` (POST/PUT/DELETE) |
| **Validación** | Manual | HTML5 `required` |
| **Fallback** | No | ✅ Automático |
| **Separación** | Mezclada | Controladores separados |

### Ejemplo de Migración

**ANTES:**
```javascript
// Vista
printdiv('L', '#contenedor', 'clientes/lista', {});

// Crear
obtiene(
    ['nombre', 'cedula'],
    ['tipo_cliente'],
    [],
    'C',
    null,
    'clientes',
    null,
    '¿Crear?',
    'crud_clientes'
);
```

**DESPUÉS:**
```javascript
// Vista
loadModuleView('#contenedor', {
    route: '/api/vistas/clientes/lista',
    legacyDir: 'clientes/lista'
});

// Crear
submitForm('#formCliente', {
    action: '/api/clientes',
    method: 'POST',
    confirmMessage: '¿Crear?'
});
```

---

## 8. Estructura de Respuestas

### Respuestas JSON Estándar

**Éxito (200, 201):**
```json
{
    "status": 1,
    "message": "Cliente creado exitosamente",
    "id": 123,
    "data": {...}
}
```

**Error de Validación (422):**
```json
{
    "status": 0,
    "message": "Campos requeridos faltantes: nombre, cedula",
    "error": "Campos requeridos faltantes: nombre, cedula",
    "errors": {
        "nombre": "El nombre es requerido",
        "cedula": "La cédula es requerida"
    }
}
```

**Error General (400):**
```json
{
    "status": 0,
    "error": "Error al procesar la solicitud",
    "message": "Error al procesar la solicitud"
}
```

**No Encontrado (404):**
```json
{
    "status": 0,
    "error": "Cliente no encontrado",
    "message": "Cliente no encontrado"
}
```

**Vista (AJAX):**
```json
{
    "status": 1,
    "html": "<div>...</div>",
    "total": 150,
    "extra_data": "..."
}
```

### Códigos HTTP

- **200 OK** - Operación exitosa (update, delete, list)
- **201 Created** - Recurso creado
- **400 Bad Request** - Error general
- **404 Not Found** - Recurso no encontrado
- **422 Unprocessable Entity** - Error de validación
- **500 Internal Server Error** - Error del servidor

---

## 9. Mejores Prácticas

### ✅ DO (Hacer)

**Controladores:**
```php
// ✅ Heredar de BaseController
class MiController extends BaseController { }

// ✅ Validar siempre
$this->validate(['nombre', 'email']);

// ✅ Sanitizar entrada de usuario
$nombre = $this->sanitize($this->post('nombre'));

// ✅ Usar findOrFail para consultas
$cliente = $this->findOrFail('tb_clientes', 'id_cliente', $id);

// ✅ Log de auditoría en cambios importantes
$this->logAudit('CREATE', 'tb_clientes', $id, $data);

// ✅ Cerrar conexión en finally
finally {
    $this->database->closeConnection();
}
```

**HTML:**
```html
<!-- ✅ Usar data-action y data-method -->
<form data-action="/api/clientes" data-method="POST">

<!-- ✅ Agregar name a todos los campos -->
<input name="nombre" required>

<!-- ✅ Usar validación HTML5 -->
<input type="email" required>
<input type="number" min="0" max="100">

<!-- ✅ Usar data-label para mensajes -->
<input name="fecha_inicio" data-label="Fecha de Inicio" required>
```

**JavaScript:**
```javascript
// ✅ Usar callbacks para acciones post-éxito
submitForm('#form', {
    onSuccess: (data) => {
        cerrarModal();
        actualizarTabla();
    }
});

// ✅ Mantener legacyDir mientras migras
loadModuleView('#contenedor', {
    route: '/api/vistas/modulo/vista',
    legacyDir: 'modulo/vista'  // fallback
});
```

### ❌ DON'T (No hacer)

```php
// ❌ No mezclar lógica en vistas
// Vista no debe tener queries directos

// ❌ No olvidar cerrar conexiones
// Siempre usar finally

// ❌ No hardcodear IDs o valores
// Usar parámetros

// ❌ No retornar HTML directo en CRUD
// Usar métodos de respuesta (created, updated, etc)
```

```html
<!-- ❌ No olvidar required -->
<input name="nombre">  <!-- MAL -->
<input name="nombre" required>  <!-- BIEN -->

<!-- ❌ No duplicar IDs -->
<div id="campo"></div>
<div id="campo"></div>  <!-- MAL -->
```

```javascript
// ❌ No hardcodear mensajes
successMessage: 'Guardado'  // MAL - Dejar que el backend lo maneje

// ❌ No mezclar sistemas sin razón
obtiene(...);  // Legacy
submitForm(...);  // Moderno
// Elegir uno y migrar completamente
```

---

## 10. Troubleshooting

### Error: "Contenedor no encontrado"
**Causa:** Selector CSS incorrecto o elemento no existe
**Solución:**
```javascript
// Verificar que el selector sea correcto
console.log(document.querySelector('#contenedor')); // Debe retornar el elemento
```

### Error: "Campos requeridos faltantes"
**Causa:** Campos no tienen `name` o no tienen `required`
**Solución:**
```html
<!-- Agregar name y required -->
<input name="campo" required>
```

### Error: "Vista no encontrada"
**Causa:** Ruta del archivo de vista incorrecta
**Solución:**
```php
// Verificar que el archivo existe
// renderView('clientes/lista') busca: views/clientes/lista.php
```

### Error: "Ruta no encontrada" (404)
**Causa:** Ruta no registrada en routes.php
**Solución:**
```php
// Verificar en api/routes.php
$r->addRoute('POST', '/clientes', 'ClienteController@store');
```

### Validación no funciona
**Causa:** Falta atributo `required`
**Solución:**
```html
<input name="nombre" required>
```

### Datos no se envían
**Causa:** Campos fuera del contenedor
**Solución:**
```html
<!-- Asegurar que campos estén dentro del contenedor -->
<div id="formCliente">
    <input name="nombre">  <!-- ✅ Dentro -->
</div>
<input name="otro">  <!-- ❌ Fuera -->
```

### Decimales no se envían correctamente
**Causa:** Formato con separadores de miles/decimales
**Solución:**
```html
<!-- submitForm() auto-desformatea campos con clase decimal-cleave-zen -->
<input class="decimal-cleave-zen" name="monto">
```

### AJAX retorna HTML en lugar de JSON
**Causa:** Controlador no usa métodos de respuesta
**Solución:**
```php
// NO hacer:
echo $html;

// Hacer:
$this->view($html);  // Para vistas
$this->success($data);  // Para CRUD
```

---

## Resumen Rápido

### Para Vistas:
1. Crear controlador: `class MiController extends BaseController`
2. Implementar método: `public function miVista(): void`
3. Registrar ruta: `$r->addRoute('POST', '/vistas/mi/vista', 'MiController@miVista')`
4. JavaScript: `loadModuleView('#contenedor', {route: '/api/vistas/mi/vista'})`

### Para CRUD:
1. Usar mismo controlador
2. Implementar: `store()`, `update($id)`, `destroy($id)`
3. Registrar rutas RESTful
4. HTML: `<form data-action="/api/recurso" data-method="POST">`
5. JavaScript: `submitForm('#form')`

### Métodos Más Usados:

**Petición:**
- `$this->post('campo')`, `$this->get('campo')`, `$this->all()`, `$this->only(['campo1', 'campo2'])`

**Validación:**
- `$this->validate(['campo1', 'campo2'])`, `$this->sanitize($valor)`

**Respuestas:**
- `$this->success($data)`, `$this->error($msg)`, `$this->created($msg)`, `$this->updated($msg)`, `$this->deleted()`

**Vistas:**
- `$html = $this->renderView('ruta/vista', $datos)`, `$this->view($html)`

**BD:**
- `$this->findOrFail('tabla', 'columna', $id)`, `$this->softDelete('tabla', 'columna', $id)`, `$this->paginate($total, $perPage, $page)`

---

**¡Sistema completo unificado! 🚀**

Toda la funcionalidad de vistas y CRUD en un solo BaseController, igual que Laravel.
