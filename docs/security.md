<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Application security

## Prompt 8 Team scope and conflict safeguards

Opening Team/card, managing staff, editing organizational overrides, changing rooms and reading employee history are separate authorizations. A permissions-only administrator can enter the appropriate card without gaining staff mutation rights. Every public Livewire operation reauthorizes; immutable identifiers and the shared signed actor snapshot guard complement this check. Current organization/branch status is re-read on the next server request.

First assignment scope restriction and final-assignment inheritance restoration are explicit intents, never silent checkbox effects. Protected accounts, self-escalation, delegation, last owner and EnsureOrganizationManagementRemainsAction remain enforced. Individual permissions remain organization-scoped; no legacy override is copied into another organization. Independent SQLite-process tests cover conflicting changes and last-manager preservation; audit failure rolls back the corresponding mutation. Invitation credentials remain digest-only and their cancellation/reissue are version-bound. Existing accepted-account, email, verification and MFA flows are reused.


## Workspace request identity and context — 2026-09-16

Every first-party authenticated Livewire snapshot carries a signed actor memo. The hook registers before Livewire boots its hook registry; a subsequent HTTP update under a different account fails with 409, even when both accounts have equivalent permissions. Authenticated legacy snapshots without the actor memo also require a fresh page before an operation. Public QR guest components retain their existing independent guest contract. Locked identifiers still require resource authorization and are never treated as hidden data.

Restaurant context comes from an authorized object, route or explicit query, in that order; conflicting explicit sources fail. Session preference is only an entry hint. Each page retains its own locked restaurant identifier, and only validated local filters participate in Livewire URL hydration. The header switcher navigates to a real authorized URL; it never rewrites another component's tenant. A revoked target and an infrastructure read failure cannot clear the identifier and silently fall back to a different accessible restaurant. GET/HEAD/prefetch never save the preference; the arrived header explicitly remembers its verified destination.

WorkspaceBoundaryTest uses original signed HTTP snapshots to test account changes, revoked access, conflicting parents, narrow roles and transient failures. UnifiedWorkspaceBrowserTest uses two actual pages in one browser context to verify that changing the shared preference in one tab cannot retarget an ordering save in the other. No authentication pipeline, QR credential, invitation or database schema is changed.

## Browser migration security boundary — 2026-09-16

Alpine owns transient browser state only; class-based Livewire validates and authorizes mutations through existing Actions. The architecture suite pins the class-based page inventory and explicit native HTTP contracts. Inline executable modules/large state objects and independent application AJAX are removed; user content is not inserted with `x-html` or `innerHTML`.

The sole first-party `fetch` exception is `resources/js/integrations/passkeys.js`: exact same-origin options/credential routes, credentials and CSRF headers, redirect rejection, AbortController ownership and stale-response checks after every asynchronous boundary. The pinned SimpleWebAuthn SDK retains cryptographic browser operations. Cancel during options GET cannot start a later ceremony. Cancel during POST never claims a rollback or success; a subsequent authoritative server refresh resolves the outcome. No auth/invitation secret is persisted in a browser store.

Generated inline PDF/emergency CSS is built only from fixed repository Sass entrypoints; the generator rejects HTML/PHP/Blade delimiters. Remaining inline styles are bounded photo focal-point data in the exact architecture allowlist. User input cannot select a filesystem stylesheet path. Existing CSRF, invite replay/expiry, MFA, session, restore locks and tenant tests remain mandatory.


## Flux Pro source and activation boundary — 2026-09-15

The preserved internal package retains its proprietary license and is explicitly marked as a user-provided snapshot with unknown upstream version; hashes prove consistency with that snapshot, not authenticity. No Composer credential is copied into provenance, tests or documentation. Serve only the normal Laravel `public/` document root; neither original nor internal package source becomes a public asset directory.

Compatibility must be resolved through an accepted release before activation. Component-hidden fields, client selection IDs, context actions and Kanban moves remain untrusted and authorize server-side. Editor support is still pending: current descriptions remain escaped plain text until a separately tested sanitizer, allowlist and rendering/storage contract exists. P16/P17 require clean release and rollback proof before original-source deletion; an integrity test alone is insufficient.

