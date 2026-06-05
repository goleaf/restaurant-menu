<?php

namespace App\Models;

use Database\Factories\PermissionUserOverrideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\Pivot;

class PermissionUserOverride extends Pivot
{
    /** @use HasFactory<PermissionUserOverrideFactory> */
    use HasFactory;

    protected $table = 'permission_user_overrides';

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
}
