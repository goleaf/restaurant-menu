<?php

use App\Actions\TableSessions\CreateGuestInviteLinkAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Livewire\PublicQr\GuestActions;
use App\Livewire\PublicQr\GuestEntry;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

test('table sessions store only a hidden guest invite token digest', function () {
    expect(Schema::hasColumns('table_sessions', [
        'guest_invite_token_hash',
        'guest_invite_created_at',
        'guest_invite_expires_at',
        'guest_invite_created_by_guest_id',
    ]))->toBeTrue()
        ->and(Schema::hasColumn('table_sessions', 'guest_invite_token'))->toBeFalse();
});

test('secure guest invite migration hashes legacy bearers without losing their expiry origin', function () {
    $connectionName = 'guest-invite-forward-migration';
    $databasePath = tempnam(sys_get_temp_dir(), 'restaurant-menu-guest-invite-');
    $originalConnection = DB::getDefaultConnection();
    $legacyToken = str_repeat('L', 64);
    $createdAt = now()->subMinutes(5)->startOfSecond();

    expect($databasePath)->toBeString();

    config()->set("database.connections.{$connectionName}", [
        ...config('database.connections.sqlite'),
        'database' => $databasePath,
    ]);

    try {
        DB::setDefaultConnection($connectionName);
        Schema::create('table_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('guest_invite_token', 64)->nullable()->unique();
            $table->timestamp('guest_invite_created_at')->nullable();
            $table->timestamps();
        });
        $legacyTableSession = new class extends Model
        {
            protected $table = 'table_sessions';

            protected $guarded = [];
        };
        $legacyTableSession->forceFill([
            'guest_invite_token' => $legacyToken,
            'guest_invite_created_at' => $createdAt,
        ])->save();
        $migration = require database_path('migrations/2026_08_24_045235_add_secure_guest_invite_fields_to_table_sessions_table.php');

        $migration->up();
        $migratedTableSession = $legacyTableSession->newQuery()->findOrFail($legacyTableSession->id);

        expect($migratedTableSession->getAttribute('guest_invite_token'))->toBeNull()
            ->and($migratedTableSession->getAttribute('guest_invite_token_hash'))->toBe(hash('sha256', $legacyToken))
            ->and((string) $migratedTableSession->getAttribute('guest_invite_expires_at'))
            ->toBe($createdAt->addMinutes(30)->toDateTimeString());

        $removal = require database_path('migrations/2026_08_24_102929_remove_legacy_guest_invite_token_from_table_sessions_table.php');

        $removal->up();

        expect(Schema::hasColumn('table_sessions', 'guest_invite_token'))->toBeFalse()
            ->and($legacyTableSession->newQuery()->findOrFail($legacyTableSession->id)->getAttribute('guest_invite_token_hash'))
            ->toBe(hash('sha256', $legacyToken));

        $removal->down();

        expect(Schema::hasColumn('table_sessions', 'guest_invite_token'))->toBeTrue()
            ->and($legacyTableSession->newQuery()->findOrFail($legacyTableSession->id)->getAttribute('guest_invite_token'))
            ->toBeNull();

        $removal->up();

        expect(Schema::hasColumn('table_sessions', 'guest_invite_token'))->toBeFalse();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge($connectionName);
        config()->set("database.connections.{$connectionName}", null);
        File::delete($databasePath);
    }
});

test('plaintext guest invite removal fails closed before dropping a populated legacy column', function (): void {
    $connectionName = 'guest-invite-removal-preflight';
    $databasePath = tempnam(sys_get_temp_dir(), 'restaurant-menu-guest-invite-preflight-');
    $originalConnection = DB::getDefaultConnection();

    expect($databasePath)->toBeString();

    config()->set("database.connections.{$connectionName}", [
        ...config('database.connections.sqlite'),
        'database' => $databasePath,
    ]);

    try {
        DB::setDefaultConnection($connectionName);
        Schema::create('table_sessions', function (Blueprint $table): void {
            $table->id();
            $table->string('guest_invite_token', 64)->nullable()->unique();
        });
        $legacyTableSession = new class extends Model
        {
            public $timestamps = false;

            protected $table = 'table_sessions';
        };
        $legacyTableSession->forceFill([
            'guest_invite_token' => str_repeat('P', 64),
        ])->save();
        $removal = require database_path('migrations/2026_08_24_102929_remove_legacy_guest_invite_token_from_table_sessions_table.php');

        expect(fn () => $removal->up())
            ->toThrow(RuntimeException::class, 'Legacy guest invitation credentials must be migrated to digests')
            ->and(Schema::hasColumn('table_sessions', 'guest_invite_token'))->toBeTrue();
    } finally {
        DB::setDefaultConnection($originalConnection);
        DB::purge($connectionName);
        config()->set("database.connections.{$connectionName}", null);
        File::delete($databasePath);
    }
});

