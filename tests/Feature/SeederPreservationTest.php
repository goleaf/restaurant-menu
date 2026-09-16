<?php

declare(strict_types=1);

use App\Enums\InvitationStatus;
use App\Enums\OrganizationUserStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Models\Branch;
use App\Models\BranchOpeningHour;
use App\Models\BranchSetting;
use App\Models\BranchUser;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\PermissionUserOverride;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\DemoRestaurantSeeder;
use Database\Seeders\FirstSuperadminSeeder;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->seederDisk = 'seeder-preservation-'.getmypid().'-'.Str::uuid();
    Storage::set('public', Storage::fake($this->seederDisk));
});

afterEach(function (): void {
    (new Filesystem)->deleteDirectory(storage_path('framework/testing/disks/'.$this->seederDisk));
});

test('superadmin bootstrap does not elevate an existing account or restore a revoked role', function (bool $existing): void {
    config()->set('platform.first_superadmin.email', 'bootstrap@example.test');
    config()->set('platform.first_superadmin.password', Str::random(32));

    if ($existing) {
        User::factory()->create(['email' => 'bootstrap@example.test']);
    }

    $this->seed(FirstSuperadminSeeder::class);
    $user = User::query()->where('email', 'bootstrap@example.test')->firstOrFail();

    if (! $existing) {
        expect($user->roles()->where('code', SystemRole::Superadmin->value)->exists())->toBeTrue();
        $user->roles()->detach();
    }

    $this->seed(FirstSuperadminSeeder::class);

    expect($user->roles()->exists())->toBeFalse();
})->with(['existing identity' => true, 'revoked bootstrap grant' => false]);

test('superadmin initialization rolls back the new identity when setup fails', function (): void {
    config()->set('platform.first_superadmin.email', 'bootstrap@example.test');
    config()->set('platform.first_superadmin.password', Str::random(32));
    Event::listen('eloquent.created: '.User::class, function (): never {
        throw new RuntimeException('Simulated initialization failure.');
    });

    expect(fn () => $this->seed(FirstSuperadminSeeder::class))->toThrow(RuntimeException::class)
        ->and(User::query()->where('email', 'bootstrap@example.test')->exists())->toBeFalse();
});

test('repeated demo seeding preserves access decisions and editable branch configuration in every tenant', function (): void {
    $this->seed(DemoRestaurantSeeder::class);

    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $waiter = User::query()->where('email', DemoAccountCatalog::forRole(SystemRole::Waiter)['email'])->firstOrFail();
    $changedRole = Role::query()->where('code', SystemRole::Cashier->value)->firstOrFail();
    $waiter->roles()->sync([$changedRole->id]);
    $waiter->update(['locale' => 'lt', 'name' => 'Edited fixture identity']);
    $memberships = OrganizationUser::query()->where('user_id', $waiter->id)->get();
    $assignments = BranchUser::query()->where('user_id', $waiter->id)->get();

    foreach ($memberships as $membership) {
        $membership->update(['status' => OrganizationUserStatus::Suspended, 'role_id' => $changedRole->id]);
    }
    foreach ($assignments as $assignment) {
        $assignment->update(['status' => OrganizationUserStatus::Removed, 'role_id' => $changedRole->id]);
    }
    foreach (Branch::query()->get() as $branch) {
        $branch->update(['public_name' => 'Edited '.$branch->id, 'timezone' => 'Europe/London']);
    }
    foreach (BranchSetting::query()->get() as $setting) {
        $setting->update(['polling_interval_seconds' => 17, 'allow_guest_invite_links' => false]);
    }
    foreach (BranchOpeningHour::query()->get() as $hour) {
        $hour->update(['is_closed' => true, 'opens_at' => null, 'closes_at' => null]);
    }

    $permission = Permission::query()->where('code', SystemPermission::ViewReports->value)->firstOrFail();
    $override = PermissionUserOverride::factory()->forUser($waiter)->forOrganization($organization)->forPermission($permission)->denied()->create();
    $foreignOrganization = Organization::query()->whereKeyNot($organization->id)->firstOrFail();
    OrganizationUser::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->update(['status' => OrganizationUserStatus::Suspended]);
    BranchUser::query()->where('organization_id', $foreignOrganization->id)->firstOrFail()->update(['status' => OrganizationUserStatus::Suspended]);

    $snapshots = [
        'user' => $waiter->fresh()->only(['name', 'locale']),
        'memberships' => OrganizationUser::query()->orderBy('id')->get()->map->only(['id', 'role_id', 'status'])->all(),
        'assignments' => BranchUser::query()->orderBy('id')->get()->map->only(['id', 'role_id', 'status'])->all(),
        'branches' => Branch::query()->orderBy('id')->get()->map->only(['id', 'public_name', 'timezone'])->all(),
        'settings' => BranchSetting::query()->orderBy('id')->get()->map->only(['id', 'polling_interval_seconds', 'allow_guest_invite_links'])->all(),
        'hours' => BranchOpeningHour::query()->orderBy('id')->get()->map->only(['id', 'is_closed', 'opens_at', 'closes_at'])->all(),
    ];

    $this->seed(DemoRestaurantSeeder::class);

    expect($waiter->fresh()->only(['name', 'locale']))->toBe($snapshots['user'])
        ->and($waiter->roles()->pluck('roles.id')->all())->toBe([$changedRole->id])
        ->and(OrganizationUser::query()->orderBy('id')->get()->map->only(['id', 'role_id', 'status'])->all())->toBe($snapshots['memberships'])
        ->and(BranchUser::query()->orderBy('id')->get()->map->only(['id', 'role_id', 'status'])->all())->toBe($snapshots['assignments'])
        ->and(Branch::query()->orderBy('id')->get()->map->only(['id', 'public_name', 'timezone'])->all())->toBe($snapshots['branches'])
        ->and(BranchSetting::query()->orderBy('id')->get()->map->only(['id', 'polling_interval_seconds', 'allow_guest_invite_links'])->all())->toBe($snapshots['settings'])
        ->and(BranchOpeningHour::query()->orderBy('id')->get()->map->only(['id', 'is_closed', 'opens_at', 'closes_at'])->all())->toBe($snapshots['hours'])
        ->and($override->fresh()?->enabled)->toBeFalse();
});

