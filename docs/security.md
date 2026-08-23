# Application security

Report vulnerabilities privately as described in the root [`SECURITY.md`](../SECURITY.md). This document is the implementation control catalogue.

## Identity and sessions

Fortify owns registration, password login/reset/confirmation and account flows. Authentication regenerates the session; logout invalidates it and regenerates the CSRF token. Sensitive endpoints use named rate limiters and responses avoid unnecessary account disclosure. Passkey and two-factor code remains feature-gated but both features are disabled by current configuration; dormant credentials stay encrypted/hidden. Demo identities are seeded only outside production.

## Authorization and isolation

Every protected route requires authentication, and every resource operation additionally uses a policy or explicit broad gate. Nested route bindings are scoped. Livewire action parameters and public properties are hostile input: the server reloads the resource in its authorized organization/branch/table scope before mutation. Owner, role, permission override and superadmin behavior is defined in [`authorization.md`](authorization.md) and tested positively and negatively.

## Tokens and sensitive values

Invitation and equivalent one-time credentials use cryptographically secure random values, store only digests, include purpose/owner/expiry, and are consumed atomically once. Complete tokens, session identifiers, authorization headers, passwords, keys and sensitive request bodies are never logged. Serialized models hide password, authentication, two-factor, remember and token material.

## Input and output

Server validation covers type, boundary, ownership, enum, nested keys, dates, money and file content. Validation does not replace authorization or database constraints. Blade escapes user data by default; raw HTML/SVG/JSON is allowed only from a named, tested trusted-data boundary. There is no first-party raw SQL, command execution, remote URL fetch or direct model access in Blade.

## Files and backup

Uploads use configured disks, MIME/content/size validation and generated names. Private downloads authorize at request time. Replacements write the new object and persist its path before deleting the old object; failure compensation avoids database/file divergence. SQLite backup download and restore are restricted to superadmins behind recent password confirmation, typed confirmation and an audited reason. Restore authorization carries a server-side one-time nonce, and the uploaded SQLite header plus the complete table/column/index/foreign-key/view fingerprint must match the current release before live data can change. Restore runs under a filesystem lock and maintenance mode, creates a private consistent pre-restore snapshot, rolls back automatically on any post-replacement failure, clears cache and remember tokens, and invalidates all sessions. File contents, absolute paths and authorization nonces are not logged.

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
