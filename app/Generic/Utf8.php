<?php

namespace Micro\Generic;

/**
 * Clase para conversión de encodings UTF-8 e ISO-8859-1
 * 
 * Esta clase proporciona alternativas a las funciones utf8_decode() y utf8_encode() 
 * que fueron deprecadas en PHP 8.2
 */
class Utf8
{
    /**
     * Decodifica un string de UTF-8 a ISO-8859-1
     * Reemplazo de utf8_decode()
     * 
     * @param string $string El string en UTF-8 a decodificar
     * @return string El string convertido a ISO-8859-1
     */
    public static function decode($string)
    {
        if (empty($string)) {
            return $string;
        }
        
        return mb_convert_encoding($string, 'ISO-8859-1', 'UTF-8');
    }

    /**
     * Codifica un string de ISO-8859-1 a UTF-8
     * Reemplazo de utf8_encode()
     * 
     * @param string $string El string en ISO-8859-1 a codificar
     * @return string El string convertido a UTF-8
     */
    public static function encode($string)
    {
        if (empty($string)) {
            return $string;
        }
        
        return mb_convert_encoding($string, 'UTF-8', 'ISO-8859-1');
    }
}
