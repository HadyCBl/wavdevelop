<?php

namespace Micro\Generic;

use DateTime;
use DateTimeZone;
use Exception;
use Micro\Helpers\Log;

/**
 * Clase para manejo y formateo de fechas
 */
class Date
{
    /**
     * Convierte una fecha a formato dd/mm/yyyy
     * 
     * @param string|DateTime|null $date Fecha a convertir
     * @param string $inputFormat Formato de entrada (default: 'Y-m-d')
     * @return string Fecha formateada como dd/mm/yyyy o cadena vacía si es inválida
     * 
     * @example
     * Date::toDMY('2025-10-25'); // Retorna: "25/10/2025"
     * Date::toDMY('2025-10-25 14:30:00'); // Retorna: "25/10/2025"
     * Date::toDMY(null); // Retorna: ""
     */
    public static function toDMY($date, string $inputFormat = 'Y-m-d'): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            if ($date instanceof DateTime) {
                return $date->format('d/m/Y');
            }

            // Si es un timestamp numérico
            if (is_numeric($date)) {
                $dateObj = new DateTime();
                $dateObj->setTimestamp((int)$date);
                return $dateObj->format('d/m/Y');
            }

            // Intentar crear DateTime desde string
            $dateObj = DateTime::createFromFormat($inputFormat, $date);

            // Si falla con el formato especificado, intentar con formatos comunes
            if ($dateObj === false) {
                $commonFormats = [
                    'Y-m-d H:i:s',
                    'Y-m-d',
                    'd/m/Y',
                    'd-m-Y',
                    'Y/m/d',
                    'm/d/Y',
                ];

                foreach ($commonFormats as $format) {
                    $dateObj = DateTime::createFromFormat($format, $date);
                    if ($dateObj !== false) {
                        break;
                    }
                }

                // Si aún falla, intentar con strtotime
                if ($dateObj === false) {
                    $timestamp = strtotime($date);
                    if ($timestamp !== false) {
                        $dateObj = new DateTime();
                        $dateObj->setTimestamp($timestamp);
                    }
                }
            }

            if ($dateObj !== false) {
                return $dateObj->format('d-m-Y');
            }

