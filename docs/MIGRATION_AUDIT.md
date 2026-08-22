# Migration audit

The 2026-08-22 baseline contains 65 migrations targeting SQLite, 48 current application/framework tables, foreign keys/indexes and no views, triggers or stored routines. Important workflow entities use soft deletion and order items carry historical snapshots.

Historical migrations include model-based backfills and therefore present drift risk, but deployed migration files are not rewritten. Corrections use forward-only migrations with expand/backfill/verify/switch/contract where necessary. Fresh test migration, representative upgrade, foreign-key/unique checks and fresh seed are mandatory. The canonical schema contract is [`data-model.md`](data-model.md).
