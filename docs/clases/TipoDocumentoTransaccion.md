# Clase TipoDocumentoTransaccion

Esta clase maneja los tipos de documentos para transacciones en los tres módulos del sistema: Ahorros, Aportaciones y Créditos.

## Estructura de Módulos

| Módulo | ID | Nombre |
|--------|-----|---------|
| Ahorros | 1 | AHORROS |
| Aportaciones | 2 | APORTACIONES |
| Créditos | 3 | CRÉDITOS |

## Tipos de Documentos Predefinidos

### Ahorros y Aportaciones (por letras)
| Tipo | Descripción |
|------|-------------|
| E | EFECTIVO |
| C | CHEQUE |
| D | DEPÓSITOS A BANCOS |
| T | TRANSFERENCIAS |

### Créditos (por números)
| Tipo | Descripción |
|------|-------------|
| 1 | EFECTIVO |
| 2 | BANCOS |
| 3 | TRANSFERENCIAS |
| 4 | REFINANCIAMIENTO |

## Tipos Personalizados

### Ahorros y Aportaciones
Los tipos personalizados se almacenan en la tabla `tb_documentos_transacciones` y se identifican por su **ID numérico**.

### Créditos
Los tipos personalizados para créditos se almacenan con un **prefijo `d_X`** donde `X` es el ID del tipo de documento creado, para evitar conflictos con los tipos predefinidos.

## Métodos Principales

### `getDescripcion($tipoDocumento, $modulo)`

Obtiene la descripción descriptiva del tipo de documento.

**Parámetros:**
- `$tipoDocumento`: El tipo de documento (string o int)
- `$modulo`: El módulo (1=Ahorros, 2=Aportaciones, 3=Créditos)

**Retorna:** `string|null` - La descripción del tipo o null si no se encuentra

**Nota:** La clase maneja automáticamente su propia conexión a la base de datos para consultar tipos personalizados.

**Ejemplos:**
```php
// Tipos predefinidos
$desc = TipoDocumentoTransaccion::getDescripcion('E', 1); // "EFECTIVO"
$desc = TipoDocumentoTransaccion::getDescripcion('2', 3); // "BANCOS"

// Tipos personalizados (la clase maneja automáticamente la conexión)
$desc = TipoDocumentoTransaccion::getDescripcion(15, 1); // Descripción del tipo ID 15
$desc = TipoDocumentoTransaccion::getDescripcion('d_8', 3); // Descripción del tipo crédito personalizado ID 8
```

### `getTiposDisponibles($modulo)`

Obtiene todos los tipos de documentos disponibles para un módulo.

**Parámetros:**
- `$modulo`: El módulo (1, 2 o 3)

**Retorna:** `array` - Array asociativo con tipo => descripción

**Nota:** La clase maneja automáticamente su propia conexión a la base de datos para incluir tipos personalizados.

**Ejemplos:**
```php
// Incluye automáticamente tipos predefinidos y personalizados
$tipos = TipoDocumentoTransaccion::getTiposDisponibles(1);
// ['E' => 'EFECTIVO', 'C' => 'CHEQUE', 'D' => 'DEPÓSITOS A BANCOS', 'T' => 'TRANSFERENCIAS', '15' => 'TIPO PERSONALIZADO']

$tipos = TipoDocumentoTransaccion::getTiposDisponibles(3);
// ['1' => 'EFECTIVO', '2' => 'BANCOS', '3' => 'TRANSFERENCIAS', '4' => 'REFINANCIAMIENTO', 'd_5' => 'TIPO PERSONALIZADO']
```

### `esValido($tipoDocumento, $modulo)`

Verifica si un tipo de documento es válido para un módulo.

**Parámetros:**
- `$tipoDocumento`: El tipo de documento
- `$modulo`: El módulo

**Retorna:** `bool` - True si es válido, false en caso contrario

**Nota:** La clase maneja automáticamente su propia conexión a la base de datos para verificar tipos personalizados.

**Ejemplos:**
```php
$esValido = TipoDocumentoTransaccion::esValido('E', 1); // true
$esValido = TipoDocumentoTransaccion::esValido('X', 1); // false
$esValido = TipoDocumentoTransaccion::esValido('d_10', 3); // true/false según exista en BD
```

### `getNombreModulo($modulo)`

Obtiene el nombre del módulo.

