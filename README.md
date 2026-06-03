# Restaurant Menu SaaS

Laravel SaaS foundation for restaurants, cafes, bars, hotels, food courts, and similar venues.

This project is not only a QR menu. The current codebase is a clean shared-hosting-friendly foundation for the platform, with authentication, system roles, permissions, organizations, brands, branches, and branch settings.

## Stack

- Laravel 13
- Livewire 4
- Blade server-rendered UI
- SQLite
- Database cache
- Database sessions
- Database queue
- Local public storage in `storage/app/public`

The project intentionally does not use Redis, WebSockets, Docker as a requirement, S3, paid external services, React, Vue, or a separate SPA frontend.

## SQLite

SQLite is the only configured database connection.

The default database file is:

```text
database/database.sqlite
```

This file is inside the project and outside `public/`, which keeps it suitable for shared hosting when the web root points to `public/`.

`.env.example` leaves `DB_DATABASE` empty so Laravel uses the safe default from `config/database.php`.

## Current Scope

Implemented:

- Public guest, auth, restaurant dashboard, and superadmin dashboard layout zones.
- Laravel/Fortify authentication with Livewire-friendly screens.
- Fixed system roles seeded from enums.
- Flexible permissions with role permissions and user overrides.
- Organizations with owner membership.
- Organization users with role, status, joined date, and inviter fields.
- Brands inside organizations.
- Branches inside brands and organizations.
- Branch settings stored in `branch_settings`.

Branch settings currently include safe defaults:

- Orders require waiter confirmation by default.
- New guests require approval by default.
- Guest-created sessions are allowed by default.
- Waiter-opened sessions are allowed by default.
- Guest invite links are allowed by default.
- Polling interval defaults to 1 second.
- Default language is `en`.
- Default currency is `EUR`.

Branch settings store order flow, guest session behavior, invite-link behavior, service charge and tips toggles, language/currency defaults, and Livewire polling interval. They are kept in the `branch_settings` table and are managed from the branch settings Livewire page.

Not implemented yet:

- Restaurant menus.
- Nested zones.
- Service points / physical tables.
- Permanent QR public tokens.
- Guest table sessions.
- Shared order drafts.
- Kitchen/bar workflows.
- Payments and analytics.

## Local Verification

Run the core checks:

```bash
php artisan migrate
php artisan test --compact
npm run build
```

For PHP formatting:

```bash
vendor/bin/pint --dirty --format agent
```
