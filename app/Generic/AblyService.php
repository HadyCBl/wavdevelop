<?php

namespace Micro\Generic;

use Micro\Helpers\Log;
use Exception;
use Micro\Exceptions\AblyServiceException;

/**
 * Servicio de Notificaciones usando Ably
 * 
 * Esta clase proporciona una interfaz unificada para:
 * - Comunicación con dispositivos de huella digital (funcionalidad existente)
 * - Notificaciones en tiempo real a usuarios y agencias
 * - Broadcast de mensajes
 * - Gestión de canales y tokens
 * 
 * @package Micro\Generic
 */
class AblyService
{
    private $ably;
    private static $instance = null;
    private $apiKey;
    private $timeout = 8000; // 8 segundos de timeout por defecto (aumentado para redes lentas)
    private array $config = [];

    /**
     * Constructor privado para patrón Singleton
     */
    private function __construct()
    {
        if (!isset($_ENV['ABLY_API_KEY'])) {
            throw new AblyServiceException("La clave de API de Ably no está configurada en las variables de entorno.");
        }
        
        $this->apiKey = $_ENV['ABLY_API_KEY'];
        $this->ably = new \Ably\AblyRest($this->apiKey);
        $this->loadConfig();
    }

    /**
     * Carga la configuración desde variables de entorno
     */
    private function loadConfig(): void
    {
        $this->config = [
            'apiKey' => $_ENV['ABLY_API_KEY'] ?? null,
            'clientKey' => $_ENV['ABLY_CLIENT_KEY'] ?? null,
            'channelPrefix' => $_ENV['ABLY_CHANNEL_PREFIX'] ?? 'app',
            'channelHuella' => $_ENV['ABLY_CHANNEL_HUELLA'] ?? 'huella',
        ];
    }

    /**
     * Obtiene la instancia única de AblyService (Singleton)
     * Retorna null si Ably no está configurado correctamente
     */
    public static function getInstance(): ?self
    {
        if (self::$instance === null) {
            try {
                self::$instance = new self();
            } catch (AblyServiceException $e) {
                Log::warning("AblyService no disponible: " . $e->getMessage());
                return null;
            }
        }
        return self::$instance;
    }

    /**
     * Configura el tiempo de espera para confirmaciones
     */
    public function setTimeout(int $milliseconds): self
    {
        $this->timeout = $milliseconds;
        return $this;
    }

    /**
     * Verifica si el servicio está habilitado y configurado
     */
    public function isEnabled(): bool
    {
        return $this->ably !== null && !empty($this->apiKey);
    }

    // ============================================
    // MÉTODOS DE HUELLA DIGITAL (EXISTENTES)
    // ============================================

