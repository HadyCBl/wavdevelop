# 🏗️ Arquitectura de Reportes Escalable

## 📁 Estructura por Controladores Especializados

```
www/
├── app/
│   └── controllers/
│       ├── BaseReporteController.php           # ⭐ Controlador base (lógica común)
│       └── Reportes/
│           ├── CreditoReporteController.php    # Reportes de créditos (6 métodos)
│           ├── AhorroReporteController.php     # Reportes de ahorros (3 métodos)
│           ├── ContabilidadReporteController.php # Reportes contables (3 métodos)
│           ├── ClienteReporteController.php    # (Futuro) Reportes de clientes
│           └── CajaReporteController.php       # (Futuro) Reportes de caja
├── api/
│   ├── routes.php                              # Rutas agrupadas por módulo
│   └── index.php                               # Dispatcher con resolución múltiple
└── includes/js/reportes/
    └── api-clients.js                          # Clientes API especializados
```

## 🎯 Ventajas de Esta Arquitectura

### ✅ Sin Controlador Monolítico
```
❌ ANTES: 1 controlador con 50+ métodos
✅ AHORA: 5 controladores con 3-8 métodos cada uno
```

### ✅ Organización Clara por Módulo
```php
// Créditos
/api/reportes/creditos/visitas-prepago
/api/reportes/creditos/desembolsados
/api/reportes/creditos/a-vencer

// Ahorros
/api/reportes/ahorros/cuentas-activas
/api/reportes/ahorros/movimientos

// Contabilidad
/api/reportes/contabilidad/balance-general
/api/reportes/contabilidad/estado-resultados
```

### ✅ Reutilización Máxima
```php
// BaseReporteController maneja:
- ✅ Validación de sesión
- ✅ Validación de datos
- ✅ Procesamiento de filtros
- ✅ Generación de Excel/PDF
- ✅ Manejo de respuestas
- ✅ Manejo de errores

// Controladores hijos solo definen:
- 📝 Query SQL
- 📝 Validaciones específicas
- 📝 Formatos disponibles
```

### ✅ Fácil de Extender
```php
// Agregar nuevo reporte es súper simple:

// 1. Método en el controlador (3 líneas)
public function miNuevoReporte() {
    return $this->generarReporte([
        'query' => $this->getQueryNuevo(),
        'validaciones' => ['fecha_inicio', 'fecha_fin'],
        'exportadores' => ['xlsx', 'pdf'],
        'nombre' => 'mi_nuevo_reporte'
    ]);
}

// 2. Query privado
private function getQueryNuevo() {
    return "SELECT /* tu query */";
}

// 3. Agregar ruta
$r->addRoute('POST', '/nuevo', 'CreditoReporteController@miNuevoReporte');
```

## 📊 Comparación de Complejidad

| Aspecto | Controlador Único | Controladores Especializados |
|---------|------------------|------------------------------|
| **Métodos por archivo** | 50+ | 3-8 |
| **Líneas por archivo** | 2000+ | 200-400 |
| **Dificultad para encontrar** | Alta | Baja |
| **Conflictos en Git** | Frecuentes | Raros |
| **Tiempo de carga** | Alto | Bajo |
| **Testabilidad** | Difícil | Fácil |

## 🚀 Ejemplos de Uso

### Desde JavaScript

```javascript
import { creditoAPI, ahorroAPI } from './reportes/api-clients.js';

// Reporte de créditos
const resultado = await creditoAPI.visitasPrepago({
    fecha_inicio: '2025-01-01',
    fecha_fin: '2025-12-31',
    filter_type: 'office',
    id_agencia: 5,
    tipo: 'xlsx'
});

// Reporte de ahorros
const movimientos = await ahorroAPI.movimientos({
    fecha_inicio: '2025-01-01',
    fecha_fin: '2025-12-31',
    tipo: 'pdf'
});
```

### Desde Alpine.js

```html
<div x-data="{
    async generarReporte() {
        const datos = await creditoAPI.desembolsados({
            fecha_inicio: this.fechaInicio,
            fecha_fin: this.fechaFin,
            tipo: 'json'
        });
        this.mostrarDatos(datos);
    }
}">
    <button @click="generarReporte()">Generar</button>
</div>
```

## 🔧 Personalización Avanzada

### Sobrescribir Exportación de Excel

```php
// En CreditoReporteController.php
protected function exportarExcel($datos, $nombre, $filtros)
{
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    
    // Título personalizado
    $sheet->setCellValue('A1', 'REPORTE DE VISITAS PREPAGO');
    $sheet->mergeCells('A1:K1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
    
    // Filtros aplicados
    $sheet->setCellValue('A2', 'Período: ' . $filtros['fecha_inicio'] . ' al ' . $filtros['fecha_fin']);
    
    // Encabezados personalizados
    $headers = ['Cuenta', 'Cliente', 'Nombre', 'Fecha', 'Saldo', 'Mora', 'Ahorro', 'Otros', 'Cuota', 'Capital', 'Interés'];
    $sheet->fromArray($headers, null, 'A4');
    
    // Datos
    $fila = 5;
    foreach ($datos as $row) {
        $sheet->fromArray(array_values($row), null, 'A' . $fila);
        $fila++;
    }
    
    // Totales
    $sheet->setCellValue('D' . $fila, 'TOTALES:');
    $sheet->setCellValue('E' . $fila, '=SUM(E5:E' . ($fila-1) . ')');
    
    // Formato
    $sheet->getStyle('A4:K4')->getFont()->setBold(true);
    $sheet->getStyle('E5:K' . $fila)->getNumberFormat()->setFormatCode('#,##0.00');
    
    // Ancho de columnas
    foreach(range('A','K') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }
    
    // Generar archivo
    ob_start();
    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
    $xlsData = ob_get_contents();
    ob_end_clean();
    
    return $this->jsonResponse([
        'status' => 1,
        'tipo' => 'xlsx',
        'archivo' => base64_encode($xlsData),
        'nombre' => $nombre . '_' . date('YmdHis') . '.xlsx'
    ]);
}
```

