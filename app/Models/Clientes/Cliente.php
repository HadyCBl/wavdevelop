<?php

namespace Micro\Models\Clientes;

use Micro\Models\BaseModel;
use Micro\Models\Seguros\Cuenta;

class Cliente extends BaseModel
{
    protected $table = 'tb_cliente';
    protected $primaryKey = 'idcod_cliente';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'short_name',
        'no_identifica',
        'date_birth',
    ];

    public function cuentas_seguro()
    {
        return $this->hasMany(Cuenta::class, 'id_cliente', 'idcod_cliente');
    }
}
