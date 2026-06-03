<?php

namespace App\Models;

use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['organization_id', 'brand_id', 'name', 'address', 'city', 'country', 'timezone', 'currency', 'is_active'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasOne<BranchSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(BranchSetting::class);
    }

    /**
     * @return HasMany<BranchUser, $this>
     */
    public function staffAssignments(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    /**
     * @return HasMany<AreaNode, $this>
     */
    public function areaNodes(): HasMany
    {
        return $this->hasMany(AreaNode::class);
    }

    /**
     * @return HasMany<ServicePoint, $this>
     */
    public function servicePoints(): HasMany
    {
        return $this->hasMany(ServicePoint::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
