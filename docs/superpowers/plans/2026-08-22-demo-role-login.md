# Demo Role Login Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an explicitly enabled, production-denied `/demo-login` page that lists all 12 system roles and authenticates an existing seeded demo account with one CSRF-protected POST.

**Architecture:** A typed server-side catalogue owns the deterministic role/name/email mapping and is reused by the demo seeder, bounded page query, login Action, and tests. A fail-closed middleware guards GET and POST independently of the feature flag; thin controllers render prepared Blade data or invoke the login Action, while the configured web guard performs the session transition.

**Tech Stack:** PHP 8.5, Laravel 13, Eloquent/SQLite, Blade SSR, Flux UI Free 2, Tailwind CSS 4, Pest 4, EN/LT/RU JSON translations.

---

## Execution contract

- Work only in `/Users/andrejprus/Herd/restaurant-menu-demo-role-login` on `feature/demo-role-login`.
- Preserve the separate dirty worktree at `/Users/andrejprus/Herd/restaurant-menu`; do not reset, restore, stash, clean, stage, or commit anything there.
- Start each behavior change with the listed failing Pest test, observe the expected failure, implement the smallest passing code, then refactor.
- Stage only the exact paths listed for each commit. Never use `git add .`, `git add -A`, or `git add --all`.
- Run Vite and Pest sequentially because both may touch generated application state.
- Do not start a development server; Laravel Herd serves the project.

### Task 0: Prepare the isolated worktree runtime

**Files:**

- Local ignored runtime only: `.env`, `vendor/`, `node_modules/`, `database/database.sqlite`, `public/build/`

- [ ] **Step 1: Confirm the isolated branch and toolchain before installation**

~~~bash
pwd
git status --short --branch
php -v
composer --version
node --version
npm --version
~~~

Expected: path is `/Users/andrejprus/Herd/restaurant-menu-demo-role-login`, branch is `feature/demo-role-login`, PHP is `>=8.5.0 <8.6.0`, Node is `>=22.12.0`, and the only initial source change is this plan if it has not yet been committed.

- [ ] **Step 2: Use the repository setup script**

~~~bash
composer run setup --no-interaction
~~~

Expected: locked Composer dependencies install, the repository script creates the ignored local `.env` when absent, generates `APP_KEY`, runs forward migrations against the local SQLite database, installs the existing npm lock, and completes the production Vite build. Do not reuse the dirty main worktree's `vendor`, `node_modules`, `.env`, or database.

- [ ] **Step 3: Confirm setup did not create tracked changes**

~~~bash
git status --short
php artisan about --only=environment
~~~

Expected: no new tracked source changes; Laravel boots in a non-production local environment. Stop if setup changed a tracked dependency manifest or lock file unexpectedly.

## File map

