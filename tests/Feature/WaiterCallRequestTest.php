<?php

use App\Enums\OrganizationUserStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemPermission;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\WaiterCallStatus;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Livewire\Waiter\Dashboard as WaiterDashboard;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use App\Models\WaiterCall;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('waiter calls and database notifications tables are available on sqlite', function () {
    expect(Schema::hasTable('notifications'))->toBeTrue()
        ->and(Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
        ]))->toBeTrue()
        ->and(Schema::hasTable('waiter_calls'))->toBeTrue()
        ->and(Schema::hasColumns('waiter_calls', [
            'branch_id',
            'service_point_id',
            'active_service_point_id',
            'table_session_id',
            'requested_by_guest_id',
            'status',
            'requested_at',
            'handled_at',
            'handled_by_user_id',
            'metadata',
        ]))->toBeTrue()
        ->and(WaiterCallStatus::values())->toBe(['pending', 'handled']);
});

test('active guest can request waiter and waiter handles the database notification', function () {
    [$qrCode, $servicePoint, $tableSession, $activeGuest, $waiter] = createPrompt65WaiterCallContext();

    $guestComponent = Livewire::withCookie(prompt65GuestCookieName($qrCode), $activeGuest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $activeGuest->id)
        ->assertSeeText('Позвать официанта')
        ->call('requestWaiter')
        ->assertSet('waiterCallPending', true)
        ->assertSeeText('Официант получил вызов.');

    $waiterCall = WaiterCall::query()->firstOrFail();

    expect($waiterCall->status)->toBe(WaiterCallStatus::Pending)
        ->and($waiterCall->requested_by_guest_id)->toBe($activeGuest->id)
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::WaitingWaiter)
        ->and($waiter->notifications()->count())->toBe(1)
        ->and((int) data_get($waiter->notifications()->firstOrFail()->data, 'waiter_call_id'))->toBe($waiterCall->id);

    $guestComponent->call('requestWaiter');

    expect(WaiterCall::query()->count())->toBe(1)
        ->and($waiter->notifications()->count())->toBe(1);

    Livewire::actingAs($waiter)
        ->test(WaiterDashboard::class)
        ->assertSet('waiterCallCount', 1)
        ->assertSee('Guest calls')
        ->assertSee('Стол у окна')
        ->assertSee('Ana')
        ->assertSee('Waiter called')
        ->call('markWaiterCallHandled', $waiterCall->id)
        ->assertSet('waiterCallCount', 0)
        ->assertSee('Вызов официанта отмечен как обработанный.');

    $notification = $waiter->notifications()->firstOrFail();
    $waiterCall = $waiterCall->fresh();

    expect($waiterCall->status)->toBe(WaiterCallStatus::Handled)
        ->and($waiterCall->handled_by_user_id)->toBe($waiter->id)
        ->and($waiterCall->handled_at)->not->toBeNull()
        ->and($servicePoint->fresh()->status)->toBe(ServicePointStatus::Occupied)
        ->and($notification->fresh()->read_at)->not->toBeNull();
});

test('non active guest cannot request waiter from an old guest token', function () {
    [$qrCode, , , $activeGuest, $waiter] = createPrompt65WaiterCallContext();

    $activeGuest->update(['status' => TableSessionGuestStatus::Removed]);

    Livewire::withCookie(prompt65GuestCookieName($qrCode), $activeGuest->guest_token)
        ->test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->call('requestWaiter')
        ->assertSet('waiterCallPending', false)
        ->assertSet('waiterCallMessage', 'Только активный гость за этим столом может позвать официанта.');

    expect(WaiterCall::query()->exists())->toBeFalse()
        ->and($waiter->notifications()->exists())->toBeFalse();
});

function createPrompt65WaiterCallContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Prompt 65 Group']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 65 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Prompt 65 Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
            'currency' => 'EUR',
        ]);
    BranchSetting::factory()->for($branch)->create();

    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Стол у окна',
            'is_active' => true,
            'status' => ServicePointStatus::Occupied,
        ]);

    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'prompt65'.fake()->unique()->bothify('????????????????'),
            'short_code' => 'P65-'.fake()->unique()->numerify('####'),
            'status' => QrCodeStatus::Active,
        ]);

    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->waiterOpened()
        ->create();

    $activeGuest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Ana',
            'status' => TableSessionGuestStatus::Active,
        ]);

    $waiter = User::factory()->create([
        'name' => 'Prompt 65 Waiter',
        'email' => 'prompt65-waiter@example.test',
    ]);

    attachPrompt65Waiter($waiter, $organization);

    return [$qrCode, $servicePoint, $tableSession, $activeGuest, $waiter];
}

function attachPrompt65Waiter(User $user, Organization $organization): Role
{
    $waiterRole = Role::query()
        ->where('code', SystemRole::Waiter->value)
        ->firstOrFail();
    $viewOrders = Permission::query()
        ->where('code', SystemPermission::ViewOrders->value)
        ->firstOrFail();

    $waiterRole->permissions()->updateExistingPivot($viewOrders->id, ['enabled' => true]);

    $organization->users()->syncWithoutDetachingOrFail([
        $user->id => [
            'role_id' => $waiterRole->id,
            'status' => OrganizationUserStatus::Active->value,
            'joined_at' => now(),
            'invited_by_user_id' => null,
        ],
    ]);

    return $waiterRole;
}

function prompt65GuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
