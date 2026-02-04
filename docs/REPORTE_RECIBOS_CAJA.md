# Reporte de Recibos de Caja

## ✅ REPORTE FUNCIONAL - Información Importante

**Campo "No. Boleta"**: La tabla `CREDKAR` **SÍ almacena** números de boleta en los campos:
- `CNUMING`: Número de boleta/recibo general
- `boletabanco`: Número de boleta bancaria específica

**Estado del Reporte**: ✅ Totalmente funcional

**Características**:
1. ✅ Agrupa recibos por grupos crediticios
2. ✅ Campo "No. Recibo Caja" se deja vacío (según especificación)
3. ✅ Genera formatos PDF y Excel
4. ✅ Obtiene datos de pagos del mes desde CREDKAR
5. ✅ Muestra número de boleta cuando está disponible

---

## Descripción General

Este reporte es revisado semanalmente por contabilidad y muestra el consolidado de **pagos de créditos** realizados durante un período específico (generalmente mensual).

La información proviene de las **boletas de depósito** que han sido aplicadas por la asistente administrativa en el sistema.

## Propósito del Reporte

1. **Control Contable**: Permite a contabilidad validar que todos los pagos recibidos estén correctamente registrados
2. **Detalle de Transacciones**: Muestra todas las transacciones del mes relacionadas con pagos de créditos
3. **Conciliación**: Facilita la conciliación entre boletas físicas de depósito y movimientos en el sistema
4. **Agrupación**: Los recibos se agrupan por **grupos crediticios**, facilitando la revisión por estructura organizacional

## Características Especiales

- ✅ **Agrupación por Grupos**: Los registros se ordenan primero por grupos crediticios, luego por individuales
- ✅ **Número de Recibo Vacío**: El campo "No. Recibo Caja IVA" se deja vacío intencionalmente (según especificaciones)
- ✅ **Formatos**: Disponible en PDF y Excel
- ✅ **Totalizadores**: Incluye totales de capital, intereses, mora, otros ingresos, impuestos

## Estructura de Datos

### Origen de los Datos

El reporte extrae información de las siguientes tablas:

#### 1. **cremcre_meta** (Información del Crédito)
- `CCODCTA`: Número de cuenta del crédito
- `CodCli`: Código del cliente
- `CCodGrupo`: Código del grupo (si aplica)
- `TipoEnti`: Tipo de entidad (G=Grupo, I=Individual)
- `NCapDes`: Monto desembolsado
- `DFecDsbls`: Fecha de desembolso
- `noPeriodo`: Plazo del crédito
- `NintApro`: Tasa de interés mensual
- `CESTADO`: Estado del crédito (F=Vigente, G=En gracia)

#### 2. **CREDKAR** (Movimientos de Pagos - Kardex de Créditos)
Esta es la **tabla principal** para el reporte de recibos:

**Campos de Pagos**:
- `KP`: Capital pagado
- `INTERES`: Intereses pagados
- `MORA`: Mora pagada
- `AHOPRG`: Ahorro programado
- `OTR`: Otros cargos
- `NMONTO`: Monto total del pago

**Campos de Control**:
- `DFECPRO`: Fecha del proceso/pago
- `DFECSIS`: Fecha sistema (timestamp)
- `CNROCUO`: Número correlativo de cuota
- `CTIPPAG`: Tipo de pago ('P'=Pago, 'D'=Desembolso)
- `CESTADO`: Estado del movimiento (''=Activo, 'X'=Anulado)

**Campos de Boleta/Recibo**:
- `CNUMING`: Número de boleta/recibo
- `boletabanco`: Número de boleta bancaria
- `DFECBANCO`: Fecha de la boleta
- `CBANCO`: Nombre del banco
- `CCODBANCO`: Cuenta bancaria

**Campos de Forma de Pago**:
- `FormPago`: Forma de pago ('1'=Efectivo, '0'=Banco)

**✅ IMPORTANTE**: 
- El campo `CNUMING` o `boletabanco` SÍ almacenan el número de boleta
- Usar `boletabanco` cuando el pago es por banco
- Usar `CNUMING` cuando es efectivo o referencia general

#### 3. **Cre_ppg** (Plan de Pagos)
- `dfecven`: Fecha de vencimiento de cuota
- `cflag`: Estado de la cuota (0=Pendiente, 1=Pagada)
- `ncapita`: Capital de la cuota
- `nintere`: Interés de la cuota

