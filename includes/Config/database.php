<?php

use Complex\Functions;

require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];
$db_name_general = $_ENV['DDBB_NAME_GENERAL'];
$type_host = $_ENV['BANDERA'];
$type_timezone = $_ENV['BANDERA_TIMEZONE'];
$key1 = $_ENV['MYKEYPASS'];

/**
 * Clase para manejar la conexión y las operaciones con la base de datos.
 */
class Database
{
    /**
     * @var string $host Host de la base de datos.
     * @var string $db_name Nombre de la base de datos principal.
     * @var string $db_name_general Nombre de la base de datos general.
     * @var string $username Nombre de usuario para la base de datos.
     * @var string $password Contraseña para la base de datos.
     * @var PDO|null $conn Conexión PDO.
     * @var bool $inTransaction Indica si hay una transacción en curso.
     */
    private $host;
    private $db_name;
    private $db_name_general;
    private $username;
    private $password;
    private $usernameGeneral;
    private $passwordGeneral;
    private $conn;
    private $inTransaction = false;

    /**
     * Constructor de la clase Database.
     *
     * @param string $host Host de la base de datos.
     * @param string $db_name Nombre de la base de datos principal.
     * @param string $username Nombre de usuario para la base de datos principal.
     * @param string $password Contraseña para la base de datos principal.
     * @param string $db_name_general Nombre de la base de datos general.
     * @param string|null $usernameGeneral Nombre de usuario opcional para la base de datos general.
     * @param string|null $passwordGeneral Contraseña opcional para la base de datos general.
     * @throws Exception Si alguno de los parámetros principales está vacío.
     */
    
    public function __construct($host, $db_name, $username, $password, $db_name_general, $usernameGeneral = null, $passwordGeneral = null)
    {
        $this->host = $host;
        $this->db_name = $db_name;
        $this->db_name_general = $db_name_general;
        $this->username = $username;
        $this->password = $password;
        $this->usernameGeneral = $usernameGeneral ?? $username;
        $this->passwordGeneral = $passwordGeneral ?? $password;
    }

    /**
     * Abre una conexión a la base de datos.
     *
     * @param int $option Opción para seleccionar la base de datos (1 para principal, otro valor para general).
     * @throws Exception Si hay un error al conectar a la base de datos.
     */

