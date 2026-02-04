<?php

namespace Micro\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modelo base para todos los modelos Eloquent
 * Extiende de este modelo para usar Eloquent ORM
 */
abstract class BaseModel extends Model
{
    /**
     * Conexión por defecto (se puede sobrescribir en modelos hijos)
     * @var string
     */
    protected $connection = 'default';

    /**
     * Deshabilitar timestamps automáticos por defecto
     * Puedes habilitarlos en modelos específicos si los necesitas
     * @var bool
     */
    public $timestamps = false;

    /**
     * Formato de fecha por defecto
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Cambiar a la conexión 'general'
     * @return $this
     */
    public function useGeneralConnection()
    {
        $this->connection = 'general';
        return $this;
    }

    /**
     * Cambiar a la conexión 'default'
     * @return $this
     */
    public function useDefaultConnection()
    {
        $this->connection = 'default';
        return $this;
    }
}
