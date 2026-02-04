# Documentación de Funciones `obtiene` y `generico`

Este documento describe las funciones `obtiene` y `generico`, que trabajan conjuntamente para recopilar datos de formularios, enviarlos al servidor mediante AJAX y manejar la respuesta. Estas funciones son comúnmente encontradas en los archivos JavaScript de diversos módulos del sistema.

---

## 1. `obtiene(...)`

### Descripción

La función `obtiene` actúa como una **capa de abstracción** o **envoltorio** (wrapper) antes de realizar una operación principal con el servidor. Sus responsabilidades principales son:
1.  Recopilar valores de elementos `<input>`, `<select>` y `<input type="radio">` utilizando las funciones auxiliares `getinputsval`, `getselectsval` y `getradiosval`.
2.  Opcionalmente, mostrar un diálogo de confirmación al usuario (usando SweetAlert) antes de proceder.
3.  Invocar a la función `generico` para realizar la solicitud AJAX real, pasándole los valores recopilados y otros parámetros necesarios.

### Sintaxis

```javascript
obtiene(inputs, selects, radios, condi, id, archivo, callback, confirmacion = false, mensaje = "¿Desea continuar con el proceso?")
```

### Parámetros

| Parámetro     | Tipo      | Descripción                                                                                                                                                              | Obligatorio | Valor por Defecto                 |
|---------------|-----------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|-----------------------------------|
| `inputs`      | Array     | Array de strings con los `id` de los elementos `<input>` cuyos valores se recopilarán.                                                                                     | Sí          | -                                 |
| `selects`     | Array     | Array de strings con los `id` de los elementos `<select>` cuyos valores seleccionados se recopilarán.                                                                      | Sí          | -                                 |
| `radios`      | Array     | Array de strings con los `name` de los grupos de `<input type="radio">` cuyos valores seleccionados se recopilarán.                                                        | Sí          | -                                 |
| `condi`       | String    | Condición o identificador para el backend (usualmente un `case` en un `switch` PHP).                                                                                       | Sí          | -                                 |
| `id`          | Mixed     | Un identificador adicional, a menudo usado para identificar el registro a afectar (ej. ID de usuario, ID de cuenta).                                                       | Sí          | -                                 |
| `archivo`     | Array    | **(Uso variable)** Datos que se necesiten mandar al servidor que no sean valores de algun elemento del formulario.          | Sí          | -                                 |
| `callback`    | Function  | **(Uso recomendado)** Una función que se ejecutará *después* de que la solicitud AJAX en `generico` sea exitosa. Ideal para lógica post-operación específica.              | No          | `null`                            |
| `confirmacion`| Boolean   | Si es `true`, muestra un diálogo de confirmación antes de llamar a `generico`.                                                                                             | No          | `false`                           |
| `mensaje`     | String    | El texto a mostrar en el diálogo de confirmación si `confirmacion` es `true`.                                                                                              | No          | "¿Desea continuar con el proceso?" |

### Retorno

Esta función **no retorna** ningún valor directamente. Su propósito es coordinar la recopilación de datos, la confirmación opcional y la llamada a `generico`.

### Funcionamiento Detallado

1.  Llama a `getinputsval(inputs)`, `getselectsval(selects)` y `getradiosval(radios)` para obtener los valores actuales de los elementos del formulario.
2.  Verifica el parámetro `confirmacion`:
    *   Si es `true`, muestra un `Swal.fire` de tipo `warning` con el `mensaje` proporcionado. Si el usuario confirma, procede al paso 3. Si cancela, no hace nada más.
    *   Si es `false`, procede directamente al paso 3.
3.  Llama a la función `generico`, pasándole:
    *   Los arrays de *valores* obtenidos (`inputs2`, `selects2`, `radios2`).
    *   Los arrays originales de *IDs/nombres* (`inputs`, `selects`, `radios`) - **Nota:** Estos son los parámetros `inputsn`, `selectsn`, `radiosn` en `generico`, que ahora son obsoletos.
    *   Los parámetros `condi`, `id`, `archivo` y `callback`.

### Ejemplo de Uso

```javascript
// IDs de los inputs, selects y names de los radios
let misInputs = ["nombre", "email"];
let misSelects = ["pais"];
let misRadios = ["estadoCivil"];

// Función callback a ejecutar si todo va bien
function postGuardadoExitoso(respuestaServidor) {
  console.log("¡Guardado con éxito!", respuestaServidor);
  // Limpiar formulario, actualizar tabla, etc.
}

// Llamar a obtiene para guardar datos, pidiendo confirmación
obtiene(
  misInputs,
  misSelects,
  misRadios,
  "guardarUsuario", // condi
  null,             // id (no aplica en este caso)
  [],   // archivo (datos adicionales a mandar al servidor)
  postGuardadoExitoso, // callback
  true,             // confirmacion
  "¿Está seguro de guardar este usuario?" // mensaje
);
```

---

## 2. `generico(...)`

### Descripción

La función `generico` es el **núcleo** que realiza la comunicación con el servidor. Envía los datos recopilados (y otros parámetros) a un script PHP centralizado (generalmente un CRUD) mediante una solicitud AJAX POST y maneja la respuesta del servidor, incluyendo casos de éxito, errores y lógicas específicas (en versiones futuras se estaran quitando las logicas especificas dentro de esa funcion, actualmente ya se recomienda utilizar la funcion callback).

