<?php

namespace Micro\Models\Seguros;

use Micro\Models\BaseModel;

class Documento extends BaseModel
{
    protected $table = 'aux_documentos';

    protected $fillable = [
        'id_auxilio',
        'descripcion',
        'ruta',
        'created_at',
        'created_by',
    ];

    public function auxilio()
    {
        return $this->belongsTo(Auxilio::class, 'id_auxilio', 'id');
    }
}
