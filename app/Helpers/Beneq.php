<?php

namespace Micro\Helpers;

use Exception;
use Micro\Exceptions\SoftException;
use Micro\Generic\AppConfig;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Clase para metodos de ayuda específicos de Beneq
 * 
 * Esta clase proporciona funciones auxiliares específicas para Beneq.
 * @package Micro\Helpers
 */
class Beneq
{
    /**
     * Escapa un texto para evitar inyecciones XSS, devolviendo un valor por defecto si el texto está vacío o no está definido.
     * @param string $text El texto a escapar.
     * @param string $default El valor por defecto a devolver si el texto está vacío o no está definido.
     * @return string El texto escapado o el valor por defecto.
     */
    public static function karely(string $text, string $default = " "): string
    {
        return (!isset($text) || trim($text) === '') ? $default : htmlspecialchars($text);
    }

    /**
     * Calcula la diferencia en días entre dos fechas.
     * @param string $fec_ini La fecha inicial en formato 'Y-m-d'.
     * @param string $fec_fin La fecha final en formato 'Y-m-d'.
     * @return int La diferencia en días entre las dos fechas.
     */

    public static function days_diff($fec_ini, $fec_fin)
    {
        $dateDifference = abs(strtotime($fec_fin) - strtotime($fec_ini));
        $dias_diferencia = $dateDifference / (60 * 60 * 24);
        //$dias_diferencia = abs($dias_diferencia); //valor absoluto y quitar posible negativo
        $dias_diferencia = floor($dias_diferencia); //quito los decimales a los días de diferencia
        return $dias_diferencia;
    }

    /**
     * Calcula la altura necesaria para un MultiCell en FPDF.
     * 
     * IMPORTANTE: Este método usa la fuente y tamaño actuales del PDF.
     * Asegúrate de establecer la fuente correcta ANTES de llamar a este método.
     * 
     * @param object $pdf La instancia de FPDF.
     * @param float $w El ancho del MultiCell.
     * @param float $h La altura de una línea de texto.
     * @param string $txt El texto a colocar en el MultiCell.
     * @param float $padding Padding adicional (opcional, por defecto 0).
     * @return float La altura total necesaria para el MultiCell.
     */
    public static function getMultiCellHeight($pdf, $w, $h, $txt, $padding = 0)
    {
        if (empty($txt)) {
            return $h;
        }

        $nb = 0;

        // Dividir por saltos de línea explícitos
        $lines = explode("\n", $txt);

        foreach ($lines as $line) {
            // Si la línea está vacía, cuenta como 1 línea
            if (trim($line) === '') {
                $nb += 1;
                continue;
            }

            // Calcular cuántas líneas ocupará esta línea considerando el ancho
            $lineWidth = $pdf->GetStringWidth($line);
            $numLines = max(1, ceil($lineWidth / $w));
            $nb += $numLines;
        }

        // Retornar altura total + padding
        return ($nb * $h) + $padding;
    }

    /**
     * Calcula la altura de un MultiCell de forma más precisa usando el método interno de FPDF.
     * 
     * Este método es MÁS PRECISO porque usa el mismo algoritmo que MultiCell usa internamente.
     * 
     * @param object $pdf La instancia de FPDF.
     * @param float $w El ancho del MultiCell.
     * @param float $h La altura de una línea de texto.
     * @param string $txt El texto a colocar en el MultiCell.
     * @return float La altura total necesaria para el MultiCell.
     */
    public static function getMultiCellHeightPrecise($pdf, $w, $h, $txt)
    {
        if (empty($txt)) {
            return $h;
        }

        // Guardar la posición actual
        $x = $pdf->GetX();
        $y = $pdf->GetY();

        // Crear una página temporal para calcular la altura sin afectar el documento
        $pdf->AddPage();

        // Dibujar el MultiCell en la página temporal
        $pdf->MultiCell($w, $h, $txt);

        // Calcular la altura que ocupó
        $height = $pdf->GetY() - $y;

        // Eliminar la página temporal (volver a la página anterior)
        $pdf->deletePage($pdf->PageNo());

        // Restaurar posición
        $pdf->SetXY($x, $y);

        return $height;
    }

    /**
     * Verifica si un MultiCell cabe en el espacio disponible de la página actual.
     * 
     * @param object $pdf La instancia de FPDF.
     * @param float $w El ancho del MultiCell.
     * @param float $h La altura de una línea de texto.
     * @param string $txt El texto a verificar.
     * @param float $marginBottom Margen inferior de la página (por defecto 15).
     * @return bool True si cabe, False si necesita nueva página.
     */
    public static function multiCellFitsInPage($pdf, $w, $h, $txt, $marginBottom = 15)
    {
        $currentY = $pdf->GetY();
        $pageHeight = $pdf->GetPageHeight();
        $availableHeight = $pageHeight - $currentY - $marginBottom;

        $requiredHeight = self::getMultiCellHeight($pdf, $w, $h, $txt);

        return $requiredHeight <= $availableHeight;
    }

