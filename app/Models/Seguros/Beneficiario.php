<?php

namespace Micro\Models\Seguros;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Micro\Models\BaseModel;
use Micro\Models\Parentesco;

class Beneficiario extends Pivot
{
    protected $table = 'aux_beneficiarios';
    
    public $timestamps = false;
    protected $fillable = [
        'id_cuenta',
        'id_beneficiario',
        'parentesco',
        'porcentaje',
        'created_at',
        'created_by',
        'updated_at',
        'updated_by',
    ];

    public function parentesco()
    {
        return $this->belongsTo(Parentesco::class, 'parentesco', 'id');
    }

    public function cuenta_seguro()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta', 'id');
    }
}
