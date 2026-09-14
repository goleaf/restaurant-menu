<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use WeakMap;

final class MenuDeletionContext
{
    /** @var WeakMap<Model, bool>|null */
    private static ?WeakMap $models = null;

    /** @param Closure(): (bool|null) $delete */
    public static function withoutCascade(Model $model, Closure $delete): ?bool
    {
        self::$models ??= new WeakMap;
        self::$models[$model] = true;

        try {
            return $delete();
        } finally {
            unset(self::$models[$model]);
        }
    }

    public static function contains(Model $model): bool
    {
        return isset(self::$models[$model]);
    }
}
