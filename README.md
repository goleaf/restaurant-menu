# Restaurant Menu SaaS

Laravel SaaS foundation for restaurants, cafes, bars, hotels, food courts, and similar venues.

This project is not only a QR menu. The current codebase is a clean shared-hosting-friendly foundation for the platform, with authentication, system roles, permissions, organizations, brands, branches, branch settings, basic superadmin access, staff invitation foundations, and simple staff management UI.

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

## Superadmin Access

`superadmin` is a platform-level role for SaaS administration. Superadmins can access the platform dashboard at:

```text
/superadmin/dashboard
```

The platform dashboard shows organizations, brands, branches, and users across the whole SaaS platform. Regular users do not see the platform dashboard link and receive `403 Forbidden` if they open the superadmin URL directly.

The first superadmin can be created by setting these values in `.env` before running the database seeder:

```text
SUPERADMIN_NAME="Platform Superadmin"
SUPERADMIN_EMAIL=admin@example.com
SUPERADMIN_PASSWORD=change-this-password
```

Then run:

```bash
php artisan db:seed
```

Do not commit real superadmin credentials. The seeder stores only the user and assigns the fixed `superadmin` role.

## Staff Management

Staff management is available only to users who have the `manage_staff` permission in the current organization context.

Organization staff is managed at:

```text
/organizations/{organization}/staff
```

Branch staff is managed at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/staff
```

The UI can:

- list organization staff;
- list branch staff;
- add a staff member manually;
- assign a fixed system role;
- create an invite link;
- create an invite code;
- activate or deactivate staff members.

Staff invitations are stored in the `invitations` table. An invitation can be scoped to an organization, optionally to a brand, and optionally to a branch. It stores the fixed role to assign later, email, phone, invite token, invite code, expiration date, status, and the user who created the invitation.

Supported statuses are:

- `pending`
- `accepted`
- `expired`
- `cancelled`
- `rejected`

This stage does not send email or SMS. The user copies invite links or invite codes manually and sends them outside the system.

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
- Basic superadmin access for the platform dashboard.
- Staff invitation model and backend creation action.
- Simple staff management UI for organization and branch staff.

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
- Staff invitation acceptance flow and email/SMS delivery.

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
