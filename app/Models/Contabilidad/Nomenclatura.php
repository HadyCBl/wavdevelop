<?php

namespace Micro\Models\Contabilidad;

use Micro\Models\BaseModel;
use Micro\Models\Scopes\StatusActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy([StatusActiveScope::class])]
class Nomenclatura extends BaseModel
{
    protected $table = 'ctb_nomenclatura';

    protected $fillable = [
        'ccodcta',
        'cdescrip',
        'tipo',
        'estado',
        'categoria_flujo',
    ];

    public function cuentasBancos()
    {
        return $this->hasMany(\Micro\Models\Bancos\Cuenta::class, 'id_nomenclatura', 'id');
    }

    public function movimientos()
    {
        return $this->hasMany(\Micro\Models\Contabilidad\Movimiento::class, 'id_ctb_nomenclatura', 'id');
    }

    public function serviciosAuxilio()
    {
        return $this->hasMany(\Micro\Models\Seguros\Servicio::class, 'id_nomenclatura', 'id');
    }
}
