<?php

namespace App\Models;

use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\ServicePointType;
use App\Enums\TableSessionStatus;
use Database\Factories\ServicePointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['branch_id', 'area_node_id', 'type', 'name', 'display_number', 'internal_code', 'capacity', 'icon', 'status', 'position_x', 'position_y', 'is_active', 'metadata'])]
class ServicePoint extends Model
{
    /** @use HasFactory<ServicePointFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'type' => 'table',
        'capacity' => 1,
        'status' => 'free',
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ServicePointType::class,
            'capacity' => 'integer',
            'status' => ServicePointStatus::class,
            'position_x' => 'float',
            'position_y' => 'float',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class)->withTrashed();
    }

    /**
     * @return BelongsTo<AreaNode, $this>
     */
    public function areaNode(): BelongsTo
    {
        return $this->belongsTo(AreaNode::class)->withTrashed();
    }

    /**
     * @return HasMany<QrCode, $this>
     */
    public function qrCodes(): HasMany
    {
        return $this->hasMany(QrCode::class);
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
     * @return HasMany<ManualPayment, $this>
     */
    public function manualPayments(): HasMany
    {
        return $this->hasMany(ManualPayment::class)
            ->orderBy('paid_at')
            ->orderBy('id');
    }

    /**
     * @return HasOne<TableSession, $this>
     */
    public function activeTableSession(): HasOne
    {
        return $this->hasOne(TableSession::class)
            ->whereIn('status', [
                TableSessionStatus::Active->value,
                TableSessionStatus::PaymentRequested->value,
            ])
            ->oldest('started_at')
            ->oldest('id');
    }

    /**
     * @return HasOne<QrCode, $this>
     */
    public function activeQrCode(): HasOne
    {
        return $this->hasOne(QrCode::class)
            ->where('status', QrCodeStatus::Active->value);
    }

    /**
     * @return HasMany<TableSessionServicePoint, $this>
     */
    public function tableSessionServicePointLinks(): HasMany
    {
        return $this->hasMany(TableSessionServicePoint::class)
            ->orderBy('linked_at')
            ->orderBy('id');
    }

    /**
     * @return HasMany<TableSessionServicePoint, $this>
     */
    public function activeTableSessionServicePointLinks(): HasMany
    {
        return $this->hasMany(TableSessionServicePoint::class)
            ->whereNull('unlinked_at')
            ->orderBy('linked_at')
            ->orderBy('id');
    }
}
