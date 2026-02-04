# Reporte de Ingresos Diarios con Visualización Mejorada

## Descripción General

Se ha implementado una mejora significativa al sistema de reportes de ingresos diarios que incluye:

1. **Agrupación por bloques de grupos** (similar al reporte de prepago)
2. **Resumen por tipo de entidad** (Individuales vs Grupales)
3. **Visualización JSON mejorada** con tabla y gráficas interactivas
4. **Datos procesados automáticamente** para visualización de gráficos

## Archivos Modificados

### 1. IngresoController.php
**Ubicación:** `www/app/Controllers/Reportes/Creditos/IngresoController.php`

#### Cambios Principales:

- **reporteJSON()**: Mejorado para devolver datos estructurados
  - `datos`: Array de registros individuales
  - `datosGrafica`: Datos agrupados por fecha para gráficas
  - `resumen`: Totales generales y por tipo de entidad

- **bodyPDF()**: Implementa agrupación por grupos
  - Detecta cambios de grupo con `$controlGroup`
  - Imprime subtotales con `imprimirTotalesGrupo()`
  - Imprime resumen final con `imprimirResumenPorTipo()`

- **bodyExcel()**: Implementa agrupación con estilo
  - Headers de grupo con formato destacado
  - Subtotales dinámicos por categoría de egreso
  - Resumen final con todas las subcategorías

#### Nuevos Métodos:

```php
// PDF
imprimirTotalesGrupo($total_monto, $total_KP, $total_INTE, $total_MORA, $total_OTR)
imprimirResumenPorTipo($totalesPorTipo)

// Excel
imprimirSubtotalExcel($sheet, $currentRow, $nombreGrupo, $tipoEnti, $totales)
imprimirResumenPorTipoExcel($sheet, $currentRow, $totalesPorTipo)
```

### 2. vite_reportes.js
**Ubicación:** `www/includes/js/vite_reportes.js`

#### Cambios Principales:

- **procesarRespuesta()**: Mejorado para manejar visualizaciones
  - Detecta `opciones.mostrarTabla` y `opciones.mostrarGrafica`
  - Soporta `dataKey` para usar datos procesados
  - Implementa `crearGraficaAvanzada()` para configuración avanzada

- **crearGraficaAvanzada()**: Nuevo método
  - Soporta múltiples datasets
  - Configuración flexible de colores y tipos
  - Compatible con Chart.js

#### Nueva Función de Exportación:

```javascript
generarReporteIngresosDiarios(opciones)
```

## Uso

### 1. Reporte PDF (Tradicional)

```javascript
generarReporteIngresosDiarios({
    tipo: 'pdf'
});
```

**Resultado:**
- Descarga PDF con grupos organizados
- Subtotales por cada grupo
- Resumen final por tipo de entidad

### 2. Reporte Excel (Tradicional)

```javascript
generarReporteIngresosDiarios({
    tipo: 'xlsx'
});
```

**Resultado:**
- Descarga Excel con formato mejorado
- Headers de grupo destacados
- Subtotales dinámicos por categoría

### 3. Visualización JSON con Tabla y Gráfica (NUEVO)

```javascript
generarReporteIngresosDiarios({
    tipo: 'json',
    mostrarTabla: true,
    mostrarGrafica: true,
    configTabla: {
        encabezados: [
            'Fecha',
            'Tipo',
            'Grupo',
            'N° Ingreso',
            'Doc',
            'Monto',
            'KP',
            'INT',
            'MOR',
            'OTR'
        ],
        keys: [
            'DFECPRO',
            'TipoEnti',
            'NombreGrupo',
            'CNUMING',
            'CNUMDOC',
            'NMONTO',
            'KP',
            'INTERES',
            'MORA',
            'OTR'
        ],
        selector: '#divshow'
    },
    configGrafica: {
        titulo: 'Ingresos Diarios por Fecha',
        type: 'bar', // 'line', 'pie', 'doughnut', etc.
        dataKey: 'datosGrafica', // usar datos procesados
        labels: 'fecha',
        datasets: [
            {
                label: 'Total Ingresos',
                key: 'total',
                color: 'rgba(75, 192, 192, 0.8)'
            },
            {
                label: 'Capital (KP)',
                key: 'capital',
                color: 'rgba(54, 162, 235, 0.8)'
            },
            {
                label: 'Interés',
                key: 'interes',
                color: 'rgba(255, 206, 86, 0.8)'
            },
            {
                label: 'Mora',
                key: 'mora',
                color: 'rgba(255, 99, 132, 0.8)'
            }
        ],
        selector: '#divshowchart'
    }
});
```

**Resultado:**
- Muestra tabla interactiva con DataTables
- Genera gráfica con Chart.js
- Usa datos agregados por fecha automáticamente

## Estructura HTML Requerida

Para usar la opción JSON con visualización, tu página debe tener:

