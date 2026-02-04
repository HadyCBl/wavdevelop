<?php

namespace App\Utils;

use App\DatabaseAdapter;
use Micro\Helpers\Log;
use Exception;

class SystemUpdate
{
    private ?DatabaseAdapter $db = null;
    protected array $tareas = [];

    protected $id_cliente = null;

    public function __construct()
    {
        $this->conectarDb();
        $this->crearTablaSiNoExiste();
        $this->id_cliente = $_ENV['ID_CLIENTE'] ?? null;
    }

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(1); // tb_agencia está en la BD principal
            } catch (Exception $e) {
                Log::error("Error al conectar BD: " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
                throw new Exception("Error crítico al conectar BD.", 0, $e);
            }
        }
    }

    // Registrar y ejecutar una tarea automáticamente si no ha sido ejecutada
    public function agregarTarea(string $nombre, string $query): void
    {
        $this->conectarDb();
        $ejecutadas = $this->obtenerTareasEjecutadas();
        if (in_array($nombre, $ejecutadas)) {
            return;
        }
        try {
            $this->db->executeQuery($query);
            date_default_timezone_set('America/Guatemala');
            $datos = [
                'nombre' => $nombre,
                'ejecutado_en' => date('Y-m-d H:i:s')
            ];
            $this->db->beginTransaction();

            $this->db->insert('cambios_pendientes', $datos);

            $this->db->commit();

            Log::info("Tarea '$nombre' ejecutada correctamente.");
        } catch (Exception $e) {
            Log::error("Error al ejecutar tarea '$nombre': " . $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
            $this->db->rollback();
        }
    }

    // Verifica y crea la tabla de control si no existe
    protected function crearTablaSiNoExiste(): void
    {
        // $this->conectarDb();
        // $sql = "
        //     CREATE TABLE IF NOT EXISTS cambios_pendientes (
        //         id INT AUTO_INCREMENT PRIMARY KEY,
        //         nombre VARCHAR(255) NOT NULL UNIQUE,
        //         ejecutado_en DATETIME DEFAULT CURRENT_TIMESTAMP
        //     );
        // ";
        // $this->db->executeQuery($sql);
    }

    // Obtener las tareas que ya se ejecutaron
    protected function obtenerTareasEjecutadas(): array
    {
        $this->conectarDb();
        $stmt = $this->db->getAllResults("SELECT nombre FROM cambios_pendientes");
        return array_column($stmt, 'nombre');
    }

    public function tasksPending()
    {

        $this->agregarTarea(
            'update_muni_nacio_clientes',
            "UPDATE tb_cliente cli 
                INNER JOIN tb_municipios muni ON muni.codigo = cli.muni_nacio AND muni.id_departamento <= 22
                SET cli.id_muni_nacio = muni.id
                WHERE cli.muni_nacio IS NOT NULL;"
        );
        $this->agregarTarea(
            'update_muni_extiende_clientes',
            "UPDATE tb_cliente cli 
                INNER JOIN tb_municipios muni ON muni.codigo = cli.muni_extiende AND muni.id_departamento <= 22
                SET cli.id_muni_extiende = muni.id
                WHERE cli.muni_extiende IS NOT NULL;"
        );
        $this->agregarTarea(
            'update_muni_reside_clientes',
            "UPDATE tb_cliente cli 
                INNER JOIN tb_municipios muni ON muni.codigo = cli.muni_reside AND muni.id_departamento <= 22
                SET cli.id_muni_reside = muni.id
                WHERE cli.muni_reside IS NOT NULL;"
        );

        $this->agregarTarea(
            'update_pais_extiende_clientes',
            "UPDATE tb_cliente SET pais_extiende = '4' WHERE pais_extiende='Guatemala';"
        );

        $this->agregarTarea(
            'update_fel_id_otr_pago_mov',
            "UPDATE otr_pago_mov mov
                    INNER JOIN cv_otros_movimientos fel 
                        ON fel.id_otro = mov.id_otr_pago
                    SET mov.id_fel = fel.id
                    WHERE mov.id_fel IS NULL;"
        );

        $this->agregarTarea(
            'update_fecha_actualizacion_clientes',
            "UPDATE tb_cliente
                    SET fecha_actualizacion =
                        CASE
                            WHEN fecha_mod IS NOT NULL
                                AND fecha_mod >= '1000-01-01'
                            THEN DATE(fecha_mod)

                            WHEN fecha_alta IS NOT NULL
                                AND fecha_alta >= '1000-01-01'
                            THEN DATE(fecha_alta)

                            ELSE fecha_actualizacion
                        END
                    WHERE fecha_actualizacion IS NULL
                    AND (
                            (fecha_mod IS NOT NULL AND fecha_mod >= '1000-01-01')
                        OR (fecha_alta IS NOT NULL AND fecha_alta >= '1000-01-01')
                    );"
        );

        /**
         * ACTUALIZACION DE DOCUMENTO LIBRO MAYOR SEGUN INSTITUCION
         */

        $queryUpdateDocumentoMayor = "UPDATE `tb_documentos` SET `nombre`='' WHERE  `id_reporte`=37;";
        if ($this->id_cliente == 35) {
            $queryUpdateDocumentoMayor = "UPDATE `tb_documentos` SET `nombre`='Micro\\Controllers\\Reportes\\Formatos\\Contabilidad\\Ammi\\LibroMayor' WHERE  `id_reporte`=37;";
        }
        if ($this->id_cliente == 43) {
            $queryUpdateDocumentoMayor = "UPDATE `tb_documentos` SET `nombre`='Micro\\Controllers\\Reportes\\Formatos\\Contabilidad\\Cope27\\LibroMayor' WHERE  `id_reporte`=37;";
        }

        $this->agregarTarea(
            'update_documento_libro_mayor',
            $queryUpdateDocumentoMayor
        );

        $this->agregarTarea(
            'asignar_permiso_ver_plazo_fijo_vencer',
            "INSERT INTO tb_autorizacion (id_usuario, id_restringido, estado)
                        SELECT 
                            u.id_usu,
                            48,
                            1
                        FROM tb_usuario u
                        WHERE u.puesto IN ('GER','CNT','CJG','CAJ','ADM','SEC')
                        AND u.estado = 1
                        AND NOT EXISTS (
                            SELECT 1
                            FROM tb_autorizacion a
                            WHERE a.id_usuario = u.id_usu
                                AND a.id_restringido = 48
                        );
                        "
        );

        // $this->agregarTarea(
        //     'modificar_view_depositos_bancos',
        //     "CREATE OR REPLACE VIEW `view_depositos_bancos` AS 
        //         select `dia`.`id` AS `id`,`dia`.`numcom` AS `numcom`,`dia`.`feccnt` AS `feccnt`,sum(`mov`.`debe`) AS `debe`,sum(`mov`.`haber`) AS `haber`,
        //         `dia`.`id_agencia` AS `id_agencia` 
        //         from ((`ctb_mov` `mov` join `ctb_diario` `dia` on(`mov`.`id_ctb_diario` = `dia`.`id`)) 
        //         join `tb_agencia` `ta` on(`dia`.`id_agencia` = `ta`.`id_agencia`)) 
        //         where `dia`.`estado` = 1 AND dia.id_ctb_tipopoliza IN (10,11,12,14) 
        //         group by `mov`.`id_ctb_diario` ;"
        // );


        // $this->agregarTarea(
        //     'modificar_vista_bancos',
        //     "CREATE OR REPLACE VIEW vista_bancos AS 
        //         SELECT dia.id,dia.numcom, dia.feccnt, SUM(debe) AS debe, SUM(haber) AS haber, 
        //             ch.monchq AS moncheque, ch.emitido AS estado, ch.numchq, dia.id_agencia
        //         FROM ctb_mov mov 
        //         INNER JOIN ctb_diario dia ON dia.id=mov.id_ctb_diario
        //         INNER JOIN ctb_chq ch ON dia.id=ch.id_ctb_diario
        //         WHERE dia.estado=1 GROUP BY mov.id_ctb_diario;"
        // );
    }
}
