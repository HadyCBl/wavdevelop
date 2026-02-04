<?php

namespace Micro\Models;

class TipoPoliza
{
    private static $ctb_tipo_poliza = array(
        array("id" => 1, "descripcion" => "CREDITOS", "abr" => "cre"),
        array("id" => 2, "descripcion" => "AHORROS", "abr" => "aho"),
        array("id" => 3, "descripcion" => "APORTACIONES", "abr" => "apr"),
        array("id" => 4, "descripcion" => "CAJA CHICA", "abr" => "cch"),
        array("id" => 5, "descripcion" => "ACTIVO FIJO", "abr" => "afj"),
        array("id" => 6, "descripcion" => "PARTIDA DE DIARIO", "abr" => "pad"),
        array("id" => 7, "descripcion" => "CHEQUES", "abr" => "chq"),
        array("id" => 8, "descripcion" => "OTROS INGRESOS", "abr" => "oin"),
        array("id" => 9, "descripcion" => "PARTIDA DE APERTURA", "abr" => "pap"),
        array("id" => 10, "descripcion" => "DEPOSITOS A BANCOS", "abr" => "dpb"),
        array("id" => 11, "descripcion" => "NOTAS DE CREDITO", "abr" => "ntc"),
        array("id" => 12, "descripcion" => "NOTAS DE DEBITO", "abr" => "ntd"),
        array("id" => 13, "descripcion" => "PARTIDA DE CIERRE", "abr" => "pci"),
        array("id" => 14, "descripcion" => "TRANSFERENCIA", "abr" => "trf"),
        array("id" => 15, "descripcion" => "CUENTAS POR COBRAR", "abr" => "cc"),
        array("id" => 16, "descripcion" => "AUXILIO POSTUMO", "abr" => "aux"),
    );

    /**
     * Obtiene la descripción según el ID.
     */
    public static function getDescripcion($id)
    {
        foreach (self::$ctb_tipo_poliza as $poliza) {
            if ($poliza['id'] === $id) {
                return $poliza['descripcion'];
            }
        }
        return null; // Retorna null si no se encuentra el ID
    }

    /**
     * Obtiene la abreviatura según el ID.
     */
    public static function getAbreviatura($id)
    {
        foreach (self::$ctb_tipo_poliza as $poliza) {
            if ($poliza['id'] === $id) {
                return $poliza['abr'];
            }
        }
        return null; // Retorna null si no se encuentra el ID
    }

    /**
     * Obtiene todos los datos de la póliza según el ID.
     */
    public static function getPoliza($id)
    {
        foreach (self::$ctb_tipo_poliza as $poliza) {
            if ($poliza['id'] === $id) {
                return $poliza;
            }
        }
        return null; // Retorna null si no se encuentra el ID
    }

    /**
     * Obtiene todos los tipos de póliza.
     */
    public static function getTiposPoliza()
    {
        return self::$ctb_tipo_poliza;
    }

    public static function getPrefixForBancoIngreso($id)
    {
        $prefixes = [
            1 => 'DP',
            2 => 'DP',
            3 => 'DP',
            6 => 'NC',
            7 => 'CH',
            10 => 'DP',
            11 => 'NC',
            14 => 'NC',
            15 => 'DP',
            16 => 'DP'
        ];

        return $prefixes[$id] ?? null;
    }
    public static function getPrefixForBancoEgreso($id)
    {
        $prefixes = [
            1 => 'CH',
            2 => 'CH',
            3 => 'CH',
            6 => 'ND',
            7 => 'CH',
            8 => 'CH',
            12 => 'ND',
            14 => 'ND',
            15 => 'CH',
            16 => 'CH'
        ];

        return $prefixes[$id] ?? null;
    }
}
