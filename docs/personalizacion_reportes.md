# 🎨 Personalización de Reportes - 3 Estrategias

## Problema: Cada Reporte es Diferente

Cada reporte tiene sus propias necesidades:
- ✅ Columnas diferentes
- ✅ Formato personalizado (moneda, fechas, porcentajes)
- ✅ Totales y subtotales
- ✅ Encabezados personalizados
- ✅ Anchos de columna específicos
- ✅ Orientación (vertical/horizontal)
- ✅ Logos, firmas, sellos

## 🎯 Estrategia 1: Clases de Configuración ⭐ (RECOMENDADA)

### Ventajas
- ✅ **Máxima personalización** sin duplicar código
- ✅ **Tipo seguro** (PHP con clases)
- ✅ **Reutilizable** (misma config para Excel y PDF)
- ✅ **Mantenible** (cambiar config sin tocar lógica)
- ✅ **Testeable** (fácil de probar)

### Estructura

```
app/controllers/Reportes/Config/
├── BaseReporteConfig.php           # Clase base abstracta
├── VisitasPrepagoConfig.php        # Config específica
├── CreditosDesembolsadosConfig.php # Config específica
└── ... (una clase por reporte complejo)
```

### Ejemplo Completo

```php
// Config del reporte
class VisitasPrepagoConfig extends BaseReporteConfig
{
    // Query SQL
    public function getQuery(): string
    {
        return "SELECT campos FROM tablas WHERE condiciones";
    }
    
    // Validaciones
    public function getCamposRequeridos(): array
    {
        return ['fecha_inicio', 'fecha_fin'];
    }
    
    // Título
    public function getTitulo(): string
    {
        return 'REPORTE DE VISITAS PREPAGO';
    }
    
    // Definición de columnas (lo más importante)
    public function getColumnas(): array
    {
        return [
            'cuenta' => [
                'titulo' => 'No. Cuenta',
                'ancho' => 15,              // Para Excel
                'alineacion' => 'center',   // left, center, right
                'tipo' => 'texto'           // texto, fecha, moneda, numero, porcentaje
            ],
            'fecha' => [
                'titulo' => 'Fecha',
                'ancho' => 15,
                'alineacion' => 'center',
                'tipo' => 'fecha',
                'formato' => 'd/m/Y'        // Formato de fecha
            ],
            'saldo' => [
                'titulo' => 'Saldo',
                'ancho' => 15,
                'alineacion' => 'right',
                'tipo' => 'moneda',
                'formato' => 'Q #,##0.00'   // Formato para Excel
            ],
            'tasa' => [
                'titulo' => 'Tasa',
                'ancho' => 10,
                'alineacion' => 'center',
                'tipo' => 'porcentaje',
                'formato' => '0.00%'
            ]
        ];
    }
    
    // Totales
    public function tieneTotales(): bool
    {
        return true;
    }
    
    public function getColumnasTotales(): array
    {
        return ['saldo', 'capital', 'interes'];
    }
    
    // Subtotales por grupo
    public function tieneSubtotales(): bool
    {
        return true;
    }
    
    public function getColumnaAgrupacion(): ?string
    {
        return 'agencia'; // Agrupar por agencia
    }
    
    // Orientación PDF
    public function getOrientacionPDF(): string
    {
        return 'L'; // L=horizontal, P=vertical
    }
    
    // Info adicional
    public function getInfoAdicional(array $filtros): array
    {
        return [
            'Período' => date('d/m/Y', strtotime($filtros['fecha_inicio'])) . 
                        ' al ' . date('d/m/Y', strtotime($filtros['fecha_fin'])),
            'Usuario' => $_SESSION['nombre'] ?? 'N/A'
        ];
    }
    
    // Procesar datos antes de exportar (opcional)
    public function procesarDatos(array $datos): array
    {
        foreach ($datos as &$row) {
            // Agregar campo calculado
            $row['total'] = $row['capital'] + $row['interes'];
            
            // Formatear texto
            $row['nombre'] = strtoupper($row['nombre']);
            
            // Cualquier transformación
        }
        return $datos;
    }
}
```

### Uso en el Controlador

```php
class CreditoReporteController extends BaseReporteController
{
    public function visitasPrepago()
    {
        $config = new VisitasPrepagoConfig();
        return $this->generarReporteConConfig($config);
    }
}
```

**¡Solo 3 líneas!** El `BaseReporteController` hace todo automáticamente:
- ✅ Lee la configuración
- ✅ Genera Excel con formato perfecto
- ✅ Genera PDF con layout profesional
- ✅ Aplica totales y subtotales
- ✅ Formatea columnas correctamente

