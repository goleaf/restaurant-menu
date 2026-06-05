# Error Handling Strategy

Prompt 343 defines the shared error approach for this project.

## Principles

- Expected business errors return controlled translated messages.
- Unexpected technical exceptions are logged by Laravel and rendered through safe error pages.
- Guests never see stack traces, raw exception messages, internal IDs, tokens, or permission keys.
- Staff/admin users see actionable guidance, not raw exception messages.
- Superadmins/developers use application logs for technical detail.
- Activity logs are written for business actions only, not for every technical exception.

## Error Types

The shared catalog is `App\Enums\ApplicationErrorType`:

- `validation_error`;
- `permission_denied`;
- `branch_access_denied`;
- `qr_not_found`;
- `qr_disabled`;
- `session_closed`;
- `guest_rejected_removed`;
- `draft_locked`;
- `order_invalid_transition`;
- `payment_invalid_amount`;
- `file_upload_error`;
- `system_error`.

Each type has a status code and translation keys:

- `errors.types.{type}.title`;
- `errors.types.{type}.message`.

## Expected Business Errors

Expected domain denials use `App\Exceptions\BusinessRuleViolation`.

- It extends Laravel `ValidationException`.
- It implements `ShouldntReport`.
- It exposes the `BusinessRuleCode` and mapped `ApplicationErrorType`.
- JSON/API responses include only controlled `message`, `error.type`, `error.code`, and validation `errors`.
- Context is for internal use only and must not be rendered to guests or ordinary staff.

Current `BusinessRuleCode` values map into the shared catalog through `BusinessRuleCode::errorType()`.

## HTTP Error Pages

Production-safe Blade error pages live in `resources/views/errors/`.

- `403` uses permission-denied copy.
- `404` uses QR/guest-friendly copy on guest surfaces and admin copy on staff surfaces.
- `419` uses session-expired copy.
- `422` uses validation copy.
- `500` and `5xx` use system-error copy.

The error shell must not render `$exception->getMessage()`.

## Backend Handler

`bootstrap/app.php` configures:

- JSON rendering for API routes.
- duplicate exception report suppression.
- `BusinessRuleViolation` as non-reportable.
- safe global exception context: method, path, route name, and guest-surface flag.
- controlled JSON rendering for `BusinessRuleViolation`.

Do not swallow unexpected exceptions. Let Laravel report them.

## UI Rules

- Validation errors should use Laravel/Livewire validation messages.
- Business action feedback should use translated Flux toasts or validation errors.
- Guest errors should be friendly and tell the guest to retry or ask staff.
- Admin errors should be actionable: check role, permission override, branch access, record state, or logs.
- Normal users must not see stack traces, raw exception messages, sensitive paths, tokens, permission internals, SQL, or config values.

## Tests

Focused command:

```bash
php artisan test --compact tests/Feature/ErrorHandlingStrategyTest.php tests/Feature/BusinessRuleExceptionTest.php
```
