<?php

namespace Micro\Models\Seguros;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;
use Illuminate\Database\Eloquent\Builder;
use Micro\Models\BaseModel;
use Micro\Models\Clientes\Beneficiario;
use Micro\Models\Clientes\Cliente;
use Micro\Models\Scopes\Seguros\CuentasNoEliminadasScope;
use Micro\Models\Seguros\Beneficiario as SegurosBeneficiario;

#[ScopedBy([CuentasNoEliminadasScope::class])]
class Cuenta extends BaseModel
{
    protected $table = 'aux_cuentas';
    protected $primaryKey = 'id';

    protected $fillable = [
        'id_cliente',
        'id_servicio',
        'fecha_inicio',
        'observaciones',
        'estado',
        'created_at',
        'created_by',
        'updated_by',
        'updated_at'
    ];

    public function renovaciones()
    {
        return $this->hasMany(Renovacion::class, 'id_cuenta', 'id');
    }

    public function servicio()
    {
        return $this->belongsTo(Servicio::class, 'id_servicio', 'id');
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente', 'idcod_cliente');
    }

    /**
     * Relacion con beneficiarios
     */

    public function beneficiarios()
    {
        return $this->belongsToMany(
            Beneficiario::class,
            'aux_beneficiarios',
            'id_cuenta',
            'id_beneficiario'
        )
            ->using(SegurosBeneficiario::class)
            ->withPivot(['parentesco', 'porcentaje', 'created_at', 'created_by']);
    }

    /**
     * Scope para cuentas vigentes
     */
    #[Scope]
    public function vigentes(Builder $query): void
    {
        $query->where('estado', 'vigente');
    }

    /**
     * Scope para cuentas cerradas
     */
    #[Scope]
    public function cerradas(Builder $query): void
    {
        $query->where('estado', 'cerrada');
    }
}
