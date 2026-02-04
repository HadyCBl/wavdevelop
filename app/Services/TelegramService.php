<?php

namespace Micro\Services;

use Micro\Helpers\Log;

/**
 * Servicio para enviar notificaciones a Telegram
 * 
 * Uso básico:
 * ```php
 * use Micro\Services\TelegramService;
 * 
 * $telegram = new TelegramService();
 * $telegram->send("Hola desde PHP!");
 * 
 * // Con formato Markdown
 * $telegram->sendMarkdown("*Mensaje en negrita* con `código`");
 * ```
 */
class TelegramService
{
    private ?string $botToken;
    private ?string $chatId;
    private string $apiUrl;
    private int $timeout;

    /**
     * Constructor del servicio de Telegram
     * 
     * @param string|null $botToken Token del bot (se obtiene de .env si no se proporciona)
     * @param string|null $chatId ID del chat (se obtiene de .env si no se proporciona)
     * @param int $timeout Timeout en segundos para las peticiones cURL
     */
    public function __construct(?string $botToken = null, ?string $chatId = null, int $timeout = 10)
    {
        $this->botToken = $botToken ?? ($_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN'));
        $this->chatId = $chatId ?? ($_ENV['TELEGRAM_CHAT_ID'] ?? getenv('TELEGRAM_CHAT_ID'));
        $this->timeout = $timeout;

        if ($this->botToken) {
            $this->apiUrl = "https://api.telegram.org/bot{$this->botToken}/";
        }
    }

    /**
     * Verifica si el servicio está configurado correctamente
     * 
     * @return bool
     */
    public function isConfigured(): bool
    {
        return !empty($this->botToken) && !empty($this->chatId);
    }

    /**
     * Envía un mensaje simple de texto
     * 
     * @param string $message Mensaje a enviar
     * @param string|null $chatId ID del chat (opcional, usa el por defecto si no se proporciona)
     * @return int|false ID del mensaje enviado o false si falla
     */
    public function send(string $message, ?string $chatId = null)
    {
        return $this->sendMessage($message, 'text', $chatId);
    }

    /**
     * Envía un mensaje con formato Markdown
     * 
     * @param string $message Mensaje en formato Markdown
     * @param string|null $chatId ID del chat (opcional)
     * @return int|false ID del mensaje enviado o false si falla
     */
    public function sendMarkdown(string $message, ?string $chatId = null)
    {
        return $this->sendMessage($message, 'Markdown', $chatId);
    }

    /**
     * Envía un mensaje con formato HTML
     * 
     * @param string $message Mensaje en formato HTML
     * @param string|null $chatId ID del chat (opcional)
     * @return int|false ID del mensaje enviado o false si falla
     */
    public function sendHTML(string $message, ?string $chatId = null)
    {
        return $this->sendMessage($message, 'HTML', $chatId);
    }

    /**
     * Envía un mensaje de error formateado
     * 
     * @param string $error Mensaje de error
     * @param array $context Contexto adicional (dominio, fecha, etc.)
     * @return int|false
     */
    public function sendError(string $error, array $context = [])
    {
        $domain = $context['domain'] ?? ($_SERVER['HTTP_HOST'] ?? php_uname('n'));
        $dateTime = $context['date'] ?? date('Y-m-d H:i:s');
        
        $message = "*❌ Error*\n\n" .
            "*Dominio:* `{$domain}`\n" .
            "*Fecha/Hora:* `{$dateTime}`\n" .
            "*Error:* `" . addslashes($error) . "`";

        // Agregar contexto adicional si existe
        foreach ($context as $key => $value) {
            if (!in_array($key, ['domain', 'date'])) {
                $key = ucfirst(str_replace('_', ' ', $key));
                $message .= "\n*{$key}:* `" . addslashes($value) . "`";
            }
        }

        return $this->sendMarkdown($message);
    }

    /**
     * Envía un mensaje de éxito formateado
     * 
     * @param string $title Título del mensaje
     * @param array $details Detalles adicionales
     * @return int|false
     */
    public function sendSuccess(string $title, array $details = [])
    {
        $message = "*✅ {$title}*\n\n";
        
        foreach ($details as $key => $value) {
            $key = ucfirst(str_replace('_', ' ', $key));
            $message .= "*{$key}:* `{$value}`\n";
        }

        return $this->sendMarkdown($message);
    }

    /**
     * Método principal para enviar mensajes
     * 
     * @param string $message Mensaje a enviar
     * @param string $parseMode Modo de parseo ('text', 'Markdown', 'HTML')
     * @param string|null $chatId ID del chat
     * @return int|false ID del mensaje o false si falla
     */
    private function sendMessage(string $message, string $parseMode = 'text', ?string $chatId = null)
    {
        if (!$this->isConfigured()) {
            Log::error("TelegramService: Bot Token o Chat ID no configurados");
            return false;
        }

        $targetChatId = $chatId ?? $this->chatId;
        $endpoint = $this->apiUrl . 'sendMessage';

        $postData = [
            'chat_id' => $targetChatId,
            'text' => $message,
        ];

        if ($parseMode !== 'text') {
            $postData['parse_mode'] = $parseMode;
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            Log::error("TelegramService cURL error: {$curlError}");
            return false;
        }

        if ($httpCode !== 200) {
            Log::error("TelegramService API error (HTTP {$httpCode}): {$response}");
            return false;
        }

        $responseData = json_decode($response, true);
        
        if (isset($responseData['ok']) && $responseData['ok'] === true && isset($responseData['result']['message_id'])) {
            return $responseData['result']['message_id'];
        }

        Log::error("TelegramService unexpected response: {$response}");
        return false;
    }

    /**
     * Obtiene información sobre el bot
     * 
     * @return array|false Información del bot o false si falla
     */
    public function getBotInfo()
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $endpoint = $this->apiUrl . 'getMe';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);

        $response = curl_exec($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        return $responseData['ok'] ? $responseData['result'] : false;
    }
}
