<?php
function erroralert($mensaje, $type)
{
?>
    <br>
    <svg xmlns="http://www.w3.org/2000/svg" class="d-none">
        <symbol id="check-circle-fill" viewBox="0 0 16 16">
            <path d="M16 8A8 8 0 1 1 0 8a8 8 0 0 1 16 0zm-3.97-3.03a.75.75 0 0 0-1.08.022L7.477 9.417 5.384 7.323a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-.01-1.05z" />
        </symbol>
        <symbol id="info-fill" viewBox="0 0 16 16">
            <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm.93-9.412-1 4.705c-.07.34.029.533.304.533.194 0 .487-.07.686-.246l-.088.416c-.287.346-.92.598-1.465.598-.703 0-1.002-.422-.808-1.319l.738-3.468c.064-.293.006-.399-.287-.47l-.451-.081.082-.381 2.29-.287zM8 5.5a1 1 0 1 1 0-2 1 1 0 0 1 0 2z" />
        </symbol>
        <symbol id="exclamation-triangle-fill" viewBox="0 0 16 16">
            <path d="M8.982 1.566a1.13 1.13 0 0 0-1.96 0L.165 13.233c-.457.778.091 1.767.98 1.767h13.713c.889 0 1.438-.99.98-1.767L8.982 1.566zM8 5c.535 0 .954.462.9.995l-.35 3.507a.552.552 0 0 1-1.1 0L7.1 5.995A.905.905 0 0 1 8 5zm.002 6a1 1 0 1 1 0 2 1 1 0 0 1 0-2z" />
        </symbol>
    </svg>
    <div class="alert alert-<?php echo $type ?> d-flex align-items-center" role="alert">
        <svg class="bi flex-shrink-0 me-2" role="img" aria-label="Danger:">
            <use xlink:href="#exclamation-triangle-fill" />
        </svg>
        <div>
            <?php echo $mensaje ?>
        </div>
    </div>
<?php
}


function calcularSaldoInicial($idCuentaContable, $idCuentaBanco, $fecha_inicio, $fecha_fin, $database)
{
    $fechaini = strtotime($fecha_inicio);
    $anioini = date("Y", $fechaini);
    $mesini = date("m", $fechaini);

    /**
     * PRIMERA CONSULTA, VERIFICAR SI NO TIENE ESPECIFICADO UN SALDO EN EL MES DE LA FECHA INICIAL DEL REPORTE
     */

    $consultaSaldo = $database->selectColumns(
        'ctb_saldos_bancos',
        ['saldo_inicial'],
        'id_cuenta_banco=? AND mes=? AND anio=?',
        [$idCuentaBanco, (int)$mesini, (int)$anioini]
    );

    $saldoInicial = $consultaSaldo[0]['saldo_inicial'] ?? 0;
    if (empty($consultaSaldo)) {
        /**
         * Si no se encontró un saldo inicial, se consulta la partida de apertura del mes correspondiente.
         */

        $consultaSaldo = $database->getAllResults(
            "SELECT debe, haber 
                            FROM ctb_diario dia 
                            INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                            WHERE dia.estado=1 AND dia.id_ctb_tipopoliza=9 AND mov.id_ctb_nomenclatura=? AND fecdoc BETWEEN ? AND ?",
            [$idCuentaContable, $fecha_inicio, $fecha_fin]
        );
        if (!empty($consultaSaldo)) {
            $saldoInicial = array_sum(array_column($consultaSaldo, 'debe')) - array_sum(array_column($consultaSaldo, 'haber'));
        } else {

            /**
             * Si no se encontró un saldo inicial para la cuenta de bancos en el mes de la fecha inicial del reporte, calcularla
             * buscar si no hay saldos definidos anteriores, y a partir de ahi realizar el calculo
             */

            $consultaSaldo = $database->selectColumns(
                'ctb_saldos_bancos',
                ['saldo_inicial', 'mes'],
                'id_cuenta_banco=? AND mes<? AND anio=?',
                [$idCuentaBanco, (int)$mesini, (int)$anioini],
                'mes DESC'
            );
            if (!empty($consultaSaldo)) {

                $saldoDefinido = $consultaSaldo[0]['saldo_inicial'];

                $consultaSaldo = $database->getSingleResult(
                    "SELECT IFNULL(SUM(debe),0) AS debe, IFNULL(SUM(haber),0) AS haber 
                            FROM ctb_diario dia 
                            INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                            WHERE dia.estado=1 AND dia.id_ctb_tipopoliza!=9 AND mov.id_ctb_nomenclatura=? AND fecdoc>= ? AND fecdoc < ?",
                    [$idCuentaContable, $anioini . '-' . $consultaSaldo[0]['mes'] . '-01', $fecha_inicio]
                );

                if (!empty($consultaSaldo)) {
                    $saldoInicial = $saldoDefinido + ($consultaSaldo['debe'] - $consultaSaldo['haber']);
                } else {
                    $saldoInicial = $saldoDefinido;
                }
            } else {
                /**
                 * si no, buscar una partida de apertura para la cuenta, y sumar desde ahi
                 * 
                 */

                $consultaSaldoFecha = $database->getAllResults(
                    "SELECT fecdoc FROM ctb_diario dia 
                                            INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                                            WHERE dia.estado=1 AND dia.id_ctb_tipopoliza=9 
                                            AND mov.id_ctb_nomenclatura=? AND fecdoc>= ? AND fecdoc < ? ORDER BY fecdoc DESC limit 1",
                    [$idCuentaContable, $anioini . '-01-01', $fecha_inicio]
                );
                if (!empty($consultaSaldoFecha)) {
                    $consultaSaldo = $database->getSingleResult(
                        "SELECT IFNULL(SUM(debe),0) AS debe, IFNULL(SUM(haber),0) AS haber 
                                FROM ctb_diario dia 
                                INNER JOIN ctb_mov mov ON mov.id_ctb_diario=dia.id
                                WHERE dia.estado=1 AND mov.id_ctb_nomenclatura=? AND fecdoc>= ? AND fecdoc < ?",
                        [$idCuentaContable, $consultaSaldoFecha[0]['fecdoc'], $fecha_inicio]
                    );
                    if (!empty($consultaSaldo)) {
                        $saldoInicial = ($consultaSaldo['debe'] - $consultaSaldo['haber']);
                    }
                }
            }
        }
    }

    return $saldoInicial;
}
