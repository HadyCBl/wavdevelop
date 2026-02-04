/**
 * AblyNotificationHelper
 * 
 * Helper para gestionar notificaciones en tiempo real con Ably
 * Proporciona una interfaz simplificada para suscribirse a canales
 * y recibir notificaciones del sistema.
 * 
 * @author Sotecpro
 * @version 1.0.0
 */

import * as Ably from 'ably';

class AblyNotificationHelper {
    /**
     * Constructor
     * @param {Object} config - Configuración de Ably
     * @param {string} config.clientKey - API Key del cliente
     * @param {string} config.channelPrefix - Prefijo de canales (default: 'app')
     * @param {number} config.userId - ID del usuario actual
     * @param {number} config.agencyId - ID de la agencia (opcional)
     */
    constructor(config = {}) {
        this.config = {
            clientKey: config.clientKey || null,
            channelPrefix: config.channelPrefix || 'app',
            channelHuella: config.channelHuella || 'huella',
            userId: config.userId || null,
            agencyId: config.agencyId || null,
            enabled: config.enabled !== false,
            echoMessages: config.echoMessages || false,
            debug: config.debug || false
        };

        this.realtime = null;
        this.channels = new Map();
        this.handlers = new Map();
        this.connectionState = 'disconnected';
        this.reconnectAttempts = 0;
        this.maxReconnectAttempts = 5;

        if (this.config.enabled && this.config.clientKey) {
            this.init();
        } else {
            this.log('warn', 'Ably no está configurado o está deshabilitado');
        }
    }

    /**
     * Inicializa la conexión con Ably
     */
    init() {
        try {
            this.realtime = new Ably.Realtime({
                key: this.config.clientKey,
                echoMessages: this.config.echoMessages,
                autoConnect: true,
                disconnectedRetryTimeout: 15000,
                suspendedRetryTimeout: 30000
            });

            this.setupConnectionHandlers();
            this.log('info', '✅ Ably inicializado correctamente');
        } catch (error) {
            this.log('error', '❌ Error inicializando Ably:', error);
            this.config.enabled = false;
        }
    }

    /**
     * Configura los manejadores de conexión
     */
    setupConnectionHandlers() {
        this.realtime.connection.on('connected', () => {
            this.connectionState = 'connected';
            this.reconnectAttempts = 0;
            this.log('info', '✅ Conectado a Ably');
            this.emit('connected');
        });

        this.realtime.connection.on('connecting', () => {
            this.connectionState = 'connecting';
            this.log('info', '🔄 Conectando a Ably...');
        });

        this.realtime.connection.on('disconnected', () => {
            this.connectionState = 'disconnected';
            this.log('warn', '⚠️ Desconectado de Ably');
            this.emit('disconnected');
        });

        this.realtime.connection.on('suspended', () => {
            this.connectionState = 'suspended';
            this.log('warn', '⚠️ Conexión suspendida');
            this.emit('suspended');
        });

        this.realtime.connection.on('failed', () => {
            this.connectionState = 'failed';
            this.log('error', '❌ Error de conexión');
            this.emit('failed');
            this.handleReconnect();
        });
    }

    /**
     * Maneja la reconexión automática
     */
    handleReconnect() {
        if (this.reconnectAttempts < this.maxReconnectAttempts) {
            this.reconnectAttempts++;
            const delay = Math.min(1000 * Math.pow(2, this.reconnectAttempts), 30000);
            
            this.log('info', `🔄 Reintentando conexión en ${delay/1000}s (intento ${this.reconnectAttempts})`);
            
            setTimeout(() => {
                this.realtime.connection.connect();
            }, delay);
        } else {
            this.log('error', '❌ Máximo de reintentos alcanzado');
            this.emit('max-reconnect-reached');
        }
    }

    /**
     * Suscribe al canal del usuario actual
     * @param {Function} onNotification - Callback para notificaciones
     * @param {Function} onDataUpdate - Callback para actualizaciones de datos
     * @param {Function} onAlert - Callback para alertas
     */
    subscribeToUser(onNotification, onDataUpdate, onAlert) {
        if (!this.config.userId) {
            this.log('warn', 'No se puede suscribir: userId no configurado');
            return null;
        }

        const channelName = `${this.config.channelPrefix}:user:${this.config.userId}`;
        const channel = this.getChannel(channelName);

        // Notificaciones generales
        if (onNotification) {
            this.subscribe(channel, 'notification', (message) => {
                this.log('debug', '📩 Notificación recibida:', message.data);
                onNotification(message.data);
            });
        }

        // Actualizaciones de datos
        if (onDataUpdate) {
            this.subscribe(channel, 'data.updated', (message) => {
                this.log('debug', '🔄 Datos actualizados:', message.data);
                onDataUpdate(message.data);
            });
        }

        // Alertas
        if (onAlert) {
            this.subscribe(channel, 'alert', (message) => {
                this.log('debug', '⚠️ Alerta recibida:', message.data);
                onAlert(message.data);
            });
        }

        return channel;
    }

