# 📋 Lógica de Pago de Créditos

## Descripción General

El sistema de pagos de créditos se encuentra principalmente en `src/cruds/crud_caja.php` (líneas 680-1340). Este documento explica en detalle cada sección del proceso de pago.

---

## 1️⃣ Recepción de Datos del Formulario

**Ubicación:** Líneas 680-700

```php
list($noRecibo, $fechaPago, $capital, $interes, $montoMora, $otrosPagos, 
     $totalGeneral, $fechaPagoBanco, $noBoletaBanco, $concepto, ...) = $_POST["inputs"];
list($bancoId, $cuentaId, $metodoPago) = $_POST["selects"];
list($tipoMontoMora) = $_POST["radios"];
```

### Componentes del Pago

| Campo | Descripción |
|-------|-------------|
| `$capital` | Monto a abonar al capital del préstamo |
| `$interes` | Monto de intereses a pagar |
| `$montoMora` | Monto de mora por atraso |
| `$otrosPagos` | Otros conceptos (ahorro programado, seguros, etc.) |
| `$metodoPago` | 1=Efectivo, 2=Banco, d_XXX=Documento especial |
| `$noRecibo` | Número de recibo/documento |
| `$fechaPago` | Fecha del pago |
| `$concepto` | Descripción del pago |

### Datos Adicionales (archivo)

```php
list(
    $codigoCredito,       // Código de la cuenta de crédito
    $detalleotros,        // Detalle de otros pagos vinculados
    $reestructura,        // Flag de reestructuración
    $identificatorsPpg,   // IDs de cuotas afectadas
    $switchCambioIntereses // Flag para cambio de intereses
) = $_POST["archivo"];
```

---

## 2️⃣ Validaciones

**Ubicación:** Líneas 715-780

### Validación de Campos

```php
$validar = validacionescampos([
    [$codigoCredito, "0", 'Debe seleccionar un crédito a pagar', 1],
    [$noRecibo, "", 'Debe digitar un número de recibo', 1],
    [$fechaPago, "", 'Debe digitar una fecha de pago', 1],
    [$concepto, "", 'Debe digitar un concepto', 1],
    [$fechaPago, $hoy, 'La fecha de pago no puede ser mayor a la fecha de hoy', 3],
    [$capital, "", 'Debe digitar un monto de capital', 1],
    [$interes, "", 'Debe digitar un monto de interés', 1],
    [$montoMora, "", 'Debe digitar un monto de mora', 1],
    [$otrosPagos, "", 'Debe digitar un monto de otros pagos', 1],
    [$capital, 0, "No puede digitar un capital menor a 0", 2],
    [$interes, 0, "No puede digitar un interes menor a 0", 2],
    [$montoMora, 0, "No puede digitar una mora menor a 0", 2],
    [$otrosPagos, 0, "No puede digitar en otros pagos un monto menor a 0", 2],
]);
```

### Validaciones Críticas del Sistema

| Validación | Función | Descripción |
|------------|---------|-------------|
| Cierre de caja | `comprobar_cierre_cajaPDO()` | Verifica que el usuario tenga caja abierta |
| Cierre de mes | `comprobar_cierrePDO()` | Verifica que el mes contable no esté cerrado |
| Boleta banco | Query a `CREDKAR` | Verifica que la boleta no esté duplicada |
| Estado crédito | `Cestado = 'F'` | Verifica que el crédito esté vigente |

### Validación de Cuentas Vinculadas

```php
if ($detalleotros != null) {
    foreach ($detalleotros as $rowval) {
        $monf = $rowval[0];
        if (is_numeric($monf) && $monf < 0) {
            throw new Exception("No puede ingresar valores negativos");
        }
        // Valida existencia de cuenta de ahorro/aportación vinculada
    }
}
```

---

## 3️⃣ Consulta de Saldos Pendientes

**Ubicación:** Líneas 790-810

