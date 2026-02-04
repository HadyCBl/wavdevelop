<?php

namespace Micro\Controllers\Seguros;

use Exception;
use Micro\Controllers\BaseController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Auth;
use Micro\Generic\Utf8;
use Micro\Helpers\Log;
use Micro\Helpers\FpdfExtend;
use Micro\Models\Seguros\Auxilio;
use Micro\Models\Seguros\Cuenta;
use Micro\Models\Seguros\Pago;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReporteController extends BaseController
{
    /**
     * Vista principal de reportes
     */
    public function index(): void
    {
        try {
            $html = $this->renderView('indicadores/seguros/reportes', [
                'csrf_token' => \Micro\Helpers\CSRFProtection::getTokenValue()
            ]);

            $this->view($html);
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error: Intente nuevamente, o reporte este codigo de error($codigoError)");
        }
    }

    /**
     * Endpoint para estadísticas del dashboard
     */
    public function estadisticasDashboard(): void
    {
        try {
            $hoy = date('Y-m-d');
            $primerDiaMes = date('Y-m-01');

            // Cuentas vigentes
            $cuentasVigentes = Cuenta::where('estado', 'vigente')->count();

            // Solicitudes pendientes
            $solicitudesPendientes = Auxilio::where('estado', 'solicitado')->count();

            // Auxilios pagados este mes
            $auxiliosPagados = Auxilio::where('estado', 'pagado')
                ->whereHas('pagos', function ($query) use ($primerDiaMes, $hoy) {
                    $query->whereBetween('fecha', [$primerDiaMes, $hoy]);
                })
                ->count();

            // Monto total pagado este mes
            $montoPagado = Pago::whereBetween('fecha', [$primerDiaMes, $hoy])
                ->sum('monto');

            $this->success([
                'data' => [
                    'cuentasVigentes' => $cuentasVigentes,
                    'solicitudesPendientes' => $solicitudesPendientes,
                    'auxiliosPagados' => $auxiliosPagados,
                    'montoPagado' => $montoPagado
                ],
                'showMessage' => 0
            ]);
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al cargar estadísticas ($codigoError)");
        }
    }

    /**
     * Reporte de Cuentas de Seguro
     */
    public function reporteCuentas(): void
    {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?? [];

            $tipo = $data['tipo'] ?? 'json';

            // Construir query
            $query = Cuenta::with(['cliente', 'servicio', 'renovaciones'])
                ->select('aux_cuentas.*');

            // Aplicar filtros
            if (!empty($data['estado_cuenta'])) {
                $query->where('aux_cuentas.estado', $data['estado_cuenta']);
            }

            if (!empty($data['fecha_inicio_desde'])) {
                $query->where('aux_cuentas.fecha_inicio', '>=', $data['fecha_inicio_desde']);
            }

            if (!empty($data['fecha_inicio_hasta'])) {
                $query->where('aux_cuentas.fecha_inicio', '<=', $data['fecha_inicio_hasta']);
            }

            if (!empty($data['nombre_cliente'])) {
                $query->whereHas('cliente', function ($q) use ($data) {
                    $q->where('short_name', 'LIKE', "%{$data['nombre_cliente']}%");
                });
            }

            if (!empty($data['cedula_cliente'])) {
                $query->whereHas('cliente', function ($q) use ($data) {
                    $q->where('no_identifica', 'LIKE', "%{$data['cedula_cliente']}%");
                });
            }

            if (!empty($data['servicio_nombre'])) {
                $query->whereHas('servicio', function ($q) use ($data) {
                    $q->where('nombre', 'LIKE', "%{$data['servicio_nombre']}%");
                });
            }

            $cuentas = $query->orderBy('aux_cuentas.created_at', 'desc')->get();

            // Preparar datos para reporte
            $datos = $cuentas->map(function ($cuenta) {
                $renovacionVigente = $cuenta->renovaciones()
                    ->where('estado', 'vigente')
                    ->where('fecha_inicio', '<=', date('Y-m-d'))
                    ->where('fecha_fin', '>=', date('Y-m-d'))
                    ->first();

                return [
                    'id' => $cuenta->id,
                    'cliente' => $cuenta->cliente->short_name ?? 'N/A',
                    'cedula' => $cuenta->cliente->no_identifica ?? 'N/A',
                    'servicio' => $cuenta->servicio->nombre ?? 'N/A',
                    'costo_servicio' => $cuenta->servicio->costo ?? 0,
                    'monto_auxilio' => $cuenta->servicio->monto_auxilio ?? 0,
                    'fecha_inicio' => $cuenta->fecha_inicio,
                    'estado' => ucfirst($cuenta->estado),
                    'renovacion_vigente' => $renovacionVigente ? 'Sí' : 'No',
                    'fecha_fin_renovacion' => $renovacionVigente->fecha_fin ?? 'N/A'
                ];
            })->toArray();

            // Generar según tipo
            switch ($tipo) {
                case 'pdf':
                    $this->generarPDFCuentas($datos);
                    break;
                case 'xlsx':
                    $this->generarExcelCuentas($datos);
                    break;
                case 'show':
                case 'json':
                default:
                    $this->success([
                        'datos' => $datos
                    ]);
                    break;
            }
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al generar reporte ($codigoError)");
        }
    }

    /**
     * Reporte de Auxilios Póstumos
     */
    public function reporteAuxilios(): void
    {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?? [];

            $tipo = $data['tipo'] ?? 'json';

            // Construir query
            $query = Auxilio::with(['cuenta.cliente', 'cuenta.servicio', 'pagos']);

            // Aplicar filtros
            if (!empty($data['estado_auxilio'])) {
                $query->where('estado', $data['estado_auxilio']);
            }

            if (!empty($data['fecha_solicitud_desde'])) {
                $query->where('fecha_solicitud', '>=', $data['fecha_solicitud_desde']);
            }

            if (!empty($data['fecha_solicitud_hasta'])) {
                $query->where('fecha_solicitud', '<=', $data['fecha_solicitud_hasta']);
            }

            if (!empty($data['fecha_fallece_desde'])) {
                $query->where('fecha_fallece', '>=', $data['fecha_fallece_desde']);
            }

            if (!empty($data['fecha_fallece_hasta'])) {
                $query->where('fecha_fallece', '<=', $data['fecha_fallece_hasta']);
            }

            if (!empty($data['monto_minimo'])) {
                $query->where('monto_aprobado', '>=', $data['monto_minimo']);
            }

            if (!empty($data['nombre_fallecido'])) {
                $query->whereHas('cuenta.cliente', function ($q) use ($data) {
                    $q->where('short_name', 'LIKE', "%{$data['nombre_fallecido']}%");
                });
            }

            $auxilios = $query->orderBy('created_at', 'desc')->get();

            // Preparar datos
            $datos = $auxilios->map(function ($auxilio) {
                $pago = $auxilio->pagos->first();

                return [
                    'id' => $auxilio->id,
                    'cliente' => $auxilio->cuenta->cliente->short_name ?? 'N/A',
                    'cedula' => $auxilio->cuenta->cliente->no_identifica ?? 'N/A',
                    'servicio' => $auxilio->cuenta->servicio->nombre ?? 'N/A',
                    'fecha_fallece' => $auxilio->fecha_fallece,
                    'fecha_solicitud' => $auxilio->fecha_solicitud,
                    'monto_aprobado' => $auxilio->monto_aprobado,
                    'estado' => ucfirst($auxilio->estado),
                    'fecha_pago' => $pago->fecha ?? 'N/A',
                    'forma_pago' => $pago ? ucfirst($pago->forma_pago) : 'N/A'
                ];
            })->toArray();

            switch ($tipo) {
                case 'pdf':
                    $this->generarPDFAuxilios($datos);
                    break;
                case 'xlsx':
                    $this->generarExcelAuxilios($datos);
                    break;
                case 'show':
                case 'json':
                default:
                    $this->success([
                        'datos' => $datos
                    ]);
                    break;
            }
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al generar reporte ($codigoError)");
        }
    }

    /**
     * Reporte de Pagos
     */
    public function reportePagos(): void
    {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?? [];

            $tipo = $data['tipo'] ?? 'json';

            // Construir query
            $query = Pago::with(['auxilio.cuenta.cliente', 'cuenta_banco.banco']);

            // Aplicar filtros
            if (!empty($data['fecha_pago_desde'])) {
                $query->where('fecha', '>=', $data['fecha_pago_desde']);
            }

            if (!empty($data['fecha_pago_hasta'])) {
                $query->where('fecha', '<=', $data['fecha_pago_hasta']);
            }

            if (!empty($data['forma_pago'])) {
                $query->where('forma_pago', $data['forma_pago']);
            }

            if (!empty($data['monto_min_pago'])) {
                $query->where('monto', '>=', $data['monto_min_pago']);
            }

            if (!empty($data['monto_max_pago'])) {
                $query->where('monto', '<=', $data['monto_max_pago']);
            }

            if (!empty($data['numdoc_pago'])) {
                $query->where(function ($q) use ($data) {
                    $q->where('numdoc', 'LIKE', "%{$data['numdoc_pago']}%")
                      ->orWhere('banco_numdoc', 'LIKE', "%{$data['numdoc_pago']}%");
                });
            }

            $pagos = $query->orderBy('fecha', 'desc')->get();

            // Preparar datos
            $datos = $pagos->map(function ($pago) {
                return [
                    'id' => $pago->id,
                    'cliente' => $pago->auxilio->cuenta->cliente->short_name ?? 'N/A',
                    'cedula' => $pago->auxilio->cuenta->cliente->no_identifica ?? 'N/A',
                    'fecha_pago' => $pago->fecha,
                    'monto' => $pago->monto,
                    'forma_pago' => ucfirst($pago->forma_pago),
                    'numdoc' => $pago->numdoc ?? 'N/A',
                    'banco' => $pago->cuenta_banco->banco->nombre_banco ?? 'N/A',
                    'banco_numdoc' => $pago->banco_numdoc ?? 'N/A',
                    'concepto' => $pago->concepto ?? 'N/A'
                ];
            })->toArray();

            switch ($tipo) {
                case 'pdf':
                    $this->generarPDFPagos($datos);
                    break;
                case 'xlsx':
                    $this->generarExcelPagos($datos);
                    break;
                case 'show':
                case 'json':
                default:
                    $this->success([
                        'datos' => $datos
                    ]);
                    break;
            }
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al generar reporte ($codigoError)");
        }
    }

    /**
     * Reporte de Estadísticas
     */
    public function reporteEstadisticas(): void
    {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?? [];

            $tipo = $data['tipo'] ?? 'json';
            $tipoAnalisis = $data['tipo_analisis'] ?? 'mensual';

            $fechaDesde = $data['periodo_desde'];
            $fechaHasta = $data['periodo_hasta'];

            $datos = [];

            switch ($tipoAnalisis) {
                case 'mensual':
                    $datos = $this->estadisticasMensuales($fechaDesde, $fechaHasta);
                    break;
                case 'por_servicio':
                    $datos = $this->estadisticasPorServicio($fechaDesde, $fechaHasta);
                    break;
                case 'por_estado':
                    $datos = $this->estadisticasPorEstado($fechaDesde, $fechaHasta);
                    break;
                case 'tendencias':
                    $datos = $this->estadisticasTendencias($fechaDesde, $fechaHasta);
                    break;
            }

            switch ($tipo) {
                case 'pdf':
                    $this->generarPDFEstadisticas($datos, $tipoAnalisis);
                    break;
                case 'xlsx':
                    $this->generarExcelEstadisticas($datos, $tipoAnalisis);
                    break;
                case 'show':
                case 'json':
                default:
                    $this->success([
                        'datos' => $datos,
                        'tipo_analisis' => $tipoAnalisis
                    ]);
                    break;
            }
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al generar estadísticas ($codigoError)");
        }
    }

    /**
     * Reporte Personalizado
     */
    public function reportePersonalizado(): void
    {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?? [];

            $tipo = $data['tipo'] ?? 'json';

            // Construir query base
            $query = Auxilio::with(['cuenta.cliente', 'cuenta.servicio', 'cuenta.beneficiarios', 'pagos', 'documentos']);

            // Filtros de fecha
            if (!empty($data['fecha_desde_pers']) && !empty($data['fecha_hasta_pers'])) {
                $tipoFecha = $data['tipo_fecha'] ?? 'solicitud';
                
                switch ($tipoFecha) {
                    case 'fallecimiento':
                        $query->whereBetween('fecha_fallece', [$data['fecha_desde_pers'], $data['fecha_hasta_pers']]);
                        break;
                    case 'pago':
                        $query->whereHas('pagos', function ($q) use ($data) {
                            $q->whereBetween('fecha', [$data['fecha_desde_pers'], $data['fecha_hasta_pers']]);
                        });
                        break;
                    default: // solicitud
                        $query->whereBetween('fecha_solicitud', [$data['fecha_desde_pers'], $data['fecha_hasta_pers']]);
                        break;
                }
            }

            // Filtros de estado
            $estados = [];
            if (!empty($data['estado_solicitado'])) $estados[] = 'solicitado';
            if (!empty($data['estado_aprobado'])) $estados[] = 'aprobado';
            if (!empty($data['estado_pagado'])) $estados[] = 'pagado';
            if (!empty($data['estado_rechazado'])) $estados[] = 'rechazado';

            if (!empty($estados)) {
                $query->whereIn('aux_auxilios.estado', $estados);
            }

            // Ordenamiento
            $ordenar = $data['ordenar_por'] ?? 'fecha_desc';
            switch ($ordenar) {
                case 'fecha_asc':
                    $query->orderBy('created_at', 'asc');
                    break;
                case 'monto_desc':
                    $query->orderBy('monto_aprobado', 'desc');
                    break;
                case 'monto_asc':
                    $query->orderBy('monto_aprobado', 'asc');
                    break;
                case 'cliente':
                    $query->join('aux_cuentas', 'aux_auxilios.id_cuenta', '=', 'aux_cuentas.id')
                          ->join('tb_cliente', 'aux_cuentas.id_cliente', '=', 'tb_cliente.idcod_cliente')
                          ->orderBy('tb_cliente.short_name', 'asc')
                          ->select('aux_auxilios.*');
                    break;
                default: // fecha_desc
                    $query->orderBy('created_at', 'desc');
                    break;
            }

            // Límite
            if (!empty($data['limite']) && is_numeric($data['limite'])) {
                $query->limit((int)$data['limite']);
            }

            $auxilios = $query->get();

            // Preparar datos según opciones seleccionadas
            $datos = $auxilios->map(function ($auxilio) use ($data) {
                $row = [
                    'id' => $auxilio->id,
                    'fecha_solicitud' => $auxilio->fecha_solicitud,
                    'fecha_fallece' => $auxilio->fecha_fallece,
                    'monto_aprobado' => $auxilio->monto_aprobado,
                    'estado' => ucfirst($auxilio->estado)
                ];

                // Incluir datos del cliente si está marcado
                if (!empty($data['incluir_cliente'])) {
                    $row['cliente'] = $auxilio->cuenta->cliente->short_name ?? 'N/A';
                    $row['cedula'] = $auxilio->cuenta->cliente->no_identifica ?? 'N/A';
                }

                // Incluir información de cuenta
                if (!empty($data['incluir_cuentas'])) {
                    $row['servicio'] = $auxilio->cuenta->servicio->nombre ?? 'N/A';
                    $row['estado_cuenta'] = $auxilio->cuenta->estado;
                }

                // Incluir beneficiarios
                if (!empty($data['incluir_beneficiarios'])) {
                    $beneficiarios = $auxilio->cuenta->beneficiarios->map(function ($ben) {
                        return $ben->pivot->nombres . ' ' . $ben->pivot->apellidos;
                    })->implode(', ');
                    $row['beneficiarios'] = $beneficiarios ?: 'N/A';
                }

                // Incluir pagos
                if (!empty($data['incluir_pagos'])) {
                    $pago = $auxilio->pagos->first();
                    $row['fecha_pago'] = $pago->fecha ?? 'N/A';
                    $row['forma_pago'] = $pago ? ucfirst($pago->forma_pago) : 'N/A';
                    $row['monto_pagado'] = $pago->monto ?? 0;
                }

                // Incluir documentos
                if (!empty($data['incluir_documentos'])) {
                    $row['documentos'] = $auxilio->documentos->count();
                }

                return $row;
            })->toArray();

            switch ($tipo) {
                case 'pdf':
                    $this->generarPDFPersonalizado($datos);
                    break;
                case 'xlsx':
                    $this->generarExcelPersonalizado($datos);
                    break;
                case 'show':
                case 'json':
                default:
                    $this->success([
                        'datos' => $datos,
                        'total' => count($datos)
                    ]);
                    break;
            }
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al generar reporte personalizado ($codigoError)");
        }
    }

    // ========== MÉTODOS AUXILIARES DE ESTADÍSTICAS ==========

    private function estadisticasMensuales($desde, $hasta)
    {
        $auxilios = Auxilio::whereBetween('fecha_solicitud', [$desde, $hasta])
            ->selectRaw("DATE_FORMAT(fecha_solicitud, '%Y-%m') as periodo")
            ->selectRaw("COUNT(*) as total")
            ->selectRaw("SUM(monto_aprobado) as monto_total")
            ->selectRaw("AVG(monto_aprobado) as promedio")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->toArray();

        return $auxilios;
    }

    private function estadisticasPorServicio($desde, $hasta)
    {
        $auxilios = Auxilio::withoutGlobalScope(\Micro\Models\Scopes\Seguros\AuxiliosNoEliminadosScope::class)
            ->whereBetween('aux_auxilios.fecha_solicitud', [$desde, $hasta])
            ->where('aux_auxilios.estado', '<>', 'eliminado')
            ->join('aux_cuentas', 'aux_auxilios.id_cuenta', '=', 'aux_cuentas.id')
            ->join('aux_servicios', 'aux_cuentas.id_servicio', '=', 'aux_servicios.id')
            ->selectRaw('aux_servicios.nombre as servicio')
            ->selectRaw('COUNT(aux_auxilios.id) as total')
            ->selectRaw('SUM(aux_auxilios.monto_aprobado) as monto_total')
            ->groupBy('aux_servicios.nombre')
            ->orderBy('total', 'desc')
            ->get()
            ->toArray();

        return $auxilios;
    }

    private function estadisticasPorEstado($desde, $hasta)
    {
        $auxilios = Auxilio::whereBetween('fecha_solicitud', [$desde, $hasta])
            ->selectRaw('aux_auxilios.estado')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(monto_aprobado) as monto_total')
            ->groupBy('aux_auxilios.estado')
            ->orderBy('total', 'desc')
            ->get()
            ->toArray();

        return $auxilios;
    }

    private function estadisticasTendencias($desde, $hasta)
    {
        $auxilios = Auxilio::whereBetween('fecha_solicitud', [$desde, $hasta])
            ->selectRaw("DATE_FORMAT(fecha_solicitud, '%Y-%m-%d') as fecha")
            ->selectRaw("COUNT(*) as cantidad")
            ->selectRaw("SUM(monto_aprobado) as monto")
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->toArray();

        return $auxilios;
    }

    // ========== GENERADORES PDF ==========

    private function generarPDFCuentas($datos)
    {
        $pdf = new FpdfExtend('L', 'mm', 'Letter');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        
        $pdf->Cell(0, 10, 'REPORTE DE CUENTAS DE SEGURO', 0, 1, 'C');
        $pdf->Ln(5);

        // Encabezados
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(15, 7, 'ID', 1, 0, 'C', true);
        $pdf->Cell(55, 7, 'Cliente', 1, 0, 'C', true);
        $pdf->Cell(25, 7, Utf8::decode('Identificación'), 1, 0, 'C', true);
        $pdf->Cell(45, 7, 'Servicio', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Costo', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'M. Auxilio', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Fecha Inicio', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Estado', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Vigente', 1, 1, 'C', true);

        // Datos
        $pdf->SetFont('Arial', '', 7);
        foreach ($datos as $row) {
            $pdf->Cell(15, 6, $row['id'], 1, 0, 'C');
            $pdf->Cell(55, 6, Utf8::decode(substr($row['cliente'], 0, 30)), 1, 0, 'L');
            $pdf->Cell(25, 6, $row['cedula'], 1, 0, 'C');
            $pdf->Cell(45, 6, Utf8::decode(substr($row['servicio'], 0, 25)), 1, 0, 'L');
            $pdf->Cell(25, 6, 'Q' . number_format($row['costo_servicio'], 2), 1, 0, 'R');
            $pdf->Cell(25, 6, 'Q' . number_format($row['monto_auxilio'], 2), 1, 0, 'R');
            $pdf->Cell(25, 6, $row['fecha_inicio'], 1, 0, 'C');
            $pdf->Cell(20, 6, Utf8::decode($row['estado']), 1, 0, 'C');
            $pdf->Cell(20, 6, Utf8::decode($row['renovacion_vigente']), 1, 1, 'C');
        }

        $archivo = base64_encode($pdf->Output('S'));
        $this->success([
            'archivo' => $archivo,
            'nombre' => 'reporte_cuentas_' . date('YmdHis') . '.pdf'
        ]);
    }

    private function generarPDFAuxilios($datos)
    {
        $pdf = new FpdfExtend('L', 'mm', 'Letter');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        
        $pdf->Cell(0, 10, Utf8::decode('REPORTE DE AUXILIOS PÓSTUMOS'), 0, 1, 'C');
        $pdf->Ln(5);

        // Encabezados
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(12, 7, 'ID', 1, 0, 'C', true);
        $pdf->Cell(50, 7, 'Cliente', 1, 0, 'C', true);
        $pdf->Cell(25, 7, Utf8::decode('Identificación'), 1, 0, 'C', true);
        $pdf->Cell(40, 7, 'Servicio', 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'F. Fallece', 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'F. Solicitud', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Monto', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Estado', 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'F. Pago', 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'Forma Pago', 1, 1, 'C', true);

        // Datos
        $pdf->SetFont('Arial', '', 7);
        $totalMonto = 0;
        foreach ($datos as $row) {
            $pdf->Cell(12, 6, $row['id'], 1, 0, 'C');
            $pdf->Cell(50, 6, Utf8::decode(substr($row['cliente'], 0, 28)), 1, 0, 'L');
            $pdf->Cell(25, 6, $row['cedula'], 1, 0, 'C');
            $pdf->Cell(40, 6, Utf8::decode(substr($row['servicio'], 0, 22)), 1, 0, 'L');
            $pdf->Cell(22, 6, $row['fecha_fallece'], 1, 0, 'C');
            $pdf->Cell(22, 6, $row['fecha_solicitud'], 1, 0, 'C');
            $pdf->Cell(25, 6, 'Q' . number_format($row['monto_aprobado'], 2), 1, 0, 'R');
            $pdf->Cell(25, 6, Utf8::decode($row['estado']), 1, 0, 'C');
            $pdf->Cell(22, 6, $row['fecha_pago'], 1, 0, 'C');
            $pdf->Cell(22, 6, Utf8::decode($row['forma_pago']), 1, 1, 'C');
            
            $totalMonto += $row['monto_aprobado'];
        }

        // Total
        $pdf->SetFont('Arial', 'B', 8);
        $pdf->Cell(193, 6, 'TOTAL', 1, 0, 'R');
        $pdf->Cell(25, 6, 'Q' . number_format($totalMonto, 2), 1, 1, 'R');

        $archivo = base64_encode($pdf->Output('S'));
        $this->success([
            'archivo' => $archivo,
            'nombre' => 'reporte_auxilios_' . date('YmdHis') . '.pdf'
        ]);
    }

    private function generarPDFPagos($datos)
    {
        $pdf = new FpdfExtend('L', 'mm', 'Letter');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        
        $pdf->Cell(0, 10, 'REPORTE DE PAGOS DE AUXILIOS', 0, 1, 'C');
        $pdf->Ln(5);

        // Encabezados
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->SetFillColor(200, 220, 255);
        $pdf->Cell(12, 7, 'ID', 1, 0, 'C', true);
        $pdf->Cell(50, 7, 'Cliente', 1, 0, 'C', true);
        $pdf->Cell(25, 7, Utf8::decode('Identificación'), 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'Fecha Pago', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Monto', 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'Forma Pago', 1, 0, 'C', true);
        $pdf->Cell(22, 7, 'No. Doc', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Banco', 1, 0, 'C', true);
        $pdf->Cell(52, 7, 'Concepto', 1, 1, 'C', true);

        // Datos
        $pdf->SetFont('Arial', '', 6);
        $totalMonto = 0;
        foreach ($datos as $row) {
            $pdf->Cell(12, 6, $row['id'], 1, 0, 'C');
            $pdf->Cell(50, 6, Utf8::decode(substr($row['cliente'], 0, 28)), 1, 0, 'L');
            $pdf->Cell(25, 6, $row['cedula'], 1, 0, 'C');
            $pdf->Cell(22, 6, $row['fecha_pago'], 1, 0, 'C');
            $pdf->Cell(25, 6, 'Q' . number_format($row['monto'], 2), 1, 0, 'R');
            $pdf->Cell(22, 6, Utf8::decode($row['forma_pago']), 1, 0, 'C');
            $pdf->Cell(22, 6, $row['numdoc'], 1, 0, 'C');
            $pdf->Cell(35, 6, Utf8::decode(substr($row['banco'], 0, 20)), 1, 0, 'L');
            $pdf->Cell(52, 6, Utf8::decode(substr($row['concepto'], 0, 30)), 1, 1, 'L');
            
            $totalMonto += $row['monto'];
        }

        // Total
        $pdf->SetFont('Arial', 'B', 7);
        $pdf->Cell(109, 6, 'TOTAL PAGADO', 1, 0, 'R');
        $pdf->Cell(25, 6, 'Q' . number_format($totalMonto, 2), 1, 1, 'R');

        $archivo = base64_encode($pdf->Output('S'));
        $this->success([
            'archivo' => $archivo,
            'nombre' => 'reporte_pagos_' . date('YmdHis') . '.pdf'
        ]);
    }

    private function generarPDFEstadisticas($datos, $tipoAnalisis)
    {
        $pdf = new FpdfExtend('P', 'mm', 'Letter');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        
        $titulo = 'ESTADÍSTICAS - ' . strtoupper(str_replace('_', ' ', $tipoAnalisis));
        $pdf->Cell(0, 10, Utf8::decode($titulo), 0, 1, 'C');
        $pdf->Ln(5);

        // El formato varía según el tipo de análisis
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(200, 220, 255);

        if ($tipoAnalisis === 'mensual') {
            $pdf->Cell(50, 7, 'Periodo', 1, 0, 'C', true);
            $pdf->Cell(35, 7, 'Total', 1, 0, 'C', true);
            $pdf->Cell(40, 7, 'Monto Total', 1, 0, 'C', true);
            $pdf->Cell(40, 7, 'Promedio', 1, 1, 'C', true);

            $pdf->SetFont('Arial', '', 8);
            foreach ($datos as $row) {
                $pdf->Cell(50, 6, $row['periodo'], 1, 0, 'C');
                $pdf->Cell(35, 6, $row['total'], 1, 0, 'C');
                $pdf->Cell(40, 6, 'Q' . number_format($row['monto_total'], 2), 1, 0, 'R');
                $pdf->Cell(40, 6, 'Q' . number_format($row['promedio'], 2), 1, 1, 'R');
            }
        } else {
            // Formato genérico para otros tipos
            $keys = !empty($datos) ? array_keys($datos[0]) : [];
            foreach ($keys as $key) {
                $pdf->Cell(50, 7, ucfirst($key), 1, 0, 'C', true);
            }
            $pdf->Ln();

            $pdf->SetFont('Arial', '', 8);
            foreach ($datos as $row) {
                foreach ($row as $value) {
                    $pdf->Cell(50, 6, Utf8::decode((string)$value), 1, 0, 'C');
                }
                $pdf->Ln();
            }
        }

        $archivo = base64_encode($pdf->Output('S'));
        $this->success([
            'archivo' => $archivo,
            'nombre' => 'reporte_estadisticas_' . date('YmdHis') . '.pdf'
        ]);
    }

    private function generarPDFPersonalizado($datos)
    {
        $pdf = new FpdfExtend('L', 'mm', 'Letter');
        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 14);
        
        $pdf->Cell(0, 10, 'REPORTE PERSONALIZADO DE AUXILIOS', 0, 1, 'C');
        $pdf->Ln(5);

        if (empty($datos)) {
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(0, 10, 'No hay datos para mostrar', 0, 1, 'C');
        } else {
            // Encabezados dinámicos
            $keys = array_keys($datos[0]);
            $cellWidth = 265 / count($keys); // Ancho total dividido entre columnas

            $pdf->SetFont('Arial', 'B', 7);
            $pdf->SetFillColor(200, 220, 255);
            foreach ($keys as $key) {
                $pdf->Cell($cellWidth, 7, Utf8::decode(ucfirst($key)), 1, 0, 'C', true);
            }
            $pdf->Ln();

            // Datos
            $pdf->SetFont('Arial', '', 6);
            foreach ($datos as $row) {
                foreach ($row as $value) {
                    $pdf->Cell($cellWidth, 6, Utf8::decode(substr((string)$value, 0, 40)), 1, 0, 'L');
                }
                $pdf->Ln();
            }
        }

        $archivo = base64_encode($pdf->Output('S'));
        $this->success([
            'archivo' => $archivo,
            'nombre' => 'reporte_personalizado_' . date('YmdHis') . '.pdf'
        ]);
    }

    // ========== GENERADORES EXCEL ==========

    private function generarExcelCuentas($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cuentas de Seguro');

        // Encabezados
        $headers = ['ID', 'Cliente', 'Identificación', 'Servicio', 'Costo', 'Monto Auxilio', 
                    'Fecha Inicio', 'Estado', 'Renovación Vigente', 'Fecha Fin'];
        $sheet->fromArray($headers, null, 'A1');

        // Estilo de encabezados
        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        // Datos
        $row = 2;
        foreach ($datos as $dato) {
            $sheet->fromArray(array_values($dato), null, "A{$row}");
            $row++;
        }

        // Auto-tamaño
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $excelData = ob_get_clean();

        $this->success([
            'archivo' => base64_encode($excelData),
            'nombre' => 'reporte_cuentas_' . date('YmdHis') . '.xlsx'
        ]);
    }

    private function generarExcelAuxilios($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Auxilios');

        $headers = ['ID', 'Cliente', 'Identificación', 'Servicio', 'Fecha Fallecimiento', 
                    'Fecha Solicitud', 'Monto Aprobado', 'Estado', 'Fecha Pago', 'Forma Pago'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($datos as $dato) {
            $sheet->fromArray(array_values($dato), null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $excelData = ob_get_clean();

        $this->success([
            'archivo' => base64_encode($excelData),
            'nombre' => 'reporte_auxilios_' . date('YmdHis') . '.xlsx'
        ]);
    }

    private function generarExcelPagos($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Pagos');

        $headers = ['ID', 'Cliente', 'Identificación', 'Fecha Pago', 'Monto', 
                    'Forma Pago', 'No. Documento', 'Banco', 'No. Cheque', 'Concepto'];
        $sheet->fromArray($headers, null, 'A1');

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
        ];
        $sheet->getStyle('A1:J1')->applyFromArray($headerStyle);

        $row = 2;
        foreach ($datos as $dato) {
            $sheet->fromArray(array_values($dato), null, "A{$row}");
            $row++;
        }

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $excelData = ob_get_clean();

        $this->success([
            'archivo' => base64_encode($excelData),
            'nombre' => 'reporte_pagos_' . date('YmdHis') . '.xlsx'
        ]);
    }

    private function generarExcelEstadisticas($datos, $tipoAnalisis)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Estadísticas');

        if (!empty($datos)) {
            $headers = array_keys($datos[0]);
            $sheet->fromArray($headers, null, 'A1');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            ];
            $sheet->getStyle('A1:' . chr(65 + count($headers) - 1) . '1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($datos as $dato) {
                $sheet->fromArray(array_values($dato), null, "A{$row}");
                $row++;
            }

            foreach (range('A', chr(65 + count($headers) - 1)) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $excelData = ob_get_clean();

        $this->success([
            'archivo' => base64_encode($excelData),
            'nombre' => 'reporte_estadisticas_' . date('YmdHis') . '.xlsx'
        ]);
    }

    private function generarExcelPersonalizado($datos)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte Personalizado');

        if (!empty($datos)) {
            $headers = array_keys($datos[0]);
            $sheet->fromArray($headers, null, 'A1');

            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4472C4']],
            ];
            $sheet->getStyle('A1:' . chr(65 + count($headers) - 1) . '1')->applyFromArray($headerStyle);

            $row = 2;
            foreach ($datos as $dato) {
                $sheet->fromArray(array_values($dato), null, "A{$row}");
                $row++;
            }

            foreach (range('A', chr(65 + count($headers) - 1)) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $excelData = ob_get_clean();

        $this->success([
            'archivo' => base64_encode($excelData),
            'nombre' => 'reporte_personalizado_' . date('YmdHis') . '.xlsx'
        ]);
    }
}
