<?php

namespace App\Models;

use Database\Factories\AvailabilityCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'actor_user_id', 'kind', 'target_id', 'payload_hash', 'result'])]
class AvailabilityCommand extends Model
{
    /** @use HasFactory<AvailabilityCommandFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $hidden = ['request_id', 'payload_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['result' => 'array', 'target_id' => 'integer'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
