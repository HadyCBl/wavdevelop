# Guía de Implementación: Filtro de Regiones en Reportes

## Contexto General

El filtro de regiones permite agrupar múltiples agencias bajo una "región" y generar reportes filtrados por región en lugar de por agencia individual. Esto facilita la visualización consolidada de datos por zonas geográficas o divisiones administrativas.

---

## Estructura de Base de Datos

### Tabla: `cre_regiones`
Almacena las definiciones de regiones.

```sql
CREATE TABLE cre_regiones (
    id_region INT PRIMARY KEY AUTO_INCREMENT,
    nombre_region VARCHAR(100) NOT NULL,
    descripcion TEXT,
    estado ENUM('activo', 'inactivo') DEFAULT 'activo',
    fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

**Ejemplo de datos:**
| id_region | nombre_region | descripcion |
|-----------|---------------|-------------|
| 1 | Norte | Agencias de la región norte |
| 2 | Sur | Agencias de la región sur |
| 3 | Oriente | Agencias de la región oriental |

---

### Tabla: `cre_regiones_agencias`
Tabla de relación entre regiones y agencias (relación muchos a muchos).

```sql
CREATE TABLE cre_regiones_agencias (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_region INT NOT NULL,
    id_agencia INT NOT NULL,
    fecha_asignacion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_region) REFERENCES cre_regiones(id_region),
    FOREIGN KEY (id_agencia) REFERENCES tb_agencia(id_agencia),
    UNIQUE KEY unique_region_agencia (id_region, id_agencia)
);
```

**Ejemplo de datos:**
| id | id_region | id_agencia |
|----|-----------|------------|
| 1 | 1 | 10 |
| 2 | 1 | 11 |
| 3 | 1 | 12 |
| 4 | 2 | 20 |
| 5 | 2 | 21 |

**Interpretación:** La región 1 (Norte) contiene las agencias 10, 11 y 12.

---

## Flujo de Datos desde el Frontend

### Convención de Payload

Para mantener compatibilidad con la estructura existente, los filtros de región se añaden **al final** del payload:

```javascript
// Estructura del payload enviado desde el frontend
datosval = [
    [`fecha`],                    // inputs[0]
    [`agenciaId`, `fondoId`, `asesorId`, `regionId`],  // selects[0-3]
    [`radioAgencia`, `radioFondo`, `radioStatus`, `radioAsesor`, `radioRegion`],  // radios[0-4]
    [`usuario`]                   // archivo[0]
];
```

### Valores del Radio Button (radios[4])
- `allregion`: Todas las regiones (sin filtro)
- `anyregion`: Filtrar por una región específica

### Valores del Select (selects[3])
- `0`: Sin región seleccionada
- `> 0`: ID de la región seleccionada

---

## Implementación en PHP Backend

### 1. Recibir y Validar Parámetros

```php
// Extraer valores del payload
$regionRadio = $radios[4] ?? null;
$regionId = isset($selects[3]) ? (int)$selects[3] : 0;