```html
<!-- Contenedor para la tabla -->
<div id="divshow" style="display: none;">
    <h2>Datos del Reporte</h2>
    <div class="table-responsive">
        <table id="tbdatashow">
            <thead></thead>
            <tbody></tbody>
        </table>
    </div>
</div>

<!-- Contenedor para la gráfica -->
<div id="divshowchart" style="display: none;">
    <h2>Visualización Gráfica</h2>
    <div class="chart-container">
        <canvas id="myChart"></canvas>
    </div>
</div>

<!-- Scripts necesarios -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script type="module">
    import { generarReporteIngresosDiarios } from '../includes/js/vite_reportes.js';
    
    window.generarJSON = function() {
        document.getElementById('divshow').style.display = 'block';
        document.getElementById('divshowchart').style.display = 'block';
        
        generarReporteIngresosDiarios({
            tipo: 'json',
            mostrarTabla: true,
            mostrarGrafica: true,
            // ... configuraciones
        });
    };
</script>
```

## Ejemplo Completo

Se ha creado una página de ejemplo en:
**`www/views/reporte_ingresos_diarios.php`**

Esta página incluye:
- Formulario de filtros (fecha inicio, fecha fin, código empleado)
- Botones para generar PDF, Excel y JSON
- Contenedores para tabla y gráfica
- Configuración completa de ejemplo

### Para probar:

1. Accede a: `http://tu-dominio/views/reporte_ingresos_diarios.php`
2. Selecciona rango de fechas
3. Haz clic en "Ver Datos y Gráfica"

## Estructura de Datos JSON

### Respuesta del Backend:

```json
{
    "status": 1,
    "datos": [
        {
            "DFECPRO": "2024-01-15",
            "TipoEnti": "GRUP",
            "NombreGrupo": "LOS EMPRENDEDORES",
            "CNUMING": "ING-001",
            "NMONTO": 1500.00,
            "KP": 1000.00,
            "INTERES": 300.00,
            "MORA": 100.00,
            "OTR": 100.00
        }
    ],
    "datosGrafica": [
        {
            "fecha": "15/01/2024",
            "total": 5000.00,
            "cantidad": 10,
            "capital": 3500.00,
            "interes": 900.00,
            "mora": 400.00,
            "otros": 200.00
        }
    ],
    "resumen": {
        "total_registros": 50,
        "total_ingresos": 75000.00,
        "total_capital": 50000.00,
        "total_interes": 15000.00,
        "total_mora": 7000.00,
        "total_otros": 3000.00,
        "por_tipo": {
            "INDIVIDUALES": 30000.00,
            "GRUPALES": 45000.00
        }
    }
}
```

## Beneficios de la Implementación

### Para PDF:
✅ Mejor organización por grupos
✅ Subtotales automáticos
✅ Resumen por tipo de entidad
✅ Fácil lectura y análisis

### Para Excel:
✅ Headers de grupo destacados
✅ Columnas dinámicas por categoría
✅ Subtotales con formato
✅ Resumen completo al final

### Para JSON:
✅ Datos listos para visualización
✅ Agregación automática por fecha
✅ Tabla interactiva con DataTables
✅ Gráficas personalizables con Chart.js
✅ Resumen de totales incluido

## Notas Técnicas

### Ordenamiento SQL:
Los datos se ordenan por:
1. Tipo de Entidad (DESC) - Grupales primero
2. Nombre de Grupo
3. Fecha de Proceso
4. Número de Ingreso

```sql
ORDER BY crem.TipoEnti DESC, grup.NombreGrupo, kar.DFECPRO, kar.CNUMING
```

### Control de Grupos:
Se usa la variable `$controlGroup` para detectar cambios:
```php
$controlGroup = $data['TipoEnti'] . '-' . $data['NombreGrupo'];
```

### Datos Agregados:
Los datos para gráficas se procesan automáticamente agrupando por fecha y sumando:
- Total de ingresos
- Cantidad de registros
- Capital, Interés, Mora, Otros

## Personalización

### Cambiar Tipo de Gráfica:

```javascript
configGrafica: {
    type: 'line', // cambiar a 'line', 'pie', 'doughnut', 'radar'
    // ...
}
```

### Agregar Más Datasets:

```javascript
datasets: [
    {
        label: 'Nuevo Dataset',
        key: 'campo_en_datos',
        color: 'rgba(255, 0, 0, 0.8)'
    }
]
```

### Usar Callback Personalizado:

```javascript
generarReporteIngresosDiarios({
    tipo: 'json',
    onSuccess: function(response) {
        console.log('Datos:', response.datos);
        console.log('Resumen:', response.resumen);
        // Tu lógica personalizada aquí
    }
});
```

## Solución de Problemas

### La tabla no se muestra:
- Verifica que el contenedor `#divshow` exista
- Asegúrate de que `mostrarTabla: true`
- Revisa que `configTabla` tenga `encabezados` y `keys` correctos

### La gráfica no aparece:
- Verifica que el canvas `#myChart` exista dentro de un contenedor visible
- Asegúrate de que Chart.js esté cargado
- Verifica que `dataKey` apunte a los datos correctos

### Los datos no coinciden:
- Verifica que los `keys` en `configTabla` correspondan a los campos de `datos`
- Para gráficas, usa `dataKey: 'datosGrafica'` para datos agregados

## Próximas Mejoras Sugeridas

1. **Filtros adicionales**: Por tipo de entidad, por grupo específico
2. **Exportación de gráficas**: Descargar gráfica como imagen
3. **Comparación de períodos**: Gráficas comparativas entre fechas
4. **Resumen ejecutivo**: Dashboard con KPIs principales
5. **Notificaciones**: Alertas cuando se alcanzan ciertos montos

## Contacto y Soporte

Para dudas o mejoras, contactar al equipo de desarrollo.
