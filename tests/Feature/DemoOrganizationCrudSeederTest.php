<?php

declare(strict_types=1);

use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Models\AreaNode;
use App\Models\AreaNodeWaiter;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\KitchenDepartment;
use App\Models\Menu;
use App\Models\MenuAvailabilitySchedule;
use App\Models\MenuItem;
use App\Models\MenuItemVariant;
use App\Models\ModifierGroup;
use App\Models\ModifierOption;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\PermissionUserOverride;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Database\Factories\MenuAvailabilityScheduleFactory;
use Database\Seeders\DemoOrganizationCrudSeeder;
use Database\Seeders\DemoRestaurantSeeder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
});

test('both demo seeders refuse production before writing any data', function (): void {
    config()->set('app.env', 'production');

    expect(fn () => $this->seed(DemoOrganizationCrudSeeder::class))
        ->toThrow(LogicException::class, 'Demo organization CRUD data cannot be seeded in production.')
        ->and(fn () => $this->seed(DemoRestaurantSeeder::class))
        ->toThrow(RuntimeException::class, 'DemoRestaurantSeeder is development-only and cannot run while APP_ENV=production.');

    expect(Organization::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

test('the CRUD seeder requires the canonical demo organization graph', function (): void {
    expect(fn () => $this->seed(DemoOrganizationCrudSeeder::class))
        ->toThrow(ModelNotFoundException::class);
});

test('the parent seeder creates every missing organization administration fixture through factories', function (): void {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = demoCrudOrganization();
    $branches = demoCrudBranches($organization);
    $branchIds = $branches->pluck('id');
    $menuIds = Menu::query()
        ->whereIn('branch_id', $branchIds)
        ->orderBy('id')
        ->pluck('id');
    $memberUserIds = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->pluck('user_id');

    expect($branches)->toHaveCount(4)
        ->and(BranchOpeningHour::query()->whereIn('branch_id', $branchIds)->count())->toBe(29)
        ->and(BranchOpeningHour::query()->whereIn('branch_id', $branchIds)->distinct()->count('day_of_week'))->toBe(7)
        ->and(BranchOpeningHour::query()->whereIn('branch_id', $branchIds)->where('is_closed', true)->exists())->toBeTrue()
        ->and(BranchOpeningHour::query()->whereIn('branch_id', $branchIds)->select(['branch_id', 'day_of_week'])->get()->groupBy(fn (BranchOpeningHour $hour): string => $hour->branch_id.':'.$hour->day_of_week)->max->count())->toBe(2)
        ->and(MenuAvailabilitySchedule::query()->whereIn('menu_id', $menuIds)->count())->toBe(8)
        ->and(MenuAvailabilitySchedule::query()->whereIn('menu_id', $menuIds)->where('starts_at', '11:00')->count())->toBe(4)
        ->and(MenuAvailabilitySchedule::query()->whereIn('menu_id', $menuIds)->where('starts_at', '12:00')->count())->toBe(4)
        ->and(ModifierGroup::query()->whereIn('branch_id', $branchIds)->count())->toBe(8)
        ->and(ModifierOption::query()->whereHas('group', fn ($query) => $query->whereIn('branch_id', $branchIds))->count())->toBe(24)
        ->and(ModifierOption::query()->whereHas('group', fn ($query) => $query->whereIn('branch_id', $branchIds))->where('price_delta_cents', 0)->exists())->toBeTrue()
        ->and(ModifierOption::query()->whereHas('group', fn ($query) => $query->whereIn('branch_id', $branchIds))->where('price_delta_cents', '>', 0)->exists())->toBeTrue()
        ->and(ModifierOption::query()->whereHas('group', fn ($query) => $query->whereIn('branch_id', $branchIds))->where('price_delta_cents', '<', 0)->exists())->toBeTrue()
        ->and(ModifierOption::query()->whereHas('group', fn ($query) => $query->whereIn('branch_id', $branchIds))->where('is_available', false)->exists())->toBeTrue()
        ->and(MenuItem::query()->whereIn('menu_id', $menuIds)->whereHas('modifierGroups')->count())->toBe(4)
        ->and(ModifierGroup::query()->whereIn('branch_id', $branchIds)->whereDoesntHave('items')->count())->toBe(4)
        ->and(AreaNodeWaiter::query()->where('organization_id', $organization->id)->count())->toBe(2);

    $terrace = $branches->firstWhere('name', 'Bella Pizza Terrace');
    $coffeeBar = $branches->firstWhere('name', 'Coffee Bar Small Hall');

    expect($terrace)->toBeInstanceOf(Branch::class)
        ->and($terrace->is_temporarily_closed)->toBeTrue()
        ->and($terrace->temporary_closed_reason)->toBe('Planned CRUD demonstration closure')
        ->and($coffeeBar)->toBeInstanceOf(Branch::class)
        ->and($coffeeBar->is_active)->toBeFalse();

    expect(Invitation::query()->where('organization_id', $organization->id)->count())->toBe(3)
        ->and(Invitation::query()->where('organization_id', $organization->id)->pluck('status')->all())
        ->toEqualCanonicalizing([
            InvitationStatus::Pending,
            InvitationStatus::Expired,
            InvitationStatus::Cancelled,
        ])
        ->and(Invitation::query()->where('organization_id', $organization->id)->whereNotNull('invite_token')->exists())->toBeFalse()
        ->and(Invitation::query()->where('organization_id', $organization->id)->whereNotNull('invite_code')->exists())->toBeFalse()
        ->and(PermissionUserOverride::query()->whereIn('user_id', $memberUserIds)->count())->toBe(2)
        ->and(PermissionUserOverride::query()->whereIn('user_id', $memberUserIds)->where('enabled', true)->count())->toBe(1)
        ->and(PermissionUserOverride::query()->whereIn('user_id', $memberUserIds)->where('enabled', false)->count())->toBe(1)
        ->and(OrganizationUser::query()->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Suspended->value)->count())->toBe(1)
        ->and(OrganizationUser::query()->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Removed->value)->count())->toBe(1)
        ->and(BranchUser::query()->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Suspended->value)->count())->toBe(1)
        ->and(BranchUser::query()->where('organization_id', $organization->id)->where('status', OrganizationUserStatus::Removed->value)->count())->toBe(1);

    expect(AreaNode::query()->whereIn('branch_id', $branchIds)->where('name', DemoOrganizationCrudSeeder::INACTIVE_AREA_NAME)->where('is_active', false)->exists())->toBeTrue()
        ->and(ServicePoint::query()->whereIn('branch_id', $branchIds)->where('internal_code', DemoOrganizationCrudSeeder::INACTIVE_SERVICE_POINT_CODE)->where('is_active', false)->exists())->toBeTrue()
        ->and(KitchenDepartment::query()->whereIn('branch_id', $branchIds)->where('name', DemoOrganizationCrudSeeder::INACTIVE_DEPARTMENT_NAME)->where('is_active', false)->exists())->toBeTrue()
        ->and(MenuItem::query()->whereIn('menu_id', $menuIds)->where('name', DemoOrganizationCrudSeeder::INACTIVE_ITEM_NAME)->where('is_available', false)->exists())->toBeTrue()
        ->and(MenuItemVariant::query()->whereHas('item', fn ($query) => $query->whereIn('menu_id', $menuIds))->where('name', DemoOrganizationCrudSeeder::INACTIVE_VARIANT_NAME)->where('is_available', false)->exists())->toBeTrue();

    $mediaPaths = demoCrudMediaPaths($organization);

    expect($mediaPaths)->not->toBeEmpty();
    Storage::disk('public')->assertExists($mediaPaths);
});

