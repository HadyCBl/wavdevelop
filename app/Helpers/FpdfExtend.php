<?php

namespace Micro\Helpers;

use Fpdf\Fpdf;

class FpdfExtend extends Fpdf
{

    function CellFit($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '', $scale = false, $force = true)
    {
        // Verificar si el texto está vacío o es solo espacios en blanco
        if (empty(trim($txt))) {
            // Si el texto está vacío, simplemente dibujar la celda sin ajustes
            $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
            return;
        }

        // Get string width
        $str_width = $this->GetStringWidth($txt);

        // Calculate ratio to fit cell
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        
        // Prevenir división por cero
        if ($str_width <= 0) {
            $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);
            return;
        }

        $ratio = ($w - $this->cMargin * 2) / $str_width;

        $fit = ($ratio < 1 || ($ratio > 1 && $force));
        if ($fit) {
            if ($scale) {
                // Calculate horizontal scaling
                $horiz_scale = $ratio * 100.0;
                // Set horizontal scaling
                $this->_out(sprintf('BT %.2F Tz ET', $horiz_scale));
            } else {
                // Calculate character spacing in points
                $char_space = ($w - $this->cMargin * 2 - $str_width) / max(mb_strlen($txt) - 1, 1) * $this->k;
                // Set character spacing
                $this->_out(sprintf('BT %.2F Tc ET', $char_space));
            }
            // Override user alignment (since text will fill up cell)
            $align = '';
        }

        // Pass on to Cell method
        $this->Cell($w, $h, $txt, $border, $ln, $align, $fill, $link);

        // Reset character spacing/horizontal scaling
        if ($fit)
            $this->_out('BT ' . ($scale ? '100 Tz' : '0 Tc') . ' ET');
    }

    //Cell with horizontal scaling only if necessary
    function CellFitScale($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, true, false);
    }

    //Cell with horizontal scaling always
    function CellFitScaleForce($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, true, true);
    }

    //Cell with character spacing only if necessary
    function CellFitSpace($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, false, false);
    }

    //Cell with character spacing always
    function CellFitSpaceForce($w, $h = 0, $txt = '', $border = 0, $ln = 0, $align = '', $fill = false, $link = '')
    {
        //Same as calling CellFit directly
        $this->CellFit($w, $h, $txt, $border, $ln, $align, $fill, $link, false, true);
    }
    /**
     * Genera firmas en el documento PDF de forma automática
     * 
     * @param array $valores Array con los nombres para las firmas
     * @param string $font Fuente a utilizar (por defecto 'Courier')
     * @param int $fontSize Tamaño de fuente (por defecto 9)
     * @param int $lineWidth Ancho de la línea de firma (por defecto 51)
     * @param int $lineSpacing Espaciado vertical entre firmas (por defecto 15)
     * 
     * Ejemplo de uso:
     * $pdf->firmas(['Juan Pérez', 'María García', 'Carlos López']);
     */
    function firmas($valores, $font = 'Courier', $fontSize = 9, $lineWidth = 51, $lineSpacing = 15)
    {
        // Validar que el array no esté vacío
        if (empty($valores) || !is_array($valores)) {
            return;
        }

        $cantidad = count($valores);
        $ancho = $this->GetPageWidth();
        $this->SetFont($font, '', $fontSize);
        $this->ln(12);

        // Calcular espaciados según el ancho de página
        $espacio1 = ($ancho > 250) ? 30 : 10;
        $espacio2 = ($ancho > 250) ? 60 : 29;
        $espacio3 = ($ancho > 250) ? 120 : 70;

        $firmasPorFila = 3;
        
        for ($i = 0; $i < $cantidad; $i++) {
            $posicionEnFila = $i % $firmasPorFila;
            $firmasRestantes = $cantidad - $i;

            // Calcular espacio según posición y cantidad restante
            if ($posicionEnFila === 0) {
                if ($firmasRestantes >= 3) {
                    $espacio = $espacio1;
                } elseif ($firmasRestantes === 2) {
                    $espacio = $espacio2;
                } else {
                    $espacio = $espacio3;
                }
            } else {
                $espacio = $espacio1;
            }

            // Espacio antes de la firma
            $this->Cell($espacio, 6, '', '', 0);
            $x = $this->GetX();
            $y = $this->GetY();

            // Línea de firma con etiqueta "F."
            $this->Cell($lineWidth, 6, 'F. ', 'B', 0, 'L');

            // Posicionar para el nombre debajo de la línea
            $this->SetXY($x, $y + 6);
            
            // Convertir y mostrar el nombre
            $nombre = mb_convert_encoding($valores[$i], 'ISO-8859-1', 'UTF-8');
            $this->Cell($lineWidth, 6, $nombre, 0, 0, 'C');

            // Si es la tercera firma en la fila o es la última, hacer salto de línea
            if (($posicionEnFila === 2) || ($i === $cantidad - 1)) {
                $this->ln($lineSpacing);
            } else {
                // Volver a la posición Y original para la siguiente firma
                $this->SetXY($this->GetX(), $y);
            }
        }
    }
}
