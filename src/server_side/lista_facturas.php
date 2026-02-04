<?php
require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
$tipo = $_GET['tip'];
$columnd = ($tipo == 2) ? "emisor" : "receptor";
$table_data->get('vs_lista_facturas', 'id', array('id', 'no_autorizacion', 'serie', 'fechahora_emision', $columnd), [0, 1, 1, 1, 1], $whereextra);
