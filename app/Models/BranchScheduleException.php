<?php

namespace App\Models;

use Database\Factories\BranchScheduleExceptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['local_date', 'is_closed', 'intervals'])]
class BranchScheduleException extends Model
{
    /** @use HasFactory<BranchScheduleExceptionFactory> */
    use HasFactory;

    /** @var array<string, mixed> */
    protected $attributes = ['is_closed' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['is_closed' => 'boolean', 'intervals' => 'array'];
    }

    /** @return BelongsTo<Branch, $this> */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
