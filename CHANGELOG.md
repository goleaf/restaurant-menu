# Changelog

## 2026-06-03

### Prompt 038 - Guest Table Page Shell

- Added the main guest table page shell for active table guests.
- The shell shows venue, current place, saved entry state, guest invite action, guest list, menu placeholder, shared order placeholder, and a `0,00` total.
- Added an isolated Livewire polling component for the guest list so only the guest block refreshes.
- Kept the step limited to the table page shell: no menu items, order draft, kitchen/bar, payment, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 037 - Guest Invite Share Link

- Added a guest invite action inside the active public QR table session.
- Active guests can create a hidden invite link for the current table session and share it through the browser native share API when available.
- Added a copy-link fallback for browsers without native share support.
- Opening the invite link keeps the public QR URL token-based and creates a pending join request after the new guest enters a name.
- Kept the step limited to guest invitation and join approval flow: no menu, order, payment, kitchen/bar, Redis, WebSocket, S3, Docker, or paid integrations.

### Prompt 036 - Guest Join Approval UI

- Added a Livewire polling block where active table guests see pending join requests.
- Allowed any active guest at the same table session to approve or reject a pending guest from the public QR UI.
- Added a waiting-state refresh for the new guest so approval restores them as an active guest and rejection shows a clear message.
- Kept the step limited to guest join approval: no menu, order draft, kitchen/bar, payment, Redis, WebSocket, S3, or Docker.

### Prompt 035 - Table Session Join Requests

- Added `table_session_join_requests` for guests who want to join an existing table session.
- Added fixed join request statuses: `pending`, `approved`, `rejected`, and `expired`.
- Added backend logic to create a join request when a table session already has active guests.
- Added backend actions so any active guest in the same table session can approve or reject a pending request.
- Kept the step limited to database and backend logic: no approval UI, menu, order, payment, kitchen/bar, Redis, WebSocket, S3, or Docker.

### Prompt 034 - Persist Guest Token

- Restored public QR guests from the saved browser guest token after page refresh.
- Added guest access state for active, closed, rejected, removed, pending approval, and left guest/session cases.
- Blocked rejected and removed guests from future item-adding capability while keeping them out of normal user accounts.
- Kept the step limited to guest token persistence: no menu, order, payment, kitchen/bar, or join approval UI.

### Prompt 033 - Table Session Guests

- Updated table session guests to store `guest_name` and a random `guest_token`.
- Added the `rejected` guest status for the future join approval flow.
- The public QR entry flow now queues the guest token in a browser cookie and keeps the guest out of normal user accounts.
- Guest relationships now return guests alphabetically by name.
- Kept the step limited to guest identity inside a table session: no join approval UI, menus, orders, kitchen/bar, or payment logic.

### Prompt 032 - Guest-Created Pending Session

- Added `table_session_guests` for storing guests inside a table session.
- Added a guest-created pending session action for the public QR landing.
- The first guest now creates a pending table session and becomes the first active guest when branch settings allow guest-created sessions.
- Added a SQLite-safe pending-session guard so repeat submits do not create duplicate pending sessions for the same service point.
- Kept the step limited to guest entry and pending sessions: no menu, orders, kitchen/bar, payment, or join approval flow.

### Prompt 031 - Waiter Open Table Action

- Added a waiter open-table backend action that creates an active table session for a service point.
- Added a SQLite-safe active-session guard so one service point cannot have two active table sessions.
- Updated the service point page with an `Open table` action for users with `view_orders` or `confirm_orders`.
- Opening a table moves the service point status to `occupied` and shows the active session in the admin UI.
- Kept the step limited to table opening: no guests, orders, order drafts, kitchen/bar, or payment logic.

### Prompt 030 - Table Sessions Schema

- Added the `table_sessions` table for branch and service point session lifecycle tracking.
- Added fixed table session statuses from `pending` through `closed` and `cancelled`.
- Added fixed session sources for waiter-opened and guest-created flows.
- Added the TableSession model, factory, enum casts, branch/service point/user relationships, and focused schema tests.
- Kept the step schema-only: no guests, menus, orders, kitchen/bar flow, payment flow, or guest landing session creation yet.

### Prompt 029 - Guest QR Landing Page

