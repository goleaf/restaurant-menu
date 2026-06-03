<?php

namespace App\Models;

use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'sort_order'])]
class Role extends Model
{
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
     * @return Attribute<never, string>
     */
    protected function code(): Attribute
    {
        return Attribute::set(
            fn (SystemRole|string $value): string => $value instanceof SystemRole
                ? $value->value
                : SystemRole::from($value)->value,
        );
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->using(PermissionRole::class)
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

    public function hasPermission(SystemPermission|string $permission): bool
    {
        return $this->permissions()
            ->where('permissions.code', SystemPermission::resolveCode($permission))
            ->wherePivot('enabled', true)
            ->exists();
    }
}
