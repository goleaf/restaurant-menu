# Application security

Report vulnerabilities privately as described in the root [`SECURITY.md`](../SECURITY.md). This document is the implementation control catalogue.

## Identity and sessions

Public Fortify registration is disabled. Account creation is available only through a valid staff invitation with recipient binding and atomic one-time consumption; Fortify owns password login/reset/confirmation and the remaining account flows. Authentication regenerates the session; logout invalidates it and regenerates the CSRF token. Sensitive endpoints use named rate limiters and responses avoid unnecessary account disclosure. Passkey and two-factor code remains feature-gated but both features are disabled by current configuration; dormant credentials stay encrypted/hidden. Demo identities are seeded only outside production.

## Demo authentication boundary

- Demo login requires the explicit `DEMO_LOGIN_ENABLED=true` flag outside production; production always returns 404 even if the flag is enabled accidentally.
- The demo environment guard has priority before CSRF and the shared demo throttle so hidden requests neither reveal the feature nor consume its rate-limit budget. Global web middleware still keeps CSRF validation before authentication.
- Both named routes are guest-only, use the normal web/CSRF stack and share a 20-requests-per-minute-per-IP limiter. The POST role parameter is restricted to the `SystemRole` enum allowlist.
- Authentication reloads the canonical demo email, revalidates the exact email-role assignment, uses the `web` guard and regenerates the session before redirecting. Missing or mismatched identities fail generically without login.
- The role page is private/non-cacheable and sends `Referrer-Policy: no-referrer` plus `X-Robots-Tag: noindex, nofollow`. Passwords, complete tokens and session identifiers are not rendered or logged.

## Authorization and isolation

Every protected route requires authentication, and every resource operation additionally uses a policy or explicit broad gate. Nested route bindings are scoped. Livewire action parameters and public properties are hostile input: the server reloads the resource in its authorized organization/branch/table scope before mutation. Owner, role, permission override and superadmin behavior is defined in [`authorization.md`](authorization.md) and tested positively and negatively.

## Tokens and sensitive values

Invitation and equivalent one-time credentials use cryptographically secure random values, store only digests, include purpose/owner/expiry, and are consumed atomically once. Complete tokens, session identifiers, authorization headers, passwords, keys and sensitive request bodies are never logged. Serialized models hide password, authentication, two-factor, remember and token material.

## Input and output

Server validation covers type, boundary, ownership, enum, nested keys, dates, money and file content. Validation does not replace authorization or database constraints. Blade escapes user data by default; raw HTML/SVG/JSON is allowed only from a named, tested trusted-data boundary. There is no first-party raw SQL, command execution, remote URL fetch or direct model access in Blade.

## Files and backup

Uploads use configured disks, MIME/content/size validation and generated names. Private downloads authorize at request time. Replacements write the new object and persist its path before deleting the old object; failure compensation avoids database/file divergence. A SQLite backup is a transactionally consistent snapshot and is restricted to superadmins; every download is audited without logging file contents.

## Payments and races

Payment creation, correction and session closure acquire an appropriate database serialization boundary, re-read the balance, validate minor-unit invariants and write atomically. Duplicate submission is harmless or rejected deterministically. External acquiring and webhooks are not present; adding them requires signature, replay and idempotency controls.

## Operational controls

- `APP_DEBUG=false` and secure cookies are production requirements.
- Production cannot run demo seeders.
- Composer and npm advisories are release gates.
- Unexpected failures are reported with bounded, non-sensitive structured context; users receive localized safe messages.
- Destructive maintenance operations are explicit, authorized, confirmed and audited.
- Dependency or environment exceptions are documented with an exact advisory/blocker and affected requirement ID.

Security regression coverage is mapped under the `sec-*` requirements in [`compliance-matrix.md`](compliance-matrix.md).
