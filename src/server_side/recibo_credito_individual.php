<?php
require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
$table_data->get('reciboCreditoIndiviudal', 'CODKAR', array('CODKAR', 'ccodcta', 'recibo', 'ciclo', 'fecha', 'monto', 'numcuota', 'CCONCEP', 'ccodusu', 'estado', 'dfecsis'), [0, 1, 1, 0, 1, 0, 0, 0, 0, 0, 0], $whereextra);
