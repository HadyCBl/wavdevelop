# Documentación de la Clase `Database`

## Descripción General

La clase `Database` proporciona una interfaz orientada a objetos para interactuar con una base de datos MySQL utilizando PDO (PHP Data Objects). Simplifica las operaciones comunes como la conexión, desconexión, gestión de transacciones (BEGIN, COMMIT, ROLLBACK) y la ejecución de consultas CRUD (Crear, Leer, Actualizar, Eliminar), incluyendo consultas preparadas para mayor seguridad. Está diseñada para ser reutilizable en diferentes partes de la aplicación.

---

## Dependencias

-   Extensión PDO de PHP habilitada.
-   Librería `vlucas/phpdotenv` para cargar variables de entorno (usada para obtener las credenciales de la BD).
-   Una función `logerrores($message, $file, $line, $errorFile, $errorLine)` que registra mensajes de error en el archivo `/logs/errores.log`.

---

## Inicialización

La clase se instancia típicamente al inicio de un script o en un archivo de configuración central, pasando las credenciales de la base de datos obtenidas de variables de entorno.

```php
<?php
// filepath: includes/Config/database.php (o similar)
require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Obtener credenciales desde .env
$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];

// Crear instancia de la clase Database
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

// Ahora el objeto $database está listo para ser usado
// Ejemplo: $database->openConnection();
?>
```

---

## Propiedades (Privadas)

-   `$host`: Host de la base de datos.
-   `$db_name`: Nombre de la base de datos principal de la aplicación.
-   `$db_name_general`: Nombre de una base de datos secundaria o general.
-   `$username`: Nombre de usuario para la conexión.
-   `$password`: Contraseña para la conexión.
-   `$conn`: Almacena el objeto de conexión PDO una vez establecido.
-   `$inTransaction`: Bandera booleana que indica si una transacción está activa.

---

## Métodos Públicos

### `__construct($host, $db_name, $username, $password, $db_name_general)`

#### Descripción
Constructor de la clase. Inicializa las propiedades con los detalles de conexión a la base de datos.

#### Parámetros
| Parámetro          | Tipo   | Descripción                                  |
| ------------------ | ------ | -------------------------------------------- |
| `$host`            | String | Host de la base de datos.                    |
| `$db_name`         | String | Nombre de la base de datos principal.        |
| `$username`        | String | Nombre de usuario de la base de datos.       |
| `$password`        | String | Contraseña de la base de datos.              |
| `$db_name_general` | String | Nombre de la base de datos general/auxiliar. |

#### Retorno
`void`

#### Ejemplo de Uso
Ver sección de **Inicialización**.

---

### `openConnection($option = 1)`

#### Descripción
Establece la conexión con la base de datos utilizando PDO. Configura atributos importantes como el modo de error (excepciones), el modo de obtención por defecto (array asociativo) y la codificación de caracteres (UTF-8).

#### Parámetros
| Parámetro | Tipo    | Descripción                                                                                                          | Obligatorio | Valor por Defecto |
| --------- | ------- | -------------------------------------------------------------------------------------------------------------------- | ----------- | ----------------- |
| `$option` | Integer | Permite seleccionar qué base de datos conectar: `1` para `$db_name` (principal), otro valor para `$db_name_general`. | No          | `1`               |

#### Retorno
`void`

#### Excepciones
-   `Exception`: Si ocurre un error durante el intento de conexión.

#### Ejemplo de Uso
```php
try {
    $database->openConnection(); // Conecta a la BD principal
    // ... realizar operaciones ...
} catch (Exception $e) {
    // Manejar error de conexión
    echo "Error de conexión: " . $e->getMessage();
}
```

---

### `closeConnection()`

#### Descripción
Cierra la conexión activa a la base de datos estableciendo la propiedad `$conn` a `null`. Es buena práctica llamarla al final de un script o en un bloque `finally`.

#### Parámetros
Ninguno.

#### Retorno
`void`

#### Ejemplo de Uso
```php
try {
    $database->openConnection();
    // ... operaciones ...
} catch (Exception $e) {
    // ... manejo de error ...
} finally {
    $database->closeConnection(); // Asegura que la conexión se cierre
}
```

---

### `beginTransaction()`

#### Descripción
Inicia una transacción en la base de datos. Desactiva el modo `autocommit`. Debe usarse antes de realizar un conjunto de operaciones que deben completarse todas o ninguna.

