# Documentación de la Clase `SecureID`

## Descripción General

La clase `SecureID` proporciona una forma sencilla de **encriptar y desencriptar** datos, especialmente identificadores (IDs), utilizando el algoritmo simétrico **AES-256-CBC**. Su objetivo principal es **ofuscar** los IDs u otros datos sensibles cuando se transmiten entre el frontend (navegador) y el backend (servidor), por ejemplo, en URLs, formularios HTML (campos ocultos) o respuestas AJAX. Esto ayuda a evitar que los IDs numéricos secuenciales sean fácilmente legibles o manipulables por un usuario final que inspeccione el código fuente o las peticiones de red.

**Importante:** Esta clase está diseñada para la **ofuscación durante el transporte**, no para el almacenamiento seguro de datos altamente sensibles a largo plazo. Utiliza una clave simétrica, lo que significa que la misma clave se usa para encriptar y desencriptar.

---

## Dependencias

-   Extensión `openssl` de PHP habilitada.

---

## Inicialización

Para usar la clase, primero debes crear una instancia proporcionando una **clave secreta** al constructor. **Esta clave debe ser la misma tanto al encriptar como al desencriptar**. Es crucial manejar esta clave de forma segura.

```php
<?php
// En un archivo de configuración central o al inicio del script
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/config.php'; // Asumiendo que $key1 está definida aquí

// ¡IMPORTANTE! La clave ($key1 en este caso) debe ser consistente
// en toda la aplicación donde se use SecureID.
// Idealmente, obtenerla de variables de entorno o un archivo seguro.
$secureID = new SecureID($key1);

// Ahora el objeto $secureID está listo para encriptar/desencriptar
?>
```

---

## Propiedades (Privadas)

-   `$encryptionKey`: Almacena el hash SHA-256 de la clave secreta proporcionada. No almacena la clave original.
-   `$cipherMethod`: Define el método de cifrado a utilizar (`AES-256-CBC`).

---

## Métodos Públicos

### `__construct($key)`

#### Descripción
Constructor de la clase. Prepara la clave de encriptación y define el método de cifrado.

#### Parámetros
| Parámetro | Tipo   | Descripción                                                                                                |
|-----------|--------|------------------------------------------------------------------------------------------------------------|
| `$key`    | String | La clave secreta que se utilizará para la encriptación/desencriptación. Se hashea internamente con SHA-256. |

#### Retorno
`void`

#### Notas
-   El uso de `hash('sha256', $key)` asegura que la clave usada para la encriptación tenga una longitud fija y adecuada para AES-256, y evita almacenar la clave original directamente en el objeto.
-   El método de cifrado `AES-256-CBC` es una elección común y segura cuando se usa correctamente con un IV único.

---

### `encrypt($id)`

#### Descripción
Encripta el dato proporcionado (`$id`) utilizando AES-256-CBC. Genera un vector de inicialización (IV) único para cada operación, lo combina con el texto cifrado y lo codifica en Base64 para un transporte seguro.

#### Parámetros
| Parámetro | Tipo  | Descripción                                    |
|-----------|-------|------------------------------------------------|
| `$id`     | Mixed | El dato (generalmente un ID) a encriptar. |

#### Retorno
-   `String`: Una cadena codificada en Base64 que contiene el dato encriptado y el IV, separados por `::`.

#### Funcionamiento
1.  Genera un IV aleatorio seguro (`openssl_random_pseudo_bytes`) adecuado para AES-256-CBC.
2.  Encripta el `$id` usando `openssl_encrypt` con la clave hasheada, el método CBC y el IV generado.
3.  Concatena el resultado encriptado y el IV con `::` como separador.
4.  Codifica toda la cadena resultante en Base64.

#### Ejemplo de Uso (Frontend - como en `aho_02.php`)
Se usa para pasar un ID de forma ofuscada, por ejemplo, en un parámetro para una llamada AJAX o un campo de formulario.

