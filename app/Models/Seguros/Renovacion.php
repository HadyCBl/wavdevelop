<?php

namespace Micro\Models\Seguros;

use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Micro\Models\BaseModel;
use Micro\Models\Scopes\Seguros\RenovacionesNoEliminadasScope;

#[ScopedBy([RenovacionesNoEliminadasScope::class])]
class Renovacion extends BaseModel
{
    protected $table = 'aux_renovaciones';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_cuenta',
        'fecha',
        'fecha_inicio',
        'fecha_fin',
        'monto',
        'numero',
        'numdoc',
        'formaPago',
        'id_ctbbanco',
        'banco_numdoc',
        'banco_fecha',
        'estado',
        'created_at',
        'created_by',
        'updated_by',
        'updated_at'
    ];

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta', 'id');
    }

    public function getNextNumeroAttribute(): int
    {
        $maxNumero = self::where('id_cuenta', $this->id_cuenta)
            ->max('numero');

        return $maxNumero ? $maxNumero + 1 : 1;
    }
}
