# Restaurant Menu SaaS

Laravel SaaS foundation for restaurants, cafes, bars, hotels, food courts, and similar venues.

This project is not only a QR menu. The current codebase is a clean shared-hosting-friendly foundation for the platform, with authentication, system roles, permissions, organizations, brands, branches, branch settings, local media storage, nested branch areas, service point schema and CRUD, table session schema, guest-created pending sessions, permanent QR schema, generation, admin display page, simple and bulk browser print templates, public QR guest landing, basic superadmin access, staff invitation foundations, simple staff management UI, and staff permission override UI.

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

## Local Media Storage

Media files are stored locally on Laravel's `public` disk. The disk root is:

```text
storage/app/public
```

The public web path is served through:

```text
public/storage
```

On shared hosting, make sure these paths are writable by PHP:

- `storage/app/public`
- `storage/framework`
- `storage/logs`

The public storage link should point from `public/storage` to `storage/app/public`. When shell access is available, run:

```bash
php artisan storage:link
```

If symbolic links are not available on a shared host, configure the host so `public/storage` exposes the same files from `storage/app/public`.

Organization, brand, and branch logos are stored in local folders under `storage/app/public/media`. Current logo paths are saved in `logo_path` columns on `organizations`, `brands`, and `branches`.

Current logo upload rules:

- images only;
- allowed extensions: `jpg`, `jpeg`, `png`, `webp`;
- maximum size: 2 MB.

Future dish images should reuse the same local public storage approach. Menu and dish entities are not implemented yet.

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

Service point statuses are:

- `free`
- `occupied`
- `reserved`
- `waiting_waiter`
- `has_new_order`
- `cooking`
- `ready_to_serve`
- `payment_requested`
- `paid`
- `closed`
- `blocked`

