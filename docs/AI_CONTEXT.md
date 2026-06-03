# AI Context

## Product Direction

This is a SaaS platform for restaurants, cafes, bars, hotels, food courts, and similar venues.

It must grow beyond a simple QR menu, but each implementation step should stay small and stable. Do not implement future stages before they are explicitly requested.

## Hard Technical Constraints

- Use Laravel, Blade, and Livewire.
- Use SQLite only.
- Keep the project suitable for shared hosting.
- Do not use Redis.
- Do not use WebSockets.
- Do not use S3.
- Do not use paid external services.
- Do not require Docker.
- Do not build a separate React/Vue/Inertia SPA.
- Store cache, sessions, and queue jobs in the database.
- Store files locally in `storage/app/public`.
- Use Livewire polling for realtime behavior when realtime is added.

## Current Implemented Scope

The project currently has:

- Basic layout zones for guest, auth, restaurant dashboard, and superadmin dashboard.
- Fortify-backed authentication.
- Fixed system roles.
- Flexible permissions with role permissions and user overrides.
- Organizations.
- Organization user memberships.
- Brands.
- Branches.
- Branch settings.

No restaurant menu, QR, guest session, table, order, kitchen, bar, payment, or analytics logic has been implemented yet.

## Current Domain Model

Organization:

- Represents the company or owner of a restaurant business.
- Has many brands.
- Has many branches through direct branch ownership.
- Has many users through `organization_users`.

Brand:

- Belongs to an organization.
- Has many branches.

Branch:

- Belongs to a brand and an organization.
- Is the current working unit for future menu, zones, service points, and orders.
- Has one settings record.

Branch settings:

- Stored in `branch_settings`.
- Created with each new branch.
- Safely created on the settings page for existing branches that do not have settings yet.

## Branch Settings Defaults

Safe defaults:

- `require_waiter_confirmation_for_orders`: true
- `allow_guest_created_sessions`: false
- `allow_waiter_opened_sessions`: true
- `allow_guest_invite_links`: false
- `guest_join_requires_approval`: true
- `polling_interval_seconds`: 1
- `default_language`: `en`
- `default_currency`: branch currency, or `EUR`
- `service_charge_enabled`: false
- `tips_enabled`: false
- `order_flow_mode`: `waiter_confirmation`

## Access Rules Already Used

- Organization access is checked in organization context.
- Branch management uses organization-scoped branch permissions and manager roles.
- Branch settings require branch management access.
- Nested route models are manually checked to ensure brand and branch belong to the route organization.

## Verification Commands

Use these checks after small changes:

```bash
php artisan migrate
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
```

For migration reversibility when a new migration is added:

```bash
php artisan migrate:rollback --step=1 --no-interaction
php artisan migrate --no-interaction
```
