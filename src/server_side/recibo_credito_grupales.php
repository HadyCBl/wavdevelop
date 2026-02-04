<?php
require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
$table_data->get('reciboCreditoGrupal', 'CODKAR', array('CODKAR', 'CNUMING', 'DFECPRO', 'NMONTO', 'NombreGrupo', 'CCodGrupo', 'NCiclo', 'codigo_grupo', 'ccodusu', 'estado', 'dfecsis'), [0, 1, 0, 0, 1, 0, 0, 0, 0, 0, 0], $whereextra);
