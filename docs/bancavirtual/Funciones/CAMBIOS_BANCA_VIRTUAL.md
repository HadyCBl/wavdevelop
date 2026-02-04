# Cambios Realizados en Banca Virtual

## Resumen
Se actualizó el módulo de creación de usuarios de Banca Virtual para usar el mismo patrón seguro que Cuentas por Cobrar, con validación CSRF y uso de la función `obtiene()`.

## Archivos Modificados

### 1. `src/cruds/crud_banca.php`
**Cambios:**
- ✅ Agregado output buffering (`ob_start()`) para evitar HTML antes del JSON
- ✅ Agregada función `sendJSON()` para respuestas limpias
- ✅ Agregada validación CSRF en todos los casos (crear, editar, eliminar)
- ✅ Validación de conexión a base de datos `$virtual` (Banca Virtual)
- ✅ Validación de existencia de cliente en `tb_cliente` antes de crear credencial
- ✅ Mensajes de error más descriptivos

**Funcionalidad:**
- Ahora se conecta correctamente a la BD de Banca Virtual (`ebsvnkfs_api_bank2`)
- Valida que el cliente exista en el core bancario antes de crear usuario
- Protección CSRF en todas las operaciones

### 2. `views/indicadores/bancavirtual/views/vbview001.php`
**Cambios:**
- ✅ Agregado campo CSRF token: `<?php echo $csrf->getTokenField(); ?>`
- ✅ Actualizado botón "Generar Credencial" para usar `obtiene()`
- ✅ Agregado atributo `required` y `data-label` a campos obligatorios
- ✅ Agregada función `limpiarFormulario()`
- ✅ Callback para recargar DataTable después de crear credencial

**Nuevo código del botón:**
```javascript
onclick="obtiene(
    ['csrf_token_name','codcli','usuario','pass'], 
    [], [], 
    'crear_credencial', 
    '0', 
    [], 
    function() { limpiarFormulario(); cargarCredenciales(); }, 
    false, 
    '', 
    '../../../src/cruds/crud_banca.php'
)"
```

## Tareas Pendientes

### Eliminar código obsoleto en vbview001.php
**Acción requerida:** Eliminar los event handlers `$('#formCredencial').on('submit'...)` y `$('#formEditarCredencial').on('submit'...)` ya que ahora se usa `obtiene()`.

**Ubicación:** Alrededor de la línea 422-490

**Código a eliminar:**
```javascript
// Submit formulario de crear credencial
$('#formCredencial').on('submit', function(e) {
    e.preventDefault();
    // ... todo el bloque AJAX ...
});

// Submit formulario de editar credencial  
$('#formEditarCredencial').on('submit', function(e) {
    e.preventDefault();
    // ... todo el bloque AJAX ...
});
```

**Dejar solo:**
```javascript
// Document Ready
$(document).ready(function() {
    cargarCredenciales();
});
```

### Actualizar función eliminarCredencial()
**Acción requerida:** Convertir la función `eliminarCredencial()` para usar `obtiene()` en lugar de AJAX directo.

**Código actual (línea ~364):**
```javascript
function eliminarCredencial(id, usuario) {
    Swal.fire({
        // confirmación...
    }).then((result) => {
        if (result.isConfirmed) {
            $.ajax({
                url: '../../src/cruds/crud_banca.php',
                method: 'POST',
                data: {
                    action: 'eliminar_credencial',
                    id: id
                },
                // ...
            });
        }
    });
}
```

**Código sugerido:**
```javascript
function eliminarCredencial(id, usuario) {
    // Crear input hidden temporal con el ID
    const tempInput = $('<input>').attr({
        type: 'hidden',
        id: 'temp_id_credencial',
        value: id
    }).appendTo('body');

    obtiene(
        ['csrf_token_name', 'temp_id_credencial'],
        [],
        [],
        'eliminar_credencial',
        '0',
        [],
        function() {
            $('#temp_id_credencial').remove();
            cargarCredenciales();
        },
        true,
        `¿Está seguro de eliminar la credencial del usuario: ${usuario}?`,
        '../../../src/cruds/crud_banca.php'
    );
}
```

### Actualizar Modal de Editar
**Acción requerida:** El modal de editar también debe usar `obtiene()` en lugar del form submit.

**Cambiar el form submit por un botón con onclick:**
```html
<button
    onclick="editarCredencialConObtiene()"
    type="button"
    class="...">
    Actualizar Credencial
</button>
```

