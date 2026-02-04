<?php

namespace Micro\Controllers\Reportes\Contabilidad;

use Exception;
use Micro\Controllers\BaseReporteController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Helpers\Log;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LibroMayorController extends BaseReporteController
{
    protected $dataReporte = [];
    protected $widthsColumns = [
        'fecha' => 17,
        'partida' => 18,
        'descripcion' => 88,
        'debe' => 24,
        'haber' => 24,
        'saldo' => 24,
    ];
    protected $filtros = [];

    protected $sizeFont = 8;
    protected $sizeFontHeader = 8;
    protected $sizeFontBody = 8;
    protected $sizeLine = 4;
    protected $saldosIniciales = [];

    public function index()
    {
        try {
            // Log::info("data input recibida:", $this->input);

            $data = [
                'fecha_inicio' => $this->input['finicio'] ?? null,
                'fecha_fin' => $this->input['ffin'] ?? null,
                'codofi' => trim($this->input['codofi'] ?? ''),
                'fondoid' => trim($this->input['fondoid'] ?? ''),
                'cuenta_contable' => trim($this->input['cuenta_contable'] ?? ''),
                'saldo_inicial' => $this->input['incluirSaldoInicial'] ?? false,
                'radio_fondos' => trim($this->input['rfondos'] ?? ''),
                'radio_agencia' => trim($this->input['ragencia'] ?? ''),
                // 'radio_cuentas' => trim($this->input['rcuentas'] ?? ''),
                'tipo' => trim($this->input['tipo'] ?? 'show'),
            ];

            $rules = [
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date|after_or_equal:fecha_inicio',
                'radio_fondos' => 'required|string',
                'radio_agencia' => 'required|string',
                // 'radio_cuentas' => 'required|string',
                'codofi' => 'validate_if:radio_agencia,anyofi|required|integer|min:1|exists:tb_agencia,id_agencia',
                'fondoid' => 'validate_if:radio_fondos,anyf|required|integer|min:1|exists:ctb_fuente_fondos,id',
                'cuenta_contable' => 'required|min:1',
                'saldo_inicial' => 'boolean',
                'tipo' => 'in:pdf,xlsx,show',
            ];

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            $this->dataReporte = $data;

            if ($data['tipo'] === 'pdf' || $data['tipo'] === 'show') {
                $this->idDocument = 37;  // ID del reporte de comprobante de Renovación

                $this->generarReporteConFormato($data, function ($id) {
                    $this->generarFormatoGenericoPdf($id);
                });
            } elseif ($data['tipo'] === 'xlsx') {
                $this->reporteExcel();
            } else {
                throw new SoftException("Tipo de reporte no soportado.");
            }
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
    private function getData()
    {
        try {

            $this->database->openConnection();

            $nomenclatura = $this->database->selectColumns(
                'ctb_nomenclatura',
                ['id', 'ccodcta', 'cdescrip'],
                'id=?',
                [$this->dataReporte['cuenta_contable']]
            );

            $cuentaCodigo = $nomenclatura[0]['ccodcta'];
            $longitudcuenta = strlen($cuentaCodigo);

            /**
             * AGREGAMOS LA CONDICION AL WHERE
             * AGREGAMOS EL FILTRO PARA MOSTRAR EN EL REPORTE
             * AGREGAMOS EL PARAMETRO PARA LA CONSULTA
             */
            // $where .=  " AND substr(ccodcta,1,$longitudcuenta)=?";
            $this->filtros['Cuenta Contable'] = $nomenclatura[0]['ccodcta'] . ' - ' . Utf8::decode($nomenclatura[0]['cdescrip']);
            // $parameters = array_merge($parameters, [$cuentaCodigo]);


            $where = ($this->dataReporte['radio_agencia']  == "anyofi") ? " AND id_agencia=?" :  "";
            $where .= ($this->dataReporte['radio_fondos'] == "anyf") ? " AND id_fuente_fondo=?" : "";

            $parameters = [$this->dataReporte['fecha_inicio'], $this->dataReporte['fecha_fin'], $cuentaCodigo];

            $parameters = ($this->dataReporte['radio_agencia'] == "anyofi") ? array_merge($parameters, [$this->dataReporte['codofi']]) : $parameters;
            $parameters = ($this->dataReporte['radio_fondos'] == "anyf") ? array_merge($parameters, [$this->dataReporte['fondoid']]) : $parameters;


            $query = "SELECT dia.numdoc,mov.id_ctb_nomenclatura,cue.ccodcta,cue.cdescrip,dia.feccnt,dia.numcom,dia.glosa,mov.debe,mov.haber,mov.id_ctb_diario,IFNULL(chq.nomchq ,'-') AS nombrecheque
                FROM ctb_diario dia 
                INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                INNER JOIN ctb_nomenclatura cue ON mov.id_ctb_nomenclatura=cue.id
                INNER JOIN ctb_fuente_fondos fon ON fon.id=mov.id_fuente_fondo
                LEFT JOIN ctb_chq chq ON chq.id_ctb_diario=dia.id
                WHERE dia.estado=1 AND feccnt BETWEEN ? AND ? AND substr(ccodcta,1,$longitudcuenta)=? $where 
                ORDER BY mov.id_ctb_nomenclatura,dia.feccnt,dia.id";
            $datos = $this->database->getAllResults($query, $parameters);
            if (empty($datos)) {
                throw new SoftException("No se encontraron datos para los filtros seleccionados.");
            }

            $idsCuentas = array_column($datos, 'id_ctb_nomenclatura');
            $idsCuentas = array_unique($idsCuentas);
            if ($this->dataReporte['saldo_inicial']) {

                /**
                 * BUSCAR LA PARTIDA DE APERTURA EN EL AÑO DE LA FECHA DE INICIO
                 * SI LA FECHA DE INICIO ES EL PRIMERO DE ENERO DEL AÑO, NO HAY NECESIDAD DE BUSCAR NADA
                 * SI NO HAY PARTIDA DE APERTURA, SE USA EL 1 DE ENERO DEL AÑO COMO FECHA INICIAL PARA EL SALDO
                 */
                $fechaInicio = new \DateTime($this->dataReporte['fecha_inicio']);
                $fechaInicioSaldo = $fechaInicio->format('Y') . '-01-01';

                // Solo buscar partida de apertura si la fecha de inicio no es el primero de enero
                if ($this->dataReporte['fecha_inicio'] !== $fechaInicioSaldo) {
                    $partidaApertura = $this->database->selectColumns(
                        'ctb_diario',
                        ['id', 'feccnt'],
                        'estado=1 AND id_ctb_tipopoliza=9 AND YEAR(feccnt)=? ORDER BY feccnt DESC LIMIT 1',
                        [$fechaInicio->format('Y')]
                    );

                    if (!empty($partidaApertura)) {
                        $fechaInicioSaldo = $partidaApertura[0]['feccnt'];
                    }

                    foreach ($idsCuentas as $idCuenta) {

                        //CALCULAR SALDO INICIAL
                        $querySaldo = "SELECT SUM(mov.debe) AS sumdebe, SUM(mov.haber) AS sumhaber
                                        FROM ctb_diario dia 
                                        INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                                        WHERE dia.estado=1 AND feccnt>=? AND feccnt < ? AND mov.id_ctb_nomenclatura=?";
                        $saldoData = $this->database->getAllResults($querySaldo, [$fechaInicioSaldo, $this->dataReporte['fecha_inicio'], $idCuenta]);
                        $sumdebe = $saldoData[0]['sumdebe'] ?? 0;
                        $sumhaber = $saldoData[0]['sumhaber'] ?? 0;
                        $saldoInicial = $sumdebe - $sumhaber;

                        $this->saldosIniciales[$idCuenta] = $saldoInicial;
                    }
                }
            }

            return $datos;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }

    /**
     * Obtiene el título del reporte (puede sobrescribirse)
     */
    protected function getTitulo()
    {
        return 'LIBRO MAYOR DEL ' .
            Date::toDMY($this->dataReporte['fecha_inicio']) . ' AL ' . Date::toDMY($this->dataReporte['fecha_fin']);
    }

    protected function generarFormatoGenericoPdf($data)
    {
        try {

            $datos = $this->getData();

            $title = $this->getTitulo();

            $headerExtra = function ($pdf) {
                $this->addHeaderExtraPDF($pdf);
            };

            $bodyFunc = function ($pdf, $datos) {
                $this->bodyPDF($pdf, $datos);
            };

            // 3. Generar PDF con plantilla
            $response = $this->generarPlantillaPDF(
                $title,
                $bodyFunc,
                $headerExtra,
                $datos,
                $this->filtros
            );

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;

            // return $this->jsonResponse([
            //     'status' => 1,
            //     'mensaje' => 'Comprobante generado exitosamente',
            //     'namefile' => $nombreArchivo,
            //     'tipo' => 'pdf',
            //     'data' => base64_encode($pdfData)
            // ]);
        } catch (SoftException $se) {
            Log::error("Error soft generando comprobante: " . $se->getMessage());
            $this->jsonResponse([
                'status' => 0,
                'mensaje' => $se->getMessage()
            ]);
        }
    }

    /**
     * Genera reporte Excel con plantilla base
     */
    public function reporteExcel()
    {
        try {
            // 1. Obtener datos
            $datos = $this->getData();

            $title = $this->getTitulo();
            $bodyFunc = function ($sheet, $row, $datos,) {
                return $this->bodyExcel($sheet, $row, $datos);
            };
            // 3. Generar Excel con plantilla
            $response = $this->generarPlantillaExcel(
                $title,
                $bodyFunc,
                $datos,
            );

            header('Content-Type: application/json');
            echo json_encode($response);
            exit;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }

    protected function bodyExcel($sheet, $row, $datos)
    {
        // Configurar título de la hoja
        $sheet->setTitle("Libro Mayor");

        // Configurar anchos de columnas
        $sheet->getColumnDimension("A")->setWidth(15);  // CUENTA
        $sheet->getColumnDimension("B")->setWidth(25);  // NOMBRE CUENTA
        $sheet->getColumnDimension("C")->setWidth(15);  // FECHA
        $sheet->getColumnDimension("D")->setWidth(10);  // PARTIDA
        $sheet->getColumnDimension("E")->setWidth(70);  // DESCRIPCIÓN
        $sheet->getColumnDimension("F")->setWidth(15);  // DEBE
        $sheet->getColumnDimension("G")->setWidth(15);  // HABER
        $sheet->getColumnDimension("H")->setWidth(15);  // SALDO
        $sheet->getColumnDimension("I")->setWidth(25);  // NOMBRE CHEQUE
        $sheet->getColumnDimension("J")->setWidth(15);  // NUMDOC

        // Encabezados de la tabla
        $sheet->setCellValue('A' . $row, 'CUENTA');
        $sheet->setCellValue('B' . $row, 'NOMBRE CUENTA');
        $sheet->setCellValue('C' . $row, 'FECHA');
        $sheet->setCellValue('D' . $row, 'PARTIDA');
        $sheet->setCellValue('E' . $row, 'DESCRIPCIÓN');
        $sheet->setCellValue('F' . $row, 'DEBE');
        $sheet->setCellValue('G' . $row, 'HABER');
        $sheet->setCellValue('H' . $row, 'SALDO');
        $sheet->setCellValue('I' . $row, 'NOMBRE CHEQUE');
        $sheet->setCellValue('J' . $row, 'NUMDOC');
        // Variables de control
        $row++;
        $header = true;
        $footer = false;
        $sumd = 0;
        $sumh = 0;
        $saldo = 0;
        $sumtd = 0;
        $sumth = 0;

        foreach ($datos as $key => $registro) {

            if ($header) {
                // Verificar si tiene saldo inicial
                $saldo = $this->saldosIniciales[$registro['id_ctb_nomenclatura']] ?? 0;

                $row++;
                $sumd = 0;
                $sumh = 0;

                // Encabezado de cuenta con estilo
                $sheet->getStyle('A' . $row . ':B' . $row)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('CCCCCC');
                $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);

                $sheet->setCellValueExplicit('A' . $row, $registro['ccodcta'], DataType::TYPE_STRING);
                $sheet->setCellValue('B' . $row, $registro['cdescrip']);

                // Saldo anterior
                $sheet->setCellValueExplicit('E' . ($row - 1), 'SALDO ANT.:', DataType::TYPE_STRING);
                $sheet->setCellValue('H' . ($row - 1), $saldo);
                $header = false;
            }

            // Datos de la fila
            $sheet->setCellValue('C' . $row, Date::toDMY($registro['feccnt']));
            $sheet->setCellValueExplicit('D' . $row, $registro['numcom'], DataType::TYPE_STRING);
            $sheet->setCellValue('E' . $row, $registro['glosa']);
            $sheet->setCellValue('F' . $row, $registro['debe']);
            $sheet->setCellValue('G' . $row, $registro['haber']);
            $sheet->setCellValue('I' . $row, $registro['nombrecheque']);
            $sheet->setCellValueExplicit('J' . $row, ($registro['numdoc'] ?? ''), DataType::TYPE_STRING);

            // Calcular saldo con fórmula
            $sheet->setCellValue('H' . $row, '=H' . ($row - 1) . '+ F' . $row . '-G' . $row);
            // Acumular totales
            $sumd += $registro['debe'];
            $sumh += $registro['haber'];
            $sumtd += $registro['debe'];
            $sumth += $registro['haber'];

            // Verificar si es el final de una cuenta o el final de los datos
            if ($key != array_key_last($datos)) {
                if ($registro['id_ctb_nomenclatura'] != $datos[$key + 1]["id_ctb_nomenclatura"]) {
                    $header = true;
                    $footer = true;
                }
            } else {
                $footer = true;
            }

            if ($footer) {
                $row++;
                // Resumen de cuenta
                $sheet->setCellValue('E' . $row, 'RESUMEN CUENTA');
                $sheet->setCellValue('F' . $row, $sumd);
                $sheet->setCellValue('G' . $row, $sumh);
                $sheet->getStyle('E' . $row . ':G' . $row)->getFont()->setBold(true);

                $footer = false;
            }

            $row++;
        }

        // Total general
        $row++;
        $sheet->setCellValue('E' . $row, 'TOTAL GENERAL:');
        $sheet->setCellValue('F' . $row, $sumtd);
        $sheet->setCellValue('G' . $row, $sumth);
        $sheet->getStyle('E' . $row . ':G' . $row)->getFont()->setBold(true);

        // Número de registros
        $row += 2;
        $sheet->setCellValue('A' . $row, 'Número de Registros:');
        $sheet->setCellValue('B' . $row, count($datos));
        $sheet->getStyle('A' . $row . ':B' . $row)->getFont()->setBold(true);

        return $row;
    }

    /**
     * Este metodo es para agregar datos que tienen que salir en el header del documento. Aparte del membrete
     * @param mixed $pdf
     * @return void
     */
    protected function addHeaderExtraPDF($pdf)
    {
        $tamanio_linea = 6;

        //Fuente
        $pdf->SetFont('Courier', 'B', $this->sizeFontHeader);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->CellFit($this->widthsColumns['fecha'], $tamanio_linea, 'FECHA', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['partida'], $tamanio_linea, 'PARTIDA', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['descripcion'], $tamanio_linea, Utf8::decode('DESCRIPCIÓN'), 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['debe'], $tamanio_linea, 'DEBE', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['haber'], $tamanio_linea, 'HABER', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['saldo'], $tamanio_linea, 'SALDO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->Ln(7);
    }

    protected function bodyPDF($pdf, $datos)
    {
        $pdf->SetFont($this->fonte, '', $this->sizeFontBody);

        $header = true;

        $footer = false;

        $sumd = 0;
        $sumh = 0;
        $saldo = 0;
        $sumtd = 0;
        $sumth = 0;

        foreach ($datos as $key => $row) {

            if ($header) {
                //ENCABEZADOS CUENTAS INDIVIDUALES
                $pdf->SetFont($this->fonte, 'B', $this->sizeFontBody);
                $pdf->CellFit($this->widthsColumns['fecha'] + $this->widthsColumns['partida'], $this->sizeLine, 'Cuenta: ' . $row['ccodcta'], 'B', 0, 'L', 0, '', 1, 0);
                $pdf->CellFit($this->widthsColumns['descripcion'] + $this->widthsColumns['debe'], $this->sizeLine, 'Nombre: ' . Utf8::decode($row['cdescrip']), 'B', 0, 'L', 0, '', 1, 0);

                $saldo = $this->saldosIniciales[$row['id_ctb_nomenclatura']] ?? 0;

                $pdf->CellFit($this->widthsColumns['haber'] + $this->widthsColumns['saldo'], $this->sizeLine, 'Saldo Ant.:' . Moneda::formato($saldo), 'B', 1, 'R', 0, '', 1, 0);
                $header = false;
            }

            //DETALLES PARTIDAS INDIVIDUALES
            $pdf->SetFont($this->fonte, '', $this->sizeFontBody);
            $pdf->CellFit($this->widthsColumns['fecha'], $this->sizeLine, Date::toDMY($row['feccnt']), '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['partida'], $this->sizeLine, $row['numcom'], '', 0, 'L', 0, '', 1, 0);

            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->MultiCell($this->widthsColumns['descripcion'], $this->sizeLine, Utf8::decode($row['glosa'] . ' - ' . $row['numdoc']));
            $x += $this->widthsColumns['descripcion'];
            $y2 = $pdf->GetY();
            if ($y > $y2) {
                $y3 = 3;
                $y = $y2;
            } else {
                $y3 = $y2 - $y;
            }
            $pdf->SetXY($x, $pdf->GetY() - $y3);
            $pdf->CellFit($this->widthsColumns['debe'], $this->sizeLine, number_format($row['debe'], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['haber'], $this->sizeLine, number_format($row['haber'], 2, '.', ','), '', 0, 'R', 0, '', 1, 0);
            //SALDO 
            $sumd = $sumd + $row['debe'];
            $sumh = $sumh + $row['haber'];
            $saldo = $saldo + $row['debe'] - $row['haber'];
            $pdf->CellFit($this->widthsColumns['saldo'], $this->sizeLine, number_format($saldo, 2, '.', ','), '', 1, 'R', 0, '', 1, 0);
            $pdf->SetY($y + $y3);

            $sumtd = $sumtd + $row['debe'];
            $sumth = $sumth + $row['haber'];

            if ($key != array_key_last($datos)) {
                if ($row['id_ctb_nomenclatura'] != $datos[$key + 1]["id_ctb_nomenclatura"]) {
                    $header = true;
                    $footer = true;
                }
            } else {
                $footer = true;
            }
            if ($footer) {
                $pdf->Ln(1);
                $pdf->Cell($this->widthsColumns['fecha'] + $this->widthsColumns['partida'] + $this->widthsColumns['descripcion'], $this->sizeLine, ' ', '', 0, 'R');
                $pdf->Cell($this->widthsColumns['debe'], $this->sizeLine, number_format($sumd, 2, '.', ','), 'BT', 0, 'R');
                $pdf->Cell($this->widthsColumns['haber'], $this->sizeLine, number_format($sumh, 2, '.', ','), 'BT', 1, 'R');
                $pdf->Cell($this->widthsColumns['fecha'] + $this->widthsColumns['partida'] + $this->widthsColumns['descripcion'], $this->sizeLine / 3, ' ', '', 0, 'R');
                $pdf->Cell($this->widthsColumns['debe'] + $this->widthsColumns['haber'], $this->sizeLine / 3, ' ', 'B', 1, 'R');
                $sumd = 0;
                $sumh = 0;
                $pdf->Ln(5);
                $footer = false;
            }
        }

        $pdf->Cell($this->widthsColumns['fecha'] + $this->widthsColumns['partida'] + $this->widthsColumns['descripcion'], $this->sizeLine, 'TOTAL GENERAL: ', '', 0, 'R');
        $pdf->Cell($this->widthsColumns['debe'], $this->sizeLine, number_format($sumtd, 2, '.', ','), 'BT', 0, 'R');
        $pdf->Cell($this->widthsColumns['haber'], $this->sizeLine, number_format($sumth, 2, '.', ','), 'BT', 1, 'R');
        $pdf->Cell($this->widthsColumns['fecha'] + $this->widthsColumns['partida'] + $this->widthsColumns['descripcion'], $this->sizeLine / 3, ' ', '', 0, 'R');
        $pdf->Cell($this->widthsColumns['debe'] + $this->widthsColumns['haber'], $this->sizeLine / 3, ' ', 'B', 1, 'R');
        $pdf->SetFont($this->fonte, 'B', $this->sizeFontBody);
        $pdf->Ln(10);
        $pdf->Cell(0, 4, 'Numero de Registros: ' . count($datos), 0, 1, 'C');
    }
}
