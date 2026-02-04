# Documentación de Funciones para Reportes en Pantalla

Este documento describe un conjunto de funciones (`updatetable`, `builddata`, `actualizarGrafica`) utilizadas para procesar datos obtenidos del servidor y presentarlos al usuario en forma de tabla interactiva (DataTable) y un gráfico de barras (Chart.js).

---

## 1. `updatetable(datos, encabezados, keys)`

### Descripción

La función `updatetable` se encarga de poblar o actualizar una tabla HTML específica (con `id="tbdatashow"`) utilizando la librería DataTables. Recibe los datos, los encabezados deseados y las claves para mapear los datos a las columnas.

### Sintaxis

```javascript
updatetable(datos, encabezados, keys)
```

### Parámetros

| Parámetro    | Tipo    | Descripción                                                                                                                               | Obligatorio |
|--------------|---------|-------------------------------------------------------------------------------------------------------------------------------------------|-------------|
| `datos`      | Array   | Un array de objetos, donde cada objeto representa una fila de datos.                                                                        | Sí          |
| `encabezados`| Array   | Un array de strings que contiene los títulos para cada columna de la tabla, en el orden deseado.                                            | Sí          |
| `keys`       | Array   | Un array de strings que especifica las claves (propiedades) de los objetos en `datos` que se deben mostrar en cada columna, en el mismo orden que `encabezados`. | Sí          |

### Retorno

Esta función **no retorna** ningún valor. Su efecto es modificar la tabla HTML `#tbdatashow` y reinicializarla como un DataTable con los nuevos datos.

### Funcionamiento Detallado

1.  **Mostrar Contenedor**: Hace visible el contenedor de la tabla (presumiblemente un `div` con `id="divshow"`).
2.  **Limpiar Encabezados**: Elimina cualquier encabezado (`<thead>`) existente en la tabla `#tbdatashow`.
3.  **Crear Nuevos Encabezados**: Crea una nueva fila de encabezado (`<tr>`) y añade celdas de encabezado (`<th>`) por cada string en el array `encabezados`.
4.  **Obtener Instancia DataTable**: Obtiene la instancia existente de DataTable asociada a `#tbdatashow`.
5.  **Mapear Datos**: Transforma el array de objetos `datos` en un array de arrays, donde cada array interno representa una fila y contiene los valores correspondientes a las `keys` especificadas, en el orden correcto.
6.  **Limpiar y Poblar Tabla**: Limpia los datos existentes en la instancia DataTable (`table.clear()`).
7.  **Agregar Nuevos Datos**: Agrega las nuevas filas de datos (el array de arrays mapeado) a la tabla (`table.rows.add(...)`).
8.  **Redibujar Tabla**: Redibuja la tabla DataTable para mostrar los nuevos datos (`.draw()`).

### Dependencias

-   **jQuery** (`$`)
-   **DataTables** (Librería para tablas interactivas)

### Ejemplo de Contexto de Uso

Esta función se llama típicamente dentro del `success` de una solicitud AJAX (como en la función `reportes`) cuando el tipo de reporte solicitado es `"show"`.

```javascript
// Dentro de la función reportes, si tipo es "show"
success: function (data) {
  var opResult = JSON.parse(data);
  if (opResult.status == 1 && tipo == "show") {
    // Llama a updatetable con los datos, encabezados y claves recibidos
    updatetable(opResult.data, opResult.encabezados, opResult.keys);
    // ... luego podría llamar a builddata ...
  }
  // ...
}
```

---

## 2. `builddata(data, label, columndata, tipodata, labeltitle, top)`

### Descripción

La función `builddata` procesa un conjunto de datos crudos para agruparlos, contarlos o sumarlos según criterios específicos. Está diseñada para preparar los datos antes de enviarlos a una función de graficación como `actualizarGrafica`.

### Sintaxis

```javascript
builddata(data, label, columndata, tipodata, labeltitle, top)
```

### Parámetros

| Parámetro    | Tipo    | Descripción                                                                                                                                                              | Obligatorio |
|--------------|---------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|
| `data`       | Array   | El array de objetos con los datos crudos a procesar (generalmente `opResult.data` de la respuesta AJAX).                                                                   | Sí          |
| `label`      | String  | La clave (propiedad) del objeto en `data` que se usará como etiqueta o categoría para agrupar (ej. 'fecha', 'nombre_producto', 'estado').                                  | Sí          |
| `columndata` | String  | La clave (propiedad) del objeto en `data` cuyo valor se contará o sumará para cada grupo definido por `label`.                                                             | Sí          |
| `tipodata`   | Integer | Un indicador para definir la operación de agregación: `1` para **contar** la cantidad de registros por grupo; `2` para **sumar** los valores de `columndata` para cada grupo. | Sí          |
| `labeltitle` | String  | El título que se usará para el conjunto de datos en el gráfico (pasado luego a `actualizarGrafica`).                                                                       | Sí          |
| `top`        | Integer | Un indicador para seleccionar los primeros (`1`) o los últimos (`!= 1`) N registros después de ordenar (usado en `actualizarGrafica`).                                      | Sí          |

### Retorno

Esta función **no retorna** ningún valor explícitamente. Su propósito es procesar los datos y luego llamar a `actualizarGrafica` con los datos agregados y el título.

### Funcionamiento Detallado