## Final team security corrections — 2026-09-15

The recipient form rejects an email that differs from the invitation before querying global account uniqueness. Existing and unknown foreign addresses produce the same localized mismatch response. Password requirements retain the shared Fortify-compatible rules and receive complete EN/LT/RU validation messages.

Invitation consent includes current recipient, scope, role and credential digests; stale forms cannot accept a replaced version. Current archived organization state blocks creation/reissue, including system actors. Exact successful replay does not mutate membership or repeat audit. All invitation responses, including 410, validation redirects and 429, carry no-store, no-referrer and noindex headers. Raw credentials remain transient and are excluded from snapshots, logs and browser storage.

Branch assignment of an existing accepted member checks the member's current organization role as well as the requested branch role and rejects self-assignment. This prevents a lower administrator from narrowing a higher manager's other branch access. Organization capability explanations inspect the actual Gate/Policy response. Current membership, subscription, branch and object-specific checks remain authoritative on every request.

## Team invitations and access — 2026-09-15

Manual browser staff provisioning is removed at the callable Livewire boundary. Invitation creation creates no User or active membership. Existing provisioning for factories, demo data and initial organization ownership remains separate. Normalized email retains provider-independent dots and plus suffixes. Creation and rotation reject duplicate active invitations in the same scope under SQLite write serialization.

The recipient sees a token-free pending route. The session stores a credential digest, and POST carries a nonsecret version fingerprint binding the displayed invitation as well as the current credential. Rotation, revocation, email changes and scope suspension invalidate stale forms. GET never accepts. Existing users authenticate and explicitly consent; mismatched accounts can switch without changing email. Registration does not confer email verification. Acceptance uses conditional persistence and transactional audit; exact retry cannot add memberships, change roles or repeat an audit event. Responses use no-store and no-referrer. Plaintext administrator links are transient display state only, never recovered from hashes or saved in browser storage.


## Catalogue exchange and photo metadata — 2026-09-15

CSV is local UTF-8, at most 100 rows and 1 MiB per import/export batch. Empty id creates an unavailable dish; an existing id updates only within the selected menu/branch. Missing CSV rows never delete data; photos, stock and kitchen routing are preserved on update. Arbitrary image URLs and foreign IDs are rejected. Preview is read-only; apply repeats validation, verifies content fingerprints and uses a durable request receipt for replay. Spreadsheet-dangerous leading values are escaped on export. Bulk selection is limited to the displayed 24 dishes with per-row scope/version checks; archive soft-deletes while preserving files and historical orders. Image presentation uses image identity plus revision checks and never rewrites originals.

## Interaction recovery authorization — 2026-09-15

Local guest dialog visibility grants no order permission. Opening still resolves the scoped public menu item, and adding still revalidates guest/session/branch availability and server-owned prices. Retained same-item configuration and request UUID reuse the existing idempotency boundary. A caught media failure is considered committed only after an existing receipt matches actor, branch, request, operation kind and target item; otherwise pending uploads are retained and no success is reported.

## Stale guest and rollback recovery — 2026-09-15

Guest URL locale persistence now follows the same active-status boundary as explicit locale events. Stale or revoked guest identities cannot mutate a guest preference merely by restoring a cookie with `?lang=`. Availability refresh never grants ordering permission: current scope, guest rights, item availability and the existing add-operation idempotency checks remain authoritative.

Rollback file compensation cannot throw even when the diagnostic logger also fails. This prevents interrupted transaction-manager cleanup from executing stale old-file deletion during a later commit. Warning context is restricted to the owned generated path and exception class; original uploads, tokens and exception messages are not logged.

## Product authorization and recovery safeguards — 2026-09-14

Reopening a temporarily closed branch requires current `manage_settings`, including from the waiter dashboard. Viewing or confirming orders does not grant this setting mutation. Catalogue, modifier and variant HTTP/Livewire reads recheck current menu-management access; a revoked capability does not survive in serialized UI flags. Image and operation Actions re-resolve the original tenant/branch and authorize every continuation or replay.

Images are content-decoded under byte/pixel/memory limits, oriented and re-encoded to remove metadata. Generated local paths and their known derivatives share rollback and cleanup ownership. UUID operation receipts prevent duplicate uploads after lost responses; stale content fingerprints and source-change markers prevent silently overwriting a competing edit or publishing an inconsistent copy.

