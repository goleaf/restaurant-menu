<?php

declare(strict_types=1);

use App\Actions\Menus\SyncMenuItemTranslationsAction;
use App\Models\MenuItem;
use App\Models\MenuItemTranslation;
use Illuminate\Support\Facades\DB;

test('clearing an explicit optional translation advances the content revision and omitted locales remain', function (): void {
    $item = MenuItem::factory()->create();
    MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => 'en', 'name' => 'Original']);
    MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => 'lt', 'name' => 'Vertimas']);
    $version = $item->fresh()->content_version;
    app(SyncMenuItemTranslationsAction::class)->handle($item, ['lt' => ['name' => null]]);
    expect($item->fresh()->content_version)->toBeGreaterThan($version)
        ->and($item->translations()->where('language_code', 'lt')->exists())->toBeFalse()
        ->and($item->translations()->where('language_code', 'en')->value('name'))->toBe('Original');
    $version = $item->fresh()->content_version;
    app(SyncMenuItemTranslationsAction::class)->handle($item, ['lt' => ['name' => null]]);
    expect($item->fresh()->content_version)->toBe($version);
});

test('a refused translation deletion rolls back its surrounding content write', function (): void {
    $item = MenuItem::factory()->create(['description' => 'Before']);
    MenuItemTranslation::factory()->for($item, 'item')->create(['language_code' => 'lt', 'name' => 'Vertimas']);
    MenuItemTranslation::deleting(fn (): bool => false);
    expect(fn () => DB::transaction(function () use ($item): void {
        $item->update(['description' => 'After']);
        app(SyncMenuItemTranslationsAction::class)->handle($item, ['lt' => ['name' => null]]);
    }))->toThrow(RuntimeException::class);
    expect($item->fresh()->description)->toBe('Before')
        ->and($item->translations()->where('language_code', 'lt')->value('name'))->toBe('Vertimas');
});
