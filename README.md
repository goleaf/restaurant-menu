# Restaurant Menu

Tenant-safe restaurant operations on Laravel and Livewire: administration, permanent QR entry, guest table sessions, shared drafts, waiter review, kitchen/bar fulfilment, offline settlement, reporting, and SQLite backup/restore.

The application is server-rendered with Blade and class-based Livewire. It requires no SPA framework, online payment provider, Redis, WebSockets, S3, Docker, cron, Supervisor, continuously running queue worker, runtime Artisan process, or separate frontend server.

## Technology

- PHP `>=8.5.0 <8.6.0` and Composer 2.
- Laravel 13, Fortify 1, class-based Livewire 4, and Flux UI Free 2.
- Tailwind CSS 4 and Vite 8, built with Node.js 22.12+ or 24 LTS and npm 10+.
- SQLite for application data, cache, sessions, and the deployable queue default.
- Local filesystem storage; public QR and menu media are served through `public/storage`.
- Pest 4, PHPUnit 12, Pint, and Larastan for quality gates.
- English, Lithuanian, and Russian interfaces and guest-visible menu content.

Exact installed versions and platform evidence are recorded in [`docs/CURRENT_VERSION.md`](docs/CURRENT_VERSION.md).

## Local installation

Laravel Herd serves this repository at its configured `.test` address. Do not start `php artisan serve`; Vite is needed only while editing frontend assets or for a production build.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
npm ci
npm run build
php artisan migrate --no-interaction
php artisan storage:link
```

Set at least these values before the first migration:

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=https://restaurant-menu.test
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/restaurant-menu/database/database.sqlite
FILESYSTEM_DISK=public
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database
```

`DB_DATABASE` may be omitted to use `database/database.sqlite`, but an explicit absolute path is safer on shared hosting. The database file, its directory, `storage/`, and `bootstrap/cache/` must be writable by PHP. Environment values are read through configuration files; after changing a cached production environment, rebuild the configuration cache.

## Storage and frontend assets

Run `php artisan storage:link` once for a release or create the equivalent `public/storage` link in the hosting panel. Permanent QR SVGs are stored under `storage/app/public/qr/`; uploaded and generated public media remain on the configured local public disk. Private SQLite backups are never placed below the web root.

For local asset development, `npm run dev` starts only the optional Vite watcher. Normal use, tests, and production use the static manifest produced by:

```bash
npm ci
npm run build
```

If Node.js is unavailable on the server, build the assets in the verified release workspace and upload the resulting `public/build` directory with the application release.

## Migrations and existing databases

For a new or existing database, apply only forward migrations:

```bash
php artisan migrate --force --no-interaction
```

Take and verify a SQLite backup before a production schema update. Never run `migrate:fresh` against an existing, shared, or production database. Roll back a migration only when its `down()` path is known to preserve all data created since deployment; otherwise fix forward.

A clean disposable development or CI database can be verified with:

```bash
php artisan migrate:fresh --seed --force --no-interaction
```

## Seed data and demo accounts

The default seeder is repeat-safe and installs fixed roles, permissions, the first-superadmin contract, and department reference data:

```bash
php artisan db:seed --no-interaction
```

The complete fictitious demo graph is opt-in and refuses production. Use a dedicated disposable SQLite database and an allowlisted non-production host:

```dotenv
APP_ENV=local
APP_URL=https://ruflo.test
DEMO_LOGIN_ENABLED=true
DEMO_LOGIN_HOSTS=ruflo.test
```

Then run `php artisan migrate:fresh --seed --force --no-interaction` only for that new demo database. To reconcile the graph without deleting an existing non-production demo database, use:

```bash
php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
```

`GET /demo-login` lists the seeded superadmin, owner, director, restaurant admin, shift manager, waiter, head chef, cook, bartender, cashier, accountant, and marketer identities. Choosing one performs a CSRF-protected, rate-limited login without exposing a reusable password. The page, POST action, and demo seeder independently deny production and non-allowlisted hosts. Passwords, token bearers, and credentials are intentionally not documented or stored in repository text. See [`docs/DEMO_LOGIN.md`](docs/DEMO_LOGIN.md) and [`docs/seeding.md`](docs/seeding.md).

## Tests and release checks

Run the focused test for changed behaviour first, followed by the applicable release gates:

```bash
vendor/bin/pint --dirty --format agent
composer validate --strict
composer audit --locked
composer analyse
php artisan test --compact
php artisan test --compact --parallel
composer test:coverage
php artisan translations:audit
npm audit --audit-level=moderate
npm run build
composer test:browser
```

`composer test:coverage` requires Xdebug or PCOV and enforces at least 90% application coverage. Authored tests use Pest; PHPUnit is its runner dependency rather than a competing test style. The complete command contract, isolated SQLite rules, query budgets, browser sizes, and cache checks are in [`docs/testing.md`](docs/testing.md).

## Shared-hosting deployment

The web root must point to `public/`. Preserve the SQLite database and storage directories outside disposable release directories, install production dependencies, build or upload verified assets, and run deployment-time maintenance commands:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci
npm run build
php artisan migrate --force --no-interaction
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, a unique `APP_KEY`, the correct `APP_URL`, secure cookies, and writable SQLite/storage/cache paths. Never enable demo login or run demo seeders in production. Verify the JSON `/up` endpoint, login, one public QR, an authorized staff workspace, assets, and recent logs after promotion.

The request lifecycle is synchronous and complete without cron, Supervisor, a persistent queue worker, a WebSocket service, a running Vite server, or an Artisan daemon. Database queues and scheduled cleanup are optional operational enhancements only; no required guest, waiter, kitchen, bar, payment, reporting, backup, or restore flow depends on them. See [`docs/deployment.md`](docs/deployment.md), [`docs/DEPLOY_SHARED_HOSTING.md`](docs/DEPLOY_SHARED_HOSTING.md), and [`docs/operations.md`](docs/operations.md).

## Documentation

- Repository rules and documentation map: [`AGENTS.md`](AGENTS.md), [`docs/index.md`](docs/index.md).
- Canonical requirements and implementation evidence: [`docs/requirements.md`](docs/requirements.md), [`docs/compliance-matrix.md`](docs/compliance-matrix.md), [`docs/REQUIREMENTS_TRACEABILITY.md`](docs/REQUIREMENTS_TRACEABILITY.md).
- Architecture and data model: [`docs/architecture.md`](docs/architecture.md), [`docs/data-model.md`](docs/data-model.md).
- Current execution evidence: [`docs/IMPLEMENTATION_PLAN.md`](docs/IMPLEMENTATION_PLAN.md), [`docs/PROGRESS.md`](docs/PROGRESS.md), [`docs/DECISIONS.md`](docs/DECISIONS.md).
- Product and interface contracts: [`PRODUCT.md`](PRODUCT.md), [`DESIGN.md`](DESIGN.md), [`docs/accessibility.md`](docs/accessibility.md).
- Release history and priority index: [`CHANGELOG.md`](CHANGELOG.md), [`ROADMAP.md`](ROADMAP.md).

Preserve tenant isolation, permanent QR identity, waiter confirmation, immutable order history, integer-cent money, all three locales, and shared-hosting compatibility in every change.
