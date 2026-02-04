<?php
class profit_cmpst
{
  // public static function addMonths($monthToAdd, $date)
  // {
  //   $d1 = new DateTime($date);

  //   $year = $d1->format('Y');
  //   $month = $d1->format('n');
  //   $day = $d1->format('d');

  //   if ($monthToAdd > 0) {
  //     $year += floor($monthToAdd / 12);
  //   } else {
  //     $year += ceil($monthToAdd / 12);
  //   }
  //   $monthToAdd = $monthToAdd % 12;
  //   $month += $monthToAdd;
  //   if ($month > 12) {
  //     $year++;
  //     $month -= 12;
  //   } elseif ($month < 1) {
  //     $year--;
  //     $month += 12;
  //   }

  //   if (!checkdate($month, $day, $year)) {
  //     $d2 = DateTime::createFromFormat('Y-n-j', $year . '-' . $month . '-1');
  //     $d2->modify('last day of');
  //   } else {
  //     $d2 = DateTime::createFromFormat('Y-n-d', $year . '-' . $month . '-' . $day);
  //   }
  //   return $d2->format('Y-m-d');
  // }
  public function addMonths2($date, $monthsToAdd)
  {
    if (!$date instanceof DateTime) {
      $date = new DateTime($date);
    }
    $tmpDate = clone $date;
    $tmpDate->modify('first day of +' . (int) $monthsToAdd . ' month');

    if ($date->format('j') > $tmpDate->format('t')) {
      $daysToAdd = $tmpDate->format('t') - 1;
    } else {
      $daysToAdd = $date->format('j') - 1;
    }

    $tmpDate->modify('+ ' . $daysToAdd . ' days');


    return $tmpDate->format('Y-m-d');
  }
  // public function ajustarFecha($fechaInicial, $fechaActual, $periodo)
  // {
  //   // Guarda el día de la fecha inicial
  //   $diaInicial = date('d', strtotime($fechaInicial));

  //   // Calcula la nueva fecha añadiendo el periodo
  //   $fechaNueva = new DateTime($fechaActual);
  //   $fechaNueva->modify($periodo);

  //   // Obtiene el mes y año de la nueva fecha
  //   $mesNuevo = $fechaNueva->format('m');
  //   $añoNuevo = $fechaNueva->format('Y');

  //   // Calcula el último día del mes nuevo
  //   $ultimoDiaMes = date('t', strtotime("$añoNuevo-$mesNuevo-01"));

  //   // Si el día inicial es mayor que el último día del mes, usa el último día del mes
  //   // De lo contrario, usa el día inicial
  //   $diaNuevo = ($diaInicial > $ultimoDiaMes) ? $ultimoDiaMes : $diaInicial;

  //   // Retorna la fecha ajustada
  //   return date('Y-m-d', strtotime("$añoNuevo-$mesNuevo-$diaNuevo"));
  // }
  // public function obtenerMesSiguiente($mesActual, $cant = 1)
  // {
  //   // Si es diciembre (12), el siguiente mes es enero (1)
  //   return ($mesActual == 12) ? 1 : $mesActual + $cant;
  // }
  // public static function DateSameMonth($date_iso, $add_sub_months = 1, $operator = "+")
  // {
  //   $mdate = [0, 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];

  //   $date = new DateTime($date_iso);
  //   $d = (int)$date->format('d');
  //   $m = (int)$date->format('m');
  //   $y = (int)$date->format('Y');

  //   if ($m == 2) {
  //     $mdate[$m] = (($y % 4) === 0) ? (($d <= 29) ? $d : 29) : (($d <= 28) ? $d : 28);
  //   }

  //   //first day / last day 
  //   if ($d == 1) {
  //     $mod = "first day of ";
  //   } elseif ($d == $mdate[$m]) {
  //     $mod = "last day of ";
  //   } else {
  //     $mod = "";
  //   }

  //   $date->modify($mod . $operator . $add_sub_months . ' months');


  //   return $date->format("Y-m-d");
  // }


