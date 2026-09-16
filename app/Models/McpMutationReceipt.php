<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\McpAbility;
use Database\Factories\McpMutationReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class McpMutationReceipt extends Model
{
    /** @use HasFactory<McpMutationReceiptFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['mcp_access_token_id', 'user_id', 'organization_id', 'branch_id', 'idempotency_key', 'ability', 'input_hash', 'result'];

    /** @var list<string> */
    protected $hidden = ['idempotency_key', 'input_hash', 'result'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['ability' => McpAbility::class, 'result' => 'array'];
    }

    /** @return BelongsTo<McpAccessToken, $this> */
    public function token(): BelongsTo
    {
        return $this->belongsTo(McpAccessToken::class, 'mcp_access_token_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
