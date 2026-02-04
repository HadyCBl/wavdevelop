<?php

namespace Micro\Controllers\Seguros;

use Exception;
use Micro\Controllers\BaseController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
use Micro\Models\Clientes\Beneficiario;
use Micro\Models\Seguros\Beneficiario as CuentaBeneficiario;
use Micro\Models\Seguros\Cuenta;

class BeneficiariosController extends BaseController
{

    public function index(): void
    {
        try {

            $html = $this->renderView('indicadores/seguros/beneficiarios', [
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
        }
    }

    public function store(): void
    {
        try {
            // Log::debug("post data", $this->all());

            $tipoAccion = $this->post('tipo_accion') ?? 'nuevo';

            if ($tipoAccion === 'existente') {
                // Procesando beneficiario existente
                $this->procesarBeneficiarioExistente();
            } else {
                // Procesando nuevo beneficiario
                $this->procesarNuevoBeneficiario();
            }
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
        }
    }

    private function procesarBeneficiarioExistente(): void
    {
        $data = [
            'id_cuenta' => $this->post('id_cuenta') ?? null,
            'id_beneficiario_existente' => $this->post('id_beneficiario_existente') ?? null,
            'parentesco_existente' => $this->post('parentesco_existente') ?? null,
            'porcentaje_existente' => $this->post('porcentaje_existente') ?? null,
        ];

        $rules = [
            'id_cuenta' => 'required|integer|exists:aux_cuentas,id',
            'id_beneficiario_existente' => 'required|integer|exists:cli_beneficiarios,id',
            'parentesco_existente' => 'required|integer|exists:tb_parentescos,id',
            'porcentaje_existente' => 'required|numeric|min:0.01|max:100',
        ];

        // Log::debug('Validando datos de beneficiario existente', $data);

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $firstError = $validator->firstOnErrors();
            throw new SoftException($firstError);
        }

        // Verificar si ya existe esta relación
        $yaExiste = CuentaBeneficiario::where('id_cuenta', $data['id_cuenta'])
            ->where('id_beneficiario', $data['id_beneficiario_existente'])
            ->exists();

        if ($yaExiste) {
            throw new SoftException('Este beneficiario ya está asignado a esta cuenta');
        }

        // Verificar que no se exceda el 100%
        $total = CuentaBeneficiario::where('id_cuenta', $data['id_cuenta'])
            ->sum('porcentaje');

        if (($total + $data['porcentaje_existente']) > 100) {
            throw new SoftException('El porcentaje excede el 100%');
        }

        // Crear la relación
        CuentaBeneficiario::create([
            'id_cuenta'        => $data['id_cuenta'],
            'id_beneficiario'  => $data['id_beneficiario_existente'],
            'parentesco'       => $data['parentesco_existente'],
            'porcentaje'       => $data['porcentaje_existente'],
            'created_by'       => $this->getUserId(),
            'created_at'       => date('Y-m-d H:i:s')
        ]);

        // Respuesta con auto-recarga de vista
        $this->successWithReload(
            'Beneficiario agregado exitosamente',
            '/api/seguros/beneficiarios/' . $data['id_cuenta'],
            '#cuadro'
        );
    }

    private function procesarNuevoBeneficiario(): void
    {
        $data = [
            'id_cuenta' => $this->post('id_cuenta') ?? null,
            'nombres' => $this->post('nombres') ?? null,
            'apellidos' => $this->post('apellidos') ?? null,
            'identificacion' => $this->post('identificacion') ?? null,
            'telefono' => $this->post('telefono') ?? null,
            'direccion' => $this->post('direccion') ?? null,
            'parentesco' => $this->post('parentesco') ?? null,
            'porcentaje' => $this->post('porcentaje') ?? null,
        ];

        $rules = [
            'id_cuenta' => 'required|integer|exists:aux_cuentas,id',
            'nombres' => 'required|string|max_length:100',
            'apellidos' => 'required|string|max_length:100',
            'identificacion' => 'required|string|max_length:20',
            'telefono' => 'required|string|max_length:20',
            'direccion' => 'required|string|max_length:255',
            'parentesco' => 'required|integer|exists:tb_parentescos,id',
            'porcentaje' => 'required|numeric|min:0.01|max:100',
        ];

        // Log::debug('Validando datos de entrada para nuevo beneficiario', $data);

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $firstError = $validator->firstOnErrors();
            throw new SoftException($firstError);
        }

        $data['identificacion'] = trim($data['identificacion']);

        $existe = Beneficiario::where('identificacion', $data['identificacion'])->exists();

        if ($existe) {
            throw new SoftException('Ya existe un beneficiario con esta identificación. Use la opción "Seleccionar Existente"');
        }

        $total = CuentaBeneficiario::where('id_cuenta', $data['id_cuenta'])
            ->sum('porcentaje');

        if (($total + $data['porcentaje']) > 100) {
            throw new SoftException('El porcentaje excede el 100%');
        }

        $beneficiario = Beneficiario::create([
            'nombres'          => $data['nombres'],
            'apellidos'        => $data['apellidos'],
            'identificacion'   => $data['identificacion'],
            'telefono'         => $data['telefono'],
            'direccion'        => $data['direccion'],
            'created_by'       => $this->getUserId(),
            'created_at'       => date('Y-m-d H:i:s')
        ]);

        CuentaBeneficiario::create([
            'id_cuenta'        => $data['id_cuenta'],
            'id_beneficiario'  => $beneficiario->id,
            'parentesco'       => $data['parentesco'],
            'porcentaje'       => $data['porcentaje'],
            'created_by'       => $this->getUserId(),
            'created_at'       => date('Y-m-d H:i:s')
        ]);

        // Respuesta con auto-recarga de vista
        $this->successWithReload(
            'Beneficiario creado y agregado exitosamente',
            '/api/seguros/beneficiarios/' . $data['id_cuenta'],
            '#cuadro'
        );
    }

