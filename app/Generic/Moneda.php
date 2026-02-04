<?php

namespace Micro\Generic;

/**
 * Clase para el manejo y formato de monedas
 * 
 * Proporciona métodos estáticos para formatear números como moneda,
 * convertir montos y realizar operaciones relacionadas con dinero.
 * 
 * @package Micro\Generic
 */
class Moneda
{
    /**
     * Símbolo de moneda por defecto
     */
    const MONEDA_DEFECTO = 'Q';

    /**
     * Obtiene la moneda por defecto desde el entorno o usa el valor por defecto
     * 
     * @return string Símbolo de la moneda por defecto
     */
    public static function obtenerMonedaDefecto()
    {
        return $_ENV['SYMBOL_CURRENCY'] ?? self::MONEDA_DEFECTO;
    }

    /**
     * Configuración de monedas disponibles
     */
    const MONEDAS = [
        'Q' => ['nombre' => 'Quetzal', 'simbolo' => 'Q', 'decimales' => 2],
        'USD' => ['nombre' => 'Dólar', 'simbolo' => '$', 'decimales' => 2],
        'EUR' => ['nombre' => 'Euro', 'simbolo' => '€', 'decimales' => 2],
        'MXN' => ['nombre' => 'Peso Mexicano', 'simbolo' => '$', 'decimales' => 2],
        'GTQ' => ['nombre' => 'Quetzal', 'simbolo' => 'Q', 'decimales' => 2],
    ];

    /**
     * Formatea un número como moneda
     * 
     * @param float|int $numero El número a formatear
     * @param string $moneda Símbolo de la moneda (por defecto null, usa la del entorno)
     * @param int $decimales Número de decimales (por defecto 2)
     * @param bool $simboloAlFinal Colocar el símbolo al final (por defecto false)
     * @return string Número formateado como moneda
     */
    public static function formato($numero, $moneda = null, $decimales = 2, $simboloAlFinal = false)
    {
        $moneda = $moneda ?? self::obtenerMonedaDefecto();
        $numeroFormateado = number_format((float)$numero, $decimales, '.', ',');
        
        if ($simboloAlFinal) {
            return "{$numeroFormateado} {$moneda}";
        }
        
        return "{$moneda} {$numeroFormateado}";
    }

    /**
     * Formatea un número como moneda sin espacios
     * 
     * @param float|int $numero El número a formatear
     * @param string $moneda Símbolo de la moneda (por defecto null, usa la del entorno)
     * @param int $decimales Número de decimales
     * @return string Número formateado como moneda sin espacios
     */
    public static function formatoCompacto($numero, $moneda = null, $decimales = 2)
    {
        $moneda = $moneda ?? self::obtenerMonedaDefecto();
        return $moneda . number_format((float)$numero, $decimales, '.', ',');
    }

    /**
     * Formatea usando el separador de miles de forma local (español)
     * 
     * @param float|int $numero El número a formatear
     * @param string $moneda Símbolo de la moneda (por defecto null, usa la del entorno)
     * @param int $decimales Número de decimales
     * @return string Número formateado con separador local
     */
    public static function formatoLocal($numero, $moneda = null, $decimales = 2)
    {
        $moneda = $moneda ?? self::obtenerMonedaDefecto();
        // Formato español: punto para miles, coma para decimales
        $numeroFormateado = number_format((float)$numero, $decimales, ',', '.');
        return "{$moneda} {$numeroFormateado}";
    }

    /**
     * Formatea solo el número sin símbolo de moneda
     * 
     * @param float|int $numero El número a formatear
     * @param int $decimales Número de decimales
     * @return string Número formateado sin símbolo
     */
    public static function formatoSinSimbolo($numero, $decimales = 2)
    {
        return number_format((float)$numero, $decimales, '.', ',');
    }

