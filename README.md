# Restaurant Menu

Restaurant Menu is a tenant-aware Laravel and Livewire application for restaurant administration and in-venue QR ordering. It covers organizations, brands and branches; areas and service points; permanent QR codes; guest table sessions and shared drafts; waiter approval; kitchen/bar fulfilment; manual offline payments; reports, exports, audit history, and local shared-hosting operations.

The application is server-rendered Blade with class-based Livewire components. It intentionally has no separate SPA, online payment provider, Redis, WebSockets, S3, or Docker requirement.

## Current baseline

- PHP 8.5
- Laravel 13
- Livewire 4
- Flux UI Free 2
- Tailwind CSS 4 with `@tailwindcss/vite`
- Vite 8
- SQLite; database cache, sessions, and queues
- Pest 4 / PHPUnit 12, Laravel Pint, Larastan
- Local public/private filesystem disks
- Locales: English (`en`), Lithuanian (`lt`), Russian (`ru`)

Exact installed versions and verification evidence are maintained in [`docs/current-state-audit.md`](docs/current-state-audit.md) and [`docs/compliance-matrix.md`](docs/compliance-matrix.md).

## Quick start

Requirements: PHP 8.5 with the extensions required by Laravel and SQLite, Composer 2, Node.js compatible with the locked Vite version, npm, and Laravel Herd.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run build
```

Herd serves the project at the URL derived from the directory name. Do not run a second application server. For a non-production demo dataset:

```bash
php artisan db:seed --class=DemoRestaurantSeeder
```

The demo seeder refuses to run when `APP_ENV=production`. See [`docs/DEMO_LOGIN.md`](docs/DEMO_LOGIN.md) before using demo accounts.

## Development commands

| Command | Purpose |
|---|---|
| `composer validate --strict` | Validate Composer metadata. |
| `composer audit` | Check PHP dependency advisories. |
| `composer lint` | Format PHP with Pint. |
| `composer lint:check` | Check PHP formatting without changes. |
| `composer analyse` | Run Larastan level 8. |
| `composer dev` | Run only Pail logs and Vite; Herd already serves PHP. |
| `php artisan test --compact` | Run the Pest suite. |
| `php artisan test --parallel --compact` | Run safe parallel tests. |
| `php artisan translations:audit` | Validate locale structure and semantic keys. |
| `php artisan translations:scan` | Compare translation use with JSON catalogues. |
| `npm ci` | Install the single locked frontend dependency graph. |
| `npm audit` | Check JavaScript dependency advisories. |
| `npm run build` | Build production assets. |

Additional static-analysis and coverage commands are documented in [`docs/testing.md`](docs/testing.md).

## Runtime model

- SQLite is the only configured database and must live outside the public web root.
- Cache, sessions, queue tables, notifications, and application data share the SQLite database by design.
- Uploaded public images use `storage/app/public`; private files and SQLite backups are never exposed by an unauthenticated storage route.
- Live updates use bounded visible Livewire polling. The application does not require WebSockets.
- The scheduler can run inactive-session cleanup every fifteen minutes, but production usability must not depend on a continuously running worker.

See [`docs/deployment.md`](docs/deployment.md) and [`docs/operations.md`](docs/operations.md) for deployment and operational checks.

## Architecture and requirements

- Documentation index: [`docs/index.md`](docs/index.md)
- Requirements catalogue: [`docs/requirements.md`](docs/requirements.md)
- Compliance matrix: [`docs/compliance-matrix.md`](docs/compliance-matrix.md)
- Architecture: [`docs/architecture.md`](docs/architecture.md)
- Domain model: [`docs/domain-model.md`](docs/domain-model.md)
- Data model: [`docs/data-model.md`](docs/data-model.md)
- Security and authorization: [`docs/security.md`](docs/security.md), [`docs/authorization.md`](docs/authorization.md)
- Livewire and frontend: [`docs/livewire.md`](docs/livewire.md), [`docs/frontend.md`](docs/frontend.md)
- Testing and seeding: [`docs/testing.md`](docs/testing.md), [`docs/seeding.md`](docs/seeding.md)
- Current modernization plan: [`docs/implementation-plan.md`](docs/implementation-plan.md)

## Contribution rules

Read [`AGENTS.md`](AGENTS.md) before changing code. Preserve tenant and branch isolation, permanent QR identity, waiter confirmation before dispatch, historical order snapshots, manual/offline payment semantics, all supported locales, shared-hosting compatibility, and the repository's TDD and verification requirements.

Historical implementation records are preserved in [`CHANGELOG.md`](CHANGELOG.md); they are not active requirements.