test('the CRUD seeder uses reusable weekday and weekend schedule states', function (): void {
    $weekday = MenuAvailabilityScheduleFactory::new()->weekday(2)->make();
    $weekend = MenuAvailabilityScheduleFactory::new()->weekend(6)->make();

    expect($weekday->day_of_week)->toBe(2)
        ->and($weekday->starts_at)->toBe('11:00')
        ->and($weekday->ends_at)->toBe('15:00')
        ->and($weekend->day_of_week)->toBe(6)
        ->and($weekend->starts_at)->toBe('12:00')
        ->and($weekend->ends_at)->toBe('23:00');
});

test('the CRUD seeder is tenant-safe idempotent and restores only its owned soft-deleted fixture', function (): void {
    $unrelatedOrganization = Organization::factory()->create(['name' => 'Unrelated Restaurant Group']);
    $unrelatedBranch = Branch::factory()->for($unrelatedOrganization)->create(['name' => 'Unrelated Branch']);
    $unrelatedArea = AreaNode::factory()->forBranch($unrelatedBranch)->create(['name' => 'Unrelated archived area']);
    $unrelatedSnapshot = [
        'organization' => $unrelatedOrganization->refresh()->only(['id', 'owner_user_id', 'name', 'logo_path', 'deleted_at']),
        'branch' => $unrelatedBranch->refresh()->only([
            'id',
            'organization_id',
            'brand_id',
            'name',
            'is_active',
            'is_temporarily_closed',
            'temporary_closed_reason',
            'temporary_closed_until',
            'logo_path',
            'cover_image_path',
            'deleted_at',
        ]),
        'area_name' => $unrelatedArea->name,
    ];

    $this->seed(DemoRestaurantSeeder::class);

    $organization = demoCrudOrganization();
    $firstSnapshot = demoCrudSnapshot($organization);
    $firstMediaHashes = collect(demoCrudMediaPaths($organization))
        ->mapWithKeys(fn (string $path): array => [$path => hash('sha256', Storage::disk('public')->get($path))])
        ->all();
    $ownedArea = AreaNode::query()
        ->whereIn('branch_id', demoCrudBranches($organization)->pluck('id'))
        ->where('name', DemoOrganizationCrudSeeder::INACTIVE_AREA_NAME)
        ->firstOrFail();

    $ownedArea->delete();
    $unrelatedArea->delete();

    $this->seed(DemoRestaurantSeeder::class);

    expect(demoCrudSnapshot($organization->refresh()))->toBe($firstSnapshot)
        ->and(AreaNode::query()->whereKey($ownedArea->id)->exists())->toBeTrue()
        ->and(AreaNode::withTrashed()->whereKey($unrelatedArea->id)->whereNotNull('deleted_at')->exists())->toBeTrue()
        ->and($unrelatedOrganization->refresh()->only(array_keys($unrelatedSnapshot['organization'])))->toBe($unrelatedSnapshot['organization'])
        ->and($unrelatedBranch->refresh()->only(array_keys($unrelatedSnapshot['branch'])))->toBe($unrelatedSnapshot['branch'])
        ->and(AreaNode::withTrashed()->findOrFail($unrelatedArea->id)->name)->toBe($unrelatedSnapshot['area_name']);

    foreach ($firstMediaHashes as $path => $hash) {
        expect(hash('sha256', Storage::disk('public')->get($path)))->toBe($hash);
    }
});

