# Sistema de Reportes - Documentación

## Arquitectura

### Estructura del Sistema

```
www/
  api/
    controllers/
      ReporteController.php    # Controlador de reportes
    routes.php                  # Definición de rutas
    index.php                   # Entry point de la API
  includes/js/
    bb_reportes.js              # Módulo JS moderno (Webpack)
    script_menu_reporte.js      # Script legacy (mantener por compatibilidad)
```

## Ventajas de la Nueva Arquitectura

### 1. **Separación de Responsabilidades**
- **Controlador**: Maneja lógica de negocio
- **Rutas**: Define endpoints
- **JS**: Interfaz de usuario y comunicación con API

### 2. **API RESTful**
```
GET    /api/reportes                        # Lista reportes disponibles
POST   /api/reportes/visitas-prepago        # Genera reporte específico
POST   /api/reportes/creditos-desembolsados
POST   /api/reportes/creditos-vencer
```

### 3. **Validación Centralizada**
- Validaciones en el controlador
- Respuestas consistentes
- Manejo de errores unificado

### 4. **Reutilización**
- Mismos endpoints para web y móvil
- Fácil integración con otros sistemas
- Testeable

## Uso del Módulo JS

### Opción 1: Con Alpine.js (Recomendado)

```html
<div x-data="{
    filterType: 'all',
    fechaInicio: '',
    fechaFin: '',
    async generarReporte(tipo) {
        const opciones = {
            tipo: tipo // 'json', 'xlsx', 'pdf', 'show'
        };
        
        await generarReporteVisitasPrepago(opciones);
    }
}">
    <form id="formReport">
        <input type="date" name="fecha_inicio" x-model="fechaInicio" required>
        <input type="date" name="fecha_fin" x-model="fechaFin" required>
        
        <select name="filter_ejecutivo" x-model="filterType">
            <option value="1">Todos</option>
            <option value="2">Por Agencia</option>
            <option value="3">Por Ejecutivo</option>
        </select>
        
        <select name="id_agencia" x-show="filterType === '2'">
            <!-- Opciones de agencias -->
        </select>
        
        <select name="id_usuario" x-show="filterType === '3'">
            <!-- Opciones de usuarios -->
        </select>
    </form>
    
    <div class="btn-group">
        <button @click="generarReporte('json')" class="btn btn-primary">
            Ver Datos
        </button>
        <button @click="generarReporte('xlsx')" class="btn btn-success">
            Exportar Excel
        </button>
        <button @click="generarReporte('pdf')" class="btn btn-danger">
            Exportar PDF
        </button>
        <button @click="generarReporte('show')" class="btn btn-info">
            Ver PDF
        </button>
    </div>
</div>
```

### Opción 2: Con jQuery (Compatibilidad)

```javascript
// En tu archivo de script
$(document).ready(function() {
    $('#btnGenerarReporte').on('click', async function() {
        try {
            // Usando la clase directamente
            const manager = window.reporteManager;
            
            await manager.generarReporte('visitas_prepago', '#formReport', {
                tipo: 'xlsx', // 'json', 'xlsx', 'pdf', 'show'
                onSuccess: (datos) => {
                    // Mostrar datos en tabla
                    manager.actualizarTabla(
                        datos,
                        ['Cuenta', 'Cliente', 'Nombre', 'Fecha'],
                        ['cuenta', 'cliente', 'nombre', 'fecha']
                    );
                }
            });
            
        } catch (error) {
            console.error(error);
        }
    });
});
```

### Opción 3: Con Módulos ES6

```javascript
// En tu archivo webpack entry
import { ReporteManager, generarReporteVisitasPrepago } from './bb_reportes.js';

const manager = new ReporteManager();

// Forma 1: Función helper
document.querySelector('#btnReporte').addEventListener('click', async () => {
    await generarReporteVisitasPrepago({
        tipo: 'xlsx'
    });
});

// Forma 2: Instancia de clase
document.querySelector('#btnReporte2').addEventListener('click', async () => {
    await manager.generarReporte('creditos_desembolsados', '#formReport', {
        tipo: 'pdf',
        onSuccess: (datos) => {
            console.log('Reporte generado:', datos);
        }
    });
});
```

## Ejemplos de Uso Completos

### Ejemplo 1: Reporte con Vista en JSON

```javascript
await generarReporteVisitasPrepago({
    tipo: 'json',
    onSuccess: (datos) => {
        // Actualizar tabla
        reporteManager.actualizarTabla(
            datos,
            ['Cuenta', 'Cliente', 'Nombre', 'Saldo'],
            ['cuenta', 'cliente', 'nombre', 'saldo'],
            '#miTabla'
        );
        
        // Actualizar gráfica
        const datosGrafica = procesarDatosParaGrafica(datos);
        reporteManager.actualizarGrafica(
            datosGrafica,
            'Visitas por Fecha',
            1,
            '#miGrafica'
        );
    }
});
```

### Ejemplo 2: Descarga Directa de Excel

```javascript
await generarReporteCreditosDesembolsados({
    tipo: 'xlsx'
    // El archivo se descarga automáticamente
});
```

### Ejemplo 3: Mostrar PDF en Nueva Ventana

```javascript
await generarReporteCreditosVencer({
    tipo: 'show'
    // Se abre PDF en nueva pestaña
});
```

## Migración desde Código Legacy

### Antes (script_menu_reporte.js):
```javascript
function reportes(datos, tipo, file, download, label, columdata, tipodata, labeltitle, top) {
    loaderefect(1);
    var datosval = [];
    datosval[0] = getinputsval(datos[0]); 
    datosval[1] = getselectsval(datos[1]); 
    datosval[2] = getradiosval(datos[2]); 
    datosval[3] = datos[3];
    var url = "views_reporte/reportes/" + file + ".php";
    $.ajax({
        url: url,
        type: "POST",
        data: { datosval, tipo },
        success: function (data) { /* ... */ }
    });
}
```

### Ahora (bb_reportes.js):
```javascript
await generarReporteVisitasPrepago({
    tipo: 'xlsx' // o 'pdf', 'json', 'show'
});
// Más simple, más claro, más mantenible
```

## Configuración de Webpack

```javascript
// webpack.config.js
module.exports = {
    entry: {
        reportes: './includes/js/bb_reportes.js',
        // ... otros entries
    },
    output: {
        filename: '[name].bundle.js',
        path: path.resolve(__dirname, 'public/assets/js'),
    }
};
```

## Ventajas vs Código Legacy

| Aspecto | Legacy | Nuevo |
|---------|--------|-------|
| **Claridad** | Múltiples parámetros confusos | API clara y tipada |
| **Mantenimiento** | Difícil de seguir | Fácil de mantener |
| **Reutilización** | Solo desde PHP | API REST reutilizable |
| **Testing** | Complicado | Fácil de testear |
| **Validación** | Dispersa | Centralizada |
| **Errors** | Inconsistente | Manejo unificado |
| **Extensibilidad** | Limitada | Muy extensible |

## Próximos Pasos

1. ✅ Implementar controlador base
2. ✅ Crear módulo JS moderno
3. ⏳ Migrar reportes existentes
4. ⏳ Agregar tests unitarios
5. ⏳ Documentar API con Swagger
6. ⏳ Agregar cache de reportes
7. ⏳ Implementar generación asíncrona para reportes pesados

## Notas Importantes

- **Compatibilidad**: El módulo se expone en `window` para código legacy
- **Validación**: Siempre validar en el backend
- **Seguridad**: CSRF tokens, validación de sesión
- **Performance**: Considerar paginación para reportes grandes
- **UX**: Loader automático durante generación
