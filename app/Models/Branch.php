<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HasLocalLogo;
use Carbon\CarbonInterface;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'brand_id',
    'name',
    'public_name',
    'public_description',
    'logo_path',
    'cover_image_path',
    'address',
    'phone',
    'email',
    'website_url',
    'instagram_url',
    'facebook_url',
    'tiktok_url',
    'city',
    'country',
    'timezone',
    'currency',
    'is_active',
    'is_temporarily_closed',
    'temporary_closed_reason',
    'temporary_closed_until',
])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory, HasLocalLogo, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_temporarily_closed' => 'boolean',
            'temporary_closed_until' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class)->withTrashed();
    }

    /**
     * @return HasOne<BranchSetting, $this>
     */
    public function settings(): HasOne
    {
        return $this->hasOne(BranchSetting::class);
    }

    /**
     * @return HasMany<BranchOpeningHour, $this>
     */
    public function openingHours(): HasMany
    {
        return $this->hasMany(BranchOpeningHour::class)
            ->orderBy('day_of_week')
            ->orderBy('sort_order')
            ->orderBy('opens_at')
            ->orderBy('id');
    }

    public function temporaryClosedUntilForBranch(): ?CarbonInterface
    {
        $timezone = $this->timezone ?: config('app.timezone', 'UTC');
        $rawValue = $this->getRawOriginal('temporary_closed_until');

        if ($rawValue instanceof CarbonInterface) {
            return $rawValue->copy()->setTimezone($timezone);
        }

        if (is_string($rawValue) && trim($rawValue) !== '') {
            return Carbon::parse($rawValue, 'UTC')->setTimezone($timezone);
        }

        $value = $this->getAttribute('temporary_closed_until');

        if ($value instanceof CarbonInterface) {
            return $value->copy()->setTimezone($timezone);
        }

        return null;
    }

    public function publicDisplayName(): string
    {
        $publicName = $this->getAttribute('public_name');

        if (is_string($publicName) && filled($publicName)) {
            return $publicName;
        }

        return (string) $this->getAttribute('name');
    }

    public function coverImageUrl(): ?string
    {
        $coverImagePath = $this->getAttribute('cover_image_path');

        if (! is_string($coverImagePath) || blank($coverImagePath)) {
            return null;
        }

        return Storage::disk('public')->url($coverImagePath);
    }

    /**
     * @return HasMany<BranchUser, $this>
     */
    public function staffAssignments(): HasMany
    {
        return $this->hasMany(BranchUser::class);
    }

    /**
     * @return HasMany<AreaNodeWaiter, $this>
     */
    public function waiterAreaAssignments(): HasMany
    {
        return $this->hasMany(AreaNodeWaiter::class);
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
     * @return HasMany<TableSession, $this>
     */
    public function tableSessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    /**
     * @return HasMany<WaiterCall, $this>
     */
    public function waiterCalls(): HasMany
    {
        return $this->hasMany(WaiterCall::class)
            ->orderBy('requested_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * @return HasMany<KitchenTicket, $this>
     */
    public function kitchenTickets(): HasMany
    {
        return $this->hasMany(KitchenTicket::class)
            ->orderBy('sent_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<OrderStatusLog, $this>
     */
    public function orderStatusLogs(): HasMany
    {
        return $this->hasMany(OrderStatusLog::class)
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<Menu, $this>
     */
    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    /**
     * @return HasMany<ModifierGroup, $this>
     */
    public function modifierGroups(): HasMany
    {
        return $this->hasMany(ModifierGroup::class)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<KitchenDepartment, $this>
     */
    public function kitchenDepartments(): HasMany
    {
        return $this->hasMany(KitchenDepartment::class)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id');
    }

    /**
     * @return HasMany<Invitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
