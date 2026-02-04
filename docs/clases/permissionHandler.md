# Documentación de la Clase `PermissionHandler`

## Descripción General

La clase `PermissionHandler` es una utilidad diseñada para determinar el nivel de acceso de un usuario (bajo, medio o alto) dentro de un contexto específico (por ejemplo, al generar un reporte). Funciona recibiendo una lista de los IDs de permisos que posee el usuario actual y los IDs específicos que definen los niveles de acceso "medio" y "alto" para ese contexto. Esto permite adaptar dinámicamente las consultas de base de datos o la interfaz de usuario según el nivel de autorización del usuario.

---

## Dependencias

Ninguna dependencia externa directa, pero asume que los permisos del usuario se obtienen previamente (generalmente de una base de datos).

---

## Inicialización

Para usar la clase, se debe crear una instancia pasando un array con los permisos del usuario (filtrados para el contexto actual) y, opcionalmente, los IDs que representan los niveles medio y alto.

```php
<?php
// En un script como reporte001.php, después de obtener los permisos del usuario

require_once __DIR__ . '/../../../includes/Config/PermissionHandler.php';
require_once __DIR__ . '/../../../includes/Config/database.php'; // Para obtener $database

// ... (conexión a BD, obtener $idusuario) ...

$database = new Database(/* ... */);

// IDs de permiso relevantes para este reporte/contexto
$mediumPermissionId = 16; // Ej: Permiso de ver cartera a nivel de agencia
$highPermissionId = 17;   // Ej: Permiso de ver cartera nivel general

try {
    $database->openConnection();

    // Obtener solo los permisos relevantes que tiene el usuario
    $permisosUsuario = $database->selectColumns(
        "tb_autorizacion",
        ["id", "id_restringido"], // Importante: 'id_restringido' es el ID del permiso
        "id_restringido IN (?,?) AND id_usuario=? AND estado=1",
        [$mediumPermissionId, $highPermissionId, $idusuario]
    );

    // Crear instancia del manejador de permisos
    $accessHandler = new PermissionHandler($permisosUsuario, $mediumPermissionId, $highPermissionId);

    // Ahora $accessHandler está listo para ser usado
    // Ejemplo: if ($accessHandler->isHigh()) { ... }

} catch (Exception $e) {
    // ... manejo de error ...
} finally {
    $database->closeConnection();
}
?>
```

---

## Propiedades (Privadas)

-   `$permissions`: Un array que contiene únicamente los IDs (`id_restringido`) de los permisos relevantes que posee el usuario.
-   `$mediumPermissionId`: El ID del permiso que define el nivel de acceso "medio". Puede ser `null`.
-   `$highPermissionId`: El ID del permiso que define el nivel de acceso "alto". Puede ser `null`.

---

## Métodos Públicos

### `__construct(array $permissions, ?int $mediumPermissionId = null, ?int $highPermissionId = null)`

#### Descripción
Constructor de la clase. Inicializa el manejador con los permisos del usuario y los IDs que definen los niveles medio y alto. Extrae solo los IDs de los permisos del array de entrada.

#### Parámetros
| Parámetro            | Tipo       | Descripción                                                                                                                                                           | Obligatorio | Valor por Defecto |
|----------------------|------------|-----------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|-------------------|
| `$permissions`       | Array      | Un array de arrays asociativos (generalmente resultado de una consulta a BD), donde cada subarray debe contener al menos la clave `'id_restringido'` con el ID del permiso. | Sí          | -                 |
| `$mediumPermissionId`| int\|null | El ID del permiso que otorga nivel "medio".                                                                                                                            | No          | `null`            |
| `$highPermissionId`  | int\|null | El ID del permiso que otorga nivel "alto".                                                                                                                             | No          | `null`            |

#### Retorno
`void`

#### Notas
-   Utiliza `array_column($permissions, 'id_restringido')` para simplificar el array de permisos a solo los IDs, facilitando las búsquedas posteriores con `in_array()`.

---

### `getAccessLevel(): string`

#### Descripción
Determina y devuelve el nivel de acceso más alto que posee el usuario basado en los permisos proporcionados.

#### Parámetros
Ninguno.

#### Retorno
-   `string`: Devuelve una de las siguientes cadenas:
    -   `'high'`: Si el usuario tiene el permiso `$highPermissionId`.
    -   `'medium'`: Si el usuario no tiene el permiso "alto" pero sí tiene el permiso `$mediumPermissionId`.
    -   `'low'`: Si el usuario no tiene ni el permiso "alto" ni el "medio", o si no se definieron IDs para medio/alto.

#### Funcionamiento
1.  Verifica si el usuario tiene nivel alto (`isHigh()`). Si es así, retorna `'high'`.
2.  Si no, verifica si tiene nivel medio (`isMedium()`). Si es así, retorna `'medium'`.
3.  Si no tiene ninguno de los anteriores, retorna `'low'`.

