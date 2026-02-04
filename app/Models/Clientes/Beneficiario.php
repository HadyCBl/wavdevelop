<?php

namespace Micro\Models\Clientes;

use Micro\Traits\CustomSoftDeletes;
use Micro\Models\BaseModel;
use Micro\Models\Seguros\Beneficiario as SegurosBeneficiario;
use Micro\Models\Seguros\Cuenta;

class Beneficiario extends BaseModel
{
    use CustomSoftDeletes;
    protected $table = 'cli_beneficiarios';

    protected $fillable = [
        'nombres',
        'apellidos',
        'identificacion',
        // 'parentesco',
        // 'porcentaje',
        'telefono',
        'direccion',
        // 'estado',
        'created_at',
        'created_by',
        'updated_by',
        'updated_at',
        'deleted_at',
        'deleted_by',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    public function cuentas_seguros()
    {
        return $this->belongsToMany(
            Cuenta::class,
            'aux_beneficiarios',
            'id_beneficiario',
            'id_cuenta'
        )
            ->using(SegurosBeneficiario::class)
            ->withPivot(['parentesco', 'porcentaje']);
    }
}
