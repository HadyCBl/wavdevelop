<?php

namespace Micro\Controllers\Seguros;

use Exception;
use Micro\Controllers\BaseController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
use Micro\Models\Seguros\Cuenta;
use Micro\Models\Seguros\Renovacion;
use Illuminate\Database\Capsule\Manager as DB;
use Micro\Helpers\Beneq;
use Micro\Models\Agencia;
use Micro\Models\Bancos\Cuenta as BancosCuenta;
use Micro\Models\Contabilidad\Diario;

class RenovacionesController extends BaseController
{

    public function index(): void
    {
        try {
            // $this->database->openConnection();

            $html = $this->renderView('indicadores/seguros/renovaciones', [
                'currentCuenta' => null,
                'csrf_token' => CSRFProtection::getTokenValue()
            ]);

            $this->view($html); // Retornar JSON con HTML
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
            // $this->database->closeConnection();
        }
    }

    public function store(): void
    {
        try {
            // Log::debug("post data", $this->all());
            /**
             * {"csrf_token":"d0649163ca69a07486b06101a82fb70e94aa3799ccc812fcd8a96bc1fb278c53","id_cuenta":"1",
             * "numdoc":"433434","fecha":"2025-12-25","monto":"233.00","fecha_inicio":"2025-12-25",
             * "fecha_fin":"2026-12-25","forma_pago":"banco","cuenta_banco":"banco_2",
             * "numero_boleta":"243","fecha_boleta":"2025-12-25"}
             */


            $data = [
                'id_cuenta' => $this->post('id_cuenta') ?? null,
                'numdoc' => $this->post('numdoc') ?? null,
                'fecha' => $this->post('fecha') ?? null,
                'monto' => $this->post('monto') ?? null,
                'fecha_inicio' => $this->post('fecha_inicio') ?? null,
                'fecha_fin' => $this->post('fecha_fin') ?? null,
                'forma_pago' => $this->post('forma_pago') ?? null,
                'cuenta_banco' => $this->post('cuenta_banco') ?? null,
                'numero_boleta' => $this->post('numero_boleta') ?? null,
                'fecha_boleta' => $this->post('fecha_boleta') ?? null,
            ];

            $rules = [
                'id_cuenta' => 'required|integer|exists:aux_cuentas,id',
                'numdoc' => 'required|string|max:50',
                'fecha' => 'required|date',
                'monto' => 'required|numeric|min:0',
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date',
                'forma_pago' => 'required|in:efectivo,banco',
            ];

            if ($data['forma_pago'] === 'banco') {
                $rules['cuenta_banco'] = 'required|integer|exists:ctb_bancos,id';
                $rules['numero_boleta'] = 'required|string|max:50';
                $rules['fecha_boleta'] = 'required|date';
            }

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            DB::beginTransaction();

            $tempCuenta = new Renovacion(['id_cuenta' => $data['id_cuenta']]);

            $renovacion =  Renovacion::create([
                'id_cuenta' => $data['id_cuenta'],
                'fecha' => $data['fecha'],
                'fecha_inicio' => $data['fecha_inicio'],
                'fecha_fin' => $data['fecha_fin'],
                'monto' => $data['monto'],
                'numero' => $tempCuenta->nextNumero,
                'numdoc' => $data['numdoc'],
                'formaPago' => $data['forma_pago'],
                'id_ctbbanco' => $data['forma_pago'] === 'banco' ? $data['cuenta_banco'] : null,
                'banco_numdoc' => $data['forma_pago'] === 'banco' ? $data['numero_boleta'] : null,
                'banco_fecha' => $data['forma_pago'] === 'banco' ? $data['fecha_boleta'] : null,
                'estado' => 'vigente',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->getUserId()
            ]);

            if($data['forma_pago'] === 'banco') {
                // actualizar cuenta bancaria (depositar)
                $bancoCuenta = BancosCuenta::find($data['cuenta_banco']);
                if (!$bancoCuenta) {
                    throw new SoftException("No se encontró la cuenta bancaria seleccionada.");
                }

                $idDebeNomenclatura = $bancoCuenta->nomenclatura->id;
            } else {
                $agencia = Agencia::find($this->getAgencyId());
                if (!$agencia) {
                    throw new SoftException("No se encontró la agencia del usuario.");
                }
                $idDebeNomenclatura = $agencia->nomenclaturaCaja->id;
            }

            /**
             * registro en la diario
             */
            $diario = Diario::create([
                'numcom' => Beneq::getNumcomEloquent($this->getUserId(), $this->getAgencyId(), $data['fecha']), 
                'id_ctb_tipopoliza' => 2, // ingresos
                'id_tb_moneda' => 1, // quetzales
                'numdoc' => $data['numdoc'],
                'glosa' => 'Renovación de póliza No. ' . $renovacion->numero . ' - Cuenta No. ' . $data['id_cuenta'],
                'fecdoc' => $data['fecha'],
                'feccnt' => $data['fecha'],
                'cod_aux' => 'AUX_' . $data['id_cuenta'],
                'id_tb_usu' => $this->getUserId(),
                'karely' => 'AUXR_' . $renovacion->id,
                'id_agencia' => $this->getAgencyId(),
                'fecmod' => date('Y-m-d H:i:s'),
                'editable' => 1,
                // 'estado' => 1,
                'created_by' => $this->getUserId(),
            ]);

            $diario->movimientos()->createMany([
                [
                    'id_fuente_fondo' => 1, 
                    'id_ctb_nomenclatura' =>  $idDebeNomenclatura,
                    'debe' => $data['monto'],
                    'haber' => 0,
                ],
                [
                    'id_fuente_fondo' => 1, 
                    'id_ctb_nomenclatura' =>  $tempCuenta->cuenta->servicio->nomenclatura->id,
                    'debe' => 0,
                    'haber' => $data['monto'],
                ],
            ]);

            DB::commit();

            // Respuesta con auto-recarga de vista
            $this->successWithReload(
                'Renovación registrada exitosamente',
                '/api/seguros/renovaciones/index',
                '#cuadro',
                ['id' => $renovacion->id]

            );
        } catch (SoftException $se) {
            DB::rollBack();
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            DB::rollBack();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
            // $this->database->closeConnection();
        }
    }

