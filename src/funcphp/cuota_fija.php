<?php
/* TENDREMOS UN Clase Y DEBEMSO DE UTILZARLA 
PARA EL CALCULO DE LOS PAGOS DE CUENTAFIJA, tambien podemos decir que es l amortizacon del 
en excel podemos encontrar estas funciones como PAGO(), PAGOINT(), PAGOPRIN()
*/


//inicamos nuestra funcion 

// NO TOCAR NADA DENTRO DE ESTAS FUNCIONES PORFAVOR (NEGROY)
class Finanza
{
    //VARIABLE EPSILON 
    public const EPSILON = 1e-6;

    //CHECA EL ZERO JAS JAS 
    private static function checkZero(float $value, float $epsilon = self::EPSILON): float
    {
        return \abs($value) < $epsilon ? 0.0 : $value;
    }

    /*        rP(1+r)ᴺ
     * PMT = --------
     *       (1+r)ᴺ-1
     */
    //CALCULO DEL VALOR DE LA CUOTA  
    public static function pmt(float $rate, int $periods, float $present_value, float $future_value = 0.0, bool $beginning = false): float
    {
        $when = $beginning ? 1 : 0;

        if ($rate == 0) {
            return - ($future_value + $present_value) / $periods;
        }

        return - ($future_value + ($present_value * \pow(1 + $rate, $periods)))
            /
            ((1 + $rate * $when) / $rate * (\pow(1 + $rate, $periods) - 1));
    }

    //FUNCION DE CALCULO DE PAGOINT PAGO DEL INTERES
    public static function ipmt(float $rate, int $period, int $periods, float $present_value, float $future_value = 0.0, bool $beginning = false): float
    {
        if ($period < 1 || $period > $periods) {
            return \NAN;
        }

        if ($rate == 0) {
            return 0;
        }

        if ($beginning && $period == 1) {
            return 0.0;
        }

        $payment = self::pmt($rate, $periods, $present_value, $future_value, $beginning);
        if ($beginning) {
            $interest = (self::fv($rate, $period - 2, $payment, $present_value, $beginning) - $payment) * $rate;
        } else {
            $interest = self::fv($rate, $period - 1, $payment, $present_value, $beginning) * $rate;
        }

        return self::checkZero($interest);
    }

    //  ESTE ES EL PAGOPRIN DEL EXCEL  PAGO DEL CAPITAL , 
    /*     @param  float $rate
     * @param  int   $period
     * @param  int   $periods
     * @param  float $present_value
     * @param  float $future_value
     * @param  bool  $beginning adjust the payment to the beginning or end of the period
     */
    public static function ppmt(float $rate, int $period, int $periods, float $present_value, float $future_value = 0.0, bool $beginning = false): float
    {
        $payment = self::pmt($rate, $periods, $present_value, $future_value, $beginning);
        $ipmt    = self::ipmt($rate, $period, $periods, $present_value, $future_value, $beginning);

        return $payment - $ipmt;
    }


    //Future value for a loan or annuity with compound interest.
    /* *                   PMT*((1+r)ᴺ - 1)
     * FV = -PV*(1+r)ᴺ - ----------------
     *                          r 
     *      * Examples:
     * * The future value in 5 years on a 30-year fixed mortgage note of $265000
     * at 3.5% interest paid at the end of every month. This is how much loan
     * principle would be outstanding:
     *   fv(0.035/12, 5*12, 1189.97, -265000, false)
     * */
    //VALOR FUTURO 
    public static function fv(float $rate, int $periods, float $payment, float $present_value, bool $beginning = false): float
    {
        $when = $beginning ? 1 : 0;

        if ($rate == 0) {
            $fv = - ($present_value + ($payment * $periods));
            return self::checkZero($fv);
        }

        $initial  = 1 + ($rate * $when);
        $compound = \pow(1 + $rate, $periods);
        $fv       = - (($present_value * $compound) + (($payment * $initial * ($compound - 1)) / $rate));

        return self::checkZero($fv);
    }
} //finanza

//EJMPLO   25000 = CAPITAL ,  14% DE INTERES, 24 PAGOS , 365, 26/07/22 DESEMBOLSO 
//VARIABLES 



/* Examples:
    * The payment on a 30-year fixed mortgage note of $265000 at 3.5% interest
    * paid at the end of every month.
    *   pmt(0.035/12, 30*12, 265000, 0, false)
    *
    * The payment on a 30-year fixed mortgage note of $265000 at 3.5% interest
    * needed to half the principal in half in 5 years:
    *   pmt(0.035/12, 5*12, 265000, 265000/2, false) 
    
     * How much money can be withdrawn at the end of every quarter from an account
     * with $1000000 earning 4% so the money lasts 20 years:
     *  pmt(0.04/4, 20*4, 1000000, 0, false)
*/
//=PMT(6%/12, 360, 0, 833333, 0)
//pmt(0.04/4, 20*4, 1000000, 0, false)
