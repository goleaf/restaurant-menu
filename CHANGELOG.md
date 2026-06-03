# Changelog

## 2026-06-03

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
