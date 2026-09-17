<?php

declare(strict_types=1);

namespace App\Support\Availability;

use App\Models\User;

/** Transient observer context supplied only by the owning transactional restriction Action. */
final readonly class MenuRestrictionAuditContext
{
    public function __construct(public User $actor, public int $organizationId, public int $branchId, public string $operation, public ?string $reason) {}
}
