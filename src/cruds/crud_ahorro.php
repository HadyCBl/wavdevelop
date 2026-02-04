<?php
include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();
/*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++ CLASES NECESARIAS PARA VALIDACIONES Y CONEXION  +++++++++++++++++++++++++++++++++++
    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
// require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
// require_once __DIR__ . '/../../includes/Config/SecureID.php';
require_once __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/model/ahorros/Ahomtip.php';
require_once __DIR__ . '/../../includes/Config/model/ahorros/CalculadoraPagos.php';
require_once __DIR__ . '/../../src/funcphp/func_gen.php';


ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '3600');

include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

// require '../../vendor/autoload.php';
include_once '../envia_correo.php';
$idusuario = $_SESSION['id'];

// date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$hoy = date("Y-m-d");
$idagencia = $_SESSION['id_agencia'];

use Micro\Helpers\Log;
use Luecano\NumeroALetras\NumeroALetras;
use App\Generic\CurrencyExchangeService;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\SecureID;
use Micro\Helpers\Beneq;

$csrf = new CSRFProtection();
$secureID = new SecureID($_ENV['MYKEYPASS']);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

$condi = $_POST["condi"];

switch ($condi) {
    case 'cahomtip':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];

        list($csrftoken, $ccodtip, $nombre, $descripcion, $tasa, $mincalc, $mindepo) = $inputs;
        list($agencia, $clase, $dias) = $selects;

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
        $validar = validar_campos_plus([
            [$nombre, "", 'El campo de nombre es obligatorio', 1],
            [$descripcion, "", 'Debe digitar una descripcion', 1],
            [$tasa, "", 'Debe digitar una tasa', 1],
            [$mincalc, "", 'Digite un monto para el saldo minimo de calculo de intereses', 1],
            [$mindepo, "", 'Digite el monto del deposito minimo para la apertura de la cuenta', 2],
            [$agencia, "0", 'Seleccione una agencia', 1],
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
            $ahomtipModel = new Ahomtip($database);
            $ahomtip = $ahomtipModel->selectAhomtipColumns(['ccodtip'], "ccodtip=?", [$ccodtip]);
            if (!empty($ahomtip)) {
                $ahomtips = $ahomtipModel->getActiveAhomtip();
                $codigosExistentes = array_column($ahomtips, "ccodtip");
                $ccodtip = generarCodigoUnico($codigosExistentes);
            }
            $database->beginTransaction();
            $datos = array(
                "ccodofi" => $agencia,
                "ccodtip" => $ccodtip,
                "nombre" => $nombre,
                "cdescripcion" => $descripcion,
                "tasa" => $tasa,
                "diascalculo" => $dias,
                "tipcuen" => $clase,
                "mincalc" => $mincalc,
                "mindepo" => $mindepo,
                "numfront" => 30,
                "front_ini" => 10,
                "numdors" => 30,
                "dors_ini" => 10,
                "id_cuenta_contable" => 1,
                "estado" => 1,
                "created_by" => $idusuario,
                "created_at" => $hoy2,
            );
            $ahomtipModel->createAhomtip($datos);

            $database->commit();
            $status = 1;
            $mensaje = "Producto creado correctamente";
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
    case 'uahomtip':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        list($csrftoken, $ccodtip, $nombre, $descripcion, $tasa, $mincalc, $mindepo) = $inputs;
        list($agencia, $clase, $dias) = $selects;
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
        $validar = validar_campos_plus([
            [$nombre, "", 'El campo de nombre es obligatorio', 1],
            [$descripcion, "", 'Debe digitar una descripcion', 1],
            [$tasa, "", 'Debe digitar una tasa', 1],
            [$mincalc, "", 'Digite un monto para el saldo minimo de calculo de intereses', 1],
            [$mindepo, "", 'Digite el monto del deposito minimo para la apertura de la cuenta', 2],
            [$agencia, "0", 'Seleccione una agencia', 1],
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
            $ahomtipModel = new Ahomtip($database);
            $ahomtip = $ahomtipModel->getAhomtipById($decryptedID);
            if (empty($ahomtip)) {
                $showmensaje = true;
                throw new Exception("No se logró encontrar el producto a actualizar");
            }
            $database->beginTransaction();
            $datos = array(
                "ccodofi" => $agencia,
                "ccodtip" => $ccodtip,
                "nombre" => $nombre,
                "cdescripcion" => $descripcion,
                "tasa" => $tasa,
                "diascalculo" => $dias,
                "tipcuen" => $clase,
                "mincalc" => $mincalc,
                "mindepo" => $mindepo,
                "estado" => 1,
                "updated_by" => $idusuario,
                "updated_at" => $hoy2,
            );
            $ahomtipModel->updateAhomtip($decryptedID, $datos);

            $database->commit();
            $status = 1;
            $mensaje = "Producto actualizado correctamente";
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

    case 'dahomtip':
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
            $ahomtipModel = new Ahomtip($database);
            $ahomtip = $ahomtipModel->getAhomtipById($decryptedID);
            if (empty($ahomtip)) {
                $showmensaje = true;
                throw new Exception("No se logró encontrar el producto a eliminar");
            }
            $result = $database->selectColumns('ahomcta', ['ccodaho'], 'SUBSTR(ccodaho,7,2)=? AND estado IN ("A","B")', [$ahomtip[0]["ccodtip"]]);
            if (!empty($result)) {
                $showmensaje = true;
                throw new Exception("Este producto no se puede eliminar porque tiene cuentas asociadas.");
            }

            $database->beginTransaction();
            $datos = array(
                "estado" => 0,
                "deleted_by" => $idusuario,
                "deleted_at" => $hoy2,
            );
            $ahomtipModel->updateAhomtip($decryptedID, $datos);

            $database->commit();
            $status = 1;
            $mensaje = "Producto eliminado correctamente";
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

    case 'cahotestigo':
        // Create a new testigo
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        // Get values from the inputs array sent by obtiene()
        $inputs = isset($_POST["inputs"]) ? $_POST["inputs"] : [];
        $archivo = isset($_POST["archivo"]) ? $_POST["archivo"] : [];

        // Check if we have enough elements in the inputs array
        if (count($inputs) < 5) {
            echo json_encode(["Error: Datos incompletos para crear testigo", 0]);
            return;
        }

        $nombre = $inputs[0];
        $dpi = $inputs[1];
        $direccion = $inputs[2];
        $telefono = $inputs[3];
        $codcrt = $inputs[4];

        // Validate required fields
        if (empty($nombre)) {
            echo json_encode(["Error: El nombre del testigo es obligatorio", 0]);
            return;
        }

        // Get the next ID (you might have auto-increment set up)
        $query = mysqli_query($conexion, "SELECT MAX(id) as max_id FROM ahotestigos");
        $row = mysqli_fetch_array($query);
        $next_id = ($row['max_id']) ? $row['max_id'] + 1 : 1;

        $sql = "INSERT INTO ahotestigos (id, id_certificado, nombre, dpi, direccion, telefono) 
                        VALUES ($next_id, '$codcrt', '$nombre', '$dpi', '$direccion', '$telefono')";

        $result = mysqli_query($conexion, $sql);

        if ($result) {
            echo json_encode(["Testigo agregado correctamente", 1]);
        } else {
            echo json_encode(["Error al agregar testigo: " . mysqli_error($conexion), 0]);
        }
        break;

    case 'uahotestigo':
        // Update an existing testigo
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        // Get values from the inputs array sent by obtiene()
        $inputs = isset($_POST["inputs"]) ? $_POST["inputs"] : [];

        // Check if we have enough elements in the inputs array
        if (count($inputs) < 5) {
            echo json_encode(["Error: Datos incompletos para actualizar testigo", 0]);
            return;
        }

        $nombre = $inputs[0];
        $dpi = $inputs[1];
        $direccion = $inputs[2];
        $telefono = $inputs[3];
        $id = $inputs[4];

        // Validate required fields
        if (empty($nombre) || empty($id)) {
            echo json_encode(["Error: El nombre y ID del testigo son obligatorios", 0]);
            return;
        }

        $sql = "UPDATE ahotestigos SET 
                        nombre = '$nombre', 
                        dpi = '$dpi', 
                        direccion = '$direccion', 
                        telefono = '$telefono' 
                        WHERE id = $id";

        $result = mysqli_query($conexion, $sql);

        if ($result) {
            echo json_encode(["Testigo actualizado correctamente", 1]);
        } else {
            echo json_encode(["Error al actualizar testigo: " . mysqli_error($conexion), 0]);
        }
        break;

    case 'dahotestigo':
        // Delete a testigo
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        // Get the testigo ID from xtra parameter
        if (isset($_POST["xtra"]) && !empty($_POST["xtra"])) {
            $id = $_POST["xtra"];
        } else {
            echo json_encode(["Error: No se proporcionó el ID del testigo a eliminar", 0]);
            return;
        }

        // Validate that ID is numeric
        if (!is_numeric($id)) {
            echo json_encode(["Error: ID de testigo inválido", 0]);
            return;
        }

        $sql = "DELETE FROM ahotestigos WHERE id = $id";

        $result = mysqli_query($conexion, $sql);

        if ($result) {
            echo json_encode(["Testigo eliminado correctamente", 1]);
        } else {
            echo json_encode(["Error al eliminar testigo: " . mysqli_error($conexion), 0]);
        }
        break;


    case 'cahomcta':
        //obtiene(['= $csrf->getTokenName() ','tasa','libreta','correlainteres'],['encargado','cuentasSelect','createdselect'],[],'cahomcta','0',['<?= htmlspecialchars($secureID->encrypt($datosCliente['idcod_cliente'] ?? '')) ',getTipCuenta()])
        /**
         * Verifica si la sesión del usuario está activa comprobando la existencia de 'id_agencia' en la sesión.
         * Si la sesión ha expirado, devuelve un mensaje en formato JSON indicando que la sesión ha expirado.
         * 
         * Luego, extrae y asigna los valores de los arreglos 'inputs', 'selects' y 'archivo' de la variable $_POST.
         * 
         * Variables extraídas de $_POST:
         * - inputs: 
         *   - csrftoken: Token CSRF para la seguridad de la solicitud.
         *   - tasa: Tasa de interés para la cuenta de ahorros.
         *   - libreta: Número de libreta de ahorros.
         *   - cuentaSecundaria: Numero de cuenta generado o seleccionado para una cuenta secundaria.
         * - selects:
         *   - encargado: Encargado de la cuenta de ahorros, '0' si no aplica.
         *   - createSecondary: Indicador de creación de cuenta secundaria (-1) || No aplica (0) || codigo de cuenta a vincular.
         *   - productoSecundario: Producto secundario a asociar.
         * - archivo:
         *   - encryptedID: ID encriptado del cliente.
         *   - codproducto: Código del producto de ahorro a generar.
         */
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken, $tasa, $libreta, $cuentaSecundaria, $fechaapertura) = $_POST["inputs"];
        list($encargado, $createSecondary, $productoSecundario) = $_POST["selects"];
        list($encryptedID, $codproducto) = $_POST["archivo"];

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        /**
         * Verifica la validez del token CSRF y valida los campos de entrada, Si el token CSRF no es válido finaliza la ejecución.
         * 
         * Valida los siguientes campos:
         * - Tasa: Debe ser un valor no vacío.
         * - Código de producto: Debe ser un valor diferente de "X".
         * - Libreta: Debe ser un valor no vacío.
         * 
         * Desencripta el ID del cliente.
         * 
         * Si se debe crear una cuenta secundaria y no se ha seleccionado un producto válido para la cuenta secundaria,
         * devuelve un mensaje de error en formato JSON y finaliza la ejecución.
         * 
         * @param object $csrf Objeto para la validación del token CSRF.
         * @param object $secureID Objeto para la desencriptación del ID del cliente.
         * @param string $encryptedID ID del cliente encriptado.
         */
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
        $validar = validar_campos_plus([
            [$tasa, "", 'Debe digitar una tasa', 1],
            [$codproducto, "X", 'Seleccione un producto', 1],
            [$libreta, "", 'Digite un numero de libreta', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $codigoCliente = $secureID->decrypt($encryptedID);

        if ($createSecondary == '-1') {
            if ($productoSecundario == '0' && strlen($productoSecundario) != 2) {
                echo json_encode(['Seleccione un producto para la cuenta secundaria', '0']);
                return;
            }
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            +++++++++++++++++++++++++++++++++ INICIO DE TRANSACCIONES EN LA BD  ++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $status = 0;
        try {
            /**
             * Abre una conexión a la base de datos y se genera el código de ahorro (ccodaho) basado en la agencia y el producto proporcionados.
             * 
             * Luego, crea una instancia del modelo Ahomtip y selecciona el registro del producto seleccionado. Si no se encuentran datos del 
             *  producto seleccionado, se establece 'showmensaje' en true para mostrar el mensaje puesto y se lanza una excepción.
             */
            $database->openConnection();

            $ccodaho = getccodahoPDO($idagencia, $codproducto, $database);

            $ahomtipModel = new Ahomtip($database);
            $ahomtip = $ahomtipModel->selectAhomtipColumns(['ccodtip'], "ccodtip=? AND estado=1", [$codproducto]);
            if (empty($ahomtip)) {
                throw new SoftException("No se logró encontrar datos del producto seleccionado");
            }

            /**
             * Maneja la lógica para la creación de una cuenta secundaria.
             *
             * Variables:
             * @var string $ccodahoSecondary Código de la cuenta secundaria.
             * @var bool $createSecondary Indicador de creación de cuenta secundaria.
             * @var string $productoSecundario Código del producto secundario.
             * @var string $cuentaSecundaria Código de la cuenta secundaria seleccionada.
             * @var object $ahomtipModel Modelo para la tabla 'ahomtip'.
             * 
             * Lógica:
             * - Si $createSecondary es '-1', se intenta obtener el código del producto secundario. Si no se encuentra, se lanza una excepción.
             * - Si $createSecondary no es '0' ni '-1', se intenta seleccionar la cuenta secundaria. Si no se encuentra, se lanza una excepción.
             */
            $ccodahoSecondary = '';

            if ($createSecondary == '-1') {
                $ahomtip = $ahomtipModel->selectAhomtipColumns(['ccodtip'], "ccodtip=? AND estado=1", [$productoSecundario]);
                if (empty($ahomtip)) {
                    throw new SoftException("No se logró encontrar datos del producto secundario seleccionado");
                }

                $ccodahoSecondary = getccodahoPDO($idagencia, $productoSecundario, $database);
            }

            if ($createSecondary != '0' && $createSecondary != '-1') {
                $selCcodahoSecondary = $database->selectColumns("ahomcta", ["ccodaho"], "ccodaho=?", [$cuentaSecundaria]);
                if (empty($selCcodahoSecondary)) {
                    throw new SoftException("No se logró encontrar la cuenta secundaria seleccionada");
                }
                $ccodahoSecondary = $cuentaSecundaria;
            }

            /**
             * Inicia una transacción en la base de datos.
             * 
             * Si $createSecondary es igual a '-1', crea una cuenta secundaria en la tabla "ahomcta" con los datos proporcionados.
             * 
             * Luego, crea una cuenta principal en la tabla "ahomcta" con los datos proporcionados.
             * 
             * Finalmente, confirma la transacción y establece el estado y mensaje de éxito.
             * 
             * Variables:
             * - $libreta: Número de libreta.
             * - $hoy: Fecha actual.
             * - $idusuario: ID del usuario que realiza la operación.
             * - $encargado: Encargado de la cuenta o quien lo esta captando.
             * - $hoy2: Fecha y hora actual.
             * - $ccodaho: Código de la cuenta principal.
             * - $tasa: Tasa de interés.
             */

            if (!Date::isValid($fechaapertura)) {
                throw new SoftException("La fecha de apertura no es válida");
            }

            $database->beginTransaction();

            $mensajeSecondary = "";
            if ($createSecondary == '-1') {
                $ahomcta = array(
                    "ccodaho" => $ccodahoSecondary,
                    "ccodcli" => $codigoCliente,
                    "nlibreta" => $libreta,
                    "estado" => "A",
                    "fecha_apertura" => $fechaapertura,
                    "fecha_mod" => $hoy,
                    "codigo_usu" => $idusuario,
                    "tasa" => 0,
                    "ctainteres" => "",
                    "encargado" => $encargado,
                    "created_by" => $idusuario,
                    "created_at" => $hoy2,
                );
                $database->insert("ahomcta", $ahomcta);
                $mensajeSecondary = ", Cuenta secundaria codigo: $ccodahoSecondary";
            }

            $ahomcta = array(
                "ccodaho" => $ccodaho,
                "ccodcli" => $codigoCliente,
                "nlibreta" => $libreta,
                "estado" => "A",
                "fecha_apertura" => $fechaapertura,
                "fecha_mod" => $hoy,
                "codigo_usu" => $idusuario,
                "tasa" => $tasa,
                "ctainteres" => $ccodahoSecondary,
                "encargado" => $encargado,
                "created_by" => $idusuario,
                "created_at" => $hoy2,
            );
            $database->insert("ahomcta", $ahomcta);

            $database->commit();
            $status = 1;
            $mensaje = "Cuenta creada correctamente con el número $ccodaho $mensajeSecondary";
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

    case 'calculoprog':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        list($csrftoken, $fechaInicio, $tasa, $monto, $plazo, $libreta) = $inputs;
        list($tipCuenta, $tipo, $estrict, $frecuencia, $ccodahopig) = $selects;

        $variable = intval($plazo / ($frecuencia / 30));
        $cuotaahorro = ($tipo === '1') ? ($monto / $variable) : $monto;

        $calculadora = new CalculadoraPagos($cuotaahorro, 0, $tasa, $fechaInicio, $frecuencia, $plazo);
        $calculadora->calcularPagos();

        $tablaPagos = $calculadora->obtenerTablaPagos();
        $eventos = $calculadora->obtenerEventos();

        echo json_encode(["Proceso correcto", '1', $tablaPagos, $eventos]);
        return;

        break;
    case 'cahomctaprogramado':
        //obtiene([' $csrf->getTokenName()',''fechaInicio', 'tasaInteres', 'montoDeposito', 'plazo', 'libreta'], ['tipCuenta', 'savingsType', 'estrict', 'frecuenciaDeposito','ccodahopig],[],'cahomctaprogramado','0',['<?= htmlspecialchars($secureID->encrypt($id))'])
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $archivo = $_POST["archivo"];

        list($csrftoken, $fechaInicio, $tasa, $monto, $plazo, $libreta) = $inputs;
        list($tipCuenta, $tipo, $estrict, $frecuencia, $ccodahopig) = $selects;
        list($encryptedID) = $archivo;

        $encryptedID = $archivo[0];
        $variable = intval($plazo / ($frecuencia / 30));
        $cuotaahorro = ($tipo === '1') ? ($monto / $variable) : $monto;

        $calculadora = new CalculadoraPagos($cuotaahorro, 0, $tasa, $fechaInicio, $frecuencia, $plazo);
        $calculadora->calcularPagos();

        $tablaPagos = $calculadora->obtenerTablaPagos();

        $fechafin = $tablaPagos[count($tablaPagos) - 1]['fecha'];
        // echo json_encode([count($plan), 0]);
        // return;
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
        $decryptedID = $secureID->decrypt($encryptedID);
        $validar = validar_campos_plus([
            [$decryptedID, "0", 'No ha seleccionado un cliente', 1],
            [$tasa, "", 'Ingrese una tasa', 1],
            [$libreta, "", 'Ingrese un numero de libreta', 1],
            [$tipCuenta, "0", 'Seleccione un producto', 1],
            [$fechaInicio, "", 'Ingrese una fecha de inicio', 1],
            [$monto, "0", 'Ingrese un monto', 1],
            [$plazo, "0", 'Ingrese un plazo', 1],
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
            $cliente = $database->selectColumns("tb_cliente", ['idcod_cliente'], "idcod_cliente=?", [$decryptedID]);
            if (empty($cliente)) {
                $showmensaje = true;
                throw new Exception("No se encontró al cliente");
            }

            $generar = getccodahoPDO($idagencia, $tipCuenta, $database);
            //REGISTRAR EN AHOMCTA
            $datos = array(
                "ccodaho" => $generar,
                "ccodcli" => $decryptedID,
                "nlibreta" => $libreta,
                "estado" => "A",
                "fecha_apertura" => $hoy,
                "fecha_cancel" => "0000-00-00",
                "fecha_ult" => "0000-00-00",
                "fecha_mod" => $hoy,
                "codigo_usu" => $idusuario,
                "tasa" => $tasa,
                "cnomaho" => "",
                "accountprg" => $ccodahopig,
                "monobj" => $monto,
                "dep" => 1,
                "ret" => 1,
                "fecini" => $fechaInicio,
                "fecfin" => DateTime::createFromFormat('d/m/Y', $fechafin)->format('Y-m-d'),
                "frec" => $frecuencia,
                "plazo" => $plazo,
                "estrict" => $estrict,
            );
            $database->beginTransaction();
            $database->insert("ahomcta", $datos);

            //REGISTRAR EN AHOMPPG
            foreach ($tablaPagos as $key => $fila) {
                $dahomppg = array(
                    "ccodaho" => $generar,
                    "fecven" => DateTime::createFromFormat('d/m/Y', $fila['fecha'])->format('Y-m-d'),
                    "estado" => "X",
                    "nrocuo" => $key + 1,
                    "monto" => $fila['deposito'],
                    "interes" => 0.00,
                    "montpag" => $fila['deposito'],
                    "intpag" => 0.00,
                    "usuario" => $idusuario,
                    "fecmod" => $hoy2,
                );
                $database->insert("ahomppg", $dahomppg);
            }

            $database->commit();
            $status = 1;
            $mensaje = "Cuenta creada correctamente con codigo " . $generar;
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
    case 'consultar_plan_pago':
        $ccodaho = $_POST['codcuenta'];
        $showmensaje = false;
        try {
            $database->openConnection();
            $ppg = $database->selectColumns("ahomppg", ['fecven', 'estado', 'nrocuo', 'monto', 'montpag'], "ccodaho=?", [$ccodaho]);
            if (empty($ppg)) {
                $showmensaje = true;
                throw new Exception("No existe el plan de pagos");
            }
            $status = 1;
            $mensaje = "Cuenta creada correctamente con codigo ";
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

        $array_datos = array();
        foreach ($ppg as $key => $fila) {
            $diasatraso = ($fila['fecven'] > $hoy) ? 0 : dias_dif($hoy, $fila['fecven']);
            $estado = '';
            if ($fila['estado'] == 'P') {
                $estado = '<span class="badge text-bg-success">Pagado</span>';
            } elseif ($fila['estado'] == 'X' && $diasatraso > 0) {
                $estado = '<span class="badge text-bg-danger">Atrasado</span>';
            } else {
                $estado = '<span class="badge text-bg-primary">Por pagar</span>';
            }
            $array_datos[$key] = array(
                "0" => $fila["nrocuo"],
                "1" => setdatefrench($fila["fecven"]),
                "2" => $estado,
                "3" => ($fila['estado'] == 'P') ? (0) : ($diasatraso),
                "4" => $fila["monto"],
            );
        }
        $results = array(
            "sEcho" => 1,
            "iTotalRecords" => count($array_datos),
            "iTotalDisplayRecords" => count($array_datos),
            "aaData" => $array_datos
        );
        echo json_encode($results);
        break;
    case 'correl':
        $tipo = $_POST["tipo"];
        $showmensaje = false;
        try {
            $database->openConnection();

            $ccodaho = getccodahoPDO($idagencia, $tipo, $database);

            $status = 1;
            $mensaje = $ccodaho;
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

    case 'create_depositos_ahommov':
        // depositos (['ccodaho', 'dfecope', 'cnumdoc', 'monto', 'cnumdocboleta', 'concepto', 'fechaBoleta', 'numero_cheque', 'fecha_cheque'],
        // ['salida', 'tipdoc', 'bancoid', 'cuentaid', 'bancoid_cheque'],
        // [],
        // 'cdahommov', '0',
        // ['<?= $account', action]

        list($ccodaho, $dfecope, $cnumdoc, $monto, $cnumdocboleta, $concepto, $fechaBoleta, $numero_cheque, $fecha_cheque) = $_POST["inputs"];
        list($salida, $tipdoc, $bancoid, $cuentaid, $bancoid_cheque) = $_POST["selects"];
        list($account, $saveRte) = $_POST["archivo"];

        $cuentaSaveTable = 0;
        $nroChequeSaveTable = '';
        $fechaChequeSaveTable = NULL;
        $fechaBancoSave = $dfecope;

        $showmensaje = false;
        try {

            if ($cnumdoc == "" || $cnumdoc == null) {
                $showmensaje = true;
                throw new Exception("El número de documento es obligatorio");
            }
            if (!validateDate($dfecope, 'Y-m-d')) {
                $showmensaje = true;
                throw new Exception("Fecha de operación inválida");
            }
            if (!is_numeric($monto) || $monto <= 0) {
                $showmensaje = true;
                throw new Exception("Monto de operación inválido");
            }
            if ($concepto == "" || $concepto == null) {
                $showmensaje = true;
                throw new Exception("El concepto de operación es obligatorio");
            }

            $database->openConnection();

            /**
             * CONSULTA DE TIPO DE CUENTA
             */

            // $tipoCuenta = $database->selectColumns("ahomtip", ['id_cuenta_contable', 'nombre', 'tipcuen'], "ccodtip=?", [substr($account, 6, 2)]);
            // if (empty($tipoCuenta)) {
            //     $showmensaje = true;
            //     throw new Exception("No se encontró el tipo de cuenta");
            // }

            /**
             * CONSULTA DE CUENTA DE CAJA
             */

            $datosGeneric = $database->selectColumns("tb_agencia", ["id_nomenclatura_caja"], "id_agencia=?", [$idagencia]);

            if (empty($datosGeneric)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de caja");
            }

            $idCuentaCaja = $datosGeneric[0]['id_nomenclatura_caja'];

            /**
             * PARA BOLETAS DE DEPOSITO
             */
            if ($tipdoc == "D") {
                if ($bancoid == 0) {
                    $showmensaje = true;
                    throw new Exception("Seleccione un banco");
                }
                if ($cuentaid == 0) {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de banco");
                }
                if ($cnumdocboleta == "") {
                    $showmensaje = true;
                    throw new Exception("El numero de boleta es obligatorio");
                }

                // $query = "SELECT id_nomenclatura cuenta FROM ctb_bancos WHERE id=?";
                $datosGeneric = $database->selectColumns("ctb_bancos", ["id_nomenclatura"], "id=?", [$cuentaid]);
                if (empty($datosGeneric)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta bancaria");
                }
                $idCuentaCaja = $datosGeneric[0]['id_nomenclatura'];

                $cuentaSaveTable = $cuentaid;
                $nroChequeSaveTable = $cnumdocboleta;
                $fechaChequeSaveTable = $fechaBoleta;
                $fechaBancoSave = $fechaBoleta;
            }

            /**
             * AGREGADO PARA TIPOS DE DOCUMENTOS DIFERENTES CREADOS POR EL USUARIO
             */
            if (is_numeric($tipdoc)) {
                $tiposDocumentosTransacciones = $database->selectColumns("tb_documentos_transacciones", ['id_cuenta_contable', 'tipo_dato'], "id=?", [$tipdoc]);
                if (empty($tiposDocumentosTransacciones)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró el tipo de documento");
                }
                if ($tiposDocumentosTransacciones[0]['tipo_dato'] == 2) {
                    if ($bancoid_cheque == 0) {
                        $showmensaje = true;
                        throw new Exception("Seleccione un banco");
                    }
                    if ($fecha_cheque == 0) {
                        $showmensaje = true;
                        throw new Exception("Seleccione una fecha para el cheque");
                    }
                    if ($numero_cheque == "") {
                        $showmensaje = true;
                        throw new Exception("El numero de cheque es obligatorio");
                    }
                }
                $idCuentaCaja = $tiposDocumentosTransacciones[0]['id_cuenta_contable'];
                $cuentaSaveTable = $bancoid_cheque;
                $nroChequeSaveTable = $numero_cheque;
                $fechaChequeSaveTable = $fecha_cheque;
            }

            /**
             * DATOS DE LA CUENTA DE AHORROS
             */

            $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,cli.no_identifica dpi,
                        cli.control_interno,IFNULL(estrict,0) estrict,cli.Direccion, tip.ccodtip,tip.nombre, tip.cdescripcion,
                        id_cuenta_contable,tip.tipcuen, calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) as saldo,
                            IFNULL((SELECT monto FROM ahomppg WHERE ccodaho=cta.ccodaho LIMIT 1),0) AS cuota
                        FROM `ahomcta` cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                        INNER JOIN ahomtip tip ON tip.ccodtip = SUBSTRING(cta.ccodaho, 7, 2)
                        WHERE `ccodaho`=? AND cta.estado='A'";

            $datosCuenta = $database->getAllResults($query, [$account]);
            if (empty($datosCuenta)) {
                $showmensaje = true;
                throw new Exception("No se encontraron datos de la cuenta");
            }

            if ($datosCuenta[0]['tipcuen'] == "pr" && $datosCuenta[0]['estrict'] == 1 && $monto != $datosCuenta[0]['cuota']) {
                $showmensaje = true;
                throw new Exception("El monto de la cuota pactada no coincide con el monto del deposito, verificar!!");
            }

            /**
             * VERIFICACION IVE, PRIMERO SE VERIFICA SI DURANTE LOS ULTIMOS 30 DIAS, NO SE HA LLENADO EL IVE
             */
            // $alertasLast30 = $database->getAllResults("SELECT id FROM tb_alerta WHERE tipo_alerta='IVE' AND estado=0 AND cod_aux=? 
            //                         AND fecha BETWEEN (DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND CURDATE();", [$account]);

            // if (empty($alertasLast30)) {

            //     $alertaHoy = $database->selectColumns('tb_alerta', ['id'], 'tipo_alerta=? AND estado=0 AND cod_aux=? AND fecha=CURDATE() AND codDoc=?', ['IVE', $account, $cnumdoc]);
            //     if (!empty($alertaHoy)) {
            //         /**
            //          * YA HAY UN REGISTRO APROBADO CON LA MISMA CUENTA, FECHA DE HOY, Y DOCUMENTO, DEJAR PASAR¡??
            //          */
            //     } else {
            //         /**
            //          * SI NO HAY REGISTROS AUTORIZADOS DURANTE ESTE DIA, CON LA MISMA CUENTA, HOY Y NUMERO DE DOCUMENTO
            //          */
            //         $alertaHoy = $database->selectColumns('tb_alerta', ['id'], 'tipo_alerta=? AND estado=1 AND cod_aux=? AND fecha=CURDATE() AND codDoc=?', ['IVE', $account, $cnumdoc]);
            //         if (!empty($alertaHoy)) {
            //             /**
            //              * SI HAY UN REGISTRO APROBADO, SE DEBE VERIFICAR SI EL MONTO DEPOSITADO + LOS ULTIMOS 30 DIAS EXCEDE LOS 10,000 DOLARES
            //              */
            //             $showmensaje = true;
            //             throw new Exception("Está pendiente la aprobación de una solicitud por parte del Administrador.");
            //         }

            //         $query = " SELECT IFNULL(SUM(monto),0) sumaDepositos FROM ahommov mov
            //             INNER JOIN ahomcta AS ac ON mov.ccodaho = ac.ccodaho
            //             WHERE ac.estado='A' AND mov.ctipope='D' AND cestado=1 
            //             AND (dfecope BETWEEN (DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND CURDATE())  AND ac.ccodcli=?";

            //         $datosIve = $database->getAllResults($query, [$datosCuenta[0]['ccodcli']]);
            //         $sumaDepositosLast30 = $datosIve[0]['sumaDepositos'] ?? 0;

            //         $service = new CurrencyExchangeService();
            //         $rate = $service->getExchangeRate('USD', 'GTQ');

            //         if (!$rate) {
            //             $rate = ['rate' => 7.7];
            //         }

            //         Log::info("datos previos:", [
            //             'sumaDepositosLast30' => $sumaDepositosLast30,
            //             'monto' => $monto,
            //             'rate' => $rate['rate']
            //         ]);

            //         if (($sumaDepositosLast30 + $monto) * $rate['rate'] > 10000) {
            //             Log::info("Exceso de monto:", [
            //                 'sumaDepositosLast30' => $sumaDepositosLast30,
            //                 'monto' => $monto,
            //                 'rate' => $rate['rate']
            //             ]);

            //             $tb_alerta = [
            //                 'puesto' => 'ADM',
            //                 'tipo_alerta' => 'IVE',
            //                 'mensaje' => 'Llenar el formulario del IVE',
            //                 'cod_aux' => $account,
            //                 'codDoc' => $cnumdoc,
            //                 'proceso' => 'EP',
            //                 'estado' => 1,
            //                 'fecha' => date('Y-m-d'),
            //                 'created_by' => $idusuario,
            //                 'created_at' => date('Y-m-d H:i:s'),
            //             ];

            //             $database->insert('tb_alerta', $tb_alerta);
            //             // echo json_encode(['alert ' . $alert . ' mov ' . $mov . ' monto ' . $monto . ' dolar ' . $dolar . '001 ALERTA IVE... en los últimos 30 días la cuenta del cliente ha superado los $10000, para continuar con la transacción el “contador o administrador” tiene que aprobar la alerta. Favor de apuntar el No. Documento: ' . $numdoc . '', '0']);

            //             $showmensaje = true;
            //             throw new Exception("001 ALERTA IVE... en los últimos 30 días la cuenta del cliente ha superado los $10,000, para continuar con la transacción se tiene que aprobar la alerta. Favor de apuntar el No. Documento: " . $cnumdoc . "");
            //         }
            //     }
            // }


            /**
             * INICIO DE TRANSACCIONES EN LA BD
             */

            // $cuentaSaveTable = $cuentaid;
            // $nroChequeSaveTable = $cnumdocboleta;
            // $fechaChequeSaveTable = $fechaBoleta;

            $database->beginTransaction();
            $ahommov = [
                'ccodaho' => $account,
                'dfecope' => $dfecope,
                'ctipope' => 'D',
                'cnumdoc' => $cnumdoc,
                'ctipdoc' => $tipdoc,
                'crazon' => 'DEPOSITO',
                'concepto' => $concepto,
                'nlibreta' => $datosCuenta[0]['nlibreta'],
                'nrochq' => $nroChequeSaveTable,
                'tipchq' => '',
                'fechaBanco' => $fechaChequeSaveTable,
                'idCuentaBanco' => $cuentaSaveTable,
                'dfeccomp' => '0000-00-00',
                'numpartida' => '0',
                'monto' => $monto,
                'lineaprint' => 'N',
                'numlinea' => 1,
                'correlativo' => 1,
                'dfecmod' => date('Y-m-d H:i:s'),
                'codusu' => $idusuario,
                'cestado' => 1,
                'auxi' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $idusuario,
            ];

            $idAhommov = $database->insert('ahommov', $ahommov);

            $database->executeQuery('CALL ahom_ordena_noLibreta(?, ?);', [$datosCuenta[0]['nlibreta'], $account]);
            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$account]);

            $database->executeQuery("CALL update_ppg_ahoProgramado(?)", [$account]);

            //AFECTACION CONTABLE
            // $numpartida = getnumcompdo($idusuario, $database); //Obtener numero de partida
            $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $dfecope);
            $ctb_diario = [
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 2,
                'id_tb_moneda' => 1,
                'numdoc' => $cnumdoc,
                'glosa' => $concepto,
                'fecdoc' => $fechaBancoSave,
                'feccnt' => $dfecope,
                'cod_aux' => $account,
                'id_tb_usu' => $idusuario,
                'id_agencia' => $idagencia,
                'karely' => 'AHO_' . $idAhommov,
                'fecmod' => date('Y-m-d H:i:s'),
                'estado' => 1,
                'editable' => 0,
                'created_by' => $idusuario,
            ];

            $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

            //AFECTACION CONTABLE MOV 1 
            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $idCuentaCaja,
                'debe' => $monto,
                'haber' => 0
            );
            $database->insert('ctb_mov', $datos);

            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $datosCuenta[0]['id_cuenta_contable'],
                'debe' => 0,
                'haber' => $monto
            );
            $database->insert('ctb_mov', $datos);

            if (is_numeric($tipdoc) && $tiposDocumentosTransacciones[0]['tipo_dato'] == 2) {

                $ctb_ban_mov = [
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_cuenta_banco' => $cuentaSaveTable,
                    'destino' => '-',
                    'numero' => $nroChequeSaveTable,
                    'fecha' => $fechaChequeSaveTable,
                    'estado' => 1, //1 entra en compensacion, 2 cuando ya esta liberado y cobrado, 0 rechazado
                ];

                $database->insert('ctb_ban_mov', $ctb_ban_mov);
            }

            if ($saveRte == 1) {
                $tb_RTE_use = [
                    'ccdocta' => $account,
                    'Nombre1' => '',
                    'Nombre2' => '',
                    'Nombre3' => '',
                    'Apellido1' => '',
                    'Apellido2' => '',
                    'Apellido_de_casada' => '',
                    'DPI' => '',
                    'ori_fondos' => '',
                    'desti_fondos' => '',
                    'Nacionalidad' => 'GT',
                    'Mon' => $monto,
                    'aux' => 'AUTOMATICO',
                    'Crateby' => $idusuario,
                    'Cretadate' => date('Y-m-d H:i:s'),
                    'recurrente' => 1,
                    'propietario' => 1,
                ];

                $database->insert('tb_RTE_use', $tb_RTE_use);
            }

            $database->commit();
            $mensaje = "Registro grabado correctamente";

            $status = 1;

            /**
             * ESTOS DATOS HABRIA QUE QUITARLOS DE ACA, Y CREAR UNA SECCION SOLO PARA CONSULTA DE LOS DATOS DE RECIBO
             */
            $format_monto = new NumeroALetras();
            $decimal = explode(".", $monto);
            $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
            $letras_monto = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";
            $particionfecha = explode("-", $dfecope);
            $auxdes = "Depósito a cuenta " . $account;
            $dpi = $datosCuenta[0]['dpi'];
            $producto = $datosCuenta[0]['nombre'];
            $controlinterno = $datosCuenta[0]['control_interno'];
            $saldoShow = $datosCuenta[0]['saldo'] + $monto;
            $direccion = $datosCuenta[0]['Direccion'];
            $ccodtip = $datosCuenta[0]['ccodtip'];
            $cdescripcion = $datosCuenta[0]['cdescripcion'];
            echo json_encode(['Registro grabado correctamente', '1', $account, number_format($monto, 2, '.', ','), setdatefrench($dfecope), $cnumdoc, $auxdes, $datosCuenta[0]['short_name'], ($_SESSION['nombre']), ($_SESSION['apellido']), $hoy, $letras_monto, $particionfecha[0], $particionfecha[1], $particionfecha[2], $dpi, $producto, $_SESSION['id'], $controlinterno, $nroChequeSaveTable, $tipdoc, $datosCuenta[0]['ccodcli'], $_SESSION["id_agencia"], 'D', $saldoShow, $monto, $direccion, $ccodtip, $cdescripcion, $concepto]);
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
            echo json_encode([$mensaje, $status]);
        } finally {
            $database->closeConnection();
        }

        break;

    case 'create_retiros_ahommov':

        /**
         * obtiene(
         * ['ccodaho', 'dfecope', 'cnumdoc', 'monto', 'numcheque', 'concepto','transfer_numero_referencia','transfer_fecha'],
         * ['salida', 'tipdoc', 'bancoid', 'cuentaid', 'negociable','transfer_cuentaid'],
         * [],
         * ['<?= $account ?>'],
         */

        try {
            $data = [
                'fecha_doc' => $_POST['inputs'][1] ?? null,
                'numdoc' => trim($_POST['inputs'][2] ?? ''),
                'monto' => $_POST['inputs'][3] ?? null,
                'numcheque' => $_POST['inputs'][4] ?? null,
                'concepto' => $_POST['inputs'][5] ?? 0,
                'transfer_numero_referencia' => $_POST['inputs'][6] ?? '',
                'transfer_fecha' => $_POST['inputs'][7] ?? null,
                'salida' => $_POST['selects'][0] ?? null,
                'tipdoc' => $_POST['selects'][1] ?? null,
                'bancoid' => $_POST['selects'][2] ?? null,
                'cuentaid' => $_POST['selects'][3] ?? null,
                'negociable' => $_POST['selects'][4] ?? null,
                'transfer_cuentaid' => $_POST['selects'][5] ?? null,
                'account' => $_POST['archivo'][0] ?? null,
            ];

            $rules = [
                'fecha_doc' => 'required|date',
                'numdoc' => 'required|min_length:1|max_length:100',
                'monto' => 'required|numeric|min:0.01',
                'concepto' => 'required|string|max_length:255',
                'salida' => 'required',
                'tipdoc' => 'required',
                'account' => 'required',
            ];
            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();

            /**
             * COMPROBACION DE CIERRES DE CAJA Y MES CONTABLE
             */
            $cierre_mes = comprobar_cierrePDO($_SESSION['id'], $data['fecha_doc'], $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }
            $cierre_caja = comprobar_cierre_cajaPDO($_SESSION['id'], $database);
            if ($cierre_caja[0] < 6) {
                $showmensaje = true;
                throw new Exception($cierre_caja[1]);
            }

            /**
             * COMPROBACION DE SALDO EN LA CUENTA
             */
            $query = "SELECT SUM(CASE WHEN `ctipope` = 'D' THEN monto ELSE 0 END) AS total_depositos,
                             SUM(CASE WHEN `ctipope` = 'R' THEN monto ELSE 0 END) AS total_retiros
                      FROM `ahommov` 
                      WHERE `ccodaho`=? AND cestado!=2";
            $movimientos = $database->getAllResults($query, [$data['account']]);
            $total_depositos = $movimientos[0]['total_depositos'] ?? 0;
            $total_retiros = $movimientos[0]['total_retiros'] ?? 0;
            // $saldo_actual = $total_depositos - $total_retiros;
            $saldo_actual = round($total_depositos - $total_retiros, 2);

            if ($data['monto'] > $saldo_actual) {
                $showmensaje = true;
                throw new Exception("Saldo insuficiente en la cuenta de ahorros");
            }

            /**
             * CONSULTA DE CUENTA DE CAJA
             */

            $datosGeneric = $database->selectColumns("tb_agencia", ["id_nomenclatura_caja"], "id_agencia=?", [$idagencia]);

            if (empty($datosGeneric)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de caja");
            }

            $idCuentaCaja = $datosGeneric[0]['id_nomenclatura_caja'];

            $banco_numdoc = '';
            $banco_fecha = null;
            $banco_idCuenta = NULL;

            /**
             * PARA CHEQUES
             */
            if ($data['tipdoc'] == "C") {
                if ($data['bancoid'] == 0) {
                    $showmensaje = true;
                    throw new Exception("Seleccione un banco");
                }
                if ($data['cuentaid'] == 0) {
                    $showmensaje = true;
                    throw new Exception("Seleccione una cuenta de banco");
                }
                if ($data['numcheque'] == "") {
                    $showmensaje = true;
                    throw new Exception("El numero de cheque es obligatorio");
                }

                // $query = "SELECT id_nomenclatura cuenta FROM ctb_bancos WHERE id=?";
                $datosGeneric = $database->selectColumns("ctb_bancos", ["id_nomenclatura"], "id=?", [$data['cuentaid']]);
                if (empty($datosGeneric)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta bancaria");
                }
                $idCuentaCaja = $datosGeneric[0]['id_nomenclatura'];

                $banco_numdoc = $data['numcheque'];
                // $banco_fecha = $data['fecha_doc'];
                $banco_idCuenta = $data['cuentaid'];
                // $cuentaSaveTable = $cuentaid;
                // $nroChequeSaveTable = $cnumdocboleta;
                // $fechaChequeSaveTable = $fechaBoleta;
                // $fechaBancoSave = $fechaBoleta;
            }

            /**
             * AGREGADO PARA TIPOS DE DOCUMENTOS DIFERENTES CREADOS POR EL USUARIO
             */
            $isTransferencia = false;
            if (is_numeric($data['tipdoc'])) {
                $database->openConnection();
                $tiposDocumentosTransacciones = $database->selectColumns("tb_documentos_transacciones", ['id_cuenta_contable', 'tipo_dato'], "id=?", [$data['tipdoc']]);
                if (empty($tiposDocumentosTransacciones)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró el tipo de documento");
                }
                $idCuentaCaja = $tiposDocumentosTransacciones[0]['id_cuenta_contable'];
                if ($tiposDocumentosTransacciones[0]['tipo_dato'] == 3) {
                    if ($data['transfer_cuentaid'] == 0) {
                        $showmensaje = true;
                        throw new Exception("Seleccione una cuenta de banco para la transferencia");
                    }
                    if ($data['transfer_fecha'] == '') {
                        $showmensaje = true;
                        throw new Exception("Seleccione una fecha para la transferencia");
                    }
                    if ($data['transfer_numero_referencia'] == "") {
                        $showmensaje = true;
                        throw new Exception("El numero de referencia es obligatorio");
                    }

                    $datosBancoTransfer = $database->selectColumns("ctb_bancos", ["id_nomenclatura"], "id=?", [$data['transfer_cuentaid']]);
                    if (empty($datosBancoTransfer)) {
                        $showmensaje = true;
                        throw new Exception("No se encontró la cuenta bancaria de transferencia");
                    }

                    $idCuentaCaja = $datosBancoTransfer[0]['id_nomenclatura'];
                    // $idCuentaBanco = $transfer_cuentaid;
                    // $fechaBoletaBanco = $transfer_fecha;
                    // $fechaDocBanco = $transfer_fecha;
                    $isTransferencia = true;
                    $banco_numdoc = $data['transfer_numero_referencia'];
                    $banco_fecha = $data['transfer_fecha'];
                    $banco_idCuenta = $data['transfer_cuentaid'];
                }

                // $cuentaSaveTable = $idCuentaCaja;
                // $nroChequeSaveTable = $transfer_numero_referencia;
                // $fechaChequeSaveTable = $transfer_fecha;
            }

            /**
             * DATOS DE LA CUENTA DE AHORROS
             */

            $query = "SELECT cta.ccodcli,cta.estado,cta.nlibreta,cli.no_tributaria num_nit,cli.short_name,cli.no_identifica dpi,
                        cli.control_interno,IFNULL(estrict,0) estrict,cli.Direccion, tip.ccodtip,tip.nombre, tip.cdescripcion,
                        id_cuenta_contable,tip.tipcuen, calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) as saldo
                        FROM `ahomcta` cta INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli 
                        INNER JOIN ahomtip tip ON tip.ccodtip = SUBSTRING(cta.ccodaho, 7, 2)
                        WHERE `ccodaho`=? AND cta.estado='A'";

            $datosCuenta = $database->getAllResults($query, [$data['account']]);
            if (empty($datosCuenta)) {
                $showmensaje = true;
                throw new Exception("No se encontraron datos de la cuenta");
            }

            /**
             * INICIO DE TRANSACCIONES EN LA BD
             */

            $database->beginTransaction();
            $ahommov = [
                'ccodaho' => $data['account'],
                'dfecope' => $data['fecha_doc'],
                'ctipope' => 'R',
                'cnumdoc' => $data['numdoc'],
                'ctipdoc' => $data['tipdoc'],
                'crazon' => 'RETIRO',
                'concepto' => $data['concepto'],
                'nlibreta' => $datosCuenta[0]['nlibreta'],
                'nrochq' => $banco_numdoc,
                'tipchq' => '',
                'fechaBanco' => $banco_fecha,
                'idCuentaBanco' => $banco_idCuenta,
                'dfeccomp' => '0000-00-00',
                'numpartida' => '',
                'monto' => $data['monto'],
                'lineaprint' => 'N',
                'numlinea' => 1,
                'correlativo' => 1,
                'dfecmod' => date('Y-m-d H:i:s'),
                'codusu' => $idusuario,
                'cestado' => 1,
                'auxi' => '',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $idusuario,
            ];

            $idAhommov = $database->insert('ahommov', $ahommov);

            $database->executeQuery('CALL ahom_ordena_noLibreta(?, ?);', [$datosCuenta[0]['nlibreta'], $data['account']]);
            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$data['account']]);

            //AFECTACION CONTABLE
            // $numpartida = getnumcompdo($idusuario, $database); //Obtener numero de partida
            $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $data['fecha_doc']);
            $ctb_diario = [
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 2,
                'id_tb_moneda' => 1,
                'numdoc' => $data['numdoc'],
                'glosa' => $data['concepto'],
                'fecdoc' => $banco_fecha ?? $data['fecha_doc'],
                'feccnt' => $data['fecha_doc'],
                'cod_aux' => $data['account'],
                'id_tb_usu' => $idusuario,
                'id_agencia' => $idagencia,
                'karely' => 'AHO_' . $idAhommov,
                'fecmod' => date('Y-m-d H:i:s'),
                'estado' => 1,
                'editable' => 0,
                'created_by' => $idusuario
            ];

            $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

            //AFECTACION CONTABLE MOV 1 
            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $idCuentaCaja,
                'debe' => 0,
                'haber' => $data['monto']
            );
            $database->insert('ctb_mov', $datos);

            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $datosCuenta[0]['id_cuenta_contable'],
                'debe' => $data['monto'],
                'haber' => 0
            );
            $database->insert('ctb_mov', $datos);

            if ($data['tipdoc'] == 'C') {
                $ctb_chq = [
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_cuenta_banco' => $banco_idCuenta,
                    'numchq' => $data['numcheque'],
                    'nomchq' => $datosCuenta[0]['short_name'],
                    'monchq' => $data['monto'],
                    'modocheque' => $data['negociable'],
                    'emitido' => '0',
                ];
                $database->insert('ctb_chq', $ctb_chq);
            }

            if ($isTransferencia) {

                $ctb_ban_mov = [
                    'id_ctb_diario' => $id_ctb_diario,
                    // 'id_cuenta_banco' => $cuentaSaveTable,
                    'destino' => '-',
                    'numero' => $data['transfer_numero_referencia'],
                    'fecha' => $data['transfer_fecha'],
                    // 'estado' => 1,
                ];

                $database->insert('ctb_ban_mov', $ctb_ban_mov);
            }

            $database->commit();
            $mensaje = "Retiro realizado correctamente";
            $status = 1;

            /**
             * RESPUESTA A RETORNAR, QUITAR ESTO DE ACA, HACER UNA PETICION SOLO PARA LOS RECIBOS
             */
            //NUMERO EN LETRAS
            $format_monto = new NumeroALetras();
            $decimal = explode(".", $data['monto']);
            $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
            $letras_monto = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";
            $particionfecha = explode("-", $data['fecha_doc']);
            echo json_encode([
                $mensaje,
                $status,
                $data['account'],
                number_format($data['monto'], 2, '.', ','),
                date("d-m-Y", strtotime($data['fecha_doc'])),
                $data['numdoc'],
                "Retiro de cuenta " . $data['account'],
                $datosCuenta[0]['short_name'],
                ($_SESSION['nombre']),
                ($_SESSION['apellido']),
                $hoy,
                $letras_monto,
                $particionfecha[0],
                $particionfecha[1],
                $particionfecha[2],
                $datosCuenta[0]['dpi'],
                $datosCuenta[0]['nombre'],
                $_SESSION['id'],
                $datosCuenta[0]['control_interno'],
                $data['numcheque'],
                $data['tipdoc'],
                $datosCuenta[0]['ccodcli'],
                $_SESSION["id_agencia"],
                'R',
                ($saldo_actual - $data['monto']),
                $data['monto'],
                $datosCuenta[0]['Direccion'],
                $datosCuenta[0]['ccodtip'],
                $datosCuenta[0]['cdescripcion'],
                $data['concepto']
            ]);
        } catch (Throwable $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
            echo json_encode([$mensaje, $status]);
        } finally {
            $database->closeConnection();
        }

        break;

    case 'create_transferencia':

        //'<?= $csrf->getTokenName()','cuenta_origen_codigo','cuenta_destino_codigo','numero_documento','fecha_transferencia','monto_transferencia','concepto_transferencia'
        list($csrftoken, $cuenta_origen_codigo, $cuenta_destino_codigo, $numero_documento, $fecha_transferencia, $monto_transferencia, $concepto_transferencia) = $_POST['inputs'];
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

        $showmensaje = false;
        try {

            if ($numero_documento == "" || $numero_documento == null) {
                $showmensaje = true;
                throw new Exception("El número de documento es obligatorio");
            }
            if (!validateDate($fecha_transferencia, 'Y-m-d')) {
                $showmensaje = true;
                throw new Exception("Fecha de transferencia inválida");
            }
            if (!is_numeric($monto_transferencia) || $monto_transferencia <= 0) {
                $showmensaje = true;
                throw new Exception("Monto de transferencia inválido");
            }
            if ($concepto_transferencia == "" || $concepto_transferencia == null) {
                $showmensaje = true;
                throw new Exception("El concepto de transferencia es obligatorio");
            }

            $database->openConnection();

            // $cuentaOrigen = $database->selectColumns('ahomcta', ['nlibreta'], 'ccodaho=? AND cestado="A"', [$cuenta_origen_codigo]);
            $cuentaOrigen = $database->getAllResults("SELECT nlibreta, calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) as saldo
                                                        FROM ahomcta cta
                                                        WHERE cta.ccodaho = ? AND cta.estado = 'A'", [$cuenta_origen_codigo]);
            if (empty($cuentaOrigen)) {
                $showmensaje = true;
                throw new Exception("La cuenta de origen no existe o no está activa");
            }
            $libretaOrigen = $cuentaOrigen[0]['nlibreta'];
            $saldoOrigen = $cuentaOrigen[0]['saldo'];

            $cuentaDestino = $database->selectColumns('ahomcta', ['nlibreta'], 'ccodaho=? AND estado="A"', [$cuenta_destino_codigo]);
            if (empty($cuentaDestino)) {
                $showmensaje = true;
                throw new Exception("La cuenta de destino no existe o no está activa");
            }
            $libretaDestino = $cuentaDestino[0]['nlibreta'];

            if ($cuenta_origen_codigo == $cuenta_destino_codigo) {
                $showmensaje = true;
                throw new Exception("No se puede transferir entre la misma cuenta");
            }

            if ($monto_transferencia > $saldoOrigen) {
                $showmensaje = true;
                throw new Exception("El monto de transferencia es mayor al saldo disponible en la cuenta de origen");
            }

            $database->beginTransaction();

            $ahommovOrigen = array(
                "ccodaho" => $cuenta_origen_codigo,
                "dfecope" => $fecha_transferencia,
                "ctipope" => "R",
                "cnumdoc" => $numero_documento,
                "ctipdoc" => 'T',
                "crazon" => "RETIRO POR TRANSFERENCIA",
                "concepto" => $concepto_transferencia,
                "nlibreta" => $libretaOrigen,
                "nrochq" => "",
                "tipchq" => "",
                "fechaBanco" => NULL,
                "idCuentaBanco" => NULL,
                "dfeccomp" => "0000-00-00",
                "numpartida" => "0",
                "monto" => $monto_transferencia,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => $hoy2,
                "codusu" => $idusuario,
                "cestado" => 1,
                "auxi" => "TRANSFERENCIA_ORIGEN_" . $cuenta_destino_codigo,
                "created_at" => $hoy2,
                "created_by" => $idusuario,
            );

            $ahommovDestino = array(
                "ccodaho" => $cuenta_destino_codigo,
                "dfecope" => $fecha_transferencia,
                "ctipope" => "D",
                "cnumdoc" => $numero_documento,
                "ctipdoc" => 'T',
                "crazon" => "DEPOSITO POR TRANSFERENCIA",
                "concepto" => $concepto_transferencia,
                "nlibreta" => $libretaDestino,
                "nrochq" => "",
                "tipchq" => "",
                "fechaBanco" => NULL,
                "idCuentaBanco" => NULL,
                "dfeccomp" => "0000-00-00",
                "numpartida" => "0",
                "monto" => $monto_transferencia,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => $hoy2,
                "codusu" => $idusuario,
                "cestado" => 1,
                "auxi" => "TRANSFERENCIA_DESTINO_" . $cuenta_origen_codigo,
                "created_at" => $hoy2,
                "created_by" => $idusuario,
            );

            $database->insert('ahommov', $ahommovOrigen);
            $database->insert('ahommov', $ahommovDestino);

            $database->executeQuery('CALL ahom_ordena_noLibreta(?, ?);', [$libretaOrigen, $cuenta_origen_codigo]);
            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$cuenta_origen_codigo]);

            $database->executeQuery('CALL ahom_ordena_noLibreta(?, ?);', [$libretaDestino, $cuenta_destino_codigo]);
            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$cuenta_destino_codigo]);

            $codigosTipos[0] = substr($cuenta_origen_codigo, 6, 2);
            $codigosTipos[1] = substr($cuenta_destino_codigo, 6, 2);

            $cuentasContableOrigen = $database->selectColumns('ahomtip', ['id_cuenta_contable'], 'ccodtip =?', [$codigosTipos[0]]);
            if (empty($cuentasContableOrigen)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta contable para la cuenta de origen.");
            }
            $cuentasContableDestino = $database->selectColumns('ahomtip', ['id_cuenta_contable'], 'ccodtip =?', [$codigosTipos[1]]);
            if (empty($cuentasContableDestino)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta contable para la cuenta de destino.");
            }

            //AFECTACION CONTABLE
            // $numpartida = getnumcompdo($idusuario, $database); //Obtener numero de partida
            $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fecha_transferencia);
            $datos = array(
                'numcom' => $numpartida,
                'id_ctb_tipopoliza' => 2,
                'id_tb_moneda' => 1,
                'numdoc' => $numero_documento,
                'glosa' => $concepto_transferencia,
                'fecdoc' => $fecha_transferencia,
                'feccnt' => $fecha_transferencia,
                'cod_aux' => $cuenta_origen_codigo . " - " . $cuenta_destino_codigo,
                'id_tb_usu' => $idusuario,
                'id_agencia' => $idagencia,
                'fecmod' => $hoy2,
                'estado' => 1,
                'editable' => 0
            );


            $id_ctb_diario = $database->insert('ctb_diario', $datos);

            //AFECTACION CONTABLE MOV 1 
            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentasContableOrigen[0]['id_cuenta_contable'],
                'debe' => $monto_transferencia,
                'haber' => 0
            );
            $database->insert('ctb_mov', $datos);

            $datos = array(
                'id_ctb_diario' => $id_ctb_diario,
                'id_fuente_fondo' => 1,
                'id_ctb_nomenclatura' => $cuentasContableDestino[0]['id_cuenta_contable'],
                'debe' => 0,
                'haber' => $monto_transferencia
            );
            $database->insert('ctb_mov', $datos);

            $database->commit();
            $mensaje = "Registro grabado correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([$mensaje, $status]);

        break;
    case 'acreditaindi':

        //obtiene(['<?= $csrf->getTokenName() ','dfecope', 'monint', 'monipf', 'monipx'], [], [], 'acreditaindi', '0', ['<?= htmlspecialchars($secureID->encrypt($account)) ']);

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }
        list($csrftoken, $fecope, $montoint, $montoipf, $montoipx, $cnumdoc) = $_POST['inputs'];
        list($encryptedID) = $_POST['archivo'];

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

        $ccodaho = $secureID->decrypt($encryptedID);

        if (!validateDate($fecope, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', '0']);
            return;
        }
        if (!is_numeric($montoint)) {
            echo json_encode(['Monto Inválido (Interés)', '0']);
            return;
        }

        $montoipf = (is_numeric($montoipf)) ? $montoipf : 0;
        // if ($montoint <= 0) {
        //     echo json_encode(['Monto negativo ó igual a 0 (Interés)', '0']);
        //     return;
        // }
        // if (is_numeric($montoipf)) {
        //     if ($montoipf < 0) {
        //         echo json_encode(['Monto negativo (Impuesto)', '0']);
        //         return;
        //     }
        // } else {
        //     $montoipf = 0;
        // }
        if ($montoint <= 0 && $montoipf <= 0) {
            echo json_encode(['El monto de interés y el monto de impuesto no pueden ser ambos cero o negativos', '0']);
            return;
        }
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++  DATOS DE LA CUENTA DE AHORROS ++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        $status = 0;
        try {
            $database->openConnection();
            $database->beginTransaction();
            $result = $database->selectColumns('ahomcta', ['nlibreta', 'ctainteres'], 'ccodaho=?', [$ccodaho]);
            if (empty($result)) {
                throw new SoftException("No se encontró la cuenta de ahorro");
            }
            $nlibreta = $result[0]['nlibreta'];
            $cueninteres = $result[0]['ctainteres'];
            $cuentaDestino = $ccodaho;

            $numeroDocumento = ($cnumdoc != "" && $cnumdoc != null) ? $cnumdoc : "INT";

            if ($cueninteres != "" && $cueninteres != null) {
                $result = $database->selectColumns('ahomcta', ['ccodaho'], 'ccodaho=?', [$cueninteres]);
                if (empty($result)) {
                    throw new SoftException("La cuenta secundaria configurada no existe");
                }
                $cuentaDestino = $cueninteres;
            }
            $datosint = array(
                "ccodaho" => $cuentaDestino,
                "dfecope" => $fecope,
                "ctipope" => "D",
                "cnumdoc" => $numeroDocumento,
                "ctipdoc" => "IN",
                "crazon" => "INTERES",
                "concepto" => "ACREDITACION DE INTERESES A CUENTA DE AHORROS: " . $cuentaDestino,
                "nlibreta" => $nlibreta,
                "nrochq" => 0,
                "tipchq" => "",
                "dfeccomp" => NULL,
                "monto" => $montoint,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => $hoy2,
                "codusu" => $idusuario,
                "cestado" => 1,
                "auxi" => "ACREDITACION INDIVIDUAL",
                "created_at" => $hoy2,
                "created_by" => $idusuario,
            );
            $datosipf = array(
                "ccodaho" => $cuentaDestino,
                "dfecope" => $fecope,
                "ctipope" => "R",
                "cnumdoc" => $numeroDocumento,
                "ctipdoc" => "IP",
                "crazon" => "INTERES",
                "concepto" => "RETENCION DE ISR: " . $cuentaDestino,
                "nlibreta" => $nlibreta,
                "nrochq" => 0,
                "tipchq" => "",
                "dfeccomp" => NULL,
                "monto" => $montoipf,
                "lineaprint" => "N",
                "numlinea" => 1,
                "correlativo" => 1,
                "dfecmod" => $hoy2,
                "codusu" => $idusuario,
                "cestado" => 1,
                "auxi" => "ACREDITACION INDIVIDUAL",
                "created_at" => $hoy2,
                "created_by" => $idusuario,
            );

            if ($montoint > 0) {
                $idAhommov = $database->insert('ahommov', $datosint);
            }
            if ($montoipf > 0) {
                $idAhommov2 = $database->insert('ahommov', $datosipf);
            }

            $database->executeQuery('CALL ahom_ordena_noLibreta(?, ?);', [$nlibreta, $cuentaDestino]);
            $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$cuentaDestino]);

            //MOVIMIENTOS EN LA CONTA
            $result = $database->getAllResults("SELECT ap.* FROM ahomparaintere ap INNER JOIN ahomtip tip ON tip.id_tipo=ap.id_tipo_cuenta 
                    WHERE ccodtip=SUBSTR(?,7,2) AND id_descript_intere IN (1,2)", [$ccodaho]);

            if (empty($result)) {
                throw new SoftException("No se encontraron cuentas contables parametrizadas.");
            }
            $keyint = array_search(1, array_column($result, 'id_descript_intere'));
            $keyisr = array_search(2, array_column($result, 'id_descript_intere'));

            if ($keyint === false || $keyisr === false) {
                throw new SoftException("No se encontraron cuentas contables parametrizadas ()." . $keyisr);
            }

            $cuentaint1 = $result[$keyint]['id_cuenta1'];
            $cuentaint2 = $result[$keyint]['id_cuenta2'];
            $cuentaisr1 = $result[$keyisr]['id_cuenta1'];
            $cuentaisr2 = $result[$keyisr]['id_cuenta2'];

            if ($montoint > 0) {
                // $numpartida = getnumcompdo($idusuario, $database); //Obtener numero de partida
                $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fecope);
                $datos = array(
                    'numcom' => $numpartida,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => $numeroDocumento,
                    'glosa' => "ACREDITACION DE INTERESES A CUENTA DE AHORROS: " . $cuentaDestino,
                    'fecdoc' => $fecope,
                    'feccnt' => $fecope,
                    'cod_aux' => $cuentaDestino,
                    'id_tb_usu' => $idusuario,
                    'karely' => "AHO_" . $idAhommov,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                    'created_by' => $idusuario,
                );


                $id_ctb_diario = $database->insert('ctb_diario', $datos);

                //AFECTACION CONTABLE MOV 1 
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaint1,
                    'debe' => $montoint,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $datos);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaint2,
                    'debe' => 0,
                    'haber' => $montoint
                );
                $database->insert('ctb_mov', $datos);
            }


            if ($montoipf > 0) {
                // $numpartida2 = getnumcompdo($idusuario, $database); //Obtener numero de partida
                $numpartida2 = Beneq::getNumcom($database, $idusuario, $idagencia, $fecope);
                $ctb_diario = array(
                    'numcom' => $numpartida2,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => $numeroDocumento,
                    'glosa' => "RETENCION DE ISR: " . $cuentaDestino,
                    'fecdoc' => $fecope,
                    'feccnt' => $fecope,
                    'cod_aux' => $cuentaDestino,
                    'id_tb_usu' => $idusuario,
                    'karely' => "AHO_" . $idAhommov2,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                    'created_by' => $idusuario,
                );

                $id_ctb_diario2 = $database->insert('ctb_diario', $ctb_diario);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario2,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaisr1,
                    'debe' => $montoipf,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $datos);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario2,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaisr2,
                    'debe' => 0,
                    'haber' => $montoipf
                );
                $database->insert('ctb_mov', $datos);
            }


            if ($montoipx > 0) {
                $result = $database->getAllResults("SELECT ap.* FROM ahomparaintere ap INNER JOIN ahomtip tip ON tip.id_tipo=ap.id_tipo_cuenta 
                    WHERE ccodtip=SUBSTR(?,7,2) AND id_descript_intere =3", [$ccodaho]);

                if (empty($result)) {
                    throw new SoftException("No se encontraron cuentas contables parametrizadas para provision.");
                }

                $cuentaprx1 = $result[0]['id_cuenta1'];
                $cuentaprx2 = $result[0]['id_cuenta2'];

                // $numpartida3 = getnumcompdo($idusuario, $database); //Obtener numero de partida
                $numpartida3 = Beneq::getNumcom($database, $idusuario, $idagencia, $fecope);
                $ctb_diario = array(
                    'numcom' => $numpartida3,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => $numeroDocumento,
                    'glosa' => "PROVISION DE INTERES: " . $cuentaDestino,
                    'fecdoc' => $fecope,
                    'feccnt' => $fecope,
                    'cod_aux' => $cuentaDestino,
                    'id_tb_usu' => $idusuario,
                    // 'karely' => "AHO_" . $idAhommov2,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                    'created_by' => $idusuario,
                );
                $id_ctb_diario3 = $database->insert('ctb_diario', $ctb_diario);
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario3,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaprx1,
                    'debe' => $montoipx,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $datos);
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario3,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaprx2,
                    'debe' => 0,
                    'haber' => $montoipx
                );
                $database->insert('ctb_mov', $datos);
            }

            $database->commit();
            $mensaje = "Registro grabado correctamente";
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
        echo json_encode([
            $mensaje,
            $status,
            'idAhommov' => $idAhommov ?? null,
            'idAhommov2' => $idAhommov2 ?? null
        ]);
        break;
    case 'modlib':
        $inputs = $_POST["inputs"];
        $inputsn = $_POST["inputsn"];  // 
        $archivos = $_POST["archivo"];
        $hoy = date("Y-m-d H:i:s");
        $hoy2 = date("Y-m-d");
        $validar = validarcampo($inputs, "");
        if ($validar == "1") {
            if ($inputs[1] < 1) {
                echo json_encode(['Ingrese un numero valido', '0']);
            } else {
                //------traer el saldo de la cuenta
                $monto = 0;
                $saldo = 0;
                $transac = mysqli_query($conexion, "SELECT `monto`,`ctipope` FROM `ahommov` WHERE `ccodaho`='$archivos[0]' AND cestado!=2");
                while ($row = mysqli_fetch_array($transac, MYSQLI_ASSOC)) {
                    $tiptr = encode_utf8($row["ctipope"]);
                    $monto = encode_utf8($row["monto"]);
                    if ($tiptr == "R") {
                        $saldo = $saldo - $monto;
                    }
                    if ($tiptr == "D") {
                        $saldo = $saldo + $monto;
                    }
                }
                //****fin saldo */
                //transaccion
                $conexion->autocommit(false);
                try {
                    $ultimonum = lastnumlin($inputs[0], $archivos[1], "ahommov", "ccodaho", $conexion);
                    $ultimocorrel = lastcorrel($inputs[0], $archivos[1], "ahommov", "ccodaho", $conexion);
                    //desactivar en ahomlib los datos de la nueva libreta
                    $conexion->query("UPDATE `ahomlib` SET `estado` = 'B',`date_fin` = '$hoy' WHERE `ccodaho` = '$inputs[0]' AND `nlibreta`=  '$archivos[1]'");
                    //insertar en ahomlib   ['ccodaho','newLibret'],['nothing'], ['nada'], 'modlib', '0', ['<?php echo $id; ',<?php echo $nlibreta; ,'<?php echo $ccodusu; ']
                    $conexion->query("INSERT INTO `ahomlib`(`nlibreta`,`ccodaho`,`estado`,`date_ini`,`ccodusu`,`crazon`) VALUES ('$inputs[1]','$inputs[0]','A','$hoy2','$archivos[2]','maxlin')");
                    //insertar en ahommov
                    $conexion->query("INSERT INTO `ahommov`(`ccodaho`,`dfecope`,`ctipope`,`cnumdoc`,`ctipdoc`,`crazon`,`nlibreta`,`nrochq`,`tipchq`,`numpartida`,`monto`,`lineaprint`,`numlinea`,`correlativo`,`dfecmod`,`codusu`) VALUES ('$inputs[0]','$hoy2','R','LIB0001','E','CAMBIO LIBRETA', '$archivos[1]','','','','$saldo','N',$ultimonum+1,$ultimocorrel+1,'$hoy','$archivos[2]')");
                    $conexion->query("INSERT INTO `ahommov`(`ccodaho`,`dfecope`,`ctipope`,`cnumdoc`,`ctipdoc`,`crazon`,`nlibreta`,`nrochq`,`tipchq`,`numpartida`,`monto`,`lineaprint`,`numlinea`,`correlativo`,`dfecmod`,`codusu`) VALUES ('$inputs[0]','$hoy2','D','LIB0001','E','SALDO INI', '$inputs[1]','','','','$saldo','N',1,$ultimocorrel+2,'$hoy','$archivos[2]')");
                    //actualizar en ahomcta
                    $conexion->query("UPDATE `ahomcta` SET `nlibreta` = '$inputs[1]',`numlinea` = 1,`correlativo` = $ultimocorrel+2 WHERE `ccodaho` = '$inputs[0]'");

                    if ($conexion->commit()) {
                        echo json_encode(['Datos ingresados correctamente', '1']);
                    } else {
                        echo json_encode(['Error al ingresar: ', '0']);
                    }
                } catch (Exception $e) {
                    $conexion->rollback();
                    echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                }
                //fin transaccion
            }
        } else {
            echo json_encode([$validar, '0']);
        }
        mysqli_close($conexion);
        break;
    /*--------------------------------------------------------------------------------- */
    case 'cahomben':
        //obtiene(['<?= $csrf->getTokenName()','benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent'], ['benparent'], [], 'cahomben', '<?= $idCertificado', ['<?= htmlspecialchars($secureID->encrypt($idCertificado))']

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        list($csrftoken, $benname, $bendpi, $bendire, $bentel, $bennac, $benporcent) = $_POST["inputs"];
        list($benparent) = $_POST["selects"];
        list($encryptedID) = $_POST["archivo"];

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
        if (!validateDate($bennac, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($bennac > $hoy) {
            echo json_encode(['Fecha de nacimiento inválida', 0]);
            return;
        }

        $validar = validacionescampos([
            [$benname, "", 'Ingrese un nombre para el beneficiario', 1],
            [$bendpi, "", 'Ingrese el numero de identificacion', 1],
            [$bendire, "", 'Ingrese la direccion del beneficiario', 1],
            [$bentel, "", 'Ingrese un numero de telefono para el beneficiario', 1],
            [$benporcent, "", 'Ingrese el porcentaje', 1],
            [$benporcent, 0, 'Porcentaje invalido', 6],
            [$benparent, "0", 'Seleccione parentesco', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        if (preg_match('/^\d{13}$/', $bendpi) == false) {
            echo json_encode(['Ingrese un número de DPI válido, debe tener 13 caracteres numericos', '0']);
            return;
        }
        if (preg_match('/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})$/', $bentel) == false) {
            echo json_encode(['Debe digitar un número de teléfono válido', '0']);
            return;
        }

        $idCertificado = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {
            $database->openConnection();
            $account = $database->selectColumns('ahomcrt', ['codaho', 'ccodcrt'], 'id_crt=?', [$idCertificado]);
            if (empty($account)) {
                $showmensaje = true;
                throw new Exception("No se encontró el certificado de ahorro");
            }

            $sumaPorcentajes = $database->getAllResults(" SELECT IFNULL(SUM(porcentaje),0) as total FROM ahomben WHERE codaho=?", [$account[0]['codaho']]);
            if (($sumaPorcentajes[0]['total'] + $benporcent) > 100) {
                $showmensaje = true;
                throw new Exception("La suma de los porcentajes no puede ser mayor a 100%");
            }

            $database->beginTransaction();
            $database->insert('ahomben', [
                'codaho' => $account[0]['codaho'],
                'nombre' => $benname,
                'dpi' => $bendpi,
                'direccion' => $bendire,
                'codparent' => $benparent,
                'fecnac' => $bennac,
                'porcentaje' => $benporcent,
                'telefono' => $bentel,
                'ccodcrt' => $account[0]['ccodcrt']
            ]);

            $database->commit();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);

        break;
    case 'dahomben':
        //obtiene([`' . $csrf->getTokenName() . '`], [], [], `dahomben`, ' . $idCertificado . ', [`' . htmlspecialchars($secureID->encrypt($value['id_ben'])) . '`]

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        list($csrftoken) = $_POST["inputs"];
        list($encryptedID) = $_POST["archivo"];

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
        $idBeneficiario = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {
            $database->openConnection();
            $dataBeneficiario = $database->selectColumns('ahomben', ['porcentaje'], 'id_ben=?', [$idBeneficiario]);
            if (empty($dataBeneficiario)) {
                $showmensaje = true;
                throw new Exception("No se encontró el beneficiario");
            }

            $database->beginTransaction();
            $database->delete('ahomben', 'id_ben=?', [$idBeneficiario]);

            $database->commit();
            $mensaje = "Beneficiario eliminado correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;
    case 'uahomben':
        //obtiene(['<?= $csrf->getTokenName()','benname', 'bendpi', 'bendire', 'bentel', 'bennac', 'benporcent','benporcentant','idben'], ['benparent'], [], 'uahomben', '<?= $idCertificado; ', ['<?= htmlspecialchars($secureID->encrypt($idCertificado))']
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        list($csrftoken, $benname, $bendpi, $bendire, $bentel, $bennac, $benporcent, $idBeneficiario) = $_POST["inputs"];
        list($benparent) = $_POST["selects"];
        list($encryptedID) = $_POST["archivo"];

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
        if (!validateDate($bennac, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($bennac > $hoy) {
            echo json_encode(['Fecha de nacimiento inválida', 0]);
            return;
        }

        $validar = validacionescampos([
            [$benname, "", 'Ingrese un nombre para el beneficiario', 1],
            [$bendpi, "", 'Ingrese el numero de identificacion', 1],
            [$bendire, "", 'Ingrese la direccion del beneficiario', 1],
            [$bentel, "", 'Ingrese un numero de telefono para el beneficiario', 1],
            [$benporcent, "", 'Ingrese el porcentaje', 1],
            [$benporcent, 0, 'Porcentaje invalido', 6],
            [$benparent, "0", 'Seleccione parentesco', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        if (preg_match('/^\d{13}$/', $bendpi) == false) {
            echo json_encode(['Ingrese un número de DPI válido, debe tener 13 caracteres numericos', '0']);
            return;
        }
        if (preg_match('/^(?:\+?502\s?)?(?:\d{10}|\d{8}|\d{4}\s?\d{4}|\d{7}|\d{9})$/', $bentel) == false) {
            echo json_encode(['Debe digitar un número de teléfono válido', '0']);
            return;
        }

        $idCertificado = $secureID->decrypt($encryptedID);


        $showmensaje = false;
        try {
            $database->openConnection();
            $account = $database->selectColumns('ahomcrt', ['codaho', 'ccodcrt'], 'id_crt=?', [$idCertificado]);
            if (empty($account)) {
                $showmensaje = true;
                throw new Exception("No se encontró el certificado de ahorro");
            }

            $dataBeneficiario = $database->selectColumns('ahomben', ['porcentaje'], 'id_ben=?', [$idBeneficiario]);
            if (empty($dataBeneficiario)) {
                $showmensaje = true;
                throw new Exception("No se encontró el beneficiario");
            }

            $sumaPorcentajes = $database->getAllResults("SELECT IFNULL(SUM(porcentaje),0) as total FROM ahomben WHERE codaho=? AND id_ben!=?;", [$account[0]['codaho'], $idBeneficiario]);
            if (($sumaPorcentajes[0]['total'] + $benporcent) > 100) {
                $showmensaje = true;
                throw new Exception("La suma de los porcentajes no puede ser mayor a 100%" . $idBeneficiario);
            }

            $database->beginTransaction();
            $database->update('ahomben', [
                'nombre' => $benname,
                'dpi' => $bendpi,
                'direccion' => $bendire,
                'codparent' => $benparent,
                'fecnac' => $bennac,
                'porcentaje' => $benporcent,
                'telefono' => $bentel,
                'ccodcrt' => $account[0]['ccodcrt']
            ], 'id_ben=?', [$idBeneficiario]);

            $database->commit();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;
    case 'cahomcrt':
        //['<?= $csrf->getTokenName()',`certif`,`monapr`,`plazo`,`gracia`,`tasint`,`fecaper`,`fecven`,`norecibo`],[`calintere`,`pagintere`],[],`cahomcrt`,`0`,['<?= htmlspecialchars($secureID->encrypt($account)) ']);

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        list($csrftoken, $codcrt, $montoApertura, $plazo, $diasGracia, $tasa, $fecApertura, $fecVence, $noRecibo) = $_POST["inputs"];
        list($calInteres, $pagIntere) = $_POST["selects"];
        list($encryptedID) = $_POST["archivo"];

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
        if (!validateDate($fecApertura, 'Y-m-d') || !validateDate($fecVence, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($fecApertura > $fecVence) {
            echo json_encode(['Rango de fechas Inválido', 0]);
            return;
        }

        $validar = validacionescampos([
            [$codcrt, "", 'Ingrese un numero de certificado', 1],
            [$montoApertura, "", 'Ingrese un monto de apertura', 1],
            [$montoApertura, 0, 'Ingrese un monto de apertura valido', 6],
            [$plazo, "", 'Ingrese un plazo', 1],
            [$plazo, 0, 'Ingrese un plazo valido', 6],
            [$tasa, "", 'Ingrese una tasa de interés', 1],
            [$tasa, 0, 'Ingrese una tasa de interés valida', 6],
            [$fecApertura, "", 'Ingrese una fecha de apertura', 1],
            [$fecVence, "", 'Ingrese una fecha de vencimiento', 1],
            // [$noRecibo, "", 'Ingrese un número de recibo', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $codCuenta = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {
            $database->openConnection();
            $account = $database->selectColumns('ahomcta', ['ccodaho'], 'ccodaho=?', [$codCuenta]);
            if (empty($account)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta");
            }

            $database->beginTransaction();
            $database->insert('ahomcrt', [
                'ccodcrt' => $codcrt,
                'codaho' => $account[0]['ccodaho'],
                'montoapr' => $montoApertura,
                'plazo' => $plazo,
                'interes' => $tasa,
                'recibo' => $noRecibo,
                'fec_apertura' => $fecApertura,
                'fec_ven' => $fecVence,
                'dia_gra' => $diasGracia,
                'calint' => $calInteres,
                'pagint' => $pagIntere,
                'estado' => 1,
                'liquidado' => 'N',
                'created_at' => $hoy2,
                'created_by' => $idusuario,
                'codusu' => $idusuario
            ]);

            $database->commit();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        return;

        break;
    case 'uahomcrt':
        //obtiene(['<?= $csrf->getTokenName()',`certif`,`monapr`,`plazo`,`gracia`,`tasint`,`fecaper`,`fecven`,`norecibo`],[`calintere`,`pagintere`],[],`uahomcrt`,`0`,['<?= htmlspecialchars($secureID->encrypt($idCertificado)) ']);printdiv('certificados', '#cuadro', 'aho_04', '0')">

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        list($csrftoken, $codcrt, $montoApertura, $plazo, $diasGracia, $tasa, $fecApertura, $fecVence, $noRecibo) = $_POST["inputs"];
        list($calInteres, $pagIntere) = $_POST["selects"];
        list($encryptedID) = $_POST["archivo"];

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
        if (!validateDate($fecApertura, 'Y-m-d') || !validateDate($fecVence, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($fecApertura > $fecVence) {
            echo json_encode(['Rango de fechas Inválido', 0]);
            return;
        }

        $validar = validacionescampos([
            [$codcrt, "", 'No hay numero de certificado', 1],
            [$montoApertura, "", 'Ingrese un monto de apertura', 1],
            [$montoApertura, 0, 'Ingrese un monto de apertura valido', 6],
            [$plazo, "", 'Ingrese un plazo', 1],
            [$plazo, 0, 'Ingrese un plazo valido', 6],
            [$tasa, "", 'Ingrese una tasa de interés', 1],
            [$tasa, 0, 'Ingrese una tasa de interés valida', 6],
            [$fecApertura, "", 'Ingrese una fecha de apertura', 1],
            [$fecVence, "", 'Ingrese una fecha de vencimiento', 1],
            // [$noRecibo, "", 'Ingrese un número de recibo', 1],
        ]);

        if ($validar[2]) {
            echo json_encode([$validar[0], $validar[1]]);
            return;
        }

        $idCertificado = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {
            $database->openConnection();
            $account = $database->selectColumns('ahomcrt', ['id_crt'], 'id_crt=?', [$idCertificado]);
            if (empty($account)) {
                $showmensaje = true;
                throw new Exception("No se encontró el certificado");
            }

            $database->beginTransaction();
            $database->update('ahomcrt', [
                'montoapr' => $montoApertura,
                'plazo' => $plazo,
                'interes' => $tasa,
                'recibo' => $noRecibo,
                'fec_apertura' => $fecApertura,
                'fec_ven' => $fecVence,
                'dia_gra' => $diasGracia,
                'calint' => $calInteres,
                'pagint' => $pagIntere,
                'estado' => 1,
                'fec_mod' => $hoy2,
                'codusu' => $idusuario
            ], 'id_crt=?', [$idCertificado]);

            $database->commit();
            $mensaje = "Certificado actualizado correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;

    case 'dahomcrt':
        //obtiene(['csrf_token'], [], [], 'dahomcrt', '0', ['${data}']
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++ VALIDACIONES Y ASIGNACION DE DATOS  +++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        list($csrftoken) = $_POST["inputs"];
        list($encryptedID) = $_POST["archivo"];

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

        // $idCertificado = $secureID->decrypt($encryptedID);
        $idCertificado = $encryptedID;

        $showmensaje = false;
        try {
            $database->openConnection();
            $account = $database->selectColumns('ahomcrt', ['id_crt'], 'id_crt=?', [$idCertificado]);
            if (empty($account)) {
                $showmensaje = true;
                throw new Exception("No se encontró el certificado");
            }

            $database->beginTransaction();
            $database->update('ahomcrt', [
                'estado' => 0,
                'deleted_at' => $hoy2,
                'deleted_by' => $idusuario
            ], 'id_crt=?', [$idCertificado]);

            $database->commit();
            $mensaje = "Certificado eliminado correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;

    case 'calfec':
        $fecapr = $_POST["fecapr"];
        $fecven = $_POST["fecven"];
        $plazo = $_POST["plazo"];
        $monto = $_POST["mon"];
        $intere = $_POST["int"];
        $account = $_POST["account"];
        $cond = $_POST["cond"];

        $showmensaje = false;
        try {
            $database->openConnection();
            $ahomtip = $database->selectColumns('ahomtip', ['diascalculo', 'inicioCalculo', 'isr'], 'ccodtip=?', [SUBSTR($account, 6, 2)]);
            if (empty($ahomtip)) {
                $showmensaje = true;
                throw new Exception("No se encontraron datos del tipo de cuenta");
            }

            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

        if (!$status) {
            $opResult = array(
                "status" => '0',
                "message" => $mensaje
            );
            echo json_encode($opResult);
            return;
        }
        $daysc = $ahomtip[0]['diascalculo'];
        $inicioCalculo = $ahomtip[0]['inicioCalculo'];
        $plazo = (!is_numeric($plazo)) ? 0 : $plazo;

        /**
         * PRIMER BLOQUE, CALCULO DE INTERESES
         */
        if ($cond == 1) {
            if (validarcampo([$monto, $intere], "") != "1") {
                echo json_encode(['status' => '0', 'message' => "Ingresar Monto y tasa de Interes"]);
                return;
            }

            if (validar_limites(1, 10000000, $monto) != "1") {
                echo json_encode(['status' => '0', 'message' => "Ingresar Monto correcto"]);
                return;
            }
            if (validar_limites(1, 100, $intere) != "1") {
                echo json_encode(['status' => '0', 'message' => "Ingresar tasa correcta"]);
                return;
            }
        }

        $monto = (!is_numeric($monto)) ? 0 : $monto;
        $intere = (!is_numeric($intere)) ? 0 : $intere;

        $plazo = ($plazo > 180 && $plazo < 190) ? 180 : $plazo;

        $interes = $monto * ($intere / 100 / $daysc);
        $interes = round($interes * $plazo, 2);
        $ipf = round($interes * ($ahomtip[0]['isr'] / 100), 2);
        $total = round($interes - $ipf, 2);

        /**
         * SEGUNDO BLOQUE, CALCULO DE FECHA DE PLAZO
         */
        if ($cond == 2) {
            if (validarcampo([$plazo], "") != "1") {
                $plazo = 0;
            }
            if (validar_limites(1, 10000, $plazo) != 1) {
                echo json_encode(['status' => '0', 'message' => "Ingresar un plazo correcto"]);
                return;
            }
        }

        $plazo -= $inicioCalculo;
        $nuevafecha = ($daysc == 360) ? sumarDiasBase30($fecapr, $plazo) : agregarDias($fecapr, $plazo);
        $date = new DateTime($nuevafecha);
        $result = ($cond == 2) ? $date->format('Y-m-d') : $fecven;

        /**
         * TERCER BLOQUE, CALCULO DE DIAS
         */
        $diasdif = ($daysc == 360) ? diferenciaEnDias($fecapr, $fecven) : dias_dif($fecapr, $fecven);
        $diferenciaDias = ($cond == 3) ? ($diasdif + $inicioCalculo) : $_POST["plazo"];

        /**
         * RESPUESTA
         */
        echo json_encode([
            "status" => '1',
            "montos" => [$interes, $ipf, $total],
            "fecha" => [$result],
            "plazo" => [$diferenciaDias]
        ]);
        break;
    case 'printcrt':
        list($idcrt) = $_POST["archivo"];

        // $showmensaje = false;
        // try {
        //     $database->openConnection();
        //     $ahomtip = $database->selectColumns('ahomtip', ['diascalculo', 'inicioCalculo', 'isr'], 'ccodtip=?', [SUBSTR($account, 6, 2)]);
        //     if (empty($ahomtip)) {
        //         $showmensaje = true;
        //         throw new Exception("No se encontraron datos del tipo de cuenta");
        //     }

        //     $status = true;
        // } catch (Exception $e) {
        //     if (!$showmensaje) {
        //         $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
        //     }
        //     $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        //     $status = false;
        // } finally {
        //     $database->closeConnection();
        // }

        // if (!$status) {
        //     $opResult = array(
        //         "status" => '0',
        //         "message" => $mensaje
        //     );
        //     echo json_encode($opResult);
        //     return;
        // }

        //+++++++++++++++++

        $idusuario = $_SESSION['id'];
        $hoy = date("Y-m-d");

        $query = "SELECT crt.*,tip.diascalculo,tip.ccodofi ,cta.ccodcli codigoCliente,tip.isr, usu.id_agencia
                    FROM `ahomcrt` crt 
                    INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                    INNER JOIN ahomtip tip on tip.ccodtip=substr(crt.codaho,7,2) 
                    INNER JOIN tb_usuario usu ON usu.id_usu = crt.codusu 
                    WHERE `id_crt`='$idcrt'";

        $datoscrt = mysqli_query($conexion, $query);
        $bandera = "Codigo de certificado no existe";
        while ($row = mysqli_fetch_array($datoscrt, MYSQLI_ASSOC)) {
            $codcrt = ($row["ccodcrt"]);
            $idcli = ($row["codigoCliente"]);
            $dayscalculo = ($row["diascalculo"]);
            $nit = ($row["num_nit"]);
            $codaho = ($row["codaho"]);
            $montoapr = ($row["montoapr"]);
            $plazo = ($row["plazo"]);
            $interes = ($row["interes"]);
            $fecapr = ($row["fec_apertura"]);
            $fec_ven = ($row["fec_ven"]);
            $ccodofi = ($row["ccodofi"]);
            $norecibo = ($row["recibo"]);
            $calint = ($row["calint"]);
            $isr = ($row["isr"]);
            $idagencia = ($row['id_agencia']);

            $bandera = "";
        }

        if ($bandera != "") {
            echo json_encode([$bandera, '0']);
            return;
        }

        $data = mysqli_query($conexion, "SELECT `short_name`,`no_identifica`,`Direccion`,`tel_no1`,`control_interno`,
        IFNULL((SELECT nombre FROM tb_municipios WHERE id=tb_cliente.id_muni_extiende),' ') municipio
        FROM `tb_cliente` WHERE `estado`=1 AND `idcod_cliente`='$idcli'");
        $bandera = "No existe el cliente relacionado a la cuenta de ahorro, revise si el cliente esta activo";
        while ($dat = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
            $nombre = ($dat["short_name"]);
            $dpi = ($dat["no_identifica"]);
            $dire = ($dat["Direccion"]);
            $tel = ($dat["tel_no1"]);
            $controlinterno = ($dat["control_interno"]);
            $extiende = ($dat["municipio"]);



            $bandera = "";
        }

        if ($bandera != "") {
            echo json_encode([$bandera, '0']);
            return;
        }

        $intcal = $montoapr * ($interes / 100 / $dayscalculo);
        $intcal = $intcal * $plazo;
        $ipf = $intcal * ($isr / 100);
        $ipf = round($ipf, 2);
        $total = $intcal - $ipf;

        //------------------  trae beneficiarios
        $confirma = 0;
        $array[] = [];
        $databen = mysqli_query($conexion, "SELECT * FROM `ahomben` WHERE `codaho`=$codaho");
        while ($fila = mysqli_fetch_array($databen, MYSQLI_ASSOC)) {
            $array[] = $fila;
            $confirma = 1;
        }
        $format_monto = new NumeroALetras();
        $montoletra = $format_monto->toMoney($montoapr, 2, 'QUETZALES', 'CENTAVOS');

        //convertir los codigos de parentesco a la descripcion
        $i = 1;
        while ($i < count($array)) {
            $array[$i]['codparent'] = \Micro\Generic\Utf8::decode(parenteco($array[$i]['codparent']));
            $i++;
        }
        //---------------------------
        $fechaletra = fechletras($hoy);
        $fecfinl = fechletras($fec_ven);
        $fecha_apertura_letra = fechletras($fecapr);

        // datos bd general encabezado o membrete pe
        $datamembrete = mysqli_query($conexion, "SELECT ofi.nom_agencia, cop.nomb_comple, cop.muni_lug, cop.emai, cop.tel_1, cop.tel_2, cop.nit, cop.log_img FROM $db_name_general.info_coperativa cop INNER JOIN tb_agencia ofi ON ofi.id_institucion = cop.id_cop WHERE ofi.id_agencia = $idagencia");
        $misdatosmembrete = [];
        while ($row = mysqli_fetch_array($datamembrete, MYSQLI_ASSOC)) {
            $misdatosmembrete[] = $row;
        }
        if (!empty($misdatosmembrete)) {
            // Accede a los datos del primer registro, por ejemplo
            $nom_agencia = $misdatosmembrete[0]['nom_agencia'];
            $nomb_comple = $misdatosmembrete[0]['nomb_comple'];
            $muni_lug = $misdatosmembrete[0]['muni_lug'];
            $emai = $misdatosmembrete[0]['emai'];
            $tel_1 = $misdatosmembrete[0]['tel_1'];
            $tel_2 = $misdatosmembrete[0]['tel_2'];
            $nit = $misdatosmembrete[0]['nit'];
            $log_img = $misdatosmembrete[0]['log_img'];
        }
        // datos bd generales para certificado de ahorro impresion de certificado
        $datosResult = array(
            "codcrt" => $codcrt,
            "nombre" => $nombre,
            "codaho" => $codaho,
            "dire" => $dire,
            "dpi" => $dpi,
            "tel" => $tel,
            "montoletra" => $montoletra,
            "montoapr" => $montoapr,
            "plazo" => $plazo,
            "fecapr" => $fecapr,
            "fec_ven" => $fec_ven,
            "interes" => $interes,
            "intcal" => $intcal,
            "ipf" => $ipf,
            "total" => $total,
            "hoy" => $hoy,
            "controlinterno" => $controlinterno,
            "ccodofi" => $ccodofi,
            "norecibo" => $norecibo,
            "nombre_usuario" => $_SESSION['nombre'],
            "apellido_usuario" => $_SESSION['apellido'],
            "extiende" => $extiende,
            "fechaletra" => $fechaletra,
            "fecfinl" => $fecfinl,
            "id_agencia" => $_SESSION['id_agencia'],
            "calint" => $calint,
            "nom_agencia" => $nom_agencia,
            "nomb_comple" => $nomb_comple,
            "muni_lug" => $muni_lug,
            "emai" => $emai,
            "tel_1" => $tel_1,
            "tel_2" => $tel_2,
            "nit" => $nit,
            "log_img" => $log_img,
            "fecha_apertura_letra" => $fecha_apertura_letra
        );

        echo json_encode([
            'Datos cargados correctamente, imprimiendo certificado',
            '1',
            'datosCertificado' =>
            [[$codcrt, $nombre, $codaho, $dire, $dpi, $tel, $montoletra, $montoapr, $plazo, $fecapr, $fec_ven, $interes, $intcal, $ipf, $total, $hoy, $controlinterno, $ccodofi, $norecibo, ($_SESSION['nombre']), ($_SESSION['apellido']), $extiende, $fechaletra, $fecfinl, $_SESSION['id_agencia'], $calint], $array, $confirma, $idcli, $datosResult]
        ]);
        mysqli_close($conexion);
        break;

    case 'process':
        if (!isset($_SESSION['id'])) {
            echo json_encode(['Session expirada, inicie sesion nuevamente', 0]);
            return;
        }
        $inputs = $_POST["inputs"];
        $selects = $_POST["selects"];
        $radios = $_POST["radios"];

        $fecha_inicio = $inputs[0];
        $fecha_final = $inputs[1];
        $tipcuenta = $selects[0];
        $r_cuenta = $radios[0];

        if (!validateDate($fecha_inicio, 'Y-m-d') || !validateDate($fecha_final, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($fecha_inicio > $fecha_final) {
            echo json_encode(['Rango de fechas Inválido', 0]);
            return;
        }
        if ($r_cuenta == "any" && $tipcuenta == '0') {
            echo json_encode(['Seleccione un tipo de cuenta válido', 0]);
            return;
        }

        $filtrocuenta = ($r_cuenta == "any") ? " AND SUBSTR(cta.ccodaho,7,2)='$tipcuenta'" : "";

        $query = "SELECT cta.ccodaho,cta.ccodcli,cli.short_name,cta.nlibreta,cta.tasa,IFNULL(id_mov,'X') idmov,
                        mov.dfecope,mov.ctipope,mov.cnumdoc,IFNULL(mov.monto,0) monto,mov.correlativo,
                        IFNULL((SELECT MIN(dfecope) FROM ahommov WHERE cestado!=2 AND ccodaho=cta.ccodaho AND dfecope<=?),'X') AS fecmin,
                        saldo_ahorro(cta.ccodaho, IFNULL(mov.dfecope, ?),IFNULL(mov.correlativo, (SELECT MAX(correlativo) 
                                 FROM ahommov WHERE ccodaho = cta.ccodaho AND dfecope <= ?))) AS saldo,tip.mincalc,tip.isr,diascalculo
                    FROM ahomcta cta 
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                    INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                    LEFT JOIN 
                        (
                            SELECT * FROM ahommov WHERE dfecope BETWEEN ? AND ? AND cestado != 2
                        ) mov ON mov.ccodaho = cta.ccodaho
                    WHERE cta.estado = 'A' " . $filtrocuenta . "
                    ORDER BY cta.ccodaho, mov.dfecope, mov.correlativo;";

        //INIT TRY
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->getAllResults($query, [$fecha_final, $fecha_final, $fecha_final, $fecha_inicio, $fecha_final]);

            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas");
            }

            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if ($status == 0) {
            $opResult = array($mensaje, 0);
            echo json_encode($opResult);
            return;
        }

        //INICIO PROCESO 
        $data = array();
        $auxarray = array();

        end($result);
        $lastKey = key($result);
        reset($result);

        $setCorte = false;
        $auxcuenta = "X";
        $auxfecha = agregarDias($fecha_inicio, -1);
        foreach ($result as $key => $fila) {
            $cuenta = $fila["ccodaho"];
            $tasa = $fila["tasa"];
            $codcli = $fila["ccodcli"];
            $idmov = $fila["idmov"];
            $fecha = ($idmov == "X") ? $fecha_final : $fila["dfecope"];
            $tipope = ($idmov == "X") ? "D" : $fila["ctipope"];
            $monto = $fila["monto"];
            $fechamin = $fila["fecmin"];
            $mincalc = $fila["mincalc"];
            $diascalculo = $fila["diascalculo"] ?? 365;
            $porcentajeIsr = round(($fila["isr"] / 100), 2);
            $saldoactual = $fila["saldo"];
            $saldoanterior = ($tipope == "R") ? ($saldoactual + $monto) : ($saldoactual - $monto);

            $auxfecha = ($fechamin == "X") ? $fecha_final : (($fechamin > $auxfecha) ? $fechamin : $auxfecha);

            $diasdif = dias_dif($auxfecha, $fecha);
            // $fechaant = $fecope;
            $interes = round($saldoanterior * ($tasa / 100) / $diascalculo * $diasdif, 2);
            $interes = ($saldoanterior >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

            $result[$key]["cnumdoc"] = ($idmov == "X") ? "corte" : $fila["cnumdoc"];
            $result[$key]["ctipope"] = $tipope;
            $result[$key]["dfecope"] = $fecha;
            $result[$key]["saldoant"] = $saldoanterior;
            $result[$key]["dias"] = $diasdif;
            $result[$key]["interescal"] = $interes;
            $result[$key]["isr"] = round($interes * $porcentajeIsr, 2);

            array_push($data, $result[$key]);

            $auxfecha = $fecha;
            if ($key === $lastKey) {
                $setCorte = ($fecha != $fecha_final) ? true : false;
            } else {
                if ($result[$key + 1]['ccodaho'] != $cuenta) {
                    $auxfecha = agregarDias($fecha_inicio, -1);
                    if ($fecha != $fecha_final) {
                        $setCorte = true;
                    }
                }
            }

            //EL CORTE DE CADA CUENTA AL FINAL DEL MES
            if ($setCorte) {
                $diasdif = dias_dif($fecha, $fecha_final);
                $interes = round($saldoactual * ($tasa / 100) / $diascalculo * $diasdif, 2);
                $interes = ($saldoactual >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

                $auxarray["ccodaho"] = $cuenta;
                $auxarray["ccodcli"] = $codcli;
                $auxarray["short_name"] = $fila["short_name"];
                $auxarray["ctipope"] = "D";
                $auxarray["tasa"] = $tasa;
                $auxarray["fecmin"] = $fechamin;
                $auxarray["dfecope"] = $fecha_final;
                $auxarray["monto"] = 0;
                $auxarray["cnumdoc"] = 'corte';
                $auxarray["mincalc"] = $mincalc;
                $auxarray["saldo"] = $saldoactual;
                $auxarray["saldoant"] = $saldoactual;
                $auxarray["dias"] = $diasdif;
                $auxarray["interescal"] = round($interes, 2);
                $auxarray["isr"] = round(($interes * $porcentajeIsr), 2);

                array_push($data, $auxarray);
                $setCorte = false;
            }
        }

        $tipocuenta = ($r_cuenta == "any") ? $selects[0] : "Todo";
        $rango = "" . date("d-m-Y", strtotime($fecha_inicio)) . "_" . date("d-m-Y", strtotime($fecha_final));
        $totalinteres = array_sum(array_column($data, "interescal"));
        $totalimpuesto = array_sum(array_column($data, "isr"));
        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();
            $datos = array(
                'tipo' => $tipocuenta,
                'rango' => $rango,
                'partida' => 0,
                'acreditado' => 0,
                'int_total' => $totalinteres,
                'isr_total' => $totalimpuesto,
                'fecmod' => $hoy2,
                'codusu' => $idusuario,
                'fechacorte' => $fecha_final,
            );
            $idaprintere = $database->insert('ahointeredetalle', $datos);

            foreach ($data as $fila) {
                if ($fila["interescal"] > 0) {
                    $datos = array(
                        'codaho' => $fila["ccodaho"],
                        'codcli' => $fila["ccodcli"],
                        'nomcli' => ($fila["short_name"]),
                        'tipope' => $fila["ctipope"],
                        'fecope' => $fila["dfecope"],
                        'numdoc' => $fila["cnumdoc"],
                        'tipdoc' => "E",
                        'monto' => $fila["monto"],
                        'saldo' => $fila["saldo"],
                        'saldoant' => $fila["saldoant"],
                        'dias' => $fila["dias"],
                        'tasa' => $fila["tasa"],
                        'intcal' => $fila["interescal"],
                        'isrcal' => $fila["isr"],
                        'idcalc' => $idaprintere,
                    );
                    $database->insert('ahointere', $datos);
                }
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
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

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        return;
        break;
    case 'procesCalculoIndi':
        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken, $fechaInicio, $fechaFin) = $_POST["inputs"];
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
        if (!validateDate($fechaInicio, 'Y-m-d') || !validateDate($fechaFin, 'Y-m-d')) {
            echo json_encode(['Fecha inválida, ingrese una fecha correcta', 0]);
            return;
        }
        if ($fechaInicio > $fechaFin) {
            echo json_encode(['Rango de fechas Inválido', 0]);
            return;
        }
        $codCuenta = $secureID->decrypt($encryptedID);

        $query = "SELECT cta.ccodaho,cli.short_name,cta.nlibreta,cta.tasa,
                    IFNULL(id_mov,'X') idmov,mov.dfecope,mov.ctipope,mov.cnumdoc,
                    IFNULL(mov.monto,0) monto,mov.correlativo,
                    IFNULL((SELECT MIN(dfecope) FROM ahommov WHERE cestado=1 AND ccodaho=cta.ccodaho AND dfecope<=?),'X') AS fecmin,
                    saldo_ahorro(cta.ccodaho, IFNULL(mov.dfecope, ?),IFNULL(mov.correlativo, (SELECT MAX(correlativo) 
                                FROM ahommov WHERE cestado=1 AND ccodaho = cta.ccodaho AND dfecope <= ?))) AS saldo,
                    tip.mincalc,tip.isr,tip.diascalculo
                    FROM ahomcta cta 
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente = cta.ccodcli
                    INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                    LEFT JOIN 
                        (
                            SELECT * FROM ahommov WHERE dfecope BETWEEN ? AND ? AND cestado =1
                        ) mov ON mov.ccodaho = cta.ccodaho
                    WHERE cta.estado = 'A' AND cta.ccodaho=?
                    ORDER BY cta.ccodaho, mov.dfecope, mov.correlativo;";

        //INIT TRY
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->getAllResults($query, [$fechaFin, $fechaFin, $fechaFin, $fechaInicio, $fechaFin, $codCuenta]);

            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta");
            }
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if (!$status) {
            $opResult = array($mensaje, 0);
            echo json_encode($opResult);
            return;
        }

        //INICIO PROCESO 
        $data = array();
        $auxarray = array();

        end($result);
        $lastKey = key($result);
        reset($result);

        $setCorte = false;
        $auxcuenta = "X";
        $auxfecha = agregarDias($fechaInicio, -1);
        foreach ($result as $key => $fila) {
            $cuenta = $fila["ccodaho"];
            $tasa = $fila["tasa"];
            $idmov = $fila["idmov"];
            $fecha = ($idmov == "X") ? $fechaFin : $fila["dfecope"];
            $tipope = ($idmov == "X") ? "D" : $fila["ctipope"];
            $monto = $fila["monto"];
            $fechamin = $fila["fecmin"];
            $mincalc = $fila["mincalc"];
            $diascalculo = $fila["diascalculo"] ?? 365;
            $porcentajeIsr = round(($fila["isr"] / 100), 2);
            $saldoactual = $fila["saldo"];
            $saldoanterior = ($tipope == "R") ? ($saldoactual + $monto) : ($saldoactual - $monto);

            $auxfecha = ($fechamin == "X") ? $fechaFin : (($fechamin > $auxfecha) ? $fechamin : $auxfecha);

            $diasdif = dias_dif($auxfecha, $fecha);
            // $fechaant = $fecope;
            $interes = round($saldoanterior * ($tasa / 100) / $diascalculo * $diasdif, 2);
            $interes = ($saldoanterior >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

            $result[$key]["cnumdoc"] = ($idmov == "X") ? "corte" : $fila["cnumdoc"];
            $result[$key]["ctipope"] = $tipope;
            $result[$key]["dfecope"] = setdatefrench($fecha);
            $result[$key]["saldoant"] = round($saldoanterior, 2);
            $result[$key]["dias"] = $diasdif;
            $result[$key]["interescal"] = $interes;

            array_push($data, $result[$key]);

            $auxfecha = $fecha;
            if ($key === $lastKey) {
                $setCorte = ($fecha != $fechaFin) ? true : false;
            } else {
                if ($result[$key + 1]['ccodaho'] != $cuenta) {
                    $auxfecha = agregarDias($fechaInicio, -1);
                    if ($fecha != $fechaFin) {
                        $setCorte = true;
                    }
                }
            }

            //EL CORTE DE CADA CUENTA AL FINAL DEL MES
            if ($setCorte) {
                $diasdif = dias_dif($fecha, $fechaFin);
                $interes = round($saldoactual * ($tasa / 100) / $diascalculo * $diasdif, 2);
                $interes = ($saldoactual >= $mincalc) ? $interes : 0; //si el saldo es menor al minimo de calculo no se calcula interes

                $auxarray["ccodaho"] = $cuenta;
                $auxarray["short_name"] = $fila["short_name"];
                $auxarray["ctipope"] = "D";
                $auxarray["tasa"] = $tasa;
                $auxarray["fecmin"] = $fechamin;
                $auxarray["dfecope"] = setdatefrench($fechaFin);
                $auxarray["monto"] = 0;
                $auxarray["cnumdoc"] = 'corte';
                $auxarray["mincalc"] = $mincalc;
                $auxarray["saldo"] = round($saldoactual, 2);
                $auxarray["saldoant"] = round($saldoactual, 2);
                $auxarray["dias"] = $diasdif;
                $auxarray["interescal"] = round($interes, 2);

                array_push($data, $auxarray);
                $setCorte = false;
            }
        }

        $totalinteres = array_sum(array_column($data, "interescal"));
        $totalinteres = round($totalinteres, 2);
        $totalimpuesto = $totalinteres * $porcentajeIsr;
        $totalimpuesto = round($totalimpuesto, 2);

        $opResult = array("Generacion Completa", 1, $data, $totalinteres, $totalimpuesto, $fechaFin);
        echo json_encode($opResult);
        break;
    case 'delete_calculo_interes':
        if (!isset($_SESSION['id'])) {
            echo json_encode(['Session expirada, inicie sesion nuevamente', 0]);
            return;
        }
        $ideliminar = $_POST["ideliminar"];
        if (!is_numeric($ideliminar)) {
            echo json_encode(['Parámetro no es numérico', 0]);
            return;
        }
        //INIT TRY
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns('ahointeredetalle', ['partida', 'acreditado'], 'id=?', [$ideliminar]);
            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("Cálculo no encontrado");
            }
            $partida = $result[0]['partida'];
            $acreditado = $result[0]['acreditado'];
            if ($partida == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue provisionado, no se puede eliminar!");
            }
            if ($acreditado == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue acreditado, no se puede eliminar!");
            }

            $database->beginTransaction();
            $database->delete("ahointere", "idcalc=?", [$ideliminar]);
            $database->delete("ahointeredetalle", "id=?", [$ideliminar]);

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        return;
        break;
    case 'acredita':
        /**
         * obtiene([`fechaInicio`],[`tipcuenta`],[`r_cuenta`],`acreditar_intereses`,`0`,
         * [' . $idcal . ',`' . $fechacorte . '`,`' . $agencia . '`,' . $codusu . ',`' . $rango . '`
         */
        $archivo = $_POST["archivo"];
        $id = $archivo[0];

        $showmensaje = false;
        try {
            $database->openConnection();
            $detalle = $database->selectColumns('ahointeredetalle', ['partida', 'acreditado', 'fechacorte'], 'id=?', [$id]);
            if (empty($detalle)) {
                $showmensaje = true;
                throw new Exception("Cálculo no encontrado");
            }
            if ($detalle[0]['partida'] == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue provisionado, no se puede realizar una acreditacion!");
            }
            if ($detalle[0]['acreditado'] == 1) {
                $showmensaje = true;
                throw new Exception("El cálculo ya fue acreditado, no se puede volver a acreditar!");
            }

            //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
            $cierre_mes = comprobar_cierrePDO($idusuario, $detalle[0]['fechacorte'], $database);
            if ($cierre_mes[0] == 0) {
                $showmensaje = true;
                throw new Exception($cierre_mes[1]);
            }

            /**
             * CUENTAS CONTABLES PARA LOS TIPOS DE CUENTAS INVOLUCRADOS
             */
            $cuentasContables = $database->getAllResults("SELECT id_descript_intere,id_cuenta1,id_cuenta2,tip.ccodtip,tip.nombre 
                                FROM ahomparaintere api 
                                    INNER JOIN ahomtip tip ON tip.id_tipo=api.id_tipo_cuenta
                                WHERE tip.ccodtip IN (SELECT SUBSTR(codaho,7,2) FROM ahointere WHERE idcalc=? GROUP BY SUBSTR(codaho,7,2));", [$id]);

            if (empty($cuentasContables)) {
                $showmensaje = true;
                throw new Exception("No se encontraron cuentas contables parametrizadas.");
            }
            /**
             * TIPOS DE CUENTAS INVOLUCRADOS
             */
            $tiposCuentas = $database->getAllResults("SELECT SUBSTR(codaho,7,2) ccodtip FROM ahointere WHERE idcalc=? 
                                                            GROUP BY SUBSTR(codaho,7,2);", [$id]);

            if (empty($tiposCuentas)) {
                $showmensaje = true;
                throw new Exception("No se encontraron los tipos de cuentas involucrados.");
            }

            /**
             * CONSULTA DE MOVIMIENTOS A ACREDITAR
             */
            $movimientos = $database->getAllResults("SELECT apint.codaho,SUM(apint.intcal) AS totalint, SUM(apint.isrcal) AS totalisr, cta.nlibreta, IFNULL(cta.ctainteres,'') ctainteres,
                                    IFNULL((SELECT MAX(numlinea) FROM ahommov WHERE ccodaho = cta.ccodaho AND nlibreta=cta.nlibreta AND cestado=1),0) numlinea,
                                    IFNULL((SELECT MAX(correlativo) FROM ahommov WHERE ccodaho = cta.ccodaho AND cestado=1),0) correlativo
                                FROM ahointere AS apint
                                INNER JOIN ahomcta AS cta ON cta.ccodaho=apint.codaho 
                                WHERE apint.idcalc=? 
                                GROUP BY apint.codaho", [$id]);

            if (empty($movimientos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron movimientos a acreditar.");
            }
            $database->beginTransaction();

            $database->update('ahointeredetalle', ['acreditado' => 1], 'id=?', [$id]);
            foreach ($movimientos as $mov) {
                $cuenta = $mov['codaho'];
                $totalint = $mov['totalint'];
                $totalisr = $mov['totalisr'];
                $nlibreta = $mov['nlibreta'];
                $numlinea = $mov['numlinea'];
                $correlativo = $mov['correlativo'];

                if ($mov['ctainteres'] != "") {
                    $cuentaSecu = $database->selectColumns('ahomcta', ['ccodaho'], 'ccodaho=?', [$mov['ctainteres']]);
                    if (empty($cuentaSecu)) {
                        $showmensaje = true;
                        throw new Exception("No se encontró la cuenta secundaria configurada para la cuenta " . $cuenta);
                    }
                    $cuenta = $cuentaSecu[0]['ccodaho'];
                }

                if ($totalint > 0) {
                    $ahommov = array(
                        'ccodaho' => $cuenta,
                        'dfecope' => $detalle[0]['fechacorte'],
                        'ctipope' => 'D',
                        'cnumdoc' => 'INT',
                        'ctipdoc' => 'IN',
                        'crazon' => 'INTERES',
                        'nlibreta' => $nlibreta,
                        'monto' => $totalint,
                        'lineaprint' => 'N',
                        'numlinea' => $numlinea + 1,
                        'correlativo' => $correlativo + 1,
                        'dfecmod' => $hoy2,
                        'codusu' => $idusuario,
                        'cestado' => 1,
                        'auxi' => 'INTERE' . $id,
                        'created_at' => $hoy2,
                        'created_by' => $idusuario,
                    );
                    $database->insert('ahommov', $ahommov);

                    if ($totalisr > 0) {
                        $ahommov = array(
                            'ccodaho' => $cuenta,
                            'dfecope' => $detalle[0]['fechacorte'],
                            'ctipope' => 'R',
                            'cnumdoc' => 'ISR',
                            'ctipdoc' => 'IP',
                            'crazon' => 'INTERES',
                            'nlibreta' => $nlibreta,
                            'monto' => $totalisr,
                            'lineaprint' => 'N',
                            'numlinea' => $numlinea + 2,
                            'correlativo' => $correlativo + 2,
                            'dfecmod' => $hoy2,
                            'codusu' => $idusuario,
                            'cestado' => 1,
                            'auxi' => 'INTERE' . $id,
                            'created_at' => $hoy2,
                            'created_by' => $idusuario,
                        );
                        $database->insert('ahommov', $ahommov);
                    }
                }
            }

            /**
             * MOVIMIENTOS EN LA CONTABILIDAD 
             *
             */
            foreach ($tiposCuentas as $key => $tipoC) {
                $ccodtipPP = $tipoC['ccodtip'];

                /**
                 * CUENTA CONTABLE PARA ACREDITACION DE INTERESES
                 */
                $cuentasInteres = array_filter($cuentasContables, function ($item) use ($ccodtipPP) {
                    return $item['ccodtip'] === $ccodtipPP && $item['id_descript_intere'] === 1;
                });

                if (empty($cuentasInteres)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta contable para la acreditación de intereses del tipo de cuenta " . $tipoC['ccodtip']);
                }

                $keyInteres = array_keys($cuentasInteres)[0];
                // echo json_encode([$keyInteres, '0']);
                // return;
                /**
                 * CUENTA CONTABLE PARA RETENCION DE ISR
                 */
                $cuentasIsr = array_filter($cuentasContables, function ($item) use ($ccodtipPP) {
                    return $item['ccodtip'] === $ccodtipPP && $item['id_descript_intere'] === 2;
                });
                if (empty($cuentasIsr)) {
                    $showmensaje = true;
                    throw new Exception("No se encontró la cuenta contable para la retención de ISR del tipo de cuenta " . $tipoC['ccodtip']);
                }
                $keyIsr = array_keys($cuentasIsr)[0];

                /**
                 * INGRESO DE MOVIMIENTOS EN LA CONTABILIDAD PARTIDA DE INTERES
                 */
                // $camp_numcom = getnumcompdo($idusuario, $database);
                $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $detalle[0]['fechacorte']);

                $ctb_diario = array(
                    'numcom' => $camp_numcom,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => 'INT',
                    'glosa' => 'ACREDITACION DE INTERESES A CUENTAS DE ' . strtoupper($cuentasInteres[0]['nombre']),
                    'fecdoc' => $detalle[0]['fechacorte'],
                    'feccnt' => $detalle[0]['fechacorte'],
                    'cod_aux' => "AHO-$ccodtipPP",
                    'id_tb_usu' => $idusuario,
                    'id_agencia' => $idagencia,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                );
                $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                // Filtrar los elementos donde ccodaport tiene ccodtip en las posiciones 7 y 8
                $filtrados = array_filter($movimientos, function ($item) use ($ccodtipPP) {
                    return substr($item['codaho'], 6, 2) === $ccodtipPP;
                });

                // Sumar los valores de totalint de los elementos filtrados
                $totalInteres = array_reduce($filtrados, function ($carry, $item) {
                    return $carry + $item['totalint'];
                }, 0);


                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasInteres[$keyInteres]['id_cuenta1'],
                    'debe' => $totalInteres,
                    'haber' => 0,
                );
                $database->insert('ctb_mov', $ctb_mov);

                $ctb_mov = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentasInteres[$keyInteres]['id_cuenta2'],
                    'debe' => 0,
                    'haber' => $totalInteres,
                );
                $database->insert('ctb_mov', $ctb_mov);

                /**
                 * INGRESO DE MOVIMIENTOS EN LA CONTABILIDAD PARTIDA DE ISR
                 */
                // Sumar los valores de totalisr de los elementos filtrados
                $totalIsr = array_reduce($filtrados, function ($carry, $item) {
                    return $carry + $item['totalisr'];
                }, 0);

                if ($totalIsr > 0) {
                    // $camp_numcom = getnumcompdo($idusuario, $database);
                    $camp_numcom = Beneq::getNumcom($database, $idusuario, $idagencia, $detalle[0]['fechacorte']);

                    $ctb_diario = array(
                        'numcom' => $camp_numcom,
                        'id_ctb_tipopoliza' => 3,
                        'id_tb_moneda' => 1,
                        'numdoc' => 'ISR',
                        'glosa' => 'RETENCION DE ISR A CUENTAS DE ' . strtoupper($cuentasInteres[0]['nombre']),
                        'fecdoc' => $detalle[0]['fechacorte'],
                        'feccnt' => $detalle[0]['fechacorte'],
                        'cod_aux' => "AHO-$ccodtipPP",
                        'id_tb_usu' => $idusuario,
                        'id_agencia' => $idagencia,
                        'fecmod' => $hoy2,
                        'estado' => 1,
                        'editable' => 0,
                    );
                    $id_ctb_diario = $database->insert('ctb_diario', $ctb_diario);

                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentasIsr[$keyIsr]['id_cuenta1'],
                        'debe' => $totalIsr,
                        'haber' => 0,
                    );
                    $database->insert('ctb_mov', $ctb_mov);

                    $ctb_mov = array(
                        'id_ctb_diario' => $id_ctb_diario,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentasIsr[$keyIsr]['id_cuenta2'],
                        'debe' => 0,
                        'haber' => $totalIsr,
                    );
                    $database->insert('ctb_mov', $ctb_mov);
                }
            }

            $database->commit();
            // $database->rollback();
            $mensaje = "Proceso concluido correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;
    case 'partidaprov':
        $archivo = $_POST["archivo"];
        $hoy = date("Y-m-d H:i:s");
        $id = $archivo[0];
        $usu = $archivo[1];
        $fechacorte = $archivo[2];
        $rango = $archivo[3];

        $campo_glosa = "";

        //------validar si existen todas las parametrizaciones correctas para realizar la acreditacion
        $consulta4 = "SELECT SUBSTR(ai.codaho,7,2) AS grupo, tp.nombre 
                    FROM ahointere ai
                    INNER JOIN ahomcta ac ON ac.ccodaho=ai.codaho 
                    INNER JOIN ahomtip tp ON SUBSTR(ac.ccodaho,7,2)=tp.ccodtip
                    WHERE ai.idcalc=" . $id . " 
                    GROUP BY SUBSTR(ai.codaho,7,2)";
        $data4 = mysqli_query($conexion, $consulta4);

        while ($row = mysqli_fetch_array($data4, MYSQLI_ASSOC)) {
            $val_tipcuenta = $row["grupo"];
            $val_nombre = $row["nombre"];
            //obtener el datos para ingresar en el campo id_ctb_nomenclatura de la tabla ctb_mov
            list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("ahomparaintere", "id_descript_intere", (tipocuenta($val_tipcuenta, "ahomtip", "id_tipo", $conexion)), (3), $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 == "X") {
                echo json_encode(['NO PUEDE REALIZAR LA PROVISIÓN DEBIDO A QUE NO HA PARAMETRIZADO UNA CUENTA CONTABLE PARA EL TIPO DE CUENTA ' . $val_nombre . ' EN RELACIÓN AL INTERES', '0']);
                return;
            }
        }
        //------FIN
        //transaccion
        $conexion->autocommit(false);
        try {
            //validacion de acreditacion
            $data3 = mysqli_query($conexion, "SELECT `partida` FROM `ahointeredetalle` WHERE id='$id'");
            while ($row = mysqli_fetch_array($data3, MYSQLI_ASSOC)) {
                $partida = $row["partida"];
            }
            if ($partida == "1") {
                echo json_encode(['Este campo ya ha sido provisionado', '1']);
                return;
            }

            //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO
            $cierre = comprobar_cierre($idusuario, $fechacorte, $conexion);
            if ($cierre[0] == 0) {
                echo json_encode([$cierre[1], '0']);
                return;
            }

            $conexion->query("UPDATE `ahointeredetalle` SET partida=1 where id=" . $id);

            $consulta = "SELECT SUBSTR(ai.codaho,7,2) AS grupo,sum(ai.intcal) as totalint, sum(isrcal) as totalisr,ac.nlibreta,ac.numlinea,ac.correlativo, tp.nombre FROM ahointere ai
                INNER JOIN ahomcta ac ON ac.ccodaho=ai.codaho 
                INNER JOIN ahomtip tp ON SUBSTR(ac.ccodaho,7,2)=tp.ccodtip
                WHERE ai.idcalc=" . $id . " 
                GROUP BY SUBSTR(ai.codaho,7,2)";
            $data = mysqli_query($conexion, $consulta);
            //insercion en la tabla de dario
            while ($row = mysqli_fetch_array($data, MYSQLI_ASSOC)) {
                $nombre = encode_utf8($row["nombre"]);
                $interes = ($row["totalint"]);
                $isr = ($row["totalisr"]);
                $grupo = ($row["grupo"]);

                if ($interes > 0) {
                    //insertar en ctb_diario
                    //glosa de provision
                    $campo_glosa .= "PROVISION DE INTERESES DE CUENTAS DE ";
                    $campo_glosa .= strtoupper($nombre);

                    //validar si es con rango de fecha o no
                    if ($rango != "Todo") {
                        $campo_glosa .= " COMPRENDIDO DEL ";
                        $campo_glosa .= substr($rango, 0, 10);
                        $campo_glosa .= " AL ";
                        $campo_glosa .= substr($rango, 11, 20);
                    }
                    //INSERCIONES EN CTB_DIARIO - INTERES ACREDITADO
                    //llamar al metodo numcom
                    // $camp_numcom = getnumcom($usu, $conexion);
                    $camp_numcom = Beneq::getNumcomLegacy($usu, $conexion, $idagencia, $fechacorte);
                    //insertar glosa de acreditacion
                    $aux = "AHO-" . $grupo;
                    $conexion->query("INSERT INTO `ctb_diario`(`numcom`,`id_ctb_tipopoliza`,`id_tb_moneda`,`numdoc`,`glosa`,`fecdoc`,`feccnt`,`cod_aux`,`id_tb_usu`,`fecmod`,`estado`,`id_agencia`) VALUES ('$camp_numcom',2,1,'PROV', '$campo_glosa','$fechacorte', '$fechacorte','$aux','$usu','$hoy',1,$idagencia)");

                    //INSERCION EN CTB_MOV PARA EL INTERES PROVISIONADO
                    $id_ctb_diario = get_id_insertado($conexion); //obtener el ultimo id insertado
                    list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("ahomparaintere", "id_descript_intere", (tipocuenta($grupo, "ahomtip", "id_tipo", $conexion)), (3), $conexion);
                    $conexion->query("INSERT INTO `ctb_mov`(`id_ctb_diario`,`numcom`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES ($id_ctb_diario,'$camp_numcom',1,$idcuenta1, '$interes',0)");
                    $conexion->query("INSERT INTO `ctb_mov`(`id_ctb_diario`,`numcom`,`id_fuente_fondo`,`id_ctb_nomenclatura`,`debe`,`haber`) VALUES ($id_ctb_diario,'$camp_numcom',1,$idcuenta2, 0,'$interes')");

                    $campo_glosa = "";
                }
            }

            $conexion->commit();
            echo json_encode(['Datos ingresados correctamente', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        //fin transaccion
        mysqli_close($conexion);

        break;
    case 'liquidcrt':

        //['<?= $csrf->getTokenName()',`moncal`,`intcal`,`penaliza`,`norecibo`,`fecacredita`],[`accion`],[],`liquidcrt`,`0`,['<?= htmlspecialchars($secureID->encrypt($idCertificado))']]
        list($csrftoken, $intcal, $ipfcalc, $penaliza, $norecibo, $fechaliquidacion) = $_POST["inputs"];
        list($accion) = $_POST["selects"];
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

        $idCertificado = $secureID->decrypt($encryptedID);

        if ($norecibo == "") {
            $opResult = array("Ingrese un numero de recibo", 0);
            echo json_encode($opResult);
            return;
        }

        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++  DATOS DE LA CUENTA DE AHORROS ++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */

        $query = "SELECT short_name,nlibreta,ctainteres,ccodcrt,cta.ccodaho,cta.estado,crt.montoapr FROM ahomcrt crt 
                        INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                        INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                        INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                        WHERE id_crt=?;";
        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();
            $dataCertificado = $database->getAllResults($query, [$idCertificado]);
            if (empty($dataCertificado)) {
                $showmensaje = true;
                throw new Exception("Certificado no encontrado");
            }
            if ($dataCertificado[0]['estado'] != 'A') {
                $showmensaje = true;
                throw new Exception("La cuenta de ahorro no se encuentra activa");
            }
            $short_name = strtoupper($dataCertificado[0]['short_name']);
            $nlibreta = $dataCertificado[0]['nlibreta'];
            $cueninteres = $dataCertificado[0]['ctainteres'];
            $cuentaDestino = $dataCertificado[0]['ccodaho'];

            if ($cueninteres != "" && $cueninteres != null) {
                $result = $database->selectColumns('ahomcta', ['ccodaho'], 'ccodaho=?', [$cueninteres]);
                if (empty($result)) {
                    $showmensaje = true;
                    throw new Exception("La cuenta secundaria configurada no existe");
                }
                $cuentaDestino = $cueninteres;
            }

            if ($accion != 3) {
                // $conexion->query("INSERT INTO `ahommov`(`ccodaho`,`dfecope`,`ctipope`,`cnumdoc`,`ctipdoc`,`crazon`,`nlibreta`,`nrochq`,`tipchq`,`numpartida`,`monto`,
                // `lineaprint`,`numlinea`,`correlativo`,`dfecmod`,`codusu`,`auxi`)
                // VALUES ('$codaho','$fechaliquidacion','D','$codcrt','IN','INTERES', $libreta,'','','',$interescal,'N',$num+1,$correl+1,'$hoy2','$codusu','INTCRT" . $codcrt . "')");
                $glosa1 = "ACREDITACION DE INTERESES DE AHORRO A PLAZO FIJO DE " . $short_name . " CON CERTIFICADO NO. " . $dataCertificado[0]['ccodcrt'];
                $datosint = array(
                    "ccodaho" => $cuentaDestino,
                    "dfecope" => $fechaliquidacion,
                    "ctipope" => "D",
                    "cnumdoc" => $dataCertificado[0]['ccodcrt'],
                    "ctipdoc" => "IN",
                    "crazon" => "INTERES",
                    "concepto" => $glosa1,
                    "nlibreta" => $nlibreta,
                    // "nrochq" => 0,
                    // "tipchq" => "",
                    // "dfeccomp" => NULL,
                    "monto" => $intcal,
                    "lineaprint" => "N",
                    "numlinea" => 1,
                    "correlativo" => 1,
                    "dfecmod" => $hoy2,
                    "codusu" => $idusuario,
                    "cestado" => 1,
                    "auxi" => "INTCRT" . $dataCertificado[0]['ccodcrt'],
                    "created_at" => $hoy2,
                    "created_by" => $idusuario,
                );
                // $conexion->query("INSERT INTO `ahommov`(`ccodaho`,`dfecope`,`ctipope`,`cnumdoc`,`ctipdoc`,`crazon`,`nlibreta`,`nrochq`,`tipchq`,`numpartida`,`monto`,
                // `lineaprint`,`numlinea`,`correlativo`,`dfecmod`,`codusu`,`auxi`)
                // VALUES ('$codaho','$fechaliquidacion','R','$codcrt','IP','IPF', $libreta,'','','',$ipfcalc,'N',$num+2,$correl+2,'$hoy2','$codusu','INTCRT" . $codcrt . "')");

                $glosa2 = "RETENCION DE ISR DE $short_name";
                $datosipf = array(
                    "ccodaho" => $cuentaDestino,
                    "dfecope" => $fechaliquidacion,
                    "ctipope" => "R",
                    "cnumdoc" => $dataCertificado[0]['ccodcrt'],
                    "ctipdoc" => "IP",
                    "crazon" => "IPF",
                    "concepto" => $glosa2,
                    "nlibreta" => $nlibreta,
                    // "nrochq" => 0,
                    // "tipchq" => "",
                    // "dfeccomp" => NULL,
                    "monto" => $ipfcalc,
                    "lineaprint" => "N",
                    "numlinea" => 1,
                    "correlativo" => 1,
                    "dfecmod" => $hoy2,
                    "codusu" => $idusuario,
                    "cestado" => 1,
                    "auxi" => "INTCRT" . $dataCertificado[0]['ccodcrt'],
                    "created_at" => $hoy2,
                    "created_by" => $idusuario,
                );

                $database->insert('ahommov', $datosint);
                if ($ipfcalc > 0) {
                    $database->insert('ahommov', $datosipf);
                }

                $database->executeQuery('CALL ahom_ordena_noLibreta(?, ?);', [$nlibreta, $cuentaDestino]);
                $database->executeQuery('CALL ahom_ordena_Transacciones(?);', [$cuentaDestino]);

                /**
                 * MOVIMIENTOS EN CONTABILIDAD
                 */

                $result = $database->getAllResults("SELECT ap.* FROM ahomparaintere ap INNER JOIN ahomtip tip ON tip.id_tipo=ap.id_tipo_cuenta 
                        WHERE ccodtip=SUBSTR(?,7,2) AND id_descript_intere IN (1,2)", [$cuentaDestino]);

                if (empty($result)) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron cuentas contables parametrizadas.");
                }
                $keyint = array_search(1, array_column($result, 'id_descript_intere'));
                $keyisr = array_search(2, array_column($result, 'id_descript_intere'));

                if ($keyint === false || $keyisr === false) {
                    $showmensaje = true;
                    throw new Exception("No se encontraron cuentas contables parametrizadas ()." . $keyisr);
                }

                $cuentaint1 = $result[$keyint]['id_cuenta1'];
                $cuentaint2 = $result[$keyint]['id_cuenta2'];
                $cuentaisr1 = $result[$keyisr]['id_cuenta1'];
                $cuentaisr2 = $result[$keyisr]['id_cuenta2'];

                //AFECTACION CONTABLE
                // $numpartida = getnumcompdo($idusuario, $database); //Obtener numero de partida
                $numpartida = Beneq::getNumcom($database, $idusuario, $idagencia, $fechaliquidacion);
                $datos = array(
                    'numcom' => $numpartida,
                    'id_ctb_tipopoliza' => 2,
                    'id_tb_moneda' => 1,
                    'numdoc' => $dataCertificado[0]['ccodcrt'],
                    'glosa' => $glosa1,
                    'fecdoc' => $fechaliquidacion,
                    'feccnt' => $fechaliquidacion,
                    'cod_aux' => $cuentaDestino,
                    'id_tb_usu' => $idusuario,
                    'fecmod' => $hoy2,
                    'estado' => 1,
                    'editable' => 0,
                    'id_agencia' => $idagencia
                );

                $id_ctb_diario = $database->insert('ctb_diario', $datos);

                //AFECTACION CONTABLE MOV 1 
                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaint1,
                    'debe' => $intcal,
                    'haber' => 0
                );
                $database->insert('ctb_mov', $datos);

                $datos = array(
                    'id_ctb_diario' => $id_ctb_diario,
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' => $cuentaint2,
                    'debe' => 0,
                    'haber' => $intcal
                );
                $database->insert('ctb_mov', $datos);

                if ($ipfcalc > 0) {
                    // $numpartida2 = getnumcompdo($idusuario, $database); //Obtener numero de partida
                    $numpartida2 = Beneq::getNumcom($database, $idusuario, $idagencia, $fechaliquidacion);
                    $ctb_diario = array(
                        'numcom' => $numpartida2,
                        'id_ctb_tipopoliza' => 2,
                        'id_tb_moneda' => 1,
                        'numdoc' => $dataCertificado[0]['ccodcrt'],
                        'glosa' => $glosa2,
                        'fecdoc' => $fechaliquidacion,
                        'feccnt' => $fechaliquidacion,
                        'cod_aux' => $cuentaDestino,
                        'id_tb_usu' => $idusuario,
                        'fecmod' => $hoy2,
                        'estado' => 1,
                        'editable' => 0,
                        'id_agencia' => $idagencia
                    );

                    $id_ctb_diario2 = $database->insert('ctb_diario', $ctb_diario);

                    $datos = array(
                        'id_ctb_diario' => $id_ctb_diario2,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentaisr1,
                        'debe' => $ipfcalc,
                        'haber' => 0
                    );
                    $database->insert('ctb_mov', $datos);

                    $datos = array(
                        'id_ctb_diario' => $id_ctb_diario2,
                        'id_fuente_fondo' => 1,
                        'id_ctb_nomenclatura' => $cuentaisr2,
                        'debe' => 0,
                        'haber' => $ipfcalc
                    );
                    $database->insert('ctb_mov', $datos);
                }
            }


            if ($accion == 1 || $accion == 3) {
                $ahomcrt = array(
                    'liquidado' => 'S',
                    'intcal' => round($intcal, 2),
                    'recibo_liquid' => $norecibo,
                    'fec_liq' => $fechaliquidacion
                );

                $database->update('ahomcrt', $ahomcrt, 'id_crt=?', [$idCertificado]);
            }

            $database->commit();
            $mensaje = "Registro grabado correctamente";
            $status = true;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }

        if ($status) {
            $format_monto = new NumeroALetras();
            $texto_monto = $format_monto->toMoney($dataCertificado[0]['montoapr'] + $intcal - $ipfcalc, 2, 'QUETZALES', 'CENTAVOS');
            $opResult = array($mensaje, 1, $dataCertificado[0]['ccodcrt'], $fechaliquidacion, $cuentaDestino, $short_name, $dataCertificado[0]['montoapr'], $intcal, $ipfcalc, $texto_monto, $norecibo);
        } else {
            $opResult = array($mensaje, 0);
        }

        echo json_encode($opResult);
        break;
    case 'liquidcrtonly':
        $archivo = $_POST["archivo"];
        $idcrt = $archivo[0];

        $hoy = date("Y-m-d");
        $hoy2 = date("Y-m-d H:i:s");

        $conexion->autocommit(false);
        try {
            $conexion->query("UPDATE `ahomcrt` SET liquidado='S',fec_liq='$hoy' where id_crt=" . $idcrt);
            $aux = mysqli_error($conexion);
            if ($aux) {
                echo json_encode(['ERROR4: ' . $aux, '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Datos ingresados correctamente', '1', $codcrt, $fechaliquidacion, $codaho, $nombrecli, $montoapr, $interescal, $ipfcalc, $texto_monto, $recibo]);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        //fin transaccion
        mysqli_close($conexion);
        break;
    case 'printliquidcrt':
        $archivo = $_POST["archivo"];
        $idcrt = $archivo[0];
        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
            ++++++++++++++++++++++++++  DATOS DE LA CUENTA DE AHORROS ++++++++++++++++++++++++
            ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
        try {
            $database->openConnection();

            $result = $database->getAllResults("SELECT cli.short_name,cli.no_identifica as cli_dpi,crt.*,tip.isr,usu.*,cta.tasa
                                    FROM ahomcrt crt 
                                    INNER JOIN ahomcta cta ON cta.ccodaho=crt.codaho
                                    INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2)
                                    INNER JOIN tb_usuario usu ON usu.id_usu=crt.codusu
                                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli WHERE id_crt=?", [$idcrt]);

            foreach ($result as $dat) {
                $codcrt = $dat['ccodcrt'];
                $fechaliquidacion = $dat['fec_liq'];
                $codaho = $dat['codaho'];
                $nombrecli = $dat['short_name'];
                $dpi = $dat['cli_dpi'];
                $montoapr = $dat['montoapr'];
                $interescal = $dat['intcal'];
                $ipfcalc = $interescal * ($dat['isr'] / 100);
                $recibo = $dat['recibo_liquid'];
                $fec_apertura = $dat['fec_apertura'];
                $fec_vencimiento = $dat['fec_ven'];
                $codcli = $dat['ccodcli'];
                $plazo = $dat['plazo'];
                $usuario = $dat['nombre'];
                $tasa = $dat['tasa'];
            }

            $mensaje = "Consulta procesada correctamente";
            $status = 1;
        } catch (Exception $e) {
            $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if ($status == 1) {
            $format_monto = new NumeroALetras();
            $liquido = round($montoapr + $interescal - $ipfcalc, 2);
            $texto_monto = $format_monto->toMoney($liquido, 2, 'QUETZALES', 'CENTAVOS');
            echo json_encode(['Datos ingresados correctamente', '1', $codcrt, $fechaliquidacion, $codaho, $nombrecli, $montoapr, $interescal, $ipfcalc, $texto_monto, $recibo, $dpi, $fec_apertura, $fec_vencimiento, $codcli, $plazo, $usuario, $tasa]);
        } else {
            echo json_encode([$mensaje, '0']);
        }
        break;
    case 'obtener_total_ben': {
            $id = $_POST["l_codaho2"];
            $consulta2 = mysqli_query($conexion, "SELECT * FROM `ahomben` WHERE `codaho`='$id'");
            //se cargan los datos de las beneficiarios a un array
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $benporcent = ($fila["porcentaje"]);
                $total = $total + $benporcent;
                $i++;
            }
            echo json_encode($total);
        }
        break;
    case 'lista_beneficiarios': {
            $id = $_POST['l_codaho'];
            $consulta2 = mysqli_query($conexion, "SELECT * FROM `ahomben` WHERE `codaho`='$id'");
            //se cargan los datos de las beneficiarios a un array
            $array_beneficiarios = array();
            $array_parenteco[] = [];
            $total = 0;
            $i = 0;
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $array_beneficiarios[] = array(
                    "0" => encode_utf8($fila["dpi"]),
                    "1" => encode_utf8($fila["nombre"]),
                    "2" => encode_utf8($fila["fecnac"]),
                    "3" => parenteco(encode_utf8($fila["codparent"])),
                    "4" => encode_utf8($fila["porcentaje"]),
                    "5" => '<button type="button" class="btn btn-warning me-1" title="Editar Beneficiario" onclick="editben(' . encode_utf8($fila["id_ben"]) . ',`' . encode_utf8($fila["nombre"]) . '`,`' . encode_utf8($fila["dpi"]) . '`,`' . encode_utf8($fila["direccion"]) . '`,' . encode_utf8($fila["codparent"]) . ',`' . encode_utf8($fila["fecnac"]) . '`,' . encode_utf8($fila["porcentaje"]) . ',`' . encode_utf8($fila["telefono"]) . '`)"><i class="fa-solid fa-pen"></i></button>
                        <button type="button" class="btn btn-danger" title="Eliminar Beneficiario" onclick="eliminar(' . encode_utf8($fila["id_ben"]) . ',`crud_ahorro`,`' . $id . '`,`delete_apr_ben`)"><i class="fa-solid fa-trash-can"></i>
                        </button>'
                );
                $i++;
            }
            $results = array(
                "sEcho" => 1, //info para datatables
                "iTotalRecords" => count($array_beneficiarios), //enviamos el total de registros al datatable
                "iTotalDisplayRecords" => count($array_beneficiarios), //enviamos el total de registros a visualizar
                "aaData" => $array_beneficiarios
            );
            mysqli_close($conexion);
            echo json_encode($results);
        }
        break;
    case 'delete_apr_ben': {
            $idaprben = $_POST["ideliminar"];
            $eliminar = "DELETE FROM ahomben WHERE id_ben =" . $idaprben;
            if (mysqli_query($conexion, $eliminar)) {
                echo json_encode(['Eliminacion correcta ', '1']);
            } else {
                echo json_encode(['Error al eliminar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case 'create_aho_ben': {
            $hoy = date("Y-m-d");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"];
            $archivos = $_POST["archivo"];
            $currentYear = date("Y");

            if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $inputs[0])) {
                echo json_encode([$inputs[0], '0']);
                return;
            }

            if (!preg_match("/^\d{13}$/", $inputs[1])) {
                echo json_encode(['EL DPI debe de contener exactamente 13 dígitos', '0']);
                return;
            }

            if (!preg_match("/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s.,#-]+$/", $inputs[2]) || strlen($inputs[2]) > 100) {
                echo json_encode(['El campo de dirección solo puede contener letras, números, espacios y los caracteres ., #- y no debe exceder 100 caracteres', '0']);
                return;
            }

            if (empty($selects[0]) || $selects[0] === "0") {
                echo json_encode(['Seleccione una opción válida', '0']);
                return;
            }

            if (!preg_match("/^[0-9]{1,13}$/", $inputs[3])) {
                echo json_encode(['Ingrese un numero de telefono valido < 13', '0']);
                return;
            }



            $consulta2 = mysqli_query($conexion, "SELECT * FROM `ahomben` WHERE `codaho`='$archivos[0]'");
            //se cargan los datos de las beneficiarios a un array
            $total_aux = 0;
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $benporcent = encode_utf8($fila["porcentaje"]);
                $total_aux = $total_aux + $benporcent;
            }

            if ($archivos[1] == "") {
                $validacion = validarcampo($inputs, "");
                if ($validacion == "1") {
                    //validando que el primer beneficiario tiene que tener el 100%
                    $total = $total_aux + $inputs[5];
                    if (($total_aux == 0) && ($total != 100)) {
                        echo json_encode(['Al ser el primer beneficiario tiene que digitar que sea el 100%', '0']);
                    } else {
                        if ($total > 100) {
                            echo json_encode(['El porcentaje ingresado del nuevo beneficiario sumados con los anteriores no puede ser mayor a 100', '0']);
                        } else {
                            if ($inputs[5] <= 0) {
                                echo json_encode(['Verifique que el porcentaje ingresado del nuevo beneficiario no puede ser menor o igual a 0', '0']);
                            } else {
                                $validparent = validarcampo($selects, "0");
                                if ($validparent == "1") {
                                    $conexion->autocommit(false);
                                    try {
                                        $conexion->query("INSERT INTO `ahomben`(`codaho`,`nombre`,`dpi`,`direccion`,`codparent`,`fecnac`,`porcentaje`,`telefono`) VALUES ('$archivos[0]','$inputs[0]','$inputs[1]','$inputs[2]','$selects[0]','$inputs[4]','$inputs[5]','$inputs[3]')");
                                        $conexion->commit();
                                        echo json_encode(['Correcto,  Beneficiario guardado ', '1']);
                                    } catch (Exception $e) {
                                        $conexion->rollback();
                                        echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                                    }
                                } else {
                                    echo json_encode(['Seleccione parentesco', '0']);
                                }
                            }
                        }
                    }
                } else {
                    echo json_encode([$validacion, '0']);
                }
            } else {
                echo json_encode(['Seleccione primeramente una cuenta de ahorro', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case 'update_aho_ben': {
            $hoy = date("Y-m-d");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos// 
            $archivos = $_POST["archivo"];

            if (!preg_match("/^[a-zA-ZáéíóúÁÉÍÓÚñÑ\s]+$/", $inputs[0])) {
                echo json_encode([$inputs[0], '0']);
                return;
            }

            if (!preg_match("/^\d{13}$/", $inputs[1])) {
                echo json_encode(['EL DPI debe de contener exactamente 13 dígitos', '0']);
                return;
            }

            if (!preg_match("/^[a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s.,#-]+$/", $inputs[2]) || strlen($inputs[2]) > 100) {
                echo json_encode(['El campo de dirección solo puede contener letras, números, espacios y los caracteres ., #- y no debe exceder 100 caracteres', '0']);
                return;
            }

            if (empty($selects[0]) || $selects[0] === "0") {
                echo json_encode(['Seleccione una opción válida', '0']);
                return;
            }

            if (!preg_match("/^[0-9]{1,13}$/", $inputs[3])) {
                echo json_encode(['Ingrese un numero de telefono valido < 13', '0']);
                return;
            }


            $consulta2 = mysqli_query($conexion, "SELECT * FROM `ahomben` WHERE `codaho`='$archivos[0]'");
            //se cargan los datos de las beneficiarios a un array
            $total_aux = 0;
            while ($fila = mysqli_fetch_array($consulta2, MYSQLI_ASSOC)) {
                $benporcent = encode_utf8($fila["porcentaje"]);
                $total_aux = $total_aux + $benporcent;
            }

            $validacion = validarcampo($inputs, "");
            if ($validacion == "1") {
                $total = $total_aux - $inputs[6] + $inputs[5];

                if ($total > 100) {
                    echo json_encode(['No se puede actualizar debido a que con el nuevo porcentaje supera el 100%, debe acomodar el o los porcentajes anteriores', '0']);
                } else if ($inputs[5] <= 0) {
                    echo json_encode(['El porcentaje nuevo no puede ser menor o igual a 0', '0']);
                } else {
                    $validparent = validarcampo($selects, "0");
                    if ($validparent == "1") {
                        $conexion->autocommit(false);
                        try {
                            $conexion->query("UPDATE `ahomben` SET `nombre` = '$inputs[0]',`dpi` = '$inputs[1]',`direccion` = '$inputs[2]',`codparent` = $selects[0],`fecnac` = '$inputs[4]',`porcentaje` = $inputs[5],`telefono` = '$inputs[3]' WHERE `id_ben` = $inputs[7]");
                            $conexion->commit();
                            echo json_encode(['Correcto,  Beneficiario actualizado', '1']);
                        } catch (Exception $e) {
                            $conexion->rollback();
                            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
                        }
                    } else {
                        echo json_encode(['Seleccione parentesco', '0']);
                    }
                }
            } else {
                echo json_encode([$validacion, '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "create_aho_cuentas_contables": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos// 
            $archivos = $_POST["archivo"];

            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de documento', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] == "") {
                echo json_encode(['Debe seleccionar una cuenta 1', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] == "") {
                echo json_encode(['Debe seleccionar una cuenta 2', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("ahomctb", "id_tipo_doc", $selects[0], $selects[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede agregar esta parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la insercion
            $conexion->autocommit(false);
            try {
                $conexion->query("INSERT INTO ahomctb (id_tipo_cuenta,id_tipo_doc,id_cuenta1,id_cuenta2,dfecmod,codusu)
                        VALUES ($selects[0],$selects[1],$inputs[0],$inputs[1],'$hoy',$archivos[0])");

                $conexion->commit();
                echo json_encode(['Datos ingresados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "update_aho_cuentas_contables": {

            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            //validar input cuenta 1
            if ($inputs[0] == "0") {
                echo json_encode(['Debe seleccionar una cuenta contable', '0']);
                return;
            }
            //se hara la actualizacion
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE ahomtip
                    SET id_cuenta_contable = $inputs[0] WHERE id_tipo=$inputs[1]");

                $conexion->commit();
                echo json_encode(['Datos actualizados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "update_aho_cuentas_contablesanterior": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"]; //selects datos// 
            $archivos = $_POST["archivo"];

            // echo json_encode([$inputs[2]."-".$inputs[3], '0']);
            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de documento', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] != "" && $inputs[2] == "") {
                echo json_encode(['Debe seleccionar una cuenta 1', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] != "" && $inputs[3] == "") {
                echo json_encode(['Debe seleccionar una cuenta 2', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            $id1 = get_ctb_nomenclatura2("ahomctb", "id_tipo_doc", $selects[0], $selects[1], $archivos[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede realizar esta actualizacion de parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la actualizacion
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE ahomctb
                    SET id_tipo_cuenta = $selects[0],id_tipo_doc=$selects[1],id_cuenta1=$inputs[0],id_cuenta2=$inputs[1],dfecmod='$hoy',codusu=$archivos[0] WHERE id=$archivos[1]");

                $conexion->commit();
                echo json_encode(['Datos actualizados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "delete_aho_cuentas_contables": {
            $id = $_POST["ideliminar"];
            $eliminar = "DELETE FROM ahomctb WHERE id =" . $id;
            if (mysqli_query($conexion, $eliminar)) {
                echo json_encode(['Eliminacion correcta ', '1']);
            } else {
                echo json_encode(['Error al eliminar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "create_aho_cuentas_intereses": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"];
            $archivos = $_POST["archivo"];

            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de operacion', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el debe', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el haber', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            list($id1, $idcuenta1, $idcuenta2) = get_ctb_nomenclatura("ahomparaintere", "id_descript_intere", $selects[0], $selects[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede agregar esta parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la insercion
            $conexion->autocommit(false);
            try {
                $conexion->query("INSERT INTO ahomparaintere (id_tipo_cuenta,id_descript_intere,id_cuenta1,id_cuenta2,dfecmod,id_usuario)
                        VALUES ($selects[0],$selects[1],$inputs[0],$inputs[1],'$hoy',$archivos[0])");

                $conexion->commit();
                echo json_encode(['Datos ingresados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "update_aho_cuentas_intereses": {
            $hoy = date("Y-m-d H:i:s");
            $inputs = $_POST["inputs"];
            $selects = $_POST["selects"];
            $archivos = $_POST["archivo"];

            //validaciones
            //validacion de select tipo de cuenta
            if ($selects[0] == "0") {
                echo json_encode(['Debe seleccionar un tipo de cuenta', '0']);
                return;
            }
            if ($selects[1] == "0") {
                echo json_encode(['Debe seleccionar un tipo de operación', '0']);
                return;
            }
            //validacion de select de tipo de documento
            //validar input cuenta 1
            if ($inputs[0] != "" && $inputs[2] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el debe', '0']);
                return;
            }
            //validar input cuenta 2
            if ($inputs[1] != "" && $inputs[3] == "") {
                echo json_encode(['Debe seleccionar una cuenta para el haber', '0']);
                return;
            }

            //Validar si ya existe una insercion con los mismos
            $id1 = get_ctb_nomenclatura2("ahomparaintere", "id_descript_intere", $selects[0], $selects[1], $archivos[1], $conexion);
            //validar si encontro un tipo de parametrizacion para el interes
            if ($id1 != "X") {
                echo json_encode(['No puede realizar esta actualizacion de parametrizacion porque ya existe', '0']);
                return;
            }

            //se hara la actualizacion
            $conexion->autocommit(false);
            try {
                $conexion->query("UPDATE ahomparaintere
                    SET id_tipo_cuenta = $selects[0],id_descript_intere=$selects[1],id_cuenta1=$inputs[0],id_cuenta2=$inputs[1],dfecmod='$hoy',id_usuario=$archivos[0] WHERE id=$archivos[1]");

                $conexion->commit();
                echo json_encode(['Datos actualizados correctamente', '1']);
            } catch (Exception $e) {
                $conexion->rollback();
                echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case "delete_aho_cuentas_intereses": {
            $id = $_POST["ideliminar"];
            $eliminar = "DELETE FROM ahomparaintere WHERE id =" . $id;
            if (mysqli_query($conexion, $eliminar)) {
                echo json_encode(['Eliminacion correcta ', '1']);
            } else {
                echo json_encode(['Error al eliminar ', '0']);
            }
            mysqli_close($conexion);
        }
        break;
    case 'edicion_recibo':
        $inputs = $_POST["inputs"];
        $hoy = date("Y-m-d H:i:s");
        $hoy2 = date("Y-m-d");

        $fechaaux4 = "";
        $usuario4 = "";

        //validar si hay campos vacios
        $valido = validarcampo([$inputs[0], $inputs[2], $inputs[3]], "");
        if ($valido != "1") {
            echo json_encode(['Hay campos que no estan llenos, no se puede completar la operación', '0']);
        }

        //consultar datos del aprmov con id recibido
        $data_ahommov = mysqli_query($conexion, "SELECT `ccodaho`,`ctipope`,`cnumdoc`,`ctipdoc`,`monto`, CAST(`created_at` AS DATE) AS created_at, created_by,`dfecope` FROM `ahommov` WHERE `id_mov`='$inputs[0]' AND cestado!=2");
        while ($da = mysqli_fetch_array($data_ahommov, MYSQLI_ASSOC)) {
            $ccodaho = $da["ccodaho"];
            $ctipope = $da["ctipope"];
            $cnumdoc = $da["cnumdoc"];
            $ctipdoc = $da["ctipdoc"];
            $ccodtip = substr($da["ccodaho"], 6, 2);
            $monto = $da["monto"];
            $fechaaux4 = $da["created_at"];
            $usuario4 = $da["created_by"];
            $dfecope = $da["dfecope"];
        }

        //COMPROBACION DE ESTADO DEL MES CONTABLE
        $cierre = comprobar_cierre($idusuario, $hoy2, $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $fechaaux4);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }

        if ($cierre_caja[0] == 8) {
            if ($usuario4 != $inputs[3]) {
                echo json_encode(['El usuario creador del registro no coincide con el que quiere editar, no es posible completar la acción', '0']);
                return;
            }
        }

        if (substr($cnumdoc, 0, 4) == "REV-") {
            echo json_encode(['No puede editar la reversión de un recibo', '0']);
            return;
        }

        //consultar datos de aprcta
        $data_ahomcta = mysqli_query($conexion, "SELECT `ccodcli` FROM `ahomcta` WHERE `ccodaho`=$ccodaho");
        while ($da = mysqli_fetch_array($data_ahomcta, MYSQLI_ASSOC)) {
            $ccodcli = $da["ccodcli"];
        }

        //consultar datos de tabla cliete para el nombre
        $data_cliente = mysqli_query($conexion, "SELECT `short_name`, `no_identifica` FROM `tb_cliente` WHERE `idcod_cliente`='$ccodcli'");
        while ($da = mysqli_fetch_array($data_cliente, MYSQLI_ASSOC)) {
            $shortname = (mb_strtoupper($da["short_name"], 'utf-8'));
            $dpi = $da["no_identifica"];
        }

        //obtener el registro anterior
        $bandera = false;
        $data_reg_ant = mysqli_query($conexion, "SELECT `id` FROM `ctb_diario` WHERE `numdoc`='$inputs[1]'");
        while ($da = mysqli_fetch_array($data_reg_ant, MYSQLI_ASSOC)) {
            $id_diario = $da["id"];
            $bandera = true;
        }

        $conexion->autocommit(false);
        try {
            //ACTUALIZACIONES EN APRMOV
            $conexion->query("UPDATE `ahommov` SET `cnumdoc` = '$inputs[2]',`dfecmod` = '$hoy',`codusu` = '$inputs[3]' WHERE `id_mov` = '$inputs[0]'");
            if ($bandera) {
                //INSERCIONES EN CTB_DIARIO
                $conexion->query("UPDATE `ctb_diario` SET `numdoc` = '$inputs[2]',`fecmod` = '$hoy',`id_tb_usu` = '$inputs[3]' WHERE `id` = $id_diario");
            }
            if ($conexion->commit()) {

                //NUMERO EN LETRAS
                $format_monto = new NumeroALetras();
                $decimal = explode(".", $monto);
                $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
                $letras_monto = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";
                $particionfecha = explode("-", $dfecope);

                ($ctipope == "D") ? $inputs[3] = "Depósito a cuenta " . $ccodaho : $inputs[3] = "Retiro a cuenta " . $ccodaho;
                echo json_encode(['Datos actualizados correctamente', '1', $ccodaho, number_format($monto, 2, '.', ','), date("d-m-Y", strtotime($hoy)), $inputs[2], $inputs[3], $shortname, decode_utf8($_SESSION['nombre']), decode_utf8($_SESSION['apellido']), $hoy, $letras_monto, $particionfecha[0], $particionfecha[1], $particionfecha[2], $dpi, "producto", $_SESSION['id']]);
            } else {
                echo json_encode(['Error al ingresar: ', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al ingresar: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);

        break;
    case 'reimpresion_recibo':
        $archivos = $_POST["archivo"];
        $hoy = date("Y-m-d H:i:s");
        $hoy2 = date("Y-m-d");

        //consultar datos del aprmov con id recibido
        $data_ahommov = mysqli_query($conexion, "SELECT `ccodaho`,`ctipope`,`cnumdoc`,`monto`,`dfecope`,`nrochq`,`ctipdoc`,correlativo,concepto,
        IFNULL((SELECT id_agencia FROM tb_usuario WHERE id_usu=aho.codusu),1) oficina
            FROM `ahommov` aho WHERE `id_mov`='$archivos[0]' AND cestado!=2");
        while ($da = mysqli_fetch_array($data_ahommov, MYSQLI_ASSOC)) {
            $ccodaho = $da["ccodaho"];
            $ctipope = $da["ctipope"];
            $cnumdoc = $da["cnumdoc"];
            $monto = $da["monto"];
            $dfecope = $da["dfecope"];
            $ncheque = $da["nrochq"];
            $tipchq = $da["ctipdoc"];
            $oficina = $da["oficina"];
            $correlativo = $da["correlativo"];
            $concepto = $da["concepto"];
        }

        //consultar datos de aprcta
        $data_ahomcta = mysqli_query($conexion, "SELECT `short_name`, `no_identifica`,tip.nombre,cli.control_interno,cli.idcod_cliente , cli.Direccion,
                                saldo_ahorro(aho.ccodaho, '$dfecope',$correlativo) AS saldo, tip.ccodtip,tip.cdescripcion
                    FROM tb_cliente cli 
                INNER JOIN ahomcta aho ON cli.idcod_cliente=aho.ccodcli 
                INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(aho.ccodaho,7,2)
                WHERE aho.ccodaho='$ccodaho'");
        while ($da = mysqli_fetch_array($data_ahomcta, MYSQLI_ASSOC)) {
            $producto = $da["nombre"];
            $shortname = (mb_strtoupper($da["short_name"], 'utf-8'));
            $dpi = $da["no_identifica"];
            $controlinterno = $da["control_interno"];
            $codcliente = $da["idcod_cliente"];
            $saldo = $da["saldo"];
            $direccion = $da["Direccion"];
            $ccodtip = ($da["ccodtip"]);
            $cdescripcion = ($da["cdescripcion"]);
        }

        if (substr($cnumdoc, 0, 4) == "REV-") {
            ($ctipope == "R") ? $archivos[1] = "Reversión de depósito a cuenta " . $ccodaho : $archivos[1] = "Reversión de retiro a cuenta " . $ccodaho;
        } else {
            ($ctipope == "D") ? $archivos[1] = "Depósito a cuenta " . $ccodaho : $archivos[1] = "Retiro a cuenta " . $ccodaho;
        }

        //NUMERO EN LETRAS
        $format_monto = new NumeroALetras();
        $decimal = explode(".", $monto);
        $res = (isset($decimal[1]) == false) ? 0 : $decimal[1];
        $letras_monto = ($format_monto->toMoney($decimal[0], 2, 'QUETZALES', '')) . " " . $res . "/100";
        $particionfecha = explode("-", $dfecope);


        // $nombreBanco = 'No disponible en reimpresion';
        //#region datos de recibo
        //datos que se enviaran al recibo de ahorros reprint
        echo json_encode(['Datos reimpresos correctamente', '1', $ccodaho, number_format($monto, 2, '.', ','), date("d-m-Y", strtotime($dfecope)), $cnumdoc, $archivos[1], $shortname, ($_SESSION['nombre']), ($_SESSION['apellido']), $hoy, $letras_monto, $particionfecha[0], $particionfecha[1], $particionfecha[2], $dpi, $producto, $_SESSION['id'], $controlinterno, $ncheque, $tipchq, $codcliente, $oficina, $ctipope, $saldo, $monto, $direccion, $ccodtip, $cdescripcion, $concepto]);
        mysqli_close($conexion);
        break;
    case 'eliminacion_recibo':
        $idDato = $_POST["ideliminar"];

        $conexion->autocommit(false);
        //Obtener informaicon de la ahommov
        $consulta = mysqli_query($conexion, "SELECT ccodaho, dfecope, cnumdoc, CAST(created_at AS DATE) AS fecsis FROM ahommov WHERE id_mov = $idDato AND cestado!=2");
        $dato = $consulta->fetch_row();

        //COMPROBAR CIERRE DE CAJA
        $fechainicio = date('Y-m-d', strtotime(date('Y-m-d') . ' - 7 days'));
        $fechafin = date('Y-m-d');
        $cierre_caja = comprobar_cierre_caja($_SESSION['id'], $conexion, 1, $fechainicio, $fechafin, $dato[3]);
        if ($cierre_caja[0] < 6) {
            echo json_encode([$cierre_caja[1], '0']);
            return;
        }
        $fechapoliza = $dato[1];
        //COMPROBAR SI EL MES CONTABLE ESTA ABIERTO 
        $consulta = mysqli_query($conexion, "SELECT feccnt FROM ctb_diario WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $fechapoliza = $fila["feccnt"];
        }

        $cierre = comprobar_cierre($idusuario, $fechapoliza, $conexion);
        if ($cierre[0] == 0) {
            echo json_encode([$cierre[1], '0']);
            return;
        }
        try {
            $res = $conexion->query("UPDATE ahommov SET cestado = '2', codusu = $idusuario, dfecmod = '$hoy2'  WHERE id_mov = $idDato");
            $aux = mysqli_error($conexion);

            $res1 = $conexion->query("UPDATE ctb_diario SET estado = '0', deleted_by = $idusuario, deleted_at = '$hoy2'  WHERE cod_aux = '$dato[0]' AND fecdoc = '$dato[1]' AND numdoc = '$dato[2]'");
            $aux1 = mysqli_error($conexion);

            if ($aux && $aux1) {
                echo json_encode(['Error fff', '0']);
                $conexion->rollback();
                return;
            }
            if (!$res && !$res1) {
                echo json_encode(['Error al ingresar ', '0']);
                $conexion->rollback();
                return;
            }
            $conexion->commit();
            echo json_encode(['Los datos fueron actualizados con exito ', '1']);
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error, al hacer el registro: ' . $e->getMessage(), '0']);
        }
        mysqli_close($conexion);
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
    case 'aho_cli':
        $consulta = $conexion->query("SELECT 
                    aho.ccodaho,
                    aho.ccodcli,
                    aho.nlibreta,
                    cli.no_identifica AS no_identifica,
                    tip.nombre,
                    cli.short_name
                FROM 
                    ahomcta aho
                INNER JOIN 
                    tb_cliente cli ON cli.idcod_cliente = aho.ccodcli
                INNER JOIN 
                    ahomtip tip ON tip.ccodtip = SUBSTR(aho.ccodaho, 7, 2)");

        $array_datos = array();
        $i = 0;
        while ($fila = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
            $array_datos[] = array(
                "0" => $i + 1,
                "1" => $fila["ccodaho"],
                "2" => $fila["ccodcli"],
                "3" => $fila["no_identifica"],
                "4" => $fila["nombre"],
                "5" => $fila["short_name"],
                "6" => '<button type="button" style="--bs-btn-padding-y: .25rem; --bs-btn-padding-x: .5rem; --bs-btn-font-size: .75rem;" class="btn btn-primary btn-sm" onclick="printdiv2(`#cuadro`, `' . $fila["ccodaho"] . '`)" data-bs-dismiss="modal">Aceptar</button>'
            );
            $i++;
        }
        $results = array(
            "draw" => isset($_POST['draw']) ? intval($_POST['draw']) : 0,
            "recordsTotal" => count($array_datos),
            "recordsFiltered" => count($array_datos),
            "data" => $array_datos
        );
        echo json_encode($results);
        // Cerrar la conexión
        mysqli_close($conexion);
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
        // list($serialSession) = $_POST["inputs"];
        // list($operacion, $idPersona,$srcPc) = $_POST["archivo"];
        //token,condi: 'activarSensor',sessionSerial,peopleCode 
        $serialSession = $_POST["sessionSerial"];
        $operacion = $_POST["operation"];
        $idPersona = $_POST["peopleCode"];
        $srcPc = $_POST["token"];


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
            $mensaje = "Huella activada correctamente";
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

        $showmensaje = false;
        try {
            $database->openConnection();

            $database->beginTransaction();

            // $database->delete('huella_temp', "pc_serial=?", [$srcPc]);

            $datos = array(
                "opc" => "stop",
            );

            $database->update('huella_temp', $datos, "pc_serial=?", [$srcPc]);

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
            'message' => $mensaje,
            'status' => $status,
        ]);

        break;
    case 'verifyFingerprint':
        $operationType = $_POST["operationType"];
        $showmensaje = false;
        try {
            $database->openConnection();
            $result = $database->selectColumns("tb_validacioneshuella", ['estado'], "id_modulo=? AND estado=1", [$operationType]);
            $verify = (empty($result)) ? 0 : 1;

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
            'verify' => ($verify ?? 0)
        ]);

        break;

    // ==================================================================================
    // ==================================================================================IVEFORMS

    case 'addIveform':
        // ==================================================================================
        // Capturar datos enviados por POST
        // ==================================================================================
        $esTitular = isset($_POST['esTitular']) ? $_POST['esTitular'] : '';
        $recurrente = isset($_POST['recurrente']) ? $_POST['recurrente'] : 0; // Valor por defecto
        $dpi = isset($_POST['dpi']) ? trim($_POST['dpi']) : '';
        $cuenta = isset($_POST['cuenta']) ? trim($_POST['cuenta']) : '';
        $nombre1 = isset($_POST['nombre1']) ? trim($_POST['nombre1']) : '';
        $nombre2 = isset($_POST['nombre2']) ? trim($_POST['nombre2']) : '';
        $nombre3 = isset($_POST['nombre3']) ? trim($_POST['nombre3']) : '';
        $apellido1 = isset($_POST['apellido1']) ? trim($_POST['apellido1']) : '';
        $apellido2 = isset($_POST['apellido2']) ? trim($_POST['apellido2']) : '';
        $apellido3 = isset($_POST['apellido3']) ? trim($_POST['apellido3']) : '';  // Apellido de casada
        $oriFondos = isset($_POST['oriFondos']) ? trim($_POST['oriFondos']) : '';
        $destFondos = isset($_POST['destFondos']) ? trim($_POST['destFondos']) : '';
        $nacionalidad = isset($_POST['nacionalidad']) ? trim($_POST['nacionalidad']) : '';
        $monto = isset($_POST['monto']) ? trim($_POST['monto']) : '0';
        $propietario = isset($_POST['propietario']) ? intval($_POST['propietario']) : 1; // Valor por defecto

        // ==================================================================================
        // Validación de datos
        // ==================================================================================
        if ($esTitular != 'si' && empty($dpi)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'El DPI no puede estar vacío.'
            ]);
            exit;
        }

        if (empty($cuenta)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'El código de cuenta no puede estar vacío.'
            ]);
            exit;
        }

        if (!is_numeric($monto) || $monto <= 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'El monto debe ser un número válido y mayor que cero.'
            ]);
            exit;
        }

        // Convertir monto a decimal
        $monto = floatval($monto);

        // ==================================================================================
        // Preparar la inserción en la base de datos
        // ==================================================================================
        $crateby = isset($_SESSION['usu']) ? $_SESSION['usu'] : 'sistema';

        $sql = "INSERT INTO tb_RTE_use (
                        ccdocta,
                        Nombre1,
                        Nombre2,
                        Nombre3,
                        Apellido1,
                        Apellido2,
                        Apellido_de_casada,
                        DPI,
                        ori_fondos,
                        desti_fondos,
                        Nacionalidad,
                        Mon,
                        Crateby,
                        Cretadate,
                        recurrente,
                        propietario
                    ) VALUES (
                        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?
                    )";

        try {
            // Preparar la sentencia
            $stmt = $conexion->prepare($sql);
            if (!$stmt) {
                throw new Exception('Error al preparar la consulta: ' . $conexion->error);
            }

            // Bind de parámetros
            $stmt->bind_param(
                "sssssssssssdsdd", // Tipos de datos: 12 strings (s), 2 doubles (d)
                $cuenta,
                $nombre1,
                $nombre2,
                $nombre3,
                $apellido1,
                $apellido2,
                $apellido3,
                $dpi,
                $oriFondos,
                $destFondos,
                $nacionalidad,
                $monto,
                $crateby,
                $recurrente,
                $propietario // Añadir el campo propietario
            );

            // Ejecutar la inserción
            if ($stmt->execute()) {
                echo json_encode([
                    'status' => 'ok',
                    'message' => 'Datos RTE guardados correctamente.'
                ]);
            } else {
                throw new Exception('Error al ejecutar la consulta: ' . $stmt->error);
            }
        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
        }
        break;

    case 'Update_ahoEST':
        // Se espera que se envíe vía POST la cuenta de ahorro a actualizar (por ejemplo, 'cuenta') y, opcionalmente, una nueva fecha de cancelación ('fecha_cancel')
        $cuenta = $_POST['cuenta'] ?? '';
        $fecha_nueva = $_POST['fecha_cancel'] ?? ''; // Si se desea modificar la fecha
        $usuario_actualiza = $_SESSION['id'] ?? '';    // Usuario que actualiza (si es que se requiere usarlo en otro proceso)
        $hoy = date("Y-m-d H:i:s");

        if (empty($cuenta)) {
            echo json_encode(['Falta el código de cuenta de ahorro para inactivar', '0']);
            return;
        }
        // Opcional: consultar el estado actual de la cuenta de ahorro
        $queryEstado = "SELECT estado, fecha_cancel FROM ahomcta WHERE ccodaho = ?";
        $stmtEstado = $conexion->prepare($queryEstado);
        $stmtEstado->bind_param('s', $cuenta);
        $stmtEstado->execute();
        $resultEstado = $stmtEstado->get_result()->fetch_assoc();
        if (!$resultEstado) {
            echo json_encode(['La cuenta de ahorro no existe', '0']);
            return;
        }
        // Si la cuenta ya está inactiva, se puede validar o notificar (por ejemplo, actualizando la fecha de cancelación)
        if ($resultEstado['estado'] === 'B') {
            if (!empty($fecha_nueva) && !validateDate($fecha_nueva, 'Y-m-d')) {
                echo json_encode(['La fecha de cancelación ingresada es inválida', '0']);
                return;
            }
            // Aquí podrías comparar el usuario si es requerido, u otras validaciones
        }

        $conexion->autocommit(false);
        try {
            // Se utiliza la fecha nueva si se envía, de lo contrario se toma la fecha actual
            $fecha_cancel_final = !empty($fecha_nueva) ? $fecha_nueva : $hoy;
            // Se elimina la actualización de "codusu", ya que esa columna no existe en ahomcta
            $res = $conexion->prepare("UPDATE ahomcta SET estado = 'B', fecha_cancel = ? WHERE ccodaho = ?");
            if (!$res) {
                throw new Exception($conexion->error);
            }
            $res->bind_param('ss', $fecha_cancel_final, $cuenta);
            $res->execute();

            if ($conexion->commit()) {
                echo json_encode(['La cuenta de ahorro ha sido actualizada/inactivada exitosamente', '1', $cuenta]);
            } else {
                echo json_encode(['Error al confirmar la transacción', '0']);
            }
        } catch (Exception $e) {
            $conexion->rollback();
            echo json_encode(['Error al actualizar/inactivar la cuenta de ahorro: ' . $e->getMessage(), '0']);
        }
        break;

    case 'lprint':
        $ccodaho = $_POST["id"];
        $checkeds = $_POST["archivo"][0];
        $ids = implode(',', array_map('intval', $checkeds));
        $showmensaje = false;
        try {
            $database->openConnection();
            $prdaho = $database->selectColumns("ahomtip", ['numfront', 'front_ini', 'numdors', 'dors_ini'], "ccodtip=?", [substr($ccodaho, 6, 2)]);
            if (empty($prdaho)) {
                $showmensaje = true;
                throw new Exception("No se encontro el producto de la cuenta de ahorro");
            }
            $query = "SELECT mov.ccodaho, dfecope,ctipdoc, ctipope, cnumdoc,mov.crazon, concepto, monto, numlinea, correlativo, 
            saldo_ahorro(mov.ccodaho, dfecope ,correlativo) AS saldo,usu.id_agencia as id_agencia, 
            ifnull(usu2.id_agencia,1) as agencia_libreta, mov.codusu
            FROM ahommov mov 
            LEFT JOIN tb_usuario usu ON usu.id_usu = mov.codusu 
            LEFT JOIN ahomlib lib ON lib.nlibreta = mov.nlibreta and mov.ccodaho=lib.ccodaho and lib.estado = 'A'
            LEFT JOIN tb_usuario usu2 on lib.ccodusu = usu2.id_usu
            WHERE id_mov IN ($ids) AND mov.ccodaho = ? AND mov.cestado = 1 ORDER BY mov.correlativo;";
            $movimientos = $database->getAllResults($query, [$ccodaho]);
            if (empty($movimientos)) {
                $showmensaje = true;
                throw new Exception("No se encontraron movimientos para la cuenta de ahorro seleccionada");
            }

            $documentos = $database->getAllResults("SELECT nombre FROM tb_documentos WHERE id_reporte = 3");
            if (empty($documentos)) {
                $showmensaje = true;
                throw new Exception("No se encontro el documento para la cuenta de ahorro seleccionada");
            }

            $datos = array(
                "lineaprint" => 'S'
            );
            $database->update('ahommov', $datos, "id_mov IN ($ids) AND ccodaho = ?", [$ccodaho]);

            $status = 1;
            $mensaje = "Proceso realizado correctamente";
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
        echo json_encode([$mensaje, $status, $movimientos, $prdaho, $documentos ?? []]);
        break;

    /* --------------------------------------------------------------------------
 |  ACTUALIZAR / INSERTAR ATRIBUTO 19  (Cliente Recurrente)  — sin auditoría
 * -------------------------------------------------------------------------- */
    case 'actua_recurrente':
        if (!isset($_POST['clientes'], $_POST['valor'])) {
            echo json_encode(['Parámetros incompletos', '0']);
            break;
        }

        $valor = intval($_POST['valor']) === 1 ? 1 : 0;
        // Manejo de clientes como strings en lugar de integers
        $clientes = is_array($_POST['clientes']) ? $_POST['clientes'] : [$_POST['clientes']];
        $clientes = array_values(array_filter($clientes, fn($i) => !empty($i)));

        if (!$clientes) {
            echo json_encode(['Lista de clientes vacía o inválida', '0']);
            break;
        }

        $id_atributo = 19;

        try {
            $database->openConnection();
            $database->beginTransaction();

            /* 1. UPDATE solo el campo valor */
            $sqlUpdate = "
        UPDATE tb_cliente_atributo
           SET valor = ?
         WHERE id_cliente = ? AND id_atributo = ?
        ";

            /* 2. INSERT con las columnas reales de tu tabla */
            $sqlInsert = "
        INSERT INTO tb_cliente_atributo (id_cliente, id_atributo, valor)
        VALUES (?, ?, ?)
        ";

            foreach ($clientes as $cli) {
                $stmt = $database->executeQuery($sqlUpdate, [$valor, $cli, $id_atributo]);
                if ($stmt->rowCount() === 0) {
                    $database->executeQuery($sqlInsert, [$cli, $id_atributo, $valor]);
                }
            }

            $database->commit();
            echo json_encode(['Actualización realizada correctamente', '1']);
        } catch (Exception $e) {
            $database->rollback();
            $codigoError = logerrores(
                $e->getMessage(),
                __FILE__,
                __LINE__,
                $e->getFile(),
                $e->getLine()
            );
            echo json_encode(["Error al actualizar ($codigoError)", '0']);
        } finally {
            $database->closeConnection();
        }
        break;

    case 'create_rte_user':

        // ['rte_ccdocta','rte_dpi','rte_nombre1','rte_nombre2','rte_nombre3','rte_apellido1','rte_apellido2','rte_apellido3','rte_ori_fondos',
        // 'rte_desti_fondos','rte_nacionalidad'], [], ['rte_esTitular']
        list($monto, $ccodaho, $dpi, $nombre1, $nombre2, $nombre3, $apellido1, $apellido2, $apellido3, $origen_fondos, $destino, $nacionalidad, $cnumdoc, $idAlerta) = $_POST["inputs"];
        list($esTitular) = $_POST["radios"];

        $showmensaje = false;
        try {

            if ($ccodaho == "" || $ccodaho == null) {
                $showmensaje = true;
                throw new Exception("El código de cuenta es obligatorio");
            }

            if ($origen_fondos == "") {
                $showmensaje = true;
                throw new Exception("El origen de fondos es obligatorio");
            }
            if ($destino == "" || $destino == null) {
                $showmensaje = true;
                throw new Exception("El destino de fondos es obligatorio");
            }
            if ($nacionalidad == "" || $nacionalidad == null) {
                $showmensaje = true;
                throw new Exception("La nacionalidad es obligatoria");
            }

            if ($esTitular == 0) {
                if ($dpi == "" || $dpi == null) {
                    $showmensaje = true;
                    throw new Exception("El DPI es obligatorio");
                }
                if ($nombre1 == "" || $nombre1 == null) {
                    $showmensaje = true;
                    throw new Exception("El primer nombre es obligatorio");
                }
                if ($apellido1 == "" || $apellido1 == null) {
                    $showmensaje = true;
                    throw new Exception("El primer apellido es obligatorio");
                }
            }
            $database->openConnection();

            $datosCuenta = $database->selectColumns("ahomcta", ['ccodcli'], "ccodaho=?", [$ccodaho]);
            if (empty($datosCuenta)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de ahorros, verifique que el código sea correcto y que el cliente esté activo");
            }

            $result = $database->selectColumns("tb_cliente_atributo", ['valor'], "id_cliente=? AND id_atributo=19", [$datosCuenta[0]['ccodcli']]);
            $verify = (empty($result)) ? 0 : trim($result[0]['valor']);

            $database->beginTransaction();

            $tb_RTE_use = [
                'ccdocta' => $ccodaho,
                'Nombre1' => $nombre1,
                'Nombre2' => $nombre2,
                'Nombre3' => $nombre3,
                'Apellido1' => $apellido1,
                'Apellido2' => $apellido2,
                'Apellido_de_casada' => $apellido3,
                'DPI' => ($esTitular == 1) ? '' : $dpi,
                'ori_fondos' => $origen_fondos,
                'desti_fondos' => $destino,
                'Nacionalidad' => $nacionalidad,
                'Mon' => $monto,
                'aux' => $cnumdoc,
                'Crateby' => $idusuario,
                'Cretadate' => $hoy2,
                'recurrente' => $verify,
                'propietario' => $esTitular,
            ];

            $idRTE = $database->insert('tb_RTE_use', $tb_RTE_use);

            if (is_numeric($idAlerta) && $idAlerta > 0) {
                $database->update('tb_alerta', ['puesto' => $idRTE], 'id=?', [$idAlerta]);
            }

            $database->commit();
            $mensaje = "Datos del depositante guardados correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
            'reprint' => 0
        ]);
        break;

    case 'create_config_interes':
        // ['<?= $csrf->getTokenName()'], ['producto_id','tipo','periodo','provisionar','estado']

        list($csrftoken) = $_POST["inputs"];
        list($producto_id, $tipo, $periodo, $provisionar) = $_POST["selects"];

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

        // $idCertificado = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {

            if ($producto_id == "" || $tipo == "" || $periodo == "" || $provisionar == "") {
                $showmensaje = true;
                throw new Exception("El producto, el tipo, el periodo y la provisión son obligatorios");
            }

            $database->openConnection();

            $verificacion = $database->selectColumns("ahomtip", ['id_tipo'], "id_tipo=? AND estado=1", [$producto_id]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se encontró el tipo de producto, verifique que el código sea correcto y que esté activo");
            }

            $verificacion = $database->selectColumns("aho_configuraciones_int", ['estado'], "producto_id=? AND estado IN (1,2)", [$producto_id]);
            if (!empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("El tipo de producto ya está configurado");
            }

            $database->beginTransaction();

            $aho_configuraciones_int = [
                'producto_id' => $producto_id,
                'tipo' => $tipo,
                'periodo' => $periodo,
                'provisionar' => ($periodo == 1) ? 0 : $provisionar,
                'estado' => 1,
                'created_by' => $idusuario,
                'created_at' => date('Y-m-d H:i:s'),
            ];

            $database->insert('aho_configuraciones_int', $aho_configuraciones_int);

            $database->commit();
            $mensaje = "Configuración creada correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
        ]);

        break;
    case 'update_config_interes':
        // ['<?= $csrf->getTokenName()'], ['producto_id','tipo','periodo','provisionar','estado']

        list($csrftoken) = $_POST["inputs"];
        list($producto_id, $tipo, $periodo, $provisionar, $estado) = $_POST["selects"];
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

        $idConfiguracion = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {

            if ($producto_id == "" || $tipo == "" || $periodo == "" || $provisionar == "") {
                $showmensaje = true;
                throw new Exception("El producto, el tipo, el periodo y la provisión son obligatorios");
            }

            $database->openConnection();

            $verificacion = $database->selectColumns("ahomtip", ['id_tipo'], "id_tipo=? AND estado=1", [$producto_id]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se encontró el tipo de producto, verifique que el código sea correcto y que esté activo");
            }

            $verificacion = $database->selectColumns("aho_configuraciones_int", ['estado'], "producto_id=? AND estado IN (1,2) AND id!=?", [$producto_id, $idConfiguracion]);
            if (!empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("El tipo de producto ya está configurado");
            }

            $database->beginTransaction();

            $aho_configuraciones_int = [
                'producto_id' => $producto_id,
                'tipo' => $tipo,
                'periodo' => $periodo,
                'provisionar' => ($periodo == 1) ? 0 : $provisionar,
                'estado' => $estado,
                'updated_by' => $idusuario,
                'updated_at' => date('Y-m-d H:i:s'),
            ];

            $database->update('aho_configuraciones_int', $aho_configuraciones_int, 'id=?', [$idConfiguracion]);

            $database->commit();
            $mensaje = "Configuración actualizada correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
        ]);

        break;
    case 'delete_config_interes':
        // ['<?= $csrf->getTokenName()'], []

        list($csrftoken) = $_POST["inputs"];
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

        $idConfiguracion = $secureID->decrypt($encryptedID);

        $showmensaje = false;
        try {

            $database->openConnection();

            $verificacion = $database->selectColumns("aho_configuraciones_int", ['estado'], "estado IN (1,2) AND id=?", [$idConfiguracion]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("La configuración seleccionada no existe o ya no está disponible.");
            }

            $database->beginTransaction();

            $aho_configuraciones_int = [
                'estado' => 0,
                'deleted_by' => $idusuario,
                'deleted_at' => date('Y-m-d H:i:s'),
            ];

            $database->update('aho_configuraciones_int', $aho_configuraciones_int, 'id=?', [$idConfiguracion]);

            $database->commit();
            $mensaje = "Configuración eliminada correctamente";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
        ]);

        break;

    case 'addTitularAccount':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken) = $_POST["inputs"];
        list($encryptedID, $codigoCliente) = $_POST["archivo"];

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

        $showmensaje = false;
        try {

            $codigoCuenta = $secureID->decrypt($encryptedID);
            $database->openConnection();

            $verificacion = $database->selectColumns("ahomcta", ['ccodcli'], "ccodaho=?", [$codigoCuenta]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de ahorros, verifique que el código sea correcto y que el cliente esté activo");
            }

            if ($verificacion[0]['ccodcli'] == $codigoCliente) {
                $showmensaje = true;
                throw new Exception("La persona seleccionada ya es titular de la cuenta, no se puede agregar nuevamente");
            }

            $verificacion2 = $database->selectColumns("cli_mancomunadas", ['id'], "ccodaho=? AND ccodcli=? AND estado=1 AND tipo='ahorro'", [$codigoCuenta, $codigoCliente]);
            if (!empty($verificacion2)) {
                $showmensaje = true;
                throw new Exception("El titular ya se encuentra agregado a la cuenta de ahorro, no se puede agregar nuevamente");
            }

            $database->beginTransaction();

            $cli_mancomunadas = [
                'tipo' => 'ahorro',
                'ccodaho' => $codigoCuenta,
                'ccodcli' => $codigoCliente,
                'estado' => 1,
                'created_by' => $idusuario,
                'created_at' => date('Y-m-d H:i:s')
            ];

            $database->insert('cli_mancomunadas', $cli_mancomunadas);

            $database->commit();
            $mensaje = "Titular agregado correctamente a la cuenta de ahorro";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
        ]);

        break;
    case 'deleteTitularAccount':

        if (!isset($_SESSION['id_agencia'])) {
            echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', '0']);
            return;
        }

        list($csrftoken) = $_POST["inputs"];
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

        $showmensaje = false;
        try {

            $idTitular = $secureID->decrypt($encryptedID);
            $database->openConnection();

            $verificacion = $database->selectColumns("cli_mancomunadas", ['ccodcli'], "id=?", [$idTitular]);
            if (empty($verificacion)) {
                $showmensaje = true;
                throw new Exception("No se encontró el titular, verifique que el código sea correcto y que el cliente esté activo");
            }

            $database->beginTransaction();

            $cli_mancomunadas = [
                'estado' => 0,
                'deleted_by' => $idusuario,
                'deleted_at' => date('Y-m-d H:i:s')
            ];

            $database->update('cli_mancomunadas', $cli_mancomunadas, 'id=?', [$idTitular]);

            $database->commit();
            $mensaje = "Titular eliminado correctamente de la cuenta de ahorro";
            $status = 1;
        } catch (Exception $e) {
            $database->rollback();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "Error: " . $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }
        echo json_encode([
            $mensaje,
            $status,
        ]);

        break;
}

//FUNCION para obtener los depositos de los utimos 30 dias y los R(reversion y retiros) asi como el valor del dolar.
function movimiento($conexion, $op = 0, $codCli = [], $tipoMov = '', $fechaH = '', $codCu = '', $db_name_general = "jpxdcegu_bd_general_coopera")
{
    switch ($op) {
        case 1: //Depositos y Retiros
            $dato = mysqli_query($conexion, "SELECT (IFNULL(SUM(monto),0))  AS dato 
            FROM ahommov AS mov
            INNER JOIN ahomcta AS ac ON mov.ccodaho = ac.ccodaho
            WHERE ac.estado = 'A' AND mov.cestado!=2 AND ac.ccodcli = '" . $codCli['ccodcli'] . "' AND ctipope = '" . $tipoMov . "'
            AND dfecope BETWEEN " . $fechaH . " AND CURDATE()");
            $error = mysqli_error($conexion);
            if ($error) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            };
            $movMot = mysqli_fetch_assoc($dato);
            return $movMot['dato'];
            break;
        case 2:
            $dato = mysqli_query($conexion, "SELECT equiDolar AS dato FROM $db_name_general.tb_monedas WHERE id = 1");
            $error = mysqli_error($conexion);
            if ($error) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            };
            $movMot = mysqli_fetch_assoc($dato);
            return $movMot['dato'];
            break;
        case 3:
            return ((movimiento($conexion, 1, $codCli, 'D', '(DATE_SUB(CURDATE(), INTERVAL 30 DAY))')) - (movimiento($conexion, 1, $codCli, 'R', '(DATE_SUB(CURDATE(), INTERVAL 30 DAY))')));
            break;
        case 4:
            $fecha = '';
            $dato = mysqli_query($conexion, "SELECT MAX(fecha) as fecha FROM tb_alerta WHERE cod_aux = '$codCu' AND proceso = ('A' OR 'A1') AND fecha BETWEEN (DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND CURDATE()");

            $fila = mysqli_affected_rows($conexion);
            if ($fila > 0) {
                $datoF = mysqli_fetch_assoc($dato);
                $fecha = "'" . $datoF['fecha'] . "'";
            }
            if ($fila == 0)
                $fecha = 'CURDATE()';
            return ((movimiento($conexion, 1, $codCli, 'D', $fecha)) - (movimiento($conexion, 1, $codCli, 'R', $fecha)));
            break;
        case 5:
            $dato = mysqli_query($conexion, "SELECT MAX(codDoc) AS codDoc FROM tb_alerta WHERE cod_aux = '" . $codCu . "'");
            $codDoc = mysqli_fetch_assoc($dato);

            $dato1 = mysqli_query($conexion, "SELECT IFNULL(dfecmod, '0') AS fecha FROM ahommov WHERE cestado!=2 AND cnumdoc = '" . $codDoc['codDoc'] . "';");

            if (mysqli_affected_rows($conexion) != 0) {
                $fechaHora = mysqli_fetch_assoc($dato1);
                $dato1 = mysqli_query($conexion, "SELECT (IFNULL(SUM(monto),0))  AS mov 
                FROM ahommov AS mov
                INNER JOIN ahomcta AS ac ON mov.ccodaho = ac.ccodaho
                WHERE ac.estado = 'A' AND mov.cestado!=2 AND ac.ccodcli = '" . $codCli['ccodcli'] . "' AND ctipope = '" . $tipoMov . "'
                AND mov.dfecmod > '" . $fechaHora['fecha'] . "';");
                $auxMov = mysqli_fetch_assoc($dato1);
                // echo json_encode(['Fecha '.$auxMov['mov'], '0']);
                // return; 
                return $auxMov['mov'];
            }
            return 0;
            break;
    }
}

//FUNCIN para el control de las alertas
function alerta($conexion, $op = 0, $codCu = '', $hoy2 = '', $codUsu = 0, $hoy = '', $proceso = '', $cnumdoc = '', $cliente = '')
{
    switch ($op) {
        case 1:
            $res = $conexion->query("INSERT INTO `tb_alerta` (`puesto`, `tipo_alerta`, `mensaje`, `cod_aux`, `proceso`,`estado`, `fecha`,`created_by`, `created_at`, `codDoc`) 
            value('ADM', 'IVE', 'Llenar el formulario del IVE', '$codCu', '$proceso', 1, '$hoy2', $codUsu, '$hoy', '$cnumdoc')");
            if (mysqli_error($conexion) || !$res) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            }
            break;
        case 2:
            $dato = '';
            $consulta = mysqli_query($conexion, "SELECT IFNULL(MAX(proceso),'0') AS pro  FROM  `tb_alerta` WHERE proceso IN ('A','A1') AND `cod_aux` = '$codCu' AND `fecha` = '$hoy2'");
            $datoAlerta = mysqli_fetch_assoc($consulta);
            $dato = $datoAlerta['pro'];

            if ($dato == 'A' || $dato == '0')
                $dato = 'VC'; //Retorno un valor vacio
            return $dato;
            break;
        case 3:
            $consulta = mysqli_query($conexion, "SELECT EXISTS(
            SELECT id FROM tb_alerta WHERE cod_aux = '$codCu' AND proceso IN ('A','A1') AND fecha BETWEEN (DATE_SUB(CURDATE(), INTERVAL 30 DAY)) AND CURDATE()) AS dato");
            $rsultadoIVE = mysqli_fetch_assoc($consulta);
            return $rsultadoIVE['dato'];
            break;
        case 4:
            $consulta = mysqli_query($conexion, "SELECT EXISTS(SELECT codDoc FROM tb_alerta WHERE 
            cod_aux = '" . $codCu . "' AND codDoc = '" . $cnumdoc . "' AND estado = 0 AND proceso IN ('A' ,'A1')) AS dato ;");
            $datoAux = mysqli_fetch_assoc($consulta);
            return $datoAux['dato'];
            break;
        case 5:
            $consulta = mysqli_query($conexion, "SELECT CONCAT(nombre, apellido) AS cli, Email FROM tb_usuario WHERE estado = 1 AND puesto IN ('CNT', 'ADM')");
            if (mysqli_error($conexion)) {
                echo json_encode(['Error … !!!,  comunicarse con soporte. ', '0']);
                return;
            }
            $arch = [$cliente, $codCu, $cnumdoc];
            while ($row = mysqli_fetch_array($consulta, MYSQLI_ASSOC)) {
                enviarCorreo("" . $row['Email'] . "", "" . $row['cli'] . "", "Alerta IVE", "<h5>El sistema se encuentra a la espera de la aprobación de una alerta de IVE.</h5>", $arch);
            }
            break;
    }
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
        } elseif ($validaciones[$i][3] == 6) { //menor o igual
            if ($validaciones[$i][0] <= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 7) { //menor o igual
            if ($validaciones[$i][0] >= $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        } elseif ($validaciones[$i][3] == 8) { //diferente de
            if ($validaciones[$i][0] != $validaciones[$i][1]) {
                return [$validaciones[$i][2], '0', true];
                $i = count($validaciones) + 1;
            }
        }
    }
    return ["", '0', false];
}

function executequery($query, $params, $typparams, $conexion)
{
    $stmt = $conexion->prepare($query);
    $aux = mysqli_error($conexion);
    if ($aux) {
        return ['ERROR: ' . $aux, false];
    }
    $types = '';
    $bindParams = [];
    $bindParams[] = &$types;
    $i = 0;
    foreach ($params as &$param) {
        // $types .= 's';
        $types .= $typparams[$i];
        $bindParams[] = &$param;
        $i++;
    }
    call_user_func_array(array($stmt, 'bind_param'), $bindParams);
    if (!$stmt->execute()) {
        return ["Error en la ejecución de la consulta: " . $stmt->error, false];
    }
    $data = [];
    $resultado = $stmt->get_result();
    $i = 0;
    while ($fila = $resultado->fetch_assoc()) {
        $data[$i] = $fila;
        $i++;
    }
    $stmt->close();
    return [$data, true];
}
