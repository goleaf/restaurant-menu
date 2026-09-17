<?php

declare(strict_types=1);

namespace App\Support\Availability;

use Carbon\CarbonImmutable;

final readonly class AvailabilityResult
{
    /** @param list<AvailabilityReason> $reasons */
    public function __construct(
        public string $scope,
        public bool $visible,
        public bool $configurable,
        public bool $acceptsNewOrders,
        public array $reasons,
        public CarbonImmutable $evaluatedAt,
        public ?CarbonImmutable $nextChangeAt = null,
        public ?CarbonImmutable $nextOrderableAt = null,
    ) {}

    public function primaryCode(): ?string
    {
        return $this->reasons[0]->code ?? null;
    }

    public function publicMessage(): string
    {
        return isset($this->reasons[0]) ? $this->reasons[0]->publicMessage() : __('availability.guest.available');
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope, 'visible' => $this->visible, 'configurable' => $this->configurable,
            'accepts_new_orders' => $this->acceptsNewOrders, 'primary_reason' => $this->primaryCode(),
            'reasons' => array_map(fn (AvailabilityReason $reason): array => $reason->toArray(), $this->reasons),
            'evaluated_at' => $this->evaluatedAt->toIso8601String(), 'next_change_at' => $this->nextChangeAt?->toIso8601String(),
            'next_orderable_at' => $this->nextOrderableAt?->toIso8601String(),
        ];
    }
}