// Validación: Si eligió "anyregion" pero no seleccionó región válida
if ($regionRadio === 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    exit;
}
```

---

### 2. Construir el Filtro SQL

```php
// FILTRO POR REGION
$filregion = ($regionRadio === 'anyregion' && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . (int)$regionId . ")"
    : "";
```

**Explicación del SQL:**
- `CODAgencia IN (SELECT ...)`: Filtra por las agencias que pertenecen a la región
- El subquery devuelve todas las agencias asignadas a la región especificada
- Si `$regionRadio !== 'anyregion'`, el filtro queda vacío (`""`) y no aplica restricción

---

### 3. Inyectar el Filtro en los Queries

El filtro `$filregion` debe inyectarse en **todos los queries** que necesiten respetar el filtro de región:

```php
$strquery_principal = "
SELECT
    cremi.CODAgencia,
    ffon.descripcion AS nombre_fondo,
    SUM(cremi.NCapDes) AS monto_otorgado
FROM cremcre_meta cremi
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND cremi.DFecDsbls <= '$filtrofecha'
  $filfondo
  $filagencia
  $filasesor
  $filregion    -- ← INYECTAR AQUÍ
  $status
GROUP BY cremi.CODAgencia, ffon.descripcion
ORDER BY cremi.CODAgencia;
";
```

**Puntos importantes:**
- El filtro se coloca **después** de otros filtros (agencia, fondo, asesor)
- No lleva `WHERE` porque se concatena con `AND` al string existente
- Si `$filagencia` está activo (filtro por agencia individual), el filtro de región puede quedar redundante pero no genera conflicto

---

## Ejemplo Completo: Reporte de Cartera Consolidada

```php
<?php
// 1. RECIBIR PAYLOAD
$datos = $_POST["datosval"];
$inputs = $datos[0];
$selects = $datos[1];
$radios = $datos[2];

// 2. VALIDAR REGIÓN
$regionRadio = $radios[4] ?? null;
$regionId = isset($selects[3]) ? (int)$selects[3] : 0;

if ($regionRadio === 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    exit;
}

// 3. CONSTRUIR FILTROS
$filtrofecha = $inputs[0];
$filfondo = ($radios[1] == "anyf") ? " AND ffon.id=" . $selects[1] : "";
$filagencia = ($radios[0] == "anyofi") ? " AND cremi.CODAgencia=" . $selects[0] : "";
$filasesor = ($radios[3] == "anyasesor") ? " AND cremi.CodAnal=" . $selects[2] : "";

// FILTRO DE REGIÓN
$filregion = ($regionRadio === 'anyregion' && $regionId > 0)
    ? " AND cremi.CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . (int)$regionId . ")"
    : "";

// 4. QUERY CON FILTRO
$query = "
SELECT
    cremi.CCODCTA,
    cli.short_name,
    cremi.NCapDes,
    ffon.descripcion AS nombre_fondo
FROM cremcre_meta cremi
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
INNER JOIN cre_productos prod ON prod.id = cremi.CCODPRD
INNER JOIN ctb_fuente_fondos ffon ON ffon.id = prod.id_fondo
WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND cremi.DFecDsbls <= '$filtrofecha'
  $filfondo
  $filagencia
  $filasesor
  $filregion    -- ← FILTRO APLICADO
ORDER BY cremi.CCODCTA;
";

// 5. EJECUTAR Y GENERAR REPORTE
$database->openConnection();
$result = $database->getAllResults($query, []);
$database->closeConnection();

// Generar PDF/Excel con los datos filtrados
printpdf($result, ...);
?>
```

---

## Patrón de Implementación para Otros Reportes

### Checklist de Implementación

#### 1. **Modificar el Frontend** (archivo `.php` de vista)
```javascript
// Añadir campo de región al formulario
<div class="form-group">
    <label>
        <input type="radio" name="rregion" value="allregion" checked>
        Todas las Regiones
    </label>
    <label>
        <input type="radio" name="rregion" value="anyregion">
        Región:
        <select id="regionid" name="regionid">
            <option value="0">Seleccione...</option>
            <!-- Cargar desde tabla cre_regiones -->
        </select>
    </label>
</div>

// Modificar el payload al enviar
let payload = [
    [fecha],
    [agenciaId, fondoId, asesorId, regionId],  // ← Añadir regionId
    [radioAgencia, radioFondo, radioStatus, radioAsesor, radioRegion],  // ← Añadir radioRegion
    [usuario]
];
```

#### 2. **Modificar el Backend** (archivo PHP del reporte)

**a) Validación de parámetros:**
```php
$regionRadio = $radios[4] ?? null;
$regionId = isset($selects[3]) ? (int)$selects[3] : 0;

if ($regionRadio === 'anyregion' && $regionId <= 0) {
    echo json_encode(['status' => 0, 'mensaje' => 'Seleccionar Región']);
    exit;
}
```

**b) Construcción del filtro:**
```php
$filregion = ($regionRadio === 'anyregion' && $regionId > 0)
    ? " AND [tabla_principal].CODAgencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . (int)$regionId . ")"
    : "";
