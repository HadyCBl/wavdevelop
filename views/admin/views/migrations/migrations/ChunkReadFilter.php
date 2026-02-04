<?php
class ChunkReadFilter implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
{
    private $startRow = 0;
    private $endRow = 0;

    // Configurar qué filas leer
    public function setRows($startRow, $chunkSize)
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize;
    }

    public function readCell($columnAddress, $row, $worksheetName = '')
    {
        // Solo leer filas dentro del rango especificado
        if ($row >= $this->startRow && $row < $this->endRow) {
            return true;
        }
        return false;
    }
}
