# Deployment

## Required platform

- PHP `>=8.5.0 <8.6.0` with Laravel-required extensions plus PDO SQLite, intl, mbstring, OpenSSL, fileinfo and GD where image handling requires it.
- Writable SQLite database directory and database file.
- Writable `storage` and `bootstrap/cache` paths.
- Composer 2, Node.js 22.12+ or 24 LTS and npm for the release build.
- HTTPS, production `APP_ENV`, `APP_DEBUG=false`, a unique `APP_KEY`, correct `APP_URL` and secure session cookies.

Core operation does not require a worker, cron, supervisor, Redis, S3, Docker, WebSockets or persistent SSH. If database queues are enabled for optional work, deployment must also supply and monitor a compatible worker; no required user workflow assumes it.

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

Before promotion, run the complete release gates in [`testing.md`](testing.md). After promotion, verify `/up`, login, one public QR route, an authorized staff dashboard, asset delivery and recent application/browser logs. Never run demo seeding or `migrate:fresh` in production.

## Environment and data

Copy only key names/default-safe examples from `.env.example`; secrets stay in the deployment environment. The release process must preserve the SQLite database and private files outside disposable release directories. Take a consistent verified backup before schema changes and retain the previous application release for rollback.

Rollback code/assets to the previous compatible release. Schema rollback is used only when the migration explicitly proves it is safe and no new data would be destroyed; otherwise roll forward. Forward data migrations document compatibility and verification in the migration/ADR.

Shared-hosting alternatives and panel-specific mechanics may be retained in `DEPLOY_SHARED_HOSTING.md`, but this file is the canonical deployment contract.
