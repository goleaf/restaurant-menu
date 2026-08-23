# Demo Role Login Design

**Status:** Approved on 2026-08-22

## Problem

The non-production demo seeder creates one account for every closed system role, but the application has no web page for discovering those accounts or entering the demo as a chosen role. Operators currently have to read seeder code or documentation and manually enter shared credentials.

## Goal

Provide a server-rendered `/demo-login` page that lists all 12 system roles and lets a guest enter an existing seeded demo account with one POST action. The feature must support an explicitly enabled public demo environment while remaining impossible to use in production.

## Scope

- List every `SystemRole` in its canonical enum order, including `superadmin`.
- Display each localized role label and deterministic demo email address.
- Show whether the corresponding seeded account is available.
- Authenticate an available account with one button press.
- Keep the page, credentials, and login endpoint unavailable unless demo login is explicitly enabled.
- Preserve the invitation-only production registration contract merged in PR #1.

## Non-goals

- Creating or repairing demo accounts from an HTTP request.
- Exposing the shared demo password in HTML or JavaScript.
- Enabling demo login in production.
- Adding impersonation, signed login URLs, a second authentication guard, or a client-side application.
- Making destructive superadmin capabilities safe for real data. A public demo deployment must remain isolated, fictitious, and resettable.

## Chosen approach

Use a server-owned demo account catalogue and two conventional Blade HTTP endpoints: a GET page and a CSRF-protected POST login action. This keeps credentials out of the browser, reuses the current Laravel session guard, and provides one authoritative role-to-demo-identity map for the seeder, UI, documentation, and tests.

Rejected alternatives:

1. Prefilling or auto-submitting the normal Fortify form would expose the shared password to the browser and couple the demo page to credential authentication.
2. Signed per-role login links would add token lifecycle and replay complexity without improving the explicitly enabled, isolated demo use case.

## Environment and routing contract

Add `DEMO_LOGIN_ENABLED=false` to `.env.example` and expose it only through `config/demo-login.php`. The feature is enabled only when both conditions are true:

1. `config('demo-login.enabled')` is strictly true.
2. `App::environment('production')` is false.

`EnsureDemoLoginIsEnabled` enforces both conditions and returns 404 when either fails. Production remains denied even if the environment variable is accidentally set to true. The middleware is shared by both endpoints so the page and mutation cannot drift.

Routes are grouped by middleware, prefix, and name:

- `GET /demo-login` as `demo-login.index`.
- `POST /demo-login/{role}` as `demo-login.authenticate`.

The group uses `guest`, `demo-login`, and the named `throttle:demo-login` limiter. The role segment uses backed-enum route binding or an equivalent `SystemRole` allowlist. Invalid role values return 404 before application logic. The existing web middleware supplies session state and CSRF validation.

Authenticated users follow the normal `guest` middleware redirect instead of silently switching identities. Switching roles therefore requires an explicit logout first.

## Application structure

| Responsibility | Proposed location |
|---|---|
| Environment flag | `config/demo-login.php`, `.env.example` |
| Canonical role/name/email mapping | `app/Support/DemoLogin/DemoAccountCatalog.php` |
| Fail-closed environment guard | `app/Http/Middleware/EnsureDemoLoginIsEnabled.php` |
| Bounded page-data preparation | `app/Actions/Auth/BuildDemoLoginPageAction.php` |
| Revalidated login operation | `app/Actions/Auth/LoginAsDemoRoleAction.php` |
| GET endpoint | `app/Http/Controllers/Auth/ShowDemoLoginController.php` |
| POST endpoint | `app/Http/Controllers/Auth/LoginAsDemoRoleController.php` |
| Server-rendered interface | `resources/views/auth/demo-login.blade.php` |
| Route and rate limiter wiring | `routes/web.php`, `bootstrap/app.php`, `app/Providers/AppServiceProvider.php` |

`DemoAccountCatalog` is the executable source of truth for all 12 demo identities. It returns typed role, name, and email records without querying the database. `DemoRestaurantSeeder` consumes the same catalogue instead of maintaining a separate identity map. The shared password remains a seed-only implementation detail and is not needed by the web login flow.

Controllers remain thin: the GET controller invokes the page-data Action and returns the view; the POST controller receives the allowlisted role, invokes the login Action, and redirects to the named dashboard route.

## Data flow

### Page request

