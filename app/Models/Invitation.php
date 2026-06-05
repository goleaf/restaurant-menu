<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['brand_id', 'email', 'phone', 'invite_token', 'invite_code', 'expires_at', 'invited_by_user_id'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => InvitationStatus::Pending->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'status' => InvitationStatus::class,
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
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Role, $this>
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function inviteLink(): string
    {
        return url('/invite/'.$this->invite_token);
    }

    public function canBeAccepted(): bool
    {
        return $this->status === InvitationStatus::Pending
            && $this->expires_at !== null
            && $this->expires_at->isFuture()
            && is_string($this->invite_token)
            && strlen($this->invite_token) === 64
            && ctype_alnum($this->invite_token);
    }

    /**
     * @param  Builder<Invitation>  $query
     * @return Builder<Invitation>
     */
    public function scopeAcceptable(Builder $query): Builder
    {
        return $query
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now());
    }

    public static function findAcceptableByToken(string $inviteToken): ?self
    {
        $inviteToken = trim($inviteToken);

        if (strlen($inviteToken) !== 64 || ! ctype_alnum($inviteToken)) {
            return null;
        }

        return self::query()
            ->select([
                'id',
                'organization_id',
                'brand_id',
                'branch_id',
                'role_id',
                'email',
                'phone',
                'invite_token',
                'invite_code',
                'expires_at',
                'status',
                'invited_by_user_id',
                'created_at',
                'updated_at',
            ])
            ->acceptable()
            ->where('invite_token', $inviteToken)
            ->first();
    }
}
