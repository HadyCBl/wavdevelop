# Validator (App\Generic\Validator)

Clase ligera inspirada en `Illuminate/Validator` para validaciones en servidor. Permite definir reglas en formato string (`"required|email|min:3"`) o como array (`['required','email']`). Soporta mensajes por defecto y mensajes personalizados. Opcionalmente puede conectarse a la base de datos mediante `DatabaseAdapter` para las reglas `unique` y `exists`.

---

## Ubicación
app\Generic\Validator.php

## Constructor
```php
public function __construct(array $data, array $rules, array $messages = [], ?DatabaseAdapter $db = null)
```
- `$data`: array asociativo con los valores (ej. `$_POST`).
- `$rules`: array asociativo `campo => reglas`.
- `$messages`: mensajes personalizados (clave => plantilla).
- `$db`: instancia de `DatabaseAdapter` para reglas que requieren BD.

## Método estático
```php
Validator::make(array $data, array $rules, array $messages = [], ?DatabaseAdapter $db = null)
```
Crea una instancia rápidamente.

## Métodos principales
- `validate(): bool` — Ejecuta la validación y retorna si pasó (true) o no (false).
- `passes(): bool` — Alias de `validate()`.
- `fails(): bool` — Negación de `passes()`.
- `errors(): array` — Devuelve array asociativo de errores `campo => [mensajes...]`.
- `first(string $field): ?string` — Primer mensaje de error de un campo.

---

## Reglas soportadas
Formato de reglas: `"required|email|min:3|max:255|unique:tabla,columna,exceptValue,exceptColumn"`

### Reglas principales

**Validación de presencia:**
- `required` — Campo obligatorio.
- `optional` / `nullable` — Campo opcional. Si está vacío, no se validan las demás reglas.
- `validate_if:field,value` — Solo valida las reglas del campo cuando otro campo tiene un valor específico. Si la condición no se cumple, **ignora TODO el campo** (no valida nada, incluso si tiene valor).

**Validación de tipo:**
- `email` — Formato email válido.
- `numeric` — Valor numérico.
- `integer` — Entero.
- `string` — Cadena de texto.
- `date` — Fecha válida (strtotime).

**Validación de longitud (para strings):**
- `min_length:x` — Longitud mínima en caracteres.
- `max_length:x` — Longitud máxima en caracteres.

**Validación de valor (para números):**
- `min_value:x` — Valor numérico mínimo.
- `max_value:x` — Valor numérico máximo.

**Validación auto-detectada:**
- `min:x` — Mínimo. Auto-detecta si validar longitud (string) o valor (número).
- `max:x` — Máximo. Auto-detecta si validar longitud (string) o valor (número).
- `between:x,y` — Entre min y max (longitud o valor según tipo).
- `size:x` — Igual a tamaño/longitud o valor.

**Validación de opciones:**
- `in:opt1,opt2` — Debe estar en la lista.
- `regex:pattern` — Expresión regular (PCRE).
- `confirmed` — Campo debe coincidir con campo_confirmation.

**Validaciones de fechas:**
- `after:date` — Fecha posterior a otra fecha (ej: `after:2024-01-01` o `after:tomorrow`).
- `before:date` — Fecha anterior a otra fecha.
- `after_or_equal:date` — Fecha posterior o igual a otra fecha.
- `before_or_equal:date` — Fecha anterior o igual a otra fecha.
- `date_format:format` — Formato de fecha específico (ej: `date_format:Y-m-d` o `date_format:d/m/Y H:i`).

**Validaciones numéricas:**
- `digits:n` — Debe tener exactamente n dígitos (ej: `digits:4` para códigos PIN).
- `digits_between:min,max` — Entre n y m dígitos (ej: `digits_between:3,6`).
- `decimal:decimals` — Número decimal con decimales específicos (ej: `decimal:2` para precios).
- `multiple_of:value` — Debe ser múltiplo de un valor (ej: `multiple_of:5`).

