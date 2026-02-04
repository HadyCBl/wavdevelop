<?php
require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
$table_data->get('vs_recibos_otros_ingresos','id',array('id', 'fecha', 'recibo', 'cliente', 'descripcion','ccodusu','estado','dfecsis','file'),[0,1,1,1,0,0,0,0,0],$whereextra);
?>