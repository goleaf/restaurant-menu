# AI Context

This file is the working memory for coding agents. Read it before each prompt and update it after each completed step.

## Current Stack

- Laravel 13.13
- PHP 8.5
- Livewire 4.3
- Flux UI Free 2.14
- Blade server-rendered UI
- SQLite only
- Database cache
- Database sessions
- Database queue
- Local public storage in `storage/app/public`
- Pest 4
- Vite / Tailwind CSS 4

## Main Business Rules

- The product is a SaaS platform for restaurants, cafes, bars, hotels, food courts, and similar venues.
- It must grow beyond a simple QR menu, but each prompt must stay small.
- One physical table / place / service point will eventually have one permanent QR code.
- QR links must not expose restaurant IDs, branch IDs, table IDs, or table numbers.
- Orders must require waiter confirmation by default.
- New guests must require approval by default.
- Branch realtime behavior must use Livewire polling, not WebSockets.

## What Is Already Done

- Laravel + Livewire project foundation.
- SQLite-only database configuration.
- Database-backed cache, sessions, and queues.
- Guest, auth, restaurant dashboard, and superadmin dashboard layout zones.
- Fortify-backed authentication.
- Fixed system roles.
- Flexible permissions with role permissions and user overrides.
- Organizations.
- Organization user memberships.
- Brands.
- Branches.
- Branch settings.
- Basic superadmin access for the platform dashboard.
- Staff invitation backend foundation.
- Simple organization and branch staff management UI.
- Staff permission override UI.

No menu, QR, service point, guest session, order draft, kitchen, bar, payment, or analytics logic has been implemented yet.

## Tables

- `users`
- `password_reset_tokens`
- `sessions`
- `cache`
- `cache_locks`
- `jobs`
- `job_batches`
- `failed_jobs`
- `passkeys`
- `roles`
- `permissions`
- `permission_role`
- `role_user`
- `permission_user_overrides`
- `organizations`
- `organization_users`
- `brands`
- `branches`
- `branch_users`
- `branch_settings`
- `invitations`
- `migrations`

## Current Domain Model

Organization:

- Represents the company or owner of a restaurant business.
- Has many brands.
- Has many branches.
- Has many users through `organization_users`.

Brand:

- Belongs to an organization.
- Has many branches.

Branch:

- Belongs to a brand and an organization.
- Is the current working unit for future menu, zones, service points, and orders.
- Has one settings record.
- Has many branch staff assignments through `branch_users`.

Branch settings:

- Stored in `branch_settings`.
- Created with each new branch.
- Safely created on the settings page for existing branches that do not have settings yet.

Superadmin access:

- Uses the fixed `superadmin` role from the `roles` table.
- First superadmin can be seeded from `SUPERADMIN_NAME`, `SUPERADMIN_EMAIL`, and `SUPERADMIN_PASSWORD`.
- The superadmin route is protected by `superadmin` middleware.
- The platform dashboard is visible only to superadmins.
- Superadmins can see all organizations, brands, branches, and users.
- Superadmins bypass organization and branch-level access checks.
- Regular users keep organization-scoped access only.

Invitation:

- Stored in `invitations`.
- Belongs to an organization.
- Can optionally belong to a brand.
- Can optionally belong to a branch.
- Belongs to a fixed system role through `role_id`.
- Stores optional `email` and `phone`.
- Stores `invite_token` and `invite_code` for future link/code flows.
- Stores `expires_at`, `status`, and `invited_by_user_id`.
- Status enum values are `pending`, `accepted`, `expired`, `cancelled`, and `rejected`.
- `CreateInvitationAction` creates pending invitations with generated token/code defaults.
- Invitation scopes must stay inside the selected organization; branch scope must match selected brand when a brand is provided.
- Staff UI can create invite links/codes, but no email/SMS delivery or public acceptance flow exists yet.

Staff management:

