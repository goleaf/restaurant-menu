# Security Rules

These rules are mandatory for Codex and future developers. They describe how
security must be preserved in the existing Laravel, Blade, and Livewire code.
This document does not add product behavior.

## Baseline

- The application is server-rendered Laravel with Livewire components, SQLite,
  database cache, database sessions, database queue, and local storage.
- There is no React, Vue, Redis, WebSocket, S3, Docker requirement, online
  payment provider, or paid infrastructure in the current security baseline.
- The server is always the source of truth for permissions, branch access,
  table/session state, order totals, payment balances, and exports.
- Public guest access is not staff authentication. Guest credentials only apply
  to the public QR/table-session flow.

## Multi-Tenant Branch Isolation

- Organization access is the outer boundary; branch access is the operational
  boundary for staff screens and exports.
- Every branch-scoped staff query must filter by branch IDs resolved for the
  current authenticated user.
- Nested route models must be checked as a real hierarchy before use:
  organization -> brand -> branch -> service point -> QR or staff record.
- Branch assignments must be respected. If a staff member is assigned to a
  subset of branches, they must not read, mutate, export, or see counts for
  other branches.
- Superadmin access is allowed only through explicit superadmin checks or
  middleware. Do not let ordinary role or permission fallbacks create accidental
  cross-branch access.
- Kitchen, bar, waiter, department, payment, QR, staff, audit, dashboard, and
  export flows must each re-check branch access at the server/action level.
- Branch IDs in Livewire public properties, route parameters, hidden inputs, or
  query strings are untrusted. Re-resolve access before every mutating action.

## Permissions And Policies

- Staff authorization uses `SystemRole`, `SystemPermission`, role permissions,
  permission overrides, organization membership, and branch assignment checks.
- Hiding a button is not authorization. Every staff action that reads sensitive
  data or mutates state must perform a server-side permission check.
- Destructive or sensitive actions must require the matching permission and
  should use an explicit confirmation flow.
- Permission management UI must show human labels and short descriptions for
  directors/managers; raw permission keys may be shown only in superadmin
  technical mode.
- Critical permission changes must require an explicit confirmation flow and a
  human-readable reason before the server mutates the override.
- Policies may be added for consistency, but they must not replace existing
  action-level branch and permission guards where those guards are the source of
  truth.
- Do not hardcode role/status strings in random views or actions. Use enums and
  translation-backed labels.

## Error Handling

- Use `App\Enums\ApplicationErrorType` for shared error categories.
- Expected domain denials should use controlled translated validation-style
  errors, preferably `App\Exceptions\BusinessRuleViolation`.
- Unexpected exceptions must not be swallowed. Let Laravel report them and show
  only safe translated error pages to users.
- Guests and ordinary staff must not see stack traces, raw exception messages,
  internal IDs, tokens, raw permission keys, SQL, filesystem paths, config
  values, or secrets.
- Activity/audit logs are for business actions. Do not write audit rows for
  every technical exception.

## Guest Vs Staff Separation

- Staff users authenticate through Laravel/Fortify session auth.
- Guests are records such as `table_session_guests` or
  `table_session_join_requests`, not `users`.
- A `guest_token` is a public table-session credential only. It must never grant
  access to staff routes, staff Livewire actions, admin exports, backups, or
  staff notifications.
- Guest actions may affect only the current QR/table-session flow after the
  server verifies QR status, service point status, branch, table session status,
  active guest status, and guest token.
- Staff screens must never trust guest session state, guest cookies, or public
  QR parameters as staff authorization.

## QR Token Rules

- `qr_codes.public_token` is the public QR route token for `/q/{token}`. It must
  be random, unique, non-incremental, and not derived from IDs.
- `qr_codes.short_code` is for staff lookup/printing. It is not authentication
  and must not replace `public_token`.
- Current QR generation uses a random 64-character `public_token`. Do not
  shorten it, encode internal IDs into it, or accept a printed `short_code` as a
  public route credential.
- Guest QR URLs must not expose organization ID, branch ID, service point ID,
  table session ID, guest ID, or order ID.
- One active QR code should represent a physical service point. Moving,
  renaming, merging, transferring, closing, paying, or cancelling sessions must
  not silently regenerate permanent QR identity.
- Disabling a QR must block public guest entry while preserving audit context.
- Reissuing a QR must revoke previous active QR rows, create a fresh random
  public token and short code, and record who performed the action.
- QR generation, disable, reissue, print, and short-code lookup actions must
  check branch access and the correct QR/service point relationship.

## guest_token Rules

- `guest_token` values must be random, unique, non-incremental, and long enough
  for bearer-token use. Current guest and join-request tokens use 64 random
  characters.
- A `guest_token` must be tied to the table session or join request it was
  created for. Restoring a guest from cookie/session must re-check token,
  branch, table session, service point, guest status, and session status.
- Do not put `guest_token` in URLs, Blade data attributes, browser-visible JSON,
  CSV exports, staff UI, logs shown to users, notifications, or screenshots.
