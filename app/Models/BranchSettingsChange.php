<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\BranchSettingsChangeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BranchSettingsChange extends Model
{
    /** @use HasFactory<BranchSettingsChangeFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['branch_id', 'user_id', 'request_id', 'group', 'payload_hash', 'result'];

    /** @var list<string> */
    protected $hidden = ['request_id', 'payload_hash'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['branch_id' => 'integer', 'user_id' => 'integer', 'result' => 'array'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
