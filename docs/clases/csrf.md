# Documentación de la Clase `CSRFProtection`

## Descripción General

La clase `CSRFProtection` implementa una medida de seguridad para prevenir ataques de **Cross-Site Request Forgery (CSRF)**. CSRF es un tipo de ataque en el que un sitio web malicioso, correo electrónico, blog, mensaje instantáneo o programa hace que el navegador web de un usuario realice una acción no deseada en un sitio de confianza en el que el usuario está actualmente autenticado.

Esta clase utiliza el **Patrón de Token Sincronizador (Synchronizer Token Pattern)**. Genera un token único y secreto asociado a la sesión del usuario. Este token debe incluirse en todas las solicitudes que cambian el estado (como formularios POST) y se valida en el lado del servidor antes de procesar la solicitud. Si el token enviado no coincide con el esperado en la sesión, la solicitud se rechaza, ya que probablemente no fue iniciada intencionalmente por el usuario.

---

## Dependencias

-   **Sesiones de PHP**: La clase requiere que las sesiones de PHP estén activas (`session_start()`). El constructor se asegura de iniciar la sesión si aún no está activa.

---

## Inicialización

Se debe crear una instancia de la clase al principio de cada script que necesite generar o validar un token CSRF. El constructor se encarga de gestionar el token en la sesión.

```php
<?php
// En un archivo de configuración central o al inicio del script
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';

// Iniciar sesión si no se ha hecho antes (aunque el constructor lo intenta)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Crear instancia de la clase
$csrf = new CSRFProtection();

// Ahora el objeto $csrf está listo para ser usado
?>
```

---

## Propiedades (Privadas)

-   `$token`: Almacena el valor del token CSRF actual para la sesión.
-   `$tokenName`: Define el nombre que se usará para almacenar el token en la variable `$_SESSION` y como nombre/id del campo en los formularios HTML. Por defecto es `'csrf_token'`.

---

## Métodos Públicos

### `__construct()`

#### Descripción
Constructor de la clase. Se asegura de que la sesión esté iniciada y gestiona la existencia del token CSRF en la sesión. Si no existe un token en `$_SESSION[$this->tokenName]`, genera uno nuevo llamando a `regenerateToken()`. Si ya existe, carga el token existente en la propiedad `$token`.

#### Parámetros
Ninguno.

#### Retorno
`void`

---

### `getToken()`

#### Descripción
Devuelve el valor del token CSRF actual almacenado en la sesión.

#### Parámetros
Ninguno.

#### Retorno
-   `String`: El valor del token CSRF actual.

#### Ejemplo de Uso (Obtener valor para AJAX)
```php
<?php
// En la vista (aho_02.php) para pasar el token en una llamada AJAX
$currentToken = $csrf->getToken();
?>
<script>
  // Ejemplo usando la función obtiene()
  obtiene(
    ['<?= $csrf->getTokenName() ?>', 'campo1', 'campo2'], // Incluir el token como primer input
    [], [],
    'cahomcta',
    '0',
    ['<?= htmlspecialchars($secureID->encrypt($datosCliente['idcod_cliente'] ?? '')) ?>', '<?= $codproducto ?>'],
    null, // Callback
    false // No confirmación
  );

  // Ejemplo con $.ajax directo
  $.ajax({
    url: 'ruta/al/backend.php',
    method: 'POST',
    data: {
      '<?= $csrf->getTokenName() ?>': '<?= $currentToken ?>',
      // otros datos...
    },
    // ...
  });
</script>
```

---

### `getTokenName()`

#### Descripción
Devuelve el nombre utilizado para el token (por defecto `'csrf_token'`). Útil para referenciar el token en la sesión o en los datos enviados desde el frontend.

#### Parámetros
Ninguno.

#### Retorno
-   `String`: El nombre del token.

#### Ejemplo de Uso
```php
<?php
// En el backend (crud_ahorro.php) para obtener el token enviado
$tokenName = $csrf->getTokenName();
$submittedToken = $_POST[$tokenName] ?? '';

// En la vista (aho_02.php) para usarlo en la función obtiene()
?>
obtiene(['<?= $csrf->getTokenName() ?>', /* otros inputs */], /* ... */);
```

---

### `regenerateToken()`

#### Descripción
Genera un nuevo token CSRF seguro y aleatorio (`bin2hex(random_bytes(32))`) y lo almacena tanto en la propiedad `$token` del objeto como en la sesión (`$_SESSION[$this->tokenName]`), sobrescribiendo cualquier valor anterior.

#### Parámetros
Ninguno.

#### Retorno
`void`

---

