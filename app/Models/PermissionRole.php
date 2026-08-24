<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PermissionRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $permission_id
 * @property bool $enabled
 */
#[Fillable(['enabled'])]
class PermissionRole extends Pivot
{
    /** @use HasFactory<PermissionRoleFactory> */
    use HasFactory;

    protected $table = 'permission_role';

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    public $incrementing = true;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
