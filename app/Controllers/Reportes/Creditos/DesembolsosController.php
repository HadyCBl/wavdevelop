<?php

namespace Micro\Controllers\Reportes\Creditos;

use App\Generic\Models\TipoDocumentoTransaccion;
use Micro\Controllers\BaseReporteController;
use Micro\Helpers\Log;
use Exception;
use Micro\Exceptions\SoftException;
use Micro\Generic\Date;
use Micro\Generic\Moneda;
use Micro\Generic\Utf8;
use Micro\Generic\Validator;
use Micro\Helpers\Beneq;

/**
 * Controlador de Reportes de Créditos
 */
class DesembolsosController extends BaseReporteController
{
    /**
     * Reporte de creditos desembolsados
     * POST /api/reportes/creditos/desembolsos
     */
    protected $widthsColumns = [
        'credito' => 25,
        'nombre_cliente' => 50,
        'solicitado' => 18,
        'aprobado' => 18,
        'desembolsado' => 20,
        'gastos' => 16,
        'tip_doc' => 20,
        'fecha_solicitud' => 20,
        'fecha_desembolso' => 20,
        'responsable' => 50,
    ];
    protected $filtros = [];
    protected $dataReporte = [];
    protected $sizeFont = 8;
    public function desembolsos()
    {

        Log::info("inputs", $this->input);
        /*inputs {"fechaInicio":"2025-12-22","fechaFinal":"2025-12-22","filter_credito":"ALL",
        "estado":"FG","ragencia":"anyofi","regionid":"0","codofi":"1","codanal":"4",
        "csrf_token":"2d50109ef0f3bef0dcfdaa998da0978988601cf3780769b95cd12ca538dab9be","tipo":"show"}
        */
        $status = false;
        try {
            $data = [
                'fecha_inicio' => $this->input['fechaInicio'] ?? null,
                'fecha_fin' => $this->input['fechaFinal'] ?? null,
                'filter_credito' => trim($this->input['filter_credito'] ?? ''),
                'estado' => trim($this->input['estado'] ?? ''),
                'radio_agencia' => trim($this->input['ragencia'] ?? ''),
                'creRegionSelect' => trim($this->input['regionid'] ?? ''),
                'creAgenciaSelect' => trim($this->input['codofi'] ?? ''),
                'creUserSelect' => trim($this->input['codanal'] ?? ''),
            ];

            $rules = [
                'fecha_inicio' => 'required|date',
                'fecha_fin' => 'required|date',
                'filter_credito' => 'required|string|in:ALL,INDI,GRUP',
                'estado' => 'required|string',
                'radio_agencia' => 'required|string',
            ];

            if ($data['radio_agencia'] == 'anyofi') {
                $rules['creAgenciaSelect'] = 'required|integer|min:1|exists:tb_agencia,id_agencia';
            }
            if ($data['radio_agencia'] == 'anyasesor') {
                $rules['creUserSelect'] = 'required|integer|min:1|exists:tb_usuario,id_usu';
            }
            if ($data['radio_agencia'] == 'anyregion') {
                $rules['creRegionSelect'] = 'required|integer|min:1|exists:cre_regiones,id';
            }

            $messages = [
                'creRegionSelect.min' => 'Seleccione una región válida.',
            ];

            // Log::debug('Validando datos de entrada para reporte de ingresos diarios', $data);

            $validator = Validator::make($data, $rules, $messages);
            if ($validator->fails()) {
                $firstError = $validator->firstOnErrors();
                throw new SoftException($firstError);
            }

            if ($data['fecha_inicio'] > $data['fecha_fin']) {
                throw new SoftException('La fecha de inicio no puede ser mayor a la fecha final.');
            }

            $this->dataReporte = $data;

            $tipo = $this->input['tipo'] ?? 'pdf';
            if ($tipo === 'pdf' || $tipo === 'show') {
                $this->reportePDF();
            } elseif ($tipo === 'xlsx') {
                $this->reporteExcel();
            } elseif ($tipo === 'json') {
                $this->reporteJSON();
            } else {
                throw new SoftException('Tipo de reporte no soportado.');
            }
            $status = true;
        } catch (SoftException $se) {
            $mensaje = "Advertencia: " . $se->getMessage();
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
        }
        if (!$status) {
            echo json_encode([
                'status' => 0,
                'mensaje' => $mensaje
            ]);
            exit;
        }
    }
    /**
     * Obtiene datos de ejemplo
     */
    private function getData()
    {
        try {
            $this->database->openConnection();
            $where = ($this->dataReporte['filter_credito'] == "ALL") ? "" : " AND cm.TipoEnti='" . $this->dataReporte['filter_credito'] . "' ";
            $where .= ($this->dataReporte['radio_agencia'] == "anyofi") ? " AND ag.id_agencia=" . $this->dataReporte['creAgenciaSelect'] : "";
            $where .= ($this->dataReporte['radio_agencia'] == "anyasesor") ? " AND us.id_usu=" . $this->dataReporte['creUserSelect'] : "";
            $where .= ($this->dataReporte['radio_agencia'] == "anyregion") ? " AND ag.id_agencia IN (SELECT id_agencia FROM cre_regiones_agencias WHERE id_region=" . $this->dataReporte['creRegionSelect'] . ")" : "";
            $where .= ($this->dataReporte["estado"] == "FG") ? " AND (cm.Cestado= 'G' OR cm.Cestado='F') " : " AND cm.Cestado='" . $this->dataReporte["estado"] . "' ";

            $estadosKeyFecha = [
                "D" => "DFecAnal",
                "E" => "DFecApr",
                "F" => "DFecDsbls",
                "A" => "DfecSol",
                "G" => "fecha_operacion",
                "L" => "fecha_operacion",
                "X" => "fecha_operacion",
                "FG" => "DFecDsbls",
            ];

            $fechaFilter = $estadosKeyFecha[$this->dataReporte["estado"]] ?? 'fecha_operacion';
            $query = "SELECT 
                        cm.TipoEnti,
                        us.puesto, 
                        ag.id_agencia,
                        ag.cod_agenc,
                        cm.CCODCTA AS cuenta,
                        cm.CodCli AS codigocliente,  
                        cl.short_name AS nombre,
                        cm.Cestado AS estado,
                        DATE(cm.DFecSol) AS fecsolicitud,
                        IFNULL(cm.DFecDsbls,'-') AS fecdesembolsado,
                        cm.DFecVen AS fecvencimiento,
                        IFNULL(kar.dfecpro_ult, '-') AS fechaultimopago,
                        cm.MontoSol AS montosoli,
                        cm.MonSug AS montoaprobado,
                        cm.NCapDes AS montodesembolsado,
                        IFNULL((SELECT CNUMING FROM CREDKAR WHERE CCODCTA=cm.CCODCTA AND CESTADO != 'X' AND CTIPPAG = 'D' limit 1), '-') AS numdoc_desembolso,
                        cm.TipDocDes AS tipo,
                        cm.DFecDsbls AS fecdes,
                        cm.NCiclo AS ciclo,
                        IFNULL(kar.sum_KP, 0) AS capital,
                        IFNULL(kar.sum_interes, 0) AS interes,
                        IFNULL(kar.sum_MORA, 0) AS mora,
                        IFNULL(kar.sum_AHOPRG_OTR, 0) AS otros_montos,

                        IFNULL((SELECT NombreGrupo 
                                FROM tb_grupo 
                                WHERE id_grupos=cm.CCodGrupo), ' ') AS NombreGrupo,
                        IFNULL((SELECT f.descripcion 
                                FROM ctb_fuente_fondos f 
                                INNER JOIN cre_productos c ON c.id_fondo=f.id 
                                WHERE c.id=cm.CCODPRD),' - ') AS fondesc,
                        IFNULL((SELECT f.id 
                                FROM ctb_fuente_fondos f 
                                INNER JOIN cre_productos c ON c.id_fondo=f.id 
                                WHERE c.id=cm.CCODPRD),' - ') AS fondoid,
                        IFNULL((SELECT descripcion 
                                FROM `jpxdcegu_bd_general_coopera`.`tb_cre_periodos` 
                                WHERE cod_msplus=cm.NtipPerC),' - ') AS frecuencia,
                        IFNULL((SELECT DestinoCredito 
                                FROM `jpxdcegu_bd_general_coopera`.`tb_destinocredito` 
                                WHERE id_DestinoCredito=cm.Cdescre),' - ') AS destino,
                        IFNULL((SELECT descripcionGarantia 
                                FROM tb_garantias_creditos gr 
                                INNER JOIN cli_garantia clgr on gr.id_garantia = clgr.idGarantia 
                                WHERE gr.id_cremcre_meta =cm.CCODCTA 
                                LIMIT 1),' - ') AS garantia,

                        prod.nombre AS producto,
                        cm.CCodGrupo id_grupos,
                        cm.noPeriodo numcuotas,
                        CONCAT(us.nombre,' ',us.apellido) AS responsable,
                        
                        IFNULL(sector.SectoresEconomicos, '-') AS sectorEconomico,
                        IFNULL(actividad.Titulo, '-') AS actividadEconomica

                    FROM cremcre_meta cm
                    INNER JOIN tb_cliente cl ON cm.CodCli=cl.idcod_cliente
                    INNER JOIN tb_usuario us ON cm.CodAnal=us.id_usu
                    INNER JOIN tb_agencia ag ON cm.CODAgencia=ag.cod_agenc
                    INNER JOIN cre_productos prod ON cm.CCODPRD = prod.id
                    INNER JOIN ctb_fuente_fondos ff ON ff.id=prod.id_fondo
                    LEFT JOIN jpxdcegu_bd_general_coopera.`tb_sectoreseconomicos` sector 
                        ON sector.id_SectoresEconomicos=cm.CSecEco
                    LEFT JOIN jpxdcegu_bd_general_coopera.`tb_ActiEcono` actividad 
                        ON actividad.id_ActiEcono=cm.ActoEcono
                    LEFT JOIN (
                        SELECT ccodcta, SUM(KP) AS sum_KP, MAX(dfecpro) AS dfecpro_ult, SUM(interes) AS sum_interes, SUM(MORA) AS sum_MORA, SUM(OTR) AS sum_AHOPRG_OTR
                        FROM CREDKAR ck
                        WHERE cestado != 'X' AND ctippag = 'P'
                        GROUP BY ccodcta
                    ) AS kar ON kar.ccodcta = cm.CCODCTA
                    WHERE (DATE($fechaFilter) BETWEEN ? AND ?)
                    $where
                    ORDER BY ff.id, cm.CCodGrupo, cm.DFecDsbls, cm.CCODCTA";

            $datos = $this->database->getAllResults($query, [
                $this->dataReporte['fecha_inicio'],
                $this->dataReporte['fecha_fin']
            ]);

            if (empty($datos)) {
                throw new SoftException("No se encontraron datos para los filtros seleccionados.");
            }

            /**
             * aqui agregar los filtros aplicados, para que los liste automaticamente en el reporte
             */

            if ($this->dataReporte['radio_agencia'] == 'anyregion') {
                $regionData = $this->database->selectColumns(
                    "cre_regiones",
                    ["nombre"],
                    "id=?",
                    [$this->dataReporte['creRegionSelect']]
                );
                $this->filtros['Region'] = $regionData[0]['nombre'] ?? '';
            }
            if ($this->dataReporte['filter_credito'] != 'ALL') {
                $tiposEnti = [
                    'INDI' => 'CRÉDITOS INDIVIDUALES',
                    'GRUP' => 'CRÉDITOS GRUPALES'
                ];
                $this->filtros['TIPOS'] = $tiposEnti[$this->dataReporte['filter_credito']] ?? '';
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

    public function reporteJSON()
    {
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();

            // 2. Procesar datos para gráficas (agrupación por fecha)
            $datosPorFecha = [];
            $totalesPorTipo = [
                'INDIVIDUALES' => 0,
                'GRUPALES' => 0
            ];

            foreach ($datos as $key => $dato) {
                $datos[$key]['formaPago'] = TipoDocumentoTransaccion::getDescripcion($dato['FormPago'], 3);
                $fecha = $dato['DFECPRO'];

                // Agrupar por fecha para gráfica
                if (!isset($datosPorFecha[$fecha])) {
                    $datosPorFecha[$fecha] = [
                        'fecha' => Date::toDMY($fecha),
                        'total' => 0,
                        'cantidad' => 0,
                        'capital' => 0,
                        'interes' => 0,
                        'mora' => 0,
                        'otros' => 0
                    ];
                }

                $datosPorFecha[$fecha]['total'] += floatval($dato['NMONTO'] ?? 0);
                $datosPorFecha[$fecha]['cantidad'] += 1;
                $datosPorFecha[$fecha]['capital'] += floatval($dato['KP'] ?? 0);
                $datosPorFecha[$fecha]['interes'] += floatval($dato['INTERES'] ?? 0);
                $datosPorFecha[$fecha]['mora'] += floatval($dato['MORA'] ?? 0);
                $datosPorFecha[$fecha]['otros'] += floatval($dato['OTR'] ?? 0);

                // Totales por tipo
                $tipoKey = ($dato['TipoEnti'] == 'GRUP') ? 'GRUPALES' : 'INDIVIDUALES';
                $totalesPorTipo[$tipoKey] += floatval($dato['NMONTO'] ?? 0);
            }

            // Convertir a array indexado para facilitar uso en JS
            $datosGrafica = array_values($datosPorFecha);

            header('Content-Type: application/json');
            echo json_encode([
                'status' => 1,
                'datos' => $datos,
                'datosGrafica' => $datosGrafica,
                'resumen' => [
                    'total_registros' => count($datos),
                    'total_ingresos' => array_sum(array_column($datos, 'NMONTO')),
                    'total_capital' => array_sum(array_column($datos, 'KP')),
                    'total_interes' => array_sum(array_column($datos, 'INTERES')),
                    'total_mora' => array_sum(array_column($datos, 'MORA')),
                    'total_otros' => array_sum(array_column($datos, 'OTR')),
                    'por_tipo' => $totalesPorTipo
                ]
            ]);
            exit;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }
    /**
     * Genera reporte PDF con plantilla base
     */
    public function reportePDF()
    {
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();
            $otros = $this->getDataOtros($datos);

            $estadosNombres = [
                'D' => 'EN ANÁLISIS',
                'E' => 'APROBADOS',
                'F' => 'DESEMBOLSADOS',
                'A' => 'SOLICITUDES',
                'G' => 'CANCELADOS',
                'L' => 'RECHAZADOS',
                'FG' => 'COLOCADOS'
            ];

            $title = 'REPORTE DE CRÉDITOS ' . ($estadosNombres[$this->dataReporte['estado']] ?? 'TODOS') . ' DEL ' .
                Date::toDMY($this->dataReporte['fecha_inicio']) . ' AL ' . Date::toDMY($this->dataReporte['fecha_fin']);

            $headerExtra = function ($pdf) {
                $this->addHeaderExtraPDF($pdf);
            };

            $bodyFunc = function ($pdf, $datos) use ($otros) {
                $this->bodyPDF($pdf, $datos, $otros['pivotData']);
            };

            // 3. Generar PDF con plantilla
            $response = $this->generarPlantillaPDF(
                $title,
                $bodyFunc,
                $headerExtra,
                $datos,
                $this->filtros,
                'L' // Orientación horizontal (Landscape)
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

    protected function bodyPDF($pdf, $datos, $pivotData = [])
    {
        $tamanio_linea = 3;

        $pdf->Ln(7);
        $pdf->SetFont($this->fonte, '', $this->sizeFont);

        $auxfondo = null;
        $auxgrupo = -1;

        // Acumuladores para el grupo actual
        $acumSoliGrupo = 0;
        $acumAproGrupo = 0;
        $acumDesGrupo = 0;
        $acumGastosGrupo = 0;

        // Totales globales
        $grandSoli = 0;
        $grandApro = 0;
        $grandDes = 0;
        $grandGastos = 0;

        // Función local para imprimir subtotales
        $imprimirSubtotales = function () use (&$pdf, &$acumSoliGrupo, &$acumAproGrupo, &$acumDesGrupo, &$acumGastosGrupo) {
            if ($pdf->GetY() > 170) {
                $pdf->AddPage();
            }
            $pdf->Ln(4);
            $pdf->SetFont($this->fonte, 'B', 7);
            $pdf->CellFit($this->widthsColumns['credito'] + $this->widthsColumns['nombre_cliente'], 6, 'Subtotal grupo:', '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['solicitado'], 6, Moneda::formato($acumSoliGrupo, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['aprobado'], 6, Moneda::formato($acumAproGrupo, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['desembolsado'], 6, Moneda::formato($acumDesGrupo, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['gastos'], 6, Moneda::formato($acumGastosGrupo, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->Ln(8);

            // Reset
            $acumSoliGrupo = 0;
            $acumAproGrupo = 0;
            $acumDesGrupo = 0;
            $acumGastosGrupo = 0;
        };

        foreach ($datos as $index => $dato) {
            $cuenta = $dato['cuenta'];
            $nombre = strtoupper(Utf8::decode(trim(preg_replace('/\s+/', ' ', $dato['nombre']))));
            $montosolicitado = (float) $dato['montosoli'];
            $montoaprobado = (float) $dato['montoaprobado'];
            $montodesembolsado = (float) $dato['montodesembolsado'];
            $tipo = $dato['tipo'];
            $fecsol = Date::isValid($dato['fecsolicitud']) ? Date::toDMY($dato['fecsolicitud']) : '-';
            $fecdes = Date::isValid($dato['fecdesembolsado']) ? Date::toDMY($dato['fecdesembolsado']) : '-';
            $responsable = strtoupper(Utf8::decode($dato['responsable']));

            $tipoenti = $dato['TipoEnti'];
            $idfondos = $dato['fondoid'];
            $nombrefondo = $dato['fondesc'];
            $idgrupo = ($tipoenti == 'GRUP') ? $dato['id_grupos'] : 0;
            $nomgrupo = $dato['NombreGrupo'];

            // Verificar cambio de fondo
            if ($idfondos != $auxfondo) {
                if ($index > 0) {
                    $imprimirSubtotales();
                }
                $pdf->SetFont($this->fonte, 'B', 8);
                $pdf->Cell(0, 6, 'FUENTE DE FONDOS: ' . strtoupper($nombrefondo), 0, 1, 'L');
                $pdf->Ln(2);
                $pdf->SetFont($this->fonte, '', $this->sizeFont);
                $auxfondo = $idfondos;
                $auxgrupo = -1;
            }

            // Verificar cambio de grupo
            if ($idgrupo != $auxgrupo) {
                if ($index > 0) {
                    $imprimirSubtotales();
                }
                $pdf->SetFont($this->fonte, 'B', 8);
                $textoGrupo = ($tipoenti == 'GRUP') ? 'GRUPO: ' . strtoupper($nomgrupo) : 'CREDITOS INDIVIDUALES';
                $pdf->Cell(0, 6, $textoGrupo, 0, 1, 'L');
                $pdf->Ln(2);
                $pdf->SetFont($this->fonte, '', $this->sizeFont);
                $auxgrupo = $idgrupo;
            }

            // Obtener gastos desde pivotData
            $detalleGastos = isset($pivotData[$cuenta]) ? $pivotData[$cuenta] : [];
            $totalGastosFila = array_sum($detalleGastos);

            // Acumular totales
            $acumSoliGrupo += $montosolicitado;
            $acumAproGrupo += $montoaprobado;
            $acumDesGrupo += $montodesembolsado;
            $acumGastosGrupo += $totalGastosFila;

            $grandSoli += $montosolicitado;
            $grandApro += $montoaprobado;
            $grandDes += $montodesembolsado;
            $grandGastos += $totalGastosFila;

            // Tipo de documento
            $tipdoc = '';
            if ($tipo == 'E')
                $tipdoc = 'EFECTIVO';
            if ($tipo == 'T')
                $tipdoc = 'TRANSFER';
            if ($tipo == 'C')
                $tipdoc = 'CHEQUE';
            if ($tipo == 'M')
                $tipdoc = 'MIXTO';

            // Imprimir fila
            $pdf->SetFont($this->fonte, '', 7);
            $pdf->CellFit($this->widthsColumns['credito'], $tamanio_linea, $cuenta, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea, $nombre, '', 0, 'L', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['solicitado'], $tamanio_linea, Moneda::formato($montosolicitado, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['aprobado'], $tamanio_linea, Moneda::formato($montoaprobado, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['desembolsado'], $tamanio_linea, Moneda::formato($montodesembolsado, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['gastos'], $tamanio_linea, Moneda::formato($totalGastosFila, ''), '', 0, 'R', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['tip_doc'], $tamanio_linea, $tipdoc, '', 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['fecha_solicitud'], $tamanio_linea, $fecsol, '', 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['fecha_desembolso'], $tamanio_linea, $fecdes, '', 0, 'C', 0, '', 1, 0);
            $pdf->CellFit($this->widthsColumns['responsable'], $tamanio_linea, $responsable, '', 1, 'L', 0, '', 1, 0);
            $pdf->Ln(1);
        }

        // Imprimir últimos subtotales
        $imprimirSubtotales();

        // Totales globales
        $pdf->Ln(6);
        $pdf->SetFont($this->fonte, 'B', 8);
        $pdf->Cell(0, 1, '', 'T', 1, 'R');
        $pdf->Ln(3);
        $pdf->CellFit($this->widthsColumns['credito'] + $this->widthsColumns['nombre_cliente'], 6, 'TOTAL GENERAL:', '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['solicitado'], 6, Moneda::formato($grandSoli, ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['aprobado'], 6, Moneda::formato($grandApro, ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['desembolsado'], 6, Moneda::formato($grandDes, ''), '', 0, 'R', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['gastos'], 6, Moneda::formato($grandGastos, ''), '', 1, 'R', 0, '', 1, 0);
    }



    protected function addHeaderExtraPDF($pdf)
    {
        $tamanio_linea = 5;

        //Fuente
        $pdf->SetFont('Courier', 'B', 8);
        $pdf->SetTextColor(0, 0, 0);

        $pdf->CellFit($this->widthsColumns['credito'], $tamanio_linea + 1, Utf8::decode('CRÉDITO'), 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['nombre_cliente'], $tamanio_linea + 1, 'NOMBRE CLIENTE', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['solicitado'], $tamanio_linea + 1, 'SOLICITADO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['aprobado'], $tamanio_linea + 1, 'APROBADO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['desembolsado'], $tamanio_linea + 1, 'DESEMBOLSADO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['gastos'], $tamanio_linea + 1, 'GASTOS', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['tip_doc'], $tamanio_linea + 1, 'TIP.DOC.', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['fecha_solicitud'], $tamanio_linea + 1, 'F.SOLICITUD', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['fecha_desembolso'], $tamanio_linea + 1, 'F.DESEMBOLSO', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->CellFit($this->widthsColumns['responsable'], $tamanio_linea + 1, 'RESPONSABLE', 'B', 0, 'C', 0, '', 1, 0);
        $pdf->Ln(7);
    }

    /**
     * Genera reporte Excel con plantilla base
     */
    public function reporteExcel()
    {
        try {
            $this->validarSesion();

            // 1. Obtener datos
            $datos = $this->getData();
            $otros = $this->getDataOtros($datos);

            $filtros = $this->filtros;

            // 2. Preparar filtros
            $filtros['Periodo'] = Date::toDMY($this->dataReporte['fecha_inicio']) . ' al ' . Date::toDMY($this->dataReporte['fecha_fin']);

            $estadosNombres = [
                'D' => 'EN ANÁLISIS',
                'E' => 'APROBADOS',
                'F' => 'DESEMBOLSADOS',
                'A' => 'SOLICITUDES',
                'G' => 'CANCELADOS',
                'L' => 'RECHAZADOS',
                'FG' => 'COLOCADOS'
            ];
            $filtros['Estado'] = $estadosNombres[$this->dataReporte['estado']] ?? 'TODOS';

            $title = 'REPORTE DE CRÉDITOS DESEMBOLSADOS ' . Date::toDMY($this->dataReporte['fecha_inicio']) . ' AL ' . Date::toDMY($this->dataReporte['fecha_fin']);

            $bodyFunc = function ($sheet, $row, $datos) use ($otros) {
                return $this->bodyExcel($sheet, $row, $datos, $otros['tiposGasto'], $otros['pivotData']);
            };

            // 3. Generar Excel con plantilla
            $response = $this->generarPlantillaExcel(
                $title,
                $bodyFunc,
                $datos,
                $filtros
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

    protected function bodyExcel($sheet, $row, $datos, $tiposGasto, $pivotData)
    {
        $sheet->setTitle("Desembolsos");

        // Encabezados principales fijos
        $encabezadosFijos = [
            "CRÉDITO",
            "NOMBRE CLIENTE",
            "SOLICITADO",
            "APROBADO",
            "DESEMBOLSADO"
        ];

        // Encabezados de gastos (dinámicos según tipos de gasto)
        $encabezadosGastos = [];
        foreach ($tiposGasto as $tg) {
            $encabezadosGastos[] = strtoupper($tg['nombre']);
        }
        // Columna para gastos no catalogados
        $encabezadosGastos[] = 'OTROS GASTOS';

        // Encabezados finales
        $encabezadosFinales = [
            "TIP.DOC",
            "F.SOLICITUD",
            "F.DESEMBOLSO",
            "NÚM. DOC.",
            "F.VENCIMIENTO",
            "F.ULT.PAGO",
            "MONTO PAGADO",
            "INT. PAGADO",
            "RESPONSABLE",
            "GRUPO",
            "PRODUCTO",
            "FUENTE FONDOS",
            "DESTINO",
            "GARANTÍA",
            "FRECUENCIA",
            "NUM CUOTAS",
            "SECTOR ECONÓMICO",
            "ACTIVIDAD ECONÓMICA"
        ];

        // Combinar todos los encabezados
        $encabezadoCompleto = array_merge($encabezadosFijos, $encabezadosGastos, $encabezadosFinales);

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($encabezadoCompleto));
        $sheet->fromArray($encabezadoCompleto, null, 'A9')->getStyle("A9:" . $lastCol . "9")->getFont()->setName($this->fonte)->setBold(true);

        $auxfondo = null;
        $auxgrupo = -1;

        // Acumuladores para el grupo actual
        $acumSoliGrupo = 0;
        $acumAproGrupo = 0;
        $acumDesGrupo = 0;
        $acumGastosGrupo = array_fill(0, count($tiposGasto) + 1, 0); // +1 para "otros"

        // Totales globales
        $grandSoli = 0;
        $grandApro = 0;
        $grandDes = 0;
        $grandGastos = array_fill(0, count($tiposGasto) + 1, 0); // +1 para "otros"

        $imprimirSubtotal = function () use (&$sheet, &$row, &$acumSoliGrupo, &$acumAproGrupo, &$acumDesGrupo, &$acumGastosGrupo, $lastCol, $tiposGasto) {
            $sheet->setCellValue("A{$row}", 'SUBTOTAL:');
            $sheet->mergeCells("A{$row}:B{$row}");
            $sheet->setCellValue("C{$row}", $acumSoliGrupo);
            $sheet->setCellValue("D{$row}", $acumAproGrupo);
            $sheet->setCellValue("E{$row}", $acumDesGrupo);

            // Escribir subtotales de cada tipo de gasto
            $colIndex = 6; // Columna F (después de DESEMBOLSADO)
            foreach ($acumGastosGrupo as $montoGasto) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
                $sheet->setCellValue("{$colLetter}{$row}", $montoGasto);
                $colIndex++;
            }

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true);

            // Formatear montos (Solicitado, Aprobado, Desembolsado + todos los gastos)
            $firstMoneyCol = "C{$row}";
            $lastGastoCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + count($acumGastosGrupo));
            $sheet->getStyle("{$firstMoneyCol}:{$lastGastoCol}{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');

            $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FFE8E8E8');

            $row++;
            $row++; // Espacio

            // Reset
            $acumSoliGrupo = 0;
            $acumAproGrupo = 0;
            $acumDesGrupo = 0;
            $acumGastosGrupo = array_fill(0, count($tiposGasto) + 1, 0);
        };

        foreach ($datos as $index => $dato) {
            $cuenta = $dato['cuenta'];
            $nombre = strtoupper($dato['nombre']);
            $montosolicitado = (float) $dato['montosoli'];
            $montoaprobado = (float) $dato['montoaprobado'];
            $montodesembolsado = (float) $dato['montodesembolsado'];
            $tipo = $dato['tipo'];
            $fecsol = Date::isValid($dato['fecsolicitud']) ? $dato['fecsolicitud'] : '-';
            $fecdes = ($dato['fecdesembolsado'] == '-') ? '-' : $dato['fecdesembolsado'];
            $fecven = (empty($dato['fecvencimiento']) || $dato['fecvencimiento'] == '-') ? '0000-00-00' : $dato['fecvencimiento'];
            $fechaultimopago = (empty($dato['fechaultimopago']) || $dato['fechaultimopago'] == '-') ? '0000-00-00' : $dato['fechaultimopago'];
            $kppag = (float) $dato['capital'];
            $intpag = (float) $dato['interes'];
            $morpag = (float) $dato['mora'];
            $otrpag = (float) $dato['otros_montos'];
            $montopagado = $kppag + $intpag + $morpag + $otrpag;
            $responsable = strtoupper($dato['responsable']);
            $doc_desembolso = $dato['numdoc_desembolso'];

            $tipoenti = $dato['TipoEnti'];
            $idfondos = $dato['fondoid'];
            $nombrefondo = $dato['fondesc'];
            $idgrupo = ($tipoenti == 'GRUP') ? $dato['id_grupos'] : 0;
            $nomgrupo = $dato['NombreGrupo'];

            // Verificar cambio de fondo
            if ($idfondos != $auxfondo) {
                if ($index > 0) {
                    $imprimirSubtotal();
                }
                $sheet->setCellValue("A{$row}", 'FUENTE DE FONDOS: ' . strtoupper($nombrefondo));
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(11);
                $sheet->getStyle("A{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFD3D3D3');
                $row++;
                $auxfondo = $idfondos;
                $auxgrupo = -1;
            }

            // Verificar cambio de grupo
            if ($idgrupo != $auxgrupo) {
                if ($index > 0) {
                    $imprimirSubtotal();
                }
                $textoGrupo = ($tipoenti == 'GRUP') ? 'GRUPO: ' . strtoupper($nomgrupo) : 'CREDITOS INDIVIDUALES';
                $sheet->setCellValue("A{$row}", $textoGrupo);
                $sheet->mergeCells("A{$row}:{$lastCol}{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(10);
                $sheet->getStyle("A{$row}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setARGB('FFE8E8E8');
                $row++;
                $auxgrupo = $idgrupo;
            }

            // Calcular gastos desglosados
            $detalleGastos = isset($pivotData[$cuenta]) ? $pivotData[$cuenta] : [];
            $gastosDesglosados = [];
            $sumaCatalogados = 0;

            // Recorrer tipos de gasto y obtener montos
            foreach ($tiposGasto as $idx => $tg) {
                $idGasto = $tg['id'];
                $montoGasto = isset($detalleGastos[$idGasto]) ? floatval($detalleGastos[$idGasto]) : 0;
                $gastosDesglosados[$idx] = $montoGasto;
                $sumaCatalogados += $montoGasto;
            }

            // Calcular "otros gastos" (no catalogados)
            $totalGastosFila = array_sum($detalleGastos);
            $otrosGastos = $totalGastosFila - $sumaCatalogados;
            $gastosDesglosados[] = $otrosGastos;

            // Acumular totales
            $acumSoliGrupo += $montosolicitado;
            $acumAproGrupo += $montoaprobado;
            $acumDesGrupo += $montodesembolsado;
            foreach ($gastosDesglosados as $idx => $monto) {
                $acumGastosGrupo[$idx] += $monto;
            }

            $grandSoli += $montosolicitado;
            $grandApro += $montoaprobado;
            $grandDes += $montodesembolsado;
            foreach ($gastosDesglosados as $idx => $monto) {
                $grandGastos[$idx] += $monto;
            }

            // Tipo de documento
            $tipdoc = '';
            if ($tipo == 'E')
                $tipdoc = 'EFECTIVO';
            if ($tipo == 'T')
                $tipdoc = 'TRANSFER';
            if ($tipo == 'C')
                $tipdoc = 'CHEQUE';
            if ($tipo == 'M')
                $tipdoc = 'MIXTO';

            // Datos de la fila (incluir gastos desglosados)
            $rowData = [
                $cuenta,
                $nombre,
                $montosolicitado,
                $montoaprobado,
                $montodesembolsado
            ];

            // Agregar cada columna de gasto
            foreach ($gastosDesglosados as $montoGasto) {
                $rowData[] = $montoGasto;
            }

            // Agregar resto de datos
            $rowData = array_merge($rowData, [
                $tipdoc,
                $fecsol,
                $fecdes,
                $doc_desembolso,
                $fecven,
                $fechaultimopago,
                $montopagado,
                $intpag,
                $responsable,
                $nomgrupo,
                $dato['producto'] ?? '',
                $nombrefondo,
                $dato['destino'] ?? '',
                $dato['garantia'] ?? '',
                $dato['frecuencia'] ?? '',
                $dato['numcuotas'] ?? '',
                $dato['sectorEconomico'] ?? '',
                $dato['actividadEconomica'] ?? ''
            ]);

            $sheet->fromArray($rowData, null, 'A' . $row);

            // Formatear columnas numéricas (Solicitado, Aprobado, Desembolsado + todos los gastos)
            $lastGastoCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + count($gastosDesglosados));
            $sheet->getStyle("C{$row}:{$lastGastoCol}{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');

            $row++;
        }

        // Imprimir último subtotal
        $imprimirSubtotal();

        // Total general
        $row++;
        $sheet->setCellValue("A{$row}", 'TOTAL GENERAL:');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue("C{$row}", $grandSoli);
        $sheet->setCellValue("D{$row}", $grandApro);
        $sheet->setCellValue("E{$row}", $grandDes);

        // Escribir totales de cada tipo de gasto
        $colIndex = 6;
        foreach ($grandGastos as $montoGasto) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);
            $sheet->setCellValue("{$colLetter}{$row}", $montoGasto);
            $colIndex++;
        }

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');

        // Formatear montos (Solicitado, Aprobado, Desembolsado + todos los gastos)
        $lastGastoCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(5 + count($grandGastos));
        $sheet->getStyle("C{$row}:{$lastGastoCol}{$row}")->getNumberFormat()->setFormatCode('"Q "#,##0.00');

        $sheet->getStyle("A{$row}:{$lastCol}{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF2C3E50');

        $row++;

        return $row;
    }

    private function getDataOtros($datos)
    {
        $tiposGasto = [];
        $pivotData = [];
        try {
            $this->database->openConnection();

            /* 
                ------------------------------------------------------------------------------
                NUEVO: OBTENER DETALLE DE GASTOS POR TIPO
                ------------------------------------------------------------------------------
                1) Tomamos todos los CCODCTA del array principal
                2) Buscamos en CREDKAR + credkar_detalle + cre_productos_gastos + cre_tipogastos 
                    la suma de cada gasto por CCODCTA.
                3) Formamos un array pivotado: $pivotData[ccodcta][id_tipogasto] = monto
                4) También sacamos un array de todos los tipos de gasto usados: $tiposGasto = [ [id, nombre], ... ]
                */

            // 1) Obtener todos los CCODCTA en un array para filtrar
            $ccodctas = array_column($datos, 'cuenta'); // índice 'cuenta'
            $ccodctas_str = implode("','", $ccodctas); // para uso en la cláusula IN

            // 2) Buscar tipos de gasto y sumas
            //    Solo buscamos aquellos con CTIPPAG='D' (que se interpretan normalmente como desembolso)
            $pivotData = [];  // pivotData[ ccodcta ][ id_tipogasto ] = total
            $tiposGastoSet = []; // para ir guardando [id_tipogasto => nombre_gasto]
            $sql_gastos = "SELECT 
                            ck.CCODCTA,
                            tg.id AS id_tipogasto,
                            tg.nombre_gasto,
                            SUM(cd.monto) AS total
                        FROM CREDKAR ck
                        INNER JOIN credkar_detalle cd ON cd.id_credkar=ck.CODKAR
                        INNER JOIN cre_productos_gastos pg ON pg.id=cd.id_concepto
                        INNER JOIN cre_tipogastos tg ON tg.id=pg.id_tipo_deGasto
                        WHERE cd.tipo='otro' AND ck.CCODCTA IN ('" . $ccodctas_str . "')
                        AND ck.CTIPPAG='D'
                        AND ck.CESTADO <> 'X'
                        GROUP BY ck.CCODCTA, tg.id
                        ORDER BY tg.id
                        ";
            // $res_gastos = mysqli_query($conexion, $sql_gastos);
            $res_gastos = $this->database->getAllResults($sql_gastos);

            foreach ($res_gastos as $rowg) {
                $cc = $rowg['CCODCTA'];
                $idg = $rowg['id_tipogasto'];
                $monto = (float) $rowg['total'];

                // acumulamos
                if (!isset($pivotData[$cc])) {
                    $pivotData[$cc] = [];
                }
                $pivotData[$cc][$idg] = $monto;

                // guardamos el tipo de gasto en un set
                if (!isset($tiposGastoSet[$idg])) {
                    $tiposGastoSet[$idg] = $rowg['nombre_gasto'];
                }
            }

            // 3) Convertimos ese $tiposGastoSet en un array ordenado para poder iterar en un orden fijo
            //    (cada vez que se necesite imprimir en PDF/Excel)
            $tiposGasto = [];
            foreach ($tiposGastoSet as $id => $nombre) {
                $tiposGasto[] = [
                    'id' => $id,
                    'nombre' => $nombre
                ];
            }
            // Ordenamos por id, si queremos
            usort($tiposGasto, function ($a, $b) {
                return ($a['id'] < $b['id']) ? -1 : 1;
            });

            return [
                'tiposGasto' => $tiposGasto,
                'pivotData' => $pivotData
            ];
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $mensaje = "Error: Intente nuevamente, o reporte este codigo de error($codigoError)";
            throw new Exception($mensaje);
        }
    }
}