- Replaced the public QR placeholder with a mobile-first guest landing page.
- Shows the venue name, local logo when available, current area, current service point, short code, guest name field, and `Войти за стол` button.
- Added Livewire name entry validation without guest registration, table sessions, menus, or orders.
- Kept QR identity stable: moving or renaming a service point still keeps the same `/q/{public_token}` URL while showing current data.
- Added tests for logo display, current area/service point display, name entry, validation, and QR stability.

### Prompt 028 - Local Media Storage

- Added local public-storage logo uploads for organizations, brands, and branches.
- Added `logo_path` fields for organization, brand, and branch records.
- Added reusable local image storage actions with image type checks and a 2 MB size limit.
- Added simple upload/remove controls to the existing organization, brand, and branch screens.
- Kept storage local to `storage/app/public` without S3, paid services, or external media providers.
- Added tests for logo upload, replacement, removal, URL generation, and file validation.

### Prompt 027 - Bulk QR Print

- Added a branch-level bulk QR print page guarded by the `generate_qr` permission.
- Added area filtering, service point selection, and a browser print-friendly multi-sticker preview.
- Added actions to select all visible service points with active QR codes and create missing QR codes for shown service points.
- Kept QR identity permanent: existing active QR codes are reused and service point rename or area moves do not affect printed URLs.
- Added tests for access control, area filtering, multi-QR selection, missing QR creation, and branch-list navigation.

### Prompt 026 - QR Print Template

- Added a browser print-friendly sticker template for one service point QR.
- The sticker shows a restaurant logo when a local logo field exists, otherwise it uses the brand name as a text mark.
- The sticker prints the menu scan text, QR image, and short code without printing service point number or area by default.
- Added a `print_table_number` setting to include the service point display number and show a stale-label warning.
- Verified the print media view in the browser without using PDF services.

### Prompt 025 - QR Admin Display Page

- Added an authenticated QR admin page for a specific service point QR record.
- The page shows branch, current area, current service point, public guest URL, SVG QR image, short code, status, and creation date.
- Added local SVG QR rendering and SVG download without external services or storage uploads.
- Added actions to open the guest URL, disable an active QR, and manually reissue a QR after a danger warning.
- Verified that normal service point rename or area move does not change or reissue the QR.

### Prompt 024 - Public QR Route

- Added the public `/q/{token}` route for permanent QR links without exposing organization, branch, service point, or table IDs.
- Added a simple mobile-first guest QR landing page that resolves active QR codes to the current service point, branch, brand, and organization.
- Added clear public messages for disabled QR codes, revoked QR codes, inactive service points, and unknown tokens.
- Verified that moving or renaming a service point keeps the QR URL stable while the public page shows the current place data.
- Added tests for active, moved, disabled, revoked, inactive, and unknown QR token cases.

### Prompt 023 - QR Generation Action

- Added a backend action that creates an active permanent QR for a service point only when one does not already exist.
- Added random 64-character public tokens and short human-readable QR codes for printing preparation.
- Added QR controls to the service point page for users with the `generate_qr` permission.
- Added QR status, short code, and `/q/{public_token}` display without adding the public QR route or PDF output.
- Added tests proving repeated generation does not create a second active QR and QR identity stays stable after service point rename or move.

### Prompt 022 - Permanent QR Schema

- Added the `qr_codes` table for permanent QR records attached to service points.
- Added QR statuses for active, disabled, and revoked records.
- Added public token and short code fields without storing table numbers, service point names, area names, or branch IDs.
- Added SQLite-safe uniqueness enforcement so one service point can have only one active QR while keeping disabled and revoked history.
- Added QR model, factory, service point relationships, audit user relationships, and tests for stability across service point rename/move.

### Prompt 021 - Service Point Statuses

- Replaced the old service point statuses with table-flow statuses from `free` through `closed` and `blocked`.
- Updated `service_points.status` to default to `free` on SQLite and migrated old status values safely.
- Added manual service point status changes from the service point page.
- Allowed users with `manage_service_points` and users with the fixed `waiter` role to change service point status.
- Added a backend status update action that future table sessions and orders can reuse.
- Added tests for status taxonomy, default status, manager status changes, and waiter-only status changes.

### Prompt 020 - Service Points CRUD

- Added a branch service point management page guarded by the `manage_service_points` permission.
- Added create, rename, zone move, icon selection, capacity editing, disable, and enable actions for service points.
- Added stable one-time `internal_code` creation so future QR identity is not tied to the service point name, number, or area.
- Added the service point management route and branch-list link.
- Added tests for access control, create, move between zones, rename, disable, invalid area assignment, and stable identity during edits.

