<?php

declare(strict_types=1);

namespace App\Support\Availability;

final readonly class AvailabilityReason
{
    public function __construct(public string $code, public string $source, public ?int $entityId = null) {}

    /** @return array{code: string, source: string, entity_id: int|null, label: string, detail: string, tone: string} */
    public function toArray(): array
    {
        $label = 'availability.reasons.'.$this->code;
        $source = 'availability.sources.'.$this->source;

        return [
            'code' => $this->code, 'source' => $this->source, 'entity_id' => $this->entityId,
            'label' => __($label), 'detail' => __($source), 'tone' => 'warning',
        ];
    }

    public function publicMessage(): string
    {
        return __(match ($this->code) {
            'branch_paused' => 'availability.guest.paused',
            'branch_schedule_closed', 'branch_date_closed' => 'availability.guest.outside_hours',
            'menu_schedule_closed' => 'availability.guest.menu_later',
            'variants_unavailable' => 'availability.guest.variant_unavailable',
            'required_options_unavailable' => 'availability.guest.configuration_unavailable',
            'item_stopped', 'item_hidden', 'category_inactive', 'item_archived', 'menu_unpublished' => 'availability.guest.item_unavailable',
            default => 'availability.guest.unavailable',
        });
    }
}
