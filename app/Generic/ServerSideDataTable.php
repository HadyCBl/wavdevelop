<?php

namespace Micro\Generic;

use Micro\Helpers\Log;
use PDO;
use PDOException;
use Exception;

/**
 * Clase moderna para manejo de DataTables con procesamiento del lado del servidor
 * Soporta consultas SQL personalizadas sin necesidad de crear vistas
 * 
 * @version 2.0
 * @author MicroSystemPlus
 */
class ServerSideDataTable
{
    private ?PDO $db = null;
    private array $config;

    /**
     * Constructor
     * @param array|null $config Configuración de conexión personalizada (opcional)
     */
    public function __construct(?array $config = null)
    {
        if ($config === null) {
            // Usar configuración por defecto desde .env
            require_once(__DIR__ . '/../../vendor/autoload.php');
            $dotenv = \Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
            $dotenv->load();

            $this->config = [
                'host' => $_ENV['DDBB_HOST'],
                'database' => $_ENV['DDBB_NAME'],
                'user' => $_ENV['DDBB_USER'],
                'password' => $_ENV['DDBB_PASSWORD']
            ];
        } else {
            $this->config = $config;
        }

        $this->conectar();
    }

    /**
     * Establece la conexión a la base de datos
     */
    private function conectar(): void
    {
        try {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=utf8mb4',
                $this->config['host'],
                $this->config['database']
            );

            $this->db = new PDO($dsn, $this->config['user'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4'
            ]);
        } catch (PDOException $e) {
            error_log("Error de conexión DB: " . $e->getMessage());
            throw new Exception("Error al conectar con la base de datos");
        }
    }

    /**
     * Procesa una tabla o vista (método compatible con versión anterior)
     * 
     * @param string $table Nombre de la tabla o vista
     * @param string $indexColumn Columna índice para conteo
     * @param array $columns Columnas a mostrar
     * @param array $searchable Columnas buscables (1=sí, 0=no)
     * @param string $whereExtra Condición WHERE adicional
     * @return void
     */
    public function processTable(
        string $table,
        string $indexColumn,
        array $columns,
        array $searchable,
        string $whereExtra = '1=1'
    ): void {
        $baseQuery = "SELECT " . implode(', ', array_map(fn($col) => "`$col`", $columns)) . " FROM `$table`";
        $countQuery = "SELECT COUNT(`$indexColumn`) FROM `$table`";

        $this->processQuery($baseQuery, $countQuery, $columns, $searchable, [], $whereExtra);
    }

    /**
     * Procesa una consulta SQL personalizada
     * 
     * @param string $baseQuery Query SELECT base (sin WHERE, ORDER BY, LIMIT)
     * @param string $countQuery Query para contar registros totales
     * @param array $columns Nombres de columnas para ordenamiento y búsqueda
     * @param array $searchable Indica qué columnas son buscables (1=sí, 0=no)
     * @param array $params Parámetros para binding seguro
     * @param string $whereExtra Condición WHERE adicional
     * @return void
     */
    public function processQuery(
        string $baseQuery,
        string $countQuery,
        array $columns,
        array $searchable,
        array $params = [],
        string $whereExtra = '1=1'
    ): void {
        try {
            // Validaciones antes de procesar
            $this->validateInputs($baseQuery, $countQuery, $columns, $searchable);

            $request = $this->parseRequest();

            // Construir WHERE para búsqueda
            $whereSearch = $this->buildSearchWhere($columns, $searchable, $request);

            // Combinar condiciones WHERE
            $whereFinal = $this->combineWhereConditions($whereExtra, $whereSearch);

            // Construir ORDER BY
            $orderBy = $this->buildOrderBy($columns, $request);

            // Construir LIMIT
            $limit = $this->buildLimit($request);

            // Query final para datos
            $finalQuery = $this->buildFinalQuery($baseQuery, $whereFinal, $orderBy, $limit);

            // Ejecutar query de datos
            $data = $this->executeQuery($finalQuery, array_merge($params, $request['searchParams']));

            // Contar registros filtrados
            $filteredCount = $this->countFiltered($baseQuery, $whereFinal, array_merge($params, $request['searchParams']));

            // Contar registros totales
            $totalCount = $this->countTotal($countQuery, $whereExtra, $params);

            // Formatear y enviar respuesta
            $this->sendResponse($data, $totalCount, $filteredCount, $request['draw']);
        } catch (PDOException $e) {
            Log::error("PDO Error en ServerSideDataTable: " . $e->getMessage());
            $this->sendError("Error al procesar la petición de datos.");
        } catch (Exception $e) {
            Log::error("Error en ServerSideDataTable: " . $e->getMessage());
            $message = $e->getCode() == 2103 ? $e->getMessage() : "Hubo un problema al procesar los datos.";
            $this->sendError($message);
        }
    }

    /**
     * Parsea los parámetros de la petición DataTables
     * Soporta tanto versión legacy (sEcho) como moderna (draw)
     */
    private function parseRequest(): array
    {
        // Detectar si es versión moderna o legacy
        $isModern = isset($_GET['draw']) || isset($_POST['draw']);

        if ($isModern) {
            // Versión moderna de DataTables
            $request = [
                'draw' => isset($_GET['draw']) ? intval($_GET['draw']) : (isset($_POST['draw']) ? intval($_POST['draw']) : 1),
                'start' => isset($_GET['start']) ? intval($_GET['start']) : (isset($_POST['start']) ? intval($_POST['start']) : 0),
                'length' => isset($_GET['length']) ? intval($_GET['length']) : (isset($_POST['length']) ? intval($_POST['length']) : 10),
                'search' => '',
                'orderColumn' => 0,
                'orderDir' => 'ASC',
                'sortingCols' => 0,
                'searchParams' => []
            ];

            // Búsqueda global
            if (isset($_GET['search']['value'])) {
                $request['search'] = trim($_GET['search']['value']);
            } elseif (isset($_POST['search']['value'])) {
                $request['search'] = trim($_POST['search']['value']);
            }

            // Ordenamiento
            if (isset($_GET['order'][0]['column'])) {
                $request['orderColumn'] = intval($_GET['order'][0]['column']);
                $request['orderDir'] = strtoupper($_GET['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
                $request['sortingCols'] = count($_GET['order']);
            } elseif (isset($_POST['order'][0]['column'])) {
                $request['orderColumn'] = intval($_POST['order'][0]['column']);
                $request['orderDir'] = strtoupper($_POST['order'][0]['dir']) === 'DESC' ? 'DESC' : 'ASC';
                $request['sortingCols'] = count($_POST['order']);
            }
        } else {
            // Versión legacy (sEcho, iDisplayStart, etc.)
            $request = [
                'draw' => isset($_GET['sEcho']) ? intval($_GET['sEcho']) : 1,
                'start' => isset($_GET['iDisplayStart']) ? intval($_GET['iDisplayStart']) : 0,
                'length' => isset($_GET['iDisplayLength']) ? intval($_GET['iDisplayLength']) : 10,
                'search' => isset($_GET['sSearch']) ? trim($_GET['sSearch']) : '',
                'orderColumn' => isset($_GET['iSortCol_0']) ? intval($_GET['iSortCol_0']) : 0,
                'orderDir' => isset($_GET['sSortDir_0']) ? $_GET['sSortDir_0'] : 'ASC',
                'sortingCols' => isset($_GET['iSortingCols']) ? intval($_GET['iSortingCols']) : 0,
                'searchParams' => []
            ];
        }

        return $request;
    }

    /**
     * Construye la cláusula WHERE para búsqueda
     * Soporta ambas versiones de DataTables
     */
    private function buildSearchWhere(array $columns, array $searchable, array &$request): string
    {
        $conditions = [];

        // Búsqueda global - CORREGIDO: usar parámetro único por columna
        if (!empty($request['search'])) {
            $searchConditions = [];
            $searchValue = '%' . $request['search'] . '%';

            foreach ($columns as $i => $column) {
                if ($searchable[$i] == 1) {
                    $paramName = "global_search_$i";
                    $searchConditions[] = "`$column` LIKE :$paramName";
                    $request['searchParams'][$paramName] = $searchValue;
                }
            }

            if (!empty($searchConditions)) {
                $conditions[] = '(' . implode(' OR ', $searchConditions) . ')';
            }
        }

        // Búsqueda por columna individual (versión legacy)
        for ($i = 0; $i < count($columns); $i++) {
            if (isset($_GET['sSearch_' . $i]) && $_GET['sSearch_' . $i] != '' && $searchable[$i] == 1) {
                $paramName = "search_col_$i";
                $conditions[] = "`{$columns[$i]}` LIKE :$paramName";
                $request['searchParams'][$paramName] = '%' . $_GET['sSearch_' . $i] . '%';
            }
        }

        // Búsqueda por columna individual (versión moderna)
        if (isset($_GET['columns']) && is_array($_GET['columns'])) {
            foreach ($_GET['columns'] as $i => $column) {
                if (
                    isset($column['search']['value']) &&
                    $column['search']['value'] != '' &&
                    isset($searchable[$i]) &&
                    $searchable[$i] == 1
                ) {
                    // Evitar duplicados si ya se agregó en la búsqueda legacy
                    $paramName = "search_col_modern_$i";
                    if (!isset($request['searchParams']["search_col_$i"])) {
                        $conditions[] = "`{$columns[$i]}` LIKE :$paramName";
                        $request['searchParams'][$paramName] = '%' . $column['search']['value'] . '%';
                    }
                }
            }
        }

        return !empty($conditions) ? implode(' AND ', $conditions) : '';
    }

    /**
     * Combina condiciones WHERE
     */
    private function combineWhereConditions(string $whereExtra, string $whereSearch): string
    {
        $conditions = [];

        if (!empty($whereExtra) && $whereExtra !== '1=1') {
            $conditions[] = "($whereExtra)";
        }

        if (!empty($whereSearch)) {
            $conditions[] = "($whereSearch)";
        }

        return !empty($conditions) ? implode(' AND ', $conditions) : '1=1';
    }

    /**
     * Construye la cláusula ORDER BY
     */
    private function buildOrderBy(array $columns, array $request): string
    {
        if ($request['sortingCols'] > 0 && isset($columns[$request['orderColumn']])) {
            $orderDir = strtoupper($request['orderDir']) === 'DESC' ? 'DESC' : 'ASC';
            return "ORDER BY `{$columns[$request['orderColumn']]}` $orderDir";
        }
        return '';
    }

    /**
     * Construye la cláusula LIMIT
     */
    private function buildLimit(array $request): string
    {
        if ($request['length'] != -1) {
            return "LIMIT {$request['start']}, {$request['length']}";
        }
        return '';
    }

    /**
     * Construye la query final completa
     */
    private function buildFinalQuery(string $baseQuery, string $where, string $orderBy, string $limit): string
    {
        // Envolver en subquery para aplicar filtros
        return "SELECT SQL_CALC_FOUND_ROWS * FROM ($baseQuery) AS dt_subquery WHERE $where $orderBy $limit";
    }

    /**
     * Ejecuta una query con parámetros
     */
    private function executeQuery(string $query, array $params): array
    {
        // Log::info("Query a ejecutar", [
        //     'query' => $query,
        //     'params' => $params
        // ]);
        $stmt = $this->db->prepare($query);

        foreach ($params as $key => $value) {
            $paramKey = strpos($key, ':') === 0 ? $key : ":$key";
            $stmt->bindValue($paramKey, $value, PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Cuenta registros filtrados
     */
    private function countFiltered(string $baseQuery, string $where, array $params): int
    {
        $query = "SELECT COUNT(*) FROM ($baseQuery) AS dt_subquery WHERE $where";
        $stmt = $this->db->prepare($query);

        foreach ($params as $key => $value) {
            $paramKey = strpos($key, ':') === 0 ? $key : ":$key";
            $stmt->bindValue($paramKey, $value, PDO::PARAM_STR);
        }

        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Cuenta registros totales
     */
    private function countTotal(string $countQuery, string $whereExtra, array $params): int
    {
        $where = (!empty($whereExtra) && $whereExtra !== '1=1') ? "WHERE $whereExtra" : '';
        $query = str_replace('SELECT COUNT', 'SELECT COUNT', $countQuery) . " $where";

        $stmt = $this->db->prepare($query);

        foreach ($params as $key => $value) {
            $paramKey = strpos($key, ':') === 0 ? $key : ":$key";
            $stmt->bindValue($paramKey, $value);
        }

        $stmt->execute();
        return (int) $stmt->fetchColumn();
    }

    /**
     * Envía la respuesta JSON
     * Soporta ambos formatos de respuesta
     */
    private function sendResponse(array $data, int $totalRecords, int $filteredRecords, int $draw): void
    {
        // Detectar si es versión moderna
        $isModern = isset($_GET['draw']) || isset($_POST['draw']);

        if ($isModern) {
            // Formato moderno
            $response = [
                'draw' => $draw,
                'recordsTotal' => $totalRecords,
                'recordsFiltered' => $filteredRecords,
                // 'data' => array_map(fn($row) => array_values($row), $data)
                'data' => $data
            ];
        } else {
            // Formato legacy
            $response = [
                'sEcho' => $draw,
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $filteredRecords,
                // 'aaData' => array_map(fn($row) => array_values($row), $data)
                'aaData' => $data
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Pragma: no-cache');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        echo json_encode($response, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Envía respuesta de error
     * Soporta ambos formatos
     */
    private function sendError(string $message): void
    {
        $isModern = isset($_GET['draw']) || isset($_POST['draw']);

        header('Content-Type: application/json; charset=utf-8');

        if ($isModern) {
            echo json_encode([
                'error' => $message,
                'data' => []
            ]);
        } else {
            echo json_encode([
                'error' => $message,
                'aaData' => []
            ]);
        }
    }

    /**
     * Valida los parámetros de entrada
     */
    private function validateInputs(
        string $baseQuery,
        string $countQuery,
        array $columns,
        array $searchable
    ): void {
        if (empty(trim($baseQuery))) {
            throw new Exception("Base query no puede estar vacía", 2103);
        }

        if (empty(trim($countQuery))) {
            throw new Exception("Count query no puede estar vacía", 2103);
        }

        if (empty($columns)) {
            throw new Exception("Columns no puede estar vacío", 2103);
        }

        if (count($columns) !== count($searchable)) {
            throw new Exception("Columns y searchable deben tener la misma longitud", 2103);
        }
    }

    /**
     * Cierra la conexión
     */
    public function __destruct()
    {
        $this->db = null;
    }
}