    /**
     * Publica un mensaje y espera confirmación usando history
     * Estrategia mejorada: búsqueda backwards + polling optimizado
     * 
     * CÓMO FUNCIONA LA BÚSQUEDA DE CONFIRMACIÓN:
     * 1. Publica mensaje con messageId único en canal específico
     * 2. Espera 500ms inicial para propagación de red
     * 3. Consulta channel->history() cada 300-700ms (polling adaptativo)
     * 4. Busca en historial mensajes con name='confirmacion'
     * 5. Valida que messageId coincida y status='recibido'
     * 6. Retorna éxito o timeout después de $this->timeout ms
     */
    public function publishWithConfirmation(string $channelName, string $eventName, array $data): array
    {
        try {
            // Generar ID único más robusto
            $messageId = uniqid('req_', true) . '_' . bin2hex(random_bytes(4));
            $data['messageId'] = $messageId;
            $data['timestamp'] = round(microtime(true) * 1000);

            $channel = $this->ably->channel($channelName);

            // ✅ Timestamp en milisegundos para Ably (marca temporal ANTES de publicar)
            $beforePublish = round(microtime(true) * 1000);

            Log::info("📤 Enviando mensaje", [
                'channel' => $channelName,
                'messageId' => $messageId,
                'timestamp' => $beforePublish,
                'eventName' => $eventName
            ]);

            // Publicar el mensaje principal
            $channel->publish($eventName, $data);

            // ✅ Espera inicial para propagación de red + procesamiento Java
            usleep(500000); // 500ms inicial

            $startTime = microtime(true) * 1000;
            $attempts = 0;

            while ((microtime(true) * 1000 - $startTime) < $this->timeout) {
                $attempts++;

                // ✅ BÚSQUEDA EN HISTORIAL: Obtiene últimos mensajes del canal
                $messages = $channel->history([
                    'direction' => 'backwards',
                    'limit' => 30  // Aumentado a 30 para mayor margen
                ]);

                $messageCount = count($messages->items);
                
                // 🐛 DEBUG: Log de todos los mensajes recibidos
                $debugMessages = [];
                foreach ($messages->items as $msg) {
                    $debugMessages[] = [
                        'name' => $msg->name,
                        'id' => $msg->id ?? 'sin-id',
                        'timestamp' => $msg->timestamp ?? 'sin-timestamp',
                        'data_preview' => substr(json_encode($msg->data), 0, 100)
                    ];
                }
                
                Log::debug("🔍 Intento $attempts: $messageCount mensajes", [
                    'elapsed' => round(microtime(true) * 1000 - $startTime) . 'ms',
                    'channel' => $channelName,
                    'messageId_buscado' => $messageId,
                    'mensajes' => $debugMessages
                ]);

                // Iterar sobre los mensajes recibidos
                foreach ($messages->items as $message) {
                    // ✅ FILTRO 1: Solo procesar eventos con name='confirmacion'
                    if ($message->name !== 'confirmacion') {
                        continue;
                    }

                    // ✅ PARSEO: Convertir data a array asociativo
                    $messageData = is_string($message->data)
                        ? json_decode($message->data, true)
                        : (is_object($message->data) ? json_decode(json_encode($message->data), true) : $message->data);

                    // 🐛 DEBUG: Log del mensaje de confirmación encontrado
                    Log::debug("🔔 Confirmación encontrada", [
                        'messageId_recibido' => $messageData['messageId'] ?? 'sin-messageId',
                        'messageId_esperado' => $messageId,
                        'status' => $messageData['status'] ?? 'sin-status',
                        'dispositivo' => $messageData['dispositivo'] ?? 'sin-dispositivo',
                        'data_completa' => $messageData
                    ]);

                    // ✅ VALIDACIÓN ESTRICTA: Verificar estructura y contenido
                    if (
                        isset($messageData['messageId']) &&
                        $messageData['messageId'] === $messageId &&
                        isset($messageData['status']) &&
                        $messageData['status'] === 'recibido'
                    ) {
                        $latency = microtime(true) * 1000 - $startTime;

                        Log::info("✅ Confirmación recibida", [
                            'messageId' => $messageId,
                            'device' => $messageData['dispositivo'] ?? 'unknown',
                            'latency' => round($latency) . 'ms',
                            'attempts' => $attempts
                        ]);

                        return [
                            'status' => 'success',
                            'message' => 'Mensaje recibido por el dispositivo',
                            'deviceId' => $messageData['dispositivo'] ?? 'unknown',
                            'latency' => round($latency),
                            'messageId' => $messageId
                        ];
                    }
                }

                // ✅ POLLING ADAPTATIVO: Esperas progresivas para optimizar recursos
                if ($attempts <= 3) {
                    usleep(300000); // 300ms primeros 3 intentos
                } elseif ($attempts <= 8) {
                    usleep(500000); // 500ms siguientes 5 intentos
                } else {
                    usleep(700000); // 700ms después
                }
            }

            // ❌ TIMEOUT: No se recibió confirmación en el tiempo límite
            Log::error("❌ Timeout esperando confirmación", [
                'messageId' => $messageId,
                'channel' => $channelName,
                'timeout' => $this->timeout . 'ms',
                'attempts' => $attempts,
                'elapsedTime' => round(microtime(true) * 1000 - $startTime) . 'ms'
            ]);

            throw new AblyServiceException(
                "El dispositivo no respondió después de {$attempts} intentos (" . 
                round($this->timeout / 1000, 1) . "s). " .
                "Verifique que la aplicación biométrica esté ejecutándose y conectada a Internet."
            );
        } catch (AblyServiceException $e) {
            throw $e;
        } catch (Exception $e) {
            Log::error("❌ Error en comunicación Ably", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new AblyServiceException("Error en comunicación: " . $e->getMessage());
        }
    }

    /**
     * ✅ Publica mensaje con validación de canal único por dispositivo
     */
    public function publishHuellaDigital(string $pcSerial, array $data): array
    {
        if (!isset($_ENV['ABLY_CHANNEL_HUELLA'])) {
            throw new AblyServiceException("El canal de huella digital no está configurado.");
        }

        // ✅ Canal único por dispositivo: evita interferencia
        $channelName = $_ENV['ABLY_CHANNEL_HUELLA'] . "_$pcSerial";

        Log::info("📡 Canal de huella", [
            'channel' => $channelName,
            'device' => $pcSerial
        ]);

        return $this->publishWithConfirmation($channelName, 'huella_digital_service', $data);
    }

    /**
     * @deprecated Usar publishHuellaDigital() que incluye confirmación
     */
    public function publishHuellaDigitalant($pcSerial, $data)
    {
        if (!isset($_ENV['ABLY_CHANNEL_HUELLA'])) {
            throw new AblyServiceException("El canal de huella digital no está configurado en las variables de entorno.");
        }

        $channelName = $_ENV['ABLY_CHANNEL_HUELLA'] . "_$pcSerial";
        return $this->publish($channelName, 'huella_digital_service', $data);
    }

    /**
     * Publica mensaje de sincronización para huella digital
     */
    public function publisSinchHuellaDigital($pcSerial, $data)
    {
        if (!isset($_ENV['ABLY_CHANNEL_HUELLA'])) {
            throw new AblyServiceException("El canal de huella digital no está configurado en las variables de entorno.");
        }

        $channelName = $_ENV['ABLY_CHANNEL_HUELLA'] . "_$pcSerial";
        return $this->publish($channelName, 'sinc', $data);
    }

    // ============================================
    // MÉTODOS DE NOTIFICACIONES (NUEVOS)
    // ============================================

    /**
     * Publica un mensaje en un canal específico (sin esperar confirmación)
     * 
     * @param string $channelName Nombre del canal
     * @param string $eventName Nombre del evento
     * @param mixed $data Datos a publicar
     * @return bool
     * @throws AblyServiceException
     */
    public function publish($channelName, $eventName, $data)
    {
        try {
            $channel = $this->ably->channel($channelName);
            $channel->publish($eventName, $data);
            return true;
        } catch (Exception $e) {
            throw new AblyServiceException("Error al publicar mensaje en Ably: " . $e->getMessage());
        }
    }

    /**
     * Envía una notificación a un usuario específico
     * 
     * @param int|string $userId ID del usuario
     * @param string $event Tipo de evento
     * @param array $data Datos de la notificación
     * @return bool
     */
    public function notifyUser($userId, string $event, array $data): bool
    {
        if (!$this->isEnabled()) {
            Log::warning("Ably no está habilitado. Notificación no enviada a usuario {$userId}.");
            return false;
        }

        try {
            $channel = $this->getUserChannel($userId);
            
            $payload = array_merge($data, [
                'event' => $event,
                'userId' => $userId,
                'timestamp' => time(),
                'messageId' => $this->generateMessageId(),
            ]);

            return $this->publish($channel, $event, $payload);
        } catch (Exception $e) {
            Log::error("Error enviando notificación a usuario", [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envía una notificación a múltiples usuarios
     * 
     * @param array $userIds Array de IDs de usuarios
     * @param string $event Tipo de evento
     * @param array $data Datos de la notificación
     * @return array Array con resultados [userId => bool]
     */
    public function notifyUsers(array $userIds, string $event, array $data): array
    {
        $results = [];
        foreach ($userIds as $userId) {
            $results[$userId] = $this->notifyUser($userId, $event, $data);
        }
        return $results;
    }

    /**
     * Envía una notificación a un canal de agencia
     * 
     * @param int|string $agencyId ID de la agencia
     * @param string $event Tipo de evento
     * @param array $data Datos de la notificación
     * @return bool
     */
    public function notifyAgency($agencyId, string $event, array $data): bool
    {
        if (!$this->isEnabled()) {
            Log::warning("Ably no está habilitado. Notificación no enviada a agencia {$agencyId}.");
            return false;
        }

        try {
            $channel = $this->getAgencyChannel($agencyId);
            
            $payload = array_merge($data, [
                'event' => $event,
                'agencyId' => $agencyId,
                'timestamp' => time(),
                'messageId' => $this->generateMessageId(),
            ]);

            return $this->publish($channel, $event, $payload);
        } catch (Exception $e) {
            Log::error("Error enviando notificación a agencia", [
                'agencyId' => $agencyId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Envía una notificación broadcast a todos los usuarios conectados
     * 
     * @param string $event Tipo de evento
     * @param array $data Datos de la notificación
     * @return bool
     */
    public function broadcast(string $event, array $data): bool
    {
        if (!$this->isEnabled()) {
            Log::warning("Ably no está habilitado. Broadcast no enviado.");
            return false;
        }

        try {
            $channel = $this->getBroadcastChannel();
            
            $payload = array_merge($data, [
                'event' => $event,
                'timestamp' => time(),
                'messageId' => $this->generateMessageId(),
            ]);

            return $this->publish($channel, $event, $payload);
        } catch (Exception $e) {
            Log::error("Error enviando broadcast", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtiene el historial de mensajes de un canal
     * 
     * @param string $channel Nombre del canal
     * @param int $limit Límite de mensajes (máx 100)
     * @return array Array de mensajes
     */
    public function getChannelHistory(string $channel, int $limit = 10): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        try {
            $channelObj = $this->ably->channel($channel);
            $history = $channelObj->history(['limit' => min($limit, 100)]);
            
            $messages = [];
            foreach ($history->items as $message) {
                $messages[] = [
                    'id' => $message->id,
                    'name' => $message->name,
                    'data' => $message->data,
                    'timestamp' => $message->timestamp,
                ];
            }
            
            return $messages;
        } catch (Exception $e) {
            Log::error("Error obteniendo historial de Ably", [
                'channel' => $channel,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Genera un token temporal para un cliente
     * 
     * @param array $capability Capacidades del token
     * @param int $ttl Tiempo de vida en segundos (default: 1 hora)
     * @return array|null Token y configuración
     */
    public function generateClientToken(array $capability = [], int $ttl = 3600): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        try {
            $tokenParams = [
                'ttl' => $ttl * 1000, // Ably usa milisegundos
            ];

            if (!empty($capability)) {
                $tokenParams['capability'] = $capability;
            }

            $tokenDetails = $this->ably->auth->requestToken($tokenParams);

            return [
                'token' => $tokenDetails->token,
                'expires' => $tokenDetails->expires,
                'capability' => $tokenDetails->capability,
            ];
        } catch (Exception $e) {
            Log::error("Error generando token de Ably", [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    // ============================================
    // MÉTODOS DE CONFIGURACIÓN (EXISTENTES + MEJORADOS)
    // ============================================

    /**
     * Obtiene la configuración de Ably para el cliente (frontend)
     * @return array
     */
    public function getClientConfig()
    {
        return [
            'clientKey' => $this->config['clientKey'] ?? null,
            'channelPrefix' => $this->config['channelPrefix'] ?? 'app',
            'channelHuella' => $this->config['channelHuella'] ?? 'huella',
            'enabled' => $this->isEnabled(),
        ];
    }

    /**
     * Obtiene la configuración de Ably para aplicación desktop
     * @return array
     */
    public function getAppDesktopConfig()
    {
        return [
            'clientKey' => $this->apiKey,
            'channelPrefix' => $this->config['channelHuella'] ?? 'huella',
        ];
    }

    // ============================================
    // MÉTODOS AUXILIARES PRIVADOS
    // ============================================

    /**
     * Obtiene el nombre del canal para un usuario específico
     */
    private function getUserChannel($userId): string
    {
        return "{$this->config['channelPrefix']}:user:{$userId}";
    }

    /**
     * Obtiene el nombre del canal para una agencia
     */
    private function getAgencyChannel($agencyId): string
    {
        return "{$this->config['channelPrefix']}:agency:{$agencyId}";
    }

    /**
     * Obtiene el nombre del canal broadcast
     */
    private function getBroadcastChannel(): string
    {
        return "{$this->config['channelPrefix']}:broadcast";
    }

    /**
     * Genera un ID único para el mensaje
     */
    private function generateMessageId(): string
    {
        return uniqid('msg_', true) . '_' . bin2hex(random_bytes(4));
    }

    /**
     * Previene la clonación del Singleton
     */
    private function __clone() {}

    /**
     * Previene la deserialización del Singleton
     */
    public function __wakeup()
    {
        throw new Exception("No se puede deserializar un Singleton.");
    }
}