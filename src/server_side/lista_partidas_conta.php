<?php

// use Micro\Helpers\Log;

require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
// Log::info('Lista Partidas Conta', ['whereextra: ' . $whereextra]);
$table_data->get('vista_partidas', 'id', array('id', 'nom_agencia', 'numcom', 'feccnt','glosa', 'debe'), [0, 1, 1, 1, 1,1], $whereextra);
