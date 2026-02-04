<?php
/**
 * Clase de utilidad para manejar las monedas de manera centralizada
 * 
 * Esta clase proporciona métodos para obtener la configuración de moneda
 * desde las variables de entorno y formatear valores monetarios.
 */
class CurrencyHelper
{
    /**
     * Obtiene la configuración de moneda desde las variables de entorno
     * 
     * @return array Configuración de moneda
     */
    public static function getCurrencyConfig()
    {
        return [
            'principal' => $_ENV['DEFAULT_CURRENCY'] ?? 'QUETZALES',
            'plural' => $_ENV['DEFAULT_CURRENCY_PLURAL'] ?? 'QUETZALES',
            'singular' => $_ENV['DEFAULT_CURRENCY_SINGULAR'] ?? 'QUETZAL',
            'cent_singular' => $_ENV['DEFAULT_CURRENCY_CENT_SINGULAR'] ?? 'CENTAVO',
            'cent_plural' => $_ENV['DEFAULT_CURRENCY_CENT_PLURAL'] ?? 'CENTAVOS',
            'symbol' => $_ENV['SYMBOL_CURRENCY'] ?? 'Q',
            'conversion_rate' => floatval($_ENV['CONERVSION_MONEDA'] ?? '7.8')
        ];
    }

    /**
     * Formatea un valor monetario con el símbolo de la moneda
     * 
     * @param float $amount Valor a formatear
     * @param bool $withSymbol Si se debe incluir el símbolo de la moneda
     * @return string Valor formateado
     */
    public static function formatAmount($amount, $withSymbol = true)
    {
        $config = self::getCurrencyConfig();
        $formatted = number_format($amount, 2, '.', ',');
        
        if ($withSymbol) {
            return $config['symbol'] . ' ' . $formatted;
        }
        
        return $formatted;
    }

    /**
     * Convierte un valor de la moneda principal a dólares
     * 
     * @param float $amount Valor en la moneda principal
     * @return float Valor en dólares
     */
    public static function convertToDollars($amount)
    {
        $config = self::getCurrencyConfig();
        return round($amount / $config['conversion_rate'], 2);
    }

    /**
     * Formatea un valor en dólares
     * 
     * @param float $amount Valor en dólares
     * @return string Valor formateado con símbolo de dólar
     */
    public static function formatDollars($amount)
    {
        return '$ ' . number_format($amount, 2, '.', ',');
    }

    /**
     * Obtiene el texto para la moneda en singular o plural según el valor
     * 
     * @param float $amount Valor a evaluar
     * @return string Texto de la moneda (singular o plural)
     */
    public static function getCurrencyText($amount)
    {
        $config = self::getCurrencyConfig();
        return $amount == 1 ? $config['singular'] : $config['plural'];
    }

    /**
     * Obtiene el texto para los centavos en singular o plural según el valor
     * 
     * @param float $amount Valor a evaluar
     * @return string Texto de los centavos (singular o plural)
     */
    public static function getCentsText($amount)
    {
        $config = self::getCurrencyConfig();
        $cents = round(($amount - floor($amount)) * 100);
        return $cents == 1 ? $config['cent_singular'] : $config['cent_plural'];
    }
} 