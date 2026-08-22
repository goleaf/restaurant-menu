# Migration audit

The final 2026-08-22 chain contains 66 migrations targeting SQLite. The added forward migration secures invitation credentials/acceptance without deleting existing data. The schema retains foreign keys/indexes, soft-deleted history and immutable order snapshots; no view, trigger or stored routine dependency was introduced.

Historical migrations include model-based backfills and therefore present drift risk, but deployed migration files were not rewritten. Isolated fresh migration completed all 66 files in 0.52 seconds, followed by default and twice-demo seeds. The canonical schema contract is [`data-model.md`](data-model.md).