    /**
     * Suscribe al canal de la agencia
     * @param {Function} callback - Callback para notificaciones de agencia
     */
    subscribeToAgency(callback) {
        if (!this.config.agencyId) {
            this.log('warn', 'No se puede suscribir: agencyId no configurado');
            return null;
        }

        const channelName = `${this.config.channelPrefix}:agency:${this.config.agencyId}`;
        const channel = this.getChannel(channelName);

        this.subscribe(channel, 'notification', (message) => {
            this.log('debug', '📢 Notificación de agencia:', message.data);
            callback(message.data);
        });

        return channel;
    }

    /**
     * Suscribe al canal broadcast
     * @param {Function} callback - Callback para mensajes broadcast
     */
    subscribeToBroadcast(callback) {
        const channelName = `${this.config.channelPrefix}:broadcast`;
        const channel = this.getChannel(channelName);

        this.subscribe(channel, 'notification', (message) => {
            this.log('debug', '📡 Broadcast recibido:', message.data);
            callback(message.data);
        });

        return channel;
    }

    /**
     * Suscribe a un canal personalizado
     * @param {string} channelName - Nombre del canal
     * @param {string} eventName - Nombre del evento
     * @param {Function} callback - Callback para mensajes
     */
    subscribeToChannel(channelName, eventName, callback) {
        const channel = this.getChannel(channelName);
        this.subscribe(channel, eventName, callback);
        return channel;
    }

    /**
     * Obtiene o crea un canal
     * @param {string} channelName - Nombre del canal
     * @returns {Object} Canal de Ably
     */
    getChannel(channelName) {
        if (!this.channels.has(channelName)) {
            const channel = this.realtime.channels.get(channelName);
            this.channels.set(channelName, channel);
            this.log('debug', `📝 Canal creado: ${channelName}`);
        }
        return this.channels.get(channelName);
    }

    /**
     * Suscribe a un evento en un canal
     * @param {Object} channel - Canal de Ably
     * @param {string} eventName - Nombre del evento
     * @param {Function} callback - Callback
     */
    subscribe(channel, eventName, callback) {
        channel.subscribe(eventName, callback);
        
        // Guardar referencia para poder desuscribir después
        const key = `${channel.name}:${eventName}`;
        if (!this.handlers.has(key)) {
            this.handlers.set(key, []);
        }
        this.handlers.get(key).push(callback);
    }

    /**
     * Desuscribe de un canal/evento
     * @param {string} channelName - Nombre del canal
     * @param {string} eventName - Nombre del evento (opcional)
     */
    unsubscribe(channelName, eventName = null) {
        const channel = this.channels.get(channelName);
        if (!channel) return;

        if (eventName) {
            channel.unsubscribe(eventName);
            const key = `${channelName}:${eventName}`;
            this.handlers.delete(key);
            this.log('debug', `🔇 Desuscrito de ${channelName}:${eventName}`);
        } else {
            channel.unsubscribe();
            // Limpiar todos los handlers del canal
            for (const [key] of this.handlers) {
                if (key.startsWith(channelName + ':')) {
                    this.handlers.delete(key);
                }
            }
            this.log('debug', `🔇 Desuscrito de ${channelName}`);
        }
    }

    /**
     * Desuscribe de todos los canales
     */
    unsubscribeAll() {
        for (const [channelName, channel] of this.channels) {
            channel.unsubscribe();
            this.log('debug', `🔇 Desuscrito de ${channelName}`);
        }
        this.handlers.clear();
        this.channels.clear();
    }

    /**
     * Publica un mensaje en un canal
     * @param {string} channelName - Nombre del canal
     * @param {string} eventName - Nombre del evento
     * @param {Object} data - Datos a enviar
     */
    publish(channelName, eventName, data) {
        if (!this.isConnected()) {
            this.log('warn', 'No se puede publicar: no conectado');
            return Promise.reject(new Error('Not connected'));
        }

        const channel = this.getChannel(channelName);
        return channel.publish(eventName, data);
    }

    /**
     * Obtiene el historial de un canal
     * @param {string} channelName - Nombre del canal
     * @param {Object} options - Opciones (limit, direction, etc)
     */
    async getHistory(channelName, options = {}) {
        const channel = this.getChannel(channelName);
        const defaultOptions = { limit: 10, direction: 'backwards', ...options };
        
        try {
            const history = await channel.history(defaultOptions);
            return history.items.map(msg => ({
                id: msg.id,
                name: msg.name,
                data: msg.data,
                timestamp: msg.timestamp
            }));
        } catch (error) {
            this.log('error', 'Error obteniendo historial:', error);
            return [];
        }
    }

