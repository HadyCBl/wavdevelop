<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;
use Micro\Generic\Auth;

class Aprcta
{
    protected $table = 'aprcta';
    protected $primaryKey = 'ccodaport';

    private DatabaseAdapter $db;
    private $userId;
    private $agencyId;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
        $this->userId = Auth::getUserId();
        $this->agencyId = Auth::get('id_agencia');
    }

    public function getAccountContable($ccodaho)
    {
        $showmensaje = false;
        try {
            $accounts = $this->db->getSingleResult(
                "SELECT id_cuenta_contable FROM aprcta cta
                    INNER JOIN aprtip tip ON tip.ccodtip=SUBSTR(cta.ccodaport,7,2)
                    WHERE cta.ccodaport=?;",
                [$ccodaho]
            );
            if (!isset($accounts['id_cuenta_contable']) || $accounts['id_cuenta_contable'] === null) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta contable para la cuenta de ahorros.");
            }
            return $accounts['id_cuenta_contable'];
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    public function getCurrentLibretaNumber($ccodaho)
    {
        $showmensaje = false;
        try {
            $result = $this->db->getSingleResult(
                "SELECT nlibreta FROM aprcta WHERE ccodaport = ?;",
                [$ccodaho]
            );

            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró la información de la cuenta.");
            }

            return $result['nlibreta'];
        } catch (Exception $e) {
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }
}
