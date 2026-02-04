<?php
function generarBotonesDocumentos($estado, $codigoCuenta) {
    global $matrizDocumentos, $nombreDocumentos;
    
    $html = '';
    
    if (isset($matrizDocumentos[$estado])) {
        foreach ($matrizDocumentos[$estado] as $documento => $idReporte) {
            $html .= sprintf(
                '<button class="btn btn-outline-success" onclick="reportes([[],[],[],[\'%s\']], \'pdf\', \'%s\',0,1)">
                    <i class="fa-solid fa-file-pdf me-2"></i>%s
                </button> ',
                $codigoCuenta,
                $idReporte,
                $nombreDocumentos[$documento]
            );
        }
    }
    
    return $html;
}