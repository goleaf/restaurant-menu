<?php

declare(strict_types=1);

use App\Actions\Organizations\CreateOrganizationAction;
use App\Enums\SystemRole;
use App\Http\Controllers\Restaurant\DownloadPreparedFileController;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Role;
use App\Models\User;
use App\Support\Files\PreparedDownloadStore;
use Database\Seeders\SystemPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    $this->seed(SystemPermissionsSeeder::class);
    Storage::fake('local');
    $this->owner = User::factory()->create();
    $organization = app(CreateOrganizationAction::class)->handle($this->owner, ['name' => 'Prepared files']);
    $this->organization = $organization;
    $brand = Brand::factory()->for($organization)->create();
    $this->branch = Branch::factory()->for($organization)->for($brand)->create();
    $this->actingAs($this->owner);
    session()->start();
    Storage::disk('local')->put('prepared-test.csv', 'safe,contents');
    $this->grant = app(PreparedDownloadStore::class)->issue($this->owner, Storage::disk('local')->path('prepared-test.csv'), 'report.csv', 'csv', $this->branch->id);
    $this->url = route('restaurant.files.download', ['grant' => $this->grant]);
    session()->save();
    $this->withCookie(config('session.cookie'), session()->getId());
});

function preparedTransportRequest(string $method, User $actor, array $headers = []): Request
{
    $request = Request::create('/restaurant/files/test', $method, server: $headers);
    $request->setLaravelSession(session()->driver());
    $request->setUserResolver(fn () => $actor);

    return $request;
}

test('prepared binary transport uses the completed file and consumes the grant once', function (): void {
    $controller = app(DownloadPreparedFileController::class);
    $response = app()->call($controller(...), ['request' => preparedTransportRequest('GET', $this->owner), 'grant' => $this->grant]);
    expect($response->getFile()->getContent())->toBe('safe,contents');
    expect(fn () => app()->call($controller(...), ['request' => preparedTransportRequest('GET', $this->owner), 'grant' => $this->grant]))->toThrow(HttpException::class);
});

test('speculative requests never consume a prepared file', function (string $method, array $headers): void {
    $controller = app(DownloadPreparedFileController::class);
    expect(fn () => app()->call($controller(...), ['request' => preparedTransportRequest($method, $this->owner, $headers), 'grant' => $this->grant]))->toThrow(HttpException::class);
    expect(app(PreparedDownloadStore::class)->peek(preparedTransportRequest('GET', $this->owner), $this->grant)['filename'])->toBe('report.csv');
})->with(['head' => ['HEAD', []], 'prefetch' => ['GET', ['HTTP_SEC_PURPOSE' => 'prefetch']], 'range' => ['GET', ['HTTP_RANGE' => 'bytes=0-5']]]);

test('prepared file authorization rejects another actor or expired grant', function (): void {
    $store = app(PreparedDownloadStore::class);
    expect(fn () => $store->peek(preparedTransportRequest('GET', User::factory()->create()), $this->grant))->toThrow(HttpException::class);
    $this->travel(6)->minutes();
    expect(fn () => $store->peek(preparedTransportRequest('GET', $this->owner), $this->grant))->toThrow(HttpException::class);
});

test('prepared file detects replacement before it is served', function (): void {
    $store = app(PreparedDownloadStore::class);
    $metadata = $store->peek(preparedTransportRequest('GET', $this->owner), $this->grant);
    file_put_contents($metadata['path'], 'tampered');
    expect(fn () => $store->consume(preparedTransportRequest('GET', $this->owner), $this->grant))->toThrow(HttpException::class);
});

test('HTTP speculative transport preserves the grant and never starts file generation', function (string $method, array $headers, int $status): void {
    $this->call($method, $this->url, server: $headers)->assertStatus($status);
    expect(session('prepared_downloads.'.$this->grant))->toBeArray()
        ->and(Storage::disk('local')->files('prepared-downloads'))->toHaveCount(1);
    $this->get($this->url)->assertOk()->assertDownload('report.csv');
})->with(['head' => ['HEAD', [], 405], 'prefetch' => ['GET', ['HTTP_PURPOSE' => 'prefetch'], 405], 'sec-prefetch' => ['GET', ['HTTP_SEC_PURPOSE' => 'prefetch;prerender'], 405], 'range' => ['GET', ['HTTP_RANGE' => 'bytes=0-3'], 416]]);

test('HTTP transport rechecks branch permission without consuming a revoked grant', function (): void {
    $this->organization->users()->detach($this->owner);
    $this->get($this->url)->assertForbidden();
    expect(session('prepared_downloads.'.$this->grant))->toBeArray();
});

test('HTTP transport cannot reuse a grant after a session change', function (): void {
    session()->regenerate();
    session()->save();
    $this->withCookie(config('session.cookie'), session()->getId())->get($this->url)->assertForbidden();
});

test('HTTP backup transport rechecks current platform role and password before consuming', function (): void {
    $role = Role::query()->where('code', SystemRole::Superadmin->value)->firstOrFail();
    $this->owner->roles()->attach($role);
    Storage::disk('local')->put('backup.sqlite', 'prepared backup');
    $grant = app(PreparedDownloadStore::class)->issue($this->owner, Storage::disk('local')->path('backup.sqlite'), 'backup.sqlite', 'sqlite');
    $url = route('restaurant.files.download', ['grant' => $grant]);
    session()->save();
    $this->get($url)->assertRedirect(route('password.confirm'));
    expect(session('prepared_downloads.'.$grant))->toBeArray();
    $this->owner->roles()->detach($role);
    $this->get($url)->assertForbidden();
    expect(session('prepared_downloads.'.$grant))->toBeArray();
    $this->owner->roles()->attach($role);
    session()->put('auth.password_confirmed_at', now()->timestamp);
    session()->save();
    $response = $this->get($url)->assertOk();
    expect($response->headers->get('Cache-Control'))->toContain('private', 'no-store')->not->toContain('public');
});
