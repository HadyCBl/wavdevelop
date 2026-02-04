# 📋 Proceso Completo de Créditos - Sistema MicroSystemPlus

## 📑 Índice

1. [Descripción General](#descripción-general)
2. [Ciclo de Vida de un Crédito](#ciclo-de-vida-de-un-crédito)
3. [Tablas Principales](#tablas-principales)
4. [Tablas Secundarias y Relacionadas](#tablas-secundarias-y-relacionadas)
5. [Procesos del Sistema](#procesos-del-sistema)
6. [Interfaces y Vistas](#interfaces-y-vistas)
7. [Modelos y Controladores](#modelos-y-controladores)
8. [APIs y Endpoints](#apis-y-endpoints)
9. [Procedimientos Almacenados](#procedimientos-almacenados)
10. [Flujos de Datos Críticos](#flujos-de-datos-críticos)
11. [Extracción y Abstracción de Información](#extracción-y-abstracción-de-información)

---

## 📖 Descripción General

El sistema de créditos de MicroSystemPlus es un módulo integral que gestiona todo el ciclo de vida de los préstamos, desde la solicitud hasta la liquidación final. El sistema soporta créditos individuales y grupales, con diferentes modalidades de pago y desembolso.

### Características Principales

- **Créditos Individuales**: Préstamos otorgados a clientes individuales
- **Créditos Grupales**: Préstamos otorgados a grupos solidarios
- **Múltiples Productos**: Diferentes líneas de crédito con tasas y condiciones específicas
- **Planes de Pago Flexibles**: Diarios, semanales, quincenales, mensuales
- **Gestión de Mora**: Cálculo automático y opción de perdón autorizado
- **Integración Contable**: Movimientos automáticos en el sistema contable
- **Reportes Complejos**: Múltiples reportes para análisis y control

---

## 🔄 Ciclo de Vida de un Crédito

```
┌─────────────────────────────────────────────────────────────────┐
│                    CICLO DE VIDA DEL CRÉDITO                    │
└─────────────────────────────────────────────────────────────────┘

1. SOLICITUD (Estado: 'A')
   ├─ Creación de solicitud
   ├─ Asignación de analista
   ├─ Vinculación de garantías
   └─ Registro en cremcre_meta con Cestado='A'

2. ANÁLISIS (Estado: 'A')
   ├─ Evaluación crediticia
   ├─ Aprobación/Rechazo
   └─ Actualización de monto sugerido

3. APROBACIÓN (Estado: 'E')
   ├─ Dictamen de aprobación
   ├─ Generación de plan de pagos (Cre_ppg)
   └─ Actualización cremcre_meta con Cestado='E'

4. DESEMBOLSO (Estado: 'F')
   ├─ Registro de desembolso en CREDKAR (CTIPPAG='D')
   ├─ Movimientos contables
   ├─ Actualización de saldos
   └─ Cambio de estado a Cestado='F' (Vigente)

5. PAGOS (Estado: 'F')
   ├─ Registro de pagos en CREDKAR (CTIPPAG='P')
   ├─ Actualización de plan de pagos
   ├─ Cálculo de mora
   └─ Movimientos contables

6. LIQUIDACIÓN (Estado: 'L')
   └─ Crédito completamente pagado

7. CANCELADO/ANULADO (Estado: 'X')
   └─ Crédito cancelado o anulado
```

### Estados del Crédito (Cestado)

| Estado | Descripción | Significado |
|--------|-------------|-------------|
| `A` | Aprobado | Solicitud aprobada, pendiente de desembolso |
| `E` | En Espera | Aprobado y listo para desembolso |
| `F` | Vigente | Crédito desembolsado y activo |
| `G` | En Gracia | Período de gracia activo |
| `L` | Liquidado | Crédito completamente pagado |
| `X` | Anulado | Crédito cancelado/anulado |

---

## 🗄️ Tablas Principales

### 1. `cremcre_meta` - Información General del Crédito

**Descripción**: Tabla central que almacena toda la información principal de cada crédito.

**Campos Críticos**:

| Campo | Tipo | Descripción | Importancia |
|-------|------|-------------|-------------|
| `CCODCTA` | VARCHAR(20) | **Código único del crédito** | ⭐⭐⭐ **PRIMARY KEY** |
| `CodCli` | VARCHAR(20) | Código del cliente | ⭐⭐⭐ Relación con tb_cliente |
| `CCodGrupo` | VARCHAR(20) | Código del grupo (si aplica) | ⭐⭐ Relación con tb_grupo |
| `TipoEnti` | VARCHAR(5) | Tipo: 'INDI' o 'GRUP' | ⭐⭐⭐ Define tipo de crédito |
| `CCODPRD` | INT | ID del producto de crédito | ⭐⭐⭐ Relación con cre_productos |
| `MonSug` | DECIMAL(20,2) | Monto sugerido/aprobado | ⭐⭐⭐ Monto del crédito |
| `NCapDes` | DECIMAL(20,2) | Capital desembolsado | ⭐⭐⭐ Capital efectivo |
| `DFecDsbls` | DATE | Fecha de desembolso | ⭐⭐⭐ Fecha crítica |
| `NintApro` | DECIMAL(10,4) | Tasa de interés aprobada | ⭐⭐⭐ Tasa del crédito |
| `noPeriodo` | INT | Número de cuotas/plazo | ⭐⭐⭐ Plazo del crédito |
| `Cestado` | VARCHAR(1) | Estado del crédito | ⭐⭐⭐ Control de flujo |
| `Dictamen` | VARCHAR(50) | Número de dictamen | ⭐⭐ Referencia legal |
| `id_fuente` | INT | Fuente de fondos | ⭐⭐ Relación con fondos |
| `Cestado` | VARCHAR(1) | Estado: A/E/F/G/L/X | ⭐⭐⭐ |

**Relaciones**:
- `CodCli` → `tb_cliente.idcod_cliente`
- `CCodGrupo` → `tb_grupo.id_grupos`
- `CCODPRD` → `cre_productos.id`
- `id_fuente` → `ctb_fuente_fondo.id`

**Uso Crítico**: 
- Consulta principal para reportes de cartera
- Validación de estado antes de operaciones
- Cálculo de saldos pendientes

---

### 2. `CREDKAR` - Kardex de Movimientos del Crédito

**Descripción**: Registra TODOS los movimientos financieros del crédito (pagos y desembolsos).

**Campos Críticos**:

| Campo | Tipo | Descripción | Importancia |
|-------|------|-------------|-------------|
| `CODKAR` | INT | ID único del movimiento | ⭐⭐⭐ PRIMARY KEY |
| `CCODCTA` | VARCHAR(20) | Código del crédito | ⭐⭐⭐ FOREIGN KEY |
| `DFECPRO` | DATE | Fecha del proceso | ⭐⭐⭐ Fecha del movimiento |
| `CNROCUO` | INT | Número correlativo de cuota | ⭐⭐⭐ Secuencia de pagos |
| `NMONTO` | DECIMAL(20,2) | Monto total del movimiento | ⭐⭐⭐ |
| `KP` | DECIMAL(20,2) | **Capital pagado** | ⭐⭐⭐ Abono a capital |
| `INTERES` | DECIMAL(20,2) | **Intereses pagados** | ⭐⭐⭐ Pago de intereses |
| `MORA` | DECIMAL(20,2) | **Mora pagada** | ⭐⭐⭐ Pago de mora |
| `AHORGP` | DECIMAL(20,2) | Ahorro programado | ⭐⭐ |
| `OTR` | DECIMAL(20,2) | Otros conceptos | ⭐⭐ |
| `CTIPPAG` | VARCHAR(3) | **Tipo: 'P'=Pago, 'D'=Desembolso** | ⭐⭐⭐ **CRÍTICO** |
| `CESTADO` | VARCHAR(1) | Estado: '1'=Activo, 'X'=Anulado | ⭐⭐⭐ Control |
| `CNUMING` | VARCHAR(20) | Número de recibo/boleta | ⭐⭐ Referencia |
| `boletabanco` | VARCHAR(100) | Boleta bancaria | ⭐⭐ Para pagos bancarios |
| `FormPago` | VARCHAR(3) | Forma de pago | ⭐⭐ 1=Efectivo, 2=Banco |
| `CBANCO` | VARCHAR(20) | Banco (si aplica) | ⭐ |
| `CCODBANCO` | VARCHAR(18) | Cuenta bancaria | ⭐ |
| `DFECBANCO` | DATE | Fecha boleta bancaria | ⭐ |
| `CCONCEP` | TEXT | Concepto del movimiento | ⭐⭐ |
| `CCODUSU` | VARCHAR(4) | Usuario que registró | ⭐⭐ Auditoría |
| `CCODOFI` | VARCHAR(3) | Oficina/Agencia | ⭐⭐ |

**Tipos de Movimiento (CTIPPAG)**:
- `P` = Pago (abono al crédito)
- `D` = Desembolso (entrega del crédito)

**Estados (CESTADO)**:
- `1` o `''` = Activo
- `X` = Anulado/Reversado

**Uso Crítico**:
- Cálculo de saldos: `Capital Desembolsado - SUM(KP donde CTIPPAG='P' y CESTADO!='X')`
- Historial de pagos del cliente
- Reportes de ingresos
- Trazabilidad de transacciones

---

### 3. `Cre_ppg` - Plan de Pagos

**Descripción**: Almacena el plan de pagos (amortización) del crédito. Cada fila representa una cuota.

**Campos Críticos**:

| Campo | Tipo | Descripción | Importancia |
|-------|------|-------------|-------------|
| `Id_ppg` | INT | ID único de la cuota | ⭐⭐⭐ PRIMARY KEY |
| `ccodcta` | VARCHAR(20) | Código del crédito | ⭐⭐⭐ FOREIGN KEY |
| `cnrocuo` | INT | Número de cuota | ⭐⭐⭐ Orden de cuotas |
| `dfecven` | DATE | Fecha de vencimiento | ⭐⭐⭐ Fecha límite |
| `dfecpag` | DATE | Fecha de pago (si pagó) | ⭐⭐ |
| `ncapita` | DECIMAL(20,2) | Capital de la cuota | ⭐⭐⭐ Monto capital |
| `nintere` | DECIMAL(20,2) | Interés de la cuota | ⭐⭐⭐ Monto interés |
| `ncappag` | DECIMAL(20,2) | Capital pagado | ⭐⭐⭐ Acumulado pagado |
| `nintpag` | DECIMAL(20,2) | Interés pagado | ⭐⭐⭐ Acumulado pagado |
| `nmorpag` | DECIMAL(20,2) | Mora pagada | ⭐⭐ Calculado automáticamente |
| `cestado` | VARCHAR(1) | Estado: 'P'=Pagada, 'X'=Pendiente | ⭐⭐⭐ Control |
| `cflag` | VARCHAR(1) | Flag adicional | ⭐ |
| `diasatraso` | INT | Días de atraso | ⭐⭐ Para cálculo de mora |

**Estados de Cuota (cestado)**:
- `P` = Pagada completamente
- `X` o `''` = Pendiente

**Uso Crítico**:
- Generación de tabla de amortización
- Cálculo de cuotas vencidas
- Cálculo de mora por días de atraso
- Reportes de cartera vencida
- Actualización automática con cada pago

**Procedimientos Relacionados**:
- `update_ppg_account(ccodcta)`: Actualiza pagos aplicados a cada cuota
- `calculo_mora(ccodcta)`: Calcula mora de cuotas vencidas

---

### 4. `tb_cliente` - Información del Cliente

**Descripción**: Datos maestros de los clientes del sistema.

**Campos Críticos para Créditos**:

| Campo | Tipo | Descripción | Importancia |
|-------|------|-------------|-------------|
| `idcod_cliente` | VARCHAR(20) | **Código único del cliente** | ⭐⭐⭐ PRIMARY KEY |
| `short_name` | VARCHAR(255) | Nombre completo | ⭐⭐⭐ |
| `no_identifica` | VARCHAR(50) | DPI/NIT | ⭐⭐⭐ Identificación |
| `Direccion` | TEXT | Dirección | ⭐⭐ |
| `tel_no1` | VARCHAR(20) | Teléfono | ⭐⭐ |
| `ciclo` | INT | Ciclo crediticio | ⭐⭐ Para créditos grupales |
| `estado` | INT | Estado: 1=Activo | ⭐⭐⭐ |

**Uso Crítico**:
- Relación con `cremcre_meta.CodCli`
- Reportes de cartera por cliente
- Validación antes de crear crédito

---

### 5. `tb_grupo` - Grupos Solidarios

**Descripción**: Información de grupos para créditos grupales.

**Campos Críticos**:

| Campo | Tipo | Descripción | Importancia |
|-------|------|-------------|-------------|
| `id_grupos` | INT | ID único del grupo | ⭐⭐⭐ PRIMARY KEY |
| `codigo_grupo` | INT | Código del grupo | ⭐⭐ |
| `NombreGrupo` | VARCHAR(255) | Nombre del grupo | ⭐⭐⭐ |
| `estado` | INT | Estado: 1=Activo | ⭐⭐⭐ |
| `estadoGrupo` | VARCHAR(1) | Estado: 'A'=Abierto, 'C'=Cerrado | ⭐⭐⭐ |

**Uso Crítico**:
- Relación con `cremcre_meta.CCodGrupo`
- Créditos grupales
- Validación de estado antes de desembolso

---

### 6. `cre_productos` - Productos de Crédito

**Descripción**: Catálogo de productos/líneas de crédito disponibles.

**Campos Críticos**:

| Campo | Tipo | Descripción | Importancia |
|-------|------|-------------|-------------|
| `id` | INT | ID único del producto | ⭐⭐⭐ PRIMARY KEY |
| `codigo` | VARCHAR(50) | Código del producto | ⭐⭐ |
| `nombre` | VARCHAR(255) | Nombre del producto | ⭐⭐⭐ |
| `id_cuenta_capital` | INT | Cuenta contable capital | ⭐⭐⭐ Para contabilidad |
| `id_cuenta_interes` | INT | Cuenta contable intereses | ⭐⭐⭐ Para contabilidad |
| `id_cuenta_mora` | INT | Cuenta contable mora | ⭐⭐ Para contabilidad |
| `tasa` | DECIMAL(10,4) | Tasa de interés | ⭐⭐⭐ |
| `monto_maximo` | DECIMAL(20,2) | Monto máximo | ⭐⭐ |

**Uso Crítico**:
- Relación con `cremcre_meta.CCODPRD`
- Validación de montos máximos
- Configuración de cuentas contables

---

## 🔗 Tablas Secundarias y Relacionadas

### Tablas de Detalle y Soporte

#### `credkar_detalle`
**Descripción**: Detalle de otros pagos/gastos vinculados a un movimiento de CREDKAR.

| Campo | Descripción |
|-------|-------------|
| `id_credkar` | FK a CREDKAR.CODKAR |
| `id_concepto` | ID del concepto/gasto |
| `monto` | Monto del concepto |

**Uso**: Almacena desglose de "otros pagos" en un pago de crédito.

---

#### `cre_ppg_log`
**Descripción**: Bitácora de mora perdonada.

| Campo | Descripción |
|-------|-------------|
| `no_cuota` | Número de cuota |
| `ccodcta` | Código del crédito |
| `credkar_id` | FK a CREDKAR |
| `morapag` | Mora que se perdonó |
| `tipo_autorizacion` | Tipo de autorización |
| `autorizado_por` | Usuario que autorizó |

**Uso**: Auditoría de mora perdonada.

---

### Tablas Contables

#### `ctb_diario`
**Descripción**: Encabezado de partidas contables.

| Campo | Descripción |
|-------|-------------|
| `numcom` | Número de partida |
| `id_ctb_tipopoliza` | Tipo de póliza (1=Efectivo, 11=Banco) |
| `numdoc` | Número de documento |
| `glosa` | Descripción |
| `fecdoc` | Fecha documento |
| `feccnt` | Fecha contable |
| `cod_aux` | Código auxiliar (CCODCTA) |
| `karely` | Referencia cruzada (CRE_CODKAR) |

**Uso**: Cada pago/desembolso genera una partida contable.

---

#### `ctb_mov`
**Descripción**: Movimientos contables (DEBE/HABER).

| Campo | Descripción |
|-------|-------------|
| `id_ctb_diario` | FK a ctb_diario |
| `id_fuente_fondo` | Fuente de fondos |
| `id_ctb_nomenclatura` | Cuenta contable |
| `debe` | Monto DEBE |
| `haber` | Monto HABER |

**Uso**: Detalle contable de cada partida.

---

### Tablas de Cuentas Vinculadas

#### `ahommov`
**Descripción**: Movimientos de cuentas de ahorro vinculadas.

**Uso**: Cuando un pago incluye depósito a cuenta de ahorro.

---

#### `aprmov`
**Descripción**: Movimientos de cuentas de aportación vinculadas.

**Uso**: Cuando un pago incluye depósito a cuenta de aportación.

---

### Tablas de Configuración

#### `tb_agencia`
**Descripción**: Información de agencias/oficinas.

| Campo | Descripción |
|-------|-------------|
| `id_agencia` | ID único |
| `cod_agenc` | Código de agencia |
| `id_nomenclatura_caja` | Cuenta contable de caja |

**Uso**: Identificación de agencia y cuenta contable de caja.

---

#### `ctb_bancos`
**Descripción**: Catálogo de bancos.

| Campo | Descripción |
|-------|-------------|
| `id` | ID único |
| `id_nomenclatura` | Cuenta contable del banco |

**Uso**: Para pagos bancarios.

---

## ⚙️ Procesos del Sistema

### 1. Proceso de Creación de Solicitud de Crédito

**Archivo**: `src/cruds/crud_credito_indi.php` (case: 'create_solicitud')

**Pasos**:

1. **Validación de Campos**
   - Cliente seleccionado
   - Producto seleccionado
   - Monto solicitado
   - Analista asignado
   - Destino, sector, actividad económica
   - Tipo de crédito y período
   - Al menos una garantía

2. **Generación de Código de Crédito**
   ```sql
   SELECT cre_crecodcta(?, '01') as ccodcta
   ```
   - Función que genera código único basado en agencia

3. **Inserción en `cremcre_meta`**
   - Estado inicial: `Cestado = 'A'` (Aprobado/Solicitado)
   - Monto solicitado vs monto sugerido
   - Vinculación de garantías

4. **Registro de Garantías**
   - Relación con tabla de garantías del cliente

**Tablas Afectadas**:
- `cremcre_meta` (INSERT)
- Tablas de garantías (relación)

---

### 2. Proceso de Análisis y Aprobación

**Archivo**: `src/cruds/crud_credito_indi.php` (case: 'create_analisis')

**Pasos**:

1. **Actualización de Monto Aprobado**
   - `MonSug` = Monto aprobado (puede ser menor al solicitado)

2. **Actualización de Estado**
   - `Cestado = 'E'` (En espera de desembolso)

3. **Registro de Dictamen**
   - `Dictamen` = Número de dictamen

**Tablas Afectadas**:
- `cremcre_meta` (UPDATE)

---

### 3. Proceso de Generación de Plan de Pagos

**Archivo**: `src/funcphp/creditos/*.php` (varios archivos según tipo)

**Tipos de Plan de Pagos**:
- **Diario**: `CalculoPagosDiarios.php`
- **Semanal**: `CalculoPagosSemanales.php`
- **Quincenal**: `CalculoPagosQuincenales.php`
- **Mensual**: Cálculo estándar

**Pasos**:

1. **Cálculo de Cuotas**
   - Capital por cuota
   - Interés por cuota
   - Fechas de vencimiento

2. **Inserción en `Cre_ppg`**
   - Una fila por cada cuota
   - Estado inicial: `cestado = 'X'` (Pendiente)

**Tablas Afectadas**:
- `Cre_ppg` (INSERT múltiple)

---

### 4. Proceso de Desembolso

**Archivo**: `src/cruds/crud_credito_indi.php` (case: 'create_desembolso')

**Pasos**:

1. **Validaciones**
   - Crédito en estado `Cestado = 'E'`
   - Mes contable no cerrado
   - Monto a desembolsar válido

2. **Registro en CREDKAR**
   ```php
   $credkar = [
       'CCODCTA' => $codcredito,
       'DFECPRO' => $fechadesembolso,
       'CTIPPAG' => 'D',  // Desembolso
       'KP' => $monto_desembolsar,
       'NMONTO' => $monto_desembolsar,
       'CESTADO' => '1'
   ];
   ```

3. **Movimientos Contables**
   - DEBE: Cuenta de caja/banco
   - HABER: Cuenta de cartera de créditos

4. **Actualización de `cremcre_meta`**
   - `NCapDes` = Monto desembolsado
   - `DFecDsbls` = Fecha de desembolso
   - `Cestado = 'F'` (Vigente)

5. **Procesamiento de Gastos**
   - Descuentos del desembolso
   - Gastos administrativos

**Tablas Afectadas**:
- `CREDKAR` (INSERT)
- `cremcre_meta` (UPDATE)
- `ctb_diario` (INSERT)
- `ctb_mov` (INSERT múltiple)

---

### 5. Proceso de Pago

**Archivo**: `src/cruds/crud_caja.php` (líneas 680-1340)

**Documentación Detallada**: Ver `docs/LOGICA_PAGO_CREDITOS.md`

**Pasos Resumidos**:

1. **Validaciones**
   - Campos requeridos
   - Montos >= 0
   - Fecha <= hoy
   - Caja abierta
   - Mes contable no cerrado
   - Crédito vigente

2. **Consulta de Saldos Pendientes**
   ```sql
   SELECT 
       (NCapDes - SUM(KP)) AS saldopendiente,
       (SUM(nintere) - SUM(INTERES)) AS intpendiente
   FROM cremcre_meta cm
   LEFT JOIN CREDKAR ck ON ck.CCODCTA = cm.CCODCTA
   WHERE cm.CCODCTA = ?
   ```

3. **Registro en CREDKAR**
   ```php
   $credkar = [
       'CCODCTA' => $codigoCredito,
       'CTIPPAG' => 'P',  // Pago
       'KP' => $capital,
       'INTERES' => $interes,
       'MORA' => $montoMora,
       'OTR' => $otrosPagos,
       'CNROCUO' => $cnrocuo,  // Siguiente número de cuota
       'CESTADO' => '1'
   ];
   ```

4. **Actualización de Plan de Pagos**
   ```sql
   CALL update_ppg_account(?);  -- Actualiza cuotas pagadas
   SELECT calculo_mora(?);        -- Recalcula mora
   ```

5. **Movimientos Contables**
   - DEBE: Caja/Banco (monto recibido)
   - HABER: Cartera (capital), Intereses, Mora

6. **Gastos Vinculados** (si aplica)
   - Depósitos a ahorro
   - Depósitos a aportaciones
   - Registro en `credkar_detalle`

7. **Reestructuración** (si aplica)
   - Recalcular plan de pagos
   - Reducir plazo o cuota

**Tablas Afectadas**:
- `CREDKAR` (INSERT)
- `credkar_detalle` (INSERT, si aplica)
- `Cre_ppg` (UPDATE múltiple, vía procedimiento)
- `ctb_diario` (INSERT)
- `ctb_mov` (INSERT múltiple)
- `ahommov` (INSERT, si aplica)
- `aprmov` (INSERT, si aplica)

---

### 6. Proceso de Cálculo de Mora

**Función**: `calculo_mora(ccodcta)`

**Lógica**:

1. Identifica cuotas vencidas (`dfecven < fecha_actual`)
2. Calcula días de atraso
3. Aplica tasa de mora
4. Actualiza `nmorpag` en `Cre_ppg`

**Ejecución Automática**:
- Después de cada pago
- Al consultar estado de cuenta
- En reportes de cartera vencida

---

## 🖥️ Interfaces y Vistas

### Vistas de Créditos Individuales

#### `views/Creditos/cre_indi/cre_indi_01.php`
**Descripción**: Formulario de solicitud de crédito individual.

**Funcionalidades**:
- Selección de cliente
- Selección de producto
- Ingreso de monto solicitado
- Asignación de analista
- Selección de garantías

---

#### `views/Creditos/cre_indi/cre_indi_02.php`
**Descripción**: Análisis y aprobación de crédito individual.

**Funcionalidades**:
- Visualización de solicitud
- Aprobación de monto
- Generación de dictamen
- Generación de plan de pagos

---

#### `views/Creditos/cre_indi/tablaAmortizacion.php`
**Descripción**: Visualización de tabla de amortización.

**Datos Mostrados**:
- Cuotas del plan de pagos
- Fechas de vencimiento
- Capital e interés por cuota
- Estado de cada cuota

---

### Vistas de Créditos Grupales

#### `views/Creditos/cre_grupo/grup002.php`
**Descripción**: Gestión de créditos grupales.

**Funcionalidades**:
- Selección de grupo
- Asignación de montos por miembro
- Desembolso grupal

---

### Vistas de Caja (Pagos)

#### `views/Creditos/caja/*.php`
**Descripción**: Múltiples vistas para registro de pagos según agencia/cooperativa.

**Funcionalidades**:
- Selección de crédito
- Ingreso de montos (capital, interés, mora, otros)
- Selección de forma de pago
- Generación de recibo

---

### Vistas de Reportes

#### `views/Creditos/views_reporte/reporte001.php`
**Descripción**: Reporte principal de cartera.

**Datos Incluidos**:
- Listado de créditos
- Saldos pendientes
- Estados de créditos
- Filtros por agencia, producto, estado

**Tablas Consultadas**:
- `cremcre_meta`
- `tb_cliente`
- `CREDKAR` (para saldos)
- `Cre_ppg` (para cuotas)

---

#### Otros Reportes Importantes

| Archivo | Descripción |
|---------|-------------|
| `cartera_en_mora.php` | Créditos con cuotas vencidas |
| `creditos_desembolsados.php` | Créditos desembolsados en período |
| `creditos_a_vencer.php` | Créditos próximos a vencer |
| `ingresos_diarios.php` | Ingresos por pagos diarios |
| `clasificacion_por_*.php` | Múltiples clasificaciones |

---

## 🏗️ Modelos y Controladores

### Modelos (app/Models/)

#### `Credkar.php`
**Descripción**: Modelo para operaciones con CREDKAR.

**Métodos Principales**:
- `applyPayment($datos)`: Registra un pago
- `getNextCuo($ccodcta)`: Obtiene siguiente número de cuota

**Uso**:
```php
$credkar = new Credkar($db);
$result = $credkar->applyPayment([
    'cuenta_id' => '0020010200000001',
    'fecha' => '2025-01-15',
    'monto_capital' => 500.00,
    'monto_interes' => 50.00,
    // ...
]);
```

---

#### `Cremcre.php`
**Descripción**: Modelo para operaciones con cremcre_meta.

**Métodos Principales**:
- `getAccountsContable($ccodcta)`: Obtiene cuentas contables
- `getAccountContableCapital($ccodcta)`: Cuenta de capital
- `getAccountContableInteres($ccodcta)`: Cuenta de intereses
- `getAccountContableMora($ccodcta)`: Cuenta de mora

---

#### `PlanPagos.php`
**Descripción**: Modelo para operaciones con Cre_ppg.

**Métodos Principales**:
- `crearCuota($datos)`: Crea una cuota
- `getCuotasPendientes($idCuenta)`: Obtiene cuotas pendientes

---

### Controladores

#### `CreditoViewController.php`
**Descripción**: Controlador para vistas de créditos (nuevo sistema).

**Métodos**:
- `lista()`: Lista de créditos
- `detalle()`: Detalle de un crédito

---

## 🌐 APIs y Endpoints

### GraphQL API

**Archivo**: `src/API/graphql/`

#### Queries Disponibles

| Query | Descripción | Tablas Consultadas |
|-------|-------------|-------------------|
| `searchCredits` | Buscar créditos | `cremcre_meta`, `tb_cliente`, `CREDKAR` |
| `getCreditDetails` | Detalles de crédito | `cremcre_meta`, `Cre_ppg` |
| `getPaymentPlan` | Plan de pagos | `Cre_ppg` |
| `getClientDetails` | Detalles de cliente | `tb_cliente` |
| `searchClientesCreditos` | Clientes con créditos | `cremcre_meta`, `tb_cliente` |
| `getEstadoCuenta` | Estado de cuenta | `CREDKAR`, `Cre_ppg` |

#### Mutations Disponibles

| Mutation | Descripción | Tablas Afectadas |
|----------|-------------|------------------|
| `savePayment` | Registrar pago | `CREDKAR`, `Cre_ppg`, `ctb_*` |
| `crearSolicitudCredito` | Crear solicitud | `cremcre_meta` |
| `crearCliente` | Crear cliente | `tb_cliente` |

**Ejemplo de Query**:
```graphql
query {
  searchCredits {
    ccodcta
    nombre
    monsug
    Saldo
    estado
  }
}
```

**Ejemplo de Mutation**:
```graphql
mutation {
  savePayment(paymentData: {
    ccodcta: "0020010200000001"
    capital: 500.00
    interes: 50.00
    # ...
  }) {
    success
    message
    receiptNumber
  }
}
```

---

### Endpoints REST (Legacy)

#### `src/cruds/crud_credito_indi.php`
**Acciones**:
- `create_solicitud`: Crear solicitud
- `create_analisis`: Análisis y aprobación
- `create_desembolso`: Desembolso
- `listado_consultar_estado_cuenta`: Estado de cuenta

#### `src/cruds/crud_caja.php`
**Acciones**:
- `pago_credito`: Registrar pago
- `anular_pago`: Anular pago

---

## 📊 Procedimientos Almacenados

### `update_ppg_account(ccodcta)`

**Descripción**: Actualiza el plan de pagos después de un pago.

**Funcionalidad**:
1. Suma los pagos de capital (`KP`) de CREDKAR
2. Suma los pagos de interés (`INTERES`) de CREDKAR
3. Actualiza `ncappag` y `nintpag` en cada cuota de `Cre_ppg`
4. Marca cuotas como pagadas (`cestado = 'P'`) si están completas

**Uso**:
```sql
CALL update_ppg_account('0020010200000001');
```

---

### `calculo_mora(ccodcta)`

**Descripción**: Calcula la mora de cuotas vencidas.

**Funcionalidad**:
1. Identifica cuotas con `dfecven < fecha_actual`
2. Calcula días de atraso
3. Aplica tasa de mora configurada
4. Actualiza `nmorpag` en `Cre_ppg`

**Uso**:
```sql
SELECT calculo_mora('0020010200000001');
```

---

### `cre_crecodcta(id_agencia, tipo)`

**Descripción**: Genera código único de crédito.

**Funcionalidad**:
- Genera código basado en agencia y secuencia
- Retorna código único para el crédito

**Uso**:
```sql
SELECT cre_crecodcta(1, '01') as ccodcta;
```

---

## 🔄 Flujos de Datos Críticos

### Flujo 1: Creación de Crédito

```
1. Usuario crea solicitud
   ↓
2. Sistema genera CCODCTA
   ↓
3. INSERT en cremcre_meta (Cestado='A')
   ↓
4. Usuario analiza y aprueba
   ↓
5. UPDATE cremcre_meta (Cestado='E', MonSug)
   ↓
6. Sistema genera plan de pagos
   ↓
7. INSERT múltiple en Cre_ppg
```

---

### Flujo 2: Desembolso

```
1. Usuario inicia desembolso
   ↓
2. Validación: Cestado='E'
   ↓
3. INSERT en CREDKAR (CTIPPAG='D')
   ↓
4. INSERT en ctb_diario
   ↓
5. INSERT múltiple en ctb_mov
   ↓
6. UPDATE cremcre_meta (NCapDes, DFecDsbls, Cestado='F')
```

---

### Flujo 3: Pago

```
1. Usuario registra pago
   ↓
2. Validaciones (caja, mes, saldos)
   ↓
3. INSERT en CREDKAR (CTIPPAG='P')
   ↓
4. CALL update_ppg_account()
   ↓
5. SELECT calculo_mora()
   ↓
6. INSERT en ctb_diario
   ↓
7. INSERT múltiple en ctb_mov
   ↓
8. Si hay gastos vinculados:
   - INSERT en credkar_detalle
   - INSERT en ahommov/aprmov
```

---

### Flujo 4: Consulta de Saldo

```
1. Usuario consulta saldo
   ↓
2. SELECT de cremcre_meta (NCapDes)
   ↓
3. SELECT SUM(KP) de CREDKAR (CTIPPAG='P', CESTADO!='X')
   ↓
4. Saldo = NCapDes - SUM(KP)
   ↓
5. SELECT de Cre_ppg para cuotas pendientes
   ↓
6. SELECT calculo_mora() para mora actual
```

---

## 📤 Extracción y Abstracción de Información

### Consultas Críticas para Extracción

#### 1. Listado de Créditos con Saldos

```sql
SELECT 
    cm.CCODCTA,
    cl.short_name AS nombre_cliente,
    cm.MonSug AS monto_aprobado,
    cm.NCapDes AS capital_desembolsado,
    cm.DFecDsbls AS fecha_desembolso,
    cm.NintApro AS tasa_interes,
    cm.noPeriodo AS numero_cuotas,
    cm.Cestado AS estado,
    -- Saldo de capital
    ROUND(
        IFNULL(cm.NCapDes, 0) - 
        IFNULL((
            SELECT SUM(ck.KP) 
            FROM CREDKAR ck 
            WHERE ck.CCODCTA = cm.CCODCTA 
            AND ck.CTIPPAG = 'P' 
            AND ck.CESTADO != 'X'
        ), 0), 
    2) AS saldo_capital,
    -- Saldo de interés
    ROUND(
        IFNULL((
            SELECT SUM(ppg.nintere) 
            FROM Cre_ppg ppg 
            WHERE ppg.ccodcta = cm.CCODCTA
        ), 0) - 
        IFNULL((
            SELECT SUM(ck.INTERES) 
            FROM CREDKAR ck 
            WHERE ck.CCODCTA = cm.CCODCTA 
            AND ck.CTIPPAG = 'P' 
            AND ck.CESTADO != 'X'
        ), 0), 
    2) AS saldo_interes
FROM cremcre_meta cm
INNER JOIN tb_cliente cl ON cl.idcod_cliente = cm.CodCli
WHERE cm.Cestado = 'F'  -- Solo créditos vigentes
ORDER BY cm.CCODCTA;
```

---

#### 2. Historial de Pagos de un Crédito

```sql
SELECT 
    ck.DFECPRO AS fecha_pago,
    ck.CNROCUO AS numero_cuota,
    ck.CNUMING AS numero_recibo,
    ck.KP AS capital_pagado,
    ck.INTERES AS interes_pagado,
    ck.MORA AS mora_pagada,
    ck.OTR AS otros_pagos,
    ck.NMONTO AS monto_total,
    ck.CCONCEP AS concepto,
    ck.FormPago AS forma_pago,
    ck.boletabanco AS boleta_banco
FROM CREDKAR ck
WHERE ck.CCODCTA = ?
AND ck.CTIPPAG = 'P'  -- Solo pagos
AND ck.CESTADO != 'X'  -- No anulados
ORDER BY ck.DFECPRO, ck.CNROCUO;
```

---

#### 3. Plan de Pagos con Estado

```sql
SELECT 
    ppg.cnrocuo AS numero_cuota,
    ppg.dfecven AS fecha_vencimiento,
    ppg.dfecpag AS fecha_pago,
    ppg.ncapita AS capital_cuota,
    ppg.nintere AS interes_cuota,
    ppg.ncappag AS capital_pagado,
    ppg.nintpag AS interes_pagado,
    ppg.nmorpag AS mora_pagada,
    ppg.cestado AS estado_cuota,
    ppg.diasatraso AS dias_atraso,
    CASE 
        WHEN ppg.dfecven < CURDATE() AND ppg.cestado != 'P' 
        THEN DATEDIFF(CURDATE(), ppg.dfecven) 
        ELSE 0 
    END AS dias_vencido
FROM Cre_ppg ppg
WHERE ppg.ccodcta = ?
ORDER BY ppg.cnrocuo;
```

---

#### 4. Cartera Vencida

```sql
SELECT 
    cm.CCODCTA,
    cl.short_name AS cliente,
    cl.no_identifica AS dpi,
    ppg.cnrocuo AS cuota_vencida,
    ppg.dfecven AS fecha_vencimiento,
    DATEDIFF(CURDATE(), ppg.dfecven) AS dias_atraso,
    ppg.ncapita AS capital_pendiente,
    ppg.nintere AS interes_pendiente,
    ppg.nmorpag AS mora_calculada
FROM cremcre_meta cm
INNER JOIN tb_cliente cl ON cl.idcod_cliente = cm.CodCli
INNER JOIN Cre_ppg ppg ON ppg.ccodcta = cm.CCODCTA
WHERE cm.Cestado = 'F'
AND ppg.dfecven < CURDATE()
AND ppg.cestado != 'P'  -- No pagada
ORDER BY ppg.dfecven;
```

---

#### 5. Ingresos por Pagos (Período)

```sql
SELECT 
    DATE(ck.DFECPRO) AS fecha_pago,
    SUM(ck.KP) AS total_capital,
    SUM(ck.INTERES) AS total_interes,
    SUM(ck.MORA) AS total_mora,
    SUM(ck.OTR) AS total_otros,
    SUM(ck.NMONTO) AS total_general,
    COUNT(*) AS numero_pagos
FROM CREDKAR ck
WHERE ck.CTIPPAG = 'P'
AND ck.CESTADO != 'X'
AND ck.DFECPRO BETWEEN ? AND ?
GROUP BY DATE(ck.DFECPRO)
ORDER BY ck.DFECPRO;
```

---

#### 6. Desembolsos (Período)

```sql
SELECT 
    cm.CCODCTA,
    cl.short_name AS cliente,
    cm.MonSug AS monto_aprobado,
    cm.NCapDes AS monto_desembolsado,
    cm.DFecDsbls AS fecha_desembolso,
    pr.nombre AS producto,
    cm.NintApro AS tasa_interes,
    cm.noPeriodo AS plazo_cuotas
FROM cremcre_meta cm
INNER JOIN tb_cliente cl ON cl.idcod_cliente = cm.CodCli
INNER JOIN cre_productos pr ON pr.id = cm.CCODPRD
WHERE cm.Cestado = 'F'
AND cm.DFecDsbls BETWEEN ? AND ?
ORDER BY cm.DFecDsbls;
```

---

### Abstracciones Recomendadas

#### 1. Vista de Saldos Consolidados

```sql
CREATE VIEW vw_saldos_creditos AS
SELECT 
    cm.CCODCTA,
    cm.CodCli,
    cm.MonSug,
    cm.NCapDes,
    -- Saldo capital
    (cm.NCapDes - IFNULL(SUM(CASE WHEN ck.CTIPPAG='P' AND ck.CESTADO!='X' THEN ck.KP ELSE 0 END), 0)) AS saldo_capital,
    -- Saldo interés
    ((SELECT SUM(nintere) FROM Cre_ppg WHERE ccodcta=cm.CCODCTA) - 
     IFNULL(SUM(CASE WHEN ck.CTIPPAG='P' AND ck.CESTADO!='X' THEN ck.INTERES ELSE 0 END), 0)) AS saldo_interes
FROM cremcre_meta cm
LEFT JOIN CREDKAR ck ON ck.CCODCTA = cm.CCODCTA
WHERE cm.Cestado = 'F'
GROUP BY cm.CCODCTA;
```

---

#### 2. Función de Cálculo de Saldo

```sql
DELIMITER //
CREATE FUNCTION fn_saldo_capital(ccodcta VARCHAR(20))
RETURNS DECIMAL(20,2)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE saldo DECIMAL(20,2);
    
    SELECT 
        IFNULL(NCapDes, 0) - 
        IFNULL((
            SELECT SUM(KP) 
            FROM CREDKAR 
            WHERE CCODCTA = ccodcta 
            AND CTIPPAG = 'P' 
            AND CESTADO != 'X'
        ), 0)
    INTO saldo
    FROM cremcre_meta
    WHERE CCODCTA = ccodcta;
    
    RETURN IFNULL(saldo, 0);
END //
DELIMITER ;
```

---

### Puntos Críticos para Extracción

#### ⚠️ Campos Esenciales para Reportes

1. **Identificación del Crédito**:
   - `cremcre_meta.CCODCTA` (siempre necesario)

2. **Información del Cliente**:
   - `tb_cliente.short_name`
   - `tb_cliente.no_identifica`

3. **Montos**:
   - `cremcre_meta.MonSug` (monto aprobado)
   - `cremcre_meta.NCapDes` (capital desembolsado)
   - Saldo = `NCapDes - SUM(CREDKAR.KP donde CTIPPAG='P')`

4. **Fechas**:
   - `cremcre_meta.DFecDsbls` (fecha desembolso)
   - `CREDKAR.DFECPRO` (fecha de pago)

5. **Estados**:
   - `cremcre_meta.Cestado` (estado del crédito)
   - `CREDKAR.CESTADO` (estado del movimiento)
   - `Cre_ppg.cestado` (estado de la cuota)

---

#### ⚠️ Consideraciones Importantes

1. **Siempre filtrar por `CESTADO != 'X'`** en CREDKAR para excluir movimientos anulados

2. **Usar `CTIPPAG = 'P'`** para pagos y `CTIPPAG = 'D'` para desembolsos

3. **Validar estado del crédito** (`Cestado = 'F'`) para créditos vigentes

4. **Considerar transacciones** al calcular saldos (usar transacciones de base de datos)

5. **Mora calculada** se actualiza con `calculo_mora()`, no se almacena históricamente

---

## 📝 Resumen Ejecutivo

### Tablas Críticas (Top 5)

1. **`cremcre_meta`** - Información central del crédito
2. **`CREDKAR`** - Todos los movimientos financieros
3. **`Cre_ppg`** - Plan de pagos y estado de cuotas
4. **`tb_cliente`** - Información del cliente
5. **`cre_productos`** - Configuración de productos

### Procesos Críticos

1. **Creación de Solicitud** → `cremcre_meta` (INSERT)
2. **Aprobación** → `cremcre_meta` (UPDATE estado)
3. **Generación de Plan** → `Cre_ppg` (INSERT múltiple)
4. **Desembolso** → `CREDKAR` (INSERT), `cremcre_meta` (UPDATE)
5. **Pago** → `CREDKAR` (INSERT), `Cre_ppg` (UPDATE vía procedimiento)

### Interfaces Críticas

1. **Solicitud de Crédito** - `cre_indi_01.php`
2. **Análisis/Aprobación** - `cre_indi_02.php`
3. **Pagos** - `caja/*.php`
4. **Reportes** - `views_reporte/*.php`

### Consultas Esenciales

1. **Saldo de Capital**: `NCapDes - SUM(KP donde CTIPPAG='P')`
2. **Cuotas Vencidas**: `Cre_ppg` donde `dfecven < CURDATE()` y `cestado != 'P'`
3. **Historial de Pagos**: `CREDKAR` donde `CTIPPAG='P'` y `CESTADO!='X'`

---

## 🔗 Referencias

- **Documentación de Pagos**: `docs/LOGICA_PAGO_CREDITOS.md`
- **Documentación de Reportes**: `docs/REPORTE_RECIBOS_CAJA.md`
- **Entidades**: `docs/entidades/credkar.md`

---

*Documento generado el: 2025-01-XX*
*Sistema: MicroSystemPlus*
*Versión del Sistema: [Versión actual]*
