<?php

namespace Micro\Helpers;

use Micro\Generic\AblyService;

/**
 * Helper para enviar notificaciones de manera simplificada
 * 
 * Proporciona una interfaz de alto nivel sobre AblyService
 * para casos de uso comunes de notificaciones.
 * 
 * @package Micro\Helpers
 */
class NotificationService
{
    /**
     * Tipos de notificación comunes
     */
    public const TYPE_SUCCESS = 'success';
    public const TYPE_ERROR = 'error';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';
    
    /**
     * Eventos de sistema
     */
    public const EVENT_USER_LOGGED_IN = 'user.logged_in';
    public const EVENT_USER_LOGGED_OUT = 'user.logged_out';
    public const EVENT_DATA_UPDATED = 'data.updated';
    public const EVENT_NEW_MESSAGE = 'message.new';
    public const EVENT_TASK_COMPLETED = 'task.completed';
    public const EVENT_ALERT = 'alert';
    public const EVENT_FINGERPRINT_CAPTURE = 'fingerprint.capture';
    public const EVENT_FINGERPRINT_VERIFY = 'fingerprint.verify';

    private AblyService $ably;

    public function __construct()
    {
        $this->ably = AblyService::getInstance();
    }

    /**
     * Obtiene la instancia del servicio
     */
    public static function getInstance(): self
    {
        return new self();
    }

    /**
     * Envía una notificación de éxito al usuario
     */
    public function success($userId, string $message, array $additionalData = []): bool
    {
        return $this->notify($userId, self::TYPE_SUCCESS, $message, $additionalData);
    }

    /**
     * Envía una notificación de error al usuario
     */
    public function error($userId, string $message, array $additionalData = []): bool
    {
        return $this->notify($userId, self::TYPE_ERROR, $message, $additionalData);
    }

    /**
     * Envía una notificación de advertencia al usuario
     */
    public function warning($userId, string $message, array $additionalData = []): bool
    {
        return $this->notify($userId, self::TYPE_WARNING, $message, $additionalData);
    }

    /**
     * Envía una notificación informativa al usuario
     */
    public function info($userId, string $message, array $additionalData = []): bool
    {
        return $this->notify($userId, self::TYPE_INFO, $message, $additionalData);
    }

    /**
     * Envía una notificación genérica al usuario
     */
    public function notify($userId, string $type, string $message, array $additionalData = []): bool
    {
        $data = array_merge([
            'type' => $type,
            'message' => $message,
        ], $additionalData);

        return $this->ably->notifyUser($userId, 'notification', $data);
    }

    /**
     * Notifica a un usuario sobre una actualización de datos
     */
    public function dataUpdated($userId, string $entity, $entityId, array $changes = []): bool
    {
        return $this->ably->notifyUser($userId, self::EVENT_DATA_UPDATED, [
            'entity' => $entity,
            'entityId' => $entityId,
            'changes' => $changes,
        ]);
    }

    /**
     * Envía una alerta a múltiples usuarios
     */
    public function alertUsers(array $userIds, string $message, string $severity = 'info', array $additionalData = []): array
    {
        $data = array_merge([
            'message' => $message,
            'severity' => $severity,
        ], $additionalData);

        return $this->ably->notifyUsers($userIds, self::EVENT_ALERT, $data);
    }

    /**
     * Notifica a todos los usuarios de una agencia
     */
    public function notifyAgency($agencyId, string $message, string $type = self::TYPE_INFO, array $additionalData = []): bool
    {
        $data = array_merge([
            'type' => $type,
            'message' => $message,
        ], $additionalData);

        return $this->ably->notifyAgency($agencyId, 'notification', $data);
    }

    /**
     * Envía un broadcast a todos los usuarios
     */
    public function broadcast(string $message, string $type = self::TYPE_INFO, array $additionalData = []): bool
    {
        $data = array_merge([
            'type' => $type,
            'message' => $message,
        ], $additionalData);

        return $this->ably->broadcast('notification', $data);
    }

    /**
     * Verifica si el servicio de notificaciones está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->ably->isEnabled();
    }

    /**
     * Obtiene la configuración del cliente
     */
    public function getClientConfig(): array
    {
        return $this->ably->getClientConfig();
    }
}