<?php

namespace App\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['code', 'name', 'sort_order'])]
class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsToMany<Role, $this>
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->using(PermissionRole::class)
            ->withPivot('enabled')
            ->withTimestamps();
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function usersWithOverrides(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'permission_user_overrides')
            ->using(PermissionUserOverride::class)
            ->withPivot('enabled')
            ->withTimestamps();
    }
}