1. Middleware verifies the explicit flag, rejects production, ensures the caller is a guest, and applies rate limiting.
2. `BuildDemoLoginPageAction` reads the 12 catalogue entries.
3. One bounded Eloquent query selects only required user columns for the 12 known emails and eager-loads only the role identifiers needed to verify each account.
4. The Action returns prepared presentation rows containing role code, localized label, email, and availability. Blade performs no query, authorization, configuration lookup, or collection transformation.
5. The view renders all roles. Missing or role-mismatched accounts remain visible but their login actions are unavailable.

### Login request

1. The same middleware rechecks the environment and guest state; CSRF and the named rate limiter run before the controller.
2. Route binding converts the untrusted role string into a valid `SystemRole`.
3. `LoginAsDemoRoleAction` resolves the expected email from the catalogue and re-queries exactly one user with its assigned system role.
4. Missing, deleted, or role-mismatched users are rejected with a localized safe error. The endpoint never creates, reseeds, or changes an account.
5. On success, the application logs in that exact user through the configured web guard, regenerates the session identifier, and redirects to `dashboard`.

The page query budget is two bounded queries at most: users plus eager-loaded roles. The login operation is also bounded to the selected user and role. There is no query in a loop.

## Interface design

The page uses the existing auth layout and Restaurant Menu design tokens. Its single purpose is role selection.

- A concise heading explains that this is an isolated demo environment.
- A responsive one-column/mobile and two-column/wide list presents all 12 roles.
- Each row shows a localized role name, its `@demo.test` email, availability text, and a full-width submit button labelled “Log in as :role”.
- Status is expressed with text and an icon, never color alone.
- Missing accounts show a localized instruction to run `DemoRestaurantSeeder`; their buttons are disabled.
- The shared password is never rendered.
- Forms use semantic HTML, visible keyboard focus, practical touch targets, stable translated text wrapping, and unique accessible button names.
- No JavaScript or Livewire component is required for the static list and POST actions.

All new visible strings are added with placeholder parity to `lang/en.json`, `lang/lt.json`, and `lang/ru.json`.

## Failure behavior

- Disabled flag: both routes return 404.
- Production environment: both routes return 404 even when the flag is true.
- Invalid role: 404 through the route constraint/binding.
- Missing or mismatched demo account: redirect back with a localized generic error and no authentication.
- Rate limit exceeded: standard localized 429 response; no account state is changed.
- Successful login: session regeneration and redirect to the named dashboard route.

Unexpected exceptions follow the existing reporting pipeline without logging passwords, session identifiers, or unnecessary personal data.

## Security considerations

The POST action intentionally bypasses password verification, so the environment middleware is a security boundary and receives positive and negative tests. The production-denial condition is hard-coded independently of the configurable flag. The route remains CSRF-protected, guest-only, allowlisted by role, and rate-limited by client IP.

The catalogue contains only fictitious `@demo.test` identities. A deployment that enables this feature must use a dedicated non-production database and local files, may expose full superadmin capability, and must be safe to reset. No real organization, guest, order, payment, audit, or backup data may be present.

## Test strategy

Add focused Pest coverage for:

- both routes returning 404 when the flag is disabled;
- both routes returning 404 in production even when enabled;
- the enabled page rendering all 12 canonical roles in deterministic order;
- absence of the shared password from the response;
- missing accounts remaining visible but unavailable;
- successful one-click login for every `SystemRole` through a dataset;
- rejection of invalid role values, missing users, and role-mismatched users;
- guest-only behavior, CSRF middleware registration, rate-limit middleware, and throttling;
- session regeneration and deterministic dashboard redirect;
- catalogue/seeder parity so every system role has exactly one demo identity;
- EN/LT/RU key and placeholder parity;
- Blade architecture rules and absence of queries or configuration access in the view.

Run the focused demo-login and seeder tests first, then the repository’s applicable formatting, static-analysis, translation, full Pest, production-build, route/cache, and browser checks. Browser verification covers all 12 actions, keyboard traversal, 320-pixel reflow, translated text expansion, console output, and disabled/production route behavior.

## Documentation impact

Update `docs/DEMO_LOGIN.md`, `docs/security.md`, `docs/seeding.md`, `docs/requirements.md`, `docs/compliance-matrix.md`, and `CHANGELOG.md` to record the explicit enablement flag, production denial, one-click behavior, all-role catalogue, query boundary, and verification evidence. Do not present the feature as a production authentication mechanism.
