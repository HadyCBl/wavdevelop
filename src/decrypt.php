<?php

use Micro\Helpers\Log;

header('Content-Type: application/json');
require_once(__DIR__ . '/../vendor/autoload.php');
Log::info("hola");

// Función para encriptar/desencriptar
function encriptar_desencriptar($action = 'encrypt', $string = false)
{
    $action = trim($action);
    $output = false;

    $myKey = 'Sotecpro1000@oW%c76+jb2';
    $myIV = 'XufeGOS5zeSPsNRCLo4fTg==';
    $encrypt_method = 'AES-256-CBC';

    $secret_key = hash('sha256', $myKey);
    $secret_iv = substr(hash('sha256', $myIV), 0, 16);

    if ($action && ($action == 'encrypt' || $action == 'decrypt') && $string) {
        $string = trim(strval($string));

        if ($action == 'encrypt') {
            $output = openssl_encrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
        }

        if ($action == 'decrypt') {
            $output = openssl_decrypt($string, $encrypt_method, $secret_key, 0, $secret_iv);
        }
    }
    return $output;
}

// Obtener datos
$data = json_decode(file_get_contents("php://input"), true);

// Procesar
if (isset($data['action']) && isset($data['value'])) {
    $action = $data['action'];
    $value = $data['value'];
    $result = encriptar_desencriptar($action, $value);
    echo json_encode($result); //retorna el resultado
} else {
    echo json_encode(null); //null
}