#### Parámetros
Ninguno.

#### Retorno
`void`

#### Ejemplo de Uso
```php
try {
    $database->openConnection();
    $database->beginTransaction(); // Iniciar transacción
    // ... insertar datos ...
    // ... actualizar datos ...
    $database->commit(); // Confirmar si todo fue bien
} catch (Exception $e) {
    $database->rollback(); // Revertir si algo falló
    // ... manejo de error ...
} finally {
    $database->closeConnection();
}
```

---

### `commit()`

#### Descripción
Confirma (hace permanentes) todas las operaciones realizadas dentro de la transacción activa.

#### Parámetros
Ninguno.

#### Retorno
`void`

#### Ejemplo de Uso
Ver ejemplo en `beginTransaction()`.

---

### `rollback()`

#### Descripción
Revierte (deshace) todas las operaciones realizadas dentro de la transacción activa. Se usa típicamente en un bloque `catch` cuando ocurre un error.

#### Parámetros
Ninguno.

#### Retorno
`void`

#### Ejemplo de Uso
Ver ejemplo en `beginTransaction()`.

---

### `selectAll($table)`

#### Descripción
Selecciona todas las columnas (`*`) y todas las filas de la tabla especificada.

#### Parámetros
| Parámetro | Tipo   | Descripción         | Obligatorio |
| --------- | ------ | ------------------- | ----------- |
| `$table`  | String | Nombre de la tabla. | Sí          |

#### Retorno
-   `Array`: Un array de arrays asociativos, donde cada subarray representa una fila.

#### Ejemplo de Uso
```php
$todosLosUsuarios = $database->selectAll('tb_usuarios');
foreach ($todosLosUsuarios as $usuario) {
    echo $usuario['nombre'];
}
```

---

### `selectById($table, $id, $columnid = "id")`

#### Descripción
Selecciona una única fila de una tabla basándose en el valor de una columna de identificación (por defecto, la columna `id`).

#### Parámetros
| Parámetro   | Tipo   | Descripción                                 | Obligatorio | Valor por Defecto |
| ----------- | ------ | ------------------------------------------- | ----------- | ----------------- |
| `$table`    | String | Nombre de la tabla.                         | Sí          | -                 |
| `$id`       | Mixed  | El valor del ID a buscar.                   | Sí          | -                 |
| `$columnid` | String | El nombre de la columna que contiene el ID. | No          | `"id"`            |

#### Retorno
-   `Array`: Un array asociativo representando la fila encontrada.
-   `false`: Si no se encuentra ninguna fila con ese ID.
-   `String`: Si ocurre un error durante la ejecución (según el código, aunque idealmente debería lanzar excepción).

#### Ejemplo de Uso
```php
$producto = $database->selectById('productos', 123, 'id_producto');
if ($producto) {
    echo $producto['nombre'];
} else {
    echo "Producto no encontrado.";
}
```

---

### `selectDataID($table, $claveCol, $dataBuscar)`

#### Descripción
Selecciona todas las filas de una tabla donde una columna específica (`$claveCol`) coincide con un valor dado (`$dataBuscar`).

#### Parámetros
| Parámetro     | Tipo   | Descripción                               | Obligatorio |
| ------------- | ------ | ----------------------------------------- | ----------- |
| `$table`      | String | Nombre de la tabla.                       | Sí          |
| `$claveCol`   | String | Nombre de la columna para la condición.   | Sí          |
| `$dataBuscar` | Mixed  | Valor a buscar en la columna `$claveCol`. | Sí          |

#### Retorno
-   `Array`: Un array de arrays asociativos con las filas encontradas.
-   `String`: Un mensaje de error si la ejecución falla.

#### Ejemplo de Uso
```php
$usuariosActivos = $database->selectDataID('usuarios', 'estado', 1);
if (is_array($usuariosActivos)) {
    echo "Encontrados: " . count($usuariosActivos) . " usuarios activos.";
} else {
    echo $usuariosActivos; // Muestra mensaje de error
}
```

---

### `selectAtributos($selectFrom, $namTable, $marcadores, $variablesClaves)`

#### Descripción
Selecciona una única fila basándose en múltiples condiciones (AND). Permite especificar qué columnas seleccionar.

