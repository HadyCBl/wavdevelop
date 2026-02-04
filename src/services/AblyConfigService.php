<?php

/**
 * Clase para manejar la configuración de Ably de manera segura
 */
class AblyConfigService {
    private static $instance = null;
    
    private function __construct() {}
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtiene la configuración de Ably de manera segura
     * @return array
     */
    public function getClientConfig() {
        return [
            'clientKey' => isset($_ENV['ABLY_CLIENT_KEY']) ? $_ENV['ABLY_CLIENT_KEY'] : null,
            'channelPrefix' => isset($_ENV['ABLY_CHANNEL_HUELLA']) ? $_ENV['ABLY_CHANNEL_HUELLA'] : null
        ];
    }
}
