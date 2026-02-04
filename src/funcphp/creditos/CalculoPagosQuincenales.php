<?php

namespace Creditos\Utilidades;

/**
 * Clase CalculoPagos
 * 
 * Esta clase permite calcular y generar una tabla de amortización para un crédito de tipo semanal, 
 * considerando el monto del crédito, la tasa de interés, el número de periodos y el tipo de tasa.
 */
class CalculoPagosQuincenales
{
    private $montoCredito;
    private $tasaInteres;
    private $numPeriodos;
    private $tipoTasa;
    private $gastos = [];
    private $fechas = [];

    /**
     * Constructor de la clase CalculoPagos.
     * 
     * @param float $montoCredito Monto total del crédito.
     * @param float $tasaInteres Tasa de interés.
     * @param int $numPeriodos Número de periodos para el pago.
     * @param int $tipoTasa Tipo de tasa de interés (anual, mensual, sobre el plazo, etc.). Valor por defecto es 10.
     * @param array $gastos Array de gastos a aplicar en las cuotas. Valor por defecto es [].
     * @param array $fechas Array de fechas de pago. Valor por defecto es [].
     */
    public function __construct($montoCredito, $tasaInteres, $numPeriodos, $tipoTasa = 10, $gastos = [], $fechas = [])
    {
        $this->montoCredito = $montoCredito;
        $this->tasaInteres = $tasaInteres;
        $this->numPeriodos = $numPeriodos;
        $this->tipoTasa = $tipoTasa;
        $this->gastos = $gastos;
        $this->fechas = is_array($fechas) && isset($fechas[1]) ? $fechas[1] : (is_array($fechas) ? $fechas : []);
    }

    /**
     * Genera la tabla de amortización del crédito con gastos incluidos.
     * 
     * @return array Arreglo que contiene los datos de cada cuota con gastos aplicados.
     */
    public function generarTablaAmortizacion()
    {
        $result = [];

        $capital = abs($this->montoCredito);
        $factorInteres = $this->obtenerFactorInteres();

        $interesQuincenal = ($this->montoCredito * ($this->tasaInteres / 100)) / $factorInteres;
        $ganancia = ($this->tipoTasa == 10) ? round(($capital * $this->tasaInteres / 100), 2) : round($interesQuincenal * $this->numPeriodos, 2);

        $precision = $this->getPrecision();
        $mode = $this->getModePrecision();

        $capitalPorCuota = round($this->montoCredito / $this->numPeriodos, 2);
        $saldoCapital = $this->montoCredito;
        $saldoint = $ganancia;

        for ($quincena = 1; $quincena <= $this->numPeriodos; $quincena++) {
            $key = $quincena - 1; // Índice base 0
            
            $saldoCapital -= $capitalPorCuota;
            $saldoint = $saldoint - $interesQuincenal;

            $interesCuota = $interesQuincenal;
            // Ajuste de saldo de interés en última cuota
            if ($quincena == $this->numPeriodos && $saldoint != 0) {
                $interesCuota = $interesQuincenal + $saldoint;
            }

            // Ajuste de saldo de capital en última cuota
            $capitalCuota = $capitalPorCuota;
            if ($quincena == $this->numPeriodos && $saldoCapital != 0) {
                $capitalCuota = $capitalPorCuota + $saldoCapital;
                $saldoCapital = 0;
            }

            // Construir la estructura de la cuota
            $fechaCuota = !empty($this->fechas) && isset($this->fechas[$key]) ? $this->fechas[$key] : null;
            
            $result[$key] = [
                'nrocuota' => $quincena,
                'fecha' => $fechaCuota,
                'cuota' => $capitalCuota + $interesCuota,
                'interes' => round($interesCuota, 2),
                'capital' => round($capitalCuota, 2),
                'saldo' => round(max($saldoCapital, 0), 2),
                'gastos' => []
            ];

            // Aplicar gastos si existen
            if (!empty($this->gastos)) {
                $gastosAplicados = $this->aplicarGastos($key, $capitalCuota, $interesCuota);
                $result[$key]['gastos'] = $gastosAplicados;
                
                // Sumar los gastos a la cuota
                $totalGastos = array_sum(array_column($gastosAplicados, 'monto'));
                $result[$key]['cuota'] += $totalGastos;
            }

            // Aplicar precisión y ajustes (para créditos quincenales - 15D)
            $cuotaSinRedondeo = $result[$key]['interes'] + $result[$key]['capital'];
            if (!empty($result[$key]['gastos'])) {
                $cuotaSinRedondeo += array_sum(array_column($result[$key]['gastos'], 'monto'));
            }
            
            // Redondear la cuota total
            $cuotaRedondeada = round($cuotaSinRedondeo, $precision, $mode);
            
            // Calcular el ajuste necesario
            $ajuste = $cuotaRedondeada - $cuotaSinRedondeo;
            
            if ($ajuste != 0) {
                // Orden de aplicación del ajuste: gastos → intereses → capital
                if (!empty($result[$key]['gastos'])) {
                    // Aplicar ajuste en el primer gasto
                    $result[$key]['gastos'][0]['monto'] = $result[$key]['gastos'][0]['monto'] + $ajuste;
                } else {
                    // Si no hay gastos, aplicar en intereses
                    $result[$key]['interes'] = $result[$key]['interes'] + $ajuste;
                }
            }
            
            // Actualizar la cuota final
            $result[$key]['cuota'] = $cuotaRedondeada;
        }

        return $result;
    }