**Agregar función JavaScript:**
```javascript
function editarCredencialConObtiene() {
    obtiene(
        ['csrf_token_name', 'edit_id', 'edit_usuario', 'edit_pass'],
        [],
        [],
        'editar_credencial',
        '0',
        [],
        function() {
            cerrarModalEditar();
            cargarCredenciales();
        },
        false,
        '',
        '../../../src/cruds/crud_banca.php'
    );
}
```

## Flujo Completo Actualizado

1. **Seleccionar Cliente** → Modal abre desde botón "Buscar Cliente"
2. **seleccionar_cliente(datos)** → Llena campos automáticamente con:
   - `codcli`: Código del cliente
   - `nombrecli`: Nombre completo
   - `usuario`: Generado como "u" + últimos 4 dígitos
   - `pass`: Contraseña aleatoria de 8 caracteres
3. **Click "Generar Credencial"** → `obtiene()` envía datos con token CSRF
4. **crud_banca.php valida:**
   - Token CSRF válido
   - Cliente existe en `tb_cliente` (core bancario)
   - Conexión a BD Banca Virtual (`$virtual`)
5. **Inserta en BD Banca Virtual** → Tabla `users` en `ebsvnkfs_api_bank2`
6. **Callback ejecuta** → Limpia formulario y recarga DataTable

## Validaciones Implementadas

### En CRUD (crud_banca.php):
- ✅ Sesión activa (`$_SESSION['id_agencia']`)
- ✅ Token CSRF válido
- ✅ Cliente existe y está activo en `tb_cliente`
- ✅ Conexión a BD Banca Virtual disponible
- ✅ Campos requeridos: `codcli`, `usuario`, `pass`

### En Frontend (vbview001.php):
- ✅ Campos con `required` en HTML5
- ✅ Validación automática de `obtiene()` antes de enviar
- ✅ Campos readonly para evitar edición manual de cliente

## Pruebas Recomendadas

1. **Crear Credencial:**
   - Seleccionar cliente del modal
   - Verificar que usuario/password se generen automáticamente
   - Click "Generar Credencial"
   - Verificar mensaje de éxito
   - Verificar que aparece en DataTable
   - Verificar en BD `ebsvnkfs_api_bank2.users`

2. **Validaciones:**
   - Intentar guardar sin seleccionar cliente → Debe fallar
   - Intentar con cliente inactivo → Debe rechazar
   - Token CSRF expirado → Debe rechazar

3. **Editar Credencial:**
   - Click en botón editar
   - Modificar usuario o contraseña
   - Guardar cambios
   - Verificar actualización en DataTable

4. **Eliminar Credencial:**
   - Click en botón eliminar
   - Confirmar eliminación
   - Verificar desaparece de DataTable
   - Verificar eliminación en BD

## Conexión a Base de Datos

**Base de Datos Principal (Core Bancario):**
- Conexión: `$conexion` 
- Uso: Validar clientes en `tb_cliente`

**Base de Datos Banca Virtual:**
- Conexión: `$virtual`
- Host: `50.31.177.130`
- BD: `ebsvnkfs_api_bank2`
- Usuario: `ebsvnkfs_andres2905`
- Uso: Almacenar usuarios en tabla `users`

**Tabla `users` (Banca Virtual):**
- `id`: INT AUTO_INCREMENT
- `name`: VARCHAR (nombre de usuario)
- `email`: VARCHAR (código de cliente)
- `password`: VARCHAR (hash bcrypt)
- `created_at`: TIMESTAMP
- `updated_at`: TIMESTAMP

## Compatibilidad

✅ **Compatible con:** 
- Módulo Cuentas por Cobrar
- Sistema de CSRF global
- Validaciones de `script_indicadores.js`
- DataTables existente
- SweetAlert2 para mensajes

⚠️ **Requiere:**
- jQuery
- SweetAlert2
- DataTables
- `script_indicadores.js` incluido
- Conexión `$virtual` configurada en `.env`

## Notas Importantes

1. **No modificar** la tabla `users` directamente en la BD, siempre usar el CRUD
2. **Password**: Se almacena con hash bcrypt, NO en texto plano
3. **Email field**: Se usa para almacenar el `codcli` (código de cliente)
4. **ON DUPLICATE KEY UPDATE**: Si el email (codcli) ya existe, actualiza el registro
5. **Token CSRF**: Se regenera automáticamente después de cada operación

## Seguridad

- ✅ CSRF Protection en todas las operaciones
- ✅ Passwords hasheados con bcrypt
- ✅ Output buffering para evitar contaminación JSON
- ✅ Validación de sesión activa
- ✅ Prepared statements en todas las consultas SQL
- ✅ Validación de conexión a BD antes de operar
- ✅ Mensajes de error genéricos al usuario (detalles en logs)