- Cookie names may be derived from the public QR token, but the raw
  `guest_token` value remains sensitive.
- If an audit/internal table stores a `guest_token` for traceability, any UI or
  export must mask it or show a non-sensitive actor label instead.
- Old, removed, rejected, left, expired, closed, or cancelled guest states must
  not be allowed to mutate orders, request waiter/bill actions, approve join
  requests, or create invite links.

## Invite Token Rules

- Staff invitations use `invitations.invite_token` and
  `invitations.invite_code`. Guest table invitations use
  `table_sessions.guest_invite_token`. These token families must never be
  reused for each other.
- Invite tokens must be random, unique, non-incremental, scoped to the intended
  organization/brand/branch/table session, and checked against status and
  expiration before use.
- Staff invite links must resolve through `Invitation::findAcceptableByToken()`
  or the same rule: 64-character alphanumeric token, `pending` status, and a
  future `expires_at`.
- Staff invitation creation must verify that the selected brand and branch
  belong to the selected organization.
- Guest invite creation must verify that the table session is active enough to
  invite, the service point is active, branch settings allow invite links, and
  the creating guest is active in that same table session.
- A closed or cancelled table session must never create a join request or order
  access from `guest_invite_token`.
- Invite links are bearer links. Do not expose them in exports, logs, public
  lists, or unrelated staff pages.

## Livewire Public Property Rules

- Treat every Livewire public property as user input. Livewire public state can
  be changed by the browser.
- Never store secrets, `.env` values, API keys, password reset tokens,
  `guest_token`, staff invite tokens, backup paths, or raw permission decisions
  in public properties.
- IDs stored in public properties must be locked, reloaded, or re-authorized
  before use. Prefer passing only IDs and reloading scoped models inside the
  action.
- Public properties may drive UI state, but they must not be the authority for
  branch access, role access, money totals, table status, guest status, or file
  paths.
- Every mutating Livewire method must validate input and perform server-side
  authorization before writing.
- Do not keep large Eloquent collections in public properties when branch or
  permission constraints matter. Build prepared payloads from server-side
  Actions instead.

## Validation Rules

- Validate all external input: HTTP request data, route parameters, query
  strings, Livewire properties, uploaded files, and CSV/export type parameters.
- Use Form Requests for controller-owned HTTP forms when the project introduces
  such forms. Use Livewire validation inside Livewire components. Use Action
  guard methods for domain invariants.
- Validate enum inputs against enum values, not free-form strings.
- Validate that IDs belong to the current organization, brand, branch, table
  session, service point, menu, order, or guest before using them.
- Normalize user-visible text fields with length limits before saving. Examples
  include guest names, reasons, notes, comments, labels, and contact fields.
- Client-side validation is advisory only. It must never replace server-side
  validation.

## File Upload Rules

- Uploaded media stays local. Use the configured local `public` disk under
  `storage/app/public`; do not add S3 or paid storage services.
- Only allow expected image uploads. Current local image rules require `image`,
  MIME-compatible `jpg`, `jpeg`, `png`, or supported `webp`, matching original
  file extensions, and a 2048 KB maximum.
- Store generated filenames, such as UUID-based names, selected from the
  validated MIME type. Never trust original filenames as storage paths.
- Do not allow PHP, PHAR, PHTML, scripts, backups, database files, secrets, or
  archives in public storage.
- Keep the `storage/app/public/.htaccess` PHP-deny rule intact for shared
  hosting.
- Deleting/replacing media must use storage-aware Actions and must not accept a
  browser-provided path without ownership and branch checks.
- Backups must never be stored in public storage.

## XSS Escaping

- Use escaped Blade output with `{{ }}` or the escaped `<x-ui.plain-text>`
  component for user, staff, guest, menu, order, branch, reason, note,
  notification, and audit values.
- Normalize current plain-text write paths through `App\Support\PlainText` or an
  equivalent explicit sanitizer before saving guest names, comments, notes,
  reasons, branch public profile text, category text, and menu item text.
- Use `{!! !!}` only for framework-generated or audited safe HTML/SVG. Current
  allowed first-party raw output is generated QR SVG only. Every raw output must
  be easy to justify in code review.
- Translated strings and dynamic translation replacements should still be
  rendered through escaped Blade output unless explicitly safe.
- Do not render guest comments, menu descriptions, audit summaries, staff names,
  export previews, or notification text as raw HTML.
- Guest comments must not support HTML. Menu descriptions are plain text unless
  a future prompt adds explicit limited-format sanitization.
- Preserve user line breaks safely with escaped text and CSS such as
  `whitespace-pre-line`; do not use raw HTML to preserve line breaks.
- Protect layouts from long unbroken strings with `break-words` or equivalent.
- URLs shown in `href`, `src`, and share links must be generated through Laravel
  helpers or sanitized before rendering.

## CSRF Route Protection

- Mutating web routes and forms must use Laravel's web middleware and CSRF
  protection.
