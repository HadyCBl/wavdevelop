<?php

namespace Micro\Models;

use Micro\Models\BaseModel;
class Agencia extends BaseModel
{
    protected $table = 'tb_agencia';

    protected $primaryKey = 'id_agencia';

    protected $fillable = [
        'nom_agencia',
        'cod_agenc',
        'id_institucion',
        'id_nomenclatura_caja',
        'municipio',
        'departamento',
        'pais',
        'id_nomenclatura_juridico'
    ];

    public function nomenclaturaCaja()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\Nomenclatura::class, 'id_nomenclatura_caja', 'id');
    }

    public function nomenclaturaJuridico()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\Nomenclatura::class, 'id_nomenclatura_juridico', 'id');
    }
}
