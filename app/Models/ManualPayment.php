<?php

namespace App\Models;

use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use Database\Factories\ManualPaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_point_id', 'table_session_id', 'table_session_guest_id', 'recorded_by_user_id', 'scope', 'payment_method', 'covered_subtotal_amount', 'service_charge_percent', 'service_charge_amount', 'tips_amount', 'amount', 'currency', 'guest_name', 'note', 'paid_at', 'metadata'])]
class ManualPayment extends Model
{
    /** @use HasFactory<ManualPaymentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'scope' => 'table',
        'payment_method' => 'cash',
        'covered_subtotal_amount' => '0.00',
        'service_charge_percent' => '0.00',
        'service_charge_amount' => '0.00',
        'tips_amount' => '0.00',
        'amount' => '0.00',
        'currency' => 'EUR',
        'metadata' => '[]',
    ];

    public function save(array $options = []): bool
    {
        if ($this->exists && $this->isDirty()) {
            return false;
        }

        return parent::save($options);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $options
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        if ($this->exists) {
            return false;
        }

        return parent::update($attributes, $options);
    }

    public function delete(): ?bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ManualPaymentScope::class,
            'payment_method' => ManualPaymentMethod::class,
            'covered_subtotal_amount' => 'decimal:2',
            'service_charge_percent' => 'decimal:2',
            'service_charge_amount' => 'decimal:2',
            'tips_amount' => 'decimal:2',
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    /**
     * @return BelongsTo<TableSession, $this>
     */
    public function tableSession(): BelongsTo
    {
        return $this->belongsTo(TableSession::class);
    }

    /**
     * @return BelongsTo<TableSessionGuest, $this>
     */
    public function guest(): BelongsTo
    {
        return $this->belongsTo(TableSessionGuest::class, 'table_session_guest_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}
