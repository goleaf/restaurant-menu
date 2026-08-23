# Migration audit

The verified 2026-08-23 chain contains 73 migrations targeting SQLite. Forward migrations secure invitation credentials, preserve item-cancellation history, migrate monetary storage to integer cents and add menu-item variants without rewriting historical deployed migrations. The schema retains foreign keys/indexes, soft-deleted history and immutable order snapshots; no view, trigger or stored routine dependency was introduced.

Historical migrations include model-based backfills and therefore present drift risk, but deployed migration files were not rewritten. Isolated fresh migration completed all 73 files, followed by two complete demo seeds. The canonical schema contract is [`data-model.md`](data-model.md).
