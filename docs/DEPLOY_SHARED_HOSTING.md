# Shared-hosting notes

The canonical release contract is [`deployment.md`](deployment.md), with runtime operations in [`operations.md`](operations.md).

Point the public document root at `public/`; keep `.env`, SQLite and private files outside public access; ensure `storage` and `bootstrap/cache` are writable; build assets before release; run only forward production migrations; cache configuration/routes/views; and verify `/up`, login and a public QR page. Core workflows do not require workers, cron, supervisor, Redis, S3, WebSockets, Docker or SSH. If the host cannot create `public/storage`, configure the equivalent safe symlink/path without exposing private disks.
