<?php

use App\Generic\CacheManager;
use App\Generic\PermissionUser;
use Micro\Helpers\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;

session_start();
include __DIR__ . '/../../includes/BD_con/db_con.php';
include __DIR__ . '/../../includes/Config/database.php';
include_once __DIR__ . '/../funcphp/func_gen.php';
include_once __DIR__ . '/../../views/infoEnti/infoEnti.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
date_default_timezone_set('America/Guatemala');
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$condi = $_POST["condi"];
mysqli_set_charset($conexion, 'utf8');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");

$condi = $_POST["condi"];

switch ($condi) {
    case 'acceso':
        if (!($csrf->validateToken($_POST[$csrf->getTokenName()]))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                false,
                $errorcsrf,
                'icon' => 'warning',
                'title' => "CSRF inválido"
            );
            echo json_encode($opResult);
            return;
        }
        $direccion_ip = $_SERVER['REMOTE_ADDR'];
        $agente_usuario = $_SERVER['HTTP_USER_AGENT'];
        $hostname = gethostbyaddr($direccion_ip);
        if ($hostname === false || $hostname === $direccion_ip) {
            $hostname = "No se pudo obtener el nombre de host";
        }

        $token_recaptcha = $_POST['token'];
        // echo json_encode([false, 'holi: ' . $token_recaptcha]);
        // return;

        $secretKey = '6Ld1-g0qAAAAAFuATmYYNSe6yE93sJeo84BpymNy';
        // $secretKey = '6Ld1-g0qAAAAAFuATmYYNSe6yE93sJeo84BpymNy2'; //prueba no mas
        $responseKey = $token_recaptcha;

        $url = 'https://www.google.com/recaptcha/api/siteverify';
        $data = [
            'secret' => $secretKey,
            'response' => $responseKey,
            'remoteip' => $direccion_ip
        ];

        $options = [
            'http' => [
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'method' => 'POST',
                'content' => http_build_query($data)
            ]
        ];

        $context = stream_context_create($options);
        $response = file_get_contents($url, false, $context);
        $responseData = json_decode($response);
        $puntuacion = $responseData->score ?? 0;
        $respuesta = $responseData->success ?? false;
        $accion = $responseData->action ?? "unknown";

        // if (true) {
        if ($respuesta && $puntuacion >= 0.5) {
            // echo json_encode([false, 'Puntuacion: ' . $responseData->score]);

            // Verificación exitosa
            // echo json_encode([false, 'reCAPTCHA verificado exitosamente.']);
            // return;
        } else {
            // Verificación fallida
            $errores = [];
            $mensaje = "La verificación de reCAPTCHA falló. ";
            if (!empty($responseData->{'error-codes'})) {
                foreach ($responseData->{'error-codes'} as $error) {
                    array_push($errores, $error);
                    $mensaje .= $error . ', ';
                }
            }
            $mensaje .= ' Puntuacion: ' . $puntuacion;
            logerrores($mensaje, __FILE__, __LINE__);
            // echo json_encode([false, $mensaje, $errores]);
            // return;
        }

        $user = $_POST["usuario"];
        $pass = $_POST["password"];

        $showmensaje = false;
        $icon = "error";
        $title = "¡ERROR!";
        try {
            $database->openConnection();

            $result = $database->selectColumns('tb_agencia', ['id_agencia']);
            $id_agencia = $result[0]['id_agencia'];

            $infoEnti = infoEntidad($id_agencia, $database, $db_name_general);
            $estado_entidad = $infoEnti['estado'];
            $fecha_pago = $infoEnti['fecha_pago'];
            $id_institucion = $infoEnti['id_institucion'];

            if ($estado_entidad == 2) {
                $showmensaje = true;
                $icon = "warning";
                $title = "¡Bloqueo temporal!";
                throw new Exception("El sistema se encuentra bloqueado por varios intentos fallidos de inicio de sesión de un usuario.");
            }
            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++  VERIFICACION DE LOS INTENTOS PERMITIDOS +++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if (isset($_SESSION['intentos'])) {
                if ($_SESSION['intentos'] >= 5) {
                    //REGISTRAR EN EL LOG LOS DATOS INGRESADOS
                    $datos = array(
                        "id_tb_usuario" => 0,
                        "fecha_inicio" => $hoy2,
                        "fecha_fin" => NULL,
                        "ip_direccion" => $direccion_ip,
                        "hostname" => $hostname,
                        "user_agent" => $agente_usuario,
                        "token" => "",
                        "status" => 0,
                        "info_adicional" => ('El numero de intentos superó el limite establecido, USER: ' . $user . '  PASS: ' . $pass)
                    );
                    $database->insert('tb_registro_login', $datos);

                    //BLOQUEAR EL ACCESO A LA INSTITUCION, 2: BLOQUEO TEMPORAL
                    $queryup = "UPDATE $db_name_general.info_coperativa SET `estado_pag`=2 WHERE id_cop=?";
                    $database->executeQuery($queryup, [$id_institucion]);

                    unset($_SESSION['intentos']);
                    $showmensaje = true;
                    throw new Exception("El numero de intentos superó el limite establecido");
                }
            }

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++  VERIFICACION DE CREDENCIALES DE USUARIO INGRESADOS ++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

            $passwordencritado = encriptar_desencriptar($key1, $key2, 'encrypt', $pass);
            $query = "SELECT * FROM `tb_usuario` tbu INNER JOIN `tb_agencia` tbg ON tbu.id_agencia=tbg.id_agencia WHERE `usu`=? AND `pass`=?";
            $datauser = $database->getAllResults($query, [$user, $passwordencritado]);
            if (empty($datauser)) {
                $_SESSION['intentos'] = isset($_SESSION['intentos']) ? $_SESSION['intentos'] + 1 : 1;
                $showmensaje = true;
                throw new Exception("Usuario / contraseña incorrecto");
            }

            $id_usuario = $datauser[0]['id_usu'];
            $id_agencia = $datauser[0]['id_agencia'];
            $estado_user = $datauser[0]['estado'];
            $passvencimiento = $datauser[0]['exp_date'];

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++  VERIFICACION DE ESTADO DEL USUARIO AUTENTICADO +++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($estado_user != 1) {
                $showmensaje = true;
                throw new Exception("Usuario inactivo");
            }
            if ($hoy > $passvencimiento) {
                $showmensaje = true;
                throw new Exception("Contraseña vencida, contacte con el Administrador");
            }
            $date15days = date('Y-m-d', strtotime($passvencimiento . ' - 15 days'));
            if ($hoy >= $date15days) {
                //CREAR ALERTA
                $result22 = $database->selectColumns('tb_alerta', ['id', 'estado'], 'cod_aux=? AND tipo_alerta="PASS"', [$id_usuario]);
                $datosalert = array(
                    "puesto" => "ADM",
                    "tipo_alerta" => "PASS",
                    "mensaje" => ("CAMBIAR CONTRASEÑA"),
                    "cod_aux" => $id_usuario,
                    "codDoc" => " ",
                    "proceso" => "X",
                    "estado" => "1",
                    "fecha" => $passvencimiento,
                    "created_by" => $id_usuario,
                    "created_at" => $hoy2,
                );
                if (empty($result22)) {
                    $database->insert('tb_alerta', $datosalert);
                } else {
                    if ($result22[0]["estado"] != 1) {
                        $database->update('tb_alerta', $datosalert, "id=?", [$result22[0]["id"]]);
                    }
                }
            } else {
                $database->update('tb_alerta', array("estado" => 0), "cod_aux=? AND tipo_alerta='PASS'", [$id_usuario]);
            }

            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++  VERIFICACION DE ESTADO DEL SISTEMA DE LA INSTITUCION ++++++++++++++
                +++++++++++++++++++++++++ EXCEPTUANDO AL USUARIO ADMINISTRADOR +++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            if ($id_usuario != 4 && $estado_entidad != 1) {
                $showmensaje = true;
                $icon = "warning";
                if ($estado_entidad == 0) {
                    $title = "¡Pago pendiente!";
                    throw new Exception("El sistema se encuentra bloqueado por falta de pago, favor de cancelar su cuota… gracias att: La administración de SOTECPRO.");
                } else {
                    $title = "¡Bloqueo temporal!";
                    throw new Exception("El sistema se encuentra bloqueado por varios intentos fallidos de inicio de sesión de un usuario.");
                }
            }

            // Calcular la fecha de pago más 5 días
            $fecha_mas_dias = date('Y-m-d', strtotime($fecha_pago . ' + 5 days'));

            if ($fecha_mas_dias <= $hoy) {
                $queryup = "UPDATE $db_name_general.info_coperativa SET `estado_pag`=0 WHERE id_cop=?";
                $database->executeQuery($queryup, [$id_institucion]);

                $showmensaje = true;
                $icon = "warning";
                $title = "¡Pago pendiente!";
                throw new Exception("El sistema se encuentra bloqueado por falta de pago, favor de cancelar su cuota… gracias att: La administración de SOTECPRO.");
            }
            $id_sesion = session_id();
            /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++  LOGIN EXITOSO, SE REGISTRA EN LA TABLA LOG ++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
            $datos = array(
                "id_tb_usuario" => $id_usuario,
                "fecha_inicio" => $hoy2,
                "fecha_fin" => NULL,
                "ip_direccion" => $direccion_ip,
                "hostname" => $hostname,
                "user_agent" => $agente_usuario,
                "token" => $id_sesion,
                "status" => 1,
                "info_adicional" => "Exitoso! con:  $puntuacion"
            );
            $idreglogin = $database->insert('tb_registro_login', $datos);
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if ($status == 0) {
            $opResult = array(
                false,
                $mensaje,
                'icon' => $icon,
                'title' => $title
            );
            echo json_encode($opResult);
            return;
        }

        // $exp_date = $datauser[0]['exp_date'];
        $puesto = $datauser[0]['puesto'];

        // $NIGGA = ValidaDatePass($exp_date, $id_usuario, $puesto, $conexion);

        $_SESSION['id'] = $id_usuario;
        $_SESSION['nombre'] = ($datauser[0]['nombre']);
        $_SESSION['apellido'] = ($datauser[0]['apellido']);
        $_SESSION['dpi'] = $datauser[0]['dpi'];
        $_SESSION['usu'] = $datauser[0]['usu'];
        $_SESSION['puesto'] = $puesto;
        $_SESSION['id_agencia'] = $id_agencia;
        $_SESSION['agencia'] = $datauser[0]['cod_agenc'];
        $_SESSION['nomagencia'] = $datauser[0]['nom_agencia'];
        $_SESSION['background'] = 0;
        $_SESSION['idreglogin'] = $idreglogin;


        if (isset($_SESSION["intentos"])) {
            unset($_SESSION["intentos"]);
        }

        http_response_code(200);
        echo json_encode([true, 'Acceso satisfactorio', $_SESSION]);
        mysqli_close($conexion);
        break;
    case 'renew_ses':

        if(!isset($_POST['token_renovacion']) || empty($_POST['token_renovacion'])) {
            $opResult = array(
                false,
                "Token de renovación es requerido",
                'icon' => 'warning',
                'title' => "Token requerido"
            );
            echo json_encode($opResult);
            return;
        }

        $decryptedID = $secureID->decrypt($_POST['token_renovacion']);
        // Log::info("Token de renovación: $decryptedID");
        if ($decryptedID !== 'reloginKeyUniqueXD') {
            $opResult = array(
                false,
                "Token de renovación inválido",
                'icon' => 'warning',
                'title' => "Token inválido"
            );
            echo json_encode($opResult);
            return;
        }

        $user = $_POST["usuario"];
        $pass = $_POST["password"];

        // MEDIDAS DE SEGURIDAD ADICIONALES
        $direccion_ip = $_SERVER['REMOTE_ADDR'];
        $agente_usuario = $_SERVER['HTTP_USER_AGENT'];

        // Limitar intentos de renovación por IP
        $intentos_key = 'renovar_intentos_' . md5($direccion_ip);
        if (!isset($_SESSION[$intentos_key])) {
            $_SESSION[$intentos_key] = 0;
        }

        if ($_SESSION[$intentos_key] >= 3) {
            $opResult = array(
                false,
                "Demasiados intentos de renovación. Intente más tarde.",
                'icon' => 'warning',
                'title' => "Límite de intentos excedido"
            );
            echo json_encode($opResult);
            return;
        }

        $showmensaje = false;
        $icon = "error";
        $title = "¡ERROR!";

        try {
            $database->openConnection();

            // Verificar credenciales básicas
            $passwordencritado = encriptar_desencriptar($key1, $key2, 'encrypt', $pass);
            $query = "SELECT * FROM `tb_usuario` tbu INNER JOIN `tb_agencia` tbg ON tbu.id_agencia=tbg.id_agencia WHERE `usu`=? AND `pass`=?";
            $datauser = $database->getAllResults($query, [$user, $passwordencritado]);

            if (empty($datauser)) {
                $_SESSION[$intentos_key]++;
                $showmensaje = true;
                throw new Exception("Usuario / contraseña incorrecto");
            }

            $id_usuario = $datauser[0]['id_usu'];
            $id_agencia = $datauser[0]['id_agencia'];
            $estado_user = $datauser[0]['estado'];
            $passvencimiento = $datauser[0]['exp_date'];

            // Verificar estado del usuario
            if ($estado_user != 1) {
                $showmensaje = true;
                throw new Exception("Usuario inactivo");
            }

            if ($hoy > $passvencimiento) {
                $showmensaje = true;
                throw new Exception("Contraseña vencida, contacte con el Administrador");
            }

            // MEDIDA DE SEGURIDAD ADICIONAL: Verificar que el usuario que intenta renovar
            // sea el mismo que tenía la sesión (si aún existe información de sesión)
            if (isset($_SESSION['id']) && $_SESSION['id'] != $id_usuario) {
                $showmensaje = true;
                throw new Exception("No se puede renovar la sesión de un usuario diferente");
            }

            // Registrar el evento de renovación de sesión en el log
            $datos_log = array(
                "id_tb_usuario" => $id_usuario,
                "fecha_inicio" => $hoy2,
                "fecha_fin" => NULL,
                "ip_direccion" => $direccion_ip,
                "hostname" => gethostbyaddr($direccion_ip),
                "user_agent" => $agente_usuario,
                "token" => session_id(),
                "status" => 1,
                "info_adicional" => "Renovación de sesión exitosa"
            );
            $database->insert('tb_registro_login', $datos_log);

            // Regenerar sesión con los datos del usuario
            $_SESSION['id'] = $id_usuario;
            $_SESSION['nombre'] = $datauser[0]['nombre'];
            $_SESSION['apellido'] = $datauser[0]['apellido'];
            $_SESSION['dpi'] = $datauser[0]['dpi'];
            $_SESSION['usu'] = $datauser[0]['usu'];
            $_SESSION['puesto'] = $datauser[0]['puesto'];
            $_SESSION['id_agencia'] = $id_agencia;
            $_SESSION['agencia'] = $datauser[0]['cod_agenc'];
            $_SESSION['nomagencia'] = $datauser[0]['nom_agencia'];
            $_SESSION['background'] = 0;

            // Limpiar contador de intentos al renovar exitosamente
            unset($_SESSION[$intentos_key]);

            $status = 1;
            $mensaje = 'Sesión renovada exitosamente';
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if ($status == 0) {
            $opResult = array(
                false,
                $mensaje,
                'icon' => $icon,
                'title' => $title
            );
            echo json_encode($opResult);
            return;
        }

        // Respuesta exitosa
        echo json_encode([true, $mensaje]);
        break;

    case 'generar_token_renovacion':
        // Generar un token único para renovación de sesión
        $token_renovacion = bin2hex(random_bytes(32));
        $timestamp = time();

        // Almacenar en sesión con timestamp (válido por 5 minutos)
        $_SESSION['token_renovacion'] = [
            'token' => $token_renovacion,
            'timestamp' => $timestamp,
            'usado' => false
        ];

        echo json_encode([true, 'Token generado', 'token' => $token_renovacion]);
        break;

    case 'salir':
        $showmensaje = false;
        $icon = "error";
        $title = "¡ERROR!";
        if (isset($_SESSION["idreglogin"])) {
            try {
                $database->openConnection();
                $datos = array(
                    "fecha_fin" => $hoy2,
                    "status" => 0,
                );
                $database->update('tb_registro_login', $datos, "id=?", [$_SESSION["idreglogin"]]);
                $status = 1;
            } catch (Exception $e) {
                if (!$showmensaje) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                }
                $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
                $status = 0;
            } finally {
                $database->closeConnection();
            }
        }

        session_unset();
        //Destruìmos la sesión
        session_destroy();
        http_response_code(200);
        echo json_encode([false, 'Sesion eliminada', $_SESSION]);
        break;
    case 'savetoken':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelva a iniciar sesion nuevamente', '0', "reprint" => 1, "timer" => 1500]);
            return;
        }
        $inputs = $_POST["inputs"];
        $token = $inputs[0];
        $codcsrf = $inputs[1];
        if (!($csrf->validateToken($codcsrf))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            echo json_encode([$errorcsrf, 0, "reprint" => 1, "timer" => 1500]);
            return;
        }

        if ($token == "") {
            echo json_encode(['Ingrese un token válido', '0', "reprint" => 1, "timer" => 1500]);
            return;
        }

        try {
            $database->openConnection();
            $result = $database->selectColumns('huella_tkn_auto', ['id', 'estado'], 'token=?', [$token]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("Ingrese un token autorizado porfavor!");
            }
            if ($result[0]["estado"] != 0) {
                $showmensaje = true;
                throw new Exception("El token ingresado ya está en uso o ha expirado.");
            }
            $direccion_ip = $_SERVER['REMOTE_ADDR'];
            $hostname = gethostbyaddr($direccion_ip);
            if ($hostname === false || $hostname === $direccion_ip) {
                $hostname = "No se pudo obtener el nombre de host";
            }

            $datos = array(
                "codusu" => $_SESSION['id'],
                "estado" => 1,
                "hostname" => $hostname,
                "updated_by" => $_SESSION['id'],
                "updated_at" => $hoy2,
            );
            $database->beginTransaction();
            $database->update('huella_tkn_auto', $datos, "id=?", [$result[0]["id"]]);
            $database->commit();
            $status = 1;
            $mensaje = "Token registrado correctamente";
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
        echo json_encode([$mensaje, $status, "reprint" => 1, "timer" => 1500]);
        break;
    case 'deletetoken':
        // $inputs = $_POST["inputs"];
        // $token = $inputs[0];
        $inputs = $_POST["ideliminar"];
        $token = $inputs[0];
        $codcsrf = $inputs[1];
        if (!($csrf->validateToken($codcsrf))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            echo json_encode([$errorcsrf, 0, "reprint" => 1, "timer" => 1500]);
            return;
        }

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelva a iniciar sesion nuevamente', '0', "reprint" => 1, "timer" => 1500]);
            return;
        }
        try {
            $database->openConnection();
            $result = $database->selectColumns('huella_tkn_auto', ['id'], 'token=?', [$token]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró el token");
            }

            $datos = array(
                "estado" => 2,
                "deleted_by" => $_SESSION['id'],
                "deleted_at" => $hoy2,
            );
            $database->beginTransaction();
            $database->update('huella_tkn_auto', $datos, "id=?", [$result[0]["id"]]);
            $database->commit();
            $status = 1;
            $mensaje = "Token removido correctamente";
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
        echo json_encode([$mensaje, $status, "reprint" => 1, "timer" => 1500]);
        break;
    case 'validar_usuario_por_mora':
        if ($_POST['username'] == "") {
            http_response_code(400);
            echo json_encode(['Debe llenar el campo de usuario', '0']);
            return;
        }
        if ($_POST['pass'] == "") {
            http_response_code(400);
            echo json_encode(['Debe llenar el campo de contraseña', '0']);
            return;
        }

        //CONSULTA DE USUARIOS
        $aux_consulta = "SELECT * FROM `tb_usuario` tbu
        INNER JOIN `tb_agencia` tbg ON tbu.id_agencia=tbg.id_agencia 
        WHERE (`puesto`='ADM' OR `puesto`='AAD' OR `puesto`='COO' OR `puesto`='GER' OR `puesto`='ANA' OR `puesto`='JAG') AND `usu`='" . $_POST['username'] . "'";
        $consulta = mysqli_query($conexion, $aux_consulta);

        if ($consulta) {
            if (mysqli_num_rows($consulta) > 0) {
                //convertir a hash
                $passwordencritado = encriptar_desencriptar($key1, $key2, 'encrypt', $_POST['pass']);
                //consultar si existe el usuario
                $aux_consulta = "SELECT * FROM `tb_usuario` tbu
                INNER JOIN `tb_agencia` tbg ON tbu.id_agencia=tbg.id_agencia 
                WHERE (`puesto`='ADM' OR `puesto`='AAD' OR `puesto`='COO' OR `puesto`='GER' OR `puesto`='ANA' OR `puesto`='JAG') AND `usu`='" . $_POST['username'] . "' AND `pass`='" . $passwordencritado . "'";
                $consulta = mysqli_query($conexion, $aux_consulta);

                if ($consulta) {
                    if (mysqli_num_rows($consulta) > 0) {
                        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                            $id = encode_utf8($fila['id_usu']);
                            $estado = encode_utf8($fila['estado']);
                        }
                        if ($estado == 1) {
                            http_response_code(200);
                            echo json_encode(['Cambio de mora autorizado', '1', true, $id]);
                        } else {
                            http_response_code(200);
                            echo json_encode(['Usuario inactivo', '0', false]);
                        }
                    } else {
                        http_response_code(200);
                        echo json_encode(['Usuario / contraseña incorrecto', '0', false]);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['Error al consultar el usuario y la contraseña', '0', false]);
                }
            } else {
                http_response_code(200);
                echo json_encode(['Usuario no encontrado con los permisos necesarios', '0', false]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['Error en el sistema de la base de datos', '0', false]);
        }
        mysqli_close($conexion);
        break;

    case 'validar_usuario_por_interes':

        $data =  $_POST['datos'];
        $usu = $data[0][0];
        $pass = $data[1];

        if ($usu == "") {
            http_response_code(400);
            echo json_encode(['Debe llenar el campo de usuario', '0']);
            return;
        }
        if ($pass == "") {
            http_response_code(400);
            echo json_encode(['Debe llenar el campo de contraseña', '0']);
            return;
        }
        //CONSULTA DE USUARIOS
        $aux_consulta = "SELECT * FROM `tb_usuario` tbu
                INNER JOIN `tb_agencia` tbg ON tbu.id_agencia=tbg.id_agencia 
                WHERE (`puesto`='ADM' OR `puesto`='AAD' OR `puesto`='COO' OR `puesto`='GER' OR `puesto`='ANA') AND `usu`='" . $usu . "'";

        $consulta = mysqli_query($conexion, $aux_consulta);

        if ($consulta) {
            if (mysqli_num_rows($consulta) > 0) {
                //convertir a hash
                $passwordencritado = encriptar_desencriptar($key1, $key2, 'encrypt', $pass);
                //consultar si existe el usuario

                // echo $usu.' - slc - '.$passwordencritado ; 
                // return; 

                $aux_consulta = "SELECT * FROM `tb_usuario` tbu
                        INNER JOIN `tb_agencia` tbg ON tbu.id_agencia=tbg.id_agencia 
                        WHERE (`puesto`='ADM' OR `puesto`='AAD' OR `puesto`='COO' OR `puesto`='GER' OR `puesto`='ANA') AND `usu`='" . $usu . "' AND `pass`='" . $passwordencritado . "'";
                $consulta = mysqli_query($conexion, $aux_consulta);

                if ($consulta) {
                    if (mysqli_num_rows($consulta) > 0) {
                        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                            $id = encode_utf8($fila['id_usu']);
                            $estado = encode_utf8($fila['estado']);
                        }
                        if ($estado == 1) {
                            http_response_code(200);
                            echo json_encode(['Cambio de interes, autorizado', '1', true, $id]);
                        } else {
                            http_response_code(200);
                            echo json_encode(['Usuario inactivo', '0', false]);
                        }
                    } else {
                        http_response_code(200);
                        echo json_encode(['Contraseña incorrecto', '0', false]);
                    }
                } else {
                    http_response_code(400);
                    echo json_encode(['Error al consultar el usuario y la contraseña', '0', false]);
                }
            } else {
                http_response_code(200);
                echo json_encode(['Usuario no encontrado con los permisos necesarios', '0', false]);
            }
        } else {
            http_response_code(400);
            echo json_encode(['Error en el sistema de la base de datos', '0', false]);
        }
        mysqli_close($conexion);
        break;

    case 'create_user':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        //validaciones
        if ($selects[0] == "0") {
            echo json_encode(['Debe selecionar una agencia', '0']);
            return;
        }
        if ($inputs[0] == "") {
            echo json_encode(['Debe llenar el campo nombres', '0']);
            return;
        }
        if ($inputs[1] == "") {
            echo json_encode(['Debe llenar el campo apellidos', '0']);
            return;
        }
        if ($inputs[2] == "") {
            echo json_encode(['Debe digitar un numero de identificacíon', '0']);
            return;
        }
        if (!is_numeric($inputs[2]) || strlen($inputs[2]) !=  13) {
            echo json_encode(['Debe digitar un numero de DPI válido', '0']);
            return;
        }
        if ($selects[1] == "0") {
            echo json_encode(['Debe selecionar un cargo', '0']);
            return;
        }
        if ($inputs[3] == "") {
            echo json_encode(['Debe digitar un correo electrónico', '0']);
            return;
        }
        //validar el correo con formato valido
        if (!filter_var($inputs[3], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['Debe digitar un correo válido', '0']);
            return;
        }
        if ($inputs[4] == "") {
            echo json_encode(['Debe digitar un nombre de usuario', '0']);
            return;
        }
        //validar la segurida de la contraseña
        $minusculas  =  preg_match('`[a-z]`',  $inputs[4]);
        $numeros =  preg_match('`[0-9]`',  $inputs[4]);
        if (!$minusculas   || !$numeros || strlen($inputs[4]) <  8) {
            echo json_encode(['El usuario debe tener un minimo de 8 caracteres y al menos un numero', '0']);
            return;
        }

        if ($inputs[5] == "") {
            echo json_encode(['Debe digitar una contraseña', '0']);
            return;
        }
        if ($inputs[6] == "") {
            echo json_encode(['Debe digitar la confirmacion de la contraseña', '0']);
            return;
        }
        //validar la confirmacion
        if ($inputs[5] != $inputs[6]) {
            echo json_encode(['La confirmacion de contraseña no es igual a la contraseña', '0']);
            return;
        }
        //validar estado inactivo
        // if ($selects[2] == "0") {
        //     echo json_encode(['Debe selecionar un cargo', '0']);
        //     return;
        // }

        //validar la segurida de la contraseña
        $mayusculas  =  preg_match('`[A-Z]`',  $inputs[5]);
        $minusculas  =  preg_match('`[a-z]`',  $inputs[5]);
        $numeros =  preg_match('`[0-9]`',  $inputs[5]);
        $specialChars  =  preg_match('`[^ \^\!\@\#\$\%\/\*\¡\¿\?]`',  $inputs[5]);

        if (!$mayusculas  || !$minusculas   || !$numeros || !$specialChars || strlen($inputs[5]) <  8) {
            echo json_encode(['La contraseña debe tener al menos 8 caracteres de longitud y debe incluir al menos una mayúscula, un número y un carácter especial', '0']);
            return;
        }

        //consultar sino existe un usuario con ese nombre
        $verificar = mysqli_query($conexion, "SELECT * FROM tb_usuario WHERE usu='" . $inputs[4] . "'");
        $bandera = false;
        while ($fila = mysqli_fetch_array($verificar, MYSQLI_ASSOC)) {
            $bandera = true;
        }
        if ($bandera) {
            echo json_encode(['No se puede registrar al usuario porque ya existe', '0']);
            return;
        }
        //encriptar_password
        $passwordencritado = encriptar_desencriptar($key1, $key2, 'encrypt', $inputs[5]);
        $fechax6 = date("Y-m-d", strtotime($hoy2 . "+6 months"));
        //realizar la insercion
        $conexion->autocommit(false);
        try {
            $res = $conexion->query("INSERT INTO `tb_usuario`(`nombre`, `apellido`, `dpi`, `usu`, `pass`, `estado`,`puesto`,`id_agencia`,`email`,`created_by`,`created_at`,`exp_date`) 
            VALUES ('$inputs[0]','$inputs[1]','$inputs[2]','$inputs[4]','$passwordencritado',1,'$selects[1]','$selects[0]','$inputs[3]','$archivo[0]','$hoy2','$fechax6')");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['Error al momento de registrar los datos', '0']);
                // echo json_encode([$aux, '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                echo json_encode(['Registro satisfactorio', '1']);
            } else {
                $conexion->rollback();
                echo json_encode(['Registro no ingresado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'update_user':
        //valores de los inputs
        $inputs = $_POST["inputs"];
        //Selects de datos 
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];
        //validaciones
        if ($inputs[7] == "") {
            echo json_encode(['No ha seleccionado un registro para actualizar', '0']);
            return;
        }
        if ($selects[0] == "0") {
            echo json_encode(['Debe selecionar una agencia', '0']);
            return;
        }
        if ($inputs[0] == "") {
            echo json_encode(['Debe llenar el campo nombres', '0']);
            return;
        }
        if ($inputs[1] == "") {
            echo json_encode(['Debe llenar el campo apellidos', '0']);
            return;
        }
        if ($inputs[2] == "") {
            echo json_encode(['Debe digitar un numero de identificacíon', '0']);
            return;
        }
        if (!is_numeric($inputs[2]) || strlen($inputs[2]) !=  13) {
            echo json_encode(['Debe digitar un numero de DPI válido', '0']);
            return;
        }
        if ($selects[1] == "0") {
            echo json_encode(['Debe selecionar un cargo', '0']);
            return;
        }
        if ($inputs[3] == "") {
            echo json_encode(['Debe digitar un correo electrónico', '0']);
            return;
        }
        //validar el correo con formato valido
        if (!filter_var($inputs[3], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['Debe digitar un correo válido', '0']);
            return;
        }
        if ($inputs[4] == "") {
            echo json_encode(['Debe digitar un nombre de usuario', '0']);
            return;
        }
        //validar la segurida de la contraseña
        $minusculas  =  preg_match('`[a-z]`', $inputs[4]);
        $numeros =  preg_match('`[0-9]`', $inputs[4]);
        if (!$minusculas   || !$numeros || strlen($inputs[4]) <  8) {
            echo json_encode([$inputs[4], '0']);
            return;
        }
        if ($inputs[5] == "") {
            echo json_encode(['Debe digitar una contraseña', '0']);
            return;
        }
        if ($inputs[6] == "") {
            echo json_encode(['Debe digitar la confirmacion de la contraseña', '0']);
            return;
        }
        //validar la confirmacion
        if ($inputs[5] != $inputs[6]) {
            echo json_encode(['La confirmacion de contraseña no es igual a la contraseña', '0']);
            return;
        }
        //validar la segurida de la contraseña
        $mayusculas  =  preg_match('`[A-Z]`',  $inputs[5]);
        $minusculas  =  preg_match('`[a-z]`',  $inputs[5]);
        $numeros =  preg_match('`[0-9]`',  $inputs[5]);
        $specialChars  =  preg_match('`[^ \^\!\@\#\$\%\/\*\¡\¿\?]`',  $inputs[5]);

        if (!$mayusculas  || !$minusculas   || !$numeros || !$specialChars || strlen($inputs[5]) <  8) {
            echo json_encode(['La contraseña debe tener al menos 8 caracteres de longitud y debe incluir al menos una mayúscula, un número y un carácter especial', '0']);
            return;
        }
        // validar estado inactivo
        if ($selects[2] < 1 || $selects[2] > 2) {
            echo json_encode(['Debe seleccionar un estado', '0']);
            return;
        }
        //consultar sino existe un usuario con ese nombre
        $verificar = mysqli_query($conexion, "SELECT * FROM tb_usuario WHERE usu='" . $inputs[4] . "' AND id_usu!='" . $inputs[7] . "'");
        $bandera = false;
        while ($fila = mysqli_fetch_array($verificar, MYSQLI_ASSOC)) {
            $bandera = true;
        }
        if ($bandera) {
            echo json_encode(['No se puede actualizar el registro porque ya existe un usuario con los mismos datos', '0']);
            return;
        }
        //encriptar_password
        $passwordencritado = encriptar_desencriptar($key1, $key2, 'encrypt', $inputs[5]);
        $fechax6 = date("Y-m-d", strtotime($hoy2 . "+6 months"));
        //realizar la insercion
        $conexion->autocommit(false);
        try {
            $res = $conexion->query("UPDATE `tb_usuario` set `nombre`= '$inputs[0]', `apellido`= '$inputs[1]', `dpi`= '$inputs[2]', `usu`= '$inputs[4]', `pass`= '$passwordencritado', `estado`= '$selects[2]', `puesto`= '$selects[1]',`id_agencia`= '$selects[0]',`email`= '$inputs[3]',`updated_by`= '$archivo[0]',`updated_at`= '$hoy2',`exp_date`='$fechax6' WHERE id_usu='$inputs[7]'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['Error al momento de actualizar los datos', '0']);
                // echo json_encode([$aux, '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                if ($_SESSION['id'] == $inputs[7]) {
                    echo json_encode(['Registro actualizado satisfactoriamente', '1', '1']);
                } else {
                    echo json_encode(['Registro actualizado satisfactoriamente', '1', '0']);
                }
            } else {
                $conexion->rollback();
                echo json_encode(['Registro no actualizado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
        break;
    case 'delete_user':
        $id = $_POST['ideliminar'];
        //realizar la insercion
        $conexion->autocommit(false);
        try {
            $res = $conexion->query("UPDATE `tb_usuario` set `estado`= '0',`deleted_by`= '" . $_SESSION['id'] . "',`deleted_at`= '$hoy2' WHERE id_usu='$id'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['Error al momento de eliminar el registro', '0']);
                // echo json_encode([$aux, '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
                if ($_SESSION['id'] == $id) {
                    echo json_encode(['Registro eliminado satisfactoriamente', '1', '1']);
                } else {
                    echo json_encode(['Registro eliminado satisfactoriamente', '1', '0']);
                }
            } else {
                $conexion->rollback();
                echo json_encode(['Registro no eliminado satisfactoriamente', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        break;


    case 'get_password':
        $id = $_POST["id"];
        $verificar = mysqli_query($conexion, "SELECT pass FROM tb_usuario WHERE id_usu='" . $id . "'");
        $sindesencriptar = "";
        while ($fila = mysqli_fetch_array($verificar, MYSQLI_ASSOC)) {
            $sindesencriptar = $fila['pass'];
        }
        //encriptar_password
        $passwordesencritado = encriptar_desencriptar($key1, $key2, 'decrypt', $sindesencriptar);
        echo json_encode($passwordesencritado);
        break;
    case 'create_permisos':
        //obtener las variables necesarias
        $id = $_POST['id_actual'];
        $usuario = $_POST['usuario'];

        //validar los campos vacios
        if ($id == "" || $usuario == "") {
            echo json_encode(['Debe seleccionar un usuario', '0']);
            return;
        }
        //validar que quiera insertar permisos de un usuario que ya tiene permisos
        $verificar = mysqli_query($conexion, "SELECT us.id_usu AS id_usuario, CONCAT(us.nombre,' ', us.apellido) AS nombre, cg.UsuariosCargoProfecional AS cargo, ag.nom_agencia AS nombreagen, ag.cod_agenc AS codagen FROM tb_usuario us
        INNER JOIN tb_permisos2 pe ON us.id_usu=pe.id_usuario
        INNER JOIN $db_name_general.tb_submenus ts ON pe.id_submenu=ts.id
        INNER JOIN $db_name_general.tb_menus tm ON ts.id_menu =tm.id
        INNER JOIN $db_name_general.tb_modulos td ON tm.id_modulo =td.id
        INNER JOIN $db_name_general.tb_permisos_modulos tbps ON td.id=tbps.id_modulo
        INNER JOIN $db_name_general.tb_usuarioscargoprofecional cg ON us.puesto=cg.id_UsuariosCargoProfecional
        INNER JOIN tb_agencia ag ON us.id_agencia=ag.id_agencia
        WHERE ts.estado='1' AND tm.estado='1' AND td.estado='1' AND tbps.estado='1' AND us.estado!='0' AND us.id_usu='$id' AND 
            tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1)
        GROUP BY us.id_usu 
        ORDER BY td.id, td.descripcion ASC");
        $bandera = false;
        while ($fila = mysqli_fetch_array($verificar, MYSQLI_ASSOC)) {
            $bandera = true;
        }
        if ($bandera) {
            echo json_encode(['No se pueden agregar los permisos al usuario seleccionado porque tal usuario ya tiene algunos permisos', '0']);
            return;
        }
        //validar que ha seleccionado permisos
        if (!isset($_POST['permisos'])) {
            echo json_encode(['No se puede realizar el registro porque no ha seleccionado al menos un permiso', '0']);
            return;
        }
        $permisos = $_POST['permisos'];

        //realizar las inserciones
        $bandera_insercion = false;
        $conexion->autocommit(false);
        for ($i = 0; $i < count($permisos); $i++) {
            //insercion en la base de datos
            $res = $conexion->query("INSERT INTO `tb_permisos2`(`id_usuario`, `id_submenu`) VALUES ('$id','$permisos[$i]')");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $bandera_insercion = true;
            }
            if (!$res) {
                $bandera_insercion = true;
            }
        }

        if ($bandera_insercion) {
            $conexion->rollback();
            echo json_encode(['Registro de permisos no satisfactorios', '0']);
        } else {
            $conexion->commit();
            echo json_encode(['Registro de permisos satisfactorios', '1']);
        }
        mysqli_close($conexion);
        break;
    case 'update_permisos':
        //obtener las variables necesarias
        $id = $_POST['id_actual'];
        $id_past = $_POST['id_pasado'];
        $usuario = $_POST['usuario'];

        // $cache = new CacheManager('permisos_');
        $adminPermisos = new PermissionUser($id);

        //validar los campos vacios
        if ($id == "" || $id_past == "" || $usuario == "") {
            echo json_encode(['Debe seleccionar un usuario', '0']);
            return;
        }
        if ($id != $id_past) {
            //validar que quiera insertar permisos de un usuario que ya tiene permisos
            $verificar = mysqli_query($conexion, "SELECT us.id_usu AS id_usuario, CONCAT(us.nombre,' ', us.apellido) AS nombre, cg.UsuariosCargoProfecional AS cargo, ag.nom_agencia AS nombreagen, ag.cod_agenc AS codagen FROM tb_usuario us
            INNER JOIN tb_permisos2 pe ON us.id_usu=pe.id_usuario
            INNER JOIN $db_name_general.tb_submenus ts ON pe.id_submenu=ts.id
            INNER JOIN $db_name_general.tb_menus tm ON ts.id_menu =tm.id
            INNER JOIN $db_name_general.tb_modulos td ON tm.id_modulo =td.id
            INNER JOIN $db_name_general.tb_permisos_modulos tbps ON td.id=tbps.id_modulo
            INNER JOIN $db_name_general.tb_usuarioscargoprofecional cg ON us.puesto=cg.id_UsuariosCargoProfecional
            INNER JOIN tb_agencia ag ON us.id_agencia=ag.id_agencia
            WHERE ts.estado='1' AND tm.estado='1' AND td.estado='1' AND tbps.estado='1' AND us.estado!='0' AND us.id_usu='$id' AND 
                tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1)
            GROUP BY us.id_usu 
			ORDER BY td.id, td.descripcion ASC");
            $bandera = false;
            while ($fila = mysqli_fetch_array($verificar, MYSQLI_ASSOC)) {
                $bandera = true;
            }
            if ($bandera) {
                echo json_encode(['No se pueden editar los permisos al usuario seleccionado porque tal usuario ya tiene algunos permisos', '0']);
                return;
            }
        }
        //validar que ha seleccionado permisos
        if (!isset($_POST['permisos'])) {
            echo json_encode(['No se puede realizar el registro porque no ha seleccionado al menos un permiso', '0']);
            return;
        }
        $permisos = $_POST['permisos'];

        //eliminacion de permisos actuales para luego reescribir
        if ($id != $id_past) {
            //eliminar registro
            $conexion->autocommit(false);
            $res = $conexion->query("DELETE FROM `tb_permisos2` WHERE id_usuario='$id_past'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Error en la eliminación de permisos anteriores, intente nuevamente', '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
            } else {
                $conexion->rollback();
                echo json_encode(['Error en la eliminación de permisos anteriores, intente nuevamente', '0']);
                return;
            }
        }
        if ($id == $id_past) {
            //eliminar registro
            $conexion->autocommit(false);
            $res = $conexion->query("DELETE FROM `tb_permisos2` WHERE id_usuario='$id'");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $conexion->rollback();
                echo json_encode(['Error en la insercion de permisos, intente nuevamente', '0']);
                return;
            }
            if ($res) {
                $conexion->commit();
            } else {
                $conexion->rollback();
                echo json_encode(['Error en la insercion de permisos, intente nuevamente', '0']);
                return;
            }
        }

        //realizar las inserciones
        $bandera_insercion = false;
        $conexion->autocommit(false);
        for ($i = 0; $i < count($permisos); $i++) {
            //insercion en la base de datos
            $res = $conexion->query("INSERT INTO `tb_permisos2`(`id_usuario`, `id_submenu`) VALUES ('$id','$permisos[$i]')");
            $aux = mysqli_error($conexion);
            if ($aux) {
                $bandera_insercion = true;
            }
            if (!$res) {
                $bandera_insercion = true;
            }
        }

        if ($bandera_insercion) {
            $conexion->rollback();
            echo json_encode(['Actualización de permisos no satisfactorios', '0']);
        } else {
            $conexion->commit();
            echo json_encode(['Actualización de permisos satisfactorios', '1']);
            // $result = $cache->clear();
            $result = $adminPermisos->clearCache();
        }
        mysqli_close($conexion);
        break;
    case 'delete_permisos':
        $id = $_POST['ideliminar'];
        $conexion->autocommit(false);
        $res = $conexion->query("DELETE FROM `tb_permisos2` WHERE id_usuario='$id'");
        $aux = mysqli_error($conexion);
        if ($aux) {
            $conexion->rollback();
            echo json_encode(['Error en la eliminacion de los registros, intente nuevamente', '0']);
            return;
        }
        if ($res) {
            $conexion->commit();
            echo json_encode(['Registros eliminados correctamente', '1']);
        } else {
            $conexion->rollback();
            echo json_encode(['Error en la eliminacion de los registros, intente nuevamente', '0']);
        }
        mysqli_close($conexion);
        break;
    case 'obtener_permisos':
        $id = $_POST['id'];
        $datos = mysqli_query($conexion, "SELECT ts.id AS id_submenu FROM tb_usuario us
        INNER JOIN tb_permisos2 pe ON us.id_usu=pe.id_usuario
        INNER JOIN $db_name_general.tb_submenus ts ON pe.id_submenu=ts.id
        INNER JOIN $db_name_general.tb_menus tm ON ts.id_menu =tm.id
        INNER JOIN $db_name_general.tb_modulos td ON tm.id_modulo =td.id
        INNER JOIN $db_name_general.tb_permisos_modulos tbps ON td.id=tbps.id_modulo
        WHERE ts.estado='1' AND tm.estado='1' AND td.estado='1' AND tbps.estado='1' AND us.estado!='0' AND us.id_usu='$id' AND 
            tbps.id_cooperativa=(SELECT ag1.id_institucion FROM tb_agencia ag1 LIMIT 1)
        ORDER BY td.id, td.descripcion ASC");
        $data[] = [];
        $bandera = false;
        $i = 0;
        while ($fila = mysqli_fetch_array($datos, MYSQLI_ASSOC)) {
            $data[$i] = $fila;
            $bandera = true;
            $i++;
        }

        if ($bandera) {
            echo json_encode(['Se encontraron permisos', '1', $data]);
        } else {
            echo json_encode(['No se encontraron permisos disponibles', '0']);
        }
        mysqli_close($conexion);
        break;
    case 'redirect':
        $url = "";
        if ($type_host == '1') {
            $url = "/microsystemplus/views/";
        } else {
            $url = "/" . "views/";
        }
        if ($url == "") {
            echo json_encode(['No se encontro la ruta especificada', '0']);
        } else {
            echo json_encode([$url, '1']);
        }
        break;
    case 'confirmar_apertura_cierre_caja':
        if ($_POST['idusuario'] == "") {
            http_response_code(400);
            echo json_encode(['No se ha encontrado el identificador del usuario', '0']);
            return;
        }
        if ($_POST['pass'] == "") {
            http_response_code(400);
            echo json_encode(['Debe llenar el campo de contraseña', '0']);
            return;
        }

        //CONSULTA DE USUARIOS
        try {
            $stmt = $conexion->prepare("SELECT * FROM `tb_usuario` tbu WHERE tbu.id_usu=?");
            if (!$stmt) {
                $error = $conexion->error;
                http_response_code(400);
                echo json_encode(['Error preparando consulta 1: ' . $error, '0', false]);
                return;
            }
            $stmt->bind_param("s", $_POST['idusuario']);
            if (!$stmt->execute()) {
                $errorMsg = $stmt->error;
                http_response_code(400);
                echo json_encode(["Error en el sistema de la base de datos: $errorMsg", '0', false]);
                return;
            }
            $resultado = $stmt->get_result();
            $numFilas = $resultado->num_rows;
            if ($numFilas > 0) {
                //convertir a hash
                $passwordencritado = encriptar_desencriptar($key1, $key2, 'encrypt', $_POST['pass']);

                //comprobar si existe el usuario
                $stmt = $conexion->prepare("SELECT * FROM `tb_usuario` tbu WHERE tbu.id_usu=? AND tbu.pass=?");
                if (!$stmt) {
                    $error = $conexion->error;
                    http_response_code(400);
                    echo json_encode(['Error preparando consulta 2: ' . $error, '0', false]);
                    return;
                }
                $stmt->bind_param("ss", $_POST['idusuario'], $passwordencritado);
                if (!$stmt->execute()) {
                    $errorMsg = $stmt->error;
                    http_response_code(400);
                    echo json_encode(["Error al consultar la contraseña: $errorMsg", '0', false]);
                    return;
                }
                $resultado = $stmt->get_result();
                $numFilas = $resultado->num_rows;
                if ($numFilas > 0) {
                    while ($fila = $resultado->fetch_assoc()) {
                        $id = encode_utf8($fila['id_usu']);
                        $estado = encode_utf8($fila['estado']);
                    }
                    if ($estado == 1) {
                        http_response_code(200);
                        echo json_encode(['Confirmación válida', '1', true, $id]);
                    } else {
                        http_response_code(200);
                        echo json_encode(['Usuario inactivo', '0', false]);
                    }
                } else {
                    http_response_code(200);
                    echo json_encode(["Contraseña incorrecta", '0', false]);
                }
            } else {
                http_response_code(200);
                echo json_encode(['Usuario no existe en el sistema', '0', false]);
            }
        } catch (Exception $e) {
            $error = ($e->getMessage());
            http_response_code(400);
            echo json_encode(["Error interno: $error", '0', false]);
        }
        break;
    case 'modo':
        if (isset($_POST['color'])) {
            $_SESSION['color'] = ($_POST['color'] == 0) ? 1 : 0;
            $_SESSION['background'] = $_POST['color'];

            http_response_code(200);
            echo json_encode([true, 'listo', $_SESSION['background']]);
        } else {
            http_response_code(400);
            // echo json_encode($consulta);
            echo json_encode('Variable no declarada');
        }
        break;
    // FUNCION PARA CAMBIO DE CONTRASEÑA   
    case 'change_pass':
        $inputs = $_POST["inputs"];
        list($encryptedID, $passant, $newpass, $newpass2, $csrftoken) = $inputs;
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++  INICIO DE VALIDACIONES ++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++  [`cont`,`password`,`newpass`,`newpass2`,'<?= $csrf->getTokenName() ']) ++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $timer = 1500;

        if (!($csrf->validateToken($csrftoken))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            $opResult = array(
                $errorcsrf,
                0,
                "reprint" => 1,
                "timer" => 2000
            );
            echo json_encode($opResult);
            return;
        }
        $decryptedID = $secureID->decrypt($encryptedID);

        try {
            if ($newpass != $newpass2) {
                $showmensaje = true;
                throw new Exception("Las contraseñas no coinciden");
            }
            $mayusculas = preg_match('`[A-Z]`', $newpass);
            $minusculas = preg_match('`[a-z]`', $newpass);
            $numeros = preg_match('`[0-9]`', $newpass);
            $specialChars = preg_match('`[^ \^\!\@\#\$\%\/\*\¡\¿\?]`', $newpass);
            if (!$mayusculas || !$minusculas || !$numeros || !$specialChars || strlen($newpass) <  8) {
                $showmensaje = true;
                $timer = 3000;
                throw new Exception("La contraseña debe tener al menos 8 caracteres de longitud y debe incluir al menos una mayúscula, un número y un carácter especial");
            }
            $fechax3 = date("Y-m-d", strtotime($hoy2 . "+6 months"));

            $database->openConnection();
            $result = $database->selectColumns('tb_usuario', ['pass', 'id_usu', 'estado'], 'id_usu=?', [$decryptedID]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se logró encontrar el usuario a actualizar");
            }
            if ($result[0]["estado"] != 1) {
                $showmensaje = true;
                throw new Exception("El usuario no se encuentra activo");
            }
            $passdecript = encriptar_desencriptar($key1, $key2, 'decrypt', $result[0]["pass"]);
            if ($passdecript != $passant) {
                $showmensaje = true;
                throw new Exception("La contraseña actual no es la correcta");
            }
            if ($newpass == $passant) {
                $showmensaje = true;
                $timer = 3000;
                throw new Exception("La nueva contraseña debe ser diferente a la anterior.");
            }
            $passencript = encriptar_desencriptar($key1, $key2, 'encrypt', $newpass);
            $datos = array(
                "pass" => $passencript,
                "exp_date" => $fechax3,
                "updated_by" => $_SESSION['id'],
                "updated_at" => $hoy2,
            );
            $database->update('tb_usuario', $datos, "id_usu=?", [$decryptedID]);

            //QUITAR ALERTA SI EXISTE
            $result22 = $database->selectColumns('tb_alerta', ['id', 'estado'], 'cod_aux=? AND tipo_alerta="PASS"', [$_SESSION['id']]);
            if (!empty($result22)) {
                $datosalert = array(
                    "estado" => "0",
                    "updated_by" => $_SESSION['id'],
                    "updated_at" => $hoy2,
                );
                $database->update('tb_alerta', $datosalert, "id=?", [$result22[0]["id"]]);
            }
            $status = 1;
            $mensaje = "Contraseña actualizada correctamente";
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? " " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status, 1, "reprint" => 1, "timer" => $timer]);
        break;
    case 'parametrizaAgencia':
        $arch = $_POST['archivo'];
        // Crear la consulta SQL con marcadores de posición '?'
        $sql = "UPDATE tb_agencia set id_nomenclatura_caja = ? WHERE id_agencia = ? ";
        // Crear una sentencia preparada
        $stmt = $conexion->prepare($sql);

        if ($stmt) {
            // Vincular parámetros y valores a los marcadores de posición
            $stmt->bind_param("ii", $arch[0], $arch[1]);

            // Ejecutar la consulta preparada para insertar los datos
            if ($stmt->execute()) {
                echo json_encode(["Datos actualizados ", '1']);
                return;
            } else {
                echo json_encode(["Error al realizar la actualización ", '0']);
                return;
            }
            // Cerrar la sentencia preparada
            $stmt->close();
        } else {
            echo "Error en la consulta: ";
        }
        // Cerrar la conexión a la base de datos
        $conexion->close();
        break;


    case 'create_tokens':
        $tokens = $_POST['tokens'];
        $idusuario = $_SESSION["id"];
        $codusu = $idusuario;

        // Validar y procesar
        $response = validate_and_insert_tokens($tokens, $codusu, $conexion);

        // Envío JSON
        echo json_encode($response);
        break;

    case 'obtener_modulos_restringidos':
        try {
            // Iniciar transacción
            $conexion->autocommit(false);

            $query = "SELECT id, modulo_area, estado 
                         FROM $db_name_general.tb_restringido 
                         WHERE estado = '1'";

            $resultado = $conexion->query($query);

            if (!$resultado) {
                throw new Exception("Error en la consulta: " . $conexion->error);
            }

            if ($resultado->num_rows > 0) {
                $modulos = [];
                while ($row = $resultado->fetch_assoc()) {
                    $modulos[] = [
                        'id' => $row['id'],
                        'modulo_area' => $row['modulo_area']
                    ];
                }
                $conexion->commit();
                echo json_encode([$modulos, '1']);
            } else {
                throw new Exception("No se encontraron módulos activos");
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode([$e->getMessage(), '0']);
            error_log("Error en obtener_modulos_restringidos: " . $e->getMessage());
        } finally {
            if (isset($resultado)) {
                $resultado->close();
            }
            $conexion->close();
        }
        break;

    case 'create_doc_transacciones':
        //['<?= $csrf->getTokenName() ','nombre'], ['id_modulo','tipo','id_cuenta_contable'], [], 'create_doc_transacciones', '0', [tipo_doc])

        list($csrfToken, $nombre) = $_POST['inputs'];
        list($id_modulo, $tipo, $id_cuenta_contable) = $_POST['selects'];
        list($tipo_doc) = $_POST['archivo'];

        // Log::info("tipo_doc: $tipo_doc");

        if (!($csrf->validateToken($csrfToken))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            echo json_encode([$errorcsrf, 0, "reprint" => 1, "timer" => 1500]);
            return;
        }

        $idusuario = $_SESSION["id"];

        $showmensaje = false;
        try {
            if (empty($nombre)) {
                $showmensaje = true;
                throw new Exception("Debe ingresar un nombre para el documento");
            }

            if (empty($id_modulo) || empty($tipo) || empty($id_cuenta_contable)) {
                $showmensaje = true;
                throw new Exception("Debe seleccionar un módulo, tipo y cuenta contable");
            }

            $database->openConnection();
            $result = $database->selectColumns('ctb_nomenclatura', ['id'], 'estado=1 AND id=?', [$id_cuenta_contable]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("La cuenta contable seleccionada no es válida o no está activa.");
            }

            $database->beginTransaction();

            $tb_documentos_transacciones = [
                'id_modulo' => $id_modulo,
                'tipo' => $tipo,
                'nombre' => $nombre,
                'id_cuenta_contable' => $id_cuenta_contable,
                'tipo_dato' => $tipo_doc ?? 1,
                'estado' => 1,
                'created_by' => $idusuario,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $inserted = $database->insert('tb_documentos_transacciones', $tb_documentos_transacciones);

            $database->commit();

            $status = 1;
            $mensaje = "Documento de transacción creado correctamente";
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
        echo json_encode([$mensaje, $status, "reprint" => 1, "timer" => 1500]);

        break;

    case 'update_doc_transacciones':
        //['<?= $csrf->getTokenName() ','nombre'], ['id_modulo','tipo','id_cuenta_contable'], [], 'update_doc_transacciones', '0', [], 'id_documento']
        list($csrfToken, $nombre) = $_POST['inputs'];
        list($id_modulo, $tipo, $id_cuenta_contable) = $_POST['selects'];
        list($id_documento, $tipo_dato) = $_POST['archivo'];

        if (!($csrf->validateToken($csrfToken))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            echo json_encode([$errorcsrf, 0, "reprint" => 1, "timer" => 1500]);
            return;
        }
        $idusuario = $_SESSION["id"];
        $showmensaje = false;
        try {
            if (empty($nombre)) {
                $showmensaje = true;
                throw new Exception("Debe ingresar un nombre para el documento");
            }

            if (empty($id_modulo) || empty($tipo) || empty($id_cuenta_contable)) {
                $showmensaje = true;
                throw new Exception("Debe seleccionar un módulo, tipo y cuenta contable");
            }

            $database->openConnection();

            $verification = $database->selectColumns('tb_documentos_transacciones', ['id'], 'id=? AND estado=1', [$id_documento]);
            if (empty($verification)) {
                $showmensaje = true;
                throw new Exception("El documento de transacción seleccionado no es válido");
            }

            $result = $database->selectColumns('ctb_nomenclatura', ['id'], 'estado=1 AND id=?', [$id_cuenta_contable]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("La cuenta contable seleccionada no es válida o no está activa.");
            }

            $database->beginTransaction();

            $tb_documentos_transacciones = [
                'id_modulo' => $id_modulo,
                'tipo' => $tipo,
                'nombre' => $nombre,
                'id_cuenta_contable' => $id_cuenta_contable,
                'tipo_dato' => $tipo_dato ?? 1, // Asignar tipo_doc si está definido, de lo contrario usar 1
                'estado' => 1,
                'updated_by' => $idusuario,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $updated = $database->update('tb_documentos_transacciones', $tb_documentos_transacciones, 'id=?', [$id_documento]);
            $database->commit();

            $status = 1;
            $mensaje = "Documento de transacción actualizado correctamente";
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
        echo json_encode([$mensaje, $status, "reprint" => 1, "timer" => 1500]);
        break;

    case 'delete_doc_transacciones':
        list($csrfToken) = $_POST['inputs'];
        list($id_documento) = $_POST['archivo'];

        if (!($csrf->validateToken($csrfToken))) {
            $errorcsrf = "Por su seguridad, esta solicitud ha expirado. Por favor, actualice la página y vuelva a intentar la acción.";
            echo json_encode([$errorcsrf, 0, "reprint" => 1, "timer" => 1500]);
            return;
        }
        $idusuario = $_SESSION["id"];
        $showmensaje = false;
        try {
            if (empty($id_documento)) {
                $showmensaje = true;
                throw new Exception("Debe seleccionar un documento de transacción para eliminar");
            }

            $database->openConnection();

            $verification = $database->selectColumns('tb_documentos_transacciones', ['id'], 'id=? AND estado=1', [$id_documento]);
            if (empty($verification)) {
                $showmensaje = true;
                throw new Exception("El documento de transacción seleccionado no es válido");
            }

            $database->beginTransaction();

            $deleted = $database->update(
                'tb_documentos_transacciones',
                ['estado' => 0, 'deleted_at' => date('Y-m-d H:i:s'), 'deleted_by' => $idusuario],
                'id=?',
                [$id_documento]
            );

            $database->commit();
            $status = 1;
            $mensaje = "Documento de transacción eliminado correctamente";
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
        echo json_encode([$mensaje, $status, "reprint" => 1, "timer" => 1500]);

        break;
}

function validate_and_insert_tokens($tokens, $codusu, $conexion)
{
    $estado = 0;
    $cero = 0;
    $created_at = date('Y-m-d H:i:s');
    $token = '';

    // Verificar la cantidad 
    $query_check = "SELECT COUNT(*) as total FROM huella_tkn_auto";
    $result = $conexion->query($query_check);

    if ($result) {
        $row = $result->fetch_assoc();
        $total_records = $row['total'];
        // Calcular tokens 
        $remaining = 50 - $total_records;
        $tokens_to_insert = min(count($tokens), $remaining);
        if ($tokens_to_insert > 0) {
            // Preparar la consulta 
            $query = "INSERT INTO huella_tkn_auto (codusu, token, created_at, created_by, estado) VALUES (?, ?, ?, ?, ?)";
            if ($stmt = $conexion->prepare($query)) {
                //  parámetros: i = integer, s = string
                $stmt->bind_param("isssi", $cero, $token, $created_at, $codusu, $estado);
                $inserted_tokens = [];
                for ($i = 0; $i < $tokens_to_insert; $i++) {
                    $token = $tokens[$i];
                    // Ejecutar la consulta 
                    if ($stmt->execute()) {
                        $inserted_tokens[] = $token;
                    } else {
                        // retornar respuesta error f
                        $stmt->close();
                        return [
                            'status' => 0,
                            'mensaje' => 'Error al insertar el token.'
                        ];
                    }
                }
                $stmt->close();
                return [
                    'status' => 1,
                    'mensaje' => 'Tokens insertados correctamente.',
                    'tokens' => $inserted_tokens
                ];
            } else {
                return [
                    'status' => 0,
                    'mensaje' => 'Error en la preparación de la consulta.'
                ];
            }
        } else {
            return [
                'status' => 0,
                'mensaje' => 'El límite de 20 registros ha sido alcanzado.'
            ];
        }
    } else {
        return [
            'status' => 0,
            'mensaje' => 'Error al verificar el número de registros.'
        ];
    }
}


//funcion para encriptar y desencriptar usuarios
// TAMBIENSE USA EN USUARIO_01 (HACERLA REUTILIZABLE)
function encriptar_desencriptar($mykey1, $mykey2, $action = 'encrypt', $string = false)
{
    $action = trim($action);
    $output = false;
    $myKey = $mykey1;
    $myIV = $mykey2;
    $encrypt_method = 'AES-256-CBC';
    $secret_key = hash('sha256', $myKey);
    $secret_iv = substr(hash('sha256', $myIV), 0, 16);

    if ($action && ($action == 'encrypt' || $action == 'decrypt') && $string) {
        $string = trim(strval($string));

        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
        };

        if ($action == 'decrypt') {
            $output = openssl_decrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
        };
    };
    return $output;
};

//revisa si ya expiro su contraseña (NEGROY)
function ValidaDatePass($exp_date, $id, $puesto, $conexion)
{
    // Obtiene la fecha actual
    $current_date = date('Y-m-d');

    // Verifica si el cod_aux existe
    $check_query = "SELECT * FROM tb_alerta WHERE cod_aux = $id";
    $check_result = $conexion->query($check_query);

    if ($check_result->num_rows > 0) {
        // El cod_aux existe, realiza la actualización del estado
        if ($exp_date <= $current_date) {
            $update_query = "UPDATE tb_alerta SET estado = 1, updated_at='$current_date' WHERE cod_aux = $id";
        } else {
            $update_query = "UPDATE tb_alerta SET estado = 0, updated_at='$current_date' WHERE cod_aux = $id";;
        }
        $update_result = $conexion->query($update_query);
        return $update_result;
    } else {
        // El cod_aux no existe, crea una nueva alerta
        $insert_query = "INSERT INTO tb_alerta (puesto, tipo_alerta, mensaje, cod_aux, codDoc, proceso, estado, fecha, created_by, updated_by, created_at, updated_at) VALUES ('$puesto', 'PASS', 'CAMBIAR CONTRASEÑA', '$id', '123', 'X', 0, '$current_date', 4, 4, '$current_date', '$current_date')";
        $agregar = "UPDATE tb_usuario set exp_date = CURRENT_DATE WHERE id_usu = '$id'";
        // REALIZA LAS CONSULTAS
        $insert_result = $conexion->query($insert_query);
        $agregar_result = $conexion->query($agregar);
        return $agregar_result;
    }
    // SIN VALIDACIONES DE CONEXION POR QUE SOY UN HUEVON PISADO 	ᕕ(⌐■_■)ᕗ ♪♬
    /** FALTA UNA VALIDACION LA CUAL ES QUE SI NO HAY FECHGA DE EXPIRACION QUE SE AGREGE LA FECHA DE HPOY 
     * $agregar = "UPDATE tb_usuario set exp_date = CURRENT_DATE WHERE id_usu = '$id'";  */
}
