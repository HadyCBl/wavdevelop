# Documentación de la Función `printdiv`

## Descripción

La función `printdiv` es una utilidad de JavaScript reutilizable, presente en varios módulos del sistema, que permite cargar contenido HTML de forma dinámica en un elemento específico de la página web. Utiliza una solicitud AJAX para obtener el contenido desde un script PHP en el servidor y lo inserta en el elemento HTML designado, sin necesidad de recargar toda la página.

---

## Sintaxis

```javascript
printdiv(condi, idiv, dir, xtra)
```

---

## Parámetros

| Parámetro | Tipo   | Descripción                                                                                                                                                              | Obligatorio |
|-----------|--------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-------------|
| `condi`   | String | Una condición o identificador. **Generalmente, este valor corresponde a un `case` dentro de una estructura `switch` en el script PHP del servidor (`dir`)**, indicando la acción específica que el backend debe realizar. | Sí          |
| `idiv`    | String | El selector CSS (por ejemplo, `#miDiv`, `.miClase`) del elemento HTML donde se insertará el contenido obtenido del servidor.                                                | Sí          |
| `dir`     | String | El nombre del archivo PHP (sin la extensión `.php`) que procesará la solicitud. **La ruta base a este archivo (ej. `aho/`, `cred/`, etc.) depende del módulo del sistema donde se esté utilizando `printdiv`**. | Sí          |
| `xtra`    | Mixed  | Datos adicionales que se pueden enviar al servidor para ser procesados junto con la `condi`. Puede ser un string, número, objeto, array, etc.                               | No          |

---

## Retorno

Esta función **no retorna** ningún valor directamente. Su propósito es modificar el DOM insertando el contenido HTML obtenido del servidor en el elemento especificado por `idiv`.

---

## Funcionamiento Detallado

1.  **Inicio de Carga**: Llama a `loaderefect(1)` para mostrar un indicador visual de que se está realizando una operación.
2.  **Construcción de URL**: Determina la URL del script PHP del servidor concatenando la **ruta base del módulo actual** (ej. `aho/`), el valor del parámetro `dir`, y `".php"`.
3.  **Solicitud AJAX**: Realiza una solicitud HTTP `POST` a la URL construida utilizando jQuery (`$.ajax`).
4.  **Envío de Datos**: Envía los valores de `condi` y `xtra` como datos en la solicitud POST. El servidor utiliza `condi` para dirigir la lógica (a menudo dentro de un `switch`).
5.  **Respuesta Exitosa (`success`)**:
    *   Si la solicitud es exitosa, recibe el contenido HTML (`data`) del servidor.
    *   Inserta este contenido HTML dentro del elemento DOM especificado por `idiv` usando `$(idiv).html(data)`.
    *   Llama a `loaderefect(0)` para ocultar el indicador de carga.
6.  **Manejo de Errores (`error`)**:
    *   Si la solicitud falla, llama a `loaderefect(0)`.
    *   Intenta parsear la respuesta de error como JSON.
    *   Busca una propiedad `messagecontrol` en el JSON parseado. Si existe, asume que es un error controlado por el sistema:
        *   Muestra un mensaje de error usando `Swal.fire`.
        *   Redirige al usuario a la URL especificada en `data2.url` después de 2 segundos.
    *   Si no hay `messagecontrol` o falla el parseo JSON, muestra el objeto `xhr` (XMLHttpRequest) en la consola para depuración.

---

## Ejemplos de Uso

*(Los ejemplos asumen que la función se usa en el módulo de Ahorros, por lo tanto, la ruta base es `aho/`)*

### Ejemplo 1: Cargar detalles de una cuenta de ahorros

```javascript
// Llamada a la función en el módulo de Ahorros
printdiv("detalleCuenta", "#cuenta-info", "cuenta_detalle", "AH00123");
```

