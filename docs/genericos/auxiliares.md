# Documentación de Funciones Auxiliares de Obtención de Valores

Este documento describe tres funciones auxiliares utilizadas para recopilar valores de diferentes tipos de elementos de formulario HTML: `getinputsval`, `getselectsval` y `getradiosval`.

---

## 1. `getinputsval(datos)`

### Descripción

La función `getinputsval` recopila los valores actuales de un conjunto de elementos `<input>` (como `text`, `number`, `date`, `hidden`, etc.) especificados por sus IDs.

### Sintaxis

```javascript
getinputsval(datos)
```

### Parámetros

| Parámetro | Tipo    | Descripción                                                              | Obligatorio |
|-----------|---------|--------------------------------------------------------------------------|-------------|
| `datos`   | Array   | Un array de strings, donde cada string es el `id` de un elemento `<input>`. | Sí          |

### Retorno

-   **Tipo**: `Array`
-   **Descripción**: Retorna un array que contiene los valores (`value`) de cada uno de los inputs especificados. **Actualmente, los valores se almacenan en el array con índices numéricos (0, 1, 2, ...)** correspondientes al orden de los IDs en el array `datos` de entrada.

### Funcionamiento

Itera sobre el array `datos`. Para cada `id` en `datos`, obtiene el elemento del DOM correspondiente usando `document.getElementById()` y extrae su propiedad `value`. Almacena estos valores en un nuevo array que luego es retornado.

### Ejemplo de Uso

**HTML:**
```html
<input type="text" id="nombreUsuario" value="JuanPerez">
<input type="number" id="edadUsuario" value="30">
<input type="hidden" id="idSecreto" value="xyz123">
```

**JavaScript:**
```javascript
let idsInputs = ["nombreUsuario", "edadUsuario", "idSecreto"];
let valoresInputs = getinputsval(idsInputs);

console.log(valoresInputs);
// Salida esperada (actualmente): ["JuanPerez", "30", "xyz123"]
```

---

## 2. `getselectsval(datos)`

### Descripción

La función `getselectsval` recopila los valores de las opciones seleccionadas actualmente en un conjunto de elementos `<select>` (listas desplegables) especificados por sus IDs.

### Sintaxis

```javascript
getselectsval(datos)
```

### Parámetros

| Parámetro | Tipo    | Descripción                                                               | Obligatorio |
|-----------|---------|---------------------------------------------------------------------------|-------------|
| `datos`   | Array   | Un array de strings, donde cada string es el `id` de un elemento `<select>`. | Sí          |

### Retorno

-   **Tipo**: `Array`
-   **Descripción**: Retorna un array que contiene los valores (`value`) de la opción seleccionada (`<option>`) para cada uno de los selects especificados. **Actualmente, los valores se almacenan en el array con índices numéricos (0, 1, 2, ...)** correspondientes al orden de los IDs en el array `datos` de entrada.

### Funcionamiento

Itera sobre el array `datos`. Para cada `id` en `datos`, obtiene el elemento `<select>` del DOM usando `document.getElementById()`. Luego, accede a la colección `options` y obtiene el `value` de la opción en el `selectedIndex`. Almacena estos valores en un nuevo array que luego es retornado.

### Ejemplo de Uso

**HTML:**
```html
<select id="paisSelect">
  <option value="GT">Guatemala</option>
  <option value="MX" selected>México</option>
  <option value="US">Estados Unidos</option>
</select>
<select id="estadoCivilSelect">
  <option value="S">Soltero(a)</option>
  <option value="C">Casado(a)</option>
</select>
```

**JavaScript:**
```javascript
let idsSelects = ["paisSelect", "estadoCivilSelect"];
let valoresSelects = getselectsval(idsSelects);

console.log(valoresSelects);
// Salida esperada (actualmente): ["MX", "S"]
// (Nota: "S" es el valor de la primera opción si ninguna está seleccionada por defecto)
```

---

## 3. `getradiosval(datos)`

### Descripción

La función `getradiosval` recopila los valores de los botones de radio (`<input type="radio">`) que están seleccionados (marcados) dentro de un conjunto de grupos de radio, especificados por el atributo `name` de cada grupo.

### Sintaxis

```javascript
getradiosval(datos)
```

### Parámetros

| Parámetro | Tipo    | Descripción                                                                                                | Obligatorio |
|-----------|---------|------------------------------------------------------------------------------------------------------------|-------------|
| `datos`   | Array   | Un array de strings, donde cada string es el valor del atributo `name` compartido por un grupo de radio buttons. | Sí          |

### Retorno

-   **Tipo**: `Array`
-   **Descripción**: Retorna un array que contiene los valores (`value`) del radio button seleccionado (`checked`) para cada uno de los grupos especificados por `name`. **Actualmente, los valores se almacenan en el array con índices numéricos (0, 1, 2, ...)** correspondientes al orden de los nombres en el array `datos` de entrada.

### Funcionamiento

Itera sobre el array `datos`. Para cada `name` en `datos`, utiliza `document.querySelector()` con un selector de atributo CSS (`input[name="..."]:checked`) para encontrar el radio button dentro de ese grupo que está actualmente marcado. Extrae su propiedad `value`. Almacena estos valores en un nuevo array que luego es retornado.

### Ejemplo de Uso

**HTML:**
```html
<p>Género:</p>
<input type="radio" id="masc" name="genero" value="M">
<label for="masc">Masculino</label><br>
<input type="radio" id="fem" name="genero" value="F" checked>
<label for="fem">Femenino</label><br>

<p>Notificaciones:</p>
<input type="radio" id="notifSi" name="notificaciones" value="1" checked>
<label for="notifSi">Sí</label><br>
<input type="radio" id="notifNo" name="notificaciones" value="0">
<label for="notifNo">No</label><br>
```

**JavaScript:**
```javascript
let namesRadios = ["genero", "notificaciones"];
let valoresRadios = getradiosval(namesRadios);

console.log(valoresRadios);
// Salida esperada (actualmente): ["F", "1"]
```

---

## Nota Importante sobre el Formato de Retorno

Actualmente, estas tres funciones (`getinputsval`, `getselectsval`, `getradiosval`) retornan los valores recopilados en un **array con índices numéricos**. Existe la posibilidad de que en futuras versiones estas funciones sean modificadas para retornar un **array asociativo** (objeto), donde las claves podrían ser los IDs o nombres de los elementos y los valores serían los valores correspondientes. Esta documentación refleja el comportamiento **actual**.

---

**Última actualización:** 14 de abril de 2025