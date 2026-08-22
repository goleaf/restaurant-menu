# Error handling strategy

Expected validation and domain conflicts return stable `ApplicationErrorType`/`BusinessRuleCode` meanings with localized safe messages. Authorization failures disclose no cross-tenant data. Unexpected exceptions are reported with bounded non-sensitive context and present a generic localized message; production never exposes stack traces or SQL.

Livewire preserves recoverable input, associates errors with fields, targets the action-specific busy state and prevents duplicate mutations. Exceptions are not swallowed. Logging must redact credentials, tokens, sessions, authorization headers and unnecessary personal data. See [`security.md`](security.md) and [`operations.md`](operations.md); tests live in `BusinessRuleExceptionTest`, `ErrorHandlingStrategyTest` and the workflow regressions.
