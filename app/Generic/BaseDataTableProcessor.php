<?php

namespace Micro\Generic;

use Micro\Generic\ServerSideDataTable;
use Micro\Helpers\Log;
use Exception;
use PDOException;

/**
 * Clase base para procesar DataTables con manejo de errores
 */
abstract class BaseDataTableProcessor
{
    protected ServerSideDataTable $datatable;

    public function __construct()
    {
        $this->datatable = new ServerSideDataTable();
    }

    /**
     * Método abstracto que debe implementar cada archivo
     */
    abstract protected function getBaseQuery(): string;
    abstract protected function getCountQuery(): string;
    abstract protected function getColumns(): array;
    abstract protected function getSearchable(): array;

    /**
     * Métodos opcionales con valores por defecto
     */
    protected function getWhereExtra(): string
    {
        return "1=1";
    }

    protected function getParams(): array
    {
        return [];
    }

    /**
     * Valida los parámetros antes de procesar
     * Soporta ambas versiones de DataTables
     */
    protected function validate(): void
    {
        // Detectar versión de DataTables
        $hasLegacyParam = isset($_GET['sEcho']) || isset($_POST['sEcho']);
        $hasModernParam = isset($_GET['draw']) || isset($_POST['draw']);

        if (!$hasLegacyParam && !$hasModernParam) {
            throw new Exception("Petición inválida de DataTables - falta parámetro draw o sEcho", 2102);
        }

        $columns = $this->getColumns();
        $searchable = $this->getSearchable();

        if (count($columns) !== count($searchable)) {
            throw new Exception("Columnas y searchable deben tener la misma longitud", 2102);
        }

        if (empty($this->getBaseQuery()) || empty($this->getCountQuery())) {
            throw new Exception("Las queries no pueden estar vacías", 2102);
        }
    }

    /**
     * Procesa la petición con manejo de errores
     */
    public function process(): void
    {
        try {
            $this->validate();

            $this->datatable->processQuery(
                $this->getBaseQuery(),
                $this->getCountQuery(),
                $this->getColumns(),
                $this->getSearchable(),
                $this->getParams(),
                $this->getWhereExtra()
            );
        } catch (PDOException $e) {
            $this->handleDatabaseError($e);
        } catch (Exception $e) {
            $this->handleGeneralError($e);
        }
    }

    /**
     * Maneja errores de base de datos
     */
    protected function handleDatabaseError(PDOException $e): void
    {
        Log::error("Error de BD en " . static::class, [
            'error' => $e->getMessage(),
            'code' => $e->getCode(),
            'trace' => $e->getTraceAsString()
        ]);

        $this->sendErrorResponse('Error al procesar solicitud de base de datos');
    }

    /**
     * Maneja errores generales
     */
    protected function handleGeneralError(Exception $e): void
    {
        Log::error("Error en " . static::class, [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);

        $message = $e->getCode() == 2102 ? $e->getMessage() : 'Ocurrió un error inesperado. General Error';
        $this->sendErrorResponse($message);
    }

    /**
     * Envía respuesta de error
     * Soporta ambos formatos
     */
    protected function sendErrorResponse(string $mensaje, string $detalle = ''): void
    {
        $isModern = isset($_GET['draw']) || isset($_POST['draw']);

        header('Content-Type: application/json; charset=utf-8');
        header('HTTP/1.1 500 Internal Server Error');

        if ($isModern) {
            $response = [
                'error' => $mensaje,
                'data' => [],
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'draw' => intval($_GET['draw'] ?? $_POST['draw'] ?? 0)
            ];
        } else {
            $response = [
                'error' => $mensaje,
                'aaData' => [],
                'iTotalRecords' => 0,
                'iTotalDisplayRecords' => 0,
                'sEcho' => intval($_GET['sEcho'] ?? 0)
            ];
        }

        // Detalles solo en desarrollo
        // if (defined('APP_ENV') && APP_ENV === 'development') {
        //     $response['detail'] = $detalle;
        // }

        echo json_encode($response);
        exit;
    }
}