test('active guest can create an invite share link for current table session', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext();

    $component = Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'venueName' => 'Guest Invite Branch',
        ])
        ->assertSeeText('Invite guest')
        ->call('createGuestInviteLink')
        ->assertSeeText('Invite link is ready.')
        ->assertSeeText('Copy link')
        ->assertSee('navigator.share', false);

    $inviteUrl = $component->get('guestInviteUrl');
    $tableSession->refresh();
    parse_str((string) parse_url($inviteUrl, PHP_URL_QUERY), $inviteQuery);
    $inviteToken = $inviteQuery['invite'] ?? null;

    expect($inviteToken)->toBeString()->toHaveLength(64);
    expect($tableSession->guest_invite_token_hash)->toBe(hash('sha256', $inviteToken));
    expect($tableSession->guest_invite_created_by_guest_id)->toBe($activeGuest->id);
    expect($tableSession->guest_invite_created_at)->not->toBeNull();
    expect($tableSession->guest_invite_expires_at)->not->toBeNull();
    expect($tableSession->guest_invite_expires_at->isFuture())->toBeTrue();
    expect($inviteUrl)->toContain('/q/'.$qrCode->public_token);
    expect($inviteUrl)->toContain('invite='.$inviteToken);
});

test('guest invite link opens landing and creates a pending join request', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext();

    $actions = Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
        ])
        ->call('createGuestInviteLink');

    parse_str((string) parse_url($actions->get('guestInviteUrl'), PHP_URL_QUERY), $inviteQuery);
    $inviteToken = $inviteQuery['invite'] ?? null;

    expect($inviteToken)->toBeString()->toHaveLength(64);

    $component = Livewire::withQueryParams(['invite' => $inviteToken])
        ->withCookie(guestInviteShareCookieName($qrCode), str_repeat('x', 64))
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('state', 'ready')
        ->assertSet('hasCurrentInviteToken', true)
        ->assertSeeText('Enter your name to ask to join this table.');

    expect(session(guestInviteShareInviteSessionKey($qrCode)))->toBe($inviteToken);

    $component
        ->set('guestName', '  Jonas  ')
        ->call('enterTable')
        ->assertHasNoErrors()
        ->assertSet('preparedGuestName', 'Jonas')
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSet('guestCanAddItems', false)
        ->assertSeeText('Request sent. Waiting for guests at the table.')
        ->assertSeeText('Request sent');

    $joinRequest = TableSessionJoinRequest::query()->firstOrFail();

    expect($joinRequest->table_session_id)->toBe($tableSession->id);
    expect($joinRequest->guest_name)->toBe('Jonas');
    expect($joinRequest->status)->toBe(TableSessionJoinRequestStatus::Pending);
    expect(TableSessionGuest::query()->where('guest_name', 'Jonas')->exists())->toBeFalse();
});

test('creating a replacement guest invite rotates the bearer and invalidates the previous link', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext();
    $component = Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
        ])
        ->call('createGuestInviteLink');
    parse_str((string) parse_url($component->get('guestInviteUrl'), PHP_URL_QUERY), $firstQuery);
    $firstToken = $firstQuery['invite'] ?? null;

    $component->call('createGuestInviteLink');
    parse_str((string) parse_url($component->get('guestInviteUrl'), PHP_URL_QUERY), $secondQuery);
    $secondToken = $secondQuery['invite'] ?? null;

    expect($firstToken)->toBeString()->toHaveLength(64)
        ->and($secondToken)->toBeString()->toHaveLength(64)
        ->and($secondToken)->not->toBe($firstToken)
        ->and($tableSession->fresh()->guest_invite_token_hash)->toBe(hash('sha256', $secondToken));

    Livewire::withQueryParams(['invite' => $firstToken])
        ->withCookie(guestInviteShareCookieName($qrCode), str_repeat('x', 64))
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Old Link')
        ->call('enterTable')
        ->assertSet('entryState', 'guest_invite_invalid')
        ->assertSet('currentJoinRequestId', null);
});

