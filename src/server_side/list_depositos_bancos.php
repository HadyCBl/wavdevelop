<?php
require 'serversideplus.php';
$whereextra = $_GET['whereextra'];
$table_data->get('view_depositos_bancos', 'id', array('id', 'numcom', 'feccnt', 'debe'), [0, 1, 1, 1], $whereextra);