#### Ejemplo de Uso
```php
$nivel = $accessHandler->getAccessLevel(); // $nivel será 'low', 'medium', o 'high'

switch ($nivel) {
    case 'high':
        // Lógica para usuarios con acceso total
        break;
    case 'medium':
        // Lógica para usuarios con acceso intermedio
        break;
    default: // 'low'
        // Lógica para usuarios con acceso básico o restringido
        break;
}
```

---

### `isLow(): bool`

#### Descripción
Verifica si el nivel de acceso del usuario es "bajo". Un usuario tiene nivel bajo si no posee ni el permiso de nivel medio ni el de nivel alto definidos para este contexto.

#### Parámetros
Ninguno.

#### Retorno
-   `bool`: `true` si el nivel de acceso es bajo, `false` en caso contrario.

#### Lógica de Determinación
Retorna `true` si se cumple alguna de estas condiciones:
-   El array `$permissions` está vacío (el usuario no tiene ninguno de los permisos relevantes).
-   No se definieron IDs para los niveles medio y alto (`$mediumPermissionId` y `$highPermissionId` son `null`).
-   El usuario **no** tiene el permiso `$mediumPermissionId` **Y** **no** tiene el permiso `$highPermissionId` en su lista de `$permissions`.

#### Ejemplo de Uso (como en `reporte001.php`)
```php
<!-- Ocultar un elemento si el usuario es de nivel bajo -->
<div class="form-check" <?= ($accessHandler->isLow()) ? 'hidden' : ''; ?>>
    <input class="form-check-input" type="radio" name="rasesor" id="allasesor" ...>
    <label ...>Todos los disponibles</label>
</div>

<!-- Deshabilitar un select si el usuario ES de nivel bajo -->
<select class="form-select" id="codanal" <?= ($accessHandler->isLow()) ? '' : 'disabled'; ?>>
    <!-- ... opciones ... -->
</select>
```

---

### `isMedium(): bool`

#### Descripción
Verifica si el nivel de acceso del usuario es "medio". Un usuario tiene nivel medio si se definió un `$mediumPermissionId` y este ID se encuentra en la lista de permisos del usuario. **Importante:** Este método no verifica si el usuario también tiene nivel alto; simplemente comprueba la presencia del permiso medio.

#### Parámetros
Ninguno.

#### Retorno
-   `bool`: `true` si se definió `$mediumPermissionId` y el usuario lo posee, `false` en caso contrario.

#### Ejemplo de Uso (como en `reporte001.php`)
```php
// Determinar condición y parámetros para una consulta SQL
$condicion = ($accessHandler->isHigh()) ? "estado=1 AND puesto='ANA'" : (($accessHandler->isMedium()) ? "estado=1 AND id_agencia=? AND puesto='ANA'" : "estado=1 AND id_usu=?");
$parametros = ($accessHandler->isHigh()) ? [] : (($accessHandler->isMedium()) ? [$idagencia] : [$idusuario]);

$users = $database->selectColumns('tb_usuario', ['id_usu', 'nombre', 'apellido', 'id_agencia'], $condicion, $parametros);
```

---

### `isHigh(): bool`

#### Descripción
Verifica si el nivel de acceso del usuario es "alto". Un usuario tiene nivel alto si se definió un `$highPermissionId` y este ID se encuentra en la lista de permisos del usuario.

#### Parámetros
Ninguno.

#### Retorno
-   `bool`: `true` si se definió `$highPermissionId` y el usuario lo posee, `false` en caso contrario.

#### Ejemplo de Uso (como en `reporte001.php`)
```php
// Determinar condición y parámetros para la consulta de agencias
$condicionAgencias = ($accessHandler->isHigh()) ? "" : "id_agencia=?";
$parametrosAgencias = ($accessHandler->isHigh()) ? [] : [$idagencia];
$agencias = $database->selectColumns('tb_agencia', ['id_agencia', 'nom_agencia', 'cod_agenc'], $condicionAgencias, $parametrosAgencias);

// Ocultar/mostrar elementos en la interfaz
?>
<div class="form-check" <?= ($accessHandler->isHigh()) ? "" : "hidden"; ?>>
    <input class="form-check-input" type="radio" name="ragencia" id="allofi" value="allofi" ...>
    <label ...>Consolidado</label>
</div>
<i <?= ($accessHandler->isHigh()) ? "hidden" : ""; ?> class="fa-solid fa-circle-info ms-3" ...></i>
<?php
```

---

## Lógica de Niveles

-   **Alto**: Tiene prioridad. Si un usuario tiene el permiso `$highPermissionId`, se considera de nivel alto, independientemente de si también tiene el permiso medio.
-   **Medio**: Se considera si el usuario *no* tiene el permiso alto, pero *sí* tiene el permiso `$mediumPermissionId`.
-   **Bajo**: Es el nivel por defecto si no se cumplen las condiciones para alto o medio.

Esta jerarquía se implementa principalmente en el método `getAccessLevel()`. Los métodos `isMedium()` e `isHigh()` solo verifican la presencia del permiso específico correspondiente.

---

**Última actualización:** 14 de abril de 2025