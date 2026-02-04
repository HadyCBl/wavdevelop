# Guía de Solución: Error "Unexpected token '<' is not valid JSON"

## 📋 Descripción del Problema

Este error ocurre cuando el servidor devuelve HTML (generalmente un error de PHP) en lugar de JSON. El error completo es:
```
Uncaught SyntaxError: Unexpected token '<', "<br /><b>"... is not valid JSON
```

## 🔍 Causas Comunes

1. **Error de PHP en el servidor**: El CRUD está devolviendo un error de PHP con HTML
2. **Formato incorrecto de datos**: Los datos enviados no coinciden con lo que espera el CRUD
3. **Falta de manejo de errores**: El CRUD no está capturando excepciones correctamente

## 🛠️ Solución Paso a Paso

### **Paso 1: Verificar el Formato de Datos en el CRUD**

El CRUD espera datos en este formato específico:

```php
// CORRECTO - Formato esperado por el CRUD
$inputs = $_POST['inputs'] ?? [];   // Array asociativo con valores de inputs
$selects = $_POST['selects'] ?? []; // Array asociativo con valores de selects  
$radios = $_POST['radios'] ?? [];   // Array asociativo con valores de radios
$archivo = $_POST['archivo'] ?? []; // Array indexado con parámetros adicionales
$condi = $_POST['condi'];           // Acción a ejecutar

// Ejemplo de cómo vienen los datos:
// $_POST['inputs']['csrf_token'] = 'abc123'
// $_POST['inputs']['id_grupo'] = '5'
// $_POST['inputs']['id_cliente'] = 'CLI001'
// $_POST['archivo'][0] = 'parametro1'
// $_POST['condi'] = 'assign_client'
```

### **Paso 2: Estructura Correcta del Caso en el CRUD**

```php
case 'assign_client':
    // 1. Verificar sesión
    if (!isset($_SESSION['id_agencia'])) {
        echo json_encode([
            'messagecontrol' => "expired", 
            'mensaje' => 'Sesion expirada',
            'url' => BASE_URL
        ]);
        return;
    }

    // 2. Obtener datos del POST
    $inputs = $_POST['inputs'] ?? [];
    
    // 3. Validar CSRF
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        echo json_encode([
            'msg' => "Token CSRF inválido",
            'status' => 0
        ]);
        return;
    }

    // 4. Extraer y validar parámetros
    $id_grupo = intval($inputs['id_grupo'] ?? 0);
    $id_cliente = $inputs['id_cliente'] ?? '';
    
    // 5. Validaciones
    $validar = validacionescampos([
        [$id_grupo, "0", 'Debe seleccionar un grupo', 1],
        [$id_cliente, "", 'Debe seleccionar un cliente', 1],
    ]);
    
    if ($validar[2]) {
        echo json_encode([
            'msg' => $validar[0],
            'status' => $validar[1]
        ]);
        return;
    }

    // 6. Lógica de negocio con try-catch
    $showmensaje = false;
    try {
        $database->openConnection();
        
        // Verificaciones
        $grupoExiste = $database->selectColumns('cc_grupos', ['id'], "id=? AND deleted_by IS NULL", [$id_grupo]);
        if (empty($grupoExiste)) {
            $showmensaje = true;
            throw new Exception("El grupo seleccionado no existe");
        }

        // Operación
        $database->beginTransaction();
        
        $datosInsert = [
            'id_grupo' => $id_grupo,
            'id_cliente' => $id_cliente
        ];
        
        $resultado = $database->insert('cc_grupos_clientes', $datosInsert);
        
        if ($resultado) {
            $database->commit();
            echo json_encode([
                'msg' => 'Cliente asignado exitosamente',
                'status' => 1
            ]);
        } else {
            $database->rollback();
            $showmensaje = true;
            throw new Exception("Error al insertar la asignación");
        }
        
    } catch (Exception $e) {
        $database->rollback();
        if (!$showmensaje) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        }
        echo json_encode([
            'msg' => ($showmensaje) 
                ? $e->getMessage() 
                : "Error: Código ($codigoError)",
            'status' => 0
        ]);
    } finally {
        $database->closeConnection();
    }
    break;
```

### **Paso 3: Llamada Correcta desde el Frontend**

Usando la función `obtiene()` del sistema:

```javascript
// CORRECTO - Uso de obtiene() con script_indicadores.js
obtiene(
    ['csrf_token_name','id_grupo','id_cliente'],  // IDs de inputs
    [],                                             // IDs de selects (vacío si no hay)
    [],                                             // IDs de radios (vacío si no hay)
    'assign_client',                                // Acción (condi)
    '0',                                            // ID para printdiv2 (usado al recargar)
    [],                                             // Parámetros extra en archivo[] (vacío si no necesita)
    'NULL',                                         // Callback (NULL si no hay)
    false,                                          // ¿Mostrar confirmación?
    '',                                             // Mensaje de confirmación
    '../../../src/cruds/crud_cuentas_cobra.php'     // Ruta al archivo CRUD
);
```

