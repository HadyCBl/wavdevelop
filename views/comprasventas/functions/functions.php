<?php

use Micro\Generic\Validator;

include __DIR__ . '/../../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 0]);
    return;
}
require_once __DIR__ . '/../../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../../includes/Config/SecureID.php';
include __DIR__ . '/../../../includes/Config/database.php';
$csrf = new CSRFProtection();
$secureID = new SecureID($key1);
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

//Antigua Conexion
include '../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

include __DIR__ . '/../../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');
$hoy2 = date("Y-m-d H:i:s");
$idusuario = $_SESSION["id"];

$inputJSON = file_get_contents('php://input');
$input = json_decode($inputJSON, true);

$condi = (isset($input["condi"])) ? $input["condi"] : ((isset($_POST["condi"]) ? $_POST["condi"] : 0));


switch ($condi) {
    case 'savefacturas':
        $tipof = $input["tipof"];
        $data = $input["jsondata"];
        // Decodificar el JSON recibido
        // $json_data = json_decode($data, true);
        $idsinsertados = [];
        $noinsertados = [];
        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();
            foreach ($data["files"] as $file) {
                // $database->beginTransaction();
                /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                    +++++++++++++++++++++++++++++++ SECCION RECEPCION DE DATOS DE FACTURA ++++++++++++++++++++++++++++++++++++
                    ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
                if (isset($file["dte:GTDocumento"]["dte:SAT"]["dte:DTE"])) {
                    $dte = $file["dte:GTDocumento"]["dte:SAT"]["dte:DTE"];

                    //1 DATOS CERTIFICACION
                    $dte_certificacion = $dte["dte:Certificacion"];
                    $cert_fechahora = $dte_certificacion["dte:FechaHoraCertificacion"]["#text"];
                    $cert_nit = $dte_certificacion["dte:NITCertificador"]["#text"];
                    $cert_nombre = $dte_certificacion["dte:NombreCertificador"]["#text"];
                    $cert_codigoautorizacion = $dte_certificacion["dte:NumeroAutorizacion"]["#text"];
                    $cert_numero = $dte_certificacion["dte:NumeroAutorizacion"]["@attributes"]["Numero"];
                    $cert_serie = $dte_certificacion["dte:NumeroAutorizacion"]["@attributes"]["Serie"];

                    $result = $database->selectColumns('cv_facturas', ['id'], 'codigo_autorizacion=? AND serie=? AND no_autorizacion=? AND estado IN (1,2)', [$cert_codigoautorizacion, $cert_serie, $cert_numero]);
                    if (!empty($result)) {
                        // $showmensaje = true;
                        // throw new Exception("Codigo: $cert_codigoautorizacion, Numero: $cert_numero, Serie: $cert_serie Ya se encuentra registrado, verificar");

                        array_push($noinsertados, [$cert_numero, $cert_serie, $cert_codigoautorizacion, "Ya se encuentra registrado"]);
                    } else {
                        $result = $database->selectColumns('cv_certificador', ['id'], 'nit=? AND estado=1', [$cert_nit]);
                        $id_certificador = (empty($result)) ? addcertificador($database, $dte_certificacion, $idusuario, $hoy2) : $result[0]["id"];
                        //FIN DATOS CERTIFICACION

                        //2 DATOS EMISION
                        $dte_datosEmision = $dte["dte:DatosEmision"];

                        //2.1 DATOS GENERALES
                        $ems_datosGenerales = $dte_datosEmision["dte:DatosGenerales"]["@attributes"];
                        $codmoneda = $ems_datosGenerales["CodigoMoneda"];
                        $fecha_hora_emision = $ems_datosGenerales["FechaHoraEmision"];
                        $tipo = $ems_datosGenerales["Tipo"];

                        $id_moneda = getidmoney($database, $codmoneda, $db_name_general);

                        $result = $database->selectColumns('cv_tiposdte', ['id'], 'codigo=?', [$tipo]);
                        if (empty($result)) {
                            $showmensaje = true;
                            throw new Exception("Tipo de DTE con Codigo: $tipo no existe, verificar");
                        }
                        $id_tipodte = $result[0]["id"];

                        //2.2 EMISOR
                        $ems_emisor = $dte_datosEmision["dte:Emisor"];
                        $emisor_nit = $ems_emisor["@attributes"]["NITEmisor"];
                        $emisor_afiliacion = $ems_emisor["@attributes"]["AfiliacionIVA"];

                        $result = $database->selectColumns('cv_emisor', ['id'], 'nit=? AND estado=1', [$emisor_nit]);
                        $id_emisor = (empty($result)) ? addemisor($database, $ems_emisor, $idusuario, $hoy2) : $result[0]["id"];


                        //2.4 RECEPTOR
                        $ems_receptor = $dte_datosEmision["dte:Receptor"];
                        $receptor_id = $ems_receptor["@attributes"]["IDReceptor"];
                        $receptor_name = $ems_receptor["@attributes"]["NombreReceptor"];

                        $result = $database->selectColumns('cv_receptor', ['id', 'nombre'], 'id_receptor=? AND estado=1', [$receptor_id]);

                        if (empty($result)) {
                            $id_receptor = addreceptor($database, $ems_receptor, $idusuario, $hoy2);
                        } else {
                            $id_receptor = $result[0]["id"];
                            if (preg_match('/^C[\/\- ]?F$/i', $receptor_id)) {
                                //BUSCAR POR NOMBRE
                                $found = false;
                                foreach ($result as $kiki => $resi) {
                                    if ($resi["nombre"] == $receptor_name) {
                                        $found = true;
                                        $id_receptor = $resi["id"];
                                        break;
                                    }
                                }
                                if (!$found) {
                                    $id_receptor = addreceptor($database, $ems_receptor, $idusuario, $hoy2);
                                }
                            }
                        }


                        // $id_receptor = (empty($result)) ? addreceptor($database, $ems_receptor, $idusuario, $hoy2) : $result[0]["id"];

                        /*  ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
                        +++++++++++++++++++ INICIO DE INSERCION EN LA BD, UNA TRANSACCION POR CADA FACTURA +++++++++++++++++++++++
                        ++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++ */
                        $fecha_hora_emision = date("Y-m-d H:i:s", strtotime($fecha_hora_emision));
                        $cert_fechahora = date("Y-m-d H:i:s", strtotime($cert_fechahora));
                        $datos = array(
                            'id_moneda' =>  $id_moneda,
                            'id_tipo' => $id_tipodte,
                            'id_emisor' => $id_emisor,
                            'id_receptor' => $id_receptor,
                            // 'id_escenariofrase' => $id_escenario,
                            'fechahora_emision' => $fecha_hora_emision,
                            'id_certificador' => $id_certificador,
                            'no_autorizacion' => $cert_numero,
                            'serie' => $cert_serie,
                            'codigo_autorizacion' => $cert_codigoautorizacion,
                            'fechahora_certificacion' => $cert_fechahora,
                            'estado' => 1,
                            'tipo' => $tipof,
                            'origen_factura' => 2,
                            'created_at' => $hoy2,
                            'created_by' => $idusuario,
                        );

                        $id_factura = $database->insert("cv_facturas", $datos);
                        //2.1 ITEMS (SON VARIAS FILAS, IDENTIFICADOR UNICO EN LA LINEA SERIA EL NumeroLinea)
                        $ems_items = $dte_datosEmision["dte:Items"];

                        if (isset($ems_items["dte:Item"]) && is_array($ems_items["dte:Item"])) {
                            if (!isset($ems_items["dte:Item"][0])) {
                                $aux = $ems_items["dte:Item"];
                                unset($ems_items["dte:Item"]);
                                $ems_items["dte:Item"][0] = $aux;
                            }
                        } else {
                            $showmensaje = true;
                            throw new Exception("no hay items en dte:Items");
                        }

                        foreach ($ems_items["dte:Item"] as $item) {
                            $noLinea = $item["@attributes"]["NumeroLinea"];
                            $bienoserv = $item["@attributes"]["BienOServicio"];
                            $cantidad = $item["dte:Cantidad"]["#text"];
                            $descripcion = $item["dte:Descripcion"]["#text"];
                            $descuento = $item["dte:Descuento"]["#text"];
                            $otrosdescuentos = (isset($item["dte:OtrosDescuento"]["#text"])) ? $item["dte:OtrosDescuento"]["#text"] : 0;
                            $precio = $item["dte:Precio"]["#text"];
                            $precioUnitario = $item["dte:PrecioUnitario"]["#text"];
                            $total = $item["dte:Total"]["#text"];

                            $datos = array(
                                'numerolinea' =>  $noLinea,
                                'tipo' => $bienoserv,
                                'cantidad' => $cantidad,
                                'descripcion' => $descripcion,
                                'precio_unitario' => $precioUnitario,
                                'precio_parcial' => $precio,
                                'descuento' => $descuento,
                                'otros_descuentos' => $otrosdescuentos,
                                'total' => $total,
                                'id_factura' => $id_factura,
                            );

                            $id_factura_item = $database->insert("cv_factura_items", $datos);

                            //VERIFICAR SI EL EMISOR ES EXE
                            if (isset($item["dte:Impuestos"])) {
                                $impuestos = $item["dte:Impuestos"]["dte:Impuesto"];
                                if (!isset($impuestos[0])) {
                                    $aux = $impuestos;
                                    unset($impuestos);
                                    $impuestos[0] = $aux;
                                }

                                foreach ($impuestos as $impuesto) {
                                    $codigoug = $impuesto["dte:CodigoUnidadGravable"]["#text"];
                                    $nombrecorto = $impuesto["dte:NombreCorto"]["#text"];

                                    //VERIFICAR, CUANDO ES PETROLEO, EL CAMPO SE LLAMA CantidadUnidadesGravables, no es MontoGravable
                                    $montoGravable = isset($impuesto["dte:MontoGravable"]["#text"]) ? $impuesto["dte:MontoGravable"]["#text"] : (isset($impuesto["dte:CantidadUnidadesGravables"]["#text"]) ? $impuesto["dte:CantidadUnidadesGravables"]["#text"] : 0);
                                    $montoImpuesto = $impuesto["dte:MontoImpuesto"]["#text"];

                                    $strquery = "SELECT cig.id FROM cv_impuestos_tipo ci INNER JOIN cv_impuestosunidadgravable cig ON cig.id_cvimpuestostipo=ci.id
                                            WHERE descripcion=? AND cig.codigo=?";
                                    $result = $database->getAllResults($strquery, [$nombrecorto, $codigoug]);
                                    if (empty($result)) {
                                        $showmensaje = true;
                                        throw new Exception("$cert_numero Codigo de unidad gravable: $codigoug para $nombrecorto, no existe, verificar");
                                    }
                                    $id_unidad_gravable = $result[0]["id"];

                                    $datos = array(
                                        'id_impuestos_unidadgravable' =>  $id_unidad_gravable,
                                        'monto_gravable' => $montoGravable,
                                        'monto_impuesto' => $montoImpuesto,
                                        'id_factura_items' => $id_factura_item,
                                    );
                                }
                            } else {
                                $datos = array(
                                    'id_impuestos_unidadgravable' => 41,
                                    'monto_gravable' => 0,
                                    'monto_impuesto' => 0,
                                    'id_factura_items' => $id_factura_item,
                                );
                            }
                            $database->insert("cv_facturaitems_impuestos", $datos);
                        }

                        //2.3 FRASES (pueden ser varias frases)
                        $ems_frases = $dte_datosEmision["dte:Frases"];
                        if (!isset($ems_frases["dte:Frase"][0])) {
                            $aux = $ems_frases["dte:Frase"];
                            unset($ems_frases["dte:Frase"]);
                            $ems_frases["dte:Frase"][0] = $aux;
                        }
                        // echo json_encode(["sadfas", 0, $ems_frases]);
                        // $database->rollback();
                        // return;
                        foreach ($ems_frases["dte:Frase"] as $frase) {
                            $frase_tipo = $frase["@attributes"]["TipoFrase"];
                            $frase_codEscenario = $frase["@attributes"]["CodigoEscenario"];

                            $result = $database->selectColumns('cv_escenarios', ['id'], 'cod_escenario=? AND id_frases=?', [$frase_codEscenario, $frase_tipo]);
                            if (empty($result)) {
                                $showmensaje = true;
                                throw new Exception("Tipo de frase: $frase_tipo, con Codigo de escenario: $frase_codEscenario no existe, verificar");
                            }
                            $id_escenario = $result[0]["id"];
                            //INSERTAR CADA UNO DE LOS ESCENARIOS DE LA FACTURA
                            $datos = array(
                                'id_escenario' =>  $id_escenario,
                                'id_factura' => $id_factura,
                            );

                            $database->insert("cv_factura_frases", $datos);
                        }

                        //2.1 TOTALES
                        // $ems_totales = $dte_datosEmision["dte:Totales"];
                        // $granTotal = $ems_totales["dte:GranTotal"]["#text"];
                        // $impuestosTotal = $ems_totales["dte:TotalImpuestos"]["dte:TotalImpuesto"];
                        // if (!isset($impuestosTotal[0])) {
                        //     $aux = $impuestosTotal;
                        //     unset($impuestosTotal);
                        //     $impuestosTotal[0] = $aux;
                        // }

                        // foreach ($impuestosTotal as $totImpuestos) {
                        //     $nombreCorto = $totImpuestos["@attributes"]["NombreCorto"];
                        //     $totMontoImpuesto = $totImpuestos["@attributes"]["TotalMontoImpuesto"];
                        // }
                        array_push($idsinsertados, [$cert_numero, $cert_serie, $cert_codigoautorizacion, "", $id_factura]);
                    }
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
        echo json_encode([$mensaje, $status, $idsinsertados, $noinsertados]);
        break;
    case 'update_status_fact':
        $archivo = $_POST["archivo"];
        $status = $archivo[0];
        $idfact = $archivo[1];
        $showmensaje = false;
        try {
            $database->openConnection();
            $database->beginTransaction();
            $datos = array(
                'estado' =>  $status,
            );

            $database->update("cv_facturas", $datos, "id=?", [$idfact]);
            $database->commit();
            // $database->rollback();
            $mensaje = "Registro actualizado correctamente";
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
        echo json_encode([$mensaje, $status]);
        break;
    case 'emitirfel':
        /*$xml = file_get_contents('php://input');

        // Aquí puedes procesar el XML, por ejemplo, guardarlo en un archivo o enviarlo a una API
        file_put_contents('factura.xml', $xml);

        // Si necesitas enviar una respuesta al cliente
        echo "XML procesado correctamente";    */
        echo "<script>alert('¡Esta es una notificación emergente desde PHP!');</script>";
        echo "<script>console.log('Prueba de recepcion de datos');</script>";
        break;
    case 'gestion':

        $tipodte = $_POST['selectdte'];
        $emisor_nit = $_POST['emisor_nit'];
        $emisor_nombre = $_POST['emisor_nombre'];
        $emisor_email = $_POST['emisor_email'];
        $emisor_direccion = $_POST['emisor_direccion'];
        $receptor_nit = $_POST['receptor_nit'];
        $receptor_nombre = $_POST['receptor_nombre'];
        $receptor_email = $_POST['receptor_email'];
        $receptor_direccion = $_POST['receptor_direccion'];
        $productos = $_POST['productos'];  // El arreglo de productos
        $condi = $_POST['condi'];
        //VARIABLES
        $idreceptor;
        $idemisor;
        $idmoneda = 1;
        $idfact = 0;

        // Verificar si el receptor ya existe
        $sq = $conexion->prepare("SELECT COUNT(*) FROM cv_receptor WHERE id_receptor = ?");
        $sq->bind_param("s", $receptor_nit);
        $sq->execute();
        $sq->bind_result($count);
        $sq->fetch();
        $sq->close();

        if ($count > 0) {
            $response = array("Ya existe un usuario con este rol.", 0);
            $sq = $conexion->prepare("SELECT id FROM cv_receptor WHERE id_receptor = ?");
            $sq->bind_param("s", $receptor_nit);
            $sq->execute();
            $sq->bind_result($idreceptor);
            $sq->fetch();
            $sq->close();
        } else {
            $database->openConnection();
            // Inserción del recepto
            try {
                // Datos a insertar
                $receptor_data = [
                    'id_receptor' => $receptor_nit, // Valor de $receptor_nit, este puede ser null si es autoincremental
                    'correo' => $receptor_email,
                    'nombre' => $receptor_nombre,
                    'direccion' => $receptor_direccion,
                    'created_at' => $hoy2, // Fecha de creación, por ejemplo: '2024-11-19 12:00:00'
                ];

                // Insertar el receptor en la tabla 'cv_receptor' y obtener el ID del nuevo registro
                $idreceptor = $database->insert('cv_receptor', $receptor_data);

                // Mostrar el ID del receptor recién insertado
                //echo "El ID del receptor insertado es: " . $idreceptor;
            } catch (Exception $e) {
                $mensaje = "Error: " . $e;
            } finally {
                $database->closeConnection();
            }
        }

        // Obtener el ID del emisor
        $sq = $conexion->prepare("SELECT id FROM cv_emisor WHERE nit = ?");
        $sq->bind_param("s", $emisor_nit);
        $sq->execute();
        $sq->bind_result($idemisor);  // Aquí bind_result para obtener el valor del id
        if ($sq->fetch()) {
            // Si se encontró el emisor, obtenemos su ID
            // El valor de $idemisor ya está disponible
            $mensaje = $sq->fetch();
        } else {
            echo "No se encontró el Emisor.";
        }
        $sq->close();

        // Crear la factura
        $database->openConnection();
        // Inserción del recepto
        try {
            // Datos a insertar
            $receptor_data = [
                'id_tipo' => $tipodte, // Valor de $receptor_nit, este puede ser null si es autoincremental
                'id_moneda' => $idmoneda,
                'id_receptor' => $idreceptor,
                'id_certificador' => '12',
                'id_emisor' => $idemisor,
                'created_at' => $hoy2,
                'origen_factura' => '1',
            ];

            // Insertar el receptor en la tabla 'cv_receptor' y obtener el ID del nuevo registro
            $idfact = $database->insert('cv_facturas', $receptor_data);

            // Mostrar el ID del receptor recién insertado
            //echo "El ID del receptor insertado es: " . $idreceptor;
        } catch (Exception $e) {
            $mensaje = "Error: " . $e;
        } finally {
            $database->closeConnection();
        }

        // Contar el número de registros
        $numeroRegistros = count($productos);
        $i = 1;
        foreach ($productos as $indice => $producto) {
            $database->openConnection();
            try {
                $datass = [
                    'numerolinea' => $i,
                    'tipo' => $producto['bs'],
                    'cantidad' => $producto['cantidad'],
                    'descripcion' => $producto['descripcion'],
                    'precio_unitario' => $producto['precioUnitario'],
                    'descuento' => $producto['descuentos'],
                    'otros_descuentos' => $producto['otrosDescuentos'],
                    'total' => $producto['total'],
                    'impuesto' => $producto['impuestos'],
                    'id_factura' => $idfact,
                ];
                $idfactdeta = $database->insert('cv_factura_items', $datass);
                $dato2 = [
                    'id_impuestos_unidadgravable' => '1',
                    'monto_gravable' => $producto['total'],
                    'monto_impuesto' => $producto['impuestos'],
                    'id_factura_items' => $idfactdeta,
                ];
                $database->insert('cv_facturaitems_impuestos', $dato2);
            } catch (Exception $e) {
                $mensaje = "Error: " . $e;
            } finally {
                $database->closeConnection();
            }
            $i++;
        }

        certificarfel($database, $idfact);
        break;
    case 'buscardat':
        $database->openConnection();
        try {
            $query = "SELECT * FROM `cv_facturas` ORDER BY id DESC LIMIT 1";
            $params = [];
            $resultado = $database->selectNom($query, $params);
            if (!empty($resultado)) {
                $ultimoRegistro = $resultado[0];
                $response = [
                    'status' => 'success',
                    'message' => 'Datos procesados correctamente',
                    'processed_data' => [
                        'fechahora_emision' => $ultimoRegistro['fechahora_emision'],
                        'no_autorizacion' => $ultimoRegistro['no_autorizacion'],
                        'serie' => $ultimoRegistro['serie'],
                        'codigo_autorizacion' => $ultimoRegistro['codigo_autorizacion'],
                        'id' => $ultimoRegistro['id']
                    ]
                ];
                echo json_encode($response);
            }
        } catch (Exception $e) {
            $mensaje = "Error: " . $e;
        } finally {
            $database->closeConnection();
        }
        break;
    case 'consultarfel':
        $id = $_POST['id'];
        $database->openConnection();

        try {
            $factura = $database->selectById("cv_facturas", $id, $columnid = "id");
            $detallefactura = $database->selectDataID("cv_factura_items", "id_factura", $id);
            $emisor = $database->selectById("cv_emisor", $factura['id_emisor'], $columnid = "id");
            $receptor = $database->selectById("cv_receptor", $factura['id_receptor'], $columnid = "id");
            if (!empty($factura)) {
                $response = [
                    'status' => 'success', // Añadir un estado de éxito
                    'factura' => $factura,
                    'detallefactura' => $detallefactura,
                    'emisor' => $emisor,
                    'receptor' => $receptor,
                ];
            } else {
                $response = [
                    'status' => 'error', // Añadir un estado de error
                    'message' => 'No se encontró la factura.'
                ];
            }
        } catch (Exception $e) {
            $response = [
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage(),
            ];
        } finally {
            $database->closeConnection();
        }

        echo json_encode($response);

        break;
    case 'anularfel':
        $idfact = $_POST['id'];
        $motivoanulacion = $_POST['motivoanulacion'];
        $database->openConnection();
        try {
            $factura = $database->selectById('cv_facturas', $idfact);
            $emisor = $database->selectById('cv_emisor', $factura['id_emisor']);
            $receptor = $database->selectById('cv_receptor', $factura['id_receptor']);
        } catch (Exception $e) {
            $mensaje = "Error: " . $e;
        } finally {
            $database->closeConnection();
        }

        $fecha = new DateTime($factura['created_at']);

        // Ajustar la hora a medianoche
        $fecha->setTime(0, 0, 0);

        // Establecer la zona horaria deseada (en este caso, UTC-6)
        $zonaHoraria = new DateTimeZone('-06:00');
        $fecha->setTimezone($zonaHoraria);

        // Convertir al formato requerido
        $fechaFormateada = $fecha->format('Y-m-d\TH:i:sP');

        $date = new DateTime($fechaFormateada);
        $timezone = new DateTimeZone('-06:00');
        $date->setTimezone($timezone);
        $formato_iso = $date->format('Y-m-d\TH:i:sP');

        $es2 = new DateTime($hoy2);
        $timezone2 = new DateTimeZone('-06:00');
        $es2->setTimezone($timezone2);
        $formato_iso2 = $date->format('Y-m-d\TH:i:sP');


        //NIT PARA PRODUCCION -  $emisor['nit']
        //NIT PARA PRUEBAS 11201065K
        $xml = "<dte:GTAnulacionDocumento xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\" xmlns:dte=\"http://www.sat.gob.gt/dte/fel/0.1.0\" 
        xmlns:n1=\"http://www.altova.com/samplexml/other-namespace\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" Version=\"0.1\" 
        xsi:schemaLocation=\"http://www.sat.gob.gt/dte/fel/0.1.0 C:\\Users\\User\\Desktop\\FEL\\Esquemas\\GT_AnulacionDocumento-0.1.0.xsd\">\n  
        <dte:SAT>\n    <dte:AnulacionDTE ID=\"DatosCertificados\">\n <dte:DatosGenerales FechaEmisionDocumentoAnular=\"" . $formato_iso .
            "\" FechaHoraAnulacion=\"" . $formato_iso2 . "\" ID=\"DatosAnulacion\" IDReceptor=\"" .
            $receptor['id_receptor'] . "\" MotivoAnulacion=\"" . $motivoanulacion . "\" NITEmisor=\"11201065K\" NumeroDocumentoAAnular=\"" .
            $factura['codigo_autorizacion'] . "\"></dte:DatosGenerales>\n    </dte:AnulacionDTE>\n  </dte:SAT>\n</dte:GTAnulacionDocumento>\n";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => "https://certificador.feel.com.gt/fel/procesounificado/transaccion/v2/xml",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $xml,
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/xml",
                "LlaveApi:" . $_ENV['LLAVAPI'],
                "LlaveFirma:" . $_ENV['LLAVFIRMA'],
                "UsuarioApi:" . $_ENV['USUARIOAPI'],
                "UsuarioFirma:" . $_ENV['USUARIOFIRMA']
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            $data = json_decode($response, true); // true convierte el JSON a un array
            $estado = $data['resultado'];
            $problema = $data['descripcion_alertas_infile'];
            $cantida = $data['cantidad_errores'];
            //ACTUALIZAR FACTURA
            if ($cantida == 0) {
                $datas = [
                    'estado' => '2',
                    'deleted_at' => $hoy2,
                    'deleted_by' =>  $_SESSION['id'],
                ];
                $condition = 'id = ?';
                $database->openConnection();
                try {
                    $database->update('cv_facturas', $datas, "id=?", [$idfact]);
                } catch (Exception $e) {
                    $mensaje = "Error: " . $e->getMessage();
                } finally {
                    $database->closeConnection();
                }
            }

            $resp = [
                'status' => 'success',
                'message' => 'Factura Anulada',
                'processed_data' => [
                    'estado' => $estado,
                    'problema' => $problema,
                    'cantidad' => $cantida,
                    'datos' => $data,
                ]
            ];
            // Enviar respuesta en formato JSON
            echo json_encode($resp);
        }
        break;

    case 'create_proveedor':
        /**
         * ['<?= $csrf->getTokenName() ?>','correo','nit','nombre_comercial','nombre','direccion'],['id_afiliacion_iva']
         */
        if (!($csrf->validateToken($_POST['inputs'][0], false))) {
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
        try {
            $datosproveedor = [
                'correo' => $_POST['inputs'][1],
                'nit' => $_POST['inputs'][2],
                'nombre_comercial' => $_POST['inputs'][3],
                'nombre' => $_POST['inputs'][4],
                'direccion' => $_POST['inputs'][5],
                'id_afiliacion_iva' => $_POST['selects'][0],
            ];


            $rules = [
                'correo' => 'optional|email|max:100',
                'nit' => 'required',
                'nombre_comercial' => 'required|min_length:3|max_length:200',
                'nombre' => 'required|max_length:200',
            ];

            $validator = Validator::make($datosproveedor, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            // $decryptedID = $secureID->decrypt($encryptedID);
            $database->openConnection();

            $verify_unique = $database->selectColumns('cv_emisor', ['id'], 'nit=? AND estado=1', [$datosproveedor['nit']]);
            if (!empty($verify_unique)) {
                $showmensaje = true;
                throw new Exception("El NIT ya se encuentra registrado en otro proveedor activo.");
            }

            $database->beginTransaction();

            $cv_emisor = [
                'id_afiliacion_iva' => ($datosproveedor['id_afiliacion_iva'] == '') ? null : $datosproveedor['id_afiliacion_iva'],
                'cod_establecimiento' => NULL,
                'correo' => $datosproveedor['correo'],
                'nit' => $datosproveedor['nit'],
                'nombre_comercial' => $datosproveedor['nombre_comercial'],
                'nombre' => $datosproveedor['nombre'],
                'direccion' => $datosproveedor['direccion'],
                'codigo_postal' => $datosproveedor['codigo_postal'] ?? '',
                'municipio' => $datosproveedor['municipio'] ?? '',
                'departamento' => $datosproveedor['departamento'] ?? '',
                'pais' => $datosproveedor['pais'] ?? '',
                'estado' => 1,
                'created_at' => $hoy2,
                'created_by' => $_SESSION['id']
            ];

            $database->insert("cv_emisor", $cv_emisor);


            $database->commit();
            $mensaje = "Proveedor creado correctamente.";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;
    case 'update_proveedor':
        /**
         * ['<?= $csrf->getTokenName() ?>','correo','nit','nombre_comercial','nombre','direccion'],['id_afiliacion_iva']
         */
        if (!($csrf->validateToken($_POST['inputs'][0], false))) {
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
        try {
            $datosproveedor = [
                'correo' => $_POST['inputs'][1],
                'nit' => $_POST['inputs'][2],
                'nombre_comercial' => $_POST['inputs'][3],
                'nombre' => $_POST['inputs'][4],
                'direccion' => $_POST['inputs'][5],
                'id_afiliacion_iva' => $_POST['selects'][0],
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
            ];


            $rules = [
                'correo' => 'optional|email|max:100',
                'nit' => 'required',
                'nombre_comercial' => 'required|min_length:3|max_length:200',
                'nombre' => 'required|min_length:3|max_length:200',
                'id' => 'required|numeric|min:1|exists:cv_emisor,id',

            ];

            $validator = Validator::make($datosproveedor, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }


            $database->openConnection();

            $verify_unique = $database->selectColumns('cv_emisor', ['id'], 'nit=? AND estado=1 AND id!=?', [$datosproveedor['nit'], $datosproveedor['id']]);
            if (!empty($verify_unique)) {
                $showmensaje = true;
                throw new Exception("El NIT ya se encuentra registrado en otro proveedor activo.");
            }

            $database->beginTransaction();

            $cv_emisor = [
                'id_afiliacion_iva' => ($datosproveedor['id_afiliacion_iva'] == '') ? null : $datosproveedor['id_afiliacion_iva'],
                'cod_establecimiento' => NULL,
                'correo' => $datosproveedor['correo'],
                'nit' => $datosproveedor['nit'],
                'nombre_comercial' => $datosproveedor['nombre_comercial'],
                'nombre' => $datosproveedor['nombre'],
                'direccion' => $datosproveedor['direccion'],
                'codigo_postal' => $datosproveedor['codigo_postal'] ?? '',
                'municipio' => $datosproveedor['municipio'] ?? '',
                'departamento' => $datosproveedor['departamento'] ?? '',
                'pais' => $datosproveedor['pais'] ?? '',
                'updated_at' => $hoy2,
                'updated_by' => $_SESSION['id']
            ];

            $database->update("cv_emisor", $cv_emisor, "id=?", [$datosproveedor['id']]);


            $database->commit();
            $mensaje = "Proveedor actualizado correctamente.";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;
    case 'delete_proveedor':
        /**
         * ['<?= $csrf->getTokenName() ?>'],[]
         */
        if (!($csrf->validateToken($_POST['inputs'][0], false))) {
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
        try {
            $datosproveedor = [
                'id' => $secureID->decrypt($_POST['archivo'][0] ?? ''),
            ];

            $rules = [
                'id' => 'required|numeric|min:1|exists:cv_emisor,id',
            ];

            $validator = Validator::make($datosproveedor, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                $showmensaje = true;
                throw new Exception($firstError);
            }

            $database->openConnection();

            $database->beginTransaction();

            $cv_emisor = [
                'estado' => 0,
                'deleted_at' => $hoy2,
                'deleted_by' => $_SESSION['id']
            ];

            $database->update("cv_emisor", $cv_emisor, "id=?", [$datosproveedor['id']]);

            $database->commit();
            $mensaje = "Proveedor eliminado correctamente.";
            $status = 1;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        $opResult = array($mensaje, $status);
        echo json_encode($opResult);
        break;
}
function addcertificador($database, $dte_certificacion, $idusuario, $hoy2)
{
    $cert_nit = $dte_certificacion["dte:NITCertificador"]["#text"];
    $cert_nombre = $dte_certificacion["dte:NombreCertificador"]["#text"];

    try {
        $datos = array(
            'nit' => $cert_nit,
            'nombre' => $cert_nombre,
            'estado' => 1,
            'created_at' => $hoy2,
            'created_by' => $idusuario,
        );

        $id_certificador = $database->insert("cv_certificador", $datos);
    } catch (Exception $e) {
        throw new Exception("Error en la creacion del certificador $cert_nit " . $e->getMessage());
    } finally {
    }
    return $id_certificador;
}

function addemisor($database, $ems_emisor, $idusuario, $hoy2)
{
    $emisor_afiliacion = $ems_emisor["@attributes"]["AfiliacionIVA"];
    $emisor_codigo = $ems_emisor["@attributes"]["CodigoEstablecimiento"];
    $emisor_correo = $ems_emisor["@attributes"]["CorreoEmisor"];
    $emisor_nit = $ems_emisor["@attributes"]["NITEmisor"];
    $emisor_nombreComercial = $ems_emisor["@attributes"]["NombreComercial"];
    $emisor_nombreEmisor = $ems_emisor["@attributes"]["NombreEmisor"];

    $emisor_codPostal = isset($ems_emisor["dte:DireccionEmisor"]["dte:CodigoPostal"]["#text"]) ? $ems_emisor["dte:DireccionEmisor"]["dte:CodigoPostal"]["#text"] : "";
    $emisor_departamento = isset($ems_emisor["dte:DireccionEmisor"]["dte:Departamento"]["#text"]) ? $ems_emisor["dte:DireccionEmisor"]["dte:Departamento"]["#text"] : "";
    $emisor_municipio = isset($ems_emisor["dte:DireccionEmisor"]["dte:Municipio"]["#text"]) ? $ems_emisor["dte:DireccionEmisor"]["dte:Municipio"]["#text"] : "";
    $emisor_direccion = isset($ems_emisor["dte:DireccionEmisor"]["dte:Direccion"]["#text"]) ? $ems_emisor["dte:DireccionEmisor"]["dte:Direccion"]["#text"] : "";
    $emisor_pais = isset($ems_emisor["dte:DireccionEmisor"]["dte:Pais"]["#text"]) ? $ems_emisor["dte:DireccionEmisor"]["dte:Pais"]["#text"] : "";
    try {
        $result = $database->selectColumns('cv_tipo_afiliacion_iva', ['id'], 'abreviacion=?', [$emisor_afiliacion]);
        if (empty($result)) {
            throw new Exception("Tipo de afiliacion al IVA con Codigo: $emisor_afiliacion no existe, verificar");
        }
        $id_afiliacion = $result[0]["id"];

        $datos = array(
            'id_afiliacion_iva' =>  $id_afiliacion,
            'cod_establecimiento' => $emisor_codigo,
            'correo' => $emisor_correo,
            'nit' => $emisor_nit,
            'nombre_comercial' => $emisor_nombreComercial,
            'nombre' => $emisor_nombreEmisor,
            'direccion' => $emisor_direccion,
            'codigo_postal' => $emisor_codPostal,
            'municipio' => $emisor_municipio,
            'departamento' => $emisor_departamento,
            'pais' => $emisor_pais,
            'estado' => 1,
            'created_at' => $hoy2,
            'created_by' => $idusuario,
        );

        $id_emisor = $database->insert("cv_emisor", $datos);
    } catch (Exception $e) {
        throw new Exception("Error en la creacion del emisor $emisor_nit " . $e->getMessage());
    } finally {
    }
    return $id_emisor;
}

function addreceptor($database, $ems_receptor, $idusuario, $hoy2)
{
    $receptor_id = $ems_receptor["@attributes"]["IDReceptor"];
    $receptor_correo = $ems_receptor["@attributes"]["CorreoReceptor"];
    $receptor_nombre = $ems_receptor["@attributes"]["NombreReceptor"];

    $receptor_codPostal = (isset($ems_receptor["dte:DireccionReceptor"]["dte:CodigoPostal"]["#text"])) ? $ems_receptor["dte:DireccionReceptor"]["dte:CodigoPostal"]["#text"] : "";
    $receptor_departamento = (isset($ems_receptor["dte:DireccionReceptor"]["dte:Departamento"]["#text"])) ? $ems_receptor["dte:DireccionReceptor"]["dte:Departamento"]["#text"] : "";
    $receptor_municipio = (isset($ems_receptor["dte:DireccionReceptor"]["dte:Municipio"]["#text"])) ? $ems_receptor["dte:DireccionReceptor"]["dte:Municipio"]["#text"] : "";
    $receptor_direccion = (isset($ems_receptor["dte:DireccionReceptor"]["dte:Direccion"]["#text"])) ? $ems_receptor["dte:DireccionReceptor"]["dte:Direccion"]["#text"] : "";
    $receptor_pais = (isset($ems_receptor["dte:DireccionReceptor"]["dte:Pais"]["#text"])) ? $ems_receptor["dte:DireccionReceptor"]["dte:Pais"]["#text"] : "";

    try {
        $datos = array(
            'id_receptor' =>  $receptor_id,
            'correo' => $receptor_correo,
            'nombre' => $receptor_nombre,
            'direccion' => $receptor_direccion,
            'codigo_postal' => $receptor_codPostal,
            'municipio' => $receptor_municipio,
            'departamento' => $receptor_departamento,
            'pais' => $receptor_pais,
            'estado' => 1,
            'created_at' => $hoy2,
            'created_by' => $idusuario,
        );

        $id_receptor = $database->insert("cv_receptor", $datos);
    } catch (Exception $e) {
        throw new Exception("Error en la creacion del receptor $receptor_id " . $e->getMessage());
    } finally {
    }
    return $id_receptor;
}
function getidmoney($database, $abr, $db_name_general)
{
    try {
        $query = "SELECT id FROM " . $db_name_general . ".tb_monedas WHERE abr=?";
        $result = $database->getAllResults($query, [$abr]);
        if (empty($result)) {
            throw new Exception("Tipo de moneda con Codigo: $abr no existe, verificar");
        }
        $id_moneda = $result[0]["id"];
    } catch (Exception $e) {
        throw new Exception("Error en la consulta de la moneda $abr " . $e->getMessage());
    } finally {
    }
    return $id_moneda;
}

function certificarfel($database, $idfact)
{

    //CONSULTAS
    $database->openConnection();
    try {
        $factura = $database->selectById('cv_facturas', $idfact);
        $emisor = $database->selectById('cv_emisor', $factura['id_emisor']);
        $receptor = $database->selectById('cv_receptor', $factura['id_receptor']);
        $detallefactura = $database->selectDataID('cv_factura_items', 'id_factura', $idfact);
    } catch (Exception $e) {
        $mensaje = "Error: " . $e;
    } finally {
        $database->closeConnection();
    }

    $fecha = new DateTime($factura['created_at']);

    // Ajustar la hora a medianoche
    $fecha->setTime(0, 0, 0);

    // Establecer la zona horaria deseada (en este caso, UTC-6)
    $zonaHoraria = new DateTimeZone('-06:00');
    $fecha->setTimezone($zonaHoraria);

    // Convertir al formato requerido
    $fechaFormateada = $fecha->format('Y-m-d\TH:i:sP');

    $agencia = $_SESSION['agencia'];
    $codnew = $agencia . $factura['id'];
    $imptotal = 0;
    $sumtotal = 0;
    //NIT PARA PRODUCCION -  $emisor['nit']
    //NIT PARA PRUEBAS 11201065K
    $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n" .
        "<dte:GTDocumento xmlns:ds=\"http://www.w3.org/2000/09/xmldsig#\" xmlns:dte=\"http://www.sat.gob.gt/dte/fel/0.2.0\" " .
        "xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" Version=\"0.1\" xsi:schemaLocation=\"http://www.sat.gob.gt/dte/fel/0.2.0\">\n" .
        "    <dte:SAT ClaseDocumento=\"dte\">\n" .
        "        <dte:DTE ID=\"DatosCertificados\">\n" .
        "            <dte:DatosEmision ID=\"DatosEmision\">\n" .
        "                <dte:DatosGenerales CodigoMoneda=\"GTQ\" FechaHoraEmision=\"" . $fechaFormateada . "\" Tipo=\"FACT\"></dte:DatosGenerales>\n" .
        "                <dte:Emisor AfiliacionIVA=\"GEN\" CodigoEstablecimiento=\"1\" NITEmisor=\"11201065K\" " .
        "NombreComercial=\"" . $emisor['nombre_comercial'] . "\" NombreEmisor=\"" . $emisor['nombre'] . "\">\n" .
        "                    <dte:DireccionEmisor>\n" .
        "                        <dte:Direccion>" . $emisor['direccion'] . "</dte:Direccion>\n" .
        "                        <dte:CodigoPostal>" . $emisor['codigo_postal'] . "</dte:CodigoPostal>\n" .
        "                        <dte:Municipio>" . $emisor['municipio'] . "</dte:Municipio>\n" .
        "                        <dte:Departamento>" . $emisor['departamento'] . "</dte:Departamento>\n" .
        "                        <dte:Pais>" . $emisor['pais'] . "</dte:Pais>\n" .
        "                    </dte:DireccionEmisor>\n" .
        "                </dte:Emisor>\n" .
        "                <dte:Receptor IDReceptor=\"" . $receptor['id_receptor'] . "\" NombreReceptor=\"" . $receptor['nombre'] . "\">\n" .
        "                    <dte:DireccionReceptor>\n" .
        "                        <dte:Direccion>" . $receptor['direccion'] . "</dte:Direccion>\n" .
        "                        <dte:CodigoPostal>16001</dte:CodigoPostal>\n" .
        "                        <dte:Municipio>" . $receptor['municipio'] . "</dte:Municipio>\n" .
        "                        <dte:Departamento>" . $receptor['departamento'] . "</dte:Departamento>\n" .
        "                        <dte:Pais>GT</dte:Pais>\n" .
        "                    </dte:DireccionReceptor>\n" .
        "                </dte:Receptor>\n" .
        "                <dte:Frases>\n" .
        "                    <dte:Frase CodigoEscenario=\"1\" TipoFrase=\"1\"></dte:Frase>\n" .
        "                </dte:Frases>\n" .
        "                <dte:Items>\n";

    // Generar los items dentro de un bucle
    $itemsXml = "";
    $imptotal = 0;
    $sumtotal = 0;

    foreach ($detallefactura as $dett) {
        $temp = $dett['total'] - $dett['impuesto'];
        $imptotal += $dett['impuesto'];
        $sumtotal += $dett['total'];

        $itemsXml .= "               <dte:Item BienOServicio=\"" . $dett['tipo'] . "\" NumeroLinea=\"" . $dett['numerolinea'] . "\">\n" .
            "                        <dte:Cantidad>" . $dett['cantidad'] . "</dte:Cantidad>\n" .
            "                        <dte:UnidadMedida>UNI</dte:UnidadMedida>\n" .
            "                        <dte:Descripcion>" . $dett['descripcion'] . "</dte:Descripcion>\n" .
            "                        <dte:PrecioUnitario>" . $dett['precio_unitario'] . "</dte:PrecioUnitario>\n" .
            "                        <dte:Precio>" . $dett['total'] . "</dte:Precio>\n" .
            "                        <dte:Descuento>" . $dett['descuento'] . "</dte:Descuento>\n" .
            "                        <dte:Impuestos>\n" .
            "                            <dte:Impuesto>\n" .
            "                                <dte:NombreCorto>IVA</dte:NombreCorto>\n" .
            "                                <dte:CodigoUnidadGravable>1</dte:CodigoUnidadGravable>\n" .
            "                                <dte:MontoGravable>" . $temp . "</dte:MontoGravable>\n" .
            "                                <dte:MontoImpuesto>" . $dett['impuesto'] . "</dte:MontoImpuesto>\n" .
            "                            </dte:Impuesto>\n" .
            "                        </dte:Impuestos>\n" .
            "                        <dte:Total>" . $dett['total'] . "</dte:Total>\n" .
            "                    </dte:Item>\n";
    }

    $xml .= $itemsXml;

    $xml .= "                </dte:Items>\n" .
        "                <dte:Totales>\n" .
        "                    <dte:TotalImpuestos>\n" .
        "                        <dte:TotalImpuesto NombreCorto=\"IVA\" TotalMontoImpuesto=\"" . $imptotal . "\"></dte:TotalImpuesto>\n" .
        "                    </dte:TotalImpuestos>\n" .
        "                    <dte:GranTotal>" . $sumtotal . "</dte:GranTotal>\n" .
        "                </dte:Totales>\n" .
        "            </dte:DatosEmision>\n" .
        "        </dte:DTE>\n" .
        "    </dte:SAT>\n" .
        "</dte:GTDocumento>";



    $curl = curl_init();

    curl_setopt_array($curl, [
        CURLOPT_URL => "https://certificador.feel.com.gt/fel/procesounificado/transaccion/v2/xml",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => [
            "Content-Type: application/xml",
            "LlaveApi:" . $_ENV['LLAVAPI'],
            "LlaveFirma:" . $_ENV['LLAVFIRMA'],
            "UsuarioApi:" . $_ENV['USUARIOAPI'],
            "UsuarioFirma:" . $_ENV['USUARIOFIRMA'],
            "identificador: " . $codnew
        ],
    ]);

    $response = curl_exec($curl);
    $err = curl_error($curl);

    curl_close($curl);

    if ($err) {
        echo "cURL Error #:" . $err;
    } else {
        $data = json_decode($response, true); // true convierte el JSON a un array
        $fecha = $data['fecha'];
        $descripcion = $data['descripcion'];
        $uuid = $data['uuid'];
        $serie = $data['serie'];
        $numero = $data['numero'];
        $xml_certificado = $data['xml_certificado'];
        $estado = $data['resultado'];
        $problema = $data['descripcion_alertas_infile'];
        //ACTUALIZAR FACTURA

        $sq = "SELECT COUNT(*) as total FROM cv_facturas WHERE codigo_autorizacion = :codigo";

        $params = ['codigo' => $uuid];


        try {
            $database->openConnection(1);
            $respuesta = $database->getSingleResult($sq, $params);
            $coun = $respuesta['total'];
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage();
        } finally {
            $database->closeConnection();
        }

        if ($estado == true) {
            if ($coun > 0) {
                $datas = [
                    'estado' => '3',
                    'created_by' => $_SESSION['id']
                ];
                $condition = 'id = ?';
                $database->openConnection();
                try {
                    $database->update('cv_facturas', $datas, "id=?", [$idfact]);
                } catch (Exception $e) {
                    $mensaje = "Error: " . $e->getMessage();
                } finally {
                    $database->closeConnection();
                }
                $resp = [
                    'status' => 'alert',
                    'message' => 'Factura Duplicada',
                    'processed_data' => [
                        'fechahora_emision' => $fecha,
                        'no_autorizacion' => $numero,
                        'serie' =>  $serie,
                        'codigo_autorizacion' => $uuid,
                        'fechahora_certificacion' => $fecha,
                        'estado' => $estado,
                        'problema' => $problema,
                    ]
                ];
            } else {
                $datas = [
                    'fechahora_emision' => $fecha,
                    'no_autorizacion' => $numero,
                    'serie' =>  $serie,
                    'codigo_autorizacion' => $uuid,
                    'fechahora_certificacion' => $fecha,
                    'created_by' => $_SESSION['id']
                ];
                $condition = 'id = ?';
                $database->openConnection();
                try {
                    $database->update('cv_facturas', $datas, "id=?", [$idfact]);
                } catch (Exception $e) {
                    $mensaje = "Error: " . $e->getMessage();
                } finally {
                    $database->closeConnection();
                }
                $resp = [
                    'status' => 'success',
                    'message' => 'Factura Emitida',
                    'processed_data' => [
                        'fechahora_emision' => $fecha,
                        'no_autorizacion' => $numero,
                        'serie' =>  $serie,
                        'codigo_autorizacion' => $uuid,
                        'fechahora_certificacion' => $fecha,
                        'estado' => $estado,
                        'problema' => $problema,
                    ]
                ];
            }
        } else {
            $datas = [
                'estado' => '3',
                'created_by' => $_SESSION['id']
            ];
            $condition = 'id = ?';
            $database->openConnection();
            try {
                $database->update('cv_facturas', $datas, "id=?", [$idfact]);
            } catch (Exception $e) {
                $mensaje = "Error: " . $e->getMessage();
            } finally {
                $database->closeConnection();
            }
            $resp = [
                'status' => 'error',
                'message' => 'Factura No Emitida',
                'processed_data' => [
                    'fechahora_emision' => $fecha,
                    'no_autorizacion' => $numero,
                    'serie' =>  $serie,
                    'codigo_autorizacion' => $uuid,
                    'fechahora_certificacion' => $fecha,
                    'estado' => $estado,
                    'problema' => $problema,
                ]
            ];
        }


        // Enviar respuesta en formato JSON
        echo json_encode($resp);
    }
}
