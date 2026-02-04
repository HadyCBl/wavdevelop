<?php
$usuario = $_SESSION["id"];
?>
<!-- Modal -->
<div class="modal fade" id="staticBackdrop" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="staticBackdropLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="staticBackdropLabel">EDITAR RECIBO DE CRÉDITOS INDIVIDUALES </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="form">
                    <!-- AREA DE CONTROL -->
                    <input type="text" id="idR" disabled hidden>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label for="text" class="form-label">No. de Recibo</label>
                                <input type="text" class="form-control" id="recibo" aria-describedby="emailHelp" onkeyup="validarV(['#recibo'])">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label for="text" class="form-label">Fecha</label>
                                <input type="date" class="form-control" id="fecha" aria-describedby="emailHelp">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="mb-3">
                                <label for="exampleFormControlTextarea1" class="form-label">Concepto</label>
                                <textarea class="form-control" id="concepto" rows="3"></textarea>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer">
                <button type="button" id="btnClo" class="btn btn-outline-danger" data-bs-dismiss="modal"><i class="fa-solid fa-circle-xmark"></i> Cerrar</button>

                <button type="button" class="btn btn-outline-primary" onclick="if(validarV(['#recibo','#fecha']) == 0)return;  obtiene(['idR','recibo','fecha','concepto'],[],[],'actReciCreIndi', '0',['<?php echo $usuario ?>']); cerrarModal('#staticBackdrop')"><i class="fa-solid fa-file-pen"></i> Actualizar</button>

            </div>

            <script>
                function validarV(nameEle) {
                    total = nameEle.length;
                    for (var con = 0; con < total; con++) {
                        if ($(nameEle[con]).val() == "") {
                            Swal.fire({
                                icon: 'error',
                                title: '¡ERROR!',
                                text: 'No puede dejar campos vacíos'
                            });
                            $(nameEle[con]).addClass("is-invalid");
                            return 0;
                        } else {
                            $(nameEle[con]).removeClass("is-invalid");
                        }
                    }
                }
            </script>

        </div>
    </div>
</div>