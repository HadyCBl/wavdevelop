# 🎨 Sistema de Reportes con Encabezado y Pie Base

Sistema modular para generar reportes PDF y Excel con **encabezado y pie profesional** reutilizable, permitiendo personalizar el cuerpo según necesidades.

---

## ✨ Características

- ✅ **Encabezado profesional** automático (logo, institución, filtros)
- ✅ **Pie de página** con numeración (Página X de Y)
- ✅ **Diseño consistente** basado en `estado_cuenta_apr.php`
- ✅ **Personalización fácil** del cuerpo del reporte
- ✅ **Soporte PDF y Excel** con mismo estilo
- ✅ **Respuesta JSON** estándar para descarga

---

## 🚀 Uso Rápido

### 1. Crear Configuración

```php
<?php
namespace App\Controllers\Reportes\Config;

class MiReporteConfig extends BaseReporteConfig
{
    public function getTitulo(): string {
        return 'MI REPORTE';
    }
    
    public function getColumnas(): array {
        return [
            'codigo' => ['titulo' => 'Codigo', 'ancho' => 25, 'tipo' => 'texto'],
            'nombre' => ['titulo' => 'Nombre', 'ancho' => 70, 'tipo' => 'texto'],
            'monto' => ['titulo' => 'Monto', 'ancho' => 30, 'tipo' => 'moneda']
        ];
    }
    
    public function getQuery(): string {
        return "SELECT codigo, nombre, monto FROM tabla WHERE estado = ?";
    }
    
    public function getCamposRequeridos(): array {
        return [];
    }
}
```

### 2. Crear Controlador Simple

```php
<?php
namespace App\Controllers\Reportes;

class MiReporteController extends BaseReporteController
{
    public function generar()
    {
        try {
            $this->validarSesion();
            
            $config = new MiReporteConfig();
            $datos = $this->database->getAllResults($config->getQuery(), ['activo']);
            
            $tipo = $_POST['tipo'] ?? 'pdf';
            $filtros = ['estado' => 'activo'];
            
            // Genera reporte con encabezado/pie automático
            return ($tipo === 'pdf') 
                ? $this->exportarPDFConConfig($datos, $config, $filtros)
                : $this->exportarExcelConConfig($datos, $config, $filtros);
                
        } catch (\Exception $e) {
            return $this->jsonResponse(['status' => 0, 'mensaje' => $e->getMessage()]);
        }
    }
}
```

### 3. Ruta API

```php
// api/routes.php
$r->addRoute('POST', '/api/reportes/mi-reporte', [
    'App\Controllers\Reportes\MiReporteController', 
    'generar'
]);
```

---

## 🎯 Personalización Avanzada

Si necesitas **personalizar completamente el cuerpo**:

```php
public function reportePersonalizado()
{
    $config = new MiReporteConfig();
    $info = $this->getInfoInstitucion();
    
    // Crear PDF con encabezado/pie base
    $pdf = $this->crearPDFBase($config, $filtros, $info);
    $pdf->AddPage();
    
    // ✨ PERSONALIZAR AQUÍ ✨
    
    // Sección 1
    $pdf->SetFont('Arial', 'B', 10);
    $pdf->Cell(0, 7, 'MI SECCION PERSONALIZADA', 0, 1);
    
    // Tabla personalizada
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(52, 73, 94);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(60, 7, 'Columna 1', 1, 0, 'C', true);
    $pdf->Cell(60, 7, 'Columna 2', 1, 1, 'C', true);
    
    // Datos...
    
    return $this->generarRespuestaPDF($pdf, $config);
}
```

---

## 📦 Métodos Disponibles

| Método | Descripción |
|--------|-------------|
| `crearPDFBase($config, $filtros, $info)` | PDF con encabezado/pie |
| `generarEncabezadoExcel($sheet, $config, $filtros, $info)` | Encabezado Excel |
| `generarRespuestaPDF($pdf, $config)` | Respuesta JSON base64 |
| `generarRespuestaExcel($spreadsheet, $config)` | Respuesta JSON base64 |
| `getInfoInstitucion()` | Info institución/agencia |
| `validarSesion()` | Validar sesión |

---

## 📄 Respuesta JSON

```json
{
    "status": 1,
    "mensaje": "Reporte generado correctamente",
    "namefile": "mi_reporte_20231119",
    "tipo": "pdf",
    "data": "data:application/pdf;base64,..."
}
```

**JavaScript descarga:**
```javascript
const link = document.createElement('a');
link.href = response.data;
link.download = response.namefile + '.' + response.tipo;
link.click();
```

---

## 📚 Documentación Completa

- **[Guía de Uso Detallada](docs/uso_reportes_base.md)** - Ejemplos completos
- **[Arquitectura](docs/arquitectura_reportes_escalable.md)** - Diseño del sistema
- **[Personalización](docs/personalizacion_reportes.md)** - Opciones avanzadas

---

## 🎨 Paleta de Colores

```php
// Azul principal (títulos)
$pdf->SetFillColor(41, 128, 185);  // #2980B9

// Gris oscuro (headers)
$pdf->SetFillColor(52, 73, 94);    // #34495E

// Gris claro (fondo)
$pdf->SetFillColor(236, 240, 241); // #ECF0F1
```

---

## 📁 Ejemplos Incluidos

1. **`EjemploSimpleController.php`** - Reporte tabla simple
2. **`EjemploPersonalizadoController.php`** - Múltiples secciones personalizadas

---

## ✅ Ventajas

- ✅ **Sin código duplicado** - Encabezados/pies centralizados
- ✅ **Consistencia visual** - Mismo diseño en todos los reportes
- ✅ **Fácil mantenimiento** - Cambios en un solo lugar
- ✅ **Flexible** - Personaliza lo que necesites
- ✅ **Profesional** - Diseño moderno y limpio

---

**Desarrollado para:** Sistema de Microsistema  
**Estilo base:** `estado_cuenta_apr.php`  
**Soporte:** PDF (FPDF) y Excel (PhpSpreadsheet)