- Create `app/Support/DemoLogin/DemoAccountCatalog.php`: canonical typed map for all 12 demo identities.
- Modify `database/seeders/DemoRestaurantSeeder.php`: consume the catalogue without changing seeded users or assignments.
- Create `config/demo-login.php` and modify `.env.example`: explicit opt-in flag.
- Create `app/Http/Middleware/EnsureDemoLoginIsEnabled.php`: deny disabled and production requests with 404.
- Create `app/Actions/Auth/BuildDemoLoginPageAction.php`: load account availability in two bounded Eloquent queries.
- Create `app/Actions/Auth/LoginAsDemoRoleAction.php`: revalidate the selected account and perform guarded session login.
- Create `app/Http/Controllers/Auth/ShowDemoLoginController.php`: return prepared Blade output with no-store/noindex headers.
- Create `app/Http/Controllers/Auth/LoginAsDemoRoleController.php`: invoke one-click login and return localized success/failure responses.
- Modify `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, and `routes/web.php`: middleware alias, named limiter, grouped named endpoints.
- Modify `resources/views/layouts/auth/simple.blade.php`: opt-in wide container without changing existing auth pages.
- Create `resources/views/auth/demo-login.blade.php`: semantic all-role list and POST forms.
- Modify `lang/en.json`, `lang/lt.json`, and `lang/ru.json`: complete localized copy with placeholder parity.
- Create `tests/Unit/DemoAccountCatalogTest.php` and `tests/Feature/DemoLoginTest.php`; modify `tests/Feature/DemoRestaurantSeederTest.php` and `tests/Feature/RouteProtectionAuditTest.php`.
- Update `docs/DEMO_LOGIN.md`, `docs/security.md`, `docs/seeding.md`, `docs/requirements.md`, `docs/compliance-matrix.md`, `docs/testing.md`, and `CHANGELOG.md`.

### Task 1: Establish the canonical demo account catalogue

**Files:**

- Create: `app/Support/DemoLogin/DemoAccountCatalog.php`
- Create: `tests/Unit/DemoAccountCatalogTest.php`

- [ ] **Step 1: Write the failing catalogue test**

Create `tests/Unit/DemoAccountCatalogTest.php`:

~~~php
<?php

declare(strict_types=1);

use App\Enums\SystemRole;
use App\Support\DemoLogin\DemoAccountCatalog;

test('demo account catalogue covers every system role in canonical order', function (): void {
    $accounts = array_map(
        static fn (array $account): array => [
            'role' => $account['role']->value,
            'name' => $account['name'],
            'email' => $account['email'],
        ],
        DemoAccountCatalog::all(),
    );

    expect($accounts)->toBe([
        ['role' => 'superadmin', 'name' => 'Demo Superadmin', 'email' => 'superadmin@demo.test'],
        ['role' => 'owner', 'name' => 'Demo Owner', 'email' => 'owner@demo.test'],
        ['role' => 'director', 'name' => 'Demo Director', 'email' => 'director@demo.test'],
        ['role' => 'restaurant_admin', 'name' => 'Demo Restaurant Admin', 'email' => 'admin@demo.test'],
        ['role' => 'shift_manager', 'name' => 'Demo Shift Manager', 'email' => 'manager@demo.test'],
        ['role' => 'waiter', 'name' => 'Demo Waiter', 'email' => 'waiter@demo.test'],
        ['role' => 'head_chef', 'name' => 'Demo Head Chef', 'email' => 'chef@demo.test'],
        ['role' => 'cook', 'name' => 'Demo Cook', 'email' => 'cook@demo.test'],
        ['role' => 'bartender', 'name' => 'Demo Bartender', 'email' => 'bartender@demo.test'],
        ['role' => 'cashier', 'name' => 'Demo Cashier', 'email' => 'cashier@demo.test'],
        ['role' => 'accountant', 'name' => 'Demo Accountant', 'email' => 'accountant@demo.test'],
        ['role' => 'marketer', 'name' => 'Demo Marketer', 'email' => 'marketer@demo.test'],
    ])->and(array_column($accounts, 'role'))->toBe(SystemRole::values());
});

test('catalogue resolves an exact identity by role', function (): void {
    expect(DemoAccountCatalog::forRole(SystemRole::HeadChef))->toBe([
        'role' => SystemRole::HeadChef,
        'name' => 'Demo Head Chef',
        'email' => 'chef@demo.test',
    ]);
});
~~~

- [ ] **Step 2: Run the test and observe RED**

Run:

~~~bash
php artisan test --compact tests/Unit/DemoAccountCatalogTest.php
~~~

Expected: FAIL because `App\Support\DemoLogin\DemoAccountCatalog` does not exist.

- [ ] **Step 3: Implement the complete catalogue**

Create `app/Support/DemoLogin/DemoAccountCatalog.php`:

~~~php
<?php

declare(strict_types=1);

namespace App\Support\DemoLogin;

use App\Enums\SystemRole;

final class DemoAccountCatalog
{
    /**
     * @var array<string, array{name: string, email: string}>
     */
    private const IDENTITIES = [
        'superadmin' => ['name' => 'Demo Superadmin', 'email' => 'superadmin@demo.test'],
        'owner' => ['name' => 'Demo Owner', 'email' => 'owner@demo.test'],
        'director' => ['name' => 'Demo Director', 'email' => 'director@demo.test'],
        'restaurant_admin' => ['name' => 'Demo Restaurant Admin', 'email' => 'admin@demo.test'],
        'shift_manager' => ['name' => 'Demo Shift Manager', 'email' => 'manager@demo.test'],
        'waiter' => ['name' => 'Demo Waiter', 'email' => 'waiter@demo.test'],
        'head_chef' => ['name' => 'Demo Head Chef', 'email' => 'chef@demo.test'],
        'cook' => ['name' => 'Demo Cook', 'email' => 'cook@demo.test'],
        'bartender' => ['name' => 'Demo Bartender', 'email' => 'bartender@demo.test'],
        'cashier' => ['name' => 'Demo Cashier', 'email' => 'cashier@demo.test'],
        'accountant' => ['name' => 'Demo Accountant', 'email' => 'accountant@demo.test'],
        'marketer' => ['name' => 'Demo Marketer', 'email' => 'marketer@demo.test'],
    ];

    /**
     * @return list<array{role: SystemRole, name: string, email: string}>
     */
    public static function all(): array
    {
        return array_map(
            static fn (SystemRole $role): array => self::forRole($role),
            SystemRole::cases(),
        );
    }

    /**
     * @return array{role: SystemRole, name: string, email: string}
     */
    public static function forRole(SystemRole $role): array
    {
        $identity = self::IDENTITIES[$role->value];

        return [
            'role' => $role,
            'name' => $identity['name'],
            'email' => $identity['email'],
        ];
    }
}
~~~

- [ ] **Step 4: Run GREEN and syntax checks**

~~~bash
php artisan test --compact tests/Unit/DemoAccountCatalogTest.php
php -l app/Support/DemoLogin/DemoAccountCatalog.php
~~~

Expected: 2 tests pass; PHP reports no syntax errors.

- [ ] **Step 5: Commit the catalogue**

~~~bash
git add -- app/Support/DemoLogin/DemoAccountCatalog.php tests/Unit/DemoAccountCatalogTest.php
git diff --cached --check
git commit -m "feat(auth): add demo account catalogue"
~~~

### Task 2: Make the demo seeder consume the catalogue

**Files:**

- Modify: `database/seeders/DemoRestaurantSeeder.php:7-40,69-74,251-299`
- Test: `tests/Feature/DemoRestaurantSeederTest.php`

- [ ] **Step 1: Record the existing characterization baseline**

~~~bash
php artisan test --compact tests/Feature/DemoRestaurantSeederTest.php
~~~

Expected: the existing seeder tests pass before the behavior-preserving refactor.

- [ ] **Step 2: Replace duplicated identity arguments with catalogue lookups**

Add:

~~~php
use App\Support\DemoLogin\DemoAccountCatalog;
~~~

Replace the initial calls in `run()`:

~~~php
$superadmin = $this->demoUser(SystemRole::Superadmin);
$this->syncPermissions($superadmin, []);

$owner = $this->demoUser(SystemRole::Owner);
~~~

Replace `demoUser()` with:

~~~php
private function demoUser(SystemRole $role): User
{
    $identity = DemoAccountCatalog::forRole($role);
    $user = User::query()
        ->select(['id', 'name', 'email', 'locale', 'email_verified_at', 'password'])
        ->where('email', $identity['email'])
        ->first();

    if (! $user instanceof User) {
        $user = User::factory()
            ->demoIdentity($identity['name'], $identity['email'])
            ->create();
    } else {
        $attributes = User::factory()
            ->demoIdentity($identity['name'], $identity['email'])
            ->make()
            ->getAttributes();

        if (Hash::check(UserFactory::DEMO_PASSWORD, (string) $user->password)) {
            unset($attributes['password']);
        }

        $user->forceFill($attributes)->save();
    }

    $roleModel = $this->role($role);
    $user->roles()->sync([$roleModel->id]);

    return $user->refresh();
}
~~~

Replace the staff calls:

~~~php
$director = $this->demoUser(SystemRole::Director);
$admin = $this->demoUser(SystemRole::RestaurantAdmin);
$manager = $this->demoUser(SystemRole::ShiftManager);
$waiter = $this->demoUser(SystemRole::Waiter);
$headChef = $this->demoUser(SystemRole::HeadChef);
$cook = $this->demoUser(SystemRole::Cook);
$bartender = $this->demoUser(SystemRole::Bartender);
$cashier = $this->demoUser(SystemRole::Cashier);
$accountant = $this->demoUser(SystemRole::Accountant);
$marketer = $this->demoUser(SystemRole::Marketer);
~~~

- [ ] **Step 3: Add an independent catalogue/seeder parity assertion**

Import `DemoAccountCatalog` and add to `tests/Feature/DemoRestaurantSeederTest.php`:

~~~php
test('demo account catalogue matches the seeded identity contract', function (): void {
    $catalogue = collect(DemoAccountCatalog::all())
        ->mapWithKeys(fn (array $account): array => [
            $account['email'] => [
                'name' => $account['name'],
                'role' => $account['role'],
            ],
        ])
        ->all();

    expect($catalogue)->toBe(demoRestaurantUsers());
});
~~~

- [ ] **Step 4: Verify the refactor**

~~~bash
php artisan test --compact tests/Unit/DemoAccountCatalogTest.php tests/Feature/DemoRestaurantSeederTest.php
vendor/bin/pint --dirty --format agent
~~~

Expected: catalogue and complete seeder tests pass; Pint exits 0.

- [ ] **Step 5: Commit the seeder integration**

~~~bash
git add -- database/seeders/DemoRestaurantSeeder.php tests/Feature/DemoRestaurantSeederTest.php
git diff --cached --check
git commit -m "refactor(seed): centralize demo account identities"
~~~

### Task 3: Add the explicit non-production environment guard

**Files:**

- Create: `config/demo-login.php`
- Create: `app/Http/Middleware/EnsureDemoLoginIsEnabled.php`
- Create: `tests/Feature/DemoLoginTest.php`
- Modify: `.env.example`

- [ ] **Step 1: Write failing middleware behavior tests**

Create `tests/Feature/DemoLoginTest.php`:

~~~php
<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureDemoLoginIsEnabled;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', EnsureDemoLoginIsEnabled::class])
        ->get('/__demo-login-probe', fn () => response('demo-enabled'));
});

test('demo login middleware hides the feature when disabled', function (): void {
    config()->set('demo-login.enabled', false);

    $this->get('/__demo-login-probe')->assertNotFound();
});

test('demo login middleware denies production even when explicitly enabled', function (): void {
    config()->set('demo-login.enabled', true);
    $this->app->detectEnvironment(fn (): string => 'production');

    $this->get('/__demo-login-probe')->assertNotFound();
});

test('demo login middleware allows an explicitly enabled non-production environment', function (): void {
    config()->set('demo-login.enabled', true);

    $this->get('/__demo-login-probe')
        ->assertOk()
        ->assertSeeText('demo-enabled');
});
~~~

- [ ] **Step 2: Run RED**

~~~bash
php artisan test --compact tests/Feature/DemoLoginTest.php
~~~

Expected: FAIL because the middleware and configuration do not exist.

- [ ] **Step 3: Add the config-only environment flag**

Create `config/demo-login.php`:

~~~php
<?php

declare(strict_types=1);

return [
    'enabled' => env('DEMO_LOGIN_ENABLED', false),
];
~~~

Add after `APP_ENV=local` in `.env.example`:

~~~dotenv
DEMO_LOGIN_ENABLED=false
~~~

- [ ] **Step 4: Implement the production hard-deny middleware**

Create `app/Http/Middleware/EnsureDemoLoginIsEnabled.php`:

~~~php
<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDemoLoginIsEnabled
{
    public function __construct(private readonly Application $application) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->application->isProduction() || config('demo-login.enabled') !== true) {
            abort(404);
        }

        return $next($request);
    }
}
~~~

- [ ] **Step 5: Run GREEN**

~~~bash
php artisan test --compact tests/Feature/DemoLoginTest.php
vendor/bin/pint --dirty --format agent
~~~

Expected: 3 middleware tests pass; Pint exits 0.

- [ ] **Step 6: Commit the environment boundary**

~~~bash
git add -- .env.example config/demo-login.php app/Http/Middleware/EnsureDemoLoginIsEnabled.php tests/Feature/DemoLoginTest.php
git diff --cached --check
git commit -m "feat(auth): guard demo login by environment"
~~~

### Task 4: Build the bounded role-availability page data

**Files:**

- Create: `app/Actions/Auth/BuildDemoLoginPageAction.php`
- Modify: `tests/Feature/DemoLoginTest.php`

- [ ] **Step 1: Write the failing availability and query-budget test**

Append the imports and test below to `tests/Feature/DemoLoginTest.php`. The helper deliberately creates only one matching demo account so missing roles can be verified without running the graph-heavy demo seeder.

~~~php
use App\Actions\Auth\BuildDemoLoginPageAction;
use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Database\Seeders\SystemRolesSeeder;

test('page data lists every role in canonical order with two bounded queries', function (): void {
    createDemoLoginAccount(SystemRole::Waiter);

    $accounts = [];
    $queryCount = countDatabaseQueries(function () use (&$accounts): void {
        $accounts = app(BuildDemoLoginPageAction::class)->handle();
    });

    expect($queryCount)->toBe(2)
        ->and(array_column($accounts, 'role'))->toBe(SystemRole::values())
        ->and(array_column($accounts, 'available'))->toBe([
            false, false, false, false, false, true,
            false, false, false, false, false, false,
        ]);
});

function createDemoLoginAccount(
    SystemRole $identityRole,
    ?SystemRole $assignedRole = null,
): User {
    test()->seed(SystemRolesSeeder::class);

    $identity = DemoAccountCatalog::forRole($identityRole);
    $user = User::factory()->demoIdentity($identity['name'], $identity['email'])->create();
    $role = Role::query()
        ->select(['id', 'code'])
        ->where('code', ($assignedRole ?? $identityRole)->value)
        ->firstOrFail();

    $user->roles()->sync([$role->id]);

    return $user;
}
~~~

- [ ] **Step 2: Run RED**

~~~bash
php artisan test --compact tests/Feature/DemoLoginTest.php
~~~

Expected: the new test fails because `BuildDemoLoginPageAction` does not exist.

- [ ] **Step 3: Implement the two-query Action**

Create `app/Actions/Auth/BuildDemoLoginPageAction.php`:

~~~php
<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;

final class BuildDemoLoginPageAction
{
    /**
     * @return list<array{role: string, label: string, email: string, available: bool}>
     */
    public function handle(): array
    {
        $catalogue = DemoAccountCatalog::all();
        $usersByEmail = User::query()
            ->select(['id', 'email'])
            ->with('roles:id,code')
            ->whereIn('email', array_column($catalogue, 'email'))
            ->get()
            ->keyBy('email');

        return array_map(
            static function (array $account) use ($usersByEmail): array {
                $user = $usersByEmail->get($account['email']);

                return [
                    'role' => $account['role']->value,
                    'label' => $account['role']->localizedLabel(),
                    'email' => $account['email'],
                    'available' => $user instanceof User
                        && $user->hasSystemRole($account['role']),
                ];
            },
            $catalogue,
        );
    }
}
~~~

- [ ] **Step 4: Verify the query contract**

~~~bash
php artisan test --compact tests/Feature/DemoLoginTest.php
vendor/bin/pint --dirty --format agent
~~~

Expected: all current demo-login tests pass and the availability Action remains exactly two queries regardless of the 12 displayed roles.

- [ ] **Step 5: Commit the page-data Action**

~~~bash
git add -- app/Actions/Auth/BuildDemoLoginPageAction.php tests/Feature/DemoLoginTest.php
git diff --cached --check
git commit -m "feat(auth): prepare demo role availability"
~~~

### Task 5: Revalidate and authenticate one selected demo role

**Files:**

- Create: `app/Actions/Auth/LoginAsDemoRoleAction.php`
- Modify: `tests/Feature/DemoLoginTest.php`

- [ ] **Step 1: Write failing login Action tests**

Append:

~~~php
use App\Actions\Auth\LoginAsDemoRoleAction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

test('login action authenticates every correctly assigned demo identity', function (
    SystemRole $role,
): void {
    $user = createDemoLoginAccount($role);
    $request = Request::create('/demo-login/'.$role->value, 'POST');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->start();
    $previousSessionId = $request->session()->getId();

    expect(app(LoginAsDemoRoleAction::class)->handle($request, $role))->toBeTrue()
        ->and(Auth::guard('web')->id())->toBe($user->id)
        ->and($request->session()->getId())->not->toBe($previousSessionId);
})->with(SystemRole::cases());

test('login action rejects a missing or mismatched demo identity', function (): void {
    $request = Request::create('/demo-login/waiter', 'POST');
    $request->setLaravelSession(app('session')->driver());

    expect(app(LoginAsDemoRoleAction::class)->handle($request, SystemRole::Waiter))->toBeFalse();

    createDemoLoginAccount(SystemRole::Waiter, SystemRole::Cook);

    expect(app(LoginAsDemoRoleAction::class)->handle($request, SystemRole::Waiter))->toBeFalse()
        ->and(Auth::guard('web')->check())->toBeFalse();
});
~~~

- [ ] **Step 2: Run RED**

~~~bash
php artisan test --compact tests/Feature/DemoLoginTest.php
~~~

Expected: FAIL because `LoginAsDemoRoleAction` does not exist.

- [ ] **Step 3: Implement server-side identity and role revalidation**

Create `app/Actions/Auth/LoginAsDemoRoleAction.php`:

~~~php
<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Enums\SystemRole;
use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class LoginAsDemoRoleAction
{
    public function handle(Request $request, SystemRole $role): bool
    {
        $identity = DemoAccountCatalog::forRole($role);
        $user = User::query()
            ->select(['id', 'email'])
            ->with('roles:id,code')
            ->where('email', $identity['email'])
            ->first();

        if (! $user instanceof User || ! $user->hasSystemRole($role)) {
            return false;
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return true;
    }
}
~~~

- [ ] **Step 4: Verify authentication without password exposure**

~~~bash
php artisan test --compact tests/Feature/DemoLoginTest.php
vendor/bin/pint --dirty --format agent
~~~

Expected: Action tests pass for all 12 roles; missing and mismatched identities remain guests.

- [ ] **Step 5: Commit the login Action**

~~~bash
git add -- app/Actions/Auth/LoginAsDemoRoleAction.php tests/Feature/DemoLoginTest.php
git diff --cached --check
git commit -m "feat(auth): authenticate seeded demo roles"
~~~

### Task 6: Add the guarded routes, controllers, localized Blade UI, and rate limit

**Files:**

- Create: `app/Http/Controllers/Auth/ShowDemoLoginController.php`
- Create: `app/Http/Controllers/Auth/LoginAsDemoRoleController.php`
- Create: `resources/views/auth/demo-login.blade.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Modify: `resources/views/layouts/auth/simple.blade.php`
- Modify: `lang/en.json`
- Modify: `lang/lt.json`
- Modify: `lang/ru.json`
- Modify: `tests/Feature/DemoLoginTest.php`
- Modify: `tests/Feature/RouteProtectionAuditTest.php`

