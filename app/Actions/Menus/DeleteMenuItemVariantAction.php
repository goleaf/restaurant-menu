<?php

declare(strict_types=1);

namespace App\Actions\Menus;

use App\Enums\MenuOperationKind;
use App\Models\Branch;
use App\Models\MenuItemVariant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

class DeleteMenuItemVariantAction
{
    public function __construct(private readonly RunDishConfigurationCommandAction $commands) {}

    public function handle(User $actor, Branch $branch, MenuItemVariant|int $variant, ?int $expectedVersion = null, ?string $requestId = null, ?int $expectedItemId = null): void
    {
        $variantId = $variant instanceof MenuItemVariant ? $variant->id : $variant;
        $expectedItemId ??= $variant instanceof MenuItemVariant ? $variant->menu_item_id : null;
        if ($expectedItemId === null) {
            throw new AuthorizationException;
        }
        $this->commands->handle($actor, $branch, MenuOperationKind::VariantChange, $variantId,
            ['operation' => 'delete_variant', 'item_id' => $expectedItemId, 'expected_version' => $expectedVersion], $requestId,
            function (User $actor, Branch $branch) use ($variantId, $expectedItemId, $expectedVersion): array {
                $variant = MenuItemVariant::query()
                    ->select(['id', 'menu_item_id', 'name', 'price_cents', 'is_default'])
                    ->with('item:id,menu_id,price_cents,variants_version')
                    ->where('menu_item_id', $expectedItemId)
                    ->whereHas('item.menu', fn ($query) => $query->where('branch_id', $branch->id))
                    ->whereKey($variantId)
                    ->lockForUpdate()
                    ->first();

                if (! $variant instanceof MenuItemVariant) {
                    throw new InvalidArgumentException('The menu item variant must belong to the selected branch.');
                }

                $item = $variant->item;
                $this->commands->assertVersion($item->variants_version, $expectedVersion);
                Gate::forUser($actor)->authorize('changeMenuPrices', $branch);
                $wasDefault = $variant->is_default;
                if ($variant->deleteOrFail() !== true) {
                    throw new \RuntimeException('The variant could not be deleted.');
                }
                if ($wasDefault) {
                    $replacement = $item->variants()->where('is_available', true)->first()
                        ?? $item->variants()->first();
                    if ($replacement instanceof MenuItemVariant && $replacement->updateOrFail(['is_default' => true]) !== true) {
                        throw new \RuntimeException('The default variant could not be saved.');
                    }
                }

                return ['item_id' => $item->id, 'menu_id' => $item->menu_id,
                    'entity_type' => 'menu_item', 'entity_id' => $item->id, 'before' => ['variant_id' => $variant->id, 'name' => $variant->name],
                    'required_abilities' => ['changeMenuPrices']];
            });
    }
}
