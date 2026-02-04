# Refactorización de Cálculos en Reportes - Guía de Implementación

## Resumen de Problemas Identificados

### 1. **Inconsistencias en Cálculos de Saldo de Capital**
- **Problema**: Diferentes lógicas entre reportes para calcular saldos
- **Causa**: Algunos reportes aplicaban filtros "mayor que 0" en PHP, otros en SQL
- **Impacto**: Diferencias de miles en totales entre reportes relacionados

### 2. **Aproximaciones y Redondeos No Controlados**
- **Problema**: Valores mostrados redondeados sin control de precisión
- **Causa**: Formatos de Excel y PDF por defecto, cálculos SQL sin CAST
- **Impacto**: Sumas no cuadraban exactamente entre reportes

### 3. **Cálculos de Porcentajes Incorrectos**
- **Problema**: Porcentajes basados en campos incorrectos
- **Causa**: Usar cantidad de registros en lugar de valores monetarios
- **Impacto**: Distribuciones porcentuales erróneas

## Soluciones Implementadas

### A. **Estandarización de Cálculo de Saldo de Capital**

#### 1. Consulta SQL Unificada
```sql
-- ANTES (Inconsistente):
-- Reporte 1: SUM(cremi.NCapDes - IFNULL(kar.sum_KP, 0)) AS saldo_capital
-- Reporte 2: cremi.NCapDes, luego PHP: $saldo = ($monto - $pagado) > 0 ? ($monto - $pagado) : 0

-- DESPUÉS (Estandarizado):
CAST(
    CASE 
        WHEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) > 0 
        THEN (cremi.NCapDes - IFNULL(kar.sum_KP, 0)) 
        ELSE 0 
    END AS DECIMAL(15,2)
) AS saldo_capital_real
```

#### 2. Subconsulta KAR Estandarizada
```sql
LEFT JOIN (
    SELECT 
        ccodcta, 
        SUM(KP) AS sum_KP, 
        MAX(dfecpro) AS dfecpro_ult, 
        SUM(interes) AS sum_interes, 
        SUM(MORA) AS sum_MORA, 
        SUM(AHOPRG) + SUM(OTR) AS sum_AHOPRG_OTR
    FROM CREDKAR
    WHERE dfecpro <= ? AND cestado != 'X' AND ctippag = 'P'
    GROUP BY ccodcta
) AS kar ON kar.ccodcta = cremi.CCODCTA
```

### B. **Eliminación de Aproximaciones**

#### 1. Consultas SQL con Precisión Decimal
```sql
-- ANTES:
SUM(campo_monetario) AS total

-- DESPUÉS:
CAST(SUM(campo_monetario) AS DECIMAL(15,2)) AS total
```

#### 2. Formato Excel Sin Redondeo Visual
```php
// ANTES:
$activa->setCellValue('C' . $i, $valor_monetario);

// DESPUÉS:
$activa->setCellValue('C' . $i, $valor_monetario);
$activa->getStyle('C' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
```

#### 3. Formato PDF con Decimales Completos
```php
// ANTES:
number_format($valor, 0, '.', ',')  // Sin decimales

// DESPUÉS:
number_format($valor, 2, '.', ',')  // Con 2 decimales
```

### C. **Corrección de Cálculos de Porcentajes**

#### 1. Porcentajes Basados en Valores Correctos
```php
// ANTES (Incorrecto):
$porcentaje = ($cantidad_registros / $total_registros) * 100;

// DESPUÉS (Correcto):
$porcentaje = ($valor_monetario / $total_monetario) * 100;
```

#### 2. Formato de Porcentajes en Excel
```php
// ANTES:
$activa->setCellValue('D' . $i, number_format($porcentaje, 2) . '%');

// DESPUÉS:
$activa->setCellValue('D' . $i, $porcentaje / 100);  // Como decimal
$activa->getStyle('D' . $i)->getNumberFormat()->setFormatCode('0.00%');
```

## Implementación Paso a Paso

### **Paso 1: Revisar Consulta SQL Principal**

1. **Identificar campos de saldo de capital**
2. **Aplicar CAST para precisión decimal**
3. **Unificar lógica de "mayor que 0"**

```sql
-- Plantilla para campo de saldo:
CAST(
    CASE 
        WHEN (campo_capital - IFNULL(pagos.sum_pagado, 0)) > 0 
        THEN (campo_capital - IFNULL(pagos.sum_pagado, 0)) 
        ELSE 0 
    END AS DECIMAL(15,2)
) AS saldo_real
```

### **Paso 2: Estandarizar Subconsultas de Pagos**

```sql
-- Plantilla subconsulta pagos:
LEFT JOIN (
    SELECT 
        campo_referencia,
        CAST(SUM(campo_pago) AS DECIMAL(15,2)) AS sum_pagado
    FROM tabla_pagos
    WHERE fecha <= ? AND estado_activo = 'S'
    GROUP BY campo_referencia
) AS pagos ON pagos.campo_referencia = tabla_principal.campo_referencia
```

### **Paso 3: Refactorizar Función Excel**

