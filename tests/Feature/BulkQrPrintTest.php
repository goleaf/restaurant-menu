<?php

use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\StoreQrCodeImageAction;
use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointType;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\Index as FloorIndex;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\PrintPanel;
use App\Livewire\Organizations\Brands\Branches\ServicePoints\SelectionOperations;
use App\Models\AreaNode;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\OrganizationUser;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('bulk qr print page requires generate qr permission', function () {
    [$organization, $brand, $branch, , , , $manager] = createPrompt27QrContext();
    $url = prompt27BulkQrPrintUrl($organization, $brand, $branch);
    $this->get($url)->assertRedirect(route('login'));
    $this->actingAs($manager)->get($url)->assertForbidden();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $this->actingAs($manager)->get($url)->assertRedirect(route('organizations.brands.branches.service-points.index', [$organization, $brand, $branch, 'zone' => 'all']));
});

test('bulk qr print filters service points by area', function () {
    [$organization, $brand, $branch, $mainHall, $terrace, $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager)->test(FloorIndex::class, compact('organization', 'brand', 'branch'))
        ->assertSee($mainHall->name)->assertSee($terrace->name)->assertSee($points['mainWithQr']->name)->assertSee($points['mainWithoutQr']->name)
        ->set('filters.area', (string) $terrace->id)->assertSee($points['terraceWithQr']->name)->assertDontSee($points['mainWithQr']->name)
        ->set('filters.area', 'none')->assertSee($points['noZoneWithQr']->name)->assertDontSee($points['terraceWithQr']->name);
});

test('bulk qr print can select multiple existing eternal qr codes', function () {
    [$organization, $brand, $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $list = Livewire::actingAs($manager)->test(FloorIndex::class, compact('organization', 'brand', 'branch'))
        ->set('filters.qr', 'with')->call('selectPage');
    $expected = [$points['mainWithQr']->id, $points['terraceWithQr']->id, $points['noZoneWithQr']->id];
    expect($list->get('selectedIds'))->toEqualCanonicalizing($expected);
    $list->call('selectPage');
    expect($list->get('selectedIds'))->toEqualCanonicalizing($expected);
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => $list->get('selectedIds')])
        ->call('preparePrint')->assertHasNoErrors()->assertSee(['QR-P27MAIN', 'QR-P27TERR', 'QR-P27NOZN'])
        ->assertSee('data:image/svg+xml;base64', false)->assertDontSee('QR-P27MISS');
    $list->call('clearSelection')->assertSet('selectedIds', []);
});

test('bulk qr print applies label design presets to selected stickers', function () {
    [$organization, , $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$points['mainWithQr']->id]])
        ->assertSet('form.preset', 'minimal')->assertSee(['Minimal', 'Classic', 'Restaurant', 'Bar', 'Hotel', 'Premium'])
        ->call('preparePrint')->assertSee('data-qr-preset="minimal"', false)
        ->set('form.preset', 'bar')->call('preparePrint')->assertHasNoErrors()->assertSet('form.preset', 'bar')
        ->assertSee('qr-sticker-preset-bar', false)->assertSee('data-qr-preset="bar"', false)->assertDontSee('Стол: 1');
});

