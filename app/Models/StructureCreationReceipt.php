<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\StructureCreationReceiptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class StructureCreationReceipt extends Model
{
    /** @use HasFactory<StructureCreationReceiptFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['actor_id', 'request_key', 'payload_hash', 'kind', 'organization_id', 'resource_id'];

    /** @var list<string> */
    protected $hidden = ['request_key', 'payload_hash'];

    /** @return array<string,string> */
    protected function casts(): array
    {
        return ['actor_id' => 'integer', 'organization_id' => 'integer', 'resource_id' => 'integer'];
    }

    /** @return BelongsTo<User,$this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Organization,$this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
