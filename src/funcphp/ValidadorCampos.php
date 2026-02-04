<?php

namespace App\Functions;

use Exception;
use DateTime;

class ValidadorCampos
{
    private $datos;
    private $errores = [];
    private $nombresCampos = [];

    public function __construct($datos, $nombresCampos = [])
    {
        $this->datos = $datos;
        $this->nombresCampos = $nombresCampos;
    }

    private function getNombreCampo($campo)
    {
        return $this->nombresCampos[$campo] ?? $campo;
    }

    public function validar($campos)
    {
        foreach ($campos as $campo => $reglas) {
            if (is_string($reglas)) {
                // Si solo se pasa el nombre del campo, asumimos que es obligatorio
                $this->validarCampoObligatorio($reglas);
            } else {
                // Si se pasan reglas específicas
                foreach ($reglas as $regla) {
                    $this->aplicarRegla($campo, $regla);
                }
            }
        }

        return $this->errores;
    }

    private function validarCampoObligatorio($campo)
    {
        if (!isset($this->datos[$campo])) {
            $this->errores[] = [
                'campo' => $campo,
                'mensaje' => $this->generarMensajeError($campo, "NO ENVIADO")
            ];
        }
    }

    private function generarMensajeError($campo, $tipoError)
    {
        $nombreCampo = $this->getNombreCampo($campo);
        switch ($tipoError) {
            case 'NO ENVIADO':
                return "El campo de {$nombreCampo} es requerido ['{$campo}']";
            case 'ESTÁ VACÍO':
                return "El campo de {$nombreCampo} no puede estar vacío ['{$campo}']";
            case 'NO ES NUMÉRICO':
                return "El campo de {$nombreCampo} debe ser un número ['{$campo}']";
            case 'NO ES UNA FECHA VÁLIDA':
                return "El campo de {$nombreCampo} debe ser una fecha válida  ['{$campo}']";
            default:
                return "El campo {$nombreCampo} {$tipoError}";
        }
    }

    public function hayErrores()
    {
        return !empty($this->errores);
    }
    private function aplicarRegla($campo, $regla)
    {
        if (!isset($this->datos[$campo])) {
            $this->errores[] = [
                'campo' => $campo,
                'mensaje' => $this->generarMensajeError($campo, "NO ENVIADO")
            ];
            return;
        }

        $valor = $this->datos[$campo];

        switch ($regla) {
            case 'required':
                if ($valor === null || $valor === '') {
                    $this->errores[] = [
                        'campo' => $campo,
                        'mensaje' => $this->generarMensajeError($campo, "ESTÁ VACÍO")
                    ];
                }
                break;

            case 'numeric':
                if (!is_numeric($valor)) {
                    $this->errores[] = [
                        'campo' => $campo,
                        'mensaje' => $this->generarMensajeError($campo, "NO ES NUMÉRICO")
                    ];
                }
                break;

            case 'date':
                if (!$this->esFormatoFechaValido($valor)) {
                    $this->errores[] = [
                        'campo' => $campo,
                        'mensaje' => $this->generarMensajeError($campo, "NO ES UNA FECHA VÁLIDA")
                    ];
                }
                break;

            default:
                throw new Exception("Regla de validación '$regla' no implementada");
        }
    }

    private function esFormatoFechaValido($fecha)
    {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        return $d && $d->format('Y-m-d') === $fecha;
    }
}

// Uso en tu código:
// $validador = new ValidadorCampos($value, $key, $totalRows);
// $camposAValidar = [
//     'dfecven',
//     'dfecope',
//     'ctipope',
//     'monto'
// ];

// $errores = $validador->validar($camposAValidar);

// if ($validador->hayErrores()) {
//     $conterrors++;
//     foreach ($errores as $error) {
//         sendSSEMessage('progress', [
//             'row' => $key + 1,
//             'total' => $totalRows,
//             'message' => $error['mensaje']
//         ]);
//     }
//     continue;
// }
