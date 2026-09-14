<?php

declare(strict_types=1);

use App\Actions\Menus\AddMenuItemImagesAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\MenuOperation;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\ParallelTesting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    ParallelTesting::resolveTokenUsing(fn (): string => 'menu-upload-replay-'.getmypid());
    Storage::fake('public');
    $this->actor = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->actor, ['name' => fake()->unique()->company()]);
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($brand)->for($organization)->create();
    $menu = Menu::factory()->for($this->branch)->active()->create();
    $category = MenuCategory::factory()->for($menu)->create();
    $this->item = MenuItem::factory()->for($menu)->for($category, 'category')->create();
});

test('committed image upload replay survives a lost response without duplicate files or gallery rows', function (): void {
    $requestId = Str::uuid()->toString();
    $files = array_map(fn (int $index): UploadedFile => UploadedFile::fake()->image($index.'.png', 800, 400), range(1, 8));
    expect(fn () => DB::transaction(function () use ($files, $requestId): void {
        app(AddMenuItemImagesAction::class)->handle($this->branch, $this->item, $files, $requestId, $this->actor);
        DB::afterCommit(fn () => throw new RuntimeException('Response lost after commit.'));
    }))->toThrow(RuntimeException::class, 'Response lost after commit.');
    $paths = Storage::disk('public')->allFiles();
    $item = app(AddMenuItemImagesAction::class)->handle($this->branch, $this->item, $files, $requestId, $this->actor);
    expect($item->galleryImages)->toHaveCount(7)
        ->and(Storage::disk('public')->allFiles())->toBe($paths)
        ->and($paths)->toHaveCount(16)
        ->and(MenuOperation::query()->sole()->phase->value)->toBe('completed');
});

test('image upload replay is bound to current actor branch and item ownership', function (): void {
    $requestId = Str::uuid()->toString();
    $files = [UploadedFile::fake()->image('image.png')];
    app(AddMenuItemImagesAction::class)->handle($this->branch, $this->item, $files, $requestId, $this->actor);
    $other = MenuItem::factory()->for($this->item->menu)->for($this->item->category, 'category')->create();
    expect(fn () => app(AddMenuItemImagesAction::class)->handle($this->branch, $other, $files, $requestId, $this->actor))->toThrow(AuthorizationException::class);
    expect(fn () => app(AddMenuItemImagesAction::class)->handle($this->branch, $this->item, $files, $requestId))->toThrow(AuthorizationException::class);
    $this->actor->organizations()->detach($this->branch->organization_id);
    expect(fn () => app(AddMenuItemImagesAction::class)->handle($this->branch, $this->item, $files, $requestId, $this->actor))->toThrow(AuthorizationException::class);
    expect(Storage::disk('public')->allFiles())->toHaveCount(1);
});

test('processor validation keeps the failing file index and rolls back the entire selected batch', function (): void {
    $files = [UploadedFile::fake()->image('good.png'), UploadedFile::fake()->image('too-wide.png', 9000, 1)];
    try {
        app(AddMenuItemImagesAction::class)->handle($this->branch, $this->item, $files, Str::uuid()->toString(), $this->actor);
        $this->fail('The pixel guard must reject this image.');
    } catch (ValidationException $exception) {
        expect(array_keys($exception->errors()))->toBe(['images.1']);
    }
    expect($this->item->fresh()->image)->toBeNull()
        ->and($this->item->galleryImages()->count())->toBe(0)
        ->and(MenuOperation::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});