```
⚠️ **Nota:** Reemplazar `[tabla_principal]` con el alias correcto de la tabla en tu query (ej: `cremi`, `cre`, etc.)

**c) Inyectar en todos los queries:**
```php
$query1 = "SELECT ... WHERE ... $filregion ...";
$query2 = "SELECT ... WHERE ... $filregion ...";
$query3 = "SELECT ... WHERE ... $filregion ...";
```

#### 3. **Opcional: Mostrar "REGION: X" en el reporte**

Para PDF:
```php
if ($regionRadio === 'anyregion' && $regionId > 0) {
    // Obtener nombre de la región
    $regionData = $database->getAllResults(
        "SELECT nombre_region FROM cre_regiones WHERE id_region=?", 
        [$regionId]
    );
    $regionNombre = $regionData[0]['nombre_region'] ?? '';
    
    // Agregar al título del PDF
    $pdf->Cell(0, 5, 'REGION: ' . $regionNombre, 0, 1, 'C');
}
```

Para Excel:
```php
if ($regionRadio === 'anyregion' && $regionId > 0) {
    $regionData = $database->getAllResults(
        "SELECT nombre_region FROM cre_regiones WHERE id_region=?", 
        [$regionId]
    );
    $regionNombre = $regionData[0]['nombre_region'] ?? '';
    
    $activa->setCellValue("A3", "REGION: " . $regionNombre);
}
```

---

## Casos de Uso y Ejemplos

### Caso 1: Filtrar por Región Norte (id=1)
```
Payload: radios[4] = "anyregion", selects[3] = 1
Filtro SQL: AND cremi.CODAgencia IN (10, 11, 12)
Resultado: Solo muestra datos de agencias 10, 11, 12
```

### Caso 2: Todas las Regiones
```
Payload: radios[4] = "allregion"
Filtro SQL: (vacío)
Resultado: Muestra datos de todas las agencias
```

### Caso 3: Región + Agencia Específica
```
Payload: 
  - radios[0] = "anyofi", selects[0] = 10 (agencia)
  - radios[4] = "anyregion", selects[3] = 1 (región)
Filtro SQL: 
  AND cremi.CODAgencia = 10
  AND cremi.CODAgencia IN (10, 11, 12)
Resultado: Solo agencia 10 (intersección de ambos filtros)
```

---

## Reportes que Necesitan este Filtro

Basándose en el patrón implementado en `cartera_consolidada.php`, estos reportes deberían tener el mismo filtro:

1. ✅ `cartera_consolidada.php` (ya implementado)
2. ❓ `estadisticos.php`
3. ❓ `cartera_fondos.php`
4. ❓ `cartera_garantias.php`
5. ❓ Otros reportes de cartera/créditos

---

## Solución de Problemas Comunes

### Error: "Undefined array key 'CCODCTA'"
**Causa:** El query devuelve datos consolidados pero el código espera datos detallados.
**Solución:** Crear un query diferente para Excel que devuelva todos los campos necesarios:
```php
// Query detallado para Excel
$queryDetalle = "SELECT 
    cremi.CCODCTA,
    cli.codcliente,
    cli.short_name,
    cli.direccion,
    ...
FROM cremcre_meta cremi
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
WHERE ...
  $filregion
";
```

### Error: "Seleccionar Región" cuando no debería
**Causa:** El frontend está enviando `radios[4] = 'anyregion'` sin seleccionar región.
**Solución:** Validar en JavaScript antes de enviar:
```javascript
if (radios[4] === 'anyregion' && selects[3] === 0) {
    alert('Debe seleccionar una región');
    return false;
}
```

### Filtro no aplica en algún query
**Causa:** Olvidaste inyectar `$filregion` en ese query.
**Solución:** Buscar todos los queries con `grep` y verificar:
```bash
grep -n "FROM cremcre_meta" archivo.php
# Verificar que cada query tenga $filregion
```

---

## Notas Finales

1. **Seguridad:** El `(int)$regionId` previene inyección SQL al forzar tipo entero.
2. **Performance:** El subquery `IN (SELECT ...)` es eficiente para pocos registros. Si hay muchas agencias, considera un JOIN.
3. **Compatibilidad:** Añadir los parámetros **al final** del payload evita romper reportes existentes.
4. **Testing:** Probar con:
   - Todas las regiones
   - Región específica
   - Región + agencia
   - Región sin agencias asignadas (debe devolver vacío)

---

**Última actualización:** 16 de diciembre de 2025  
**Implementado en:** `cartera_consolidada.php`