```php
$querysaldos = "SELECT 
    IFNULL((ROUND((IFNULL(cm.NCapDes,0)),2) - 
        (SELECT ROUND(IFNULL(SUM(c.KP),0),2) 
         FROM CREDKAR c 
         WHERE c.CTIPPAG = 'P' AND c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X')),0) AS saldopendiente,
    IFNULL(ROUND(
        (SELECT ROUND(IFNULL(SUM(nintere),0),2) FROM Cre_ppg WHERE ccodcta = cm.CCODCTA) -
        (SELECT ROUND(IFNULL(SUM(c.INTERES),0),2) 
         FROM CREDKAR c 
         WHERE c.CTIPPAG = 'P' AND c.CCODCTA = cm.CCODCTA AND c.CESTADO!='X'),2),0) AS intpendiente 
FROM cremcre_meta cm 
WHERE cm.CCODCTA = ?";
```

### Cálculo de Saldos

| Variable | Fórmula |
|----------|---------|
| `saldopendiente` | Capital Desembolsado - Suma de Pagos de Capital |
| `intpendiente` | Intereses del Plan - Suma de Intereses Pagados |

### Verificaciones

```php
$capital_pendiente = ($saldosCredito[0]['saldopendiente'] > 0) 
    ? round($saldosCredito[0]['saldopendiente'], 2) : 0;
$interes_pendiente = ($saldosCredito[0]['intpendiente'] > 0) 
    ? round($saldosCredito[0]['intpendiente'], 2) : 0;

// Validación configurable
if (!$appConfigGeneral->validarSaldoKpXPagosKp()) {
    if ($capital > $capital_pendiente) {
        // Log o excepción según configuración
    }
}
```

---

## 4️⃣ Obtención de Cuentas Contables

**Ubicación:** Líneas 820-850

```php
$cuentasContables = $database->getAllResults("
    SELECT id_cuenta_capital, id_cuenta_interes, id_cuenta_mora, id_cuenta_otros,
           id_fondo, cm.Cestado, cp.id idProducto 
    FROM cre_productos cp 
    INNER JOIN cremcre_meta cm ON cp.id=cm.CCODPRD 
    WHERE cm.CCODCTA=?", 
[$codigoCredito]);
```

### Cuentas Obtenidas

| Variable | Descripción |
|----------|-------------|
| `id_nomenclatura_capital` | Cuenta contable para cartera de créditos |
| `id_nomenclatura_interes` | Cuenta contable para ingresos por intereses |
| `id_nomenclatura_mora` | Cuenta contable para ingresos por mora |
| `id_nomenclatura_otros` | Cuenta contable para otros ingresos |
| `id_fondo` | Fuente de fondos del crédito |
| `id_nomenclatura_caja` | Cuenta de caja de la agencia |

---

## 5️⃣ Registro del Pago en CREDKAR

**Ubicación:** Líneas 930-970

```php
// Obtener número de cuota
$result = $database->getAllResults("
    SELECT IFNULL(MAX(ck.CNROCUO),0)+1 AS correlrocuo 
    FROM CREDKAR ck 
    WHERE ck.CCODCTA=? AND CTIPPAG = 'P' AND CESTADO = '1'", 
[$codigoCredito]);
$cnrocuo = (empty($result)) ? 1 : $result[0]['correlrocuo'];

// Insertar registro de pago
$credkar = array(
    'CCODCTA' => $codigoCredito,      // Cuenta de crédito
    'DFECPRO' => $fechaPago,          // Fecha del pago
    'DFECSIS' => $hoy2,               // Fecha del sistema
    'CNROCUO' => $cnrocuo,            // Número de cuota
    'NMONTO' => $totalGeneral,        // Monto total pagado
    'CNUMING' => $noRecibo,           // Número de documento
    'CCONCEP' => $concepto,           // Concepto
    'KP' => $capital,                 // Abono a capital
    'INTERES' => $interes,            // Pago de intereses
    'MORA' => $montoMora,             // Pago de mora
    'AHOPRG' => 0,                    // Ahorro programado
    'OTR' => $otrosPagos,             // Otros conceptos
    'CCODINS' => "1",                 // Código institución
    'CCODOFI' => $idagencia,          // Código oficina
    'CCODUSU' => $idusuario,          // Código usuario
    'CTIPPAG' => "P",                 // Tipo: P = Pago
    'CMONEDA' => "Q",                 // Moneda
    'CBANCO' => $bancoSaveTable,      // Banco (si aplica)
    'FormPago' => $metodoPago,        // Forma de pago
    'CCODBANCO' => $cuentaSaveTable,  // Cuenta banco
    'DFECBANCO' => $fechaChequeSaveTable,  // Fecha banco
    'boletabanco' => $nroChequeSaveTable,  // Boleta banco
    'CESTADO' => "1",                 // Estado activo
    'DFECMOD' => $hoy2,               // Fecha modificación
);

$id_credkar = $database->insert('CREDKAR', $credkar);
```

