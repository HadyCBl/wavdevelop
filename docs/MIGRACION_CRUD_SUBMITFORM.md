# Guía de Migración: obtiene() a submitForm() con Controladores

## 📋 Índice
- [Introducción](#introducción)
- [Comparación Legacy vs Nuevo](#comparación-legacy-vs-nuevo)
- [La Función submitForm()](#la-función-submitform)
- [Cómo Migrar Operaciones CRUD](#cómo-migrar-operaciones-crud)
- [Ejemplos Prácticos](#ejemplos-prácticos)
- [Usando data-action](#usando-data-action)
- [Mejores Prácticas](#mejores-prácticas)

---

## Introducción

Este sistema permite la **migración gradual** de operaciones CRUD legacy (`obtiene()`) a controladores modernos con FastRoute, usando la nueva función `submitForm()`.

### ✅ Ventajas del Nuevo Sistema

- **Auto-recolección**: No necesitas listar manualmente inputs/selects/radios
- **Validación automática**: Usa atributo `required` de HTML5
- **Atributo `data-action`**: Define el endpoint directamente en el HTML
- **Código más limpio**: Menos parámetros, más legible
- **Separación de lógica**: Controladores manejan el negocio
- **Fallback automático**: Puede volver a sistema legacy si falla

---

## Comparación Legacy vs Nuevo

### Sistema Legacy (obtiene)

```javascript
// Debes listar manualmente cada campo
obtiene(
    ['nombre', 'apellido', 'dpi', 'telefono', 'email'], // inputs
    ['tipo_cliente', 'ciudad'],                         // selects
    ['genero'],                                          // radios
    'C',                                                 // condi
    null,                                                // id
    'clientes',                                          // archivo
    myCallback,                                          // callback
    '¿Está seguro de crear este cliente?',             // confirm
    'crud_clientes'                                      // fileDestino
);
```

**Problemas:**
- ❌ Mucha repetición de código
- ❌ Fácil olvidar campos
- ❌ Difícil de mantener
- ❌ Parámetros en orden específico

### Sistema Nuevo (submitForm)

```html
<div id="formCliente" data-action="/api/crud/clientes">
    <input id="nombre" name="nombre" required>
    <input id="apellido" name="apellido" required>
    <input id="dpi" name="dpi" required>
    <input id="telefono" name="telefono">
    <input id="email" name="email" type="email">
    <select id="tipo_cliente" name="tipo_cliente" required>
        <option value="">Seleccione...</option>
    </select>
</div>

<button onclick="guardarCliente()">Guardar</button>
```

```javascript
function guardarCliente() {
    submitForm('#formCliente', {
        condi: 'C',
        confirmMessage: '¿Está seguro de crear este cliente?',
        onSuccess: (data) => {
            console.log('Cliente creado:', data.id_cliente);
            // Hacer algo más...
        }
    });
}
```

**Ventajas:**
- ✅ Auto-detecta todos los campos `required`
- ✅ Menos código, más legible
- ✅ Endpoint definido en HTML
- ✅ Fácil de mantener

---

## La Función submitForm()

### Sintaxis Completa

```javascript
submitForm(containerSelector, options)
```

### Parámetros

**`containerSelector`** (string) - Selector del contenedor con los campos
- Puede ser un `<form>`, `<div>`, o cualquier elemento

**`options`** (object) - Configuración de la operación

| Opción | Tipo | Default | Descripción |
|--------|------|---------|-------------|
| `action` | string | data-action del elemento | Endpoint de la API |
| `condi` | string | 'C' | Condición: C=Crear, U=Actualizar, D=Eliminar |
| `id` | number/string | null | ID del registro (para U o D) |
| `extraData` | object | {} | Datos adicionales a enviar |
| `onSuccess` | function | null | Callback al éxito |
| `onError` | function | null | Callback al error |
| `confirmMessage` | string/false | false | Mensaje de confirmación |
| `successMessage` | string | null | Mensaje personalizado de éxito |
| `reloadView` | boolean | false | Recargar vista después |
| `viewToReload` | string | '#cuadro' | Vista a recargar |
| `useLegacy` | boolean | false | Forzar sistema legacy |
| `legacyParams` | array | null | Params para sistema legacy |

### Auto-recolección de Campos

`submitForm` recolecta automáticamente:

✅ **Inputs** con `name` o `id`:
```html
<input id="nombre" name="nombre" required>
<input type="email" id="email" required>
<input type="number" id="edad" min="18" max="99">
<input class="decimal-cleave-zen" id="monto"> <!-- Auto-desformatea -->
```

✅ **Selects** con `name` o `id`:
```html
<select id="ciudad" required>
    <option value="">Seleccione...</option>
    <option value="1">Guatemala</option>
</select>
```

✅ **Textareas**:
```html
<textarea id="observaciones" name="observaciones"></textarea>
```

✅ **Checkboxes**:
```html
<input type="checkbox" id="activo" name="activo"> <!-- Envía true/false -->
```

✅ **Radios**:
```html
<input type="radio" name="genero" value="M"> Masculino
<input type="radio" name="genero" value="F"> Femenino
<!-- Solo envía el seleccionado -->
```

---

## Cómo Migrar Operaciones CRUD

### Paso 1: Crear el Controlador

```php
<?php
// app/Controllers/MiCrudController.php

namespace App\Controllers;

use Exception;

class MiCrudController extends BaseCrudController
{
    public function handleCrud(): void
    {
        try {
            $this->database->openConnection();
            
            $condi = $this->getCondi();
            $id = $this->post('id');

            $this->handleByCondi($condi, [
                'C' => fn() => $this->crear(),
                'U' => fn() => $this->actualizar($id),
                'D' => fn() => $this->eliminar($id),
            ]);

        } catch (Exception $e) {
            $this->crudErrorResponse($e->getMessage());
        } finally {
            $this->database->closeConnection();
        }
    }

    private function crear(): void
    {
        // Validar campos requeridos
        $this->validateFields(['nombre', 'tipo']);

        // Obtener datos del formulario
        $nombre = $this->post('nombre');
        $tipo = $this->post('tipo');
        
        // ... lógica de negocio ...
        
        // Respuesta
        $this->crudSuccessResponse(
            'Registro creado exitosamente',
            ['id' => $nuevoId],
            true  // reprint = recargar vista
        );
    }

    private function actualizar($id): void
    {
        if (!$id) {
            $this->crudErrorResponse('ID no proporcionado');
            return;
        }
        
        // ... lógica ...
        
        $this->crudSuccessResponse('Actualizado exitosamente', [], true);
    }

    private function eliminar($id): void
    {
        // ... lógica ...
        
        $this->crudSuccessResponse('Eliminado exitosamente', [], true);
    }
}
```

### Paso 2: Registrar la Ruta

```php
// api/routes.php

$r->addGroup('/crud', function($r) {
    $r->addRoute('POST', '/mimodulo', 'MiCrudController@handleCrud');
});
```

### Paso 3: Actualizar el HTML

**ANTES:**
```html
<div id="cuadro">
    <input id="nombre" required>
    <select id="tipo" required>
        <!-- opciones -->
    </select>
    
    <button onclick="guardar()">Guardar</button>
</div>

<script>
function guardar() {
    obtiene(
        ['nombre'], ['tipo'], [],
        'C', null, 'mimodulo',
        null, false, 'crud_mimodulo'
    );
}
</script>
```

**AHORA:**
```html
<div id="formMiModulo" data-action="/api/crud/mimodulo">
    <input id="nombre" name="nombre" required>
    <select id="tipo" name="tipo" required>
        <!-- opciones -->
    </select>
    
    <button onclick="guardar()">Guardar</button>
</div>

<script>
function guardar() {
    submitForm('#formMiModulo', {
        condi: 'C',
        reloadView: true,
        viewToReload: '#cuadro'
    });
}
</script>
```

---

## Ejemplos Prácticos

### Ejemplo 1: Crear (Simple)

```html
<div id="formNuevo" data-action="/api/crud/productos">
    <input id="nombre" required data-label="Nombre del Producto">
    <input id="precio" type="number" min="0" required>
    <select id="categoria" required>
        <option value="">Seleccione...</option>
    </select>
</div>

<button onclick="crearProducto()">Crear</button>
```

```javascript
function crearProducto() {
    submitForm('#formNuevo', {
        condi: 'C',
        successMessage: 'Producto creado correctamente',
        onSuccess: (response) => {
            console.log('ID del producto:', response.id);
            limpiarFormulario();
        }
    });
}
```

### Ejemplo 2: Actualizar con Confirmación

```html
<div id="formEditar" data-action="/api/crud/clientes">
    <input type="hidden" id="id_cliente" value="123">
    <input id="nombre" required>
    <input id="apellido" required>
    <input id="email" type="email">
</div>

<button onclick="actualizarCliente()">Actualizar</button>
```

```javascript
function actualizarCliente() {
    const idCliente = document.getElementById('id_cliente').value;
    
    submitForm('#formEditar', {
        condi: 'U',
        id: idCliente,
        confirmMessage: '¿Está seguro de actualizar este cliente?',
        reloadView: true,
        onSuccess: (data) => {
            // Cerrar modal, etc.
            cerrarModal();
        }
    });
}
```

### Ejemplo 3: Eliminar

```javascript
function eliminarProducto(idProducto) {
    // No necesitas formulario para eliminar
    // Puedes crear un div invisible o usar extraData
    
    submitForm('body', {  // O cualquier contenedor
        action: '/api/crud/productos',
        condi: 'D',
        id: idProducto,
        confirmMessage: '¿Está seguro de eliminar este producto?',
        extraData: {
            motivo: 'Producto descontinuado'
        },
        reloadView: true
    });
}
```

### Ejemplo 4: Con Campos Decimales (cleave-zen)

```html
<div id="formCredito" data-action="/api/crud/creditos">
    <input class="decimal-cleave-zen" id="monto" required 
           data-decimals="2" data-prefix="Q ">
    <input class="decimal-cleave-zen" id="tasa_interes" required
           data-decimals="2">
</div>
```

```javascript
function guardarCredito() {
    submitForm('#formCredito', {
        condi: 'C',
        onSuccess: (data) => {
            // Los valores decimales se envían ya desformateados
            console.log('Monto guardado:', data.monto);
        }
    });
}
```

---

## Usando data-action

Puedes definir el endpoint de tres formas:

### Opción 1: Atributo `data-action` (Recomendado)

```html
<div id="miForm" data-action="/api/crud/clientes">
    <!-- campos -->
</div>

<script>
submitForm('#miForm', { condi: 'C' });
// Usa automáticamente /api/crud/clientes
</script>
```

### Opción 2: Atributo `action` (HTML Form)

```html
<form id="miForm" action="/api/crud/clientes">
    <!-- campos -->
</form>

<script>
submitForm('#miForm', { condi: 'C' });
</script>
```

### Opción 3: En el parámetro `options`

```html
<div id="miForm">
    <!-- campos -->
</div>

<script>
submitForm('#miForm', {
    action: '/api/crud/clientes',
    condi: 'C'
});
</script>
```

---

## Mejores Prácticas

### ✅ DO (Hacer)

1. **Usar `data-action` en el contenedor**
```html
<div id="form" data-action="/api/crud/modulo">
```

2. **Agregar `name` a todos los campos**
```html
<input id="nombre" name="nombre" required>
```

3. **Usar `data-label` para mensajes claros**
```html
<input id="fecha_inicio" data-label="Fecha de Inicio" required>
```

4. **Validar con atributos HTML5**
```html
<input type="email" required>
<input type="number" min="0" max="100">
<input minlength="3" maxlength="50">
```

5. **Usar callbacks para acciones post-éxito**
```javascript
submitForm('#form', {
    onSuccess: (data) => {
        cerrarModal();
        actualizarTabla();
    }
});
```

### ❌ DON'T (No hacer)

1. **No olvidar `required` en campos obligatorios**
```html
<!-- MAL -->
<input id="nombre">

<!-- BIEN -->
<input id="nombre" required>
```

2. **No hardcodear mensajes en JavaScript**
```javascript
// MAL
successMessage: 'Cliente creado'

// BIEN - Dejar que el controlador lo maneje
// El controlador retorna el mensaje apropiado
```

3. **No mezclar sistemas sin necesidad**
```javascript
// MAL - Usar ambos al mismo tiempo sin razón
obtiene(...);
submitForm(...);

// BIEN - Elegir uno y migrar gradualmente
submitForm(..., { useLegacy: false });
```

---

## Migración Paso a Paso

### Checklist de Migración

- [ ] **Fase 1: Preparación**
  - [ ] Controlador creado (extiende `BaseCrudController`)
  - [ ] Ruta registrada en `routes.php`
  - [ ] Métodos crear/actualizar/eliminar implementados

- [ ] **Fase 2: HTML**
  - [ ] Agregar `data-action` al contenedor
  - [ ] Verificar que todos los campos tengan `name` o `id`
  - [ ] Agregar `required` a campos obligatorios
  - [ ] Agregar `data-label` para mensajes personalizados

- [ ] **Fase 3: JavaScript**
  - [ ] Reemplazar `obtiene()` por `submitForm()`
  - [ ] Configurar callbacks si son necesarios
  - [ ] Configurar `reloadView` si aplica

- [ ] **Fase 4: Pruebas**
  - [ ] Probar crear registro
  - [ ] Probar actualizar registro
  - [ ] Probar eliminar registro
  - [ ] Verificar validaciones
  - [ ] Verificar mensajes de error/éxito

- [ ] **Fase 5: Cleanup**
  - [ ] Remover código legacy comentado
  - [ ] Documentar cambios
  - [ ] Actualizar guías de usuario si aplica

---

## Troubleshooting

### Error: "Contenedor no encontrado"
**Solución:** Verificar que el selector sea correcto y el elemento exista en el DOM

### Error: "Campos requeridos faltantes"
**Solución:** Asegurarse que todos los campos tengan `name` o `id`

### No se envían los datos
**Solución:** Verificar que los campos estén dentro del contenedor especificado

### Validación no funciona
**Solución:** Agregar atributo `required` a los campos obligatorios

### Endpoint no responde
**Solución:** 
1. Verificar que la ruta esté registrada en `routes.php`
2. Verificar que el controlador exista
3. Revisar logs del navegador y servidor

---

## Comparativa Rápida

| Característica | `obtiene()` Legacy | `submitForm()` Nuevo |
|----------------|-------------------|---------------------|
| Listar campos manualmente | ✅ Requerido | ❌ Auto-detecta |
| Validación | Manual en array | HTML5 `required` |
| Endpoint | Parámetro hardcoded | Atributo `data-action` |
| Confirmación | String en parámetro | Opción `confirmMessage` |
| Callback | Parámetro posicional | Opción `onSuccess` |
| Fallback | No disponible | ✅ Con `useLegacy` |
| Legibilidad | ⭐⭐ | ⭐⭐⭐⭐⭐ |
| Mantenibilidad | ⭐⭐ | ⭐⭐⭐⭐⭐ |

---

**¡Listo para migrar! 🚀** Comienza con un formulario pequeño y ve escalando gradualmente.