- [ ] **Step 1: Write the failing HTTP contract tests**

Replace the temporary probe-only emphasis with tests against the real named endpoints. Keep the middleware unit boundary tests from Task 3, then append:

~~~php
use Database\Factories\UserFactory;

test('disabled and production demo login routes are hidden', function (): void {
    config()->set('demo-login.enabled', false);
    $this->get('/demo-login')->assertNotFound();
    $this->post('/demo-login/waiter')->assertNotFound();

    config()->set('demo-login.enabled', true);
    $this->app->detectEnvironment(fn (): string => 'production');
    $this->get('/demo-login')->assertNotFound();
    $this->post('/demo-login/waiter')->assertNotFound();
});

test('enabled demo login page lists all roles without exposing the password', function (): void {
    config()->set('demo-login.enabled', true);
    createDemoLoginAccount(SystemRole::Waiter);

    $response = $this->get(route('demo-login.index'));

    $response->assertOk()
        ->assertHeaderContains('Cache-Control', 'no-store')
        ->assertHeaderContains('Cache-Control', 'private')
        ->assertHeader('Referrer-Policy', 'no-referrer')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSeeInOrder(array_map(
            static fn (SystemRole $role): string => $role->localizedLabel(),
            SystemRole::cases(),
        ))
        ->assertSee('waiter@demo.test')
        ->assertSee('cook@demo.test')
        ->assertSee('disabled', escape: false)
        ->assertDontSee(UserFactory::DEMO_PASSWORD);
});