### Estructura de la Tabla CREDKAR

| Campo | Tipo | Descripción |
|-------|------|-------------|
| `CCODCTA` | VARCHAR | Código de cuenta de crédito |
| `DFECPRO` | DATE | Fecha de proceso/pago |
| `CNROCUO` | INT | Número de cuota |
| `NMONTO` | DECIMAL | Monto total del movimiento |
| `KP` | DECIMAL | Abono a capital |
| `INTERES` | DECIMAL | Pago de intereses |
| `MORA` | DECIMAL | Pago de mora |
| `OTR` | DECIMAL | Otros pagos |
| `CTIPPAG` | CHAR | Tipo: P=Pago, D=Desembolso |
| `CESTADO` | CHAR | Estado: 1=Activo, X=Anulado |

---

## 6️⃣ Actualización del Plan de Pagos

**Ubicación:** Líneas 1010-1015

```php
// Actualiza el estado de las cuotas en Cre_ppg
$database->executeQuery('CALL update_ppg_account(?);', [$codigoCredito]);

// Recalcula la mora de las cuotas vencidas
$database->executeQuery('SELECT calculo_mora(?);', [$codigoCredito]);
```

### Procedimiento `update_ppg_account`

Este procedimiento almacenado:
1. Actualiza los campos `ncappag`, `nintpag`, `nmorpag` de cada cuota
2. Marca como pagadas (`cestado = 'P'`) las cuotas completamente abonadas
3. Actualiza el saldo de capital restante

### Función `calculo_mora`

Esta función:
1. Identifica cuotas vencidas no pagadas
2. Calcula la mora según la tasa configurada
3. Actualiza el campo `nmorpag` en `Cre_ppg`

---

## 7️⃣ Control de Mora Perdonada

**Ubicación:** Líneas 1020-1060

```php
if ($tipoMontoMora === 'perdon' && !empty($tipoAutorizacion)) {
    // Consultar los valores de mora de los Id_ppg seleccionados
    $placeholders = implode(',', array_fill(0, count($identificatorsPpg), '?'));
    $query = "SELECT cnrocuo, nmorpag FROM Cre_ppg WHERE Id_ppg IN ($placeholders)";
    $ppgMoraAnt = $database->getAllResults($query, $identificatorsPpg);

    // Registrar en bitácora si hubo perdón de mora
    if (array_sum(array_column($ppgMoraAnt, 'nmorpag')) != $montoMora) {
        foreach ($ppgMoraAnt as $ppg) {
            $cre_ppg_log = array(
                "no_cuota" => $ppg['cnrocuo'],
                "ccodcta" => $codigoCredito,
                "credkar_id" => $id_credkar,
                "morapag" => $ppg['nmorpag'],
                "tipo_autorizacion" => $tipoAuth,
                "autorizado_por" => $idUserAuth[0]['id']
            );
            $database->insert('cre_ppg_log', $cre_ppg_log);
        }
    }
}
```

---

## 8️⃣ Movimientos Contables

**Ubicación:** Líneas 1050-1200

### Creación de la Partida Contable