### Sintaxis

```javascript
generico(inputs, selects, radios, inputsn, selectsn, radiosn, condi, id, archivo, callback)
```

### Parámetros

| Parámetro   | Tipo     | Descripción                                                                                                                                                                                                                            | Obligatorio |
|-------------|----------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|
| `inputs`    | Array    | Array con los **valores** de los elementos `<input>` (recibido desde `obtiene`).                                                                                                                                                         | Sí          |
| `selects`   | Array    | Array con los **valores** de los elementos `<select>` (recibido desde `obtiene`).                                                                                                                                                        | Sí          |
| `radios`    | Array    | Array con los **valores** de los `<input type="radio">` seleccionados (recibido desde `obtiene`).                                                                                                                                        | Sí          |
| `inputsn`   | Array    | **(Obsoleto)** Array con los **IDs** de los elementos `<input>`. Ya no se utiliza activamente en la lógica principal de `generico`, aunque se recibe como parámetro.                                                                       | Sí          |
| `selectsn`  | Array    | **(Obsoleto)** Array con los **IDs** de los elementos `<select>`. Ya no se utiliza activamente.                                                                                                                                          | Sí          |
| `radiosn`   | Array    | **(Obsoleto)** Array con los **names** de los grupos de radio. Ya no se utiliza activamente.                                                                                                                                             | Sí          |
| `condi`     | String   | Condición o identificador para el backend (usualmente un `case` en un `switch` PHP). Determina la acción a realizar en el servidor.                                                                                                     | Sí          |
| `id`        | Mixed    | Identificador adicional para el backend (ej. ID del registro a modificar/eliminar).                                                                                                                                                    | Sí          |
| `archivo`   | Array   | **(Uso variable)** Un array con valores que se necesiten enviar al servidor que no sean elementos de algun formulario                    | Sí          |
| `callback`  | Function | **(Uso recomendado)** Función a ejecutar si la solicitud AJAX es exitosa y no es un caso especial (como `alertaIVE` o reportes). Recibe la respuesta parseada del servidor (`data2`) como argumento. **Es la forma moderna de manejar la lógica post-éxito.** | No          |

### Retorno

Esta función **no retorna** ningún valor directamente. Maneja la respuesta AJAX y ejecuta acciones como mostrar alertas (SweetAlert), actualizar partes de la página (a través del `callback` o lógica legacy) o manejar redirecciones.

### Funcionamiento Detallado

1.  **Inicio de Carga**: Llama a `loaderefect(1)`.
2.  **Solicitud AJAX**: Realiza una solicitud `POST` a una URL **generalmente fija** para el módulo (ej. `../src/cruds/crud_ahorro.php`).
3.  **Envío de Datos**: Envía todos los parámetros recibidos (incluyendo los obsoletos `inputsn`, `selectsn`, `radiosn`) al script PHP.
4.  **Respuesta Exitosa (`success`)**:
    *   Parsea la respuesta JSON (`data`).
    *   **Manejo de Casos Especiales (deprecatado en versiones posteriores)**:
        *   Si `data2.status === 'alertaIVE'`: Muestra alertas específicas (IVE, espera de aprobación) y detiene el flujo normal.
    *   **Éxito General**:
        *   Si `data2[1] == "1"` (indicador de éxito del backend):
            *   Muestra un `Swal.fire` de éxito.
            *   **Ejecución Post-Éxito**:
                *   **(Legacy)** Ejecuta bloques `if/else if` basados en `condi` para realizar acciones específicas (ej. `creaComprobante`, `creaLib`, recargar tablas, llamar a `printdiv2`). **Esta forma está siendo reemplazada por el uso del `callback`**.
                *   **(Recomendado)** Si se proporcionó una función `callback`, la ejecuta pasándole `data2`.
    *   **Otros Casos**:
        *   **Error del Backend**: Si no es éxito ni caso especial, muestra un `Swal.fire` de error con el mensaje `data2[0]`. .
5.  **Finalización (`complete`)**: Llama a `loaderefect(0)` para ocultar el indicador de carga, independientemente del resultado.

### Notas Importantes

-   **Dependencias**: Requiere **jQuery** (`$`), `loaderefect()`, y **SweetAlert** (`Swal`).
-   **URL del Backend**: La URL del AJAX es crucial y generalmente apunta a un script CRUD centralizado para el módulo.
-   **Parámetros Obsoletos**: `inputsn`, `selectsn`, `radiosn` se siguen pasando pero **no deben ser considerados fiables** para nueva lógica dentro de `generico`. Su propósito original era enviar los nombres/IDs junto con los valores, pero esto ya no es necesario o se maneja de otra forma.
-   **Uso del Callback**: Para nueva funcionalidad o refactorización, **se recomienda encarecidamente utilizar el parámetro `callback`** para manejar la lógica que debe ejecutarse después de una operación exitosa, en lugar de añadir más bloques `if/else if` dentro del `success` de `generico`. Esto mejora la modularidad y legibilidad.
-   **Seguridad**: El script PHP backend **debe** validar y sanitizar rigurosamente todos los datos recibidos.

---

**Última actualización:** 14 de abril de 2025