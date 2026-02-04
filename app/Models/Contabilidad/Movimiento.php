<?php

namespace Micro\Models\Contabilidad;

use Micro\Models\BaseModel;
use Micro\Models\Scopes\StatusActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

class Movimiento extends BaseModel
{
    protected $table = 'ctb_mov';

    protected $fillable = [
        'id_ctb_diario',
        'id_fuente_fondo',
        'id_ctb_nomenclatura',
        'debe',
        'haber',
    ];

    public function diario()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\Diario::class, 'id_ctb_diario', 'id');
    }

    public function fuenteFondo()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\FuenteFondo::class, 'id_fuente_fondo', 'id');
    }

    public function nomenclatura()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\Nomenclatura::class, 'id_ctb_nomenclatura', 'id');
    }
}
