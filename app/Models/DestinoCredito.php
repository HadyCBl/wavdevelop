<?php

namespace Micro\Models;

use Micro\Helpers\Log;
use Exception;

class DestinoCredito
{
    private array $cache = [];
    private array $destinosCredito;

    public function __construct()
    {

        $this->destinosCredito = [
            [
                'id' => 1,
                'descripcion' => 'Gastos de Operacion e Inversión',
                'cod_crediref' => 23,
            ],
            [
                'id' => 2,
                'descripcion' => 'Gastos de Operación',
                'cod_crediref' => 25,
            ],
            [
                'id' => 3,
                'descripcion' => 'Gastos Personales',
                'cod_crediref' => 24,
            ],
            [
                'id' => 4,
                'descripcion' => 'Capital de Trabajo',
                'cod_crediref' => 23,
            ],
            [
                'id' => 5,
                'descripcion' => 'Consumo',
                'cod_crediref' => 5,
            ],
            [
                'id' => 6,
                'descripcion' => 'Null',
                'cod_crediref' => 0,
            ],
            [
                'id' => 7,
                'descripcion' => 'Activo fijo',
                'cod_crediref' => 25,
            ],
            [
                'id' => 8,
                'descripcion' => 'Comercio',
                'cod_crediref' => 1,
            ],
            [
                'id' => 9,
                'descripcion' => 'Industria',
                'cod_crediref' => 2,
            ],
            [
                'id' => 10,
                'descripcion' => 'Educación',
                'cod_crediref' => 3,
            ],
            [
                'id' => 11,
                'descripcion' => 'Estudios',
                'cod_crediref' => 4,
            ],
            [
                'id' => 12,
                'descripcion' => 'Tecnología',
                'cod_crediref' => 6,
            ],
            [
                'id' => 13,
                'descripcion' => 'Agricultura',
                'cod_crediref' => 7,
            ],
            [
                'id' => 14,
                'descripcion' => 'Vivienda Nueva',
                'cod_crediref' => 8,
            ],
            [
                'id' => 15,
                'descripcion' => 'Vivienda Remodelación',
                'cod_crediref' => 9,
            ],
            [
                'id' => 16,
                'descripcion' => 'Unificación de Deuda',
                'cod_crediref' => 10,
            ],
            [
                'id' => 17,
                'descripcion' => 'Cancelación de Deuda',
                'cod_crediref' => 11,
            ],
            [
                'id' => 18,
                'descripcion' => 'Turismo Nacional',
                'cod_crediref' => 12,
            ],
            [
                'id' => 19,
                'descripcion' => 'Turismo Extranjero',
                'cod_crediref' => 13,
            ],
            [
                'id' => 20,
                'descripcion' => 'Automóviles',
                'cod_crediref' => 14,
            ],
            [
                'id' => 21,
                'descripcion' => 'Motocicletas',
                'cod_crediref' => 15,
            ],
            [
                'id' => 22,
                'descripcion' => 'Salud',
                'cod_crediref' => 16,
            ],
            [
                'id' => 23,
                'descripcion' => 'Deportes',
                'cod_crediref' => 17,
            ],
            [
                'id' => 24,
                'descripcion' => 'Pago de Tarjetas de Crédito',
                'cod_crediref' => 18,
            ],
            [
                'id' => 25,
                'descripcion' => 'Pecuario',
                'cod_crediref' => 19,
            ],
            [
                'id' => 26,
                'descripcion' => 'Forestal',
                'cod_crediref' => 20,
            ],
            [
                'id' => 27,
                'descripcion' => 'Artesanías',
                'cod_crediref' => 21,
            ],
            [
                'id' => 28,
                'descripcion' => 'Migración',
                'cod_crediref' => 22,
            ],
            [
                'id' => 29,
                'descripcion' => 'Otros',
                'cod_crediref' => 25,
            ],
            [
                'id' => 30,
                'descripcion' => 'Agropecuario',
                'cod_crediref' => 26,
            ],
            [
                'id' => 31,
                'descripcion' => 'Servicios',
                'cod_crediref' => 27,
            ],
            [
                'id' => 32,
                'descripcion' => 'Vivienda',
                'cod_crediref' => 28,
            ],
            [
                'id' => 33,
                'descripcion' => 'Menaje de casa (Utensilios de casa)',
                'cod_crediref' => 29,
            ],
            [
                'id' => 34,
                'descripcion' => 'Inmuebles',
                'cod_crediref' => 30,
            ],
            [
                'id' => 35,
                'descripcion' => 'Construcción de Vivienda',
                'cod_crediref' => 0,
            ],
            [
                'id' => 36,
                'descripcion' => 'Compra de terreno',
                'cod_crediref' => 0,
            ],
            [
                'id' => 37,
                'descripcion' => 'Siembra de Cultivos',
                'cod_crediref' => 0,
            ],
            [
                'id' => 38,
                'descripcion' => 'Capital de Negocio - Tienda',
                'cod_crediref' => 0,
            ],
            [
                'id' => 39,
                'descripcion' => 'Capital de Negocio - Panaderia',
                'cod_crediref' => 0,
            ],
            [
                'id' => 40,
                'descripcion' => 'Capital de Negocio - Carpinteria',
                'cod_crediref' => 0,
            ],
            [
                'id' => 41,
                'descripcion' => 'Capital de Negocio - Venta de Ropa',
                'cod_crediref' => 0,
            ],
            [
                'id' => 42,
                'descripcion' => 'Capital de Negocio - Zapateria',
                'cod_crediref' => 0,
            ],
            [
                'id' => 43,
                'descripcion' => 'Compra de Animales Domesticos',
                'cod_crediref' => 0,
            ],
            [
                'id' => 44,
                'descripcion' => 'Crianza de Animales Domesticos',
                'cod_crediref' => 0,
            ],
            [
                'id' => 45,
                'descripcion' => 'Compra de Ganados Vacunos',
                'cod_crediref' => 0,
            ],
            [
                'id' => 46,
                'descripcion' => 'Compra de Electrodomeseticos',
                'cod_crediref' => 0,
            ],
            [
                'id' => 47,
                'descripcion' => 'Compras de  ',
                'cod_crediref' => 0,
            ],
        ];
    }