    /**
     * Verifica si está conectado
     * @returns {boolean}
     */
    isConnected() {
        return this.connectionState === 'connected';
    }

    /**
     * Obtiene el estado de la conexión
     * @returns {string}
     */
    getConnectionState() {
        return this.connectionState;
    }

    /**
     * Cierra la conexión
     */
    close() {
        if (this.realtime) {
            this.unsubscribeAll();
            this.realtime.close();
            this.connectionState = 'closed';
            this.log('info', '🔌 Conexión cerrada');
        }
    }

    /**
     * Emite un evento personalizado
     * @param {string} eventName - Nombre del evento
     * @param {*} data - Datos del evento
     */
    emit(eventName, data = null) {
        const event = new CustomEvent(`ably:${eventName}`, { detail: data });
        window.dispatchEvent(event);
    }

    /**
     * Escucha eventos personalizados
     * @param {string} eventName - Nombre del evento
     * @param {Function} callback - Callback
     */
    on(eventName, callback) {
        window.addEventListener(`ably:${eventName}`, (e) => callback(e.detail));
    }

    /**
     * Logging condicional
     * @param {string} level - Nivel del log (info, warn, error, debug)
     * @param {...any} args - Argumentos a loguear
     */
    log(level, ...args) {
        if (level === 'debug' && !this.config.debug) return;
        
        const styles = {
            info: 'color: #3b82f6',
            warn: 'color: #f59e0b',
            error: 'color: #ef4444',
            debug: 'color: #8b5cf6'
        };

        console[level === 'debug' ? 'log' : level](
            `%c[Ably ${level.toUpperCase()}]`,
            styles[level] || '',
            ...args
        );
    }
}

// Helper functions para uso común
export const NotificationHelper = {
    /**
     * Muestra una notificación toast
     * @param {Object} data - Datos de la notificación
     * @param {string} data.type - Tipo de notificación (success, error, warning, info)
     * @param {string} data.message - Mensaje a mostrar
     * @param {boolean} data.useSwal - Forzar uso de SweetAlert2 (default: false)
     * @param {number} data.duration - Duración en ms (default: 5000)
     */
    showToast(data) {
        const { type, message, useSwal = false, duration = 5000 } = data;
        
        // Si se especifica explícitamente usar SweetAlert2
        if (useSwal && window.Swal) {
            const Toast = window.Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duration,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', window.Swal.stopTimer);
                    toast.addEventListener('mouseleave', window.Swal.resumeTimer);
                }
            });

            const icons = {
                success: 'success',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };

            Toast.fire({
                icon: icons[type] || 'info',
                title: message
            });
        }
        // Prioridad 1: Toastr (por defecto)
        else if (window.toastr) {
            const toastrType = type || 'info';
            const options = {
                closeButton: true,
                progressBar: true,
                timeOut: duration,
                positionClass: 'toast-top-right'
            };
            
            window.toastr[toastrType](message, '', options);
        }
        // Prioridad 2: SweetAlert2 (si Toastr no está disponible)
        else if (window.Swal) {
            const Toast = window.Swal.mixin({
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: duration,
                timerProgressBar: true,
                didOpen: (toast) => {
                    toast.addEventListener('mouseenter', window.Swal.stopTimer);
                    toast.addEventListener('mouseleave', window.Swal.resumeTimer);
                }
            });

            const icons = {
                success: 'success',
                error: 'error',
                warning: 'warning',
                info: 'info'
            };

            Toast.fire({
                icon: icons[type] || 'info',
                title: message
            });
        }
        // Fallback a console
        else {
            console.log(`[${type?.toUpperCase()}] ${message}`);
        }
    },

    /**
     * Reproduce sonido de notificación
     * @param {string} soundPath - Ruta del archivo de audio
     */
    playSound(soundPath = '/assets/sounds/notification.mp3') {
        try {
            const audio = new Audio(soundPath);
            audio.volume = 0.5;
            audio.play().catch(e => console.warn('No se pudo reproducir sonido:', e));
        } catch (error) {
            console.warn('Error reproduciendo sonido:', error);
        }
    },

    /**
     * Recarga una tabla DataTable
     * @param {string} tableId - ID de la tabla
     */
    reloadDataTable(tableId) {
        if (window.$ && $.fn.DataTable) {
            const table = $(`#${tableId}`).DataTable();
            if (table) {
                table.ajax.reload(null, false);
            }
        }
    }
};

export default AblyNotificationHelper;
