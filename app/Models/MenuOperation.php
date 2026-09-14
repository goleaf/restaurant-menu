<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MenuOperationKind;
use App\Enums\MenuOperationPhase;
use Carbon\CarbonImmutable;
use Database\Factories\MenuOperationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property MenuOperationKind $kind
 * @property MenuOperationPhase $phase
 * @property CarbonImmutable|null $completed_at
 * @property list<string> $pending_cleanup
 * @property array<string, mixed> $payload
 */
#[Fillable(['request_id'])]
class MenuOperation extends Model
{
    /** @use HasFactory<MenuOperationFactory> */
    use HasFactory;

    protected $attributes = ['phase' => 'preparing', 'cursor' => 0, 'processed_count' => 0, 'source_changed' => false, 'pending_cleanup' => '[]', 'payload' => '[]'];

    protected $hidden = ['pending_cleanup', 'payload', 'active_scope'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['kind' => MenuOperationKind::class, 'phase' => MenuOperationPhase::class, 'cursor' => 'integer', 'processed_count' => 'integer',
            'target_id' => 'integer', 'result_id' => 'integer', 'source_changed' => 'boolean', 'pending_cleanup' => 'array', 'payload' => 'array', 'completed_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<Menu, $this> */
    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class)->withTrashed();
    }

    /** @return BelongsTo<MenuItem, $this> */
    public function result(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'result_id')->withTrashed();
    }

    /** @return HasMany<MenuOperationCategory, $this> */
    public function categories(): HasMany
    {
        return $this->hasMany(MenuOperationCategory::class);
    }

    /** @return array{request_id: string, kind: string, phase: string, processed_count: int, completed: bool, result_id: ?int} */
    public function progress(): array
    {
        return ['request_id' => $this->request_id, 'kind' => $this->kind->value, 'phase' => $this->phase->value,
            'processed_count' => $this->processed_count, 'completed' => $this->completed_at !== null,
            'result_id' => $this->phase === MenuOperationPhase::Completed ? $this->result_id : null];
    }
}
