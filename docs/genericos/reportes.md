# Documentación de la Función `reportes`

## Descripción

La función `reportes` es una utilidad centralizada, presente en diversos módulos del sistema, diseñada para generar y gestionar la presentación de reportes. Puede generar reportes en diferentes formatos (PDF, Excel) para descarga o visualización, o mostrar datos directamente en pantalla en una tabla interactiva (DataTable) y un gráfico (Chart.js). Recopila datos de entrada del usuario, consulta la configuración del reporte si es necesario, realiza una solicitud AJAX al script de generación de reportes correspondiente y maneja la respuesta para mostrar o descargar el resultado.

---

## Sintaxis

```javascript
reportes(datos, tipo, file, download = 1, bandera = 0, label = "NULL", columdata = "NULL", tipodata = 1, labeltitle = "", top = 1)
```

---

## Parámetros

| Parámetro    | Tipo    | Descripción                                                                                                                                                                                                                            | Obligatorio | Valor por Defecto |
|--------------|---------|----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|-------------------|
| `datos`      | Array   | Un array que contiene cuatro subarrays: `[inputsIds, selectsIds, radiosNames, otrosDatos]`. Los tres primeros son arrays de strings con los IDs/names de los elementos de formulario cuyos valores se recopilarán. `otrosDatos` puede contener valores adicionales. | Sí          | -                 |
| `tipo`       | String  | El formato o tipo de salida deseado para el reporte. Valores comunes: `'pdf'`, `'xlsx'`, `'show'`.                                                                                                                                       | Sí          | -                 |
| `file`       | Mixed   | Identificador del reporte a generar. Puede ser un número (ID de descripción del reporte) o un string (nombre base del archivo PHP). Se usa para determinar el script PHP a ejecutar y/o consultar su configuración.                        | Sí          | -                 |
| `download`   | Integer | Controla el comportamiento para reportes descargables (`pdf`, `xlsx`): `0` para intentar mostrar en una nueva pestaña, `1` para iniciar la descarga del archivo.                                                                           | No          | `1`               |
| `bandera`    | Integer | Si es `1`, llama a `consultar_reporte` para obtener la configuración (nombre de archivo real, tipo) del reporte desde el servidor usando `file` como ID. Si es `0`, asume que `file` ya es el nombre del archivo PHP a usar.               | No          | `0`               |
| `label`      | String  | **(Solo para `tipo='show'`)** La clave de los datos a usar como etiqueta para agrupar en `builddata`.                                                                                                                                   | No          | `"NULL"`          |
| `columdata`  | String  | **(Solo para `tipo='show'`)** La clave de los datos cuyo valor se sumará o contará en `builddata`.                                                                                                                                      | No          | `"NULL"`          |
| `tipodata`   | Integer | **(Solo para `tipo='show'`)** Define la operación en `builddata`: `1` para contar, `2` para sumar.                                                                                                                                      | No          | `1`               |
| `labeltitle` | String  | **(Solo para `tipo='show'`)** El título para el dataset en el gráfico generado por `actualizarGrafica`.                                                                                                                                 | No          | `""`              |
| `top`        | Integer | **(Solo para `tipo='show'`)** Indica si mostrar los primeros (`1`) o los últimos (`!= 1`) N registros en el gráfico (`actualizarGrafica`).                                                                                              | No          | `1`               |

---

## Retorno

Esta función **no retorna** ningún valor directamente. Su propósito es interactuar con el servidor para generar un reporte y luego, dependiendo del `tipo` y la respuesta, iniciar una descarga, abrir una nueva pestaña o actualizar elementos en la página actual (tabla y gráfico).

---

## Funcionamiento Detallado

1.  **Recopilar Valores**: Llama a `getinputsval`, `getselectsval`, `getradiosval` usando los arrays proporcionados en `datos[0]`, `datos[1]`, `datos[2]` para obtener los valores actuales del formulario. Almacena estos valores y `datos[3]` en `datosval`.
2.  **Consultar Configuración (Opcional)**:
    *   Si `bandera` es `1`, llama a `consultar_reporte(file, bandera)`. Esta función (asíncrona, usa Promesa) consulta al servidor para obtener el nombre real del archivo PHP (`action.file`) y su tipo (`action.type`) basado en el ID `file`. Actualiza `file` con el nombre obtenido.
    *   Si `bandera` es `0`, continúa asumiendo que `file` es el nombre del script PHP.
