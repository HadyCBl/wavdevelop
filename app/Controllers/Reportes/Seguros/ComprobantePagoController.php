<?php

namespace Micro\Controllers\Reportes\Seguros;

use Exception;
use Micro\Controllers\BaseReporteController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Helpers\Log;
use Micro\Models\Seguros\Pago;
use Micro\Models\Seguros\Renovacion;
use Micro\Models\Seguros\Auxilio;
use Illuminate\Database\Capsule\Manager as DB;

class ComprobantePagoController extends BaseReporteController
{
    public function index()
    {
        try {
            $id = $this->input['id'] ?? null;

            if (!$id) {
                return $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'ID de pago no proporcionado'
                ]);
            }

            $this->generarReporteConFormato($id, function ($id) {
                $this->generarFormatoGenerico($id);
            });
        } catch (SoftException $se) {
            Log::error("Error soft generando comprobante: " . $se->getMessage());
            return $this->jsonResponse([
                'status' => 0,
                'mensaje' => $se->getMessage()
            ]);
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            return $this->jsonResponse([
                'status' => 0,
                'mensaje' => "Error al generar comprobante. Código: $codigoError"
            ]);
        }
    }

    private function generarFormatoGenerico($id)
    {
        try {
            // Log::info("Obteniendo datos para comprobante de pago de auxilio ID: $id");
            // // Obtener pago de auxilio con todas sus relaciones usando Eloquent
            // // Activar log de consultas Eloquent
            // DB::enableQueryLog();

            $pago = Pago::with([
                'auxilio.cuenta.cliente:idcod_cliente,short_name,no_identifica',
                'auxilio.cuenta.servicio:id,nombre,monto_auxilio',
                'cuenta_banco.banco:id,nombre'
            ])
                ->where('id', $id)
                ->first();

            // Ver las consultas ejecutadas
            // $queries = DB::getQueryLog();
            // Log::info("Consultas ejecutadas:", $queries);

            // Log::info("Pago obtenido: " . ($pago ? "Sí" : "No"));

            if (!$pago) {
                $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'Pago no encontrado'
                ]);
                return;
            }

            // Verificar que sea un pago de auxilio
            if (!$pago->auxilio) {
                $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => 'Este pago no corresponde a un auxilio'
                ]);
                return;
            }

            // Obtener información de la institución
            $info = $this->getInfoInstitucion();

            // Crear PDF base sin encabezado predefinido
            $pdf = $this->crearPDFBase('P', 'Letter');
            $pdf->AddPage();

            // Generar encabezado institucional
            $this->generarEncabezadoInstitucional($pdf, 'COMPROBANTE DE PAGO DE AUXILIO PÓSTUMO', $info);

            // Generar cuerpo del comprobante
            $this->generarCuerpoComprobante($pdf, $pago, $info);

            // Generar respuesta con base64
            ob_start();
            $pdf->Output('I', 'comprobante.pdf');
            $pdfData = ob_get_contents();
            ob_end_clean();

            $nombreArchivo = "comprobante_auxilio_{$id}_" . date('YmdHis') . ".pdf";

            return $this->jsonResponse([
                'status' => 1,
                'mensaje' => 'Comprobante generado exitosamente',
                'namefile' => $nombreArchivo,
                'tipo' => 'pdf',
                'data' => base64_encode($pdfData)
            ]);
        } catch (SoftException $se) {
            Log::error("Error soft generando comprobante: " . $se->getMessage());
            $this->jsonResponse([
                'status' => 0,
                'mensaje' => $se->getMessage()
            ]);
        }
    }

    /**
     * Genera el cuerpo del comprobante de pago de auxilio
     */
    private function generarCuerpoComprobante($pdf, $pago, $info)
    {
        $auxilio = $pago->auxilio;
        $cuenta = $auxilio->cuenta;
        $cliente = $cuenta->cliente;
        $servicio = $cuenta->servicio;

        // Espaciado
        $pdf->Ln(5);
        
        // ===== INFORMACIÓN DEL PAGO =====
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, Utf8::decode('INFORMACIÓN DEL PAGO'), 0, 1, 'L');
        $pdf->Ln(2);

        // Tabla de información del pago
        $pdf->SetFont('Arial', '', 10);
        $y = $pdf->GetY();
        
        // Columna izquierda
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, 'No. Comprobante:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, str_pad($pago->id, 8, '0', STR_PAD_LEFT), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, 'Fecha de Pago:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, date('d/m/Y', strtotime($pago->fecha)), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, 'Forma de Pago:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, strtoupper($pago->forma_pago), 0, 1, 'L');

        if ($pago->forma_pago === 'banco' && $pago->cuenta_banco) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 5, 'Banco:', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(60, 5, Utf8::decode($pago->cuenta_banco->banco->nombre ?? ''), 0, 1, 'L');
            
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 5, 'No. Cheque:', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(60, 5, $pago->banco_numdoc ?? '', 0, 1, 'L');
        }

        if ($pago->numdoc) {
            $pdf->SetFont('Arial', 'B', 9);
            $pdf->Cell(45, 5, 'No. Documento:', 0, 0, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(60, 5, $pago->numdoc, 0, 1, 'L');
        }

        $pdf->Ln(5);

        // ===== INFORMACIÓN DEL BENEFICIARIO =====
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, Utf8::decode('DATOS DEL BENEFICIARIO'), 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, 'Nombre:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, Utf8::decode($cliente->short_name), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, Utf8::decode('Identificación:'), 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, $cliente->no_identifica, 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, 'Servicio:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 5, Utf8::decode($servicio->nombre), 0, 1, 'L');

        $pdf->Ln(5);

        // ===== INFORMACIÓN DEL AUXILIO =====
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, Utf8::decode('INFORMACIÓN DEL AUXILIO'), 0, 1, 'L');
        $pdf->Ln(2);

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, 'Fecha Fallecimiento:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, date('d/m/Y', strtotime($auxilio->fecha_fallece)), 0, 1, 'L');

        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(45, 5, 'Fecha Solicitud:', 0, 0, 'L');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(60, 5, date('d/m/Y', strtotime($auxilio->fecha_solicitud)), 0, 1, 'L');

        $pdf->Ln(5);

        // ===== DETALLE DEL MONTO =====
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 6, 'DETALLE DEL MONTO', 0, 1, 'L');
        $pdf->Ln(2);

        // Tabla del monto
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(120, 7, 'Concepto', 1, 0, 'C', true);
        $pdf->Cell(70, 7, 'Monto', 1, 1, 'C', true);

        $pdf->SetFont('Arial', '', 10);
        $pdf->Cell(120, 7, Utf8::decode($pago->concepto ?? 'Pago de Auxilio Póstumo'), 1, 0, 'L');
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(70, 7, 'Q ' . number_format($pago->monto, 2), 1, 1, 'R');

        $pdf->Ln(3);

        // Total con resaltado
        $pdf->SetFillColor(220, 220, 220);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(120, 8, 'TOTAL PAGADO', 1, 0, 'R', true);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(70, 8, 'Q ' . number_format($pago->monto, 2), 1, 1, 'R', true);

        // Notas si existen
        if ($auxilio->notas) {
            $pdf->Ln(5);
            $pdf->SetFont('Arial', 'B', 10);
            $pdf->Cell(0, 6, 'OBSERVACIONES:', 0, 1, 'L');
            $pdf->SetFont('Arial', '', 9);
            $pdf->MultiCell(0, 5, Utf8::decode($auxilio->notas), 0, 'L');
        }

        // Firmas
        $pdf->Ln(15);
        $pdf->SetFont('Arial', '', 9);
        
        // Línea de firma - Entregado por
        $pdf->Cell(90, 5, str_repeat('_', 50), 0, 0, 'C');
        $pdf->Cell(10, 5, '', 0, 0);
        $pdf->Cell(90, 5, str_repeat('_', 50), 0, 1, 'C');
        
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell(90, 5, Utf8::decode('Entregado por'), 0, 0, 'C');
        $pdf->Cell(10, 5, '', 0, 0);
        $pdf->Cell(90, 5, 'Recibido por', 0, 1, 'C');

        // Pie de página
        $pdf->Ln(10);
        $pdf->SetFont('Arial', 'I', 8);
        $pdf->SetTextColor(100, 100, 100);
        $pdf->Cell(0, 4, Utf8::decode('Documento generado el ' . date('d/m/Y H:i:s')), 0, 1, 'C');
        // $pdf->Cell(0, 4, Utf8::decode($info['nombre_institucion']), 0, 1, 'C');
    }
}