test('bulk qr print offers and creates missing qr without duplicating active qr codes', function () {
    [$organization, , $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $missing = $points['mainWithoutQr'];
    $selected = [$missing->id, $points['mainWithQr']->id];
    $component = Livewire::actingAs($manager)->test(SelectionOperations::class, ['branchId' => $branch->id, 'ids' => $selected, 'operation' => 'generate'])
        ->assertSee($missing->name)->call('generateNext')->assertHasNoErrors()->assertSet('finished', true);
    expect($component->get('completedIds'))->toEqualCanonicalizing($selected);
    $component->call('generateNext')->assertHasNoErrors();
    expect(QrCode::query()->where('service_point_id', $missing->id)->where('status', QrCodeStatus::Active)->count())->toBe(1)
        ->and(QrCode::query()->where('service_point_id', $points['mainWithQr']->id)->count())->toBe(1);
});

test('floor selection opens canonical printing only with generate qr permission', function (): void {
    [$organization, $brand, $branch, , , $points, $manager] = createPrompt27QrContext();
    Livewire::actingAs($manager)->test(PrintPanel::class, ['branchId' => $branch->id, 'ids' => [$points['mainWithQr']->id]])->assertForbidden();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    Livewire::actingAs($manager->fresh())->test(FloorIndex::class, compact('organization', 'brand', 'branch'))
        ->call('selectPoint', $points['mainWithQr']->id)->assertSee(__('floor.print_selected'))
        ->call('openSelection', 'print')->assertSet('panel', 'print');
});

test('bulk QR generation accounts for a nonactive QR and continues with later selected tables', function (QrCodeStatus $status): void {
    [$organization, , $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $blocked = $points['mainWithQr'];
    $missing = $points['mainWithoutQr'];
    $qr = $blocked->activeQrCode;
    $token = $qr->public_token;
    $qr->forceFill(['status' => $status])->save();
    expect($qr->fresh()->status)->toBe($status);

    $component = Livewire::actingAs($manager)->test(SelectionOperations::class, [
        'branchId' => $branch->id, 'ids' => [$blocked->id, $missing->id], 'operation' => 'generate',
    ])->call('generateNext')->assertHasNoErrors()
        ->assertSet('completedIds', [$missing->id])
        ->assertSet('skippedIds', [$blocked->id => 'reissue_required'])
        ->assertSet('finished', true)
        ->assertSee(__('floor.qr.result.reissue_required'))
        ->assertDontSee('variant="success"', false);

    $component->call('generateNext')->assertHasNoErrors()->assertSet('completedIds', [$missing->id]);
    expect($qr->fresh()->public_token)->toBe($token)
        ->and($qr->fresh()->status)->toBe($status)
        ->and(QrCode::query()->where('service_point_id', $blocked->id)->count())->toBe(1)
        ->and(QrCode::query()->where('service_point_id', $missing->id)->count())->toBe(1);
})->with([QrCodeStatus::Disabled, QrCodeStatus::Revoked]);

test('bulk QR file failure does not block later targets and explicit retry repairs only the unfinished image', function (): void {
    Storage::fake('public');
    [$organization, , $branch, , , $points, $manager] = createPrompt27QrContext();
    grantPrompt27Permission($manager, $organization, SystemPermission::GenerateQr);
    $first = $points['mainWithoutQr'];
    $second = ServicePoint::factory()->for($branch)->create();
    $actual = Storage::disk('public');
    $rejectFirstWrite = true;
    $writes = 0;
    $disk = Mockery::mock(Filesystem::class);
    $disk->shouldReceive('exists')->andReturnUsing(fn (string $path): bool => $actual->exists($path));
    $disk->shouldReceive('get')->andReturnUsing(fn (string $path): ?string => $actual->get($path));
    $disk->shouldReceive('put')->andReturnUsing(function (string $path, string $contents) use ($actual, &$rejectFirstWrite, &$writes): bool {
        $writes++;
        if ($rejectFirstWrite) {
            $rejectFirstWrite = false;

            return $actual->put($path, substr($contents, 0, 32), 'public');
        }

        return $actual->put($path, $contents, 'public');
    });
    $disk->shouldReceive('move')->andReturnUsing(fn (string $from, string $to): bool => $actual->move($from, $to));
    $disk->shouldReceive('delete')->andReturnUsing(fn (string $path): bool => $actual->delete($path));
    $factory = Mockery::mock(FilesystemFactory::class);
    $factory->shouldReceive('disk')->with('public')->andReturn($disk);
    $images = app()->makeWith(StoreQrCodeImageAction::class, ['filesystem' => $factory]);
    app()->instance(StoreQrCodeImageAction::class, $images);

    $component = Livewire::actingAs($manager)->test(SelectionOperations::class, [
        'branchId' => $branch->id, 'ids' => [$first->id, $second->id], 'operation' => 'generate',
    ])->call('generateNext')->assertHasNoErrors()
        ->assertSet('completedIds', [$second->id])
        ->assertSet('failedIds', [$first->id => 'image_failed'])
        ->assertSet('finished', true)
        ->assertSee(__('floor.qr.result.image_failed'));
    $firstQr = QrCode::query()->where('service_point_id', $first->id)->sole();
    $secondQr = QrCode::query()->where('service_point_id', $second->id)->sole();
    expect($writes)->toBe(2)->and($actual->allFiles('qr'))->toHaveCount(1);

    $component->call('generateNext')->assertSet('failedIds', [$first->id => 'image_failed']);
    expect($writes)->toBe(2);
    $component->call('retryFailed')->assertHasNoErrors()->assertSet('failedIds', [])->assertSet('finished', true);
    expect($component->get('completedIds'))->toEqualCanonicalizing([$first->id, $second->id]);
    expect($writes)->toBe(3)->and($actual->allFiles('qr'))->toHaveCount(2)
        ->and(QrCode::query()->where('service_point_id', $first->id)->sole()->public_token)->toBe($firstQr->public_token)
        ->and(QrCode::query()->where('service_point_id', $second->id)->sole()->public_token)->toBe($secondQr->public_token);
});

function createPrompt27QrContext(): array
{
    $manager = User::factory()->create();
    $organization = (new CreateOrganizationAction)->handle($manager, ['name' => 'QR Bulk Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Bella Bulk']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Bella Bulk Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);

    $restrictedRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();

    $manager->roles()->sync([$restrictedRole->id]);
    OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $manager->id)
        ->firstOrFail()
        ->forceFill(['role_id' => $restrictedRole->id])
        ->save();

    $mainHall = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Main Hall', 'sort_order' => 1]);
    $terrace = AreaNode::factory()
        ->for($branch)
        ->create(['name' => 'Terrace', 'sort_order' => 2]);

    $mainWithQr = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Main Table 1',
            'display_number' => '1',
        ]);
    $mainWithoutQr = ServicePoint::factory()
        ->for($branch)
        ->for($mainHall)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Main Table 2',
            'display_number' => '2',
        ]);
    $terraceWithQr = ServicePoint::factory()
        ->for($branch)
        ->for($terrace)
        ->create([
            'type' => ServicePointType::Table,
            'name' => 'Terrace Table 3',
            'display_number' => '3',
        ]);
    $noZoneWithQr = ServicePoint::factory()
        ->for($branch)
        ->create([
            'type' => ServicePointType::BarSeat,
            'name' => 'Bar Seat 4',
            'display_number' => '4',
        ]);

    QrCode::factory()
        ->for($mainWithQr)
        ->create([
            'public_token' => 'prompt27maintoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P27MAIN',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);
    QrCode::factory()
        ->for($terraceWithQr)
        ->create([
            'public_token' => 'prompt27terracetoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P27TERR',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);
    QrCode::factory()
        ->for($noZoneWithQr)
        ->create([
            'public_token' => 'prompt27nozonetoken'.fake()->unique()->bothify('####'),
            'short_code' => 'QR-P27NOZN',
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => $manager->id,
        ]);

    return [
        $organization,
        $brand,
        $branch,
        $mainHall,
        $terrace,
        [
            'mainWithQr' => $mainWithQr,
            'mainWithoutQr' => $mainWithoutQr,
            'terraceWithQr' => $terraceWithQr,
            'noZoneWithQr' => $noZoneWithQr,
        ],
        $manager->fresh(),
    ];
}

function grantPrompt27Permission(User $user, Organization $organization, SystemPermission $permission): void
{
    $membership = OrganizationUser::query()
        ->where('organization_id', $organization->id)
        ->where('user_id', $user->id)
        ->where('status', OrganizationUserStatus::Active->value)
        ->firstOrFail();
    $permissionModel = Permission::query()
        ->where('code', $permission->value)
        ->firstOrFail();

    $membership->role->permissions()->updateExistingPivot($permissionModel->id, ['enabled' => true]);
}

function prompt27BulkQrPrintUrl(Organization $organization, Brand $brand, Branch $branch): string
{
    return route('organizations.brands.branches.qr.print', [
        $organization,
        $brand,
        $branch,
    ]);
}
