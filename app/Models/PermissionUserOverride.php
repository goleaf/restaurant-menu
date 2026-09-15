<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PermissionUserOverrideFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

/**
 * @property int $permission_id
 * @property bool $enabled
 * @property int|null $organization_id
 * @property string $scope_key
 */
#[Fillable(['enabled'])]
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
            'organization_id' => 'integer',
        ];
    }

    /**
     * Resolve preserved legacy data without copying an ambiguous grant to other tenants.
     *
     * @param  Collection<int, self>  $overrides
     * @return Collection<int, bool>
     */
    public static function effectiveForOrganization(Collection $overrides, int $organizationId, bool $singleOrganization): Collection
    {
        $legacy = $overrides->filter(fn (self $override): bool => $override->organization_id === null
            && $override->scope_key === 'legacy'
            && (! $override->enabled || $singleOrganization));
        $scoped = $overrides->filter(fn (self $override): bool => $override->organization_id === $organizationId
            && $override->scope_key === 'organization:'.$organizationId);

        return $legacy->concat($scoped)->mapWithKeys(fn (self $override): array => [$override->permission_id => $override->enabled]);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Permission, $this> */
    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }
}
