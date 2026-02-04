<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  header('location: ' . BASE_URL . '404.php');
}
session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
  ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

use Micro\Generic\AblyService;
use Micro\Helpers\Log;
use Micro\Exceptions\AblyServiceException;
use App\Generic\Agencia;
use App\Generic\FileProcessor;
use App\Generic\Models\ClienteJsonService;
use Micro\Exceptions\SoftException;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Models\Departamento;
use Micro\Models\Identificacion;
use Micro\Models\Pais;

$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);

$condisList = ["create_cliente_natural", "update_cliente_natural"];
if (in_array($_POST["condi"], $condisList) && !isset($_SESSION['id'])) {
  $encrypt = $secureID->encrypt('reloginKeyUniqueXD');
  echo json_encode(['Su sesión ha expirado, por favor inicie sesión nuevamente', '0', 'relogin' => 1, 'key' => $encrypt]);
  return;
}

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');

$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idagencia = $_SESSION['id_agencia'];

$condi = $_POST["condi"];
switch ($condi) {
  case 'cargar_imagen': {
      // OBTENER CODIGO DE CLIENTE
      $ccodcli = $_POST['codcli'];
      $salida = "../../../"; // SUDOMINIOS PROPIOS
      $queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
      INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop where ag.id_agencia=" . $_SESSION['id_agencia']);
      $infoi = [];
      while ($fil = mysqli_fetch_array($queryins)) {
        $infoi[] = $fil;
      }
      if (empty($infoi)) {
        echo json_encode(['No se encuentra la ruta de la organizacion', '0']);
        return;
      }
      $folderprincipal = $infoi[0]['folder'];
      $entrada = "imgcoope.microsystemplus.com/" . $folderprincipal . "/" . $ccodcli;
      $rutaEnServidor = $salida . $entrada;
      $extensiones = ["jpg", "jpeg", "pjpeg", "png", "gif"];

      // Eliminar archivos existentes con las extensiones permitidas
      foreach ($extensiones as $ext) {
        $filePath = $rutaEnServidor . "/" . $ccodcli . "." . $ext;
        if (file_exists($filePath)) {
          unlink($filePath);
        }
      }

      // Crear la ruta si no existe
      if (!is_dir($rutaEnServidor)) {
        mkdir($rutaEnServidor, 0777, true);
      }

      // Verificar si se subió una imagen
      if (is_uploaded_file($_FILES['fileimg']['tmp_name'])) {
        $rutaTemporal = $_FILES['fileimg']['tmp_name'];
        $info = pathinfo($_FILES['fileimg']['name']);
        $nombreImagen = $ccodcli;
        $nomimagen = '/' . $nombreImagen . "." . $info['extension'];
        $rutaDestino = $rutaEnServidor . $nomimagen;

        // Verificar que el tipo sea JPEG, PNG, o GIF
        $validTypes = ["image/jpeg", "image/jpg", "image/png", "image/gif"];
        if (in_array($_FILES["fileimg"]["type"], $validTypes)) {
          // Tamaño máximo deseado
          $maxWidth = 800;
          $maxHeight = 600;
          list($origWidth, $origHeight) = getimagesize($rutaTemporal);
          $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
          $newWidth = intval($origWidth * $ratio);
          $newHeight = intval($origHeight * $ratio);

          // Crear imagen desde el archivo temporal
          switch ($_FILES["fileimg"]["type"]) {
            case "image/jpeg":
            case "image/jpg":
              $srcImage = imagecreatefromjpeg($rutaTemporal);
              break;
            case "image/png":
              $srcImage = imagecreatefrompng($rutaTemporal);
              break;
            case "image/gif":
              $srcImage = imagecreatefromgif($rutaTemporal);
              break;
          }
          $dstImage = imagecreatetruecolor($newWidth, $newHeight);

          // Redimensionar y copiar la imagen
          imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

          // Guardar la imagen en el destino con calidad 75
          $saveSuccess = false;
          switch ($_FILES["fileimg"]["type"]) {
            case "image/jpeg":
            case "image/jpg":
              $saveSuccess = imagejpeg($dstImage, $rutaDestino, 75);
              break;
            case "image/png":
              $saveSuccess = imagepng($dstImage, $rutaDestino);
              break;
            case "image/gif":
              $saveSuccess = imagegif($dstImage, $rutaDestino);
              break;
          }

          if ($saveSuccess) {
            // Continuar con la transacción y actualizar la base de datos
            $conexion->autocommit(false);
            try {
              $consulta2 = mysqli_query($conexion, "UPDATE `tb_cliente` SET `url_img`='" . $entrada . $nomimagen . "' WHERE idcod_cliente = '" . $ccodcli . "'");
              if (mysqli_error($conexion) || !$consulta2) {
                echo json_encode(['Error en la inserción de la ruta de la foto', '0']);
                $conexion->rollback();
                return;
              }
              $conexion->commit();
              echo json_encode(['Foto de cliente cargado correctamente', '1']);
            } catch (Exception $e) {
              $conexion->rollback();
              echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
          } else {
            echo json_encode(['Fallo al guardar la imagen optimizada', '0']);
          }
          // Liberar memoria
          imagedestroy($srcImage);
          imagedestroy($dstImage);
        } else {
          echo json_encode(['La extensión de la imagen no es permitida, ingrese una imagen jpeg, jpg, png o gif', '0']);
        }
      }
      mysqli_close($conexion);
    }
    break;
  case 'consultaCre':
    $cnt = $_POST["cnt"];
    mysqli_query($conexion, "SELECT * FROM cremcre_meta cm INNER JOIN tb_grupo gp ON cm.CCodGrupo=gp.id_grupos INNER JOIN tb_cliente_tb_grupo cgp ON gp.id_grupos=cgp.Codigo_grupo WHERE (cm.Cestado='F' OR cm.Cestado='A' OR cm.Cestado='D' OR cm.Cestado='E') AND cm.CCodGrupo=" . $cnt);
    $info = mysqli_affected_rows($conexion);
    mysqli_query($conexion, "SELECT estadoGrupo FROM tb_grupo WHERE estado = 1 AND estadoGrupo = 'A' AND id_grupos =" . $cnt);
    $info1 = mysqli_affected_rows($conexion);
    echo $info . '  ' . $info1;
    if ($info >= 1 && $info = 0) {
      echo 1;
    } else {
      echo 0;
    }
    break;
  /*------------------ INSERTAR DATOS DE GRUPOS  --------------------------------------------------------------- */
  case 'grptable':
    $cnt = $_POST["cnt"];
    $i = 1;
    mysqli_query($conexion, "SELECT * FROM cremcre_meta cm INNER JOIN tb_grupo gp ON cm.CCodGrupo=gp.id_grupos INNER JOIN tb_cliente_tb_grupo cgp ON gp.id_grupos=cgp.Codigo_grupo WHERE (cm.Cestado='F' OR cm.Cestado='A' OR cm.Cestado='D' OR cm.Cestado='E') AND cm.CCodGrupo=" . $cnt);
    $info = mysqli_affected_rows($conexion);
    $control = 0;
    if ($info >= 1) {
      $control = 1;
    }
    mysqli_query($conexion, "SELECT estadoGrupo FROM tb_grupo WHERE estado = 1 AND estadoGrupo = 'A' AND id_grupos =" . $cnt);
    $estadoGupo = mysqli_affected_rows($conexion);
    $consulta = mysqli_query($conexion, "SELECT idcod_cliente, short_name, no_identifica, date_birth, id_grupo, cod_cargo  FROM tb_cliente INNER JOIN tb_cliente_tb_grupo ON tb_cliente.idcod_cliente = tb_cliente_tb_grupo.cliente_id WHERE Codigo_grupo = $cnt AND tb_cliente_tb_grupo.estado = 1");

    while ($grpclt = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
      $id = $grpclt["idcod_cliente"];
      $short_name = ($grpclt["short_name"]);
      $no_identifica = ($grpclt["no_identifica"]);
      $date_birth = ($grpclt["date_birth"]);
      $idgrptabl = $grpclt["id_grupo"];
      $codcar = $grpclt["cod_cargo"];
      $cc = "";
      try {
        $database->openConnection(2);
        $polizas = $database->selectColumns('tb_cargo_grupo', ['id', 'nombre']);

        foreach ($polizas as $dat2) {
          if ($dat2['id'] == $codcar) {
            $cc = $dat2['nombre'];
          }
        }
        if ($cc == "") {
          $cc = "Miembro";
        }
      } catch (Exception $e) {
        echo "Error: " . $e->getMessage();
      } finally {
        $database->closeConnection();
      }

      $info = ($estadoGupo == 1) ? '<button type="button" class="btn btn-default" title="Eliminar" onclick="dltgrpcli(&apos;' .
        $id . '&apos;, &apos;' . $idgrptabl . '&apos;)"> <i class="fa-solid fa-trash-can"></i></button>
                                    <button type="button" onclick="obtcligrup(this)" id="ACargo" class="btn btn-outline-primary select-cargo" 
                                    title="Asignar cargo" data-bs-toggle="modal" data-bs-target="#asignarcargo" data-id="' . $id . ', ' . $cnt . '">
                                    <i class="fa-solid fa-edit"></i> </button>' : (($estadoGupo == 0 && $control == 1) ? '<button type="button" class="btn btn-default" 
                                    title="No se puede eliminar" onclick="msjCreditosPen()"><i class="fa-solid fa-lock"></i></button> 
                                    ' :
          '<button type="button" class="btn btn-default miclase" title="No se puede eliminar" 
                                    onclick="msjCreditosPen()"><i class="fa-solid fa-lock"></i></button> 
                                    x');
      echo '
    <tr>
        <td>' . $i . '</td>
        <td>' . $id . '</td>
        <td>' . $short_name . '</td>
        <td>' . $no_identifica . '</td>
        <td>' . $date_birth . '</td>
        <td>' . $cc . '</td>
        <td>' . $info . '</td>
      </tr>';
      $i++;
    }
    mysqli_close($conexion);
    break;

  /*--------------------------------------------------------------------------------- */
  case 'instupftgrp':
    $cnt = $_POST["cnt"]; // opcion 0 insert, diferente de 0 para actualizar 
    $grpinptval2 = $_POST["grpinptval2"];
    $depa = $_POST["depa"];
    $muni = $_POST["muni"];
    $usuario = $_POST["usuario"];
    if ($cnt == "0") {
      //$id = date("dmyhis");// Lo usan para, generar codigo 
      $est = 0;
      $caracteres = '0123456789';
      $codigo;
      while ($est != 1) {
        $codigo = '';
        $max = strlen($caracteres) - 1;
        for ($i = 0; $i < 5; $i++) {
          $codigo .= $caracteres[mt_rand(0, $max)];
        }
        $cod = intval($codigo);

        $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM tb_grupo WHERE codigo_grupo = $codigo ) AS Resultado");
        // Si la consulta fue exitosa
        $resultado = $validarRep->fetch_assoc()['Resultado'];
        if ($resultado == 0) {
          $est = 1;
        } //Fin validad repetidos
      }
      // inserta el grupo generar el cod del grupo 
      // $consulta = mysqli_query($conexion, "INSERT INTO `tb_grupo`(`id_grupos`, `codigo_grupo`, `NombreGrupo`, `fecha_sys`, `Depa`, `Muni`, `canton`, `direc`) VALUES ('','$id','".$grpinptval2[0]."','".$grpinptval2[5]."','$depa','$muni','".$grpinptval2[1]."','".$grpinptval2[2]."')");

      // $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM tb_grupo WHERE NombreGrupo = '$grpinptval2[0]' ) AS Resultado");
      // // Si la consulta fue exitosa
      // $resultado = $validarRep->fetch_assoc()['Resultado'];
      // if ($resultado == 1) {
      //   echo 'El nombre del grupo que ingreso ya existe en el sistema.';
      //   return;
      // } //Fin validad repetidos
      $consulta = mysqli_query($conexion, "INSERT INTO `tb_grupo`(`codigo_grupo`, `NombreGrupo`, `fecha_sys`, `Depa`, `Muni`, `canton`, `direc`, `estado`, `created_by`, `created_at`,`estadoGrupo`, `close_by`, `close_at`) VALUES ($codigo,'$grpinptval2[0]','$hoy','$depa','$muni','$grpinptval2[1]','$grpinptval2[2]',1,$usuario,'$hoy2', 'A', $usuario, '$hoy2')");
      //  echo json_encode([$codigo, " - ".$consulta]); 
      if (!$consulta) {
        //  echo "Error al INGRESAR".mysqli_error();
        //echo "Error al INGRESAR";
      } else {
        echo "GRUPO INGRESADO";
      }
    } else {
      //echo $usuario." - ".$hoy2." - ".$cnt; return; 
      // REALIZA UN UDATE
      // $consulta2 = mysqli_query($conexion, "UPDATE `tb_grupo` SET `NombreGrupo`='" . $grpinptval2[0] . "',`fecha_sys`='" . $hoy2 . "',`Depa`='$depa',`Muni`='$muni',`canton`='" . $grpinptval2[1] . "',`direc`='" . $grpinptval2[2] . "','update_at ='" . $usuario .", updated_at = '".$hoy2."' WHERE id_grupos = $cnt ");
      // $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM tb_grupo WHERE NombreGrupo = '$grpinptval2[0]' AND id_grupos != $cnt) AS Resultado");
      // // Si la consulta fue exitosa
      // $resultado = $validarRep->fetch_assoc()['Resultado'];
      // if ($resultado == 1) {
      //   echo 'El nombre del grupo que ingreso ya existe en el sistema.';
      //   return;
      // } //Fin validad repetidos
      $consulta2 = mysqli_query($conexion, "UPDATE `tb_grupo` SET `NombreGrupo`='$grpinptval2[0]', `fecha_sys`='$hoy2', `Depa`='$depa', `Muni`='$muni', `canton`='$grpinptval2[1]', `direc`='$grpinptval2[2]', `updated_by`='$usuario', `updated_at`='$hoy2' WHERE `id_grupos` = $cnt");

      if (!$consulta2) {
        // echo "Error al Actualizar".mysqli_error();
        echo "Error al Actualizar";
      } else {
        echo "GRUPO ACTUALIZADO";
      }
    }
    break;

  /*--------------------------------------------------------------------------------- */
  case 'instclntingrp':
    $idcln = $_POST["cln"]; //id del cliente
    $idgrp = $_POST["cnt"]; //id del grupo   
    $usuario = $_POST["usuario"];
    //Validar si el codigo de usuario ya existe en en el sistema
    $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM tb_cliente_tb_grupo WHERE cliente_id = $idcln AND Codigo_grupo=$idgrp AND estado=1) AS Resultado");
    //Recoger el resultado 
    $resultado = $validarRep->fetch_assoc()['Resultado'];
    if ($resultado == 1) {
      echo "El cliente ya pertenece al grupo";
      return;
    }
    // $insertamela = mysqli_query($conexion, "INSERT INTO `tb_cliente_tb_grupo` (`Codigo_grupo`, `cliente_id`,'estado','created_by','created_at') VALUES ('$idgrp', '$idcln',1,'$usuario','$hoy2');");
    //Validar si el grupo esta abierto
    $validarRep = $conexion->query("SELECT EXISTS(SELECT * FROM tb_grupo WHERE estadoGrupo = 'A' AND id_grupos = $idgrp) AS Resultado");
    //Recoger el resultado 
    $resultado = $validarRep->fetch_assoc()['Resultado'];
    // $resultado = 1; //--REQ--fape--1--sin restricciones para agregar integrantes a grupo
    if ($resultado == 0) {
      echo "En el grupo ya no se pueden ingresar clientes, por que se encuentra cerrado";
      return;
    }
    $insertamela = mysqli_query($conexion, "INSERT INTO `tb_cliente_tb_grupo` (`Codigo_grupo`, `cliente_id`, estado, created_by, created_at) VALUES ('$idgrp', '$idcln', 1, '$usuario', '$hoy2')");

    if (!$insertamela) {
      // echo "Error al Actualizar".mysqli_error();
      echo "Error al Ingresar el Cliente";
    } else {
      echo "Cliente Agregado";
    }
    mysqli_close($conexion);
    break;
  /* ----------------------------------------------------------------------------------*/
  case 'cerrarGrupo':
    $idgrp = $_POST["cnt"]; //id del grupo   
    $usuario = $_POST["usuario"];

    mysqli_query($conexion, "SELECT *FROM tb_cliente_tb_grupo WHERE Codigo_grupo = $idgrp AND estado = 1");
    $info = mysqli_affected_rows($conexion);
    //echo $info.' ---  '.$idgrp; return ; 
    if ($info == 0) {
      echo "Para cerrar el grupo, tiene que ingresar clientes";
      return;
    }

    $consulta = mysqli_query($conexion, "UPDATE tb_grupo SET estadoGrupo = 'C', open_by =  $usuario, open_at = '$hoy2' WHERE id_grupos =" . $idgrp);
    if (!$consulta) {
      // echo "Error al Actualizar".mysqli_error();
      echo "Error al Eliminar";
    } else {
      echo "Grupo cerrado";
    }

    mysqli_close($conexion);

    break;
  /* ----------------------------------------------------------------------------------*/
  case 'abrirGrupo':
    $idgrp = $_POST["cnt"]; //id del grupo   
    $usuario = $_POST["usuario"];

    mysqli_query($conexion, "SELECT * FROM cremcre_meta cm INNER JOIN tb_grupo gp ON cm.CCodGrupo=gp.id_grupos INNER JOIN tb_cliente_tb_grupo cgp ON gp.id_grupos=cgp.Codigo_grupo WHERE (cm.Cestado='F' OR cm.Cestado='A' OR cm.Cestado='D' OR cm.Cestado='E') AND cm.CCodGrupo=" . $idgrp);

    $info = mysqli_affected_rows($conexion);
    $abrirgrupo = ($_ENV['CLI_ABRIR_GRUPO'] ?? 0) == 1;
    $control = 0;
    // $info=0; //--REQ--fape--1--sin restricciones para agregar integrantes a grupo
    if ($info >= 1 && !$abrirgrupo) {
      echo "El grupo está en un ciclo de crédito, razón por la cual no se puede abrir. ";
      return;
    }

    $consulta = mysqli_query($conexion, "UPDATE tb_grupo SET estadoGrupo = 'A', close_by =  $usuario, close_at = '$hoy2' WHERE id_grupos =" . $idgrp);
    if (!$consulta) {
      // echo "Error al Actualizar".mysqli_error();
      echo "Error al Eliminar";
    } else {
      echo "El grupo esta abierto";
    }

    mysqli_close($conexion);

    break;

  /*--------------------------------------------------------------------------------- */
  case 'dltclntgrp': //ELIMINAR CLIENTE DEL GURPO...
    $idclnt = $_POST["id"];
    $idgrptabl = $_POST["nme"];
    $usuario = $_POST["usuario"];

    mysqli_query($conexion, "SELECT * FROM cremcre_meta cm
    INNER JOIN tb_grupo gp ON cm.CCodGrupo=gp.id_grupos
    INNER JOIN tb_cliente_tb_grupo cgp ON gp.id_grupos=cgp.Codigo_grupo
    WHERE cgp.estado=1 AND (cm.Cestado='F' OR cm.Cestado='A' OR cm.Cestado='D' OR cm.Cestado='E') AND cm.CodCli=" . $idclnt);

    $info = mysqli_affected_rows($conexion);

    if ($info >= 1) {
      // echo "El cliente no se puede eliminar, tiene créditos pendientes.";
      // return;
    }
    // $consulta = mysqli_query($conexion, "DELETE FROM `tb_cliente_tb_grupo` WHERE `tb_cliente_tb_grupo`.`id_grupo` = $idgrptabl");
    $consulta = mysqli_query($conexion, "UPDATE tb_cliente_tb_grupo set estado = 0, deleted_by = $usuario, deleted_at = '$hoy2' WHERE id_grupo=" . $idgrptabl);
    if (!$consulta) {
      // echo "Error al Actualizar".mysqli_error();
      echo "Error al Eliminar";
    } else {
      echo "Cliente ELIMINADO DEL GRUPO";
    }

    mysqli_close($conexion);
    break;

  /*--------------------------------------------------------------------------------- */
  /*--------------------------------------------------------------------------------- */
  case 'agrcargo': //AGREGAR CARGO...
    $idcli = $_POST["idcli"]; //id del cliente
    $codgrup = $_POST["codgrup"]; // Codigo DEL GRUPO
    $codcargo = $_POST["codcargo"]; //Codigo del cargo
    $table = "tb_cliente_tb_grupo";
    $data = [
      'cod_cargo' => $codcargo,
    ];
    $condition = 'cliente_id = ?';
    $conditionParams = [$idcli];
    $query = "SELECT count(*) as cont FROM tb_cliente_tb_grupo WHERE cod_cargo = :id AND codigo_grupo = :cod";
    $params = ['id' => $codcargo, 'cod' => $codgrup];
    try {
      $database->openConnection(1);
      $datos = $database->getSingleResult($query, $params);
      if ($datos['cont'] == 0 || $codcargo == 1) {
        $database->update($table, $data, "codigo_grupo = ? AND cliente_id = ?", [$codgrup, $idcli]);
      }
    } catch (Exception $e) {
      echo "Error: " . $e->getMessage();
    } finally {
      $database->closeConnection();
    }

    break;

  /*--------------------------------------------------------------------------------- */
  case 'dltgrp':
    $idgrp = $_POST["cnt"];
    $usuario = $_POST['usuario'];
    //echo "GRUPO ELIMINADO";
    // $consulta = mysqli_query($conexion, "DELETE FROM `tb_grupo` WHERE `tb_grupo`.`id_grupos` = $idgrp");
    mysqli_query($conexion, "SELECT * FROM cremcre_meta cm INNER JOIN tb_grupo gp ON cm.CCodGrupo=gp.id_grupos INNER JOIN tb_cliente_tb_grupo cgp ON gp.id_grupos=cgp.Codigo_grupo WHERE (cm.Cestado='F' OR cm.Cestado='A' OR cm.Cestado='D' OR cm.Cestado='E') AND cm.CCodGrupo=" . $idgrp);
    $info = mysqli_affected_rows($conexion);

    if ($info >= 1) {
      echo "No se puede eliminar, el grupo tiene creditos pendientes";
      return;
    }
    mysqli_query($conexion, "SELECT *FROM tb_cliente_tb_grupo WHERE Codigo_grupo = " . $idgrp . " AND estado = 1; ");
    $info = mysqli_affected_rows($conexion);

    if ($info >= 1) {
      echo "Para eliminar el grupo primero tiene que eliminar a los clientes...";
      return;
    }

    $consulta = mysqli_query($conexion, "UPDATE tb_grupo SET estado = 0, deleted_by = $usuario, deleted_at = '$hoy2' WHERE id_grupos=" . $idgrp);

    if (!$consulta) {
      echo "Error al Eliminar";
    } else {
      echo "GRUPO ELIMINADO";
    }
    mysqli_close($conexion);
    break;
  case 'create_balance_economico':
    //validar todos los campos necesarios
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$inputs[0], "", 'Debe seleccionar un cliente', 1],
      [$inputs[1], "", 'Debe seleccionar un cliente', 1],
      [$inputs[16], "", 'Debe digitar una fecha de evaluación', 1],
      [$inputs[17], "", 'Debe digitar una fecha de balance', 1],
      [$inputs[2], "", 'Digite un número para el campo ventas', 1],
      [$inputs[2], 0, 'Digite un número no negativo para el campo ventas', 2],
      [$inputs[3], "", 'Digite un número para el campo recuperación de cuentas por cobrar', 1],
      [$inputs[3], 0, 'Digite un número no negativo para el campo recuperación de cuentas por cobrar', 2],
      [$inputs[4], "", 'Digite un número para el campo compra de mercadería', 1],
      [$inputs[4], 0, 'Digite un número no negativo para el campo compra de mercadería', 2],
      [$inputs[5], "", 'Digite un número para el campo gastos del negocio', 1],
      [$inputs[5], 0, 'Digite un número no negativo para el campo gastos del negocio', 2],
      [$inputs[6], "", 'Digite un número para el campo pagos de créditos', 1],
      [$inputs[6], 0, 'Digite un número no negativo para el campo pagos de créditos', 2],
      [$inputs[7], "", 'Digite un número para el campo disponible', 1],
      [$inputs[7], 0, 'Digite un número no negativo para el campo disponible', 2],
      [$inputs[8], "", 'Digite un número para el campo cuentas por cobrar', 1],
      [$inputs[8], 0, 'Digite un número no negativo para el campo cuentas por cobrar', 2],
      [$inputs[9], "", 'Digite un número para el campo inventario', 1],
      [$inputs[9], 0, 'Digite un número no negativo para el campo inventario', 2],
      [$inputs[10], "", 'Digite un número para el campo activo fijo', 1],
      [$inputs[10], 0, 'Digite un número no negativo para el campo activo fijo', 2],
      [$inputs[11], "", 'Digite un número para el campo proveedores', 1],
      [$inputs[11], 0, 'Digite un número no negativo para el campo proveedores', 2],
      [$inputs[12], "", 'Digite un número para el campo otros préstamos', 1],
      [$inputs[12], 0, 'Digite un número no negativo para el campo otros préstamos', 2],
      [$inputs[13], "", 'Digite un número para el campo préstamos a instituciones', 1],
      [$inputs[13], 0, 'Digite un número no negativo para el campo préstamos a instituciones', 2],
      [$inputs[14], "", 'Digite un número para el campo patrimonio', 1],
      [$inputs[14], 0, 'Digite un número no negativo para el campo patrimonio', 2],
      // [$inputs[15], 0, 'El campo saldo debe ser igual a 0, cuadre los montos', 2],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    //sumar activo y pasivo
    if (($inputs[7] + $inputs[8] + $inputs[9] + $inputs[10]) != ($inputs[11] + $inputs[12] + $inputs[13] + $inputs[14])) {
      echo json_encode(['La suma de saldo debe ser igual a 0, cuadre los montos', '0']);
      return;
    }

    //VAMOS A REALIZAR LA INSERCION EN LA TABLA DE BALANCES
    $res = $conexion->query("INSERT INTO `tb_cli_balance`(`ccodcli`,`fechaeval`,`fechabalance`,`ventas`,`cuenta_por_cobrar`,`mercaderia`,`negocio`,`pago_creditos`,`disponible`,`cuenta_por_cobrar2`,`inventario`,`activo_fijo`,`proveedores`,`otros_prestamos`,`prest_instituciones`,`patrimonio`,`created_at`,`created_by`) VALUES ('$inputs[0]','$inputs[16]','$inputs[17]','$inputs[2]', '$inputs[3]','$inputs[4]','$inputs[5]','$inputs[6]','$inputs[7]','$inputs[8]','$inputs[9]','$inputs[10]','$inputs[11]','$inputs[12]','$inputs[13]','$inputs[14]','$hoy2','$archivo[0]')");
    $aux = mysqli_error($conexion);
    $balance_insert = get_id_insertado($conexion);
    if ($aux) {
      echo json_encode(['Error en la inserción del balance económico' . $aux, '0']);
      return;
    }
    if (!$res) {
      echo json_encode(['No se logro insertar el balance económico', '0']);
      return;
    }

    echo json_encode(['Balance económico registrado satisfactoriamente', '1']);
    mysqli_close($conexion);
    break;
  case 'update_balance_economico':
    //validar todos los campos necesarios
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$inputs[0], "", 'Debe seleccionar un cliente', 1],
      [$inputs[1], "", 'Debe seleccionar un cliente', 1],
      [$inputs[16], "", 'Debe digitar una fecha de evaluación', 1],
      [$inputs[17], "", 'Debe digitar una fecha de balance', 1],
      [$inputs[2], "", 'Digite un número para el campo ventas', 1],
      [$inputs[2], 0, 'Digite un número no negativo para el campo ventas', 2],
      [$inputs[3], "", 'Digite un número para el campo recuperación de cuentas por cobrar', 1],
      [$inputs[3], 0, 'Digite un número no negativo para el campo recuperación de cuentas por cobrar', 2],
      [$inputs[4], "", 'Digite un número para el campo compra de mercadería', 1],
      [$inputs[4], 0, 'Digite un número no negativo para el campo compra de mercadería', 2],
      [$inputs[5], "", 'Digite un número para el campo gastos del negocio', 1],
      [$inputs[5], 0, 'Digite un número no negativo para el campo gastos del negocio', 2],
      [$inputs[6], "", 'Digite un número para el campo pagos de créditos', 1],
      [$inputs[6], 0, 'Digite un número no negativo para el campo pagos de créditos', 2],
      [$inputs[7], "", 'Digite un número para el campo disponible', 1],
      [$inputs[7], 0, 'Digite un número no negativo para el campo disponible', 2],
      [$inputs[8], "", 'Digite un número para el campo cuentas por cobrar', 1],
      [$inputs[8], 0, 'Digite un número no negativo para el campo cuentas por cobrar', 2],
      [$inputs[9], "", 'Digite un número para el campo inventario', 1],
      [$inputs[9], 0, 'Digite un número no negativo para el campo inventario', 2],
      [$inputs[10], "", 'Digite un número para el campo activo fijo', 1],
      [$inputs[10], 0, 'Digite un número no negativo para el campo activo fijo', 2],
      [$inputs[11], "", 'Digite un número para el campo proveedores', 1],
      [$inputs[11], 0, 'Digite un número no negativo para el campo proveedores', 2],
      [$inputs[12], "", 'Digite un número para el campo otros préstamos', 1],
      [$inputs[12], 0, 'Digite un número no negativo para el campo otros préstamos', 2],
      [$inputs[13], "", 'Digite un número para el campo préstamos a instituciones', 1],
      [$inputs[13], 0, 'Digite un número no negativo para el campo préstamos a instituciones', 2],
      [$inputs[14], "", 'Digite un número para el campo patrimonio', 1],
      [$inputs[14], 0, 'Digite un número no negativo para el campo patrimonio', 2],
      // [$inputs[15], 0, 'El campo saldo debe ser igual a 0, cuadre los montos', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    //sumar activo y pasivo
    if (($inputs[7] + $inputs[8] + $inputs[9] + $inputs[10]) != ($inputs[11] + $inputs[12] + $inputs[13] + $inputs[14])) {
      echo json_encode(['La suma de saldo debe ser igual a 0, cuadre los montos2', '0']);
      return;
    }

    $aux_balance = $archivo[0];

    //VAMOS A REALIZAR LA INSERCION EN LA TABLA DE BALANCES
    $res = $conexion->query("UPDATE `tb_cli_balance`
      SET `fechaeval`='$inputs[16]',
      `fechabalance`='$inputs[17]',
      `ventas`='$inputs[2]',
      `cuenta_por_cobrar`='$inputs[3]',
      `mercaderia`='$inputs[4]',
      `negocio`='$inputs[5]',
      `pago_creditos`='$inputs[6]',
      `disponible`='$inputs[7]',
      `cuenta_por_cobrar2`='$inputs[8]',
      `inventario`='$inputs[9]',
      `activo_fijo`='$inputs[10]',
      `proveedores`='$inputs[11]',
      `otros_prestamos`='$inputs[12]',
      `prest_instituciones`='$inputs[13]',
      `patrimonio`='$inputs[14]',
      `updated_at`='$hoy2',
      `updated_by`='$idusuario' WHERE `id`='$aux_balance'");
    $aux = mysqli_error($conexion);
    $balance_insert = get_id_insertado($conexion);
    if ($aux) {
      echo json_encode(['Error en la inserción del balance económico' . $aux, '0']);
      return;
    }
    if (!$res) {
      echo json_encode(['No se logro insertar el balance económico', '0']);
      return;
    }

    echo json_encode(['Balance económico registrado satisfactoriamente', '1']);
    mysqli_close($conexion);
    break;
  case 'delete_balance_economico':
    //validar todos los campos necesarios
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$inputs[0], "", 'Debe seleccionar un cliente', 1],
      [$inputs[1], "", 'Debe seleccionar un cliente', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    $aux_balance = $archivo[0];
    //VAMOS A REALIZAR LA INSERCION EN LA TABLA DE BALANCES
    $res = $conexion->query("DELETE FROM `tb_cli_balance` WHERE `id`='$aux_balance'");
    $aux = mysqli_error($conexion);
    $balance_insert = get_id_insertado($conexion);
    if ($aux) {
      echo json_encode(['Error en la inserción del balance económico' . $aux, '0']);
      return;
    }
    if (!$res) {
      echo json_encode(['No se logro insertar el balance económico', '0']);
      return;
    }

    echo json_encode(['Balance económico eliminado satisfactoriamente', '1']);
    mysqli_close($conexion);
    break;
  case 'generar_json_cli':
    $idcliente = $_POST['idcliente'];
    $clienteService = new ClienteJsonService($database);
    $result = $clienteService->generarJsonCliente($idcliente);
    echo json_encode($result, JSON_PRETTY_PRINT);
    break;
  //crear el cliente natural 
  case 'create_cliente_natural':

    if (!isset($_SESSION['id'])) {
      echo json_encode(["Sesion expirada, por favor inicie sesión nuevamente", "0"]);
      return;
    }

    $inputs   = json_decode($_POST["inputs"], true);
    $selects  = json_decode($_POST["selects"], true);
    $radios   = json_decode($_POST["radios"], true);
    /**
     * RECOLECCION DE INPUTS
     * [`nom1`,`nom2`,`nom3`,`ape1`,`ape2`,`ape3`,`profesion`,`email`,`conyugue`,`fechanacimiento`,
     *  `dirnac`,`edad`, `numberdoc`,`numbernit`,`afiliggs`,`reside`,`dirviv`,`refviv`,`representante`,`refn1`,
     *  `ref1`,`refn2`,`ref2`,`refn3`,`ref3`,`tel1`,`tel2`,`telconyuge`,`zonaviv`,`barrioviv`,
     *  `hijos`,`dependencia`,`codinterno`,`observaciones`,`refd1`,`refdir1`,`refd2`,`refdir2`,`refd3`,`refdir3`,
     * `pep_entidad`,`pep_puesto`,`pep_origen_riqueza_otro`,
     * `pariente_pep_primer_apellido`,`pariente_pep_segundo_apellido`,`pariente_pep_apellido_casada`,
     * `pariente_pep_primer_nombre`, `pariente_pep_segundo_nombre`, `pariente_pep_otros_nombres`,
     * `pariente_pep_entidad`, `pariente_pep_puesto`, `asociado_pep_otro_motivo`,
     * `asociado_pep_primer_apellido`,`asociado_pep_segundo_apellido`,`asociado_pep_apellido_casada`,
     * `asociado_pep_primer_nombre`, `asociado_pep_segundo_nombre`, `asociado_pep_otros_nombres`,
     * `asociado_pep_entidad`, `asociado_pep_puesto`], 
     */

    // Log::info("Inputs recibidos", $inputs);

    $appPaisVersion = $_ENV['APP_PAIS_VERSION'] ?? 'GT';
    $catalogVersionCountry = array(
      'GT' => array(
        'nombre' => 'Guatemala',
      ),
      'MX' => array(
        'nombre' => 'Mexico',
      ),
    );

    $appPaisVersionName = $catalogVersionCountry[$appPaisVersion]['nombre'] ?? 'Guatemala';

    list(
      $nombre1,        // 0
      $nombre2,        // 1
      $nombre3,        // 2
      $apellido1,      // 3
      $apellido2,      // 4
      $apellido3,      // 5
      $profesion,      // 6
      $email,          // 7
      $conyuge,        // 8
      $fechaNacimiento, // 9
      $dirNacimiento,  // 10
      $edad,           // 11
      $numeroDoc,      // 12
      $numeroNIT,      // 13
      $afiliacionIGSS, // 14
      $reside,         // 15
      $dirVivienda,    // 16
      $refVivienda,    // 17
      $representante,  // 18
      $refNombre1,     // 19
      $ref1,           // 20
      $refNombre2,     // 21
      $ref2,           // 22
      $refNombre3,     // 23
      $ref3,           // 24
      $tel1,           // 25
      $tel2,           // 26
      $telConyuge,     // 27
      $zonaVivienda,   // 28
      $barrioVivienda, // 29
      $hijos,          // 30
      $dependencia,    // 31
      $codInterno,     // 32
      $observaciones,  // 33
      $ref_dir1,       // 34
      $ref_refDir1,    // 35
      $ref_dir2,       // 36
      $ref_refDir2,    // 37
      $ref_dir3,       // 38
      $ref_refDir3,    // 39
      $pep_entidad,    // 40
      $pep_puesto,     // 41
      $pep_origen_riqueza_otro, // 42
      $pariente_pep_primer_apellido, // 43
      $pariente_pep_segundo_apellido, // 44
      $pariente_pep_apellido_casada, // 45
      $pariente_pep_primer_nombre, // 46
      $pariente_pep_segundo_nombre, // 47
      $pariente_pep_otros_nombres, // 48
      $pariente_pep_entidad, // 49
      $pariente_pep_puesto, // 50
      $asociado_pep_otro_motivo, // 51
      $asociado_pep_primer_apellido, // 52
      $asociado_pep_segundo_apellido, // 53
      $asociado_pep_apellido_casada, // 54
      $asociado_pep_primer_nombre, // 55
      $asociado_pep_segundo_nombre, // 56
      $asociado_pep_otros_nombres, // 57
      $asociado_pep_entidad, // 58
      $asociado_pep_puesto,  // 59
      $fechaingreso // 60
    ) = $inputs;

    /**
     * RECOLECCION DE DATOS DE SELECTS
     * [`genero`,`estcivil`,`origen`,`paisnac`,`depnac`,`muninac`, `docextend`,`tipodoc`,`tipoidentri`,`nacionalidad`,
     *  `condicion`, `depdom`,`munidom`,`actpropio`,`actcalidad`,`otranacionalidad`,`etnia`,`religion`,`educacion`,`relinsti`,
     *  `agencidplus`,`refp1`,`refp2`,`refp3`,`actividadEconomicaSat`,`pep_pais`,
     * `pariente_pep_parentesco`,`pariente_pep_sexo`,`pariente_pep_condicion`,`pariente_pep_pais`,
     * `asociado_pep_motivo`,`asociado_pep_sexo`,`asociado_pep_condicion`,`asociado_pep_pais`
     * `condicionMigratoria`,'paisdom' ], 
     */

    list(
      $genero,           // 0
      $estcivil,         // 1
      $origen,           // 2
      $paisnac,          // 3
      $depnac,           // 4
      $muninac,          // 5
      $docextend,        // 6
      $tipodoc,          // 7
      $tipoIdentiTributaria, // 8
      $nacionalidad,     // 9
      $condicion,        // 10
      $depadom,          // 11
      $munidom,          // 12
      $actpropio,        // 13
      $actcalidad,       // 14
      $otranacionalidad, // 15
      $etnia,            // 16
      $religion,         // 17
      $educacion,        // 18
      $relinsti,         // 19
      $agenciaID,        // 20
      $refp1,            // 21
      $refp2,            // 22
      $refp3,            // 23
      $actividadEconomicaSat, // 24
      $pep_pais,          // 25
      $pariente_pep_parentesco, // 26
      $pariente_pep_sexo, // 27
      $pariente_pep_condicion, // 28
      $pariente_pep_pais, // 29
      $asociado_pep_motivo, // 30
      $asociado_pep_sexo, // 31
      $asociado_pep_condicion, // 32
      $asociado_pep_pais, // 33
      $condicionMigratoria, // 34
      $paisdom, // 35
    ) = $selects;

    /**
     * RECOLECCION DE DATOS DE RADIOBUTTONS
     *  [ `leer`,`escribir`,`firma`,`pep`,`cpe`,`tipo_cliente`,`pariente_pep`,`asociado_pep`   ],
     */
    list($leer, $escribir, $firma, $pep, $cpe, $tipo_cliente, $pariente_pep, $asociado_pep, $esEmpleado) = $radios;

    /**
     * RECOLECCION DE DATOS DE OTROS
     *  [ `idDraft` ]
     */

    $archivo = json_decode($_POST["archivo"], true);
    list($idDraft, $origenesRiqueza) = $archivo;

    // Log::info("datos de archivo", $archivo);

    //+++++++++++++++++++++++++++++++++++++++++
    //+++++++++++++++++++++++++++++++++++++++++
    $showmensaje = false;
    try {
      $validar = validar_campos_plus([
        [$nombre1, "", 'Debe ingresar un primer nombre', 1],
        [$apellido1, "", 'Debe ingresar un primer apellido', 1],
        [$genero, "0", 'Debe seleccionar un genero', 1],
        [$estcivil, "0", 'Debe seleccionar un estado civil', 1],
        [$profesion, "", 'Debe ingresar una profesión', 1],
      ]);
      if ($validar[2]) {
        $showmensaje = true;
        throw new Exception($validar[0]);
      }

      // Validación del correo electrónico (si se ingresa)
      if (!empty($email)) {
        $validar = validar_campos_plus([
          [
            $email,
            '/^(?:[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?$/',
            'Debe ingresar un correo electronico valido, sino tiene correo deje el campo vacio',
            4
          ],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      // Otras validaciones (fecha de nacimiento, documento, país, etc.)
      $validar = validar_campos_plus([
        [$fechaNacimiento, "", 'Debe ingresar una fecha de nacimiento', 1],
        [$edad, "1", 'Debe ingresar una fecha de nacimiento valida', 2],
        [$origen, "0", 'Debe seleccionar un origen valido', 1],
        [$paisnac, "0", 'Debe seleccionar un país', 1],
        [$depnac, "0", 'Debe seleccionar un departamento', (($paisnac != $appPaisVersion) ? 5 : 1)],
        [$muninac, "0", 'Debe seleccionar un municipio', (($paisnac != $appPaisVersion) ? 5 : 1)],
        [$docextend, "0", 'Debe seleccionar un lugar de extension de documento', 1],
        [$tipodoc, "0", 'Debe seleccionar un tipo de documento', 1],
        [$numeroDoc, "", 'Debe ingresar un numero de documento de identificación', 1],
        // [
        //   $numeroDoc,
        //   (($docextend != "Guatemala") ? '/^[A-Z0-9]{6,15}$/' : '/^(?:[0-9]{4}[-\s]?[0-9]{5}[-\s]?[0-9]{4}|[0-9]{13})$/'),
        //   'Debe ingresar un numero de documento de identificación valido',
        //   4
        // ],
        [
          $tipoIdentiTributaria,
          "0",
          'Debe seleccionar un tipo de identificacion tributaria',
          (($docextend == "4") ? 1 : 5) // Si el doc es de Guatemala, es obligatorio
        ],
      ]);
      if ($validar[2]) {
        // echo json_encode([$validar[0], $validar[1]]);
        // return;
        $showmensaje = true;
        throw new Exception($validar[0]);
      }

      /**
       * VALIDACION DEL NUMERO DE IDENTIFICACION SEGUN TIPO
       *  USAR LA CLASE IDENTIFICACION
       */

      $tipoIdentificacionObject = Identificacion::obtenerPorCodigo($tipodoc);
      if ($tipoIdentificacionObject) {
        $esValido = Identificacion::validarNumeroPorId($tipoIdentificacionObject['id'], $numeroDoc);
        if (!$esValido) {
          $showmensaje = true;
          throw new Exception('El número de identificación no es válido para el tipo seleccionado');
        }
      } else {
        $showmensaje = true;
        throw new Exception('Tipo de identificación no reconocido');
      }


      /**
       * VALIDACION PARA NIT O CUI
       */
      if ($tipoIdentiTributaria == "NIT") {
        $validar = validar_campos_plus([
          [$numeroNIT, "", 'Debe ingresar un numero de identificacion tributaria', (($docextend == "4") ? 1 : 5)],
          // [
          //   $numeroNIT,
          //   "/^(?:\d{6,7}-?\d{1}|\d{8,9}-?\d{1}-?\d{2}|\d{12}-?\d{1}-?\d{2})[A-Z]?$/",
          //   'Debe ingresar un numero de documento de identificación tributaria valido',
          //   (($docextend == "4") ? 4 : 5)
          // ],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      if ($tipoIdentiTributaria == "CUI") {
        $validar = validar_campos_plus([
          [$numeroNIT, "", 'Debe ingresar un numero de CUI', (($docextend != "GT") ? 5 : 1)],
          [
            $numeroNIT,
            "/^(?:[0-9]{4}[-\s]?[0-9]{5}[-\s]?[0-9]{4}|[0-9]{13})$/",
            'Debe ingresar un numero de CUI valido',
            (($docextend == "4") ? 4 : 5)
          ],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      // Otras validaciones (nacionalidad, vivienda, teléfonos, etc.)
      $validar = validar_campos_plus([
        [$nacionalidad, "0", 'Debe seleccionar una nacionalidad', 1],
        [$condicion, "0", 'Debe seleccionar una condición de vivienda', 1],
        [$reside, "/^(1000|1[0-9]{3}|[2-9][0-9]{3})$/", 'Debe digitar una año de residencia', 4],
        [$depadom, "0", 'Debe seleccionar un departamento de domicilio', (($paisdom != $appPaisVersion) ? 5 : 1)],
        [$munidom, "0", 'Debe seleccionar un municipio de domicilio', (($paisdom != $appPaisVersion) ? 5 : 1)],
        [$tel1, '/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})$/', 'Digite un numero de telefono 1', 4],
        [$tel2, "/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})?$/", 'Digite un numero de telefono valido en telefono 2', 4],
        [$actpropio, "0", 'Debe seleccionar un tipo de actuación', 1],
      ]);
      if ($validar[2]) {
        $showmensaje = true;
        throw new Exception($validar[0]);
      }

      // Si actúa en nombre propio, validar representante
      if ($actpropio == "2") {
        $validar = validar_campos_plus([
          [$representante, "", 'Debe digitar un nombre de representante', 1],
          [$actcalidad, "", 'Debe seleccionar una calidad de actuación', 1],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      // Validaciones adicionales para referencias y radios
      $validar = validar_campos_plus([
        [$etnia, "0", 'Debe seleccionar un tipo de etnia', 1],
        [$religion, "0", 'Debe seleccionar un tipo de religion', 1],
        [$educacion, "0", 'Debe seleccionar un tipo de educación', 1],
        [$relinsti, "0", 'Debe seleccionar un tipo de relacion institucional', 1],
        [$refNombre1, "", 'Debe digitar ref. nombre 1', 1],
        [$ref1, "", 'Debe digitar ref. telefono 1', 1],
        [$ref1, '/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})$/', 'Debe digitar ref. telefono 1', 4],
        [$ref2, "/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})?$/", 'Debe digitar ref. telefono 2 válido', 4],
        [$ref3, "/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})?$/", 'Debe digitar ref. telefono 3 válido', 4],
        [$leer, "/^(Si|No)$/i", 'Debe seleccionar si sabe leer o no', 4],
        [$escribir, "/^(Si|No)$/i", 'Debe seleccionar si sabe escribir o no', 4],
        [$firma, "/^(Si|No)$/i", 'Debe seleccionar si sabe firmar o no', 4],
        [$pep, "/^(Si|No)$/i", 'Debe seleccionar si es cliente es PEP o no', 4],
        [$cpe, "/^(Si|No)$/i", 'Debe seleccionar si es cliente es CPE o no', 4],
      ]);
      if ($validar[2]) {
        $showmensaje = true;
        throw new Exception($validar[0]);
      }

      /**
       * VALIDACIONES DE PEP
       */
      if ($pep == "Si") {
        $validar = validar_campos_plus([
          [$pep_entidad, "", 'Debe digitar la entidad donde labora el PEP', 1],
          [$pep_puesto, "", 'Debe digitar el puesto que desempeña el PEP', 1],
          [$pep_pais, "", 'Debe seleccionar un país', 1],
        ]);

        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }

        if (empty($origenesRiqueza)) {
          $showmensaje = true;
          throw new Exception("Debe seleccionar al menos un origen de riqueza");
        }

        if (in_array(8, $origenesRiqueza)) {
          if (empty($pep_origen_riqueza_otro)) {
            $showmensaje = true;
            throw new Exception("Debe especificar el origen de riqueza 'Otro'");
          }
        }
      }

      /**
       * VALIDACIONES DE PEP PARA PARIENTES
       */
      if ($pariente_pep == "Si") {
        $validar = validar_campos_plus([
          [$pariente_pep_primer_apellido, "", 'Debe digitar el primer apellido del pariente PEP', 1],
          [$pariente_pep_primer_nombre, "", 'Debe digitar el primer nombre del pariente PEP', 1],
          [$pariente_pep_parentesco, "", 'Debe seleccionar el parentesco del pariente PEP', 1],
          [$pariente_pep_sexo, "", 'Debe seleccionar el sexo del pariente PEP', 1],
          [$pariente_pep_condicion, "", 'Debe seleccionar la condición del pariente PEP', 1],
          [$pariente_pep_entidad, "", 'Debe digitar la entidad donde labora el pariente PEP', 1],
          [$pariente_pep_puesto, "", 'Debe digitar el puesto que desempeña el pariente PEP', 1],
          [$pariente_pep_pais, "", 'Debe seleccionar un país de la entidad donde labora el pariente PEP', 1],
        ]);

        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      /**
       * VALIDACIONES DE PEP PARA ASOCIADOS
       */
      if ($asociado_pep == "Si") {
        $validar = validar_campos_plus([
          [$asociado_pep_primer_apellido, "", 'Debe digitar el primer apellido del asociado PEP', 1],
          [$asociado_pep_primer_nombre, "", 'Debe digitar el primer nombre del asociado PEP', 1],
          [$asociado_pep_motivo, "", 'Debe seleccionar el motivo por el cual es asociado PEP', 1],
          [$asociado_pep_sexo, "", 'Debe seleccionar el sexo del asociado PEP', 1],
          [$asociado_pep_condicion, "", 'Debe seleccionar la condición del asociado PEP', 1],
          [$asociado_pep_entidad, "", 'Debe digitar la entidad donde labora el asociado PEP', 1],
          [$asociado_pep_puesto, "", 'Debe digitar el puesto que desempeña el asociado PEP', 1],
          [$asociado_pep_pais, "", 'Debe seleccionar un país de la entidad donde labora el asociado PEP', 1],
        ]);

        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }
      // $showmensaje = true;
      // throw new Exception("Hasta aca tamos bien");

      // Transformar nombres a mayúsculas y generar short_name y compl_name
      $short_name = concatenar_nombre([$nombre1, $nombre2, $nombre3], [$apellido1, $apellido2, $apellido3], " ");
      $compl_name = concatenar_nombre([$apellido1, $apellido2, $apellido3], [$nombre1, $nombre2, $nombre3], ", ");
      $nombre1 = mb_strtoupper($nombre1, 'UTF-8');
      $nombre2 = mb_strtoupper($nombre2, 'UTF-8');
      $nombre3 = mb_strtoupper($nombre3, 'UTF-8');
      $apellido1 = mb_strtoupper($apellido1, 'UTF-8');
      $apellido2 = mb_strtoupper($apellido2, 'UTF-8');
      $apellido3 = mb_strtoupper($apellido3, 'UTF-8');

      $depnac = (!in_array($paisnac, array_keys($catalogVersionCountry))) ? '' : $depnac;
      $depadom = (!in_array($paisdom, array_keys($catalogVersionCountry))) ? '' : $depadom;
      $muninac = (!in_array($paisnac, array_keys($catalogVersionCountry))) ? NULL : $muninac;
      $munidom = (!in_array($paisdom, array_keys($catalogVersionCountry))) ? NULL : $munidom;

      // $docextend = ($docextend != $appPaisVersionName) ? "" : $docextend;
      // $numeroNIT = ($docextend != $appPaisVersionName) ? "" : $numeroNIT;

      /**
       * VALIDACION DE CONDICION MIGRATORIA
       */
      if ($paisnac != $appPaisVersion) {
        $validar = validar_campos_plus([
          [$condicionMigratoria, "", 'Debe seleccionar una condición migratoria', 1],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      } else {
        $condicionMigratoria = "";
      }

      $database->openConnection();

      $verificacion = $database->selectColumns("tb_cliente", ['idcod_cliente'], 'no_identifica = ?', [$numeroDoc]);
      if (!empty($verificacion)) {
        $showmensaje = true;
        throw new Exception("DPI ya registrado");
      }

      // GENERAR EL CODIGO DEL CLIENTE
      $codgen = cli_gencodclientePDO($agenciaID, $database);

      $database->beginTransaction();

      // PREPARACIÓN DEL ARRAY PARA TB_CLIENTE CON EL NUEVO ORDEN
      $data = array(
        'idcod_cliente'    => $codgen,
        'id_tipoCliente'   => 'NATURAL',
        'agencia'          => $agenciaID,
        'primer_name'      => $nombre1,
        'segundo_name'     => $nombre2,
        'tercer_name'      => $nombre3,
        'primer_last'      => $apellido1,
        'segundo_last'     => $apellido2,
        'casada_last'      => $apellido3,
        'short_name'       => $short_name,
        'compl_name'       => $compl_name,
        'date_birth'       => $fechaNacimiento,
        'genero'           => $genero,
        'estado_civil'     => $estcivil,
        'origen'           => $origen,
        'pais_nacio'       => $paisnac,
        'depa_nacio'       => $depnac,
        'muni_nacio'       => '-',
        'id_muni_nacio'    => $muninac,
        'aldea'            => $dirNacimiento,
        'type_doc'         => $tipodoc,
        'no_identifica'    => $numeroDoc,
        'pais_extiende'    => $docextend,
        'nacionalidad'     => $nacionalidad,
        'depa_extiende'    => $depnac,
        'muni_extiende'    => '-',
        'id_muni_extiende' => $muninac,
        'otra_nacion'      => $otranacionalidad,
        'identi_tribu'     => $tipoIdentiTributaria,
        'no_tributaria'    => ($numeroNIT == "") ? '-' : $numeroNIT,
        'no_igss'          => $afiliacionIGSS,
        'profesion'        => $profesion,
        'Direccion'        => $dirVivienda,
        'depa_reside'      => $depadom,
        'muni_reside'      => '-',
        'id_muni_reside'   => $munidom,
        'aldea_reside'     => $refVivienda,
        'tel_no1'          => $tel1,
        'tel_no2'          => $tel2,
        'area'             => '',
        'ano_reside'       => $reside,
        'vivienda_Condi'   => $condicion,
        'email'            => $email,
        'relac_propo'      => $relinsti,
        'monto_ingre'      => '0',
        'actu_Propio'      => $actpropio,
        'representante_name' => ($actpropio == "2") ? $representante : ' ',
        'repre_calidad'    => ($actpropio == "2") ? $actcalidad : ' ',
        'id_religion'      => $religion,
        'leer'             => $leer,
        'escribir'         => $escribir,
        'firma'            => $firma,
        'cargo_grupo'      => '',
        'educa'            => $educacion,
        'idioma'           => $etnia,
        'Rel_insti'        => $relinsti,
        'datos_Adicionales' => '',
        'Conyuge'          => $conyuge,
        'telconyuge'       => $telConyuge,
        'zona'             => $zonaVivienda,
        'barrio'           => $barrioVivienda,
        'hijos'            => ($hijos == "") ? 0 : $hijos,
        'dependencia'      => ($dependencia == "") ? 0 : $dependencia,
        'control_interno'  => ($codInterno == "") ? " " : $codInterno,
        // Referencias
        'Nomb_Ref1'        => $refNombre1,
        'Tel_Ref1'         => $ref1,
        'Nomb_Ref2'        => $refNombre2,
        'Tel_Ref2'         => $ref2,
        'Nomb_Ref3'        => $refNombre3,
        'Tel_Ref3'         => $ref3,
        'PEP'              => $pep,
        'CPE'              => $cpe,
        'estado'           => '1',
        'fecha_alta'       => $fechaingreso,
        'created_by'       => $idusuario,
        'fecha_mod'        => $hoy2,
        'observaciones'    => $observaciones,
        'url_img'          => '',
        'fiador'           => $tipo_cliente,
        'fecha_actualizacion' => $fechaingreso, // cuando se crea el cliente, se toma la fecha actual como la fecha ultima de actualizacion
      );

      $database->insert('tb_cliente', $data);

      $atributos = [
        // Referencia 1
        ['id_atributo' => 1, 'valor' => $refp1], // reiParentesco1
        ['id_atributo' => 4, 'valor' => $ref_dir1],  // direccionParentesco1
        ['id_atributo' => 7, 'valor' => $ref_refDir1],  // reiDireccionParentesco1

        // Referencia 2
        ['id_atributo' => 2, 'valor' => $refp2], // reiParentesco2
        ['id_atributo' => 5, 'valor' => $ref_dir2],  // direccionParentesco2
        ['id_atributo' => 8, 'valor' => $ref_refDir2],  // reiDireccionParentesco2

        // Referencia 3
        ['id_atributo' => 3, 'valor' => $refp3], // reiParentesco3
        ['id_atributo' => 6, 'valor' => $ref_dir3],  // direccionParentesco3
        ['id_atributo' => 9, 'valor' => $ref_refDir3],  // reiDireccionParentesco3

        // Actividad Económica SAT
        ['id_atributo' => 10, 'valor' => $actividadEconomicaSat], // actividadEconomicaSat

        // Pariente PEP
        ['id_atributo' => 11, 'valor' => $pariente_pep], // pariente_pep

        // Asociado PEP
        ['id_atributo' => 12, 'valor' => $asociado_pep], // asociado_pep

        // Condición migratoria
        ['id_atributo' => 13, 'valor' => $condicionMigratoria], // condicion_migratoria

        // Es empleado
        ['id_atributo' => 14, 'valor' => $esEmpleado], // es_empleado
      ];

      foreach ($atributos as $attr) {
        // Validar: solo se inserta si el valor no es vacío
        if (trim($attr['valor']) != "") {
          $database->insert('tb_cliente_atributo', [
            'id_cliente' => $codgen,
            'id_atributo' => $attr['id_atributo'],
            'valor' => $attr['valor']
          ]);
        }
      }

      /**
       * MANEJO DE DATOS DE PEP
       */

      if ($pep == "Si") {
        // Primero, eliminar cualquier dato PEP existente para este cliente (en caso de edición)
        // $database->delete('tb_cliente_pep', 'id_cliente = ?', [$codgen]);

        // Insertar los nuevos datos PEP
        $origenesRiquezaStr = implode(",", $origenesRiqueza);
        $idDatosPep = $database->insert('cli_datos_pep', [
          'id_cliente' => $codgen,
          'entidad' => $pep_entidad,
          'puesto' => $pep_puesto,
          'paisEntidad' => $pep_pais,
          'otroOrigen' => (in_array(8, $origenesRiqueza)) ? $pep_origen_riqueza_otro : ''
        ]);

        //insertar los origenes en la tabla de union cli_origenes_riqueza
        foreach ($origenesRiqueza as $origenId) {
          $database->insert('cli_origenes_riqueza', [
            'id_pep' => $idDatosPep,
            'id_origen' => $origenId
          ]);
        }
      }

      /**
       * MANEJO DE DATOS DE PEP PARA PARIENTES
       */
      if ($pariente_pep == "Si") {
        $database->insert('cli_complementos_pep', [
          'id_cliente' => $codgen,
          'tipo' => 'pariente',
          'parentesco' => $pariente_pep_parentesco,
          'primerApellido' => $pariente_pep_primer_apellido,
          'segundoApellido' => $pariente_pep_segundo_apellido,
          'apellidoCasada' => $pariente_pep_apellido_casada,
          'primerNombre' => $pariente_pep_primer_nombre,
          'segundoNombre' => $pariente_pep_segundo_nombre,
          'otrosNombres' => $pariente_pep_otros_nombres,
          'sexo' => $pariente_pep_sexo,
          'condicion' => $pariente_pep_condicion,
          'entidad' => $pariente_pep_entidad,
          'puesto' => $pariente_pep_puesto,
          'pais' => $pariente_pep_pais,
          'estado' => 1,
          'created_by' => $idusuario,
          'created_at' => $hoy2,
        ]);
      }

      /**
       * MANEJO DE DATOS DE PEP PARA ASOCIADOS
       */
      if ($asociado_pep == "Si") {
        if ($asociado_pep_motivo == 5 && empty($asociado_pep_otro_motivo)) {
          $showmensaje = true;
          throw new Exception("Debe especificar el motivo 'Otro' por el cual es asociado PEP");
        }

        $database->insert('cli_complementos_pep', [
          'id_cliente' => $codgen,
          'tipo' => 'asociado',
          'motivoAsociacion' => $asociado_pep_motivo,
          'detalleOtro' => ($asociado_pep_motivo == 5) ? $asociado_pep_otro_motivo : '',
          'primerApellido' => $asociado_pep_primer_apellido,
          'segundoApellido' => $asociado_pep_segundo_apellido,
          'apellidoCasada' => $asociado_pep_apellido_casada,
          'primerNombre' => $asociado_pep_primer_nombre,
          'segundoNombre' => $asociado_pep_segundo_nombre,
          'otrosNombres' => $asociado_pep_otros_nombres,
          'sexo' => $asociado_pep_sexo,
          'condicion' => $asociado_pep_condicion,
          'entidad' => $asociado_pep_entidad,
          'puesto' => $asociado_pep_puesto,
          'pais' => $asociado_pep_pais,
          'estado' => 1,
          'created_by' => $idusuario,
          'created_at' => $hoy2,
        ]);
      }

      /**
       * MANEJO DE LA IMAGEN
       */

      $folderInstitucion = (new Agencia($idagencia))->institucion?->getFolderInstitucion();

      if ($folderInstitucion === null) {
        $showmensaje = true;
        throw new Exception("No se pudo obtener la carpeta.");
      }

      if (isset($_FILES['fileimg']) && is_uploaded_file($_FILES['fileimg']['tmp_name'])) {
        $ccodcli = $codgen; // Usamos el código del cliente recién insertado
        $salida = "../../../"; // Ruta base
        $entrada = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/" . $ccodcli;
        $rutaEnServidor = $salida . $entrada;
        $extensiones = ["jpg", "jpeg", "pjpeg", "png", "gif"];

        // Eliminar archivos existentes con las extensiones permitidas
        foreach ($extensiones as $ext) {
          $filePath = $rutaEnServidor . "/" . $ccodcli . "." . $ext;
          if (file_exists($filePath)) {
            unlink($filePath);
          }
        }

        // Crear la carpeta si no existe
        if (!is_dir($rutaEnServidor)) {
          mkdir($rutaEnServidor, 0777, true);
        }

        $rutaTemporal = $_FILES['fileimg']['tmp_name'];
        $info = pathinfo($_FILES['fileimg']['name']);
        $nombreImagen = $ccodcli;
        $nomimagen = '/' . $nombreImagen . "." . $info['extension'];
        $rutaDestino = $rutaEnServidor . $nomimagen;

        // Verificar que el tipo de archivo sea permitido
        $validTypes = ["image/jpeg", "image/jpg", "image/png", "image/gif"];
        if (!in_array($_FILES["fileimg"]["type"], $validTypes)) {
          $showmensaje = true;
          throw new Exception('La extensión de la imagen no es permitida, ingrese una imagen jpeg, jpg, png o gif');
        }

        // Redimensionar la imagen
        $maxWidth = 800;
        $maxHeight = 600;
        list($origWidth, $origHeight) = getimagesize($rutaTemporal);
        $ratio = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1);
        $newWidth = intval($origWidth * $ratio);
        $newHeight = intval($origHeight * $ratio);

        // Crear la imagen a partir del archivo temporal
        switch ($_FILES["fileimg"]["type"]) {
          case "image/jpeg":
          case "image/jpg":
            $srcImage = imagecreatefromjpeg($rutaTemporal);
            break;
          case "image/png":
            $srcImage = imagecreatefrompng($rutaTemporal);
            break;
          case "image/gif":
            $srcImage = imagecreatefromgif($rutaTemporal);
            break;
        }
        $dstImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($dstImage, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        // Guardar la imagen optimizada
        $saveSuccess = false;
        switch ($_FILES["fileimg"]["type"]) {
          case "image/jpeg":
          case "image/jpg":
            $saveSuccess = imagejpeg($dstImage, $rutaDestino, 75);
            break;
          case "image/png":
            $saveSuccess = imagepng($dstImage, $rutaDestino);
            break;
          case "image/gif":
            $saveSuccess = imagegif($dstImage, $rutaDestino);
            break;
        }
        imagedestroy($srcImage);
        imagedestroy($dstImage);

        if (!$saveSuccess) {
          $showmensaje = true;
          throw new Exception('Fallo al guardar la imagen optimizada');
        }

        $database->update('tb_cliente', ['url_img' => $entrada . $nomimagen], 'idcod_cliente = ?', [$ccodcli]);
      } else {
        //aqui verificar si hay alguno en draft y copiarlo
        if ($idDraft != 0) {
          try {

            // $database->openConnection();

            $imagenDraft = $database->selectColumns('tb_clientes_draft', ['img_cliente'], 'id=?', [$idDraft]);
            if (!empty($imagenDraft) && $imagenDraft[0]['img_cliente'] != '') {
              $salida = "../../../";
              //verificar si existe la imagen
              if (!file_exists($salida . $imagenDraft[0]['img_cliente'])) {
                throw new Exception("La imagen del draft no existe.");
              }

              $extension = pathinfo($imagenDraft[0]['img_cliente'], PATHINFO_EXTENSION);
              $nuevoNombre =  $codgen . "." . $extension;

              $entrada = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/" . $codgen;
              $rutaEnServidor = $salida . $entrada;
              $rutaOrigen = $salida . $imagenDraft[0]['img_cliente'];
              // Copiar la imagen del draft a la ubicación final
              $rutaDestino = $rutaEnServidor . '/' . $nuevoNombre;
              if (!is_dir(dirname($rutaDestino))) {
                mkdir(dirname($rutaDestino), 0777, true); // Crea la carpeta destino si no existe
              }
              rename($rutaOrigen, $rutaDestino);

              $database->update('tb_cliente', ['url_img' => $entrada . '/' . $nuevoNombre], 'idcod_cliente=?', [$codgen]);
            }
          } catch (Throwable $e) {
            // $database->closeConnection();
            Log::error("Error al procesar el draft: " . $e->getMessage());
            // $conexion->rollback();
            // echo json_encode(["Error: " . $e->getMessage(), '0']);
          }
          $database->update('tb_clientes_draft', ['estado' => 0], 'id=?', [$idDraft]);
        }
      }

      $database->commit();

      $status = 1;
      $mensaje = "Cliente ingresado correctamente, codigo: " . $codgen;
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status
    ]);
    //+++++++++++++++++++++++++++++++++++++++++
    //+++++++++++++++++++++++++++++++++++++++++
    break;
  case 'create_cliente_draft':
    $inputs = json_decode($_POST["inputs"], true);
    $selects = json_decode($_POST["selects"], true);
    $radios = json_decode($_POST["radios"], true);
    $otrosDatos = json_decode($_POST["archivo"], true);
    // Log::info("create_cliente_draft: " . json_encode($_POST));
    // [`nom1`,`nom2`,`nom3`,`ape1`,`ape2`,`ape3`,`profesion`,`email`,`conyugue`,
    // `fechanacimiento`,`dirnac`,`edad`,`numberdoc`,`numbernit`,`afiliggs`,
    // `reside`,`dirviv`,`refviv`,`representante`,`refn1`,`ref1`,`refn2`,`ref2`,
    // `refn3`,`ref3`,`tel1`,`tel2`,`telconyuge`,`zonaviv`,`barrioviv`,
    // `hijos`,`dependencia`,`codinterno`,`observaciones`,
    // `refd1`,`refdir1`,`refd2`,`refdir2`,`refd3`,`refdir3`], [
    // `genero`,`estcivil`,`origen`,`paisnac`,`depnac`,`muninac`,
    // `docextend`,`tipodoc`,`tipoidentri`,`nacionalidad`,`condicion`,
    // `depdom`,`munidom`,`actpropio`,`actcalidad`,`otranacionalidad`,
    // `etnia`,`religion`,`educacion`,`relinsti`,`agencidplus`,`refp1`,`refp2`,`refp3`], [
    // `leer`,`escribir`,`firma`,`pep`,`cpe`,`tipo_cliente`], 
    // `create_cliente_draft`, `0`, [])
    list(
      $nombre1,
      $nombre2,
      $nombre3,
      $apellido1,
      $apellido2,
      $apellido3,
      $profesion,
      $email,
      $conyuge,
      $fechaNacimiento,
      $dirNacimiento,
      $edad,
      $numeroDoc,
      $numeroNIT,
      $afiliacionIGSS,
      $reside,
      $dirVivienda,
      $refVivienda,
      $representante,
      $refNombre1,
      $ref1,
      $refNombre2,
      $ref2,
      $refNombre3,
      $ref3,
      $tel1,
      $tel2,
      $telConyuge,
      $zonaVivienda,
      $barrioVivienda,
      $hijos,
      $dependencia,
      $codInterno,
      $observaciones,
      $refDir1,
      $refDir2,
      $refDir3
    ) = $inputs;
    list(
      $genero,
      $estcivil,
      $origen,
      $paisnac,
      $depnac,
      $muninac,
      $docextend,
      $tipodoc,
      $tipoIdentiTributaria,
      $nacionalidad,
      $condicion,
      $depadom,
      $munidom,
      $actpropio,
      $actcalidad,
      $otranacionalidad,
      $etnia,
      $religion,
      $educacion,
      $relinsti,
      $agencidplus,
      $refp1,
      $refp2,
      $refp3
    ) = $selects;
    list($leer, $escribir, $firma, $pep, $cpe, $tipo_cliente) = $radios;
    list($idDraft) = $otrosDatos;

    // Log::info("create_cliente_draft: " . json_encode($_POST));

    // Log::info("agencia: " . $agencidplus);

    $tb_clientes_draft = [
      'tipoCliente' => 'NATURAL',
      'agencia' => $agencidplus,
      'primer_nombre' => $nombre1,
      'segundo_nombre' => $nombre2,
      'tercer_nombre' => $nombre3,
      'primer_apellido' => $apellido1,
      'segundo_apellido' => $apellido2,
      'casada_apellido' => $apellido3,
      'nombre_corto' => concatenar_nombre([$nombre1, $nombre2, $nombre3], [$apellido1, $apellido2, $apellido3], " "),
      'nombre_completo' => concatenar_nombre([$apellido1, $apellido2, $apellido3], [$nombre1, $nombre2, $nombre3], ", "),
      // 'img_cliente' => '',
      'fecha_nacimiento' => $fechaNacimiento,
      'genero' => $genero,
      'estado_civil' => $estcivil,
      'origen' => $origen,
      'pais_nacio' => $paisnac,
      'depa_nacio' => $depnac,
      'muni_nacio' => $muninac,
      'aldea' => $dirNacimiento,
      'tipo_documento' => $tipodoc,
      'no_identifica' => $numeroDoc,
      'pais_extiende' => $docextend,
      'nacionalidad' => $nacionalidad,
      'depa_extiende' => $depnac,
      'muni_extiende' => $muninac,
      'otra_nacion' => $otranacionalidad,
      'identi_tribu' => $tipoIdentiTributaria,
      'no_tributaria' => $numeroNIT,
      'no_igss' => $afiliacionIGSS,
      'profesion' => $profesion,
      'Direccion' => $dirVivienda,
      'depa_reside' => $depadom,
      'muni_reside' => $munidom,
      'aldea_reside' => $dirVivienda,
      'tel_no1' => $tel1,
      'tel_no2' => $tel2,
      // 'area' => $area,
      'ano_reside' => $reside,
      'vivienda_Condi' => $condicion,
      'email' => $email,
      'relac_propo' => $relinsti,
      // 'monto_ingre' => 0.00,
      'actu_Propio' => $actpropio,
      'representante_name' => ($actpropio == "2") ? $representante : ' ',
      'repre_calidad' => ($actpropio == "2") ? $actcalidad : ' ',
      'id_religion' => $religion,
      'leer' => $leer,
      'escribir' => $escribir,
      'firma' => $firma,
      // 'cargo_grupo' => null,
      'educacion' => $educacion,
      'idioma' => $etnia,
      'relacion_insti' => $relinsti,
      // 'datos_Adicionales' => '',
      'conyuge' => $conyuge,
      'telconyuge' => $telConyuge,
      'zona' => $zonaVivienda,
      'barrio' => $barrioVivienda,
      'hijos' => $hijos,
      'dependencia' => $dependencia,
      'nombre_ref1' => $refNombre1,
      'nombre_ref2' => $refNombre2,
      'nombre_ref3' => $refNombre3,
      'tel_ref1' => $ref1,
      'tel_ref2' => $ref2,
      'tel_ref3' => $ref3,
      'PEP' => $pep,
      'CPE' => $cpe,
      'control_interno' => $codInterno,
      'observaciones' => $observaciones,
      'estado' => '1',
      // 'created_by' => $idusuario,
      // 'created_at' => $hoy2
    ];

    $showmensaje = false;
    try {
      if ($nombre1 == "") {
        $showmensaje = true;
        throw new Exception("El primer nombre es obligatorio para guardar como borrador");
      }

      $database->openConnection();

      $database->beginTransaction();
      // Log::info("idDraft", [$idDraft]);
      if ($idDraft == '0') {
        // Log::info("aki", [$tb_clientes_draft]);
        $tb_clientes_draft['created_by'] = $idusuario;
        $tb_clientes_draft['created_at'] = $hoy2;
        $idRegistro = $database->insert('tb_clientes_draft', $tb_clientes_draft);
      } else {
        $idRegistro = $idDraft;
        $tb_clientes_draft['updated_at'] = $hoy2;

        // Log::info("existente", [$tb_clientes_draft]);
        $database->update('tb_clientes_draft', $tb_clientes_draft, 'id=?', [$idRegistro]);
      }

      // Procesar archivos si existen
      $archivos = [];
      if (isset($_FILES['fileimg']) && is_uploaded_file($_FILES['fileimg']['tmp_name'])) {
        // if (isset($_FILES['files'])) {

        // Log::info("imagen cargada", [$_FILES['fileimg']]);

        $folderInstitucion = (new Agencia($idagencia))->institucion?->getFolderInstitucion();

        if ($folderInstitucion === null) {
          $showmensaje = true;
          throw new Exception("No se pudo obtener la carpeta.");
        }
        // $entrada = "imgcoope.microsystemplus.com/" . $folderprincipal . "/" . $ccodcli;
        $rutaSave = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/clientesdraft";
        $rutaEnServidor = "../../../" . $rutaSave;

        // foreach ($_FILES['fileimg']['name'] as $key => $name) {
        //comprobar si existe la ruta, si no, se crea
        if (!is_dir($rutaEnServidor)) {
          mkdir($rutaEnServidor, 0777, true);
        }
        // Log::info("Procesando archivo", [$key, $name, $_FILES['files']['tmp_name'][$key]]);
        if (!empty($_FILES['fileimg']['tmp_name'])) {
          $tmp_name = $_FILES['fileimg']['tmp_name'];
          $error = $_FILES['fileimg']['error'];
          //   $rutaTemporal = $_FILES['fileimg']['tmp_name'];
          // $info = pathinfo($_FILES['fileimg']['name']);

          // Generar un nombre único para el archivo
          $extension = pathinfo($_FILES['fileimg']['name'], PATHINFO_EXTENSION);
          $nombreImagen = $idRegistro . '_' . date('Ymdhis'); //asignar nuevo nombre
          $nuevo_nombre = $nombreImagen . '.' . $extension;
          $ruta_destino = $rutaEnServidor . '/' . $nuevo_nombre;

          // Mover el archivo a la carpeta de destino
          if ($error == UPLOAD_ERR_OK && move_uploaded_file($tmp_name, $ruta_destino)) {
            // $archivos[$key] = $nuevo_nombre;
            // $cli_garantia['archivo'] = $rutaSave . '/' . $nuevo_nombre;

            $database->update('tb_clientes_draft', ['img_cliente' => $rutaSave . '/' . $nuevo_nombre], 'id=?', [$idRegistro]);
          } else {
            $showmensaje = true;
            throw new Exception("Error al cargar el archivo: " . $name);
          }
        }
        // }
      }

      $database->commit();

      $status = 1;
      $mensaje = "Datos guardados como borrador, busque en la lista de borradores.";
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status
    ]);

    break;
  //actualizacion de funcion de cliente Natural 

  //-----Actualizar el CLiente Natural
  case 'update_cliente_natural':

    if (!isset($_SESSION['id'])) {
      echo json_encode(["Sesion expirada, por favor inicie sesión nuevamente", "0"]);
      return;
    }

    $inputs   = $_POST["inputs"];
    $selects  = $_POST["selects"];
    $radios   = $_POST["radios"];
    $archivo  = $_POST["archivo"];
    $codcliente = $_POST["id"];

    $appPaisVersion = $_ENV['APP_PAIS_VERSION'] ?? 'GT';
    $catalogVersionCountry = array(
      'GT' => array(
        'nombre' => 'Guatemala',
      ),
      'MX' => array(
        'nombre' => 'Mexico',
      ),
    );

    $appPaisVersionName = $catalogVersionCountry[$appPaisVersion]['nombre'] ?? 'Guatemala';
    //+++++++++++++++++++++++++++++++++++++++++++++++++
    //+++++++++++++++++++++++++++++++++++++++++++++++++
    list(
      $nombre1,        // 0
      $nombre2,        // 1
      $nombre3,        // 2
      $apellido1,      // 3
      $apellido2,      // 4
      $apellido3,      // 5
      $profesion,      // 6
      $email,          // 7
      $conyuge,        // 8
      $fechaNacimiento, // 9
      $dirNacimiento,  // 10
      $edad,           // 11
      $numeroDoc,      // 12
      $numeroNIT,      // 13
      $afiliacionIGSS, // 14
      $reside,         // 15
      $dirVivienda,    // 16
      $refVivienda,    // 17
      $representante,  // 18
      $refNombre1,     // 19
      $ref1,           // 20
      $refNombre2,     // 21
      $ref2,           // 22
      $refNombre3,     // 23
      $ref3,           // 24
      $tel1,           // 25
      $tel2,           // 26
      $telConyuge,     // 27
      $zonaVivienda,   // 28
      $barrioVivienda, // 29
      $hijos,          // 30
      $dependencia,    // 31
      $codInterno,     // 32
      $observaciones,  // 33
      $ref_dir1,       // 34
      $ref_refDir1,    // 35
      $ref_dir2,       // 36
      $ref_refDir2,    // 37
      $ref_dir3,       // 38
      $ref_refDir3,     // 39
      $pep_entidad,    // 40
      $pep_puesto,     // 41
      $pep_origen_riqueza_otro, // 42
      $pariente_pep_primer_apellido, // 43
      $pariente_pep_segundo_apellido, // 44
      $pariente_pep_apellido_casada, // 45
      $pariente_pep_primer_nombre, // 46
      $pariente_pep_segundo_nombre, // 47
      $pariente_pep_otros_nombres, // 48
      $pariente_pep_entidad, // 49
      $pariente_pep_puesto, // 50
      $asociado_pep_otro_motivo, // 51
      $asociado_pep_primer_apellido, // 52
      $asociado_pep_segundo_apellido, // 53
      $asociado_pep_apellido_casada, // 54
      $asociado_pep_primer_nombre, // 55
      $asociado_pep_segundo_nombre, // 56
      $asociado_pep_otros_nombres, // 57
      $asociado_pep_entidad, // 58
      $asociado_pep_puesto  // 59
    ) = $inputs;

    /**
     * RECOLECCION DE DATOS DE SELECTS
     * [`genero`,`estcivil`,`origen`,`paisnac`,`depnac`,`muninac`, `docextend`,`tipodoc`,`tipoidentri`,`nacionalidad`,
     *  `condicion`, `depdom`,`munidom`,`actpropio`,`actcalidad`,`otranacionalidad`,`etnia`,`religion`,`educacion`,`relinsti`,
     *  `agencidplus`,`refp1`,`refp2`,`refp3`,`actividadEconomicaSat`,`pep_pais` ], 
     * `pariente_pep_parentesco`,`pariente_pep_sexo`,`pariente_pep_condicion`,`pariente_pep_pais`,
     * `asociado_pep_motivo`,`asociado_pep_sexo`,`asociado_pep_condicion`,`asociado_pep_pais` 
     * `condicionMigratoria`,'paisdom'], 
     */

    list(
      $genero,           // 0
      $estcivil,         // 1
      $origen,           // 2
      $paisnac,          // 3
      $depnac,           // 4
      $muninac,          // 5
      $docextend,        // 6
      $tipodoc,          // 7
      $tipoIdentiTributaria, // 8
      $nacionalidad,     // 9
      $condicion,        // 10
      $depadom,          // 11
      $munidom,          // 12
      $actpropio,        // 13
      $actcalidad,       // 14
      $otranacionalidad, // 15
      $etnia,            // 16
      $religion,         // 17
      $educacion,        // 18
      $relinsti,         // 19
      $agenciaID,        // 20
      $refp1,            // 21
      $refp2,            // 22
      $refp3,            // 23
      $actividadEconomicaSat, // 24
      $pep_pais,          // 25
      $pariente_pep_parentesco, // 26
      $pariente_pep_sexo, // 27
      $pariente_pep_condicion, // 28
      $pariente_pep_pais, // 29
      $asociado_pep_motivo, // 30
      $asociado_pep_sexo, // 31
      $asociado_pep_condicion, // 32
      $asociado_pep_pais, // 33
      $condicionMigratoria, // 34
      $paisdom            // 35
    ) = $selects;

    /**
     * RECOLECCION DE DATOS DE RADIOBUTTONS
     *  [ `leer`,`escribir`,`firma`,`pep`,`cpe`,`tipo_cliente`,`pariente_pep`,`asociado_pep`   ],
     */
    list($leer, $escribir, $firma, $pep, $cpe, $tipo_cliente, $pariente_pep, $asociado_pep, $esEmpleado) = $radios;

    /**
     * RECOLECCION DE DATOS DE OTROS
     *  [ `idDraft` ]
     */

    list($idClienteUpdate) = $archivo;

    // Log::info("archivo", $archivo);

    $origenesRiqueza = $archivo[2] ?? [];

    //+++++++++++++++++++++++++++++++++++++++++
    //+++++++++++++++++++++++++++++++++++++++++
    $showmensaje = false;
    try {
      $dataProcess = [
        'fechaActualizacion' => $_POST['inputs'][60] ?? null,
        'checkFechaActualizacion' => filter_var($_POST['archivo'][1] ?? false, FILTER_VALIDATE_BOOLEAN),
        'codigoCliente' => $_POST['archivo'][0] ?? '',
      ];

      $rules = [
        'checkFechaActualizacion' => 'required|boolean',
        'fechaActualizacion' => 'validate_if:checkFechaActualizacion,true|required|date|before_or_equal:today',
        'codigoCliente' => 'required|string|max_length:50',
      ];

      $validator = Validator::make($dataProcess, $rules);
      if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
      }

      $validar = validar_campos_plus([
        [$nombre1, "", 'Debe ingresar un primer nombre', 1],
        [$apellido1, "", 'Debe ingresar un primer apellido', 1],
        [$genero, "0", 'Debe seleccionar un genero', 1],
        [$estcivil, "0", 'Debe seleccionar un estado civil', 1],
        [$profesion, "", 'Debe ingresar una profesión', 1],
      ]);
      if ($validar[2]) {
        $showmensaje = true;
        throw new Exception($validar[0]);
        // echo json_encode([$validar[0], $validar[1]]);
        // return;
      }

      // Validación del correo electrónico (si se ingresa)
      if (!empty($email)) {
        $validar = validar_campos_plus([
          [
            $email,
            '/^(?:[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})?$/',
            'Debe ingresar un correo electronico valido, sino tiene correo deje el campo vacio',
            4
          ],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
          // echo json_encode([$validar[0], $validar[1]]);
          // return;
        }
      }

      // Otras validaciones (fecha de nacimiento, documento, país, etc.)
      $validar = validar_campos_plus([
        [$fechaNacimiento, "", 'Debe ingresar una fecha de nacimiento', 1],
        [$edad, "1", 'Debe ingresar una fecha de nacimiento valida', 2],
        [$origen, "0", 'Debe seleccionar un origen valido', 1],
        [$paisnac, "0", 'Debe seleccionar un país', 1],
        [$depnac, "0", 'Debe seleccionar un departamento', (($paisnac != $appPaisVersion) ? 5 : 1)],
        [$muninac, "0", 'Debe seleccionar un municipio', (($paisnac != $appPaisVersion) ? 5 : 1)],
        [$docextend, "0", 'Debe seleccionar un lugar de extension de documento', 1],
        [$tipodoc, "0", 'Debe seleccionar un tipo de documento', 1],
        [$numeroDoc, "", 'Debe ingresar un numero de documento de identificación', 1],
        // [
        //   $numeroDoc,
        //   (($docextend != "Guatemala") ? '/^[A-Z0-9]{6,15}$/' : '/^(?:[0-9]{4}[-\s]?[0-9]{5}[-\s]?[0-9]{4}|[0-9]{13})$/'),
        //   'Debe ingresar un numero de documento de identificación valido',
        //   4
        // ],
        [
          $tipoIdentiTributaria,
          "0",
          'Debe seleccionar un tipo de identificacion tributaria',
          (($docextend == "4") ? 1 : 5) // Si el doc es de Guatemala, es obligatorio
        ],
      ]);
      if ($validar[2]) {
        // echo json_encode([$validar[0], $validar[1]]);
        // return;
        $showmensaje = true;
        throw new Exception($validar[0]);
      }


      /**
       * VALIDACION DEL NUMERO DE IDENTIFICACION SEGUN TIPO
       *  USAR LA CLASE IDENTIFICACION
       */

      $tipoIdentificacionObject = Identificacion::obtenerPorCodigo($tipodoc);
      if ($tipoIdentificacionObject) {
        $esValido = Identificacion::validarNumeroPorId($tipoIdentificacionObject['id'], $numeroDoc);
        if (!$esValido) {
          $showmensaje = true;
          throw new Exception('El número de identificación no es válido para el tipo seleccionado');
        }
      } else {
        $showmensaje = true;
        throw new Exception('Tipo de identificación no reconocido');
      }

      /**
       * VALIDACION PARA NIT O CUI
       */
      if ($tipoIdentiTributaria == "NIT") {
        $validar = validar_campos_plus([
          [$numeroNIT, "", 'Debe ingresar un numero de identificacion tributaria', (($docextend == "4") ? 1 : 5)],
          // [
          //   $numeroNIT,
          //   "/^(?:\d{6,7}-?\d{1}|\d{8,9}-?\d{1}-?\d{2}|\d{12}-?\d{1}-?\d{2})[A-Z]?$/",
          //   'Debe ingresar un numero de documento de identificación tributaria valido',
          //   (($docextend == "4") ? 4 : 5)
          // ],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      if ($tipoIdentiTributaria == "CUI") {
        $validar = validar_campos_plus([
          [$numeroNIT, "", 'Debe ingresar un numero de CUI', (($docextend != "GT") ? 5 : 1)],
          [
            $numeroNIT,
            "/^(?:[0-9]{4}[-\s]?[0-9]{5}[-\s]?[0-9]{4}|[0-9]{13})$/",
            'Debe ingresar un numero de CUI valido',
            (($docextend == "4") ? 4 : 5)
          ],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      // Otras validaciones (nacionalidad, vivienda, teléfonos, etc.)
      $validar = validar_campos_plus([
        [$nacionalidad, "0", 'Debe seleccionar una nacionalidad', 1],
        [$condicion, "0", 'Debe seleccionar una condición de vivienda', 1],
        [$reside, "/^(1000|1[0-9]{3}|[2-9][0-9]{3})$/", 'Debe digitar una año de residencia', 4],
        [$depadom, "0", 'Debe seleccionar un departamento de domicilio', (($paisdom != $appPaisVersion) ? 5 : 1)],
        [$munidom, "0", 'Debe seleccionar un municipio de domicilio', (($paisdom != $appPaisVersion) ? 5 : 1)],
        [$tel1, '/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})$/', 'Digite un numero de telefono 1', 4],
        [$tel2, "/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})?$/", 'Digite un numero de telefono valido en telefono 2', 4],
        [$actpropio, "0", 'Debe seleccionar un tipo de actuación', 1],
      ]);
      if ($validar[2]) {
        $showmensaje = true;
        throw new Exception($validar[0]);
      }

      // Si actúa en nombre propio, validar representante
      if ($actpropio == "2") {
        $validar = validar_campos_plus([
          [$representante, "", 'Debe digitar un nombre de representante', 1],
          [$actcalidad, "", 'Debe seleccionar una calidad de actuación', 1],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      // Validaciones adicionales para referencias y radios
      $validar = validar_campos_plus([
        [$etnia, "0", 'Debe seleccionar un tipo de etnia', 1],
        [$religion, "0", 'Debe seleccionar un tipo de religion', 1],
        [$educacion, "0", 'Debe seleccionar un tipo de educación', 1],
        [$relinsti, "0", 'Debe seleccionar un tipo de relacion institucional', 1],
        [$refNombre1, "", 'Debe digitar ref. nombre 1', 1],
        [$ref1, "", 'Debe digitar ref. telefono 1', 1],
        [$ref1, '/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})$/', 'Debe digitar ref. telefono 1', 4],
        [$ref2, "/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})?$/", 'Debe digitar ref. telefono 2 válido', 4],
        [$ref3, "/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})?$/", 'Debe digitar ref. telefono 3 válido', 4],
        [$leer, "/^(Si|No)$/i", 'Debe seleccionar si sabe leer o no', 4],
        [$escribir, "/^(Si|No)$/i", 'Debe seleccionar si sabe escribir o no', 4],
        [$firma, "/^(Si|No)$/i", 'Debe seleccionar si sabe firmar o no', 4],
        [$pep, "/^(Si|No)$/i", 'Debe seleccionar si es cliente es PEP o no', 4],
        [$cpe, "/^(Si|No)$/i", 'Debe seleccionar si es cliente es CPE o no', 4],
      ]);
      if ($validar[2]) {
        $showmensaje = true;
        throw new Exception($validar[0]);
        // echo json_encode([$validar[0], $validar[1]]);
        // return;
      }

      /**
       * VALIDACIONES DE PEP
       */
      if ($pep == "Si") {
        $validar = validar_campos_plus([
          [$pep_entidad, "", 'Debe digitar la entidad donde labora el PEP', 1],
          [$pep_puesto, "", 'Debe digitar el puesto que desempeña el PEP', 1],
          [$pep_pais, "", 'Debe seleccionar un país', 1],
        ]);

        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }

        if (empty($origenesRiqueza)) {
          $showmensaje = true;
          throw new Exception("Debe seleccionar al menos un origen de riqueza");
        }

        if (in_array(8, $origenesRiqueza)) {
          if (empty($pep_origen_riqueza_otro)) {
            $showmensaje = true;
            throw new Exception("Debe especificar el origen de riqueza 'Otro'");
          }
        }
      }


      /**
       * VALIDACIONES DE PEP PARA PARIENTES
       */
      if ($pariente_pep == "Si") {
        $validar = validar_campos_plus([
          [$pariente_pep_primer_apellido, "", 'Debe digitar el primer apellido del pariente PEP', 1],
          [$pariente_pep_primer_nombre, "", 'Debe digitar el primer nombre del pariente PEP', 1],
          [$pariente_pep_parentesco, "", 'Debe seleccionar el parentesco del pariente PEP', 1],
          [$pariente_pep_sexo, "", 'Debe seleccionar el sexo del pariente PEP', 1],
          [$pariente_pep_condicion, "", 'Debe seleccionar la condición del pariente PEP', 1],
          [$pariente_pep_entidad, "", 'Debe digitar la entidad donde labora el pariente PEP', 1],
          [$pariente_pep_puesto, "", 'Debe digitar el puesto que desempeña el pariente PEP', 1],
          [$pariente_pep_pais, "", 'Debe seleccionar un país de la entidad donde labora el pariente PEP', 1],
        ]);

        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      /**
       * VALIDACIONES DE PEP PARA ASOCIADOS
       */
      if ($asociado_pep == "Si") {
        $validar = validar_campos_plus([
          [$asociado_pep_primer_apellido, "", 'Debe digitar el primer apellido del asociado PEP', 1],
          [$asociado_pep_primer_nombre, "", 'Debe digitar el primer nombre del asociado PEP', 1],
          [$asociado_pep_motivo, "", 'Debe seleccionar el motivo por el cual es asociado PEP', 1],
          [$asociado_pep_sexo, "", 'Debe seleccionar el sexo del asociado PEP', 1],
          [$asociado_pep_condicion, "", 'Debe seleccionar la condición del asociado PEP', 1],
          [$asociado_pep_entidad, "", 'Debe digitar la entidad donde labora el asociado PEP', 1],
          [$asociado_pep_puesto, "", 'Debe digitar el puesto que desempeña el asociado PEP', 1],
          [$asociado_pep_pais, "", 'Debe seleccionar un país de la entidad donde labora el asociado PEP', 1],
        ]);

        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      }

      //LEESTO
      // Transformar nombres a mayúsculas y generar short_name y compl_name
      $short_name = concatenar_nombre([$nombre1, $nombre2, $nombre3], [$apellido1, $apellido2, $apellido3], " ");
      $compl_name = concatenar_nombre([$apellido1, $apellido2, $apellido3], [$nombre1, $nombre2, $nombre3], ", ");
      $nombre1 = mb_strtoupper($nombre1, 'UTF-8');
      $nombre2 = mb_strtoupper($nombre2, 'UTF-8');
      $nombre3 = mb_strtoupper($nombre3, 'UTF-8');
      $apellido1 = mb_strtoupper($apellido1, 'UTF-8');
      $apellido2 = mb_strtoupper($apellido2, 'UTF-8');
      $apellido3 = mb_strtoupper($apellido3, 'UTF-8');

      $depnac = (!in_array($paisnac, array_keys($catalogVersionCountry))) ? '' : $depnac;
      $depadom = (!in_array($paisdom, array_keys($catalogVersionCountry))) ? '' : $depadom;
      $muninac = (!in_array($paisnac, array_keys($catalogVersionCountry))) ? NULL : $muninac;
      $munidom = (!in_array($paisdom, array_keys($catalogVersionCountry))) ? NULL : $munidom;

      // $docextend = ($docextend != $appPaisVersionName) ? "" : $docextend;
      // $numeroNIT = ($docextend != $appPaisVersionName) ? "" : $numeroNIT;

      /**
       * VALIDACION DE CONDICION MIGRATORIA
       */
      if ($paisnac != $appPaisVersion) {
        $validar = validar_campos_plus([
          [$condicionMigratoria, "", 'Debe seleccionar una condición migratoria', 1],
        ]);
        if ($validar[2]) {
          $showmensaje = true;
          throw new Exception($validar[0]);
        }
      } else {
        $condicionMigratoria = "";
      }

      $database->openConnection();

      $verificacion = $database->selectColumns("tb_cliente", ['idcod_cliente'], 'no_identifica = ? AND idcod_cliente != ?', [$numeroDoc, $codcliente]);
      if (!empty($verificacion)) {
        $showmensaje = true;
        throw new Exception("DPI ya registrado");
      }

      // GENERAR EL CODIGO DEL CLIENTE
      // $codgen = cli_gencodclientePDO($agenciaID, $database);

      $database->beginTransaction();

      // PREPARACIÓN DEL ARRAY PARA TB_CLIENTE CON EL NUEVO ORDEN
      $data = array(
        // 'idcod_cliente'    => $codgen,
        // 'id_tipoCliente'   => 'NATURAL',
        // 'agencia'          => $agenciaID,
        'primer_name'      => $nombre1,
        'segundo_name'     => $nombre2,
        'tercer_name'      => $nombre3,
        'primer_last'      => $apellido1,
        'segundo_last'     => $apellido2,
        'casada_last'      => $apellido3,
        'short_name'       => $short_name,
        'compl_name'       => $compl_name,
        'date_birth'       => $fechaNacimiento,
        'genero'           => $genero,
        'estado_civil'     => $estcivil,
        'origen'           => $origen,
        'pais_nacio'       => $paisnac,
        'depa_nacio'       => $depnac,
        'muni_nacio'       => '-',
        'id_muni_nacio'    => $muninac,
        'aldea'            => $dirNacimiento,
        'type_doc'         => $tipodoc,
        'no_identifica'    => $numeroDoc,
        'pais_extiende'    => $docextend,
        'nacionalidad'     => $nacionalidad,
        'depa_extiende'    => $depnac,
        'muni_extiende'    => '-',
        'id_muni_extiende' => $muninac,
        'otra_nacion'      => $otranacionalidad,
        'identi_tribu'     => $tipoIdentiTributaria,
        'no_tributaria'    => ($numeroNIT == "") ? '-' : $numeroNIT,
        'no_igss'          => $afiliacionIGSS,
        'profesion'        => $profesion,
        'Direccion'        => $dirVivienda,
        'depa_reside'      => $depadom,
        'muni_reside'      => '-',
        'id_muni_reside'   => $munidom,
        'aldea_reside'     => $refVivienda,
        'tel_no1'          => $tel1,
        'tel_no2'          => $tel2,
        'area'             => '',
        'ano_reside'       => $reside,
        'vivienda_Condi'   => $condicion,
        'email'            => $email,
        'relac_propo'      => $relinsti,
        'monto_ingre'      => '0',
        'actu_Propio'      => $actpropio,
        'representante_name' => ($actpropio == "2") ? $representante : ' ',
        'repre_calidad'    => ($actpropio == "2") ? $actcalidad : ' ',
        'id_religion'      => $religion,
        'leer'             => $leer,
        'escribir'         => $escribir,
        'firma'            => $firma,
        'cargo_grupo'      => '',
        'educa'            => $educacion,
        'idioma'           => $etnia,
        'Rel_insti'        => $relinsti,
        'datos_Adicionales' => '',
        'Conyuge'          => $conyuge,
        'telconyuge'       => $telConyuge,
        'zona'             => $zonaVivienda,
        'barrio'           => $barrioVivienda,
        'hijos'            => ($hijos == "") ? 0 : $hijos,
        'dependencia'      => ($dependencia == "") ? 0 : $dependencia,
        'control_interno'  => ($codInterno == "") ? " " : $codInterno,
        // Referencias
        'Nomb_Ref1'        => $refNombre1,
        'Tel_Ref1'         => $ref1,
        'Nomb_Ref2'        => $refNombre2,
        'Tel_Ref2'         => $ref2,
        'Nomb_Ref3'        => $refNombre3,
        'Tel_Ref3'         => $ref3,
        'PEP'              => $pep,
        'CPE'              => $cpe,
        'updated_by'       => $idusuario,
        'fecha_mod'        => $hoy2,
        'observaciones'    => $observaciones,
        // 'url_img'          => '',
        'fiador'           => $tipo_cliente,
      );

      if ($dataProcess['checkFechaActualizacion']) {
        $data['fecha_actualizacion'] = $dataProcess['fechaActualizacion'];
      }

      $database->update('tb_cliente', $data, 'idcod_cliente = ?', [$codcliente]);

      $atributos = [
        // Referencia 1
        ['id_atributo' => 1, 'valor' => $refp1], // reiParentesco1
        ['id_atributo' => 4, 'valor' => $ref_dir1],  // direccionParentesco1
        ['id_atributo' => 7, 'valor' => $ref_refDir1],  // reiDireccionParentesco1

        // Referencia 2
        ['id_atributo' => 2, 'valor' => $refp2], // reiParentesco2
        ['id_atributo' => 5, 'valor' => $ref_dir2],  // direccionParentesco2
        ['id_atributo' => 8, 'valor' => $ref_refDir2],  // reiDireccionParentesco2

        // Referencia 3
        ['id_atributo' => 3, 'valor' => $refp3], // reiParentesco3
        ['id_atributo' => 6, 'valor' => $ref_dir3],  // direccionParentesco3
        ['id_atributo' => 9, 'valor' => $ref_refDir3],  // reiDireccionParentesco3

        // Actividad Económica SAT
        ['id_atributo' => 10, 'valor' => $actividadEconomicaSat], // actividadEconomicaSat

        // Pariente PEP
        ['id_atributo' => 11, 'valor' => $pariente_pep], // pariente_pep

        // Asociado PEP
        ['id_atributo' => 12, 'valor' => $asociado_pep], // asociado_pep

        // Condición migratoria
        ['id_atributo' => 13, 'valor' => $condicionMigratoria], // condicion_migratoria

        // Es empleado
        ['id_atributo' => 14, 'valor' => $esEmpleado], // es_empleado
      ];

      foreach ($atributos as $attr) {
        // Validar: solo se inserta si el valor no es vacío
        $verificando = $database->selectColumns('tb_cliente_atributo', ['id'], 'id_cliente = ? AND id_atributo = ?', [$codcliente, $attr['id_atributo']]);
        if (!empty($verificando)) {
          /**
           * SI YA EXISTE EL ATRIBUTO, SOLO ACTUALIZARLO
           */
          $database->update('tb_cliente_atributo', ['valor' => $attr['valor']], 'id_cliente = ? AND id_atributo = ?', [$codcliente, $attr['id_atributo']]);
        } else {
          /**
           * SI NO EXISTE, VERIFICAR SI EL VALOR NO ESTÁ VACÍO
           */
          if (trim($attr['valor']) != "") {
            $database->insert('tb_cliente_atributo', [
              'id_cliente' => $codcliente,
              'id_atributo' => $attr['id_atributo'],
              'valor' => $attr['valor']
            ]);
          }
        }
      }

      /**
       * MANEJO DE DATOS DE PEP
       */

      if ($pep == "Si") {

        $idDatosPepExist = $database->selectColumns('cli_datos_pep', ['id'], 'id_cliente = ?', [$codcliente]);
        if (empty($idDatosPepExist)) {
          // Insertar los nuevos datos PEP
          $idDatosPep = $database->insert('cli_datos_pep', [
            'id_cliente' => $codcliente,
            'entidad' => $pep_entidad,
            'puesto' => $pep_puesto,
            'paisEntidad' => $pep_pais,
            'otroOrigen' => (in_array(8, $origenesRiqueza)) ? $pep_origen_riqueza_otro : ''
          ]);

          //insertar los origenes en la tabla de union cli_origenes_riqueza
          foreach ($origenesRiqueza as $origenId) {
            $database->insert('cli_origenes_riqueza', [
              'id_pep' => $idDatosPep,
              'id_origen' => $origenId
            ]);
          }
        } else {
          // Actualizar los datos PEP existentes
          $database->update('cli_datos_pep', [
            'entidad' => $pep_entidad,
            'puesto' => $pep_puesto,
            'paisEntidad' => $pep_pais,
            'otroOrigen' => (in_array(8, $origenesRiqueza)) ? $pep_origen_riqueza_otro : ''
          ], 'id = ?', [$idDatosPepExist[0]['id']]);

          // Manejar los orígenes de riqueza
          // Primero, eliminar los orígenes existentes
          $database->delete('cli_origenes_riqueza', 'id_pep = ?', [$idDatosPepExist[0]['id']]);

          // Luego, insertar los orígenes seleccionados nuevamente
          foreach ($origenesRiqueza as $origenId) {
            $database->insert('cli_origenes_riqueza', [
              'id_pep' => $idDatosPepExist[0]['id'],
              'id_origen' => $origenId
            ]);
          }
        }
      }

      /**
       * MANEJO DE DATOS DE PEP PARA PARIENTES
       */
      if ($pariente_pep == "Si") {
        $idParientePepExist = $database->selectColumns('cli_complementos_pep', ['id'], 'id_cliente = ? AND tipo = ? AND estado=1', [$codcliente, 'pariente']);
        if (empty($idParientePepExist)) {
          // Insertar los nuevos datos de pariente PEP
          $database->insert('cli_complementos_pep', [
            'id_cliente' => $codcliente,
            'tipo' => 'pariente',
            'parentesco' => $pariente_pep_parentesco,
            'primerApellido' => $pariente_pep_primer_apellido,
            'segundoApellido' => $pariente_pep_segundo_apellido,
            'apellidoCasada' => $pariente_pep_apellido_casada,
            'primerNombre' => $pariente_pep_primer_nombre,
            'segundoNombre' => $pariente_pep_segundo_nombre,
            'otrosNombres' => $pariente_pep_otros_nombres,
            'sexo' => $pariente_pep_sexo,
            'condicion' => $pariente_pep_condicion,
            'entidad' => $pariente_pep_entidad,
            'puesto' => $pariente_pep_puesto,
            'pais' => $pariente_pep_pais,
            'estado' => 1,
            'created_by' => $idusuario,
            'created_at' => $hoy2,
          ]);
        } else {
          // Actualizar los datos de pariente PEP existentes
          $database->update('cli_complementos_pep', [
            'parentesco' => $pariente_pep_parentesco,
            'primerApellido' => $pariente_pep_primer_apellido,
            'segundoApellido' => $pariente_pep_segundo_apellido,
            'apellidoCasada' => $pariente_pep_apellido_casada,
            'primerNombre' => $pariente_pep_primer_nombre,
            'segundoNombre' => $pariente_pep_segundo_nombre,
            'otrosNombres' => $pariente_pep_otros_nombres,
            'sexo' => $pariente_pep_sexo,
            'condicion' => $pariente_pep_condicion,
            'entidad' => $pariente_pep_entidad,
            'puesto' => $pariente_pep_puesto,
            'pais' => $pariente_pep_pais,
            'updated_by' => $idusuario,
            'updated_at' => $hoy2,
          ], 'id = ?', [$idParientePepExist[0]['id']]);
        }
      } else {
        // Si el usuario cambió de "Sí" a "No", desactivar el registro existente
        $database->update('cli_complementos_pep', [
          'estado' => 0,
          'deleted_by' => $idusuario,
          'deleted_at' => $hoy2,
        ], 'id_cliente = ? AND tipo = ?', [$codcliente, 'pariente']);
      }

      /**
       * MANEJO DE DATOS DE PEP PARA ASOCIADOS
       */
      if ($asociado_pep == "Si") {

        if ($asociado_pep_motivo == 5 && empty($asociado_pep_otro_motivo)) {
          $showmensaje = true;
          throw new Exception("Debe especificar el motivo 'Otro' por el cual es asociado PEP");
        }

        $idAsociadoPepExist = $database->selectColumns('cli_complementos_pep', ['id'], 'id_cliente = ? AND tipo = ? AND estado=1', [$codcliente, 'asociado']);
        if (empty($idAsociadoPepExist)) {
          // Insertar los nuevos datos de asociado PEP
          $database->insert('cli_complementos_pep', [
            'id_cliente' => $codcliente,
            'tipo' => 'asociado',
            'motivoAsociacion' => $asociado_pep_motivo,
            'detalleOtro' => ($asociado_pep_motivo == 5) ? $asociado_pep_otro_motivo : '',
            'primerApellido' => $asociado_pep_primer_apellido,
            'segundoApellido' => $asociado_pep_segundo_apellido,
            'apellidoCasada' => $asociado_pep_apellido_casada,
            'primerNombre' => $asociado_pep_primer_nombre,
            'segundoNombre' => $asociado_pep_segundo_nombre,
            'otrosNombres' => $asociado_pep_otros_nombres,
            'sexo' => $asociado_pep_sexo,
            'condicion' => $asociado_pep_condicion,
            'entidad' => $asociado_pep_entidad,
            'puesto' => $asociado_pep_puesto,
            'pais' => $asociado_pep_pais,
            'estado' => 1,
            'created_by' => $idusuario,
            'created_at' => $hoy2,
          ]);
        } else {
          // Actualizar los datos de asociado PEP existentes
          $database->update('cli_complementos_pep', [
            'motivoAsociacion' => $asociado_pep_motivo,
            'detalleOtro' => ($asociado_pep_motivo == 5) ? $asociado_pep_otro_motivo : '',
            'primerApellido' => $asociado_pep_primer_apellido,
            'segundoApellido' => $asociado_pep_segundo_apellido,
            'apellidoCasada' => $asociado_pep_apellido_casada,
            'primerNombre' => $asociado_pep_primer_nombre,
            'segundoNombre' => $asociado_pep_segundo_nombre,
            'otrosNombres' => $asociado_pep_otros_nombres,
            'sexo' => $asociado_pep_sexo,
            'condicion' => $asociado_pep_condicion,
            'entidad' => $asociado_pep_entidad,
            'puesto' => $asociado_pep_puesto,
            'pais' => $asociado_pep_pais,
            'updated_by' => $idusuario,
            'updated_at' => $hoy2,
          ], 'id = ?', [$idAsociadoPepExist[0]['id']]);
        }
      } else {
        // Si el usuario cambió de "Sí" a "No", desactivar el registro
        $database->update('cli_complementos_pep', [
          'estado' => 0,
          'deleted_by' => $idusuario,
          'deleted_at' => $hoy2,
        ], 'id_cliente = ? AND tipo = ?', [$codcliente, 'asociado']);
      }

      $database->commit();

      $status = 1;
      $mensaje = "Datos de cliente actualizados correctamente: " . $codcliente;
    } catch (SoftException $e) {
      $database->rollback();
      $mensaje = $e->getMessage();
      $status = 0;
      // }
      // catch (Exception $e) {
      //   $database->rollback();
      //   $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      //   $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      // 
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status
    ]);
    //+++++++++++++++++++++++++++++++++++++++++++++++++
    //+++++++++++++++++++++++++++++++++++++++++++++++++

    break;

  case 'delete_cliente_natural': {
      $archivo = $_POST["ideliminar"];
      $validar = validar_campos_plus([
        [$archivo[0], "", 'No se ha detectado una agencia, recargue la pagina nuevamente', 1],
        [$archivo[3], "", 'Debe seleccionar un cliente a eliminar', 1],
      ]);
      if ($validar[2]) {
        echo json_encode([$validar[0], $validar[1]]);
        return;
      }
      //PREPARACION DE ARRAY
      $data = array(
        'estado' => '0',
        'fecha_mod' => date('Y-m-d')
      );

      $id = $archivo[3];
      $conexion->autocommit(FALSE);
      try {
        // Columnas a actualizar
        $setCols = [];
        foreach ($data as $key => $value) {
          $setCols[] = "$key = ?";
        }
        $setStr = implode(', ', $setCols);
        $stmt = $conexion->prepare("UPDATE tb_cliente SET $setStr WHERE idcod_cliente = ?");
        $values = array_values($data);
        $values[] = $id;
        $types = str_repeat('s', count($values));
        $stmt->bind_param($types, ...$values);
        if ($stmt->execute()) {
          $conexion->commit();
          echo json_encode([
            "Cliente eliminado corre
          tamente: " . $archivo[3],
            '1'
          ]);
        } else {
          $errorMsg = $stmt->error;
          $conexion->rollback();
          echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
        }
      } catch (Exception $e) {
        $conexion->rollback();
        echo json_encode(["Error: " . $e->getMessage(), '0']);
      } finally {
        $stmt->close();
        $conexion->close();
      }
    }
    break;
  case 'delete_image_cliente':
    $archivo = $_POST["ideliminar"];
    $codcliente = $archivo[1];
    $imgurl = $archivo[0];

    $consulta = mysqli_query($conexion, "SELECT url_img FROM tb_cliente tc WHERE tc.estado='1' AND tc.idcod_cliente='$codcliente'");
    $urlimg = 'xxxx';
    while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
      $urlimg = $fila['url_img'];
    }
    $salida = "../../../";
    $rutaenserver = $salida . $urlimg;
    if (file_exists($rutaenserver)) {
      unlink($rutaenserver);
      //inicio transaccion
      $conexion->autocommit(false);
      try {
        $conexion->query("UPDATE `tb_cliente` set `url_img`='ImageDeleted' WHERE idcod_cliente='$codcliente'");
        $aux = mysqli_error($conexion);
        if ($aux) {
          echo json_encode(['Error:' . $aux, '0']);
          $conexion->rollback();
          return;
        }
        $conexion->commit();
        echo json_encode(['Foto eliminada correctamente!', 1]);
      } catch (Exception $e) {
        $conexion->rollback();
        echo json_encode(['Error al eliminar la foto: ' . $e->getMessage(), '0']);
      }
      mysqli_close($conexion);
    } else {
      echo json_encode(['Archivo no encontrado', 1]);
    }
    break;
  case 'buscar_municipios':
    $id = $_POST['id'];
    if (!isset($_SESSION['id'])) {
      echo json_encode(['Sesion expirada', 0]);
      return;
    }
    $showmensaje = false;
    try {
      $database->openConnection();

      // Log::info("Buscando municipios del departamento: $id");

      $municipios = $database->selectColumns('tb_municipios', ['id AS codigo_municipio', 'nombre'], 'id_departamento = ?', [$id]);

      $status = 1;
      $mensaje = "Proceso exitoso";
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status,
      $municipios ?? []
    ]);

    break;

  case 'create_ingreso_propio':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $radios = $_POST["radios"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$archivo[3], "", 'Debe seleccionar un cliente', 1],
      [$inputs[0], "", 'Debe ingresar un nombre de negocio', 1],
      [$inputs[1], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[2], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[3], "", 'Debe ingresar una fecha de inicio o inscripción', 1],
      [$radios[0], "", 'Debe seleccionar un tipo de si tiene o no patente', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }
    //validar tipo de idendentificacion
    if ($radios[0] == "si") {
      $validar = validar_campos_plus([
        [$inputs[4], "", 'Debe ingresar un numero registro', 1],
        [$inputs[5], "", 'Debe ingresar un numero de folio', 1],
        [$inputs[6], "", 'Debe ingresar un numero de libro', 1],
      ]);
      if ($validar[2]) {
        echo json_encode([$validar[0], $validar[1]]);
        return;
      }
    } else {
      $inputs[4] = "";
      $inputs[5] = "";
      $inputs[6] = "";
    }

    $validar = validar_campos_plus([
      [$inputs[7], "", 'Debe ingresar un número de telefono', 1],
      [$selects[0], "", 'Debe ingresar una condicion local', 1],
      [$inputs[8], "", 'Debe ingresar un ingreso mensual estimado', 1],
      [$selects[1], "0", 'Debe seleccionar un departamento', 1],
      [$selects[2], "0", 'Debe ingresar un municipio', 1],
      [$inputs[9], "", 'Debe ingresar una dirección', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    //PREPARACION DE ARRAY
    $data = array(
      'id_cliente' => $archivo[3],
      'nombre_empresa' => $inputs[0],
      'patente' => $radios[0],
      'no_registro' => $inputs[4],
      'folio' => $inputs[5],
      'libro' => $inputs[6],
      'fecha_patente' => date('Y-m-d'),
      'depa_negocio' => $selects[1],
      'muni_negocio' => $selects[2],
      'detalle_ingreso' => '',
      'direc_negocio' => $inputs[9],
      'referencia' => $inputs[11],
      'telefono_negocio' => $inputs[7],
      'puesto_ocupa' => '',
      'empleados' => $inputs[10],
      'fecha_sys' => date('Y-m-d'),
      'fecha_labor' => $inputs[3],
      'sueldo_base' => $inputs[8],
      'NIT_empresa' => '',
      'condi_negocio' => $selects[0],
      'actividad_economica' => $inputs[1],
      'sector_Econo' => '',
      'fuente_ingreso' => 'Propio',
      'Tipo_ingreso' => '1',
      'created_at' => date('Y-m-d')
    );

    $conexion->autocommit(FALSE);
    try {
      $columns = implode(', ', array_keys($data));
      $placeholders = implode(', ', array_fill(0, count($data), '?'));
      $stmt = $conexion->prepare("INSERT INTO tb_ingresos ($columns) VALUES ($placeholders)");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $stmt->bind_param($types, ...$values);
      if ($stmt->execute()) {
        $conexion->commit();
        echo json_encode(["Ingreso propio ingresado correctamente del cliente: " . $archivo[3], '1']);
      } else {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
      }
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $conexion->close();
    }
    break;
  case 'update_ingreso_propio':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $radios = $_POST["radios"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$archivo[3], "", 'Debe seleccionar un cliente', 1],
      [$archivo[4], "", 'Debe seleccionar un registro a editar', 1],
      [$inputs[0], "", 'Debe ingresar un nombre de negocio', 1],
      [$inputs[1], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[2], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[3], "", 'Debe ingresar una fecha de inicio o inscripción', 1],
      [$radios[0], "", 'Debe seleccionar un tipo de si tiene o no patente', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }
    //validar tipo de idendentificacion
    if ($radios[0] == "si") {
      $validar = validar_campos_plus([
        [$inputs[4], "", 'Debe ingresar un numero registro', 1],
        [$inputs[5], "", 'Debe ingresar un numero de folio', 1],
        [$inputs[6], "", 'Debe ingresar un numero de libro', 1],
      ]);
      if ($validar[2]) {
        echo json_encode([$validar[0], $validar[1]]);
        return;
      }
    } else {
      $inputs[4] = "";
      $inputs[5] = "";
      $inputs[6] = "";
    }

    $validar = validar_campos_plus([
      [$inputs[7], "", 'Debe ingresar un número de telefono', 1],
      [$selects[0], "", 'Debe ingresar una condicion local', 1],
      [$inputs[8], "", 'Debe ingresar un ingreso mensual estimado', 1],
      [$selects[1], "0", 'Debe seleccionar un departamento', 1],
      [$selects[2], "0", 'Debe ingresar un municipio', 1],
      [$inputs[9], "", 'Debe ingresar una dirección', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    //PREPARACION DE ARRAY
    $data = array(
      'id_cliente' => $archivo[3],
      'nombre_empresa' => $inputs[0],
      'patente' => $radios[0],
      'no_registro' => $inputs[4],
      'folio' => $inputs[5],
      'libro' => $inputs[6],
      'fecha_patente' => $inputs[3],
      'depa_negocio' => $selects[1],
      'muni_negocio' => $selects[2],
      'detalle_ingreso' => '',
      'direc_negocio' => $inputs[9],
      'referencia' => $inputs[11],
      'telefono_negocio' => $inputs[7],
      'puesto_ocupa' => '',
      'empleados' => ($inputs[10] == "") ? 0 : $inputs[10],
      'fecha_sys' => date('Y-m-d'),
      'fecha_labor' => $inputs[3],
      'sueldo_base' => $inputs[8],
      'NIT_empresa' => '',
      'condi_negocio' => $selects[0],
      'actividad_economica' => $inputs[1],
      'sector_Econo' => '',
      'fuente_ingreso' => 'Propio',
      'Tipo_ingreso' => '1'
    );

    $id = $archivo[4];
    $conexion->autocommit(FALSE);
    try {
      // Columnas a actualizar
      $setCols = [];
      foreach ($data as $key => $value) {
        $setCols[] = "$key = ?";
      }
      $setStr = implode(', ', $setCols);
      $stmt = $conexion->prepare("UPDATE tb_ingresos SET $setStr WHERE id_ingre_dependi = ?");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $values[] = $id; // Agregar ID al final
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $stmt->bind_param($types, ...$values);
      if ($stmt->execute()) {
        $conexion->commit();
        echo json_encode(["Ingreso propio actualizado correctamente del cliente: " . $archivo[3], '1']);
      } else {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
      }
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $conexion->close();
    }
    break;
  case 'create_ingreso_dependiente':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $radios = $_POST["radios"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$archivo[3], "", 'Debe seleccionar un cliente', 1],
      [$selects[0], "", 'Debe seleccionar un sector', 1],
      [$inputs[0], "", 'Debe ingresar un nombre de negocio', 1],
      [$inputs[1], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[2], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[3], "", 'Debe ingresar una puesto', 1],
      [$inputs[5], "", 'Debe ingresar una direccion', 1],
      [$selects[1], "0", 'Debe seleccionar un departamento', 1],
      [$selects[2], "0", 'Debe ingresar un municipio', 1],
      [$inputs[4], "", 'Debe ingresar un ingreso mensual estimado', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    //PREPARACION DE ARRAY
    $data = array(
      'id_cliente' => $archivo[3],
      'nombre_empresa' => $inputs[0],
      'patente' => '',
      'no_registro' => '',
      'folio' => '',
      'libro' => '',
      'fecha_patente' => '0000-00-00',
      'depa_negocio' => $selects[1],
      'muni_negocio' => $selects[2],
      'detalle_ingreso' => '',
      'direc_negocio' => $inputs[5],
      'telefono_negocio' => '0',
      'puesto_ocupa' => $inputs[3],
      'fecha_sys' => date('Y-m-d'),
      'fecha_labor' => $inputs[6],
      'sueldo_base' => $inputs[4],
      'NIT_empresa' => '0',
      'condi_negocio' => '',
      'actividad_economica' => $inputs[1],
      'sector_Econo' => '',
      'fuente_ingreso' => 'Dependencia',
      'Tipo_ingreso' => '2',
      'created_at' => date('Y-m-d')
    );

    $conexion->autocommit(FALSE);
    try {
      $columns = implode(', ', array_keys($data));
      $placeholders = implode(', ', array_fill(0, count($data), '?'));
      $stmt = $conexion->prepare("INSERT INTO tb_ingresos ($columns) VALUES ($placeholders)");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $stmt->bind_param($types, ...$values);
      if ($stmt->execute()) {
        $conexion->commit();
        echo json_encode(["Ingreso en dependencia ingresado correctamente del cliente: " . $archivo[3], '1']);
      } else {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
      }
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $conexion->close();
    }
    break;
  case 'update_ingreso_dependiente':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$archivo[3], "", 'Debe seleccionar un cliente', 1],
      [$archivo[4], "", 'Debe seleccionar un registro a editar', 1],
      [$selects[0], "", 'Debe seleccionar un sector', 1],
      [$inputs[0], "", 'Debe ingresar un nombre de negocio', 1],
      [$inputs[1], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[2], "", 'Debe ingresar una actividad economica', 1],
      [$inputs[3], "", 'Debe ingresar una puesto', 1],
      [$inputs[5], "", 'Debe ingresar una direccion', 1],
      [$selects[1], "0", 'Debe seleccionar un departamento', 1],
      [$selects[2], "0", 'Debe ingresar un municipio', 1],
      [$inputs[4], "", 'Debe ingresar un ingreso mensual estimado', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    //PREPARACION DE ARRAY
    //PREPARACION DE ARRAY
    $data = array(
      'id_cliente' => $archivo[3],
      'nombre_empresa' => $inputs[0],
      'patente' => '',
      'no_registro' => '',
      'folio' => '',
      'libro' => '',
      'fecha_patente' => '0000-00-00',
      'depa_negocio' => $selects[1],
      'muni_negocio' => $selects[2],
      'detalle_ingreso' => '',
      'direc_negocio' => $inputs[5],
      'telefono_negocio' => '0',
      'puesto_ocupa' => $inputs[3],
      'fecha_sys' => date('Y-m-d'),
      'fecha_labor' => $inputs[6],
      'sueldo_base' => $inputs[4],
      'NIT_empresa' => '',
      'condi_negocio' => '',
      'actividad_economica' => $inputs[1],
      'sector_Econo' => '',
      'fuente_ingreso' => 'Dependencia',
      'Tipo_ingreso' => '2'
    );

    $id = $archivo[4];
    $conexion->autocommit(FALSE);
    try {
      // Columnas a actualizar
      $setCols = [];
      foreach ($data as $key => $value) {
        $setCols[] = "$key = ?";
      }
      $setStr = implode(', ', $setCols);
      $stmt = $conexion->prepare("UPDATE tb_ingresos SET $setStr WHERE id_ingre_dependi = ?");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $values[] = $id; // Agregar ID al final
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $stmt->bind_param($types, ...$values);
      if ($stmt->execute()) {
        $conexion->commit();
        echo json_encode(["Ingreso en dependencia actualizado correctamente del cliente: " . $archivo[3], '1']);
      } else {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
      }
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $conexion->close();
    }
    break;
  case 'create_otros_ingresos':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $radios = $_POST["radios"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$archivo[3], "", 'Debe seleccionar un cliente', 1],
      [$selects[0], "", 'Debe seleccionar un tipo de ingreso', 1],
      [$inputs[1], "", 'Debe ingresar un monto aproximado mensual', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    //PREPARACION DE ARRAY
    $data = array(
      'id_cliente' => $archivo[3],
      'nombre_empresa' => $selects[0],
      'patente' => '',
      'no_registro' => '',
      'folio' => '',
      'libro' => '',
      'fecha_patente' => '0000-00-00',
      'depa_negocio' => '',
      'muni_negocio' => '',
      'detalle_ingreso' => $inputs[0],
      'direc_negocio' => '',
      'telefono_negocio' => '00',
      'puesto_ocupa' => '',
      'fecha_sys' => date('Y-m-d'),
      'fecha_labor' => '0000-00-00',
      'sueldo_base' => $inputs[1],
      'NIT_empresa' => '',
      'condi_negocio' => '',
      'actividad_economica' => '',
      'sector_Econo' => '',
      'fuente_ingreso' => 'Otros',
      'Tipo_ingreso' => '3',
      'created_at' => date('Y-m-d')
    );

    $conexion->autocommit(FALSE);
    try {
      $columns = implode(', ', array_keys($data));
      $placeholders = implode(', ', array_fill(0, count($data), '?'));
      $stmt = $conexion->prepare("INSERT INTO tb_ingresos ($columns) VALUES ($placeholders)");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $stmt->bind_param($types, ...$values);
      if ($stmt->execute()) {
        $conexion->commit();
        echo json_encode(["Otro ingreso registrado correctamente del cliente: " . $archivo[3], '1']);
      } else {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
      }
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $conexion->close();
    }
    break;
  case 'update_otros_ingresos':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$archivo[3], "", 'Debe seleccionar un cliente', 1],
      [$archivo[4], "", 'Debe seleccionar un registro a editar', 1],
      [$selects[0], "", 'Debe seleccionar un tipo de ingreso', 1],
      [$inputs[1], "", 'Debe ingresar un monto aproximado mensual', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }
    //PREPARACION DE ARRAY
    $data = array(
      'id_cliente' => $archivo[3],
      'nombre_empresa' => $selects[0],
      'patente' => '',
      'no_registro' => '',
      'folio' => '',
      'libro' => '',
      'fecha_patente' => '0000-00-00',
      'depa_negocio' => '',
      'muni_negocio' => '',
      'detalle_ingreso' => $inputs[0],
      'direc_negocio' => '',
      'telefono_negocio' => '000000000',
      'puesto_ocupa' => '',
      'fecha_sys' => date('Y-m-d'),
      'fecha_labor' => '0000-00-00',
      'sueldo_base' => $inputs[1],
      'NIT_empresa' => '',
      'condi_negocio' => '',
      'actividad_economica' => '',
      'sector_Econo' => '',
      'fuente_ingreso' => 'Otros',
      'Tipo_ingreso' => '3',
    );

    $id = $archivo[4];
    $conexion->autocommit(FALSE);
    try {
      // Columnas a actualizar
      $setCols = [];
      foreach ($data as $key => $value) {
        $setCols[] = "$key = ?";
      }
      $setStr = implode(', ', $setCols);
      $stmt = $conexion->prepare("UPDATE tb_ingresos SET $setStr WHERE id_ingre_dependi = ?");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $values[] = $id; // Agregar ID al final
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $stmt->bind_param($types, ...$values);
      if ($stmt->execute()) {
        $conexion->commit();
        echo json_encode(["Otro ingreso actualizado correctamente del cliente: " . $archivo[3], '1']);
      } else {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
      }
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $conexion->close();
    }
    break;
  case 'delete_perfil_economico':
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$archivo[0], "", 'Debe seleccionar un registro a eliminar', 1],
      [$archivo[1], "", 'Debe seleccionar un cliente', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    $id = $archivo[0];
    $conexion->autocommit(FALSE);
    try {
      $stmt = $conexion->prepare("DELETE FROM tb_ingresos WHERE id_ingre_dependi = ?");
      $stmt->bind_param('i', $id);
      if ($stmt->execute()) {
        $conexion->commit();
        echo json_encode(["Registro de perfil economico eliminado satisfactoriamente: " . $archivo[1], '1']);
      } else {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
      }
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $conexion->close();
    }
    break;

  case 'create_cliente_juridico':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $archivo = $_POST["archivo"];

    $validar = validar_campos_plus([
      [$inputs[0], "", 'Debe ingresar una razón social', 1],
      [$inputs[1], "", 'Debe ingresar un nombre comercial', 1],
      [$inputs[2], "", 'Debe ingresar un numero de registro de sociedad', 1],
      [$inputs[3], "", 'Debe ingresar nombre representante legal', 1],
      [$inputs[4], "", 'Debe digitar una fecha de fundación', 1],
      [$selects[0], "0", 'Debe seleccionar un departamento', 1],
      [$selects[1], "0", 'Debe seleccionar un municipio', 1],
      [$inputs[6], "", 'Debe ingresar el domicilio fiscal', 1],
      [$inputs[7], "", 'Debe ingresar el nombre de presidente(a)', 1],
      [$inputs[8], "", 'Debe ingresar el nombre de vicepresidente(a)', 1],
      [$inputs[9], "", 'Debe ingresar el nombre de secretario(a)', 1],
      [$inputs[10], "", 'Debe ingresar el nombre de tesorero(a)', 1],
      [$inputs[11], "", 'Debe ingresar el nombre de vocal 1', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }
    // Si la referencia y vocal 2 esta vacio, colocar guiones
    $inputs[5] = ($inputs[5] == "") ? '-' : $inputs[5];
    $inputs[12] = ($inputs[12] == "") ? '-' : $inputs[12];

    //GENERAR EL CODIGO DEL CLIENTE
    $gencodigo = getcodcli($archivo[0], $conexion);
    if ($gencodigo[0] == 0) {
      echo json_encode([$gencodigo[1], '0']);
      return;
    }
    $codgen = $gencodigo[1];
    //PREPARACION DE ARRAY
    $data = array(
      'idcod_cliente' => $codgen,
      'id_tipoCliente' => 'JURIDICO',
      'agencia' => $archivo[2],
      'primer_name' => '-',
      'segundo_name' => '-',
      'tercer_name' => '-',
      'primer_last' => '-',
      'segundo_last' => '-',
      'casada_last' => '-',
      'short_name' => $inputs[1],
      'compl_name' => $inputs[0],
      'url_img' => '',
      'date_birth' => $inputs[4],
      'no_identifica' => $inputs[2],
      'identi_tribu' => 'CUI',
      'no_tributaria' => $inputs[2],
      'Direccion' => $inputs[6],
      'depa_reside' => $selects[0],
      'muni_reside' => '-',
      'id_muni_reside' => $selects[1],
      'aldea_reside' => $inputs[5],
      'representante_name' => $inputs[3],
      'estado' => '1',
      'fecha_alta' => date('Y-m-d'),
      'fecha_mod' => date('Y-m-d'),
    );

    $conexion->autocommit(FALSE);
    try {
      // //INSERCION DE CLIENTE NATURAL
      $columns = implode(', ', array_keys($data));
      $placeholders = implode(', ', array_fill(0, count($data), '?'));
      $stmt = $conexion->prepare("INSERT INTO tb_cliente ($columns) VALUES ($placeholders)");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $id_cliente_natural = "";
      $stmt->bind_param($types, ...$values);
      if (!$stmt->execute()) {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
        return;
      }
      // INSERCION DE SOCIOS DE CLIENTES JURIDICOS
      $data2 = array(
        array(
          'name_socio' => $inputs[7],
          'puesto_socio' => 'Presidente',
          'id_clnt_ntral' => $codgen
        ),
        array(
          'name_socio' => $inputs[8],
          'puesto_socio' => 'Vicepresidente',
          'id_clnt_ntral' => $codgen
        ),
        array(
          'name_socio' => $inputs[9],
          'puesto_socio' => 'Secretario',
          'id_clnt_ntral' => $codgen
        ),
        array(
          'name_socio' => $inputs[10],
          'puesto_socio' => 'Tesorero',
          'id_clnt_ntral' => $codgen
        ),
        array(
          'name_socio' => $inputs[11],
          'puesto_socio' => 'Vocal 1',
          'id_clnt_ntral' => $codgen
        ),
        array(
          'name_socio' => $inputs[12],
          'puesto_socio' => 'Vocal 2',
          'id_clnt_ntral' => $codgen
        )
      );

      foreach ($data2 as $key => $value) {
        $columns = implode(', ', array_keys($value));
        $placeholders = implode(', ', array_fill(0, count($value), '?'));
        $stmt2 = $conexion->prepare("INSERT INTO tb_socios_juri ($columns) VALUES ($placeholders)");
        // Obtener los valores del array de datos
        $values = array_values($value);
        // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
        $types = str_repeat('s', count($values));
        // Vincular los parámetros
        $stmt2->bind_param($types, ...$values);
        if (!$stmt2->execute()) {
          $errorMsg = $stmt2->error;
          $conexion->rollback();
          echo json_encode(["Error al ejecutar consulta 2: $errorMsg", '0']);
          return;
        }
      }
      //Realizar el commit especifico
      $conexion->commit();
      echo json_encode(["Cliente juridico ingresado correctamente: " . $codgen, '1']);
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $stmt2->close();
      $conexion->close();
    }
    break;
  case 'update_huellamodulos':

    if ($conexion->connect_error) {
      die("Error en la conexión: " . $conexion->connect_error);
    }
    $id = $_POST["id"];
    $estado = $_POST["estado"];

    // Validar 
    $id = $conexion->real_escape_string($id);
    $estado = $conexion->real_escape_string($estado);

    $sql = "UPDATE tb_validacioneshuella SET estado = '$estado' WHERE id_modulo = '$id'";

    // Ejecutar la consulta y verificar si fue exitosa
    if ($conexion->query($sql) === TRUE) {
      echo json_encode(["Mensaje" => "Estado actualizado correctamente", "Estado" => $estado]);
    } else {
      echo json_encode(["Error" => "Error al actualizar: " . $conexion->error]);
    }

    // Cerrar la conexión
    $conexion->close();

    break;
  case 'update_cliente_juridico':
    $inputs = $_POST["inputs"];
    $selects = $_POST["selects"];
    $archivo = $_POST["archivo"];
    // COLOCAR EL VALOR DE LA AGENCIA

    $validar = validar_campos_plus([
      [$archivo[3], "", 'Debe seleccionar un cliente juridico a actualizar', 1],
      [$inputs[0], "", 'Debe ingresar una razón social', 1],
      [$inputs[1], "", 'Debe ingresar un nombre comercial', 1],
      [$inputs[2], "", 'Debe ingresar un numero de registro de sociedad', 1],
      [$inputs[3], "", 'Debe ingresar nombre representante legal', 1],
      [$inputs[4], "", 'Debe digitar una fecha de fundación', 1],
      [$selects[0], "0", 'Debe seleccionar un departamento', 1],
      [$selects[1], "0", 'Debe seleccionar un municipio', 1],
      [$inputs[6], "", 'Debe ingresar el domicilio fiscal', 1],
      [$inputs[7], "", 'Debe ingresar el nombre de presidente(a)', 1],
      [$inputs[8], "", 'Debe ingresar el nombre de vicepresidente(a)', 1],
      [$inputs[9], "", 'Debe ingresar el nombre de secretario(a)', 1],
      [$inputs[10], "", 'Debe ingresar el nombre de tesorero(a)', 1],
      [$inputs[11], "", 'Debe ingresar el nombre de vocal 1', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }
    // Si la referencia y vocal 2 esta vacio, colocar guiones
    $inputs[5] = ($inputs[5] == "") ? '-' : $inputs[5];
    $inputs[12] = ($inputs[12] == "") ? '-' : $inputs[12];

    // PREPARACION DE ARRAY
    $data = array(
      'id_tipoCliente' => 'JURIDICO',
      'agencia' => $archivo[2],
      'primer_name' => '-',
      'segundo_name' => '-',
      'tercer_name' => '-',
      'primer_last' => '-',
      'segundo_last' => '-',
      'casada_last' => '-',
      'short_name' => $inputs[1],
      'compl_name' => $inputs[0],
      'url_img' => '',
      'date_birth' => $inputs[4],
      'no_identifica' => $inputs[2],
      'identi_tribu' => 'CUI',
      'no_tributaria' => $inputs[2],
      'Direccion' => $inputs[6],
      'depa_reside' => $selects[0],
      'muni_reside' => '-',
      'id_muni_reside' => $selects[1],
      'aldea_reside' => $inputs[5],
      'representante_name' => $inputs[3],
      'estado' => '1',
      'fecha_mod' => date('Y-m-d'),
    );

    $id = $archivo[3];
    $conexion->autocommit(FALSE);
    try {
      // Columnas a actualizar
      $setCols = [];
      foreach ($data as $key => $value) {
        $setCols[] = "$key = ?";
      }
      $setStr = implode(', ', $setCols);
      $stmt = $conexion->prepare("UPDATE tb_cliente SET $setStr WHERE idcod_cliente = ?");
      // Obtener los valores del array de datos
      $values = array_values($data);
      // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
      $values[] = $id; // Agregar ID al final
      $types = str_repeat('s', count($values));
      // Vincular los parámetros
      $stmt->bind_param($types, ...$values);
      if (!$stmt->execute()) {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta: $errorMsg", '0']);
        return;
      }

      //ELIMINAR LOS REGISTROS ANTERIORES
      $stmt = $conexion->prepare("DELETE FROM tb_socios_juri WHERE id_clnt_ntral = ?");
      $stmt->bind_param('s', $id);
      if (!$stmt->execute()) {
        $errorMsg = $stmt->error;
        $conexion->rollback();
        echo json_encode(["Error al ejecutar consulta 2: $errorMsg", '0']);
        return;
      }

      // INSERCION DE SOCIOS DE CLIENTES JURIDICOS
      $data2 = array(
        array(
          'name_socio' => $inputs[7],
          'puesto_socio' => 'Presidente',
          'id_clnt_ntral' => $id
        ),
        array(
          'name_socio' => $inputs[8],
          'puesto_socio' => 'Vicepresidente',
          'id_clnt_ntral' => $id
        ),
        array(
          'name_socio' => $inputs[9],
          'puesto_socio' => 'Secretario',
          'id_clnt_ntral' => $id
        ),
        array(
          'name_socio' => $inputs[10],
          'puesto_socio' => 'Tesorero',
          'id_clnt_ntral' => $id
        ),
        array(
          'name_socio' => $inputs[11],
          'puesto_socio' => 'Vocal 1',
          'id_clnt_ntral' => $id
        ),
        array(
          'name_socio' => $inputs[12],
          'puesto_socio' => 'Vocal 2',
          'id_clnt_ntral' => $id
        )
      );

      foreach ($data2 as $key => $value) {
        $columns = implode(', ', array_keys($value));
        $placeholders = implode(', ', array_fill(0, count($value), '?'));
        $stmt2 = $conexion->prepare("INSERT INTO tb_socios_juri ($columns) VALUES ($placeholders)");
        // Obtener los valores del array de datos
        $values = array_values($value);
        // Obtener los tipos de datos para los valores (pueden ser todos 's' para cadena)
        $types = str_repeat('s', count($values));
        // Vincular los parámetros
        $stmt2->bind_param($types, ...$values);
        if (!$stmt2->execute()) {
          $errorMsg = $stmt2->error;
          $conexion->rollback();
          echo json_encode(["Error al ejecutar consulta 3: $errorMsg", '0']);
          return;
        }
      }
      //Realizar el commit especifico
      $conexion->commit();
      echo json_encode(["Cliente jurídico actualizado correctamente: " . $archivo[3], '1']);
    } catch (Exception $e) {
      $conexion->rollback();
      echo json_encode(["Error: " . $e->getMessage(), '0']);
    } finally {
      $stmt->close();
      $stmt2->close();
      $conexion->close();
    }
    break;
  // ------------> NEGROY dpivalidate validaciones ≽^•⩊•^≼
  case 'dpivalidate':
    $dpi = $_POST["dpi"];
    $cli = $_POST["cli"];
    if ($cli == 1) { // validamos solo DPI CLIENTE NUEVO 
      $consulta = "SELECT idcod_cliente, no_identifica, agencia, short_name 
        FROM `tb_cliente` WHERE no_identifica= '$dpi';";
    } else { // VALIDO con el codigo del cliente, si ya exite algun cliente != CLIENTE_Id
      $consulta = "SELECT idcod_cliente, no_identifica, agencia, short_name 
        FROM `tb_cliente` WHERE no_identifica = $dpi AND idcod_cliente != '$cli';";
    }
    $queryins = mysqli_query($conexion, $consulta);
    $numeroResultados = mysqli_num_rows($queryins);
    echo $numeroResultados;
    return;
    break;
  case 'validateNumberDocument':
    list($cli, $number) = $_POST["archivo"];
    $showmensaje = false;
    try {
      $database->openConnection();

      if ($cli == 1) {
        $result = $database->selectColumns('tb_cliente', ['idcod_cliente'], 'no_identifica=?', [$number]);
      } else {
        $result = $database->selectColumns('tb_cliente', ['idcod_cliente'], 'no_identifica=? AND idcod_cliente!=?', [$number, $cli]);
      }

      $mensaje = "Número de documento disponible.";
      if (!empty($result)) {
        $mensaje = "El número de documento ya está registrado en el sistema";
        $count = 1;
      }

      $status = 1;
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status,
      'message' => $mensaje ?? '',
      'count' => $count ?? 0,
      'reprint' => 0,
      'timer' => 10
    ]);
    break;
  case 'activarSensor':
    /**
     * Activar el sensor de huella dactilar
     * 
     * Este script realiza las siguientes acciones:
     * 1. Verifica si la variable de sesión 'id_agencia' está definida.
     *    - Si no está definida, devuelve un mensaje JSON indicando que la sesión ha expirado.
     * 2. Obtiene el valor del identificador o token desde los datos POST.
     * 3. Valida el identificador o token utilizando la función 'validacionescampos'.
     *    - Si la validación falla, devuelve un mensaje JSON con el error correspondiente.
     * 
     * CONTINUA ....
     * @return void
     */
    if (!isset($_SESSION['id_agencia'])) {
      echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
      return;
    }
    list($srcPc, $serialSession) = $_POST["inputs"];
    list($operacion, $idPersona) = $_POST["archivo"];

    $validar = validacionescampos([
      [$srcPc, "", 'No existe ningun identificador o token (verifique que tenga asignado uno)', 1],
    ]);

    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    /**
     * Este código realiza las siguientes acciones:
     * 1. Abre una conexión a la base de datos.
     * 2. Inicia una transacción.
     * 3. Elimina cualquier registro existente en la tabla 'huella_temp' para el PC especificado.
     * 4. Inserta un nuevo registro en la tabla 'huella_temp' con los datos proporcionados.
     * 5. Confirma la transacción.
     * 6. Maneja cualquier excepción que ocurra durante el proceso, registrando el error y proporcionando un mensaje adecuado.
     * 
     * Variables:
     * - $showmensaje: Booleano que indica si se debe mostrar el mensaje de error detallado.
     * - $database: Objeto de la base de datos utilizado para realizar las operaciones.
     * - $srcPc: Serial del PC desde el cual se está activando el sensor de huella.
     * - $hoy2: Fecha actual.
     * - $status: Estado de la operación (1 para éxito, 0 para error).
     * - $mensaje: Mensaje de resultado de la operación.
     * - $codigoError: Código de error registrado en caso de excepción.
     * 
     * Excepciones:
     * - Captura cualquier excepción durante la transacción y realiza un rollback.
     * - Registra el error y proporciona un mensaje de error adecuado.
     * 
     * Respuesta JSON:
     * - $mensaje: Mensaje de resultado.
     * - $status: Estado de la operación.
     * - "reprint": Indica si se debe reimprimir (0 en este caso).
     * - "timer": Tiempo de espera (1000 ms en este caso).
     */
    $showmensaje = false;
    try {
      $database->openConnection();

      $database->beginTransaction();

      $database->delete('huella_temp', "pc_serial=?", [$srcPc]);

      $datos = array(
        "fecha_creacion" => $hoy2,
        "pc_serial" => $srcPc,
        "texto" => "El sensor de huella dactilar esta activado",
        "statusPlantilla" => ($operacion == 0) ? "Muestras Restantes: 4" : NULL,
        "opc" => ($operacion == 0) ? "capturar" : "leer",
        "serialSession" => $serialSession,
        "findCode" => ($operacion == 0) ? 0 : $idPersona,
        "typeFindCode" => $operacion,
      );

      $database->insert('huella_temp', $datos);

      $database->commit();

      $status = 1;
      $mensaje = "Proceso lanzado correctamente, espere a que el sensor de huella dactilar se active";
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status,
      "reprint" => 0,
      "timer" => 1000
    ]);

    break;
  case 'activarSensorCaptura':
    /**
     * Activar el sensor de huella dactilar
     * 
     * Este script realiza las siguientes acciones:
     * 1. Verifica si la variable de sesión 'id_agencia' está definida.
     *    - Si no está definida, devuelve un mensaje JSON indicando que la sesión ha expirado.
     * 2. Obtiene el valor del identificador o token desde los datos POST.
     * 3. Valida el identificador o token utilizando la función 'validacionescampos'.
     *    - Si la validación falla, devuelve un mensaje JSON con el error correspondiente.
     * 
     * CONTINUA ....
     * @return void
     */
    if (!isset($_SESSION['id_agencia'])) {
      echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
      return;
    }
    list($srcPc, $serialSession) = $_POST["inputs"];
    list($operacion, $idPersona) = $_POST["archivo"];

    $validar = validacionescampos([
      [$srcPc, "", 'No existe ningun identificador o token (verifique que tenga asignado uno)', 1],
    ]);

    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }


    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    /**
     * Este código realiza las siguientes acciones:
     * 1. Abre una conexión a la base de datos.
     * 2. Inicia una transacción.
     * 3. Elimina cualquier registro existente en la tabla 'huella_temp' para el PC especificado.
     * 4. Inserta un nuevo registro en la tabla 'huella_temp' con los datos proporcionados.
     * 5. Confirma la transacción.
     * 6. Maneja cualquier excepción que ocurra durante el proceso, registrando el error y proporcionando un mensaje adecuado.
     * 
     * Variables:
     * - $showmensaje: Booleano que indica si se debe mostrar el mensaje de error detallado.
     * - $database: Objeto de la base de datos utilizado para realizar las operaciones.
     * - $srcPc: Serial del PC desde el cual se está activando el sensor de huella.
     * - $hoy2: Fecha actual.
     * - $status: Estado de la operación (1 para éxito, 0 para error).
     * - $mensaje: Mensaje de resultado de la operación.
     * - $codigoError: Código de error registrado en caso de excepción.
     * 
     * Excepciones:
     * - Captura cualquier excepción durante la transacción y realiza un rollback.
     * - Registra el error y proporciona un mensaje de error adecuado.
     * 
     * Respuesta JSON:
     * - $mensaje: Mensaje de resultado.
     * - $status: Estado de la operación.
     * - "reprint": Indica si se debe reimprimir (0 en este caso).
     * - "timer": Tiempo de espera (1000 ms en este caso).
     */
    $showmensaje = false;
    try {
      $database->openConnection();

      $database->beginTransaction();

      $database->delete('huella_temp', "pc_serial=?", [$srcPc]);

      $datos = array(
        "fecha_creacion" => $hoy2,
        "pc_serial" => $srcPc,
        "texto" => "El sensor de huella dactilar esta activado",
        "statusPlantilla" => ($operacion == 0) ? "Muestras Restantes: 4" : NULL,
        "opc" => ($operacion == 0) ? "capturar" : "leer",
        "serialSession" => $serialSession,
        "findCode" => ($operacion == 0) ? 0 : $idPersona,
        "typeFindCode" => $operacion,
      );

      $database->insert('huella_temp', $datos);

      $ablyService = AblyService::getInstance();
      $huellaData = [
        "operacion" => "capturar",
        "serialSession" => $serialSession,
        "idPersona" => $srcPc
      ];

      // Usar el nuevo método con confirmación
      $confirmacion = $ablyService->publishHuellaDigital($srcPc, $huellaData);

      Log::info("Publicación de huella digital enviada para el PC: $srcPc", [$confirmacion]);

      if (!isset($confirmacion['status']) || $confirmacion['status'] !== 'success') {
        $showmensaje = true;
        throw new AblyServiceException("El dispositivo no confirmó la recepción del comando");
      }

      $database->commit();

      $status = 1;
      $mensaje = "Proceso lanzado correctamente, espere a que el sensor de huella dactilar se active";
    } catch (Throwable $e) {
      $database->rollback();
      $status = 0;
      // Verificar si es una excepción de AblyService
      if ($e instanceof AblyServiceException) {
        $mensaje = "Error de comunicación con el dispositivo: " . $e->getMessage();
      } else {
        if (!$showmensaje) {
          $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        }
        $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      }
    } finally {
      $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status,
      "reprint" => 0,
      "timer" => 1000
    ]);

    break;
  case 'saveHuella':
    /**
     * Este script realiza el guardado de un registro de huella digital.
     * 
     * Validaciones y operaciones incluidas:
     * - Verificación de la existencia de la sesión de usuario.
     * - Validación del token CSRF para prevenir ataques de falsificación de solicitudes.
     * - Validación de campos obligatorios enviados a través de POST.
     * - Desencriptación del ID del cliente.
     * 
     * Variables POST esperadas:
     * - inputs: Array que contiene el token CSRF y un identificador.
     * - selects: Array que contiene la selección de mano y dedo.
     * - archivo: Array que contiene el ID del cliente encriptado.
     * 
     * Respuestas JSON posibles:
     * - [$errorcsrf, 0, "reprint" => 1, "timer" => 3000]: Si el token CSRF no es válido.
     * - [$validar[0], $validar[1]]: Si alguna de las validaciones de campos falla.
     */
    if (!isset($_SESSION['id_agencia'])) {
      echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
      return;
    }
    list($csrftoken, $srcPc, $serialSession) = $_POST["inputs"];
    list($mano, $dedo) = $_POST["selects"];
    list($encryptedID) = $_POST["archivo"];

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    if (!($csrf->validateToken($csrftoken, false))) {
      $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
      $opResult = array(
        $errorcsrf,
        0,
        "reprint" => 1,
        "timer" => 3000
      );
      echo json_encode($opResult);
      return;
    }
    $validar = validacionescampos([
      [$srcPc, "", 'No existe ningun identificador o token (verifique que tenga asignado uno)', 1],
      [$mano, "", 'Debe seleccionar una mano', 1],
      [$dedo, "", 'Debe seleccionar un dedo', 1],
      [$encryptedID, "", 'Error al obtener el ID del cliente', 1]
    ]);

    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    $decryptedID = $secureID->decrypt($encryptedID);
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    /**
     * Este script maneja la inserción de datos de la huella digital en la base de datos.
     * 
     * Variables:
     * - $showmensaje: Indica si se debe mostrar un mensaje de error específico.
     * - $tempHuella: Almacena los datos temporales de la huella obtenidos de la base de datos.
     * - $datos: Array que contiene los datos de la huella digital a insertar en la base de datos.
     * - $status: Indica el estado de la operación (1 para éxito, 0 para error).
     * - $mensaje: Mensaje de éxito o error que se enviará como respuesta.
     * 
     * Funcionalidad:
     * 1. Abre una conexión a la base de datos.
     * 2. Selecciona los datos temporales de la huella basándose en el serial del PC.
     * 3. Si no se encuentran datos temporales, lanza una excepción.
     * 4. Inicia una transacción en la base de datos.
     * 5. Inserta los datos de la huella digital en la tabla 'huella_digital'.
     * 6. Elimina los datos temporales de la tabla 'huella_temp'.
     * 7. Confirma la transacción.
     * 8. En caso de error, revierte la transacción y registra el error.
     * 9. Cierra la conexión a la base de datos.
     * 10. Devuelve un mensaje y el estado de la operación en formato JSON.
     */
    $showmensaje = false;
    try {
      $database->openConnection();
      $tempHuella = $database->selectColumns('huella_temp', ['imgHuella', 'huella'], "pc_serial=? AND serialSession=?", [$srcPc, $serialSession]);
      if (empty($tempHuella)) {
        $showmensaje = true;
        throw new Exception("No se encontró ningun registro de huella a guardar");
      }
      $huellaVerify = $database->selectColumns('huella_digital', ['mano', 'dedo'], "id_persona=? AND mano=? AND dedo=? AND estado=1", [$decryptedID, $mano, $dedo]);
      if (!empty($huellaVerify)) {
        $showmensaje = true;
        throw new Exception("Ya existe una huella registrada para la mano y dedo seleccionados");
      }
      $database->beginTransaction();
      $datos = array(
        "id_persona" => $decryptedID,
        "mano" => $mano,
        "dedo" => $dedo,
        "huella" => $tempHuella[0]['huella'],
        "imgHuella" => $tempHuella[0]['imgHuella'],
        "estado" => 1,
        "tipo_persona" => 1,
        "created_by" => $idusuario,
        "created_at" => $hoy2,
      );
      $database->insert('huella_digital', $datos);

      $database->delete('huella_temp', "pc_serial=?", [$srcPc]);

      $database->commit();
      $status = 1;
      $mensaje = "Registro guardado correctamente";
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([$mensaje, $status]);
    break;
  case 'eliminaHuella':
    /**
     * Eliminacion de registro de una huella de un cliente.
     * 
     * - Si la sesión ha expirado, devuelve un mensaje de error en formato JSON.
     * - Valida el ID encriptado recibido desde el formulario.
     * - Desencripta el ID para su posterior uso.
     * 
     * @return void
     */
    if (!isset($_SESSION['id_agencia'])) {
      echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
      return;
    }

    $encryptedID = $_POST["ideliminar"];

    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    $validar = validacionescampos([
      [$encryptedID, "", 'Error al obtener el ID del registro', 1]
    ]);

    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }

    $decryptedID = $secureID->decrypt($encryptedID);
    /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
    /**
     * Elimina la huella digital de la base de datos.
     *
     * @throws Exception Si no se encuentra ningún registro de huella a eliminar.
     *
     * @global object $database Instancia de la conexión a la base de datos.
     * @global int $idusuario ID del usuario que realiza la eliminación.
     * @global string $hoy2 Fecha y hora actual.
     * @global int $decryptedID ID de la persona cuya huella digital se va a eliminar.
     *
     * @return void
     */
    $showmensaje = false;
    try {
      $database->openConnection();
      $huella = $database->selectColumns('huella_digital', ['id_persona'], "id=?", [$decryptedID]);
      if (empty($huella)) {
        $showmensaje = true;
        throw new Exception("No se encontró ningun registro de huella a eliminar");
      }
      $database->beginTransaction();
      $datos = array(
        'estado' => 0,
        'deleted_by' => $idusuario,
        'deleted_at' => $hoy2,
      );
      $database->update('huella_digital', $datos, "id=?", [$decryptedID]);

      $database->commit();
      $status = 1;
      $mensaje = "Huella eliminada correctamente";
    } catch (Exception $e) {
      $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      $database->closeConnection();
    }
    echo json_encode([$mensaje, $status]);
    break;

  case 'loadDepartamentosByPais':
    list($pais) = $_POST["archivo"];

    // Log::info("Cargando departamentos para el país: $pais");
    $showmensaje = false;
    try {
      // $database->openConnection();

      if (empty($pais) || $pais == "0") {
        $showmensaje = true;
        throw new Exception("No se ha seleccionado un país válido");
      }

      $idPais = Pais::obtenerPorCodigo($pais);
      // Log::info("ID del país obtenido: ", ['idPais' => $idPais]);
      if ($idPais === null) {
        $showmensaje = true;
        throw new Exception("El país seleccionado no es válido");
      }

      // Departamento::invalidarCachePorPais($idPais['id']);

      $opcionesDepartamentos = Departamento::obtenerParaSelect($idPais['id'] ?? 0);
      $htmlOptions = "<option value='0'>Seleccione un departamento</option>";
      foreach ($opcionesDepartamentos as $id => $nombre) {
        $htmlOptions .= "<option value='{$id}'>{$nombre}</option>";
      }

      $status = 1;
      $mensaje = "Departamentos cargados correctamente";
    } catch (Exception $e) {
      // $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      // $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status,
      'htmldata' => $htmlOptions ?? '',
      'reprint' => 0,
      'timer' => 10
    ]);
    break;
  case 'loadDocumentosByPais':
    list($pais) = $_POST["archivo"];

    // Log::info("Cargando documentos para el país: $pais");
    $showmensaje = false;
    try {
      // $database->openConnection();

      if (empty($pais) || $pais == "0") {
        $showmensaje = true;
        throw new Exception("No se ha seleccionado un país válido");
      }
      $tiposCompletos = Identificacion::obtenerPorPaisConGlobales($pais);
      // Log::info("ID del país obtenido: ", ['idPais' => $idPais]);
      if ($tiposCompletos === null) {
        $showmensaje = true;
        throw new Exception("El país seleccionado no es válido");
      }

      $htmlOptions = "<option value='0' selected disabled>Seleccione un documento</option>";
      $first = true;
      foreach ($tiposCompletos as $id => $tipo) {
        $selected = $first ? " selected" : "";
        $htmlOptions .= "<option value='{$tipo['codigo']}' data-regex='{$tipo['mascara_regex']}'{$selected}>{$tipo['nombre']}</option>";
        $first = false;
      }

      $status = 1;
      $mensaje = "Documentos cargados correctamente";
    } catch (Exception $e) {
      // $database->rollback();
      if (!$showmensaje) {
        $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      }
      $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
      $status = 0;
    } finally {
      // $database->closeConnection();
    }
    echo json_encode([
      $mensaje,
      $status,
      'htmldata' => $htmlOptions ?? '',
      'reprint' => 0,
      'timer' => 10
    ]);
    break;

  case 'huellaDesac':
    #Trabajo en proceso
    break;
  case 'DATA_REF':
    #CASE DATOS DE REREFECIA 

    break;

  case 'consultar_reporte':
    $id_descripcion = $_POST["id_descripcion"];
    $validar = validar_campos_plus([
      [$id_descripcion, "", 'No se ha detectado un identificador de reporte válido', 1],
      [$id_descripcion, "0", 'Ingrese un número de reporte mayor a 0', 1],
    ]);
    if ($validar[2]) {
      echo json_encode([$validar[0], $validar[1]]);
      return;
    }
    try {
      //Validar si de casualidad ya se hizo el cierre otro usuario
      $stmt = $conexion->prepare("SELECT * FROM tb_documentos td WHERE td.id_reporte = ?");
      if (!$stmt) {
        throw new Exception("Error en la consulta 1: " . $conexion->error);
      }
      $stmt->bind_param("s", $id_descripcion); //El arroba omite el warning de php
      if (!$stmt->execute()) {
        throw new Exception("Error en la ejecucion de la consulta 1: " . $stmt->error);
      }
      $result = $stmt->get_result();
      $numFilas2 = $result->num_rows;
      if ($numFilas2 == 0) {
        throw new Exception("No se encontro el reporte en el listado de documentos disponible");
      }
      $fila = $result->fetch_assoc();
      echo json_encode(["Reporte encontrado", '1', $fila['nombre'], $fila['file']]);
    } catch (Exception $e) {
      //Captura el error
      $mensaje_error = $e->getMessage();
      echo json_encode([$mensaje_error, '0']);
    } finally {
      if ($stmt !== false) {
        $stmt->close();
      }
      $conexion->close();
    }
    break;

  //---------Guardar Info Adicional Cliente------//
  case 'guardar_info_adicional_cliente':
    $response = [
      'message' => 'Error al guardar la información',
      'status' => '0',
      'reprint' => 1
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();


      //error_log("POST inputs: " . ($_POST['inputs'] ?? 'NO EXISTE'));

      // Obtener datos del formulario
      $inputs = json_decode($_POST['inputs'], true);
      $archivo = json_decode($_POST['archivo'], true);

      //error_log("Inputs decodificados: " . print_r($inputs, true));

      // Obtener ID del cliente
      $entidad_id = '';
      if (!empty($inputs) && isset($inputs[1])) {
        $entidad_id = $inputs[1];
      }
      if (empty($entidad_id) && !empty($_POST['id'])) {
        $entidad_id = $_POST['id'];
      }
      if (empty($entidad_id) && !empty($archivo) && isset($archivo[0])) {
        $entidad_id = $archivo[0];
      }

      $entidad_tipo = 'cliente';

      // Mapear datos del formulario
      if (!empty($inputs) && is_array($inputs)) {
        $descripcion = trim($inputs[2] ?? '');
        $latitud = !empty($inputs[3]) && $inputs[3] !== '' ? (float) $inputs[3] : null;
        $longitud = !empty($inputs[4]) && $inputs[4] !== '' ? (float) $inputs[4] : null;
        $altitud = !empty($inputs[5]) && $inputs[5] !== '' ? (float) $inputs[5] : null;
        $precision = !empty($inputs[6]) && $inputs[6] !== '' ? (float) $inputs[6] : null;
        $direccion_texto = trim($inputs[7] ?? '');
      }

      // Validaciones básicas
      if (empty($entidad_id)) {
        throw new Exception("ID de cliente requerido");
      }

      if (empty($descripcion)) {
        throw new Exception("La descripción es obligatoria");
      }

      // Validar que el cliente existe y obtener su agencia
      $cliente_data = $database->selectColumns(
        'tb_cliente',
        ['idcod_cliente', 'agencia'],
        "estado=1 AND idcod_cliente=?",
        [$entidad_id]
      );

      if (empty($cliente_data)) {
        throw new Exception("Cliente no encontrado");
      }

      $id_agencia_cliente = $cliente_data[0]['agencia'];
      //error_log("Agencia del cliente: " . $id_agencia_cliente);

      // Validar coordenadas GPS
      if (($latitud !== null || $longitud !== null) && ($latitud === null || $longitud === null)) {
        throw new Exception("Si proporciona coordenadas, debe incluir tanto latitud como longitud");
      }

      if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
        throw new Exception("Latitud debe estar entre -90 y 90 grados");
      }

      if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
        throw new Exception("Longitud debe estar entre -180 y 180 grados");
      }

      // Preparar datos para inserción
      $datos_adicionales = [
        'entidad_tipo' => $entidad_tipo,
        'entidad_id' => $entidad_id,
        'descripcion' => $descripcion,
        'latitud' => $latitud,
        'longitud' => $longitud,
        'altitud' => $altitud,
        'precision' => $precision,
        'direccion_texto' => $direccion_texto,
        'estado' => 1,
        'created_by' => $_SESSION['userID'] ?? $idusuario ?? 1,
        'created_at' => date('Y-m-d H:i:s')
      ];

      //error_log("Datos a insertar: " . print_r($datos_adicionales, true));

      // Insertar en cli_adicionales
      $id_adicional = $database->insert('cli_adicionales', $datos_adicionales);

      if (!$id_adicional) {
        throw new Exception("Error al insertar información adicional");
      }

      //error_log("✓ Registro creado con ID: " . $id_adicional);

      // Procesar archivos adjuntos si existen
      $archivos_guardados = 0;
      if (!empty($_FILES['archivos_adjuntos'])) {
        // ACTUALIZADO: Pasar los parámetros necesarios
        $archivos_guardados = procesarArchivosAdicionales(
          $id_adicional,
          $_FILES['archivos_adjuntos'],
          $database,
          $entidad_id,
          $id_agencia_cliente
        );
      }

      $database->commit();

      $mensaje = "Información adicional guardada exitosamente";
      if ($archivos_guardados > 0) {
        $mensaje .= " con {$archivos_guardados} archivo(s) adjunto(s)";
      }

      $response = [
        'message' => $mensaje,
        'status' => '1',
        'reprint' => 1,
        'timer' => 3000,
        'id_adicional' => $id_adicional,
        'archivos_guardados' => $archivos_guardados
      ];
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      //error_log("ERROR en guardar_info_adicional_cliente: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
    break;



  case 'cargar_info_adicional':
    $response = [
      'message' => 'Error al cargar la información',
      'status' => '0',
      'reprint' => 0
    ];

    try {
      $database->openConnection();

      $id_adicional = $_POST['id'] ?? 0;

      if (empty($id_adicional)) {
        throw new Exception("ID de información adicional requerido");
      }

      // Obtener información adicional
      $info_adicional = $database->selectColumns(
        'cli_adicionales',
        ['id', 'entidad_tipo', 'entidad_id', 'descripcion', 'latitud', 'longitud', 'altitud', '`precision`', 'direccion_texto'],
        "id=? AND estado=1",
        [$id_adicional]
      );

      if (empty($info_adicional)) {
        throw new Exception("Registro no encontrado");
      }

      // Obtener archivos asociados
      $archivos = $database->selectColumns(
        'cli_adicional_archivos',
        ['id', 'path_file'],
        "id_adicional=?",
        [$id_adicional]
      );

      // ✅ Procesar archivos con FileProcessor
      $fileProcessor = new FileProcessor(__DIR__ . '/../../');
      $archivos_procesados = [];

      foreach ($archivos as $archivo) {
        $path_file = $archivo['path_file'];
        $archivo_procesado = [
          'id' => $archivo['id'],
          'path_file' => $path_file,
          'filename' => basename($path_file),
          'exists' => false,
          'is_image' => false,
          'data_uri' => null
        ];

        if ($fileProcessor->fileExists($path_file)) {
          $archivo_procesado['exists'] = true;
          $archivo_procesado['is_image'] = $fileProcessor->isImage($path_file);

          if ($archivo_procesado['is_image']) {
            $archivo_procesado['data_uri'] = $fileProcessor->getDataUri($path_file);
          }
        }

        $archivos_procesados[] = $archivo_procesado;
      }

      $response = [
        'message' => 'Información cargada exitosamente',
        'status' => '1',
        'reprint' => 0,
        'data' => [
          'info' => $info_adicional[0],
          'archivos' => $archivos_procesados
        ]
      ];
    } catch (Exception $e) {
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      //error_log("❌ Error en cargar_info_adicional: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response['data'] ?? null]);
    break;

  case 'actualizar_info_adicional':
    $response = [
      'message' => 'Error al actualizar la información',
      'status' => '0',
      'reprint' => 1
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();

      $id_adicional = $_POST['id'] ?? 0;
      $inputs = json_decode($_POST['inputs'], true);

      if (empty($id_adicional)) {
        throw new Exception("ID de información adicional requerido");
      }

      // ✅ PASO 1: Verificar que el registro existe y obtener entidad_id
      $info_existente = $database->selectColumns(
        'cli_adicionales',
        ['id', 'entidad_id', 'entidad_tipo'],
        "id=? AND estado=1",
        [$id_adicional]
      );

      if (empty($info_existente)) {
        throw new Exception("Registro no encontrado");
      }

      $entidad_id = $info_existente[0]['entidad_id'];
      $entidad_tipo = $info_existente[0]['entidad_tipo'];

      // ✅ PASO 2: Obtener la agencia del cliente desde tb_cliente
      $cliente_data = $database->selectColumns(
        'tb_cliente',
        ['idcod_cliente', 'agencia'],
        "idcod_cliente=? AND estado=1",
        [$entidad_id]
      );

      if (empty($cliente_data)) {
        throw new Exception("Cliente no encontrado o inactivo");
      }

      $id_agencia_cliente = $cliente_data[0]['agencia'];

      error_log("✅ Cliente: {$entidad_id} | Agencia: {$id_agencia_cliente}");

      // Mapear datos (soporta formato indexado y asociativo)
      $descripcion = '';
      $latitud = null;
      $longitud = null;
      $altitud = null;
      $precision = null;
      $direccion_texto = '';

      if (!empty($inputs) && is_array($inputs)) {
        if (isset($inputs['descripcion'])) {
          // Formato asociativo
          $descripcion = trim($inputs['descripcion'] ?? '');
          $latitud = !empty($inputs['latitud']) && $inputs['latitud'] !== '' ? (float) $inputs['latitud'] : null;
          $longitud = !empty($inputs['longitud']) && $inputs['longitud'] !== '' ? (float) $inputs['longitud'] : null;
          $altitud = !empty($inputs['altitud']) && $inputs['altitud'] !== '' ? (float) $inputs['altitud'] : null;
          $precision = !empty($inputs['precision_gps']) && $inputs['precision_gps'] !== '' ? (float) $inputs['precision_gps'] : null;
          $direccion_texto = trim($inputs['direccion_texto'] ?? '');
        } else {
          // Formato indexado
          $descripcion = trim($inputs[2] ?? '');
          $latitud = !empty($inputs[3]) && $inputs[3] !== '' ? (float) $inputs[3] : null;
          $longitud = !empty($inputs[4]) && $inputs[4] !== '' ? (float) $inputs[4] : null;
          $altitud = !empty($inputs[5]) && $inputs[5] !== '' ? (float) $inputs[5] : null;
          $precision = !empty($inputs[6]) && $inputs[6] !== '' ? (float) $inputs[6] : null;
          $direccion_texto = trim($inputs[7] ?? '');
        }
      }

      if (empty($descripcion)) {
        throw new Exception("La descripción es obligatoria");
      }

      // Validar coordenadas
      if (($latitud !== null || $longitud !== null) && ($latitud === null || $longitud === null)) {
        throw new Exception("Si proporciona coordenadas, debe incluir tanto latitud como longitud");
      }

      if ($latitud !== null && ($latitud < -90 || $latitud > 90)) {
        throw new Exception("Latitud debe estar entre -90 y 90 grados");
      }

      if ($longitud !== null && ($longitud < -180 || $longitud > 180)) {
        throw new Exception("Longitud debe estar entre -180 y 180 grados");
      }

      // Preparar datos para actualización
      $datos_actualizacion = [
        'descripcion' => $descripcion,
        'latitud' => $latitud,
        'longitud' => $longitud,
        'altitud' => $altitud,
        '`precision`' => $precision,
        'direccion_texto' => $direccion_texto,
        'updated_by' => $_SESSION['userID'] ?? $idusuario ?? 1,
        'updated_at' => date('Y-m-d H:i:s')
      ];

      // Actualizar registro
      $actualizado = $database->update(
        'cli_adicionales',
        $datos_actualizacion,
        "id=?",
        [$id_adicional]
      );

      if (!$actualizado) {
        throw new Exception("Error al actualizar la información");
      }

      // ✅ PROCESAR ARCHIVOS NUEVOS CON PARÁMETROS CORRECTOS
      $archivos_guardados = 0;
      $nombres_archivos = ['archivos_adjuntos', 'archivos', 'files', 'archivos_adicionales'];

      foreach ($nombres_archivos as $nombre) {
        if (!empty($_FILES[$nombre])) {
          $archivos_guardados = procesarArchivosAdicionales(
            $id_adicional,
            $_FILES[$nombre],
            $database,
            $entidad_id,           // ✅ ID del cliente
            $id_agencia_cliente    // ✅ ID de la agencia
          );
          break;
        }
      }

      $database->commit();

      $mensaje = "Información actualizada exitosamente";
      if ($archivos_guardados > 0) {
        $mensaje .= " con {$archivos_guardados} archivo(s) adicional(es)";
      }

      $response = [
        'message' => $mensaje,
        'status' => '1',
        'reprint' => 1,
        'timer' => 3000,
        'archivos_guardados' => $archivos_guardados
      ];

      error_log("✅ Actualización completada: {$mensaje}");
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("❌ Error en actualización: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
    break;

  case 'eliminar_info_adicional':
    $response = [
      'message' => 'Error al eliminar la información',
      'status' => '0',
      'reprint' => 1
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();

      $id_adicional = $_POST['id'] ?? 0;


      error_log("ID adicional: " . $id_adicional);

      if (empty($id_adicional)) {
        throw new Exception("ID de información adicional requerido");
      }

      // Verificar que el registro existe
      $info_existente = $database->selectColumns(
        'cli_adicionales',
        ['id', 'entidad_id'],
        "id=? AND estado=1",
        [$id_adicional]
      );

      if (empty($info_existente)) {
        throw new Exception("Registro no encontrado");
      }

      // ✅ Obtener archivos asociados antes de eliminar
      $archivos_asociados = $database->selectColumns(
        'cli_adicional_archivos',
        ['id', 'path_file'],
        "id_adicional=?",
        [$id_adicional]
      );

      error_log("Archivos asociados encontrados: " . count($archivos_asociados));

      // Marcar como eliminado (soft delete)
      $eliminado = $database->update(
        'cli_adicionales',
        [
          'estado' => 0,
          'deleted_by' => $_SESSION['userID'] ?? $idusuario ?? 1,
          'deleted_at' => date('Y-m-d H:i:s')
        ],
        "id=?",
        [$id_adicional]
      );

      if (!$eliminado) {
        throw new Exception("Error al eliminar la información");
      }

      error_log("✓ Registro marcado como eliminado");

      // ✅ Eliminar archivos físicos y registros con la ruta correcta
      $archivos_eliminados = 0;
      foreach ($archivos_asociados as $archivo) {
        try {
          // ✅ CORRECCIÓN: Usar la ruta correcta (3 niveles arriba desde controllers/actions/)
          $ruta_completa = __DIR__ . '/../../../' . $archivo['path_file'];

          error_log("Intentando eliminar: " . $ruta_completa);

          // Eliminar archivo físico si existe
          if (file_exists($ruta_completa)) {
            if (unlink($ruta_completa)) {
              error_log("✓ Archivo físico eliminado: " . $ruta_completa);
            } else {
              error_log("⚠ No se pudo eliminar el archivo: " . $ruta_completa);
            }
          } else {
            error_log("⚠ Archivo no existe: " . $ruta_completa);
          }

          // Eliminar registro de archivo
          $database->delete('cli_adicional_archivos', "id=?", [$archivo['id']]);
          $archivos_eliminados++;
        } catch (Exception $e) {
          error_log("ERROR eliminando archivo ID {$archivo['id']}: " . $e->getMessage());
          logerrores("Error eliminando archivo: " . $e->getMessage(), __FILE__, __LINE__);
        }
      }

      // ✅ OPCIONAL: Intentar eliminar la carpeta si está vacía
      if (!empty($archivos_asociados)) {
        try {
          $primer_archivo = $archivos_asociados[0]['path_file'];
          $carpeta_adicionales = dirname(__DIR__ . '/../../../' . $primer_archivo);

          if (is_dir($carpeta_adicionales) && count(scandir($carpeta_adicionales)) === 2) {
            rmdir($carpeta_adicionales);
            error_log("✓ Carpeta adicionales eliminada: " . $carpeta_adicionales);
          }
        } catch (Exception $e) {
          error_log("No se pudo eliminar la carpeta: " . $e->getMessage());
        }
      }

      $database->commit();

      $mensaje = "Información eliminada exitosamente";
      if ($archivos_eliminados > 0) {
        $mensaje .= " junto con {$archivos_eliminados} archivo(s)";
      }

      $response = [
        'message' => $mensaje,
        'status' => '1',
        'reprint' => 1,
        'timer' => 3000,
        'archivos_eliminados' => $archivos_eliminados
      ];

      error_log("✓ Eliminación completada");
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("ERROR en eliminar_info_adicional: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
    break;

  case 'eliminar_archivo_adicional':
    $response = [
      'message' => 'Error al eliminar el archivo',
      'status' => '0',
      'reprint' => 0
    ];

    try {
      $database->openConnection();
      $database->beginTransaction();

      $id_archivo = $_POST['id'] ?? 0;


      error_log("ID archivo: " . $id_archivo);

      if (empty($id_archivo)) {
        throw new Exception("ID de archivo requerido");
      }

      // Obtener información del archivo
      $archivo = $database->selectColumns(
        'cli_adicional_archivos',
        ['id', 'path_file'],
        "id=?",
        [$id_archivo]
      );

      if (empty($archivo)) {
        throw new Exception("Archivo no encontrado");
      }

      error_log("Path del archivo: " . $archivo[0]['path_file']);

      // ✅ CORRECCIÓN: Construir ruta correcta (3 niveles arriba desde controllers/actions/)
      $ruta_completa = __DIR__ . '/../../../' . $archivo[0]['path_file'];

      error_log("Ruta completa: " . $ruta_completa);

      // Eliminar archivo físico
      if (file_exists($ruta_completa)) {
        if (unlink($ruta_completa)) {
          error_log("✓ Archivo físico eliminado exitosamente: " . $ruta_completa);
        } else {
          error_log("⚠ No se pudo eliminar el archivo físico");
          // No lanzar excepción, continuar con la eliminación del registro
        }
      } else {
        error_log("⚠ Archivo físico no existe: " . $ruta_completa);
      }

      // Eliminar registro de la base de datos
      $database->delete('cli_adicional_archivos', "id=?", [$id_archivo]);

      $database->commit();

      $response = [
        'message' => 'Archivo eliminado exitosamente',
        'status' => '1',
        'reprint' => 0
      ];

      error_log("✓ Eliminación completada");
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $response['message'] = "Error: " . $e->getMessage() . " (Código: $codigoError)";
      error_log("ERROR en eliminar_archivo_adicional: " . $e->getMessage());
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$response['message'], $response['status'], $response]);
    break;
  case 'create_egresos':
    $status = 0;
    try {
      /**
       * [`det_egreso`,`monto_egreso`]
       */
      $data = [
        'nombre' => $_POST['inputs'][0] ?? null,
        'monto' => $_POST['inputs'][1] ?? null,
        'codigoCliente' => $_POST['archivo'][0] ?? '',
      ];

      $rules = [
        'nombre' => 'required|string|max_length:100',
        'monto' => 'required|numeric|min:0.01',
        'codigoCliente' => 'required|string|max_length:50',
      ];

      $validator = Validator::make($data, $rules);
      if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
      }

      $database->openConnection();

      $cli_egresos = [
        'id_cliente' => $data['codigoCliente'],
        'nombre' => $data['nombre'],
        'monto' => $data['monto'],
        'estado' => '1',
        'created_by' => $idusuario,
        'created_at' => date('Y-m-d H:i:s'),
      ];

      $idOtrPago = $database->insert('cli_egresos', $cli_egresos);

      $database->commit();
      $mensaje = "Registro insertado correctamente";
      $status = 1;
    } catch (SoftException $e) {
      $database->rollback();
      $mensaje = $e->getMessage();
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$mensaje, $status]);
    break;
  case 'update_egresos':
    $status = 0;
    try {
      /**
       * [`det_egreso`,`monto_egreso`]
       */
      $data = [
        'nombre' => $_POST['inputs'][0] ?? null,
        'monto' => $_POST['inputs'][1] ?? null,
        'codigoCliente' => $_POST['archivo'][0] ?? '',
        'id_egreso' => $_POST['archivo'][1] ?? '',
      ];

      $rules = [
        'nombre' => 'required|string|max_length:100',
        'monto' => 'required|numeric|min:0.01',
        'codigoCliente' => 'required|string|max_length:50',
        'id_egreso' => 'required|numeric|min:1|exists:cli_egresos,id',
      ];

      $validator = Validator::make($data, $rules);
      if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
      }

      $database->openConnection();

      $cli_egresos = [
        // 'id_cliente' => $data['codigoCliente'],
        'nombre' => $data['nombre'],
        'monto' => $data['monto'],
        // 'estado' => '1',
        'updated_by' => $idusuario,
        'updated_at' => date('Y-m-d H:i:s'),
      ];

      $idOtrPago = $database->update('cli_egresos', $cli_egresos, 'id=?', [$data['id_egreso']]);

      $database->commit();
      $mensaje = "Registro actualizado correctamente";
      $status = 1;
    } catch (SoftException $e) {
      $database->rollback();
      $mensaje = $e->getMessage();
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$mensaje, $status]);
    break;
  case 'delete_egreso':
    $status = 0;
    try {
      $data = [
        'id_egreso' => $_POST['archivo'][0] ?? '',
      ];

      $rules = [
        'id_egreso' => 'required|numeric|min:1|exists:cli_egresos,id',
      ];

      $validator = Validator::make($data, $rules);
      if ($validator->fails()) {
        $firstError = $validator->firstOnErrors();
        throw new SoftException($firstError);
      }

      $database->openConnection();

      $cli_egresos = [
        'estado' => '0',
        'deleted_by' => $idusuario,
        'deleted_at' => date('Y-m-d H:i:s'),
      ];

      $database->update('cli_egresos', $cli_egresos, 'id=?', [$data['id_egreso']]);

      $database->commit();
      $mensaje = "Registro eliminado correctamente";
      $status = 1;
    } catch (SoftException $e) {
      $database->rollback();
      $mensaje = $e->getMessage();
    } catch (Exception $e) {
      $database->rollback();
      $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
      $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
    } finally {
      $database->closeConnection();
    }

    echo json_encode([$mensaje, $status]);
    break;
}


function validar_campos_plus($validaciones)
{
  for ($i = 0; $i < count($validaciones); $i++) {
    if ($validaciones[$i][3] == 1) { //igual
      if ($validaciones[$i][0] == $validaciones[$i][1]) {
        return [$validaciones[$i][2], '0', true];
        $i = count($validaciones) + 1;
      }
    } elseif ($validaciones[$i][3] == 2) { //menor que
      if ($validaciones[$i][0] < $validaciones[$i][1]) {
        return [$validaciones[$i][2], '0', true];
        $i = count($validaciones) + 1;
      }
    } elseif ($validaciones[$i][3] == 3) { //mayor que
      if ($validaciones[$i][0] > $validaciones[$i][1]) {
        return [$validaciones[$i][2], '0', true];
        $i = count($validaciones) + 1;
      }
    } elseif ($validaciones[$i][3] == 4) { //Validarexpresionesregulares
      if (validar_expresion_regular($validaciones[$i][0], $validaciones[$i][1])) {
        return [$validaciones[$i][2], '0', true];
        $i = count($validaciones) + 1;
      }
    } elseif ($validaciones[$i][3] == 5) { //Escapar de la validacion
    }
  }
  return ["", '0', false];
}

function validar_expresion_regular($cadena, $expresion_regular)
{
  if (preg_match($expresion_regular, $cadena)) {
    return false;
  } else {
    return true;
  }
}

//FUNCION PARA CONCATENAR NOMBRES
function concatenar_nombre($array1, $array2, $separador)
{
  $concatenado = '';
  foreach ($array1 as $valor) {
    if (!empty($valor)) {
      // Convertir a mayúsculas maneando tildes
      $valor = mb_strtoupper($valor, 'UTF-8');
      $concatenado .= $valor . ' ';
    }
  }
  $concatenado2 = '';
  foreach ($array2 as $valor) {
    if (!empty($valor)) {
      // Convertir a mayúsculas maneando tildes
      $valor = mb_strtoupper($valor, 'UTF-8');
      $concatenado2 .= $valor . ' ';
    }
  }
  return trim($concatenado) . $separador . trim($concatenado2);
}

//funcion para validar si un campo es requerido
function validar_requerido($valor)
{
  return !empty($valor);
}


/**
 * Procesa y guarda archivos adicionales del cliente
 * Sigue el patrón: imgcoope.microsystemplus.com/{folder}/{cliente}/adicionales/
 * 
 * @param int $id_adicional ID del registro en cli_adicionales
 * @param array $archivos Array de archivos de $_FILES
 * @param object $database Instancia de la base de datos
 * @param string $entidad_id ID del cliente
 * @param int $id_agencia ID de la agencia
 * @return int Cantidad de archivos guardados exitosamente
 */
function procesarArchivosAdicionales($id_adicional, $archivos, $database, $entidad_id, $id_agencia)
{
  $archivos_guardados = 0;


  error_log("ID adicional: " . $id_adicional);
  error_log("Entidad ID (cliente): " . $entidad_id);
  error_log("ID agencia: " . $id_agencia);

  try {
    // Obtener el folder de la institución usando la clase Agencia
    $folderInstitucion = (new Agencia($id_agencia))->institucion?->getFolderInstitucion();

    if ($folderInstitucion === null) {
      error_log("ERROR: No se pudo obtener la carpeta de la institución");
      throw new Exception("No se pudo obtener la carpeta de la institución");
    }

    error_log("Folder institución: " . $folderInstitucion);

    // Construir la ruta siguiendo el patrón establecido
    // Estructura: imgcoope.microsystemplus.com/{folder}/{cliente}/adicionales/
    $salida = "../../../"; // Salir 3 niveles desde controllers/actions/
    $entrada = "imgcoope.microsystemplus.com/" . $folderInstitucion . "/" . $entidad_id . "/adicionales";
    $rutaEnServidor = $salida . $entrada;

    error_log("Ruta base: " . $entrada);
    error_log("Ruta en servidor: " . $rutaEnServidor);

    // Crear el directorio si no existe
    if (!is_dir($rutaEnServidor)) {
      if (!mkdir($rutaEnServidor, 0777, true)) {
        error_log("ERROR: No se pudo crear el directorio: " . $rutaEnServidor);
        throw new Exception("No se pudo crear el directorio de archivos adicionales");
      }
      error_log("✓ Directorio creado: " . $rutaEnServidor);
    }

    // Tipos de archivo permitidos
    $tipos_permitidos = [
      'image/jpeg',
      'image/jpg',
      'image/png',
      'image/gif',
      'image/bmp',
      'image/webp',
      'application/pdf',
      'application/msword',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
      'application/vnd.ms-excel',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'text/plain'
    ];

    $max_size = 5 * 1024 * 1024; // 5MB máximo por archivo

    // Determinar si son múltiples archivos o uno solo
    $es_multiple = is_array($archivos['name']);
    $total_archivos = $es_multiple ? count($archivos['name']) : 1;

    error_log("Total de archivos a procesar: " . $total_archivos);

    for ($i = 0; $i < $total_archivos; $i++) {
      try {
        // Obtener datos del archivo según si es múltiple o único
        $nombre_original = $es_multiple ? $archivos['name'][$i] : $archivos['name'];
        $tipo = $es_multiple ? $archivos['type'][$i] : $archivos['type'];
        $tamanio = $es_multiple ? $archivos['size'][$i] : $archivos['size'];
        $tmp_name = $es_multiple ? $archivos['tmp_name'][$i] : $archivos['tmp_name'];
        $error = $es_multiple ? $archivos['error'][$i] : $archivos['error'];


        error_log("Nombre original: " . $nombre_original);
        error_log("Tipo: " . $tipo);
        error_log("Tamaño: " . $tamanio . " bytes");

        // Validar errores de carga
        if ($error !== UPLOAD_ERR_OK) {
          error_log("Error de carga (código $error)");
          continue;
        }

        // Validar que el archivo temporal existe
        if (!is_uploaded_file($tmp_name)) {
          error_log("ERROR: No es un archivo subido válido");
          continue;
        }

        // Validar tipo de archivo
        if (!in_array($tipo, $tipos_permitidos)) {
          error_log("ERROR: Tipo de archivo no permitido: " . $tipo);
          continue;
        }

        // Validar tamaño
        if ($tamanio > $max_size) {
          error_log("ERROR: Archivo demasiado grande (" . round($tamanio / 1024 / 1024, 2) . " MB)");
          continue;
        }

        // Generar nombre único siguiendo el patrón del sistema
        // Formato: adicional_{id_adicional}_{hash_único}
        $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
        $hash = substr(md5(uniqid() . time() . $nombre_original), 0, 15);
        $nombre_archivo = "adicional_{$id_adicional}_{$hash}.{$extension}";

        $ruta_completa = $rutaEnServidor . "/" . $nombre_archivo;
        $ruta_relativa = $entrada . "/" . $nombre_archivo;

        error_log("Nombre generado: " . $nombre_archivo);
        error_log("Ruta completa: " . $ruta_completa);
        error_log("Ruta relativa (BD): " . $ruta_relativa);

        // Mover el archivo al destino final
        if (!move_uploaded_file($tmp_name, $ruta_completa)) {
          error_log("ERROR: No se pudo mover el archivo a: " . $ruta_completa);
          continue;
        }

        // Verificar que el archivo se guardó correctamente
        if (!file_exists($ruta_completa)) {
          error_log("ERROR: El archivo no existe después de moverlo");
          continue;
        }

        $file_size = filesize($ruta_completa);
        error_log("✓ Archivo guardado exitosamente");
        error_log("✓ Tamaño en disco: " . $file_size . " bytes");
        error_log("✓ Permisos: " . substr(sprintf('%o', fileperms($ruta_completa)), -4));

        // Guardar SOLO los campos que tiene tu tabla
        $datos_archivo = [
          'id_adicional' => $id_adicional,
          'path_file' => $ruta_relativa
        ];

        error_log("Insertando en BD: " . json_encode($datos_archivo));

        if ($database->insert('cli_adicional_archivos', $datos_archivo)) {
          $archivos_guardados++;
          error_log("✓ Archivo registrado en BD exitosamente");
        } else {
          error_log("ERROR: No se pudo registrar en la base de datos");
          // Eliminar el archivo físico si no se pudo registrar en BD
          @unlink($ruta_completa);
        }
      } catch (Exception $e) {
        error_log("ERROR procesando archivo $i: " . $e->getMessage());
        logerrores("Error procesando archivo adicional: " . $e->getMessage(), __FILE__, __LINE__);
        continue;
      }
    }
  } catch (Exception $e) {
    error_log("ERROR CRÍTICO en procesarArchivosAdicionales: " . $e->getMessage());
    logerrores("Error crítico procesando archivos adicionales: " . $e->getMessage(), __FILE__, __LINE__);
  }


  error_log("Total archivos guardados: " . $archivos_guardados . " de " . $total_archivos);

  return $archivos_guardados;
}