- Public guest routes may be unauthenticated only when they are GET-only guest
  entry/read surfaces such as `/guest` and `/q/{token}`.
- Admin, organization, restaurant, waiter, kitchen, bar, department, export,
  settings, profile, and superadmin screens must require authenticated web
  sessions.
- Superadmin backup/download routes must require both `auth` and `superadmin`.
- Export downloads must require route-level auth and server-side `export_data`
  branch access.
- Blade forms that POST, PUT, PATCH, or DELETE must include `@csrf`.
- Livewire requests are protected through the web/session CSRF flow. Do not
  move Livewire actions to unprotected routes.
- Public GET routes such as `/q/{token}` may render public pages, but all
  mutating public guest actions still require server-side token and state
  checks.
- Do not add CSRF exclusions unless the route is a deliberately audited webhook.
  No webhook baseline exists in this project.
- Do not expose the private `local` filesystem disk through unauthenticated
  storage routes. Public media belongs on the `public` disk and `/storage`
  symlink; sensitive downloads need explicit auth, permission, superadmin, or
  signed-url access checks.

## Money Calculation Server-Side

- Never trust frontend totals, hidden totals, JavaScript calculations, or Blade
  calculations for billing.
- Manual payment summaries must be calculated from confirmed server-side
  records: orders, order items, manual payments, table sessions, guests, branch
  settings, service charge, and tips settings.
- Open drafts cannot be paid. Staff must confirm, reject, or return a draft
  before payment is recorded.
- Payment records must store snapshots needed to preserve historical service
  charge, tips, covered subtotal, currency, guest, and staff actor data.
- Tips are optional extras and must not reduce the required subtotal or service
  charge balance.
- Exports may display payment data, but they must not become a source of truth
  for future calculations.

## Activity Log Integrity

- Audit logs should be append-only for normal application flows.
- Sensitive actions must record actor, organization, branch, entity type,
  entity ID, old values, new values, and timestamp when available.
- Activity logs must be scoped by accessible organization and branch IDs before
  display.
- Do not let public guests write staff audit records as authenticated users.
  Guest actors must stay separate from staff actors.
- `guest_token` may exist in internal audit data only when needed for
  traceability. UI, exports, and staff-visible summaries must mask or omit it.
- Do not delete or edit audit logs to hide actions. If correction is needed,
  write a new event that explains the correction.

## Backup Security

- SQLite backup download is superadmin-only and must stay behind `auth` plus
  explicit superadmin middleware/checks.
- Backup download must use no-store/no-cache headers and must not create a
  public backup copy on the server.
- Backups contain sensitive data: users, staff access, guest sessions, tokens,
  orders, payments, and audit records.
- Keep SQLite database files, downloaded backups, manual backup copies, and
  media archives outside `public/` and outside git.
- Never include `.env`, secrets, credentials, or private hosting paths in a
  downloadable backup or media archive.
- Backup actions must be audit logged with the authenticated superadmin actor.

## Exports Security

- CSV export downloads require authentication and the `export_data` permission
  for the resolved accessible branches.
- Export branch IDs must come from server-side branch access resolution, not the
  requested URL alone.
- Exports must include only the selected branch data. A user assigned to one
  branch must not receive rows from another branch.
- Export type must be validated against `DataExportType`.
- CSV responses should be streamed and no-store/no-cache. Do not create export
  files in public storage.
- Exports must not include `.env`, secrets, password hashes, two-factor secrets,
  remember tokens, `guest_token`, invite tokens, backup paths, or private
  filesystem paths.
- Treat downloaded exports as sensitive files. They may contain guest names,
  staff names, order history, payment data, menu data, and service point data.

## Shared Hosting Limitations

- Keep the project compatible with shared hosting: SQLite, local files, database
  cache, database sessions, and database queue.
- Do not add Redis, WebSockets, S3, external queue/cache services, paid backup
  services, Docker requirements, React/Vue SPA migration, or online payment
  dependencies without an explicit product decision.
- Keep the web root pointed at `public/` whenever possible.
- Keep database files, backups, generated private files, and logs outside the
  public web root.
- Preserve `public/.htaccess` front-controller behavior and
  `storage/app/public/.htaccess` PHP execution denial for Apache-style shared
  hosting.
- Long-running work must fit shared-hosting constraints. Prefer small bounded
  database jobs, database queue, and explicit scheduler/cron notes when needed.

## Never do

- Never trust frontend totals.
- Never expose `guest_token`.
- Never expose `.env` or secrets.
- Never use public incremental IDs as tokens.
- Never allow staff action without permission.
- Never show another branch data.
- Never put business logic only in Blade.
- Never store sensitive data in Livewire public properties.
- Never use a QR short code, invite code, database ID, email, phone, or name as
  an authentication token.
- Never create staff access from a guest session.
- Never store backups or private exports under `public/` or public storage.
- Never bypass branch isolation because a button is hidden in the UI.
