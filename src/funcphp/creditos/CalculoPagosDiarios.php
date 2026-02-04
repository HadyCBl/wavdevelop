<?php

namespace Creditos\Utilidades;

/**
 * Clase CalculoPagosDiarios
 * 
 * Esta clase se encarga de calcular los pagos diarios de un préstamo, incluyendo el capital y los intereses.
 * 
 * @package MicrosystemPlus
 */
class CalculoPagosDiarios
{
    private $monto;
    private $interes;
    private $no_cuotas;
    private $tipoTasa;
    private $diasCalculo;
    private $gastos = [];
    private $fechas = [];

    /**
     * Constructor de la clase CalculoPagosDiarios.
     * 
     * @param float $monto Monto del préstamo.
     * @param float $interes Tasa de interés del préstamo.
     * @param int $no_cuotas Número de cuotas del préstamo.
     * @param int $tipoTasa Tipo de tasa de interés (por defecto 10).
     * @param int $diasCalculo Número de días para el cálculo (por defecto 360).
     * @param array $gastos Array de gastos a aplicar en las cuotas. Valor por defecto es [].
     * @param array $fechas Array de fechas de pago. Valor por defecto es [].
     */
    public function __construct($monto, $interes, $no_cuotas, $tipoTasa = 10, $diasCalculo = 360, $gastos = [], $fechas = [])
    {
        $this->monto = $monto;
        $this->interes = $interes;
        $this->no_cuotas = $no_cuotas;
        $this->tipoTasa = $tipoTasa;
        $this->diasCalculo = $diasCalculo;
        $this->gastos = $gastos;
        $this->fechas = is_array($fechas) && isset($fechas[1]) ? $fechas[1] : (is_array($fechas) ? $fechas : []);
    }

    /**
     * Método para calcular los montos diarios de capital e interés con gastos incluidos.
     * 
     * @return array Arreglo que contiene los datos de cada cuota con gastos aplicados.
     */
    public function calculoMontosDiario()
    {
        $result = [];

        $factorInteres = $this->obtenerFactorInteres();
        $capital = abs($this->monto);

        $capcuo = round($capital / $this->no_cuotas, 2);
        $intcuo = round($this->monto * $this->interes / 100 / $factorInteres, 2);
        $ganancia = ($this->tipoTasa == 10) ? round(($capital * $this->interes / 100), 2) : round($intcuo * $this->no_cuotas, 2);
        $saldoint = $ganancia;

        $precision = $this->getPrecision();
        $mode = $this->getModePrecision();

        for ($i = 1; $i <= $this->no_cuotas; $i++) {
            $key = $i - 1; // Índice base 0
            
            $capital = $capital - $capcuo;
            $saldoint = $saldoint - $intcuo;
            
            $interesCuota = $intcuo;
            // Ajuste de saldo de interés en última cuota
            if ($i == $this->no_cuotas && $saldoint != 0) {
                $interesCuota = $intcuo + $saldoint;
            }

            // Ajuste de saldo de capital en última cuota
            $capitalCuota = $capcuo;
            if ($i == $this->no_cuotas && $capital != 0) {
                $capitalCuota = $capcuo + $capital;
                $capital = 0;
            }

            // Construir la estructura de la cuota
            $fechaCuota = !empty($this->fechas) && isset($this->fechas[$key]) ? $this->fechas[$key] : null;
            
            $result[$key] = [
                'nrocuota' => $i,
                'fecha' => $fechaCuota,
                'cuota' => $capitalCuota + $interesCuota,
                'interes' => round($interesCuota, 2),
                'capital' => round($capitalCuota, 2),
                'saldo' => round(max($capital, 0), 2),
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

            // Aplicar precisión y ajustes (para créditos diarios - 1D)
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
        $cantidadCuotas = $this->no_cuotas;

        foreach ($this->gastos as $gasto) {
            $tipo = $gasto['tipo_deMonto'];
            $monto = $gasto['monto'];
            $calculax = $gasto['calculox'];
            $monsugc = $this->monto;
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
     * Método privado para obtener el factor de interés basado en el tipo de tasa.
     * 
     * @return int Factor de interés.
     */
    private function obtenerFactorInteres()
    {
        switch ($this->tipoTasa) {

            case 9: // Anual

                return $this->diasCalculo;

            case 5: // Mensual

                return ($this->diasCalculo == 360) ? 30 : 31;

            case 10: // Sobre el plazo

                return $this->no_cuotas;
                // return 1;

            default:

                return ($this->diasCalculo == 360) ? 30 : 31;
        }
        return ($this->diasCalculo == 360) ? 30 : 31;
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
