<?php

declare(strict_types=1);

use App\Actions\Menus\RemoveMenuItemImageAction;
use App\Actions\Menus\ResumeMenuImageCleanupAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\MenuOperationKind;
use App\Livewire\Organizations\Brands\Branches\Menu\Dish;
use App\Models\Branch;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuItemImage;
use App\Models\MenuOperation;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    ParallelTesting::resolveTokenUsing(fn (): string => 'media-cleanup-'.getmypid());
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => 'Media cleanup scope']);
    $this->branch = Branch::factory()->for($organization)->create();
    $this->menu = Menu::factory()->for($this->branch)->active()->create();
    $this->category = MenuCategory::factory()->for($this->menu)->active()->create();
    $this->item = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create(['image' => 'media/cleanup-original.png']);
    $this->gallery = MenuItemImage::factory()->for($this->item, 'item')->create(['path' => 'media/cleanup-promoted.png']);
    $disk = Storage::disk('public');
    $disk->put($this->item->image, 'Original image');
    $disk->put($this->gallery->path, 'Retained image');
    $this->requestId = (string) Str::uuid();
    $proxy = Mockery::mock($disk);
    $proxy->shouldReceive('delete')->andReturn(false);
    Storage::set('public', $proxy);
    expect(fn () => app(RemoveMenuItemImageAction::class)->handle($this->branch, $this->item,
        hash('sha256', $this->item->image), $this->requestId, $this->actor))->toThrow(RuntimeException::class);
    Storage::set('public', $disk);
});

test('dedicated image cleanup completes only its committed removal and safely repeats', function (): void {
    expect($this->item->fresh()->image)->toBe('media/cleanup-promoted.png');
    $operation = app(ResumeMenuImageCleanupAction::class)->handle($this->actor, $this->branch, $this->item->id, $this->requestId);
    expect($operation->completed_at)->not->toBeNull()
        ->and($operation->pending_cleanup)->toBe([])
        ->and($this->item->fresh()->image)->toBe('media/cleanup-promoted.png')
        ->and($this->menu->fresh()->trashed())->toBeFalse()
        ->and($this->category->fresh()->trashed())->toBeFalse();
    Storage::disk('public')->assertMissing('media/cleanup-original.png');
    Storage::disk('public')->assertExists('media/cleanup-promoted.png');
    $version = $this->item->fresh()->media_version;
    $again = app(ResumeMenuImageCleanupAction::class)->handle($this->actor, $this->branch, $this->item->id, $this->requestId);
    expect($again->completed_at->equalTo($operation->completed_at))->toBeTrue()
        ->and($this->item->fresh()->media_version)->toBe($version)
        ->and(MenuOperation::query()->count())->toBe(1);
});

test('image cleanup never runs for a foreign actor item branch or a revoked membership', function (string $case): void {
    $actor = $this->actor;
    $branch = $this->branch;
    $itemId = $this->item->id;
    if ($case === 'actor') {
        $actor = User::factory()->create();
    } elseif ($case === 'item') {
        $itemId = MenuItem::factory()->for($this->menu)->for($this->category, 'category')->create()->id;
    } elseif ($case === 'branch') {
        $branch = Branch::factory()->for($this->branch->organization)->create();
    } else {
        $actor->organizations()->detach($branch->organization_id);
    }
    expect(fn () => app(ResumeMenuImageCleanupAction::class)->handle($actor, $branch, $itemId, $this->requestId))
        ->toThrow(AuthorizationException::class);
    expect(MenuOperation::query()->sole()->completed_at)->toBeNull();
    Storage::disk('public')->assertExists(['media/cleanup-original.png', 'media/cleanup-promoted.png']);
})->with(['actor', 'item', 'branch', 'revoked']);

test('image cleanup refuses a valid UUID belonging to another operation kind', function (): void {
    $operation = MenuOperation::factory()->create(['request_id' => (string) Str::uuid(), 'branch_id' => $this->branch->id,
        'menu_id' => $this->menu->id, 'actor_user_id' => $this->actor->id, 'target_id' => $this->item->id,
        'kind' => MenuOperationKind::DeleteMenu, 'pending_cleanup' => ['media/cleanup-promoted.png']]);
    expect(fn () => app(ResumeMenuImageCleanupAction::class)->handle($this->actor, $this->branch, $this->item->id, $operation->request_id))
        ->toThrow(AuthorizationException::class);
    expect($operation->fresh()->completed_at)->toBeNull();
    Storage::disk('public')->assertExists(['media/cleanup-original.png', 'media/cleanup-promoted.png']);
});

test('an enclosing rollback does not flush media before the actual commit', function (): void {
    expect(fn () => DB::transaction(function (): never {
        app(ResumeMenuImageCleanupAction::class)->handle($this->actor, $this->branch, $this->item->id, $this->requestId);
        throw new RuntimeException('Outer transaction was rejected');
    }))->toThrow(RuntimeException::class, 'Outer transaction was rejected');
    expect(MenuOperation::query()->sole()->completed_at)->toBeNull();
    Storage::disk('public')->assertExists(['media/cleanup-original.png', 'media/cleanup-promoted.png']);
    app(ResumeMenuImageCleanupAction::class)->handle($this->actor, $this->branch, $this->item->id, $this->requestId);
    Storage::disk('public')->assertMissing('media/cleanup-original.png');
    Storage::disk('public')->assertExists('media/cleanup-promoted.png');
});

test('a fresh dish card restores its scoped cleanup continuation and preserves other drafts when retrying', function (): void {
    Livewire::actingAs($this->actor)->test(Dish::class, [
        'organization' => $this->branch->organization, 'brand' => $this->branch->brand,
        'branch' => $this->branch, 'item' => $this->item,
    ])->call('selectSection', 'photos')
        ->assertSet('pendingImageOperationRequestId', $this->requestId)
        ->assertSee('wire:click="retryItemImageCleanup"', false)
        ->set('editingItemForm.itemTranslations.en.name', 'Retain unsaved dish title')
        ->set('itemImageUploads.'.$this->item->id, [UploadedFile::fake()->image('pending.png', 100, 100)])
        ->call('retryItemImageCleanup')
        ->assertHasNoErrors()
        ->assertSet('pendingImageOperationRequestId', null)
        ->assertDontSee('wire:click="retryItemImageCleanup"', false)
        ->assertSet('editingItemForm.itemTranslations.en.name', 'Retain unsaved dish title')
        ->assertSet('itemImageUploads.'.$this->item->id, fn (array $files): bool => count($files) === 1);
    expect($this->item->fresh()->name)->not->toBe('Retain unsaved dish title')
        ->and($this->item->fresh()->image)->toBe('media/cleanup-promoted.png')
        ->and(MenuOperation::query()->sole()->completed_at)->not->toBeNull();
});