    /**
     * Formatea un número en formato contable (negativos entre paréntesis)
     * 
     * @param float|int $numero El número a formatear
     * @param string $moneda Símbolo de la moneda (por defecto null, usa la del entorno)
     * @param int $decimales Número de decimales
     * @return string Número formateado en estilo contable
     */
    public static function formatoContable($numero, $moneda = null, $decimales = 2)
    {
        $moneda = $moneda ?? self::obtenerMonedaDefecto();
        $valor = (float)$numero;
        $numeroFormateado = number_format(abs($valor), $decimales, '.', ',');
        
        if ($valor < 0) {
            return "({$moneda} {$numeroFormateado})";
        }
        
        return "{$moneda} {$numeroFormateado}";
    }

    /**
     * Convierte un número a palabras (en español)
     * 
     * @param float|int $numero El número a convertir
     * @param string $moneda Nombre de la moneda (por defecto 'Quetzales')
     * @return string Número en palabras
     */
    public static function enPalabras($numero, $moneda = 'Quetzales')
    {
        $valor = (float)$numero;
        $entero = floor($valor);
        $decimales = round(($valor - $entero) * 100);
        
        $enteroEnPalabras = self::numeroALetras($entero);
        
        if ($decimales > 0) {
            return "{$enteroEnPalabras} {$moneda} con {$decimales}/100";
        }
        
        return "{$enteroEnPalabras} {$moneda} exactos";
    }

    /**
     * Obtiene el símbolo de una moneda por su código
     * 
     * @param string $codigo Código de la moneda
     * @return string Símbolo de la moneda
     */
    public static function obtenerSimbolo($codigo)
    {
        return self::MONEDAS[$codigo]['simbolo'] ?? $codigo;
    }

    /**
     * Obtiene el nombre completo de una moneda
     * 
     * @param string $codigo Código de la moneda
     * @return string Nombre de la moneda
     */
    public static function obtenerNombre($codigo)
    {
        return self::MONEDAS[$codigo]['nombre'] ?? $codigo;
    }

    /**
     * Valida si una moneda existe en la configuración
     * 
     * @param string $codigo Código de la moneda
     * @return bool True si existe, false si no
     */
    public static function esMonedaValida($codigo)
    {
        return isset(self::MONEDAS[$codigo]);
    }

    /**
     * Redondea un monto según las reglas bancarias
     * 
     * @param float $numero El número a redondear
     * @param int $decimales Número de decimales
     * @return float Número redondeado
     */
    public static function redondear($numero, $decimales = 2)
    {
        return round((float)$numero, $decimales, PHP_ROUND_HALF_UP);
    }

    /**
     * Calcula el porcentaje de un monto
     * 
     * @param float $monto El monto base
     * @param float $porcentaje El porcentaje a calcular
     * @param bool $sumar Si debe sumar el porcentaje al monto
     * @return float Resultado del cálculo
     */
    public static function calcularPorcentaje($monto, $porcentaje, $sumar = false)
    {
        $valor = (float)$monto * ((float)$porcentaje / 100);
        
        if ($sumar) {
            return $monto + $valor;
        }
        
        return $valor;
    }

    /**
     * Compara dos montos y retorna la diferencia
     * 
     * @param float $monto1 Primer monto
     * @param float $monto2 Segundo monto
     * @return float Diferencia entre los montos
     */
    public static function diferencia($monto1, $monto2)
    {
        return (float)$monto1 - (float)$monto2;
    }

    /**
     * Suma un array de montos
     * 
     * @param array $montos Array de montos a sumar
     * @return float Suma total
     */
    public static function sumar(array $montos)
    {
        return array_reduce($montos, function ($carry, $monto) {
            return $carry + (float)$monto;
        }, 0);
    }