    public function show($id): void
    {
        try {

            // DB::enableQueryLog();
            // $currentClient = Renovacion::find($id);
            $currentClient = Cuenta::with(
                'cliente:idcod_cliente,short_name,no_identifica',
                'servicio:id,nombre,costo',
                'renovaciones:id,id_cuenta,fecha,fecha_fin,fecha_inicio,monto,numero'
            )
                ->where('id', $id)
                ->select([
                    'id',
                    'id_cliente',
                    'id_servicio',
                    'fecha_inicio',
                    'observaciones',
                    'estado'
                ])
                ->first();

            // Log::debug('Consultas ejecutadas', DB::getQueryLog());
            if (!$currentClient) {
                throw new SoftException("No se encontró la informacion de la renovación solicitada.");
            }



            // Log::debug("Current Cuenta", [Servicio::all()]);
            Log::debug("Current Cuenta", [$currentClient]);

            // $this->database->openConnection();

            $cuentasBancos = BancosCuenta::with(
                'banco:id,nombre',
            )
                ->select(['id', 'id_banco', 'numcuenta'])
                ->get();

            $html = $this->renderView('indicadores/seguros/renovaciones', [
                'cuenta' => $currentClient ?? null,
                'cuentasBancos' => $cuentasBancos,
                'csrf_token' => CSRFProtection::getTokenValue()
            ]);

            $this->view($html);
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
            // $this->database->closeConnection();
        }
    }

    // public function edit($id): void
    // {
    //     try {
    //         $this->database->openConnection();

    //         $currentClient = $this->database->selectColumns(
    //             "tb_cliente",
    //             ["idcod_cliente", "short_name", "no_identifica"],
    //             "idcod_cliente= ? AND estado=1",
    //             [$id]
    //         );

    //         if (empty($currentClient)) {
    //             throw new SoftException("No se encontró la informacion del cliente, verifique que esté activo.");
    //         }

    //         $cuentas = $this->database->getAllResults(
    //             "SELECT ac.id, ac.fecha_inicio, ac.observaciones, ac.estado,
    //                     aser.nombre AS servicio_nombre, aser.costo AS servicio_costo
    //              FROM aux_cuentas ac
    //              JOIN aux_servicios aser ON ac.id_servicio = aser.id
    //              WHERE ac.id_cliente = ? AND ac.estado IN ('vigente','cerrada')
    //              ORDER BY ac.fecha_inicio DESC",
    //             [$id]
    //         );