SQLite restoration requires the SQLite3 extension and Online Backup API. A shared HTTP request lock protects reads/session writes; restore acquires exclusive access before authentication, and checks maintenance again after acquiring it. Failed restore plus failed rollback retains maintenance and the durable recovery barrier. Supported database/file/array session state, remember tokens and configured cache stores are invalidated on success. An unsupported or failed invalidation fails the operation; it is not silently treated as success. External CLI writers must be stopped separately; the local lock is not a multi-host coordination system. Recovery instructions are in operations.md.

## Eighth audit input and allocation boundaries — 2026-09-14

Area/service-point create, edit and bulk inputs keep their original browser types for shared validation. String-only trimming and numeric plus integer rules prevent silent coercion of names, flags, capacity, sort and parent selectors. Parent/area ownership and current capability checks remain mandatory. The reusable bulk Action also bounds allocation before queries, rejects reversed/nonpositive ranges and aborts the entire transaction if a model event rejects a required save. Archived internal codes stay reserved; no QR credential is generated or exposed by table creation.

## Seventh audit input boundaries — 2026-09-14

Menu, category, dish, schedule, modifier-group, option and variant create/edit operations now validate raw transport values. Arrays/nulls receive field errors, boolean names and integer limits are rejected, native float/boolean money stays invalid, and the string `false` cannot become a true flag through PHP property coercion. Safe projections protect dependent reads from invalid selectors while original IDs still pass numeric/integer and branch/parent-scoped existence rules. Valid numeric strings and `0`/`1` checkbox encodings retain exact persistence. Existing server-side authorization and independent price/availability capabilities remain required; MenuEditorTransportTest and the existing menu permission tests cover these boundaries.

## Sixth audit trust boundaries — 2026-09-14

Logo/cover mutation reloads the active record through its original organization/brand scope before storing a replacement or removing the current reference. Stale models after another save or rollback cannot choose the cleanup path; unrelated dirty attributes are not persisted. Moved or archived records fail before file storage. Existing policy checks at entry points and transaction compensation remain required.

Kitchen-department create/edit validates original transport values: arrays and nulls receive field errors, boolean names/sort positions are rejected, and the string false is rejected rather than becoming an enabled flag. Accepted numeric strings and boolean true/false/1/0 encodings remain supported. Access-query optimization must preserve membership, subscription and assignment restriction semantics; it does not cache authorization.

## Required media writes and commit failures

Logo, cover and gallery Actions reject cancelled model saves/creates/deletes inside their owning transaction. Shared replacement/removal owns a transaction even for a standalone caller; new-file compensation is registered before persistence. A persistence exception rolls back the reference and removes only uncommitted uploads. An exception from a persistence observer after commit must preserve the committed new file; later old-file cleanup may not execute and can leave an orphan. Do not interpret `saveOrFail()` or `deleteOrFail()` as guaranteed exception-on-veto methods.

## Menu deletion containment

Parent media cleanup scopes discovery and category observer cascades to the original menu, including malformed cross-menu child/item references. A visited-category set makes cyclic discovery terminate, and fallback candidates recheck active existence before deletion. Remaining menu-owned items are removed without touching their foreign category. A deletion-event veto rolls back root, child and gallery writes and preserves media. Current paths are streamed to an owned temporary file before deletion; database rollback retains the referenced media, while physical deletion runs after the outer commit. This temporary file is not a durable retry queue: an interrupted process or post-commit storage failure can leave orphaned files, and cannot undo the committed database operation.

## Compound settings and media transactions

Branch settings are validated before any write and the aggregate Action rechecks current branch ownership, membership and the branch-scoped settings identifier inside the transaction. Media replacement/removal retains old files until the outer default SQLite transaction commits and compensates new files on rollback. A cleanup exception after commit preserves the now-referenced replacement. Nested rollback, stale/forged tenant state and late-step failure have explicit regression tests.

Report vulnerabilities privately as described in the root [`SECURITY.md`](../SECURITY.md). This document is the implementation control catalogue.

## Identity and sessions