    /**
     * Aplica los gastos a una cuota específica.
     * 
     * @param int $key Índice de la cuota.
     * @param float $capital Capital de la cuota.
     * @param float $interes Interés de la cuota.
     * @return array Array de gastos aplicados.
     */
    private function aplicarGastos($key, $capital, $interes)
    {
        $gastosAplicados = [];
        $cantidadCuotas = $this->numPeriodos;

        foreach ($this->gastos as $gasto) {
            $tipo = $gasto['tipo_deMonto'];
            $monto = $gasto['monto'];
            $calculax = $gasto['calculax'];
            $monsugc = $this->montoCredito;
            $distribucion = $gasto['distribucion'] ?? null;

            // Validación para la distribución de gastos
            if (is_numeric($distribucion) && $distribucion > 0 && $distribucion <= $cantidadCuotas) {
                $cantidadCuotas = $distribucion;
            }

            $mongas = 0;
            if ($tipo == 1) {
                // Monto fijo por cuota
                $mongas = ($calculax == 1) ? $monto : (($calculax == 2) ? ($monto / $cantidadCuotas) : $monto);
            } elseif ($tipo == 2) {
                // Porcentaje del monto de la cuota
                $mongas = ($calculax == 1) ? ($monto / 100 * $capital) : 
                          (($calculax == 2) ? ($monto / 100 * $interes) : 
                          (($calculax == 3) ? ($monto / 100 * ($capital + $interes)) : 
                          (($calculax == 4) ? ($monsugc * $monto / 100 / 12) : 
                          (($calculax == 5) ? ($monsugc * $monto / 100 / $cantidadCuotas) : 0))));
            }

            // Si la cuota es mayor a la cantidad de cuotas distribuidas, no se cobra el gasto
            if (($key + 1) <= $cantidadCuotas) {
                $gastosAplicados[] = [
                    'id' => $gasto['id'],
                    'nombre' => $gasto['nombre_gasto'] ?? 'Gasto',
                    'afecta_modulo' => $gasto['afecta_modulo'],
                    'monto' => round($mongas, 2)
                ];
            }
        }

        return $gastosAplicados;
    }

    /**
     * Obtiene el factor de interés basado en el tipo de tasa.
     * 
     * @return int Factor de interés.
     */
    private function obtenerFactorInteres()
    {
        switch ($this->tipoTasa) {

            case 9: // Anual

                return 24;

            case 5: // Mensual

                return 2;

            case 10: // Sobre el plazo

                return $this->numPeriodos;

            default:

                return 2;
        }
        return 2;
    }
    public function getTasaMensual()
    {
        switch ($this->tipoTasa) {

            case 9: // Anual

                return $this->tasaInteres / 12;

            default:

                return $this->tasaInteres;
        }
    }

    private function getPrecision()
    {
        $config = new \Micro\Generic\AppConfig();
        $precision = $config->getPrecisionCreditos();
        if ($precision === null) {
            return 2; // Valor por defecto si no se encuentra la configuración
        }
        return (int)$precision;
    }

    private function getModePrecision()
    {
        $config = new \Micro\Generic\AppConfig();
        $mode = $config->getModePrecisionCreditos();
        if ($mode === null) {
            return PHP_ROUND_HALF_EVEN; // Valor por defecto si no se encuentra la configuración
        }
        return (int)$mode;
    }
}
