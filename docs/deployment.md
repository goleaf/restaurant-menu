<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Deployment


## Branch control update — 2026-09-15

Apply the additive `2026_09_15_121714_add_report_name_index_to_order_items_table` migration through the existing one-time deployment procedure after backup. It adds the report-name index without rewriting order names, timestamps or prices; its isolated rollback only removes that index. Existing working databases are not migrated by the development verification run. No cron, worker, Redis, WebSocket or runtime Node dependency is added. Rebuild frontend assets for the updated Blade screens and refresh the usual caches.


## Product-level migration and repeatable setup — 2026-09-15

Deploy code with the additive photo-presentation migration using the existing migration procedure and a verified backup; do not rewrite existing migrations or reset application data. No new dependency, service, scheduler requirement or secret is introduced. `composer setup` calls `app:ensure-key`, which preserves an existing environment-file or externally configured APP_KEY and only generates a missing key. The command is tested with disposable environment files; the working key is never regenerated for verification. Public menu cache payload moves to v7 and invalidation includes previous v6 keys.

## Interaction follow-up deployment contract — 2026-09-15

This follow-up changes first-party Blade/JavaScript and Livewire recovery handling only. Deploy the normal compiled Vite assets together with the PHP/Blade release; no dependency update, migration, service, cron job or queue worker is added. The browser test's bounded multipart adapter exists only in the isolated Pest application because the installed Pest HTTP driver omits multipart files. It must never be registered in runtime middleware. Permanent upload failures retain the batch for explicit retry; a completed receipt prevents duplicating a committed upload after a callback error.

## Rollback media cleanup limit — 2026-09-15

No new dependency or migration is required for this recovery update. If storage refuses deletion during a rolled-back upload, the original database exception is preserved and cleanup attempts continue for the other new variants. When logging is available, `Unable to clean up a rolled-back image.` records the generated path and exception class.

A failed rollback deletion can leave an unreferenced generated file; this boundary is not a durable cleanup queue. After correcting disk permissions/capacity, verify that the logged path and its variants are not referenced by any image-owning record before manually removing only those confirmed orphaned files. Preserve all referenced old and replacement images. Normal committed catalogue image operations continue to use the existing resumable cleanup ledger. Required guest and restaurant workflows still need no queue worker or cron.

## Required platform

Catalogue UI deletion persists progress and pending media paths in `menu_operations` and its category frontier. Each continuation processes at most 50 entities; failed cleanup remains retryable after reload. Keep the same database and local media disk available across requests. The direct legacy parent-deletion Actions still use private temporary cleanup spools and synchronous after-commit cleanup; they are not the resumable UI protocol and must not be used for unbounded HTTP work.

- PHP `>=8.5.0 <8.6.0` with Laravel-required extensions plus PDO SQLite, SQLite3, intl, mbstring, OpenSSL, fileinfo, GD and EXIF. Composer checks the SQLite and image-processing extension requirements; GD must decode the offered JPEG/PNG/WebP formats. HEIC and AVIF are not accepted.
- Writable SQLite database directory and database file.
- Writable `storage` and `bootstrap/cache` paths.
- PHP `upload_max_filesize` and `post_max_size` values larger than the biggest SQLite snapshot that operators may restore (the application rejects restore uploads above 256 MB).
- Composer 2, Node.js 22.12+ or 24 LTS and npm for the release build.
- HTTPS, production `APP_ENV`, `APP_DEBUG=false`, a unique `APP_KEY`, correct `APP_URL` and secure session cookies.
- A working production mail transport and `ERROR_NOTIFICATION_EMAIL` when operations email alerts are enabled.

Core operation does not require a worker, cron, supervisor, Redis, S3, Docker, WebSockets or persistent SSH. If database queues are enabled for optional work, deployment must also supply and monitor a compatible worker; no required user workflow assumes it.

Apply `2026_09_14_160946_create_menu_operations_tables` before serving the updated catalogue. It creates two operation-ledger tables without rewriting restaurant records. Do not roll it back while operations or pending media cleanup remain. Photo uploads retain the 2 MB/file and eight-image/item limits, reject images above 20 megapixels or an 8192-pixel edge, and check estimated decoder memory before allocation. Display and thumbnail variants are generated synchronously; original uploads are not retained. Existing image paths remain readable.

All application PHP requests share the SQLite restore coordination lock through session persistence. The restore request acquires exclusive access before authentication reads. `storage/framework/sqlite-restore.lock.blocked` is a durable recovery barrier; do not remove it merely to clear a 503. Read the verified-state recovery procedure in [operations.md](operations.md). External CLI database writers must be quiesced separately.

## Reproducible release

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Before promotion, run the complete release gates in [`testing.md`](testing.md). After promotion, verify `/up`, its self-contained JSON `status`, `X-Request-Id` and no-store response without any asset requests; then verify login, one public QR route, an authorized staff dashboard, asset delivery and recent application/browser logs. Test-fire error notification delivery with an authorized staging failure before enabling it in production. Never run demo seeding or `migrate:fresh` in production.

## Environment and data

Copy only key names/default-safe examples from `.env.example`; secrets stay in the deployment environment. Use `LOG_STACK=daily`, `LOG_LEVEL=info`, a bounded `LOG_DAILY_DAYS`, and keep the four `HEALTH_CHECK_*` dependency checks enabled. Error alerts are disabled until a real `ERROR_NOTIFICATION_EMAIL` and production mailer are configured; the file cache is the safe default deduplication store when the database itself fails. The release process must preserve the SQLite database and private files outside disposable release directories. Take a consistent verified backup before schema changes and retain the previous application release for rollback. Keep `storage/app/private/backups/sqlite` writable and outside publicly served paths; it holds temporary restore candidates and retained pre-restore safety snapshots.

Rollback code/assets to the previous compatible release. Schema rollback is used only when the migration explicitly proves it is safe and no new data would be destroyed; otherwise roll forward. Forward data migrations document compatibility and verification in the migration/ADR.

Shared-hosting alternatives and panel-specific mechanics may be retained in `DEPLOY_SHARED_HOSTING.md`, but this file is the canonical deployment contract.
