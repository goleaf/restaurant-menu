<?php

use App\Enums\ApplicationErrorType;
use App\Enums\BusinessRuleCode;
use App\Exceptions\BusinessRuleViolation;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Route;

test('application error catalog covers the shared error strategy', function () {
    expect(ApplicationErrorType::values())->toBe([
        'validation_error',
        'permission_denied',
        'branch_access_denied',
        'qr_not_found',
        'qr_disabled',
        'session_closed',
        'guest_rejected_removed',
        'draft_locked',
        'order_invalid_transition',
        'payment_invalid_amount',
        'file_upload_error',
        'system_error',
    ])
        ->and(ApplicationErrorType::ValidationError->statusCode())->toBe(422)
        ->and(ApplicationErrorType::BranchAccessDenied->statusCode())->toBe(403)
        ->and(ApplicationErrorType::QrNotFound->statusCode())->toBe(404)
        ->and(ApplicationErrorType::SessionClosed->statusCode())->toBe(409)
        ->and(ApplicationErrorType::SystemError->statusCode())->toBe(500)
        ->and(ApplicationErrorType::SystemError->title())->toBe(__('errors.types.system_error.title'))
        ->and(ApplicationErrorType::SystemError->message())->toBe(__('errors.types.system_error.message'));
});

test('business rule codes map into application error types', function () {
    expect(BusinessRuleCode::SessionClosed->errorType())->toBe(ApplicationErrorType::SessionClosed)
        ->and(BusinessRuleCode::DraftLocked->errorType())->toBe(ApplicationErrorType::DraftLocked)
        ->and(BusinessRuleCode::GuestNotApproved->errorType())->toBe(ApplicationErrorType::GuestRejectedOrRemoved)
        ->and(BusinessRuleCode::OrderAlreadyCancelled->errorType())->toBe(ApplicationErrorType::OrderInvalidTransition)
        ->and(BusinessRuleCode::PaymentExceedsRemaining->errorType())->toBe(ApplicationErrorType::PaymentInvalidAmount)
        ->and(BusinessRuleCode::QrDisabled->errorType())->toBe(ApplicationErrorType::QrDisabled)
        ->and(BusinessRuleCode::BranchInaccessible->errorType())->toBe(ApplicationErrorType::BranchAccessDenied)
        ->and(BusinessRuleCode::RequiredModifierMissing->errorType())->toBe(ApplicationErrorType::ValidationError);
});

test('business rule violations render controlled json without context leaks', function () {
    Route::get('/__test-errors/business-rule-json', function (): never {
        throw BusinessRuleViolation::for(
            rule: BusinessRuleCode::SessionClosed,
            field: 'draft_edit',
            context: ['guest_token' => 'secret-guest-token'],
        );
    });

    $this->getJson('/__test-errors/business-rule-json')
        ->assertStatus(409)
        ->assertJsonPath('message', ApplicationErrorType::SessionClosed->message())
        ->assertJsonPath('error.type', ApplicationErrorType::SessionClosed->value)
        ->assertJsonPath('error.code', BusinessRuleCode::SessionClosed->value)
        ->assertJsonPath('errors.draft_edit.0', BusinessRuleCode::SessionClosed->message())
        ->assertDontSee('secret-guest-token');
});

test('production permission errors render actionable admin copy without raw exception message', function () {
    config()->set('app.debug', false);
    $user = User::factory()->create();

    Route::get('/__test-errors/forbidden', fn () => abort(403, 'raw manage_payments permission failure'));

    $this->actingAs($user)
        ->get('/__test-errors/forbidden')
        ->assertForbidden()
        ->assertSee(__('errors.admin.permission_denied.title'))
        ->assertSee(__('errors.admin.permission_denied.hint'))
        ->assertDontSee('raw manage_payments permission failure')
        ->assertDontSee('manage_payments');
});

test('guest not found errors render friendly copy without token details', function () {
    config()->set('app.debug', false);

    Route::get('/guest/__test-error-not-found', fn () => abort(404, 'raw guest invite token secret'));

    $this->get('/guest/__test-error-not-found')
        ->assertNotFound()
        ->assertSee(__('errors.guest.not_found.title'))
        ->assertSee(__('errors.guest.not_found.hint'))
        ->assertDontSee('raw guest invite token secret');
});

test('production system errors hide raw exception messages and do not create audit logs', function () {
    config()->set('app.debug', false);
    $beforeCount = AuditLog::query()->count();

    Route::get('/__test-errors/system', function (): never {
        throw new RuntimeException('raw stack trace secret should stay in logs only');
    });

    $this->get('/__test-errors/system')
        ->assertStatus(500)
        ->assertSee(__('errors.admin.system.title'))
        ->assertSee(__('errors.admin.system.hint'))
        ->assertDontSee('raw stack trace secret should stay in logs only');

    expect(AuditLog::query()->count())->toBe($beforeCount);
});
