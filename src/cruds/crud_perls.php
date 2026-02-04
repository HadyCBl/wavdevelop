<?php
session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
include '../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idusuario = $_SESSION['id'];
$idagencia = $_SESSION['id_agencia'];
$condi = $_POST["condi"];
switch ($condi) {
    case 'add_clase':
        $ccodcta = $_POST['ccodcta'];
        $id = $_POST['id'];
        $descrip = $_POST['descrip'];


        // Realizar la verificación si ya existe una entrada con los valores proporcionados
        $consulta = $conexion->query("SELECT id_tipo, id_ctb_nomenclatura  FROM ctb_parametros_general WHERE id_ctb_nomenclatura = '$id' AND id_tipo = '$ccodcta'");
        
        if ($consulta) {
            if ($consulta->num_rows > 0) {
                echo json_encode(array("existe" => true));
            } else {

                $insercion = $conexion->query("INSERT INTO ctb_parametros_general (id_ctb_nomenclatura, descripcion,id_tipo) VALUES ('$id','$descrip', '$ccodcta')");
        
                if ($insercion) {
                    echo json_encode(array("exito" => true));
                } else {
                    echo json_encode(array("error" => "Error al insertar datos: " . $conexion->error));
                }
            }
        } else {
            echo json_encode(array("error" => "Error en la consulta: " . $conexion->error));
        }
        
        mysqli_close($conexion);
        
        break;
        case 'update_class':
            $id = $_POST['id'];
            $ccodcta = $_POST['ccodcta'];
            $clase = $_POST['clase'];
            $descrip = $_POST['descrip']; 
        
            // Verificar si la entrada existe
            $consulta = $conexion->query("SELECT id FROM ctb_parametros_general WHERE id_ctb_nomenclatura = '$clase' AND id_tipo = '$ccodcta' AND descripcion = '$descrip' ");
            
            if ($consulta) {
                if ($consulta->num_rows > 0) {
                    // Ya existe una entrada con los mismos valores, pero diferente ID
                    echo json_encode(array("existe" => true));
                } else {
                    // Realizar la actualización
                    $actualizacion = $conexion->query("UPDATE ctb_parametros_general SET id_ctb_nomenclatura = '$clase', descripcion = '$descrip', id_tipo = '$ccodcta' WHERE id = '$id'");
                    
                    if ($actualizacion) {
                        echo json_encode(array("exito" => true));
                    } else {
                        echo json_encode(array("error" => "Error al actualizar datos: " . $conexion->error));
                    }
                }
            } else {
                echo json_encode(array("error" => "Error en la consulta: " . $conexion->error));
            }
            
            mysqli_close($conexion);
            
            break;
            case 'delete_class':
                $id = $_POST['id'];
                // Verificar si la entrada existe
                $consulta = $conexion->query("SELECT * FROM ctb_parametros_general WHERE id = '$id'");
            
                if ($consulta) {
                    if ($consulta->num_rows > 0) {
                        // Realizar la eliminación
                        $eliminacion = $conexion->query("DELETE FROM ctb_parametros_general WHERE id = '$id'");
            
                        if ($eliminacion) {
                            echo json_encode(array("exito" => true));
                        } else {
                            echo json_encode(array("error" => "Error al eliminar datos: " . $conexion->error));
                        }
                    } else {
                        echo json_encode(array("error" => "Datos ya eliminados."));
                    }
                } else {
                    echo json_encode(array("error" => "Error en la consulta: " . $conexion->error));
                }
            
                mysqli_close($conexion);
            
                break;
            
        

}