- Organization staff page is `App\Livewire\Organizations\Staff\Index`.
- Branch staff page is `App\Livewire\Organizations\Brands\Branches\Staff\Index`.
- Staff permission override page is `App\Livewire\Organizations\Staff\Permissions`.
- Organization staff route is `GET /organizations/{organization}/staff`.
- Branch staff route is `GET /organizations/{organization}/brands/{brand}/branches/{branch}/staff`.
- Staff permission route is `GET /organizations/{organization}/staff/{staffMember}/permissions`.
- Access requires `manage_staff` in the current organization context.
- Manual staff creation creates or reuses a user, assigns the fixed role, and creates an active `organization_users` membership.
- Branch manual staff creation also creates an active `branch_users` assignment.
- Staff deactivate sets status to `suspended`; activate sets status to `active`.
- Invite link/code creation stores an `invitations` record only; no email/SMS is sent.
- Invite links are displayed for manual copy, but public invite acceptance is not implemented yet.

Staff permission overrides:

- Roles remain fixed; the permission page does not edit a staff member's role.
- Each permission can be `default`, `allow`, or `deny`.
- `default` removes the `permission_user_overrides` row and falls back to `permission_role`.
- `allow` and `deny` save `permission_user_overrides.enabled` as true or false.
- Effective permission display is computed from superadmin access, then user override, then role default.
- Superadmins always have full effective access.
- Critical permissions include `manage_staff`, `manage_subscription`, `manage_settings`, and `export_data`.
- Critical permission changes show a warning.
- Users cannot edit their own permission overrides from the staff permission page.

## Branch Settings Defaults

- `require_waiter_confirmation_for_orders`: true
- `allow_guest_created_sessions`: true
- `allow_waiter_opened_sessions`: true
- `allow_guest_invite_links`: true
- `guest_join_requires_approval`: true
- `polling_interval_seconds`: 1
- `default_language`: `en`
- `default_currency`: branch currency, or `EUR`
- `service_charge_enabled`: false
- `tips_enabled`: false
- `order_flow_mode`: `waiter_confirmation`

## Routes

- `GET /` -> `home`
- `GET /guest` -> `guest.home`
- `GET /dashboard` -> `dashboard`
- `GET /organizations` -> `organizations.index`
- `GET /organizations/{organization}/staff` -> `organizations.staff.index`
- `GET /organizations/{organization}/staff/{staffMember}/permissions` -> `organizations.staff.permissions`
- `GET /organizations/{organization}/brands` -> `organizations.brands.index`
- `GET /organizations/{organization}/brands/{brand}/branches` -> `organizations.brands.branches.index`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/staff` -> `organizations.brands.branches.staff.index`
- `GET /organizations/{organization}/brands/{brand}/branches/{branch}/settings` -> `organizations.brands.branches.settings.index`
- `GET /restaurant/dashboard` -> `restaurant.dashboard`
- `GET /superadmin/dashboard` -> `superadmin.dashboard` guarded by `auth` + `superadmin`
- Auth and profile routes are provided by Fortify and `routes/settings.php`.

## Livewire Components

- `App\Livewire\Organizations\Index`
- `App\Livewire\Organizations\Staff\Index`
- `App\Livewire\Organizations\Staff\Permissions`
- `App\Livewire\Organizations\Brands\Index`
- `App\Livewire\Organizations\Brands\Branches\Index`
- `App\Livewire\Organizations\Brands\Branches\Staff\Index`
- `App\Livewire\Organizations\Brands\Branches\Settings`
- `App\Livewire\Superadmin\Dashboard`
- `App\Livewire\Settings\Profile`
- `App\Livewire\Settings\Security`
- `App\Livewire\Settings\Appearance`
- `App\Livewire\Settings\DeleteUserForm`
- `App\Livewire\Settings\TwoFactor\RecoveryCodes`
- `App\Livewire\Actions\Logout`

## Next Step

The next expected product step may be invite acceptance flow, zones, or service points, but only implement it when a prompt explicitly requests it.

## Do Not Break

- Do not rewrite architecture.
- Do not add unrelated future features.
- Do not add Redis, WebSockets, S3, Docker, paid services, React, Vue, Inertia, or a separate SPA.
- Do not expose internal IDs in future QR/public guest URLs.
- Do not remove SQLite support.
- Do not switch cache, sessions, or queues away from database drivers.
- Do not commit `.env`, SQLite database files, `vendor`, `node_modules`, or storage uploads.
- Do not add business logic to Blade templates.
- Do not add raw SQL strings.

## Verification Commands

Use these checks after small changes:

```bash
php artisan migrate --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact
npm run build
```

For migration reversibility when a new migration is added:

```bash
php artisan migrate:rollback --step=1 --no-interaction
php artisan migrate --no-interaction
```
