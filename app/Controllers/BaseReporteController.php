<?php

namespace Micro\Controllers;

use Exception;
use Micro\Controllers\Reportes\Config\BaseReporteConfig;
use Micro\Helpers\Log;
use Micro\Generic\Utf8;
use Micro\Helpers\FpdfExtend;
use PhpOffice\PhpSpreadsheet\Calculation\Statistical\Distributions\F;

/**
 * Controlador Base para Reportes
 * 
 * Proporciona funcionalidad común para todos los reportes:
 * - Validación de sesión
 * - Validación de datos
 * - Procesamiento de filtros
 * - Generación de reportes
 * - Manejo de respuestas
 */
abstract class BaseReporteController
{
    protected $database;
    protected $session;
    protected $input;
    protected $fonte = 'Courier';
    protected $sizeFont = 10;

    protected $idDocument = null;

    public function __construct($database, $session)
    {
        $this->database = $database;
        $this->session = $session;
        $this->input = $this->getJsonInput();
    }

    /**
     * Genera el reporte delegando a formato personalizado si existe idDocument
     * 
     * @param mixed $id ID del registro a procesar
     * @param callable $callbackGenerico Función que genera el formato genérico
     * @return void Envía respuesta JSON y termina ejecución
     */
    protected function generarReporteConFormato($id, callable $callbackGenerico): void
    {
        // Si no hay documento personalizado, ejecutar callback genérico
        if ($this->idDocument === null) {
            $callbackGenerico($id);
            return;
        }

        try {
            $this->database->openConnection();

            // Consultar clase de formato en tb_documentos
            $documento = $this->database->selectColumns(
                'tb_documentos',
                ['nombre'],
                'id_reporte = ?',
                [$this->idDocument]
            );

            if (empty($documento) || empty($documento[0]['nombre'])) {
                Log::warning("Documento {$this->idDocument} no tiene clase_formato definida, usando formato genérico");
                $callbackGenerico($id);
                return;
            }

            $claseFormato = $documento[0]['nombre'];
            // $claseFormato = "Micro\\Controllers\\Reportes\\Formatos\\Seguros\\Codepa\\ContratoRenovacion"; // FORZAR AQUI PARA PRUEBAS

            // Verificar que la clase exista
            if (!class_exists($claseFormato)) {
                Log::error("Clase de formato no existe: {$claseFormato}");
                $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => "Formato personalizado no encontrado"
                ]);
                return;
            }

            // Instanciar y ejecutar formato personalizado
            $formateador = new $claseFormato($this->database, $this->session);

            // Verificar que implementa el método generar
            if (!method_exists($formateador, 'generar')) {
                Log::error("Clase {$claseFormato} no implementa método generar()");
                $this->jsonResponse([
                    'status' => 0,
                    'mensaje' => "Formato personalizado inválido"
                ]);
                return;
            }

