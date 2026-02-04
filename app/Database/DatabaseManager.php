<?php

namespace Micro\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
// use Illuminate\Container\Container;
// use Illuminate\Events\Dispatcher;

/**
 * Gestor de conexiones a base de datos usando Eloquent ORM
 * Implementa Singleton para asegurar una única instancia
 */
class DatabaseManager
{
    /**
     * Instancia única del manager
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * Instancia de Capsule
     * @var Capsule
     */
    private Capsule $capsule;

    /**
     * Indica si ya se inicializó
     * @var bool
     */
    private bool $booted = false;

    /**
     * Constructor privado para Singleton
     */
    private function __construct()
    {
        $this->capsule = new Capsule;
    }

    /**
     * Obtener instancia única (Singleton)
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Configurar e inicializar Eloquent
     * @param array $config Configuración personalizada (opcional)
     * @return self
     */
    public function boot(array $config = []): self
    {
        if ($this->booted) {
            return $this;
        }

        // Configurar conexión principal
        $this->addDefaultConnection($config['default'] ?? []);

        // Configurar conexión general
        $this->addGeneralConnection($config['general'] ?? []);

        // Event dispatcher (opcional)
        // if ($config['events'] ?? true) {
        //     $this->capsule->setEventDispatcher(new Dispatcher(new Container));
        // }

        // Hacer accesible globalmente
        $this->capsule->setAsGlobal();

        // Iniciar Eloquent
        $this->capsule->bootEloquent();

        $this->booted = true;

        return $this;
    }

    /**
     * Agregar conexión principal
     * @param array $customConfig
     * @return void
     */
    private function addDefaultConnection(array $customConfig = []): void
    {
        $config = array_merge([
            'driver'    => 'mysql',
            'host'      => $_ENV['DDBB_HOST'] ?? 'localhost',
            'database'  => $_ENV['DDBB_NAME'] ?? '',
            'username'  => $_ENV['DDBB_USER'] ?? '',
            'password'  => $_ENV['DDBB_PASSWORD'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ], $customConfig);

        $this->capsule->addConnection($config, 'default');
    }

    /**
     * Agregar conexión general
     * @param array $customConfig
     * @return void
     */
    private function addGeneralConnection(array $customConfig = []): void
    {
        $config = array_merge([
            'driver'    => 'mysql',
            'host'      => $_ENV['DDBB_HOST'] ?? 'localhost',
            'database'  => $_ENV['DDBB_NAME_GENERAL'] ?? '',
            'username'  => $_ENV['DDBB_USER'] ?? '',
            'password'  => $_ENV['DDBB_PASSWORD'] ?? '',
            'charset'   => 'utf8mb4',
            'collation' => 'utf8mb4_general_ci',
            'prefix'    => '',
            'strict'    => true,
            'engine'    => null,
        ], $customConfig);

        $this->capsule->addConnection($config, 'general');
    }

    /**
     * Agregar una conexión personalizada
     * @param string $name Nombre de la conexión
     * @param array $config Configuración
     * @return self
     */
    public function addConnection(string $name, array $config): self
    {
        $this->capsule->addConnection($config, $name);
        return $this;
    }

    /**
     * Obtener instancia de Capsule
     * @return Capsule
     */
    public function getCapsule(): Capsule
    {
        return $this->capsule;
    }

    /**
     * Obtener una conexión específica
     * @param string|null $name
     * @return \Illuminate\Database\Connection
     */
    public function connection(?string $name = null)
    {
        return $this->capsule->getConnection($name);
    }

    /**
     * Verificar si está inicializado
     * @return bool
     */
    public function isBooted(): bool
    {
        return $this->booted;
    }

    /**
     * Prevenir clonación
     */
    private function __clone() {}

    /**
     * Prevenir deserialización
     */
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }
}
