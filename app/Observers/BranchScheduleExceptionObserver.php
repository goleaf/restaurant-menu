<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Branches\ForgetBranchCacheAction;
use App\Models\BranchScheduleException;

final class BranchScheduleExceptionObserver
{
    public function __construct(private readonly ForgetBranchCacheAction $cache) {}

    public function saved(BranchScheduleException $exception): void
    {
        $this->cache->handle($exception->branch_id);
        $previous = $exception->getOriginal('branch_id');
        if (is_int($previous) && $previous !== $exception->branch_id) {
            $this->cache->handle($previous);
        }
    }

    public function deleted(BranchScheduleException $exception): void
    {
        $this->cache->handle($exception->branch_id);
    }
}