**Validaciones de strings:**
- `starts_with:val1,val2` — Debe comenzar con alguno de los valores (ej: `starts_with:http://,https://`).
- `ends_with:val1,val2` — Debe terminar con alguno de los valores (ej: `ends_with:.jpg,.png`).
- `contains:value` — Debe contener el texto especificado (ej: `contains:@`).
- `lowercase` — Todo el texto debe estar en minúsculas.
- `uppercase` — Todo el texto debe estar en MAYÚSCULAS.

**Validaciones relacionales (comparación entre campos):**
- `same:field` — Debe ser igual a otro campo (ej: `same:password` para confirmación).
- `different:field` — Debe ser diferente a otro campo.
- `gt:field` — Mayor que otro campo (valor numérico o longitud de string).
- `gte:field` — Mayor o igual que otro campo.
- `lt:field` — Menor que otro campo.
- `lte:field` — Menor o igual que otro campo.

**Validaciones condicionales avanzadas:**
- `required_if:field,value` — Requerido si otro campo tiene cierto valor (ej: `required_if:tipo,empresa`). Si el campo es `optional`, valida las demás reglas si hay valor.
- `required_unless:field,value1,value2` — Requerido a menos que otro campo tenga alguno de los valores.
- `required_with:field1,field2` — Requerido si alguno de los campos está presente.
- `required_without:field1,field2` — Requerido si alguno de los campos NO está presente.
- `validate_if:field,value` — **Solo valida TODO el campo** cuando otro campo tiene un valor específico. Diferente a `required_if`: si la condición es falsa, ignora completamente el campo (no valida nada).

**Validación con base de datos:**
- `unique:table,column,exceptValue,exceptColumn` — Requiere `DatabaseAdapter`. Comprueba que no exista.
  - Ej: `unique:users,email` o `unique:users,email,5,id` (ignora id=5).
- `exists:table,column` — Requiere `DatabaseAdapter`. Comprueba que exista el valor en la tabla.

> **Nota:** Reglas desconocidas se ignoran para no bloquear el flujo.

### Campos opcionales con validación condicional

Usa `optional` o `nullable` para campos que no son obligatorios pero que deben cumplir ciertas reglas si se ingresan:

```php
$rules = [
    'email' => 'optional|email',  // Email no es obligatorio, pero si se ingresa debe ser válido
    'telefono' => 'nullable|regex:/^[0-9]{10}$/',  // Teléfono opcional con formato específico
];
```

### Diferencia entre `required_if` y `validate_if`

**Use `required_if` con `optional`** cuando:
- El campo es **obligatorio bajo ciertas condiciones**
- Pero si tiene valor en **cualquier caso**, debe ser válido

```php
// Ejemplo: Fecha obligatoria si checkbox=true, pero si se ingresa siempre debe ser válida
'fecha' => 'required_if:check,true|optional|date|before_or_equal:today'

// Comportamiento:
// check=false, fecha='' → ✅ PASA (opcional)
// check=false, fecha='2027-01-01' → ❌ FALLA (valida fecha futura)
// check=true, fecha='' → ❌ FALLA (requerido)
// check=true, fecha='2026-01-15' → ✅ PASA
```

**Use `validate_if`** cuando:
- El campo **solo es relevante** bajo ciertas condiciones
- Si la condición no se cumple, el campo es **completamente ignorado**

```php
// Ejemplo: Fecha solo importa si checkbox=true, sino se ignora completamente
'fecha' => 'validate_if:check,true|required|date|before_or_equal:today'

// Comportamiento:
// check=false, fecha='' → ✅ PASA (ignora todo)
// check=false, fecha='2027-01-01' → ✅ PASA (ignora todo, incluso fecha inválida)
// check=true, fecha='' → ❌ FALLA (requerido)
// check=true, fecha='2026-01-15' → ✅ PASA
```

### Diferencia entre min/max y min_length/max_length

**Problema anterior:** Un string como `"999"` con regla `min:5` pasaba la validación porque se evaluaba como número (999 >= 5) en lugar de validar su longitud (3 caracteres).

**Solución actual:**

