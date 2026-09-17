<?php

declare(strict_types=1);

use App\Actions\Modifiers\CloneModifierGroupForMenuItemAction;
use App\Models\AuditLog;
use App\Models\MenuItem;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\DishConfigurationFixtures;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('copying a shared modifier group replaces only the selected dish link and replays once', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $other = MenuItem::factory()->for($item->menu)->for($item->category, 'category')->create();
    $group = ModifierGroup::factory()->for($branch)->withTranslations()->create();
    $option = ModifierOption::factory()->for($group, 'group')->withTranslations()->create(['price_delta_cents' => 125]);
    $item->modifierGroups()->attach($group);
    $other->modifierGroups()->attach($group);
    $version = (int) $group->fresh()->content_version;
    $requestId = (string) Str::uuid();
    $action = app(CloneModifierGroupForMenuItemAction::class);
    $copy = $action->handle($actor, $branch, $item, $group, 'Private extras', 0, $version, $requestId);
    $replay = $action->handle($actor, $branch, $item, $group, 'Private extras', 0, $version, $requestId);

    expect($copy->id)->not->toBe($group->id)->and($replay->id)->toBe($copy->id)
        ->and($item->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$copy->id])
        ->and($other->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$group->id])
        ->and($copy->options->sole()->id)->not->toBe($option->id)
        ->and($copy->options->sole()->price_delta_cents)->toBe(125)
        ->and($copy->options->sole()->translations->pluck('name', 'language_code')->all())->toBe($option->translations->pluck('name', 'language_code')->all())
        ->and($group->fresh()->content_version)->toBe($version + 1)
        ->and($item->fresh()->modifier_links_version)->toBe(1);
});

test('shared modifier copy rejects stale source and rolls back clone and link when audit fails', function (): void {
    [$actor, $branch, $item] = DishConfigurationFixtures::context();
    $group = ModifierGroup::factory()->for($branch)->create();
    $item->modifierGroups()->attach($group);
    $version = (int) $group->fresh()->content_version;
    $group->update(['name' => 'Changed source']);
    $action = app(CloneModifierGroupForMenuItemAction::class);
    expect(fn () => $action->handle($actor, $branch, $item, $group, 'Private extras', 0, $version, (string) Str::uuid()))
        ->toThrow(ValidationException::class);

    AuditLog::creating(fn (): bool => false);
    try {
        expect(fn () => $action->handle($actor, $branch, $item, $group, 'Private extras', 0, (int) $group->fresh()->content_version, (string) Str::uuid()))
            ->toThrow(RuntimeException::class);
    } finally {
        AuditLog::flushEventListeners();
    }
    expect($item->modifierGroups()->pluck('modifier_groups.id')->all())->toBe([$group->id])
        ->and(ModifierGroup::query()->where('branch_id', $branch->id)->count())->toBe(1)
        ->and($item->fresh()->modifier_links_version)->toBe(0);
});