**Parámetros:**
- `$modulo`: El ID del módulo

**Retorna:** `string|null` - El nombre del módulo

**Ejemplos:**
```php
$nombre = TipoDocumentoTransaccion::getNombreModulo(1); // "AHORROS"
$nombre = TipoDocumentoTransaccion::getNombreModulo(3); // "CRÉDITOS"
```

## Métodos Utilitarios

### `formatearTipoCreditoPersonalizado($id)`

Convierte un ID de tipo personalizado al formato con prefijo para créditos.

```php
$tipo = TipoDocumentoTransaccion::formatearTipoCreditoPersonalizado(25); // "d_25"
```

### `extraerIdDeTipoCreditoPersonalizado($tipoConPrefijo)`

Extrae el ID de un tipo de crédito con prefijo.

```php
$id = TipoDocumentoTransaccion::extraerIdDeTipoCreditoPersonalizado('d_25'); // 25
```

## Ejemplos de Uso Práctico

### Función Helper para Mostrar Tipos

```php
function mostrarTipoDocumento($tipoDocumento, $modulo) {
    $nombreModulo = TipoDocumentoTransaccion::getNombreModulo($modulo);
    $descripcion = TipoDocumentoTransaccion::getDescripcion($tipoDocumento, $modulo);
    
    if ($descripcion) {
        return "$nombreModulo - Tipo: $tipoDocumento ($descripcion)";
    } else {
        return "$nombreModulo - Tipo: $tipoDocumento (TIPO NO VÁLIDO)";
    }
}

// Uso
echo mostrarTipoDocumento('E', 1); // "AHORROS - Tipo: E (EFECTIVO)"
echo mostrarTipoDocumento('2', 3); // "CRÉDITOS - Tipo: 2 (BANCOS)"
```

### Generar Select HTML

```php
function generarSelectTiposDocumento($modulo, $selectedValue = '') {
    $tipos = TipoDocumentoTransaccion::getTiposDisponibles($modulo);
    $nombreModulo = TipoDocumentoTransaccion::getNombreModulo($modulo);
    
    $html = "<select name='tipo_documento' class='form-control'>";
    $html .= "<option value=''>Seleccionar tipo de documento para $nombreModulo</option>";
    
    foreach ($tipos as $tipo => $descripcion) {
        $selected = ($tipo == $selectedValue) ? 'selected' : '';
        $html .= "<option value='$tipo' $selected>$descripcion</option>";
    }
    
    $html .= "</select>";
    return $html;
}
```

### Validación en Formularios

```php
function validarTipoDocumento($tipoDocumento, $modulo) {
    if (empty($tipoDocumento)) {
        return "Debe seleccionar un tipo de documento";
    }
    
    if (!TipoDocumentoTransaccion::esValido($tipoDocumento, $modulo)) {
        $nombreModulo = TipoDocumentoTransaccion::getNombreModulo($modulo);
        return "El tipo de documento '$tipoDocumento' no es válido para $nombreModulo";
    }
    
    return null; // No hay errores
}
```

## Consideraciones Importantes

1. **Conexión Automática**: La clase maneja automáticamente su propia conexión a la base de datos usando `DatabaseAdapter`. No es necesario pasar una instancia de base de datos.

2. **Optimización de Recursos**: Las conexiones se abren y cierran automáticamente para cada consulta, optimizando el uso de recursos.

3. **Créditos Personalizados**: Los tipos personalizados para créditos usan el prefijo `d_` para evitar conflictos con los tipos predefinidos.

4. **Manejo de Errores**: Todos los métodos manejan excepciones internamente y retornan `null` o `false` en caso de error.

5. **Case Sensitive**: Los tipos predefinidos son case-sensitive ('E' != 'e').

6. **Validación**: Siempre usa `esValido()` antes de procesar un tipo de documento.

7. **Logs**: La clase registra automáticamente errores de conexión usando la clase `Log` del sistema.

## Integración con el Sistema Existente

Esta clase está diseñada para integrarse perfectamente con el sistema existente:

- Compatible con la tabla `tb_documentos_transacciones`
- Respeta los tipos predefinidos ya implementados
- Sigue las convenciones de nomenclatura del proyecto
- Maneja la lógica de prefijos para créditos como se especificó

## Namespace

La clase utiliza el namespace `App\Generic` para mantener consistencia con otras clases del sistema como `TipoPoliza`.