test('post endpoint logs in each available demo role', function (SystemRole $role): void {
    config()->set('demo-login.enabled', true);
    $user = createDemoLoginAccount($role);

    $this->post(route('demo-login.authenticate', ['role' => $role->value]))
        ->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($user);
})->with(SystemRole::cases());

test('post endpoint rejects invalid missing and mismatched identities', function (): void {
    config()->set('demo-login.enabled', true);

    $this->post('/demo-login/not-a-role')->assertNotFound();
    $this->post(route('demo-login.authenticate', ['role' => SystemRole::Waiter->value]))
        ->assertRedirectToRoute('demo-login.index')
        ->assertSessionHasErrors('demo_login');

    createDemoLoginAccount(SystemRole::Waiter, SystemRole::Cook);

    $this->post(route('demo-login.authenticate', ['role' => SystemRole::Waiter->value]))
        ->assertRedirectToRoute('demo-login.index')
        ->assertSessionHasErrors('demo_login');
    $this->assertGuest();
});

test('authenticated users cannot switch identity through demo login', function (): void {
    config()->set('demo-login.enabled', true);
    $currentUser = User::factory()->create();
    createDemoLoginAccount(SystemRole::Waiter);

    $this->actingAs($currentUser)
        ->post(route('demo-login.authenticate', ['role' => SystemRole::Waiter->value]))
        ->assertRedirectToRoute('dashboard');

    $this->assertAuthenticatedAs($currentUser);
});