test('expired guest invite cannot create a join request', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext();
    $actions = Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
        ])
        ->call('createGuestInviteLink');
    parse_str((string) parse_url($actions->get('guestInviteUrl'), PHP_URL_QUERY), $inviteQuery);
    $inviteToken = $inviteQuery['invite'] ?? null;
    $tableSession->forceFill(['guest_invite_expires_at' => now()->subSecond()])->save();

    Livewire::withQueryParams(['invite' => $inviteToken])
        ->withCookie(guestInviteShareCookieName($qrCode), str_repeat('x', 64))
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->set('guestName', 'Late Guest')
        ->call('enterTable')
        ->assertSet('entryState', 'guest_invite_invalid')
        ->assertSet('entryIssueCode', 'invite_expired')
        ->assertSet('currentJoinRequestId', null);

    expect(TableSessionJoinRequest::query()->count())->toBe(0);
});

test('join request action revalidates an invite bearer after locking the table session', function () {
    [, , $tableSession, $activeGuest] = createGuestInviteShareContext();
    $createdInvite = app(CreateGuestInviteLinkAction::class)->handle($tableSession, $activeGuest);
    $tableSession->forceFill([
        'guest_invite_token_hash' => hash('sha256', str_repeat('R', 64)),
    ])->save();

    $joinRequest = app(CreateTableSessionJoinRequestAction::class)->handle(
        $tableSession,
        'Stale invite',
        str_repeat('J', 64),
        $createdInvite->token,
    );

    expect($joinRequest)->toBeNull()
        ->and(TableSessionJoinRequest::query()->where('guest_name', 'Stale invite')->exists())->toBeFalse();
});

test('branch setting can disable guest invite links', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext(allowGuestInviteLinks: false);

    Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
        ])
        ->call('createGuestInviteLink')
        ->assertSet('guestInviteUrl', '')
        ->assertSeeText(__('ui.actions.tablesessions.createguestinvitelinkaction.priglaseniia_gostei_po'));

    expect($tableSession->fresh()->guest_invite_token_hash)->toBeNull();
});

test('guest invite action rejects a table session outside the public qr branch', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestInviteShareContext();
    [$otherQrCode] = createGuestInviteShareContext();

    Livewire::withCookie(guestInviteShareCookieName($qrCode), $activeGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $tableSession->id,
            'currentGuestId' => $activeGuest->id,
            'publicToken' => $otherQrCode->public_token,
        ])
        ->call('createGuestInviteLink')
        ->assertSet('guestInviteUrl', '')
        ->assertSet('guestInviteMessage', __('guest.table.invite_requires_active_guest'));

    expect($tableSession->fresh()->guest_invite_token_hash)->toBeNull();
});

test('guest invite bearer cannot be used through another restaurant qr', function () {
    [$sourceQrCode, , $sourceTableSession, $sourceGuest] = createGuestInviteShareContext();
    [$foreignQrCode] = createGuestInviteShareContext();
    $actions = Livewire::withCookie(guestInviteShareCookieName($sourceQrCode), $sourceGuest->guest_token)
        ->test(GuestActions::class, [
            'tableSessionId' => $sourceTableSession->id,
            'currentGuestId' => $sourceGuest->id,
            'publicToken' => $sourceQrCode->public_token,
        ])
        ->call('createGuestInviteLink');
    parse_str((string) parse_url($actions->get('guestInviteUrl'), PHP_URL_QUERY), $inviteQuery);
    $inviteToken = $inviteQuery['invite'] ?? null;

    Livewire::withQueryParams(['invite' => $inviteToken])
        ->withCookie(guestInviteShareCookieName($foreignQrCode), str_repeat('x', 64))
        ->test(GuestEntry::class, ['token' => $foreignQrCode->public_token])
        ->set('guestName', 'Cross Tenant')
        ->call('enterTable')
        ->assertSet('entryState', 'guest_invite_invalid')
        ->assertSet('currentTableSessionId', null)
        ->assertSet('currentJoinRequestId', null);

    expect(TableSessionJoinRequest::query()->where('guest_name', 'Cross Tenant')->exists())->toBeFalse();
});

function createGuestInviteShareContext(bool $allowGuestInviteLinks = true): array
{
    $organization = Organization::factory()->create(['name' => 'Guest Invite Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Guest Invite Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Guest Invite Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);
    BranchSetting::factory()
        ->for($branch)
        ->create(['allow_guest_invite_links' => $allowGuestInviteLinks]);
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Guest Invite Table',
            'is_active' => true,
            'status' => ServicePointStatus::Free,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'guestinvite'.fake()->unique()->numerify('######'),
            'short_code' => 'QR-GI'.fake()->unique()->numerify('####'),
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

    return [$qrCode, $servicePoint, $tableSession, $activeGuest, $branch];
}

function guestInviteShareCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}

function guestInviteShareInviteSessionKey(QrCode $qrCode): string
{
    return 'guest_invites.'.substr(hash('sha256', $qrCode->public_token), 0, 24).'.invite_token';
}
