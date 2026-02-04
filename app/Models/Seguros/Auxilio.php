<?php

namespace Micro\Models\Seguros;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Micro\Models\BaseModel;
use Micro\Models\Scopes\Seguros\AuxiliosNoEliminadosScope;
use Micro\Models\Seguros\Beneficiario as SegurosBeneficiario;

#[ScopedBy([AuxiliosNoEliminadosScope::class])]
class Auxilio extends BaseModel
{
    protected $table = 'aux_auxilios';

    protected $fillable = [
        'id_cuenta',
        'fecha_fallece',
        'fecha_solicitud',
        'monto_aprobado',
        'estado',
        'notas',
        'created_by',
        'deleted_by',
        'updated_by',
        'created_at',
        'updated_at',
        'deleted_at'
    ];

    public function pagos()
    {
        return $this->hasMany(Pago::class, 'id_auxilio', 'id');
    }

    public function documentos()
    {
        return $this->hasMany(Documento::class, 'id_auxilio', 'id');
    }

    public function beneficiario()
    {
        return $this->belongsTo(SegurosBeneficiario::class, 'id_beneficiario', 'id');
    }

    public function cuenta()
    {
        return $this->belongsTo(Cuenta::class, 'id_cuenta', 'id');
    }

    /**
     * Scope para auxilios solicitados
     */
    #[Scope]
    public function solicitados(Builder $query): void
    {
        $query->where('estado', 'solicitado');
    }

    /**
     * Scope para auxilios aprobados
     */
    #[Scope]
    public function aprobados(Builder $query): void
    {
        $query->where('estado', 'aprobado');
    }

    #[Scope]
    public function rechazados(Builder $query): void
    {
        $query->where('estado', 'rechazado');
    }

    #[Scope]
    public function pagados(Builder $query): void
    {
        $query->where('estado', 'pagado');
    }
}
