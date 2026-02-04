<!--  DATOS DEL CARGOS -->
<?php

function cargos()
{
    ?>
    <script>
   
   function datt(){
    const valor1 = document.getElementById('fname1').value;
    console.log('Valor del input:', valor1);
    //alert(valor1);
    const valor2 = document.getElementById('fname2').value;
    console.log('Valor del input:', valor2);
    //alert(valor2);
    <?php
    $idcli = "<script>valor1</script>";
    $codgrup= "<script>valor2</script>";?>
   }</script><?php
    
?>

</script><?php
    //NUEVA CONEXION
    include __DIR__ . '/../../includes/Config/database.php';
    $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
    try{
        $database->openConnection(2);
        $cargos = $database->selectColumns('tb_cargo_grupo', ['id','nombre', 'descripcion']);
        foreach ($cargos as $cargo) {
            $idd= $cargo['id'];
            echo '
            <tr>  
                     <td>' . $cargo['id'] . '</td>
                     <td>' . $cargo['nombre'] . '</td>
                     <td>' . $cargo['descripcion'] . '</td>
                     <td> <button class="btn btn-success" onclick="ambos('. $idd .')"> SELECCIONAR </button> </td>
            </tr> ';
        }
    }catch (Exception $e){
        echo "Error: " . $e->getMessage();
    }finally {
        $database->closeConnection();
    }
    

}?>
<script>
 function ambos(idcargo) {
    let idd = document.getElementById('fname1').value;
    let codgrup = document.getElementById('fname2').value;
    //alert(idd + " , " + codgrup + ", " + idcargo);
    cambcargo(idd, codgrup, idcargo);
    }
</script>


<div class="modal fade" id="asignarcargo">
    <div class="modal-dialog modal-lg ">
        <div class="modal-content" style=" margin-left: -20%;">
            <!-- Modal Header -->
            <div class="modal-header">
                
                <h4 class="modal-title">Asignar cargos</h4>
                <input type="hidden" id="fname1">
                <input type="hidden" id="fname2">
              
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <!-- Modal body -->
            <div class="modal-body">
                <table id="table_id3" class="table table-striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>CARGO</th>
                            <th>DESCRIPCION</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="categoria_tb">
                    <?php
                        cargos();
                        ?>
                        
                    </tbody>
                </table>
            </div>
            <!-- Modal footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

    