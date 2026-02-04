<?php

class CalculoPagos {
    private $montoCredito;       // Monto total del crédito
    private $tasaInteres;        // Tasa de interés anual
    private $numPeriodos;        // Número de periodos para el pago
    private $frecuencia;         // Frecuencia de pago (semanal o diaria)

    public function __construct($montoCredito, $tasaInteres, $numPeriodos, $frecuencia = 'semanal') {
        $this->montoCredito = $montoCredito;
        $this->tasaInteres = $tasaInteres;
        $this->numPeriodos = $numPeriodos;
        $this->frecuencia = $frecuencia;
    }

    // Generar tabla de amortización dependiendo de la frecuencia
    public function generarTablaAmortizacion() {
        if ($this->frecuencia === 'diaria') {
            return $this->generarTablaAmortizacionDiaria();
        } else {
            return $this->generarTablaAmortizacionSemanal2();
        }
    }

    // Generar tabla de amortización semanal
    private function generarTablaAmortizacionSemanal() {
        $tablaAmortizacion = [];

        $interesSemanal = ($this->montoCredito * ($this->tasaInteres / 100)) / 4;
        $capitalPorCuota = $this->montoCredito / $this->numPeriodos;
        $saldoCapital = $this->montoCredito;

        for ($semana = 1; $semana <= $this->numPeriodos; $semana++) {
            $saldoCapital -= $capitalPorCuota;

            $tablaAmortizacion[] = [
                'periodo' => $semana,
                'capital' => round($capitalPorCuota, 2),
                'interes' => round($interesSemanal, 2),
                'saldoCapital' => round(max($saldoCapital, 0), 2),
            ];
        }

        return $tablaAmortizacion;
    }
    private function generarTablaAmortizacionSemanal2() {
        $tablaAmortizacion = [];

        $pagoInteres = [];
        $pagoCapital = [];
        $saldocapital = [];

        $interesSemanal = ($this->montoCredito * ($this->tasaInteres / 100)) / 4;
        $capitalPorCuota = $this->montoCredito / $this->numPeriodos;
        $saldoCapital = $this->montoCredito;

        for ($semana = 1; $semana <= $this->numPeriodos; $semana++) {
            $saldoCapital -= $capitalPorCuota;

            $tablaAmortizacion[] = [
                'periodo' => $semana,
                'capital' => round($capitalPorCuota, 2),
                'interes' => round($interesSemanal, 2),
                'saldoCapital' => round(max($saldoCapital, 0), 2),
            ];

            array_push($saldocapital, round($saldoCapital, 2));
            array_push($pagoCapital, round($capitalPorCuota, 2));
            array_push($pagoInteres, $interesSemanal);
        }

        // return $tablaAmortizacion;
        return array($pagoInteres, $pagoCapital, $saldocapital);
    }

    // Generar tabla de amortización diaria
    private function generarTablaAmortizacionDiaria() {
        $tablaAmortizacion = [];

        $interesDiario = ($this->montoCredito * ($this->tasaInteres / 100)) / 20;
        $capitalPorCuota = $this->montoCredito / $this->numPeriodos;
        $saldoCapital = $this->montoCredito;

        for ($dia = 1; $dia <= $this->numPeriodos; $dia++) {
            $saldoCapital -= $capitalPorCuota;

            $tablaAmortizacion[] = [
                'periodo' => $dia,
                'capital' => round($capitalPorCuota, 2),
                'interes' => round($interesDiario, 2),
                'saldoCapital' => round(max($saldoCapital, 0), 2),
            ];
        }

        return $tablaAmortizacion;
    }
}
