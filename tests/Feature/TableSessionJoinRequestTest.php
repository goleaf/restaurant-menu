<?php

use App\Actions\TableSessions\ApproveTableSessionJoinRequestAction;
use App\Actions\TableSessions\CreateTableSessionJoinRequestAction;
use App\Actions\TableSessions\RejectTableSessionJoinRequestAction;
use App\Enums\TableSessionGuestStatus;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

test('table session join requests table stores join approval fields', function () {
    expect(Schema::hasTable('table_session_join_requests'))->toBeTrue();
    expect(Schema::hasColumns('table_session_join_requests', [
        'table_session_id',
        'guest_name',
        'guest_token',
        'status',
        'approved_by_guest_id',
        'rejected_by_guest_id',
        'approved_by_user_id',
        'rejected_by_user_id',
        'expires_at',
    ]))->toBeTrue();
});

test('table session join request statuses include fixed lifecycle states', function () {
    expect(TableSessionJoinRequestStatus::values())->toBe([
        'pending',
        'approved',
        'rejected',
        'expired',
    ]);
});

test('table session join request belongs to session and moderator guests', function () {
    $tableSession = TableSession::factory()->create();
    $approver = TableSessionGuest::factory()->for($tableSession)->create(['guest_name' => 'Approver']);
    $rejecter = TableSessionGuest::factory()->for($tableSession)->create(['guest_name' => 'Rejecter']);
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'approved_by_guest_id' => $approver->id,
            'rejected_by_guest_id' => $rejecter->id,
        ]);

    expect($joinRequest->tableSession->is($tableSession))->toBeTrue();
    expect($joinRequest->approvedByGuest->is($approver))->toBeTrue();
    expect($joinRequest->rejectedByGuest->is($rejecter))->toBeTrue();
    expect($tableSession->joinRequests()->whereKey($joinRequest->id)->exists())->toBeTrue();
    expect($approver->approvedJoinRequests()->whereKey($joinRequest->id)->exists())->toBeTrue();
    expect($rejecter->rejectedJoinRequests()->whereKey($joinRequest->id)->exists())->toBeTrue();
});

test('join request action creates pending request only when session has active guests', function () {
    $action = app(CreateTableSessionJoinRequestAction::class);
    $tableSession = TableSession::factory()->create();

    expect($action->handle($tableSession, '  Mira  '))->toBeNull();

    TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['status' => TableSessionGuestStatus::Active]);

    $joinRequest = $action->handle($tableSession, '  Mira  ');

    expect($joinRequest)->toBeInstanceOf(TableSessionJoinRequest::class);
    expect($joinRequest->table_session_id)->toBe($tableSession->id);
    expect($joinRequest->guest_name)->toBe('Mira');
    expect($joinRequest->status)->toBe(TableSessionJoinRequestStatus::Pending);
    expect($joinRequest->guest_token)->not->toBeNull();
    expect(strlen($joinRequest->guest_token))->toBe(64);
    expect($joinRequest->expires_at)->not->toBeNull();
});

test('join request creation is idempotent for the same guest credential', function () {
    $tableSession = TableSession::factory()->active()->create();
    TableSessionGuest::factory()->for($tableSession)->active()->create();
    $credential = str_repeat('J', 64);
    $action = app(CreateTableSessionJoinRequestAction::class);

    $first = $action->handle($tableSession, 'Mira', $credential);
    $second = $action->handle($tableSession, 'Mira', $credential);

    expect($first)->toBeInstanceOf(TableSessionJoinRequest::class)
        ->and($second?->id)->toBe($first->id)
        ->and($tableSession->joinRequests()->count())->toBe(1);
});

test('pending join requests are bounded per table session', function () {
    $tableSession = TableSession::factory()->active()->create();
    TableSessionGuest::factory()->for($tableSession)->active()->create();
    TableSessionJoinRequest::factory()
        ->count(20)
        ->for($tableSession)
        ->pending()
        ->create();

    $joinRequest = app(CreateTableSessionJoinRequestAction::class)
        ->handle($tableSession, 'Overflow guest', str_repeat('B', 64));

    expect($joinRequest)->toBeNull()
        ->and($tableSession->joinRequests()->count())->toBe(20);
});