    private function cargarDatos(int $id): array
    {
        if (!isset($this->cache[$id])) {
            $destinoData = array_filter($this->destinosCredito, function ($item) use ($id) {
                return $item['id'] === $id;
            });

            if (empty($destinoData)) {
                return [
                    'descripcion' => ' - ',
                    'cod_crediref' => ' - '
                ];
            }

            $this->cache[$id] = reset($destinoData);
        }

        return $this->cache[$id];
    }

    public function getDescripcion(int $id): ?string
    {
        return $this->cargarDatos($id)['descripcion'] ?? null;
    }

    public function getCodigoCrediref(int $id): ?int
    {
        $valor = $this->cargarDatos($id)['cod_crediref'] ?? null;
        return $valor !== null && $valor !== ' - ' ? (int)$valor : null;
    }

    public function getDatos(int $id): ?array
    {
        try {
            return $this->cargarDatos($id);
        } catch (Exception $e) {
            return null;
        }
    }

    public static function buscarPorCodigoCrediref(int $codigoCrediref): ?array
    {
        $instance = new self();
        $resultado = array_filter($instance->destinosCredito, function ($item) use ($codigoCrediref) {
            return $item['cod_crediref'] === $codigoCrediref;
        });

        return !empty($resultado) ? reset($resultado) : null;
    }

    public function getAllDestinos(): array
    {
        return $this->destinosCredito;
    }

    public static function getAll(): array
    {
        $instance = new self();
        return $instance->getAllDestinos();
    }
}

/**
 * Ejemplo de uso:
 */

// $destino = new DestinoCredito();
// echo $destino->getDescripcion(1); // Mostrará "Gastos de Operacion e Inversión"
// echo $destino->getCodigoCrediref(1); // Mostrará 23
// $datosCompletos = $destino->getDatos(1); // Retornará el array completo para el ID 1
// $datosPorCodigo = DestinoCredito::buscarPorCodigoCrediref(23); // Buscará por código crediref

// Forma 1: Usando el método de instancia
// $destino = new DestinoCredito();
// $todosLosDestinos = $destino->getAllDestinos();

// // Forma 2: Usando el método estático
// $todosLosDestinos = DestinoCredito::getAll();