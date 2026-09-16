<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\McpAccessTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $organization_id
 * @property int $branch_id
 * @property string $name
 * @property string $token_hash
 * @property list<string> $abilities
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $revoked_at
 * @property-read User $user
 * @property-read Branch $branch
 */
#[Fillable(['name'])]
#[Hidden(['token_hash'])]
class McpAccessToken extends Model
{
    /** @use HasFactory<McpAccessTokenFactory> */
    use HasFactory;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['abilities' => 'array', 'expires_at' => 'immutable_datetime', 'revoked_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @param Builder<McpAccessToken> $query @return Builder<McpAccessToken> */
    public function scopeUsable(Builder $query): Builder
    {
        return $query->select(['id', 'user_id', 'organization_id', 'branch_id', 'name', 'token_hash', 'abilities', 'expires_at', 'revoked_at'])
            ->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
