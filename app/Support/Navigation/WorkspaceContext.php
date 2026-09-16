<?php

declare(strict_types=1);

namespace App\Support\Navigation;

final readonly class WorkspaceContext
{
    public function __construct(
        public int $actorId,
        public string $mode,
        public string $destination,
        public ?int $branchId = null,
        public ?int $organizationId = null,
        public ?int $brandId = null,
        public ?string $name = null,
        public ?string $description = null,
    ) {}
}