test('demo login endpoints are rate limited', function (): void {
    config()->set('demo-login.enabled', true);

    foreach (range(1, 20) as $attempt) {
        $this->get(route('demo-login.index'))->assertOk();
    }

    $this->get(route('demo-login.index'))->assertTooManyRequests();
});
~~~

Add this route-boundary test to `tests/Feature/RouteProtectionAuditTest.php`:

~~~php
test('demo login routes retain guest environment and throttle boundaries', function (): void {
    foreach (['demo-login.index', 'demo-login.authenticate'] as $routeName) {
        $route = prompt334RouteByName($routeName);

        expect($route)->not->toBeNull()
            ->and(prompt334RouteMiddleware($route))->toContain('web')
            ->and(prompt334RouteMiddleware($route))->toContain('demo-login')
            ->and(prompt334RouteMiddleware($route))->toContain('guest')
            ->and(prompt334RouteMiddleware($route))->toContain('throttle:demo-login')
            ->and(prompt334RouteMiddleware($route))->not->toContain('auth');
    }

    expect(prompt334RouteMethods(prompt334RouteByName('demo-login.index')))->toContain('GET')
        ->and(prompt334RouteMethods(prompt334RouteByName('demo-login.authenticate')))->toContain('POST');
});
~~~

- [ ] **Step 2: Run RED**

~~~bash
php artisan test --compact tests/Feature/DemoLoginTest.php tests/Feature/RouteProtectionAuditTest.php
~~~

Expected: route-based tests fail because the controllers, named endpoints, alias, view, and limiter are not registered.

- [ ] **Step 3: Register the middleware alias, limiter, and grouped routes**

In `bootstrap/app.php` import `EnsureDemoLoginIsEnabled` and add:

~~~php
'demo-login' => EnsureDemoLoginIsEnabled::class,
~~~

In `AppServiceProvider::configureRateLimiting()` add:

~~~php
RateLimiter::for('demo-login', fn (Request $request): Limit => Limit::perMinute(20)
    ->by((string) $request->ip()));
~~~

Import both controllers and `SystemRole` in `routes/web.php`, then add:

