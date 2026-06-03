<?php

namespace App\Models;

use App\Enums\QrCodeStatus;
use Database\Factories\QrCodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['service_point_id', 'public_token', 'short_code', 'status', 'created_by_user_id', 'revoked_at', 'revoked_by_user_id'])]
class QrCode extends Model
{
    /** @use HasFactory<QrCodeFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'active',
    ];

    protected static function booted(): void
    {
        static::saving(function (QrCode $qrCode): void {
            $status = $qrCode->status instanceof QrCodeStatus
                ? $qrCode->status
                : QrCodeStatus::from($qrCode->status ?? QrCodeStatus::Active->value);

            $qrCode->active_service_point_id = $status === QrCodeStatus::Active
                ? $qrCode->service_point_id
                : null;
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QrCodeStatus::class,
            'revoked_at' => 'datetime',
        ];
    }

    public function publicPath(): string
    {
        return '/q/'.$this->public_token;
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function servicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class);
    }

    /**
     * @return BelongsTo<ServicePoint, $this>
     */
    public function activeServicePoint(): BelongsTo
    {
        return $this->belongsTo(ServicePoint::class, 'active_service_point_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }
}
