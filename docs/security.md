# Application security

Report vulnerabilities privately as described in the root [`SECURITY.md`](../SECURITY.md). This document is the implementation control catalogue.

## Identity and sessions

Public Fortify registration is disabled at the feature, route, backend and navigation boundaries. Account creation is available only through `/invite/{token}` for a valid email-bound staff invitation; an existing matching account signs in and joins the tenant instead. The bearer token is removed from the URL immediately, acceptance is an atomic compare-and-set from pending to accepted, and replay cannot create duplicate membership. Expired, revoked, malformed, mismatched and otherwise unavailable credentials use localized token-free states without tenant or recipient disclosure. The invitation limiter applies both a per-credential/client budget and an independent per-client budget, so rotating guessed credentials cannot bypass throttling. Fortify owns password login/reset/confirmation and the remaining account flows. Authentication regenerates the session; logout invalidates it and regenerates the CSRF token. Sensitive endpoints use named rate limiters and responses avoid unnecessary account disclosure. Passkey and two-factor code remains feature-gated but both features are disabled by current configuration; dormant credentials stay encrypted/hidden. Demo identities are seeded only outside production.

## Demo authentication boundary

- Demo login requires the explicit `DEMO_LOGIN_ENABLED=true` flag outside production; production always returns 404 even if the flag is enabled accidentally.
- The demo environment guard has priority before CSRF and the shared demo throttle so hidden requests neither reveal the feature nor consume its rate-limit budget. Global web middleware still keeps CSRF validation before authentication.
- Both named routes are guest-only, use the normal web/CSRF stack and share a 20-requests-per-minute-per-IP limiter. The POST role parameter is restricted to the `SystemRole` enum allowlist.
- Authentication reloads the canonical demo email, revalidates the exact email-role assignment, uses the `web` guard and regenerates the session before redirecting. Missing or mismatched identities fail generically without login.
- The role page is private/non-cacheable and sends `Referrer-Policy: no-referrer` plus `X-Robots-Tag: noindex, nofollow`. Passwords, complete tokens and session identifiers are not rendered or logged.

## Authorization and isolation

Every protected route requires authentication, and every resource operation additionally uses a policy or explicit broad gate. Nested route bindings are scoped. Livewire action parameters and public properties are hostile input: the server reloads the resource in its authorized organization/branch/table scope before mutation. Owner, role, permission override and superadmin behavior is defined in [`authorization.md`](authorization.md) and tested positively and negatively.

Restaurant onboarding exposes only a locked checkpoint identifier and locked selected UI step. Checkpoint creation rejects users who already hold any tenant membership and fails closed for orphaned non-owner system identities, preventing limited staff from minting an owner context. Every Livewire hydration reauthorizes the checkpoint owner plus the active membership, subscription and exact active branch assignment when branch assignments exist; suspended/removed assignments and explicit permission denies never fall back to owner/director shortcuts. Stale snapshots stop returning setup data as soon as access is revoked. The read service scopes each organization, brand, branch, area, service point, QR and menu relation before hydrating it, so a corrupt checkpoint cannot place another tenant's names or QR identity in component state. Every write re-resolves the checkpoint by authenticated user, validates the persisted organization/brand/branch and table/area chain, and authorizes the concrete organization/brand/branch/area/service-point/QR/menu/price/availability operation before writing. Ordered onboarding service points replace browser-submitted ID arrays; the expected count, table type, contiguous positions, database uniqueness and retryable transactions make duplicate/stale submissions converge on one tenant graph. Cross-branch table links and stale cross-tenant branches fail before the first mutation; same-branch survivors are reused during hard-deleted area recovery, and a missing hard-deleted table is reconstructed before QR/menu completion. A soft-deleted linked resource is recoverable only through the explicit checkpoint-resource policy operation when its original owner/parent chain still matches and the subscription remains active.

## Tokens and sensitive values

Invitation and equivalent one-time credentials use cryptographically secure random values, store only SHA-256 digests, include recipient, tenant, role, creator and expiry scope, and are consumed atomically once. Creating, reissuing, revoking and accepting an invitation writes a token-free audit event. Because a digest cannot reconstruct its bearer, “reissue” rotates the credential, invalidates the old link, extends expiry and exposes the replacement only in the current authorized administrator UI state. No invitation email is sent while no delivery integration is configured; the administrator copies the displayed link through a trusted channel. The obsolete `invitations.invite_token` and `invite_code` plaintext columns were removed by a guarded forward migration that refuses to contract the schema if either legacy value exists. Complete tokens, session identifiers, authorization headers, passwords, keys and sensitive request bodies are never logged. Serialized models hide password, authentication, two-factor, remember and token material.

## Input and output

Server validation covers type, boundary, ownership, enum, nested keys, dates, money and file content. Validation does not replace authorization or database constraints. Blade escapes user data by default; raw HTML/SVG/JSON is allowed only from a named, tested trusted-data boundary. There is no first-party raw SQL, command execution, remote URL fetch or direct model access in Blade.

## Files and backup

Uploads use configured disks, MIME/content/size validation and generated names. Dish galleries additionally enforce branch ownership and a combined primary-plus-secondary maximum of eight before storage. New files are collected for compensation: any persistence failure deletes only those attempted paths, while promotion swaps stored references without copying and deletion removes a file only after persistence succeeds. Soft deletion of a dish, category or menu removes every owned gallery row transactionally and then cleans its primary and secondary files. Private downloads authorize at request time. Replacements write the new object and persist its path before deleting the old object; failure compensation avoids database/file divergence. SQLite backup download and restore are restricted to superadmins behind recent password confirmation, typed confirmation and an audited reason. Restore authorization carries a server-side one-time nonce, and the uploaded SQLite header plus the complete table/column/index/foreign-key/view fingerprint must match the current release before live data can change. Restore runs under a filesystem lock and maintenance mode, creates a private consistent pre-restore snapshot, rolls back automatically on any post-replacement failure, clears cache and remember tokens, and invalidates all sessions. File contents, original names, absolute paths and authorization nonces are not logged.

## Payments and races

Payment creation, correction and session closure acquire an appropriate database serialization boundary, re-read the balance, validate minor-unit invariants and write atomically. Duplicate submission is harmless or rejected deterministically. External acquiring and webhooks are not present; adding them requires signature, replay and idempotency controls.

## Operational controls

- `APP_DEBUG=false` and secure cookies are production requirements.
- Production cannot run demo seeders.
- Composer and npm advisories are release gates.
- Unexpected failures are reported with bounded, non-sensitive structured context; users receive localized safe messages.
- Raw request paths are excluded from exception context; UUID request IDs and route templates provide correlation without logging QR/invitation credentials.
- Production error email is opt-in, deduplicated and limited to safe incident metadata; exception messages, stack traces, request bodies and user data remain in neither the alert nor its deduplication key.
- Log context is recursively redacted and production file logs rotate with bounded retention.
- Destructive maintenance operations are explicit, authorized, confirmed and audited.
- Dependency or environment exceptions are documented with an exact advisory/blocker and affected requirement ID.

Security regression coverage is mapped under the `sec-*` requirements in [`compliance-matrix.md`](compliance-matrix.md).