    /**
     * Formatea un número como moneda con colores para HTML
     * 
     * @param float $numero El número a formatear
     * @param string $moneda Símbolo de la moneda (por defecto null, usa la del entorno)
     * @param string $colorPositivo Color para valores positivos (por defecto verde)
     * @param string $colorNegativo Color para valores negativos (por defecto rojo)
     * @return string HTML con el monto formateado y coloreado
     */
    public static function formatoHTML($numero, $moneda = null, $colorPositivo = '#28a745', $colorNegativo = '#dc3545')
    {
        $moneda = $moneda ?? self::obtenerMonedaDefecto();
        $valor = (float)$numero;
        $color = $valor >= 0 ? $colorPositivo : $colorNegativo;
        $montoFormateado = self::formato($valor, $moneda);
        
        return "<span style='color: {$color}; font-weight: bold;'>{$montoFormateado}</span>";
    }

    /**
     * Limpia una cadena de texto con formato de moneda y la convierte a float
     * 
     * @param string $cadena Cadena con formato de moneda
     * @return float Valor numérico
     */
    public static function limpiar($cadena)
    {
        // Eliminar símbolos de moneda y espacios
        $limpia = preg_replace('/[^0-9.,\-]/', '', $cadena);
        
        // Reemplazar coma por punto si es separador decimal
        if (substr_count($limpia, ',') === 1 && substr_count($limpia, '.') === 0) {
            $limpia = str_replace(',', '.', $limpia);
        } else {
            // Eliminar separadores de miles
            $limpia = str_replace(',', '', $limpia);
        }
        
        return (float)$limpia;
    }

    /**
     * Convierte un número entero a letras (español)
     * Método auxiliar privado para enPalabras()
     * 
     * @param int $numero Número a convertir
     * @return string Número en letras
     */
    private static function numeroALetras($numero)
    {
        $unidades = ['', 'UNO', 'DOS', 'TRES', 'CUATRO', 'CINCO', 'SEIS', 'SIETE', 'OCHO', 'NUEVE'];
        $decenas = ['', 'DIEZ', 'VEINTE', 'TREINTA', 'CUARENTA', 'CINCUENTA', 'SESENTA', 'SETENTA', 'OCHENTA', 'NOVENTA'];
        $especiales = ['DIEZ', 'ONCE', 'DOCE', 'TRECE', 'CATORCE', 'QUINCE', 'DIECISEIS', 'DIECISIETE', 'DIECIOCHO', 'DIECINUEVE'];
        $centenas = ['', 'CIENTO', 'DOSCIENTOS', 'TRESCIENTOS', 'CUATROCIENTOS', 'QUINIENTOS', 'SEISCIENTOS', 'SETECIENTOS', 'OCHOCIENTOS', 'NOVECIENTOS'];
        
        if ($numero == 0) return 'CERO';
        if ($numero == 100) return 'CIEN';
        
        $resultado = '';
        
        // Millones
        if ($numero >= 1000000) {
            $millones = floor($numero / 1000000);
            $resultado .= ($millones == 1 ? 'UN MILLON' : self::numeroALetras($millones) . ' MILLONES');
            $numero %= 1000000;
            if ($numero > 0) $resultado .= ' ';
        }
        
        // Miles
        if ($numero >= 1000) {
            $miles = floor($numero / 1000);
            $resultado .= ($miles == 1 ? 'MIL' : self::numeroALetras($miles) . ' MIL');
            $numero %= 1000;
            if ($numero > 0) $resultado .= ' ';
        }
        
        // Centenas
        if ($numero >= 100) {
            $resultado .= $centenas[floor($numero / 100)];
            $numero %= 100;
            if ($numero > 0) $resultado .= ' ';
        }
        
        // Decenas y unidades
        if ($numero >= 10 && $numero < 20) {
            $resultado .= $especiales[$numero - 10];
        } elseif ($numero >= 20) {
            $resultado .= $decenas[floor($numero / 10)];
            $numero %= 10;
            if ($numero > 0) $resultado .= ' Y ' . $unidades[$numero];
        } elseif ($numero > 0) {
            $resultado .= $unidades[$numero];
        }
        
        return $resultado;
    }
}
