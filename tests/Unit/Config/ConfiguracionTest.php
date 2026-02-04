<?php
use PHPUnit\Framework\TestCase;

class ConfiguracionTest extends TestCase
{
    public function testConfiguracionCargaCorrectamente()
    {
        $configuracion = include 'ruta/a/tu/configuracion.php';
        $this->assertNotEmpty($configuracion);
    }

    public function testConfiguracionValoresCorrectos()
    {
        $configuracion = include 'ruta/a/tu/configuracion.php';
        $this->assertEquals('valorEsperado', $configuracion['clave']);
    }
}