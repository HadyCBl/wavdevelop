<?php

namespace Micro\Models\Contabilidad;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Micro\Models\BaseModel;
use Micro\Models\Scopes\StatusActiveScope;

#[ScopedBy([StatusActiveScope::class])]
class FuenteFondo extends BaseModel
{
    protected $table = 'ctb_fuente_fondos';

    protected $fillable = [
        'descripcion',
        'id_usuario',
        'estado',
        'dfecmod',
        'deleted_at'
    ];

    public function movimientos()
    {
        return $this->hasMany(\Micro\Models\Contabilidad\Movimiento::class, 'id_fuente_fondo', 'id');
    }
}
