<?php

namespace Micro\Traits;

use Illuminate\Database\Eloquent\Builder;

trait CustomSoftDeletes
{
    /**
     * Indica si el modelo está siendo forzado a eliminarse
     */
    protected $forceDeleting = false;

    /**
     * Boot the soft deleting trait for a model
     */
    public static function bootCustomSoftDeletes()
    {
        static::addGlobalScope(new \Micro\Models\Scopes\StatusActiveScope());
    }

    /**
     * Perform the actual delete query on this model instance
     */
    public function delete()
    {
        if (is_null($this->getKeyName())) {
            throw new \Exception('No primary key defined on model.');
        }

        if (!$this->exists) {
            return;
        }

        if ($this->forceDeleting) {
            return $this->performDeleteOnModel();
        }

        return $this->runSoftDelete();
    }

    /**
     * Force a hard delete on a soft deleted model
     */
    public function forceDelete()
    {
        $this->forceDeleting = true;

        return tap($this->delete(), function ($deleted) {
            $this->forceDeleting = false;

            if ($deleted) {
                $this->exists = false;
            }
        });
    }

    /**
     * Perform the actual delete query on this model instance
     */
    protected function performDeleteOnModel()
    {
        return $this->setKeysForSaveQuery($this->newModelQuery())->delete();
    }

    /**
     * Perform the soft delete
     */
    protected function runSoftDelete()
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());

        $columns = [
            'estado' => '0',
            'deleted_by' => $this->getCurrentUserId(),
        ];

        if ($this->timestamps && !is_null($this->getUpdatedAtColumn())) {
            $time = $this->freshTimestamp();
            $this->{$this->getUpdatedAtColumn()} = $time;
            $columns[$this->getUpdatedAtColumn()] = $this->fromDateTime($time);
        }

        // Agregar deleted_at si existe la columna
        if ($this->hasDeletedAtColumn()) {
            $time = $this->freshTimestamp();
            $columns['deleted_at'] = $this->fromDateTime($time);
            $this->deleted_at = $time;
        }

        $this->estado = '0';
        $this->deleted_by = $this->getCurrentUserId();

        $query->update($columns);

        $this->syncOriginalAttributes(array_keys($columns));

        return true;
    }

    /**
     * Restore a soft-deleted model instance
     */
    public function restore()
    {
        if (!$this->trashed()) {
            return false;
        }

        $this->estado = '1';
        $this->deleted_by = null;

        if ($this->hasDeletedAtColumn()) {
            $this->deleted_at = null;
        }

        $this->exists = true;

        return $this->save();
    }

    /**
     * Determine if the model instance has been soft-deleted
     */
    public function trashed()
    {
        return $this->estado == '0';
    }

    /**
     * Determine if the model is currently force deleting
     */
    public function isForceDeleting()
    {
        return $this->forceDeleting;
    }

    /**
     * Register a restoring model event with the dispatcher
     */
    public static function restoring($callback)
    {
        static::registerModelEvent('restoring', $callback);
    }

    /**
     * Register a restored model event with the dispatcher
     */
    public static function restored($callback)
    {
        static::registerModelEvent('restored', $callback);
    }

    /**
     * Determine if deleted_at column exists
     */
    protected function hasDeletedAtColumn()
    {
        return in_array('deleted_at', $this->fillable) ||
            property_exists($this, 'dates') && in_array('deleted_at', $this->dates);
    }

    /**
     * Get current user ID
     */
    protected function getCurrentUserId()
    {
        return \Micro\Generic\Auth::getUserId() ?? null;
    }

    /**
     * Get a new query builder that includes soft deleted models
     */
    public static function withTrashed()
    {
        return (new static)->newQueryWithoutScope(new \Micro\Models\Scopes\StatusActiveScope());
    }

    /**
     * Get a new query builder that only includes soft deleted models
     */
    public static function onlyTrashed()
    {
        return (new static)->newQueryWithoutScope(new \Micro\Models\Scopes\StatusActiveScope())
            ->where('estado', '0');
    }

    /**
     * Scope to include trashed models
     */
    public function scopeWithTrashed(Builder $query)
    {
        return $query->withoutGlobalScope(\Micro\Models\Scopes\StatusActiveScope::class);
    }

    /**
     * Scope to only get trashed models
     */
    public function scopeOnlyTrashed(Builder $query)
    {
        return $query->withoutGlobalScope(\Micro\Models\Scopes\StatusActiveScope::class)
            ->where('estado', '0');
    }
}
