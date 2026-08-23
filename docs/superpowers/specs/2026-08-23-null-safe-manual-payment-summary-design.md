# Null-safe manual payment summary design

## Context

`BuildManualPaymentSummaryAction` has failed repeatedly when an HTTP request observed `NULL` in cents-based payment snapshots or branch service-charge settings. The canonical schema declares these columns `NOT NULL`, but SQLite file replacement or a legacy/intermediate database state can expose nullable rows to an already-running application request.

The payment summary must remain financially conservative: unexpected missing values must never increase the paid amount or reduce the outstanding balance.

## Goals

- Keep waiter payment summaries available when nullable legacy/intermediate money data is encountered.
- Preserve integer-only minor-unit arithmetic.
- Treat unknown payment amounts as unpaid instead of inventing value.
- Record safe diagnostic context without logging guest data, notes, tokens, or other sensitive values.
- Keep the current Eloquent query count and UI contract unchanged.

## Non-goals

- Relaxing the canonical `NOT NULL` database constraints.
- Repairing or rewriting SQLite backup files from the summary action.
- Adding float-based compatibility calculations.
- Changing payment recording, Blade, Livewire, routes, or translations.

## Design

`BuildManualPaymentSummaryAction` will normalize every nullable integer snapshot it consumes at its existing presentation boundary:

- `covered_subtotal_cents`, `service_charge_cents`, `tips_cents`, and `amount_cents` fall back to `0`.
- A missing `service_charge_basis_points` value falls back to the branch defaults already returned by `BranchSetting::defaults()`.
- Normalization remains private to the Action; model casts and database constraints remain strict for all write paths.

The Action will collect the affected record ID and column name while building one summary, then emit one warning with bounded structured context. It will not log monetary values, customer-visible text, or credentials.

This is fail-closed for settlement: missing data can leave more money outstanding, but cannot mark an unpaid table as paid.

## Data flow

1. The existing bounded Eloquent graph reloads the table session, branch settings, payments, guests, and orders.
2. Nullable legacy/intermediate integers are normalized before collection aggregation or formatter calls.
3. The summary uses only integers and returns the existing array shape.
4. If normalization occurred, one warning is recorded after the summary is built.

## Testing

A focused Pest regression will temporarily make the affected columns nullable in the isolated test SQLite database, create factory-backed records containing `NULL`, and execute the real summary Action. It will assert:

- the original two type errors no longer occur;
- paid values normalize to zero and never overstate settlement;
- service-charge basis points use the branch default;
- one safe warning is recorded;
- the temporary schema is restored in a `finally` block.

The existing manual-payment feature suite, Pint, Larastan, and a real Herd table-detail request remain the verification gates.
