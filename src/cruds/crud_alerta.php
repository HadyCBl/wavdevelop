<?php

use App\DatabaseAdapter;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\PermissionManager;
use Micro\Helpers\Log;

include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 0]);
    return;
}

$hoy = date("Y-m-d");
$hoy2 = date("Y-m-d H:i:s");
$idusuario = $_SESSION["id"];
$puestouser = $_SESSION["puesto"];

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$condi = (isset($input["condi"])) ? $input["condi"] : ((isset($_POST["condi"]) ? $_POST["condi"] : 0));

$database = new DatabaseAdapter();

switch ($condi) {
    case 'proceIVE': //Aprobar alerta del ive o denegar proceso
        $dato = $_POST['datos'];
        $codusu = $idusuario;
        // $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns('tb_alerta', ['proceso'], 'id=?', [$dato[0]]);
            if (empty($result)) {
                // $showmensaje = true;
                throw new SoftException("No se encontro la alerta especificada");
            }
            $tipproceso = ($result[0]["proceso"] == 'EP1') ? 'A1' : 'A';
            $database->beginTransaction();
            $datos = array(
                'proceso' =>  $tipproceso,
                'estado' => 0,
                'updated_by' => $idusuario,
                'updated_at' => $hoy2,
            );

            // Log::info("Datos a actualizar: ", $datos);

            $database->update("tb_alerta", $datos, "id=?", [$dato[0]]);
            $database->commit();
            // $database->rollback();
            $mensaje = "La petición fue aprobada";
            $status = 1;
        } catch (SoftException $se) {
            $database->rollback();
            $mensaje = $se->getMessage();
            $status = 0;
        } catch (Exception $e) {
            $database->rollback();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
    case 'autorizarMora':
        $dato = $_POST['datos'];
        $codusu = $idusuario;
        // $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns('tb_alerta', ['proceso'], 'id=?', [$dato[0]]);
            if (empty($result)) {
                // $showmensaje = true;
                throw new SoftException("No se encontro la alerta especificada");
            }
            // $tipproceso = ($result[0]["proceso"] == 'EP1') ? 'A1' : 'A';
            $database->beginTransaction();
            $datos = array(
                // 'proceso' =>  $tipproceso,
                'estado' => 0,
                'updated_by' => $idusuario,
                'updated_at' => $hoy2,
            );

            $database->update("tb_alerta", $datos, "id=?", [$dato[0]]);
            $database->commit();
            // $database->rollback();
            $mensaje = "La petición fue aprobada";
            $status = 1;
        } catch (SoftException $se) {
            $database->rollback();
            $mensaje = $se->getMessage();
            $status = 0;
        } catch (Exception $e) {
            $database->rollback();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;

    case 'notifications':
        $ive = ["GER", "ADM", "CNT"];
        $pf = ["GER", "CNT", "CJG", "CAJ", "ADM", "SEC"];
        $pass = ['otr_g'];
        $opcion = $input["opcion"];

        try {
            $userPermissions = new PermissionManager($idusuario);

            $condiMora = "";
            $params = [$hoy, $idusuario];

            if ($userPermissions->isLevelOne(PermissionManager::PERDON_MORA)) {
                $condiMora = "OR (((tipo_alerta='MORA' AND ale.estado=1) OR (tipo_alerta='MORA' AND (ale.fecha)= ?)) AND usu.id_agencia=?)";
                $params[] = $hoy;
                $params[] = $_SESSION['id_agencia'];
            }
            if ($userPermissions->isLevelTwo(PermissionManager::PERDON_MORA)) {
                $condiMora = "OR (((tipo_alerta='MORA' AND ale.estado=1) OR (tipo_alerta='MORA' AND (ale.fecha)= ?)))";
                $params[] = $hoy;
            }

            $database->openConnection();

            $query1 = "SELECT ale.id, cli.idcod_cliente, cli.short_name,tipo_alerta, mensaje, fecha,cod_aux,codDoc,proceso,ale.estado, ale.puesto FROM tb_alerta ale 
                            LEFT JOIN ahomcta cu ON cu.ccodaho = ale.cod_aux
                            LEFT JOIN tb_cliente cli ON cli.idcod_cliente = cu.ccodcli
                            LEFT JOIN tb_usuario usu ON usu.id_usu = ale.created_by
                            WHERE 
                                (tipo_alerta='IVE' AND ale.estado=1) OR (tipo_alerta='IVE' AND (ale.fecha)= ?) 
                                OR (tipo_alerta='PASS' AND ale.estado=1 AND cod_aux=?) $condiMora;";
            $alerts = $database->getAllResults($query1, $params);

            /**
             * Obtiene los plazos fijos y auxilios póstumos a vencer en los próximos 30 días
             * Combina ambas consultas en una sola ordenada por fecha de vencimiento
             */
            if ($opcion == 1) {
                $queries = [];

                if ($userPermissions->isLevelOne(PermissionManager::VER_PLAZO_FIJO_VENCER)) {
                    $queries[] = "SELECT 
                            tc.idcod_cliente,
                            tc.short_name, 
                            crt.codaho as codigo,
                            tc.no_identifica,
                            crt.fec_ven as fecha_vencimiento,
                            'plazo_fijo' as tipo
                        FROM ahomcrt crt 
                        INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                        INNER JOIN tb_cliente tc ON tc.idcod_cliente = cta.ccodcli
                        WHERE liquidado='N' 
                            AND crt.fec_ven > CURDATE() 
                            AND crt.fec_ven < DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                }

                if ($userPermissions->isLevelOne(PermissionManager::VER_AUXILIO_POSTUMO_VENCER)) {
                    $queries[] = "SELECT 
                            cli.idcod_cliente,
                            cli.short_name,
                            CAST(cue.id AS CHAR) as codigo,
                            cli.no_identifica,
                            ren.fecha_fin as fecha_vencimiento,
                            'auxilio' as tipo
                        FROM aux_renovaciones ren
                        INNER JOIN aux_cuentas cue ON cue.id=ren.id_cuenta
                        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cue.id_cliente
                        WHERE cue.estado = 'vigente' 
                            AND ren.estado = 'vigente' 
                            AND ren.fecha_fin > CURDATE() 
                            AND ren.fecha_fin < DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
                }

                // Log::info("Consultas de vencimientos: ", $queries);

                if (!empty($queries)) {
                    $query2 = implode(" UNION ALL ", $queries) . " ORDER BY fecha_vencimiento ASC";

                    // Log::info("Consulta combinada de vencimientos: " . $query2);

                    $vencimientos = $database->getAllResults($query2);
                } else {
                    $vencimientos = [];
                }
            }

            $mensaje = "Registro grabado correctamente";
            $status = 1;
        } catch (SoftException $se) {
            $mensaje = $se->getMessage();
            $status = 0;
        } catch (Throwable $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if (!$status) {
            echo json_encode([$mensaje, 0]);
            return;
        }
        $check = '<svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-circle-check" width="40" height="40" viewBox="0 0 24 24" stroke-width="1.5" stroke="#00b341" fill="none" stroke-linecap="round" stroke-linejoin="round">
        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
        <circle cx="12" cy="12" r="9" />
        <path d="M9 12l2 2l4 -4" />
      </svg>';
        $notificaciones = [];
        if (!empty($alerts)) {
            foreach ($alerts as $alert) {
                if ($alert["tipo_alerta"] == "IVE") {
                    if (in_array($puestouser, $ive)) {
                        $boton = ($alert["estado"] == 0) ? $check : '<button type="button" id="btnSelec" class="btn btn-success" onclick="obtieneAux([' . $alert['id'] . ',`A`])"><i class="fa-solid fa-check-to-slot"></i></button>';
                        $datos = [
                            'imgSrc' => 'https://i.pinimg.com/564x/74/af/3c/74af3c317f674700956e06a12e7d7fe8.jpg',
                            'title' => 'Alerta de IVE',
                            'message' => $alert["cod_aux"] . "-" . $alert["short_name"] . " - " . $alert["mensaje"] . ", Num. Doc: " . $alert["codDoc"],
                            'tipo' => 1,
                            'button' => $boton
                        ];
                        array_push($notificaciones, $datos);
                    }
                } else if ($alert["tipo_alerta"] == "MORA") {
                    // if (in_array($puestouser, $ive)) {
                    $boton = ($alert["estado"] == 0) ? $check : '<button type="button" id="btnSelec" class="btn btn-success" onclick="obtieneAux([' . $alert['id'] . ',`A`],`autorizarMora`)"><i class="fa-solid fa-check-to-slot"></i></button>';
                    $datos = [
                        'imgSrc' => 'https://cdn.pixabay.com/photo/2013/11/21/07/30/castle-214452_1280.jpg',
                        'title' => 'Solicitud de modificacion de Mora',
                        'message' => "Credito: " . $alert["cod_aux"] . " - " . $alert["mensaje"] . ", Num. Doc: " . $alert["codDoc"],
                        'tipo' => 1,
                        'button' => $boton
                    ];
                    array_push($notificaciones, $datos);
                    // }
                } else if ($alert["tipo_alerta"] == "PASS") {
                    if (in_array($puestouser, $pass)) {
                        $boton = ($alert["estado"] == 0) ? $check : '<button type="button" id="btnSelec" class="btn btn-success" onclick="obtieneAux([' . $alert['id'] . ',`A`])"><i class="fa-solid fa-check-to-slot"></i></button>';
                        $datos = [
                            'imgSrc' => 'https://i.pinimg.com/564x/74/af/3c/74af3c317f674700956e06a12e7d7fe8.jpg',
                            'title' => 'Alerta de Cambio de contraseña',
                            'message' => "Usuario: " . $_SESSION['user'] . ", Num. Doc: " . $alert["codDoc"],
                            'tipo' => 5,
                            'button' => $boton
                        ];
                        array_push($notificaciones, $datos);
                    }
                } else {
                    if ($opcion == 1) {
                        // $boton = '<button type="button" id="btnSelec" class="btn btn-primary" onclick="printd2(`change_pass`, `#cuadro`, `./admin/views/usuario/usuario_01.php`, `0`);$(`.notifications`).toggleClass(`open`);">Realizar cambio</button>';
                        $boton = '<button type="button" id="btnSelec" class="btn btn-primary" onclick="directaccess(`./admin/admin_usu.php`,`change_pass`,`usuario_01`,`0`)">Realizar cambio</button>';
                        $datos = [
                            'imgSrc' => 'https://static.vecteezy.com/system/resources/previews/047/356/095/non_2x/secure-login-form-page-with-password-on-computer-and-padlock-sign-in-to-account-safety-verification-personal-online-account-protection-3d-icon-cartoon-minimal-style-vector.jpg',
                            'title' => 'Cambio de contraseña',
                            'message' => "Su contraseña está próxima a vencerse. Por favor, cámbiela antes del " . date("d-m-Y", strtotime($alert["fecha"])) . " para evitar problemas de acceso.",
                            'tipo' => 2,
                            'button' => $boton
                        ];
                        array_push($notificaciones, $datos);
                    }
                }
            }
        }
        if ($opcion == 1) {
            foreach (($vencimientos ?? []) as $fila) {
                if ($fila['tipo'] === 'plazo_fijo') {
                    // if (in_array($puestouser, $pf)) {
                    $datos = [
                        'imgSrc' => 'https://cdn.pixabay.com/photo/2022/05/21/11/40/money-bag-7211306_1280.png',
                        'title' => 'Plazo fijo a vencer',
                        'message' => $fila["codigo"] . "-" . $fila["short_name"] . " VENCE: " . Date::toDMY($fila["fecha_vencimiento"]),
                        'tipo' => 3,
                    ];
                    array_push($notificaciones, $datos);
                    // }
                } else { // tipo === 'auxilio'
                    $datos = [
                        'imgSrc' => 'https://cdn.pixabay.com/photo/2021/10/07/05/43/first-aid-kit-6687410_1280.png',
                        'title' => 'Contrato de auxilio póstumo a vencer',
                        'message' => $fila["no_identifica"] . "-" . $fila["short_name"] . " VENCE: " . Date::toDMY($fila["fecha_vencimiento"]),
                        'tipo' => 3,
                    ];
                    array_push($notificaciones, $datos);
                }
            }
        }
        echo json_encode([$notificaciones, 1]);
        break;

    case 'solicitar_autorizacion_mora':
        $codigoCredito = $_POST["codcredito"];
        // $mensaje = $_POST["justificacion"];
        $numeroDocumento = $_POST["norecibo"];

        try {
            $database->openConnection();
            $result = $database->selectColumns('tb_alerta', ['estado'], 'codDoc=? AND cod_aux=?', [$numeroDocumento, $codigoCredito]);
            if (!empty($result) && $result[0]['estado'] == 1) {
                throw new SoftException("Ya existe una alerta pendiente de autorizacion para este credito y documento. Por favor, espere a que sea procesada antes de enviar una nueva solicitud.");
            }
            if (!empty($result) && $result[0]['estado'] == 0) {
                http_response_code(200);
                echo json_encode(['Cambio de mora autorizado', '2', true]);
                return;
            }
            $database->beginTransaction();

            $alerta = [
                'puesto' => 'ADM',
                'tipo_alerta' => 'MORA',
                'mensaje' => "Solicitud de autorizacion de mora para el credito: $codigoCredito, documento: $numeroDocumento",
                'cod_aux' => $codigoCredito,
                'codDoc' => $numeroDocumento,
                // 'proceso' => 'A',
                'estado' => 1,
                'fecha' => $hoy,
                'created_by' => $idusuario,
                'created_at' => date("Y-m-d H:i:s"),
            ];

            $database->insert("tb_alerta", $alerta);
            $database->commit();
            $mensaje = "Solicitud de autorizacion de mora enviada correctamente";
            $status = 1;
        } catch (SoftException $se) {
            $database->rollback();
            $mensaje = $se->getMessage();
            $status = 0;
        } catch (Exception $e) {
            $database->rollback();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);
        break;
}
