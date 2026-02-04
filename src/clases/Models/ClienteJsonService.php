<?php

namespace App\Generic\Models;

// use App\DatabaseAdapter;
use Exception;
use Micro\Models\Municipio;

class ClienteJsonService
{
    private $db;
    private $codigoClientePrincipal;

    private $departamentosCatalogo = [];

    public function __construct($db, $codigoClientePrincipal = null)
    {
        $this->db = $db;
        $this->codigoClientePrincipal = $codigoClientePrincipal;
        $this->db->openConnection(1); // Abre conexión principal
        $this->loadDepartamentosCatalogo();
    }

    private function loadDepartamentosCatalogo()
    {
        if (empty($this->departamentosCatalogo)) {
            $departamentos = $this->db->getAllResults("SELECT id, cod_sib FROM tb_departamentos WHERE id_pais=4");
            foreach ($departamentos as $depa) {
                $this->departamentosCatalogo[$depa['id']] = $depa['cod_sib'];
            }
        }
    }

    public function getTitulares($idcliente)
    {
        $result = [];
        $showmensaje = false;
        try {
            $titular = $this->db->selectColumns('tb_cliente', ['actu_Propio', 'repre_calidad', 'fecha_alta', 'fecha_mod'], 'idcod_cliente=?', [$idcliente]);
            if (empty($titular)) {
                $showmensaje = true;
                throw new Exception('No se encontró titular');
            }
            /**
             * ASIGNACION DE TIPO DE ACTUACION, 
             * C cliente o sea actua en nombre propio
             * R representante
             */
            $result['tipoActuacion'] = ($titular[0]['actu_Propio'] == '2') ? 'R' : 'C';
            if ($result['tipoActuacion'] == 'R') {
                $result['calidadActua'] = $titular[0]['repre_calidad'];
            }

            $datosAgencia = $this->db->selectColumns('tb_agencia', ['pais', 'departamento', 'municipio'], 'id_agencia=?', [$_SESSION['id_agencia']]);
            if (empty($datosAgencia)) {
                $showmensaje = true;
                throw new Exception('No se encontró datos de agencia');
            }
            $datosAgencia = $datosAgencia[0];

            $result['lugar'] = $this->getLugar($datosAgencia['departamento'], $datosAgencia['municipio'], $datosAgencia['pais']);

            /**
             * VALIDACION DE FECHA, TOMAR PRIMERO FECHA ALTA, COMO SEGUNDA OPCION FECHA MOD, Y DE ULTIMO LA FECHA DE HOY
             */
            $fecha = $titular[0]['fecha_alta'];
            if (empty($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
                $fecha = $titular[0]['fecha_mod'];
                if (empty($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || !strtotime($fecha)) {
                    $fecha = date('Y-m-d');
                }
            }
            $result['fecha'] = str_replace('-', '', $fecha);

            // Cliente
            $result['cliente'] = $this->getDatosPersonales($idcliente);

            /**
             * DATOS DEL REPRESENTANTE
             */
            if ($result['tipoActuacion'] == 'R') {
                // $result['representante'] = $this->getDatosPersonales($titular[0]['id_representante']);
                //llenar los datos cuando se tenga
            }

            $result['infoEconomica'] = [$this->getInfoEconomica($idcliente)];
            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
            // $status = 0;
            // return [0, 'Error al obtener titulares: ' . $e->getMessage(), []];
        }
    }

    private function getDatosPersonales($idcliente)
    {
        $result = [];
        $showmensaje = false;

        $datosClientes = [
            'primer_last',
            'segundo_last',
            'casada_last',
            'primer_name',
            'segundo_name',
            'tercer_name',
            'date_birth',
            'nacionalidad',
            'otra_nacion',
            'pais_nacio',
            'depa_nacio',
            'id_muni_nacio',
            'genero',
            'estado_civil',
            'profesion',
            'type_doc',
            'no_identifica',
            'no_tributaria',
            'tel_no1',
            'tel_no2',
            'email',
            'Direccion',
            'depa_reside',
            'id_muni_reside',
            'zona',
            'PEP',
            'CPE'
        ];
        try {
            $titular = $this->db->selectColumns('tb_cliente', $datosClientes, 'idcod_cliente=?', [$idcliente]);
            if (empty($titular)) {
                $showmensaje = true;
                throw new Exception('No se encontró la información del titular');
            }
            $titular = $titular[0];

            $titular['muni_nacio'] = Municipio::obtenerCodigo($titular['id_muni_nacio'] ?? 0);
            $titular['muni_reside'] = Municipio::obtenerCodigo($titular['id_muni_reside'] ?? 0);

            /**
             * PARIENTE PEP
             */
            $adicionalesCliente = $this->db->selectColumns(
                'tb_cliente_atributo',
                ['id_atributo', 'valor'],
                'id_cliente=? AND id_atributo IN (11,12,13)',
                [$idcliente]
            );

            $result['primerApellido'] = $titular['primer_last'];
            $result['segundoApellido'] = $titular['segundo_last'];
            $result['apellidoCasada'] = $titular['casada_last'];
            $result['primerNombre'] = $titular['primer_name'];
            $result['segundoNombre'] = $titular['segundo_name'];
            $result['otrosNombres'] = $titular['tercer_name'];
            $result['fechaNacimiento'] = str_replace('-', '', $titular['date_birth']);
            // Validar nacionalidad: debe ser de 2 letras, si no, poner 'GT'
            $nacionalidad = strtoupper($titular['nacionalidad']);
            if (!preg_match('/^[A-Z]{2}$/', $nacionalidad)) {
                $nacionalidad = 'GT';
            }
            $result['nacionalidades'] = [$nacionalidad];

            // otraNacion es opcional y debe ser de 2 letras si existe, y no debe ser igual a la primera nacionalidad
            if (
                !empty($titular['otra_nacion']) &&
                preg_match('/^[A-Z]{2}$/', strtoupper($titular['otra_nacion'])) &&
                strtoupper($titular['otra_nacion']) !== $nacionalidad
            ) {
                $result['nacionalidades'][] = strtoupper($titular['otra_nacion']);
            }

            $result['nacimiento'] = $this->getLugar(
                $titular['depa_nacio'],
                $titular['muni_nacio'],
                $titular['pais_nacio']
            );

            if ($result['nacimiento']['pais'] !== 'GT') {
                /**
                 * CUANDO NO ES GUATEMALTECO, LA CONDICION MIGRATORIA ES OBLIGATORIA, 
                 */
                // Obtener el valor del atributo 13 para condición migratoria
                $condicionMigratoria = null;
                if (!empty($adicionalesCliente)) {
                    foreach ($adicionalesCliente as $adicional) {
                        if ($adicional['id_atributo'] == 13) {
                            $condicionMigratoria = (int)$adicional['valor'];
                            break;
                        }
                    }
                }
                $result['condicionMigratoria'] = $condicionMigratoria ?? 7;
            }

            if (isset($result['condicionMigratoria']) && $result['condicionMigratoria'] === 8) {
                /**
                 * SI LA CONDICION MIGRATORIA ES 8, ES OBLIGATORIO EL CAMPO otraCondicionMigratoria,
                 * llenarla con la informacion que corresponde cuando se tenga
                 */
                $result['otraCondicionMigratoria'] = ' ';
            }

            // Solo pueden ser M o F, si hubiera algo diferente a esto, que se ponga M por defecto
            $genero = strtoupper($titular['genero']);
            if ($genero !== 'M' && $genero !== 'F') {
                $genero = 'M';
            }
            $result['sexo'] = $genero;

            /**
             * ESTADO CIVIL SOLO PUEDEN SER S O C
             * DEFAULT S
             */

            $estadoCivil = strtoupper($titular['estado_civil']);
            // Eliminar posibles "(A)" y espacios
            $estadoCivil = preg_replace('/\(A\)/', '', $estadoCivil);
            $estadoCivil = trim($estadoCivil);

            if ($estadoCivil === 'CASADO' || $estadoCivil === 'CASADA' || $estadoCivil === 'C') {
                $result['estadoCivil'] = 'C';
            } elseif ($estadoCivil === 'SOLTERO' || $estadoCivil === 'SOLTERA' || $estadoCivil === 'S') {
                $result['estadoCivil'] = 'S';
            } else {
                $result['estadoCivil'] = 'S';
            }

            // Profesion
            $result['profesionOficio'] = strtoupper(trim($titular['profesion'] ?? ''));

            // Tipo de documento de identificación: solo D (DPI) o P (Pasaporte), por defecto D
            $tipoDoc = strtoupper(trim($titular['type_doc']));
            if ($tipoDoc === 'DPI' || $tipoDoc === 'D') {
                $result['tipoDocumentoIdentificacion'] = 'D';
            } elseif ($tipoDoc === 'PASAPORTE' || $tipoDoc === 'P') {
                $result['tipoDocumentoIdentificacion'] = 'P';
            } else {
                $result['tipoDocumentoIdentificacion'] = 'D';
            }

            // No identifica
            $result['numeroDocumentoIdentificacion'] = strtoupper(trim($titular['no_identifica']));

            if ($result['tipoDocumentoIdentificacion'] === 'P') {
                // Emisión del pasaporte, si no hay, poner vacío
                $result['emisionPasaporte'] = ''; // Aqui se pone el pais
            } else {
                $result['emisionPasaporte'] = '';
            }

            /**
             * Identifica el Número de Identificación Tributaria sin guion. Llave Opcional. 
             * Validación: cuando el valor de “nacimiento.pais” sea igual a “GT”, se validar el NIT respecto a la normativa de Guatemala.
             */

            if ($result['nacimiento']['pais'] == 'GT') {
                $result['nit'] = str_replace('-', '', strtoupper(trim($titular['no_tributaria'])));
            }

            /**
             * telefonos, arreglo numerico,separados por una coma ejemplo [1111111,11222212]
             */

            $result['telefonos'] = [];
            if (!empty($titular['tel_no1'])) {
                $telefono1 = strtoupper(trim($titular['tel_no1']));
                $telefono1 = str_replace('-', '', $telefono1);
                $result['telefonos'][] = $telefono1;
            }
            if (!empty($titular['tel_no2'])) {
                $telefono2 = strtoupper(trim($titular['tel_no2']));
                $telefono2 = str_replace('-', '', $telefono2);
                $result['telefonos'][] = $telefono2;
            }

            // Email
            if (filter_var(trim($titular['email']), FILTER_VALIDATE_EMAIL)) {
                $result['email'] = strtolower(trim($titular['email']));
            } else {
                $result['email'] = '';
            }

            // Aldea donde reside
            $result['direccionResidencia'] = strtoupper(trim($titular['Direccion']));

            /**
             * RESIDENCIA
             */
            $result['residencia'] = $this->getLugar(
                $titular['depa_reside'],
                $titular['muni_reside']
            );

            if ($result['residencia']['pais'] == 'GT') {
                $result['zona'] = strtoupper(trim($titular['zona'] ?? ''));
            }

            /**
             * PEP, S si, N no
             */

            // PEP: puede venir como SI, NO, S, N, s, n, etc. Normalizar a 'S' o 'N'
            $pep = strtoupper(trim($titular['PEP']));
            if ($pep === 'SI' || $pep === 'S') {
                $result['pep'] = 'S';
            } elseif ($pep === 'NO' || $pep === 'N') {
                $result['pep'] = 'N';
            } else {
                $result['pep'] = 'N'; // Valor por defecto
            }

            /**
             * DATOS PEP, Información sobre la condición de PEP. 
             * Llave Obligatoria cuando el valor de “pep” sea “S”.
             */

            if ($result['pep'] === 'S') {
                $result['datosPep'] = $this->getDatosPep($idcliente);
            }

            /**
             * PARIENTE PEP, S si, N no
             * Llave obligatoria, como no se tiene esa informacion todavia, se dejara en N, alimentar con datos de la bd despues
             */

            // Buscar en $adicionalesCliente el atributo 11, por defecto es N si no existe
            $result['parientePep'] = 'N';
            if (!empty($adicionalesCliente)) {
                foreach ($adicionalesCliente as $adicional) {
                    if ($adicional['id_atributo'] == 11) {
                        $valor = strtoupper(trim($adicional['valor'] ?? ''));
                        $valor = strtolower($valor);
                        $result['parientePep'] = ($valor === 'si' || $valor === 's') ? 'S' : 'N';
                        break;
                    }
                }
            }

            /**
             * datos Pariente PEP, Llave Obligatoria cuando el valor de “parientePep” sea “S”.
             */
            if ($result['parientePep'] === 'S') {
                $result['datosParientePep'] = $this->getDatosParientePep($idcliente);
            }

            /**
             * ASOCIADO PEP, Información sobre si la persona es asociado cercano de una PEP. 
             * Seleccionar una de las dos opciones S) Sí N) No,  Llave Obligatoria
             */
            $result['asociadoPep'] = 'N';
            if (!empty($adicionalesCliente)) {
                foreach ($adicionalesCliente as $adicional) {
                    if ($adicional['id_atributo'] == 12) {
                        $valor = strtoupper(trim($adicional['valor'] ?? ''));
                        $valor = strtolower($valor);
                        $result['asociadoPep'] = ($valor === 'si' || $valor === 's') ? 'S' : 'N';
                        break;
                    }
                }
            }

            /**
             * DATOS ASOCIADO PEP, Información sobre el asociado cercano PEP. 
             * Llave Obligatoria cuando el valor de “asociadoPep” sea “S”.
             */
            if ($result['asociadoPep'] === 'S') {
                $result['datosAsociadoPep'] = $this->getDatosAsociadoPep($idcliente);
            }

            /**
             * CPE, La persona de quien se está obteniendo la información es CPE. 
             * Seleccionar una de las dos opciones S) Sí N) No NA) No Aplica 
             * Llave Obligatoria según normativa vigente.
             */

            $cpe = strtoupper(trim($titular['CPE']));
            if ($cpe === 'SI' || $cpe === 'S') {
                $result['cpe'] = 'S';
            } elseif ($cpe === 'NO' || $cpe === 'N') {
                $result['cpe'] = 'N';
            } else {
                $result['cpe'] = 'NA';
            }

            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function getDatosPep($codigoCliente)
    {
        $result = [];
        $showmensaje = false;
        try {
            /**
             * TODOS ESTOS DATOS, HAY QUE CONSULTARLOS A LA BD, POR EL MOMENTO SE LLENARAN DE VACIOS, 
             */
            $datosPep = $this->db->selectColumns('cli_datos_pep', [
                'id',
                'entidad',
                'puesto',
                'paisEntidad',
                'otroOrigen'
            ], 'id_cliente=?', [$codigoCliente]);

            if (empty($datosPep)) {
                $showmensaje = true;
                throw new Exception('No se encontró la información de PEP');
            }

            if (!empty($datosPep)) {
                foreach ($datosPep as $value) {
                    $origenesRiqueza = $this->db->selectColumns('cli_origenes_riqueza', [
                        'id_origen'
                    ], 'id_pep=?', [$value['id']]);
                    $result[] = [
                        'entidad' => $value['entidad'],
                        'puestoDesempenia' => $value['puesto'],
                        'paisEntidad' => $value['paisEntidad'],
                        'origenRiqueza' => array_column($origenesRiqueza, 'id_origen'),
                        'otroOrigenRiqueza' => $value['otroOrigen']
                    ];
                }
                return $result;
            }

            $result[] = [
                'entidad' => '',
                'puestoDesempenia' => '',
                'paisEntidad' => 'GT',
                'origenRiqueza' => [],
                'otroOrigenRiqueza' => ''
            ];
            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function getDatosParientePep($codigoCliente)
    {
        $result = [];
        $showmensaje = false;
        try {
            /**
             * TODOS ESTOS DATOS, HAY QUE CONSULTARLOS A LA BD, POR EL MOMENTO SE LLENARAN DE VACIOS, 
             */
            $datosPariente = $this->db->getAllResults(
                "SELECT comp.id,par.cod_sib,par.descripcion FROM cli_complementos_pep comp
                INNER JOIN tb_parentescos par ON par.id=comp.parentesco
                WHERE comp.id_cliente=? AND comp.estado=1",
                [$codigoCliente]
            );

            if (!empty($datosPariente)) {
                foreach ($datosPariente as $pariente) {
                    $item = [];
                    $item['parentesco'] = $pariente['cod_sib'];
                    $item['otroParentesco'] = ($item['parentesco'] == 'OTRO') ? $pariente['descripcion'] : '';
                    // Complemento para el Pariente PEP
                    $item['complementoPAPEP'] = $this->getComplementoPAPEP($pariente['id']);
                    $result[] = $item;
                }
            }

            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function getComplementoPAPEP($idComplemento = NULL)
    {
        $result = [];
        $showmensaje = false;
        try {
            /**
             * TODOS ESTOS DATOS, HAY QUE CONSULTARLOS A LA BD, POR EL MOMENTO SE LLENARAN DE VACIOS, 
             */

            $datosPEP = $this->db->selectColumns('cli_complementos_pep', [
                'primerApellido',
                'segundoApellido',
                'apellidoCasada',
                'primerNombre',
                'segundoNombre',
                'otrosNombres',
                'sexo',
                'condicion',
                'entidad',
                'puesto',
                'pais'
            ], 'id=?', [$idComplemento]);

            if (empty($datosPEP)) {
                $showmensaje = true;
                throw new Exception('No se encontró la información del complemento PEP');
            }

            $result['primerApellido'] = $datosPEP[0]['primerApellido'];
            $result['segundoApellido'] = $datosPEP[0]['segundoApellido'];
            $result['apellidoCasada'] = $datosPEP[0]['apellidoCasada'];
            $result['primerNombre'] = $datosPEP[0]['primerNombre'];
            $result['segundoNombre'] = $datosPEP[0]['segundoNombre'];
            $result['otrosNombres'] = $datosPEP[0]['otrosNombres'];
            $result['sexo'] = $datosPEP[0]['sexo'];
            $result['condicion'] = $datosPEP[0]['condicion'];
            $result['entidad'] = $datosPEP[0]['entidad'];
            $result['puestoDesempenia'] = $datosPEP[0]['puesto'];
            $result['paisEntidad'] = $datosPEP[0]['pais'];


            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function getDatosAsociadoPep($codigoCliente)
    {
        $result = [];
        $showmensaje = false;
        try {
            /**
             * TODOS ESTOS DATOS, HAY QUE CONSULTARLOS A LA BD, POR EL MOMENTO SE LLENARAN DE VACIOS,
             */
            $datosAsociado = $this->db->getAllResults(
                "SELECT mot.codigo,comp.detalleOtro,comp.id FROM cli_complementos_pep comp
                    INNER JOIN tb_motivos_pep mot ON mot.id=comp.motivoAsociacion
                    WHERE comp.estado=1 AND comp.id_cliente=?",
                [$codigoCliente]
            );

            if (!empty($datosAsociado)) {
                foreach ($datosAsociado as $asociado) {
                    $item = [];
                    $item['motivoAsociacion'] = $asociado['codigo'];
                    $item['otroMotivoAsociacion'] = ($item['motivoAsociacion'] == 'O') ? $asociado['detalleOtro'] : '';
                    // Complemento para el ASOCIADO PEP
                    $item['complementoPAPEP'] = $this->getComplementoPAPEP($asociado['id']);
                    $result[] = $item;
                }
            }

            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    public function getInfoEconomica($idcliente)
    {
        $result = [];
        $showmensaje = false;
        try {

            $ingresosCliente = $this->db->selectColumns('tb_ingresos', ['created_at', 'fecha_sys', 'sueldo_base', 'Tipo_ingreso', 'nombre_empresa', 'detalle_ingreso'], 'id_cliente=?', [$idcliente]);
            if (empty($ingresosCliente)) {
                $showmensaje = true;
                throw new Exception('No se encontró información económica del cliente');
            }

            $adicionalesCliente = $this->db->getAllResults(
                "SELECT id_atributo,act.descripcion FROM tb_cliente_atributo atr 
                    INNER JOIN sat_actividades act ON act.id=atr.valor
                    WHERE atr.id_atributo=10 AND atr.id_cliente=?",
                [$idcliente]
            );

            /**
             * Indicar si la información económica corresponde al inicio de la relación de negocios o a una actualización. 
             * De acuerdo al siguiente catálogo: I) Perfil inicial A) Actualización de perfil Llave Obligatoria.
             * NOTA DE CAMBIO: como no se tiene esa informacion, por el momento lo tomaremos como I, cuando se agregue el campo de control, cambiarlo
             */

            // Validar si created_at es igual a fecha_sys (created_at es datetime, fecha_sys es date)
            if (!empty($ingresosCliente[0]['created_at']) && !empty($ingresosCliente[0]['fecha_sys'])) {
                $fechaCreated = date('Y-m-d', strtotime($ingresosCliente[0]['created_at']));
                $fechaSys = $ingresosCliente[0]['fecha_sys'];
                $result['establecimientoInformacion'] = ($fechaCreated === $fechaSys) ? 'I' : 'A';
            } else {
                $result['establecimientoInformacion'] = 'I';
            }

            /**
             * FECHA Fecha en la que se ingresa o en la que se actualiza la información. 
             * Llave Obligatoria, formato YYYYMMDD
             */

            $result['fecha'] = !empty($ingresosCliente[0]['fecha_sys']) ? date('Ymd', strtotime($ingresosCliente[0]['fecha_sys'])) : date('Ymd');

            /**
             * ACTIVIDAD ECONOMICA SEGUN EL RTU, OPCIONAL
             * esta informacion se ingresa por cliente, atributo 10 en tb_cliente_atributo
             */

            $result['actividadEconomicaRTU'] = '';
            if (!empty($adicionalesCliente)) {
                foreach ($adicionalesCliente as $adicional) {
                    if ($adicional['id_atributo'] == 10 && !empty($adicional['descripcion'])) {
                        $result['actividadEconomicaRTU'] = $adicional['descripcion'];
                        break;
                    }
                }
            }

            /**
             * MONTOS INGRESOS, Información de los ingresos del cliente. Llave obligatoria según diccionario actual.
             * Validación: haber registrado al menos un ingreso.
             */

            // montosIngresos debe ser un arreglo de ingresos individuales con su respectiva moneda
            $result['montosIngresos'] = [];
            foreach ($ingresosCliente as $ingreso) {
                $result['montosIngresos'][] = [
                    'moneda' => 'GTQ', // por el momento se deja en quetzales, cambiar cuando se tenga la información
                    'monto' => $ingreso['sueldo_base']
                ];
            }

            /**
             * MONTOS EGRESOS, PRIMERA COMPROBACION, INFORMACION DE EGRESOS DEL CLIENTE en cli_egresos. 
             * SEGUNDA COMPROBACION EN TB_CLI_BALANCE, SI NO HAY REGISTROS EN LA PRIMERA.
             * Llave obligatoria según diccionario actual.
             */

            $egresosCliente = $this->db->selectColumns('cli_egresos', ['nombre', 'monto'], 'id_cliente=? AND estado="1"', [$idcliente]);
            if (!empty($egresosCliente)) {
                // montosEgresos debe ser un arreglo de egresos individuales con su respectiva moneda
                $result['montosEgresos'] = [];
                foreach ($egresosCliente as $egreso) {
                    $result['montosEgresos'][] = [
                        // 'concepto' => $egreso['nombre'], // No se usa concepto por el momento
                        'moneda' => 'GTQ',
                        'monto' => $egreso['monto']
                    ];
                }
                // return $result;
            } else {
                $egresosCliente = $this->db->selectColumns('tb_cli_balance', ['mercaderia', 'negocio', 'pago_creditos'], 'ccodcli=? AND estado=1', [$idcliente], 'created_at DESC LIMIT 1');
                if (empty($egresosCliente)) {
                    $showmensaje = true;
                    throw new Exception('No se encontró información de egresos del cliente');
                }
                /**
                 * MONTO EGRESOS, Información de los egresos del cliente. Llave obligatoria según diccionario actual.
                 * Validación: haber registrado al menos un egreso.
                 */

                // montosEgresos debe ser un arreglo de egresos individuales con su respectiva moneda
                $result['montosEgresos'] = [];
                foreach ($egresosCliente as $egreso) {
                    if (isset($egreso['mercaderia']) && $egreso['mercaderia'] > 0) {
                        $result['montosEgresos'][] = [
                            // 'concepto' => 'mercaderia',
                            'moneda' => 'GTQ',
                            'monto' => $egreso['mercaderia']
                        ];
                    }
                    if (isset($egreso['negocio']) && $egreso['negocio'] > 0) {
                        $result['montosEgresos'][] = [
                            // 'concepto' => 'negocio',
                            'moneda' => 'GTQ',
                            'monto' => $egreso['negocio']
                        ];
                    }
                    if (isset($egreso['pago_creditos']) && $egreso['pago_creditos'] > 0) {
                        $result['montosEgresos'][] = [
                            // 'concepto' => 'pago_creditos',
                            'moneda' => 'GTQ',
                            'monto' => $egreso['pago_creditos']
                        ];
                    }
                }
            }


            /**
             * NEGOCIO PROPIO, Se describe la información que corresponde, cuando la fuente de la cual se originan los ingresos es negocio propio.
             * Llave Obligatoria: Al menos debe ingresar un negocio propio, relación dependencia u otros ingresos
             */

            // Buscar en ingresosCliente los que tengan Tipo_ingreso igual a 1 y nombre_empresa no vacío
            $negociosPropios = array_filter($ingresosCliente, fn($ingreso) => $ingreso['Tipo_ingreso'] == 1 && !empty($ingreso['nombre_empresa']));
            $result['negocioPropio'] = [];
            foreach ($negociosPropios as $negocio) {
                $result['negocioPropio'][] = [
                    'nombreComercial' => $negocio['nombre_empresa']
                ];
            }

            /**
             * RELACION DEPENDENCIA, Se describe la información que corresponde, cuando la fuente de la cual se originan los ingresos es relación de dependencia.
             * Llave Obligatoria: Al menos debe ingresar un negocio propio, relación dependencia u otros ingresos
             */

            $dependencias = array_filter($ingresosCliente, fn($ingreso) => $ingreso['Tipo_ingreso'] == 2 && !empty($ingreso['nombre_empresa']));
            $result['relacionDependencia'] = [];
            foreach ($dependencias as $dependencia) {
                $result['relacionDependencia'][] = [
                    'nombreEmpleador' => $dependencia['nombre_empresa']
                ];
            }

            /**
             * otrosIngresos, Se describe la información que corresponde, cuando la fuente de la cual se originan los ingresos es por otros ingresos. 
             * Llave Obligatoria
             */

            $otrosIngresos = array_filter($ingresosCliente, fn($ingreso) => $ingreso['Tipo_ingreso'] == 3);
            if ($otrosIngresos) {
                $result['otrosIngresos'] = $this->getOtrosIngresos($otrosIngresos);
            }

            /**
             * PROPOSITO RC, Descripción del propósito de la relación comercial con la persona obligada.
             * Llave Obligatoria.
             */

            $datosCliente = $this->db->selectColumns('tb_cliente', ['Rel_insti'], 'idcod_cliente=?', [$idcliente]);
            if (empty($datosCliente)) {
                $showmensaje = true;
                throw new Exception('No se encontró información de propósito de relación comercial');
            }
            $result['propositoRC'] = $datosCliente[0]['Rel_insti'] ?? '';

            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function getOtrosIngresos($datos)
    {
        /**
         * Obtiene la información de otros ingresos.
         * Tipo de la otra fuente de ingresos, de acuerdo con el siguiente catálogo: 
         * P) Actividades profesionales
         * M) Manutención
         * R) Rentas
         * J) Jubilación
         * O) Otra fuente de ingresos
         * Llave Obligatoria.
         */
        $result = [];
        foreach ($datos as $ingreso) {
            // Normalizar el nombre de la fuente de ingreso
            $fuente = strtoupper(trim($ingreso['nombre_empresa']));
            $fuente = str_replace(
                ['Á', 'É', 'Í', 'Ó', 'Ú', 'Ñ'],
                ['A', 'E', 'I', 'O', 'U', 'N'],
                $fuente
            );

            // Mapear al catálogo
            switch ($fuente) {
                case 'ACTIVIDADES PROFESIONALES':
                case 'PROFESIONAL':
                case 'PROFESIONALES':
                    $tipo = 'P';
                    break;
                case 'MANUTENCION':
                case 'MANUTENCION FAMILIAR':
                    $tipo = 'M';
                    break;
                case 'RENTAS':
                case 'RENTA':
                    $tipo = 'R';
                    break;
                case 'JUBILACION':
                case 'JUBILACIÓN':
                    $tipo = 'J';
                    break;
                default:
                    $tipo = 'O';
                    break;
            }

            $result[] = [
                'tipoOtrasFuentesIngresos' => $tipo,
                'otrasFuentesIngreso' => $ingreso['detalle_ingreso'] ?? '',
            ];
        }
        return $result;
    }

    public function getProductos($idcliente)
    {
        $result = [];
        $showmensaje = false;
        try {

            /**
             * LO DE APORTACIONES PREGUNTAR TODAVIA
             */
            $productosAportaciones = $this->db->getAllResults(
                "SELECT cta.ccodaport,cta.fecha_apertura,tip.nombre nombreProducto, ofi.pais,ofi.departamento,ofi.municipio, 
                        cli.short_name, calcular_saldo_apr_tipcuenta(cta.ccodaport, CURDATE()) AS valor
                                FROM aprcta cta
                                INNER JOIN aprtip tip ON tip.ccodtip=SUBSTR(cta.ccodaport,7,2) 
                                INNER JOIN tb_agencia ofi ON ofi.cod_agenc=tip.ccodage
                                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                                WHERE ccodcli=?;",
                [$idcliente]
            );

            if (!empty($productosAportaciones)) {
                foreach ($productosAportaciones as $producto) {
                    $prod = [];
                    $prod['lugar'] = $this->getLugar($producto['departamento'], $producto['municipio'], $producto['pais']);
                    $prod['fecha'] = !empty($producto['fecha_apertura']) ? date('Ymd', strtotime($producto['fecha_apertura'])) : date('Ymd');
                    $prod['tipo'] = 'APORTACION'; // AHO para ahorro
                    $prod['nombreDescripcion'] = $producto['nombreProducto'];
                    $prod['identificador'] = $producto['ccodaport'];
                    $prod['nombreContratante'] = $producto['short_name'];
                    $prod['moneda'] = $producto['moneda'] ?? 'GTQ'; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UNA MONEDA POR PRODUCTO, CAMBIARLO
                    $prod['valor'] = $producto['valor'] ?? 0; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UN VALOR POR PRODUCTO, CAMBIARLO

                    /**
                     * FONDOS, Indicar si los fondos para adquirir el producto o servicio son propios o de un tercero. 
                     * Seleccionar una de las dos opciones: 
                     * 1) Fondos propios 2) Fondos de un tercero 3) Fondos propios y de un tercero 
                     * Llave obligatoria.
                     */
                    $prod['fondosPropios'] = $producto['fondo'] ?? 1; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UN VALOR POR PRODUCTO, CAMBIARLO
                    $result[] = $prod;
                }
            }

            /**
             * LO DE AHORROS PREGUNTAR TODAVIA
             */
            $productosAhorro = $this->db->getAllResults(
                "SELECT cta.ccodaho,cta.fecha_apertura,tip.nombre nombreProducto, ofi.pais,ofi.departamento,ofi.municipio, 
                        cli.short_name, calcular_saldo_aho_tipcuenta(cta.ccodaho, CURDATE()) AS valor
                                FROM ahomcta cta
                                INNER JOIN ahomtip tip ON tip.ccodtip=SUBSTR(cta.ccodaho,7,2) 
                                INNER JOIN tb_agencia ofi ON ofi.cod_agenc=tip.ccodofi
                                INNER JOIN tb_cliente cli ON cli.idcod_cliente=cta.ccodcli
                                WHERE ccodcli=?;",
                [$idcliente]
            );

            if (!empty($productosAhorro)) {
                foreach ($productosAhorro as $producto) {
                    $prod = [];
                    $prod['lugar'] = $this->getLugar($producto['departamento'], $producto['municipio'], $producto['pais']);
                    $prod['fecha'] = !empty($producto['fecha_apertura']) ? date('Ymd', strtotime($producto['fecha_apertura'])) : date('Ymd');
                    $prod['tipo'] = 'AHORRO'; // AHO para ahorro
                    $prod['nombreDescripcion'] = $producto['nombreProducto'];
                    $prod['identificador'] = $producto['ccodaho'];
                    $prod['nombreContratante'] = $producto['short_name'];
                    $prod['moneda'] = $producto['moneda'] ?? 'GTQ'; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UNA MONEDA POR PRODUCTO, CAMBIARLO
                    $prod['valor'] = $producto['valor'] ?? 0; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UN VALOR POR PRODUCTO, CAMBIARLO

                    /**
                     * FONDOS, Indicar si los fondos para adquirir el producto o servicio son propios o de un tercero. 
                     * Seleccionar una de las dos opciones: 
                     * 1) Fondos propios 2) Fondos de un tercero 3) Fondos propios y de un tercero 
                     * Llave obligatoria.
                     */
                    $prod['fondosPropios'] = $producto['fondo'] ?? 1; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UN VALOR POR PRODUCTO, CAMBIARLO
                    $result[] = $prod;
                }
            }

            $productosCreditos = $this->db->getAllResults(
                "SELECT cre.CCODCTA,cre.DFecDsbls,prd.nombre nombreProducto, ofi.pais, ofi.departamento, ofi.municipio, cli.short_name, cre.NCapDes
                    FROM cremcre_meta cre
                    INNER JOIN cre_productos prd ON prd.id=cre.CCODPRD
                    INNER JOIN tb_agencia ofi ON ofi.cod_agenc=cre.CODAgencia
                    INNER JOIN tb_cliente cli ON cli.idcod_cliente=cre.CodCli
                    WHERE cre.CodCli=? AND cre.Cestado='F';",
                [$idcliente]
            );

            if (!empty($productosCreditos)) {
                foreach ($productosCreditos as $producto) {
                    $prod = [];
                    $prod['lugar'] = $this->getLugar($producto['departamento'], $producto['municipio'], $producto['pais']);
                    $prod['fecha'] = !empty($producto['DFecDsbls']) ? date('Ymd', strtotime($producto['DFecDsbls'])) : date('Ymd');
                    $prod['tipo'] = 'CREDITO'; // AHO para ahorro
                    $prod['nombreDescripcion'] = $producto['nombreProducto'];
                    $prod['identificador'] = $producto['CCODCTA'];
                    $prod['nombreContrata'] = $producto['short_name'];
                    $prod['moneda'] = $producto['moneda'] ?? 'GTQ'; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UNA MONEDA POR PRODUCTO, CAMBIARLO
                    $prod['valor'] = $producto['NCapDes'] ?? 0;

                    /**
                     * FONDOS, Indicar si los fondos para adquirir el producto o servicio son propios o de un tercero. 
                     * Seleccionar una de las dos opciones: 
                     * 1) Fondos propios 2) Fondos de un tercero 3) Fondos propios y de un tercero 
                     * Llave obligatoria.
                     */
                    $prod['fondosPropios'] = $producto['fondo'] ?? 1; // POR EL MOMENTO LO DEJE ASI, CUANDO SE DEFINA UN VALOR POR PRODUCTO, CAMBIARLO

                    /**
                     * HAY OTROS CAMPOS, REVISAR SI APLICAN PARA ESTOS TIPOS DE PRODUCTOS
                     */
                    $result[] = $prod;
                }
            }

            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    private function getBeneficiariosAhorros($ccodaho)
    {
        $result = [];
        $showmensaje = false;
        try {
            $beneficiarios = $this->db->selectColumns("ahomben", ['nombre', 'dpi', 'porcentaje'], 'ccodaho=?', [$ccodaho]);

            if (!empty($beneficiarios)) {
                foreach ($beneficiarios as $beneficiario) {
                    $result[] = [
                        'nombre' => $beneficiario['nombre'],
                        'tipo' => $beneficiario['tipo'],
                        'porcentaje' => $beneficiario['porcentaje']
                    ];
                }
            }
            return $result;
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 1);
            $codigoDevuelto = ($showmensaje) ? 1 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    public function generarJsonCliente($idcliente)
    {
        try {
            $data = [
                'titulares' => [],
                'productos' => [],
                'beneficiarios' => []
            ];

            $data['titulares'] = [$this->getTitulares($idcliente)];

            $data['productos'] = $this->getProductos($idcliente);

            // $perfil = $this->getPerfilEconomico($idcliente, $db_name_general);
            // $data['perfilEconomico'] = $perfil;
            /**
             * al parecer los beneficiarios, solo aplica para aseguradoras
             */

            return [
                'status' => 1,
                'msj' => 'JSON Generado correctamente',
                'data' => $data,
                'file' => 'JSON_' . $idcliente . '_' . date('d-m-Y')
            ];
        } catch (Exception $e) {
            return [
                'status' => 0,
                'msj' => 'Error al generar JSON: ' . $e->getMessage(),
                'data' => [],
                'file' => ''
            ];
        }
    }

    private function getLugar($departamento, $municipio, $pais = 'GT')
    {
        $result = [];

        // Validar país: debe ser de 2 letras, si no, poner 'GT'
        if (preg_match('/^[A-Z]{2}$/', strtoupper($pais))) {
            $result['pais'] = strtoupper($pais);
        } else {
            $result['pais'] = 'GT';
        }

        // Si el país no es GT, departamento y municipio no se llenan
        if ($result['pais'] !== 'GT') {
            $result['departamento'] = '';
            $result['municipio'] = '';
        } else {
            // // Departamento: debe ser numérico, 2 dígitos, 0 a la izquierda
            // $departamento = preg_replace('/\D/', '', $departamento);
            // $result['departamento'] = str_pad(substr($departamento, 0, 2), 2, '0', STR_PAD_LEFT);

            $result['departamento'] = $this->departamentosCatalogo[$departamento] ?? '';

            // Municipio: debe ser numérico, 4 dígitos, 0 a la izquierda
            $municipio = preg_replace('/\D/', '', $municipio);
            $result['municipio'] = str_pad(substr($municipio, 0, 4), 4, '0', STR_PAD_LEFT);
        }

        return $result;
    }
}