#### 4. **tb_cliente** (Información del Cliente)
- `short_name`: Nombre del titular
- `no_identifica`: DPI del cliente

#### 5. **tb_grupo** (Información de Grupos)
- `NombreGrupo`: Nombre del grupo crediticio

#### 6. **cre_productos** (Productos Crediticios)
- `descripcion`: Nombre del producto
- `id_fondo`: Fuente de fondos

---

## Proceso de Alimentación de Datos

### Flujo Normal de Operación

```
1. RECEPCIÓN DE PAGO
   ↓
2. EMISIÓN DE BOLETA DE DEPÓSITO (física)
   ↓
3. REGISTRO EN EL SISTEMA (Asistente Administrativa)
   - Ingresa a módulo de pagos
   - Selecciona el crédito
   - Registra monto de capital, intereses, mora
   - Ingresa número de boleta
   - Confirma el pago
   ↓
4. GENERACIÓN DE REGISTRO EN CREDKAR
   - Se crea automáticamente un registro con:
     * KP = capital pagado
     * interes = interés pagado
     * MORA = mora pagada
     * AHOPRG/OTR = otros conceptos
     * creferencia = número de boleta
     * dfecpro = fecha de proceso
     * ctippag = 'P' (pago)
     * cestado = '' (activo, no anulado)
   ↓
5. ACTUALIZACIÓN DE PLAN DE PAGOS (Cre_ppg)
   - Se marcan las cuotas como pagadas (cflag=1)
   ↓
6. REPORTE DE RECIBOS DE CAJA
   - Contabilidad genera el reporte
   - Revisa que todas las boletas estén aplicadas
   - Valida montos contra boletas físicas
```

---

## Campos del Reporte y Su Cálculo

### Columna: **No**
- **Origen**: Contador secuencial
- **Cálculo**: Auto-incrementado por el reporte

### Columna: **Préstamo**
- **Origen**: `cremcre_meta.CCODCTA` + año de desembolso
- **Formato**: `{cuenta}-{año}`
- **Ejemplo**: `1001-2024`

### Columna: **Nombre Grupo**
- **Origen**: `tb_grupo.NombreGrupo`
- **Valor**: `-` si es crédito individual

### Columna: **Titular Pagaré**
- **Origen**: `tb_cliente.short_name`

### Columna: **DPI**
- **Origen**: `tb_cliente.no_identifica`

### Columna: **Monto**
- **Origen**: `cremcre_meta.NCapDes`
- **Descripción**: Monto original desembolsado

### Columna: **Saldo**
- **Origen**: Calculado
- **Fórmula**: `NCapDes - SUM(CREDKAR.KP hasta la fecha)`
- **Descripción**: Saldo de capital pendiente

### Columna: **Plazo**
- **Origen**: `cremcre_meta.noPeriodo`
- **Descripción**: Número total de cuotas del crédito

### Columna: **Int. Mens**
- **Origen**: `cremcre_meta.NintApro`
- **Descripción**: Tasa de interés mensual

### Columna: **No. Falta**
- **Origen**: Calculado de `Cre_ppg`
- **Fórmula**: `COUNT(cuotas WHERE cflag=0 AND dfecven <= fecha_reporte)`
- **Descripción**: Número de cuotas pendientes de pago

### Columna: **Capital** 💰
- **Origen**: `SUM(CREDKAR.KP)` del mes
- **Filtro**: `dfecpro BETWEEN inicio_mes AND fin_mes`
- **Descripción**: **Capital pagado durante el mes**

### Columna: **Intereses** 💰
- **Origen**: `SUM(CREDKAR.interes)` del mes
- **Descripción**: **Intereses pagados durante el mes**

### Columna: **Ct. Mor** 💰
- **Origen**: `SUM(CREDKAR.MORA)` del mes
- **Descripción**: **Mora pagada durante el mes**

### Columna: **Otros Ing** 💰
- **Origen**: `SUM(CREDKAR.AHOPRG + CREDKAR.OTR)` del mes
- **Descripción**: **Otros ingresos del mes** (ahorro programado, cargos adicionales)

