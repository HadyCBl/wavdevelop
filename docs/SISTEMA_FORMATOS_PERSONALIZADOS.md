# Sistema de Formatos Personalizados de Reportes

## 📋 Descripción General

Sistema centralizado para manejar formatos personalizados de reportes por institución, permitiendo que cada organización tenga sus propias plantillas sin modificar el código base.

## 🏗️ Arquitectura

```
BaseReporteController (padre)
    ↓
    ├── generarReporteConFormato()  ← Lógica centralizada
    ↓
ContratoRenovacionController (hijo)
    ↓
    ├── index() → Usa delegación
    ├── generarFormatoGenerico() → Formato base
    ↓
    └── Si idDocument != null
        ↓
        Consulta tb_documentos
        ↓
        Instancia clase de formato
        ↓
Formatos/Seguros/Institucion1/ContratoRenovacion.php
Formatos/Seguros/Institucion2/ContratoRenovacion.php
```

## 📁 Estructura de Directorios

```
app/Controllers/
├── BaseReporteController.php           ← Lógica centralizada
├── Reportes/
│   ├── Formatos/
│   │   ├── BaseFormato.php            ← Clase base para formatos
│   │   ├── Seguros/
│   │   │   ├── InstitucionEjemplo/
│   │   │   │   └── ContratoRenovacion.php
│   │   │   ├── CooperativaA/
│   │   │   │   ├── ContratoRenovacion.php
│   │   │   │   └── ReciboPago.php
│   │   │   └── CooperativaB/
│   │   │       └── ContratoRenovacion.php
│   │   ├── Creditos/
│   │   │   ├── InstitucionEjemplo/
│   │   │   │   └── SolicitudCredito.php
│   │   └── Ahorros/
│   │       └── InstitucionEjemplo/
│   │           └── EstadoCuenta.php
│   └── Seguros/
│       └── ContratoRenovacionController.php
```

## 🔧 Componentes Principales

### 1. BaseReporteController

**Método clave: `generarReporteConFormato()`**

Este método centraliza toda la lógica de delegación:

```php
protected function generarReporteConFormato($id, callable $callbackGenerico)
{
    // Si no hay documento personalizado → formato genérico
    if ($this->idDocument === null) {
        return $callbackGenerico($id);
    }

    // Consultar tb_documentos
    // Instanciar clase de formato
    // Ejecutar formato personalizado
}
```

**Características:**
- ✅ Consulta `tb_documentos` solo si `idDocument` tiene valor
- ✅ Verifica que la clase de formato exista
- ✅ Valida que implemente el método `generar()`
- ✅ Maneja errores y logging automáticamente
- ✅ Fallback al formato genérico si hay problemas

### 2. BaseFormato

Clase abstracta con funcionalidad común para todos los formatos:

```php
abstract class BaseFormato
{
    abstract public function generar($id); // ← OBLIGATORIO implementar
    
    protected function crearPDFBase($orientacion, $tamano);
    protected function getInfoInstitucion();
    protected function generarEncabezadoInstitucional($pdf, $titulo, $info);
    protected function generarPieComprobante($pdf, $texto);
    protected function formatoMoneda($valor);
    // ... más helpers
}
```

**Helpers disponibles:**
- `crearPDFBase()` - Crea instancia de FPDF configurada
- `getInfoInstitucion()` - Obtiene datos de la cooperativa
- `generarEncabezadoInstitucional()` - Encabezado estándar
- `generarPieComprobante()` - Pie de página
- `formatoMoneda()` - Formato de moneda
- `decode()` - Conversión UTF-8 para FPDF
- `jsonResponse()` - Respuesta JSON estándar

### 3. Formato Personalizado (Ejemplo)

Cada institución implementa su propio formato:

```php
namespace Micro\Controllers\Reportes\Formatos\Seguros\CooperativaA;

class ContratoRenovacion extends BaseFormato
{
    public function generar($id)
    {
        // 1. Obtener datos
        $datos = $this->obtenerDatos($id);
        
        // 2. Crear PDF
        $pdf = $this->crearPDFBase('P', 'Letter');
        
        // 3. Encabezado personalizado
        $this->generarEncabezadoPersonalizado($pdf);
        
        // 4. Cuerpo personalizado
        $this->generarCuerpoPersonalizado($pdf, $datos);
        
        // 5. Retornar respuesta
        return $this->generarRespuestaPDF($pdf, $id);
    }
    
    // Métodos privados específicos de esta institución...
}
```

## 💾 Tabla tb_documentos

Estructura recomendada:

```sql
CREATE TABLE tb_documentos (
    id INT PRIMARY KEY AUTO_INCREMENT,
    id_reporte INT NOT NULL,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT,
    clase_formato VARCHAR(500),  -- Namespace completo de la clase
    estado TINYINT DEFAULT 1,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

**Ejemplo de registros:**

```sql
INSERT INTO tb_documentos (id_reporte, nombre, clase_formato) VALUES
(101, 'Contrato Renovación - Cooperativa A', 
 'Micro\\Controllers\\Reportes\\Formatos\\Seguros\\CooperativaA\\ContratoRenovacion'),
 
(101, 'Contrato Renovación - Cooperativa B', 
 'Micro\\Controllers\\Reportes\\Formatos\\Seguros\\CooperativaB\\ContratoRenovacion');
```

## 🚀 Uso en Controladores

### Ejemplo 1: Usar formato personalizado

```php
class ContratoRenovacionController extends BaseReporteController
{
    public function index()
    {
        $id = $this->input['id'] ?? null;
        
        // Establecer ID del documento personalizado (si existe)
        $this->idDocument = $this->input['idDocument'] ?? null;
        
        // Delegar a sistema centralizado
        return $this->generarReporteConFormato($id, function($id) {
            return $this->generarFormatoGenerico($id);
        });
    }
    
    private function generarFormatoGenerico($id)
    {
        // Implementación del formato base/genérico
    }
}
```

### Ejemplo 2: Siempre usar formato genérico

```php
class OtroReporteController extends BaseReporteController
{
    public function index()
    {
        $id = $this->input['id'] ?? null;
        
        // NO establecer idDocument → siempre usa genérico
        
        return $this->generarReporteConFormato($id, function($id) {
            return $this->generarFormatoGenerico($id);
        });
    }
}
```

## 📝 Crear un Nuevo Formato Personalizado

### Paso 1: Crear carpeta de la institución

```bash
app/Controllers/Reportes/Formatos/Seguros/MiCooperativa/
```

### Paso 2: Crear clase de formato

```php
<?php
namespace Micro\Controllers\Reportes\Formatos\Seguros\MiCooperativa;

use Micro\Controllers\Reportes\Formatos\BaseFormato;

class ContratoRenovacion extends BaseFormato
{
    public function generar($id)
    {
        // Tu implementación aquí
    }
}
```

### Paso 3: Registrar en tb_documentos

```sql
INSERT INTO tb_documentos (id_reporte, nombre, clase_formato, estado) VALUES
(101, 'Contrato Renovación - Mi Cooperativa', 
 'Micro\\Controllers\\Reportes\\Formatos\\Seguros\\MiCooperativa\\ContratoRenovacion',
 1);
```

### Paso 4: Usar en frontend

```javascript
fetch('/api/reportes/seguros/contrato-renovacion', {
    method: 'POST',
    body: JSON.stringify({
        id: 123,
        idDocument: 5  // ← ID del registro en tb_documentos
    })
});
```

## ✨ Ventajas del Sistema

1. **Centralizado**: Lógica de delegación en un solo lugar
2. **Escalable**: Agregar nueva institución = nueva carpeta + clase
3. **Mantenible**: Cada formato está aislado
4. **Flexible**: Formato genérico como fallback
5. **Seguro**: Validaciones automáticas de clases
6. **DRY**: Heredan helpers de BaseFormato
7. **Testeable**: Cada formato es independiente
8. **Logging**: Errores registrados automáticamente

## 🔍 Flujo de Ejecución

```
1. Usuario solicita reporte con idDocument=5
   ↓
2. ContratoRenovacionController.index()
   ↓
3. BaseReporteController.generarReporteConFormato()
   ↓
4. Consulta tb_documentos WHERE id=5
   ↓
5. Obtiene clase_formato: "Micro\...\CooperativaA\ContratoRenovacion"
   ↓
6. Verifica que clase exista
   ↓
7. Instancia: new CooperativaA\ContratoRenovacion()
   ↓
8. Ejecuta: $formateador->generar($id)
   ↓
9. Retorna PDF generado con formato personalizado
```

## 📌 Notas Importantes

- **Namespace**: Respetar el namespace completo en `clase_formato`
- **Método generar()**: Es obligatorio implementarlo
- **Errores**: Se registran automáticamente en logs
- **Fallback**: Si falla formato personalizado, usa genérico (opcional)
- **Base de datos**: `clase_formato` puede ser NULL para usar solo genérico

## 🎯 Ejemplo Completo de Implementación

Ver archivo completo en:
- `app/Controllers/Reportes/Formatos/Seguros/InstitucionEjemplo/ContratoRenovacion.php`

Este archivo contiene un ejemplo funcional con todos los elementos necesarios.