### `validateToken($token, $estrict = true)`

#### Descripción
Valida si el token proporcionado (`$token`) coincide con el token almacenado en la sesión actual. Utiliza `hash_equals()` para una comparación segura contra ataques de temporización.

#### Parámetros
| Parámetro | Tipo    | Descripción                                                                                                                                                                                                                            | Obligatorio | Valor por Defecto |
|-----------|---------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|-------------------|
| `$token`  | String  | El token enviado por el cliente (generalmente desde `$_POST` o `$_GET`).                                                                                                                                                               | Sí          | -                 |
| `$estrict`| Boolean | Si es `true` (por defecto), el token en la sesión se **regenerará automáticamente** después de una validación exitosa. Esto asegura que cada token se use una sola vez (más seguro). Si es `false`, el token en la sesión no se cambia. | No          | `true`            |

#### Retorno
-   `Boolean`: `true` si el token es válido, `false` en caso contrario (token incorrecto o no existe token en sesión).

#### Ejemplo de Uso (Backend - como en `crud_ahorro.php`)
```php
<?php
// En crud_ahorro.php, al inicio del case correspondiente

// Obtener el token enviado (asumiendo que es el primer elemento del array 'inputs')
$submittedToken = $_POST["inputs"][0] ?? '';

// Validar el token. Usar $estrict = false si el mismo token puede
// ser usado para múltiples peticiones AJAX en la misma página sin recargar.
// Usar $estrict = true (o por defecto) para máxima seguridad en formularios.
if (!($csrf->validateToken($submittedToken, false))) { // Ejemplo con $estrict = false
    // El token es inválido o ha expirado
    echo json_encode(["Token CSRF inválido o expirado", 0]);
    exit; // Detener la ejecución
}

// Si la validación pasa, continuar con el procesamiento normal...
// ...
?>
```

---

### `getTokenField()`

#### Descripción
Genera una cadena HTML que representa un campo de formulario oculto (`<input type="hidden">`) que contiene el nombre y el valor del token CSRF actual. Este campo debe incluirse dentro de cualquier formulario HTML cuyas solicitudes deban ser protegidas.

#### Parámetros
Ninguno.

#### Retorno
-   `String`: La etiqueta HTML `<input type="hidden">` completa.

#### Ejemplo de Uso (Frontend - como en `aho_02.php` o vistas con formularios)
```php
<div>
    <?php echo $csrf->getTokenField(); ?>
    <!-- Otros campos del formulario -->
    <label for="nombre">Nombre:</label>
    <input type="text" id="nombre" name="nombre">
    <button onclick="obtiene(['<?= $csrf->getTokenName() ?>', /* otros inputs */], /* ... */);">Guardar</button>
</div>
```

---

## Flujo de Uso Típico

1.  **En la Vista (Generación de Formulario/Página):**
    *   Crear una instancia de `CSRFProtection`.
    *   Si es un formulario HTML, incluir el campo oculto usando `$csrf->getTokenField()`.
    *   Si se harán peticiones AJAX, obtener el token con `$csrf->getToken()` y el nombre con `$csrf->getTokenName()` e incluirlos en los datos de la petición AJAX.

2.  **En el Backend (Procesamiento de la Solicitud):**
    *   Crear una instancia de `CSRFProtection`.
    *   Obtener el token enviado por el cliente (ej. `$_POST[$csrf->getTokenName()]`).
    *   Validar el token usando `$csrf->validateToken($submittedToken)`.
    *   Si la validación falla, rechazar la solicitud (mostrar error, redirigir, etc.).
    *   Si la validación es exitosa, proceder con el procesamiento normal de la solicitud.

---

## Consideraciones de Seguridad

-   **Inicio de Sesión**: Es crucial que `session_start()` se llame *antes* de cualquier salida HTML y antes de instanciar `CSRFProtection`.
-   **Validación Temprana**: La validación del token (`validateToken()`) debe realizarse lo antes posible en el script del backend, antes de realizar cualquier acción que modifique datos o estado.
-   **Modo Estricto (`$estrict = true`)**: Es la opción más segura, ya que invalida el token después de su primer uso. Sin embargo, puede causar problemas en interfaces con múltiples peticiones AJAX que dependen del mismo token inicial sin recargar la página. En esos casos, se puede usar `$estrict = false`, pero se debe ser consciente de que el mismo token será válido hasta que la sesión expire o se regenere manualmente.
-   **HTTPS**: Siempre usar HTTPS para proteger la transmisión del token (y todos los datos) entre el cliente y el servidor.

---

**Última actualización:** 14 de abril de 2025