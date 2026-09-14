<?php

declare(strict_types=1);

use App\Actions\Menus\ContinueMenuOperationAction;
use App\Actions\Menus\StartMenuDeletionAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuOperationPhase;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\MenuOperationCategory;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-operation-'.getmypid());
    Storage::fake('public');
});

function menuOperationContext(): array
{
    $actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($actor, ['name' => fake()->unique()->company()]);
    $brand = Brand::factory()->for($organization)->create();
    $branch = Branch::factory()->for($brand)->for($organization)->create();
    $menu = Menu::factory()->for($branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create();

    return [$actor, $branch, $menu, $category];
}

function finishMenuOperation(User $actor, Branch $branch, MenuOperation $operation): MenuOperation
{
    for ($attempt = 0; $attempt < 40 && ! $operation->progress()['completed']; $attempt++) {
        $operation = app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id);
    }
    expect($operation->progress()['completed'])->toBeTrue();

    return $operation;
}

test('durable operation schema and factories preserve indexed identities and private plans', function (): void {
    expect(Schema::hasColumns('menu_operations', ['request_id', 'branch_id', 'actor_user_id', 'pending_cleanup', 'payload', 'completed_at']))->toBeTrue();
    $operation = MenuOperation::factory()->create();
    $node = MenuOperationCategory::factory()->for($operation, 'operation')->create();
    expect($operation->pending_cleanup)->toBe([])
        ->and($operation->toArray())->not->toHaveKeys(['pending_cleanup', 'payload'])
        ->and($node->operation->id)->toBe($operation->id)
        ->and($node->category->menu_id)->toBe($operation->menu_id);
});

test('large menu deletion is resumable bounded and removes the root last', function (): void {
    [$actor, $branch, $menu, $category] = menuOperationContext();
    MenuItem::factory()->count(101)->for($menu)->for($category, 'category')->create();
    $requestId = Str::uuid()->toString();
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, $requestId);
    expect($menu->items()->count())->toBe(101);
    expect(app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, $requestId)->id)->toBe($operation->id);
    $operation = app(ContinueMenuOperationAction::class)->handle($actor, $branch, $requestId);
    expect($menu->items()->count())->toBe(51)
        ->and($operation->processed_count)->toBe(50)
        ->and($menu->fresh()->trashed())->toBeFalse();
    $operation = finishMenuOperation($actor, $branch, $operation);
    expect($menu->fresh()->trashed())->toBeTrue()
        ->and($category->fresh()->trashed())->toBeTrue()
        ->and($menu->items()->exists())->toBeFalse()
        ->and($operation->pending_cleanup)->toBe([]);
    expect(app(ContinueMenuOperationAction::class)->handle($actor, $branch, $requestId)->progress())->toBe($operation->progress());
});

test('category discovery terminates cycles and excludes malformed foreign menu links', function (): void {
    [$actor, $branch, $menu, $root] = menuOperationContext();
    $child = MenuCategory::factory()->childOf($root)->create();
    $root->update(['parent_id' => $child->id]);
    $owned = MenuItem::factory()->for($menu)->for($child, 'category')->create();
    $foreignMenu = Menu::factory()->create();
    $foreignCategory = MenuCategory::factory()->for($foreignMenu)->create(['parent_id' => $root->id]);
    $foreignItem = MenuItem::factory()->for($foreignMenu)->create(['category_id' => $root->id]);
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $root, Str::uuid()->toString());
    finishMenuOperation($actor, $branch, $operation);

    expect($root->fresh()->trashed())->toBeTrue()
        ->and($child->fresh()->trashed())->toBeTrue()
        ->and($owned->fresh()->trashed())->toBeTrue()
        ->and($foreignCategory->fresh()->trashed())->toBeFalse()
        ->and($foreignItem->fresh()->trashed())->toBeFalse()
        ->and($menu->fresh()->trashed())->toBeFalse();
});

test('failed post commit media cleanup leaves a durable bounded retry plan', function (): void {
    [$actor, $branch, $menu, $category] = menuOperationContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/durable-primary.jpg']);
    $image = MenuItemImage::factory()->for($item, 'item')->create(['path' => 'media/durable-secondary.jpg']);
    $disk = Storage::disk('public');
    $disk->put($item->image, 'primary');
    $disk->put($image->path, 'secondary');
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->andReturn(false);
    Storage::set('public', $proxy);
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, Str::uuid()->toString());

    expect(fn () => app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id))->toThrow(RuntimeException::class);
    expect($item->fresh()->trashed())->toBeTrue()
        ->and($item->galleryImages()->exists())->toBeFalse()
        ->and($operation->fresh()->pending_cleanup)->toBe([$item->image, $image->path])
        ->and($menu->fresh()->trashed())->toBeFalse();
    Storage::set('public', $disk);
    finishMenuOperation($actor, $branch, $operation->fresh());
    expect($disk->allFiles())->toBe([]);
});

test('a required deletion veto rolls back the batch and observer suppression cannot leak', function (): void {
    [$actor, $branch, $menu, $category] = menuOperationContext();
    MenuItem::factory()->count(3)->for($menu)->for($category, 'category')->create();
    $deleted = 0;
    MenuItem::deleting(function () use (&$deleted): bool {
        return ++$deleted !== 2;
    });
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, Str::uuid()->toString());
    expect(fn () => app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id))->toThrow(RuntimeException::class);
    expect($menu->items()->count())->toBe(3)
        ->and($operation->fresh()->processed_count)->toBe(0);
    $category->delete();
    expect($menu->items()->count())->toBe(0);
});