### Columna: **OTROS CARGOS** 💰
- **Origen**: Calculado o campo específico
- **Descripción**: Recargos por cartera castigada u otros conceptos especiales
- **Nota**: Actualmente se retorna 0 (ajustar según necesidad)

### Columna: **Ing Percib** 💰
- **Origen**: Calculado
- **Fórmula**: `Capital + Intereses + Mora + Otros Ing + Otros Cargos`
- **Descripción**: **Total de ingresos percibidos en el pago**

### Columna: **Mto. Depos** 💰
- **Origen**: Igual a Ing Percib
- **Descripción**: Monto total depositado (debe coincidir con boleta)

### Columna: **Boleta**
- **Origen**: `CREDKAR.boletabanco` o `CREDKAR.CNUMING`
- **Prioridad**: Se usa `boletabanco` si está lleno, sino `CNUMING`
- **Descripción**: Número de boleta de depósito bancario o recibo de pago
- **Nota**: Si el pago es en efectivo, generalmente se usa `CNUMING`; si es por banco, se usa `boletabanco`

### Columna: **Fecha**
- **Origen**: `CREDKAR.dfecpro`
- **Descripción**: Fecha en que se procesó el pago

### Columna: **Recibo**
- **Origen**: Vacío intencionalmente
- **Descripción**: Campo para número de recibo de caja (no se llena automáticamente)
- **Uso**: Contabilidad puede llenarlo manualmente si es necesario

### Columna: **Impuesto** 💰
- **Origen**: Calculado
- **Fórmula**: `Ing Percib × 0.10`
- **Descripción**: Impuesto a pagar (10% sobre ingresos)

### Columna: **% peso**
- **Origen**: Constante
- **Valor**: `10.00`
- **Descripción**: Porcentaje del peso del impuesto

---

## Consulta SQL Principal

```sql
SELECT 
    -- Identificación del crédito
    cremi.CCODCTA AS cuenta,
    cremi.CCodGrupo,
    cremi.TipoEnti,
    IFNULL(grupo.NombreGrupo, '-') AS nombre_grupo,
    cli.short_name AS titular_pagare,
    cli.no_identifica AS dpi,
    
    -- Datos del crédito
    cremi.NCapDes AS monto,
    cremi.noPeriodo AS plazo,
    cremi.NintApro AS interes_mensual,
    
    -- Saldo actual
    GREATEST(0, cremi.NCapDes - IFNULL(kar_total.sum_KP, 0)) AS saldo,
    
    -- Cuotas pendientes
    IFNULL(ppg_pend.cuotas_pendientes, 0) AS no_falta_saldo,
    
    -- PAGOS DEL MES (componentes del recibo)
    IFNULL(kar_mes.sum_KP, 0) AS capital,
    IFNULL(kar_mes.sum_interes, 0) AS intereses,
    IFNULL(kar_mes.sum_MORA, 0) AS costo_mora,
    IFNULL(kar_mes.sum_AHOPRG_OTR, 0) AS otros_ingresos,
    
    -- Total del recibo
    (IFNULL(kar_mes.sum_KP, 0) + IFNULL(kar_mes.sum_interes, 0) + 
     IFNULL(kar_mes.sum_MORA, 0) + IFNULL(kar_mes.sum_AHOPRG_OTR, 0)) AS ingresos_percibidos,
    
    -- Información de la boleta
    IFNULL(kar_mes.max_referencia, '-') AS no_boleta_pago,
    IFNULL(kar_mes.max_fecha, '-') AS fecha

FROM cremcre_meta cremi
INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
LEFT JOIN tb_grupo grupo ON grupo.id_grupos = cremi.CCodGrupo

-- Pagos del mes específico
LEFT JOIN (
    SELECT 
        ccodcta,
        SUM(KP) AS sum_KP,
        SUM(interes) AS sum_interes,
        SUM(MORA) AS sum_MORA,
        SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR,
        MAX(creferencia) AS max_referencia,
        MAX(dfecpro) AS max_fecha
    FROM CREDKAR
    WHERE dfecpro BETWEEN '2024-01-01' AND '2024-01-31'  -- MES A REPORTAR
      AND cestado != 'X'  -- Excluir anulados
      AND ctippag = 'P'   -- Solo pagos
    GROUP BY ccodcta
) AS kar_mes ON kar_mes.ccodcta = cremi.CCODCTA

WHERE (cremi.CESTADO='F' OR cremi.CESTADO='G')
  AND kar_mes.sum_KP IS NOT NULL  -- Solo créditos con pagos en el mes

ORDER BY 
    CASE WHEN cremi.TipoEnti = 'G' THEN 0 ELSE 1 END,  -- Grupos primero
    cremi.CCodGrupo,
    cremi.CodCli
```