```php
$numpartida = getnumcompdo($idusuario, $database);

$ctb_diario = array(
    'numcom' => $numpartida,           // Número de partida
    'id_ctb_tipopoliza' => $id_ctb_tipopoliza,  // 1=Efectivo, 11=Banco
    'id_tb_moneda' => 1,               // Moneda local
    'numdoc' => $numdocdiario,         // Número de documento
    'glosa' => $concepto,              // Descripción
    'fecdoc' => $fechaBancoSave,       // Fecha documento
    'feccnt' => $fechaPago,            // Fecha contable
    'cod_aux' => $codigoCredito,       // Código auxiliar
    'id_tb_usu' => $idusuario,         // Usuario
    'karely' => "CRE_" . $id_credkar,  // Referencia cruzada
    'id_agencia' => $idagencia,        // Agencia
    'fecmod' => $hoy2,                 // Fecha modificación
    'estado' => 1,                     // Activo
    'editable' => 0                    // No editable
);
$id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);
```

### Estructura de la Partida

```
┌─────────────────────────────────────────────────────────────┐
│           PARTIDA CONTABLE (PAGO DE CRÉDITO)                │
├─────────────────────────────────────────────────────────────┤
│ DEBE                          │ HABER                       │
├───────────────────────────────┼─────────────────────────────┤
│ Caja/Bancos   Q xxx.xx        │                             │
│ (Monto total recibido)        │                             │
├───────────────────────────────┼─────────────────────────────┤
│                               │ Cartera de Créditos Q xxx   │
│                               │ (Abono a capital)           │
├───────────────────────────────┼─────────────────────────────┤
│                               │ Intereses s/Préstamos Q xxx │
│                               │ (Ingreso por intereses)     │
├───────────────────────────────┼─────────────────────────────┤
│                               │ Mora s/Préstamos    Q xxx   │
│                               │ (Ingreso por mora)          │
├───────────────────────────────┼─────────────────────────────┤
│                               │ IVA por Pagar       Q xxx   │
│                               │ (Si aplica desglose)        │
└───────────────────────────────┴─────────────────────────────┘
```

### Movimiento del DEBE (Total Recibido)

```php
$ctb_mov = array(
    'id_ctb_diario' => $id_ctb_diario,
    'id_fuente_fondo' => $id_fondo,
    'id_ctb_nomenclatura' => $id_nomenclatura_caja,  // Caja o Banco
    'debe' => $totalGeneral,
    'haber' => 0
);
$database->insert('ctb_mov', $ctb_mov);
```

### Movimientos del HABER (Detalle)

```php
// CAPITAL
if ($capital > 0) {
    $ctb_mov = array(
        'id_ctb_diario' => $id_ctb_diario,
        'id_fuente_fondo' => $id_fondo,
        'id_ctb_nomenclatura' => $id_nomenclatura_capital,
        'debe' => 0,
        'haber' => $capital
    );
    $database->insert('ctb_mov', $ctb_mov);
}

// INTERESES (con posible desglose de IVA)
if ($montoIntGravamen > 0) {
    $ctb_mov = array(
        'id_ctb_diario' => $id_ctb_diario,
        'id_fuente_fondo' => $id_fondo,
        'id_ctb_nomenclatura' => $id_nomenclatura_interes,
        'debe' => 0,
        'haber' => $montoIntGravamen
    );
    $database->insert('ctb_mov', $ctb_mov);
}

// MORA
if ($montoMoraGravamen > 0) {
    $ctb_mov = array(
        'id_ctb_diario' => $id_ctb_diario,
        'id_fuente_fondo' => $id_fondo,
        'id_ctb_nomenclatura' => $id_nomenclatura_mora,
        'debe' => 0,
        'haber' => $montoMoraGravamen
    );
    $database->insert('ctb_mov', $ctb_mov);
}
```

### Desglose de IVA (si está configurado)