    /**
     * Metodo estatico para obtener el codigo de poliza siguiente
     * @param mixed $database
     * @param mixed $idUser
     * @param mixed $idagencia
     * @param mixed $fechaDocumento
     * @throws SoftException
     */
    public static function getNumcom($database, $idUser, $idagencia, $fechaDocumento = null)
    {
        try {
            if ($fechaDocumento === null) {
                $fechaDocumento = date('Y-m-d');
            }
            $opcion = AppConfig::getFormatoCodigoPolizaEstatico();

            if ($opcion == 2) {
                /**
                 * formato 01 26 01 000001
                 * agencia + año + mes + correlativo
                 */
                $result = $database->getAllResults("SELECT ctb_codigo_poliza_anio(?, ?) numcom", [$idagencia, $fechaDocumento]);
            } else {
                /**
                 * formato 12 01 000001
                 * mes + agencia + correlativo
                 */
                $result = $database->getAllResults("SELECT ctb_codigo_poliza(?) numcom", [$idUser]);
            }

            if (empty($result)) {
                throw new SoftException('Error al obtener el número de póliza');
            }
            return $result[0]['numcom'];
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            Log::errorWithCode("Error al obtener el número de póliza: " . $e->getMessage());
            throw new SoftException('Error al obtener el número de póliza');
        }
    }

    /**
     * Obtener el código de póliza siguiente usando Eloquent
     * @param int $idUser ID del usuario
     * @param int $idagencia ID de la agencia
     * @param string|null $fechaDocumento Fecha del documento (Y-m-d)
     * @return string Número de póliza generado
     * @throws SoftException
     */
    public static function getNumcomEloquent($idUser, $idagencia, $fechaDocumento = null)
    {
        try {
            if ($fechaDocumento === null) {
                $fechaDocumento = date('Y-m-d');
            }
            
            $opcion = AppConfig::getFormatoCodigoPolizaEstatico();

            if ($opcion == 2) {
                /**
                 * formato 01 26 01 000001
                 * agencia + año + mes + correlativo
                 */
                $result = DB::select('SELECT ctb_codigo_poliza_anio(?, ?) as numcom', [$idagencia, $fechaDocumento]);
            } else {
                /**
                 * formato 12 01 000001
                 * mes + agencia + correlativo
                 */
                $result = DB::select('SELECT ctb_codigo_poliza(?) as numcom', [$idUser]);
            }

            if (empty($result)) {
                throw new SoftException('Error al obtener el número de póliza');
            }
            
            return $result[0]->numcom;
        } catch (SoftException $se) {
            throw new SoftException($se->getMessage());
        } catch (Exception $e) {
            Log::errorWithCode("Error al obtener el número de póliza: " . $e->getMessage());
            throw new SoftException('Error al obtener el número de póliza');
        }
    }

    /**
     * Este metodo es legacy, usar Beneq::getNumcom o Beneq::getNumcomEloquent en su lugar, 
     * puesta solo para compatibilidad con funciones antiguas
     * @deprecated Usar getNumcom() con PDO o getNumcomEloquent() con Eloquent
     * @param mixed $userid
     * @param mixed $conexion
     * @param mixed $agenciaId
     * @param mixed $fechaDocumento
     * @throws Exception
     */
    public static function getNumcomLegacy($userid, $conexion, $agenciaId, $fechaDocumento = null)
    {
        if ($fechaDocumento === null) {
            $fechaDocumento = date('Y-m-d');
        }

        // Obtener la opción de formato de código de póliza
         $opcion = AppConfig::getFormatoCodigoPolizaEstatico();
        if ($opcion == 2) {
            $query = "SELECT ctb_codigo_poliza_anio(?, ?) AS numcom";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("is", $agenciaId, $fechaDocumento);
        } else {
            $query = "SELECT ctb_codigo_poliza(?) AS numcom";
            $stmt = $conexion->prepare($query);
            $stmt->bind_param("i", $userid);
        }

        if ($stmt === false) {
            throw new Exception("Error en la preparación de la consulta: " . $conexion->error);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("No se pudo obtener el número de póliza.");
        }

        $row = $result->fetch_assoc();
        return $row['numcom'];
    }
}