    public function openConnection($option = 1)
    {
        $dbnombre = ($option == 1) ? $this->db_name : $this->db_name_general;
        $dbUser = ($option == 1) ? $this->username : $this->usernameGeneral;
        $dbPassword = ($option == 1) ? $this->password : $this->passwordGeneral;
        $dsn = "mysql:host=" . $this->host . ";dbname=" . $dbnombre . ";charset=utf8";
        try {
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8" // Agregar esta línea
            ];
            $this->conn = new PDO($dsn, $dbUser, $dbPassword, $options);
            // $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            // echo 'conectado';
        } catch (PDOException $e) {
            throw new Exception("Error al conectar a la base de datos: " . $e->getMessage());
        }
    }
    /**
     * Cierra la conexión a la base de datos.
     */
    public function closeConnection()
    {
        $this->conn = null;
    }

    /**
     * Inicia una transacción.
     */
    public function beginTransaction()
    {
        if (!$this->inTransaction) {
            $this->conn->beginTransaction();
            $this->inTransaction = true;
        }
    }

    /**
     * Confirma una transacción.
     */
    public function commit()
    {
        if ($this->inTransaction) {
            $this->conn->commit();
            $this->inTransaction = false;
        }
    }

    /**
     * Revierte una transacción.
     */
    public function rollback()
    {
        if ($this->inTransaction) {
            $this->conn->rollback();
            $this->inTransaction = false;
        }
    }

    /**
     * Selecciona todos los registros de una tabla.
     *
     * @param string $table Nombre de la tabla.
     * @return array Todos los registros de la tabla.
     */
    public function selectAll($table)
    {
        $sql = "SELECT * FROM $table";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Selecciona un registro por su ID.
     *
     * @param string $table Nombre de la tabla.
     * @param mixed $id ID del registro.
     * @return array|false El registro encontrado o false si no se encontró.
     */
    public function selectById($table, $id, $columnid = "id")
    {
        $sql = "SELECT * FROM $table WHERE $columnid = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id);
        $rst =  $stmt->execute();
        if (!$rst) {
            return "Se presento un erro";
        }
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Selecciona registros de una tabla por una columna específica.
     *
     * @param string $table Nombre de la tabla.
     * @param string $claveCol Nombre de la columna.
     * @param mixed $dataBuscar Valor a buscar.
     * @return array|string Los registros encontrados o un mensaje de error.
     */
    public function selectDataID($table, $claveCol, $dataBuscar)
    {
        $sql = "SELECT * FROM $table WHERE $claveCol = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $dataBuscar);
        if ($stmt->execute()) {
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            return "Se presentó un error al ejecutar la consulta.";
        }
    }

    /**
     * Selecciona registros de una tabla con condiciones específicas.
     *
     * @param string $selectFrom Parte de la consulta SELECT.
     * @param string $namTable Nombre de la tabla.
     * @param array $marcadores Marcadores de las columnas.
     * @param array $variablesClaves Valores de las columnas.
     * @return array|string El registro encontrado o un mensaje de error.
     */
    public function selectAtributos($selectFrom, $namTable, $marcadores, $variablesClaves)
    {
        $condiciones = array();
        $contador = 0;
        $flag = count($variablesClaves);

        // Construir las condiciones de la consulta
        foreach ($marcadores as $indice => $campo) {
            $condiciones[] = "$campo = :$campo";
        }

        $sql = $selectFrom . " " . $namTable . " WHERE " . implode(" AND ", $condiciones);
        //return $sql; 
        $stmt = $this->conn->prepare($sql);

        foreach ($marcadores as $campo) {
            $stmt->bindParam(":$campo", $variablesClaves[$contador]);
            $contador++;
        }

        if ($stmt->execute()) {
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            return "Se presentó un error al ejecutar la consulta.";
        }
    }

    /**
     * Ejecuta una consulta SELECT.
     *
     * @param string $query La consulta SQL.
     * @param array $params Parámetros de la consulta.
     * @return array Los resultados de la consulta.
     * @throws Exception Si hay un error al ejecutar la consulta.
     */
    public function selectNom($query, $params = [])
    {
        try {
            $statement = $this->conn->prepare($query);
            $statement->execute($params);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al ejecutar la consulta: " . $e->getMessage());
        }
    }

    /**
     * Ejecuta una consulta SELECT con opciones.
     *
     * @param string $query La consulta SQL.
     * @param array $params Parámetros de la consulta.
     * @param int $op Opción de retorno (0: columna, 1: fila, 2: tabla).
     * @return mixed El resultado de la consulta según la opción.
     * @throws Exception Si hay un error al ejecutar la consulta.
     */
    public function selectEspecial($query, $params = [], $op = 0)
    {
        try {
            $statement = $this->conn->prepare($query);
            $statement->execute($params);
            if ($op == 0) { //Retorna solo un dato
                return $statement->fetchColumn();
            } else if ($op == 1) { //Retorna uan fila
                return $statement->fetch(PDO::FETCH_ASSOC);
            } else if ($op == 2) { //Retorna una tabla
                return $statement->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            throw new Exception("Error al ejecutar la consulta: " . $e->getMessage());
        }
    }

    /**
     * Selecciona columnas específicas de una tabla.
     *
     * @param string $table Nombre de la tabla.
     * @param array $columns Columnas a seleccionar.
     * @param string $condition Condición de la consulta.
     * @param array $params Parámetros de la consulta.
     * @return array Los registros seleccionados.
     */
    public function selectColumns($table, $columns = ['*'], $condition = '', $params = [], $orderBy = '')
    {
        $selectedColumns = implode(',', $columns);
        $query = "SELECT $selectedColumns FROM $table";
        if (!empty($condition)) {
            $query .= " WHERE $condition";
        }
        if (!empty($orderBy)) {
            $query .= " ORDER BY $orderBy";
        }
        $statement = $this->executeQuery($query, $params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ejecuta una consulta SQL.
     *
     * @param string $query La consulta SQL.
     * @param array $params Parámetros de la consulta.
     * @return PDOStatement El resultado de la consulta.
     * @throws Exception Si hay un error al ejecutar la consulta.
     */
    public function executeQuery($query, $params = [])
    {
        try {
            $statement = $this->conn->prepare($query);
            $statement->execute($params);
            return $statement;
        } catch (PDOException $e) {
            throw new Exception("Error al ejecutar la consulta aki: " . $e->getMessage());
        }
    }

    /**
     * Obtiene todos los resultados de una consulta SQL.
     *
     * @param string $query La consulta SQL.
     * @param array $params Parámetros de la consulta.
     * @return array Los resultados de la consulta.
     * @throws Exception Si hay un error al obtener los resultados.
     */
    public function getAllResults($query, $params = [])
    {
        try {
            $statement = $this->executeQuery($query, $params);
            return $statement->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new Exception("Error al obtener todos los resultados: " . $e->getMessage());
        }
    }

    /**
     * Obtiene un solo resultado de una consulta SQL.
     *
     * @param string $query La consulta SQL.
     * @param array $params Parámetros de la consulta.
     * @return array El resultado de la consulta.
     */
    public function getSingleResult($query, $params = [])
    {
        $statement = $this->executeQuery($query, $params);
        return $statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Inserta un registro en una tabla.
     *
     * @param string $table Nombre de la tabla.
     * @param array $data Datos a insertar.
     * @return string El ID del registro insertado.
     * @throws Exception Si hay un error al insertar datos.
     */
    public function insert($table, $data)
    {
        try {
            // Decodificar todos los valores del array $data
            // $data = $this->utf8DecodeArray($data);

            $keys = implode(',', array_map(function ($key) {
                return "`" . str_replace("`", "``", $key) . "`";
            }, array_keys($data)));

            $values = implode(',', array_fill(0, count($data), '?'));
            $query = "INSERT INTO `$table` ($keys) VALUES ($values)";
            $params = array_values($data);

            $this->executeQuery($query, $params);
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Error al insertar datos en la tabla $table: " . $e->getMessage());
        }
    }

    /**
     * Actualiza un registro en una tabla.
     *
     * @param string $table Nombre de la tabla.
     * @param array $data Datos a actualizar.
     * @param string $condition Condición para identificar el registro.
     * @param array $params Parámetros adicionales de la consulta.
     */
    public function update($table, $data, $condition, $conditionParams = [])
    {
        try {
            // Decodificar todos los valores del array $data
            // $data = $this->utf8DecodeArray($data);

            $set_clause = implode('=?, ', array_keys($data)) . '=?';
            $query = "UPDATE `$table` SET $set_clause WHERE $condition";
            $params = array_merge(array_values($data), $conditionParams);

            $statement = $this->executeQuery($query, $params);
            return $statement->rowCount();
        } catch (PDOException $e) {
            throw new Exception("Error al actualizar datos en la tabla $table: " . $e->getMessage());
        }
    }

    /**
     * Elimina un registro de una tabla.
     *
     * @param string $table Nombre de la tabla.
     * @param string $condition Condición para identificar el registro.
     * @param array $params Parámetros de la consulta.
     */
    public function delete($table, $condition, $params = [])
    {
        $query = "DELETE FROM $table WHERE $condition";
        $this->executeQuery($query, $params);
    }

    /**
     * Ejecuta una consulta JOIN.
     *
     * @param array $tables Tablas involucradas en el JOIN.
     * @param array $columns Columnas a seleccionar.
     * @param array $joins Condiciones del JOIN.
     * @param string $condition Condición adicional.
     * @param array $params Parámetros de la consulta.
     * @return array Los resultados de la consulta.
     */
    public function joinQuery($tables, $columns = ['*'], $joins = [], $condition = '', $params = [])
    {
        // Construir la parte de las columnas seleccionadas
        $selectedColumns = implode(',', $columns);

        // Construir la parte de los joins
        $joinClause = '';
        foreach ($joins as $join) {
            $joinClause .= " {$join['type']} JOIN {$join['table']} ON {$join['condition']}";
        }

        // Construir la consulta SQL completa
        $query = "SELECT $selectedColumns FROM $tables[0] $joinClause";
        for ($i = 1; $i < count($tables); $i++) {
            $query .= " INNER JOIN $tables[$i] ON {$joins[$i - 1]['next_condition']}";
        }
        if (!empty($condition)) {
            $query .= " WHERE $condition";
        }

        // Ejecutar la consulta y retornar los resultados
        $statement = $this->executeQuery($query, $params);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }
}
