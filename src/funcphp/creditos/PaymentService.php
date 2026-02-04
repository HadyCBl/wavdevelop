<?php

namespace Creditos\Utilidades;

use App\Adapters\DatabaseAdapter;
// use Micro\Helpers\Logger;
use Exception;

class PaymentService
{
    protected $database;
    protected $logger;
    protected $userId;
    protected $agencyId;

    public function __construct($database = null)
    {
        if ($database) {
            $this->database = $database;
        } else {
            $this->database = DatabaseAdapter::getInstance();
        }
        // $this->logger = new Logger();
        $this->userId = $_SESSION['id'] ?? null;
        $this->agencyId = $_SESSION['id_agencia'] ?? null;
    }

    public function validateSession()
    {
        if (!$this->userId || !$this->agencyId) {
            throw new Exception("Sesión no válida");
        }
    }

    // protected function validateCashierStatus()
    // {
    //     $result = $this->database->getAllResults(
    //         "SELECT status FROM cashier_sessions WHERE user_id = ? AND status = 'open'",
    //         [$this->userId]
    //     );
    //     if (empty($result)) {
    //         throw new Exception("La caja no está abierta");
    //     }
    // }

    protected function validateDuplicateReceipt($receiptNumber)
    {
        $result = $this->database->selectColumns(
            'CREDKAR',
            ['CNUMING'],
            'CNUMING = ?',
            [$receiptNumber]
        );
        if (!empty($result)) {
            throw new Exception("El número de recibo ya existe");
        }
    }

    protected function getPendingBalance($loanId)
    {
        $result = $this->database->getAllResults(
            "SELECT IFNULL((ROUND((IFNULL(cm.NCapDes,0)),2)-
            (SELECT ROUND(IFNULL(SUM(c.KP),0),2) 
             FROM CREDKAR c 
             WHERE c.CTIPPAG = 'P' AND c.CCODCTA = cm.CCODCTA 
             AND c.CESTADO!='X')),0) AS saldo_pendiente 
             FROM cremcre_meta cm 
             WHERE cm.CCODCTA = ?",
            [$loanId]
        );
        return $result[0]['saldo_pendiente'] ?? 0;
    }

    // protected function updatePaymentSchedule($loanId)
    // {
    //     // Actualizar plan de pagos
    //     $this->database->update(
    //         'cremcre_meta',
    //         ['fecha_ultimo_pago' => date('Y-m-d')],
    //         'CCODCTA = ?',
    //         [$loanId]
    //     );
    // }

    // protected function executeTransaction(callable $operations)
    // {
    //     try {
    //         $this->database->beginTransaction();

    //         $result = $operations();

    //         $this->database->commit();
    //         return $result;
    //     } catch (Exception $e) {
    //         $this->database->rollback();
    //         $this->logger->error('Error en transacción', [
    //             'error' => $e->getMessage(),
    //             'trace' => $e->getTraceAsString()
    //         ]);
    //         throw $e;
    //     }
    // }

    // protected function processPayment($paymentData)
    // {
    //     return $this->executeTransaction(function () use ($paymentData) {
    //         // 1. Validaciones iniciales
    //         $this->validateSession();
    //         $this->validateCashierStatus();
    //         $this->validatePaymentData($paymentData);

    //         // 2. Registrar pago en CREDKAR
    //         $paymentId = $this->registerPayment($paymentData);

    //         // 3. Registrar asiento contable
    //         $journalId = $this->registerAccountingEntry($paymentData);

    //         // 4. Actualizar plan de pagos
    //         $this->updatePaymentSchedule($paymentData['loan_id']);

    //         // 5. Registrar detalles adicionales si existen
    //         if (!empty($paymentData['additional_details'])) {
    //             $this->registerAdditionalDetails($paymentId, $paymentData['additional_details']);
    //         }

    //         return [
    //             'status' => 1,
    //             'payment_id' => $paymentId,
    //             'journal_id' => $journalId
    //         ];
    //     });
    // }
}
