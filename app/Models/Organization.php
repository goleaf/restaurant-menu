<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OrganizationUserStatus;
use App\Models\Concerns\HasLocalLogo;
use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $active_branches_count
 */
#[Fillable(['owner_user_id', 'name', 'logo_path'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory, HasLocalLogo, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_users')
            ->withPivot(['role_id', 'status', 'joined_at', 'invited_by_user_id'])
            ->withTimestamps();
    }

    /**
     * @return HasMany<OrganizationUser, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationUser::class);
    }

    /**
     * @return HasMany<BranchUser, $this>
     */
    public function branchUsers(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    /**
     * @return HasMany<Brand, $this>
     */
    public function brands(): HasMany
    {
        return $this->hasMany(Brand::class);
    }

    /**
     * @return HasMany<Branch, $this>
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    /**
     * @return HasManyThrough<ServicePoint, Branch, $this>
     */
    public function servicePoints(): HasManyThrough
    {
        return $this->hasManyThrough(ServicePoint::class, Branch::class);
    }

    /**
     * @return HasManyThrough<Order, Branch, $this>
     */
    public function orders(): HasManyThrough
    {
        return $this->hasManyThrough(Order::class, Branch::class);
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * @return HasOne<OrganizationSubscription, $this>
     */
    public function subscription(): HasOne
    {
        return $this->hasOne(OrganizationSubscription::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()
            ->wherePivot('status', OrganizationUserStatus::Active->value);
    }
}
