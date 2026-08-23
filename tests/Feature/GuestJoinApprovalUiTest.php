<?php

use App\Actions\TableSessions\ApproveTableSessionJoinRequestAction;
use App\Enums\QrCodeStatus;
use App\Enums\ServicePointStatus;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Livewire\PublicQr\GuestEntry;
use App\Livewire\PublicQr\JoinRequests;
use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\Brand;
use App\Models\Organization;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Mockery\MockInterface;

test('active guest can approve a pending guest from the polled join requests block', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestJoinApprovalContext();
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Jonas',
            'status' => TableSessionJoinRequestStatus::Pending,
        ]);

    Livewire::withCookie(guestJoinApprovalCookieName($qrCode), $activeGuest->guest_token)
        ->test(JoinRequests::class, [
            'tableSessionId' => $tableSession->id,
            'guestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->assertSet('canModerate', true)
        ->assertSeeText('Jonas')
        ->call('approve', $joinRequest->id)
        ->assertSeeText('Guest approved.');

    $approvedGuest = TableSessionGuest::query()
        ->where('guest_token', $joinRequest->guest_token)
        ->firstOrFail();

    expect($approvedGuest->table_session_id)->toBe($tableSession->id);
    expect($approvedGuest->guest_name)->toBe('Jonas');
    expect($approvedGuest->status)->toBe(TableSessionGuestStatus::Active);
    expect($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Approved);
    expect($joinRequest->fresh()->approved_by_guest_id)->toBe($activeGuest->id);
});

test('waiting guest becomes active after approval status polling', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestJoinApprovalContext();
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Mila',
            'status' => TableSessionJoinRequestStatus::Pending,
        ]);

    $waitingGuest = Livewire::withCookie(guestJoinApprovalCookieName($qrCode), $joinRequest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', null)
        ->assertSet('currentJoinRequestId', $joinRequest->id)
        ->assertSet('guestCanAddItems', false)
        ->assertSeeText('Request sent. Waiting for guests at the table.');

    app(ApproveTableSessionJoinRequestAction::class)->handle($joinRequest, $activeGuest);

    $approvedGuest = TableSessionGuest::query()
        ->where('guest_token', $joinRequest->guest_token)
        ->firstOrFail();

    $waitingGuest
        ->call('refreshJoinRequestStatus')
        ->assertSet('currentTableSessionId', $tableSession->id)
        ->assertSet('currentGuestId', $approvedGuest->id)
        ->assertSet('currentJoinRequestId', null)
        ->assertSet('guestCanAddItems', true)
        ->assertSeeText('Entry saved')
        ->assertSeeText('Entry saved');
});

test('waiting guest sees rejection after a current guest rejects from the join requests block', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestJoinApprovalContext();
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Nina',
            'status' => TableSessionJoinRequestStatus::Pending,
        ]);

    $waitingGuest = Livewire::withCookie(guestJoinApprovalCookieName($qrCode), $joinRequest->guest_token)
        ->test(GuestEntry::class, ['token' => $qrCode->public_token])
        ->assertSet('currentJoinRequestId', $joinRequest->id);

    Livewire::withCookie(guestJoinApprovalCookieName($qrCode), $activeGuest->guest_token)
        ->test(JoinRequests::class, [
            'tableSessionId' => $tableSession->id,
            'guestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->call('reject', $joinRequest->id)
        ->assertSeeText('Guest rejected.');

    $waitingGuest
        ->call('refreshJoinRequestStatus')
        ->assertSet('currentGuestId', null)
        ->assertSet('currentJoinRequestId', $joinRequest->id)
        ->assertSet('guestCanAddItems', false)
        ->assertSet('entryState', 'join_request_blocked')
        ->assertSeeText('Your request to join this table was rejected.')
        ->assertSeeText('Request closed');

    expect($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Rejected);
    expect(
        TableSessionGuest::query()
            ->where('guest_token', $joinRequest->guest_token)
            ->exists()
    )->toBeFalse();
});

test('join request moderation shows the first validation error returned by the action', function () {
    [$qrCode, , $tableSession, $activeGuest] = createGuestJoinApprovalContext();
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Tomas',
            'status' => TableSessionJoinRequestStatus::Pending,
        ]);

    $this->mock(ApproveTableSessionJoinRequestAction::class, function (MockInterface $mock): void {
        $mock->shouldReceive('handle')
            ->once()
            ->andThrow(ValidationException::withMessages([
                'join_request' => ['The join request changed before approval.'],
            ]));
    });

    Livewire::withCookie(guestJoinApprovalCookieName($qrCode), $activeGuest->guest_token)
        ->test(JoinRequests::class, [
            'tableSessionId' => $tableSession->id,
            'guestId' => $activeGuest->id,
            'publicToken' => $qrCode->public_token,
            'language' => 'en',
        ])
        ->call('approve', $joinRequest->id)
        ->assertSet('notice', 'The join request changed before approval.')
        ->assertSet('noticeTone', 'warning')
        ->assertSeeText('The join request changed before approval.');

    expect($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Pending);
});

function createGuestJoinApprovalContext(): array
{
    $organization = Organization::factory()->create(['name' => 'Join Approval Group']);
    $brand = Brand::factory()->for($organization)->create(['name' => 'Join Approval Brand']);
    $branch = Branch::factory()
        ->for($organization)
        ->for($brand)
        ->create([
            'name' => 'Join Approval Branch',
            'city' => 'Vilnius',
            'country' => 'Lithuania',
        ]);
    BranchSetting::factory()->for($branch)->create();
    $servicePoint = ServicePoint::factory()
        ->for($branch)
        ->create([
            'name' => 'Join Approval Table',
            'is_active' => true,
            'status' => ServicePointStatus::Free,
        ]);
    $qrCode = QrCode::factory()
        ->for($servicePoint)
        ->create([
            'public_token' => 'joinapproval'.fake()->unique()->numerify('######'),
            'short_code' => 'QR-JA'.fake()->unique()->numerify('####'),
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

    return [$qrCode, $servicePoint, $tableSession, $activeGuest];
}

function guestJoinApprovalCookieName(QrCode $qrCode): string
{
    return 'guest_token_'.substr(hash('sha256', $qrCode->public_token), 0, 24);
}
