<?php
require 'serverside.php';
$table_data->get('clienteGarantia', 'idcod_cliente', array('idcod_cliente', 'cod', 'short_name'), [0, 1, 1]);
// $table_data->get('clienteGarantia','idcod_cliente',array('idcod_cliente', 'cod', 'short_name','tipocliente'),[0,1,1,1]);
