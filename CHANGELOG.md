# Changelog

## 2026-06-03

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