    //         $serviciosExistentes = $this->database->selectColumns(
    //             "aux_servicios",
    //             ["id", "nombre", "descripcion", "costo"],
    //             'estado="1"'
    //         );

    //         $html = $this->renderView('indicadores/seguros/cuentas', [
    //             'services' => $serviciosExistentes,
    //             'currentClient' => $currentClient[0] ?? null,
    //             'cuentas' => $cuentas,
    //             'csrf_token' => CSRFProtection::getTokenValue()
    //         ]);

    //         $this->view($html);
    //     } catch (SoftException $se) {
    //         $this->error("Advertencia: " . $se->getMessage());
    //     } catch (Exception $e) {
    //         $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    //         $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
    //     } finally {
    //         $this->database->closeConnection();
    //     }
    // }

    // public function update(): void
    // {
    //     try {
    //         $data = [
    //             'id' => $this->input('id') ?? null,
    //             'nombre' => $this->input('nombre') ?? null,
    //             'descripcion' => $this->input('descripcion') ?? null,
    //             'costo' => $this->input('costo') ?? null,
    //             'id_nomenclatura' => $this->input('cuenta_contable') ?? null,
    //         ];

    //         $rules = [
    //             'id' => 'required|integer|exists:aux_servicios,id',
    //             'nombre' => 'required|string|max:100',
    //             'descripcion' => 'required|string|max:255',
    //             'costo' => 'required|numeric|min:0',
    //             'id_nomenclatura' => 'required|integer|min:1|exists:ctb_nomenclatura,id',
    //         ];

    //         // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

    //         $validator = Validator::make($data, $rules);
    //         if ($validator->fails()) {
    //             $firstError = $validator->firstOnErrors();
    //             throw new SoftException($firstError);
    //         }

    //         $this->database->openConnection();

    //         // Actualizar servicio en la base de datos
    //         $this->database->update(
    //             'aux_servicios',
    //             [
    //                 'nombre' => $data['nombre'],
    //                 'descripcion' => $data['descripcion'],
    //                 'costo' => $data['costo'],
    //                 'id_nomenclatura' => $data['id_nomenclatura'],
    //                 'updated_at' => date('Y-m-d H:i:s'),
    //                 // 'updated_by' => Auth::get('id')
    //                 'updated_by' => $this->getUserId()
    //             ],
    //             'id = ?',
    //             [$data['id']]
    //         );

    //         // Respuesta con auto-recarga de vista
    //         $this->successWithReload(
    //             'Servicio actualizado exitosamente',
    //             '/api/seguros/servicios/index',
    //             '#cuadro'
    //         );
    //     } catch (SoftException $se) {
    //         $this->error("Advertencia: " . $se->getMessage());
    //     } catch (Exception $e) {
    //         $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    //         $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
    //     } finally {
    //         $this->database->closeConnection();
    //     }
    // }
    // public function delete($id): void
    // {
    //     try {
    //         $this->database->openConnection();

    //         // Verificar si el servicio existe
    //         $servicio = $this->database->selectColumns(
    //             'aux_servicios',
    //             ['id'],
    //             'id = ? AND estado="1"',
    //             [$id]
    //         );

    //         if (empty($servicio)) {
    //             throw new SoftException("El servicio que intenta eliminar no existe.");
    //         }

    //         // Eliminar (marcar como inactivo) el servicio en la base de datos
    //         $this->database->update(
    //             'aux_servicios',
    //             [
    //                 'estado' => '0',
    //                 'deleted_at' => date('Y-m-d H:i:s'),
    //                 'deleted_by' => $this->getUserId()
    //             ],
    //             'id = ?',
    //             [$id]
    //         );

    //         // Respuesta con auto-recarga de vista
    //         $this->successWithReload(
    //             'Servicio eliminado exitosamente',
    //             '/api/seguros/servicios/index',
    //             '#cuadro'
    //         );
    //     } catch (SoftException $se) {
    //         $this->error("Advertencia: " . $se->getMessage());
    //     } catch (Exception $e) {
    //         $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
    //         $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
    //     } finally {
    //         $this->database->closeConnection();
    //     }
    // }
}
