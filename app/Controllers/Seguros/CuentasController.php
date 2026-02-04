<?php

namespace Micro\Controllers\Seguros;

use Exception;
use Micro\Controllers\BaseController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;

class CuentasController extends BaseController
{

    public function index(): void
    {
        try {
            $this->database->openConnection();

            $html = $this->renderView('indicadores/seguros/cuentas', [
                'services' => [],
                'currentClient' => null,
                'cuentas' => [],
                'csrf_token' => CSRFProtection::getTokenValue()
            ]);

            $this->view($html); // Retornar JSON con HTML
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
            $this->database->closeConnection();
        }
    }

    public function store(): void
    {
        try {
            // Log::debug("post data", $this->all());
            /**
             * {"csrf_token":"d0649163ca69a07486b06101a82fb70e94aa3799ccc812fcd8a96bc1fb278c53","id_cliente":"25001000173",
             * "servicio_id":"5","selected_service_id":"5","fecha_inicio":"2025-12-24","observaciones":""}
             */

            $data = [
                'id_cliente' => $this->post('id_cliente') ?? null,
                'servicio_id' => $this->post('servicio_id') ?? null,
                'fecha_inicio' => $this->post('fecha_inicio') ?? null,
                'observaciones' => $this->post('observaciones') ?? '',
            ];

            $rules = [
                'id_cliente' => 'required|string|exists:tb_cliente,idcod_cliente',
                'servicio_id' => 'required|integer|min:1|exists:aux_servicios,id',
                'fecha_inicio' => 'required|date',
                'observaciones' => 'optional|string|max:255',
            ];

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            $this->database->openConnection();

            // Insertar nuevo servicio en la base de datos
            $this->database->insert('aux_cuentas', [
                'id_cliente' => $data['id_cliente'],
                'id_servicio' => $data['servicio_id'],
                'fecha_inicio' => $data['fecha_inicio'],
                'observaciones' => $data['observaciones'],
                'estado' => 'vigente',
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->getUserId()
            ]);

            // Respuesta con auto-recarga de vista
            $this->successWithReload(
                'Cuenta de seguro creada exitosamente',
                '/api/seguros/cuentas/index',
                '#cuadro'
            );
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
            $this->database->closeConnection();
        }
    }

    public function edit($id): void
    {
        try {
            $this->database->openConnection();

            $currentClient = $this->database->selectColumns(
                "tb_cliente",
                ["idcod_cliente", "short_name", "no_identifica"],
                "idcod_cliente= ? AND estado=1",
                [$id]
            );

            if (empty($currentClient)) {
                throw new SoftException("No se encontró la informacion del cliente, verifique que esté activo.");
            }

            $cuentas = $this->database->getAllResults(
                "SELECT ac.id, ac.fecha_inicio, ac.observaciones, ac.estado,
                        aser.nombre AS servicio_nombre, aser.costo AS servicio_costo
                 FROM aux_cuentas ac
                 JOIN aux_servicios aser ON ac.id_servicio = aser.id
                 WHERE ac.id_cliente = ? AND ac.estado IN ('vigente','cerrada')
                 ORDER BY ac.fecha_inicio DESC",
                [$id]
            );

            $serviciosExistentes = $this->database->selectColumns(
                "aux_servicios",
                ["id", "nombre", "descripcion", "costo"],
                'estado="1"'
            );

            $html = $this->renderView('indicadores/seguros/cuentas', [
                'services' => $serviciosExistentes,
                'currentClient' => $currentClient[0] ?? null,
                'cuentas' => $cuentas,
                'csrf_token' => CSRFProtection::getTokenValue()
            ]);

            $this->view($html);
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
            $this->database->closeConnection();
        }
    }

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
