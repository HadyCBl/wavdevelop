<?php

use Micro\Helpers\Log;
use Micro\Models\Departamento;

session_start();
include '../../includes/BD_con/db_con.php';
mysqli_set_charset($conexion, 'utf8');
mysqli_set_charset($general, 'utf8');
date_default_timezone_set('America/Guatemala');

$codusu = $_SESSION['id'];
$condi = $_POST["condi"];
$hoy = date("Y-m-d");

switch ($condi) {
    ///////////////////////////CASE ADICION GRUPOS
    case 'add_grupo':
        try {
            /**
             * TEMPORALMENTE FIJO, SE CARGAN LOS DE GUATE, DESPUES HACERLO DINAMICO DEPENDIENDO DEL PAIS
             */
            $departamentosGuatemala = Departamento::obtenerPorPais(4);
        } catch (Exception $e) {
            $departamentosGuatemala = [];
            Log::error("Error al obtener departamentos: " . $e->getMessage());
        }
    ?>
        <input type="text" value="add_grupo" id="condi" hidden>
        <input type="text" value="grupos" id="file" hidden>
        <!--  LLENAR DATOS PARA -->
        <div class="container">
            <div class="text" style="text-align: center">ADICION DE GRUPOS</div>
            <div class="card crdbody">
                <div class="card-header panelcolor">AGREGAR UN GRUPO</div>
                <div class="card-body">

                    <div class="row crdbody col-sm-2">
                        <!-- REVISAR LAS tildes , AL MOMNETO DE JALAR LOS DATOS, Tambien los datos que se muestran en el SELECT,  -->
                        <!-- PARA LA BUSQUEDA DE CLIENTES MOSNTRAR LOS CLIENTES QUE YA ESTAN EN EL GRUPO ?? EVITAR REPETIDOS??   -->
                        <button type="button" onclick="opciones(1)" class="btn btn-outline-primary" title="Buscar Grupo"
                            data-bs-toggle="modal" data-bs-target="#buscargrupo">
                            <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo </button>
                    </div>
                    <br>
                    <form action="">

                        <div class="row crdbody">

                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text">Codigo de grupo</span>
                                    <input type="text" class="form-control" id="CodigoGrupo" placeholder="Código de grupo"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text">Nombre del Grupo Solidario</span>
                                    <input type="text" class="form-control" id="NombreGrupo" placeholder="Nombre Grupo"
                                        required>
                                </div>
                            </div>
                        </div> <!-- <div class="row crdbody"> _-->


                        <div class="row crdbody">

                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text">Referencia </span>
                                    <input type="text" class="form-control" id="canton"
                                        placeholder="Canton, Aldea, Colonia, etc.">
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text">Direccion</span>
                                    <input type="text" class="form-control" id="direcciongrupo" placeholder="14 av 2-14 z4"
                                        required>
                                </div>
                            </div>
                        </div> <!-- <div class="row crdbody"> _-->


                        <div class="row crdbody">

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">Departamento</span>
                                    <select id="depargrupo" class="form-select" onchange="municipio('#munigrupo', this.value)">
                                        <option selected>...</option>
                                        <?php
                                        foreach ($departamentosGuatemala as $departamento) {
                                            $nombre = $departamento["nombre"];
                                            $codigo_departa = $departamento["id"];

                                            echo '<option value="' . $codigo_departa . '">' . $nombre . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">Municipio</span>
                                    <select id="munigrupo" class="form-select">
                                        <option selected>...</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                    </form>
                    <input type="number" value="0" id="contador" disabled hidden>
                    <input type="text" id="idUsuario" value="<?php echo $codusu ?>" disabled hidden>
                    <div class="row">
                        <div class=" col-sm-6">
                            <button id="btnGua" class="btn btn-outline-success" type="submit"
                                onclick="insttbl('<?= $codusu ?>');">
                                <i class="fa-solid fa-floppy-disk"></i> Guardar</button>

                            <button id="btnAct" class="btn btn-outline-primary" type="submit"
                                onclick="insttbl('<?= $codusu ?>');">
                                <i class="fa-solid fa-pen-to-square"></i> Actualizar</button>

                            <button id="btnEli" onclick="limpiar();" class="btn btn-outline-danger" type="submit">
                                <i class="fa-solid fa-mug-hot"></i> Limpiar</button>
                        </div>

                    </div>

                </div>

            </div>
        </div>
        </div>
        <!-- fin container 1-->
        <!-- ----------- inicio container 2---------------- -->
        <div class="container">
            <div class="card crdbody">
                <div class="card-header panelcolor">INTEGRANTES DE GRUPO</div>
                <div class="card-body">

                    <!-- ------------ De los grupos que estan en proceso de credito  ------------------ -->
                    <div class="row">
                        <div class="col-lg-12 col-md-12 col-sm-12" id="msjAlerta">

                            <div class="alert alert-warning d-flex align-items-center" role="alert">
                                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                                    <use xlink:href="#exclamation-triangle-fill" />
                                </svg>
                                <div>
                                    El grupo seleccionado se encuentra en un ciclo crediticio, razón por la cual no puede ser
                                    modificado.
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-12 col-md-12 col-sm-12" id="msjAlerta1">

                            <div class="alert alert-success d-flex align-items-center" role="alert">
                                <svg class="bi flex-shrink-0 me-2" width="24" height="24" role="img" aria-label="Warning:">
                                    <use xlink:href="#exclamation-triangle-fill" />
                                </svg>
                                <div>
                                    Este grupo no tiene créditos. Puede agregar o quitar clientes del grupo.
                                </div>
                            </div>
                        </div>


                    </div>

                    <!-- Inicio de tabla responsive-->
                    <table id="tblgrupoIntegrantes" class="table table-responsive">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Codigo</th>
                                <th>Nombre Cliente</th>
                                <th>Identidad</th>
                                <th>Fecha</th>
                                <th>Cargo</th>
                                <th>Opciones</th>
                            </tr>
                        </thead>
                        <tbody id="tbgrpclt">
                            <tr>
                                <td>-</td>
                                <td>-</td>
                                <td>- -</td>
                                <td>-</td>
                                <td>-</td>
                                <td>-</td>
                                <td>
                                    <button type="button" class="btn btn-outline-primary" title="Eliminar">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>

                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" id="ingresarCliente" class="btn btn-outline-info" title="Adicionar Integrante"
                        onclick="addclntgrp()">
                        <i class="fa-solid fa-circle-plus fa-xl"></i></button>

                    <button type="button" class="btn btn-outline-info" title="Imprimir" onclick="printreportgroup()"
                        data-target="_blank">
                        <i class="fa-solid fa-print"></i></button>

                    <button type="button" id="cerrar" class="btn btn-warning" data-target="_blank" onclick="cerrarGrupo()">
                        <i class="fa-solid fa-lock-open"></i> Cerrar Grupo</button>

                    <button type="button" id="abrir" class="btn btn-warning" data-target="_blank" onclick="abrirGrupo()">
                        <i class="fa-solid fa-lock "></i> Abrir Grupo</button>
                </div>
            </div>
        </div>

        </div>
        <?php
        include_once "../../src/cris_modales/modales.php";
        include_once "../../src/cris_modales/mdls_GruposClientes.php";
        include_once "../../src/cris_modales/mdls_AsignarCargo.php"; ?>
        <!--  LLENAR DATOS PARA -->
        <script>
            $(document).ready(function() {
                $('#btnAct').hide();
                $('#btnEli').hide();
                $('#cerrar').hide();
                $('#abrir').hide();
                $('#ingresarCliente').hide();
                $('#msjAlerta').hide();
                $('#msjAlerta1').hide();
            });

            function obtcligrup(boton) {
                const dataId = boton.getAttribute('data-id'); // Obtener el valor del data-id
                const valores = dataId.split(','); // Separar los valores en un array

                // Imprimir cada valor en una línea separada
                let i = 0;
                let a = 0;
                let b = 0;
                valores.forEach(valor => {
                    i++;
                    let cant = "fname".concat(i);
                    document.getElementById(cant).value = valor;
                    console.log(valor); // Muestra cada valor en la consola
                    if (i == 1) {
                        a = valor;
                    } else {
                        b = valor;
                    }
                });
            }
        </script>
    <?php
    //inicio bloqyue pph
    break;
    ///////////////////////////CASE ADICION GRUPOS--------------------------------
    case 'consulta_grupo':

        try {
            /**
             * TEMPORALMENTE FIJO, SE CARGAN LOS DE GUATE, DESPUES HACERLO DINAMICO DEPENDIENDO DEL PAIS
             */
            $departamentosGuatemala = Departamento::obtenerPorPais(4);
        } catch (Exception $e) {
            $departamentosGuatemala = [];
            Log::error("Error al obtener departamentos: " . $e->getMessage());
        }
    ?>
        <!-- ----------- inicio container 1---------------- -->
        <div class="container">
            <div class="text" style="text-align: center">DAR DE BAJA A GRUPO</div>
            <div class="card crdbody">
                <div class="card-header panelcolor">SELECCION DE GRUPO</div>
                <div class="card-body">
                    <div class="row col-sm-2 crdbody">
                        <button type="button" class="btn btn-outline-primary" title="Buscar Grupo" data-bs-toggle="modal"
                            data-bs-target="#buscargrupo">
                            <i class="fa-solid fa-magnifying-glass"></i> Buscar Grupo </button>
                    </div>

                    <br>

                    <form action="">
                        <div class="row crdbody">

                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text">Codigo de grupo</span>
                                    <input type="text" class="form-control" id="CodigoGrupo" placeholder="Código de grupo"
                                        readonly>
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text">Nombre del Grupo Solidario</span>
                                    <input type="text" class="form-control" id="NombreGrupo" placeholder="Nombre Grupo"
                                        required>
                                </div>
                            </div>
                        </div>

                        <div class="row crdbody">

                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text">Canton</span>
                                    <input type="text" class="form-control" id="canton"
                                        placeholder="Canton, Aldea, Colonia, etc.">
                                </div>
                            </div>

                            <div class="col-md-7">
                                <div class="input-group">
                                    <span class="input-group-text">Direccion</span>
                                    <input type="text" class="form-control" id="direcciongrupo" placeholder="14 av 2-14 z4"
                                        required>
                                </div>
                            </div>
                        </div> <!-- <div class="row crdbody"> _-->


                        <div class="row crdbody">

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">Departamento</span>
                                    <select id="depargrupo" class="form-select" onchange="municipio('#munigrupo', this.value)">
                                        <option selected>...</option>
                                        <?php
                                        foreach ($departamentosGuatemala as $departamento) {
                                            $nombre = $departamento["nombre"];
                                            $codigo_departa = $departamento["id"];

                                            echo '<option value="' . $codigo_departa . '">' . $nombre . '</option>';
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="input-group">
                                    <span class="input-group-text">Municipio</span>
                                    <select id="munigrupo" class="form-select">
                                        <option selected>...</option>
                                    </select>
                                </div>
                            </div>
                        </div>


                </div>
                </form>
                <div class="col-12">
                    <button type="button" onclick="opciones(1)" id="ACargo" class="btn btn-outline-primary"
                        title="Asignar cargo" data-bs-toggle="modal" data-bs-target="#asignarcargo">
                        <i class="fa-solid fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger" id="EliminaGrupo" type="submit" onclick="anulargrp();">
                        <i class="fa-solid fa-trash"></i> Eliminar </button>

                    <button class="btn btn-warning" id="msjCreditosPen" type="submit" onclick="msjCreditosPen();">
                        <i class="fa-solid fa-lock"></i> </button>
                    <input type="number" value="0" id="contador" disabled hidden>
                    <input type="text" id="idUsuario" value="<?php echo $codusu ?>" disabled hidden>

                </div>

            </div>
        </div>
        </div>

        <?php

        include_once "../../src/cris_modales/mdls_GruposClientes.php";
        //include_once "../../src/cris_modales/mdls_AsignarCargo.php"; 
        ?>
        <!-- fin container 2-->
        <!-- ----------- inicio container 1---------------- -->
        <script>
            $(document).ready(function() {
                $('#EliminaGrupo').hide();
                $('#msjCreditosPen').hide();
            });
        </script>

    <?php
        break;
}


?>