1. **Usar reglas específicas (recomendado):**
```php
$rules = [
    'codigo' => 'required|string|min_length:5|max_length:10',  // Valida longitud
    'edad' => 'required|numeric|min_value:18|max_value:100',   // Valida valor
];
```

2. **Auto-detección con min/max:**
```php
$rules = [
    'nombre' => 'required|string|min:3|max:50',    // Auto-detecta: valida longitud
    'edad' => 'required|numeric|min:18|max:100',   // Auto-detecta: valida valor
];
```

La auto-detección se basa en:
- Si hay reglas `numeric`, `integer`, `min_value`, o `max_value` → valida como número
- Si hay reglas `string`, `email`, `min_length`, o `max_length` → valida como string
- Si no hay indicación clara → verifica el tipo del valor

---

## Mensajes por defecto y personalizados
Mensajes por defecto se definen en la clase. Puedes pasar `$messages` con claves iguales a las reglas para sobreescribir:
```php
$messages = [
  'required' => 'El campo :attribute es obligatorio.',
  'min.string' => 'El :attribute debe tener al menos :min caracteres.',
  'after' => 'La :attribute debe ser posterior a :date.',
  'digits' => 'El :attribute debe tener :digits dígitos.',
  'starts_with' => 'El :attribute debe comenzar con: :values.',
];
```
Plantillas usan reemplazos disponibles: 
- `:attribute` - Nombre del campo
- `:min`, `:max` - Valores mínimos/máximos
- `:size`, `:digits`, `:decimal` - Tamaños específicos
- `:value`, `:values` - Valores para comparación
- `:date`, `:format` - Para validaciones de fecha
- `:other` - Campo relacionado

---

## Ejemplos de uso

### Validación simple:
```php
use Micro\Generic\Validator;

$data = $_POST;
$rules = [
  'nombre' => 'required|string|min_length:3|max_length:100',
  'email'  => 'required|email|unique:users,email',
  'edad'   => 'required|integer|min_value:18|max_value:120'
];

$validator = Validator::make($data, $rules, [], $databaseAdapter); // $databaseAdapter opcional
if ($validator->fails()) {
  $errors = $validator->errors();
  // manejar errores
} else {
  // datos válidos
}
```

### Campos opcionales con validación:
```php
$data = [
    'nombre' => 'Juan Pérez',
    'email' => '',  // Vacío, no generará error
    'telefono' => '5512345678',
];

$rules = [
    'nombre' => 'required|string|min_length:3',
    'email' => 'optional|email',  // Solo valida si no está vacío
    'telefono' => 'nullable|regex:/^[0-9]{10}$/',  // Debe tener 10 dígitos si se ingresa
];

$validator = Validator::make($data, $rules);
// Validación exitosa - email vacío no genera error, telefono cumple el formato
```

### Validación de longitud vs valor:
```php
$data = [
    'codigo_producto' => '12345',  // String con 5 caracteres
    'cantidad' => 100,  // Número con valor 100
    'descripcion' => 'Producto de ejemplo',
];

$rules = [
    'codigo_producto' => 'required|string|min_length:5|max_length:20',  // Valida longitud
    'cantidad' => 'required|numeric|min_value:1|max_value:1000',  // Valida valor
    'descripcion' => 'optional|string|max_length:200',  // Opcional con máximo de caracteres
];

$validator = Validator::make($data, $rules);
```

### Validaciones de fechas:
```php
$data = [
    'fecha_nacimiento' => '1990-05-15',
    'fecha_inicio' => '2024-01-01',
    'fecha_fin' => '2024-12-31',
    'fecha_reserva' => '2024-06-15',
];

$rules = [
    'fecha_nacimiento' => 'required|date|before:today',  // Debe ser anterior a hoy
    'fecha_inicio' => 'required|date|after_or_equal:2024-01-01',
    'fecha_fin' => 'required|date|after:fecha_inicio',  // Posterior a fecha_inicio
    'fecha_reserva' => 'required|date_format:Y-m-d',  // Formato específico
];

$validator = Validator::make($data, $rules);
```

