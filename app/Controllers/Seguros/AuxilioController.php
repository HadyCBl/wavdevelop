<?php

namespace Micro\Controllers\Seguros;

use Exception;
use Micro\Controllers\BaseController;
use Micro\Exceptions\SoftException;
use Micro\Generic\Auth;
use Micro\Generic\Validator;
use Micro\Helpers\CSRFProtection;
use Micro\Helpers\Log;
use Micro\Models\Bancos\Cuenta as BancosCuenta;
use Micro\Models\Clientes\Beneficiario;
use Micro\Models\Seguros\Auxilio;
use Micro\Models\Seguros\Beneficiario as CuentaBeneficiario;
use Micro\Models\Seguros\Cuenta;
use Micro\Models\Seguros\Documento;
use Micro\Models\Seguros\Pago;
use Micro\Models\Seguros\Renovacion;
use Micro\Services\FileStorageService;
use Illuminate\Database\Capsule\Manager as DB;
use Micro\Helpers\Beneq;
use Micro\Models\Agencia;
use Micro\Models\Contabilidad\Diario;

class AuxilioController extends BaseController
{

    public function index(): void
    {
        try {
            $html = $this->renderView('indicadores/seguros/auxilios', [
                'csrf_token' => CSRFProtection::getTokenValue()
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
     * Buscar cuentas vigentes por nombre o cédula
     */
    public function buscarCuentas(): void
    {
        try {
            $busqueda = $_GET['q'] ?? '';

            if (strlen($busqueda) < 3) {
                $this->error("Ingrese al menos 3 caracteres para buscar");
                return;
            }

            $cuentas = Cuenta::where('estado', 'vigente')
                ->with(['cliente', 'servicio', 'renovaciones'])
                ->whereHas('cliente', function ($query) use ($busqueda) {
                    $query->where('short_name', 'LIKE', "%{$busqueda}%")
                        ->orWhere('no_identifica', 'LIKE', "%{$busqueda}%");
                })
                ->get()
                ->map(function ($cuenta) {
                    // Verificar renovación vigente actual
                    $renovacionVigente = $cuenta->renovaciones()
                        ->where('estado', 'vigente')
                        ->where('fecha_inicio', '<=', date('Y-m-d'))
                        ->where('fecha_fin', '>=', date('Y-m-d'))
                        ->orderBy('fecha_fin', 'DESC')
                        ->first();

                    return [
                        'id' => $cuenta->id,
                        'cliente' => [
                            'nombre' => $cuenta->cliente->short_name ?? '',
                            'identificacion' => $cuenta->cliente->no_identifica ?? '',
                        ],
                        'servicio' => [
                            'id' => $cuenta->servicio->id ?? null,
                            'nombre' => $cuenta->servicio->nombre ?? '',
                            'monto_auxilio' => $cuenta->servicio->monto_auxilio ?? 0,
                        ],
                        // 'beneficiarios' => $cuenta->beneficiarios->map(function ($ben) {
                        //     return [
                        //         'id' => $ben->id,
                        //         'nombre' => trim(($ben->nombres ?? '') . ' ' . ($ben->apellidos ?? '')),
                        //         'cedula' => $ben->identificacion ?? '',
                        //         'parentesco' => $ben->pivot->parentesco ?? '',
                        //         'porcentaje' => $ben->pivot->porcentaje ?? 0,
                        //     ];
                        // }),
                        'renovacion_vigente' => $renovacionVigente ? [
                            'id' => $renovacionVigente->id,
                            'fecha_inicio' => $renovacionVigente->fecha_inicio,
                            'fecha_fin' => $renovacionVigente->fecha_fin,
                            'monto' => $renovacionVigente->monto,
                        ] : null,
                    ];
                });

            $this->success(['data' => $cuentas, 'showMessage' => 0], "Cuentas encontradas");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al buscar cuentas ($codigoError)");
        }
    }

    /**
     * Verificar si hay renovación vigente en la fecha de fallecimiento
     */
    public function verificarRenovacion(): void
    {
        try {
            $data = [
                'id_cuenta' => $this->input('id_cuenta') ?? null,
                'fecha_fallece' => $this->input('fecha_fallece') ?? null
            ];

            $rules = [
                'id_cuenta' => 'required|integer',
                'fecha_fallece' => 'required|date'
            ];

            $validator = new Validator($data, $rules);

            if ($validator->fails()) {
                $this->error($validator->firstOnErrors() ?? 'Error de validación');
                return;
            }

            $cuenta = Cuenta::with(['servicio', 'renovaciones'])->find($data['id_cuenta']);

            if (!$cuenta) {
                $this->error("Cuenta no encontrada");
                return;
            }

            Log::info("cuenta", $cuenta->toArray());

            // Buscar renovación vigente en la fecha de fallecimiento
            $renovacion = $cuenta->renovaciones()
                ->where('estado', 'vigente')
                ->where('fecha_inicio', '<=', $data['fecha_fallece'])
                ->where('fecha_fin', '>=', $data['fecha_fallece'])
                ->first();

            if ($renovacion) {
                $this->success([
                    'vigente' => true,
                    'renovacion_id' => $renovacion->id,
                    'monto_aprobado' => $cuenta->servicio->monto_auxilio ?? 0,
                    'fecha_inicio' => $renovacion->fecha_inicio,
                    'fecha_fin' => $renovacion->fecha_fin,
                ], "Renovación vigente encontrada");
            } else {
                $this->error("No hay renovación vigente en la fecha de fallecimiento");
            }
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al verificar renovación ($codigoError)");
        }
    }

    /**
     * Crear solicitud de auxilio
     */
    public function store(): void
    {
        try {
            // Log::info("inputs ", $this->all());
            $data = $_POST;

            // Log::info("Datos para crear auxilio", $data);
            // Log::info("Archivos recibidos", $_FILES);

            $rules = [
                'id_cuenta' => 'required|integer',
                'fecha_fallece' => 'required|date',
                'fecha_solicitud' => 'required|date',
                'monto_aprobado' => 'required|numeric'
            ];

            $validator = new Validator($data, $rules);

            if ($validator->fails()) {
                $this->error($validator->firstOnErrors() ?? 'Error de validación');
                return;
            }

            // Verificar que la cuenta existe y está vigente
            $cuenta = Cuenta::where('estado', 'vigente')->with('servicio')->find($data['id_cuenta']);
            if (!$cuenta) {
                $this->error("Cuenta no válida o no vigente");
                return;
            }

            // Verificar renovación vigente en fecha de fallecimiento
            $renovacionVigente = Renovacion::where('id_cuenta', $data['id_cuenta'])
                ->where('estado', 'vigente')
                ->where('fecha_inicio', '<=', $data['fecha_fallece'])
                ->where('fecha_fin', '>=', $data['fecha_fallece'])
                ->first();

            if (!$renovacionVigente) {
                $this->error("No existe renovación vigente en la fecha de fallecimiento");
                return;
            }

            // Crear auxilio
            $auxilio = Auxilio::create([
                'id_cuenta' => $data['id_cuenta'],
                'fecha_fallece' => $data['fecha_fallece'],
                'fecha_solicitud' => $data['fecha_solicitud'],
                'monto_aprobado' => $data['monto_aprobado'],
                'estado' => 'solicitado',
                'notas' => $data['notas'] ?? null,
                'created_by' => Auth::getUserId(),
            ]);

            // Procesar documentos si existen
            if (isset($_FILES['documentos']) && !empty($_FILES['documentos']['name'])) {
                // Verificar que haya archivos válidos
                if (is_array($_FILES['documentos']['name'])) {
                    // Filtrar archivos vacíos
                    $archivosValidos = false;
                    foreach ($_FILES['documentos']['name'] as $nombre) {
                        if (!empty($nombre)) {
                            $archivosValidos = true;
                            break;
                        }
                    }
                    if ($archivosValidos) {
                        $this->procesarDocumentos($auxilio->id, $_FILES['documentos']);
                    }
                } else if (!empty($_FILES['documentos']['name'])) {
                    // Un solo archivo
                    $this->procesarDocumentos($auxilio->id, $_FILES['documentos']);
                }
            }

            $this->success([
                'id' => $auxilio->id,
                'estado' => $auxilio->estado,
            ], "Solicitud de auxilio creada exitosamente");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al crear solicitud ($codigoError)");
        }
    }

    /**
     * Listar auxilios por estado
     */
    public function listar(): void
    {
        try {
            $estado = $_GET['estado'] ?? null;

            $query = Auxilio::with([
                'cuenta.cliente:idcod_cliente,short_name,no_identifica',
                'cuenta.servicio',
                'cuenta.beneficiarios',
                'pagos'
            ]);

            if ($estado) {
                $query->where('estado', $estado);
            }

            $auxilios = $query->orderBy('created_at', 'desc')->get();

            $this->success(['data' => $auxilios], "Auxilios obtenidos");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al listar auxilios ($codigoError)");
        }
    }

    /**
     * Aprobar solicitud de auxilio
     */
    public function aprobar($id): void
    {
        try {
            $auxilio = Auxilio::find($id);

            if (!$auxilio) {
                // $this->error("Auxilio no encontrado");
                // return;
                throw new SoftException("Auxilio no encontrado");
            }

            if ($auxilio->estado !== 'solicitado') {
                // $this->error("Solo se pueden aprobar auxilios en estado 'solicitado'");
                // return;
                throw new SoftException("Solo se pueden aprobar auxilios en estado 'solicitado'");
            }

            $montoAprobado = $this->input('monto_aprobado') ?? 0;

            $auxilio->update([
                'estado' => 'aprobado',
                'updated_by' => Auth::getUserId(),
                'monto_aprobado' => $montoAprobado,
            ]);

            $this->success([
                'id' => $auxilio->id,
                'estado' => $auxilio->estado,
            ], "Auxilio aprobado exitosamente");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al aprobar auxilio ($codigoError)");
        }
    }

    /**
     * Rechazar solicitud de auxilio
     */
    public function rechazar($id): void
    {
        try {
            $json = file_get_contents('php://input');
            $data = json_decode($json, true) ?? [];

            $auxilio = Auxilio::find($id);

            if (!$auxilio) {
                // $this->error("Auxilio no encontrado");
                // return;
                throw new SoftException("Auxilio no encontrado");
            }

            if ($auxilio->estado !== 'solicitado') {
                // $this->error("Solo se pueden rechazar auxilios en estado 'solicitado'");
                // return;
                throw new SoftException("Solo se pueden rechazar auxilios en estado 'solicitado'");
            }

            $motivo = $data['motivo'] ?? 'Sin motivo especificado';

            $auxilio->update([
                'estado' => 'rechazado',
                'notas' => ($auxilio->notas ? $auxilio->notas . "\n\n" : '') . "RECHAZADO: " . $motivo,
                'updated_by' => Auth::getUserId(),
            ]);

            $this->success([
                'id' => $auxilio->id,
                'estado' => $auxilio->estado,
            ], "Auxilio rechazado");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al rechazar auxilio ($codigoError)");
        }
    }

    /**
     * Registrar pago de auxilio aprobado
     */
    public function registrarPago($id): void
    {
        try {
            // $json = file_get_contents('php://input');
            // $data = json_decode($json, true) ?? [];

            // Log::info("Inputs para registrar pago", $this->all());

            $data = [
                'fecha' => $this->input('fecha_pago') ?? null,
                'monto' => $this->input('monto_pago') ?? null,
                'numdoc' => $this->input('numdoc') ?? null,
                'concepto' => $this->input('concepto') ?? null,
                'forma_pago' => $this->input('forma_pago') ?? null,
                'id_ctbbanco' => $this->input('id_ctbbanco') ?? null,
                'banco_numdoc' => $this->input('banco_numdoc') ?? null,
                'destinatario_cheque' => $this->input('destinatario_cheque') ?? null,
            ];

            $rules = [
                'fecha' => 'required|date',
                'monto' => 'required|numeric|min:0.01',
                'forma_pago' => 'required|string',
                'concepto' => 'required|string|max_length:255',
                'numdoc' => 'nullable|string|max_length:50',
                'id_ctbbanco' => 'required_if:forma_pago,banco|nullable|integer',
                'banco_numdoc' => 'required_if:forma_pago,banco|nullable|string|max_length:50',
                'destinatario_cheque' => 'required_if:forma_pago,banco|nullable|string|max_length:100',
            ];

            $validator = new Validator($data, $rules);

            if ($validator->fails()) {
                // $this->error($validator->firstOnErrors() ?? 'Error de validación');
                // return;
                throw new SoftException($validator->firstOnErrors() ?? 'Error de validación');
            }

            $auxilio = Auxilio::find($id);

            if (!$auxilio) {
                throw new SoftException("Auxilio no encontrado");
            }

            if ($auxilio->estado !== 'aprobado') {
                throw new SoftException("Solo se pueden pagar auxilios aprobados");
            }

            if ($data['forma_pago'] === 'banco') {
                // actualizar cuenta bancaria (depositar)
                $bancoCuenta = BancosCuenta::find($data['id_ctbbanco']);
                if (!$bancoCuenta) {
                    throw new SoftException("No se encontró la cuenta bancaria seleccionada.");
                }

                $idHaberNomenclatura = $bancoCuenta->nomenclatura->id;
            } else {
                $agencia = Agencia::find($this->getAgencyId());
                if (!$agencia) {
                    throw new SoftException("No se encontró la agencia del usuario.");
                }
                $idHaberNomenclatura = $agencia->nomenclaturaCaja->id;
            }

            DB::beginTransaction();

            // Crear registro de pago
            $pago = Pago::create([
                'id_auxilio' => $auxilio->id,
                'fecha' => $data['fecha'],
                'monto' => $data['monto'],
                'numdoc' => $data['numdoc'] ?? null,
                'forma_pago' => $data['forma_pago'],
                'concepto' => $data['concepto'],
                'id_ctbbanco' => $data['forma_pago'] === 'banco' ? ($data['id_ctbbanco'] ?? null) : null,
                'banco_numdoc' =>  $data['forma_pago'] === 'banco' ? ($data['banco_numdoc'] ?? null) : null,
                'banco_fecha' => $data['forma_pago'] === 'banco' ? ($data['fecha'] ?? null) : null,
                'created_by' => Auth::getUserId(),
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Actualizar estado del auxilio
            $auxilio->update([
                'estado' => 'pagado',
                'updated_by' => Auth::getUserId(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            /**
             * registro en la diario
             */
            $diario = Diario::create([
                'numcom' => Beneq::getNumcomEloquent($this->getUserId(), $this->getAgencyId(), $data['fecha']),
                'id_ctb_tipopoliza' => 2, // ingresos
                'id_tb_moneda' => 1, // quetzales
                'numdoc' => $data['numdoc'],
                'glosa' => $data['concepto'],
                'fecdoc' => $data['fecha'],
                'feccnt' => $data['fecha'],
                'cod_aux' => 'AUX_' . $auxilio->id_cuenta,
                'id_tb_usu' => $this->getUserId(),
                'karely' => 'AUXP_' . $pago->id,
                'id_agencia' => $this->getAgencyId(),
                'fecmod' => date('Y-m-d H:i:s'),
                'editable' => 1,
                'created_by' => $this->getUserId(),
            ]);

            $diario->movimientos()->createMany([
                [
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' =>  $idHaberNomenclatura,
                    'debe' => 0,
                    'haber' => $data['monto'],
                ],
                [
                    'id_fuente_fondo' => 1,
                    'id_ctb_nomenclatura' =>  $auxilio->cuenta->servicio->nomenclatura->id,
                    'debe' => $data['monto'],
                    'haber' => 0,
                ],
            ]);

            if ($data['forma_pago'] === 'banco') {
                // registrar cheque
                $diario->cheques()->create([
                    'id_cuenta_banco' => $data['id_ctbbanco'],
                    'numchq' => $data['banco_numdoc'],
                    'nomchq' => $data['destinatario_cheque'],
                    'monchq' => $data['monto'],
                    'modocheque' => 0,
                    'emitido' => '0',
                ]);
            }

            DB::commit();

            $this->success([
                'pago_id' => $pago->id,
                'auxilio_id' => $auxilio->id,
                'estado' => $auxilio->estado,
            ], "Pago registrado exitosamente");
        } catch (SoftException $se) {
            DB::rollBack();
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            DB::rollBack();
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al registrar pago ($codigoError)");
        }
    }

    /**
     * Obtener historial de auxilios
     */
    public function historial(): void
    {
        try {
            $estado = $_GET['estado'] ?? null;

            $query = Auxilio::with([
                'cuenta.cliente',
                'cuenta.beneficiarios',
                'pagos'
            ])->whereIn('estado', ['pagado', 'rechazado']);

            if ($estado) {
                $query->where('estado', $estado);
            }

            $historial = $query->orderBy('updated_at', 'desc')->get();

            $this->success(['data' => $historial], "Historial obtenido");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al obtener historial ($codigoError)");
        }
    }

    /**
     * Ver detalle de un auxilio
     */
    public function show($id): void
    {
        try {
            $auxilio = Auxilio::with([
                'cuenta.cliente',
                'cuenta.servicio',
                'cuenta.beneficiarios',
                'pagos.cuenta_banco',
                'documentos'
            ])->find($id);

            if (!$auxilio) {
                $this->error("Auxilio no encontrado");
                return;
            }

            $this->success(['data' => $auxilio], "Detalle del auxilio");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al obtener detalle ($codigoError)");
        }
    }

    /**
     * Procesar y guardar documentos usando FileStorageService
     */
    private function procesarDocumentos($idAuxilio, $archivos): void
    {
        try {
            FileStorageService::uploadFiles(
                $archivos,
                'auxilios',
                $idAuxilio,
                function ($filepath, $originalName, $newName) use ($idAuxilio) {
                    // Callback: guardar en BD cuando el archivo se suba exitosamente
                    Documento::create([
                        'id_auxilio' => $idAuxilio,
                        'descripcion' => $originalName,
                        'ruta' => $filepath,
                        'created_by' => Auth::getUserId(),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                }
            );
        } catch (Exception $e) {
            Log::error("Error al procesar documentos: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Servir archivo usando FileStorageService
     */
    public function servirArchivo($ruta): void
    {
        FileStorageService::serveFile($ruta, false);
    }

    /**
     * Listar cuentas bancarias
     */
    public function listarBancos(): void
    {
        try {
            $cuentasBancos = BancosCuenta::with('banco')
                ->get();

            $this->success(['data' => $cuentasBancos], "Cuentas bancarias obtenidas");
        } catch (SoftException $se) {
            $this->error("Advertencia: " . $se->getMessage());
        } catch (Exception $e) {
            $codigoError = Log::errorWithCode($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            $this->error("Error al listar auxilios ($codigoError)");
        }
    }
}
