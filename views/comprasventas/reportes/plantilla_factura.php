<?php
    session_start();
    //NUEVA CONEXION
    include __DIR__ . '/../../../includes/Config/database.php';
    $database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
    //ANTIGUA CONEXION
    include '../../../includes/BD_con/db_con.php';
    mysqli_set_charset($conexion, 'utf8');
    include '../../../src/funcphp/func_gen.php';
    require '../../../fpdf/fpdf.php';
    
    if (!isset($_SESSION['id_agencia'])) {
        echo json_encode(['status' => 0, 'mensaje' => 'Sesión expirada, vuelve a iniciar sesión e intente nuevamente']);
        return;
    }

    $queryins = mysqli_query($conexion, "SELECT * FROM $db_name_general.info_coperativa ins
    INNER JOIN tb_agencia ag ON ag.id_institucion=ins.id_cop WHERE ag.id_agencia=" . $_SESSION['id_agencia']);
    $info = [];
    $j = 0;
    while ($fil = mysqli_fetch_array($queryins)) {
        $info[$j] = $fil;
        $j++;
    }
    if ($j == 0) {
        echo json_encode(['status' => 0, 'mensaje' => 'Institución asignada a la agencia no encontrada']);
        return;
    }

    // Datos de la institución
    $oficina = decode_utf8($info[0]["nom_agencia"]);
    $institucion = decode_utf8($info[0]["nomb_comple"]);
    $direccionins = decode_utf8($info[0]["muni_lug"]);
    $emailins = $info[0]["emai"];
    $telefonosins = $info[0]["tel_1"] . '   ' . $info[0]["tel_2"];
    $nitins = $info[0]["nit"];
    $rutalogomicro = "../../../includes/img/logomicro.png";
    $rutalogoins = "../../.." . $info[0]["log_img"];

    $id = $_POST['id'];
    $database->openConnection();
    try { 
        $factura = $database->selectById("cv_facturas", $id, $columnid="id");
        $detallefactura = $database->selectDataID("cv_factura_items", "id_factura", $id);
        $emisor = $database->selectById("cv_emisor", $factura['id_emisor'], $columnid="id");
        $receptor = $database->selectById("cv_receptor", $factura['id_receptor'], $columnid="id");
        $certificador = $database->selectById("cv_certificador", $factura['id_certificador'], $columnid="id");
    } catch (Exception $e) {
        $mensaje = "Error: " . $e;
        $response = [
            'status' => 'error',
            'message' => 'Error: ' . $e->getMessage(),
        ];
    } finally {
        $database->closeConnection();
    }
    // --------------------------------------------------------------------------------------------------------------------------------
    class PDF extends FPDF
    {
        function RoundedRect($x, $y, $w, $h, $r, $style = '') {
            $k = $this->k;
            $hp = $this->h;
            if ($style == 'F') {
                $op = 'f';
            } elseif ($style == 'DF' || $style == 'FD') {
                $op = 'B';
            } else {
                $op = 'S';
            }
            $MyArc = 4 / 3 * (sqrt(2) - 1);
            $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
            $xc = $x + $w - $r;
            $yc = $y + $r;
            $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));
            $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
            $xc = $x + $w - $r;
            $yc = $y + $h - $r;
            $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
            $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
            $xc = $x + $r;
            $yc = $y + $h - $r;
            $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
            $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
            $xc = $x + $r;
            $yc = $y + $r;
            $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
            $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
            $this->_out($op);
        }
    
        function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
            $h = $this->h;
            $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
        }

        public $institucion;
        public $pathlogo;
        public $pathlogoins;
        public $oficina;
        public $direccion;
        public $email;
        public $telefonos;
        public $nit;

        public function __construct($institucion, $pathlogo, $pathlogoins, $oficina, $direccion, $email, $telefonos, $nit)
        {
            parent::__construct('P');
            $this->institucion = $institucion;
            $this->pathlogo = $pathlogo;
            $this->pathlogoins = $pathlogoins;
            $this->oficina = $oficina;
            $this->direccion = $direccion;
            $this->email = $email;
            $this->telefonos = $telefonos;
            $this->nit = $nit;
        }
        function Header()
        {
            $hoy = date("Y-m-d H:i:s");
            // Logo 
            $this->Image($this->pathlogoins, 10, 8, 33);
            $this->SetFont('Arial', '', 8);
            $this->Cell(0, 3, $this->institucion, 0, 1, 'C');
            $this->Cell(0, 3, $this->direccion, 0, 1, 'C');
            $this->Cell(0, 3, 'Email: ' . $this->email, 0, 1, 'C');
            $this->Cell(0, 3, 'Tel: ' . $this->telefonos, 0, 1, 'C');
            $this->Cell(0, 3, 'NIT: ' . $this->nit, 'B', 1, 'C');
            $this->SetFont('Arial', '', 7);
            $this->SetXY(-30, 5);
            $this->Cell(10, 2, $hoy, 0, 1, 'L');
            $this->SetXY(-25, 8);
            $this->Ln(10);
        }

    }
    $pdf = new PDF($institucion, $rutalogomicro, $rutalogoins, $oficina, $direccionins, $emailins, $telefonosins, $nitins);

    $pdf->AliasNbPages();
    $pdf->AddPage();
    $fuente = 'Helvetica';
    $tamañofuente = 7;
    $ancho_linea = 40;
    $tamanio_linea = 6; //altura de la linea/celda
    $ancho_linea2 = 20; //anchura de la linea/celda
    $fila = 0;
    // Configuración de colores
    $pdf->SetFillColor(173, 216, 230); // Color celeste para recuadro
    $pdf->SetTextColor(0, 0, 0);      // Color de texto

    // Márgenes personalizados
    $margen_izquierdo = 10;
    $pdf->SetLeftMargin($margen_izquierdo);

    // Sección: Información General (2 columnas en recuadro)
    $pdf->SetFont($fuente, 'B', $tamañofuente);

    $pdf->Cell(190, 10, '', 0, 1, 'C', 0); // Espacio superior
    $altura_recuadro = 3 * $tamanio_linea; // Altura ajustada al contenido
    $pdf->RoundedRect(10, $pdf->GetY(), 190, $altura_recuadro, 2, 'DF');

    $x_inicial = 12;
    $y_inicial = $pdf->GetY();
    $pdf->SetXY($x_inicial, $y_inicial); // Ajustar posición inicial dentro del recuadro

    $pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8('Número de Autorización:'), 0, 0, 'L');
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell($ancho_linea + 30, $tamanio_linea, $factura['codigo_autorizacion'], 0, 0, 'L');

    $pdf->SetXY(110, $y_inicial);
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8('Serie:'), 0, 0, 'L');
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell($ancho_linea + 30, $tamanio_linea, $factura['serie'], 0, 1, 'L');

    $pdf->SetXY($x_inicial, $y_inicial + $tamanio_linea);
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8('Fecha de Emisión:'), 0, 0, 'L');
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell($ancho_linea + 30, $tamanio_linea, $factura['fechahora_emision'], 0, 0, 'L');

    $pdf->SetXY(110, $y_inicial + $tamanio_linea);
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8('Num. de DTE:'), 0, 0, 'L');
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell($ancho_linea + 30, $tamanio_linea, $factura['no_autorizacion'], 0, 1, 'L');

    $pdf->SetXY($x_inicial, $y_inicial + 2 * ($tamanio_linea));
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->Cell($ancho_linea, $tamanio_linea, decode_utf8('Tipo de DTE:'), 0, 0, 'L');
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell($ancho_linea + 50, $tamanio_linea, 'FACTURA', 0, 1, 'L');

    $pdf->Ln(5); // Espaciado entre secciones

    // Sección: Información del Emisor y Receptor (2 columnas)
    $tamanio_linea = 5;
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->Cell(95, $tamanio_linea, decode_utf8('EMISOR'), 0, 0, 'L');
    $pdf->Cell(95, $tamanio_linea, decode_utf8('RECEPTOR'), 0, 1, 'L');

    // Fila 1: NIT (Emisor y Receptor)
    $pdf->Cell(45, $tamanio_linea, decode_utf8('NIT:'), 0, 0, 'L');
    $pdf->SetXY(45, $pdf->GetY());
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell(50, $tamanio_linea, $emisor['nit'], 0, 0, 'L'); // NIT Emisor

    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(110, $pdf->GetY()); // Ajustar para receptor en la misma línea
    $pdf->Cell(45, $tamanio_linea, decode_utf8('NIT:'), 0, 0, 'L');
    $pdf->SetXY(140, $pdf->GetY());
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell(70, $tamanio_linea, $receptor['id_receptor'], 0, 1, 'L'); // NIT Receptor

    // Fila 2: Razón Social (Emisor y Receptor)
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(10, $pdf->GetY()); // Alineación a la siguiente línea
    $pdf->Cell(45, $tamanio_linea, decode_utf8('Razón Social:'), 0, 0, 'L');
    $pdf->SetXY(45, $pdf->GetY());  // Ajustar posición para MultiCell
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->MultiCell(60, $tamanio_linea, $emisor['nombre_comercial'], 0, 'L'); // MultiCell para texto largo

    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(110, $pdf->GetY() - 9); // Alineación a la misma fila para receptor
    $pdf->Cell(45, $tamanio_linea, decode_utf8('Nombre:'), 0, 0, 'L');
    $pdf->SetXY(140, $pdf->GetY());  // Ajustar posición para MultiCell
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->MultiCell(60, $tamanio_linea, $receptor['nombre'], 0, 'L'); // MultiCell para texto largo

    // Fila 3: Nombre (Emisor y Receptor)
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(10, $pdf->GetY() + 3); // Alineación a la siguiente línea
    $pdf->Cell(45, $tamanio_linea, decode_utf8('Nombre:'), 0, 0, 'L');
    $pdf->SetXY(45, $pdf->GetY());  // Ajustar posición para MultiCell
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->MultiCell(60, $tamanio_linea, $emisor['nombre'], 0, 'L'); // MultiCell para texto largo

    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(110, $pdf->GetY() - 6); // Alineación a la misma fila para receptor
    $pdf->Cell(45, $tamanio_linea, decode_utf8('Correo Electronico:'), 0, 0, 'L');
    $pdf->SetXY(140, $pdf->GetY());  // Ajustar posición para MultiCell
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->MultiCell(70, $tamanio_linea, $receptor['correo'] ?? '', 0, 'L'); // MultiCell para texto largo

    // Fila 4: Email (Emisor y Receptor)
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(10, $pdf->GetY() + 3); // Alineación a la siguiente línea
    $pdf->Cell(45, $tamanio_linea, decode_utf8('Correo Electronico:'), 0, 0, 'L');
    $pdf->SetXY(45, $pdf->GetY());
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell(60, $tamanio_linea, $emisor['correo'] ?? '', 0, 0, 'L'); // Email Emisor

    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(110, $pdf->GetY()); // Alineación para receptor
    $pdf->Cell(45, $tamanio_linea, decode_utf8('Direccion:'), 0, 0, 'L');
    $pdf->SetXY(140, $pdf->GetY());
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->Cell(70, $tamanio_linea, $receptor['direccion'] ?? '', 0, 1, 'L'); // Email Receptor

    // Fila 5: Dirección (Receptor)
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->SetXY(10, $pdf->GetY() + 3); // Alineación a la siguiente línea
    $pdf->Cell(45, $tamanio_linea, decode_utf8('Direccion:'), 0, 0, 'L');
    $pdf->SetXY(45, $pdf->GetY());  // Ajustar posición para MultiCell
    $pdf->SetFont($fuente, '', $tamañofuente);
    $pdf->MultiCell(60, $tamanio_linea, $emisor['direccion'] ?? '', 0, 'L'); // MultiCell para texto largo

    $pdf->Ln(5); // Espaciado entre secciones
    $ancho_columna = 20;
    // Encabezados de las columnas
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->Cell(10, $tamanio_linea, 'B/S', 1, 0, 'C', 1);
    $pdf->Cell(15, $tamanio_linea, 'CANT', 1, 0, 'C', 1);
    $pdf->Cell($ancho_columna * 2, $tamanio_linea, 'DESCRIPCION', 1, 0, 'C', 1);
    $pdf->Cell(30, $tamanio_linea, 'P. UNIT Q(CON IVA)', 1, 0, 'C', 1);
    $pdf->Cell($ancho_columna, $tamanio_linea, 'DESCUENTO', 1, 0, 'C', 1);
    $pdf->Cell(30, $tamanio_linea, 'OTROS DESCUENTOS', 1, 0, 'C', 1);
    $pdf->Cell(25, $tamanio_linea, 'TOTAL', 1, 0, 'C', 1);
    $pdf->Cell($ancho_columna, $tamanio_linea, 'IMPUESTO', 1, 1, 'C', 1);

    // Variable para sumar el total
    $total_factura = 0;

    // Recorriendo los datos de los productos o servicios
    foreach ($detallefactura as $item) {
        // Llenar las celdas con los datos del arreglo
        $pdf->SetFont($fuente, '', $tamañofuente);
        $pdf->Cell(10, $tamanio_linea, $item['tipo'], 1, 0, 'C');  // B/S
        $pdf->Cell(15, $tamanio_linea, round($item['cantidad'], 2), 1, 0, 'C');  // CANT
        $pdf->Cell($ancho_columna * 2, $tamanio_linea, $item['descripcion'], 1, 0, 'L');  // DESCRIPCION
        $pdf->Cell(30, $tamanio_linea, number_format($item['precio_unitario'], 2), 1, 0, 'R');  // P. UNIT Q(CON IVA)
        $pdf->Cell($ancho_columna, $tamanio_linea, number_format($item['descuento'], 2), 1, 0, 'R');  // DESCUENTO
        $pdf->Cell(30, $tamanio_linea, number_format($item['otros_descuentos'], 2), 1, 0, 'R');  // OTROS DESCUENTOS
        $pdf->Cell(25, $tamanio_linea, number_format($item['total'], 2), 1, 0, 'R');  // TOTAL
        $pdf->Cell($ancho_columna, $tamanio_linea, number_format($item['impuesto'], 2), 1, 1, 'R');  // IMPUESTO

        // Sumar el total a la variable
        $total_factura += $item['total'];
    }

    // Espacio antes de mostrar el total
    $pdf->Ln(5);

    // Mostrar el total de la factura
    $pdf->SetFont($fuente, 'B', $tamañofuente);
    $pdf->Cell(165, $tamanio_linea, 'TOTAL FACTURA:', 1, 0, 'C', 1);
    $pdf->Cell(25, $tamanio_linea, number_format($total_factura, 2), 1, 1, 'R', 1);

    $pdf ->Ln(2);
    $texto_certificador = 'CERTIFICADOR: ' . $certificador['nombre'] . ', Nit: ' . $certificador['nit'];
    $pdf->Cell(190, 5, decode_utf8($texto_certificador), 1, 1, 'L');

    // Fin del documento
    ob_start();
    $pdf->Output();
    $pdfData = ob_get_contents();
    ob_end_clean();

    $opResult = array(
        'status' => 1,
        'mensaje' => 'PDF generado correctamente',
        'namefile' => "Perfil_economico",
        'tipo' => "pdf",
        'data' => "data:application/pdf;base64," . base64_encode($pdfData)
    );
    echo json_encode($opResult);
?>