<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property SystemRole $code
 */
#[Fillable(['code', 'name', 'sort_order'])]
class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'code' => SystemRole::class,
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->withPivot('enabled')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasMany<BranchUser, $this>
     */
    public function branchUsers(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    public function hasPermission(SystemPermission|string $permission): bool
    {
        return $this->permissions()
            ->where('permissions.code', SystemPermission::resolveCode($permission))
            ->wherePivot('enabled', true)
            ->exists();
    }
}
