<?php

declare(strict_types=1);

use App\Actions\Menus\CreateMenuItemVariantAction;
use App\Actions\Menus\DeleteMenuItemVariantAction;
use App\Actions\Menus\UpdateMenuItemVariantAction;
use App\Actions\Modifiers\CloneModifierGroupForMenuItemAction;
use App\Actions\Modifiers\CreateModifierGroupAction;
use App\Actions\Modifiers\DeleteModifierGroupAction;
use App\Actions\Modifiers\DeleteModifierOptionAction;
use App\Actions\Modifiers\UnassignModifierGroupFromMenuItemAction;
use App\Actions\Modifiers\UpdateModifierOptionAction;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\AuditLog;
use App\Models\MenuItemVariant;
use App\Models\MenuOperation;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\DishConfigurationFixtures;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('variant create replays its original result without adding another variant', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $requestId = (string) Str::uuid();
    $action = app(CreateMenuItemVariantAction::class);
    $first = $action->handle($actor, $branch, $item, dishVariantData('Large'), expectedVersion: 0, requestId: $requestId);
    $second = $action->handle($actor, $branch, $item, dishVariantData('Large'), expectedVersion: 0, requestId: $requestId);

    expect($second->id)->toBe($first->id)
        ->and($item->variants()->count())->toBe(1)
        ->and(MenuOperation::query()->where('request_id', $requestId)->count())->toBe(1);
});

test('variant aggregate rejects an ABA update even if the displayed values are the same again', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $variant = MenuItemVariant::factory()->for($item, 'item')->create(['name' => 'Original']);
    $version = (int) $item->fresh()->variants_version;
    $variant->update(['name' => 'Intermediate']);
    $variant->update(['name' => 'Original']);

    expect(fn () => app(UpdateMenuItemVariantAction::class)->handle(
        $actor, $branch, $variant, dishVariantData('Overwritten'), expectedVersion: $version, requestId: (string) Str::uuid(),
    ))->toThrow(ValidationException::class);
    expect($variant->fresh()->name)->toBe('Original');
});

test('variant completed replay reauthorizes the actor and binds exact payload', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $requestId = (string) Str::uuid();
    $action = app(CreateMenuItemVariantAction::class);
    $action->handle($actor, $branch, $item, dishVariantData('Large'), expectedVersion: 0, requestId: $requestId);

    expect(fn () => $action->handle($actor, $branch, $item, dishVariantData('Different'), expectedVersion: 0, requestId: $requestId))
        ->toThrow(AuthorizationException::class);
    $actor->roles()->detach();
    expect(fn () => $action->handle($actor, $branch, $item, dishVariantData('Large'), expectedVersion: 0, requestId: $requestId))
        ->toThrow(AuthorizationException::class);
    expect($item->variants()->count())->toBe(1);
});

test('omitted privileged fields preserve variant and modifier option price and stop flags', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $variant = MenuItemVariant::factory()->for($item, 'item')->create(['price_cents' => 2345, 'is_available' => false]);
    $data = dishVariantData('Renamed variant');
    unset($data['price'], $data['is_available']);
    app(UpdateMenuItemVariantAction::class)->handle($actor, $branch, $variant, $data);
    expect($variant->fresh()->price_cents)->toBe(2345)->and($variant->fresh()->is_available)->toBeFalse();

    $group = ModifierGroup::factory()->for($branch)->create();
    $option = ModifierOption::factory()->for($group, 'group')->create(['price_delta_cents' => 125, 'is_available' => false]);
    app(UpdateModifierOptionAction::class)->handle($actor, $branch, $option, ['name' => 'Renamed option', 'sort_order' => 0]);
    expect($option->fresh()->price_delta_cents)->toBe(125)->and($option->fresh()->is_available)->toBeFalse();
});

test('dish configuration actions validate untrusted input before any business or audit write', function (string $field, mixed $value): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $data = dishVariantData('Invalid variant');
    $data[$field] = $value;
    $auditCount = AuditLog::query()->count();
    expect(fn () => app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, $data, 0, (string) Str::uuid()))
        ->toThrow(ValidationException::class);
    expect($item->variants()->count())->toBe(0)->and($item->fresh()->variants_version)->toBe(0)
        ->and(MenuOperation::query()->count())->toBe(0)->and(AuditLog::query()->count())->toBe($auditCount);
})->with([
    'invalid enum' => ['type', 'foreign'],
    'name array' => ['name', ['bad']],
    'fractional cents' => ['price', '12.345'],
    'invalid default' => ['is_default', 'not-a-boolean'],
    'fractional sort' => ['sort_order', 1.2],
    'translation array' => ['translations', ['en' => ['bad']]],
]);

