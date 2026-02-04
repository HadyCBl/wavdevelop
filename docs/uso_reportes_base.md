# Guía de Uso: Sistema de Reportes Base

## 📋 Descripción General

El sistema de reportes base proporciona **encabezados y pies de página profesionales** reutilizables para PDF y Excel. El **cuerpo del reporte se personaliza** en cada controlador hijo.

**Estilo de referencia:** `estado_cuenta_apr.php`

---

## 🎨 Características del Diseño Base

### PDF
- ✅ **Encabezado profesional** con:
  - Línea decorativa superior (azul)
  - Logo de la institución
  - Información de la cooperativa/agencia
  - Fecha y usuario de generación
  - Título del reporte
  - Filtros/información adicional
- ✅ **Pie de página** con:
  - Línea decorativa
  - Número de página (Página X de Y)
  
### Excel
- ✅ **Encabezado profesional** con:
  - Nombre de institución (16pt, negrita, centrado)
  - Nombre de oficina (12pt, negrita)
  - Dirección y contacto
  - Título del reporte con fondo azul
  - Filtros con fondo gris

---

## 🚀 Uso Básico

### 1. Crear Configuración del Reporte

```php
<?php
namespace App\Controllers\Reportes\Config;

class MiReporteConfig extends BaseReporteConfig
{
    public function getQuery(): string
    {
        return "SELECT id, nombre, monto, fecha 
                FROM mi_tabla 
                WHERE estado = ?";
    }
    
    public function getColumnas(): array
    {
        return [
            'id' => [
                'titulo' => 'ID',
                'ancho' => 15,
                'alineacion' => 'center',
                'tipo' => 'texto'
            ],
            'nombre' => [
                'titulo' => 'Nombre',
                'ancho' => 60,
                'alineacion' => 'left',
                'tipo' => 'texto'
            ],
            'monto' => [
                'titulo' => 'Monto',
                'ancho' => 30,
                'alineacion' => 'right',
                'tipo' => 'moneda',
                'formato' => 'Q #,##0.00'
            ],
            'fecha' => [
                'titulo' => 'Fecha',
                'ancho' => 25,
                'alineacion' => 'center',
                'tipo' => 'fecha'
            ]
        ];
    }
    
    public function getTitulo(): string
    {
        return 'Mi Reporte Personalizado';
    }
    
    public function getCamposRequeridos(): array
    {
        return ['fecha_inicio', 'fecha_fin'];
    }
    
    // Opcional: Información adicional en el encabezado
    public function getInfoAdicional(array $filtros): array
    {
        return [
            'Periodo' => ($filtros['fecha_inicio'] ?? 'N/A') . ' al ' . ($filtros['fecha_fin'] ?? 'N/A'),
            'Estado' => $filtros['estado'] ?? 'Todos'
        ];
    }
}
```

### 2. Usar Reporte Simple (Con Encabezado/Pie Base)

```php
<?php
namespace App\Controllers\Reportes;

use App\Controllers\BaseReporteController;
use App\Controllers\Reportes\Config\MiReporteConfig;

class MiReporteController extends BaseReporteController
{
    public function generarReporte()
    {
        try {
            $this->validarSesion();
            
            // Obtener datos POST
            $filtros = [
                'fecha_inicio' => $_POST['fecha_inicio'] ?? null,
                'fecha_fin' => $_POST['fecha_fin'] ?? null,
                'estado' => $_POST['estado'] ?? 'activo'
            ];
            
            $tipo = $_POST['tipo'] ?? 'pdf'; // pdf o xlsx
            
            // Crear configuración
            $config = new MiReporteConfig();
            
            // Validar
            $this->validarDatos($filtros, $config->getCamposRequeridos());
            
            // Obtener datos
            $query = $config->getQuery();
            $params = [$filtros['estado']];
            $datos = $this->database->getAllResults($query, $params);
            
            // Generar reporte (usa encabezado/pie base)
            if ($tipo === 'pdf') {
                return $this->exportarPDFConConfig($datos, $config, $filtros);
            } else {
                return $this->exportarExcelConConfig($datos, $config, $filtros);
            }
            
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'status' => 0,
                'mensaje' => $e->getMessage()
            ]);
        }
    }
}
```

