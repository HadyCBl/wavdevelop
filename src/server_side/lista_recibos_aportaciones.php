<?php
require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
$table_data->get('vs_recibos_aportaciones', 'id', array('id', 'cnumdoc', 'ccodaport', 'crazon', 'ctipdoc', 'dfecope', 'monto', 'ccodusu', 'estado', 'dfecsis'), [0, 1, 1, 0, 1, 0, 0, 0, 0, 0], $whereextra);