#### Parámetros
| Parámetro          | Tipo   | Descripción                                                                  | Obligatorio |
| ------------------ | ------ | ---------------------------------------------------------------------------- | ----------- |
| `$selectFrom`      | String | La parte `SELECT ... FROM` de la consulta (ej. `SELECT id, nombre FROM`).    | Sí          |
| `$namTable`        | String | Nombre de la tabla (sin el `FROM`).                                          | Sí          |
| `$marcadores`      | Array  | Array de strings con los nombres de las columnas para las condiciones WHERE. | Sí          |
| `$variablesClaves` | Array  | Array con los valores correspondientes a `$marcadores`, en el mismo orden.   | Sí          |

#### Retorno
-   `Array`: Un array asociativo representando la fila encontrada.
-   `String`: Un mensaje de error si la ejecución falla.

#### Ejemplo de Uso
```php
$columnas = ['nombre', 'email'];
$valores = ['usuario@ejemplo.com', 1]; // email='usuario@ejemplo.com' AND activo=1
$usuario = $database->selectAtributos('SELECT id, nombre, email', 'usuarios', $columnas, $valores);
if (is_array($usuario)) {
    echo "ID del usuario: " . $usuario['id'];
} else {
    echo $usuario; // Mensaje de error
}
```

---

### `selectNom($query, $params = [])`

#### Descripción
Ejecuta una consulta SELECT preparada y devuelve todos los resultados como un array de arrays asociativos.

#### Parámetros
| Parámetro | Tipo   | Descripción                                                          | Obligatorio | Valor por Defecto |
| --------- | ------ | -------------------------------------------------------------------- | ----------- | ----------------- |
| `$query`  | String | La consulta SQL SELECT completa, usando `?` o marcadores con nombre. | Sí          | -                 |
| `$params` | Array  | Array con los valores para los marcadores en la consulta, en orden.  | No          | `[]`              |

#### Retorno
-   `Array`: Un array de arrays asociativos con todos los resultados.

#### Excepciones
-   `Exception`: Si ocurre un error al preparar o ejecutar la consulta.

#### Ejemplo de Uso
```php
$sql = "SELECT nombre, email FROM usuarios WHERE id_agencia = ? AND estado = ?";
$params = [$_SESSION['id_agencia'], 1];
try {
    $usuariosAgencia = $database->selectNom($sql, $params);
    // ... procesar $usuariosAgencia ...
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `selectEspecial($query, $params = [], $op = 0)`

#### Descripción
Ejecuta una consulta SELECT preparada y devuelve el resultado en diferentes formatos según el parámetro `$op`.

#### Parámetros
| Parámetro | Tipo    | Descripción                                                                                                                                                                                                     | Obligatorio | Valor por Defecto |
| --------- | ------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- | ----------------- |
| `$query`  | String  | La consulta SQL SELECT completa, usando `?` o marcadores con nombre.                                                                                                                                            | Sí          | -                 |
| `$params` | Array   | Array con los valores para los marcadores en la consulta, en orden.                                                                                                                                             | No          | `[]`              |
| `$op`     | Integer | Formato del resultado: `0`=Valor de la primera columna de la primera fila (`fetchColumn`), `1`=Primera fila como array asociativo (`fetch`), `2`=Todas las filas como array de arrays asociativos (`fetchAll`). | No          | `0`               |

#### Retorno
-   `Mixed`: Depende del valor de `$op` (un valor escalar, un array asociativo, o un array de arrays asociativos).

#### Excepciones
-   `Exception`: Si ocurre un error al preparar o ejecutar la consulta.

#### Ejemplo de Uso
```php
// Obtener solo el conteo (op=0)
$count = $database->selectEspecial("SELECT COUNT(*) FROM productos WHERE categoria = ?", ['electronica'], 0);

// Obtener la primera fila (op=1)
$primerProducto = $database->selectEspecial("SELECT * FROM productos ORDER BY id LIMIT 1", [], 1);

