<?php

use App\Actions\Invitations\CreateInvitationAction;
use App\Actions\Organizations\CreateOrganizationAction;
use App\Actions\QrCodes\GenerateQrCodeForServicePointAction;
use App\Enums\DataExportType;
use App\Enums\InvitationStatus;
use App\Enums\MenuStatus;
use App\Enums\OrderStatus;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\SystemRole;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\Show as PublicQrShow;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\DraftOrder;
use App\Models\Invitation;
use App\Models\ManualPayment;
use App\Models\Menu;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\QrCode;
use App\Models\Role;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\User;
use Database\Seeders\SystemPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\FileOperationPage;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
});

test('generated qr and staff invitation tokens are long random route credentials', function (): void {
    [$organization, , , $servicePoint, $createdBy] = prompt333BranchContext();

    $qrCode = app(GenerateQrCodeForServicePointAction::class)->handle($servicePoint, $createdBy);

    expect($qrCode->public_token)
        ->toHaveLength(64)
        ->toMatch('/\A[A-Za-z0-9]+\z/')
        ->not->toBe($qrCode->short_code)
        ->not->toBe((string) $organization->id)
        ->not->toBe((string) $servicePoint->id)
        ->not->toMatch('/\A\d+\z/');

    expect(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->toBe('/q/'.$qrCode->public_token);

    $this->get('/q/'.$qrCode->short_code)->assertNotFound();

    $role = Role::query()->where('code', SystemRole::Waiter->value)->firstOrFail();
    $createdInvitation = app(CreateInvitationAction::class)->handle($organization, $role, $createdBy, [
        'email' => 'token-recipient@example.test',
    ]);

    expect($createdInvitation->token)
        ->toHaveLength(64)
        ->toMatch('/\A[A-Za-z0-9]+\z/')
        ->not->toBe((string) $organization->id)
        ->not->toMatch('/\A\d+\z/')
        ->and($createdInvitation->code)
        ->toHaveLength(8)
        ->toMatch('/\A[A-Z0-9]+\z/');
});

test('guest tokens stay hidden and cannot authenticate staff routes', function (): void {
    [, , , $servicePoint] = prompt333BranchContext();
    $qrCode = app(GenerateQrCodeForServicePointAction::class)->handle($servicePoint);

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', '  Ana  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('preparedGuestName', 'Ana');

    $guest = TableSessionGuest::query()->firstOrFail();

    expect($guest->guest_token)
        ->toHaveLength(64)
        ->toMatch('/\A[A-Za-z0-9]+\z/')
        ->not->toBe((string) $guest->id)
        ->not->toBe((string) $guest->table_session_id)
        ->not->toMatch('/\A\d+\z/');

    $this->withCookie(prompt333GuestCookieName($qrCode), $guest->guest_token)
        ->get(route('public.qr.show', ['token' => $qrCode->public_token], false))
        ->assertOk()
        ->assertDontSee($guest->guest_token, false);

    $this->withCookie(prompt333GuestCookieName($qrCode), $guest->guest_token)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'));
});

test('guest credentials are hidden from model arrays and json without changing server access', function (string $modelClass): void {
    $model = $modelClass::factory()->create();
    $token = $model->guest_token;
    $expectedAttributes = [
        'id' => $model->id,
        'guest_name' => $model->guest_name,
        'table_session_id' => $model->table_session_id,
    ];

    $queries = countDatabaseQueries(function () use ($model, $token, $expectedAttributes): void {
        expect($model->attributesToArray())->not->toHaveKey('guest_token')
            ->and($model->toArray())->toMatchArray($expectedAttributes)->not->toHaveKey('guest_token')
            ->and(json_decode($model->toJson(), true, flags: JSON_THROW_ON_ERROR))
            ->toMatchArray($expectedAttributes)->not->toHaveKey('guest_token')
            ->and($model->toJson())->not->toContain($token);
    });

    expect($queries)->toBe(0)
        ->and($token)->toHaveLength(64)
        ->and($model->guest_token)->toBe($token)
        ->and($model->fresh()->guest_token)->toBe($token);
})->with([
    'table guest' => TableSessionGuest::class,
    'join request' => TableSessionJoinRequest::class,
]);

test('loaded table session relationships do not serialize guest credentials', function (): void {
    $tableSession = TableSession::factory()->active()->create();
    $guest = TableSessionGuest::factory()->for($tableSession)->create();
    $joinRequest = TableSessionJoinRequest::factory()->for($tableSession)->create();
    $tableSession->load(['guests', 'joinRequests']);

    $queries = countDatabaseQueries(function () use ($tableSession, $guest, $joinRequest): void {
        $attributes = $tableSession->toArray();

        expect($attributes['guests'])->toHaveCount(1)
            ->and($attributes['join_requests'])->toHaveCount(1)
            ->and($attributes['guests'][0])->toMatchArray(['id' => $guest->id, 'guest_name' => $guest->guest_name])
            ->not->toHaveKey('guest_token')
            ->and($attributes['join_requests'][0])->toMatchArray(['id' => $joinRequest->id, 'guest_name' => $joinRequest->guest_name])
            ->not->toHaveKey('guest_token')
            ->and(json_encode($tableSession, JSON_THROW_ON_ERROR))
            ->not->toContain('guest_token', $guest->guest_token, $joinRequest->guest_token);
    });

    expect($queries)->toBe(0);
});

test('expired or non pending staff invitation tokens are not acceptable', function (): void {
    $activeToken = str_repeat('A', 64);
    $expiredToken = str_repeat('B', 64);
    $acceptedToken = str_repeat('C', 64);
    $active = Invitation::factory()->create([
        'invite_token_hash' => hash('sha256', $activeToken),
        'status' => InvitationStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);
    $expired = Invitation::factory()->create([
        'invite_token_hash' => hash('sha256', $expiredToken),
        'status' => InvitationStatus::Pending,
        'expires_at' => now()->subMinute(),
    ]);
    $accepted = Invitation::factory()->create([
        'invite_token_hash' => hash('sha256', $acceptedToken),
        'status' => InvitationStatus::Accepted,
        'expires_at' => now()->addHour(),
    ]);

    expect(Invitation::findAcceptableByToken($activeToken)?->id)->toBe($active->id)
        ->and($active->canBeAccepted())->toBeTrue()
        ->and(Invitation::findAcceptableByToken($expiredToken))->toBeNull()
        ->and($expired->canBeAccepted())->toBeFalse()
        ->and(Invitation::findAcceptableByToken($acceptedToken))->toBeNull()
        ->and($accepted->canBeAccepted())->toBeFalse()
        ->and(Invitation::findAcceptableByToken('short-token'))->toBeNull();
});

test('revoked qr and closed session invite tokens cannot create guest access', function (): void {
    [, , , $servicePoint] = prompt333BranchContext();
    $qrCode = app(GenerateQrCodeForServicePointAction::class)->handle($servicePoint);
    $closedInviteToken = str_repeat('D', 64);
    $closedTableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->waiterOpened()
        ->create([
            'status' => TableSessionStatus::Closed,
            'started_at' => now()->subHour(),
            'ended_at' => now()->subMinute(),
            'guest_invite_token_hash' => hash('sha256', $closedInviteToken),
            'guest_invite_created_at' => now()->subHour(),
            'guest_invite_expires_at' => now()->addHour(),
        ]);
    TableSessionGuest::factory()
        ->for($closedTableSession)
        ->create(['status' => TableSessionGuestStatus::Active]);

    Livewire::withQueryParams(['invite' => $closedInviteToken])
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('hasCurrentInviteToken', true)
        ->set('guestName', 'Jonas')
        ->call('enterTable')
        ->assertSet('entryState', 'guest_invite_invalid')
        ->assertSet('currentGuestId', null)
        ->assertSet('currentJoinRequestId', null)
        ->assertSet('guestCanAddItems', false);

    expect(TableSessionJoinRequest::query()->count())->toBe(0);

    $qrCode->forceFill([
        'status' => QrCodeStatus::Revoked,
        'revoked_at' => now(),
    ])->save();

    Livewire::test(PublicQrShow::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'revoked');

    Livewire::test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Mila')
        ->call('enterTable')
        ->assertSet('currentTableSessionId', null)
        ->assertSet('guestCanAddItems', false);
});

test('branch csv exports do not include raw security tokens', function (): void {
    [$organization, , $branch, $servicePoint] = prompt333BranchContext();
    $superadmin = prompt333Superadmin();
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => str_repeat('Q', 64),
            'short_code' => 'QR333SAFE',
            'status' => QrCodeStatus::Active,
        ]);
    $tableSession = TableSession::factory()
        ->forServicePoint($servicePoint)
        ->active()
        ->create();
    $guestInviteToken = str_repeat('S', 64);
    $tableSession->forceFill([
        'guest_invite_token_hash' => hash('sha256', $guestInviteToken),
        'guest_invite_created_at' => now(),
        'guest_invite_expires_at' => now()->addHour(),
    ])->save();
    $guest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Export Guest',
            'guest_token' => str_repeat('G', 64),
        ]);
    $draftOrder = DraftOrder::factory()
        ->for($tableSession)
        ->create();
    $order = Order::factory()->create([
        'branch_id' => $branch->id,
        'service_point_id' => $servicePoint->id,
        'table_session_id' => $tableSession->id,
        'draft_order_id' => $draftOrder->id,
        'status' => OrderStatus::Served,
        'total_price_cents' => 1200,
    ]);
    OrderItem::factory()
        ->for($order)
        ->for($guest, 'guest')
        ->create([
            'guest_name' => 'Export Guest',
            'item_name' => 'Token safe soup',
            'total_price_cents' => 1200,
        ]);
    ManualPayment::factory()
        ->forGuest($guest)
        ->create([
            'recorded_by_user_id' => $superadmin->id,
            'note' => 'No token in export',
        ]);
    $menu = Menu::factory()
        ->for($branch)
        ->create(['status' => MenuStatus::Active]);
    $category = MenuCategory::factory()
        ->for($menu)
        ->create();
    MenuItem::factory()
        ->for($menu)
        ->for($category, 'category')
        ->create(['name' => 'Export Menu Item']);
    $invitation = Invitation::factory()
        ->for($organization)
        ->create(['invite_token_hash' => hash('sha256', str_repeat('I', 64))]);

    $this->actingAs($superadmin);
    $contents = [];
    foreach (DataExportType::cases() as $type) {
        $snapshot = FileOperationPage::open($this, route('restaurant.exports.index', ['branch' => $branch->id]), 'exports');
        $prepared = FileOperationPage::call($this, $snapshot, 'downloadCsv', params: [$branch->id, $type->value])->assertOk();
        $response = $this->get($prepared->json('components.0.effects.redirect'))->assertOk();
        $contents[] = $response->baseResponse->getFile()->getContent();
    }
    $content = implode("\n", $contents);

    expect($content)
        ->not->toContain($qrCode->public_token)
        ->not->toContain($guest->guest_token)
        ->not->toContain($guestInviteToken)
        ->not->toContain(str_repeat('I', 64))
        ->not->toContain('guest_token')
        ->not->toContain('invite_token')
        ->not->toContain('public_token');
});

function prompt333BranchContext(): array
{
    $owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($owner, ['name' => 'Prompt 333 Organization']);
    $brand = Brand::factory()
        ->for($organization)
        ->create(['name' => 'Prompt 333 Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create(['name' => 'Prompt 333 Branch']);
    BranchSetting::factory()
        ->for($branch)
        ->create();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Prompt 333 Table',
            'status' => ServicePointStatus::Free,
            'is_active' => true,
        ]);

    return [$organization, $brand, $branch, $servicePoint, $owner->fresh()];
}

function prompt333GuestCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}

function prompt333Superadmin(): User
{
    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();
    $user = User::factory()->create();
    $user->roles()->attach($role);

    return $user;
}
