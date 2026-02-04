<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../../../includes/Config/database.php';
require_once __DIR__ . '/../../../../src/funcphp/func_gen.php';

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

$idusuario = $_SESSION['id'];

date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$idagencia = $_SESSION['id_agencia'];

$condi = $_POST["condi"];

switch ($condi) {
    case 'ccategory':
        //'<?= $csrf->getTokenName()','nombre','descripcion','minimo','maximo'],[],[],'ccategory','0',['category']
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];

        list($csrftoken, $nombre, $descripcion, $minimo, $maximo) = $inputs;

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
            [$nombre, "", 'El campo de nombre es obligatorio', 1],
            [$descripcion, "", 'Debe digitar una descripcion', 1],
            [$minimo, "", 'Digite un monto minimo para la categoria', 1],
            [$maximo, "", 'Digite un monto maximo para la categoria', 1],
        ]);
        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();
            $category = $database->selectColumns('kpi_categorys', ['nombre'], "nombre=? AND estado=1",[$nombre]);

            if (!empty($category)) {
                $showmensaje = true;
                throw new Exception("Ya existe una categoría con el nombre de $nombre");
            }
            $database->beginTransaction();
            $datos = array(
                "nombre" => $nombre,
                "descripcion" => $descripcion,
                "monto_minimo" => $minimo,
                "monto_maximo" => $maximo,
                "estado" => 1,
                "created_by" => $idusuario,
                "created_at" => $hoy2,
            );
            $database->insert("kpi_categorys",$datos);

            $database->commit();
            $status = 1;
            $mensaje = "Categoría creada exitosamente";
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
    case 'ucategory':
          //'<?= $csrf->getTokenName()','nombre','descripcion','minimo','maximo'],[],[],'ccategory','0',[id]
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $archivo = $_POST["archivo"];

        list($csrftoken, $nombre, $descripcion, $minimo, $maximo) = $inputs;
        list($encryptedID) = $archivo;

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
            [$nombre, "", 'El campo de nombre es obligatorio', 1],
            [$descripcion, "", 'Debe digitar una descripcion', 1],
            [$minimo, "", 'Digite un monto minimo para la categoria', 1],
            [$maximo, "", 'Digite un monto maximo para la categoria', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }
        $decryptedID = $secureID->decrypt($encryptedID);
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();

            $category = $database->selectColumns('kpi_categorys', ['nombre'], "id=?",[$decryptedID]);
            if (empty($category)) {
                $showmensaje = true;
                throw new Exception("No se logró encontrar la categoría a actualizar");
            }

            $category = $database->selectColumns('kpi_categorys', ['nombre'], "nombre=? AND id!=?",[$nombre,$decryptedID]);
            if (!empty($category)) {
                $showmensaje = true;
                throw new Exception("Ya existe una categoría con el nombre de $nombre");
            }

            $database->beginTransaction();
            $datos = array(
                "nombre" => $nombre,
                "descripcion" => $descripcion,
                "monto_minimo" => $minimo,
                "monto_maximo" => $maximo,
                "updated_by" => $idusuario,
                "updated_at" => $hoy2,
            );
            $database->update("kpi_categorys",$datos,"id=?",[$decryptedID]);

            $database->commit();
            $status = 1;
            $mensaje = "Categoría actualizada correctamente";
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

    case 'dcategory':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $encryptedID = $_POST["ideliminar"];
        $decryptedID = $secureID->decrypt($encryptedID);
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $showmensaje = false;
        try {
            $database->openConnection();
            $category = $database->selectColumns('kpi_categorys', ['nombre'], "id=?",[$decryptedID]);
            if (empty($category)) {
                $showmensaje = true;
                throw new Exception("No se logró encontrar la categoría a actualizar");
            }

            $database->beginTransaction();
            $datos = array(
                "estado" => 0,
                "deleted_by" => $idusuario,
                "deleted_at" => $hoy2,
            );

            $database->update("kpi_categorys",$datos,"id=?",[$decryptedID]);

            $database->commit();
            $status = 1;
            $mensaje = "Categoría eliminada correctamente";
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
}