1.  **Obtener Etiquetas Únicas**: Utiliza `reduce` para encontrar todos los registros únicos basados en el valor de la propiedad `label`.
2.  **Iterar y Agregar**:
    *   Recorre cada registro único encontrado.
    *   Para cada valor único de `label` (ej. una fecha específica), filtra el array `data` original para obtener todos los registros que coinciden con esa etiqueta.
    *   **Calcular Valor Agregado**:
        *   Si `tipodata` es `1`, cuenta cuántos registros hay en el grupo filtrado (`tabulado.length`).
        *   Si `tipodata` es `2`, extrae los valores de la propiedad `columndata` del grupo filtrado, los convierte a `float` y los suma usando `reduce`.
    *   **Construir Array Procesado**: Crea un nuevo array (`datos`) donde cada elemento es un objeto con las propiedades `no` (índice), `fecha` (la etiqueta única, **nota:** el nombre 'fecha' es fijo aquí, podría ser confuso si `label` no es una fecha) y `cantidad` (el valor contado o sumado).
3.  **Ordenar Datos**: Ordena el array `datos` procesado en orden descendente basado en la propiedad `fecha`.
4.  **Llamar a Graficación**: Invoca a la función `actualizarGrafica`, pasándole los `datosOrdenados`, el `labeltitle` y el parámetro `top`.

### Ejemplo de Contexto de Uso

Similar a `updatetable`, se llama después de obtener datos para un reporte tipo `"show"`.

```javascript
// Dentro de la función reportes, si tipo es "show"
success: function (data) {
  var opResult = JSON.parse(data);
  if (opResult.status == 1 && tipo == "show") {
    updatetable(opResult.data, opResult.encabezados, opResult.keys);
    // Llama a builddata para procesar los mismos datos para el gráfico
    // Ej: Agrupar por 'fecha_transaccion', sumar 'monto', título 'Monto por Día', mostrar top 30
    builddata(opResult.data, 'fecha_transaccion', 'monto', 2, 'Monto por Día', 1);
  }
  // ...
}
```

---

## 3. `actualizarGrafica(datos, labeltitle, topdown)`

### Descripción

La función `actualizarGrafica` se encarga de renderizar o actualizar un gráfico de barras en un elemento `<canvas>` con `id="myChart"`, utilizando la librería Chart.js y el plugin Chartjs Datalabels. Muestra un número limitado de puntos de datos (top o bottom 30).

### Sintaxis

```javascript
actualizarGrafica(datos, labeltitle, topdown)
```

### Parámetros

| Parámetro    | Tipo    | Descripción                                                                                                                                                              | Obligatorio |
|--------------|---------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|
| `datos`      | Array   | El array de objetos con los datos **procesados y agregados** (resultado de `builddata`), típicamente con propiedades como `fecha` (etiqueta) y `cantidad` (valor).         | Sí          |
| `labeltitle` | String  | El título para el conjunto de datos (dataset) que se mostrará en la leyenda o tooltips del gráfico.                                                                       | Sí          |
| `topdown`    | Integer | Un indicador para seleccionar qué parte de los datos `datos` mostrar: `1` para los primeros 30 registros (top); cualquier otro valor para los últimos 30 registros (bottom). | Sí          |

### Retorno

Esta función **no retorna** ningún valor. Su efecto es dibujar o actualizar el gráfico en el canvas `#myChart`.

### Funcionamiento Detallado

1.  **Mostrar Contenedor**: Hace visible el contenedor del gráfico (presumiblemente un `div` con `id="divshowchart"`).
2.  **Seleccionar Datos**: Utiliza `slice()` para extraer los primeros 30 (`topdown == 1`) o los últimos 30 (`topdown != 1`) elementos del array `datos`.
3.  **Extraer Etiquetas y Valores**: Mapea los datos seleccionados (`top`) para crear dos arrays separados: `palabras` (con los valores de la propiedad `fecha` de cada objeto) y `cant` (con los valores de la propiedad `cantidad`).
4.  **Obtener Contexto del Canvas**: Obtiene el contexto 2D del elemento `<canvas>` con `id="myChart"`.
5.  **Destruir Gráfico Anterior**: Si ya existe una instancia de Chart.js (`myChart`) asociada a ese canvas, la destruye para evitar conflictos o duplicados.
6.  **Configurar Datos del Gráfico**: Crea un objeto `data` para Chart.js, especificando las `labels` (array `palabras`) y los `datasets`. El dataset incluye el `label` (título), los `data` (array `cant`) y opciones de estilo (colores, bordes).
7.  **Configurar Opciones del Gráfico**: Crea un objeto `config` para Chart.js, especificando el `type` ('bar'), los `data` y las `options`. Las opciones incluyen la configuración del plugin `datalabels` (para mostrar los valores encima de las barras) y configuraciones básicas de escalas y datasets.
8.  **Crear Nueva Instancia del Gráfico**: Crea una nueva instancia de `Chart` usando el contexto del canvas y la configuración (`myChart = new Chart(ctx, config)`), lo que dibuja el gráfico.

### Dependencias

-   **Chart.js** (Librería principal de gráficos)
-   **Chartjs Datalabels plugin** (Plugin para mostrar valores en el gráfico)

### Ejemplo de Contexto de Uso

Esta función es llamada exclusivamente por `builddata` después de procesar los datos.

```javascript
// Dentro de la función builddata
function builddata(data, label, columndata, tipodata, labeltitle, top) {
  // ... (procesamiento de datos) ...
  const datosOrdenados = datos.sort((a, b) => b.fecha - a.fecha);
  // Llama a actualizarGrafica con los datos procesados
  actualizarGrafica(datosOrdenados, labeltitle, top);
}
```

---

**Última actualización:** 14 de abril de 2025