<div class="modal fade" id="modal_cancelar_credito" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog  modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h1 class="modal-title fs-5" id="staticBackdropLabel">Motivo de rechazo crédito</h1>
            </div>
            <div class="modal-body">
                <!-- COD APORTACION Y NOMBRE -->
                <div class="container contenedort">
                    <div class="row mb-2">
                        <div class="col-sm-6">
                            <!-- titulo -->
                            <span class="input-group-addon col-8">Código de crédito</span>
                            <div class="input-group">
                                <input type="text" class="form-control " id="credito" readonly>
                                <input type="text" class="form-control" id="id_hidden" hidden>
                            </div>
                        </div>
                        <div class="col-sm-6">
                            <span class="input-group-addon col-8">Nombre cliente</span>
                            <input type="text" class="form-control " id="nombre" readonly>
                        </div>
                    </div>
                </div>
                <!-- AGREGAR LA TABLA DE BENEFICIARIOS -->


                <div class="container contenedort">
                    <!--Aho_0_BeneAho Nacimiento, parentesco, porcentaje-->
                    <div class="row mb-3">
                        <div class="col-12">
                            <span class="input-group-addon col-8">Motivos de rechazo</span>
                            <select class="form-select  col-sm-12" id="rechazoid">
                                <option value="0" selected>Seleccione motivo de rechazo</option>
                                <?php
                                $consulta = mysqli_query($general, "SELECT * FROM `tb_rechazocreditos`");
                                while ($fila = mysqli_fetch_array($consulta)) {
                                    echo '<option value="' . $fila['id'] . '">' . $fila['descripcion'] . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="" type="button" class="btn btn-primary" onclick="grabar_cancelar_credito('#id_hidden', '0')">
                    <i class="fa fa-floppy-disk"></i> Guardar
                </button>
                <button type="button" class="btn btn-secondary" id="cancelar_ben" onclick=" cerrar_modal_cualquiera_con_valor('#modal_cancelar_credito', '#id_hidden',[`#credito`,`#nombre`])">Cancelar</button>
            </div>
        </div>
    </div>
</div>