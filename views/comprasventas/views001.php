<?php

use PhpOffice\PhpSpreadsheet\Reader\Xml\Style\NumberFormat;

include __DIR__ . '/../../includes/Config/config.php';
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('location: ' . BASE_URL . '404.php');
}
session_start();
// if (!isset($_SESSION['id_agencia'])) {
//     http_response_code(400);
//     echo json_encode(['messagecontrol' => "expired", 'mensaje' => 'Sesion expirada, vuelve a iniciar sesion e intente nuevamente', 'url' => BASE_URL]);
//     return;
// }
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');

$idusuario = $_SESSION['id'];
$ofi = $_SESSION['agencia'];
include_once "../../src/cris_modales/mdls_Facturas.php";
include_once "../../src/cris_modales/mdls_buscarfacturas.php";
include __DIR__ . '/../../includes/Config/database.php';
require_once __DIR__ . '/../../includes/Config/CSRFProtection.php';
require_once __DIR__ . '/../../includes/Config/SecureID.php';
$database = new Database($db_host, $db_name, $db_user, $db_password, $db_name_general);
include __DIR__ . '/../../src/funcphp/func_gen.php';
date_default_timezone_set('America/Guatemala');

$csrf = new CSRFProtection();
$secureID = new SecureID($key1);

$condi = $_POST["condi"];
switch ($condi) {
    case 'loadfiles':
        /**
         * PARA CARGA DE FACTURAS DE COMPRAS
         */
        $xtra = $_POST["xtra"];
        //1 ventas, 2 compras
        $imgurl =  "https://cdn.pixabay.com/photo/2018/09/11/16/34/pay-3669963_1280.jpg";
        $tipofactura = 2;
        $title = " compras";
?>
        <style>
            .card-image-container {
                height: 20rem;
                overflow: hidden;
            }

            .card-image-container img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: top;
            }
        </style>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="loadfilesventas" style="display: none;">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-image-container">
                            <img src="<?php echo $imgurl; ?>" class="card-img-top" alt="Facturas">
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Facturas de <?php echo $title; ?></h5>
                            <p class="card-text">Se requiere que cargue un archivo comprimido en formato .zip. Este archivo debe
                                contener todas
                                las facturas de <?php echo $title; ?> en formato .xml. Asegúrese de que todos los documentos
                                estén incluidos y correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="zipFile" accept=".zip" />
                                <label class="input-group-text" for="zipFile">Cargar</label>
                            </div>
                            <button type="button" class="btn btn-outline-success"
                                onclick="loadfilecompressed(<?php echo $tipofactura; ?>);">
                                <i class="fa-regular fa-floppy-disk"></i>Guardar facturas
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" id="invoice_success" style="display: none;">
                        <div class="card-body">
                            <h5 class="card-title">Facturas guardadas</h5>
                            <ul class="list-group" id="group_success">

                            </ul>
                        </div>
                    </div>
                    <div class="card" id="invoice_danger" style="display: none;">
                        <div class="card-body">
                            <h5 class="card-title">Facturas No guardadas</h5>
                            <ul class="list-group" id="group_danger">

                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <script>
            //CONVERTIR ARCHIVOS XML A FORMATO JSON
            function xmlToJson(xml) {
                let obj = {};
                if (xml.nodeType === 1) { // Element
                    if (xml.attributes.length > 0) {
                        obj["@attributes"] = {};
                        for (let j = 0; j < xml.attributes.length; j++) {
                            const attribute = xml.attributes.item(j);
                            obj["@attributes"][attribute.nodeName] = attribute.nodeValue;
                        }
                    }
                } else if (xml.nodeType === 3) { // Text
                    obj = xml.nodeValue;
                }
                if (xml.hasChildNodes()) {
                    for (let i = 0; i < xml.childNodes.length; i++) {
                        const item = xml.childNodes.item(i);
                        const nodeName = item.nodeName;
                        if (typeof(obj[nodeName]) === "undefined") {
                            obj[nodeName] = xmlToJson(item);
                        } else {
                            if (typeof(obj[nodeName].push) === "undefined") {
                                const old = obj[nodeName];
                                obj[nodeName] = [];
                                obj[nodeName].push(old);
                            }
                            obj[nodeName].push(xmlToJson(item));
                        }
                    }
                }
                return obj;
            }

            function loadfilecompressed(tipofactura) {
                loaderefect(1);
                let fileInput = document.getElementById('zipFile');
                let file = fileInput.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const arrayBuffer = e.target.result;
                        JSZip.loadAsync(arrayBuffer).then(function(zip) {
                            let allJson = []; // Array para almacenar cada JSON generado
                            let promises = []; // Array para almacenar las promesas de procesamiento de cada archivo XML

                            zip.forEach(function(relativePath, zipEntry) {
                                if (zipEntry.name.endsWith('.xml')) {
                                    let promise = zipEntry.async('text').then(function(content) {
                                        // Convertir XML a JSON
                                        const parser = new DOMParser();
                                        const xmlDoc = parser.parseFromString(content, 'text/xml');
                                        const json = xmlToJson(xmlDoc);
                                        allJson.push(json); // Añadir el JSON generado al array
                                    });
                                    promises.push(promise); // Añadir la promesa al array de promesas
                                }
                            });

                            // Esperar a que todas las promesas se resuelvan
                            Promise.all(promises).then(function() {
                                // Combinar todos los JSON en un solo objeto
                                const combinedJson = {
                                    files: allJson
                                };
                                //SE ENVIA EL PAQUETE CON TODOS LOS JSON AL SERVIDOR
                                sendJsonToServer(combinedJson, tipofactura)
                            });
                        });
                    };
                    reader.readAsArrayBuffer(file);
                } else {
                    loaderefect(0);
                    alert("No se ha seleccionado ningún archivo");
                }
            }

            function sendJsonToServer(jsonData, tipofactura) {
                $.ajax({
                    url: 'functions/functions.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        'condi': "savefacturas",
                        'tipof': tipofactura,
                        'jsondata': jsonData
                    }),
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener("progress", function(evt) {
                            if (evt.lengthComputable) {
                                var porcentaje = (evt.loaded / evt.total) * 100;
                                console.log('Progreso: ' + porcentaje.toFixed(2) + '%');
                                // actualizarBarraDeProgreso(porcentaje);
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        console.log(response);
                        const data2 = JSON.parse(response);

                        console.log(data2);
                        // echo json_encode([$mensaje, $status, $idsinsertados,$noinsertados]);
                        if (data2[1] == 1) {
                            document.getElementById('zipFile').value = "";
                            generarListaFacturas(data2[2], 'group_success', 'list-group-item-success',
                                'invoice_success');
                            generarListaFacturas(data2[3], 'group_danger', 'list-group-item-danger', 'invoice_danger');
                            alert(data2[0]);
                        } else {
                            alert(data2[0]);
                        }
                    },
                    complete: function(data) {
                        loaderefect(0);
                        // console.log(data);
                    },
                    error: function(error) {
                        console.error('Error:', error);
                    }
                });
            }

            function generarListaFacturas(facturas, elementoId, clase, idDiv) {
                const ul = document.getElementById(elementoId);
                ul.innerHTML = '';

                facturas.forEach(factura => {
                    $('#' + idDiv).show();
                    let razon = (factura[3] != "") ? " Razon: " + factura[3] : "";

                    let texto = "Numero: " + factura[0] + " Serie: " + factura[1] + " => " + factura[2] + razon;
                    const li = document.createElement('li');
                    li.className = `list-group-item ${clase}`;
                    li.textContent = texto;
                    ul.appendChild(li);
                });
            }
        </script>
    <?php
        break;
    case 'loadfilesventas':
        $xtra = $_POST["xtra"];
        //1 ventas, 2 compras
        $imgurl =  "https://cdn.pixabay.com/photo/2018/12/18/22/21/money-3883174_1280.png";
        $tipofactura = 1;
        $title = " ventas";
    ?>
        <style>
            .card-image-container {
                height: 20rem;
                overflow: hidden;
            }

            .card-image-container img {
                width: 100%;
                height: 100%;
                object-fit: cover;
                object-position: top;
            }
        </style>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="loadfilesventas" style="display: none;">
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.7.1/jszip.min.js"></script>
        <div class="container">
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" style="width: 100%;">
                        <div class="card-image-container">
                            <img src="<?php echo $imgurl; ?>" class="card-img-top" alt="Facturas">
                        </div>
                        <div class="card-body">
                            <h5 class="card-title">Facturas de <?php echo $title; ?></h5>
                            <p class="card-text">Se requiere que cargue un archivo comprimido en formato .zip. Este archivo debe
                                contener todas
                                las facturas de <?php echo $title; ?> en formato .xml. Asegúrese de que todos los documentos
                                estén incluidos y correctamente
                                formateados antes de realizar la carga.</p>
                            <div class="input-group mb-3">
                                <input type="file" class="form-control" id="zipFile" accept=".zip" />
                                <label class="input-group-text" for="zipFile">Cargar</label>
                            </div>
                            <button type="button" class="btn btn-outline-success"
                                onclick="loadfilecompressed(<?php echo $tipofactura; ?>);">
                                <i class="fa-regular fa-floppy-disk"></i>Guardar facturas
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-12 col-md-12 col-sm-12">
                    <div class="card" id="invoice_success" style="display: none;">
                        <div class="card-body">
                            <h5 class="card-title">Facturas guardadas</h5>
                            <ul class="list-group" id="group_success">

                            </ul>
                        </div>
                    </div>
                    <div class="card" id="invoice_danger" style="display: none;">
                        <div class="card-body">
                            <h5 class="card-title">Facturas No guardadas</h5>
                            <ul class="list-group" id="group_danger">

                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>


        <script>
            //CONVERTIR ARCHIVOS XML A FORMATO JSON
            function xmlToJson(xml) {
                let obj = {};
                if (xml.nodeType === 1) { // Element
                    if (xml.attributes.length > 0) {
                        obj["@attributes"] = {};
                        for (let j = 0; j < xml.attributes.length; j++) {
                            const attribute = xml.attributes.item(j);
                            obj["@attributes"][attribute.nodeName] = attribute.nodeValue;
                        }
                    }
                } else if (xml.nodeType === 3) { // Text
                    obj = xml.nodeValue;
                }
                if (xml.hasChildNodes()) {
                    for (let i = 0; i < xml.childNodes.length; i++) {
                        const item = xml.childNodes.item(i);
                        const nodeName = item.nodeName;
                        if (typeof(obj[nodeName]) === "undefined") {
                            obj[nodeName] = xmlToJson(item);
                        } else {
                            if (typeof(obj[nodeName].push) === "undefined") {
                                const old = obj[nodeName];
                                obj[nodeName] = [];
                                obj[nodeName].push(old);
                            }
                            obj[nodeName].push(xmlToJson(item));
                        }
                    }
                }
                return obj;
            }

            function loadfilecompressed(tipofactura) {
                loaderefect(1);
                let fileInput = document.getElementById('zipFile');
                let file = fileInput.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const arrayBuffer = e.target.result;
                        JSZip.loadAsync(arrayBuffer).then(function(zip) {
                            let allJson = []; // Array para almacenar cada JSON generado
                            let promises = []; // Array para almacenar las promesas de procesamiento de cada archivo XML

                            zip.forEach(function(relativePath, zipEntry) {
                                if (zipEntry.name.endsWith('.xml')) {
                                    let promise = zipEntry.async('text').then(function(content) {
                                        // Convertir XML a JSON
                                        const parser = new DOMParser();
                                        const xmlDoc = parser.parseFromString(content, 'text/xml');
                                        const json = xmlToJson(xmlDoc);
                                        allJson.push(json); // Añadir el JSON generado al array
                                    });
                                    promises.push(promise); // Añadir la promesa al array de promesas
                                }
                            });

                            // Esperar a que todas las promesas se resuelvan
                            Promise.all(promises).then(function() {
                                // Combinar todos los JSON en un solo objeto
                                const combinedJson = {
                                    files: allJson
                                };
                                //SE ENVIA EL PAQUETE CON TODOS LOS JSON AL SERVIDOR
                                sendJsonToServer(combinedJson, tipofactura)
                            });
                        });
                    };
                    reader.readAsArrayBuffer(file);
                } else {
                    loaderefect(0);
                    alert("No se ha seleccionado ningún archivo");
                }
            }

            function sendJsonToServer(jsonData, tipofactura) {
                $.ajax({
                    url: 'functions/functions.php',
                    type: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        'condi': "savefacturas",
                        'tipof': tipofactura,
                        'jsondata': jsonData
                    }),
                    xhr: function() {
                        var xhr = new window.XMLHttpRequest();
                        xhr.upload.addEventListener("progress", function(evt) {
                            if (evt.lengthComputable) {
                                var porcentaje = (evt.loaded / evt.total) * 100;
                                console.log('Progreso: ' + porcentaje.toFixed(2) + '%');
                                // actualizarBarraDeProgreso(porcentaje);
                            }
                        }, false);
                        return xhr;
                    },
                    success: function(response) {
                        console.log(response);
                        const data2 = JSON.parse(response);

                        console.log(data2);
                        // echo json_encode([$mensaje, $status, $idsinsertados,$noinsertados]);
                        if (data2[1] == 1) {
                            document.getElementById('zipFile').value = "";
                            generarListaFacturas(data2[2], 'group_success', 'list-group-item-success',
                                'invoice_success');
                            generarListaFacturas(data2[3], 'group_danger', 'list-group-item-danger', 'invoice_danger');
                            alert(data2[0]);
                        } else {
                            alert(data2[0]);
                        }
                    },
                    complete: function(data) {
                        loaderefect(0);
                        // console.log(data);
                    },
                    error: function(error) {
                        console.error('Error:', error);
                    }
                });
            }

            function generarListaFacturas(facturas, elementoId, clase, idDiv) {
                const ul = document.getElementById(elementoId);
                ul.innerHTML = '';

                facturas.forEach(factura => {
                    $('#' + idDiv).show();
                    let razon = (factura[3] != "") ? " Razon: " + factura[3] : "";

                    let texto = "Numero: " + factura[0] + " Serie: " + factura[1] + " => " + factura[2] + razon;
                    const li = document.createElement('li');
                    li.className = `list-group-item ${clase}`;
                    li.textContent = texto;
                    ul.appendChild(li);
                });
            }
        </script>
    <?php
        break;
    case 'invoices':
        $xtra = $_POST["xtra"];
        $idfactura = $xtra;
        $tipofactura = 2;
        $statusf = 2;
        // $idfactura = 16;
        $strquery = "SELECT fac.id id_fact, fechahora_emision,codigo_autorizacion,td.codigo,serie,no_autorizacion,em.nit nit_emisor,em.nombre nombre_emisor,
                        cod_establecimiento,nombre_comercial,em.direccion direcemisor,em.correo correoemisor,rec.id_receptor,rec.nombre nombre_receptor,
                        rec.direccion direreceptor,rec.correo correoreceptor,cert.nit nit_certificador,cert.nombre nombre_certificador, id_moneda,
                        fit.*,fitm.monto_impuesto,imtip.descripcion nameimpuesto,imtip.id idtipim,fac.fechahora_certificacion,fac.tipo tipo_factura,fac.estado statusf, fac.origen_factura orign
                        FROM cv_facturas fac 
                        INNER JOIN cv_factura_items fit ON fac.id=fit.id_factura
                        INNER JOIN cv_facturaitems_impuestos fitm ON fitm.id_factura_items=fit.id
                        INNER JOIN cv_impuestosunidadgravable ugr ON ugr.id=fitm.id_impuestos_unidadgravable
                        INNER JOIN cv_impuestos_tipo imtip ON imtip.id=ugr.id_cvimpuestostipo
                        INNER JOIN cv_tiposdte td ON fac.id_tipo=td.id
                        INNER JOIN cv_receptor rec ON fac.id_receptor=rec.id
                        INNER JOIN cv_emisor em ON fac.id_emisor=em.id
                        INNER JOIN cv_certificador cert ON fac.id_certificador=cert.id
                        WHERE fac.estado IN (1,2) AND fac.id=?
                        ORDER BY fac.fechahora_emision,fac.id;";

        $query2 = "SELECT esc.* FROM cv_factura_frases fra
                        INNER JOIN cv_escenarios esc ON esc.id=fra.id_escenario
                        WHERE fra.id_factura=?;";

        try {
            $database->openConnection();
            $factura = $database->getAllResults($strquery, [$idfactura]);
            if (empty($factura)) {
                $showmensaje = true;
                throw new Exception($xtra . " No se encontró la factura --- :  " . $tipofactura);
            }
            $frases = $database->getAllResults($query2, [$idfactura]);
            if (empty($frases)) {
                // $showmensaje = true;
                // throw new Exception("No se encontró la factura");
            }
            $tiposimpuestos = $database->selectAll('cv_impuestos_tipo');
            $tipofactura = $factura[0]["tipo_factura"];
            $titlefacturacion = "Factura de Compra";
            $statusf = $factura[0]["statusf"];

            $mensaje = "";
            $status = 1;
        } catch (Exception $e) {
            $mensaje = " " . $e->getMessage();
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if (!$status) {
            echo "error: $mensaje";
        }
        $title = ($tipofactura == 1) ? "Ventas" : (($tipofactura == 2) ? "Compras" : "");
        $titlecolumn = ($tipofactura == 1) ? "Receptor" : (($tipofactura == 2) ? "Emisor" : "X");
        $checked = ($statusf == 1) ? "checked" : "";
    ?>
        <style>
            #tb_facturadetalle tbody tr td {
                font-size: 0.70rem;
                /* Ajusta el tamaño de la fuente según sea necesario */
            }

            .toggle {
                --bg-toggle: hsl(0, 0%, 75%);
                /* Gris claro */
                --bg-circle: hsl(0, 0%, 50%);
                /* Gris medio */
                width: 120px;
                height: 60px;
                background-color: var(--bg-toggle);
                box-shadow: 0 .3rem 5rem 0 rgba(125, 125, 125, 0.25);
                border-radius: 4rem;
                display: flex;
                align-items: center;
                padding: 0 .3rem;
                transition: background-color 400ms;
            }

            .toggle__circle {
                width: 50px;
                height: 50px;
                cursor: pointer;
                background-color: var(--bg-circle);
                border-radius: 50%;
                position: relative;
                transition: margin 400ms ease-in-out, background-color 1000ms;
            }

            .toggle__circle::after,
            .toggle__circle::before {
                content: '';
                position: absolute;
                background-color: var(--bg-toggle);
                bottom: 118%;
                transform-origin: bottom left;
            }

            .toggle__circle::before {
                width: 15px;
                height: 25px;
                left: 32%;
                border-radius: 0% 100% 0% 100% / 0% 27% 73% 100%;
                transform: translateX(-70%) rotate(-2deg);
            }

            .toggle__circle::after {
                width: 25px;
                height: 30px;
                left: 48%;
                border-radius: 100% 0% 100% 0% / 100% 0% 100% 0%;
                transform: rotate(-20deg);
            }

            #statusfact:checked+.toggle>.toggle__circle {
                margin-left: calc(120px - (.3rem * 2) - 50px);
            }

            #statusfact:checked+.toggle {
                --bg-toggle: hsl(96, 85%, 34%);
                --bg-circle: hsl(0, 0%, 96%);
            }
        </style>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="invoices" style="display: none;">
        <div class="container">
            <div class="card">
                <div class="card-body">
                    <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal"
                        data-bs-target="#modalFacturas">
                        <i class="fas fa-list"></i> Ver Facturas de <?= $title ?? "" ?>
                    </button>
                    <br>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <h3 class="card-title">Numero de DTE: <?= $factura[0]["no_autorizacion"] ?? "" ?></h3>
                        <h3 class="card-title"><?= $titlefacturacion ?? "" ?></h3>
                        <!-- <div>
                                        <button class="btn btn-outline-secondary me-2">SAVE</button>
                                        <button class="btn btn-danger">PRINT</button>
                                    </div> -->
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">NUMERO DE AUTORIZACION</h6>
                                    <span class="badge text-bg-secondary">Serie:</span> <?= $factura[0]["serie"] ?? "" ?><br>
                                    <span class="badge text-bg-secondary">Emision:</span>
                                    <?= $factura[0]["fechahora_emision"] ?? "" ?><br>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><?= $factura[0]["codigo_autorizacion"] ?? "" ?><br>
                                        <span class="badge text-bg-secondary">Num. de DTE:</span>
                                        <?= $factura[0]["no_autorizacion"] ?? "" ?><br>
                                        <span class="badge text-bg-secondary">Certificacion:</span>
                                        <?= $factura[0]["fechahora_certificacion"] ?? "" ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">EMISOR</h6>
                            <p><span class="badge text-bg-primary">Nombre:</span> <?= $factura[0]["nombre_emisor"] ?? "" ?><br>
                                <span class="badge text-bg-primary">Nombre Comercial:</span>
                                <?= $factura[0]["nombre_comercial"] ?? "" ?><br>
                                <span class="badge text-bg-primary">Email: </span> <?= $factura[0]["correemisor"] ?? "" ?><br>
                                <span class="badge text-bg-primary">Direccion:</span>
                                <?= $factura[0]["direcemisor"] ?? "" ?><br>
                                <span class="badge text-bg-primary">NIT:</span> <?= $factura[0]["nit_emisor"] ?? "" ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">RECEPTOR</h6>
                            <p><span class="badge text-bg-success">Nombre:</span>
                                <?= $factura[0]["nombre_receptor"] ?? "" ?><br>
                                <span class="badge text-bg-success">Email: </span>
                                <?= $factura[0]["correoreceptor"] ?? "" ?><br>
                                <span class="badge text-bg-success">Direccion:</span> <?= $factura[0]["direreceptor"] ?? "" ?><br>
                                <span class="badge text-bg-success">NIT:</span> <?= $factura[0]["id_receptor"] ?? "" ?>
                            </p>
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr style='font-size: 0.8em;'>
                                <th>#</th>
                                <th>B/S</th>
                                <th>CANT.</th>
                                <th>DESCRIPCION</th>
                                <th>P UNIT. (CON IVA)</th>
                                <th>DESCUENTOS</th>
                                <th>OTROS DESCUENTOS</th>
                                <th>TOTAL</th>
                                <th>IMPUESTOS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (isset($factura[0])) {
                                $sumastotales = 0;
                                $validoscantidad = decimalesvalidos(array_column($factura, "cantidad"));
                                $validosprecioun = decimalesvalidos(array_column($factura, "precio_unitario"));
                                $validosdescuent = decimalesvalidos(array_column($factura, "descuento"));
                                $validosotrdescuent = decimalesvalidos(array_column($factura, "otros_descuentos"));
                                $validostotal = decimalesvalidos(array_column($factura, "total"));
                                $validosimpuestos = decimalesvalidos(array_column($factura, "monto_impuesto"));

                                end($factura);
                                $lastKey = key($factura);
                                reset($factura);

                                $printheader = true;
                                $printfooter = false;
                                foreach ($factura as $key => $item) {
                                    $iditem = $item["id"];

                                    if ($printheader) {
                                        echo "<tr style='font-size: 0.8em;'>";
                                        echo "<td>" . $item['numerolinea'] . "</td>";
                                        echo "<td>" . $item['tipo'] . "</td>";
                                        echo "<td>" . number_format($item['cantidad'], $validoscantidad)  . "</td>";
                                        echo "<td>" . $item['descripcion'] . "</td>";
                                        echo "<td>" . number_format($item['precio_unitario'], $validosprecioun) . "</td>";
                                        echo "<td>" . number_format($item['descuento'], $validosdescuent) . "</td>";
                                        echo "<td>" . number_format($item['otros_descuentos'], $validosotrdescuent) . "</td>";
                                        echo "<td>" . number_format($item['total'], $validostotal) . "</td>";
                                        echo "<td>";
                                        $sumastotales += $item['total'];
                                        $printheader = false;
                                    }
                                    echo '<div class="row">
                                                            <div class="col-5 text-end" style="font-size: 0.8em;">' . $item['nameimpuesto'] . '</div>
                                                            <div class="col-7 text-end" style="font-size: 0.8em;">' . number_format($item['monto_impuesto'], $validosimpuestos) . '</div>
                                                        </div>';

                                    if ($key === $lastKey) {
                                        $printfooter = true;
                                    } else {
                                        if ($factura[$key + 1]['id'] != $iditem) {
                                            $printheader = true;
                                            $printfooter = true;
                                        }
                                    }

                                    if ($printfooter) {
                                        echo "</td>";
                                        echo "</tr>";
                                        $printfooter = false;
                                    }
                                }
                            }
                            ?>
                        </tbody>

                    </table>
                    <?php
                    if (isset($factura[0])) {
                    ?>
                        <div class="row justify-content-end">
                            <div class="col-md-4">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="text-end"><span class="badge text-bg-primary">Total</span></td>
                                        <td class="text-end"><?= number_format($sumastotales, 6)  ?? "" ?></td>
                                    </tr>
                                    <?php
                                    foreach ($tiposimpuestos as $impuesto) {
                                        $idimpuesto = $impuesto["id"];
                                        $descripcion = $impuesto["descripcion"];
                                        $sumimpuesto = number_format(sumarray($factura, $idimpuesto, "idtipim", "monto_impuesto"), 6);
                                        if ($sumimpuesto > 0) {
                                            echo '<tr><td class="text-end"><span class="badge text-bg-warning">' . $descripcion . '</span></td><td class="text-end">' . $sumimpuesto . '</td>';
                                        }
                                    }
                                    ?>
                                </table>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                    <div class="card">
                        <div class="card-body">
                            <?php
                            if (isset($factura[0])) {
                                foreach ($frases as $frase) {
                                    $frasename = $frase["nombre"];
                                    echo '<p class="card-text">* ' . $frasename . '</p>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <br>
                    <div class="card">
                        <div class="card-body">
                            <h5>CERTIFICADOR</h5>
                            <?php
                            if (isset($factura[0])) {
                                echo '<p class="card-text">' . $factura[0]["nombre_certificador"] . ' NIT: ' . $factura[0]["nit_certificador"] . '</p>';
                            }
                            ?>
                        </div>
                    </div>
                    <br>
                    <?php
                    if (!isset($factura[0]["orign"]) || $factura[0]["orign"] != 1) {
                    ?>
                        <!-- <div class="card mb-3" style="max-width: 18rem;"> -->
                        <div>ESTADO DE LA FACTURA</div>
                        <br>

                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-2 col-md-3 col-sm-4">
                                    <span class="badge text-bg-dark">Anulada</span>
                                </div>
                                <div class="col-lg-2 col-md-3 col-sm-4">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <input type="checkbox" name="check-toggle" id="statusfact" hidden=""
                                            onchange="changestatus(this,<?= $factura[0]['id_fact']  ?? 0 ?>)" <?= $checked ?>>
                                        <label for="statusfact" class="toggle">
                                            <div class="toggle__circle"></div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-3 col-sm-4">
                                    <span class="badge text-bg-success text-end" style="text-align: right;">Activa</span>
                                </div>
                            </div>
                        </div>

                    <?php
                    }
                    ?>
                    <!-- </div> -->

                </div>
            </div>
        </div>
        <div class="modal fade" id="modalFacturas" tabindex="-1" aria-labelledby="modalPartidasLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalPartidasLabel">Facturas de <?= $title  ?? "" ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="list-example" class="h-100 flex-column align-items-stretch pe-1 border-end">
                            <table class="table nowrap" id="tb_facturadetalle" style="width: 100% !important;">
                                <thead>
                                    <tr style="font-size: 0.80rem;">
                                        <th>Numero</th>
                                        <th>Serie</th>
                                        <th>Fecha de Emision</th>
                                        <th><?= $titlecolumn  ?? "" ?></th>
                                        <th>Accion</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var table_partidas_aux;
            $(document).ready(function() {
                table_partidas_aux = $('#tb_facturadetalle').on('search.dt').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../../src/server_side/lista_facturas.php",
                    columns: [{
                            data: [1]
                        },
                        {
                            data: [2]
                        },
                        {
                            data: [3]
                        },
                        {
                            data: [4]
                        },
                        {
                            data: [0],
                            render: function(data, type, row) {
                                // console.log(data)
                                return `<button data-bs-dismiss="modal" aria-label="Close" type="button" class="btn btn-outline-success btn-sm" onclick="printdiv('invoices', '#cuadro', 'views001', '${data}');" >
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>`;
                            }
                        },
                    ],
                    "fnServerParams": function(aoData) {
                        aoData.push({
                            "name": "whereextra",
                            "value": "estado IN (1,2) AND tipo =" + '<?= $tipofactura ?>'
                        });
                        aoData.push({
                            "name": "tip",
                            "value": '<?= $tipofactura ?>'
                        });
                    },
                    //"bDestroy": true,
                    "language": {
                        "lengthMenu": "Mostrar _MENU_ registros",
                        "zeroRecords": "No se encontraron registros",
                        "info": " ",
                        "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "infoFiltered": "(filtrado de un total de: _MAX_ registros)",
                        "sSearch": "Buscar: ",
                        "oPaginate": {
                            "sFirst": "Primero",
                            "sLast": "Ultimo",
                            "sNext": "Siguiente",
                            "sPrevious": "Anterior"
                        },
                        "sProcessing": "Procesando..."
                    }
                });
            });

            function changestatus(elem, id) {
                // console.log(id)
                if (id != 0) {
                    let estado = (elem.checked) ? 1 : 2;
                    // console.log(estado)
                    obtiene([], [], [], 'update_status_fact', id, [estado, id])
                }
            }
        </script>
    <?php
        break;
    case 'invoicesventas':
        $xtra = $_POST["xtra"];
        $idfactura = $xtra;
        $tipofactura = 1;
        $statusf = 2;
        // $idfactura = 16;
        $strquery = "SELECT fac.id id_fact, fechahora_emision,codigo_autorizacion,td.codigo,serie,no_autorizacion,em.nit nit_emisor,em.nombre nombre_emisor,
                        cod_establecimiento,nombre_comercial,em.direccion direcemisor,em.correo correoemisor,rec.id_receptor,rec.nombre nombre_receptor,
                        rec.direccion direreceptor,rec.correo correoreceptor,cert.nit nit_certificador,cert.nombre nombre_certificador, id_moneda,
                        fit.*,fitm.monto_impuesto,imtip.descripcion nameimpuesto,imtip.id idtipim,fac.fechahora_certificacion,fac.tipo tipo_factura,fac.estado statusf, fac.origen_factura orign
                        FROM cv_facturas fac 
                        INNER JOIN cv_factura_items fit ON fac.id=fit.id_factura
                        INNER JOIN cv_facturaitems_impuestos fitm ON fitm.id_factura_items=fit.id
                        INNER JOIN cv_impuestosunidadgravable ugr ON ugr.id=fitm.id_impuestos_unidadgravable
                        INNER JOIN cv_impuestos_tipo imtip ON imtip.id=ugr.id_cvimpuestostipo
                        INNER JOIN cv_tiposdte td ON fac.id_tipo=td.id
                        INNER JOIN cv_receptor rec ON fac.id_receptor=rec.id
                        INNER JOIN cv_emisor em ON fac.id_emisor=em.id
                        INNER JOIN cv_certificador cert ON fac.id_certificador=cert.id
                        WHERE fac.estado IN (1,2) AND fac.id=?
                        ORDER BY fac.fechahora_emision,fac.id;";

        $query2 = "SELECT esc.* FROM cv_factura_frases fra
                        INNER JOIN cv_escenarios esc ON esc.id=fra.id_escenario
                        WHERE fra.id_factura=?;";

        try {
            $database->openConnection();
            $factura = $database->getAllResults($strquery, [$idfactura]);
            if (empty($factura)) {
                $showmensaje = true;
                throw new Exception($xtra . " No se encontró la factura --- :  " . $tipofactura);
            }
            $frases = $database->getAllResults($query2, [$idfactura]);
            if (empty($frases)) {
                // $showmensaje = true;
                // throw new Exception("No se encontró la factura");
            }
            $tiposimpuestos = $database->selectAll('cv_impuestos_tipo');
            $tipofactura = $factura[0]["tipo_factura"];
            $titlefacturacion =  "Factura de Venta";
            $statusf = $factura[0]["statusf"];

            $mensaje = "";
            $status = 1;
        } catch (Exception $e) {
            $mensaje = " " . $e->getMessage();
            $status = 0;
        } finally {
            $database->closeConnection();
        }

        if (!$status) {
            echo "error: $mensaje";
        }
        $title = ($tipofactura == 1) ? "Ventas" : (($tipofactura == 2) ? "Compras" : "");
        $titlecolumn = ($tipofactura == 1) ? "Receptor" : (($tipofactura == 2) ? "Emisor" : "X");
        $checked = ($statusf == 1) ? "checked" : "";
    ?>
        <style>
            #tb_facturadetalle tbody tr td {
                font-size: 0.70rem;
                /* Ajusta el tamaño de la fuente según sea necesario */
            }

            .toggle {
                --bg-toggle: hsl(0, 0%, 75%);
                /* Gris claro */
                --bg-circle: hsl(0, 0%, 50%);
                /* Gris medio */
                width: 120px;
                height: 60px;
                background-color: var(--bg-toggle);
                box-shadow: 0 .3rem 5rem 0 rgba(125, 125, 125, 0.25);
                border-radius: 4rem;
                display: flex;
                align-items: center;
                padding: 0 .3rem;
                transition: background-color 400ms;
            }

            .toggle__circle {
                width: 50px;
                height: 50px;
                cursor: pointer;
                background-color: var(--bg-circle);
                border-radius: 50%;
                position: relative;
                transition: margin 400ms ease-in-out, background-color 1000ms;
            }

            .toggle__circle::after,
            .toggle__circle::before {
                content: '';
                position: absolute;
                background-color: var(--bg-toggle);
                bottom: 118%;
                transform-origin: bottom left;
            }

            .toggle__circle::before {
                width: 15px;
                height: 25px;
                left: 32%;
                border-radius: 0% 100% 0% 100% / 0% 27% 73% 100%;
                transform: translateX(-70%) rotate(-2deg);
            }

            .toggle__circle::after {
                width: 25px;
                height: 30px;
                left: 48%;
                border-radius: 100% 0% 100% 0% / 100% 0% 100% 0%;
                transform: rotate(-20deg);
            }

            #statusfact:checked+.toggle>.toggle__circle {
                margin-left: calc(120px - (.3rem * 2) - 50px);
            }

            #statusfact:checked+.toggle {
                --bg-toggle: hsl(96, 85%, 34%);
                --bg-circle: hsl(0, 0%, 96%);
            }
        </style>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="invoicesventas" style="display: none;">
        <div class="container">
            <div class="card">
                <div class="card-body">
                    <button type="button" class="btn btn-outline-primary me-2" data-bs-toggle="modal"
                        data-bs-target="#modalFacturas">
                        <i class="fas fa-list"></i> Ver Facturas de <?= $title ?? "" ?>
                    </button>
                    <br>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <h3 class="card-title">Numero de DTE: <?= $factura[0]["no_autorizacion"] ?? "" ?></h3>
                        <h3 class="card-title"><?= $titlefacturacion ?? "" ?></h3>
                        <!-- <div>
                                        <button class="btn btn-outline-secondary me-2">SAVE</button>
                                        <button class="btn btn-danger">PRINT</button>
                                    </div> -->
                    </div>
                    <div class="card">
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6 class="text-muted">NUMERO DE AUTORIZACION</h6>
                                    <span class="badge text-bg-secondary">Serie:</span> <?= $factura[0]["serie"] ?? "" ?><br>
                                    <span class="badge text-bg-secondary">Emision:</span>
                                    <?= $factura[0]["fechahora_emision"] ?? "" ?><br>
                                    </p>
                                </div>
                                <div class="col-md-6">
                                    <p><?= $factura[0]["codigo_autorizacion"] ?? "" ?><br>
                                        <span class="badge text-bg-secondary">Num. de DTE:</span>
                                        <?= $factura[0]["no_autorizacion"] ?? "" ?><br>
                                        <span class="badge text-bg-secondary">Certificacion:</span>
                                        <?= $factura[0]["fechahora_certificacion"] ?? "" ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <br>
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted">EMISOR</h6>
                            <p><span class="badge text-bg-primary">Nombre:</span> <?= $factura[0]["nombre_emisor"] ?? "" ?><br>
                                <span class="badge text-bg-primary">Nombre Comercial:</span>
                                <?= $factura[0]["nombre_comercial"] ?? "" ?><br>
                                <span class="badge text-bg-primary">Email: </span> <?= $factura[0]["correemisor"] ?? "" ?><br>
                                <span class="badge text-bg-primary">Direccion:</span>
                                <?= $factura[0]["direcemisor"] ?? "" ?><br>
                                <span class="badge text-bg-primary">NIT:</span> <?= $factura[0]["nit_emisor"] ?? "" ?>
                            </p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-muted">RECEPTOR</h6>
                            <p><span class="badge text-bg-success">Nombre:</span>
                                <?= $factura[0]["nombre_receptor"] ?? "" ?><br>
                                <span class="badge text-bg-success">Email: </span>
                                <?= $factura[0]["correoreceptor"] ?? "" ?><br>
                                <span class="badge text-bg-success">Direccion:</span> <?= $factura[0]["direreceptor"] ?? "" ?><br>
                                <span class="badge text-bg-success">NIT:</span> <?= $factura[0]["id_receptor"] ?? "" ?>
                            </p>
                        </div>
                    </div>

                    <table class="table">
                        <thead>
                            <tr style='font-size: 0.8em;'>
                                <th>#</th>
                                <th>B/S</th>
                                <th>CANT.</th>
                                <th>DESCRIPCION</th>
                                <th>P UNIT. (CON IVA)</th>
                                <th>DESCUENTOS</th>
                                <th>OTROS DESCUENTOS</th>
                                <th>TOTAL</th>
                                <th>IMPUESTOS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if (isset($factura[0])) {
                                $sumastotales = 0;
                                $validoscantidad = decimalesvalidos(array_column($factura, "cantidad"));
                                $validosprecioun = decimalesvalidos(array_column($factura, "precio_unitario"));
                                $validosdescuent = decimalesvalidos(array_column($factura, "descuento"));
                                $validosotrdescuent = decimalesvalidos(array_column($factura, "otros_descuentos"));
                                $validostotal = decimalesvalidos(array_column($factura, "total"));
                                $validosimpuestos = decimalesvalidos(array_column($factura, "monto_impuesto"));

                                end($factura);
                                $lastKey = key($factura);
                                reset($factura);

                                $printheader = true;
                                $printfooter = false;
                                foreach ($factura as $key => $item) {
                                    $iditem = $item["id"];

                                    if ($printheader) {
                                        echo "<tr style='font-size: 0.8em;'>";
                                        echo "<td>" . $item['numerolinea'] . "</td>";
                                        echo "<td>" . $item['tipo'] . "</td>";
                                        echo "<td>" . number_format($item['cantidad'], $validoscantidad)  . "</td>";
                                        echo "<td>" . $item['descripcion'] . "</td>";
                                        echo "<td>" . number_format($item['precio_unitario'], $validosprecioun) . "</td>";
                                        echo "<td>" . number_format($item['descuento'], $validosdescuent) . "</td>";
                                        echo "<td>" . number_format($item['otros_descuentos'], $validosotrdescuent) . "</td>";
                                        echo "<td>" . number_format($item['total'], $validostotal) . "</td>";
                                        echo "<td>";
                                        $sumastotales += $item['total'];
                                        $printheader = false;
                                    }
                                    echo '<div class="row">
                                                            <div class="col-5 text-end" style="font-size: 0.8em;">' . $item['nameimpuesto'] . '</div>
                                                            <div class="col-7 text-end" style="font-size: 0.8em;">' . number_format($item['monto_impuesto'], $validosimpuestos) . '</div>
                                                        </div>';

                                    if ($key === $lastKey) {
                                        $printfooter = true;
                                    } else {
                                        if ($factura[$key + 1]['id'] != $iditem) {
                                            $printheader = true;
                                            $printfooter = true;
                                        }
                                    }

                                    if ($printfooter) {
                                        echo "</td>";
                                        echo "</tr>";
                                        $printfooter = false;
                                    }
                                }
                            }
                            ?>
                        </tbody>

                    </table>
                    <?php
                    if (isset($factura[0])) {
                    ?>
                        <div class="row justify-content-end">
                            <div class="col-md-4">
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="text-end"><span class="badge text-bg-primary">Total</span></td>
                                        <td class="text-end"><?= number_format($sumastotales, 6)  ?? "" ?></td>
                                    </tr>
                                    <?php
                                    foreach ($tiposimpuestos as $impuesto) {
                                        $idimpuesto = $impuesto["id"];
                                        $descripcion = $impuesto["descripcion"];
                                        $sumimpuesto = number_format(sumarray($factura, $idimpuesto, "idtipim", "monto_impuesto"), 6);
                                        if ($sumimpuesto > 0) {
                                            echo '<tr><td class="text-end"><span class="badge text-bg-warning">' . $descripcion . '</span></td><td class="text-end">' . $sumimpuesto . '</td>';
                                        }
                                    }
                                    ?>
                                </table>
                            </div>
                        </div>
                    <?php
                    }
                    ?>
                    <div class="card">
                        <div class="card-body">
                            <?php
                            if (isset($factura[0])) {
                                foreach ($frases as $frase) {
                                    $frasename = $frase["nombre"];
                                    echo '<p class="card-text">* ' . $frasename . '</p>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <br>
                    <div class="card">
                        <div class="card-body">
                            <h5>CERTIFICADOR</h5>
                            <?php
                            if (isset($factura[0])) {
                                echo '<p class="card-text">' . $factura[0]["nombre_certificador"] . ' NIT: ' . $factura[0]["nit_certificador"] . '</p>';
                            }
                            ?>
                        </div>
                    </div>
                    <br>
                    <?php
                    if (!isset($factura[0]["orign"]) || $factura[0]["orign"] != 1) {
                    ?>
                        <!-- <div class="card mb-3" style="max-width: 18rem;"> -->
                        <div>ESTADO DE LA FACTURA</div>
                        <br>

                        <div class="card-body">
                            <div class="row">
                                <div class="col-lg-2 col-md-3 col-sm-4">
                                    <span class="badge text-bg-dark">Anulada</span>
                                </div>
                                <div class="col-lg-2 col-md-3 col-sm-4">
                                    <div class="d-flex justify-content-center align-items-center">
                                        <input type="checkbox" name="check-toggle" id="statusfact" hidden=""
                                            onchange="changestatus(this,<?= $factura[0]['id_fact']  ?? 0 ?>)" <?= $checked ?>>
                                        <label for="statusfact" class="toggle">
                                            <div class="toggle__circle"></div>
                                        </label>
                                    </div>
                                </div>
                                <div class="col-lg-2 col-md-3 col-sm-4">
                                    <span class="badge text-bg-success text-end" style="text-align: right;">Activa</span>
                                </div>
                            </div>
                        </div>

                    <?php
                    }
                    ?>
                    <!-- </div> -->

                </div>
            </div>
        </div>
        <div class="modal fade" id="modalFacturas" tabindex="-1" aria-labelledby="modalPartidasLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalPartidasLabel">Facturas de <?= $title  ?? "" ?></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div id="list-example" class="h-100 flex-column align-items-stretch pe-1 border-end">
                            <table class="table nowrap" id="tb_facturadetalle" style="width: 100% !important;">
                                <thead>
                                    <tr style="font-size: 0.80rem;">
                                        <th>Numero</th>
                                        <th>Serie</th>
                                        <th>Fecha de Emision</th>
                                        <th><?= $titlecolumn  ?? "" ?></th>
                                        <th>Accion</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script>
            var table_partidas_aux;
            $(document).ready(function() {
                table_partidas_aux = $('#tb_facturadetalle').on('search.dt').DataTable({
                    "processing": true,
                    "serverSide": true,
                    "sAjaxSource": "../../src/server_side/lista_facturas.php",
                    columns: [{
                            data: [1]
                        },
                        {
                            data: [2]
                        },
                        {
                            data: [3]
                        },
                        {
                            data: [4]
                        },
                        {
                            data: [0],
                            render: function(data, type, row) {
                                // console.log(data)
                                return `<button data-bs-dismiss="modal" aria-label="Close" type="button" class="btn btn-outline-success btn-sm" onclick="printdiv('invoices', '#cuadro', 'views001', '${data}');" >
                                                        <i class="fa-regular fa-eye"></i>
                                                    </button>`;
                            }
                        },
                    ],
                    "fnServerParams": function(aoData) {
                        aoData.push({
                            "name": "whereextra",
                            "value": "estado IN (1,2) AND tipo =" + '<?= $tipofactura ?>'
                        });
                        aoData.push({
                            "name": "tip",
                            "value": '<?= $tipofactura ?>'
                        });
                    },
                    //"bDestroy": true,
                    "language": {
                        "lengthMenu": "Mostrar _MENU_ registros",
                        "zeroRecords": "No se encontraron registros",
                        "info": " ",
                        "infoEmpty": "Mostrando registros del 0 al 0 de un total de 0 registros",
                        "infoFiltered": "(filtrado de un total de: _MAX_ registros)",
                        "sSearch": "Buscar: ",
                        "oPaginate": {
                            "sFirst": "Primero",
                            "sLast": "Ultimo",
                            "sNext": "Siguiente",
                            "sPrevious": "Anterior"
                        },
                        "sProcessing": "Procesando..."
                    }
                });
            });

            function changestatus(elem, id) {
                // console.log(id)
                if (id != 0) {
                    let estado = (elem.checked) ? 1 : 2;
                    // console.log(estado)
                    obtiene([], [], [], 'update_status_fact', id, [estado, id])
                }
            }
        </script>
    <?php
        break;
    case 'reportes':
        $xtra = $_POST["xtra"];
        //1 ventas, 2 compras

        $tipofactura = 2;
        $title = " DE COMPRAS";
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="reportes" style="display: none;">
        <div class="text" style="text-align:center">FACTURAS <?= $title ?? "" ?></div>
        <div class="card">
            <!-- <div class="card-header">FILTROS</div> -->
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row m-2">
                        <div class="col-sm-12 col-md-6 col-lg-6">
                            <div class="card" style="height: 100%;">
                                <div class="card-header">Fecha de emisión</div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6 col-lg-6">
                            <div class="card" style="height: 100%;">
                                <div class="card-header">Estado</div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <span class="input-group-addon col-8">Estado</span>
                                            <select class="form-select  col-md-12" aria-label="Default select example"
                                                id="estado">
                                                <option value="0">Todas</option>
                                                <option value="1" selected>Activos</option>
                                                <option value="2">Anulados</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- <div class="col-sm-12 col-md-4 col-lg-4">
                                    <div class="card" style="height: 100%;">
                                        <div class="card-header">Tipo</div>
                                        <div class="card-body">
                                            <div class="row" id="filfechas">
                                                <div class="col-sm-12">
                                                    <span class="input-group-addon col-8">Tipo</span>
                                                    <select class="form-select  col-md-12" aria-label="Default select example" id="tipo">
                                                        <option value="0">Todas</option>
                                                        <option value="1" selected>Ventas</option>
                                                        <option value="2">Compras</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div> -->
                    </div>
                </div>
                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center">
                        <!-- <button type="button" class="btn btn-outline-primary" title="Reporte de ingresos" onclick="reportes([[`finicio`,`ffin`],[],[],[]],`show`,`invoices`,0,'DFECPRO','NMONTO',2,'Montos',0)">
                                    <i class="fa-solid fa-eye"></i> Ver
                                </button> -->
                        <button type="button" class="btn btn-outline-danger" title="Reporte de ingresos en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`estado`],[],[<?= $tipofactura ?>]],`pdf`,`invoices`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Reporte de ingresos en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`estado`],[],[<?= $tipofactura ?>]],`xlsx`,`invoices`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
                <div id="divshow" class="container contenedort" style="display: none;">
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-sm small-font">
                            <thead>
                                <tr>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'reportesventas':
        $xtra = $_POST["xtra"];
        //1 ventas, 2 compras

        $tipofactura = 1;
        $title = " DE VENTAS";
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="reportes" style="display: none;">
        <div class="text" style="text-align:center">FACTURAS <?= $title ?? "" ?></div>
        <div class="card">
            <!-- <div class="card-header">FILTROS</div> -->
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row m-2">
                        <div class="col-sm-12 col-md-6 col-lg-6">
                            <div class="card" style="height: 100%;">
                                <div class="card-header">Fecha de emisión</div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="finicio" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="ffin" min="1950-01-01"
                                                value="<?php echo date("Y-m-d"); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6 col-lg-6">
                            <div class="card" style="height: 100%;">
                                <div class="card-header">Estado</div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <span class="input-group-addon col-8">Estado</span>
                                            <select class="form-select  col-md-12" aria-label="Default select example"
                                                id="estado">
                                                <option value="0">Todas</option>
                                                <option value="1" selected>Activos</option>
                                                <option value="2">Anulados</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center">
                        <!-- <button type="button" class="btn btn-outline-primary" title="Reporte de ingresos" onclick="reportes([[`finicio`,`ffin`],[],[],[]],`show`,`invoices`,0,'DFECPRO','NMONTO',2,'Montos',0)">
                                    <i class="fa-solid fa-eye"></i> Ver
                                </button> -->
                        <button type="button" class="btn btn-outline-danger" title="Reporte de ingresos en pdf"
                            onclick="reportes([[`finicio`,`ffin`],[`estado`],[],[<?= $tipofactura ?>]],`pdf`,`invoices`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Reporte de ingresos en Excel"
                            onclick="reportes([[`finicio`,`ffin`],[`estado`],[],[<?= $tipofactura ?>]],`xlsx`,`invoices`,1)">
                            <i class="fa-solid fa-file-excel"></i>Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
                <div id="divshow" class="container contenedort" style="display: none;">
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-sm small-font">
                            <thead>
                                <tr>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'converttojson':
        $xmlFile = $_FILES['xmlFile']['tmp_name'];
        if (file_exists($xmlFile)) {
            $xmlString = file_get_contents($xmlFile);

            // Convertir el contenido XML en un objeto SimpleXMLElement
            $xmlObject = simplexml_load_string($xmlString);

            // Convertir el objeto SimpleXMLElement en un array
            $array = json_decode(json_encode($xmlObject), true);

            // Convertir el array a JSON
            $json = json_encode($array, JSON_PRETTY_PRINT);
            echo $json;
        } else {
            echo json_encode(['error' => 'No se pudo cargar el archivo XML.']);
        }
        break;
    case 'gen_fac': ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="reportes" style="display: none;">


        <div class="card">
            <!-- <div class="card-header">FILTROS</div> -->

            <div class="container">

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <h3 class="card-title">GENERAR FACTURA</h3>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <p>
                                    <span class="badge text-bg-secondary">NUMERO DE AUTORIZACION:</span>
                                    <input type="text" id="noautorizacion" class="form-control" disabled>
                                    <br>
                                    <span class="badge text-bg-secondary">Serie:</span>
                                    <input type="text" id="noserie" class="form-control" disabled>
                                    <br>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p>
                                    <span class="badge text-bg-secondary">Emision:</span>
                                    <input type="text" id="fechemision" class="form-control" disabled>
                                    <br>
                                    <span class="badge text-bg-secondary">Num. de DTE:</span>
                                    <input type="text" id="nodte" class="form-control" disabled>
                                    <br>
                                </p>
                            </div>
                            <div class="col-md-12">
                                <p>
                                    <?php
                                    $sql = "SELECT id, nombre FROM productos";
                                    $sql = mysqli_query($conexion, "SELECT * FROM cv_tiposdte");
                                    echo '<span class="badge text-bg-secondary">Tipo de DTE:</span>';
                                    if ($sql->num_rows > 0) {
                                        echo '<select class="form-select" id="tipodte">';

                                        // Recorrer los resultados y crear las opciones del select
                                        while ($row = $sql->fetch_assoc()) {
                                            echo '<option value="' . $row['id'] . '">' . $row['nombre'] . ' - ' . $row['codigo'] . '</option>';
                                        }

                                        echo '</select>';
                                    }
                                    ?>
                                    <br>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <br>
                <div class="row mb-4">
                    <div class="col-md-6">
                        <h6 class="text-muted">EMISOR</h6>
                        <p>
                            <span class="badge text-bg-primary">NIT:</span>
                            <input type="text" id="nitcliente" class="form-control" value="8111731" disabled><br>
                            <span class="badge text-bg-primary">Nombre:</span>
                            <input type="text" id="nombrecliente" class="form-control" value="MARIO ROBERTO, MONTENEGRO" disabled><br>
                            <span class="badge text-bg-primary">Email: </span>
                            <input type="email" id="emailcliente" class="form-control" value="sotecpro@sotecprotech.com" disabled><br>
                            <span class="badge text-bg-primary">Direccion:</span>
                            <input type="text" id="direccioncliente" class="form-control" value="16 Avenida 1-09 Zona 1, Cobán, Alta Verapaz" disabled><br>

                        </p>
                    </div>
                    <div class="col-md-6">
                        <h6 class="text-muted">RECEPTOR</h6>
                        <p>
                        <div>
                            <span class="badge text-bg-success">NIT:</span>
                            <div class="input-group">
                                <button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#buscargrupo" id="btnbuscarnit"><i class="fas fa-search"></i></button>
                                <input type="text" class="form-control" id="nitcliente2" minlength="2" maxlength="8" aria-label="Example text with button addon">
                            </div>
                        </div>
                        <br>
                        <input type="hidden" id="clienteid2">
                        <span class="badge text-bg-success">Nombre:</span>
                        <input type="text" id="nombrecliente2" class="form-control"><br>
                        <span class="badge text-bg-success">Email: </span>
                        <input type="text" id="emailcliente2" class="form-control"><br>
                        <span class="badge text-bg-success">Direccion:</span>
                        <input type="text" id="direccioncliente2" class="form-control"><br>
                        </p>
                    </div>
                    <div id="modalreceptor"></div>
                </div>
                <div class="card">
                    <div class="card-body">
                        <table class="table" style="width: 100%; table-layout: fixed;" id="miTabla">
                            <thead>
                                <tr style="font-size: 0.8em;">
                                    <th scope="col" style="width: 10%;">B/S</th>
                                    <th scope="col" style="width: 10%;">CANT.</th>
                                    <th scope="col" style="width: 20%;">DESCRIPCION</th>
                                    <th scope="col" style="width: 15%;">P UNIT Q. (CON IVA)</th>
                                    <th scope="col" style="width: 10%;">DESCUENTOS (Q.)</th>
                                    <th scope="col" style="width: 10%;">OTROS DESCUENTOS (Q.)</th>
                                    <th scope="col" style="width: 10%;">TOTAL</th>
                                    <th scope="col" style="width: 10%;">IMPUESTOS</th>
                                    <th scope="col" style="width: 10%;">ACCIONES</th>
                                </tr>
                            </thead>
                            <tbody> <!-- 
                                <tr>
                                    <td>
                                        <select class="form-select" id="exampleSelect">
                                            <option value="1">Bien</option>
                                            <option value="2">Servicio</option>
                                        </select>
                                    </td>
                                    <td><input type="number" id="cant-0" class="form-control" min="0"></td>
                                    <td><input type="text" id="desc-0" class="form-control"></td>
                                    <td><input type="number" id="precio-0" class="form-control" min="0"></td>
                                    <td><input type="number" id="desc1-0" class="form-control" min="0"></td>
                                    <td><input type="number" id="desc2-0" class="form-control" min="0"></td>
                                    <td><input type="number" id="total-0" class="form-control" min="0"></td>
                                    <td><input type="number" id="impuestos-0" class="form-control" min="0"></td>
                                    <td></td>
                                </tr>-->
                            </tbody>
                        </table>
                        <div class="row">
                            <div class="col-8">
                                <input type="text" class="alert alert-primary w-100" value="TOTAL" disabled>
                            </div>
                            <div class="col-3">
                                <input type="number" class="alert alert-primary" id="sumtotal" min="0" value="0.00" disabled>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary" onclick="agregarFilaVacia();" id="AddFila">
                            <i class="fa-solid fa-plus"></i> Agregar nuevo producto
                        </button>
                    </div>
                </div>
                <br>
                <button type="submit" class="btn btn-outline-success" onclick="validarCampos();" id="EmitFel">
                    <i class="fa-solid fa-receipt"></i> Emitir factura
                </button>
                <button type="submit" class="btn btn-outline-success" onclick="generarYDescargarXML();" id="DesXML" style="display: none;">
                    <i class="fa-solid fa-file-export"></i> Descargar factura XML
                </button>
                <button type="submit" class="btn btn-outline-success" id="ImpFel" onclick="imprimirfel(this.value);" style="display: none;">
                    <i class="fa-solid fa-file-export"></i> Imprimir Factura
                </button>
                <br>
                <br>
                <div class="card">
                    <div class="card-body">
                        <h5>CERTIFICADOR: INFILE</h5>
                    </div>
                </div>
                <br>

                <br>
            </div>
        </div>
    <?php

        break;

    case 'anular_fel':
    ?>
        <div class="card">
            <div class="container">
                <div class="d-flex justify-content-between align-items-center mt-4">
                    <h3 class="card-title">ANULAR FACTURA</h3>
                </div>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <h6 class="text-muted">DATOS FACTURA</h6>
                            <div>
                                <span class="badge text-bg-success">BUSCAR FACTURA:</span>
                                <div class="input-group">
                                    <button class="btn btn-outline-success" type="button" data-bs-toggle="modal" data-bs-target="#buscarfacturasdet" id="btnbuscarfact"><i class="fas fa-search"></i></button>
                                    <input type="text" class="form-control" id="nombrcliente" minlength="2" maxlength="8" aria-label="Example text with button addon" disabled>
                                </div><br>
                            </div>
                            <div id="modalreceptor2"></div>

                            <div class="col-md-6">
                                <p>
                                    <span class="badge text-bg-secondary">NUMERO DE AUTORIZACION:</span>
                                    <input type="text" id="noautorizacion" class="form-control" disabled>
                                    <br>
                                    <span class="badge text-bg-secondary">Serie:</span>
                                    <input type="text" id="noserie" class="form-control" disabled>
                                    <br>
                                </p>
                            </div>
                            <div class="col-md-6">
                                <p>
                                    <span class="badge text-bg-secondary">Emision:</span>
                                    <input type="text" id="fechemision" class="form-control" disabled>
                                    <br>
                                    <span class="badge text-bg-secondary">Num. de DTE:</span>
                                    <input type="text" id="nodte" class="form-control" disabled>
                                    <br>
                                </p>
                            </div>
                            <div>
                                <span class="badge text-bg-secondary">Total:</span>
                                <input type="text" id="total" class="form-control" disabled><br>
                            </div>
                        </div>
                    </div>
                </div><br>
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <h6 class="text-muted">MOTIVO DE ANULACION</h6>
                            <div>
                                <div class="form-floating">
                                    <textarea class="form-control" placeholder="Describa el motivo para la anulacion" id="motivoanulacion" style="height: 100px"></textarea>
                                    <label for="floatingTextarea2"></label>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                <br>
                <button type="submit" class="btn btn-outline-danger" id="anulardte" onclick="validarcamposanulacion(this.value);">
                    <i class="fa-solid fa-receipt"></i> Anular Factura
                </button>
                <br><br>
            </div>
        </div>
    <?php
        break;

    case 'repo_lib_compras':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="repo_lib_compras" style="display: none;">
        <div class="text" style="text-align:center">LIBRO DE COMPRAS</div>
        <div class="card">
            <div id="cardBody" class="card-body" x-data="{
                usarFechaRegistro: true,
                usarFechaFEL: false,
            }">
                <div class="container contenedort">
                    <div class="row m-2">
                        <div class="col-sm-12 col-md-6 col-lg-6">
                            <div class="card" style="height: 100%;">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="switchFechaRegistro" 
                                            x-model="usarFechaRegistro">
                                        <label class="form-check-label" for="switchFechaRegistro">
                                            <strong>FECHA DE REGISTRO</strong>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body" x-show="usarFechaRegistro">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="finicio" min="1950-01-01" 
                                                value="<?= date("Y-m-d"); ?>" :required="usarFechaRegistro">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="ffin" min="1950-01-01" 
                                                value="<?= date("Y-m-d"); ?>" :required="usarFechaRegistro">
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body text-center text-muted" x-show="!usarFechaRegistro" x-cloak>
                                    <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                    <p class="mb-0">Filtro desactivado</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-sm-12 col-md-6 col-lg-6">
                            <div class="card" style="height: 100%;">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="switchFechaFEL" 
                                            x-model="usarFechaFEL">
                                        <label class="form-check-label" for="switchFechaFEL">
                                            <strong>FECHA DE FACTURAS FEL</strong>
                                        </label>
                                    </div>
                                </div>
                                <div class="card-body" x-show="usarFechaFEL">
                                    <div class="row" id="filfechasFEL">
                                        <div class="col-sm-12">
                                            <label for="finicio_fel">Desde</label>
                                            <input type="date" class="form-control" id="finicio_fel" min="1950-01-01" 
                                                value="<?= date("Y-m-d"); ?>" :required="usarFechaFEL">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin_fel">Hasta</label>
                                            <input type="date" class="form-control" id="ffin_fel" min="1950-01-01" 
                                                value="<?= date("Y-m-d"); ?>" :required="usarFechaFEL">
                                        </div>
                                    </div>
                                </div>
                                <div class="card-body text-center text-muted" x-show="!usarFechaFEL" x-cloak>
                                    <i class="fas fa-calendar-times fa-2x mb-2"></i>
                                    <p class="mb-0">Filtro desactivado</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="row m-2">
                        <div class="col-sm-12">
                            <div class="card">
                                <div class="card-header">TIPO DE LÍNEA</div>
                                <div class="card-body">
                                    <div class="row" id="filTipoLinea">
                                        <div class="col-sm-12">
                                            <span class="input-group-addon col-8">Tipo</span>
                                            <select class="form-select col-md-12" aria-label="Default select example" id="tipoLinea">
                                                <option value="0" selected>Todos</option>
                                                <option value="B">Bienes</option>
                                                <option value="S">Servicios</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="alert alert-info mx-2" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Nota:</strong> Puedes usar ambos filtros de fecha simultáneamente o solo uno de ellos. 
                        Al menos uno debe estar activo para generar el reporte.
                    </div>
                </div>
                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center">
                        <button type="button" class="btn btn-outline-danger" title="Reporte de libro de compras en pdf" 
                            @click="reportes([[`finicio`, `ffin`,`finicio_fel`, `ffin_fel`],[`tipoLinea`],[],[getAlpineData('#cardBody','usarFechaRegistro'), getAlpineData('#cardBody','usarFechaFEL')]],`pdf`,`rep_lib_compras`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Reporte de libro de compras en Excel" 
                            @click="reportes([[`finicio`, `ffin`,`finicio_fel`, `ffin_fel`],[`tipoLinea`],[],[getAlpineData('#cardBody','usarFechaRegistro'), getAlpineData('#cardBody','usarFechaFEL')]],`xlsx`,`rep_lib_compras`,1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                    </div>
                </div>
                <div id="divshow" class="container contenedort" style="display: none;">
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-sm small-font">
                            <thead>
                                <tr>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>
    <?php
        break;

    case 'repo_lib_ventas':
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="repo_lib_ventas" style="display: none;">
        <div class="text" style="text-align:center">LIBRO DE VENTAS</div>
        <div class="card">
            <div class="card-body">
                <div class="container contenedort">
                    <div class="row m-2">
                        <!-- Se elimina el bloque del filtro de Estado -->
                        <div class="col-sm-12">
                            <div class="card" style="height: 100%;">
                                <div class="card-header">RANGO DE FECHAS</div>
                                <div class="card-body">
                                    <div class="row" id="filfechas">
                                        <div class="col-sm-12">
                                            <label for="finicio">Desde</label>
                                            <input type="date" class="form-control" id="finicio" min="1950-01-01" value="<?= date('Y-m-d'); ?>">
                                        </div>
                                        <div class="col-sm-12">
                                            <label for="ffin">Hasta</label>
                                            <input type="date" class="form-control" id="ffin" min="1950-01-01" value="<?= date('Y-m-d'); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row justify-items-md-center m-3">
                    <div class="col align-items-center">
                        <!-- Se elimina el filtro de estado del array de parámetros -->
                        <button type="button" class="btn btn-outline-danger" title="Reporte de ingresos en pdf" onclick="reportes([[`finicio`,`ffin`],[],[],[1]],`pdf`,`rep_lib_ventas`,0)">
                            <i class="fa-solid fa-file-pdf"></i> Pdf
                        </button>
                        <button type="button" class="btn btn-outline-success" title="Reporte de ingresos en Excel" onclick="reportes([[`finicio`,`ffin`],[],[],[1]],`xlsx`,`rep_lib_ventas`,1)">
                            <i class="fa-solid fa-file-excel"></i> Excel
                        </button>
                        <button type="button" class="btn btn-outline-danger" onclick="printdiv2('#cuadro','0')">
                            <i class="fa-solid fa-ban"></i> Cancelar
                        </button>
                        <button type="button" class="btn btn-outline-warning" onclick="salir()">
                            <i class="fa-solid fa-circle-xmark"></i> Salir
                        </button>
                    </div>
                </div>
                <div id="divshow" class="container contenedort" style="display: none;">
                    <style>
                        .small-font th,
                        .small-font td {
                            font-size: 12px;
                        }
                    </style>
                    <div class="table-responsive-sm">
                        <table id="tbdatashow" class="table table-sm small-font">
                            <thead>
                                <tr>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
                <div id="divshowchart" class="container contenedort" style="display: none;">
                    <canvas id="myChart"></canvas>
                </div>
            </div>
        </div>
    <?php
        break;
    case 'proveedores':
        $showmensaje = false;
        $idEmisorSelected = $_POST['xtra'] ?? null;
        try {
            $database->openConnection();

            $proveedores = $database->selectColumns("cv_emisor", ['id', 'nit', 'nombre_comercial', 'nombre'], 'estado = 1');
            if ($idEmisorSelected && $idEmisorSelected != '0') {
                $proveedorSelected = $database->selectColumns("cv_emisor", ['id', 'id_afiliacion_iva', 'correo', 'nit', 'nombre_comercial', 'nombre', 'direccion'], 'id = ?', [$idEmisorSelected]);
                if (empty($proveedorSelected)) {
                    $showmensaje = true;
                    throw new Exception("El proveedor seleccionado no existe.");
                }
            }
            $tiposAfiliaciones = $database->selectColumns("cv_tipo_afiliacion_iva", ['id', 'abreviacion', 'descripcion']);

            $status = true;
        } catch (Exception $e) {
            if (!$showmensaje) {
                $codigoError = logerrores($e->getMessage(), __FILE__, __LINE__, $e->getFile(), $e->getLine());
            }
            $mensaje = ($showmensaje) ? "" . $e->getMessage() : "¡Error! Intente nuevamente, o reporte este codigo de error($codigoError)";
            $status = false;
        } finally {
            $database->closeConnection();
        }
    ?>
        <input type="text" id="file" value="views001" style="display: none;">
        <input type="text" id="condi" value="proveedores" style="display: none;">

        <div class="contenedort">
            <?php if (!$status) { ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>!!</strong> <?= $mensaje; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>
            <form id="formProveedor">
                <div class="card p-3 mb-4">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="id_afiliacion_iva">Afiliación IVA </label>
                                <select class="form-select" id="id_afiliacion_iva" name="id_afiliacion_iva">
                                    <option value="">Seleccione...</option>
                                    <?php
                                    if (!empty($tiposAfiliaciones)) {
                                        foreach ($tiposAfiliaciones as $tipo) {
                                            $selected = (isset($proveedorSelected[0]['id_afiliacion_iva']) && $proveedorSelected[0]['id_afiliacion_iva'] == $tipo['id']) ? 'selected' : '';
                                            $id = htmlspecialchars($tipo['id']);
                                            $label = isset($tipo['abreviacion']) ? $tipo['abreviacion'] : '';
                                            $desc = isset($tipo['descripcion']) ? $tipo['descripcion'] : '';
                                            echo "<option value=\"{$id}\" $selected>" . htmlspecialchars(trim("$label - $desc")) . "</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="correo">Correo </label>
                                <input type="email" class="form-control" id="correo"
                                    name="correo" maxlength="60" value="<?= isset($proveedorSelected[0]['correo']) ? htmlspecialchars($proveedorSelected[0]['correo']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nit">NIT <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nit"
                                    name="nit" required maxlength="45" value="<?= isset($proveedorSelected[0]['nit']) ? htmlspecialchars($proveedorSelected[0]['nit']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre_comercial">Nombre Comercial <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre_comercial"
                                    name="nombre_comercial" required maxlength="100" value="<?= isset($proveedorSelected[0]['nombre_comercial']) ? htmlspecialchars($proveedorSelected[0]['nombre_comercial']) : ''; ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="nombre">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="nombre"
                                    name="nombre" required maxlength="100" value="<?= isset($proveedorSelected[0]['nombre']) ? htmlspecialchars($proveedorSelected[0]['nombre']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="direccion">Dirección</label>
                        <input type="text" class="form-control" id="direccion"
                            name="direccion" maxlength="100" value="<?= isset($proveedorSelected[0]['direccion']) ? htmlspecialchars($proveedorSelected[0]['direccion']) : ''; ?>">
                    </div>
                </div>
                <?= $csrf->getTokenField(); ?>
                <div class="card-footer text-end">
                    <button type="button" class="btn btn-secondary" onclick="printdiv2('#cuadro','0')">
                        <i class="fas fa-times"></i> Cancelar
                    </button>
                    <?php if (isset($proveedorSelected[0]['id']) && $status) : ?>
                        <button type="button" class="btn btn-primary" onclick="obtiene(['<?= $csrf->getTokenName() ?>','correo','nit','nombre_comercial','nombre','direccion'],['id_afiliacion_iva'],[],'update_proveedor','0',['<?= htmlspecialchars($secureID->encrypt($proveedorSelected[0]['id'])) ?>'],'NULL','¿Está seguro de actualizar el proveedor?')">
                            <i class="fas fa-save"></i> Actualizar
                        </button>
                    <?php endif; ?>
                    <?php if (!isset($proveedorSelected) && $status) : ?>
                        <button type="button" class="btn btn-primary" onclick="obtiene(['<?= $csrf->getTokenName() ?>','correo','nit','nombre_comercial','nombre','direccion'],['id_afiliacion_iva'],[],'create_proveedor','0',[],'NULL','¿Está seguro de guardar el proveedor?')">
                            <i class="fas fa-save"></i> Guardar
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="container-fluid mt-3">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="fas fa-truck"></i> Proveedores existentes</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tabla_proveedores" class="table table-striped table-bordered table-hover table-sm small text-nowrap align-middle" style="width:100%">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>NIT</th>
                                    <th>Nombre Comercial</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($proveedores ?? []) as $key => $proveedor) { ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($key + 1); ?></td>
                                        <td><?php echo htmlspecialchars($proveedor['nit']); ?></td>
                                        <td class="text-truncate"><?php echo htmlspecialchars($proveedor['nombre_comercial']); ?></td>
                                        <td class="text-center">
                                            <button type="button" class="btn btn-warning btn-sm me-1" onclick="printdiv2('#cuadro', <?= $proveedor['id']; ?>)" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-danger btn-sm" onclick="obtiene(['<?= $csrf->getTokenName() ?>'],[],[],'delete_proveedor','0',['<?= htmlspecialchars($secureID->encrypt($proveedor['id'])) ?>'],'NULL','¿Está seguro de eliminar el proveedor?')" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <script>
            $(document).ready(function() {
                convert_table_to_datatable('tabla_proveedores');
                inicializarValidacionAutomaticaGeneric('#formProveedor');
            });
        </script>
<?php
        break;
}

function simplexml_to_array($xml)
{
    $array = [];

    foreach ((array) $xml as $index => $node) {
        $array[$index] = (is_object($node) || is_array($node)) ? simplexml_to_array($node) : $node;
    }

    if (isset($xml->attributes)) {
        foreach ($xml->attributes() as $attrName => $attrValue) {
            $array["@attributes"][$attrName] = (string) $attrValue;
        }
    }

    return $array;
}
function decimalesvalidos($numeros)
{
    $max_decimales_validos = 0;
    foreach ($numeros as $numero) {
        $numero_str = rtrim(rtrim($numero, '0'), '.');
        $decimales = strlen(substr(strrchr($numero_str, "."), 1));
        if ($decimales > $max_decimales_validos) {
            $max_decimales_validos = $decimales;
        }
    }
    return $max_decimales_validos;
}
function sumarray($data, $filterCategory, $indice_category, $indice_value)
{
    $sum = 0;
    // $filterCategory = 'A';
    foreach ($data as $row) {
        if ($row[$indice_category] == $filterCategory) {
            $sum += $row[$indice_value];
        }
    }
    return $sum;
}
?>
<style>
    th,
    td {
        padding: 8px;
        word-wrap: break-word;
    }

    th:nth-child(1),
    td:nth-child(1) {
        width: 10%;
    }

    th:nth-child(3),
    td:nth-child(3) {
        width: 30%;
    }

    th:nth-child(9),
    td:nth-child(9) {
        width: 15%;
    }
</style>