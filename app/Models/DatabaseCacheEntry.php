<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\DatabaseCacheEntryFactory;
use Illuminate\Cache\Repository;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $key
 * @property int $expiration
 */
#[Fillable(['key', 'value', 'expiration'])]
class DatabaseCacheEntry extends Model
{
    /** @use HasFactory<DatabaseCacheEntryFactory> */
    use HasFactory;

    protected $table = 'cache';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    /** @var list<string> */
    protected $hidden = ['key', 'value'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expiration' => 'integer'];
    }

    /** @param Builder<DatabaseCacheEntry> $query */
    #[Scope]
    protected function expiredReports(Builder $query, string $prefix, int $expiredAt): void
    {
        $query->select(['key', 'expiration'])
            ->where('expiration', '<=', $expiredAt)
            ->where(function (Builder $families) use ($prefix): void {
                foreach ([
                    'analytics:dashboard:',
                    'restaurant-dashboard:',
                    Repository::FLEXIBLE_CREATED_KEY_PREFIX.'analytics:dashboard:',
                    Repository::FLEXIBLE_CREATED_KEY_PREFIX.'restaurant-dashboard:',
                    'report-version:',
                ] as $family) {
                    $families->orWhere(fn (Builder $range): Builder => $range
                        ->where('key', '>=', $prefix.$family)
                        ->where('key', '<', $prefix.substr($family, 0, -1).';'));
                }
            });
    }
}
