# Restaurant Menu SaaS

Laravel SaaS foundation for restaurants, cafes, bars, hotels, food courts, and similar venues.

This project is not only a QR menu. The current codebase is a clean shared-hosting-friendly foundation for the platform, with authentication, system roles, permissions, organizations, brands, branches, branch settings, nested branch areas, service point schema, basic superadmin access, staff invitation foundations, simple staff management UI, and staff permission override UI.

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

Staff permission overrides are managed at:

```text
/organizations/{organization}/staff/{staffMember}/permissions
```

The employee permission page keeps the fixed staff role unchanged and lets a manager set each permission to `default`, `allow`, or `deny`. `default` removes the user override and falls back to role permissions. `allow` and `deny` save a user override in `permission_user_overrides`. Superadmins always keep full computed access.

Changing critical permissions shows a warning, and users cannot edit their own permission overrides from this page.

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

## Area Nodes

Area nodes are the nested zone structure inside a branch. They are stored in the `area_nodes` table and belong to one branch.

Each area node can have a `parent_id`, so branches can model structures such as floors, halls, terraces, VIP rooms, hotel areas, pickup areas, delivery areas, and custom groups. Area nodes store `type`, `name`, optional `icon`, `sort_order`, `is_active`, optional `metadata`, and support soft delete through `deleted_at`.

Branch areas are managed at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/areas
```

The area UI is guarded by the `manage_zones` permission in the current organization context. It can add common zone presets, choose an icon, rename, move a zone inside another zone, disable/enable zones, and soft delete zones while keeping child zones visible.

Area management does not create QR codes.

## Service Points

Service points are physical service locations inside a branch. They are stored in the `service_points` table and belong to one branch. A service point can optionally belong to an area node, so it can be moved between halls, floors, terraces, rooms, pickup areas, or other zones by changing `area_node_id`.

Supported service point types are table, bar seat, VIP table, room, booth, sunbed, hotel room, pickup window, delivery point, and other.

Service points store `type`, `name`, optional `display_number`, optional `internal_code`, `capacity`, optional `icon`, `status`, optional map coordinates, `is_active`, optional `metadata`, and support soft delete through `deleted_at`.

Future permanent QR codes should be attached to the stable service point record. Renaming a service point or moving it to another area must not change the future QR identity. QR codes are not implemented yet.

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
- Nested branch areas stored in `area_nodes`.
- Service point schema stored in `service_points`.
- Basic superadmin access for the platform dashboard.
- Staff invitation model and backend creation action.
- Simple staff management UI for organization and branch staff.
- Staff permission override UI with default / allow / deny states.

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
