<?php

namespace Micro\Models\Bancos;

use Micro\Models\BaseModel;
use Micro\Models\Bancos\Cuenta as BancoCuenta;
use Micro\Models\Scopes\StatusActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

class Cheque extends BaseModel
{
    protected $table = 'ctb_chq';

    protected $fillable = [
        'id_ctb_diario',
        'id_cuenta_banco',
        'numchq',
        'nomchq',
        'monchq',
        'modocheque',
        'emitido'
    ];

    public function diario()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\Diario::class, 'id_ctb_diario', 'id');
    }
    public function cuentaBanco()
    {
        return $this->belongsTo(BancoCuenta::class, 'id_cuenta_banco', 'id');
    }
}