// Obtener todas las filas (op=2)
$todosProductos = $database->selectEspecial("SELECT * FROM productos", [], 2);
```

---

### `selectColumns($table, $columns = ['*'], $condition = '', $params = [])`

#### Descripción
Selecciona columnas específicas de una tabla, opcionalmente con una condición WHERE. Devuelve todas las filas que coinciden.

#### Parámetros
| Parámetro    | Tipo   | Descripción                                                       | Obligatorio | Valor por Defecto |
| ------------ | ------ | ----------------------------------------------------------------- | ----------- | ----------------- |
| `$table`     | String | Nombre de la tabla.                                               | Sí          | -                 |
| `$columns`   | Array  | Array de strings con los nombres de las columnas a seleccionar.   | No          | `['*']`           |
| `$condition` | String | La condición SQL para la cláusula WHERE (sin la palabra `WHERE`). | No          | `''`              |
| `$params`    | Array  | Array con los valores para los marcadores `?` en la `$condition`. | No          | `[]`              |

#### Retorno
-   `Array`: Un array de arrays asociativos con las filas y columnas seleccionadas.

#### Excepciones
-   `Exception`: Si ocurre un error al preparar o ejecutar la consulta.

#### Ejemplo de Uso (de `crud_ahorro.php`)
```php
// Seleccionar solo la columna 'ccodtip' donde ccodtip coincida
$ccodtip = '01'; // Ejemplo
try {
    $ahomtip = $database->selectColumns('ahomtip', ['ccodtip'], "ccodtip=?", [$ccodtip]);
    if (!empty($ahomtip)) {
        // ... existe ...
    }
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `executeQuery($query, $params = [])`

#### Descripción
Método de bajo nivel que prepara y ejecuta cualquier consulta SQL (SELECT, INSERT, UPDATE, DELETE, etc.) utilizando sentencias preparadas.

#### Parámetros
| Parámetro | Tipo   | Descripción                                                         | Obligatorio | Valor por Defecto |
| --------- | ------ | ------------------------------------------------------------------- | ----------- | ----------------- |
| `$query`  | String | La consulta SQL completa, usando `?` o marcadores con nombre.       | Sí          | -                 |
| `$params` | Array  | Array con los valores para los marcadores en la consulta, en orden. | No          | `[]`              |

#### Retorno
-   `PDOStatement`: El objeto PDOStatement resultante de la ejecución. Se puede usar para obtener resultados (`fetch`, `fetchAll`), contar filas afectadas (`rowCount`), etc.

#### Excepciones
-   `Exception`: Si ocurre un error al preparar o ejecutar la consulta.

#### Ejemplo de Uso
```php
try {
    $stmt = $database->executeQuery('UPDATE usuarios SET ultimo_login = NOW() WHERE id = ?', [$userId]);
    $filasAfectadas = $stmt->rowCount();
    echo "Se actualizó el último login para $filasAfectadas usuario(s).";
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `getAllResults($query, $params = [])`

#### Descripción
Ejecuta una consulta (usando `executeQuery`) y obtiene todos los resultados como un array de arrays asociativos. Esencialmente un alias para `executeQuery` seguido de `fetchAll(PDO::FETCH_ASSOC)`.

#### Parámetros
| Parámetro | Tipo   | Descripción                                                         | Obligatorio | Valor por Defecto |
| --------- | ------ | ------------------------------------------------------------------- | ----------- | ----------------- |
| `$query`  | String | La consulta SQL completa, usando `?` o marcadores con nombre.       | Sí          | -                 |
| `$params` | Array  | Array con los valores para los marcadores en la consulta, en orden. | No          | `[]`              |

#### Retorno
-   `Array`: Un array de arrays asociativos con todos los resultados.

#### Excepciones
-   `Exception`: Si ocurre un error al ejecutar la consulta u obtener los resultados.

#### Ejemplo de Uso
```php
try {
    $reporte = $database->getAllResults("CALL sp_GenerarReporte(?, ?)", [$fechaInicio, $fechaFin]);
    // ... procesar $reporte ...
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `getSingleResult($query, $params = [])`

#### Descripción
Ejecuta una consulta (usando `executeQuery`) y obtiene la primera fila del resultado como un array asociativo. Esencialmente un alias para `executeQuery` seguido de `fetch(PDO::FETCH_ASSOC)`.

#### Parámetros
| Parámetro | Tipo   | Descripción                                                         | Obligatorio | Valor por Defecto |
| --------- | ------ | ------------------------------------------------------------------- | ----------- | ----------------- |
| `$query`  | String | La consulta SQL completa, usando `?` o marcadores con nombre.       | Sí          | -                 |
| `$params` | Array  | Array con los valores para los marcadores en la consulta, en orden. | No          | `[]`              |

#### Retorno
-   `Array`: Un array asociativo con la primera fila del resultado.
-   `false`: Si la consulta no devuelve filas.

#### Excepciones
-   `Exception`: Si ocurre un error al ejecutar la consulta.

#### Ejemplo de Uso
```php
try {
    $configuracion = $database->getSingleResult("SELECT * FROM configuracion WHERE id = ?", [1]);
    if ($configuracion) {
        $tituloSitio = $configuracion['titulo'];
    }
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `insert($table, $data)`

#### Descripción
Inserta una nueva fila en la tabla especificada. Construye dinámicamente la consulta INSERT basada en las claves y valores del array `$data`.

#### Parámetros
| Parámetro | Tipo   | Descripción                                                                                               | Obligatorio |
| --------- | ------ | --------------------------------------------------------------------------------------------------------- | ----------- |
| `$table`  | String | Nombre de la tabla donde insertar.                                                                        | Sí          |
| `$data`   | Array  | Array asociativo donde las claves son los nombres de las columnas y los valores son los datos a insertar. | Sí          |

#### Retorno
-   `String`: El ID del último registro insertado (`lastInsertId()`).

#### Excepciones
-   `Exception`: Si ocurre un error durante la inserción.

#### Ejemplo de Uso (Adaptado de `crud_ahorro.php`)
```php
$datos = array(
    "ccodofi" => $agencia,
    "ccodtip" => $ccodtip,
    "nombre" => $nombre,
    // ... otras columnas ...
    "created_by" => $idusuario,
    "created_at" => $hoy2,
);
try {
    $nuevoId = $database->insert('ahomtip', $datos);
    echo "Registro insertado con ID: " . $nuevoId;
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `update($table, $data, $condition, $conditionParams = [])`

#### Descripción
Actualiza una o más filas en la tabla especificada que cumplan con la condición dada. Construye dinámicamente la cláusula SET basada en el array `$data`.

#### Parámetros
| Parámetro          | Tipo   | Descripción                                                                         | Obligatorio | Valor por Defecto |
| ------------------ | ------ | ----------------------------------------------------------------------------------- | ----------- | ----------------- |
| `$table`           | String | Nombre de la tabla a actualizar.                                                    | Sí          | -                 |
| `$data`            | Array  | Array asociativo con `columna => nuevo_valor`.                                      | Sí          | -                 |
| `$condition`       | String | La condición SQL para la cláusula WHERE (ej. `id = ?`, `email = ? AND activo = ?`). | Sí          | -                 |
| `$conditionParams` | Array  | Array con los valores para los marcadores `?` en la `$condition`.                   | No          | `[]`              |

#### Retorno
-   `Integer`: El número de filas afectadas por la actualización (`rowCount()`).

#### Excepciones
-   `Exception`: Si ocurre un error durante la actualización.

#### Ejemplo de Uso (Adaptado de `crud_ahorro.php`)
```php
$datos = array(
    "nombre" => $nombre,
    "tasa" => $tasa,
    // ... otras columnas ...
    "updated_by" => $idusuario,
    "updated_at" => $hoy2,
);
$decryptedID = 15; // Ejemplo
try {
    $filasAfectadas = $database->update('ahomtip', $datos, 'id = ?', [$decryptedID]);
    echo "Se actualizaron $filasAfectadas filas.";
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `delete($table, $condition, $params = [])`

#### Descripción
Elimina una o más filas de la tabla especificada que cumplan con la condición dada.

#### Parámetros
| Parámetro    | Tipo   | Descripción                                                           | Obligatorio | Valor por Defecto |
| ------------ | ------ | --------------------------------------------------------------------- | ----------- | ----------------- |
| `$table`     | String | Nombre de la tabla de donde eliminar.                                 | Sí          | -                 |
| `$condition` | String | La condición SQL para la cláusula WHERE (ej. `id = ?`, `estado = ?`). | Sí          | -                 |
| `$params`    | Array  | Array con los valores para los marcadores `?` en la `$condition`.     | No          | `[]`              |

#### Retorno
`void` (Aunque internamente usa `executeQuery`, no devuelve el `rowCount`).

#### Excepciones
-   `Exception`: Si ocurre un error durante la eliminación.

#### Ejemplo de Uso (Adaptado de `crud_ahorro.php`)
```php
$idBeneficiario = 50; // Ejemplo
try {
    $database->delete('ahomben', 'id_ben = ?', [$idBeneficiario]);
    echo "Beneficiario eliminado.";
} catch (Exception $e) {
    // ... manejo de error ...
}
```

---

### `joinQuery($tables, $columns = ['*'], $joins = [], $condition = '', $params = [])`

#### Descripción
Construye y ejecuta una consulta SQL que involucra múltiples tablas unidas mediante JOIN (INNER JOIN, LEFT JOIN, etc.).

#### Parámetros
| Parámetro    | Tipo   | Descripción                                                                                                                                                                                                                                                                                               | Obligatorio | Valor por Defecto |
| ------------ | ------ | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------- | ----------------- |
| `$tables`    | Array  | Array con los nombres de las tablas. La primera tabla es la base del `FROM`.                                                                                                                                                                                                                              | Sí          | -                 |
| `$columns`   | Array  | Array de strings con los nombres de las columnas a seleccionar (puede incluir alias de tabla, ej. `t1.columna`).                                                                                                                                                                                          | No          | `['*']`           |
| `$joins`     | Array  | Array de arrays asociativos, cada uno describiendo un JOIN: `['type' => 'INNER', 'table' => 'tabla2', 'condition' => 'tabla1.id = tabla2.fk_id', 'next_condition' => 'tabla2.id = tabla3.fk_id']` (Nota: `next_condition` parece incorrecto en el código original, debería ser parte del siguiente JOIN). | No          | `[]`              |
| `$condition` | String | La condición SQL para la cláusula WHERE (opcional).                                                                                                                                                                                                                                                       | No          | `''`              |
| `$params`    | Array  | Array con los valores para los marcadores `?` en la `$condition`.                                                                                                                                                                                                                                         | No          | `[]`              |

#### Retorno
-   `Array`: Un array de arrays asociativos con las filas y columnas resultantes del JOIN.

#### Excepciones
-   `Exception`: Si ocurre un error al preparar o ejecutar la consulta.

#### Ejemplo de Uso
```php
$tablas = ['pedidos', 'clientes'];
$columnas = ['pedidos.id', 'pedidos.fecha', 'clientes.nombre'];
$joins = [
    ['type' => 'INNER', 'table' => 'clientes', 'condition' => 'pedidos.id_cliente = clientes.id']
];
$condicion = 'pedidos.fecha >= ?';
$parametros = ['2024-01-01'];

try {
    $pedidosClientes = $database->joinQuery($tablas, $columnas, $joins, $condicion, $parametros);
    // ... procesar resultados ...
} catch (Exception $e) {
    // ... manejo de error ...
}
```
*(Nota: La implementación de `joinQuery` en el código original podría necesitar revisión, especialmente la parte de `next_condition`)*

---

### `utf8DecodeArray($data)`

#### Descripción
Recorre un array y aplica `utf8_decode()` a cada elemento que sea una cadena de texto. **Nota:** Esta función está presente pero parece no ser utilizada (comentada) en los métodos `insert` y `update`. Su necesidad depende de la codificación de los datos de entrada y la configuración de la base de datos/conexión. Usar con precaución.

#### Parámetros
| Parámetro | Tipo  | Descripción                              | Obligatorio |
| --------- | ----- | ---------------------------------------- | ----------- |
| `$data`   | Array | El array cuyos strings se decodificarán. | Sí          |

#### Retorno
-   `Array`: El mismo array con los strings decodificados.

#### Ejemplo de Uso
```php
$datosConUtf8 = ['nombre' => 'José Niño', 'ciudad' => 'México'];
$datosDecodificados = $database->utf8DecodeArray($datosConUtf8);
// $datosDecodificados contendría ['nombre' => 'Jos? Ni?o', 'ciudad' => 'M?xico'] si la codificación original era UTF-8 y el entorno no lo maneja bien.
```

---

## Gestión de Transacciones (Ejemplo Completo con Manejo de Errores Controlados)

Es fundamental usar transacciones cuando múltiples operaciones deben ocurrir como una unidad atómica. El uso de `$showmensaje` permite controlar qué tipo de mensaje de error se devuelve al usuario final.

```php
// $showmensaje = false; // Para errores no controlados (muestra código de error)
$showmensaje = true; // Para errores controlados (muestra mensaje de excepción directo, usar con precaución)

try {
    $database->openConnection();
    $database->beginTransaction(); // Iniciar transacción

    // Operación 1: Insertar datos principales
    $datosPrincipales = [/* ... */];
    $idPrincipal = $database->insert('tabla_principal', $datosPrincipales);

    // Operación 2: Insertar datos relacionados
    $datosRelacionados = ['fk_id' => $idPrincipal, /* ... */];
    $database->insert('tabla_relacionada', $datosRelacionados);

    // Operación 3: Actualizar un contador (simulando un posible error)
    // $database->executeQuery('UPDATE contadores SET valor = valor + 1 WHERE nombre = ?', ['operaciones_exitosas']);
    // Descomentar la siguiente línea para simular un error SQL
    // $database->executeQuery('UPDATE tabla_inexistente SET valor = 1 WHERE id = ?', [1]);


    $database->commit(); // Confirmar todas las operaciones
    $status = 1;
    $mensaje = "Operación completada con éxito.";

} catch (Exception $e) {
    $database->rollback(); // Revertir todo si algo falla

    // Manejo del mensaje de error basado en $showmensaje
    if (!$showmensaje) {
        // Error no controlado: Registrar el error detallado y obtener un código
        // La función logerrores guarda en /logs/errores.log
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        // Mensaje genérico para el usuario final
        $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    } else {
        // Error controlado: Mostrar el mensaje de la excepción directamente
        // ¡PRECAUCIÓN! Esto puede exponer detalles internos. Usar solo si es seguro.
        $mensaje = "Error controlado: " . $e->getMessage();
    }
    $status = 0; // Indicar que la operación falló

} finally {
    $database->closeConnection(); // Siempre cerrar la conexión
}
// Devolver el resultado (generalmente para AJAX)
echo json_encode([$mensaje, $status]);
```

---

## Manejo de Errores

La clase utiliza el modo de error `PDO::ERRMODE_EXCEPTION`. Esto significa que si ocurre un error en la base de datos (consulta mal formada, violación de restricción, etc.), PDO lanzará una `PDOException` (que es una subclase de `Exception`).

**Es obligatorio envolver todas las llamadas a los métodos de esta clase que interactúan con la base de datos en bloques `try...catch`**.

Dentro del bloque `catch`, se utiliza una variable booleana `$showmensaje` (definida *antes* del bloque `try`) para determinar cómo se gestiona el mensaje de error:

1.  **Si `$showmensaje` es `false` (Errores No Controlados):**
    *   Se asume que el error no es esperado o no debe ser expuesto directamente al usuario.
    *   Se llama a la función `logerrores()`, pasándole los detalles de la excepción (`$e->getMessage()`, archivo, línea, etc.).
    *   `logerrores()` registra esta información detallada en el archivo `/logs/errores.log`.
    *   `logerrores()` devuelve un código de error único.
    *   Se genera un mensaje genérico para el usuario final, incluyendo el código de error para que pueda ser reportado y rastreado en los logs. Ejemplo: `"Error: Intente nuevamente, o reporte este codigo de error(XYZ123)"`.

2.  **Si `$showmensaje` es `true` (Errores Controlados):**
    *   Se asume que el mensaje de la excepción es seguro y significativo para mostrarlo directamente al usuario (por ejemplo, un mensaje de validación específico lanzado desde un trigger o procedimiento almacenado).
    *   **No** se llama a `logerrores()`.
    *   El mensaje devuelto al usuario es directamente el mensaje de la excepción (`$e->getMessage()`).
    *   **Precaución:** Usar `$showmensaje = true` con cuidado, ya que podría exponer información sensible sobre la estructura de la base de datos o la lógica interna si la excepción no es específicamente diseñada para ser mostrada.

Independientemente del valor de `$showmensaje`, si se está dentro de una transacción (`$database->beginTransaction()`), se debe llamar a `$database->rollback()` en el bloque `catch` para deshacer cualquier cambio parcial realizado antes de que ocurriera el error.

Finalmente, el bloque `finally` asegura que `$database->closeConnection()` se ejecute siempre, liberando los recursos de la base de datos.

---

## Codificación de Caracteres

La conexión se establece explícitamente con el charset `utf8` mediante `PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"`. Esto asegura que la comunicación entre PHP y MySQL se realice en UTF-8, lo cual es recomendable para manejar correctamente caracteres especiales y acentos. La función `utf8DecodeArray` podría ser necesaria solo si los datos *entrantes* a PHP no están en UTF-8, lo cual es menos común en aplicaciones web modernas.

---

**Última actualización:** 14 de abril de 2025