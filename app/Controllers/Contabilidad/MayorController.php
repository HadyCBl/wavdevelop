<?php

namespace Micro\Controllers\Contabilidad;

use Exception;
use Micro\Controllers\BaseController;
use Micro\Exceptions\SoftException;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
use Micro\Models\Agencia;
use Micro\Models\Contabilidad\FuenteFondo;
use Micro\Models\Contabilidad\Nomenclatura;

class MayorController extends BaseController
{

    public function index(): void
    {
        try {

            $agencias = Agencia::all();

            $fondos = FuenteFondo::all();

            $nomenclaturaContable = Nomenclatura::select('id', 'ccodcta', 'cdescrip', 'tipo')->orderBy('ccodcta')->get();
            if ($nomenclaturaContable->isEmpty()) {
                throw new SoftException("No se ha configurado la nomenclatura contable. Por favor, configurela antes de continuar.");
            }

            $html = $this->renderView('conta/mayor', [
                'agencias' => $agencias,
                'fondos' => $fondos,
                'permissions' => $this->getPermissions(),
                'idagencia' => $this->session['id_agencia'] ?? 0,
                'csrf_token' => CSRFProtection::getTokenValue(),
                'nomenclaturaContable' => $nomenclaturaContable
            ]);

            $this->view($html);
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        }
    }
}