---

## Filtros Disponibles

### 1. **Agencia** (Radio: ragencia)
- `allg`: Todas las agencias (consolidado)
- `anyofi`: Agencia específica → Requiere seleccionar en `selects[0]`

### 2. **Fuente de Fondos** (Radio: rfondos)
- `allf`: Todas las fuentes de fondos
- `anyf`: Fuente específica → Requiere seleccionar en `selects[1]`

### 3. **Tipo de Entidad** (Radio: allstatus)
- `allstatus`: Grupos e individuales
- `G`: Solo grupos
- `I`: Solo individuales

### 4. **Asesor** (Radio: anyasesor)
- `allasesor`: Todos los asesores
- `anyasesor`: Asesor específico → Requiere seleccionar en `selects[2]`

### 5. **Fecha** (Input: ffin)
- Fecha final del período (generalmente último día del mes)
- El reporte toma todo el mes de esa fecha

---

## Cómo Alimentar Correctamente la Base de Datos

### ⚠️ **IMPORTANTE**: Registro de Pagos

Para que los recibos aparezcan en el reporte, la asistente administrativa debe:

#### 1. **Al recibir un pago**:
   ```
   a) Emitir boleta de depósito física
   b) Anotar número de boleta
   c) Registrar fecha de depósito
   d) Separar montos: capital, interés, mora, otros
   ```

#### 2. **Ingresar al sistema**:
   - Módulo: **Pagos de Créditos** o **Recepción de Pagos**
   - Buscar el crédito por número de cuenta o nombre de cliente
   - Validar que sea el crédito correcto

#### 3. **Completar formulario de pago**:
   ```php
   Fecha de Pago: [____/____/____]  // Fecha del depósito
   No. Boleta:    [____________]     // Referencia bancaria
   Capital:       [Q _______.__]     // Abono a capital
   Interés:       [Q _______.__]     // Interés corriente
   Mora:          [Q _______.__]     // Interés moratorio
   Otros:         [Q _______.__]     // Otros cargos/ahorros
   ```

