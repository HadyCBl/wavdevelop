<?php
session_start();
include '../../../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
date_default_timezone_set('America/Guatemala');
$condi = $_POST["condi"];
$codusu = $_SESSION['id'];

switch ($condi) {
    case 'dias_laborales': {
        
            $codusu = $_SESSION['id'];
            $id_agencia = $_SESSION['id_agencia'];
            $codagencia = $_SESSION['agencia'];
            $xtra = $_POST["xtra"];
    ?>
            <!--Aho_0_PrmtrzcAhrrs Inicio de Ahorro Sección 0 Parametros cuentas ahorro-->
            <input type="text" id="file" value="creditos_02" style="display: none;">
            <input type="text" id="condi" value="dias_laborales" style="display: none;">
            <div class="text" style="text-align:center">DIAS LABORALES</div>
            <div class="card">
                <div class="card-header">Días laborales</div>
                <div class="card-body" style="padding-bottom: 0px !important;">
                    <table id="table_id2" class="table table-hover table-border">
                        <thead class="text-light table-head-aprt" style="font-size: 0.8rem;">
                            <tr>
                                <th>ID</th>
                                <th>Día</th>
                                <th>Laboral</th>
                                <th>Acciones</th>
                                <th>Dia ajuste</th>
                            </tr>
                        </thead>
                        <?php

                        $query = "SELECT td.*, (SELECT tdl.dia FROM tb_dias_laborales tdl WHERE tdl.id_dia=td.id_dia_ajuste AND producto=0) AS dia_ajuste FROM tb_dias_laborales td WHERE producto=0";
                        $result = $conexion->query($query);

                        if ($result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) { ?>
                                <tr>
                                    <td> <?= $row["id_dia"]  ?></td>
                                    <td> <?= $row["dia"] ?></td>
                                    <td>
                                        <?php if ($row["laboral"] == 1) { ?>
                                            <span class="badge text-bg-success">Se labora</span>
                                        <?php } else { ?>
                                            <span class="badge text-bg-secondary">No se labora</span>
                                        <?php } ?>
                                    </td>
                                    <!-- switch -->
                                    <td>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" <?= ($row['laboral'] == 1) ? 'checked' : ' '; ?> id="<?= "S-" . $row["id"]; ?>" onchange="estado_switch('<?= 'S-' . $row['id'] ?>','<?= $row['id']; ?>')">
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($row["laboral"] == 0) {
                                            //BUSCAR OPCIONES DIA
                                            $banderaant = false;
                                            $banderades = false;
                                            $k = 1;
                                            $diasajuste = array();
                                            $idant = $row["id"];
                                            $iddes = $row["id"];
                                            while ($k < 4) {
                                                // validar rangos
                                                $idant = $idant - 1;
                                                $iddes = $iddes + 1;

                                                if ($idant == 0) {
                                                    $idant = 7;
                                                }

                                                if ($iddes == 8) {
                                                    $iddes = 1;
                                                }
                                                if ($banderaant == false) {

                                                    $res = $conexion->query("SELECT tdl.id AS id, tdl.dia AS dia FROM tb_dias_laborales tdl WHERE (tdl.id = $idant) AND tdl.laboral = 1");
                                                    $aux = mysqli_error($conexion);
                                                    if ($aux) {
                                                        echo json_encode(['Fallo al consultar dia de ajuste', '0']);
                                                        return;
                                                    }
                                                    if (!$res) {
                                                        echo json_encode(['Error al consultar dia de ajuste', '1']);
                                                    }
                                                    //pasar los datos al array
                                                    while ($row2 = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
                                                        $diasajuste[] = $row2;
                                                        $banderaant = true;
                                                    }
                                                }
                                                if ($banderades == false) {
                                                    $res = $conexion->query("SELECT tdl.id AS id, tdl.dia AS dia FROM tb_dias_laborales tdl WHERE (tdl.id = $iddes) AND tdl.laboral = 1");
                                                    $aux = mysqli_error($conexion);
                                                    if ($aux) {
                                                        echo json_encode(['Fallo al consultar dia de ajuste', '0']);
                                                        return;
                                                    }
                                                    if (!$res) {
                                                        echo json_encode(['Error al consultar dia de ajuste', '1']);
                                                    }
                                                    //pasar los datos al array
                                                    while ($row1 = mysqli_fetch_array($res, MYSQLI_ASSOC)) {
                                                        $diasajuste[] = $row1;
                                                        $banderades = true;
                                                    }
                                                }
                                                $k = ($banderaant && $banderades) ? 4 : $k;
                                                $k++;
                                            };
                                        ?>
                                            <div class="row">
                                                <div class="col">
                                                    <select class="form-select form-select-sm" aria-label=".form-select-sm example" onchange="dia_ajuste(this.value, '<?= $row['id']; ?>')">
                                                        <?php
                                                        //IMPRESION DE DIAS
                                                        $selected = "";
                                                        foreach ($diasajuste as $key => $value) {
                                                            ($value["id"] == $row['id_dia_ajuste']) ? $selected = "selected" : $selected = "";
                                                            $nombre = $value["dia"];
                                                            $id_dia = $value["id"];
                                                            echo '<option value="' . $id_dia . '" ' . $selected . '>' . $nombre . '</option>';
                                                        }
                                                        ?>
                                                    </select>
                                                </div>
                                            </div>
                                        <?php } ?>
                                    </td>
                                </tr>
                            <?php
                            }
                        } else { ?>
                            <tr>
                                <td colspan='3'>No se encontraron resultados en la consulta.</td>
                            </tr>
                        <?php }
                        ?>
                    </table>
                </div>
            </div>
            <?php
            ?>
            <script>
                function estado_switch(elemento, id) {
                    var switchElement = document.getElementById(elemento);
                    var estado = switchElement.checked;
                    estado = estado ? 1 : 0;
                    obtiene([], [], [], `update_dias_laborales`, `0`, [id, estado]);
                }
                //Funcion para dia de ajuste con select
                function dia_ajuste(id, id_dia_general) {
                    obtiene([], [], [], `update_dia_ajuste`, `0`, [id, id_dia_general]);
                }
            </script>
    <?php }
        break;
    case 'regiones':
            $xtra = $_POST["xtra"];
            $codusu = $_SESSION['id'];
    ?>
            <style>
                .select2-container--bootstrap-5 .select2-selection {
                    width: 100%;
                    min-height: calc(1.5em + .75rem + 2px);
                    padding: 16px 12px;
                    font-family: inherit;
                    font-size: 1rem;
                    font-weight: 400;
                    line-height: 1.5;
                    color: #212529;
                    background-color: #fff;
                    border: 1px solid #ced4da;
                    border-radius: .375rem;
                    transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
                    -webkit-appearance: none;
                    -moz-appearance: none;
                    appearance: none
                }
            </style>
    
            <input type="text" id="condi" value="regiones" hidden>
            <input type="text" id="file" value="creditos_02" hidden>
            
            <!-- Título principal -->
            <div class="text-center mb-4">
                <h3 class="text-primary">
                    <i class="fa-solid fa-map-location-dot me-2"></i>
                    PARAMETRIZACIÓN DE REGIONES DE CRÉDITO
                </h3>
                <p class="text-muted">Administre las regiones de crédito, asignación de analistas y agencias</p>
            </div>
            
            <!-- Card del formulario -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-pen-to-square me-2"></i>
                        Formulario de Región
                    </h5>
                </div>
                <div class="card-body">
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <!-- nombre -->
                        <div class="row">
                            <div class="col-12">
                                <label for="name" class="form-label fw-semibold">
                                    <i class="fa-solid fa-tag text-primary me-1"></i>
                                    Nombre de la Región <span class="text-danger">*</span>
                                </label>
                                <div class="input-group mb-3">
                                    <span class="input-group-text bg-light">
                                        <i class="fa-solid fa-map-marker-alt text-primary"></i>
                                    </span>
                                    <input type="text" class="form-control" id="name" 
                                           placeholder="Ingrese el nombre de la región" required>
                                    <input type="text" id="id_region" hidden>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Encargado de la región -->
                        <div class="row">
                            <div class="col-md-6">
                                <label for="select2_encargado" class="form-label fw-semibold">
                                    <i class="fa-solid fa-user-tie text-success me-1"></i>
                                    Encargado de la Región (Analista) <span class="text-danger">*</span>
                                </label>
                                <select id="select2_encargado" class="form-select mb-3"
                                    data-placeholder="Seleccione el analista encargado" data-control="select2">
                                    <option value="">-- Seleccione un analista --</option>
                                    <?php
                                    $consultaAnalistas = mysqli_query($conexion, "SELECT CONCAT(nombre, ' ', apellido) AS nameusu, id_usu FROM tb_usuario WHERE puesto='ANA' AND estado=1");
                                    while ($analista = mysqli_fetch_array($consultaAnalistas, MYSQLI_ASSOC)) {
                                        $nombre = $analista["nameusu"];
                                        $id_usu = $analista["id_usu"];
                                    ?>
                                        <option value="<?= $id_usu ?>"><?= $nombre ?></option>
                                    <?php }
                                    ?>
                                </select>
                            </div>
                            
                            <!-- Agencias -->
                            <div class="col-md-6">
                                <label for="select2_agencies" class="form-label fw-semibold">
                                    <i class="fa-solid fa-building text-info me-1"></i>
                                    Agencias de la Región <span class="text-danger">*</span>
                                </label>
                                <select id="select2_agencies" multiple="multiple" class="form-select mb-3">
                                    <?php
                                    $consultaAgencias = mysqli_query($conexion, "SELECT nom_agencia, id_agencia FROM tb_agencia");
                                    while ($agencia = mysqli_fetch_array($consultaAgencias, MYSQLI_ASSOC)) {
                                        $nomage = $agencia["nom_agencia"];
                                        $id_age = $agencia["id_agencia"];
                                    ?>
                                        <option value="<?= $id_age ?>"><?= $nomage ?></option>
                                    <?php }
                                    ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Botones de acción -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2 justify-content-center mt-3 p-3 bg-light rounded">
                                    <button type="button" class="btn btn-success px-4" id="btGuardar"
                                    onclick="obtiene([`name`,`select2_encargado`],[],[],`create_region`,`crud_regiones`,['<?= $codusu; ?>',getSelectedSelect2('select2_agencies')])">
                                    <i class="fa-solid fa-floppy-disk me-2"></i>Guardar Región
                                </button>
                                <button type="button" class="btn btn-primary px-4" id="btEditar" style="display: none;"
                                    onclick="obtiene([`name`,`select2_encargado`,`id_region`],[],[],`update_region`,`crud_regiones`,['<?= $codusu; ?>',getSelectedSelect2('select2_agencies')])">
                                        <i class="fa-solid fa-pen-to-square me-2"></i>Actualizar Región
                                    </button>
                                    <button type="button" class="btn btn-warning px-4" onclick="limpiarFormularioRegion()">
                                        <i class="fa-solid fa-broom me-2"></i>Limpiar
                                    </button>
                                    <button type="button" class="btn btn-danger px-4" onclick="salir()">
                                        <i class="fa-solid fa-circle-xmark me-2"></i>Salir
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
    
            <!-- Card de la tabla de regiones -->
            <div class="card shadow-sm">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fa-solid fa-list me-2"></i>
                        Listado de Regiones
                    </h5>
                </div>
                <div class="card-body">
                    <div class="container contenedort" style="max-width: 100% !important;">
                        <div class="row">
                            <div class="col">
                                <div class="table-responsive">
                                    <table id="table-regiones" class="table table-hover table-striped align-middle">
                                        <thead class="table-dark">
                                            <tr class="text-center">
                                                <th style="width: 5%;"><i class="fa-solid fa-hashtag"></i></th>
                                                <th style="width: 20%;">
                                                    <i class="fa-solid fa-map-marker-alt me-1"></i>Región
                                                </th>
                                                <th style="width: 18%;">
                                                    <i class="fa-solid fa-user-tie me-1"></i>Encargado
                                                </th>
                                                <th style="width: 18%;">
                                                    <i class="fa-solid fa-building me-1"></i>Agencia
                                                </th>
                                                <th style="width: 12%;">
                                                    <i class="fa-solid fa-building-circle-check me-1"></i>Agencias
                                                </th>
                                                <!-- <th style="width: 12%;">
                                                    <i class="fa-solid fa-toggle-on me-1"></i>Estado
                                                </th> -->
                                                <th style="width: 15%;">
                                                    <i class="fa-solid fa-gear me-1"></i>Acciones
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody id="tb_cuerpo_regiones">
                                            <?php
                                            $consultaRegiones = mysqli_query($conexion, "SELECT 
                                                cr.id, 
                                                cr.nombre, 
                                                cr.id_encargado,
                                                CONCAT(u.nombre, ' ', u.apellido) AS nombre_encargado,
                                                ag.nom_agencia AS nombre_agencia_encargado,
                                                cr.estado,
                                                IFNULL((SELECT COUNT(*) FROM cre_regiones_agencias cra WHERE cra.id_region = cr.id), 0) AS countAgencies,
                                                (SELECT GROUP_CONCAT(cra.id_agencia) FROM cre_regiones_agencias cra WHERE cra.id_region = cr.id) AS agencyIds,
                                                (SELECT GROUP_CONCAT(a.nom_agencia SEPARATOR ', ') FROM cre_regiones_agencias cra LEFT JOIN tb_agencia a ON cra.id_agencia = a.id_agencia WHERE cra.id_region = cr.id) AS agencyNames
                                            FROM cre_regiones cr
                                            LEFT JOIN tb_usuario u ON cr.id_encargado = u.id_usu
                                            LEFT JOIN tb_agencia ag ON u.id_agencia = ag.id_agencia
                                            WHERE cr.estado IN (0, 1)
                                            ORDER BY cr.estado DESC, cr.nombre");
                                            while ($dataRegion = mysqli_fetch_array($consultaRegiones, MYSQLI_ASSOC)) {
                                                $id = $dataRegion["id"];
                                                $nombre = $dataRegion["nombre"];
                                                $nombreEncargado = $dataRegion["nombre_encargado"] ?? 'Sin asignar';
                                                $nombreAgenciaEncargado = $dataRegion["nombre_agencia_encargado"] ?? 'N/A';
                                                $idEncargado = $dataRegion["id_encargado"] ?? '';
                                                $countAgencies = $dataRegion["countAgencies"];
                                                $agencyIds = $dataRegion["agencyIds"] ?? '';
                                                $agencyNames = $dataRegion["agencyNames"] ?? 'Sin agencias';
                                                $estado = $dataRegion["estado"];
                                            ?>
                                                <tr>
                                                    <td class="text-center fw-bold"><?= $id ?></td>
                                                    <td>
                                                        <i class="fa-solid fa-map-marker-alt text-primary me-2"></i>
                                                        <strong><?= $nombre ?></strong>
                                                    </td>
                                                    <td>
                                                        <i class="fa-solid fa-user-circle text-success me-2"></i>
                                                        <?= $nombreEncargado ?>
                                                    </td>
                                                    <td>
                                                        <i class="fa-solid fa-building text-info me-2"></i>
                                                        <div style="max-width: 300px; white-space: normal;">
                                                            <?= $agencyNames ?>
                                                        </div>
                                                    </td>
                                                    <td class="text-center">
                                                        <span class="badge bg-info rounded-pill fs-6">
                                                            <i class="fa-solid fa-building-circle-check me-1"></i>
                                                            <?= $countAgencies ?>
                                                        </span>
                                                    </td>
                                                    <!-- <td class="text-center">
                                                        <div class="form-check form-switch d-flex justify-content-center">
                                                            <input class="form-check-input" type="checkbox" role="switch" disabled
                                                                  +
                                                                   onchange="estado_switch_region('S-region-')">
                                                        </div>
                                                    </td> -->
                                                    <td class="text-center">
                                                        <div class="btn-group" role="group">
                                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                                    title="Editar región"
                                                                    onclick="editarRegion('<?= $id ?>','<?= htmlspecialchars($nombre, ENT_QUOTES) ?>','<?= $idEncargado ?>','<?= $agencyIds ?>')">
                                                                <i class="fa-solid fa-pen-to-square"></i>
                                                            </button>
                                                            <?php if ($estado == 1) { ?>
                                                                <button type="button" class="btn btn-sm btn-outline-danger" 
                                                                        title="Eliminar región"
                                                                        onclick="eliminar('<?= $id ?>', 'crud_regiones', '0', 'delete_region', '')">
                                                                    <i class="fa-solid fa-trash-alt"></i>
                                                                </button>
                                                            <?php } else { ?>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary" 
                                                                        title="Región desactivada - use el switch para reactivar"
                                                                        disabled>
                                                                    <i class="fa-solid fa-ban"></i>
                                                                </button>
                                                            <?php } ?>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php
                                            } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <script>
                //Datatable para parametrizacion
                $(document).ready(function() {
                    // Inicializar Select2 PRIMERO (independiente de DataTable)
                    try {
                        $('#select2_agencies').select2({
                            theme: 'bootstrap-5',
                            language: "es",
                            placeholder: "Seleccione agencias",
                            closeOnSelect: false,
                            width: '100%'
                        });
                        
                        $('#select2_encargado').select2({
                            theme: 'bootstrap-5',
                            language: "es",
                            placeholder: "Seleccione el encargado",
                            width: '100%'
                        });
                    } catch (error) {
                        console.error('Error al inicializar Select2:', error);
                    }
                    
                    // Inicializar DataTable DESPUÉS
                    try {
                        if (typeof convertir_tabla_a_datatable === 'function') {
                            convertir_tabla_a_datatable("table-regiones");
                        } else {
                            // Fallback: inicializar DataTable directamente
                            $('#table-regiones').DataTable({
                                language: {
                                        url: 'https://cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json'
                                },
                                responsive: true,
                                pageLength: 10
                            });
                        }
                    } catch (error) {
                        console.error('Error al inicializar DataTable:', error);
                    }
                    
                    // Inicializar tooltips de Bootstrap
                    try {
                        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                            return new bootstrap.Tooltip(tooltipTriggerEl);
                        });
                    } catch (error) {
                        console.error('Error al inicializar tooltips:', error);
                    }
                });
                
                // Función para editar región
                function editarRegion(id, nombre, idEncargado, agencyIds) {
                    // console.log('🔍 Editando región:', {id, nombre, idEncargado, agencyIds});
                    $('#id_region').val(id);
                    $('#name').val(nombre);
                    $('#select2_encargado').val(idEncargado).trigger('change');
                    
                    // Configurar las agencias seleccionadas
                    if (agencyIds && agencyIds !== '' && agencyIds !== 'null') {
                        var agenciesArray = agencyIds.split(',');
                        // console.log('📋 Agencias a cargar:', agenciesArray);
                        $('#select2_agencies').val(agenciesArray).trigger('change');
                    } else {
                        // console.log('⚠️ Sin agencias asignadas');
                        $('#select2_agencies').val([]).trigger('change');
                    }
                    
                    // Mostrar botón editar y ocultar guardar
                    $('#btEditar').show();
                    $('#btGuardar').hide();
                    
                    // Scroll al formulario
                    $('html, body').animate({
                        scrollTop: $("#name").offset().top - 100
                    }, 500);
                }
                
                // Función para limpiar formulario
                function limpiarFormularioRegion() {
                    $('#id_region').val('');
                    $('#name').val('');
                    $('#select2_encargado').val('').trigger('change');
                    $('#select2_agencies').val([]).trigger('change');
                    $('#btEditar').hide();
                    $('#btGuardar').show();
                }
                
                // Función para cambiar estado de región (activar/desactivar)
                function estado_switch_region(elemento, id) {
                    var switchElement = document.getElementById(elemento);
                    var estado = switchElement.checked;
                    estado = estado ? 1 : 0;
                    // console.log('🔄 Cambiando estado de región:', {id, estado});
                    // console.log('📤 Enviando a backend con archivo:', [id, estado]);
                    
                    // Enviar petición y recargar la vista después
                    $.ajax({
                        url: 'src/cruds/crud_regiones.php',
                        type: 'POST',
                        data: {
                            condi: 'update_estado_region',
                            archivo: [id, estado]
                        },
                        success: function(response) {
                            // console.log('✅ Respuesta del servidor:', response);
                            try {
                                var res = JSON.parse(response);
                                if (res[1] == '1') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: '¡Éxito!',
                                        text: res[0],
                                        timer: 2000,
                                        showConfirmButton: false
                                    });
                                    // Recargar la vista para mostrar los cambios
                                    printdiv2('regiones', 'creditos_02', '');
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'Error',
                                        text: res[0]
                                    });
                                    // Revertir el switch si falló
                                    switchElement.checked = !switchElement.checked;
                                }
                            } catch(e) {
                                console.error('Error al parsear respuesta:', e);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: 'Error al procesar la respuesta del servidor'
                                });
                                switchElement.checked = !switchElement.checked;
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('❌ Error en petición:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de conexión',
                                text: 'No se pudo conectar con el servidor'
                            });
                            // Revertir el switch si hay error
                            switchElement.checked = !switchElement.checked;
                        }
                    });
                }
            </script>
        <?php
    break;
}
?>