### Validaciones numéricas avanzadas:
```php
$data = [
    'pin' => '1234',
    'telefono' => '5512345678',
    'precio' => '19.99',
    'cantidad_paquetes' => '15',
];

$rules = [
    'pin' => 'required|digits:4',  // Exactamente 4 dígitos
    'telefono' => 'required|digits_between:10,11',  // Entre 10 y 11 dígitos
    'precio' => 'required|decimal:2',  // Decimal con 2 decimales
    'cantidad_paquetes' => 'required|numeric|multiple_of:5',  // Múltiplo de 5
];

$validator = Validator::make($data, $rules);
```

### Validaciones de strings:
```php
$data = [
    'url' => 'https://ejemplo.com',
    'imagen' => 'foto.jpg',
    'email' => 'usuario@correo.com',
    'codigo' => 'ABC123',
];

$rules = [
    'url' => 'required|starts_with:http://,https://',
    'imagen' => 'required|ends_with:.jpg,.png,.gif',
    'email' => 'required|contains:@',
    'codigo' => 'required|uppercase',  // Solo mayúsculas
];

$validator = Validator::make($data, $rules);
```

### Validaciones relacionales:
```php
$data = [
    'password' => 'secreto123',
    'password_confirmation' => 'secreto123',
    'edad_minima' => '18',
    'edad_maxima' => '65',
    'monto_minimo' => '100',
    'monto_maximo' => '500',
];

$rules = [
    'password' => 'required|min_length:8',
    'password_confirmation' => 'required|same:password',
    'edad_minima' => 'required|numeric',
    'edad_maxima' => 'required|numeric|gt:edad_minima',  // Mayor que edad_minima
    'monto_minimo' => 'required|numeric',
    'monto_maximo' => 'required|numeric|gte:monto_minimo',  // Mayor o igual
];

$validator = Validator::make($data, $rules);
```

### Validaciones condicionales avanzadas:
```php
$data = [
    'tipo_persona' => 'empresa',
    'razon_social' => 'Mi Empresa S.A.',
    'rfc' => '',
    'telefono_casa' => '',
    'telefono_celular' => '5512345678',
];

$rules = [
    'tipo_persona' => 'required|in:fisica,empresa',
    'razon_social' => 'required_if:tipo_persona,empresa',  // Solo si es empresa
    'rfc' => 'required_unless:tipo_persona,extranjero',  // Excepto si es extranjero
    'telefono_casa' => 'required_without:telefono_celular',  // Si no hay celular
    'telefono_celular' => 'nullable|digits:10',
];

$validator = Validator::make($data, $rules);
```

### Validaciones condicionales con `validate_if`:
```php
// Caso: Solo validar fecha si un checkbox está marcado
$data = [
    'requiere_actualizacion' => 'true',  // Checkbox marcado
    'fecha_actualizacion' => '2026-01-15',
];

$rules = [
    'requiere_actualizacion' => 'required|in:true,false',
    // Solo valida fecha cuando requiere_actualizacion es 'true'
    'fecha_actualizacion' => 'validate_if:requiere_actualizacion,true|required|date|before_or_equal:today',
];

$validator = Validator::make($data, $rules);

// Comparación de comportamiento:
// Con validate_if:
// - requiere_actualizacion=false, fecha_actualizacion='' → ✅ PASA (ignora TODO)
// - requiere_actualizacion=false, fecha_actualizacion='2027-01-01' → ✅ PASA (ignora TODO)
// - requiere_actualizacion=true, fecha_actualizacion='' → ❌ FALLA (required)
// - requiere_actualizacion=true, fecha_actualizacion='2027-01-01' → ❌ FALLA (fecha futura)

// Con required_if + optional:
$rules2 = [
    'fecha_actualizacion' => 'required_if:requiere_actualizacion,true|optional|date|before_or_equal:today',
];
// - requiere_actualizacion=false, fecha_actualizacion='' → ✅ PASA
// - requiere_actualizacion=false, fecha_actualizacion='2027-01-01' → ❌ FALLA (valida date y before_or_equal)
// - requiere_actualizacion=true, fecha_actualizacion='' → ❌ FALLA (required_if)
```