            return '';
        } catch (Exception $e) {
            // error_log("Error al convertir fecha: " . $e->getMessage());
            Log::error('Error al convertir fecha: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Convierte una fecha a formato yyyy-mm-dd
     * 
     * @param string|DateTime|null $date Fecha a convertir
     * @param string $inputFormat Formato de entrada (default: 'd/m/Y')
     * @return string Fecha formateada como yyyy-mm-dd o cadena vacía si es inválida
     * 
     * @example
     * Date::toYMD('25/10/2025'); // Retorna: "2025-10-25"
     */
    public static function toYMD($date, string $inputFormat = 'd/m/Y'): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d');
            }

            if (is_numeric($date)) {
                $dateObj = new DateTime();
                $dateObj->setTimestamp((int)$date);
                return $dateObj->format('Y-m-d');
            }

            $dateObj = DateTime::createFromFormat($inputFormat, $date);

            if ($dateObj === false) {
                $commonFormats = [
                    'd/m/Y H:i:s',
                    'd/m/Y',
                    'Y-m-d',
                    'd-m-Y',
                    'Y/m/d',
                    'm/d/Y',
                ];

                foreach ($commonFormats as $format) {
                    $dateObj = DateTime::createFromFormat($format, $date);
                    if ($dateObj !== false) {
                        break;
                    }
                }

                if ($dateObj === false) {
                    $timestamp = strtotime($date);
                    if ($timestamp !== false) {
                        $dateObj = new DateTime();
                        $dateObj->setTimestamp($timestamp);
                    }
                }
            }

            if ($dateObj !== false) {
                return $dateObj->format('Y-m-d');
            }

            return '';
        } catch (Exception $e) {
            // error_log("Error al convertir fecha: " . $e->getMessage());
            Log::error('Error al convertir fecha: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Convierte una fecha a formato yyyy-mm-dd hh:mm:ss
     * 
     * @param string|DateTime|null $date Fecha a convertir
     * @return string Fecha y hora formateada o cadena vacía si es inválida
     * 
     * @example
     * Date::toDateTime('25/10/2025'); // Retorna: "2025-10-25 00:00:00"
     */
    public static function toDateTime($date): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            if ($date instanceof DateTime) {
                return $date->format('Y-m-d H:i:s');
            }

            if (is_numeric($date)) {
                $dateObj = new DateTime();
                $dateObj->setTimestamp((int)$date);
                return $dateObj->format('Y-m-d H:i:s');
            }

            $timestamp = strtotime($date);
            if ($timestamp !== false) {
                $dateObj = new DateTime();
                $dateObj->setTimestamp($timestamp);
                return $dateObj->format('Y-m-d H:i:s');
            }

            return '';
        } catch (Exception $e) {
            // error_log("Error al convertir fecha: " . $e->getMessage());
            Log::error('Error al convertir fecha: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Obtiene la fecha actual en formato especificado
     * 
     * @param string $format Formato de salida (default: 'Y-m-d')
     * @param string|null $timezone Zona horaria (default: null - usa la del servidor)
     * @return string Fecha actual formateada
     * 
     * @example
     * Date::now(); // Retorna: "2025-10-25"
     * Date::now('d/m/Y'); // Retorna: "25/10/2025"
     * Date::now('Y-m-d H:i:s', 'America/Guatemala'); // Retorna: "2025-10-25 14:30:00"
     */
    public static function now(string $format = 'Y-m-d', ?string $timezone = null): string
    {
        try {
            if ($timezone) {
                $dateObj = new DateTime('now', new DateTimeZone($timezone));
            } else {
                $dateObj = new DateTime('now');
            }
            return $dateObj->format($format);
        } catch (Exception $e) {
            // error_log("Error al obtener fecha actual: " . $e->getMessage());
            Log::error('Error al obtener fecha actual: ' . $e->getMessage());
            return date($format);
        }
    }

    /**
     * Valida si una fecha es válida
     * 
     * @param string|null $date Fecha a validar
     * @param string $format Formato esperado (default: 'Y-m-d')
     * @return bool True si la fecha es válida, false en caso contrario
     * 
     * @example
     * Date::isValid('2025-10-25'); // Retorna: true
     * Date::isValid('2025-13-45'); // Retorna: false
     * Date::isValid('25/10/2025', 'd/m/Y'); // Retorna: true
     * Date::isValid(null); // Retorna: false
     */
    public static function isValid(?string $date, string $format = 'Y-m-d'): bool
    {
        if (empty($date)) {
            return false;
        }

        $dateObj = DateTime::createFromFormat($format, $date);
        return $dateObj !== false && $dateObj->format($format) === $date;
    }

    /**
     * Calcula la diferencia entre dos fechas en días
     * 
     * @param string|DateTime $date1 Primera fecha
     * @param string|DateTime $date2 Segunda fecha (default: fecha actual)
     * @return int Diferencia en días
     * 
     * @example
     * Date::diffInDays('2025-10-20', '2025-10-25'); // Retorna: 5
     * Date::diffInDays('2025-10-25'); // Retorna: diferencia con hoy
     */
    public static function diffInDays($date1, $date2 = null): int
    {
        try {
            if (!($date1 instanceof DateTime)) {
                $date1 = new DateTime($date1);
            }

            if ($date2 === null) {
                $date2 = new DateTime();
            } elseif (!($date2 instanceof DateTime)) {
                $date2 = new DateTime($date2);
            }

            $diff = $date1->diff($date2);
            return (int)$diff->format('%r%a');
        } catch (Exception $e) {
            // error_log("Error al calcular diferencia de fechas: " . $e->getMessage());
            Log::error('Error al calcular diferencia de fechas: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Agrega días a una fecha
     * 
     * @param string|DateTime $date Fecha inicial
     * @param int $days Días a agregar (puede ser negativo para restar)
     * @param string $outputFormat Formato de salida (default: 'Y-m-d')
     * @return string Fecha resultante formateada
     * 
     * @example
     * Date::addDays('2025-10-25', 5); // Retorna: "2025-10-30"
     * Date::addDays('2025-10-25', -5); // Retorna: "2025-10-20"
     */
    public static function addDays($date, int $days, string $outputFormat = 'Y-m-d'): string
    {
        try {
            if (!($date instanceof DateTime)) {
                $dateObj = new DateTime($date);
            } else {
                $dateObj = clone $date;
            }

            $dateObj->modify("{$days} days");
            return $dateObj->format($outputFormat);
        } catch (Exception $e) {
            // error_log("Error al agregar días: " . $e->getMessage());
            Log::error('Error al agregar días: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Agrega meses a una fecha
     * 
     * @param string|DateTime $date Fecha inicial
     * @param int $months Meses a agregar (puede ser negativo para restar)
     * @param string $outputFormat Formato de salida (default: 'Y-m-d')
     * @return string Fecha resultante formateada
     * 
     * @example
     * Date::addMonths('2025-10-25', 2); // Retorna: "2025-12-25"
     * Date::addMonths('2025-10-25', -2); // Retorna: "2025-08-25"
     */
    public static function addMonths($date, int $months, string $outputFormat = 'Y-m-d'): string
    {
        try {
            if (!($date instanceof DateTime)) {
                $dateObj = new DateTime($date);
            } else {
                $dateObj = clone $date;
            }

            $dateObj->modify("{$months} months");
            return $dateObj->format($outputFormat);
        } catch (Exception $e) {
            // error_log("Error al agregar meses: " . $e->getMessage());
            Log::error('Error al agregar meses: ' . $e->getMessage());
            return '';
        }
    }

    /**
     * Formatea una fecha según el formato especificado
     * 
     * @param string|DateTime|null $date Fecha a formatear
     * @param string $outputFormat Formato de salida
     * @param string $inputFormat Formato de entrada (default: 'Y-m-d')
     * @return string Fecha formateada o cadena vacía si es inválida
     * 
     * @example
     * Date::format('2025-10-25', 'd/m/Y'); // Retorna: "25/10/2025"
     * Date::format('2025-10-25', 'l, d \de F \de Y'); // Retorna: "Saturday, 25 de October de 2025"
     */
    public static function format($date, string $outputFormat, string $inputFormat = 'Y-m-d'): string
    {
        if (empty($date)) {
            return '';
        }

        try {
            if ($date instanceof DateTime) {
                return $date->format($outputFormat);
            }

            $dateObj = DateTime::createFromFormat($inputFormat, $date);

            if ($dateObj === false) {
                $timestamp = strtotime($date);
                if ($timestamp !== false) {
                    $dateObj = new DateTime();
                    $dateObj->setTimestamp($timestamp);
                } else {
                    return '';
                }
            }

            return $dateObj->format($outputFormat);
        } catch (Exception $e) {
            // error_log("Error al formatear fecha: " . $e->getMessage());
            Log::error('Error al formatear fecha: ' . $e->getMessage());
            return '';
        }
    }

    // 
    /**
     * Calcula la edad en años a partir de una fecha de nacimiento
     * 
     * @param string|DateTime $birthDate Fecha de nacimiento
     * @param string|DateTime|null $referenceDate Fecha de referencia (default: fecha actual)
     * @return int Edad en años
     * 
     * @example
     * Date::calculateAge('1990-05-15'); // Retorna: edad actual
     * Date::calculateAge('1990-05-15', '2025-05-15'); // Retorna: 35
     */
    public static function calculateAge($birthDate, $referenceDate = null): int
    {
        try {
            if (!($birthDate instanceof DateTime)) {
                $birthDateObj = new DateTime($birthDate);
            } else {
                $birthDateObj = $birthDate;
            }

            if ($referenceDate === null) {
                $referenceDateObj = new DateTime();
            } elseif (!($referenceDate instanceof DateTime)) {
                $referenceDateObj = new DateTime($referenceDate);
            } else {
                $referenceDateObj = $referenceDate;
            }

            $diff = $birthDateObj->diff($referenceDateObj);
            return (int)$diff->y;
        } catch (Exception $e) {
            // error_log("Error al calcular edad: " . $e->getMessage());
            Log::error('Error al calcular edad: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calcula la diferencia entre dos fechas en meses
     * 
     * @param string|DateTime $date1 Primera fecha
     * @param string|DateTime $date2 Segunda fecha (default: fecha actual)
     * @return int Diferencia en meses (puede ser negativo si date1 > date2)
     * 
     * @example
     * Date::diffInMonths('2025-10-25', '2026-01-25'); // Retorna: 3
     * Date::diffInMonths('2026-01-25', '2025-10-25'); // Retorna: -3
     * Date::diffInMonths('2025-10-25'); // Retorna: diferencia en meses con hoy
     */
    public static function diffInMonths($date1, $date2 = null): int
    {
        try {
            if (!($date1 instanceof DateTime)) {
                $date1 = new DateTime($date1);
            }

            if ($date2 === null) {
                $date2 = new DateTime();
            } elseif (!($date2 instanceof DateTime)) {
                $date2 = new DateTime($date2);
            }

            $diff = $date1->diff($date2);
            $months = ($diff->y * 12) + $diff->m;
            
            // Si la diferencia es negativa (date1 > date2), retornar negativo
            return $diff->invert ? -$months : $months;
        } catch (Exception $e) {
            Log::error('Error al calcular diferencia de fechas en meses: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Calcula la diferencia entre dos fechas en años
     * 
     * @param string|DateTime $date1 Primera fecha
     * @param string|DateTime $date2 Segunda fecha (default: fecha actual)
     * @return int Diferencia en años (puede ser negativo si date1 > date2)
     * 
     * @example
     * Date::diffInYears('2020-05-15', '2025-05-15'); // Retorna: 5
     * Date::diffInYears('2025-05-15', '2020-05-15'); // Retorna: -5
     * Date::diffInYears('2020-05-15'); // Retorna: diferencia en años con hoy
     */
    public static function diffInYears($date1, $date2 = null): int
    {
        try {
            if (!($date1 instanceof DateTime)) {
                $date1 = new DateTime($date1);
            }

            if ($date2 === null) {
                $date2 = new DateTime();
            } elseif (!($date2 instanceof DateTime)) {
                $date2 = new DateTime($date2);
            }

            $diff = $date1->diff($date2);
            $years = $diff->y;
            
            // Si la diferencia es negativa (date1 > date2), retornar negativo
            return $diff->invert ? -$years : $years;
        } catch (Exception $e) {
            Log::error('Error al calcular diferencia de fechas en años: ' . $e->getMessage());
            return 0;
        }
    }
}
