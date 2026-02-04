<?php
require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
$table_data->get('vs_recibos_ahorros', 'id', array('id', 'cnumdoc', 'ccodaho', 'crazon', 'ctipdoc', 'dfecope', 'monto', 'ccodusu', 'estado', 'dfecsis'), [0, 1, 1, 0, 1, 0, 0, 0, 0, 0], $whereextra);
