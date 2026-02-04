<?php

namespace Micro\Controllers\Seguros;

use Exception;
use Micro\Controllers\BaseController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
use Illuminate\Database\Capsule\Manager as DB;
use Micro\Models\Contabilidad\Nomenclatura;
use Micro\Models\Seguros\Servicio;

class ServiciosController extends BaseController
{

    public function index(): void
    {
        try {

            $serviciosExistentes = Servicio::select('id', 'nombre', 'descripcion', 'costo', 'id_nomenclatura')->get();

            $nomenclaturaContable = Nomenclatura::select('id', 'ccodcta', 'cdescrip', 'tipo')->orderBy('ccodcta')->get();
            if ($nomenclaturaContable->isEmpty()) {
                throw new SoftException("No se ha configurado la nomenclatura contable. Por favor, configurela antes de continuar.");
            }

            $html = $this->renderView('indicadores/seguros/servicios', [
                'services' => $serviciosExistentes,
                'nomenclaturaContable' => $nomenclaturaContable,
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

            $data = [
                'nombre' => $this->post('nombre') ?? null,
                'descripcion' => $this->post('descripcion') ?? null,
                'costo' => $this->post('costo') ?? null,
                'id_nomenclatura' => $this->post('cuenta_contable') ?? null,
                'edad_minima' => $this->post('edad_minima') ?? null,
                'edad_maxima' => $this->post('edad_maxima') ?? null,
                'notas' => $this->post('notas') ?? null,
            ];

            $rules = [
                'nombre' => 'required|string|max:100',
                'descripcion' => 'required|string|max:255',
                'costo' => 'required|numeric|min:0',
                'id_nomenclatura' => 'required|integer|min:1|exists:ctb_nomenclatura,id',
                'edad_minima' => 'nullable|integer|min:0|max:120',
                'edad_maxima' => 'nullable|integer|min:0|max:120',
                'notas' => 'nullable|string|max:255',
            ];

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            if (isset($data['edad_minima'], $data['edad_maxima']) && $data['edad_minima'] > $data['edad_maxima']) {
                throw new SoftException("La edad mínima no puede ser mayor que la edad máxima.");
            }

            // $this->database->openConnection();

            Servicio::create([
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'],
                'costo' => $data['costo'],
                'id_nomenclatura' => $data['id_nomenclatura'],
                'edad_minima' => ($data['edad_minima'] === '') ? null : $data['edad_minima'],
                'edad_maxima' => ($data['edad_maxima'] === '') ? null : $data['edad_maxima'],
                'notas' => $data['notas'],
                'created_at' => date('Y-m-d H:i:s'),
                'created_by' => $this->getUserId()
            ]);

            // Respuesta con auto-recarga de vista
            $this->successWithReload(
                'Servicio agregado exitosamente',
                '/api/seguros/servicios/index',
                '#cuadro'
            );
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
        }
    }

    public function edit($id): void
    {
        try {

            $servicio = Servicio::find($id);
            if (!$servicio || $servicio->estado != '1') {
                throw new SoftException("El servicio que intenta editar no existe.");
            }

            $serviciosExistentes = Servicio::select('id', 'nombre', 'descripcion', 'costo', 'id_nomenclatura')->get();

            $nomenclaturaContable = Nomenclatura::select('id', 'ccodcta', 'cdescrip', 'tipo')->orderBy('ccodcta')->get();
            if ($nomenclaturaContable->isEmpty()) {
                throw new SoftException("No se ha configurado la nomenclatura contable. Por favor, configurela antes de continuar.");
            }

            $html = $this->renderView('indicadores/seguros/servicios', [
                'services' => $serviciosExistentes,
                'currentService' => $servicio ?? null,
                'nomenclaturaContable' => $nomenclaturaContable,
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

    public function update(): void
    {
        try {
            $data = [
                'id' => $this->input('id') ?? null,
                'nombre' => $this->input('nombre') ?? null,
                'descripcion' => $this->input('descripcion') ?? null,
                'costo' => $this->input('costo') ?? null,
                'id_nomenclatura' => $this->input('cuenta_contable') ?? null,
                'edad_minima' => $this->input('edad_minima') ?? null,
                'edad_maxima' => $this->input('edad_maxima') ?? null,
                'notas' => $this->input('notas') ?? null,
            ];

            $rules = [
                'id' => 'required|integer|exists:aux_servicios,id',
                'nombre' => 'required|string|max:100',
                'descripcion' => 'required|string|max:255',
                'costo' => 'required|numeric|min:0',
                'id_nomenclatura' => 'required|integer|min:1|exists:ctb_nomenclatura,id',
                'edad_minima' => 'nullable|integer|min:0|max:120',
                'edad_maxima' => 'nullable|integer|min:0|max:120',
                'notas' => 'nullable|string|max:255',
            ];

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            if (isset($data['edad_minima'], $data['edad_maxima']) && $data['edad_minima'] > $data['edad_maxima']) {
                throw new SoftException("La edad mínima no puede ser mayor que la edad máxima.");
            }

            $service = Servicio::find($data['id']);

            if (!$service || $service->estado != '1') {
                throw new SoftException("El servicio que intenta actualizar no existe.");
            }

            $service->nombre = $data['nombre'];
            $service->descripcion = $data['descripcion'];
            $service->costo = $data['costo'];
            $service->id_nomenclatura = $data['id_nomenclatura'];
            $service->edad_minima = ($data['edad_minima'] === '') ? null : $data['edad_minima'];
            $service->edad_maxima = ($data['edad_maxima'] === '') ? null : $data['edad_maxima'];
            $service->notas = $data['notas'];
            $service->updated_at = date('Y-m-d H:i:s');
            $service->updated_by = $this->getUserId();
            $service->save();

            // Respuesta con auto-recarga de vista
            $this->successWithReload(
                'Servicio actualizado exitosamente',
                '/api/seguros/servicios/index',
                '#cuadro'
            );
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
        }
    }
    public function delete($id): void
    {
        try {
            $this->database->openConnection();

            $service = Servicio::find($id);

            if (!$service || $service->estado != '1') {
                throw new SoftException("El servicio que intenta eliminar no existe.");
            }

            $service->delete();

            // Respuesta con auto-recarga de vista
            $this->successWithReload(
                'Servicio eliminado exitosamente',
                '/api/seguros/servicios/index',
                '#cuadro'
            );
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        } finally {
            // $this->database->closeConnection();
        }
    }
}
