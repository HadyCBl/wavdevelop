# 🚀 Guía Rápida: Uso de Vite

## Desarrollo con HMR (Hot Module Replacement)

### 1. Iniciar el servidor de desarrollo
```bash
npm run vite:dev
```

Esto iniciará el dev server en `http://localhost:5173`

### 2. Crear una nueva entrada

**a) Crear el archivo JS:**
```javascript
// includes/js/vite_mimodulo.js
import '../css/mimodulo.css';
import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

console.log('Mi módulo con Vite');
```

**b) Agregar a vite.config.js:**
```javascript
const entries = {
  mimodulo: './includes/js/vite_mimodulo.js', // ← Agregar aquí
};
```

**c) Registrar dependencias en AssetVite.php:**
```php
private static array $bundleDependencies = [
    'mimodulo' => ['alpine'], // ← Agregar aquí
];
```

### 3. Usar en tu vista PHP

```php
<?php
use Micro\Generic\AssetVite;

AssetVite::setEnvironment(false); // false = desarrollo
AssetVite::setHostUrl('http://localhost');
AssetVite::enableDevMode(true, 'http://localhost:5173'); // HMR activado

// Registrar dependencias
AssetVite::registerBundleDependencies('mimodulo', ['alpine']);

// Cargar assets
echo AssetVite::render('mimodulo');
?>
```

### 4. Probar los cambios

Con el dev server corriendo (`npm run vite:dev`):
1. Edita tu archivo JS o CSS
2. Los cambios se reflejan **instantáneamente** sin recargar la página
3. ¡Eso es HMR en acción! 🔥

## Producción

### 1. Compilar para producción
```bash
npm run vite:build
```

### 2. Cambiar a modo producción en PHP
```php
AssetVite::setEnvironment(true); // true = producción
AssetVite::setHostUrl('http://tudominio.com');
// NO llamar enableDevMode() en producción
```

## Comparación Webpack vs Vite

| Característica | Webpack | Vite |
|---|---|---|
| **Dev server start** | ~5-10 segundos | ~300ms |
| **HMR** | ~1-3 segundos | ~50ms |
| **Build producción** | ~60 segundos | ~10 segundos |
| **Configuración** | Compleja | Simple |

## Estructura de Archivos

```
www/
├── includes/js/
│   ├── bb_*.js          ← Webpack (actual)
│   └── vite_*.js        ← Vite (nuevo)
├── public/assets/
│   ├── dist/            ← Webpack output
│   └── vite-dist/       ← Vite output
├── webpack.config.js    ← Config Webpack
├── vite.config.js       ← Config Vite
└── app/Generic/
    ├── Asset.php        ← Clase Webpack
    └── AssetVite.php    ← Clase Vite
```

## Scripts NPM

```bash
# Webpack (sistema actual)
npm run dev              # Watch mode
npm run build            # Build producción

# Vite (sistema nuevo)
npm run vite:dev         # Dev server con HMR
npm run vite:build       # Build producción
npm run vite:preview     # Preview del build
npm run vite:clean       # Limpiar cache
```

## Tips y Buenas Prácticas

✅ **Usa Vite para nuevos módulos** - Mejor experiencia de desarrollo  
✅ **Mantén HMR activo en desarrollo** - Cambios instantáneos  
✅ **Importa CSS en JS** - Mejor tree-shaking  
✅ **Usa ES Modules** - `import/export` en lugar de `require`  
✅ **Aprovecha el code splitting** - Vite lo hace automáticamente  

❌ **NO mezcles Webpack y Vite** en el mismo bundle  
❌ **NO uses jQuery** si puedes evitarlo (usa Alpine)  
❌ **NO olvides** deshabilitar devMode en producción  

## Debugging

```php
// Ver información de debug
echo AssetVite::debug();
```

Esto mostrará:
- Estado del environment
- Si dev mode está activo
- Manifest path
- Assets disponibles
- Dependencias registradas

## Problemas Comunes

### HMR no funciona
1. Verifica que el dev server esté corriendo: `npm run vite:dev`
2. Verifica la URL del dev server en `enableDevMode()`
3. Revisa la consola del navegador

### Assets no cargan en producción
1. Ejecuta el build: `npm run vite:build`
2. Verifica que el manifest existe: `public/assets/vite-dist/manifest.json`
3. Desactiva devMode: NO llamar `enableDevMode()` en producción

### Error "Cannot find module"
1. Instala las dependencias: `npm install`
2. Verifica que la ruta en `entries` sea correcta
3. Verifica que el archivo JS exista

## Siguiente Paso

Prueba el ejemplo incluido:
1. `npm run vite:dev`
2. Abre en el navegador: `http://localhost/views/example_vite.php`
3. Edita `includes/js/vite_example.js` y ve los cambios instantáneos

¡Disfruta del desarrollo ultrarrápido con Vite! ⚡