#### 4. **Al confirmar el pago, el sistema debe**:
   
   **Insertar en CREDKAR** (Nombres de columnas en MAYÚSCULAS):
   ```sql
   INSERT INTO CREDKAR (
       CCODCTA,      -- Número de cuenta del crédito
       DFECPRO,      -- Fecha del proceso/pago
       DFECSIS,      -- Fecha sistema (NOW())
       CNROCUO,      -- Número de cuota (correlativo)
       NMONTO,       -- Monto total del pago
       CNUMING,      -- Número de boleta/recibo
       CCONCEP,      -- Concepto del pago
       KP,           -- Capital pagado
       INTERES,      -- Interés pagado
       MORA,         -- Mora pagada
       AHOPRG,       -- Ahorro programado
       OTR,          -- Otros cargos
       CCODINS,      -- Código institución
       CCODOFI,      -- Código oficina
       CCODUSU,      -- Usuario que registra
       CTIPPAG,      -- 'P' para pago normal
       CMONEDA,      -- Código moneda
       FormPago,     -- '1' = Efectivo, '0' = Banco
       DFECBANCO,    -- Fecha de boleta (si es banco)
       boletabanco,  -- Número de boleta bancaria
       CBANCO,       -- Nombre del banco
       CCODBANCO,    -- Número de cuenta bancaria
       CESTADO,      -- '' (vacío = activo, 'X' = anulado)
       DFECMOD       -- Fecha modificación
   ) VALUES (
       '1001',
       '2024-01-15',
       NOW(),
       1,
       3158.33,      -- Total: capital + interés + mora + otros
       'BOL-2024-001',
       'Pago de cuota mensual',
       2083.33,
       875.00,
       150.00,
       0.00,
       50.00,
       '001',        -- Código institución
       '001',        -- Código oficina
       '123',        -- ID del usuario
       'P',
       'GTQ',
       '1',          -- Efectivo
       NULL,
       '',
       '',
       '',
       '',           -- Estado activo
       CURDATE()
   );
   ```
   
   **✅ VENTAJA**: CREDKAR SÍ tiene campos `CNUMING` y `boletabanco` para almacenar números de boleta.
   ```

   **Actualizar Cre_ppg**:
   ```sql
   -- Marcar cuotas como pagadas
   UPDATE Cre_ppg
   SET cflag = 1
   WHERE ccodcta = '1001'
     AND dfecven <= '2024-01-15'
     AND cflag = 0
   LIMIT 1;  -- O las cuotas que corresponda según el monto
   ```

#### 5. **Validaciones importantes**:
   - ✅ La suma `KP + INTERES + MORA + AHOPRG + OTR` debe coincidir con `NMONTO`
   - ✅ El estado debe ser '' (no 'X')
   - ✅ El tipo de pago debe ser 'P' (no 'D' de desembolso)
   - ✅ La fecha debe estar dentro del mes a reportar
   - ✅ Validar que `CNUMING` o `boletabanco` estén llenos para trazabilidad
   
**Recomendación**: Asegurar que al registrar pagos siempre se llene:
- `CNUMING`: Para pagos en efectivo (número de recibo interno)
- `boletabanco`: Para pagos bancarios (número de boleta del banco)
- `DFECBANCO`: Fecha de la boleta bancaria
- `CBANCO` y `CCODBANCO`: Información del banco si aplica

---

## Validaciones del Reporte

El reporte solo incluye registros que cumplan:

1. ✅ Crédito en estado vigente: `CESTADO IN ('F', 'G')`
2. ✅ Pago registrado en el mes: `dfecpro BETWEEN inicio_mes AND fin_mes`
3. ✅ Pago no anulado: `cestado != 'X'`
4. ✅ Tipo de pago normal: `ctippag = 'P'`
5. ✅ Crédito desembolsado antes o durante el período: `DFecDsbls <= fecha_filtro`

---

## Ejemplo de Registro Completo

### Cliente Individual

**Datos del crédito** (cremcre_meta):
- CCODCTA: `1001`
- CodCli: `500`
- TipoEnti: `I` (Individual)
- NCapDes: `50000.00`
- DFecDsbls: `2023-12-01`
- noPeriodo: `24`
- NintApro: `2.5`

**Pago del mes** (CREDKAR):
```sql
INSERT INTO CREDKAR VALUES (
    '1001',           -- ccodcta
    '2024-01-15',     -- dfecpro
    2083.33,          -- KP (capital)
    875.00,           -- interes
    150.00,           -- MORA
    0.00,             -- AHOPRG
    50.00,            -- OTR
    'BOL-2024-001',   -- creferencia
    'P',              -- ctippag
    '',               -- cestado (activo)
    123               -- cusuario
);
```

**Resultado en el reporte**:
| No | Préstamo  | Titular          | Capital | Intereses | Mora   | Otros | Ing Percib | Boleta      |
|----|-----------|------------------|---------|-----------|--------|-------|------------|-------------|
| 1  | 1001-2023 | MARIA LOPEZ      | 2083.33 | 875.00    | 150.00 | 50.00 | 3158.33    | BOL-2024-001|

---

## Troubleshooting

### ❌ **No aparecen registros en el reporte**

**Posibles causas**:
1. No hay pagos registrados en el mes seleccionado
2. Los pagos están anulados (`cestado = 'X'`)
3. El tipo de pago no es 'P'
4. La fecha del pago está fuera del rango del mes

**Solución**:
```sql
-- Verificar pagos del mes
SELECT * FROM CREDKAR
WHERE dfecpro BETWEEN '2024-01-01' AND '2024-01-31'
  AND cestado != 'X'
  AND ctippag = 'P';
```

### ❌ **Montos no coinciden con boletas**

**Posibles causas**:
1. Distribución incorrecta entre capital, interés, mora
2. Pago registrado con monto equivocado
3. Múltiples pagos del mismo crédito en el mes

**Solución**:
```sql
-- Ver detalle de pagos por crédito
SELECT 
    ccodcta,
    dfecpro,
    KP AS capital,
    interes,
    MORA,
    (KP + interes + MORA + AHOPRG + OTR) AS total,
    creferencia