### Prompt 019 - Service Points Schema

- Added the `service_points` table for physical branch service locations.
- Added fixed service point types for tables, bar seats, VIP tables, rooms, booths, sunbeds, hotel rooms, pickup windows, delivery points, and other points.
- Added service point status values, model, factory, branch relationship, area node relationship, metadata casting, coordinate casting, and soft delete support.
- Added tests for schema fields, fixed taxonomies, branch and area relationships, moving between areas without changing identity fields, optional area assignment, and soft delete behavior.

### Prompt 018 - Area Nodes CRUD

- Added a branch area management page guarded by the `manage_zones` permission.
- Added simple nested area tree UI for floors, groups, halls, terraces, VIP rooms, and custom areas.
- Added create, rename, move, icon selection, enable/disable, and soft delete actions for area nodes.
- Added tests for access control, nested creation, moving, disabling, soft delete behavior, and cycle prevention.

### Prompt 017 - Area Nodes Schema

- Added the `area_nodes` table for nested branch area structure.
- Added fixed area node types for groups, floors, halls, terraces, VIP rooms, bar areas, banquet halls, rooms, hotel areas, pickup areas, delivery areas, and custom areas.
- Added the AreaNode model, factory, branch relationship, parent/children relationships, metadata casting, and soft delete support.
- Added tests for schema fields, fixed area types, nesting, metadata casting, and soft delete behavior.

### Prompt 016 - Permission Override UI

- Added a staff permission override page guarded by `manage_staff`.
- Added default / allow / deny permission states for individual staff users.
- Added critical permission warnings and self-edit protection.
- Added computed effective permission display that respects superadmin access, user overrides, and role defaults.
- Added staff list links and tests for access control, override persistence, and superadmin behavior.

### Prompt 015 - Staff Management UI

- Added the `branch_users` table for branch-level staff assignments.
- Added organization and branch staff management Livewire pages.
- Added manual staff creation with fixed role assignment.
- Added invite link and invite code creation from the staff UI without sending email or SMS.
- Added staff activate/deactivate actions guarded by `manage_staff`.
- Added tests proving regular users without `manage_staff` cannot access staff pages.

### Prompt 014 - Staff Invitations

- Added staff invitation statuses for pending, accepted, expired, cancelled, and rejected invitations.
- Added the `invitations` table for organization, brand, and branch-scoped staff invitations.
- Added the Invitation model, factory, relationships, and backend creation action.
- Added backend safeguards so brand and branch invitation scopes must belong to the selected organization.
- Added tests for invitation schema, generated token/code defaults, fixed role assignment, and invalid scope rejection.

### Prompt 013 - Superadmin Access

- Added first-superadmin seeding through safe `.env` configuration.
- Added protected platform dashboard access for users with the fixed `superadmin` role.
- Hid platform dashboard navigation from regular users and blocked direct access with `403 Forbidden`.
- Added a simple platform dashboard that lists all organizations, brands, branches, and users.
- Updated user access checks so superadmins bypass organization and branch-level restrictions.

### Prompt 012 - Branch Settings

- Added branch settings defaults for order confirmation, guest approvals, guest-created sessions, waiter-opened sessions, invite links, polling interval, language, currency, service charge, tips, and order flow mode.
- Updated branch settings so guest-created sessions and guest invite links are enabled by default.
- Verified the `branch_settings` SQLite defaults through migration and schema inspection.

### Foundation

- Prepared a Laravel + Livewire + SQLite foundation for a shared-hosting restaurant SaaS platform.
- Configured SQLite as the only database connection, with the database file stored at `database/database.sqlite`.
- Configured cache, sessions, and queues to use database-backed storage.
- Added basic Blade + Livewire layout zones for guest, auth, restaurant dashboard, and superadmin dashboard surfaces.
- Added Laravel/Fortify authentication flows for registration, login, logout, password reset, and profile/security pages.
- Added fixed system roles and seeded them from enum-backed definitions.
- Added flexible permissions with role permissions and user-level overrides.
- Added organizations as restaurant-business owners, including owner membership creation.
- Added organization user memberships with role, status, join date, and inviter fields.
- Added brands inside organizations.
- Added branches inside brands and organizations.
- Added branch settings stored in `branch_settings`, including safe defaults for waiter confirmation, guest approval, and 1-second polling.
- Added tests for authentication, layout zones, roles, permissions, organizations, organization access, brands, branches, and branch settings.