test('active guest can approve pending join request into active table guest', function () {
    $tableSession = TableSession::factory()->create();
    $approver = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['status' => TableSessionGuestStatus::Active]);
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'guest_name' => 'Lina',
            'status' => TableSessionJoinRequestStatus::Pending,
        ]);

    $guest = app(ApproveTableSessionJoinRequestAction::class)->handle($joinRequest, $approver);

    expect($guest->table_session_id)->toBe($tableSession->id);
    expect($guest->guest_name)->toBe('Lina');
    expect($guest->guest_token)->toBe($joinRequest->guest_token);
    expect($guest->status)->toBe(TableSessionGuestStatus::Active);
    expect($guest->joined_at)->not->toBeNull();
    expect($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Approved);
    expect($joinRequest->fresh()->approved_by_guest_id)->toBe($approver->id);
});

test('repeated approval returns the same guest without duplicating membership', function () {
    $tableSession = TableSession::factory()->active()->create();
    $approver = TableSessionGuest::factory()->for($tableSession)->active()->create();
    $joinRequest = TableSessionJoinRequest::factory()->for($tableSession)->pending()->create();
    $action = app(ApproveTableSessionJoinRequestAction::class);

    $first = $action->handle($joinRequest, $approver);
    $second = $action->handle($joinRequest->fresh(), $approver);

    expect($second->id)->toBe($first->id)
        ->and(TableSessionGuest::query()->where('guest_token', $joinRequest->guest_token)->count())->toBe(1)
        ->and($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Approved);
});

test('active guest can reject pending join request without creating table guest', function () {
    $tableSession = TableSession::factory()->create();
    $rejecter = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['status' => TableSessionGuestStatus::Active]);
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create(['status' => TableSessionJoinRequestStatus::Pending]);

    $result = app(RejectTableSessionJoinRequestAction::class)->handle($joinRequest, $rejecter);

    expect($result->status)->toBe(TableSessionJoinRequestStatus::Rejected);
    expect($result->rejected_by_guest_id)->toBe($rejecter->id);
    expect(
        TableSessionGuest::query()
            ->where('guest_token', $joinRequest->guest_token)
            ->exists()
    )->toBeFalse();
});

test('repeated rejection is idempotent for the same decided request', function () {
    $tableSession = TableSession::factory()->active()->create();
    $rejecter = TableSessionGuest::factory()->for($tableSession)->active()->create();
    $joinRequest = TableSessionJoinRequest::factory()->for($tableSession)->pending()->create();
    $action = app(RejectTableSessionJoinRequestAction::class);

    $first = $action->handle($joinRequest, $rejecter);
    $second = $action->handle($joinRequest->fresh(), $rejecter);

    expect($second->id)->toBe($first->id)
        ->and($second->status)->toBe(TableSessionJoinRequestStatus::Rejected)
        ->and(TableSessionGuest::query()->where('guest_token', $joinRequest->guest_token)->count())->toBe(0);
});

test('non active guest cannot approve or reject join request', function () {
    $tableSession = TableSession::factory()->create();
    $removedGuest = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['status' => TableSessionGuestStatus::Removed]);
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create(['status' => TableSessionJoinRequestStatus::Pending]);

    expect(fn () => app(ApproveTableSessionJoinRequestAction::class)->handle($joinRequest, $removedGuest))
        ->toThrow(ValidationException::class);

    expect(fn () => app(RejectTableSessionJoinRequestAction::class)->handle($joinRequest, $removedGuest))
        ->toThrow(ValidationException::class);
});

test('expired join request is marked expired when moderation is attempted', function () {
    $tableSession = TableSession::factory()->create();
    $approver = TableSessionGuest::factory()
        ->for($tableSession)
        ->create(['status' => TableSessionGuestStatus::Active]);
    $joinRequest = TableSessionJoinRequest::factory()
        ->for($tableSession)
        ->create([
            'status' => TableSessionJoinRequestStatus::Pending,
            'expires_at' => now()->subMinute(),
        ]);

    expect(fn () => app(ApproveTableSessionJoinRequestAction::class)->handle($joinRequest, $approver))
        ->toThrow(ValidationException::class);

    expect($joinRequest->fresh()->status)->toBe(TableSessionJoinRequestStatus::Expired);
});