### Agregar Validaciones Personalizadas

```php
// En CreditoReporteController.php
public function visitasPrepago()
{
    // Validación adicional específica
    if (isset($this->input['id_agencia']) && !$this->validarAgenciaExiste($this->input['id_agencia'])) {
        return $this->jsonResponse([
            'status' => 0,
            'mensaje' => 'La agencia seleccionada no existe'
        ], 400);
    }
    
    return $this->generarReporte([
        'query' => $this->getQueryVisitasPrepago(),
        'validaciones' => ['fecha_inicio', 'fecha_fin'],
        'exportadores' => ['xlsx', 'pdf', 'json'],
        'nombre' => 'visitas_prepago'
    ]);
}

private function validarAgenciaExiste($idAgencia)
{
    $agencia = $this->getAgenciaInfo($idAgencia);
    return $agencia !== null;
}
```

## 📋 Checklist para Agregar Nuevo Reporte

- [ ] Definir en qué módulo va (Crédito, Ahorro, Contabilidad, etc.)
- [ ] Crear método en controlador correspondiente
- [ ] Escribir query SQL con placeholders
- [ ] Definir validaciones requeridas
- [ ] Especificar formatos disponibles (xlsx, pdf, json)
- [ ] Agregar ruta en `routes.php`
- [ ] Probar con Postman/curl
- [ ] Agregar método en cliente JS (opcional)
- [ ] Documentar en README

## 🎨 Crear Nuevo Módulo de Reportes

```php
// 1. Crear controlador
// www/app/controllers/Reportes/ClienteReporteController.php
<?php
namespace App\Controllers\Reportes;
use App\Controllers\BaseReporteController;

class ClienteReporteController extends BaseReporteController
{
    public function listadoGeneral()
    {
        return $this->generarReporte([
            'query' => $this->getQueryListado(),
            'validaciones' => [],
            'exportadores' => ['xlsx', 'pdf'],
            'nombre' => 'listado_clientes'
        ]);
    }
    
    private function getQueryListado()
    {
        return "SELECT * FROM tb_cliente WHERE estado=1";
    }
}
```

```php
// 2. Agregar grupo de rutas
// www/api/routes.php
$r->addGroup('/clientes', function($r) {
    $r->addRoute('POST', '/listado-general', 'ClienteReporteController@listadoGeneral');
    $r->addRoute('POST', '/por-agencia', 'ClienteReporteController@porAgencia');
});
```

```javascript
// 3. Crear cliente JS (opcional)
// www/includes/js/reportes/api-clients.js
export class ClienteReporteAPI {
    constructor(baseURL = '/api/reportes/clientes') {
        this.baseURL = baseURL;
    }
    
    async listadoGeneral(filtros) {
        return this.request('/listado-general', filtros);
    }
    
    async request(endpoint, data) {
        const response = await fetch(this.baseURL + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        return response.json();
    }
}
```

## 🔍 Testing

```php
// Probar endpoint con curl
curl -X POST http://localhost/api/reportes/creditos/visitas-prepago \
  -H "Content-Type: application/json" \
  -d '{
    "fecha_inicio": "2025-01-01",
    "fecha_fin": "2025-12-31",
    "filter_type": "all",
    "tipo": "json"
  }'
```

## 💡 Mejores Prácticas

1. ✅ **Un controlador por módulo** (Créditos, Ahorros, etc.)
2. ✅ **Queries en métodos privados** (fácil de mantener)
3. ✅ **Usar BaseReporteController** (no reinventar la rueda)
4. ✅ **Nombrar rutas descriptivamente** (`/visitas-prepago` mejor que `/reporte1`)
5. ✅ **Agrupar rutas por módulo** (organización clara)
6. ✅ **Validar en el controlador** (no confiar en el frontend)
7. ✅ **Loguear errores** (facilita debugging)
8. ✅ **Respuestas consistentes** (usar `jsonResponse()`)

## 🚦 Próximos Pasos

- [ ] Migrar reportes legacy a nuevos controladores
- [ ] Agregar tests unitarios por controlador
- [ ] Implementar cache de reportes frecuentes
- [ ] Agregar paginación para reportes grandes
- [ ] Crear jobs para reportes pesados (queue)
- [ ] Documentar con Swagger/OpenAPI

---

**¿Dudas?** Este sistema es **infinitamente escalable** sin saturar ningún archivo. 🚀
