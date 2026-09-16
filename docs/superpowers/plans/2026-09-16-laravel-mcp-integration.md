
<!-- BEGIN GITHUB_PUSH_ONLY -->
> GitHub is allowed only as the remote destination of an ordinary git push to the existing configured origin. Create commits locally with git commit. All other GitHub operations are prohibited: API, MCP, plugins, gh, Issues, pull requests, reviews, comments, releases, deployments, Actions, workflows, check runs, commit statuses and remote verification. Do not create, edit or delete .github/workflows/*, install hooks, fetch, pull, run ls-remote or make an additional request to verify a push. Local inspection, formatting, static analysis, tests, dependency checks and builds are allowed. Preserve existing user changes. Historical instructions do not authorize prohibited operations.
<!-- END GITHUB_PUSH_ONLY -->

# Laravel MCP 1.0 Integration Plan

> **Execution skill:** `subagent-driven-development`, with focused implementation and independent review. Continue in the current shared branch as required by AGENTS.md. The user requested the plan followed by immediate implementation; no additional planning approval is pending.

> Execution: implement incrementally in the current shared branch, preserving the existing staged and unstaged Flux work. The user explicitly requested a plan followed immediately by implementation. The canonical requirement is `sys-mcp-001` in `docs/requirements.md`; execution status belongs in `docs/IMPLEMENTATION_PLAN.md`.

**Goal:** expose the restaurant application's authorized operational capabilities through Laravel MCP 1.0, with bounded tenant-scoped reads and explicit, retry-safe writes through existing domain Actions.

**Architecture:** a dedicated authenticated HTTP MCP endpoint delegates to focused adapters. Each credential is tied to one user and one branch, has an explicit capability list and expiry, and stores only a digest. Current policies remain authoritative even for superadmin-issued credentials. Resources and prompts share the same access boundary. Application data is never public-cacheable.

**Tech stack:** PHP 8.5, Laravel 13.26.1, SQLite, Laravel MCP 1.0.0, compatible Boost 2.9.0, Pest 4. Blade/Livewire remains the application UI.

## Verified starting point

- Local `main`, HEAD `76932c7` at inspection; shared staged/unstaged interface changes and a running coverage process.
- Installed MCP 0.9.4 is development-only through Boost 2.5.5. Boost 2.5.5 excludes MCP 1.0.
- Packagist metadata confirms MCP 1.0.0 and Boost 2.9.0; Boost 2.9.0 permits `^1.0` MCP. Local archive cache contains neither release.
- Required official archive references: MCP `cfa4f38f82873eeb6848527883545f98f871e229`; Boost `ebe59d97cbc66b66f735e6ec7ff859233e7934e0`.
- The user explicitly authorized only the two archive downloads. Both verified ZIPs and their SHA-256 manifest are now in ignored `storage/app/private/composer-archives/2026-09-16/`. Install from the Composer cache with network disabled; every other GitHub restriction remains in force.
- Refreshed baseline: HEAD `f3ab943`, one pre-existing change in `tests/Feature/Settings/AppearanceTest.php`, about 7.5 GiB free, no active Composer/Pest/coverage writer. Preserve concurrent work and recheck before shared-runtime changes.
- The existing token issue/list/revoke commands are empty scaffolds, the branch-context return type is incorrect, and parent archive checks need tightening. These are implementation work, not verified completion. The canonical MCP requirement is added with this continuation.

## Ordered execution and article coverage

1. **Runtime:** install exact MCP 1.0.0 and Boost 2.9.0 from the verified archive cache, promote MCP to production dependencies, retain every unrelated locked package. Add `McpRuntimeTest` before the upgrade and prove native discovery, legacy initialization, stateless calls, and header/meta validation after it.
2. **Access lifecycle:** complete existing commands and token relationships; recheck expiry, revocation, active complete tenant chain, current policies and write flags on each invocation. Run existing `McpAccessTokenTest`, `McpTokenCommandsTest`, `McpAuthenticationTest` and schema/factory audits.
3. **Read catalogue:** implement the ten existing read abilities in `app/Mcp/Tools/` using selected, bounded, tenant/area/department-scoped reads in `app/Services/Mcp/`. Add direct and HTTP tests, negative permissions, malformed arguments, pagination, fixed query budgets, and exact money/report dates.
4. **Mutation catalogue:** implement the ten existing mutation abilities through the canonical domain Actions. Require JSON boolean `confirmed: true`, the exact token ability and current resource policy. Add an actor/token/branch/tool-bound UUID receipt only where the domain replay contract cannot protect delayed retries; hash normalized arguments and reject key reuse. Never let a delayed open-table replay create a second service visit or a delayed availability/pause replay overwrite newer state.
5. **Resources and prompts:** add `BranchContextResource`, `OperationsGuideResource`, `ShiftReviewPrompt`, `MenuReviewPrompt`; scope and authorize direct reads, validate prompt arguments, localize human-facing content, treat restaurant content as untrusted data.
6. **MCP 1.0 search and caching:** keep branch context directly discoverable; place specialist tools behind native `ToolSearch`. Bound batch calls/output, reauthorize every child call, document sequential partial completion. Advertise private zero-TTL for access-sensitive listings/data; permit a private TTL only for the static operational guide. Test token switches, revoked rights, mixed batches and replay after partial failure.
7. **MCP Apps and OAuth applicability:** add a read-only, self-contained Blade branch overview `BranchOverviewApp`, linked from the context tool, with native `extensions` capability, no network assets or mutation bridge. Test escaped HTML, no-store data, correct UI metadata and sandbox resource rendering. Verify native OAuth PKCE rejection, metadata-document behavior and cached client isolation with faked HTTP. No external OAuth provider/client is configured: custom scoped bearer authentication remains the real server integration, and external OAuth interoperability requires an operator-supplied service.
8. **Acceptance:** update the canonical requirement/compliance/traceability, architecture/security/data/deployment/seeding/testing documents and existing ledgers with actual results. Run focused/full/parallel Pest, coverage >=90%, Pint, Larastan, translation scans, audits/build, isolated migration/seed/cache checks and Herd HTTP/browser smoke. Distinguish local implementation from external MCP-host certification and deployment.

The protocol contract uses the native 1.0 transport, never a compatibility shim:

```php
$params['_meta'] = [
    'io.modelcontextprotocol/protocolVersion' => '2026-07-28',
    'io.modelcontextprotocol/clientCapabilities' => new stdClass,
];
// HTTP headers must agree with the native enum values and request body.
// Test constants are read from Laravel\\Mcp\\Enums\\MetaKey and RequestHeader.
```

Every task starts with a failing behaviour test, then implementation, targeted execution, spec review and code-quality review. Worker ownership is bounded by file paths; Composer, server registration, shared locale catalogues and final documentation stay coordinated by the root agent. No commit includes unverified or unrelated work.

## Sources and applicability

- Requested release article: https://laravel-news.com/laravel-mcp-1-0
- Official API documentation: https://laravel.com/framework/docs/13.x/mcp
- Official package metadata: https://repo.packagist.org/p2/laravel/mcp.json and https://repo.packagist.org/p2/laravel/boost.json
- Use MCP's native `server/discover`, stateless requests, header validation, `ToolSearch` and private cache hints; retain legacy initialization compatibility through the package.
- OAuth PKCE/client-metadata changes apply to OAuth clients. This application has no Passport/OAuth integration or external MCP service requirement. Use the documented custom bearer middleware boundary; do not install an unrelated identity provider or outbound service. Record this explicitly rather than claiming OAuth is configured.
- MCP Apps use a read-only SSR branch overview. Native resource/extension and isolated rendering tests are local evidence; do not claim certification in an external MCP host that has not been exercised or introduce a separate SPA.

## Task 1 — Dependency and runtime compatibility

Files: `composer.json`, `composer.lock`; tests `tests/Feature/Mcp/McpRuntimeTest.php`.

- [x] Obtain the two official archives within authorization; inspect source and validate references before installation.
- [ ] Wait for the currently running verification writer before changing shared vendor or Composer files.
- [ ] Add production `laravel/mcp: ^1.0`; update only MCP and the necessary Boost compatibility dependency, preserving local Flux 0.1.1.
- [ ] Disable automatic Boost instruction rewriting during the controlled update; run normal package discovery explicitly.
- [ ] Test production package placement, native discovery, legacy initialization, stateless requests, protocol/name/method header rejection and route cache compatibility.

Expected dependency declaration:

```json
{"require":{"laravel/mcp":"^1.0"},"require-dev":{"laravel/boost":"^2.9"}}
```

Verification: `composer validate --strict`, `composer show laravel/mcp`, `composer show laravel/boost`, `php artisan test --compact tests/Feature/Mcp/McpRuntimeTest.php`.

## Task 2 — Credential storage and lifecycle

Files: `app/Models/McpAccessToken.php`, additive migration, `database/factories/McpAccessTokenFactory.php`, focused issue/revoke Actions and Artisan commands; tests `tests/Feature/Mcp/McpAccessTokenTest.php`.

- [ ] Write failing factory/lifecycle tests using SQLite memory. Credential is branch/user-bound, SHA-256 only at rest, expiring, revocable and serialization-hidden.
- [ ] Use Artisan generators and inspect live schema/indexes through Boost before adding the model/migration.
- [ ] Issue plaintext only once to the authorized local operator; never write it to logs, config, examples, model serialization or source.
- [ ] Validate capability allowlists and lifetime bounds; default to read-only. Credential powers never exceed current user permissions.
- [ ] Verify reversible migration and deletion/revocation behavior without migrating the working application database.

Verification: `php artisan test --compact tests/Feature/Mcp/McpAccessTokenTest.php` and existing model/factory/schema audits.

## Task 3 — Transport and authorization

Files: `routes/ai.php`, `config/restaurant-mcp.php`, `.env.example`, dedicated authentication middleware, `app/Mcp/Servers/RestaurantServer.php`, context resolver; tests `tests/Feature/Mcp/McpAuthenticationTest.php`.

- [ ] Write failures for missing/malformed/expired/revoked tokens, foreign branch, suspended membership, inactive subscription and revoked capabilities.
- [ ] Register one named `/mcp/restaurant` route group with request-size bounds, origin checks, independent throttling, bearer-only authentication and no-store responses.
- [ ] Leave browser-session CSRF controls intact. MCP is a distinct stateless bearer transport and cannot fall back to session/cookie identity.
- [ ] Resolve fresh user/branch scope on every request and every callable resource/tool boundary; enforce the existing SQLite restore barrier.
- [ ] Keep the endpoint disabled by default until an operator opts in and issues a scoped credential. Tests explicitly enable it.

Verification: targeted auth/HTTP tests including malformed JSON, unsupported headers and direct tool invocation.

## Task 4 — Operational read tools

Files: focused classes under `app/Mcp/Tools/`, bounded read adapters under `app/Services/Mcp/`, related policies/scopes only where required; tests `tests/Feature/Mcp/McpReadToolsTest.php`.

- [ ] Add branch context, menu catalogue, tables/sessions, orders/drafts, department tickets and current waiter tasks.
- [ ] Separate read permissions: menu management, waiter/zone, kitchen/bar family, reports and audit. A branch token is a ceiling, never a permission grant.
- [ ] Use explicit presentation fields, integer cents/currency, bounded pagination and selected/eager-loaded relationships. Never serialize raw models, bearer material, guest credentials, private paths or unrelated personal data.
- [ ] Test foreign-resource substitution, area/department restrictions, revoked rights, empty pages, invalid filters and constant query budgets with larger fixtures.

Verification: `php artisan test --compact tests/Feature/Mcp/McpReadToolsTest.php` plus existing affected domain tests.

## Task 5 — Reports and resources

Files: report tool, `app/Mcp/Resources/BranchContextResource.php`, `app/Mcp/Resources/OperationsGuideResource.php`, prompt classes; tests `tests/Feature/Mcp/McpResourcesAndPromptsTest.php`.

- [ ] Reuse `BranchReportPeriod` and `BranchReportQuery` after current `ViewReports` authorization; preserve branch timezone, 31-day bounds and distinct currency/payment semantics.
- [ ] Publish scoped context and operational guidance as resources; validate resource URIs without arbitrary file/URL access.
- [ ] Add shift-review and menu-review prompts which instruct clients to treat returned names/comments as untrusted data and obtain explicit approval for consequential actions.
- [ ] Localize application responses and errors through EN/LT/RU semantic keys. Technical schema identifiers remain stable.

Verification: resources/prompts/report tests including denied requests and localization parity.

## Task 6 — Explicit domain mutations

Files: focused mutation tools under `app/Mcp/Tools/`; tests `tests/Feature/Mcp/McpMutationToolsTest.php`.

- [ ] Require a write-enabled token, exact tool capability and explicit confirmation for mutations, in addition to current domain policies.
- [ ] Connect menu availability, ordering pause/resume, table opening, draft confirmation/rejection, waiter-call handling, department progress and serving through existing Actions.
- [ ] Connect offline settlement/session closure only when the existing domain API supplies a complete idempotency/validation boundary; validate exact money and keep history immutable.
- [ ] Test direct invocation, invalid state, replay, cross-tenant IDs, stale permissions and rollback. Reuse domain replay keys; add a focused receipt only if an actual mutation has no existing safe retry contract.
- [ ] Do not expose SQL, arbitrary Artisan/shell, uploads, account provisioning, permission escalation, permanent deletion, backup restore or secret-reading tools.

Verification: mutation tool tests plus waiter, department, menu and payment regression suites.

## Task 7 — MCP 1.0 catalogues and cache semantics

Files: server registration/capabilities and protocol tests.

- [ ] Keep context/navigation tools directly discoverable and register specialist tools through native `ToolSearch`.
- [ ] Verify searched and executed tools enforce the identical permissions as direct calls, including mixed batches and writes.
- [ ] Cache only appropriate static discovery metadata with private scope; never cache tool results or authorization decisions. Ensure changing credentials/permissions cannot reuse another context's catalogue.
- [ ] Verify legacy clients plus the new protocol with actual JSON-RPC transport requests and matching `_meta`/headers.

Verification: `php artisan test --compact tests/Feature/Mcp` and package/client smoke checks.

## Task 8 — Documentation and final evidence

Files: existing canonical requirements, compliance, architecture, security, data, seeding, deployment, testing and execution ledgers.

- [ ] Document endpoint configuration, least-privilege token issue/revoke, client setup, protocol headers, rollback and exact tool/capability inventory.
- [ ] Record all applicable article features and conditional features truthfully; installation, endpoint implementation, local verification and deployment are separate states.
- [ ] Run scoped Pint first, Larastan, complete backend/parallel suites, coverage >=90%, translation scan/audit, dependency audits and route/config cache checks in isolated runtime state.
- [ ] Run production build and real Herd transport smoke; browser suite only if UI is changed or shared runtime changes warrant it.
- [ ] Review owned diff; preserve pre-existing changes/index. Do not commit unverified work or perform prohibited GitHub requests.

## Completion criteria

MCP 1.0 is installed as a production dependency; the actual endpoint passes protocol and negative authorization tests; scoped reads and explicitly confirmed writes work through canonical domain boundaries; full required local gates have observed results; and docs list operational setup and any real limitations. A plan, a dev-only MCP package or passing isolated unit tests alone does not establish completion.