FROM CREDKAR
WHERE ccodcta = '1001'
  AND dfecpro BETWEEN '2024-01-01' AND '2024-01-31'
  AND cestado != 'X';
```

### ❌ **No. Boleta aparece vacío**

**Causa**: Los campos `CNUMING` y `boletabanco` están vacíos en CREDKAR

**Verificar**:
```sql
SELECT 
    CCODCTA, DFECPRO, NMONTO, 
    CNUMING, boletabanco, FormPago
FROM CREDKAR
WHERE CTIPPAG = 'P' 
  AND CESTADO != 'X'
  AND MONTH(DFECPRO) = MONTH(CURDATE())
  AND (CNUMING IS NULL OR CNUMING = '')
  AND (boletabanco IS NULL OR boletabanco = '')
ORDER BY DFECPRO DESC;
```

**Soluciones**:

**Opción 1 - Completar datos faltantes**:
```sql
-- Para pagos en efectivo
UPDATE CREDKAR 
SET CNUMING = 'REC-2024-001'
WHERE CCODCTA = '12345' 
  AND DFECPRO = '2024-01-15'
  AND CTIPPAG = 'P'
  AND FormPago = '1';

-- Para pagos bancarios
UPDATE CREDKAR 
SET boletabanco = 'BOL-BANCO-001',
    DFECBANCO = '2024-01-15',
    CBANCO = 'BANRURAL',
    CCODBANCO = '1234567890'
WHERE CCODCTA = '12345' 
  AND DFECPRO = '2024-01-15'
  AND CTIPPAG = 'P'
  AND FormPago = '0';
```

**Opción 2 - Modificar módulo de pagos**:
Asegurar que el formulario de registro de pagos tenga campos obligatorios:
- Número de recibo/boleta (según forma de pago)
- Banco y cuenta (si es pago bancario)
- Validar que estos campos no queden vacíos antes de guardar

---

## Frecuencia de Generación

- **Semanal**: Contabilidad revisa avances durante el mes
- **Mensual**: Reporte oficial al cierre del mes
- **Bajo demanda**: Cuando se necesita verificar transacciones específicas

---

## Usuarios del Reporte

1. **Contabilidad**: Validación y conciliación de ingresos
2. **Gerencia**: Supervisión de recuperación de cartera
3. **Auditoría**: Verificación de transacciones
4. **Asistente Administrativa**: Control de aplicación de boletas

---

## Notas Adicionales

- 📌 El reporte agrupa primero por **grupos**, luego individuales
- 📌 El campo "No. Recibo Caja IVA" se deja vacío intencionalmente
- 📌 Si un crédito tuvo múltiples pagos en el mes, se suman en un solo registro
- 📌 El impuesto se calcula automáticamente al 10%
- 📌 Los datos deben coincidir exactamente con las boletas físicas

---

## Mejoras Futuras Recomendadas

### 1. **Validar Llenado de Campos de Boleta** ✅

CREDKAR ya tiene los campos necesarios, solo falta validar que se llenen:

**Modificar módulo de registro de pagos**:
```php
// Validación en el formulario de pagos
if ($formaPago == 'banco') {
    if (empty($boletabanco) || empty($fechaBoleta) || empty($banco)) {
        throw new Exception('Debe ingresar número de boleta, fecha y banco para pagos bancarios');
    }
} else {
    if (empty($numeroRecibo)) {
        throw new Exception('Debe ingresar número de recibo para pagos en efectivo');
    }
}
```

**Campos a validar según forma de pago**:
- **Efectivo** (`FormPago = '1'`): 
  - `CNUMING` (obligatorio)
- **Banco** (`FormPago = '0'`):
  - `boletabanco` (obligatorio)
  - `DFECBANCO` (obligatorio)
  - `CBANCO` (obligatorio)
  - `CCODBANCO` (opcional pero recomendado)

### 2. **Agregar Índices para Búsquedas Rápidas** 🔧

```sql
-- Mejorar performance de consultas por boleta
ALTER TABLE CREDKAR ADD INDEX idx_cnuming (CNUMING);
ALTER TABLE CREDKAR ADD INDEX idx_boletabanco (boletabanco);
ALTER TABLE CREDKAR ADD INDEX idx_dfecpro_ctippag (DFECPRO, CTIPPAG);

