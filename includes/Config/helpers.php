<?php

use Micro\Helpers\Log;

if (!function_exists('setAppTimezone')) {
    // Log::info('Setting application timezone.');
    function setAppTimezone(?string $timezone = null, string $default = 'America/Guatemala')
    {
        // Log::info('Setting application timezone.', ['timezone' => $timezone, 'default' => $default]);
        // if (!isset($_ENV['APP_TIMEZONE'])) {
        //     Log::info('APP_TIMEZONE not set in .env file. Using default timezone: ' . $default);
        // } else {
        //     Log::info('APP_TIMEZONE found in .env file: ' . $_ENV['APP_TIMEZONE']);
        // } 
        $timezone = $timezone ?? $_ENV['APP_TIMEZONE'] ?? $default;
        if (in_array($timezone, timezone_identifiers_list())) {
            date_default_timezone_set($timezone);
        } else {
            date_default_timezone_set($default);
            Log::error("Invalid timezone: $timezone. Defaulting to $default.");
        }
    }
}