```php
$desglose_iva = $appConfigGeneral->desglosarIva();

if ($desglose_iva) {
    $montoIntGravamen = round(($montoInteresReal / 1.12), 2);
    $montoMoraGravamen = round(($montoMora / 1.12), 2);
    $ivaTotal = (($montoInteresReal - $montoIntGravamen) + ($montoMora - $montoMoraGravamen));

    if ($ivaTotal > 0) {
        $ctb_mov = array(
            'id_ctb_diario' => $id_ctb_diario,
            'id_fuente_fondo' => $id_fondo,
            'id_ctb_nomenclatura' => $idNomenclaturaIvaXPagar,
            'debe' => 0,
            'haber' => $ivaTotal
        );
        $database->insert('ctb_mov', $ctb_mov);
    }
}
```

---

## 9️⃣ Gastos Vinculados

**Ubicación:** Líneas 1215-1310

### Procesamiento de Otros Pagos

```php
if ($detalleotros != null) {
    foreach ($detalleotros as $rowval) {
        // Estructura: [monto, idgasto, idcontable, modulo, codaho]
        $monf = $rowval[0];      // Monto
        $idgasto = $rowval[1];   // ID del gasto
        $modulo = $rowval[3];    // 1=Ahorro, 2=Aportaciones
        
        // Registrar detalle en credkar_detalle
        $credkar_detalle = array(
            'id_credkar' => $id_credkar,
            'id_concepto' => $idgasto,
            'monto' => $monf
        );
        $database->insert('credkar_detalle', $credkar_detalle);
        
        // Movimiento contable del gasto
        $ctb_mov = array(
            'id_ctb_diario' => $id_ctb_diario,
            'id_fuente_fondo' => $id_fondo,
            'id_ctb_nomenclatura' => $rowval[2],
            'debe' => 0,
            'haber' => $monf
        );
        $database->insert('ctb_mov', $ctb_mov);
    }
}
```

### Depósito a Cuenta de Ahorro Vinculada

```php
if ($modulo == '1') {  // Ahorro
    $ahommov = array(
        'ccodaho' => $rowval[4],         // Código cuenta ahorro
        'dfecope' => $fechaPago,         // Fecha operación
        'ctipope' => "D",                // Tipo: D=Depósito
        'cnumdoc' => $noRecibo,          // Número documento
        'ctipdoc' => "V",                // Tipo doc: V=Vinculado
        'crazon' => "DEPOSITO VINCULADO",
        'nlibreta' => $nlibreta,
        'monto' => $monf,
        'auxi' => $codigoCredito,        // Referencia al crédito
        ...
    );
    $database->insert('ahommov', $ahommov);
    
    // Reordenar transacciones
    $database->executeQuery('CALL ahom_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
    $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$rowval[4]]);
}
```

### Depósito a Cuenta de Aportaciones

```php
if ($modulo == '2') {  // Aportaciones
    $aprmov = array(
        'ccodaport' => $rowval[4],       // Código cuenta aportación
        'dfecope' => $fechaPago,
        'ctipope' => "D",                // Depósito
        'cnumdoc' => $noRecibo,
        'ctipdoc' => "V",                // Vinculado
        'crazon' => "DEPOSITO VINCULADO",
        'monto' => $monf,
        'auxi' => $codigoCredito,
        ...
    );
    $database->insert('aprmov', $aprmov);
    
    // Reordenar transacciones
    $database->executeQuery('CALL apr_ordena_noLibreta(?,?);', [$nlibreta, $rowval[4]]);
    $database->executeQuery('CALL apr_ordena_Transacciones(?);', [$rowval[4]]);
}
```

---

## 🔟 Reestructuración de Crédito

**Ubicación:** Líneas 1320-1330

```php
if ($reestructura == '1') {
    Log::info("Reestructurando credito", [$codigoCredito, $fechaPago]);
    $credito = new CreditoAmortizationSystem($codigoCredito, $database);
    $credito->procesaReestructura();
}
```

### Clase CreditoAmortizationSystem

**Ubicación:** `src/funcphp/creditos/CreditoAmortizationSystem.php`

Esta clase maneja:
- Recálculo del plan de pagos después de un pago adelantado
- Dos opciones de reestructuración:
  - **Reducir plazo:** Mantiene la cuota, reduce el número de pagos
  - **Reducir cuota:** Mantiene el plazo, reduce el monto de la cuota