test('demo permission fixtures are organization scoped and preserve foreign and edited overrides', function (): void {
    $this->seed(DemoRestaurantSeeder::class);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $user = User::query()->where('email', 'permission.staff@demo.test')->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ChangeAvailability->value)->firstOrFail();
    $own = PermissionUserOverride::query()->where('user_id', $user->id)->where('permission_id', $permission->id)->firstOrFail();

    expect($own->organization_id)->toBe($organization->id);
    $own->update(['enabled' => false]);
    $foreign = PermissionUserOverride::factory()->forUser($user)->forPermission($permission)
        ->forOrganization(Organization::factory()->create())->allowed()->create();

    $this->seed(DemoRestaurantSeeder::class);

    expect($own->fresh()->enabled)->toBeFalse()
        ->and($foreign->fresh()->enabled)->toBeTrue()
        ->and($foreign->fresh()->organization_id)->toBe($foreign->organization_id);
});

test('repeated demo seeding preserves a revoked invitation and its rotated credential', function (): void {
    $this->seed(DemoRestaurantSeeder::class);
    $invitation = Invitation::query()->where('email', 'pending.invitation@demo.test')->firstOrFail();
    $invitation->forceFill([
        'status' => InvitationStatus::Cancelled,
        'invite_token_hash' => hash('sha256', Str::random(64)),
        'invite_code_hash' => hash('sha256', Str::random(8)),
    ])->save();
    $original = $invitation->getAttributes();

    $this->seed(DemoRestaurantSeeder::class);

    expect($invitation->fresh()->getAttributes())->toBe($original);
});

test('scoped demo fixtures preserve an existing legacy denial during upgrade', function (): void {
    $this->seed(DemoRestaurantSeeder::class);
    $organization = Organization::query()->where('name', DemoRestaurantSeeder::ORGANIZATION_NAME)->firstOrFail();
    $user = User::query()->where('email', 'permission.staff@demo.test')->firstOrFail();
    $permission = Permission::query()->where('code', SystemPermission::ChangeAvailability->value)->firstOrFail();
    $legacy = PermissionUserOverride::query()->where('organization_id', $organization->id)
        ->where('user_id', $user->id)->where('permission_id', $permission->id)->firstOrFail();
    $legacy->forceFill(['organization_id' => null, 'scope_key' => 'legacy', 'enabled' => false])->save();

    $this->seed(DemoRestaurantSeeder::class);

    expect($legacy->fresh()->organization_id)->toBeNull()
        ->and($legacy->fresh()->enabled)->toBeFalse()
        ->and(PermissionUserOverride::query()->where('organization_id', $organization->id)
            ->where('user_id', $user->id)->where('permission_id', $permission->id)->exists())->toBeFalse();
});