---

## 🎯 Personalización Avanzada del Cuerpo

Si necesitas **personalizar completamente el cuerpo** del reporte (más allá de una tabla simple):

### PDF Personalizado

```php
public function generarReporteComplejo()
{
    try {
        // ... validaciones y obtención de datos ...
        
        $config = new MiReporteConfig();
        
        // Obtener información institución
        $info = $this->getInfoInstitucion();
        
        // Crear PDF con encabezado/pie base
        $pdf = $this->crearPDFBase($config, $filtros, $info);
        $pdf->AddPage();
        $pdf->SetAutoPageBreak(true, 25);
        
        // ✨ PERSONALIZAR EL CUERPO AQUÍ ✨
        
        // Sección 1: Resumen
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(41, 128, 185);
        $pdf->Cell(0, 7, 'RESUMEN', 0, 1, 'L');
        $pdf->SetDrawColor(41, 128, 185);
        $pdf->Line(10, $pdf->GetY(), 206, $pdf->GetY());
        $pdf->Ln(3);
        
        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(50, 6, 'Total de registros:', 0, 0);
        $pdf->Cell(0, 6, count($datos), 0, 1);
        $pdf->Ln(5);
        
        // Sección 2: Tabla personalizada
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        
        $pdf->Cell(40, 7, 'Codigo', 1, 0, 'C', true);
        $pdf->Cell(80, 7, 'Descripcion', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Monto', 1, 0, 'C', true);
        $pdf->Cell(30, 7, 'Estado', 1, 1, 'C', true);
        
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(52, 73, 94);
        $fill = false;
        
        foreach ($datos as $row) {
            if ($fill) {
                $pdf->SetFillColor(245, 245, 245);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell(40, 6, $row['codigo'], 1, 0, 'C', $fill);
            $pdf->Cell(80, 6, iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $row['nombre']), 1, 0, 'L', $fill);
            $pdf->Cell(35, 6, 'Q ' . number_format($row['monto'], 2), 1, 0, 'R', $fill);
            $pdf->Cell(30, 6, $row['estado'], 1, 1, 'C', $fill);
            
            $fill = !$fill;
        }
        
        // Totales
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(52, 73, 94);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(120, 7, 'TOTAL:', 1, 0, 'R', true);
        $pdf->Cell(35, 7, 'Q ' . number_format(array_sum(array_column($datos, 'monto')), 2), 1, 0, 'R', true);
        $pdf->Cell(30, 7, '', 1, 1, 'C', true);
        
        // Generar respuesta
        return $this->generarRespuestaPDF($pdf, $config);
        
    } catch (\Exception $e) {
        return $this->jsonResponse(['status' => 0, 'mensaje' => $e->getMessage()]);
    }
}
```

### Excel Personalizado

```php
public function generarReporteExcelComplejo()
{
    try {
        // ... validaciones y obtención de datos ...
        
        $config = new MiReporteConfig();
        
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $info = $this->getInfoInstitucion();
        
        // Configurar documento
        $spreadsheet->getProperties()
            ->setCreator($info['nomb_comple'])
            ->setTitle($config->getTitulo());
        
        // Generar encabezado base
        $row = $this->generarEncabezadoExcel($sheet, $config, $filtros, $info);
        
        // ✨ PERSONALIZAR EL CUERPO AQUÍ ✨
        
        // Sección de resumen
        $sheet->mergeCells("A{$row}:D{$row}");
        $sheet->setCellValue("A{$row}", "RESUMEN");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFE8F8F5');
        $row++;
        
        $sheet->setCellValue("A{$row}", "Total de registros:");
        $sheet->getStyle("A{$row}")->getFont()->setBold(true);
        $sheet->setCellValue("B{$row}", count($datos));
        $row += 2;
        
        // Tabla de datos
        $sheet->setCellValue("A{$row}", "Codigo");
        $sheet->setCellValue("B{$row}", "Nombre");
        $sheet->setCellValue("C{$row}", "Monto");
        $sheet->setCellValue("D{$row}", "Fecha");
        
        $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $sheet->getStyle("A{$row}:D{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF34495E');
        $row++;
        
        foreach ($datos as $item) {
            $sheet->setCellValue("A{$row}", $item['codigo']);
            $sheet->setCellValue("B{$row}", $item['nombre']);
            $sheet->setCellValue("C{$row}", floatval($item['monto']));
            $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
            $sheet->setCellValue("D{$row}", \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel(strtotime($item['fecha'])));
            $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
            $row++;
        }
        
        // Total
        $sheet->setCellValue("A{$row}", "TOTAL:");
        $sheet->getStyle("A{$row}:B{$row}")->getFont()->setBold(true);
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", array_sum(array_column($datos, 'monto')));
        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');
        $sheet->getStyle("C{$row}")->getFont()->setBold(true);
        
        // Autoajustar columnas
        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Generar respuesta
        return $this->generarRespuestaExcel($spreadsheet, $config);
        
    } catch (\Exception $e) {
        return $this->jsonResponse(['status' => 0, 'mensaje' => $e->getMessage()]);
    }
}
```