Public Fortify registration is disabled at the feature, route, backend and navigation boundaries. Account creation is available only through `/invite/{token}` for a valid email-bound staff invitation; an existing matching account signs in and joins the tenant instead. The bearer token is removed from the URL immediately, acceptance is an atomic compare-and-set from pending to accepted, and replay cannot create duplicate membership. Expired, revoked, malformed, mismatched and otherwise unavailable credentials use localized token-free states without tenant or recipient disclosure. The invitation limiter applies both a per-credential/client budget and an independent per-client budget, so rotating guessed credentials cannot bypass throttling. Fortify owns password login/reset/confirmation and the remaining account flows. Authentication regenerates the session; logout invalidates it and regenerates the CSRF token. Sensitive endpoints use named rate limiters and responses avoid unnecessary account disclosure. Passkey and two-factor code remains feature-gated but both features are disabled by current configuration; dormant credentials stay encrypted/hidden. Demo identities are seeded only outside production.

Security and recovery-code Livewire actions enforce the Fortify feature flag on the server before reading or mutating credentials. Recovery codes additionally require active two-factor authentication. Disabled-feature calls preserve dormant credentials. Tests explicitly enable optional features inside an isolated application to exercise their positive paths; production defaults remain disabled.

`RequireRecentPasswordConfirmation` delegates timeout and response selection to Laravel and aborts with its 423 response when password confirmation has expired. It is both the `password.confirm` route middleware and persistent Livewire middleware, including nested recovery-code requests. This closes Livewire's handling gap for a returned JSON denial while preserving browser redirects and successful recent-confirmation requests.

## Demo authentication boundary

- Demo login requires the explicit `DEMO_LOGIN_ENABLED=true` flag outside production and a normalized request host in `DEMO_LOGIN_HOSTS`; the default allowlist contains only `ruflo.test`. Production and all other hosts return 404 even if the flag is enabled accidentally.
- The demo environment guard has priority before CSRF and the shared demo throttle so hidden requests neither reveal the feature nor consume its rate-limit budget. Global web middleware still keeps CSRF validation before authentication.
- Both named routes are guest-only, use the normal web/CSRF stack and share a 20-requests-per-minute-per-IP limiter. The POST role parameter is restricted to the `SystemRole` enum allowlist.
- Authentication reloads the canonical demo email, revalidates the exact email-role assignment, uses the `web` guard and regenerates the session before redirecting. Missing or mismatched identities fail generically without login.
- The role page is private/non-cacheable and sends `Referrer-Policy: no-referrer` plus `X-Robots-Tag: noindex, nofollow`. Seeded identities receive an unknown random password only on initial creation and preserve its hash on repeated runs; passwords, complete tokens and session identifiers are not rendered, documented or logged.

## Authorization and isolation

Report-cache reads resolve current access before looking up a versioned branch/capability/date/locale key. Per-branch random generations make obsolete in-flight writes unreachable after invalidation, including variants lost from the physical-cleanup registry. Deferred regeneration preserves locale and maximum age. When the cache uses a different database connection, commit and rollback callbacks invalidate any interim generation again. This cache is not used to authorize mutations or supply guest-menu availability decisions; retention and cleanup are documented in [`caching.md`](caching.md).

Every protected route requires authentication, and every resource operation additionally uses a policy or explicit broad gate. Nested route bindings are scoped. Livewire action parameters and public properties are hostile input: the server reloads the resource in its authorized organization/branch/table scope before mutation. Owner, role, permission override and superadmin behavior is defined in [`authorization.md`](authorization.md) and tested positively and negatively.

Order mutations never trust a rendered control, locked property, ticket ID or Livewire payload. Waiter confirmation authorizes the tenant-scoped draft and includes its mandatory department dispatch inside that already-authorized transaction; a send-only actor cannot confirm. Kitchen and bar changes require both the exact accessible department scope and `KitchenTicketPolicy::updateStatus`; waiter service requires `OrderPolicy::markServed`. Every Action reloads the resource under a database lock before validating the centralized transition contract, so cross-tenant substitution, repeated clicks and concurrent confirmation cannot create a second order or ticket set.

