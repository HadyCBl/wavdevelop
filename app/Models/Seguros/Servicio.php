<?php

namespace Micro\Models\Seguros;

use Micro\Traits\CustomSoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletes;
use Micro\Models\BaseModel;
use Micro\Models\Scopes\StatusActiveScope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Micro\Generic\Auth;

// #[ScopedBy([StatusActiveScope::class])]
class Servicio extends BaseModel
{
    // use SoftDeletes;
    use CustomSoftDeletes;
    protected $table = 'aux_servicios';
    protected $primaryKey = 'id';

    protected $fillable = [
        'nombre',
        'descripcion',
        'costo',
        'monto_auxilio',
        'edad_minima',
        'edad_maxima',
        'notas',
        'id_nomenclatura',
        'estado',
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

    public function cuentas()
    {
        return $this->hasMany(Cuenta::class, 'id_servicio', 'id');
    }

    public function nomenclatura()
    {
        return $this->belongsTo(\Micro\Models\Contabilidad\Nomenclatura::class, 'id_nomenclatura', 'id');
    }

    /**
     * Sobrescribir el método delete
     */
    // public function delete()
    // {
    //     // Si ya está eliminado, ejecutar delete normal
    //     if ($this->trashed()) {
    //         return parent::delete();
    //     }

    //     // Actualizar campos personalizados
    //     $this->estado = '0';
    //     $this->deleted_by = Auth::getUserId();
    //     $this->timestamps = false; // Opcional: evitar actualizar updated_at
    //     $this->save();
    //     $this->timestamps = true;

    //     // Ejecutar soft delete
    //     return parent::delete();
    // }
}