### Obtener primer error de un campo:
```php
$first = $validator->first('email');
```

### Validación donde `unique` ignora un id:
```php
$rules['email'] = 'required|email|unique:users,email,'.$userId.',id';
```

---

## Consideraciones sobre integración con BD
- Para `unique` y `exists` la clase necesita una instancia de `DatabaseAdapter`.
- Si hay error al consultar la BD, por defecto la validación de `unique`/`exists` devuelve `false` (se puede ajustar según preferencia).
- `DatabaseAdapter` envía queries simples (`SELECT COUNT(*) ...`) usando `selectEspecial`.

---

## Comportamiento de `addError`
- Forma el mensaje usando plantillas y parámetros.
- Agrega el mensaje al array `$this->errors[$field][]`.

---

## Recomendaciones
- Usar el nombre de campo legible en los mensajes personalizados (o transformar `:attribute` antes de mostrar).
- Sanitizar/escapar datos antes de insertar en BD; este validador sólo valida formatos y existencia/únicidad.
- Manejar las excepciones lanzadas por reglas `unique`/`exists` (se lanzan si no hay `DatabaseAdapter`).

---

## Ejemplo con uso en un controlador
```php
$data = [
  'nombre' => trim($_POST['nombre'] ?? ''),
  'email' => trim($_POST['email'] ?? ''),
  'telefono' => trim($_POST['telefono'] ?? ''),
  'fecha_inicio' => $_POST['fecha_inicio'] ?? null,
  'fecha_fin' => $_POST['fecha_fin'] ?? null,
];

$rules = [
  'nombre' => 'required|string|min_length:3|max_length:100',
  'email' => 'optional|email',  // Email opcional pero válido si se ingresa
  'telefono' => 'nullable|regex:/^[0-9]{10}$/',  // Teléfono opcional con formato
  'fecha_inicio' => 'required|date',
  'fecha_fin' => 'required|date'
];

$validator = Validator::make($data, $rules, [], new \App\DatabaseAdapter());
if ($validator->fails()) {
  header('Content-Type: application/json', true, 400);
  echo json_encode(['status' => 0, 'errors' => $validator->errors()]);
  exit;
}
// continuar procesamiento...
```

---

## Mensajes personalizados para nuevas reglas

Puedes personalizar los mensajes de error incluyendo las nuevas reglas:

```php
$customMessages = [
    'email.email' => 'Por favor ingresa un correo electrónico válido.',
    'edad.min_value' => 'Debes ser mayor de edad para registrarte.',
    'edad.max_value' => 'La edad máxima permitida es :max años.',
    'codigo.min_length' => 'El código debe tener al menos :min caracteres.',
    'codigo.max_length' => 'El código no puede exceder :max caracteres.',
    // Fechas
    'fecha_inicio.after' => 'La fecha de inicio debe ser posterior a :date.',
    'fecha_fin.before_or_equal' => 'La fecha de fin no puede ser posterior a hoy.',
    // Numéricos
    'pin.digits' => 'El PIN debe tener exactamente :digits dígitos.',
    'precio.decimal' => 'El precio debe tener :decimal decimales.',
    // Strings
    'url.starts_with' => 'La URL debe comenzar con http:// o https://',
    'codigo.uppercase' => 'El código debe estar en mayúsculas.',
    // Relacionales
    'password_confirmation.same' => 'Las contraseñas no coinciden.',
    'edad_maxima.gt' => 'La edad máxima debe ser mayor que la mínima.',
    // Condicionales
    'razon_social.required_if' => 'La razón social es obligatoria para empresas.',
];

$validator = Validator::make($data, $rules, $customMessages);
```

---

## Limitaciones y posibles mejoras
- No soporta validaciones anidadas complejas (arrays multidimensionales) como Laravel.
- No hay localización automática; mensajes deben proveerse según idioma.
- Se podría extender para soportar closures/Rules personalizados.

---

## Ver también
- [Ejemplos de uso del Validator](./validator-ejemplos.md) - Documentación detallada con múltiples ejemplos de uso de campos opcionales y validación de longitud vs valor.
