<?php

namespace Micro\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Clase helper para facilitar el trabajo con PhpSpreadsheet
 * Proporciona métodos compatibles con versiones futuras
 */
class ExcelHelper
{
    /**
     * Convierte un número de columna a letra (1=A, 27=AA, etc.)
     */
    public static function columnNumberToLetter(int $columnNumber): string
    {
        $letter = '';
        $columnNumber--;

        while ($columnNumber >= 0) {
            $letter = chr($columnNumber % 26 + 65) . $letter;
            $columnNumber = intval($columnNumber / 26) - 1;
        }

        return $letter;
    }

    /**
     * Establece el valor de una celda usando número de columna y fila
     * Reemplazo para setCellValueByColumnAndRow (deprecated)
     * 
     * @param Worksheet $sheet Hoja de trabajo
     * @param int $column Número de columna (1-based)
     * @param int $row Número de fila
     * @param mixed $value Valor a establecer
     * @return Worksheet
     */
    public static function setCellByColumnRow(Worksheet $sheet, int $column, int $row, $value): Worksheet
    {
        $cellCoordinate = self::columnNumberToLetter($column) . $row;
        return $sheet->setCellValue($cellCoordinate, $value);
    }

    /**
     * Establece el valor de una celda de forma explícita usando número de columna y fila
     * 
     * @param Worksheet $sheet Hoja de trabajo
     * @param int $column Número de columna (1-based)
     * @param int $row Número de fila
     * @param mixed $value Valor a establecer
     * @param string $dataType Tipo de dato
     * @return Worksheet
     */
    public static function setCellExplicitByColumnRow(
        Worksheet $sheet,
        int $column,
        int $row,
        $value,
        string $dataType = \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
    ): Worksheet {
        $cellCoordinate = self::columnNumberToLetter($column) . $row;
        return $sheet->setCellValueExplicit($cellCoordinate, $value, $dataType);
    }

    /**
     * Obtiene el valor de una celda usando número de columna y fila
     * 
     * @param Worksheet $sheet Hoja de trabajo
     * @param int $column Número de columna (1-based)
     * @param int $row Número de fila
     * @return mixed
     */
    public static function getCellByColumnRow(Worksheet $sheet, int $column, int $row)
    {
        $cellCoordinate = self::columnNumberToLetter($column) . $row;
        return $sheet->getCell($cellCoordinate)->getValue();
    }

    /**
     * Aplica estilo a una celda usando número de columna y fila
     * 
     * @param Worksheet $sheet Hoja de trabajo
     * @param int $column Número de columna (1-based)
     * @param int $row Número de fila
     * @return \PhpOffice\PhpSpreadsheet\Style\Style
     */
    public static function getStyleByColumnRow(Worksheet $sheet, int $column, int $row)
    {
        $cellCoordinate = self::columnNumberToLetter($column) . $row;
        return $sheet->getStyle($cellCoordinate);
    }

    /**
     * Contador estático para columnas (útil en bucles)
     * 
     * @param int|bool $reset Si es int, resetea al valor, si es false incrementa
     * @return int
     */
    public static function columnCounter($reset = false): int
    {
        static $counter = 0;
        $counter = ($reset === false) ? $counter + 1 : (int)$reset;
        return $counter;
    }

    /**
     * Establece múltiples celdas en una fila usando un array asociativo
     * 
     * @param Worksheet $sheet Hoja de trabajo
     * @param int $startColumn Columna inicial
     * @param int $row Número de fila
     * @param array $values Array de valores a establecer
     * @return int Número de la siguiente columna disponible
     */
    public static function setRowValues(Worksheet $sheet, int $startColumn, int $row, array $values): int
    {
        $currentColumn = $startColumn;
        foreach ($values as $value) {
            self::setCellByColumnRow($sheet, $currentColumn, $row, $value);
            $currentColumn++;
        }
        return $currentColumn;
    }
}