3.  **Construir URL**: Determina la URL del script PHP del reporte concatenando la **ruta base de reportes del módulo actual** (ej. `aho/reportes/`), el valor de `file` (nombre del script) y `".php"`.
4.  **Solicitud AJAX**: Realiza una solicitud `POST` a la URL construida.
5.  **Envío de Datos**: Envía `datosval` (los valores recopilados) y el `tipo` de reporte solicitado al script PHP.
6.  **Respuesta Exitosa (`success`)**:
    *   Parsea la respuesta JSON (`data`).
    *   Verifica `opResult.status`:
        *   Si es `1` (éxito):
            *   **Si `tipo == "show"`**:
                *   Llama a `updatetable(opResult.data, opResult.encabezados, opResult.keys)` para llenar la tabla DataTable.
                *   Llama a `builddata(opResult.data, label, columdata, tipodata, labeltitle, top)` para procesar los datos y generar/actualizar el gráfico.
            *   **Si `tipo != "show"` (ej. 'pdf', 'xlsx')**:
                *   Determina la extensión del archivo (usa `opResult.extension` si existe, si no, usa `tipo`).
                *   Determina si se debe descargar (usa `opResult.download` si existe, si no, usa `download`).
                *   **Si `download == 0`**: Abre una nueva pestaña e intenta mostrar el archivo usando un `<object>`.
                *   **Si `download == 1`**: Crea un enlace (`<a>`) temporal invisible con la URL del archivo (`opResult.data`), le asigna el nombre de archivo (`opResult.namefile` + extensión), simula un clic para iniciar la descarga y luego elimina el enlace.
                *   Muestra un `Swal.fire` de éxito.
        *   Si es `0` (error): Muestra un `Swal.fire` de error con `opResult.mensaje`.
7.  **Finalización (`complete`)**: Llama a `loaderefect(0)` para ocultar el indicador de carga.
8.  **Manejo de Errores (Promesa/AJAX)**: Si `consultar_reporte` (si se usó) o la AJAX principal fallan, se captura el error y se muestra un `Swal.fire` de error.

---

## Dependencias

-   **jQuery** (`$`)
-   **SweetAlert** (`Swal`)
-   `loaderefect()` (Función para indicador de carga)
-   `getinputsval()`, `getselectsval()`, `getradiosval()` (Funciones auxiliares de obtención de valores)
-   `consultar_reporte()` (Función para obtener configuración del reporte, usa AJAX)
-   **(Si `tipo == 'show'`)**:
    *   **DataTables** (Para la tabla interactiva `#tbdatashow`)
    *   **Chart.js** y **Chartjs Datalabels plugin** (Para el gráfico en `#myChart`)
    *   `updatetable()`, `builddata()`, `actualizarGrafica()` (Funciones para mostrar datos en pantalla)

---

## Ejemplo de Uso

```javascript
// IDs/Names de los filtros del reporte
let inputsReporte = ["fechaInicio", "fechaFin"];
let selectsReporte = ["tipoCuenta"];
let radiosReporte = ["estado"];
let otrosDatos = ["usuarioActualId"]; // Datos adicionales

// Llamar para generar un PDF descargable del reporte con ID 5
reportes(
  [inputsReporte, selectsReporte, radiosReporte, otrosDatos],
  'pdf', // tipo
  5,     // file (ID del reporte)
  1,     // download (descargar)
  1      // bandera (consultar configuración)
);

// Llamar para mostrar datos en pantalla (tabla y gráfico) del reporte 'ventas_diarias.php'
// Agrupando por 'fecha', sumando 'total_venta', mostrando Top 10
reportes(
  [inputsReporte, [], [], otrosDatos], // Sin selects ni radios
  'show', // tipo
  'ventas_diarias', // file (nombre del script)
  1,     // download (irrelevante para 'show')
  0,     // bandera (no consultar config)
  'fecha', // label para builddata
  'total_venta', // columdata para builddata
  2,     // tipodata (sumar)
  'Ventas Diarias', // labeltitle para gráfico
  1      // top (mostrar top N)
);
```

---

## Notas Importantes

-   **Contexto del Módulo**: La ruta base para los scripts PHP de reportes (ej. `aho/reportes/`) **varía según el módulo** donde se utilice la función `reportes`.
-   **Script del Servidor**: El script PHP del reporte (`file`) debe estar preparado para recibir `datosval` y `tipo` vía POST y generar la salida correspondiente (JSON con datos/URL, o directamente el contenido del archivo).
-   **Seguridad**: Es crucial validar y sanitizar todos los datos (`datosval`, `tipo`, `file`) en el script PHP del servidor.
-   **Flexibilidad**: La función es bastante flexible al permitir diferentes formatos de salida y manejar tanto la descarga como la visualización en pantalla.

---

**Última actualización:** 14 de abril de 2025