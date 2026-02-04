<?php
namespace App\Services\Payments;

use Creditos\Utilidades\PaymentService;
use Exception;

class IndividualPaymentService extends PaymentService {
    
    public function processPayment($paymentData) {
        try {
            $this->database->beginTransaction();

            // Validaciones básicas
            $this->validateSession();
            // $this->validateCashierStatus();
            $this->validatePaymentData($paymentData);

            // Procesar pago
            $paymentId = $this->registerPayment($paymentData);
            
            // Registrar asiento contable
            // $journalId = $this->registerAccountingEntry($paymentData);

            // Actualizar plan de pagos
            // $this->updatePaymentSchedule($paymentData['loan_id']);

            $this->database->commit();

            $this->logger->info('Pago registrado exitosamente', [
                'payment_id' => $paymentId,
                'loan_id' => $paymentData['loan_id']
            ]);

            return [
                'status' => 1,
                'message' => 'Pago registrado correctamente',
                'payment_id' => $paymentId,
                // 'journal_id' => $journalId
            ];

        } catch (Exception $e) {
            $this->database->rollback();
            $this->logger->error('Error al procesar pago', [
                'error' => $e->getMessage(),
                'data' => $paymentData
            ]);
            throw $e;
        }
    }

    private function validatePaymentData($data) {
        $requiredFields = ['loan_id', 'amount', 'receipt_number', 'payment_date'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new Exception("Campo requerido no proporcionado: $field");
            }
        }

        $this->validateDuplicateReceipt($data['receipt_number']);
        
        $pendingBalance = $this->getPendingBalance($data['loan_id']);
        if ($data['amount'] > $pendingBalance) {
            throw new Exception("El monto excede el saldo pendiente");
        }
    }

    private function registerPayment($data) {
        return $this->database->insert('CREDKAR', [
            'CCODCTA' => $data['loan_id'],
            'DFECPRO' => $data['payment_date'],
            'CNUMING' => $data['receipt_number'],
            'NMONTO' => $data['amount'],
            'DFECSIS' => date('Y-m-d H:i:s'),
            'CUSUARIO' => $this->userId,
            'CESTADO' => 'A'
        ]);
    }

    // private function registerAccountingEntry($data) {
    //     $entry = [
    //         'numcom' => $this->getNextVoucherNumber(),
    //         'date' => $data['payment_date'],
    //         'concept' => $data['concept'] ?? 'Pago de crédito',
    //         'amount' => $data['amount'],
    //         'user_id' => $this->userId,
    //         'agency_id' => $this->agencyId
    //     ];

    //     return $this->createAccountingEntry($entry);
    // }

    private function getNextVoucherNumber() {
        $result = $this->database->getAllResults(
            "SELECT MAX(numcom) as last_number FROM ctb_diario"
        );
        return ($result[0]['last_number'] ?? 0) + 1;
    }
}