<?php

declare(strict_types=1);

namespace App\Actions\Modifiers;

use App\Models\Branch;
use App\Models\ModifierOption;
use App\Models\User;
use App\Support\MoneyFormatter;
use Illuminate\Support\Facades\Gate;

final class BuildModifierOptionAttributesAction
{
    /**
     * @param  array{name: string, price_delta?: string|int, is_available?: bool, sort_order: int}  $data
     * @return array{name: string, price_delta_cents: int, is_available: bool, sort_order: int}
     */
    public function handle(User $actor, Branch $branch, array $data, ?ModifierOption $existing = null): array
    {
        $existingPriceCents = $existing instanceof ModifierOption ? $existing->price_delta_cents : 0;
        $existingAvailability = $existing instanceof ModifierOption ? $existing->is_available : true;

        return [
            'name' => $data['name'],
            'price_delta_cents' => Gate::forUser($actor)->allows('changeMenuPrices', $branch)
                ? MoneyFormatter::decimalToCents($data['price_delta'] ?? 0)
                : $existingPriceCents,
            'is_available' => Gate::forUser($actor)->allows('changeMenuAvailability', $branch)
                ? (bool) ($data['is_available'] ?? true)
                : $existingAvailability,
            'sort_order' => $data['sort_order'],
        ];
    }
}