---

## 📦 Métodos Reutilizables Disponibles

### BaseReporteController

| Método | Descripción |
|--------|-------------|
| `crearPDFBase($config, $filtros, $info)` | Crea PDF con encabezado/pie profesional |
| `generarEncabezadoExcel($sheet, $config, $filtros, $info)` | Genera encabezado Excel, retorna fila siguiente |
| `generarRespuestaPDF($pdf, $config)` | Convierte PDF a JSON base64 |
| `generarRespuestaExcel($spreadsheet, $config)` | Convierte Excel a JSON base64 |
| `getInfoInstitucion()` | Obtiene datos de institución/agencia |
| `validarSesion()` | Valida sesión activa |
| `validarDatos($datos, $requeridos)` | Valida campos requeridos |

---

## 🎨 Paleta de Colores (Estilo estado_cuenta_apr)

```php
// Azul principal (encabezados, títulos)
$pdf->SetFillColor(41, 128, 185);  // #2980B9

// Gris oscuro (tabla headers)
$pdf->SetFillColor(52, 73, 94);    // #34495E

// Gris claro (fondo encabezado)
$pdf->SetFillColor(236, 240, 241); // #ECF0F1

// Gris medio (subtítulos)
$pdf->SetFillColor(149, 165, 166); // #95A5A6

// Texto principal
$pdf->SetTextColor(52, 73, 94);    // #34495E

// Texto secundario
$pdf->SetTextColor(127, 140, 141); // #7F8C8D
```

---

## 📝 Ejemplo de Ruta API

```php
// api/routes.php
$r->addRoute('GET', '/api/reportes/mi-reporte', ['App\Controllers\Reportes\MiReporteController', 'generarReporte']);
```

---

## 🔧 Tips y Buenas Prácticas

1. **Siempre usa `iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $texto)` en PDF** para evitar problemas con acentos
2. **Formatea fechas en Excel** con `\PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel()`
3. **Usa `SetAutoPageBreak(true, 25)`** para que el pie de página tenga espacio
4. **Valida sesión y datos** antes de generar reportes
5. **Maneja excepciones** y retorna JSON con status 0 en caso de error
6. **Usa `ob_start()` y `ob_end_clean()`** para capturar output de PDF/Excel

---

## 📄 Respuesta JSON Estándar

```json
{
    "status": 1,
    "mensaje": "Reporte generado correctamente",
    "namefile": "mi_reporte_20231119",
    "tipo": "pdf",
    "data": "data:application/pdf;base64,JVBERi0xLj..."
}
```

**JavaScript (descarga):**
```javascript
if (response.status === 1) {
    const link = document.createElement('a');
    link.href = response.data;
    link.download = response.namefile + '.' + response.tipo;
    link.click();
}
```

---

## ✅ Ventajas del Sistema Base

- ✅ **Consistencia visual** en todos los reportes
- ✅ **Encabezados/pies profesionales** sin código repetido
- ✅ **Fácil personalización** del cuerpo
- ✅ **Información institucional** automática
- ✅ **Manejo de errores** estandarizado
- ✅ **Respuestas JSON** uniformes para descarga
- ✅ **Soporte PDF y Excel** con el mismo estilo
