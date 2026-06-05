<?php

namespace App\Models;

use Database\Factories\PermissionRoleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['role_id', 'permission_id', 'enabled'])]
class PermissionRole extends Pivot
{
    /** @use HasFactory<PermissionRoleFactory> */
    use HasFactory;

    protected $table = 'permission_role';

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
}
