<?php
require 'serverside.php';
// $table_data->get('clientesReporte','id',array('id', 'nombre','dpi','fecNac','tipocliente'),[1,1,1,1,1]);
$table_data->get('clientesReporte', 'id', array('id', 'nombre', 'dpi', 'fecNac'), [1, 1, 1, 1]);
