<?php

namespace Micro\Models;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;
use Micro\Generic\Auth;

class Cremcre
{
    protected $table = 'cremcre_meta';
    protected $primaryKey = 'CCODCTA';

    private DatabaseAdapter $db;
    private $userId;
    private $agencyId;

    public function __construct(DatabaseAdapter $db)
    {
        $this->db = $db;
        $this->userId = Auth::getUserId();
        $this->agencyId = Auth::get('id_agencia');
    }

    public function getAccountsContable($ccodcta)
    {
        $showmensaje = false;
        try {
            $result = $this->db->getSingleResult(
                "SELECT prod.id_cuenta_capital,prod.id_cuenta_interes,prod.id_cuenta_mora 
                    FROM cremcre_meta cre
                    INNER JOIN cre_productos prod ON prod.id=cre.CCODPRD
                    WHERE cre.CCODCTA=?;",
                [$ccodcta]
            );

            if (empty($result)) {
                $showmensaje = true;
                throw new Exception("No se encontró la informacion de la cuenta.");
            }

            return [
                'cuenta_capital' => $result['id_cuenta_capital'],
                'cuenta_interes' => $result['id_cuenta_interes'],
                'cuenta_mora' => $result['id_cuenta_mora'],
            ];
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

    public function getAccountContableCapital($ccodcta)
    {
        $showmensaje = false;
        try {
            $accounts = $this->getAccountsContable($ccodcta);
            if (!isset($accounts['cuenta_capital']) || $accounts['cuenta_capital'] === null) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta capital.");
            }
            return $accounts['cuenta_capital'];
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

    public function getAccountContableInteres($ccodcta)
    {
        $showmensaje = false;
        try {
            $accounts = $this->getAccountsContable($ccodcta);
            if (!isset($accounts['cuenta_interes']) || $accounts['cuenta_interes'] === null) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de interés.");
            }
            return $accounts['cuenta_interes'];
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

    public function getAccountContableMora($ccodcta)
    {
        $showmensaje = false;
        try {
            $accounts = $this->getAccountsContable($ccodcta);
            if (!isset($accounts['cuenta_mora']) || $accounts['cuenta_mora'] === null) {
                $showmensaje = true;
                throw new Exception("No se encontró la cuenta de mora.");
            }
            return $accounts['cuenta_mora'];
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
