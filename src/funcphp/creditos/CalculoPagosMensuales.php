<?php
namespace Creditos\Utilidades;

class CalculoPagosMensuales
{
    private $CtipCre;
    private $rate;
    private $MonSug;
    private $periods;
    private $future_value;
    private $beginning;
    private $fechas;
    private $fechadesembolso;
    private $diascalculo;
    private $primeracuota;
    private $ntipperc;
    private $peripagcap;
    private $afectaInteres;

    public function __construct($CtipCre, $rate, $MonSug, $periods, $future_value, $beginning, $fechas, $fechadesembolso, $diascalculo, $primeracuota = false, $ntipperc = "1M", $peripagcap = 1, $afectaInteres = 1)
    {
        $this->CtipCre = $CtipCre;
        $this->rate = $rate;
        $this->MonSug = $MonSug;
        $this->periods = $periods;
        $this->future_value = $future_value;
        $this->beginning = $beginning;
        $this->fechas = $fechas;
        $this->fechadesembolso = $fechadesembolso;
        $this->diascalculo = $diascalculo;
        $this->primeracuota = $primeracuota;
        $this->ntipperc = $ntipperc;
        $this->peripagcap = $peripagcap;
        $this->afectaInteres = $afectaInteres;
    }

    public function amortiza()
    {
        $pagoInteres = [];
        $pagoCapital = [];
        $saldocapital = [];

        switch ($this->CtipCre) {
            case 'Amer':
                $CAPITAL = abs($this->MonSug);
                $ipmt = round(($this->rate * $CAPITAL), 2);
                for ($i = 1; $i <= ($this->periods - 1); $i++) {
                    array_push($pagoInteres, $ipmt);
                    array_push($pagoCapital, 0);
                    array_push($saldocapital, $CAPITAL);
                }
                array_push($pagoInteres, $ipmt);
                array_push($pagoCapital, $CAPITAL);
                array_push($saldocapital, 0);
                return array($pagoInteres, $pagoCapital, $saldocapital);
                break;
            case 'Franc':
                $CAPITAL = $this->MonSug;
                $this->MonSug = $this->MonSug * -1;
                $when = $this->beginning ? 1 : 0;
                $cuota =  ($this->future_value + ($CAPITAL * \pow(1 + $this->rate, $this->periods)))
                    /
                    ((1 + $this->rate * $when) / $this->rate * (\pow(1 + $this->rate, $this->periods) - 1));
                $rate = $this->rate * 12;
                $cuotas = $this->refixedcuotas($cuota, $CAPITAL, $this->fechas, $this->fechadesembolso, $rate, $this->diascalculo, $CAPITAL, $this->primeracuota, 1);
                return $cuotas;
                break;
            case 'Flat':
                $CAPITAL = abs($this->MonSug);
                $intstotal = round(($CAPITAL * $this->rate), 2);
                $periodokp = (int)($this->periods / $this->peripagcap);
                $capflatreal =  round($CAPITAL / $periodokp, 2);
                $auxshow = 1;
                for ($i = 1; $i <= $this->periods; $i++) {
                    $capflat = ($auxshow >= $this->peripagcap) ? $capflatreal : 0;
                    $auxshow = ($auxshow >= $this->peripagcap) ? 1 : $auxshow + 1;
                    $intflat = ($this->ntipperc == "1M" && $this->peripagcap > 1 && $this->afectaInteres == 1) ? round(($CAPITAL * $this->rate), 2) : $intstotal;
                    $cuota = $intflat + $capflat;
                    $CAPITAL = $CAPITAL - $capflat;
                    array_push($saldocapital, round($CAPITAL, 2));
                    array_push($pagoCapital, round($capflat, 2));
                    array_push($pagoInteres, round($intflat, 2));
                }
                return array($pagoInteres, $pagoCapital, $saldocapital);
                break;
            case 'Germa':
                $Cap_amrt = round(($this->MonSug) / $this->periods, 2);
                $fechanterior = agregarMes($this->fechas[0], -1);
                $rate = $this->rate * 12;
                foreach ($this->fechas as $fecha) {
                    $dias = ($this->diascalculo == 360) ? 30 : 31;
                    $ipmt = abs($this->MonSug) * ($rate) / $this->diascalculo * $dias;
                    array_push($pagoInteres, $ipmt);
                    $this->MonSug = $this->MonSug - $Cap_amrt;
                    array_push($saldocapital, $this->MonSug);
                    array_push($pagoCapital, abs($Cap_amrt));
                    $fechanterior = $fecha;
                }
                return array($pagoInteres, $pagoCapital, $saldocapital);
                break;
            default:
                return array($pagoInteres, $pagoCapital, $saldocapital);
                break;
        }
    }

    public function refixedcuotas($cuota, $diferencia, $fechas, $fechadesembolso, $rate, $diascalculo, $CAPITAL, $primeracuota, $repet)
    {
        $pagoInteres = [];
        $pagoCapital = [];
        $saldocapital = [];

        $cuota2 = ($repet == 1) ? $cuota : ($cuota + ($diferencia / count($fechas)));
        $cuota = ($cuota + $cuota2) / 2;

        $saldo = $CAPITAL;
        $fechanterior = $fechadesembolso;
        $i = 0;
        foreach ($fechas as $fecha) {
            $dias = ($diascalculo == 360) ? 30 : dias_dif($fechanterior, $fecha);
            if ($primeracuota==1 && $i == 0) {
                $dias = ($diascalculo == 360) ? diferenciaEnDias($fechadesembolso, $fecha) : dias_dif($fechadesembolso, $fecha);
            }

            $ipmt = ($saldo) * ($rate) / $diascalculo * $dias;
            $ipmt = round($ipmt, 2);
            array_push($pagoInteres, $ipmt);

            $ppmt = $cuota - (($saldo) * ($rate) / $diascalculo * $dias);
            $ppmt = ($ppmt > 0) ? round($ppmt, 2) : 0;

            array_push($pagoCapital, $ppmt);

            $saldo  = $saldo - $ppmt;
            $saldo = round($saldo, 2);
            array_push($saldocapital, $saldo);

            $fechanterior = $fecha;
            $i++;
        }
        $cuotas = array($pagoInteres, $pagoCapital, $saldocapital);
        $diferencia = $saldo;
        $diferencia = abs((int)($diferencia));
        if ($diferencia > 4) {
            $cuotas = $this->refixedcuotas($cuota, $saldo, $fechas, $fechadesembolso, $rate, $diascalculo, $CAPITAL, $primeracuota, $repet + 1);
        }
        return $cuotas;
    }
}