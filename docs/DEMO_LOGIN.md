# Demo login

The demo-login surface is an opt-in operator tool for a dedicated public demo environment. It must use an isolated non-production database populated only with deterministic fictitious data from `DemoRestaurantSeeder`. The seeder and the HTTP middleware independently refuse production.

## Prepare the demo environment

1. Confirm that the application points to an isolated non-production SQLite database. Never seed a production or shared customer database.
2. Seed the complete demo restaurant and its role accounts:

   ```bash
   php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
   ```

3. Set `DEMO_LOGIN_ENABLED=true` only in the dedicated demo environment. The default in `.env.example` is `false`.
4. Rebuild the cached configuration so the flag takes effect:

   ```bash
   php artisan config:cache
   ```

5. Confirm the two named routes and their middleware:

   ```bash
   php artisan route:list --name=demo-login
   ```

The order matters: the account catalogue describes expected identities, but the page enables an identity only after the seeded user exists with that exact system role.

## Runtime behaviour

`GET /demo-login` lists all 12 canonical `SystemRole` identities in catalogue order. A missing user or a user whose assigned role does not match the catalogue remains visibly disabled. No password is rendered.

Each enabled choice submits a normal CSRF-protected `POST /demo-login/{role}`. The server applies the enum allowlist, reloads the canonical email, verifies the exact email-role assignment, authenticates through the `web` guard, regenerates the session, and redirects to the prepared dashboard. Both routes are guest-only and share a limit of 20 requests per minute per IP address.

The page response is private and non-cacheable, sends a no-referrer policy, and opts out of search indexing. The shared seed password remains an operator/testing implementation detail: it must never appear in demo-login HTML, application logs, documentation intended for visitors, or support screenshots. Credentials, complete tokens, and session identifiers must not be logged.

## Safety boundary and shutdown

- Production returns 404 for both routes even if `DEMO_LOGIN_ENABLED=true` was set accidentally.
- Any environment with the flag disabled returns 404 for both routes.
- The environment guard runs before CSRF and the shared demo throttle, so disabled and production probes do not consume the rate-limit budget.
- The normal web middleware priority still keeps CSRF validation before authentication on protected routes.

To disable the surface, set `DEMO_LOGIN_ENABLED=false` in that environment and run `php artisan config:cache` again. Verify afterward with `php artisan route:list --name=demo-login`; the named routes remain registered, but requests are hidden by the environment guard.

Never remove the production refusal, reuse this flow as normal account provisioning, or connect it to a production database. The complete deterministic data contract is documented in [`seeding.md`](seeding.md).
