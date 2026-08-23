<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuItemVariantType;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\User;
use App\Support\MoneyFormatter;
use App\Support\PlainText;
use Illuminate\Support\Facades\Gate;

class BuildMenuItemVariantAttributesAction
{
    /**
     * @param  array{type: string, name: string, price?: string|int, weight: string|null, volume: string|null, is_default: bool, is_available?: bool, sort_order: int}  $data
     * @return array{type: string, name: string, price_cents: int, weight: string|null, volume: string|null, is_default: bool, is_available: bool, sort_order: int}
     */
    public function handle(
        User $actor,
        Branch $branch,
        MenuItem $item,
        array $data,
        ?MenuItemVariant $existing = null,
    ): array {
        $existingPriceCents = $existing instanceof MenuItemVariant ? $existing->price_cents : $item->price_cents;
        $existingAvailability = $existing instanceof MenuItemVariant ? $existing->is_available : true;

        return [
            'type' => MenuItemVariantType::from($data['type'])->value,
            'name' => PlainText::required($data['name'], 160, squish: true),
            'price_cents' => Gate::forUser($actor)->allows('changeMenuPrices', $branch)
                ? MoneyFormatter::decimalToCents($data['price'] ?? 0)
                : $existingPriceCents,
            'weight' => $this->optionalString($data['weight']),
            'volume' => $this->optionalString($data['volume']),
            'is_default' => (bool) $data['is_default'],
            'is_available' => Gate::forUser($actor)->allows('changeMenuAvailability', $branch)
                ? (bool) ($data['is_available'] ?? true)
                : $existingAvailability,
            'sort_order' => $data['sort_order'],
        ];
    }

    private function optionalString(?string $value): ?string
    {
        return $value === null || $value === '' ? null : $value;
    }
}
