<?php
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    include __DIR__ . '/../../../../includes/Config/config.php';
    header('location: ' . BASE_URL . '404.php');
}

session_start();
if (!isset($_SESSION['id_agencia'])) {
    echo json_encode(['Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 0]);
    return;
}
//Nueva Conexion
include __DIR__ . '/../../../../includes/Config/database.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);

//Antigua Conexion
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

require __DIR__ . '/../../../../fpdf/fpdf.php';
require __DIR__ . '/../../../../vendor/autoload.php';
include __DIR__ . '/../../../../src/funcphp/fun_ppg.php';

use Creditos\Utilidades\CalculoPagosDiarios;
use Creditos\Utilidades\CalculoPagosSemanales;
use Creditos\Utilidades\PaymentManager;

$utilidadesCreditos = new PaymentManager();

date_default_timezone_set('America/Guatemala');

$hoy2 = date("Y-m-d H:i:s");
$idusuario = $_SESSION["id"];

// $condi = (isset($input["condi"])) ? $input["condi"] : ((isset($_POST["condi"]) ? $_POST["condi"] : 0));
$archivo = $_POST["datosval"][3];
$idRegistro = $archivo[0];
$condi = $archivo[1];


switch ($condi) {
    case 'solicitud':
        if(isset($idRegistro)) {
            $cod2 = $idRegistro;
            if ($cod2 !== null) {
                try {
                    $database->openConnection();
                    $info = $database->getAllResults("SELECT * FROM " . $db_name_general . ".info_coperativa ins
                                                       INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop
                                                       WHERE ag.id_agencia=?", [$_SESSION['id_agencia']]);
                    if (empty($info)) {
                        throw new Exception("Institución asignada a la agencia no encontrada");
                    }
                    $movimiento = $database->selectColumns("tb_movimientos_caja", ["*"], "id=?", [$cod2]);
                    if (empty($movimiento)) {
                        throw new Exception("Movimiento no encontrado");
                    }
                    $usuario = $database->selectColumns("tb_usuario", ["id_usu", "nombre", "apellido"], "id_usu=?", [$movimiento[0]['created_by']]);
                    if (empty($usuario)) {
                        throw new Exception("Usuario creador no encontrado");
                    }
                    $nombreUsuario = $usuario[0]['nombre'] . ' ' . $usuario[0]['apellido'];
                    $detaMovimiento = 0;
                    if($movimiento[0]['detalle'] == 1){
                        $detaMovimiento = $database->getAllResults("SELECT mon.nombre, deno.tipo, mon.abr, deno.monto, deta.cantidad, deno.monto * deta.cantidad AS resultado 
                                                    FROM " . $db_name_general . ".denominaciones deno
                                                    INNER JOIN detalle_movimiento deta ON deta.id_denominacion = deno.id
                                                    INNER JOIN " . $db_name_general . ".tb_monedas mon ON mon.id = deno.id_moneda
                                                    WHERE deta.id_movimiento=?", [$movimiento[0]['id']]);
                        if (empty($detaMovimiento)) {
                            throw new Exception("Institución asignada a la agencia no encontrada");
                        }
                    }
                    
                    if (empty($movimiento)) {
                        throw new Exception("Movimiento no encontrado");
                    }
                    $status = 1;
                } catch (Exception $e) {
                    $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
                    $mensaje = "Error: Intente nuevamente o reporte este código de error ($codigoError)";
                    echo json_encode(['status' => 0, 'message' => $mensaje]);
                    exit; 
                } finally {
                    $database->closeConnection();
                }
                
                
                function printpdf( $cod2, $movimiento, $nombreUsuario, $detaMovimiento = [],$info = [])
                {
                    class PDF extends FPDF
                    {
                        private $info;
                        private $cod2;
                        private $movimiento;
                        private $nombreUsuario;
                        function __construct($info, $cod2, $movimiento, $nombreUsuario)
                        {
                            parent::__construct();
                            $this->info = $info;
                            $this->cod2 = $cod2;
                            $this->movimiento = $movimiento;
                            $this->nombreUsuario = $nombreUsuario;
                        }

                        function Header()
                        {
                            $hoy = date("Y-m-d H:i:s");
                            $this->SetFont('Arial', 'B', 8);

                            // $logoMicro = '../../../../includes/img/logomicro.png'; 

                            $this->Cell(0, 0, '', 0, 1, 'L', $this->Image('../../../../' . $this->info[0]["log_img"], 20, 12, 25));
                        
                            $nombreCompleto = isset($this->info[0]['nomb_comple']) ? decode_utf8($this->info[0]['nomb_comple']) : 'Información no disponible';
                            $nomb_cor = isset($this->info[0]["nomb_cor"]) ? decode_utf8($this->info[0]["nomb_cor"]) : "Información no disponible";
                            $muni_lug = isset($this->info[0]["muni_lug"]) ? $this->info[0]["muni_lug"] : "Información no disponible";
                            $email = isset($this->info[0]["emai"]) ? 'Email: ' . $this->info[0]["emai"] : "Email no disponible";
                            $tel = (isset($this->info[0]["tel_1"]) && isset($this->info[0]["tel_2"])) ? 'Tel: ' . $this->info[0]["tel_1"] . " Y " . $this->info[0]["tel_2"] : "Teléfono no disponible";
                            $nit = isset($this->info[0]["nit"]) ? 'NIT: ' . $this->info[0]["nit"] : "NIT no disponible";
                            $nom_agencia = isset($this->info[0]["nom_agencia"]) ? mb_strtoupper($this->info[0]["nom_agencia"], 'utf-8') : "Agencia no disponible";

                            $this->Cell(190, 3, $nombreCompleto, 0, 1, 'C');
                            $this->Cell(190, 3, $nomb_cor, 0, 1, 'C');
                            $this->Cell(190, 3, $muni_lug, 0, 1, 'C');
                            $this->Cell(190, 3, $email, 0, 1, 'C');
                            $this->Cell(190, 3, $tel, 0, 1, 'C');
                            $this->Cell(190, 3, $nit, 0, 1, 'C');
                            $this->Cell(0, 3, $nom_agencia, 'B', 1, 'C');
                            $this->SetFont('Arial', '', 7);
                            $this->SetXY(-30, 5);
                            $this->Cell(10, 2, $hoy, 0, 1, 'L');
                            $this->SetXY(-25, 8);
                            $this->Ln(25);
                        
                            $this->SetFont('Arial', 'B', 12);
                            $this->Cell(90, 5, 'Solicitud de Movimiento en Caja', 0, 1, 'L');

                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(50, 5, 'Usuario: ', 0, 0, 'L');

                            $this->SetFont('Arial', '', 10);
                            $this->Cell(0, 5, decode_utf8($this->nombreUsuario), 0, 1, 'L'); 

                            $tipoMovimiento = '';
                            if ($this->movimiento[0]['tipo'] == 1) {
                                $tipoMovimiento = 'Depósito';
                            } elseif ($this->movimiento[0]['tipo'] == 2) {
                                $tipoMovimiento = 'Retiro';
                            } else {
                                $tipoMovimiento = 'Inválido';
                            }
                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(50, 5, decode_utf8('Tipo de Movimiento: '), 0, 0, 'L');
                            $this->SetFont('Arial', '', 10);
                            $this->Cell(0, 5, decode_utf8($tipoMovimiento), 0, 1, 'L');
                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(50, 5, decode_utf8('No. de Transacción: '), 0, 0, 'L'); 
                            $this->SetFont('Arial', '', 10);
                            $this->Cell(0, 5, $this->cod2, 0, 1, 'L');  
                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(50, 5, 'Fecha y hora de Solicitud: ', 0, 0, 'L');
                            $this->SetFont('Arial', '', 10);
                            $this->Cell(0, 5, $this->movimiento[0]['created_at'], 0, 1, 'L'); 
                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(50, 5, decode_utf8('Estado: '), 0, 0, 'L');
                            $this->SetFont('Arial', '', 10);

                            switch ($this->movimiento[0]['estado']){
                                case '0':
                                    $this->Cell(0, 5, 'Rechazado en fecha ' . $this->movimiento[0]['updated_at'], 0, 1, 'L'); 
                                    break;
                                case '1':
                                    $this->Cell(0, 5, 'Solicitud', 0, 1, 'L'); 
                                    break;
                                case '2':
                                    $this->Cell(0, 5, 'Aprobado en fecha ' . $this->movimiento[0]['updated_at'], 0, 1, 'L');
                                    break;
                            }
                            $this->SetFont('Arial', 'B', 10);
                            $this->Cell(50, 5, 'Monto Total: ', 0, 0, 'L');
                            $this->SetFont('Arial', '', 10);
                            $this->Cell(0, 5, 'Q. ' . $this->movimiento[0]['total'], 0, 1, 'L'); 
                            $this->Ln(3);

                        }

                        function Footer()
                        {
                            $this->SetY(-15);
                            $this->SetFont('Arial', 'I', 8);
                            $this->Cell(0, 10, decode_utf8('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
                        }
                    function TableData($detaMovimiento = [])
                    {
                        $tipoMovimiento = '';
                        if ($this->movimiento[0]['tipo'] == 1) {
                            $tipoMovimiento = 'Depósito';
                        } elseif ($this->movimiento[0]['tipo'] == 2) {
                            $tipoMovimiento = 'Retiro';
                        } else {
                            $tipoMovimiento = 'Inválido';
                        }

                        $this->SetFont('Arial', '', 10);
                        $this->MultiCell(0, 7, decode_utf8("Por medio de la presente, me permito solicitar formalmente la realización de un " . $tipoMovimiento ." de efectivo, en cumplimiento con los procedimientos internos establecidos y las políticas de manejo de fondos. Este trámite se realiza con el objetivo de ajustar la disponibilidad de efectivo en la caja." ), 0, 'J');                            
                        if($this->movimiento[0]['detalle'] == 1){
                            $this->MultiCell(0, 5, decode_utf8("El monto se desglosa de la siguiente forma: "), 0, 'L');
                            $this->Ln(2);
                            $w = array(60, 50, 50);
                        
                            $this->SetX(($this->GetPageWidth() - array_sum($w)) / 2);
                        
                            $this->SetFillColor(44, 62, 80);
                            $this->SetTextColor(255, 255, 255); 
                        
                            $this->Cell($w[0], 7, decode_utf8('Denominación'), 1, 0, 'C', true);
                            $this->Cell($w[1], 7, 'Cantidad', 1, 0, 'C', true);
                            $this->Cell($w[2], 7, 'Total', 1, 1, 'C', true);

                            $this->SetTextColor(0, 0, 0); 

                            $i = 0;
                            $totalElementos = count($detaMovimiento);
                            $totalGeneral = 0;  
                            while ($i < $totalElementos) {
                                $monto = $detaMovimiento[$i]['monto'];
                                $abr = $detaMovimiento[$i]['abr'];
                                $resultado = $detaMovimiento[$i]['resultado'];
                                $cantidad = $detaMovimiento[$i]['cantidad'];
                        
                                if ($i % 2 == 0) {
                                    $this->SetFillColor(236, 240, 241); 
                                } else {
                                    $this->SetFillColor(255, 255, 255); 
                                }
                        
                                $this->SetX(($this->GetPageWidth() - array_sum($w)) / 2); 
                        
                                if($detaMovimiento[$i]['tipo'] == 1){
                                    $this->Cell($w[0], 7, $abr . ' - ' . $monto . " - Billete", 1, 0, 'C', true);
                                }else{
                                    $this->Cell($w[0], 7, $abr . ' - ' . $monto . " - Moneda", 1, 0, 'C', true);
                                }
                                
                                $this->Cell($w[1], 7, $cantidad, 1, 0, 'C', true);
                                $this->Cell($w[2], 7, number_format($resultado, 2), 1, 1, 'C', true);
                        
                                $totalGeneral += $resultado;

                                $i++;
                            }
                        
                            $this->SetFont('Arial', 'B', 10);
                            $this->SetX(($this->GetPageWidth() - array_sum($w)) / 2);
                        
                            $this->SetFillColor(52, 152, 219); 
                            $this->Cell($w[0] + $w[1], 7, 'Total General', 1, 0, 'C', true);  
                            $this->Cell($w[2], 7, number_format($totalGeneral, 2), 1, 1, 'C', true);
                        } else {
                            $this->Ln(5);
                            $this->SetFont('Arial', 'B', 12);
                            $this->MultiCell(0, 5, decode_utf8("El monto total solicitado no ha sido desglosado en denominaciones específicas. Por lo tanto, la operación se procesará de acuerdo con las denominaciones estándar disponibles en la bóveda, según las políticas internas de la institucion."), 0, 'C');
                            
                        }

                        // Espacio para la firma
                        $this->Ln(20);  

                        $this->SetFont('Arial', 'B', 10);
                        $this->Cell(0, 5, '________________________________', 0, 1, 'C');
                        $this->Cell(0, 5, decode_utf8($this->nombreUsuario), 0, 1, 'C');
                    }
                    }

                    $pdf = new PDF($info, $cod2, $movimiento, $nombreUsuario, $detaMovimiento);
                    $pdf->AliasNbPages();
                    $pdf->AddPage();
                    $pdf->TableData($detaMovimiento);

                    ob_start();
                    $pdf->Output();
                    $pdfData = ob_get_contents();
                    ob_end_clean();

                    $opResult = array(
                        'status' => 1,
                        'message' => 'Reporte generado correctamente',
                        'namefile' => "Solicitud Caja",
                        'tipo' => "pdf",
                        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
                    );
                    echo json_encode($opResult);
                }
                $tipo ="pdf";
                switch ($tipo) {
                    case 'xlsx':
                        // printxls($result, [$texto_reporte, $_SESSION['id'], $hoy]);
                        break;
                    case 'pdf':
                        printpdf($cod2, $movimiento, $nombreUsuario, $detaMovimiento,$info);
                        break;
                }
              
            } else {
                echo json_encode(['status' => 0, 'message' => 'No se pudo decodificar los datos JSON']);
            }
        } else {
            echo json_encode(['status' => 0, 'message' => 'No se recibieron datos']);
        }
    break;
}