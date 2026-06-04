<?php

namespace App\Models;

use App\Enums\OrganizationSubscriptionPaymentStatus;
use App\Enums\OrganizationSubscriptionStatus;
use Database\Factories\OrganizationSubscriptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['organization_id', 'status', 'started_at', 'next_payment_at', 'payment_status'])]
class OrganizationSubscription extends Model
{
    /** @use HasFactory<OrganizationSubscriptionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
        'payment_status' => 'pending',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrganizationSubscriptionStatus::class,
            'started_at' => 'datetime',
            'next_payment_at' => 'datetime',
            'payment_status' => OrganizationSubscriptionPaymentStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
