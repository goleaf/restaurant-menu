<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Enums\MenuAllergen;
use App\Enums\MenuDietaryLabel;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Support\Availability\AvailabilityResult;
use App\Support\LocalImageVariants;
use App\Support\MenuImagePresentation;

final class GuestMenuItemPresenter
{
    /** @return array<string, mixed> */
    public function present(MenuItem $item, string $languageCode, AvailabilityResult $decision): array
    {
        $imageVariants = LocalImageVariants::forPath($item->image);
        $imagePresentation = MenuImagePresentation::localized($item->image_presentation, $languageCode, (string) ($item->getAttribute('localized_name') ?: $item->name));

        return [
            'id' => $item->id,
            'name' => $this->translatedText(
                is_string($item->getAttribute('localized_name'))
                    ? $item->getAttribute('localized_name')
                    : null,
                $item->name,
            ),
            'description' => $item->getAttribute('has_localized_content')
                ? $item->getAttribute('localized_description')
                : $item->description,
            'price_cents' => $item->price_cents,
            'allergens' => $this->selectedLabelOptions($item->allergens, MenuAllergen::options($languageCode)),
            'dietary_labels' => $this->selectedLabelOptions($item->dietary_labels, MenuDietaryLabel::options($languageCode)),
            'image_url' => $imageVariants['url'],
            'image_variants' => $imageVariants,
            'image_alt' => $imagePresentation['alt'],
            'image_object_position' => $imagePresentation['object_position'],
            'weight' => $item->weight,
            'volume' => $item->volume,
            'calories' => $item->calories,
            'is_available' => $decision->acceptsNewOrders,
            'availability_reason' => $decision->acceptsNewOrders ? null : $decision->publicMessage(),
            'availability_label' => $decision->primaryCode() === 'item_stopped' ? __('menu.guest.out_of_stock') : __('menu.guest.unavailable'),
            'variants' => $item->variants->where('is_available', true)
                ->map(fn (MenuItemVariant $variant): array => [
                    'id' => $variant->id,
                    'type' => $variant->type->value,
                    'type_label' => $variant->type->label($languageCode),
                    'name' => $this->translatedText(
                        is_string($variant->getAttribute('localized_name'))
                            ? $variant->getAttribute('localized_name')
                            : null,
                        $variant->name,
                    ),
                    'price_cents' => $variant->price_cents,
                    'weight' => $variant->weight,
                    'volume' => $variant->volume,
                    'is_default' => $variant->is_default,
                ])
                ->values()
                ->all(),
            'modifier_groups' => $item->modifierGroups
                ->map(fn (ModifierGroup $modifierGroup): array => $this->modifierGroupPayload($modifierGroup))
                ->values()
                ->all(),
        ];
    }

    /** @param list<string> $selectedValues
     * @param list<array{value:string,label:string}> $options
     * @return list<array{value:string,label:string}> */
    private function selectedLabelOptions(array $selectedValues, array $options): array
    {
        return array_values(array_filter(
            $options,
            fn (array $option): bool => in_array($option['value'], $selectedValues, true),
        ));
    }

    /** @return array<string, mixed> */
    private function modifierGroupPayload(ModifierGroup $modifierGroup): array
    {
        return [
            'id' => $modifierGroup->id,
            'name' => $this->translatedText(
                is_string($modifierGroup->getAttribute('localized_name'))
                    ? $modifierGroup->getAttribute('localized_name')
                    : null,
                $modifierGroup->name,
            ),
            'is_required' => $modifierGroup->is_required,
            'min_select' => $modifierGroup->min_select,
            'max_select' => $modifierGroup->max_select,
            'options' => $modifierGroup->options->where('is_available', true)
                ->map(fn (ModifierOption $modifierOption): array => [
                    'id' => $modifierOption->id,
                    'name' => $this->translatedText(
                        is_string($modifierOption->getAttribute('localized_name'))
                            ? $modifierOption->getAttribute('localized_name')
                            : null,
                        $modifierOption->name,
                    ),
                    'price_delta_cents' => $modifierOption->price_delta_cents,
                ])
                ->values()
                ->all(),
        ];
    }

    private function translatedText(?string $translatedText, ?string $fallbackText): ?string
    {
        if (filled($translatedText)) {
            return $translatedText;
        }

        return $fallbackText;
    }
}