-   `condi`: `"detalleCuenta"` (corresponderá a `case "detalleCuenta":` en `aho/cuenta_detalle.php`).
-   `idiv`: `"#cuenta-info"`.
-   `dir`: `"cuenta_detalle"` (se llamará a `aho/cuenta_detalle.php`).
-   `xtra`: `"AH00123"` (el código de la cuenta).

### Ejemplo 2: Cargar formulario para nuevo beneficiario

```javascript
// Llamada a la función en el módulo de Ahorros
printdiv("formNuevoBenef", "#modal-beneficiario-body", "beneficiario_form");
```

-   `condi`: `"formNuevoBenef"` (corresponderá a `case "formNuevoBenef":` en `aho/beneficiario_form.php`).
-   `idiv`: `"#modal-beneficiario-body"`.
-   `dir`: `"beneficiario_form"` (se llamará a `aho/beneficiario_form.php`).
-   `xtra`: No se pasa.

### Ejemplo 3: Cargar historial de movimientos (módulo Créditos)

*(Este ejemplo hipotético muestra cómo cambiaría la ruta base)*

```javascript
// Llamada a la función (hipotética) en el módulo de Créditos
// Asumiendo que la ruta base para créditos es 'cred/'
// printdiv("historialMov", "#credito-historial", "movimientos_credito", { prestamoId: "CR0055" });
```

-   `condi`: `"historialMov"`.
-   `idiv`: `"#credito-historial"`.
-   `dir`: `"movimientos_credito"` (se llamaría a `cred/movimientos_credito.php`).
-   `xtra`: `{ prestamoId: "CR0055" }`.

---

## Notas Importantes

-   **Dependencias**: Requiere **jQuery** (`$`), una función `loaderefect()` y **SweetAlert** (`Swal`).
-   **Contexto del Módulo**: La ruta base para el script PHP (`dir`) **varía según el módulo** del sistema (ej. `aho/`, `cred/`, `admin/`, etc.). Asegúrate de que la ruta sea correcta para el contexto donde se usa `printdiv`.
-   **Script del Servidor**: El script PHP especificado por `dir` debe estar preparado para recibir `condi` y `xtra` vía POST y contener la lógica (a menudo un `switch` basado en `condi`) para generar el HTML correspondiente.
-   **Seguridad**: Es **fundamental** que los scripts PHP del lado del servidor validen y saniticen todos los datos recibidos (`condi` y `xtra`) para prevenir vulnerabilidades.
-   **Errores Controlados**: El mecanismo `messagecontrol` permite una gestión de errores centralizada desde el backend.

---

## Código Fuente de Referencia (Módulo Ahorros)

```javascript
// filepath: c:\laragon\www\microsystemplus\includes\js\scrpt_aho.js
function printdiv(condi, idiv, dir, xtra) {
  loaderefect(1); // Muestra el loader
  // La ruta base 'aho/' es específica de este módulo
  dire = "aho/" + dir + ".php"; // Construye la URL del script PHP
  $.ajax({
    url: dire, // URL del servidor
    method: "POST", // Método HTTP
    data: { condi, xtra }, // Datos a enviar (condi usualmente es un 'case' en PHP)
    success: function (data) { // Función si la solicitud es exitosa
      loaderefect(0); // Oculta el loader
      $(idiv).html(data); // Inserta la respuesta HTML en el div
    },
    error: function (xhr) { // Función si hay un error
      loaderefect(0); // Oculta el loader
      try {
        const data2 = JSON.parse(xhr.responseText);
        if ("messagecontrol" in data2) { // Manejo de errores controlados
          Swal.fire({
            icon: 'error',
            title: '¡ERROR!',
            text: 'Información de error: ' + data2.mensaje
          }).then(() => { /* Callback vacío */ });
          setTimeout(() => { // Redirección controlada
            window.location.href = data2.url;
          }, 2000);
        } else {
          console.log(xhr); // Error no controlado
        }
      } catch (e) {
        console.error("Error al procesar la respuesta del servidor:", e);
        console.log(xhr); // Error de parseo o respuesta inesperada
      }
    }
  });
}
```

---

**Última actualización:** 14 de abril de 2025