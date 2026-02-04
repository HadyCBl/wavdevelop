# Clase Utf8

## Descripción

La clase `Utf8` proporciona métodos estáticos para la conversión de encodings entre UTF-8 e ISO-8859-1. Esta clase fue creada como alternativa a las funciones `utf8_decode()` y `utf8_encode()` que fueron deprecadas en PHP 8.2.

## Namespace

```php
Micro\Generic\Utf8
```

## Ubicación

```
app/Generic/Utf8.php
```

## Métodos

### decode()

Decodifica un string de UTF-8 a ISO-8859-1. Este método reemplaza la función deprecada `utf8_decode()`.

#### Sintaxis

```php
public static function decode(string $string): string
```

#### Parámetros

- **$string** (string): El string en UTF-8 a decodificar

#### Retorna

- **string**: El string convertido a ISO-8859-1

#### Ejemplo de uso

```php
use Micro\Generic\Utf8;

$stringUtf8 = "Héllo Wörld";
$stringIso = Utf8::decode($stringUtf8);

echo $stringIso; // Output en ISO-8859-1
```

#### Comportamiento

- Si el string está vacío, retorna el mismo string sin procesarlo
- Utiliza `mb_convert_encoding()` internamente para la conversión

---

### encode()

Codifica un string de ISO-8859-1 a UTF-8. Este método reemplaza la función deprecada `utf8_encode()`.

#### Sintaxis

```php
public static function encode(string $string): string
```

#### Parámetros

- **$string** (string): El string en ISO-8859-1 a codificar

#### Retorna

- **string**: El string convertido a UTF-8

#### Ejemplo de uso

```php
use Micro\Generic\Utf8;

$stringIso = "Héllo Wörld"; // String en ISO-8859-1
$stringUtf8 = Utf8::encode($stringIso);

echo $stringUtf8; // Output en UTF-8
```

#### Comportamiento

- Si el string está vacío, retorna el mismo string sin procesarlo
- Utiliza `mb_convert_encoding()` internamente para la conversión

---

## Ejemplos de uso completos

### Ejemplo 1: Convertir datos de base de datos

```php
use Micro\Generic\Utf8;

// Datos que vienen de una BD antigua en ISO-8859-1
$nombre = "José María";
$descripcion = "Descripción con ñ y tildes";

// Convertir a UTF-8 para mostrar en web moderna
$nombreUtf8 = Utf8::encode($nombre);
$descripcionUtf8 = Utf8::encode($descripcion);

echo $nombreUtf8; // José María (en UTF-8)
```

### Ejemplo 2: Preparar datos para sistema legacy

```php
use Micro\Generic\Utf8;

// Datos modernos en UTF-8
$datosFormulario = $_POST['nombre']; // "Año 2025"

// Convertir a ISO-8859-1 para sistema antiguo
$datosLegacy = Utf8::decode($datosFormulario);

// Enviar a sistema legacy que espera ISO-8859-1
enviarASistemaLegacy($datosLegacy);
```

### Ejemplo 3: Conversión de arrays

```php
use Micro\Generic\Utf8;

$datos = [
    'nombre' => 'José',
    'apellido' => 'González',
    'ciudad' => 'Bogotá'
];

// Convertir todos los valores a UTF-8
$datosUtf8 = array_map([Utf8::class, 'encode'], $datos);

print_r($datosUtf8);
```

### Ejemplo 4: Conversión bidireccional

```php
use Micro\Generic\Utf8;

$original = "Texto con ñ y acentuación";

// UTF-8 → ISO-8859-1
$iso = Utf8::decode($original);

// ISO-8859-1 → UTF-8
$utf8 = Utf8::encode($iso);

echo $utf8; // "Texto con ñ y acentuación"
```

---

## Notas importantes

### Compatibilidad con PHP 8.2+

Esta clase es especialmente útil en proyectos que han migrado a PHP 8.2 o superior, donde las funciones `utf8_encode()` y `utf8_decode()` ya no están disponibles.

### Requisitos

- PHP con extensión `mbstring` habilitada
- La extensión `mbstring` debe estar activa en el `php.ini`

### Verificar extensión mbstring

```php
if (!extension_loaded('mbstring')) {
    die('La extensión mbstring no está disponible');
}
```

### Encodings soportados

La clase trabaja específicamente con:
- **UTF-8**: Encoding moderno estándar para web
- **ISO-8859-1** (Latin-1): Encoding legacy común en sistemas antiguos

### Casos de uso comunes

1. **Migración de sistemas legacy** que usan ISO-8859-1 a aplicaciones modernas UTF-8
2. **Integración con APIs antiguas** que requieren ISO-8859-1
3. **Compatibilidad con bases de datos** configuradas con diferentes encodings
4. **Procesamiento de archivos** con diferentes codificaciones
5. **Generación de PDFs** o reportes que requieren encodings específicos

---

## Diferencias con funciones deprecadas

| Función deprecada | Método equivalente | Conversión |
|------------------|-------------------|------------|
| `utf8_decode()` | `Utf8::decode()` | UTF-8 → ISO-8859-1 |
| `utf8_encode()` | `Utf8::encode()` | ISO-8859-1 → UTF-8 |

---

## Ventajas

1. ✅ **Compatible con PHP 8.2+**
2. ✅ **Métodos estáticos** - No requiere instanciación
3. ✅ **Validación de strings vacíos**
4. ✅ **Documentación completa con PHPDoc**
5. ✅ **Namespace organizado**
6. ✅ **Fácil de usar y mantener**

---

## Ver también

- [Validator](validator.md) - Validación de datos
- [Documentación oficial de mbstring](https://www.php.net/manual/es/book.mbstring.php)