test('resume rejects a foreign actor branch and revoked membership without progress', function (string $case): void {
    [$actor, $branch, $menu] = menuOperationContext();
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, Str::uuid()->toString());
    [$otherActor, $otherBranch] = menuOperationContext();
    if ($case === 'actor') {
        $actor = $otherActor;
    }
    if ($case === 'branch') {
        $branch = $otherBranch;
    }
    if ($case === 'revoked') {
        $actor->organizations()->detach($branch->organization_id);
    }
    expect(fn () => app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id))->toThrow(AuthorizationException::class);
    expect($operation->fresh()->processed_count)->toBe(0)
        ->and($menu->fresh()->trashed())->toBeFalse();
})->with(['actor', 'branch', 'revoked']);

test('operation start rejects malformed UUID and foreign targets before creating a ledger', function (): void {
    [$actor, $branch, $menu] = menuOperationContext();
    expect(fn () => app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, 'invalid'))->toThrow(ValidationException::class);
    $otherMenu = Menu::factory()->create();
    expect(fn () => app(StartMenuDeletionAction::class)->handle($actor, $branch, $otherMenu, Str::uuid()->toString()))->toThrow(AuthorizationException::class);
    expect(MenuOperation::query()->count())->toBe(0);
});

test('outer rollback restores staged operation progress and retains all files', function (): void {
    [$actor, $branch, $menu, $category] = menuOperationContext();
    $item = MenuItem::factory()->for($menu)->for($category, 'category')->create(['image' => 'media/outer-original.jpg']);
    Storage::disk('public')->put($item->image, 'original');
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, Str::uuid()->toString());
    expect(fn () => DB::transaction(function () use ($actor, $branch, $operation): never {
        app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id);
        throw new RuntimeException('Outer failure.');
    }))->toThrow(RuntimeException::class, 'Outer failure.');
    expect($item->fresh()->trashed())->toBeFalse()
        ->and($operation->fresh()->processed_count)->toBe(0)
        ->and($operation->fresh()->pending_cleanup)->toBe([]);
    Storage::disk('public')->assertExists($item->image);
});

test('late category descendants extend the frontier without rewriting previously scanned nodes', function (): void {
    [$actor, $branch, $menu, $root] = menuOperationContext();
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $root, Str::uuid()->toString());
    $oldCategories = MenuCategory::factory()->count(51)->childOf($root)->create(['deleted_at' => now()]);
    foreach ($oldCategories as $category) {
        MenuOperationCategory::factory()->for($operation, 'operation')->for($category, 'category')->discovered()->create();
    }
    $operation->categories()->update(['discovered' => true]);
    $operation->forceFill(['phase' => MenuOperationPhase::Finalizing])->save();
    $late = MenuCategory::factory()->childOf($root)->create();
    $lateItem = MenuItem::factory()->for($menu)->for($late, 'category')->create();
    $operation = app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id);
    expect($operation->categories()->where('discovered', true)->count())->toBe(52)
        ->and($root->fresh()->trashed())->toBeFalse();
    finishMenuOperation($actor, $branch, $operation);
    expect($late->fresh()->trashed())->toBeTrue()->and($lateItem->fresh()->trashed())->toBeTrue();
});

test('a full image batch durably retains at most four hundred canonical cleanup paths', function (): void {
    [$actor, $branch, $menu, $category] = menuOperationContext();
    $items = MenuItem::factory()->count(50)->for($menu)->for($category, 'category')
        ->state(fn (): array => ['image' => 'media/'.Str::uuid().'.jpg'])
        ->has(MenuItemImage::factory()->count(7)->sequence(fn (Sequence $sequence): array => ['path' => 'media/'.Str::uuid().'.jpg', 'sort_order' => $sequence->index]), 'galleryImages')
        ->create();
    $disk = Storage::disk('public');
    $items->load('galleryImages');
    foreach ($items as $item) {
        $disk->put($item->image, 'primary');
        foreach ($item->galleryImages as $image) {
            $disk->put($image->path, 'secondary');
        }
    }
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->andReturn(false);
    Storage::set('public', $proxy);
    $operation = app(StartMenuDeletionAction::class)->handle($actor, $branch, $menu, Str::uuid()->toString());
    expect(fn () => app(ContinueMenuOperationAction::class)->handle($actor, $branch, $operation->request_id))->toThrow(RuntimeException::class);
    expect($operation->fresh()->pending_cleanup)->toHaveCount(400)
        ->and($operation->fresh()->processed_count)->toBe(50)
        ->and($menu->fresh()->trashed())->toBeFalse()
        ->and(MenuItemImage::query()->count())->toBe(0);
    Storage::set('public', $disk);
    finishMenuOperation($actor, $branch, $operation->fresh());
    expect($disk->allFiles())->toBe([]);
});

test('operation migration rolls back only its new tables and supports historical model writes', function (): void {
    $migration = require database_path('migrations/2026_09_14_160946_create_menu_operations_tables.php');
    $migration->down();
    expect(Schema::hasTable('menu_operations'))->toBeFalse()
        ->and(Schema::hasTable('menu_operation_categories'))->toBeFalse();
    $item = MenuItem::factory()->withTranslations()->create();
    $migration->up();
    expect(MenuItem::query()->findOrFail($item->id)->translations()->count())->toBe(3)
        ->and(Schema::hasIndex('menu_operations', ['request_id'], 'unique'))->toBeTrue()
        ->and(Schema::hasIndex('menu_operations', ['kind', 'target_id']))->toBeTrue()
        ->and(Schema::hasIndex('menu_operation_categories', ['menu_operation_id', 'menu_category_id'], 'unique'))->toBeTrue();
    expect(MenuOperation::factory()->create()->exists)->toBeTrue();
});