test('demo invitations expose no raw token code or digest in presentation data', function (): void {
    $this->seed(DemoRestaurantSeeder::class);

    $invitations = Invitation::query()
        ->where('organization_id', demoCrudOrganization()->id)
        ->orderBy('id')
        ->get();
    $presentationJson = $invitations->toJson();

    expect($invitations)->toHaveCount(3)
        ->and($presentationJson)->not->toContain('invite_token')
        ->and($presentationJson)->not->toContain('invite_code')
        ->and($presentationJson)->not->toContain('demo-crud-token')
        ->and($presentationJson)->not->toContain('CRUD');

    foreach ($invitations as $invitation) {
        expect($invitation->getRawOriginal('invite_token'))->toBeNull()
            ->and($invitation->getRawOriginal('invite_code'))->toBeNull()
            ->and(strlen((string) $invitation->getRawOriginal('invite_token_hash')))->toBe(64)
            ->and(strlen((string) $invitation->getRawOriginal('invite_code_hash')))->toBe(64);
    }
});

function demoCrudOrganization(): Organization
{
    return Organization::query()
        ->select(['id', 'owner_user_id', 'name', 'logo_path'])
        ->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)
        ->firstOrFail();
}

/**
 * @return Collection<int, Branch>
 */
function demoCrudBranches(Organization $organization): Collection
{
    return Branch::query()
        ->where('organization_id', $organization->id)
        ->orderBy('id')
        ->get();
}

/**
 * @return list<string>
 */
function demoCrudMediaPaths(Organization $organization): array
{
    return collect([$organization->logo_path])
        ->merge($organization->brands()->pluck('logo_path'))
        ->merge($organization->branches()->pluck('logo_path'))
        ->merge($organization->branches()->pluck('cover_image_path'))
        ->merge(MenuItem::query()
            ->whereHas('menu.branch', fn ($query) => $query->where('organization_id', $organization->id))
            ->whereNotNull('image')
            ->pluck('image'))
        ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
        ->unique()
        ->values()
        ->all();
}

/**
 * @return array<string, mixed>
 */
function demoCrudSnapshot(Organization $organization): array
{
    $branches = demoCrudBranches($organization);
    $branchIds = $branches->pluck('id');
    $menuIds = Menu::query()->whereIn('branch_id', $branchIds)->orderBy('id')->pluck('id');
    $memberUserIds = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->pluck('user_id');

    return [
        'branch_ids' => $branchIds->all(),
        'opening_hour_ids' => BranchOpeningHour::query()->whereIn('branch_id', $branchIds)->orderBy('id')->pluck('id')->all(),
        'schedule_ids' => MenuAvailabilitySchedule::query()->whereIn('menu_id', $menuIds)->orderBy('id')->pluck('id')->all(),
        'modifier_group_ids' => ModifierGroup::query()->whereIn('branch_id', $branchIds)->orderBy('id')->pluck('id')->all(),
        'modifier_option_ids' => ModifierOption::query()->whereHas('group', fn ($query) => $query->whereIn('branch_id', $branchIds))->orderBy('id')->pluck('id')->all(),
        'area_waiter_ids' => AreaNodeWaiter::query()->where('organization_id', $organization->id)->orderBy('id')->pluck('id')->all(),
        'invitation_ids' => Invitation::query()->where('organization_id', $organization->id)->orderBy('id')->pluck('id')->all(),
        'invitation_hashes' => Invitation::query()->where('organization_id', $organization->id)->orderBy('id')->get()->map(fn (Invitation $invitation): array => [
            $invitation->getRawOriginal('invite_token_hash'),
            $invitation->getRawOriginal('invite_code_hash'),
        ])->all(),
        'override_ids' => PermissionUserOverride::query()->whereIn('user_id', $memberUserIds)->orderBy('id')->pluck('id')->all(),
        'qr_tokens' => QrCode::query()->whereHas('servicePoint', fn ($query) => $query->whereIn('branch_id', $branchIds))->orderBy('id')->pluck('public_token')->all(),
        'media_paths' => demoCrudMediaPaths($organization->refresh()),
    ];
}
