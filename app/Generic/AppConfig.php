<?php

namespace Micro\Generic;

use App\DatabaseAdapter;
use App\Generic\CacheManager;
use Micro\Helpers\Log;
use Exception;

class AppConfig
{
    private ?DatabaseAdapter $db = null;
    private array $configuracionesCargadas = [];
    private CacheManager $cache;

    // Mapeo de nombres de configuración a sus id_config
    private const CONFIG_IDS = [
        'CAMPO_FECHA_CAJA' => 1,
        'DESGLOSAR_IVA' => 2,
        'PERMITIR_PAGOS_KP' => 3,
        'PERMITIR_PAGOS_INT' => 4,
        'LOGO_SISTEMA' => 5,
        'NOMBRE_SISTEMA' => 6,
        'LOGO_LOGIN' => 7,
        'PERMITIR_REPETIR_BOLETAS_POR_BANCOS' => 8,
        'HABILITAR_AHORROS_GARANTIA' => 9,
        'HABILITAR_APORTACIONES_GARANTIA' => 10,
        'PRECISION_CREDITOS' => 11,
        'MODE_PRECISION_CREDITOS' => 12,
        'CALCULO_INTERESES_AL_DIA_CAJA' => 13,
        'CAMPOS_PAGOS_CREDITOS' => 14,
        'CODIGO_POLIZA' => 15,
    ];

    // Descripciones para cada configuración
    private const CONFIG_DESCRIPTIONS = [
        1 => 'Campo de fecha A Tomar en cuenta para cuadre de caja, 1 fecha de sistema, 2 fecha de documento',
        2 => 'Desglosar el IVA en la partida de pagos de creditos, 1 si, 0 no (por defecto)',
        3 => 'Permitir pagos de capital mayores al saldo kp pendiente?',
        4 => 'Permitir pagos de intereses mayores al saldo de Interes pendiente?',
        5 => 'Logo sistema',
        6 => 'Nombre Sistema',
        7 => 'Logo Login',
        8 => 'Permite repetir números de boleta por cada banco, 1 si, 0 no',
        9 => 'Lista de tipos de ahorro que pueden usarse como garantía, separados por coma',
        10 => 'Lista de tipos de aportación que pueden usarse como garantía, separados por coma',
        11 => 'Precisión de créditos, número de decimales a mostrar en los cálculos',
        12 => 'Modo de precisión de créditos, PHP_ROUND_HALF_EVEN (redondea al par más cercano), PHP_ROUND_HALF_UP (redondea hacia arriba), PHP_ROUND_HALF_DOWN (redondea hacia abajo), PHP_ROUND_HALF_ODD (redondea al impar más cercano)',
        13 => 'Calculo de intereses al día en caja de creditos, mostrar? 1 si, 0 no',
        14 => 'Campos de pagos de créditos, separados por coma (disponibles: "ccodcta, codcli, dpi, nombre, ciclo, monto, saldo, diapago, analista, agencia")',
        15 => 'Formato de código de póliza contable',
    ];

    public function __construct()
    {
        $this->cache = new CacheManager('config_', 21600); // Cache por 6 horas
    }

    private function conectarDb(): void
    {
        if ($this->db === null) {
            try {
                $this->db = new DatabaseAdapter();
                $this->db->openConnection(1); // Conectar a la BD principal
            } catch (Exception $e) {
                Log::error("Error al conectar a la base de datos: " . $e->getMessage(), [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                ]);
                throw new Exception("Error al conectar con la base de datos para configuraciones: " . $e->getMessage());
            }
        }
    }

    /**
     * Obtiene el valor de una configuración específica por su id_config.
     * Carga desde la BD si aún no ha sido cargada.
     *
     * @param int $idConfig El ID de la configuración.
     * @return string|null El valor de la configuración o null si no se encuentra.
     * @throws Exception Si hay un error al obtener la configuración.
     */
    private function getValorConfig(int $idConfig): ?string
    {
        // Intentar obtener del caché primero
        $cacheKey = "config_{$idConfig}";
        $valor = $this->cache->get($cacheKey);

        $valor = null; // Forzar recarga desde BD para evitar problemas de caché obsoleto

        if ($valor !== null) {
            $this->configuracionesCargadas[$idConfig] = $valor;
            return $valor;
        }

        // Si no está en caché, verificar memoria local
        if (isset($this->configuracionesCargadas[$idConfig])) {
            return $this->configuracionesCargadas[$idConfig];
        }

        $this->conectarDb(); // Asegura que la conexión esté activa

        try {
            // Usar parámetros preparados para seguridad
            $resultado = $this->db->selectColumns("tb_configuraciones", ["valor"], "id_config = ?", [$idConfig]);
            $valor = (!empty($resultado) && isset($resultado[0]["valor"])) ? $resultado[0]["valor"] : null;
            // Guardar en memoria y caché
            $this->configuracionesCargadas[$idConfig] = $valor;
            $this->cache->set($cacheKey, $valor);
            return $valor;
        } catch (Exception $e) {
            Log::error("Error al obtener la configuración con ID {$idConfig}: " . $e->getMessage());
            throw new Exception("Error al obtener la configuración con ID {$idConfig}: " . $e->getMessage());
        }
    }

    // --- Métodos específicos para cada configuración ---

