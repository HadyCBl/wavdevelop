<?php

namespace Micro\Models\Seguros;

use Micro\Models\BaseModel;
use Micro\Traits\CustomSoftDeletes;
use Micro\Models\Bancos\Cuenta as CuentaBanco;

class Pago extends BaseModel
{
    use CustomSoftDeletes;
    protected $table = 'aux_auxilios_pagos';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_auxilio',
        'fecha',
        'monto',
        'numdoc',
        'concepto',
        'forma_pago',
        'id_ctbbanco',
        'banco_numdoc',
        'banco_fecha',
        // 'estado',
        'created_at',
        'created_by',
        'updated_by',
        'updated_at'
    ];

    public function auxilio()
    {
        return $this->belongsTo(Auxilio::class, 'id_auxilio', 'id');
    }

    public function cuenta_banco()
    {
        return $this->belongsTo(CuentaBanco::class, 'id_ctbbanco', 'id');
    }
}