            $formateador->generar($id);
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode(
                $e->getMessage(),
                __FILE__,
                __LINE__,
                $e->getFile(),
                $e->getLine()
            );
            $this->jsonResponse([
                'status' => 0,
                'mensaje' => "Error al generar formato personalizado. Código: $codigoError"
            ]);
        } finally {
            $this->database->closeConnection();
        }
    }

    /**
     * Obtiene información de la institución y agencia
     */
    protected function getInfoInstitucion()
    {
        $showmensaje = false;
        try {
            $db_name_general = $_ENV['DDBB_NAME_GENERAL'];
            $idAgencia = $this->session['id_agencia'] ?? null;

            if (!$idAgencia) {
                $showmensaje = true;
                throw new Exception("No se encontró la agencia en la sesión");
            }

            $query = "SELECT nom_agencia, nomb_comple, muni_lug, emai, tel_1, tel_2, nit, log_img
                      FROM {$db_name_general}.info_coperativa ins
                      INNER JOIN tb_agencia ag ON ag.id_institucion = ins.id_cop
                      WHERE ag.id_agencia = ?";

            $this->database->openConnection();
            $info = $this->database->getAllResults($query, [$idAgencia]);

            if (empty($info)) {
                $showmensaje = true;
                throw new Exception("Institución asignada a la agencia no encontrada");
            }

            return $info[0];
        } catch (Exception $e) {
            // Log::error("Error obteniendo información de la institución: " . $e->getMessage(), ['id_agencia' => $this->session['id_agencia'] ?? null]);
            // throw $e;
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        } finally {
            $this->database->closeConnection();
        }
    }

    /**
     * Valida que la sesión esté activa
     */
    protected function validarSesion()
    {
        if (!isset($this->session['id_agencia'])) {
            $this->jsonResponse([
                'status' => 0,
                'messagecontrol' => 'expired',
                'mensaje' => 'Sesión expirada, vuelve a iniciar sesión',
                'url' => BASE_URL
            ], 401);
        }
    }

    /**
     * Exporta datos según tipo (forma simple)
     */
    protected function exportar($datos, $tipo, $nombre, $filtros): void
    {
        switch ($tipo) {
            case 'xlsx':
                $this->exportarExcel($datos, $nombre, $filtros);
                break;
            case 'pdf':
                $this->exportarPDF($datos, $nombre, $filtros);
                break;
            case 'json':
            default:
                $this->jsonResponse([
                    'status' => 1,
                    'datos' => $datos,
                    'filtros' => $filtros,
                    'total' => count($datos)
                ]);
        }
    }

    /**
     * Genera respuesta JSON para descarga de PDF
     */
    protected function generarRespuestaPDF($pdf, BaseReporteConfig $config): void
    {
        ob_start();
        $pdf->Output('I', 'reporte.pdf');
        $pdfData = ob_get_contents();
        ob_end_clean();

        $nombreArchivo = strtolower(str_replace(' ', '_', $config->getTitulo())) . '_' . date('YmdHis') . '.pdf';

        $this->jsonResponse([
            'status' => 1,
            'mensaje' => 'Reporte generado correctamente',
            'namefile' => $config->getTitulo(),
            'tipo' => 'pdf',
            'data' => 'data:application/pdf;base64,' . base64_encode($pdfData)
        ]);
    }

    /**
     * Genera encabezado profesional en Excel (estilo estado_cuenta_apr)
     * Retorna la fila donde debe comenzar el contenido
     */
    protected function generarEncabezadoExcel($sheet, BaseReporteConfig $config, array $filtros, array $info)
    {
        $row = 1;
        $columnas = $config->getColumnas();
        $maxCol = !empty($columnas) ? count($columnas) : 4;
        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxCol);

        // Título principal - Institución
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", $info['nomb_comple']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        // Oficina
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", $info['nom_agencia']);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        // Dirección
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", $info['muni_lug']);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        // Contacto
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", "Email: {$info['emai']} | Tel: {$info['tel_1']} | NIT: {$info['nit']}");
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $row++;

        $row++; // Línea en blanco

        // Título del reporte
        $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
        $sheet->setCellValue("A{$row}", strtoupper($config->getTitulo()));
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF3498DB');
        $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
        $row++;

        // Información adicional
        $infoAdicional = $config->getInfoAdicional($filtros);
        if (!empty($infoAdicional)) {
            $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
            $texto = [];
            foreach ($infoAdicional as $label => $valor) {
                $texto[] = $label . ': ' . $valor;
            }
            $sheet->setCellValue("A{$row}", implode(' | ', $texto));
            $sheet->getStyle("A{$row}")->getFont()->setBold(true);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF95A5A6');
            $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
            $row++;
        }

        $row += 2; // Líneas en blanco

        return $row;
    }

    /**
     * Genera respuesta JSON para descarga de Excel
     */
    protected function generarRespuestaExcel($spreadsheet, BaseReporteConfig $config): void
    {
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

        ob_start();
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $nombreArchivo = strtolower(str_replace(' ', '_', $config->getTitulo())) . '_' . date('YmdHis');

        $this->jsonResponse([
            'status' => 1,
            'mensaje' => 'Reporte generado correctamente',
            'namefile' => $nombreArchivo,
            'tipo' => 'xlsx',
            'data' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode($xlsData)
        ]);
    }

    /**
     * Exporta a Excel (forma simple - backward compatibility)
     * Los controladores hijos pueden sobrescribir este método
     */
    protected function exportarExcel($datos, $nombre, $filtros): void
    {
        // Implementación básica - sobrescribir si necesitas personalizar
        require_once __DIR__ . '/../../vendor/autoload.php';

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados (usar las keys del primer registro)
        if (!empty($datos)) {
            $headers = array_keys($datos[0]);
            $sheet->fromArray($headers, null, 'A1');

            // Datos
            $fila = 2;
            foreach ($datos as $row) {
                $sheet->fromArray(array_values($row), null, 'A' . $fila);
                $fila++;
            }
        }

        // Generar archivo
        ob_start();
        $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        $xlsData = ob_get_contents();
        ob_end_clean();

        $this->jsonResponse([
            'status' => 1,
            'tipo' => 'xlsx',
            'archivo' => base64_encode($xlsData),
            'nombre' => $nombre . '_' . date('YmdHis') . '.xlsx'
        ]);
    }

    /**
     * Exporta a PDF
     * Los controladores hijos pueden sobrescribir este método
     */
    protected function exportarPDF($datos, $nombre, $filtros): void
    {
        // Implementación básica - sobrescribir si necesitas personalizar
        // require_once __DIR__ . '/../../fpdf/fpdf.php';

        $pdf = new FpdfExtend();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(0, 10, strtoupper($nombre), 0, 1, 'C');

        // Aquí agregarías la lógica para formatear tu PDF
        // Los controladores hijos pueden sobrescribir para personalizar

        $pdfData = $pdf->Output('S');

        $this->jsonResponse([
            'status' => 1,
            'tipo' => 'pdf',
            'archivo' => base64_encode($pdfData),
            'nombre' => $nombre . '_' . date('YmdHis') . '.pdf'
        ]);
    }

    /**
     * Obtiene input JSON
     */
    protected function getJsonInput()
    {
        return json_decode(file_get_contents('php://input'), true) ?? [];
    }

    /**
     * Envía respuesta JSON y termina ejecución
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
     * Helper para obtener información de agencia
     */
    protected function getAgenciaInfo($idAgencia)
    {
        $this->database->openConnection();
        $agencia = $this->database->selectColumns(
            'tb_agencia',
            ['id_agencia', 'cod_agenc', 'nom_agencia'],
            'id_agencia = ?',
            [$idAgencia]
        );
        $this->database->closeConnection();

        return $agencia[0] ?? null;
    }

    /**
     * Helper para obtener información de usuario
     */
    protected function getUserInfo($idUsuario)
    {
        $this->database->openConnection();
        $usuario = $this->database->selectColumns(
            'tb_usuario',
            ['id_usu', 'nombre', 'apellido', 'puesto'],
            'id_usu = ?',
            [$idUsuario]
        );
        $this->database->closeConnection();

        return $usuario[0] ?? null;
    }

    // ============================================================================
    // MÉTODOS SIMPLIFICADOS PARA PLANTILLAS PDF Y EXCEL
    // ============================================================================

    /**
     * Genera el encabezado del PDF (puede sobrescribirse en clases hijas)
     * 
     * @param FpdfExtend $pdf Objeto PDF
     * @param string $titulo Título del reporte
     * @param array $filtros Filtros aplicados
     * @param array $info Información institucional
     * @param array $session Sesión del usuario
     * @param string $orientacion Orientación del documento
     * @param callable|null $headerAdd Función adicional para el header
     * @return void
     */
    public function generarHeaderPDF($pdf, $titulo, $filtros, $info, $session, $orientacion, $headerAdd = null)
    {
        // Ancho dinámico según orientación (Letter: 216mm vertical, 279mm horizontal)
        $pageWidth = $orientacion === 'L' ? 279 : 216;
        $rightMargin = $orientacion === 'L' ? 220 : 150;
        $lineEnd = $orientacion === 'L' ? 269 : 206;

        // Línea decorativa superior
        $pdf->SetFillColor(41, 128, 185);
        $pdf->Rect(0, 0, $pageWidth, 3, 'F');

        // Fondo encabezado
        $pdf->SetFillColor(236, 240, 241);
        $pdf->Rect(0, 3, $pageWidth, 45, 'F');

        // Fecha y usuario
        $pdf->SetFont('Arial', '', 7);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->SetXY($rightMargin, 6);
        $pdf->Cell(50, 3, 'Generado: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
        $pdf->SetX($rightMargin);
        $pdf->Cell(50, 3, 'Usuario: ' . ($session['nombre'] ?? 'Sistema'), 0, 1, 'R');

        // Logo
        $rutaLogo = __DIR__ . '/../../' . $info['log_img'];
        if (file_exists($rutaLogo)) {
            $pdf->Image($rutaLogo, 15, 12, 35);
        }

        // Información institución
        $pdf->SetTextColor(41, 128, 185);
        $pdf->SetFont('Arial', 'B', 13);
        $pdf->SetXY(55, 14);
        $pdf->Cell(0, 5, Utf8::decode($info['nomb_comple']), 0, 1, 'L');

        $pdf->SetTextColor(52, 73, 94);
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetX(55);
        $pdf->Cell(0, 4, Utf8::decode($info['nom_agencia']), 0, 1, 'L');

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(127, 140, 141);
        $pdf->SetX(55);
        $pdf->Cell(0, 3.5, Utf8::decode($info['muni_lug']), 0, 1, 'L');

        $pdf->SetX(55);
        $pdf->Cell(0, 3.5, 'Email: ' . $info['emai'] . ' | Tel: ' . $info['tel_1'], 0, 1, 'L');

        $pdf->SetX(55);
        $pdf->Cell(0, 3.5, 'NIT: ' . $info['nit'], 0, 1, 'L');

        $pdf->Ln(3);

        // Título del reporte
        $pdf->SetFillColor(41, 128, 185);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->Cell(0, 8, Utf8::decode($titulo), 0, 1, 'C', true);

        // Filtros
        if (!empty($filtros)) {
            $pdf->Ln(5);
            $pdf->SetFillColor(149, 165, 166);
            $pdf->SetFont('Arial', 'B', 9);
            $texto = [];
            foreach ($filtros as $label => $valor) {
                $texto[] = $label . ': ' . $valor;
            }
            $pdf->Cell(0, 6, Utf8::decode(implode(' | ', $texto)), 0, 1, 'C', true);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont($this->fonte, 'B', $this->sizeFont);

        // Llamar función adicional si existe
        if ($headerAdd !== null && is_callable($headerAdd)) {
            try {
                $pdf->Ln(5);
                call_user_func($headerAdd, $pdf);
            } catch (Exception $e) {
                error_log("Error ejecutando headerAdd: " . $e->getMessage());
            }
        }
    }

    /**
     * Genera el pie de página del PDF (puede sobrescribirse en clases hijas)
     * 
     * @param FpdfExtend $pdf Objeto PDF
     * @param string $orientacion Orientación del documento
     * @return void
     */
    public function generarFooterPDF($pdf, $orientacion)
    {
        // Ancho dinámico según orientación
        $pageWidth = $orientacion === 'L' ? 279 : 216;
        $lineEnd = $orientacion === 'L' ? 269 : 206;

        $pdf->SetY(-13);
        $pdf->SetDrawColor(41, 128, 185);
        $pdf->SetLineWidth(0.5);
        $pdf->Line(10, $pdf->GetY(), $lineEnd, $pdf->GetY());

        $pdf->SetY(-12);
        $pdf->SetFillColor(236, 240, 241);
        $pdf->Rect(0, $pdf->GetY(), $pageWidth, 17, 'F');

        $pdf->SetY(-9);
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetTextColor(52, 73, 94);
        $pdf->Cell(0, 4, Utf8::decode('Página ') . $pdf->PageNo() . ' de {nb}', 0, 0, 'C');
    }

    /**
     * Genera plantilla PDF completa (encabezado + cuerpo + pie)
     * Solo necesitas pasar el título y un callback para el cuerpo
     * 
     * Para personalizar el encabezado o pie, sobrescribe los métodos:
     * - generarHeaderPDF() en tu clase hija
     * - generarFooterPDF() en tu clase hija
     * 
     * @param string $titulo Título del reporte
     * @param callable $cuerpoPDF Función que recibe ($pdf, $datos) y genera el cuerpo
     * @param callable|null $headerAdd Función adicional para agregar al header ($pdf) (opcional)
     * @param array $datos Datos del reporte
     * @param array $filtros Filtros aplicados (opcional, para mostrar en encabezado)
     * @return array Respuesta JSON con PDF en base64
     */
    protected function generarPlantillaPDF($titulo, callable $cuerpoPDF, $headerAdd = null, array $datos = [], array $filtros = [], $orientacion = 'P')
    {
        $showmensaje = false;
        try {
            $info = $this->getInfoInstitucion();
            $controller = $this; // Referencia al controlador para usar en la clase anónima

            // Crear clase PDF anónima con encabezado y pie
            $pdf = new class($titulo, $filtros, $info, $this->session, $headerAdd, $orientacion, $this->fonte, $this->sizeFont, $controller) extends FpdfExtend {
                private $titulo;
                private $filtros;
                private $info;
                private $session;
                private $headerAdd;
                private $orientacion;
                private $fonte;
                private $sizeFont;
                private $controller;

                public function __construct($titulo, $filtros, $info, $session, $headerAdd, $orientacion = 'P', $fonte = 'Courier', $sizeFont = 10, $controller = null)
                {
                    $this->titulo = $titulo;
                    $this->filtros = $filtros;
                    $this->info = $info;
                    $this->session = $session;
                    $this->headerAdd = $headerAdd;
                    $this->orientacion = $orientacion;
                    $this->fonte = $fonte;
                    $this->sizeFont = $sizeFont;
                    $this->controller = $controller;
                    parent::__construct($orientacion, 'mm', 'Letter');
                }

                function Header()
                {
                    // Delegar al método del controlador si existe
                    if ($this->controller !== null) {
                        $this->controller->generarHeaderPDF(
                            $this,
                            $this->titulo,
                            $this->filtros,
                            $this->info,
                            $this->session,
                            $this->orientacion,
                            $this->headerAdd
                        );
                    }
                }

                function Footer()
                {
                    // Delegar al método del controlador si existe
                    if ($this->controller !== null) {
                        $this->controller->generarFooterPDF($this, $this->orientacion);
                    }
                }
            };

            $pdf->AliasNbPages();
            $pdf->AddPage();
            $pdf->SetAutoPageBreak(true, 15);

            // Llamar al callback para generar el cuerpo
            $cuerpoPDF($pdf, $datos);

            // Generar respuesta (Output('S') retorna string, no envía al navegador)
            $pdfData = $pdf->Output('S');

            $nombreArchivo = strtolower(str_replace(' ', '_', $titulo)) . '_' . date('YmdHis');

            return [
                'status' => 1,
                'mensaje' => 'Reporte generado correctamente',
                'namefile' => $nombreArchivo,
                'tipo' => 'pdf',
                'data' => 'data:application/pdf;base64,' . base64_encode($pdfData)
            ];
        } catch (Exception $e) {
            // No usar Log::error aquí porque puede causar output antes del JSON
            // @error_log("Error generando plantilla PDF para reporte: $titulo - " . $e->getMessage());
            // return [
            //     'status' => 0,
            //     'mensaje' => 'Error generando el reporte PDF: ' . $e->getMessage()
            // ];
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    /**
     * Genera plantilla Excel completa (encabezado + cuerpo)
     * Solo necesitas pasar el título y un callback para el cuerpo
     * 
     * @param string $titulo Título del reporte
     * @param callable $cuerpoExcel Función que recibe ($sheet, $row, $datos) y retorna última fila
     * @param array $datos Datos del reporte
     * @param array $filtros Filtros aplicados (opcional, para mostrar en encabezado)
     * @return array Respuesta JSON con Excel en base64
     */
    protected function generarPlantillaExcel($titulo, callable $cuerpoExcel, array $datos, array $filtros = [])
    {
        $showmensaje = false;
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            $info = $this->getInfoInstitucion();

            // Configurar documento
            $spreadsheet->getProperties()
                ->setCreator($info['nomb_comple'])
                ->setTitle($titulo)
                ->setSubject('Reporte del Sistema')
                ->setCategory('Reportes');

            $row = 1;

            // Encabezado profesional
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", $info['nomb_comple']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(16);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $row++;

            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", $info['nom_agencia']);
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $row++;

            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", $info['muni_lug']);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $row++;

            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", "Email: {$info['emai']} | Tel: {$info['tel_1']} | NIT: {$info['nit']}");
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $row++;

            $row++;

            // Título
            $sheet->mergeCells("A{$row}:F{$row}");
            $sheet->setCellValue("A{$row}", strtoupper($titulo));
            $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
            $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF3498DB');
            $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
            $row++;

            // Filtros
            if (!empty($filtros)) {
                $sheet->mergeCells("A{$row}:F{$row}");
                $texto = [];
                foreach ($filtros as $label => $valor) {
                    $texto[] = $label . ': ' . $valor;
                }
                $sheet->setCellValue("A{$row}", implode(' | ', $texto));
                $sheet->getStyle("A{$row}")->getFont()->setBold(true);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("A{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FF95A5A6');
                $sheet->getStyle("A{$row}")->getFont()->getColor()->setARGB('FFFFFFFF');
                $row++;
            }

            $row += 2;

            // Llamar al callback para generar el cuerpo
            $lastRow = $cuerpoExcel($sheet, $row, $datos);

            // Autoajustar columnas
            foreach (range('A', 'Z') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Generar archivo
            $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');

            ob_start();
            $writer->save('php://output');
            $xlsData = ob_get_contents();
            ob_end_clean();

            $nombreArchivo = strtolower(str_replace(' ', '_', $titulo)) . '_' . date('YmdHis');

            return [
                'status' => 1,
                'mensaje' => 'Reporte generado correctamente',
                'namefile' => $nombreArchivo,
                'tipo' => 'xlsx',
                'data' => 'data:application/vnd.openxmlformats-officedocument.spreadsheetml.sheet;base64,' . base64_encode($xlsData)
            ];
        } catch (Exception $e) {
            // return [
            //     'status' => 0,
            //     'mensaje' => 'Error cargando la librería de Excel: ' . $e->getMessage()
            // ];
            $showmensaje = ($showmensaje || $e->getCode() == 210398);
            $codigoDevuelto = ($showmensaje) ? 210398 : $e->getCode();
            if (!$showmensaje) {
                $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? $e->getMessage() : "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje, $codigoDevuelto);
        }
    }

    // ============================================================================
    // MÉTODOS PARA COMPROBANTES Y DOCUMENTOS PERSONALIZADOS
    // ============================================================================

    /**
     * Crea un objeto PDF base sin encabezado institucional
     * Útil para comprobantes, recibos y documentos con diseño personalizado
     * 
     * @param string $orientacion 'P' = Portrait (vertical), 'L' = Landscape (horizontal)
     * @param string $tamano 'Letter', 'Legal', 'A4', etc.
     * @return \FPDF Objeto PDF listo para personalizar
     * 
     * @example
     * $pdf = $this->crearPDFBase('P', 'Letter');
     * $pdf->AddPage();
     * $pdf->SetFont('Courier', 'B', 14);
     * $pdf->Cell(0, 10, 'MI COMPROBANTE PERSONALIZADO', 0, 1, 'C');
     */
    protected function crearPDFBase($orientacion = 'P', $tamano = 'Letter'): FpdfExtend
    {
        // require_once __DIR__ . '/../../fpdf/fpdf.php';
        // require_once __DIR__ . '/../../fpdf/pdf_js.php';
        // require_once __DIR__ . '/../../fpdf/fpdf_js.php';
        // require_once __DIR__ . '/../Helpers/FpdfExtend.php';

        $pdf = new FpdfExtend($orientacion, 'mm', $tamano);
        $pdf->SetAutoPageBreak(true, 10);

        return $pdf;
    }

    /**
     * Genera encabezado institucional para comprobantes
     * Útil cuando quieres el encabezado estándar pero control total del cuerpo
     * 
     * @param \FPDF $pdf Objeto PDF
     * @param string $titulo Título del documento (ej: 'COMPROBANTE DE PAGO')
     * @param array $info Información institucional (opcional, se obtiene automáticamente si no se pasa)
     * @return void
     * 
     * @example
     * $pdf = $this->crearPDFBase();
     * $pdf->AddPage();
     * $this->generarEncabezadoInstitucional($pdf, 'RECIBO DE PAGO');
     * // Aquí continúas con tu contenido personalizado
     */
    protected function generarEncabezadoInstitucional($pdf, $titulo, $info = null)
    {
        if ($info === null) {
            $info = $this->getInfoInstitucion();
        }

        // Logo o nombre de la institución
        $pdf->SetFont($this->fonte, 'B', 14);
        $pdf->Cell(0, 6, Utf8::decode($info['nomb_comple']), 0, 1, 'C');

        $pdf->SetFont($this->fonte, '', 10);
        $pdf->Cell(0, 5, Utf8::decode($info['nom_agencia']), 0, 1, 'C');

        $pdf->SetFont($this->fonte, '', 9);
        $pdf->Cell(0, 4, Utf8::decode($info['muni_lug']), 0, 1, 'C');
        $pdf->Cell(0, 4, "Tel: {$info['tel_1']} | NIT: {$info['nit']}", 0, 1, 'C');

        $pdf->Ln(3);

        // Línea separadora
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->Line(10, $pdf->GetY(), 200, $pdf->GetY());
        $pdf->Ln(2);

        // Título del documento
        $pdf->SetFont($this->fonte, 'B', 12);
        $pdf->Cell(0, 8, Utf8::decode($titulo), 0, 1, 'C');
        $pdf->Ln(2);
    }

    /**
     * Genera pie de página estándar para comprobantes
     * 
     * @param \FPDF $pdf Objeto PDF
     * @param string $textoAdicional Texto adicional opcional
     * @return void
     */
    protected function generarPieComprobante($pdf, $textoAdicional = null)
    {
        $pdf->SetY(-25);
        $pdf->SetFont($this->fonte, 'I', 8);
        $pdf->Cell(0, 4, Utf8::decode('Generado el ' . date('d/m/Y H:i:s')), 0, 1, 'C');

        if ($textoAdicional) {
            $pdf->Cell(0, 4, Utf8::decode($textoAdicional), 0, 1, 'C');
        }
    }
}
