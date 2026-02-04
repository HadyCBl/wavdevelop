<?php
class ConsutlaSql
{
    public function g_insert($tabla, $datos){
        $campos = implode(', ', array_keys($datos));
        $marcadores = ':' . implode(', :', array_keys($datos));
        return "INSERT INTO " . $tabla . " ($campos) VALUES ($marcadores)";
    }

    public function g_update($name_tabla, $datos, $colClave) {
        // Inicializar un array para almacenar los pares campo = valor
        $setValues = array();
    
        // Construir los pares campo = valor para la consulta SQL
        foreach ($datos as $campo => $valor) {
            // Evitar la actualización del campo clave primaria
            if ($campo != $colClave) {
                // Agregar el par campo = valor al array setValues
                // Asegurarse de usar marcadores de posición para evitar inyección SQL
                $setValues[] = "$campo = :$campo";
            }
        }
    
        // Combinar los pares campo = valor con comas y agregarlos a la consulta SQL
        $setValuesString = implode(", ", $setValues);
    
        // Construir la consulta SQL de actualización
        $sql = "UPDATE $name_tabla SET $setValuesString WHERE $colClave = :$colClave";
    
        return $sql;
    }
    

}
