# 📂 Formatos Personalizados de Reportes

Esta carpeta contiene las clases de formato personalizado para cada institución.

## 📖 ¿Qué hay aquí?

- **BaseFormato.php**: Clase base con helpers comunes
- **[Módulo]/[Institución]/[TipoReporte].php**: Formatos personalizados

## 🎨 Estructura Organizacional

```
Formatos/
├── BaseFormato.php              ← Clase base (NO MODIFICAR)
├── Seguros/                     ← Módulo de Seguros
│   ├── InstitucionEjemplo/      ← Ejemplo de referencia
│   │   └── ContratoRenovacion.php
│   ├── CooperativaA/            ← Cooperativa A
│   │   ├── ContratoRenovacion.php
│   │   └── ReciboPago.php
│   └── CooperativaB/            ← Cooperativa B
│       └── ContratoRenovacion.php
├── Creditos/                    ← Módulo de Créditos
│   └── InstitucionEjemplo/
│       └── SolicitudCredito.php
└── Ahorros/                     ← Módulo de Ahorros
    └── InstitucionEjemplo/
        └── EstadoCuenta.php
```

## 🚀 Crear Nuevo Formato

### 1. Crear carpeta de tu institución

```bash
Formatos/[Modulo]/[TuInstitucion]/
```

Ejemplo:
```bash
Formatos/Seguros/CooperativaSanJuan/
```

### 2. Copiar archivo de ejemplo

Copia el archivo de `InstitucionEjemplo` como base:

```bash
cp Seguros/InstitucionEjemplo/ContratoRenovacion.php \
   Seguros/CooperativaSanJuan/ContratoRenovacion.php
```

### 3. Modificar namespace

Abre el archivo y cambia el namespace:

```php
<?php
// ANTES:
namespace Micro\Controllers\Reportes\Formatos\Seguros\InstitucionEjemplo;

// DESPUÉS:
namespace Micro\Controllers\Reportes\Formatos\Seguros\CooperativaSanJuan;
```

### 4. Personalizar el método generar()

Modifica los métodos privados según las necesidades de tu institución:

```php
public function generar($id)
{
    // 1. Obtener datos
    $datos = $this->obtenerRenovacion($id);
    
    // 2. Crear PDF
    $pdf = $this->crearPDFBase('P', 'Letter');
    $pdf->AddPage();
    
    // 3. Encabezado (personaliza esto)
    $this->generarEncabezadoPersonalizado($pdf, $info);
    
    // 4. Cuerpo (personaliza esto)
    $this->generarCuerpoPersonalizado($pdf, $datos, $info);
    
    // 5. Pie
    $this->generarPieComprobante($pdf, 'Tu mensaje');
    
    // 6. Retornar
    return $this->generarRespuestaPDF($pdf, $id);
}
```

### 5. Registrar en base de datos

```sql
INSERT INTO tb_documentos (id_reporte, nombre, clase_formato, estado) VALUES
(101, 'Contrato Renovación - Cooperativa San Juan', 
 'Micro\\Controllers\\Reportes\\Formatos\\Seguros\\CooperativaSanJuan\\ContratoRenovacion',
 1);
```

## 🛠️ Helpers Disponibles (de BaseFormato)

### Gestión de PDF

```php
// Crear PDF base
$pdf = $this->crearPDFBase('P', 'Letter');
// Opciones: P/L (Portrait/Landscape), Letter/Legal/A4
```

### Información Institucional

```php
// Obtener datos de la institución
$info = $this->getInfoInstitucion();
// Retorna: nom_agencia, nomb_comple, muni_lug, emai, tel_1, tel_2, nit, log_img
```

### Encabezados y Pies

```php
// Encabezado estándar con logo
$this->generarEncabezadoInstitucional($pdf, 'TÍTULO DEL REPORTE', $info);

// Pie de página estándar
$this->generarPieComprobante($pdf, 'Texto del pie');
```

### Formato y Conversión

```php
// Formatear moneda
$montoFormateado = $this->formatoMoneda(1500.50);
// Resultado: "Q1,500.50" o similar

// Decodificar UTF-8 para FPDF
$texto = $this->decode('Ñoño García');
```

### Respuestas

```php
// Respuesta JSON estándar
return $this->jsonResponse([
    'status' => 1,
    'mensaje' => 'Éxito',
    'data' => $datos
]);
```

## 📋 Plantilla Básica

```php
<?php

namespace Micro\Controllers\Reportes\Formatos\[Modulo]\[TuInstitucion];

use Exception;
use Micro\Controllers\Reportes\Formatos\BaseFormato;
use Micro\Helpers\Log;
// ... otros imports necesarios

class [NombreReporte] extends BaseFormato
{
    public function generar($id)
    {
        try {
            // 1. Validar ID
            if (!$id) {
                return $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'ID no proporcionado'
                ]);
            }

            // 2. Obtener datos
            $datos = $this->obtenerDatos($id);
            if (!$datos) {
                return $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'Datos no encontrados'
                ]);
            }

            // 3. Obtener info institución
            $info = $this->getInfoInstitucion();

            // 4. Crear PDF
            $pdf = $this->crearPDFBase('P', 'Letter');
            $pdf->AddPage();

            // 5. Generar contenido
            $this->generarEncabezadoPersonalizado($pdf, $info);
            $this->generarCuerpoPersonalizado($pdf, $datos);
            $this->generarPieComprobante($pdf, 'Mensaje');

            // 6. Retornar respuesta
            return $this->generarRespuestaPDF($pdf, $id);

        } catch (Exception $e) {
            $codigo = Log::errorWithCode(
                $e->getMessage(),
                __FILE__,
                __LINE__,
                $e->getFile(),
                $e->getLine()
            );
            return $this->jsonResponse([
                'status' => 0,
                'mensaje' => "Error. Código: $codigo"
            ]);
        }
    }

    private function obtenerDatos($id)
    {
        // Tu lógica aquí
    }

    private function generarEncabezadoPersonalizado($pdf, $info)
    {
        // Tu diseño aquí
    }

    private function generarCuerpoPersonalizado($pdf, $datos)
    {
        // Tu diseño aquí
    }

    private function generarRespuestaPDF($pdf, $id)
    {
        ob_start();
        $pdf->Output('I', 'reporte.pdf');
        $pdfData = ob_get_contents();
        ob_end_clean();

        return $this->jsonResponse([
            'status' => 1,
            'mensaje' => 'Reporte generado',
            'namefile' => "reporte_{$id}.pdf",
            'tipo' => 'pdf',
            'data' => base64_encode($pdfData)
        ]);
    }
}
```

## ⚠️ Reglas Importantes

1. **NO modificar `BaseFormato.php`** - Es compartido por todos
2. **Usar namespace correcto** - Debe coincidir con la ruta
3. **Implementar método `generar()`** - Es obligatorio
4. **Manejar errores** - Usar try-catch y Log
5. **Retornar formato estándar** - Usar jsonResponse()
6. **Documentar personalizaciones** - Comentarios claros

## 🔍 Testing

Para probar tu formato:

```php
// En tu controlador
$this->idDocument = 5; // ID de tb_documentos

// O desde frontend
fetch('/api/reporte', {
    method: 'POST',
    body: JSON.stringify({
        id: 123,
        idDocument: 5
    })
});
```

## 📚 Documentación Completa

Ver: `/docs/SISTEMA_FORMATOS_PERSONALIZADOS.md`

## 🆘 Soporte

Para dudas o problemas:
1. Revisar `InstitucionEjemplo/ContratoRenovacion.php`
2. Consultar documentación completa
3. Revisar logs en caso de errores
