<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvitationStatus;
use Carbon\CarbonInterface;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $organization_id
 * @property int|null $brand_id
 * @property int|null $branch_id
 * @property int $role_id
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $invite_token_hash
 * @property string|null $invite_code_hash
 * @property CarbonInterface $expires_at
 * @property InvitationStatus $status
 * @property int|null $invited_by_user_id
 * @property int|null $accepted_by_user_id
 * @property CarbonInterface|null $accepted_at
 * @property-read Organization|null $organization
 * @property-read Branch|null $branch
 * @property-read Role|null $role
 */
#[Fillable(['email', 'phone'])]
#[Hidden(['invite_token_hash', 'invite_code_hash'])]
class Invitation extends Model
{
    public const MAX_USES = 1;

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
            'accepted_at' => 'datetime',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by_user_id');
    }

    public function canBeAccepted(): bool
    {
        return $this->status === InvitationStatus::Pending
            && $this->expires_at->isFuture()
            && is_string($this->invite_token_hash)
            && strlen($this->invite_token_hash) === 64;
    }

    public function effectiveStatus(): InvitationStatus
    {
        if ($this->status === InvitationStatus::Pending && $this->expires_at->isPast()) {
            return InvitationStatus::Expired;
        }

        return $this->status;
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
                'invite_token_hash',
                'invite_code_hash',
                'expires_at',
                'status',
                'invited_by_user_id',
                'accepted_by_user_id',
                'accepted_at',
                'created_at',
                'updated_at',
            ])
            ->acceptable()
            ->where('invite_token_hash', self::tokenHash($inviteToken))
            ->first();
    }

    public static function findAcceptableById(int $invitationId): ?self
    {
        $invitation = self::query()
            ->acceptable()
            ->whereKey($invitationId)
            ->first();

        return $invitation?->canBeAccepted() === true ? $invitation : null;
    }

    private static function tokenHash(string $inviteToken): string
    {
        return hash('sha256', $inviteToken);
    }
}
