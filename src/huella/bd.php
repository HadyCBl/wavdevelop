<?php

if (basename($_SERVER['PHP_SELF']) == "bd.php")
    exit();
require_once(__DIR__ . '/../../vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$db_host = $_ENV['DDBB_HOST'];
$db_user = $_ENV['DDBB_USER'];
$db_password = $_ENV['DDBB_PASSWORD'];
$db_name = $_ENV['DDBB_NAME'];

class bd
{

    private $con;
    private $stm;
    private $rs;

    public function __construct($host, $db_name, $username, $password)
    {
        try {
            // $this->con = new PDO('mysql:host=localhost;dbname=app_biometrico;charset=utf8', "root", "",
            $this->con = new PDO(
                "mysql:host=" . $host . ";dbname=" . $db_name . ";charset=utf8",
                "$username",
                "$password",
                array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
            );
            $this->con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (Exception $e) {
            echo json_encode($e->getMessage());
            die();
        }
    }

    public function desconectar()
    {
        $this->rs = null;
        $this->stm = null;
        $this->con = null;
    }

    // public function findAll($query, $opc = "") {
    //     $this->stm = $this->con->prepare($query);
    //     $this->stm->execute();
    //     if ($opc) {
    //         $this->rs = $this->stm->fetchAll(PDO::FETCH_OBJ);
    //     } else {
    //         $this->rs = $this->stm->fetchAll(PDO::FETCH_ASSOC);
    //     }
    //     return $this->rs;
    // }
    public function findAll($query, $opc = "") {
        $stmt = $this->con->prepare($query);
        $stmt->execute();
        $result = $opc 
            ? $stmt->fetchAll(PDO::FETCH_OBJ) 
            : $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor(); // ¡Esto libera la conexión!
        return $result;
    }


    public function exec($query)
    {
        $this->stm = $this->con->prepare($query);
        $this->stm->execute();
        return $this->stm->rowCount();
    }
}
