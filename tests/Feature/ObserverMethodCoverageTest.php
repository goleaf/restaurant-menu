<?php

declare(strict_types=1);

use App\Observers\BranchObserver;
use App\Observers\BranchSettingObserver;
use App\Observers\BrandObserver;
use App\Observers\DraftOrderObserver;
use App\Observers\KitchenDepartmentObserver;
use App\Observers\KitchenTicketItemObserver;
use App\Observers\KitchenTicketObserver;
use App\Observers\ManualPaymentObserver;
use App\Observers\MenuAvailabilityScheduleObserver;
use App\Observers\MenuCategoryObserver;
use App\Observers\MenuCategoryTranslationObserver;
use App\Observers\MenuItemTranslationObserver;
use App\Observers\MenuItemVariantTranslationObserver;
use App\Observers\MenuObserver;
use App\Observers\ModifierGroupObserver;
use App\Observers\ModifierOptionObserver;
use App\Observers\OrderItemObserver;
use App\Observers\OrderObserver;
use App\Observers\OrganizationObserver;
use App\Observers\TableSessionObserver;
use Illuminate\Database\Eloquent\Model;

test('observer lifecycle hook accepts a persisted model and completes its invalidation work', function (string $observerClass, string $hook): void {
    $observer = app($observerClass);
    $method = new ReflectionMethod($observer, $hook);
    $parameterType = $method->getParameters()[0]->getType();

    expect($parameterType)->toBeInstanceOf(ReflectionNamedType::class);

    /** @var class-string<Model> $modelClass */
    $modelClass = $parameterType->getName();
    $model = $modelClass::factory()->create();

    expect($method->invoke($observer, $model))->toBeNull();
})->with([
    'branch restored' => [BranchObserver::class, 'restored'],
    'branch force deleted' => [BranchObserver::class, 'forceDeleted'],
    'branch setting deleted' => [BranchSettingObserver::class, 'deleted'],
    'branch setting restored' => [BranchSettingObserver::class, 'restored'],
    'branch setting force deleted' => [BranchSettingObserver::class, 'forceDeleted'],
    'brand restored' => [BrandObserver::class, 'restored'],
    'brand force deleted' => [BrandObserver::class, 'forceDeleted'],
    'draft order deleted' => [DraftOrderObserver::class, 'deleted'],
    'draft order restored' => [DraftOrderObserver::class, 'restored'],
    'draft order force deleted' => [DraftOrderObserver::class, 'forceDeleted'],
    'kitchen department restored' => [KitchenDepartmentObserver::class, 'restored'],
    'kitchen department force deleted' => [KitchenDepartmentObserver::class, 'forceDeleted'],
    'kitchen ticket item deleted' => [KitchenTicketItemObserver::class, 'deleted'],
    'kitchen ticket item restored' => [KitchenTicketItemObserver::class, 'restored'],
    'kitchen ticket item force deleted' => [KitchenTicketItemObserver::class, 'forceDeleted'],
    'kitchen ticket deleted' => [KitchenTicketObserver::class, 'deleted'],
    'kitchen ticket restored' => [KitchenTicketObserver::class, 'restored'],
    'kitchen ticket force deleted' => [KitchenTicketObserver::class, 'forceDeleted'],
    'manual payment updated' => [ManualPaymentObserver::class, 'updated'],
    'manual payment deleted' => [ManualPaymentObserver::class, 'deleted'],
    'manual payment restored' => [ManualPaymentObserver::class, 'restored'],
    'manual payment force deleted' => [ManualPaymentObserver::class, 'forceDeleted'],
    'menu schedule restored' => [MenuAvailabilityScheduleObserver::class, 'restored'],
    'menu schedule force deleted' => [MenuAvailabilityScheduleObserver::class, 'forceDeleted'],
    'menu category force deleted' => [MenuCategoryObserver::class, 'forceDeleted'],
    'menu category translation deleted' => [MenuCategoryTranslationObserver::class, 'deleted'],
    'menu category translation restored' => [MenuCategoryTranslationObserver::class, 'restored'],
    'menu category translation force deleted' => [MenuCategoryTranslationObserver::class, 'forceDeleted'],
    'menu item translation deleted' => [MenuItemTranslationObserver::class, 'deleted'],
    'menu item translation restored' => [MenuItemTranslationObserver::class, 'restored'],
    'menu item translation force deleted' => [MenuItemTranslationObserver::class, 'forceDeleted'],
    'menu variant translation deleted' => [MenuItemVariantTranslationObserver::class, 'deleted'],
    'menu force deleted' => [MenuObserver::class, 'forceDeleted'],
    'modifier group restored' => [ModifierGroupObserver::class, 'restored'],
    'modifier group force deleted' => [ModifierGroupObserver::class, 'forceDeleted'],
    'modifier option restored' => [ModifierOptionObserver::class, 'restored'],
    'modifier option force deleted' => [ModifierOptionObserver::class, 'forceDeleted'],
    'order item deleted' => [OrderItemObserver::class, 'deleted'],
    'order item restored' => [OrderItemObserver::class, 'restored'],
    'order item force deleted' => [OrderItemObserver::class, 'forceDeleted'],
    'order deleted' => [OrderObserver::class, 'deleted'],
    'order restored' => [OrderObserver::class, 'restored'],
    'order force deleted' => [OrderObserver::class, 'forceDeleted'],
    'organization restored' => [OrganizationObserver::class, 'restored'],
    'organization force deleted' => [OrganizationObserver::class, 'forceDeleted'],
    'table session deleted' => [TableSessionObserver::class, 'deleted'],
    'table session restored' => [TableSessionObserver::class, 'restored'],
    'table session force deleted' => [TableSessionObserver::class, 'forceDeleted'],
]);
