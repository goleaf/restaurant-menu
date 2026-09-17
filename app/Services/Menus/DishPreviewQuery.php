<?php

declare(strict_types=1);

namespace App\Services\Menus;

use App\Actions\DraftOrders\Support\CalculateDraftOrderLinePrice;
use App\Actions\Menus\BuildMenuItemAttributesAction;
use App\Data\Menus\MenuItemData;
use App\Models\Branch;
use App\Models\User;
use App\Services\Availability\AvailabilityEvaluator;
use App\Support\LocalImageVariants;
use App\Support\MenuImagePresentation;
use App\Support\MoneyFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class DishPreviewQuery
{
    public function __construct(
        private CatalogData $catalog,
        private GuestMenuItemPresenter $presenter,
        private AvailabilityEvaluator $availability,
        private CalculateDraftOrderLinePrice $price,
        private BuildMenuItemAttributesAction $attributes,
    ) {}

    /** @param array{menuId:int,categoryId:int,kitchenDepartmentId:?int,data:MenuItemData}|null $draft
     * @param  array<array-key,mixed>  $modifiers
     * @return array<string,mixed>
     */
    public function for(User $actor, Branch $branch, int $itemId, string $locale, ?int $variantId, array $modifiers, ?array $draft = null): array
    {
        $item = $this->catalog->findBranchItem($branch->id, $itemId);
        if ($draft !== null) {
            $menu = $this->catalog->findBranchMenu($branch, $draft['menuId']);
            $category = $this->catalog->findMenuCategory($menu, $draft['categoryId']);
            $item = clone $item;
            $item->forceFill($this->attributes->handle($actor, $branch, $menu, $category, $draft['kitchenDepartmentId'], $draft['data'], $item, seedMissingDepartment: false));
            $translation = $draft['data']->translations[$locale] ?? [];
        } else {
            $translation = $this->catalog->translationValues($item)[$locale] ?? [];
        }
        $item->setAttribute('localized_name', $translation['name'] ?? null);
        $item->setAttribute('has_localized_content', array_key_exists('description', $translation));
        $item->setAttribute('localized_description', $translation['description'] ?? null);
        $item->load([
            ...AvailabilityEvaluator::itemRelations(),
            'variants' => fn ($query) => $query->select(['id', 'menu_item_id', 'name', 'type', 'price_cents', 'weight', 'volume', 'is_default', 'is_available', 'sort_order'])->with('translations:id,menu_item_variant_id,language_code,name'),
            'modifierGroups' => fn ($query) => $query->select(['modifier_groups.id', 'branch_id', 'name', 'is_required', 'min_select', 'max_select', 'sort_order'])->where('branch_id', $branch->id)->with('translations:id,modifier_group_id,language_code,name'),
            'modifierGroups.options' => fn ($query) => $query->select(['id', 'modifier_group_id', 'name', 'price_delta_cents', 'is_available', 'sort_order'])->with('translations:id,modifier_option_id,language_code,name'),
        ]);
        foreach ($item->variants as $variant) {
            $variant->setAttribute('localized_name', $variant->translations->firstWhere('language_code', $locale)?->name);
        }
        foreach ($item->modifierGroups as $group) {
            $group->setAttribute('localized_name', $group->translations->firstWhere('language_code', $locale)?->name);
            foreach ($group->options as $option) {
                $option->setAttribute('localized_name', $option->translations->firstWhere('language_code', $locale)?->name);
            }
        }
        $at = CarbonImmutable::now();
        $availability = $this->availability->item($item, $at);
        $payload = $this->presenter->present($item, $locale, $availability);
        $images = [];
        if (filled($item->image)) {
            $images[] = [...LocalImageVariants::forPath($item->image), ...MenuImagePresentation::localized($item->image_presentation, $locale, $payload['name'])];
        }
        foreach ($item->galleryImages as $image) {
            $images[] = [...LocalImageVariants::forPath($image->path), ...MenuImagePresentation::localized($image->presentation, $locale, $payload['name'])];
        }
        $error = null;
        $formattedPrice = null;
        try {
            $price = $this->price->forMenuItem($item, $modifiers, 1, $variantId);
            $formattedPrice = MoneyFormatter::formatCents($price['total_price_cents'], $branch->currency);
        } catch (ValidationException $exception) {
            $error = collect($exception->errors())->flatten()->first();
        }

        return [...$payload, 'images' => $images, 'formatted_price' => $formattedPrice, 'configuration_error' => $error,
            'language' => $locale, 'availability' => $availability->toArray(), 'source' => $draft === null ? 'saved' : 'draft', 'evaluated_at' => $at->setTimezone($branch->timezone)->format('Y-m-d H:i:s'),
            'variants' => array_map(fn (array $variant): array => [...$variant, 'formatted_price' => MoneyFormatter::formatCents($variant['price_cents'], $branch->currency)], $payload['variants']),
            'modifier_groups' => array_map(fn (array $group): array => [...$group, 'selection_limits' => __($group['max_select'] === 0 ? 'dish.preview.minimum_selection' : 'dish.preview.selection_limits', ['min' => max($group['is_required'] ? 1 : 0, $group['min_select']), 'max' => $group['max_select']]), 'options' => array_map(fn (array $option): array => [...$option, 'formatted_price' => MoneyFormatter::formatCents($option['price_delta_cents'], $branch->currency)], $group['options'])], $payload['modifier_groups']),
        ];
    }
}
