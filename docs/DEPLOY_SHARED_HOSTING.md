# Shared Hosting Deployment Notes

This project is designed for a simple shared-hosting setup:

- Laravel + Blade + Livewire.
- SQLite database file.
- Database cache, database sessions, and database queue.
- Local media files in `storage/app/public`.
- No Redis, WebSockets, S3, Docker, paid storage, or external queue service.

## Web Root

Point the hosting domain document root to:

```text
public
```

Do not point the public web root at the project root. The SQLite database file,
`.env`, `storage`, and application code must stay outside the public web root.

## Environment

Create a real `.env` on the server from `.env.example`. Do not commit it.

Minimum shared-hosting values:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.example

DB_CONNECTION=sqlite
DB_DATABASE=/absolute/server/path/to/project/database/database.sqlite
DB_FOREIGN_KEYS=true

CACHE_STORE=database
SESSION_DRIVER=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=public
BROADCAST_CONNECTION=log
```

`DB_DATABASE` may be left empty only when Laravel can safely resolve
`database/database.sqlite` in the deployed project path. On shared hosting, an
absolute path is clearer and safer.

Generate the application key once on the server:

```bash
php artisan key:generate
```

## SQLite

The default SQLite file is:

```text
database/database.sqlite
```

Create it before running migrations:

```bash
touch database/database.sqlite
```

The `database` directory and `database/database.sqlite` file must be writable by
the PHP user. Keep the file outside `public/`.

Do not commit SQLite database files. The repository ignores `database/*.sqlite*`.

## Writable Paths

These paths must be writable by PHP:

```text
database
database/database.sqlite
storage/app/public
storage/framework/cache
storage/framework/sessions
storage/framework/views
storage/logs
bootstrap/cache
```

If your host exposes a file manager instead of shell access, set writable
permissions there. Avoid broad permissions such as `777` unless your host
explicitly requires them; prefer the narrowest permission that allows PHP to
write.

## Local Storage And Public Files

Uploaded logos and future dish images are stored on Laravel's `public` disk:

```text
storage/app/public
```

Laravel expects these files to be reachable at:

```text
public/storage
```

When symbolic links are available, run:

```bash
php artisan storage:link
```

This creates:

```text
public/storage -> storage/app/public
```

If symbolic links are not available, use your host's control panel to map
`public/storage` to `storage/app/public`, or ask the host to enable symlinks for
this directory. As a last resort, `public/storage` must contain the same files as
`storage/app/public`, but that manual-copy approach requires extra care because
new uploads must stay in sync.

Do not commit `public/storage`, local media uploads, or copied storage files.

## Migrations

Run migrations after the SQLite file exists:

```bash
php artisan migrate --force
```

The migrations create the base Laravel tables plus this project's cache,
session, queue, restaurant, QR, guest, order, payment, notification, and audit
tables.

Optional demo data for local QA or a first test install:

```bash
php artisan db:seed --class=DemoRestaurantSeeder --force
```

Do not run the demo seeder on production data unless you intentionally want demo
records.

## Database Cache

Cache uses the `cache` and `cache_locks` tables. Keep:

```ini
CACHE_STORE=database
```

Do not configure Redis or cache tags. Branch menu, polling interval, and
dashboard cache invalidation are built for the database cache store.

## Database Sessions

Sessions use the `sessions` table. Keep:

```ini
SESSION_DRIVER=database
```

This avoids file-session permission problems on shared hosting and keeps session
storage inside SQLite.

## Database Queue

Queues use the `jobs`, `job_batches`, and `failed_jobs` tables. Keep:

```ini
QUEUE_CONNECTION=database
```

If your host allows a long-running process, run a queue worker according to the
host's process rules. If long-running workers are not available, run the queue
worker from cron:

```bash
php /absolute/server/path/to/project/artisan queue:work --stop-when-empty --tries=3
```

Frequency depends on the host. A common shared-hosting fallback is every minute
if the control panel allows it.

## Scheduler Cron

If cron is available, add Laravel's scheduler:

```cron
* * * * * php /absolute/server/path/to/project/artisan schedule:run >> /dev/null 2>&1
```

The scheduler is optional for the current baseline but should be configured when
future scheduled cleanup, reporting, or maintenance tasks are added.

## Build Assets

If the host cannot run Node, build assets before uploading:

```bash
npm run build
```

Upload the generated `public/build` output with the deployment. Docker is not
required.

## Production Cache Commands

After changing `.env` or config:

```bash
php artisan config:clear
php artisan config:cache
```

After changing routes or Blade views:

```bash
php artisan route:clear
php artisan view:clear
```

Use these only after files are deployed and `.env` is correct.

## Files That Must Not Be Committed

Never commit:

```text
.env
.env.backup
.env.production
database/*.sqlite*
storage/app/public/*
storage/logs/*
storage/framework/*
public/storage
vendor
node_modules
public/build
auth.json
```

Backups, CSV exports, media files, logs, and SQLite database copies can contain
sensitive data. Keep them outside git and outside the public web root unless
they are intentionally public media exposed through `public/storage`.

## Infrastructure Boundaries

Do not add these for this deployment profile:

- Redis.
- WebSockets.
- S3.
- Docker as a required path.
- External queue services.
- Paid storage, backup, PDF, SMS, push, or payment services.

Realtime behavior remains Livewire polling. Files remain local. Queue, cache,
and sessions remain database-backed.
