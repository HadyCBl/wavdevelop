<?php

namespace Micro\Controllers\Reportes\Formatos;

use Exception;
use Micro\Helpers\Log;
use Micro\Helpers\FpdfExtend;
use Micro\Generic\Utf8;
use Micro\Generic\Moneda;

/**
 * Clase Base para Formatos Personalizados de Reportes
 * 
 * Proporciona funcionalidad común para todos los formatos personalizados:
 * - Acceso a la base de datos
 * - Sesión del usuario
 * - Métodos helper para PDF
 * - Respuestas JSON
 * - Información institucional
 */
abstract class BaseFormato
{
    protected $database;
    protected $session;
    protected $fonte = 'Courier';
    protected $sizeFont = 10;

    public function __construct($database, $session)
    {
        $this->database = $database;
        $this->session = $session;
    }

    /**
     * Método principal que debe implementar cada formato personalizado
     * 
     * @param mixed $data Datos necesarios para generar el formato
     * @return void Envía respuesta JSON y termina ejecución
     */
    abstract public function generar($data): void;

    /**
     * Crea una instancia de PDF con configuración base
     */
    protected function crearPDFBase($orientacion = 'P', $tamano = 'Letter')
    {
        $pdf = new FpdfExtend($orientacion, 'mm', $tamano);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->SetMargins(10, 10, 10);
        return $pdf;
    }

    /**
     * Obtiene información de la institución
     */
    protected function getInfoInstitucion()
    {
        $db_name_general = $_ENV['DDBB_NAME_GENERAL'];
        $idAgencia = $this->session['id_agencia'] ?? null;

        if (!$idAgencia) {
            throw new Exception("No se encontró la agencia en la sesión");
        }

        $query = "SELECT nom_agencia, nomb_comple, muni_lug, emai, tel_1, tel_2, nit, log_img
                  FROM {$db_name_general}.info_coperativa ins
                  INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop
                  WHERE ag.id_agencia = ?";

        $this->database->openConnection();
        $info = $this->database->getAllResults($query, [$idAgencia]);
        $this->database->closeConnection();

        if (empty($info)) {
            throw new Exception("Institución no encontrada");
        }

        return $info[0];
    }

    /**
     * Genera encabezado institucional en el PDF
     */
    protected function generarEncabezadoInstitucional($pdf, $titulo, $info)
    {
        // Logo
        $logoPath = __DIR__ . '/../../../../public/assets/img/' . ($info['log_img'] ?? 'logo_default.png');
        if (file_exists($logoPath)) {
            $pdf->Image($logoPath, 10, 10, 30);
        }

        // Información institucional
        $pdf->SetFont($this->fonte, 'B', 14);
        $pdf->Cell(0, 6, Utf8::decode($info['nomb_comple']), 0, 1, 'C');
        
        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 5, Utf8::decode($info['nom_agencia']), 0, 1, 'C');
        $pdf->Cell(0, 5, Utf8::decode($info['muni_lug']), 0, 1, 'C');
        $pdf->Cell(0, 5, "NIT: {$info['nit']} | Tel: {$info['tel_1']}", 0, 1, 'C');
        
        $pdf->Ln(3);

        // Título del documento
        $pdf->SetFont($this->fonte, 'B', 12);
        $pdf->Cell(0, 7, Utf8::decode($titulo), 0, 1, 'C');
        $pdf->Ln(3);

        // Línea separadora
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(5);
    }

    /**
     * Genera pie de página en el PDF
     */
    protected function generarPieComprobante($pdf, $texto)
    {
        $pdf->Ln(5);
        $pdf->SetDrawColor(200, 200, 200);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);
        
        $pdf->SetFont($this->fonte, 'I', 8);
        $pdf->Cell(0, 4, Utf8::decode($texto), 0, 1, 'C');
        $pdf->Cell(0, 4, 'Fecha y hora: ' . date('d/m/Y H:i:s'), 0, 1, 'C');
    }

    /**
     * Genera respuesta JSON estándar y termina la ejecución
     * 
     * @param array $data Datos a enviar como JSON
     * @param int $statusCode Código de estado HTTP
     * @return void
     */
    protected function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Formatea un valor monetario
     */
    protected function formatoMoneda($valor)
    {
        return Moneda::formato($valor);
    }

    /**
     * Convierte texto a UTF-8 decodificado para FPDF
     */
    protected function decode($texto)
    {
        return Utf8::decode($texto);
    }
}
