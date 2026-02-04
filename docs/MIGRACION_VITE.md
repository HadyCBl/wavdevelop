# Migración de Webpack a Vite

Este proyecto está configurado para usar **Webpack** (sistema actual) y **Vite** (sistema nuevo) en paralelo, permitiendo una migración gradual.

## 📁 Estructura de Assets

- **Webpack**: `public/assets/dist/` - Sistema actual
- **Vite**: `public/assets/vite-dist/` - Sistema nuevo

## 🚀 Scripts Disponibles

### Webpack (Actual)
```bash
npm run dev              # Build desarrollo + watch
npm run dev:another      # Build específico del bundle "another"
npm run build            # Build producción todos los bundles
npm run build:another    # Build producción bundle específico
npm run clean            # Limpiar cache webpack
```

### Vite (Nuevo)
```bash
npm run vite:dev         # Dev server con HMR
npm run vite:build       # Build producción
npm run vite:preview     # Preview del build
npm run vite:clean       # Limpiar dist vite
```

## 📝 Uso en PHP

### Webpack (Actual)
```php
use Micro\Generic\Asset;

// Configuración inicial
Asset::setEnvironment(true); // true = producción
Asset::setHostUrl('http://localhost');

// Cargar un bundle
echo Asset::render('another');

// O separado
echo Asset::style('another');
echo Asset::script('another');
```

### Vite (Nuevo)
```php
use Micro\Generic\AssetVite;

// Configuración inicial
AssetVite::setEnvironment(true); // true = producción
AssetVite::setHostUrl('http://localhost');

// IMPORTANTE: En desarrollo, habilitar HMR
AssetVite::enableDevMode(true, 'http://localhost:5173');

// Cargar un bundle
echo AssetVite::render('dashboard');

// O separado
echo AssetVite::style('dashboard');
echo AssetVite::script('dashboard');
```

## 🔧 Agregar Nuevas Entradas

### Webpack
1. Editar `webpack.config.js`:
```javascript
const entries = {
  another: "./includes/js/bb_anothermodules.js",
  caja: "./includes/js/bb_caja.js",
  nuevo_modulo: "./includes/js/bb_nuevo.js", // ← Agregar aquí
};
```

2. Registrar dependencias en `Asset.php`:
```php
private static array $bundleDependencies = [
    'nuevo_modulo' => ['jquery', 'alpine'],
];
```

### Vite
1. Editar `vite.config.js`:
```javascript
const entries = {
  dashboard: './includes/js/vite_dashboard.js',
  settings: './includes/js/vite_settings.js', // ← Agregar aquí
};
```

2. Registrar dependencias en `AssetVite.php`:
```php
private static array $bundleDependencies = [
    'settings' => ['alpine'],
];
```

## 📦 Dependencias Disponibles

Ambos sistemas reconocen estas dependencias compartidas:
- `jquery` - jQuery
- `alpine` - Alpine.js
- `datatables` - DataTables

Solo se cargan si el bundle las necesita.

## 🔄 Plan de Migración

### Fase 1: Setup (✅ COMPLETADA)
- [x] Instalar Vite
- [x] Crear configuración
- [x] Crear clase AssetVite.php
- [x] Agregar scripts npm

### Fase 2: Nuevos Módulos (👈 AQUÍ ESTAMOS)
- [ ] Usar Vite para nuevos módulos
- [ ] Ejemplo: crear `vite_dashboard.js`
- [ ] Probar HMR en desarrollo

### Fase 3: Migración Gradual
- [ ] Migrar módulos existentes uno por uno
- [ ] Comparar rendimiento
- [ ] Ajustar configuración según necesidades

### Fase 4: Finalización
- [ ] Migrar todos los módulos
- [ ] Remover Webpack
- [ ] Limpiar dependencias

## 🎯 Ventajas de Vite

✅ **Hot Module Replacement (HMR)** - Cambios instantáneos sin recargar  
✅ **Build más rápido** - ESBuild es ~10-100x más rápido que Webpack  
✅ **ES Modules nativos** - Mejor treeshaking  
✅ **Configuración más simple** - Menos código de configuración  
✅ **Dev server más rápido** - Inicio en milisegundos  

## 📋 Ejemplo de Entrada Nueva

### Crear archivo JS para Vite
`includes/js/vite_dashboard.js`:
```javascript
// Importar CSS
import '../css/dashboard.css';

// Importar Alpine si se necesita
import Alpine from 'alpinejs';
window.Alpine = Alpine;
Alpine.start();

// Tu código
console.log('Dashboard con Vite');
```

### Agregar al vite.config.js
```javascript
const entries = {
  dashboard: './includes/js/vite_dashboard.js', // ← Nueva entrada
};
```

### Registrar dependencias
En `AssetVite.php`:
```php
private static array $bundleDependencies = [
    'dashboard' => ['alpine'], // Necesita Alpine
];
```

### Usar en PHP
```php
use Micro\Generic\AssetVite;

AssetVite::setEnvironment(false); // desarrollo
AssetVite::setHostUrl('http://localhost');
AssetVite::enableDevMode(true, 'http://localhost:5173');

echo AssetVite::render('dashboard');
```

## 🐛 Debug

Ambas clases tienen método debug:
```php
echo Asset::debug();      // Info webpack
echo AssetVite::debug();  // Info vite
```

## 📚 Recursos

- [Documentación Vite](https://vitejs.dev/)
- [Migración desde Webpack](https://vitejs.dev/guide/migration.html)
- [Guía de HMR](https://vitejs.dev/guide/api-hmr.html)

---

## ⚠️ IMPORTANTE

- **NO** eliminar archivos Webpack hasta completar la migración
- Mantener ambos manifests actualizados
- Probar en desarrollo antes de build producción
- Documentar cada bundle migrado
