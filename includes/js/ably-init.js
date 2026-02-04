/**
 * Ejemplo de uso del AblyNotificationHelper
 * 
 * Este archivo muestra cómo usar el helper de Ably
 * en diferentes escenarios.
 */

import AblyNotificationHelper, { NotificationHelper } from './AblyNotificationHelper.js';

// ============================================
// CONFIGURACIÓN INICIAL
// ============================================

// Obtener configuración desde PHP (inyectada en el HTML)
const ablyConfig = window.ablyConfig || {
    clientKey: null,
    channelPrefix: 'app',
    userId: null,
    agencyId: null,
    enabled: false
};

// Crear instancia del helper
const ably = new AblyNotificationHelper({
    clientKey: ablyConfig.clientKey,
    channelPrefix: ablyConfig.channelPrefix,
    channelHuella: ablyConfig.channelHuella,
    userId: ablyConfig.userId,
    agencyId: ablyConfig.agencyId,
    enabled: ablyConfig.enabled,
    debug: true // Activar logs en desarrollo
});

// ============================================
// EJEMPLO 1: SUSCRIBIRSE A NOTIFICACIONES DEL USUARIO
// ============================================

ably.subscribeToUser(
    // Callback para notificaciones generales
    (data) => {
        console.log('Notificación recibida:', data);
        
        // Mostrar toast según el tipo
        NotificationHelper.showToast(data);
        
        // Reproducir sonido
        if (data.playSound !== false) {
            NotificationHelper.playSound();
        }
        
        // Acciones adicionales según el tipo
        switch(data.type) {
            case 'success':
                // Hacer algo específico para éxito
                break;
            case 'error':
                // Mostrar detalles del error si existen
                if (data.errorCode) {
                    console.error('Código de error:', data.errorCode);
                }
                break;
        }
    },
    
    // Callback para actualizaciones de datos
    (data) => {
        console.log('Datos actualizados:', data);
        
        // Recargar tabla según la entidad actualizada
        switch(data.entity) {
            case 'cliente':
                NotificationHelper.reloadDataTable('clientesTable');
                NotificationHelper.showToast({
                    type: 'info',
                    message: 'Cliente actualizado'
                });
                break;
            case 'prestamo':
                NotificationHelper.reloadDataTable('prestamosTable');
                NotificationHelper.showToast({
                    type: 'info',
                    message: 'Préstamo actualizado'
                });
                break;
            case 'pago':
                NotificationHelper.reloadDataTable('pagosTable');
                break;
        }
    },
    
    // Callback para alertas
    (data) => {
        console.log('Alerta recibida:', data);
        
        // Mostrar alerta modal según severidad
        if (window.Swal) {
            const icons = {
                info: 'info',
                warning: 'warning',
                critical: 'error'
            };
            
            window.Swal.fire({
                icon: icons[data.severity] || 'info',
                title: data.severity === 'critical' ? '⚠️ ALERTA CRÍTICA' : 'Alerta',
                text: data.message,
                confirmButtonText: 'Entendido'
            });
        }
    }
);

// ============================================
// EJEMPLO 2: SUSCRIBIRSE A NOTIFICACIONES DE AGENCIA
// ============================================

if (ablyConfig.agencyId) {
    ably.subscribeToAgency((data) => {
        console.log('Notificación de agencia:', data);
        
        // Mostrar notificación destacada
        NotificationHelper.showToast({
            type: data.type || 'info',
            message: `📢 ${data.message}`
        });
    });
}

// ============================================
// EJEMPLO 3: SUSCRIBIRSE A BROADCAST
// ============================================

ably.subscribeToBroadcast((data) => {
    console.log('Broadcast recibido:', data);
    
    // Mostrar banner superior persistente
    if (window.Swal) {
        window.Swal.fire({
            icon: data.type === 'warning' ? 'warning' : 'info',
            title: 'Anuncio del Sistema',
            html: data.message,
            showConfirmButton: true,
            allowOutsideClick: false,
            position: 'top'
        });
    }
});

// ============================================
// EJEMPLO 4: CANAL PERSONALIZADO
// ============================================

// Suscribirse a reportes
ably.subscribeToChannel('app:reports', 'report_ready', (message) => {
    const data = message.data;
    
    if (window.Swal) {
        window.Swal.fire({
            icon: 'success',
            title: 'Reporte Listo',
            text: 'Su reporte ha sido generado',
            showCancelButton: true,
            confirmButtonText: 'Descargar',
            cancelButtonText: 'Cerrar'
        }).then((result) => {
            if (result.isConfirmed && data.url) {
                window.location.href = data.url;
            }
        });
    }
});

// ============================================
// EJEMPLO 5: OBTENER HISTORIAL
// ============================================

async function loadNotificationHistory() {
    const history = await ably.getHistory(`app:user:${ablyConfig.userId}`, {
        limit: 20
    });
    
    console.log('Historial de notificaciones:', history);
    
    // Mostrar en una lista
    const historyContainer = document.getElementById('notification-history');
    if (historyContainer) {
        historyContainer.innerHTML = history.map(msg => `
            <div class="notification-item">
                <span class="timestamp">${new Date(msg.timestamp).toLocaleString()}</span>
                <span class="message">${msg.data.message || 'Sin mensaje'}</span>
            </div>
        `).join('');
    }
}

// ============================================
// EJEMPLO 6: EVENTOS DE CONEXIÓN
// ============================================

ably.on('connected', () => {
    console.log('✅ Conectado exitosamente');
    document.getElementById('connection-status')?.classList.add('connected');
});

ably.on('disconnected', () => {
    console.warn('⚠️ Desconectado');
    document.getElementById('connection-status')?.classList.remove('connected');
});

ably.on('failed', () => {
    console.error('❌ Error de conexión');
    NotificationHelper.showToast({
        type: 'error',
        message: 'Error de conexión con notificaciones en tiempo real'
    });
});

// ============================================
// EJEMPLO 7: LIMPIEZA AL CERRAR SESIÓN
// ============================================

// Función de logout
window.logoutUser = function() {
    // Cerrar conexión Ably
    ably.close();
    
    // Continuar con logout normal
    window.location.href = '/logout.php';
};

// Cleanup al salir de la página
window.addEventListener('beforeunload', () => {
    ably.close();
});

// ============================================
// EJEMPLO 8: INDICADOR DE ESTADO EN UI
// ============================================

function updateConnectionIndicator() {
    const indicator = document.getElementById('ably-status');
    if (!indicator) return;
    
    const state = ably.getConnectionState();
    const states = {
        connected: { text: 'Conectado', class: 'bg-green-500' },
        connecting: { text: 'Conectando...', class: 'bg-yellow-500' },
        disconnected: { text: 'Desconectado', class: 'bg-red-500' },
        suspended: { text: 'Suspendido', class: 'bg-orange-500' }
    };
    
    const current = states[state] || states.disconnected;
    indicator.textContent = current.text;
    indicator.className = `px-3 py-1 rounded-full text-white text-xs ${current.class}`;
}

// Actualizar cada segundo
setInterval(updateConnectionIndicator, 1000);

// ============================================
// EXPORTAR PARA USO GLOBAL
// ============================================

window.ablyHelper = ably;
window.notificationHelper = NotificationHelper;

export { ably, NotificationHelper };
