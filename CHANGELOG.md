# Changelog

## 2026-06-03

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