---

## 📊 Flujo Resumido del Proceso

```
┌──────────────────────────────────────────────────────────────┐
│                    INICIO DEL PAGO                           │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  1. VALIDAR DATOS                                            │
│     • Campos requeridos                                      │
│     • Montos >= 0                                            │
│     • Fecha <= hoy                                           │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  2. VALIDAR SISTEMA                                          │
│     • Caja abierta del usuario                               │
│     • Mes contable no cerrado                                │
│     • Crédito vigente (Cestado = 'F')                        │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  3. VERIFICAR SALDOS                                         │
│     • Capital pendiente >= Capital a pagar                   │
│     • Interés pendiente >= Interés a pagar                   │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  4. INICIAR TRANSACCIÓN                                      │
│     $database->beginTransaction();                           │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  5. INSERTAR PAGO EN CREDKAR                                 │
│     • Registra capital, interés, mora, otros                 │
│     • Genera número de cuota                                 │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  6. ACTUALIZAR PLAN DE PAGOS                                 │
│     • CALL update_ppg_account                                │
│     • SELECT calculo_mora                                    │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  7. REGISTRAR PERDÓN DE MORA (si aplica)                     │
│     • Bitácora de mora perdonada                             │
│     • Usuario autorizador                                    │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  8. CREAR PARTIDA CONTABLE                                   │
│     • ctb_diario: Encabezado                                 │
│     • ctb_mov: Movimientos DEBE/HABER                        │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  9. PROCESAR GASTOS VINCULADOS                               │
│     • Depósitos a ahorro                                     │
│     • Depósitos a aportaciones                               │
│     • credkar_detalle                                        │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  10. REESTRUCTURAR (si aplica)                               │
│      • CreditoAmortizationSystem                             │
│      • Recalcular plan de pagos                              │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│  11. CONFIRMAR TRANSACCIÓN                                   │
│      $database->commit();                                    │
└─────────────────────────┬────────────────────────────────────┘
                          ▼
┌──────────────────────────────────────────────────────────────┐
│                    FIN DEL PAGO                              │
│      Respuesta: [mensaje, status, noRecibo, noCuota]         │
└──────────────────────────────────────────────────────────────┘
```

---

## 📁 Tablas Involucradas

| Tabla | Descripción |
|-------|-------------|
| `CREDKAR` | Kardex de movimientos del crédito |
| `credkar_detalle` | Detalle de otros pagos/gastos |
| `Cre_ppg` | Plan de pagos del crédito |
| `cremcre_meta` | Información general del crédito |
| `cre_ppg_log` | Bitácora de mora perdonada |
| `ctb_diario` | Encabezado de partidas contables |
| `ctb_mov` | Movimientos contables (DEBE/HABER) |
| `ahommov` | Movimientos de cuentas de ahorro |
| `aprmov` | Movimientos de cuentas de aportación |

---

## ⚙️ Configuraciones Relevantes

| Configuración | Descripción |
|---------------|-------------|
| `validarSaldoKpXPagosKp()` | Validar capital vs saldo pendiente |
| `validarSaldoIntXPagosInt()` | Validar interés vs saldo pendiente |
| `desglosarIva()` | Separar IVA de intereses y mora |
| `permitirRepetirBoletasPorBancos()` | Permitir boletas duplicadas |

---

## 🔗 Archivos Relacionados

- `src/cruds/crud_caja.php` - Lógica principal de pagos
- `src/funcphp/creditos/CreditoAmortizationSystem.php` - Reestructuración
- `src/funcphp/creditos/CalculoPagosSemanales.php` - Cálculo de cuotas semanales
- `src/funcphp/creditos/CalculoPagosDiarios.php` - Cálculo de cuotas diarias
- `src/funcphp/creditos/CalculoPagosQuincenales.php` - Cálculo de cuotas quincenales
- `src/API/graphql/resolvers.js` - API GraphQL para pagos

---

*Documentación generada el 17 de diciembre de 2025*