test('modifier actions reject impossible limits and normalized duplicate names inside their transaction', function (): void {
    [$actor, $branch] = DishConfigurationFixtures::context();
    $group = ModifierGroup::factory()->for($branch)->create(['name' => 'Existing group']);
    $action = app(CreateModifierGroupAction::class);
    $data = ['name' => 'Invalid group', 'is_required' => true, 'min_select' => 2, 'max_select' => 1, 'sort_order' => 0];
    expect(fn () => $action->handle($actor, $branch, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    $data['max_select'] = 0;
    $data['name'] = ' Existing   group ';
    expect(fn () => $action->handle($actor, $branch, $data, (string) Str::uuid()))->toThrow(ValidationException::class);
    expect(ModifierGroup::query()->where('branch_id', $branch->id)->count())->toBe(1)
        ->and(MenuOperation::query()->count())->toBe(0);
});

test('indirect variant and modifier price changes require price authority', function (string $operation): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $actor->roles()->detach();
    OrganizationUser::factory()->forUser($actor)->forOrganization($branch->organization)->forSystemRole(SystemRole::Waiter)->create();
    PermissionUserOverride::factory()->forUser($actor)->forOrganization($branch->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail())->allowed()->create();
    PermissionUserOverride::factory()->forUser($actor)->forOrganization($branch->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ChangePrices->value)->firstOrFail())->denied()->create();
    $variant = MenuItemVariant::factory()->for($item, 'item')->create(['is_default' => true]);
    $other = MenuItemVariant::factory()->for($item, 'item')->create(['is_default' => false]);
    $group = ModifierGroup::factory()->for($branch)->create();
    $option = ModifierOption::factory()->for($group, 'group')->create(['price_delta_cents' => 150]);
    $item->modifierGroups()->attach($group);
    $data = dishVariantData('Changed');
    $data['is_default'] = true;
    unset($data['price']);
    $groupVersion = $group->fresh()->content_version;
    $request = (string) Str::uuid();
    $run = match ($operation) {
        'variant default' => fn () => app(UpdateMenuItemVariantAction::class)->handle($actor, $branch, $other, $data),
        'variant delete' => fn () => app(DeleteMenuItemVariantAction::class)->handle($actor, $branch, $variant),
        'option delete' => fn () => app(DeleteModifierOptionAction::class)->handle($actor, $branch, $option),
        'group delete' => fn () => app(DeleteModifierGroupAction::class)->handle($actor, $branch, $group),
        'group detach' => fn () => app(UnassignModifierGroupFromMenuItemAction::class)->handle($actor, $branch, $item, $group),
        'group copy' => fn () => app(CloneModifierGroupForMenuItemAction::class)->handle($actor, $branch, $item, $group, 'Private group', 0, $groupVersion, $request),
    };
    expect($run)->toThrow(AuthorizationException::class);
    expect($item->variants()->count())->toBe(2)->and($variant->fresh()->is_default)->toBeTrue()
        ->and($item->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$group->id])
        ->and($option->fresh()->price_delta_cents)->toBe(150)->and(MenuOperation::query()->count())->toBe(0);
})->with(['variant default', 'variant delete', 'option delete', 'group delete', 'group detach', 'group copy']);

test('completed paid default variant creation replay still requires its original indirect price authority', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $actor->roles()->detach();
    OrganizationUser::factory()->forUser($actor)->forOrganization($branch->organization)->forSystemRole(SystemRole::Waiter)->create();
    $permission = Permission::query()->where('code', SystemPermission::ChangePrices->value)->firstOrFail();
    $allow = PermissionUserOverride::factory()->forUser($actor)->forOrganization($branch->organization)->forPermission($permission)->allowed()->create();
    PermissionUserOverride::factory()->forUser($actor)->forOrganization($branch->organization)
        ->forPermission(Permission::query()->where('code', SystemPermission::ManageMenu->value)->firstOrFail())->allowed()->create();
    MenuItemVariant::factory()->for($item, 'item')->create(['is_default' => true]);
    $data = dishVariantData('New default');
    $data['is_default'] = true;
    unset($data['price']);
    $version = $item->fresh()->variants_version;
    $request = (string) Str::uuid();
    app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, $data, $version, $request);
    $allow->update(['enabled' => false]);
    expect(fn () => app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, $data, $version, $request))->toThrow(AuthorizationException::class);
    expect($item->variants()->count())->toBe(2);
});

test('direct variant validation uses the active locale and translated field labels', function (string $locale): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $previous = app()->getLocale();
    app()->setLocale($locale);
    try {
        $data = dishVariantData('Invalid money');
        $data['price'] = '12.345';
        try {
            app(CreateMenuItemVariantAction::class)->handle($actor, $branch, $item, $data, 0, (string) Str::uuid());
            test()->fail('Invalid money was accepted.');
        } catch (ValidationException $exception) {
            $message = $exception->errors()['variantPrice'][0];
            expect($message)->toContain(__('guest.cart.price'))->not->toContain('variantPrice')
                ->not->toContain('validation.')->not->toContain(':attribute');
        }
    } finally {
        app()->setLocale($previous);
    }
})->with(['en', 'lt', 'ru']);

/** @return array<string,mixed> */
function dishVariantData(string $name): array
{
    return ['type' => 'portion', 'name' => $name, 'price' => '12.50', 'weight' => null, 'volume' => null,
        'is_default' => false, 'is_available' => true, 'sort_order' => 0,
        'translations' => ['en' => $name, 'lt' => $name, 'ru' => $name]];
}