---

## 🎯 Estrategia 2: Sobrescribir Métodos de Exportación

Para reportes con layout MUY específico, sobrescribe los métodos de exportación.

### Ejemplo

```php
class CreditoReporteController extends BaseReporteController
{
    public function visitasPrepago()
    {
        $config = new VisitasPrepagoConfig();
        return $this->generarReporteConConfig($config);
    }
    
    // Sobrescribir solo si necesitas TOTAL personalización
    protected function exportarExcelConConfig(array $datos, BaseReporteConfig $config, array $filtros)
    {
        // Tu implementación 100% personalizada
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Logo de la empresa
        $drawing = new \PhpOffice\PhpSpreadsheet\Worksheet\Drawing();
        $drawing->setPath('path/to/logo.png');
        $drawing->setCoordinates('A1');
        $drawing->setHeight(50);
        $drawing->setWorksheet($sheet);
        
        // Encabezado super personalizado
        $sheet->setCellValue('A5', 'MI REPORTE ESPECIAL');
        $sheet->mergeCells('A5:K5');
        
        // Tu formato específico de datos
        $fila = 10;
        foreach ($datos as $row) {
            // Tu lógica personalizada...
        }
        
        // Firmas, sellos, etc.
        $sheet->setCellValue('A100', '___________________');
        $sheet->setCellValue('A101', 'Gerente General');
        
        // Generar
        ob_start();
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();
        
        return $this->jsonResponse([
            'status' => 1,
            'tipo' => 'xlsx',
            'archivo' => base64_encode($xlsData),
            'nombre' => 'mi_reporte_especial.xlsx'
        ]);
    }
}
```

---

## 🎯 Estrategia 3: Forma Simple (Sin Config Class)

Para reportes muy simples, sin totales ni formato especial.

### Ejemplo

```php
class CreditoReporteController extends BaseReporteController
{
    public function listadoSimple()
    {
        return $this->generarReporte([
            'query' => "SELECT id, nombre, fecha FROM tabla",
            'validaciones' => ['fecha_inicio'],
            'exportadores' => ['xlsx', 'json'],
            'nombre' => 'listado_simple'
        ]);
    }
}
```

Genera Excel básico automáticamente (sin formato especial).

---

## 📊 ¿Cuándo Usar Cada Estrategia?

| Estrategia | Cuándo Usar | Complejidad | Personalización |
|------------|-------------|-------------|-----------------|
| **Config Class** | Reportes con formato, totales, subtotales | Media | Alta |
| **Sobrescribir Métodos** | Layout MUY específico, logos, firmas | Alta | Máxima |
| **Forma Simple** | Listados básicos, sin formato | Baja | Mínima |

## 💡 Ejemplos de Casos Reales

### Caso 1: Reporte con Subtotales por Agencia

```php
class VisitasPrepagoConfig extends BaseReporteConfig
{
    public function tieneSubtotales(): bool
    {
        return true; // ✅ Activar subtotales
    }
    
    public function getColumnaAgrupacion(): ?string
    {
        return 'agencia'; // 📊 Agrupar por esta columna
    }
    
    public function getColumnasTotales(): array
    {
        return ['saldo', 'capital', 'interes']; // 💰 Totalizar estas
    }
}
```

**Resultado en Excel:**
```
AGENCIA CENTRAL
  Cuenta  Cliente  Saldo    Capital   Interés
  001     Juan     5000.00  4500.00   500.00
  002     María    3000.00  2800.00   200.00
  Subtotal AGENCIA CENTRAL:  8000.00  7300.00  700.00

AGENCIA NORTE
  Cuenta  Cliente  Saldo    Capital   Interés
  003     Pedro    2000.00  1900.00   100.00
  Subtotal AGENCIA NORTE:    2000.00  1900.00  100.00

TOTAL GENERAL:               10000.00 9200.00  800.00
```

### Caso 2: Reporte con Múltiples Formatos de Columna

```php
public function getColumnas(): array
{
    return [
        'fecha' => [
            'titulo' => 'Fecha',
            'tipo' => 'fecha',
            'formato' => 'd/m/Y'  // 📅 dd/mm/yyyy
        ],
        'monto' => [
            'titulo' => 'Monto',
            'tipo' => 'moneda',
            'formato' => 'Q #,##0.00'  // 💰 Q 1,234.56
        ],
        'tasa' => [
            'titulo' => 'Tasa',
            'tipo' => 'porcentaje',
            'formato' => '0.00%'  // 📊 12.50%
        ],
        'dias' => [
            'titulo' => 'Días',
            'tipo' => 'numero',
            'formato' => '0'  // 🔢 sin decimales
        ]
    ];
}
```