    public function update(): void
    {
        try {
            $tipoEdicion = $this->input('tipo_edicion') ?? 'relacion';

            if ($tipoEdicion === 'datos') {
                $this->updateDatosPersonales();
            } else {
                $this->updateRelacion();
            }
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        }
    }

    private function updateRelacion(): void
    {
        $data = [
            'id_cuenta' => $this->input('id_cuenta') ?? null,
            'id_beneficiario' => $this->input('id_beneficiario') ?? null,
            'parentesco' => $this->input('parentesco') ?? null,
            'porcentaje' => $this->input('porcentaje') ?? null,
        ];

        $rules = [
            'id_cuenta' => 'required|integer|exists:aux_cuentas,id',
            'id_beneficiario' => 'required|integer|exists:cli_beneficiarios,id',
            'parentesco' => 'required|integer|exists:tb_parentescos,id',
            'porcentaje' => 'required|numeric|min:0.01|max:100',
        ];

        // Log::debug('Validando datos de actualización de relación beneficiario', $data);

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $firstError = $validator->firstOnErrors();
            throw new SoftException($firstError);
        }

        // Buscar la relación
        $relacion = CuentaBeneficiario::where('id_cuenta', $data['id_cuenta'])
            ->where('id_beneficiario', $data['id_beneficiario'])
            ->first();

        if (!$relacion) {
            throw new SoftException('No se encontró la relación beneficiario-cuenta');
        }

        // Calcular el total excluyendo el porcentaje actual del beneficiario
        $totalOtros = CuentaBeneficiario::where('id_cuenta', $data['id_cuenta'])
            ->where('id_beneficiario', '!=', $data['id_beneficiario'])
            ->sum('porcentaje');

        if (($totalOtros + $data['porcentaje']) > 100) {
            throw new SoftException('El porcentaje excede el 100%');
        }

        // Actualizar la relación
        $relacion->parentesco = $data['parentesco'];
        $relacion->porcentaje = $data['porcentaje'];
        $relacion->updated_by = $this->getUserId();
        $relacion->updated_at = date('Y-m-d H:i:s');
        $relacion->save();