**Importante**: Los inputs deben tener los IDs correctos en el HTML:

```html
<input type="text" id="id_grupo" name="id_grupo" required />
<input type="text" id="id_cliente" name="id_cliente" required />
<input type="hidden" name="csrf_token_name" id="csrf_token_name" value="token_value" />
```

### **Paso 4: Debugging - Cómo Encontrar el Error**

Si sigue fallando, agregar temporalmente en el CRUD:

```php
// AL INICIO DEL ARCHIVO CRUD (después de session_start)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ANTES DEL switch($condi)
file_put_contents('debug.log', print_r($_POST, true), FILE_APPEND);
```

Esto creará un archivo `debug.log` que mostrará exactamente qué datos están llegando.

## ✅ Checklist de Verificación

- [ ] El CRUD tiene `try-catch` en todos los casos
- [ ] Todos los `echo json_encode()` usan formato correcto: `['msg' => '...', 'status' => 0 o 1]`
- [ ] Los inputs en HTML tienen los IDs correctos
- [ ] La función `obtiene()` recibe los parámetros en el orden correcto
- [ ] El CRUD no tiene errores de PHP (verificar con error_reporting)
- [ ] La base de datos existe y las tablas están creadas
- [ ] Los campos en `$inputs` coinciden con los IDs del HTML

## 🚨 Errores Comunes a Evitar

### ❌ **ERROR 1: Formato incorrecto en obtiene()**
```javascript
// MAL - Esto causa el error JSON
obtiene(
    ['id_grupo'],  // Falta el csrf_token
    [], [],
    'assign_client',
    '0', [],
    'NULL', false, '',
    '../crud.php'
);
```

### ❌ **ERROR 2: No capturar excepciones en CRUD**
```php
// MAL - Error de PHP se mostrará como HTML
case 'assign_client':
    $id_grupo = $_POST['inputs']['id_grupo']; // Si no existe, error fatal
    // ... resto del código
```

### ❌ **ERROR 3: Mezclar formatos de respuesta**
```php
// MAL - Inconsistente con otras respuestas
echo json_encode(['message' => 'OK', 'success' => true]); // Diferente formato
```

### ✅ **CORRECTO: Formato consistente**
```php
// BIEN - Formato estándar del sistema
echo json_encode(['msg' => 'Operación exitosa', 'status' => 1]);
```

## 📝 Template Rápido para Nuevos Casos CRUD

```php
case 'nuevo_caso':
    if (!isset($_SESSION['id_agencia'])) {
        echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada', 'url' => BASE_URL]);
        return;
    }

    $inputs = $_POST['inputs'] ?? [];
    
    require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
    $csrf = new CSRFProtection();
    
    if (!($csrf->validateToken($inputs['csrf_token'] ?? '', false))) {
        echo json_encode(['msg' => "Token CSRF inválido", 'status' => 0]);
        return;
    }

    // Extraer parámetros
    $param1 = $inputs['param1'] ?? '';
    $param2 = intval($inputs['param2'] ?? 0);
    
    // Validaciones
    $validar = validacionescampos([
        [$param1, "", 'El parámetro 1 es obligatorio', 1],
        [$param2, "0", 'El parámetro 2 es obligatorio', 1],
    ]);
    
    if ($validar[2]) {
        echo json_encode(['msg' => $validar[0], 'status' => $validar[1]]);
        return;
    }

    $showmensaje = false;
    try {
        $database->openConnection();
        $database->beginTransaction();
        
        // Lógica aquí
        
        $database->commit();
        echo json_encode(['msg' => 'Operación exitosa', 'status' => 1]);
        
    } catch (Exception $e) {
        $database->rollback();
        if (!$showmensaje) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        }
        echo json_encode([
            'msg' => ($showmensaje) ? $e->getMessage() : "Error: Código ($codigoError)",
            'status' => 0
        ]);
    } finally {
        $database->closeConnection();
    }
    break;
```

## 🔧 Herramientas de Debugging

1. **Console del Navegador**: Ver el error exacto
2. **Network Tab**: Ver la respuesta real del servidor
3. **PHP Error Log**: Revisar errores de PHP
4. **file_put_contents()**: Crear logs personalizados

---

**Nota Final**: Siempre mantener el formato consistente en TODO el sistema para evitar estos errores.