    public function getOptionCampoFechaCaja(): int
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['CAMPO_FECHA_CAJA']);

        return $valor !== null ? (int)$valor : 2;
    }

    public function desglosarIva(): bool
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['DESGLOSAR_IVA']);
        if ($valor === null) {
            return false; // Valor por defecto si no está configurado
        }
        return (int)$valor === 1;
    }

    public function validarSaldoKpXPagosKp(): bool
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['PERMITIR_PAGOS_KP']);
        if ($valor === null) {
            return false; // Valor por defecto si no está configurado
        }
        return (int)$valor === 1;
    }

    public function validarSaldoIntXPagosInt(): bool
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['PERMITIR_PAGOS_INT']);
        if ($valor === null) {
            return false; // Valor por defecto si no está configurado
        }
        return (int)$valor === 1;
    }

    public function getLogoSistema(): ?string
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['LOGO_SISTEMA']);
        return (!is_null($valor) && is_string($valor) && trim($valor) !== '') ? $valor : 'logomicro.png';
    }

    public function getNombreSistema(): ?string
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['NOMBRE_SISTEMA']);
        // Log::info("Nombre del sistema: " . $valor);
        return (!is_null($valor) && is_string($valor) && trim($valor) !== '') ? $valor : 'https://img.microsystemplus2.com/mguate.avif';
    }

    public function getLogoLogin(): ?string
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['LOGO_LOGIN']);
        return (!is_null($valor) && is_string($valor) && trim($valor) !== '') ? $valor : 'logomicro.png';
    }

    public function permitirRepetirBoletasPorBancos(): bool
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['PERMITIR_REPETIR_BOLETAS_POR_BANCOS']);
        if ($valor === null) {
            return true;
        }
        return (int)$valor === 1;
    }

    public function habilitarAhorrosGarantia(): ?string
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['HABILITAR_AHORROS_GARANTIA']);
        if ($valor === null || trim($valor) === '') {
            return "'pf'";
        }
        return $valor;
    }

    public function habilitarAportacionesGarantia(): ?string
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['HABILITAR_APORTACIONES_GARANTIA']);
        if ($valor === null || trim($valor) === '') {
            return "'-'";
        }
        return $valor;
    }

    public function getPrecisionCreditos(): int
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['PRECISION_CREDITOS']);
        return $valor !== null ? (int)$valor : 2; // Valor por defecto de 2 decimales
    }

    public function getModePrecisionCreditos(): int
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['MODE_PRECISION_CREDITOS']);

        $modos = [
            'PHP_ROUND_HALF_UP'   => PHP_ROUND_HALF_UP,
            'PHP_ROUND_HALF_DOWN' => PHP_ROUND_HALF_DOWN,
            'PHP_ROUND_HALF_EVEN' => PHP_ROUND_HALF_EVEN,
            'PHP_ROUND_HALF_ODD'  => PHP_ROUND_HALF_ODD,
        ];

        return isset($modos[$valor]) ? $modos[$valor] : PHP_ROUND_HALF_EVEN;
    }

    public function calcularInteresesAlDiaCaja(): bool
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['CALCULO_INTERESES_AL_DIA_CAJA']);
        if ($valor === null) {
            return false;
        }
        return $valor === '1';
    }

    public function getCamposPagosCreditos(): array
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['CAMPOS_PAGOS_CREDITOS']);
        if ($valor === null || trim($valor) === '') {
            return ["ccodcta", "codcli", "dpi", "nombre", "ciclo", "diapago", "monto", "saldo"];
        }
        return array_map('trim', explode(',', $valor));
    }

    public function getFormatoCodigoPoliza(): string
    {
        $valor = $this->getValorConfig(self::CONFIG_IDS['CODIGO_POLIZA']);
        if ($valor === null || trim($valor) === '') {
            return '1';
        }
        return $valor;
    }

    //
    public static function getFormatoCodigoPolizaEstatico(): string
    {
        $instance = new self();
        return $instance->getFormatoCodigoPoliza();
    }

    /**
     * Obtener todas las claves de configuración.
     * @return array<string>
     */

    public function getAllConfigurationKeys(): array
    {
        $result = [];
        foreach (self::CONFIG_IDS as $key => $id) {
            $result[] = [
                'name' => $key,
                'id' => $id,
                'description' => self::CONFIG_DESCRIPTIONS[$id] ?? 'Sin descripción'
            ];
        }
        return $result;
    }

    /**
     * Devuelve los valores por defecto de cada configuración.
     * @return array<string, mixed>
     */
    public function getDefaultValuesForConfigurations(): array
    {
        return [
            1  => 2,
            2  => '0',
            3  => '0',
            4  => '0',
            5  => 'logomicro.png',
            6  => 'Microsystemplus',
            7  => 'https://img.microsystemplus2.com/mguate.avif',
            8  => '1',
            9  => "'pf'",
            10 => "'-'",
            11 => 2,
            12 => 'PHP_ROUND_HALF_EVEN',
            13 => '0',
            14 => "ccodcta,codcli,dpi,nombre,ciclo,diapago,monto,saldo",
            15 => 1,
        ];
    }

    /**
     * Limpia el caché de configuraciones
     */
    public function clearCache(): bool
    {
        $this->configuracionesCargadas = [];
        return $this->cache->clear();
    }

    public function __destruct()
    {
        if ($this->db !== null) {
            $this->db->closeConnection();
        }
    }
}