  public function calcudate2($fechaini, $NoCuota, $periodo, $diaslaborales)
  {
    $fechaini2 = $fechaini;
    $fechareal = $fechaini;
    $fchspgs = [];
    $fchsreal = [];
    $daY = $fechaini;

    $meses = (strpos($periodo, 'months') !== false) ? true : false;
    $mesesASumar = (int) trim(str_replace(['months', '+'], '', $periodo));
    // error_log("mesesASumar: " . $mesesASumar);
    // error_log("meses: " . $meses);
    for ($i = 1; $i <= $NoCuota; $i++) {
      /* ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                            AGREGADO POR BENEQ*/
      $cantdias = 0;
      $numdia = date('N', strtotime($fechaini));
      $indice = array_search($numdia, array_column($diaslaborales, 'id_dia'));
      if ($diaslaborales[$indice]['laboral'] == 0) {
        $diareemplazo = $diaslaborales[$indice]['id_dia_ajuste'];
        $j = $indice;
        $flag = false;
        $cont = 0;
        while (!$flag) {
          $j = ($j >= 6) ? 0 : $j + 1;
          if ($diaslaborales[$j]['id_dia'] == $diareemplazo) {
            $flag = true;
          }
          $cont++;
        }
        $cantdias = ($cont <= 3) ? '+ ' . $cont : '- ' . ($numdia - ($cont - (7 - $numdia)));
        $daY = date('Y-m-d', strtotime($fechaini . ' ' . $cantdias . ' day'));
        $dia = date('D', strtotime($daY));
      }
      /*                    FIN AGREGADO POR BENEQ
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
      array_push($fchspgs, $daY);
      array_push($fchsreal, $fechareal);

      $daY = date('Y-m-d', strtotime($fechaini . $periodo));
      if ($meses) {
        // $daY = $this->DateSameMonth($fechaini, $mesesASumar);
        // $daY = self::addMonths($mesesASumar, $fechaini);
        $daY = self::addMonths2($fechaini2, $mesesASumar * $i);

        // error_log("aki ta papu: " . $daY);
      }

      //$timestamp = strtotime($daY);
      $fechaini = $daY;
      $fechareal = $daY;
    }

    $interes_calc = new profit_cmpst();
    $diasntrfchs = $interes_calc->calcuentreFCHAS($fchsreal, $fechaini2);
    // return array($diasntrfchs, $fchspgs, $fchsreal);
    return array($diasntrfchs, $fchsreal, $fchspgs); //ESPECIAL ADG
  } //calcudate

  //CALCULAR LOS DIAS ENTRE FECHAS REALES. pero no desde la fecha de pago 
  public function calcuentreFCHAS($fchspgs, $fechaini)
  {
    $dtini = 0;
    $difinicial = [];
    $canti1 = count($fchspgs);

    //CALCULA  ENTRE FECHAS DE PAGO 
    for ($i = 0; $i < $canti1 - 1; $i++) {
      if ($i == 0) {
        $datediff = strtotime($fchspgs[$i]) - strtotime($fechaini);
        // echo $fchspgs[$i] ." - ".$fechaini . "<br>";
        $fchcv = round($datediff / (60 * 60 * 24));
        array_push($difinicial, $fchcv);
        // echo  " " .$i ." ---- ".$fchcv."<br>" ; 
        $datediff = strtotime($fchspgs[$i + 1]) - strtotime($fchspgs[$i]);
        //echo $fchspgs[$i+1]." - ".$fchspgs[$i] . "<br>";
        $fchcv = round($datediff / (60 * 60 * 24));
        array_push($difinicial, $fchcv);
        // echo  " " .$i ." ---- ".$fchcv."<br>" ; 
      } else {
        $datediff = strtotime($fchspgs[$i + 1]) - strtotime($fchspgs[$i]);
        //echo $fchspgs[$i+1]." - ".$fchspgs[$i] . "<br>";
        $fchcv = round($datediff / (60 * 60 * 24));
        //echo  " " .$i ." ---- ".$fchcv."<br>" ; 
        array_push($difinicial, $fchcv);
      }
      //IMPRIMER LA RESTA
      $dtini = $datediff;
    }
    //CALCULO ENTRE LA PRIMERA FECHA DE PAGO Y LOS DEMAS PAGOS 
    //print_r($difinicial); 
    return $difinicial;
  }

  // ESTA FUNCION ES PAR CAMBIAR EL TIPO DE PERIODO, SI PAGARA POR MESNUALIDAS, BI, TRI. 
  public function ntipPerc($periodo)
  {

    switch ($periodo) {
      // Pago mensual
      case '1M':
        $mes = 1;
        $frecuencia = 12 / $mes;
        $periodo = ' + 1 months';
        return array($mes, $frecuencia, $periodo);
        break;
      //// Pago Bimensual
      case '2M':
        $mes = 2;
        $frecuencia = 12 / $mes;
        $periodo = ' + 2 months';
        return array($mes, $frecuencia, $periodo);
        break;
      //// Pago Trimestral
      case '3M':
        $mes = 3;
        $frecuencia = 12 / $mes;
        $periodo = ' + 3 months';
        return array($mes, $frecuencia, $periodo);
        break;
      // PAGO Semestral
      case '6M':
        $mes = 6;
        $frecuencia = 12 / $mes;
        $periodo = ' + 6 months';
        return array($mes, $frecuencia, $periodo);
        break;
      // Pago DIARIO, falta semanal y quincenal
      case '1D':
        $mes = 1;
        $frecuencia = 12 * 30; // 
        $periodo = ' + 1 Day';
        return array($mes, $frecuencia, $periodo);
        break;
      //  PAGO semanal 
      case '7D':
        $mes = 1;
        $frecuencia = 12 * 4;
        $periodo = ' + 7 Day';
        return array($mes, $frecuencia, $periodo);
        break;
      // PAGO quincenal
      case '15D':
        $mes = 2;
        $frecuencia = 12 * 2;
        $periodo = ' + 15 Day';
        return array($mes, $frecuencia, $periodo);
        break;
      // PAGO Catorcenal
      case '14D':
        $mes = 1;
        $frecuencia = 12 * 2;
        $periodo = ' + 14 Day';
        return array($mes, $frecuencia, $periodo);
        break;
      // PAGO ANUAL
      case '12M':
        $mes = 12;
        $frecuencia = 12 / $mes;
        $periodo = ' + 12 months';
        return array($mes, $frecuencia, $periodo);
        break;
      // POR DEFECTO SE DARA PAGO MENSUAL
      default:
        $mes = 1;
        $frecuencia = 12 / $mes;
        $periodo = ' + 1 months';
        return array($mes, $frecuencia, $periodo);
        break;
    }
  }
}