Kitchen and bar type scopes are deliberately disjoint at the wrapper boundary: kitchen receives kitchen/dessert/hookah/custom, bar receives bar only. A staff member permitted to use both workspaces must still enter the separate authorized surface; forging a bar ticket identifier through the kitchen component or print route is rejected before mutation or disclosure. Table opening likewise reauthorizes the locked service point inside the Action and accepts only a free/reserved point when no active session can be reused.

Restaurant onboarding exposes only a locked checkpoint identifier and locked selected UI step. Checkpoint creation rejects users who already hold any tenant membership and fails closed for orphaned non-owner system identities, preventing limited staff from minting an owner context. Every Livewire hydration reauthorizes the checkpoint owner plus the active membership, subscription and exact active branch assignment when branch assignments exist; suspended/removed assignments and explicit permission denies never fall back to owner/director shortcuts. Stale snapshots stop returning setup data as soon as access is revoked. The read service scopes each organization, brand, branch, area, service point, QR and menu relation before hydrating it, so a corrupt checkpoint cannot place another tenant's names or QR identity in component state. Every write re-resolves the checkpoint by authenticated user, validates the persisted organization/brand/branch and table/area chain, and authorizes the concrete organization/brand/branch/area/service-point/QR/menu/price/availability operation before writing. Ordered onboarding service points replace browser-submitted ID arrays; the expected count, table type, contiguous positions, database uniqueness and retryable transactions make duplicate/stale submissions converge on one tenant graph. Cross-branch table links and stale cross-tenant branches fail before the first mutation; same-branch survivors are reused during hard-deleted area recovery, and a missing hard-deleted table is reconstructed before QR/menu completion. A soft-deleted linked resource is recoverable only through the explicit checkpoint-resource policy operation when its original owner/parent chain still matches and the subscription remains active.

## Tokens and sensitive values

Invitation and equivalent one-time credentials use cryptographically secure random values, store only SHA-256 digests, include recipient, tenant, role, creator and expiry scope, and are consumed atomically once. Creating, reissuing, revoking and accepting an invitation writes a token-free audit event. Because a digest cannot reconstruct its bearer, “reissue” rotates the credential, invalidates the old link, extends expiry and exposes the replacement only in the current authorized administrator UI state. No invitation email is sent while no delivery integration is configured; the administrator copies the displayed link through a trusted channel. The obsolete `invitations.invite_token` and `invite_code` plaintext columns were removed by a guarded forward migration that refuses to contract the schema if either legacy value exists. Complete tokens, session identifiers, authorization headers, passwords, keys and sensitive request bodies are never logged. Serialized models hide password, authentication, two-factor, remember and token material.

Guest share links follow the same bearer rule without pretending to be account invitations: every request rotates a 64-character token, persists only its SHA-256 digest plus creator/time/30-minute expiry, and exposes plaintext only in the current active guest's Livewire response. Lookup is constrained by the scanned QR restaurant before the digest comparison; the digest and expiry are revalidated while holding the target table-session lock before a join request is inserted. The final guarded forward migration removes the empty compatibility plaintext column and fails closed if an existing installation still contains a value. Expired, rotated, closed and cross-restaurant links fail with generic localized states. Public QR GETs and Livewire entry attempts have separate hashed-key rate limits. The persistent guest cookie remains an opaque 64-character identity credential, is namespaced by the QR digest and hidden from serialization/HTML/logs. Every mutation and isolated polling refresh revalidates the credential against the active guest and the exact session QR service point or an active merged-table link; revoked access clears serialized guest data before rendering.

`TableSessionGuest` and `TableSessionJoinRequest` declare `#[Hidden(['guest_token'])]`, so their credential is excluded by default from Eloquent attribute arrays, model arrays and JSON, including loaded relationships serialized through `TableSession`. Tests reproduce the former model-serialization exposure; this is not evidence of a public endpoint leak. Direct server-side attribute access and the stored credential remain available for cookie lookup and join approval. Hiding affects serialization only: it does not filter SQL columns, redact arbitrary raw attributes or replace authorization and explicit presentation maps. Permanent credential hiding belongs on the model rather than requiring each caller to remember `makeHidden()`.

## Input and output

