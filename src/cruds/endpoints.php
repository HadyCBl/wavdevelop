<?php

use Micro\Generic\AblyService;
use Micro\Exceptions\AblyServiceException;
use App\Generic\CurrencyExchangeService;
use Micro\Helpers\Log;

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}
session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';

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
            echo json_encode([
                'message' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente',
                'status' => '0'
            ]);
            return;
        }
        // list($serialSession) = $_POST["inputs"];
        // list($operacion, $idPersona,$srcPc) = $_POST["archivo"];
        //token,condi: 'activarSensor',sessionSerial,peopleCode 
        $serialSession = $_POST["sessionSerial"];
        $operacion = $_POST["operation"];
        $idPersona = $_POST["peopleCode"];
        $srcPc = $_POST["token"];

        $huella_version = isset($_ENV['HUELLA_VERSION']) ? $_ENV['HUELLA_VERSION'] : 1;


        $validar = validacionescampos([
            [$srcPc, "", 'No existe ningun identificador o token (verifique que tenga asignado uno)', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([
                'message' => $validar[0],
                'status' => $validar[1]
            ]);
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

        /**
         * TIPO DE CODIGO typeFindCode
         * 0 = Captura de Huella
         * 1 = Verificacion por codigo de cliente
         * 2 = Verificacion por codigo de usuario
         * 3 = Verificacion por codigo de cuenta de ahorros (incluye para cuentas mancomunadas)
         * 4 = Verificacion por codigo de cuenta de aportaciones
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

            if ($huella_version == 2) {
                $ablyService = AblyService::getInstance();
                $ablyService->setTimeout(10000); // 10 segundos

                $huellaData = [
                    "operacion" => ($operacion == 0) ? "capturar" : "leer",
                    "serialSession" => $serialSession,
                    "idPersona" => $idPersona,
                    "pcSerial" => $srcPc
                ];

                try {
                    $confirmacion = $ablyService->publishHuellaDigital($srcPc, $huellaData);

                    // Log::info("✅ Sensor activado con confirmación", [
                    //     'device' => $srcPc,
                    //     'latency' => $confirmacion['latency'] ?? 'N/A'
                    // ]);

                    $mensaje = "Sensor activado correctamente";
                    $status = 1;
                } catch (AblyServiceException $e) {
                    Log::warning("⚠️ Sensor activado sin confirmación Ably", [
                        'error' => $e->getMessage(),
                        'device' => $srcPc
                    ]);

                    // ✅ No fallar si la BD fue exitosa
                    $mensaje = "Sensor activado. Si no responde, verifique la aplicación.";
                    $status = 1;
                }
            } else {
                $mensaje = "Sensor activado (modo legacy)";
                $status = 1;
            }
            $database->commit();

            $status = 1;
            // $mensaje = "Proceso lanzado correctamente, espere a que el sensor de huella dactilar se active";
        } catch (Exception $e) {
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
            'message' =>   $mensaje,
            'status' =>  $status,
        ]);

        break;
    case 'detenerSensor':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        // list($serialSession) = $_POST["inputs"];
        // list($operacion, $idPersona,$srcPc) = $_POST["archivo"];
        //token,condi: 'activarSensor',sessionSerial,peopleCode 
        $serialSession = $_POST["sessionSerial"];
        $srcPc = $_POST["token"];
        $huella_version = isset($_ENV['HUELLA_VERSION']) ? $_ENV['HUELLA_VERSION'] : 1;

        $validar = validacionescampos([
            [$srcPc, "", 'No existe ningun identificador o token (verifique que tenga asignado uno)', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([
                'message' => $validar[0],
                'status' => $validar[1]
            ]);
            return;
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                +++++++++++++++++++++++++++++++  INICIO DE TRANSACCIONES +++++++++++++++++++++++++++++++++++++++++++++++++
                ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $showmensaje = false;
        try {
            $database->openConnection();

            $database->beginTransaction();

            // $database->delete('huella_temp', "pc_serial=?", [$srcPc]);

            $datos = array(
                "opc" => "stop",
            );

            $database->update('huella_temp', $datos, "pc_serial=?", [$srcPc]);

            if ($huella_version == 2) {
                $ablyService = AblyService::getInstance();
                $huellaData = [
                    "operacion" => "stop",
                    "serialSession" => $serialSession,
                    "idPersona" => $srcPc,
                    // "messageId" => uniqid() // Generar un ID único para el mensaje
                ];

                // Usar el nuevo método con confirmación
                $confirmacion = $ablyService->publishHuellaDigitalant($srcPc, $huellaData);
            }

            $database->commit();

            $status = 1;
            $mensaje = "Huella detenida correctamente";
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
            'message' =>   $mensaje,
            'status' =>  $status,
        ]);

        break;
    case 'verifyFingerprint':
        $operationType = $_POST["operationType"];
        $huella_version = isset($_ENV['HUELLA_VERSION']) ? $_ENV['HUELLA_VERSION'] : 1;
        // Log::info("Verificando huella digital para el tipo de operación: $huella_version");
        $type = array(
            3 => 1, // Codigo de cuenta de ahorros
            4 => 2 // Codigo de cuenta de aportaciones
        );
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns("tb_validacioneshuella", ['estado'], "id_modulo=? AND estado=1", [$type[$operationType]]);
            $verify = (empty($result)) ? 0 : 1;

            if ($huella_version == 2) {
                $ablyService = AblyService::getInstance();
                $ablyConfig = $ablyService ? $ablyService->getClientConfig() : [
                    'clientKey' => null,
                    'channelPrefix' => '',
                    'enabled' => false
                ];
            }

            $status = 1;
            $mensaje = ($verify) ? "Se necesita autorizacion por medio de huella digital" : "Validacion correcta";
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
            'message' => $mensaje,
            'status' => $status,
            'verify' => ($verify ?? 0),
            'huella_version' => $huella_version,
            'keyClient' => ($ablyConfig['clientKey'] ?? ''),
            'channelPrefix' => ($ablyConfig['channelPrefix'] ?? ''),
        ]);
        break;
    case 'sincData':
        $srcPc = $_POST["srn"];
        // $sql = "SELECT pc_serial,imgHuella,update_time,texto,statusPlantilla,opc" . " FROM huella_temp ORDER BY update_time DESC LIMIT 1";
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns("huella_temp", ['imgHuella', 'texto', 'statusPlantilla'], "pc_serial=?", [$srcPc]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontraron datos para la instancia");
            }

            $result = $result[0];

            $status = 1;
            $mensaje = "Datos sincronizados correctamente";
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
            'message' => $mensaje,
            'status' => $status,
            'data' => ($result ?? []),
        ]);
        break;
    case 'verifyIve':
        $ccodaho = $_POST["ccodaho"];
        $monto = $_POST["monto"];
        $cnumdoc = $_POST["cnumdoc"];

        // retornar 0 si no hay nada, 1 si tiene que mostrar el formulario, 2 para guardar pero no mostrar

        $retorno = 0;
        $idAlertaReturn = 0;

        $showmensaje = false;
        try {
            $service = new CurrencyExchangeService();
            $rate = $service->getExchangeRate('USD', 'GTQ');

            if (!$rate) {
                $rate = ['rate' => 7.7];
            }
            $database->openConnection();

            /**
             * DATOS DE LA CUENTA DE AHORROS
             */

            $datosCuenta = $database->selectColumns("ahomcta", ['ccodcli'], "ccodaho=?", [$ccodaho]);
            if (empty($datosCuenta)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de ahorros, verifique que el código sea correcto y que el cliente esté activo");
            }

            /**
             * VERIFICACION DEL CLIENTE RECURRENTE
             */

            $result = $database->selectColumns("tb_cliente_atributo", ['valor'], "id_cliente=? AND id_atributo=19", [$datosCuenta[0]['ccodcli']]);
            $verify = (empty($result)) ? 0 : trim($result[0]['valor']);

            /**
             * CONSULTA DE SALDOS DURANTE LOS ULTOMOS 30 DIAS
             */

            $query = " SELECT IFNULL(SUM(monto),0) sumaDepositos FROM ahommov mov
                        INNER JOIN ahomcta AS ac ON mov.ccodaho = ac.ccodaho
                        WHERE ac.estado='A' AND mov.ctipope='D' AND mov.cestado=1 
                        AND (dfecope BETWEEN (DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND CURDATE())  AND ac.ccodcli=?";

            $datosIve = $database->getAllResults($query, [$datosCuenta[0]['ccodcli']]);
            $sumaDepositosLast30 = $datosIve[0]['sumaDepositos'] ?? 0;

            Log::info("datos previos:", [
                'sumaDepositosLast30' => $sumaDepositosLast30,
                'monto' => $monto,
                'rate' => $rate['rate']
            ]);

            if (($monto)  > 10000 * $rate['rate']) {
                /**
                 * SE CALCULA SOBRE EL MONTO DE LA TRANSACCION
                 */
                // if (($sumaDepositosLast30 + $monto) * $rate['rate'] > 10000) { //anterior, si se quiere de los ultimos 30 dias
                Log::info("Exceso de monto:", [
                    'sumaDepositosLast30' => $sumaDepositosLast30,
                    'monto' => $monto,
                    'rate' => $rate['rate']
                ]);

                $alertaHoy = $database->selectColumns('tb_alerta', ['id'], 'tipo_alerta=? AND estado=0 AND cod_aux=? AND fecha=CURDATE() AND codDoc=?', ['IVE', $ccodaho, $cnumdoc]);
                if (empty($alertaHoy)) {
                    /**
                     * SI NO HAY REGISTROS AUTORIZADOS DURANTE ESTE DIA, CON LA MISMA CUENTA, HOY Y NUMERO DE DOCUMENTO
                     */
                    $alertaHoy = $database->selectColumns('tb_alerta', ['id'], 'tipo_alerta=? AND estado=1 AND cod_aux=? AND fecha=CURDATE() AND codDoc=?', ['IVE', $ccodaho, $cnumdoc]);
                    if (!empty($alertaHoy)) {
                        /**
                         * SI HAY UN REGISTRO APROBADO, SE DEBE VERIFICAR SI EL MONTO DEPOSITADO + LOS ULTIMOS 30 DIAS EXCEDE LOS 10,000 DOLARES
                         */
                        $showmensaje = true;
                        throw new Exception("Está pendiente la aprobación de una solicitud por parte del Administrador.");
                    }

                    $tb_alerta = [
                        'puesto' => 'ADM',
                        'tipo_alerta' => 'IVE',
                        'mensaje' => 'Llenar el formulario del IVE',
                        'cod_aux' => $ccodaho,
                        'codDoc' => $cnumdoc,
                        'proceso' => 'EP',
                        'estado' => 1,
                        'fecha' => date('Y-m-d'),
                        'created_by' => $idusuario,
                        'created_at' => date('Y-m-d H:i:s'),
                    ];

                    $idAlertaReturn = $database->insert('tb_alerta', $tb_alerta);

                    /**
                     * CONSULTAR SI YA HIZO TRANSACCIONES DURANTE ESTE MES, EN LA CUENTA
                     */
                    $transaccionesMes = $database->getAllResults("SELECT Cretadate FROM tb_RTE_use WHERE ccdocta=? AND MONTH(Cretadate)=MONTH(CURDATE())", [$ccodaho]);

                    $retorno = 1; // se debe llenar el formulario
                    if (!empty($transaccionesMes)) {
                        /**
                         * SI YA HIZO TRANSACCIONES, SE DEBE VERIFICAR SI ES RECURRENTE
                         */

                        if ($verify == 1) {

                            $retorno = 2; // Hay transacciones, se debe guardar pero no mostrar el formulario
                        }

                        $verificacionTransaccion = $database->selectColumns("tb_RTE_use", ['Mon'], "ccdocta=? AND aux=? AND DATE(Cretadate)=CURDATE()", [$ccodaho, $cnumdoc]);
                        if (!empty($verificacionTransaccion)) {
                            $retorno = 0;
                        }
                    }

                    if ($retorno == 1) {
                        $showmensaje = true;
                        throw new Exception("001 ALERTA IVE... el monto ingresado ha superado los $10,000, para continuar con la transacción se tiene que aprobar la alerta. Favor de apuntar el No. Documento: " . $cnumdoc . "");
                    }
                }

                if ($retorno == 1) {
                }
            }

            $status = 1;
            $mensaje = "Validacion correcta";
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
            'message' => $mensaje,
            'status' => $status,
            'return' => $retorno,
            'idAlerta' => $idAlertaReturn
        ]);

        break;
}
