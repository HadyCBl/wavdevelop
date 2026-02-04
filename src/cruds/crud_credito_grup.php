<?php

use PhpOffice\PhpSpreadsheet\Worksheet\Row;

session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
// include '../../src/funcphp/func_gen.php';
include '../../src/funcphp/fun_ppg.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s"); 
$hoy = date("Y-m-d");

$condi = $_POST["condi"];
switch ($condi) {
    case 'listadp_desembolso_a_solicitud':

        $consulta = mysqli_query($conexion, "SELECT cm.CCODCTA AS ccodcta, cl.short_name, cm.CodCli AS codcli, cm.CODAgencia AS codagencia, pd.cod_producto AS codproducto, cm.MonSug AS monto, cm.Cestado AS estado   FROM cremcre_meta cm
        INNER JOIN cre_productos pd ON cm.CCODPRD=pd.id 
        INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente WHERE cm.Cestado='E' AND cm.TipoEnti='INDI'");
        //se cargan los datos de las beneficiarios a un array
        $array_datos = array();
        $array_parenteco[] = [];
        $total = 0;
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $array_datos[] = array(
                "0" => $i + 1,
                "1" => $fila["short_name"],
                "2" => $fila["codproducto"],
                "3" => $fila["ccodcta"],
                "4" => $fila["monto"],
                "5" => '<button type="button" class="btn btn-danger" data-bs-dismiss="modal"   data-ccodcta="' . $fila["ccodcta"] . '" onclick="enviarDesem(this)">Desembolso a Solicitud</button>'
            );
            $i++;

            // eliminar(ideliminar, dir, xtra, condi)
        }
        $results = array(
            "sEcho" => 1, //info para datatables
            "iTotalRecords" => count($array_datos), //enviamos el total de registros al datatable
            "iTotalDisplayRecords" => count($array_datos), //enviamos el total de registros a visualizar
            "aaData" => $array_datos
        );
        mysqli_close($conexion);
        echo json_encode($results);
    
            
    break;
}
?>