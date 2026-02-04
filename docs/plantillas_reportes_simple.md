# 🎨 Plantillas Simplificadas para Reportes

## ✨ Descripción

Métodos ultra-simplificados para generar reportes PDF y Excel. **Solo personalizas el cuerpo**, el encabezado y pie son automáticos.

---

## 🚀 Uso Rápido

### 📄 PDF

```php
public function miReporte()
{
    $datos = $this->obtenerDatos();
    
    $filtros = [
        'Periodo' => '01/11/2025 al 30/11/2025',
        'Estado' => 'Activo'
    ];
    
    $response = $this->generarPlantillaPDF(
        'MI REPORTE',
        function($pdf, $datos) {
            // ✨ SOLO ESCRIBES ESTO - EL CUERPO ✨
            
            $pdf->SetFont('Arial', 'B', 8);
            $pdf->Cell(60, 7, 'Nombre', 1);
            $pdf->Cell(40, 7, 'Monto', 1, 1);
            
            foreach ($datos as $row) {
                $pdf->Cell(60, 6, $row['nombre'], 1);
                $pdf->Cell(40, 6, 'Q ' . number_format($row['monto'], 2), 1, 1);
            }
        },
        $datos,
        $filtros
    );
    
    return $this->jsonResponse($response);
}
```

### 📊 Excel

```php
public function miReporteExcel()
{
    $datos = $this->obtenerDatos();
    
    $filtros = ['Periodo' => '01/11/2025 al 30/11/2025'];
    
    $response = $this->generarPlantillaExcel(
        'MI REPORTE',
        function($sheet, $row, $datos) {
            // ✨ SOLO ESCRIBES ESTO - EL CUERPO ✨
            
            $sheet->setCellValue("A{$row}", 'Nombre');
            $sheet->setCellValue("B{$row}", 'Monto');
            $row++;
            
            foreach ($datos as $item) {
                $sheet->setCellValue("A{$row}", $item['nombre']);
                $sheet->setCellValue("B{$row}", $item['monto']);
                $row++;
            }
            
            return $row; // Retornar última fila usada
        },
        $datos,
        $filtros
    );
    
    return $this->jsonResponse($response);
}
```

---

## 📦 Métodos Disponibles

### `generarPlantillaPDF($titulo, $cuerpoPDF, $datos, $filtros)`

**Parámetros:**
- `$titulo` (string): Título del reporte
- `$cuerpoPDF` (callable): Función que recibe `($pdf, $datos)` y genera el cuerpo
- `$datos` (array): Datos del reporte
- `$filtros` (array): [Opcional] Filtros a mostrar en encabezado

**Retorna:** Array con estructura JSON (status, mensaje, namefile, tipo, data)

### `generarPlantillaExcel($titulo, $cuerpoExcel, $datos, $filtros)`

**Parámetros:**
- `$titulo` (string): Título del reporte
- `$cuerpoExcel` (callable): Función que recibe `($sheet, $row, $datos)` y retorna última fila
- `$datos` (array): Datos del reporte
- `$filtros` (array): [Opcional] Filtros a mostrar en encabezado

**Retorna:** Array con estructura JSON (status, mensaje, namefile, tipo, data)

---

## 🎨 Qué Incluye Automáticamente

### PDF:
- ✅ Línea decorativa azul superior
- ✅ Logo de la institución
- ✅ Nombre de institución y agencia
- ✅ Dirección, email, teléfono, NIT
- ✅ Fecha y usuario de generación
- ✅ Título del reporte con fondo azul
- ✅ Filtros con fondo gris
- ✅ Pie de página con número de página

### Excel:
- ✅ Nombre de institución (16pt, centrado)
- ✅ Nombre de agencia (12pt, centrado)
- ✅ Dirección y contacto
- ✅ Título con fondo azul (#3498DB)
- ✅ Filtros con fondo gris (#95A5A6)
- ✅ Auto-ajuste de columnas

---

## 💡 Ejemplos Completos

### Ejemplo 1: Tabla Simple

```php
$response = $this->generarPlantillaPDF(
    'LISTADO DE CLIENTES',
    function($pdf, $datos) {
        // Encabezados
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(40, 7, 'Codigo', 1, 0, 'C', true);
        $pdf->Cell(100, 7, 'Nombre', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Telefono', 1, 1, 'C', true);
        
        // Datos
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        
        foreach ($datos as $row) {
            $pdf->Cell(40, 6, $row['codigo'], 1);
            $pdf->Cell(100, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $row['nombre']), 1);
            $pdf->Cell(40, 6, $row['telefono'], 1, 1);
        }
    },
    $datos
);
```

### Ejemplo 2: Tabla con Totales

```php
$response = $this->generarPlantillaPDF(
    'CREDITOS DESEMBOLSADOS',
    function($pdf, $datos) {
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(60, 7, 'Cliente', 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Monto', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Fecha', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(0, 0, 0);
        $total = 0;
        
        foreach ($datos as $row) {
            $pdf->Cell(60, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $row['cliente']), 1);
            $pdf->Cell(40, 6, 'Q ' . number_format($row['monto'], 2), 1, 0, 'R');
            $pdf->Cell(30, 6, date('d/m/Y', strtotime($row['fecha'])), 1, 1, 'C');
            $total += $row['monto'];
        }
        
        // Total
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(60, 7, 'TOTAL:', 1, 0, 'R', true);
        $pdf->Cell(40, 7, 'Q ' . number_format($total, 2), 1, 0, 'R', true);
        $pdf->Cell(30, 7, '', 1, 1, 'C', true);
    },
    $datos,
    ['Periodo' => '01/11/2025 al 30/11/2025']
);
```

### Ejemplo 3: Excel con Formato

```php
$response = $this->generarPlantillaExcel(
    'REPORTE DE AHORROS',
    function($sheet, $row, $datos) {
        // Encabezados
        $headers = ['Codigo', 'Cliente', 'Saldo', 'Ultima Transaccion'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue("{$col}{$row}", $header);
            $sheet->getStyle("{$col}{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle("{$col}{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF34495E');
            $col++;
        }
        $row++;
        
        // Datos
        $total = 0;
        foreach ($datos as $item) {
            $sheet->setCellValue("A{$row}", $item['codigo']);
            $sheet->setCellValue("B{$row}", $item['cliente']);
            $sheet->setCellValue("C{$row}", $item['saldo']);
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
            $sheet->setCellValue("D{$row}", \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($item['fecha'])));
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $total += $item['saldo'];
            $row++;
        }
        
        // Total
        $sheet->setCellValue("A{$row}", 'TOTAL:');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("C{$row}", $total);
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        
        return $row;
    },
    $datos,
    ['Estado' => 'Activos']
);
```

---

## 🎯 Ventajas

- ✅ **Ultra-simple**: Solo 3 líneas de código base
- ✅ **Sin duplicación**: Encabezado y pie centralizados
- ✅ **Flexible**: Personaliza el cuerpo como quieras
- ✅ **Profesional**: Diseño consistente automático
- ✅ **Rápido**: Crea reportes en minutos

---

## 📁 Archivo de Ejemplo

Ver: `app/controllers/Reportes/EjemploPlantillaController.php`

---

**Desarrollado para:** Sistema Microsistema  
**Basado en:** estado_cuenta_apr.php