Server validation covers type, boundary, ownership, enum, nested keys, dates, money and file content. Validation does not replace authorization or database constraints. Blade escapes user data by default; raw HTML/SVG/JSON is allowed only from a named, tested trusted-data boundary. There is no first-party raw SQL, command execution, remote URL fetch or direct model access in Blade.

Shared monetary validation rules reject floats, booleans, malformed decimal strings and integer-cent overflow before invoking a write Action. The reusable `DecimalMoney` rule shares the exact parser used during persistence, so incomplete inputs such as `.50` and `1.` produce localized field errors instead of conversion exceptions.

CSV exports pass every cell through `StreamBranchCsvExportAction::spreadsheetCell`. Formula-leading strings (including full-width markers and leading Unicode whitespace/control characters) receive an apostrophe text prefix; leading ASCII controls are also prefixed. Ordinary text, numeric values and persisted source records are unchanged. `fputcsv` uses explicit `escape: ''` so quotes, delimiters, backslashes and embedded newlines survive a standard CSV parse without creating extra cells. Tests cover all four export types and exact parsed payment values. This is an export-time neutralization boundary, not a universal guarantee after a spreadsheet editor saves/reopens or transforms the file; consumers must keep imported untrusted fields as text. See [OWASP CSV injection](https://community.owasp.org/attacks/CSV_Injection) and the [PHP CSV escape contract](https://www.php.net/manual/en/function.fputcsv.php).

## Files and backup

Uploads use configured disks, MIME/content/size validation and generated names. Dish galleries additionally enforce branch ownership and a combined primary-plus-secondary maximum of eight before storage. Each new gallery path registers rollback compensation immediately after storage. Enclosing rollback removes attempted paths, while exceptions in post-commit callbacks preserve committed images. Promotion swaps stored references without copying. Soft deletion reloads the original parent-scoped dish/category/menu, removes owned gallery rows transactionally and cleans the current primary and secondary files only after the outer commit. Private downloads authorize at request time. Shared image replacements write the new object and persist its path, then delete the old object only after the outer default SQLite transaction commits (including standalone calls, which open their own persistence transaction); rollback compensation removes the new file. A post-commit cleanup failure retains the committed replacement. SQLite backup download and restore are restricted to superadmins behind recent password confirmation, typed confirmation and an audited reason. Restore authorization carries a server-side one-time nonce, and the uploaded SQLite header plus the complete table/column/index/foreign-key/view fingerprint must match the current release before live data can change. Restore runs under a filesystem lock and maintenance mode, creates a private consistent pre-restore snapshot, rolls back automatically on any post-replacement failure, clears cache and remember tokens, and invalidates all sessions. File contents, original names, absolute paths and authorization nonces are not logged.

## Payments and races

Payment creation, correction and session closure acquire an appropriate database serialization boundary, re-read the balance, validate minor-unit invariants and write atomically. Bill, paid and closed table-session states advance only eligible canonical order states and retain actor-bearing status history. Pending draft items or unfinished fulfilment prevent close even when the caller has close permission; the server guard is independent of the prepared UI capability. Duplicate submission is harmless or rejected deterministically. External acquiring and webhooks are not present; adding them requires signature, replay and idempotency controls.

Guest/waiter draft-item creation uses a non-secret locked UUID attempt key and a draft-scoped database unique constraint. The Action returns the existing item for an exact replay and rejects the same key when it belongs to another guest; logging and status-history side effects run only for the newly created row. Quantity is revalidated through the immutable 1–99 value object inside the Action path, independently of Livewire form validation.

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

## Explicit local demo password display — 2026-09-16

The user-authorized local `/login` directory is an exception to the password-free demo presentation below. Both application environment checks must equal `local`, demo mode must be enabled and the request host must be allowlisted. `DEMO_LOGIN_PASSWORD` is an optional local operator value in the ignored `.env`; there is no default credential. It is displayed only for an exact catalogued demo email with its expected account or company role whose current hash verifies. Ordinary accounts, changed demo passwords, production, staging and foreign hosts never disclose it. Hashes, tokens and other authentication material are not projected. Responses are private/no-store/noindex with no-referrer. The existing `/demo-login` route keeps its original password-free and CSRF/throttle/session contract.