Service points are managed at:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/service-points
```

The service point UI is guarded by the `manage_service_points` permission in the current organization context for CRUD actions. It can add common service point presets, choose a zone, choose a type and icon, set a name, number, and capacity, rename, move between zones, and disable/enable service points.

Service point status can be changed manually by a user with `manage_service_points` or by a user with the fixed `waiter` role in the organization. The status update is handled through a backend action so later table sessions and orders can reuse the same status-change path.

Users with `view_orders` or `confirm_orders` can open a table from the service point page. Opening a table creates or returns the current active table session for that service point and moves the service point status to `occupied`.

Permanent QR codes are attached to the stable service point record. The CRUD action creates an internal service point code once, and editing does not change it. Renaming a service point or moving it to another area must not change the QR identity.

Users with `generate_qr` can open the service point page, create a missing active QR, and show the existing QR details. Users without `generate_qr` cannot generate or show QR details from this UI.

## Table Sessions

Table sessions are stored in the `table_sessions` table. A table session belongs to one branch and one service point.

The table stores:

- `branch_id`
- `service_point_id`
- `active_service_point_id`
- `pending_service_point_id`
- `opened_by_user_id`
- `opened_by_guest_id`
- `status`
- `source`
- `started_at`
- `ended_at`
- `closed_by_user_id`
- `metadata`

Supported statuses are:

- `pending`
- `active`
- `waiting_waiter_confirmation`
- `payment_requested`
- `paid`
- `closed`
- `cancelled`

Supported sources are:

- `waiter_opened`
- `guest_created`

`opened_by_user_id` is for waiter-created sessions. `opened_by_guest_id` stores the first session guest id when the first guest creates a pending session.

SQLite enforces one active table session per service point through internal nullable `active_service_point_id`. Closed, cancelled, or other non-active session history can remain for the same service point.

SQLite also enforces one pending table session per service point through internal nullable `pending_service_point_id`. This protects the public QR flow from creating duplicate pending sessions on repeat submit.

`OpenTableSessionForServicePointAction` creates an active waiter-opened session only when the service point does not already have one. If an active session already exists, the action returns it and does not create a second active session automatically.

`CreateGuestPendingTableSessionAction` creates a pending guest-created session only when the service point has no active or pending session and branch settings allow guest-created sessions. The first guest is stored as an active guest inside that pending session.

This stage does not create menus, orders, order drafts, kitchen/bar workflows, or payment flows. Guest-created sessions do not send anything to the kitchen or bar.

## Table Session Guests

Table session guests are stored in the `table_session_guests` table and belong to one table session.

The table stores `guest_name`, a random `guest_token`, `status`, `joined_at`, optional `left_at`, and optional JSON `metadata`.

Guests are not user accounts and do not need registration. The public QR entry flow queues the `guest_token` in a browser cookie so the guest can be recognized later without exposing internal IDs.

Supported guest statuses are:

- `pending_approval`
- `active`
- `rejected`
- `left`
- `removed`

The first guest created from the public QR landing is saved as `active`. Guest lists are ordered alphabetically by `guest_name`. Future prompts will add approval for additional guests.

## Permanent QR Codes

Permanent QR records are stored in the `qr_codes` table.

Each QR record belongs to one service point and stores:

- `public_token`
- `short_code`
- `status`
- creation audit user
- optional revocation date and revocation audit user

QR statuses are:

- `active`
- `disabled`
- `revoked`

The QR record does not store table numbers, service point names, area names, or branch IDs. The future public QR URL must use `public_token`, not internal IDs or visible table labels.

SQLite enforces one active QR per service point through an internal nullable `active_service_point_id` uniqueness guard. This allows disabled and revoked QR history while preventing a second active QR for the same physical service point.

`GenerateQrCodeForServicePointAction` creates a QR only when the service point does not already have an active QR. If an active QR already exists, the action returns the existing record instead of creating a second active QR automatically.

Generated QR URLs use:

```text
/q/{public_token}
```

The public `/q/{public_token}` route resolves the QR token, checks the QR status, loads the current service point, current area, branch, brand, organization, and local logo, and opens a mobile-first guest landing page. The URL does not include organization IDs, branch IDs, service point IDs, table numbers, or area names.

The guest landing page shows the venue name, logo when available, current area, current service point, a guest name field, and the `Войти за стол` button. If there is no active or pending table session and `allow_guest_created_sessions` is enabled, submitting the name creates a pending table session and the first active guest inside it. If an active session already exists, the page shows a message for the future join flow instead of creating a new pending session.

Disabled and revoked QR codes show a clear public error message. Active QR codes for inactive service points show a clear message telling the guest to ask staff. Moving or renaming a service point does not change the QR URL; the public page loads the current service point data each time.

Users with `generate_qr` can open the QR admin page from a service point. The page shows the branch, current area, current service point, public URL, local SVG QR image, short code, status, and creation date. It can open the guest URL, download the QR SVG image, disable the QR, or manually reissue the QR after a danger warning.

Manual reissue revokes the current active QR and creates a new active QR for the same service point. Normal service point editing, including rename and area move, does not reissue or change the QR.

Users with `generate_qr` can also open a browser print-friendly sticker template for one service point QR. The sticker shows a restaurant logo only if a local logo field already exists, otherwise it uses the brand name as a simple text mark. It prints the text `Сканируйте, чтобы открыть меню`, the QR image, and the `short_code`.

The print template does not print service point number or area by default. It has a `print_table_number` setting for including the service point display number. When that setting is enabled, the UI warns that future rename or area moves can make printed sticker text stale.

Users with `generate_qr` can also open a branch-level bulk QR print page:

```text
/organizations/{organization}/brands/{brand}/branches/{branch}/qr/print
```

The bulk print page lets the user filter by area or show all areas, select service points, see which service points already have active QR codes, create missing QR codes, and open a browser print-friendly multi-sticker page. Existing active QR codes are reused, so one service point still has one active permanent QR.

PDF generation is not implemented yet.

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
- Local logo uploads for organizations, brands, and branches.
- Nested branch areas stored in `area_nodes`.
- Service point schema and CRUD UI stored in `service_points`.
- Service point operational statuses and manual status changes.
- Table session schema stored in `table_sessions`.
- First guest pending session creation from the public QR landing.
- Permanent QR schema, generation action, admin display page, simple and bulk browser print templates, and public `/q/{public_token}` route.
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
- QR PDF generation.
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