### Caso 3: Procesar Datos Antes de Exportar

```php
public function procesarDatos(array $datos): array
{
    foreach ($datos as &$row) {
        // Agregar campo calculado
        $row['total_a_pagar'] = $row['capital'] + $row['interes'] + $row['mora'];
        
        // Formatear texto
        $row['nombre'] = mb_strtoupper($row['nombre'], 'UTF-8');
        
        // Agregar semáforo
        if ($row['dias_mora'] > 30) {
            $row['estado'] = '🔴 VENCIDO';
        } elseif ($row['dias_mora'] > 0) {
            $row['estado'] = '🟡 MORA';
        } else {
            $row['estado'] = '🟢 AL DÍA';
        }
        
        // Formatear moneda en PHP (para PDF)
        $row['saldo_fmt'] = 'Q ' . number_format($row['saldo'], 2);
    }
    
    // Ordenar resultados
    usort($datos, function($a, $b) {
        return $b['dias_mora'] <=> $a['dias_mora'];
    });
    
    return $datos;
}
```

### Caso 4: Información Adicional Dinámica

```php
public function getInfoAdicional(array $filtros): array
{
    $info = [
        'Período' => date('d/m/Y', strtotime($filtros['fecha_inicio'])) . 
                    ' al ' . date('d/m/Y', strtotime($filtros['fecha_fin'])),
        'Fecha de generación' => date('d/m/Y H:i:s'),
        'Usuario' => $_SESSION['nombre_completo'] ?? 'Sistema'
    ];
    
    // Info condicional
    if ($filtros['filter_type'] === 'office') {
        $agencia = $this->getAgenciaInfo($filtros['id_agencia']);
        $info['Agencia'] = $agencia['nom_agencia'] ?? 'N/A';
    }
    
    if ($filtros['filter_type'] === 'executive') {
        $usuario = $this->getUserInfo($filtros['id_usuario']);
        $info['Ejecutivo'] = ($usuario['nombre'] ?? '') . ' ' . ($usuario['apellido'] ?? '');
    }
    
    return $info;
}
```

---

## 🚀 Migrar Reporte Existente

### Antes (reporte002.php - legacy)

```php
// 500+ líneas de código mezclado
switch ($condi) {
    case 'reportePrepago':
        // HTML mezclado con PHP
        // Query directo
        // Sin reutilización
        break;
}
```

### Después (Con Config Class)

**1. Crear Config**
```php
// app/controllers/Reportes/Config/VisitasPrepagoConfig.php
class VisitasPrepagoConfig extends BaseReporteConfig {
    // Definir columnas, query, totales
}
```

**2. Usar en Controlador**
```php
// app/controllers/Reportes/CreditoReporteController.php
public function visitasPrepago() {
    $config = new VisitasPrepagoConfig();
    return $this->generarReporteConConfig($config);
}
```

**3. Agregar Ruta**
```php
// api/routes.php
$r->addRoute('POST', '/visitas-prepago', 'CreditoReporteController@visitasPrepago');
```

**4. Llamar desde JS**
```javascript
await creditoAPI.visitasPrepago({
    fecha_inicio: '2025-01-01',
    fecha_fin: '2025-12-31',
    tipo: 'xlsx'
});
```

---

## ✅ Checklist de Personalización

Cuando crees un nuevo reporte, define:

- [ ] **Query SQL** con placeholders
- [ ] **Campos requeridos** para validación
- [ ] **Título del reporte**
- [ ] **Columnas** con tipo, ancho, alineación, formato
- [ ] **¿Tiene totales?** Si sí, ¿cuáles columnas?
- [ ] **¿Tiene subtotales?** Si sí, ¿por qué columna agrupar?
- [ ] **Orientación PDF** (vertical/horizontal)
- [ ] **Información adicional** (período, usuario, etc.)
- [ ] **¿Necesita procesamiento?** (campos calculados, formato especial)

---

## 🎨 Resultado Final

Con esta arquitectura puedes crear reportes 100% personalizados en **minutos**, no horas:

✅ **Excel profesional** con formato automático  
✅ **PDF con layout perfecto**  
✅ **Totales y subtotales** automáticos  
✅ **Formato de columnas** (moneda, fecha, %)  
✅ **Sin duplicar código**  
✅ **Fácil de mantener**  

**¿Necesitas un reporte nuevo? Solo crea una clase de configuración.** 🚀
