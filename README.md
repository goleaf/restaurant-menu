# Restaurant Menu

Tenant-safe restaurant operations on Laravel and Livewire: administration, permanent QR entry, guest table sessions, shared drafts, waiter review, kitchen/bar fulfilment, offline settlement, reporting, and SQLite backup/restore.

The application is server-rendered Blade. It intentionally requires no SPA framework, online payment provider, Redis, WebSockets, S3, Docker, or continuously running queue worker.

## Stack

PHP 8.5, Laravel 13, Livewire 4, Flux UI Free 2, Tailwind CSS 4, Vite 8, SQLite, Pest 4, Pint, and Larastan. Supported locales are English, Lithuanian, and Russian. Exact locked versions are recorded in [`docs/CURRENT_VERSION.md`](docs/CURRENT_VERSION.md).

## Quick start

Requirements: PHP 8.5 with Laravel/SQLite extensions, Composer 2, Node.js, npm, and Laravel Herd.

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
npm ci
npm run build
```

Herd serves the repository automatically. Optional local demo data:

```bash
php artisan db:seed --class=DemoRestaurantSeeder
```

The demo seeder refuses production execution. Safe demo accounts are documented in [`docs/DEMO_LOGIN.md`](docs/DEMO_LOGIN.md).

## Quality commands

| Command | Purpose |
|---|---|
| `composer analyse` | Run Larastan. |
| `composer lint` | Format PHP with Pint. |
| `composer test:coverage` | Enforce 90% application coverage; requires Xdebug or PCOV. |
| `php artisan test --compact` | Run Pest. |
| `php artisan translations:audit` | Verify EN/LT/RU catalogues. |
| `composer audit && npm audit` | Check dependency advisories. |
| `npm run build` | Build production assets. |

## Documentation

- Start with [`AGENTS.md`](AGENTS.md) and [`docs/index.md`](docs/index.md).
- Active requirements: [`docs/requirements.md`](docs/requirements.md).
- Implementation evidence: [`docs/compliance-matrix.md`](docs/compliance-matrix.md).
- Current priorities: [`ROADMAP.md`](ROADMAP.md).
- Architecture and operations: [`docs/architecture.md`](docs/architecture.md), [`docs/deployment.md`](docs/deployment.md), [`docs/operations.md`](docs/operations.md).
- Release history: [`CHANGELOG.md`](CHANGELOG.md).

Preserve tenant isolation, permanent QR identity, waiter confirmation, immutable order history, integer-cent money, all three locales, and shared-hosting compatibility.
