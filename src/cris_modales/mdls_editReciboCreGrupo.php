<?php
$usuario = $_SESSION["id"];
?>

<!-- Modal -->
<div class="modal fade" id="modalCreReGrup" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">EDITAR RECIBO DE CRÉDITOS GRUPALES </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form">

                    <!-- AREA DE CONTROL -->
                    <input type="text" id="idGru" disabled hidden>
                    <input type="text" id="antRe" disabled hidden>
                    <input type="text" id="ciclo" disabled hidden>

                    <div class="alert alert-warning" role="alert">
                    <div class="row">
                       
                            <div class="col-lg-6 col-md-12">
                                <div class="mb-3">
                                    <label for="text" class="form-label">No. de Recibo</label>
                                    <input type="text" min="0" class="form-control" id="recibo" aria-describedby="emailHelp" onkeyup="validarV(['#recibo'])">
                                </div>
                            </div>

                            <div class="col-lg-6 col-md-12">
                                <div class="mb-3">
                                    <label for="text" class="form-label">Fecha</label>
                                    <input type="date" class="form-control" id="fecha" aria-describedby="emailHelp">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="alert alert-warning" role="alert">

                                <div class="row">
                                    <h3><label class="d-flex justify-content-center"><b>Información del grupo</b></label></h3>

                                    <div class="col-lg-6 mt-3">
                                        <h4><b>Nombre del grupo: </b><label id="nomGrupo"> - - - </label></h4>
                                    </div>

                                    <div class="col-lg-6 mt-3">
                                        <h4><b>Código de grupo: </b> <label id="codGrup"> - - - </label></h4>
                                    </div>

                                    <div class="col-lg-12 mt-2">
                                        <h5><b>Integrantes: </b> </h5>
                                    </div>

                                    <div class="col-lg-12 mt-2">
                                        <div id="integrantes"></div> <!-- Aqui se inyecta el codigo para obtener el concepto de cada cliente. -->
                                    </div>

                                </div>

                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnClo" class="btn btn-outline-danger" data-bs-dismiss="modal"><i class="fa-solid fa-circle-xmark"></i> Cerrar</button>

                <!-- <button type="button" id="btnAct" class="btn btn-outline-primary" onclick="obtiene(['idR','recibo','fecha','concepto', 'coGru'],[],[],'actReciCreGru', '0',['<?php echo $usuario ?>']); cerrarModal('#staticBackdrop')"><i class="fa-solid fa-file-pen"></i> Actualizar</button> -->
                <button type="button" id="btnAct" class="btn btn-outline-primary"><i class="fa-solid fa-file-pen"></i> Actualizar</button>
                <script>
                    function validarV(nameEle){
                        total = nameEle.length; 
                        for(var con = 0 ; con < total ; con++){
                            if($(nameEle[con]).val() == "" ){
                                Swal.fire({ icon: 'error', title: '¡ERROR!', text: 'No puede dejar campos vacíos' }); 
                                $(nameEle[con]).addClass("is-invalid");
                                return 0 ; 
                            }else{
                                $(nameEle[con]).removeClass("is-invalid");
                            }
                        }
                    }
                    $('#btnAct').click(function() {
                        if(validarV(['#recibo','#fecha'])==0)return ; 
                        var datos = capDataMul('concep', '#total');
                        //console.log(datos);
                        obtiene(['idGru', 'recibo', 'fecha', 'ciclo', 'antRe'], [], [], 'actReciCreGru', '0', ['<?php echo $usuario ?>', datos]);
                        cerrarModal('#modalCreReGrup');
                    })
                </script>
            </div>
        </div>
    </div>
</div>