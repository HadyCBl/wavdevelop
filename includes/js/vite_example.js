// Ejemplo de entrada para Vite
// includes/js/vite_example.js

// 1. Importar estilos
import '../css/example.css';

// 2. Importar librerías necesarias
import Alpine from 'alpinejs';
import persist from '@alpinejs/persist';

// 3. Configurar Alpine solo si no está inicializado
if (!window.Alpine) {
    Alpine.plugin(persist);
    window.Alpine = Alpine;
    Alpine.start();
    console.log('🎉 Alpine.js iniciado');
}

// 4. Tu código principal
function init() {
    console.log('✅ Ejemplo Vite cargado correctamente');
    console.log('⚡ HMR: Edita este archivo y ve los cambios instantáneos');
    
    // Tu lógica aquí
    console.log('hola chavales');

    console.log("El valor de APP_ENV es: " + import.meta.env.VITE_APP_ENV);
}

// Ejecutar init cuando el DOM esté listo o inmediatamente si ya lo está
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}

// 5. HMR (Hot Module Replacement) - Solo en desarrollo
if (import.meta.hot) {
    import.meta.hot.accept(() => {
        console.log('🔥 HMR: Módulo actualizado instantáneamente sd');
        // Re-ejecutar init en HMR
        init();
    });
}