```php
// 1. Calcular totales una sola vez:
$total_cantidad = array_sum(array_column($registro, 'cantidad'));
$total_saldo = array_sum(array_column($registro, 'saldo_real'));

// 2. En el loop de datos:
while ($fila < count($registro)) {
    $saldo = $registro[$fila]["saldo_real"];
    $porcentaje = ($total_saldo != 0) ? ($saldo / $total_saldo) * 100 : 0;
    
    // Valor exacto sin redondeo:
    $activa->setCellValue('C' . $i, $saldo);
    $activa->getStyle('C' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // Porcentaje como decimal:
    $activa->setCellValue('D' . $i, $porcentaje / 100);
    $activa->getStyle('D' . $i)->getNumberFormat()->setFormatCode('0.00%');
    
    $fila++;
    $i++;
}
```

### **Paso 4: Refactorizar Función PDF**

```php
// En el loop de datos:
while ($fila < count($registro)) {
    $saldo = $registro[$fila]["saldo_real"];  // Usar valor directo del SQL
    $porcentaje = ($total_saldo != 0) ? ($saldo / $total_saldo) * 100 : 0;
    
    // Mostrar con 2 decimales:
    $pdf->Cell(20, 4, number_format($saldo, 2, '.', ','), 0, 0, 'R');
    $pdf->Cell(15, 4, number_format($porcentaje, 2, '.', ',') . '%', 0, 1, 'R');
    
    $fila++;
}
```

### **Paso 5: Eliminar Doble Procesamiento en PHP**

```php
// ANTES (Doble procesamiento):
$saldo_calculado = $monto - $pagado;
$saldo_final = ($saldo_calculado > 0) ? $saldo_calculado : 0;

// DESPUÉS (Usar valor directo del SQL):
$saldo_final = $registro[$fila]["saldo_real"];  // Ya procesado en SQL
```

## Checklist de Validación

### ✅ **Consistencia entre Reportes**
- [ ] Misma consulta SQL para cálculos de saldo
- [ ] Misma subconsulta para pagos/movimientos
- [ ] Mismos filtros aplicados (fechas, estados, etc.)

### ✅ **Precisión Decimal**
- [ ] CAST(... AS DECIMAL(15,2)) en campos monetarios
- [ ] Formato '#,##0.00' en Excel para valores monetarios
- [ ] number_format(..., 2, '.', ',') en PDF

### ✅ **Cálculos de Porcentajes**
- [ ] Basados en valores monetarios, no en cantidades
- [ ] Formato '0.00%' en Excel (como decimal)
- [ ] Calculados una sola vez con totales correctos

### ✅ **Eliminación de Aproximaciones**
- [ ] Sin redondeos intermedios en PHP
- [ ] Valores directos desde SQL sin reprocesar
- [ ] Formatos que muestran precisión completa

## Plantilla de Implementación

```php
// 1. SQL con precisión:
$query = "SELECT 
    campo_agrupacion,
    COUNT(*) AS cantidad,
    CAST(SUM(CASE WHEN saldo > 0 THEN saldo ELSE 0 END) AS DECIMAL(15,2)) AS total_saldo
    FROM tabla_principal p
    LEFT JOIN subconsulta_pagos sp ON sp.ref = p.ref
    WHERE condiciones
    GROUP BY campo_agrupacion";

// 2. Función Excel:
function generarExcel($datos) {
    $total_global = array_sum(array_column($datos, 'total_saldo'));
    
    foreach ($datos as $fila) {
        $porcentaje = ($total_global != 0) ? ($fila['total_saldo'] / $total_global) * 100 : 0;
        
        $excel->setCellValue('A' . $i, $fila['campo_agrupacion']);
        $excel->setCellValue('B' . $i, $fila['total_saldo']);
        $excel->getStyle('B' . $i)->getNumberFormat()->setFormatCode('#,##0.00');
        $excel->setCellValue('C' . $i, $porcentaje / 100);
        $excel->getStyle('C' . $i)->getNumberFormat()->setFormatCode('0.00%');
    }
}

// 3. Función PDF:
function generarPDF($datos) {
    $total_global = array_sum(array_column($datos, 'total_saldo'));
    
    foreach ($datos as $fila) {
        $porcentaje = ($total_global != 0) ? ($fila['total_saldo'] / $total_global) * 100 : 0;
        
        $pdf->Cell(30, 4, $fila['campo_agrupacion'], 0, 0, 'L');
        $pdf->Cell(25, 4, number_format($fila['total_saldo'], 2, '.', ','), 0, 0, 'R');
        $pdf->Cell(20, 4, number_format($porcentaje, 2, '.', ',') . '%', 0, 1, 'R');
    }
}
```

## Notas Importantes

1. **Backup**: Siempre respaldar reportes antes de modificar
2. **Pruebas**: Comparar totales antes y después de cambios
3. **Documentación**: Actualizar documentación de cada reporte modificado
4. **Consistencia**: Aplicar todos los cambios en reportes relacionados simultáneamente

Esta refactorización garantiza que todos los reportes muestren valores exactos y consistentes, eliminando discrepancias causadas por aproximaciones o cálculos inconsistentes.