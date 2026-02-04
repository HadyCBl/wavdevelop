# Ejemplos de Uso del Validator

## Campos Opcionales con Validación Condicional

El Validator ahora soporta campos opcionales mediante las reglas `optional` o `nullable`. Cuando un campo tiene esta regla, si está vacío no se validarán las demás reglas, pero si tiene un valor, se aplicarán todas las validaciones especificadas.

### Ejemplo 1: Email Opcional

```php
use Micro\Generic\Validator;

$data = [
    'nombre' => 'Juan Pérez',
    'email' => '',  // Vacío, no generará error
];

$rules = [
    'nombre' => 'required|string|min_length:3|max_length:100',
    'email' => 'optional|email',  // Opcional, pero si se ingresa debe ser email válido
];

$validator = Validator::make($data, $rules);

if ($validator->fails()) {
    echo "Errores: " . json_encode($validator->errors());
} else {
    echo "Validación exitosa!";
}
// Resultado: Validación exitosa!
```

### Ejemplo 2: Email Opcional con Valor Inválido

```php
$data = [
    'nombre' => 'Juan Pérez',
    'email' => 'correo-invalido',  // Tiene valor pero no es email válido
];

$rules = [
    'nombre' => 'required|string|min_length:3|max_length:100',
    'email' => 'optional|email',
];

$validator = Validator::make($data, $rules);

if ($validator->fails()) {
    echo "Errores: " . json_encode($validator->errors());
    // Resultado: {"email":["El campo email debe ser un email válido."]}
}
```

### Ejemplo 3: Teléfono Opcional con Formato

```php
$data = [
    'nombre' => 'Juan Pérez',
    'telefono' => '',  // Vacío, no genera error
];

$rules = [
    'nombre' => 'required|string',
    'telefono' => 'nullable|regex:/^[0-9]{10}$/',  // Opcional, pero si se ingresa debe ser 10 dígitos
];

$validator = Validator::make($data, $rules);
// Validación exitosa!
```

## Validación de Longitud vs Valor Numérico

El Validator ahora distingue correctamente entre validación de longitud de strings y validación de valores numéricos.

### Reglas Específicas Nuevas

- **`min_length:n`** / **`max_length:n`**: Valida la longitud de un string (número de caracteres)
- **`min_value:n`** / **`max_value:n`**: Valida el valor numérico (rango de números)

### Reglas Auto-Detectadas

- **`min:n`** / **`max:n`**: Auto-detecta si debe validar longitud o valor basándose en:
  - Si hay reglas `numeric`, `integer`, `min_value`, o `max_value` → valida como número
  - Si hay reglas `string`, `email`, `min_length`, o `max_length` → valida como string
  - Si no hay indicación clara → verifica el tipo del valor

### Ejemplo 1: Validación de Longitud de String

```php
$data = [
    'nombre' => 'Juan',
    'codigo' => '12345',  // String con números
];

$rules = [
    'nombre' => 'required|string|min_length:3|max_length:50',
    'codigo' => 'required|string|min_length:5|max_length:10',
];

$validator = Validator::make($data, $rules);
// Validación exitosa! - El código "12345" tiene 5 caracteres (longitud válida)
```

### Ejemplo 2: Validación de Valor Numérico

```php
$data = [
    'edad' => 25,
    'precio' => 150.50,
];

$rules = [
    'edad' => 'required|numeric|min_value:18|max_value:100',
    'precio' => 'required|numeric|min_value:10|max_value:1000',
];

$validator = Validator::make($data, $rules);
// Validación exitosa! - Los valores numéricos están en el rango correcto
```

### Ejemplo 3: String con Números - El Problema Resuelto

Antes, si tenías un string como "999" y usabas `min:5`, el validador lo trataba como número (999 >= 5) en lugar de validar su longitud (3 caracteres).

