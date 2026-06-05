<?php

use App\Enums\SystemRole;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DemoRestaurantSeeder;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(SystemPermissionsSeeder::class);
});

test('superadmin dashboard shows production safety warnings without exposing secrets', function () {
    config()->set('app.env', 'production');
    config()->set('app.debug', true);
    $secretKey = 'base64:'.base64_encode(str_repeat('a', 32));
    config()->set('app.key', $secretKey);

    $superadmin = createProductionSafetySuperadmin();

    $this->actingAs($superadmin)
        ->get(route('superadmin.dashboard'))
        ->assertOk()
        ->assertSee('System health')
        ->assertSee('Environment')
        ->assertSee('Production')
        ->assertSee('APP_DEBUG is enabled in production')
        ->assertDontSee($secretKey);
});

test('demo restaurant seeder is development only in production', function () {
    config()->set('app.env', 'production');

    expect(fn () => $this->seed(DemoRestaurantSeeder::class))
        ->toThrow(RuntimeException::class, 'development-only');
});

test('routes do not expose unsafe production developer endpoints', function () {
    $unsafeTokens = [
        'debug',
        'phpinfo',
        'test-payment',
        'test_payment',
        'fake-training',
        'fake_training',
        'training-mode',
        'training_mode',
        'log-viewer',
        'log_viewer',
        'logs-viewer',
        'logs_viewer',
        'telescope',
    ];

    $matches = collect(Route::getRoutes())
        ->flatMap(fn ($route): array => [
            $route->uri(),
            $route->getName() ?? '',
            $route->getActionName(),
        ])
        ->filter(fn (string $value): bool => Str::contains(Str::lower($value), $unsafeTokens))
        ->values()
        ->all();

    expect($matches)->toBeEmpty();
});

test('public storage denies php execution on shared hosting', function () {
    $path = storage_path('app/public/.htaccess');

    expect(File::exists($path))->toBeTrue();

    $rules = File::get($path);

    expect($rules)
        ->toContain('FilesMatch')
        ->toContain('php')
        ->toContain('Require all denied');
});

function createProductionSafetySuperadmin(): User
{
    $user = User::factory()->create([
        'name' => 'Production Safety Superadmin',
        'email' => 'production-safety-superadmin@example.test',
    ]);

    $role = Role::query()
        ->where('code', SystemRole::Superadmin->value)
        ->firstOrFail();

    $user->roles()->syncWithoutDetachingOrFail([$role->id]);

    return $user;
}
