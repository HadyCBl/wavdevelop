<?php

class CalculadoraPagos
{
    private $cuotaAhorro;
    private $saldoAcumulado;
    private $tasaInteres;
    private $fechaInicio;
    private $diasEntrePagos;
    private $plazoObjetivo;
    private $tablaPagos = [];
    private $eventos = [];

    public function __construct($cuotaAhorro, $saldoAcumulado, $tasaInteres, $fechaInicio, $diasEntrePagos, $plazoObjetivo)
    {
        $this->cuotaAhorro = $cuotaAhorro;
        $this->saldoAcumulado = $saldoAcumulado;
        $this->tasaInteres = $tasaInteres;
        $this->fechaInicio = new DateTime($fechaInicio);
        $this->diasEntrePagos = $diasEntrePagos;
        $this->plazoObjetivo = $plazoObjetivo;
    }

    public function calcularPagos()
    {
        $numeroPagos = intval($this->plazoObjetivo / ($this->diasEntrePagos / 30));
        for ($i = 0; $i < $numeroPagos; $i++) {
            // Calcular la fecha del pago
            $fechaPago = clone $this->fechaInicio;

            if ($this->diasEntrePagos == 30) {
                $fechaPago->modify('+' . ($i) . ' month');
            } else {
                $fechaPago->modify('+' . ($i * $this->diasEntrePagos) . ' days');
            }

            // Calcular el interés del periodo
            $interesPeriodo = $this->saldoAcumulado * ($this->tasaInteres/100 / 12) * ($this->diasEntrePagos / 30);

            // Actualizar el saldo acumulado
            // $this->saldoAcumulado += $this->cuotaAhorro + $interesPeriodo;
            $this->saldoAcumulado += $this->cuotaAhorro;

            // Guardar los detalles en la tabla de pagos
            $this->tablaPagos[] = [
                'no' => ($i + 1),
                'fecha' => $fechaPago->format('d/m/Y'),
                'deposito' => round($this->cuotaAhorro, 2),
                'interes' => round($interesPeriodo, 2),
                'saldo' => round($this->saldoAcumulado, 2)
            ];

            // Crear el evento para el calendario
            $this->eventos[] = [
                'title' => "Dep: Q" . number_format($this->cuotaAhorro, 2),
                'start' => $fechaPago->format(DateTime::ISO8601),
                'extendedProps' => [
                    'description' => "Depósito: Q" . number_format($this->cuotaAhorro, 2)
                ],
            ];
        }
    }

    public function obtenerTablaPagos()
    {
        return $this->tablaPagos;
    }

    public function obtenerEventos()
    {
        return $this->eventos;
    }
}

// Ejemplo de uso
// $cuotaAhorro = 1000.00;
// $saldoAcumulado = 5000.00;
// $tasaInteres = 0.05; // 5% de interés anual
// $fechaInicio = '2023-01-01';
// $diasEntrePagos = 30;
// $plazoObjetivo = 12 * 30; // 12 meses (360 días)

// $calculadora = new CalculadoraPagos($cuotaAhorro, $saldoAcumulado, $tasaInteres, $fechaInicio, $diasEntrePagos, $plazoObjetivo);
// $calculadora->calcularPagos();

// $tablaPagos = $calculadora->obtenerTablaPagos();
// $eventos = $calculadora->obtenerEventos();

// // Imprimir la tabla de pagos
// echo "<pre>";
// print_r($tablaPagos);
// echo "</pre>";

// // Imprimir los eventos
// echo "<pre>";
// print_r($eventos);
// echo "</pre>";