```php
// ❌ PROBLEMA ANTERIOR:
$data = ['codigo' => '999'];
$rules = ['codigo' => 'required|min:5'];
// Pasaba la validación porque 999 > 5 (validaba como número)

// ✅ SOLUCIÓN ACTUAL:
$data = ['codigo' => '999'];
$rules = ['codigo' => 'required|string|min:5'];
// Ahora falla correctamente porque el string tiene solo 3 caracteres, no 5

// O mejor aún, usar las reglas específicas:
$rules = ['codigo' => 'required|string|min_length:5|max_length:10'];
```

### Ejemplo 4: Auto-Detección con min/max

```php
$data = [
    'edad' => '25',  // String que parece número
    'nombre' => 'Ana',
];

$rules = [
    'edad' => 'required|numeric|min:18|max:100',  // numeric indica: validar como valor
    'nombre' => 'required|string|min:3|max:50',   // string indica: validar como longitud
];

$validator = Validator::make($data, $rules);
// Validación exitosa! - Edad valida el valor 25, nombre valida longitud 3
```

## Combinación de Reglas Opcionales y Validación de Tipo

### Ejemplo: Formulario de Registro Completo

```php
$data = [
    'nombre' => 'María García',
    'email' => 'maria@example.com',
    'telefono' => '',  // Opcional
    'edad' => 28,
    'codigo_postal' => '28001',
    'biografia' => '',  // Opcional
];

$rules = [
    'nombre' => 'required|string|min_length:3|max_length:100',
    'email' => 'required|email|max_length:255',
    'telefono' => 'nullable|regex:/^[0-9]{10}$/',  // Opcional: 10 dígitos
    'edad' => 'required|integer|min_value:18|max_value:120',
    'codigo_postal' => 'required|string|min_length:5|max_length:5',
    'biografia' => 'nullable|string|max_length:500',  // Opcional: hasta 500 caracteres
];

$validator = Validator::make($data, $rules);

if ($validator->passes()) {
    echo "Usuario registrado exitosamente!";
}
```

### Ejemplo: Producto con Precio Opcional

```php
$data = [
    'nombre_producto' => 'Laptop HP',
    'sku' => 'LAP-HP-001',
    'precio' => '',  // Precio opcional (puede agregarse después)
    'descuento' => 15,  // Descuento en porcentaje
];

$rules = [
    'nombre_producto' => 'required|string|min_length:3|max_length:100',
    'sku' => 'required|string|min_length:5|max_length:20',
    'precio' => 'nullable|numeric|min_value:0',  // Opcional pero si se ingresa debe ser >= 0
    'descuento' => 'nullable|numeric|min_value:0|max_value:100',  // Entre 0 y 100%
];

$validator = Validator::make($data, $rules);
```

## Mensajes de Error Personalizados

Puedes personalizar los mensajes de error para las nuevas reglas:

```php
$data = ['edad' => 15];

$rules = [
    'edad' => 'required|numeric|min_value:18|max_value:65',
];

$customMessages = [
    'edad.min_value' => 'Debes ser mayor de edad para registrarte.',
    'edad.max_value' => 'La edad máxima permitida es :max años.',
];

$validator = Validator::make($data, $rules, $customMessages);

if ($validator->fails()) {
    echo $validator->first('edad');
    // Resultado: "Debes ser mayor de edad para registrarte."
}
```

## Mejores Prácticas

1. **Usa `optional` o `nullable`** cuando un campo no sea obligatorio
2. **Usa reglas específicas** cuando sea posible:
   - `min_length`/`max_length` para strings
   - `min_value`/`max_value` para números
3. **Declara el tipo** con `string`, `numeric`, o `integer` para mayor claridad
4. **Combina reglas** para validaciones complejas pero mantenlas legibles

## Resumen de Cambios

### Antes ❌
```php
// Problema: "999" pasaba min:5 porque se validaba como número
$rules = ['codigo' => 'required|min:5'];  // Ambiguo
```

### Ahora ✅
```php
// Solución 1: Declarar el tipo
$rules = ['codigo' => 'required|string|min:5'];

// Solución 2: Usar reglas específicas (recomendado)
$rules = ['codigo' => 'required|string|min_length:5|max_length:10'];

// Campos opcionales con validación
$rules = ['email' => 'optional|email'];  // Solo valida si no está vacío
```
