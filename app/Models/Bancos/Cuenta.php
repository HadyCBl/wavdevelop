<?php

namespace Micro\Models\Bancos;

use Micro\Models\BaseModel;
use Micro\Models\Scopes\StatusActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy([StatusActiveScope::class])]
class Cuenta extends BaseModel
{
    protected $table = 'ctb_bancos';

    protected $fillable = [
        'id_banco',
        'numcuenta',
        'id_nomenclatura',
        'estado',
        'id_nomenclatura',
        'estado',
    ];

    public function banco()
    {
        return $this->belongsTo(Banco::class, 'id_banco', 'id');
    }

    public function nomenclatura()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\Nomenclatura::class, 'id_nomenclatura', 'id');
    }
}
