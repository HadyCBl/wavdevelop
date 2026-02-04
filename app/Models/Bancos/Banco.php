<?php

namespace Micro\Models\Bancos;

use Micro\Models\BaseModel;
use Micro\Models\Scopes\StatusActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy([StatusActiveScope::class])]
class Banco extends BaseModel
{
    protected $table = 'tb_bancos';

    protected $fillable = [
        'nombre',
        'estado',
        'abreviatura',
    ];

    public function cuentas()
    {
        return $this->hasMany(Cuenta::class, 'id_banco', 'id');
    }
}
