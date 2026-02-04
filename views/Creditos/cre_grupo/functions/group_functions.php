<?php
//FILTRO DE DATOS BY BENEQ
function filtro($array, $columna, $p1, $p2)
{
  return (array_keys(array_filter(array_column($array, $columna), function ($var) use ($p1, $p2) {
    return ($var >= $p1 && $var <= $p2);
  })));
}
