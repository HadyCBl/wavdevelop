# CORRECCIONES Y CRUDS - Banca Virtual

## 1. Corrección de Consulta de Clientes

### Problema Identificado
La consulta en `obtener_clientes_simple.php` usaba nombres de columnas incorrectos.

### Estructura Real de `tb_cliente`:
```sql
-- Nombres
primer_name, segundo_name, tercer_name

-- Apellidos  
primer_last, segundo_last, casada_last

-- Otros campos importantes
idcod_cliente, no_identifica, estado, compl_name, short_name
```

### Consulta CORREGIDA:
```sql
SELECT 
    idcod_cliente AS codigo,
    CONCAT(
        COALESCE(primer_name, ''), ' ',
        COALESCE(segundo_name, ''), ' ',
        COALESCE(tercer_name, ''), ' ',
        COALESCE(primer_last, ''), ' ',
        COALESCE(segundo_last, ''), ' ',
        COALESCE(casada_last, '')
    ) AS nombre
FROM tb_cliente 
WHERE estado = 1
ORDER BY primer_name, primer_last
LIMIT 5000
```

---

## 2. CRUD para `cre_prod_public`

### Estructura de la Tabla:
```sql
CREATE TABLE cre_prod_public (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nombre VARCHAR(191) NOT NULL,
    descripcion TEXT,
    published TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### Archivo Creado:
`src/cruds/crud_productos_publicos.php`

### Operaciones Implementadas:

#### ✅ guardarProductoPublico
```php
POST: [
    'action' => 'guardarProductoPublico',
    'token' => CSRF_TOKEN,
    'nomPro' => 'Nombre del producto',
    'desPro' => 'Descripción',
    'published' => 0|1
]
```

**Validaciones:**
- Token CSRF válido
- Nombre obligatorio (mínimo 3 caracteres)
- Published: 0 (no publicado) o 1 (publicado)

**Respuesta:**
```json
{"status": 1, "msg": "Producto guardado correctamente"}
```

#### ✅ actualizarProductoPublico
```php
POST: [
    'action' => 'actualizarProductoPublico',
    'token' => CSRF_TOKEN,
    'idPro' => 123,
    'nomPro' => 'Nombre actualizado',
    'desPro' => 'Descripción actualizada',
    'published' => 0|1
]
```

#### ✅ cambiarEstadoProductoPublico
```php
POST: [
    'action' => 'cambiarEstadoProductoPublico',
    'token' => CSRF_TOKEN,
    'tempId' => 123,
    'tempPublished' => 0|1
]
```

#### ✅ eliminarProductoPublico
```php
POST: [
    'action' => 'eliminarProductoPublico',
    'token' => CSRF_TOKEN,
    'tempIdEliminar' => 123
]
```

---

## 3. CRUD para `services_public`

### Estructura de la Tabla:
```sql
CREATE TABLE services_public (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    image VARCHAR(256),
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### Archivo Creado:
`src/cruds/crud_servicios_publicos.php`

### Operaciones Implementadas:

#### ✅ guardarServicioPublico
```php
POST: [
    'action' => 'guardarServicioPublico',
    'token' => CSRF_TOKEN,
    'titSer' => 'Título del servicio',
    'bodSer' => 'Descripción del servicio'
]

FILES: [
    'imgSer' => archivo_imagen (opcional)
]
```

**Validaciones:**
- Token CSRF válido
- Título obligatorio (mínimo 3 caracteres)
- Descripción obligatoria (mínimo 10 caracteres)
- Imagen opcional (JPG, PNG, GIF, WEBP)

**Upload de Imagen:**
- Directorio: `public/uploads/servicios/`
- Formato nombre: `servicio_{timestamp}_{uniqid}.{ext}`
- Validación de extensión
- Creación automática de directorio

**Respuesta:**
```json
{"status": 1, "msg": "Servicio guardado correctamente"}
```

#### ✅ actualizarServicioPublico
```php
POST: [
    'action' => 'actualizarServicioPublico',
    'token' => CSRF_TOKEN,
    'idSer' => 456,
    'titSer' => 'Título actualizado',
    'bodSer' => 'Descripción actualizada',
    'imgActual' => 'public/uploads/servicios/imagen_actual.jpg'
]

FILES: [
    'imgSer' => nueva_imagen (opcional)
]
```

**Funcionalidad:**
- Si se envía nueva imagen: reemplaza la anterior y elimina archivo viejo
- Si no se envía imagen: mantiene la actual
- Actualiza campos title, body e image

#### ✅ eliminarServicioPublico
```php
POST: [
    'action' => 'eliminarServicioPublico',
    'token' => CSRF_TOKEN,
    'tempIdEliminar' => 456
]
```

**Funcionalidad:**
- Elimina registro de la base de datos
- Elimina archivo de imagen del servidor (si existe)

---

## 4. Integración con Frontend

### Para Productos (`vbview001.php` - case 'Productos_virtual_bank'):

**Cambiar la ruta del CRUD:**
```javascript
// ANTES (no existe)
obtiene([...], 'guardarProductoPublico', ...)

// DESPUÉS
obtiene(
    ['token','nomPro','desPro'],
    ['published'],
    [],
    'guardarProductoPublico',
    '0',
    ['<?php echo $codusu; ?>'],
    function(data2) { location.reload(); },
    false,
    '',
    '../../../src/cruds/crud_productos_publicos.php' // ← AGREGAR ESTA RUTA
)
```

### Para Servicios (`vbview001.php` - case 'Servicios_virtual_bank'):

**Debido a que se maneja upload de imágenes, usar FormData:**
```javascript
function guardarServicioPublico() {
    var formData = new FormData();
    formData.append('action', 'guardarServicioPublico');
    formData.append('token', $('#token').val());
    formData.append('titSer', $('#titSer').val());
    formData.append('bodSer', $('#bodSer').val());
    
    // Agregar imagen si existe
    var imgFile = $('#imgSer')[0].files[0];
    if (imgFile) {
        formData.append('imgSer', imgFile);
    }
    
    $.ajax({
        url: '../../../src/cruds/crud_servicios_publicos.php',
        method: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        beforeSend: function() {
            loaderefect(1);
        },
        success: function(resp) {
            let res = typeof resp === 'string' ? JSON.parse(resp) : resp;
            if (res.status == 1) {
                Swal.fire('Correcto', res.msg, 'success');
                location.reload();
            } else {
                Swal.fire('Error', res.msg, 'error');
            }
        },
        error: function() {
            Swal.fire('Error', 'No se pudo procesar la solicitud', 'error');
        },
        complete: function() {
            loaderefect(0);
        }
    });
}
```

---

## 5. Seguridad Implementada

### ✅ Protección CSRF
- Validación de token en todas las operaciones
- Token único por sesión

### ✅ Validación de Sesión
- Verificación de usuario autenticado
- Respuesta 401 si no hay sesión

### ✅ Prepared Statements
- Protección contra SQL Injection
- Binding de parámetros tipados

### ✅ Validación de Imágenes
- Extensiones permitidas: jpg, jpeg, png, gif, webp
- Nombres únicos con timestamp + uniqid
- Eliminación de archivos huérfanos

### ✅ Output Buffering
- Limpieza de salida antes de JSON
- Headers correctos (Content-Type)
- Codificación UTF-8

---

## 6. Resumen de Archivos

### Archivos Modificados:
1. ✅ `src/server_side/obtener_clientes_simple.php` - Consulta corregida

### Archivos Creados:
2. ✅ `src/cruds/crud_productos_publicos.php` - CRUD completo
3. ✅ `src/cruds/crud_servicios_publicos.php` - CRUD con upload

### Directorios Necesarios:
4. ✅ `public/uploads/servicios/` - Creación automática

---

## 7. Testing

### Checklist de Pruebas:

#### Selector de Clientes:
- [ ] Carga correcta de clientes al abrir página
- [ ] Nombres completos se muestran correctamente
- [ ] Selección auto-completa formulario

#### Productos Públicos:
- [ ] Guardar producto nuevo
- [ ] Actualizar producto existente
- [ ] Publicar/Despublicar producto
- [ ] Eliminar producto
- [ ] Validación de campos obligatorios

#### Servicios Públicos:
- [ ] Guardar servicio sin imagen
- [ ] Guardar servicio con imagen
- [ ] Actualizar servicio sin cambiar imagen
- [ ] Actualizar servicio con nueva imagen
- [ ] Eliminar servicio (y su imagen)
- [ ] Validación de formato de imagen

---

**Fecha**: 2025-10-26  
**Estado**: ✅ Implementado y listo para pruebas  
**Próximo paso**: Integrar rutas en el frontend
