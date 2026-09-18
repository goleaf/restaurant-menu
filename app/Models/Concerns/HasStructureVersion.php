<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

trait HasStructureVersion
{
    protected static function bootHasStructureVersion(): void
    {
        static::updating(function (self $model): void {
            if ($model->isDirty($model->structureVersionFields())) {
                $model->structure_version = ((int) $model->getOriginal('structure_version')) + 1;
            }
        });
        static::deleted(function (self $model): void {
            if (method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting()) {
                $model->newQueryWithoutScopes()->whereKey($model->getKey())->increment('structure_version');
                $model->structure_version++;
            }
        });
    }

    /** @param Builder<static> $query */
    protected function performUpdate(Builder $query): bool
    {
        if ($this->fireModelEvent('updating') === false) {
            return false;
        }
        if ($this->usesTimestamps()) {
            $this->updateTimestamps();
        }
        $dirty = $this->getDirtyForUpdate();
        if ($dirty === []) {
            return true;
        }
        $query = $this->setKeysForSaveQuery($query);
        $versioned = array_key_exists('structure_version', $dirty);
        if ($versioned) {
            $query->where('structure_version', (int) $this->getOriginal('structure_version'));
        }
        $changed = $query->update($dirty);
        if ($versioned && $changed !== 1) {
            throw ValidationException::withMessages(['structureVersion' => __('floor.errors.stale')]);
        }
        $this->syncChanges();
        $this->fireModelEvent('updated', false);

        return true;
    }

    /** @return list<string> */
    abstract protected function structureVersionFields(): array;
}
