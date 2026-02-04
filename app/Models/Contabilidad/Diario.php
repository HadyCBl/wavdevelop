<?php

namespace Micro\Models\Contabilidad;

use Micro\Models\BaseModel;
use Micro\Models\Scopes\StatusActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ScopedBy([StatusActiveScope::class])]
class Diario extends BaseModel
{
    protected $table = 'ctb_diario';

    protected $fillable = [
        'numcom',
        'id_ctb_tipopoliza',
        'id_tb_moneda',
        'numdoc',
        'glosa',
        'fecdoc',
        'feccnt',
        'cod_aux',
        'id_tb_usu',
        'karely',
        'id_agencia',
        'editable',
        'estado',
        'fecmod',
        'created_by'
    ];

    public function movimientos()
    {
        return $this->hasMany(\Micro\Models\Contabilidad\Movimiento::class, 'id_ctb_diario', 'id');
    }

    public function cheques()
    {
        return $this->hasMany(\Micro\Models\Bancos\Cheque::class, 'id_ctb_diario', 'id');
    }
}