        // Respuesta con auto-recarga de vista
        $this->successWithReload(
            'Relación actualizada exitosamente',
            '/api/seguros/beneficiarios/' . $data['id_cuenta'],
            '#cuadro'
        );
    }

    private function updateDatosPersonales(): void
    {
        $data = [
            'id_beneficiario' => $this->input('id_beneficiario') ?? null,
            'nombres' => $this->input('nombres') ?? null,
            'apellidos' => $this->input('apellidos') ?? null,
            'identificacion' => $this->input('identificacion') ?? null,
            'telefono' => $this->input('telefono') ?? null,
            'direccion' => $this->input('direccion') ?? null,
        ];

        $rules = [
            'id_beneficiario' => 'required|integer|exists:cli_beneficiarios,id',
            'nombres' => 'required|string|max_length:100',
            'apellidos' => 'required|string|max_length:100',
            'identificacion' => 'required|string|max_length:20',
            'telefono' => 'required|string|max_length:20',
            'direccion' => 'required|string|max_length:255',
        ];

        // Log::debug('Validando datos personales de beneficiario', $data);

        $validator = Validator::make($data, $rules);
        if ($validator->fails()) {
            $firstError = $validator->firstOnErrors();
            throw new SoftException($firstError);
        }

        $data['identificacion'] = trim($data['identificacion']);

        // Buscar el beneficiario
        $beneficiario = Beneficiario::find($data['id_beneficiario']);

        if (!$beneficiario) {
            throw new SoftException('No se encontró el beneficiario');
        }

        // Verificar si la identificación ya existe en otro beneficiario
        $existeOtro = Beneficiario::where('identificacion', $data['identificacion'])
            ->where('id', '!=', $data['id_beneficiario'])
            ->exists();

        if ($existeOtro) {
            throw new SoftException('Ya existe otro beneficiario con esta identificación');
        }

        // Actualizar datos personales
        $beneficiario->nombres = $data['nombres'];
        $beneficiario->apellidos = $data['apellidos'];
        $beneficiario->identificacion = $data['identificacion'];
        $beneficiario->telefono = $data['telefono'];
        $beneficiario->direccion = $data['direccion'];
        $beneficiario->updated_by = $this->getUserId();
        $beneficiario->updated_at = date('Y-m-d H:i:s');
        $beneficiario->save();

        // Obtener id_cuenta para recargar la vista (de cualquier cuenta asociada)
        $relacion = CuentaBeneficiario::where('id_beneficiario', $data['id_beneficiario'])->first();
        $idCuenta = $relacion ? $relacion->id_cuenta : null;

        if ($idCuenta) {
            $this->successWithReload(
                'Datos del beneficiario actualizados exitosamente',
                '/api/seguros/beneficiarios/' . $idCuenta,
                '#cuadro'
            );
        } else {
            $this->success('Datos del beneficiario actualizados exitosamente');
        }
    }

    public function destroy($id): void
    {
        try {
            $data = [
                'id_beneficiario_pivot' => $id ?? null,
            ];
            $rules = [
                'id_beneficiario_pivot' => 'required|integer|exists:aux_beneficiarios,id',
            ];

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            /**
             * obtener id_cuenta antes de eliminar
             */
            $beneficiario = CuentaBeneficiario::findOrFail($data['id_beneficiario_pivot']);

            $idCuenta = $beneficiario->id_cuenta;

            // $deleted = CuentaBeneficiario::where('id', $data['id_beneficiario_pivot'])
            //     ->delete();
            $deleted = $beneficiario->delete();

            if (!$deleted) {
                throw new SoftException('No se pudo eliminar el beneficiario de la cuenta');
            }

            /**
             * obtener id_cuenta para recargar la vista
             */

            // $this->success('Beneficiario eliminado de la cuenta exitosamente');
            $this->successWithReload(
                'Beneficiario eliminado de la cuenta exitosamente',
                '/api/seguros/beneficiarios/' . $idCuenta,
                '#cuadro'
            );
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        }
    }

    public function show($id): void
    {
        try {
            $currentClient = Cuenta::with([
                'cliente:idcod_cliente,short_name,no_identifica',
                'servicio:id,nombre,costo',
                'beneficiarios' => function ($q) {
                    $q->select(
                        'cli_beneficiarios.id',
                        'aux_beneficiarios.id as idPivot',
                        'nombres',
                        'apellidos',
                        'identificacion',
                        'telefono',
                        'direccion',
                        'aux_beneficiarios.parentesco as idParentesco',
                        'aux_beneficiarios.porcentaje',
                        'tb_parentescos.descripcion as parentesco'
                    )
                        ->leftJoin(
                            'tb_parentescos',
                            'tb_parentescos.id',
                            '=',
                            'aux_beneficiarios.parentesco'
                        );
                }
            ])
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

            $parentescos = \Micro\Models\Parentesco::all();

            $html = $this->renderView('indicadores/seguros/beneficiarios', [
                'cuenta' => $currentClient ?? null,
                'parentescos' => $parentescos,
                'csrf_token' => CSRFProtection::getTokenValue()
            ]);

            $this->view($html);
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
        }
    }
}