~~~php
Route::middleware(['demo-login', 'guest', 'throttle:demo-login'])
    ->prefix('demo-login')
    ->name('demo-login.')
    ->group(function (): void {
        Route::get('/', ShowDemoLoginController::class)->name('index');
        Route::post('{role}', LoginAsDemoRoleController::class)
            ->whereIn('role', SystemRole::values())
            ->name('authenticate');
    });
~~~

The `web` middleware remains implicit because these routes are loaded from `routes/web.php`; the route audit must observe it in the gathered middleware.

- [ ] **Step 4: Implement thin controllers**

Create `ShowDemoLoginController`:

~~~php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\BuildDemoLoginPageAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

final class ShowDemoLoginController extends Controller
{
    public function __invoke(BuildDemoLoginPageAction $action): Response
    {
        return response()
            ->view('auth.demo-login', ['accounts' => $action->handle()])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
~~~

Create `LoginAsDemoRoleController`:

~~~php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Actions\Auth\LoginAsDemoRoleAction;
use App\Enums\SystemRole;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class LoginAsDemoRoleController extends Controller
{
    public function __invoke(
        Request $request,
        SystemRole $role,
        LoginAsDemoRoleAction $action,
    ): RedirectResponse {
        if (! $action->handle($request, $role)) {
            return to_route('demo-login.index')
                ->withErrors(['demo_login' => __('demo_login.unavailable_error')]);
        }

        return to_route('dashboard');
    }
}
~~~

- [ ] **Step 5: Make the shared auth layout opt-in wide**

Add props at the top of `resources/views/layouts/auth/simple.blade.php`:

~~~blade
@props([
    'title' => null,
    'wide' => false,
])
~~~

Replace only the fixed wrapper class:

~~~blade
<div @class([
    'flex w-full flex-col gap-2',
    'max-w-sm' => ! $wide,
    'max-w-4xl' => $wide,
])>
~~~

This preserves the width of login, reset, 2FA, and existing auth screens.

- [ ] **Step 6: Create the semantic all-role Blade page**

Create `resources/views/auth/demo-login.blade.php`:

~~~blade
<x-layouts::auth.simple :title="__('demo_login.title')" wide>
    <x-auth-header
        :title="__('demo_login.title')"
        :description="__('demo_login.description')"
    />

    <div role="note" class="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">
        {{ __('demo_login.warning') }}
    </div>

    @if ($errors->has('demo_login'))
        <div role="alert" class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-950 dark:border-red-700 dark:bg-red-950 dark:text-red-100">
            {{ $errors->first('demo_login') }}
        </div>
    @endif

    <ul class="grid gap-3 sm:grid-cols-2">
        @forelse ($accounts as $account)
            <li class="flex min-w-0 flex-col gap-3 rounded-xl border border-zinc-200 p-4 dark:border-zinc-700">
                <div class="flex items-start gap-3">
                    <flux:icon
                        :name="$account['available'] ? 'check-circle' : 'exclamation-circle'"
                        class="mt-0.5 size-5 shrink-0"
                        aria-hidden="true"
                    />
                    <div class="min-w-0">
                        <h2 class="font-semibold">{{ $account['label'] }}</h2>
                        <p class="break-all text-sm text-zinc-600 dark:text-zinc-300">{{ $account['email'] }}</p>
                        <p class="mt-1 text-sm">
                            {{ $account['available'] ? __('demo_login.available') : __('demo_login.unavailable') }}
                        </p>
                        @unless ($account['available'])
                            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-300">
                                {{ __('demo_login.unavailable_hint') }}
                            </p>
                        @endunless
                    </div>
                </div>

                <form method="POST" action="{{ route('demo-login.authenticate', ['role' => $account['role']]) }}">
                    @csrf
                    <flux:button
                        type="submit"
                        variant="primary"
                        class="w-full"
                        :disabled="! $account['available']"
                    >
                        {{ __('demo_login.login_as', ['role' => $account['label']]) }}
                    </flux:button>
                </form>
            </li>
        @empty
            <li>{{ __('demo_login.empty') }}</li>
        @endforelse
    </ul>
</x-layouts::auth.simple>
~~~

Keep all values prepared by the Action; do not add model, authorization, collection, or query calls to Blade.

- [ ] **Step 7: Add exact EN/LT/RU copy**

Add these keys to all three JSON files, preserving valid JSON and placeholder parity:

| Key | EN | LT | RU |
|---|---|---|---|
| `demo_login.title` | Demo role login | Demonstracinis prisijungimas pagal rolę | Демо-вход по роли |
| `demo_login.description` | Choose a role to enter the prepared demo workspace. | Pasirinkite rolę ir atidarykite paruoštą demonstracinę aplinką. | Выберите роль, чтобы открыть подготовленную демо-среду. |
| `demo_login.warning` | Demo access is temporary and may be reset. Do not enter real or sensitive data. | Demonstracinė prieiga yra laikina ir gali būti atkurta. Neįveskite tikrų ar jautrių duomenų. | Демо-доступ временный и может быть сброшен. Не вводите реальные или конфиденциальные данные. |
| `demo_login.available` | Ready to sign in | Galima prisijungti | Можно войти |
| `demo_login.unavailable` | Demo account is not seeded | Demonstracinė paskyra neparuošta | Демо-аккаунт не создан |
| `demo_login.unavailable_hint` | Ask the demo administrator to run DemoRestaurantSeeder. | Paprašykite demonstracinės aplinkos administratoriaus paleisti DemoRestaurantSeeder. | Попросите администратора демо-среды запустить DemoRestaurantSeeder. |
| `demo_login.login_as` | Sign in as :role | Prisijungti kaip :role | Войти как :role |
| `demo_login.unavailable_error` | This demo role is unavailable. Ask the demo administrator to reseed the environment. | Ši demonstracinė rolė nepasiekiama. Paprašykite administratoriaus iš naujo paruošti aplinką. | Эта демо-роль недоступна. Попросите администратора заново заполнить демо-среду. |
| `demo_login.empty` | No demo roles are configured. | Demonstracinės rolės nesukonfigūruotos. | Демо-роли не настроены. |

- [ ] **Step 8: Run focused backend, localization, and frontend verification**

~~~bash
php artisan route:list --name=demo-login -v
php artisan test --compact tests/Feature/DemoLoginTest.php tests/Feature/RouteProtectionAuditTest.php
php artisan translations:scan --json
php artisan translations:audit
vendor/bin/pint --dirty --format agent
npm run build
~~~

Expected: two named demo routes, all focused tests pass, translation tools report no missing/mismatched keys, Pint exits 0, and Vite completes a production build.

- [ ] **Step 9: Commit the complete HTTP slice**

~~~bash
git add -- app/Http/Controllers/Auth/ShowDemoLoginController.php app/Http/Controllers/Auth/LoginAsDemoRoleController.php bootstrap/app.php app/Providers/AppServiceProvider.php routes/web.php resources/views/layouts/auth/simple.blade.php resources/views/auth/demo-login.blade.php lang/en.json lang/lt.json lang/ru.json tests/Feature/DemoLoginTest.php tests/Feature/RouteProtectionAuditTest.php
git diff --cached --check
git commit -m "feat(auth): add all-role demo login"
~~~

### Task 7: Update the canonical requirement and operator documentation

**Files:**

- Modify: `docs/requirements.md`
- Modify: `docs/compliance-matrix.md`
- Modify: `docs/DEMO_LOGIN.md`
- Modify: `docs/security.md`
- Modify: `docs/seeding.md`
- Modify: `docs/testing.md`
- Modify: `CHANGELOG.md`

- [ ] **Step 1: Add the active requirement**

Add `sys-auth-003` immediately after `sys-auth-002` in `docs/requirements.md`:

~~~markdown
| `sys-auth-003` | An explicitly enabled non-production demo environment lets a guest choose any seeded system role and enter its prepared workspace without receiving a reusable password. | Demo visitor / seeded User, Role, session | Both GET and POST are hard-denied in production and hidden when disabled; guest-only, CSRF-protected and rate-limited POST revalidates the canonical email-role assignment before login and regenerates the session. | EN/LT/RU, all 12 roles in canonical order, missing accounts disabled, no password in HTML, no-store/noindex response, accessible keyboard and responsive states. |
~~~

- [ ] **Step 2: Add the compliance evidence row and adjust totals**

Add after `sys-auth-002` in `docs/compliance-matrix.md`:

~~~markdown
| `sys-auth-003` | explicit all-role demo login | demo account catalogue, guarded controllers and Actions | existing users/roles/sessions; no schema change | production hard-deny, explicit flag, guest/CSRF/throttle, server role revalidation, session regeneration | localized 12-role Blade page; unavailable states; no credentials exposed | deterministic demo identities for every role | `DemoLoginTest`, route audit, two-query budget, browser role/login/a11y checks | implemented and verified |
~~~

After all gates are observed, change the headline from 48/47/1 to **49 requirements catalogued; 48 implemented and verified; 0 partial; 0 blocked; 1 not applicable with reason**. Update any route/test/translation totals only from the final command output; never estimate them.

- [ ] **Step 3: Rewrite the demo operator guide around the safe switch**

`docs/DEMO_LOGIN.md` must document:

- seed only with `DemoRestaurantSeeder` in an isolated non-production database;
- set `DEMO_LOGIN_ENABLED=true` only in the dedicated public demo environment, then rebuild config cache;
- `/demo-login` lists all 12 role identities and disables missing or mismatched seeded accounts;
- every click sends a CSRF-protected POST, revalidates the role, regenerates the session, and redirects to `dashboard`;
- the feature always returns 404 in production even when the flag is accidentally true;
- set the flag false and rebuild config cache to disable it;
- no reusable seed password is exposed; the demo-login page authenticates only the server-side canonical identity selected through the guarded role action.

Include exact enable/disable commands:

~~~bash
php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
php artisan config:cache
php artisan route:list --name=demo-login
~~~

- [ ] **Step 4: Reconcile the supporting documents**

- `docs/security.md`: record production hard-deny, guest/CSRF/throttle boundary, role revalidation, session regeneration, no-store/noindex headers, and absence of credentials/tokens in HTML or logs.
- `docs/seeding.md`: make `DemoAccountCatalog` the canonical identity map shared by the seeder and demo login; record all-role parity coverage.
- `docs/testing.md`: list `DemoLoginTest`, the two-query budget, route audit, three-locale checks, and browser acceptance coverage.
- `CHANGELOG.md`: add an Unreleased entry describing the opt-in all-role demo login and its safety boundary.

- [ ] **Step 5: Verify documentation against implementation**

~~~bash
rg -n "sys-auth-003|DEMO_LOGIN_ENABLED|demo-login|DemoAccountCatalog" docs CHANGELOG.md .env.example config app routes tests
php artisan translations:scan --json
php artisan translations:audit
git diff --check
~~~

Expected: the requirement, compliance evidence, configuration, operator guide, and tests describe the same flag, routes, 12-role catalogue, and production denial; translation checks exit 0.

- [ ] **Step 6: Commit documentation**

~~~bash
git add -- docs/requirements.md docs/compliance-matrix.md docs/DEMO_LOGIN.md docs/security.md docs/seeding.md docs/testing.md CHANGELOG.md
git diff --cached --check
git commit -m "docs(auth): document demo role login"
~~~

### Task 8: Run the full stable-tree and browser acceptance gates

**Files:**

- Verify only; update documentation totals from observed output if they differ.

- [ ] **Step 1: Audit scope and source boundaries before expensive gates**

~~~bash
git status --short --branch
git diff origin/main...HEAD --stat
git diff origin/main...HEAD -- . ':(exclude)docs/superpowers/specs/*' ':(exclude)docs/superpowers/plans/*'
rg -n "DB::(select|statement|raw)|selectRaw|whereRaw|Model::all" app routes tests database config
rg -n "@php|@endphp|<\\?php" resources/views
git diff --check origin/main...HEAD
~~~

Expected: only demo-login/spec/plan/documentation scope is changed; no forbidden SQL or Blade PHP was introduced; diff check is clean. Review every reported search hit in pre-existing code rather than treating a broad-match baseline as a new failure.

- [ ] **Step 2: Run dependency, formatting, static-analysis, and focused gates**

Run sequentially:

~~~bash
composer validate --strict
composer audit --locked --no-interaction
composer prohibits php 8.6 --locked
vendor/bin/pint --parallel --test
vendor/bin/phpstan analyse --memory-limit=1G
php artisan test --compact tests/Unit/DemoAccountCatalogTest.php tests/Feature/DemoLoginTest.php tests/Feature/DemoRestaurantSeederTest.php tests/Feature/RouteProtectionAuditTest.php
php artisan translations:scan --json
php artisan translations:audit
npm audit --audit-level=low
npm run build
~~~

Expected: every command exits 0; Composer/npm report no advisories; PHPStan reports no errors; focused Pest, localization, and Vite build pass.

- [ ] **Step 3: Run the complete Pest suite sequentially and in parallel**

~~~bash
php artisan test --compact
php artisan test --compact --parallel
~~~

Expected: both complete runs exit 0. Capture their actual passed/skipped/assertion counts and elapsed times for `docs/compliance-matrix.md` and `docs/testing.md`.

- [ ] **Step 4: Verify production caches**

~~~bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan about --only=environment
php artisan route:list --name=demo-login
php artisan optimize:clear
~~~

Expected: all caches build and clear successfully; route list contains exactly the GET and POST demo endpoints. With the default false flag, an HTTP request remains 404 even though route compilation succeeds.

- [ ] **Step 5: Verify fresh SQLite migration and idempotent seeding**

Create an isolated temporary SQLite file with `mktemp`, point only these commands at it, and register a shell `trap` to remove it on exit:

~~~bash
DEMO_DB_PATH="$(mktemp "${TMPDIR:-/tmp}/restaurant-menu-demo-login.sqlite.XXXXXX")"
trap 'rm -f "$DEMO_DB_PATH"' EXIT
DB_CONNECTION=sqlite DB_DATABASE="$DEMO_DB_PATH" php artisan migrate:fresh --seed --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$DEMO_DB_PATH" php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$DEMO_DB_PATH" php artisan db:seed --class=DemoRestaurantSeeder --no-interaction
DB_CONNECTION=sqlite DB_DATABASE="$DEMO_DB_PATH" php artisan tinker --execute="dump(\\App\\Models\\User::query()->whereIn('email', array_column(\\App\\Support\\DemoLogin\\DemoAccountCatalog::all(), 'email'))->count());"
~~~

Expected: fresh migration/default seed and two demo seeds exit 0; the final bounded Eloquent count prints `12`. Never point `migrate:fresh` at the worktree's normal database.

- [ ] **Step 6: Run real-browser acceptance through Herd**

Resolve the worktree URL with Laravel Boost/Herd tooling; do not start `artisan serve`. In a disposable isolated Chrome profile, temporarily enable `DEMO_LOGIN_ENABLED=true` only for this non-production worktree and clear/rebuild its config cache. Verify:

1. GET `/demo-login` shows all 12 roles in canonical order, correct email, warning, status, and full-width action.
2. A missing role account is disabled; its POST cannot authenticate.
3. Each seeded role can log in by one click and reaches `/dashboard` as the selected user.
4. Returning as an authenticated user cannot switch identities because `guest` redirects to `dashboard`.
5. The page has no demo password, token, sensitive query string, console error, failed asset request, or horizontal overflow.
6. Keyboard order, visible focus, accessible names/status text, 200% zoom, reduced motion, forced colors, and 320x640 / 360x640 / 768x1024 / 1440x900 layouts remain usable.
7. EN, LT, and RU copy renders without overflow and the `:role` placeholder is replaced.
8. Confirm that the session cookie identifier changes across the successful POST without recording either identifier in logs, screenshots, or documentation.

Then set `DEMO_LOGIN_ENABLED=false`, rebuild config cache, and verify GET and POST both return 404. Temporarily set the local application environment to `production` with the flag true, rebuild config cache, verify both remain 404, and immediately restore the prior non-production values. Do not commit `.env`.

- [ ] **Step 7: Reconcile observed totals and rerun the affected documentation checks**

If route/test/translation/build totals changed, update only the affected lines in `docs/compliance-matrix.md`, `docs/testing.md`, and `CHANGELOG.md` from observed output:

~~~bash
git add -- docs/compliance-matrix.md docs/testing.md CHANGELOG.md
git diff --cached --check
git commit -m "docs(quality): record demo login verification"
~~~

Skip this commit when no evidence lines need adjustment.

- [ ] **Step 8: Final review and handoff**

~~~bash
git status --short --branch
git log --oneline --decorate origin/main..HEAD
git diff --check origin/main...HEAD
git diff --stat origin/main...HEAD
~~~

Expected: clean `feature/demo-role-login` worktree, coherent conventional commits, and only the intended spec/plan/implementation/test/documentation paths. Do not merge or push until explicitly requested.