```php
<?php
// En aho_02.php (o similar, donde se genera HTML/JS)
$idCliente = $datosCliente['idcod_cliente'] ?? ''; // ID original, ej: 45
$encryptedClientId = $secureID->encrypt($idCliente);
// $encryptedClientId contendrá algo como: "AbCdEfG...HijKlM==::XyZ123..."
?>

<!-- Ejemplo en un formulario -->
<input type="hidden" name="clienteIdOfuscado" value="<?= htmlspecialchars($encryptedClientId) ?>">

<!-- Ejemplo en una llamada AJAX (usando la función obtiene) -->
<script>
  obtiene(
    ['campo1', 'campo2'],
    [], [],
    'cahomcta', // condi
    '0',        // id (no usado aquí para el ID principal)
    ['<?= htmlspecialchars($encryptedClientId) ?>', '<?= $codproducto ?>'] // archivo (pasando el ID encriptado)
  );
</script>
```

---

### `decrypt($encryptedData)`

#### Descripción
Desencripta una cadena de datos que fue previamente encriptada por el método `encrypt` utilizando la misma clave secreta.

#### Parámetros
| Parámetro       | Tipo   | Descripción                                                                 |
|-----------------|--------|-----------------------------------------------------------------------------|
| `$encryptedData`| String | La cadena codificada en Base64 generada por el método `encrypt`.            |

#### Retorno
-   `Mixed`: El dato original desencriptado.
-   `false`: Si la desencriptación falla (por ejemplo, datos corruptos, clave incorrecta).

#### Funcionamiento
1.  Decodifica la cadena de entrada Base64 (`base64_decode`).
2.  Divide la cadena decodificada en dos partes usando `::` como separador para obtener el dato encriptado y el IV (`explode`).
3.  Desencripta el dato usando `openssl_decrypt` con la clave hasheada, el método CBC y el IV extraído.

#### Ejemplo de Uso (Backend - como en `crud_ahorro.php`)
Se usa para recuperar el ID original a partir del dato ofuscado recibido del frontend.

```php
<?php
// En crud_ahorro.php (o el script que procesa la petición)

// Asumiendo que $encryptedID viene de $_POST['archivo'][0] o similar
$encryptedID = $_POST["archivo"][0]; // ej: "AbCdEfG...HijKlM==::XyZ123..."

// Desencriptar usando la misma instancia de SecureID (con la misma clave)
$decryptedID = $secureID->decrypt($encryptedID);
// $decryptedID debería contener el ID original, ej: 45

if ($decryptedID !== false) {
    // Usar el ID desencriptado de forma segura
    $codigoCliente = $decryptedID;
    // ... lógica para crear la cuenta usando $codigoCliente ...
} else {
    // Error: El dato encriptado es inválido o la clave es incorrecta
    echo json_encode(["Error al desencriptar el ID", 0]);
    exit;
}

// Ejemplo en otro case de crud_ahorro.php
case 'dahomtip':
    $encryptedID = $_POST["ideliminar"];
    $decryptedID = $secureID->decrypt($encryptedID);
    if ($decryptedID === false) {
        // Manejar error
        echo json_encode(["ID inválido", 0]);
        exit;
    }
    // Usar $decryptedID para la eliminación
    $database->delete('ahomtip', 'id = ?', [$decryptedID]);
    // ...
    break;
?>
```

---

## Consideraciones de Seguridad

-   **Clave Secreta (`$key`)**:
    *   La seguridad de todo el sistema depende de mantener esta clave secreta.
    *   **NUNCA** la almacenes directamente en el código fuente ni la subas a sistemas de control de versiones (como Git).
    *   Utiliza métodos seguros para gestionarla, como **variables de entorno** (cargadas con `phpdotenv` como se hace para la base de datos) o archivos de configuración fuera del directorio web raíz.
    *   Asegúrate de que la **misma clave** se utilice consistentemente en todos los lugares donde se instancie `SecureID`.
-   **Propósito (Ofuscación)**: Recuerda que esto es principalmente para ofuscación, no para protección criptográfica robusta de datos almacenados. No previene ataques si un atacante obtiene la clave secreta.
-   **Vector de Inicialización (IV)**: El uso de un IV aleatorio diferente para cada encriptación es crucial para la seguridad del modo CBC. La clase maneja esto correctamente al generarlo y almacenarlo junto con el texto cifrado.
-   **Base64**: Es solo una codificación para transporte, no añade seguridad por sí misma.

---

**Última actualización:** 14 de abril de 2025