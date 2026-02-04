<?php
    require 'serversideplus.php';
    $whereextra = $_GET['whereextra'];
    $table_data->get('fiadorGaratia','idcod_cliente',array('idcod_cliente', 'cod', 'cliente'),[0,1,1],$whereextra);
?>