-- Índice compuesto para búsquedas del reporte
ALTER TABLE CREDKAR ADD INDEX idx_reporte_recibos (CTIPPAG, CESTADO, DFECPRO);
```

### 3. **Procedimiento para Detectar Pagos Sin Boleta** 🔍

Crear tabla de vinculación entre pagos y depósitos bancarios:

```sql
CREATE TABLE credkar_depositos_bancarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    id_credkar INT NOT NULL COMMENT 'Puede ser NULL si es búsqueda manual',
    cuenta_credito VARCHAR(20) NOT NULL,
    no_boleta VARCHAR(50) NOT NULL,
    banco VARCHAR(100),
    numero_cuenta VARCHAR(50),
    fecha_deposito DATE NOT NULL,
    monto_depositado DECIMAL(12,2) NOT NULL,
    imagen_boleta VARCHAR(255) COMMENT 'Ruta a escaneo de boleta',
    registrado_por INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cuenta_credito) REFERENCES cremcre_meta(CCODCTA),
    FOREIGN KEY (registrado_por) REFERENCES tb_usuario(id_usu),
    UNIQUE KEY uk_boleta (no_boleta),
    INDEX idx_cuenta_fecha (cuenta_credito, fecha_deposito)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Query del Reporte Mejorado**:
```sql
LEFT JOIN credkar_depositos_bancarios cdb ON cdb.cuenta_credito = cremi.CCODCTA
    AND cdb.fecha_deposito = kar_mes.max_fecha
```

### 3. **Validación Automática de Conciliación** ✅

Agregar procedimiento almacenado para validar pagos vs boletas:

```sql
DELIMITER $$
CREATE PROCEDURE sp_validar_recibos_mes(IN p_fecha_fin DATE)
BEGIN
    -- Listar pagos sin boleta
    SELECT 
        cremi.CCODCTA AS cuenta,
        cli.short_name AS cliente,
        kar.dfecpro AS fecha_pago,
        kar.NMONTO AS monto,
        'SIN BOLETA' AS observacion
    FROM CREDKAR kar
    INNER JOIN cremcre_meta cremi ON cremi.CCODCTA = kar.ccodcta
    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cremi.CodCli
    WHERE kar.dfecpro BETWEEN DATE_FORMAT(p_fecha_fin, '%Y-%m-01') AND p_fecha_fin
      AND kar.ctippag = 'P'
      AND kar.cestado != 'X'
      AND (kar.no_boleta IS NULL OR kar.no_boleta = '')
    ORDER BY kar.dfecpro;
END$$
DELIMITER ;

-- Uso:
CALL sp_validar_recibos_mes('2024-01-31');
```

### 4. **Dashboard de Recibos Pendientes** 📊

Crear vista para monitoreo en tiempo real:

```sql
CREATE VIEW vista_recibos_pendientes AS
SELECT 
    DATE_FORMAT(kar.dfecpro, '%Y-%m') AS mes,
    COUNT(*) AS total_pagos,
    SUM(CASE WHEN kar.no_boleta IS NULL OR kar.no_boleta = '' THEN 1 ELSE 0 END) AS sin_boleta,
    SUM(CASE WHEN kar.no_recibo_caja IS NULL OR kar.no_recibo_caja = '' THEN 1 ELSE 0 END) AS sin_recibo,
    SUM(kar.NMONTO) AS monto_total,
    ROUND(SUM(CASE WHEN kar.no_boleta IS NULL OR kar.no_boleta = '' THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) AS porcentaje_sin_boleta
FROM CREDKAR kar
WHERE kar.ctippag = 'P' AND kar.cestado != 'X'
  AND kar.dfecpro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
GROUP BY DATE_FORMAT(kar.dfecpro, '%Y-%m')
ORDER BY mes DESC;
```

---

## Última Actualización
- **Fecha**: 2024-01-15
- **Versión**: 1.1
- **Cambios**:
  - ✅ Identificada limitación de campo `no_boleta` en CREDKAR
  - ✅ Ajustada consulta SQL para funcionar sin campo `creferencia`
  - ✅ Documentadas 4 soluciones para el problema de boletas
  - ✅ Agregados scripts SQL para mejoras futuras
- **Autor**: Sistema MicroSystemPlus
