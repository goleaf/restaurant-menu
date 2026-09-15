<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->keyEnvironment = sys_get_temp_dir().'/restaurant-key-test-'.Str::uuid();
    File::makeDirectory($this->keyEnvironment);
    $this->app->useEnvironmentPath($this->keyEnvironment);
    $this->app->loadEnvironmentFrom('.env');
    config(['app.key' => '']);
});

afterEach(function (): void {
    File::deleteDirectory($this->keyEnvironment);
});

test('repeat setup preserves an existing environment application key even with empty cached configuration', function (): void {
    $content = 'APP_KEY="base64:'.base64_encode(random_bytes(32)).'"'."\nAPP_NAME=Example\n";
    File::put($this->keyEnvironment.'/.env', $content);
    $this->artisan('app:ensure-key')->assertSuccessful();
    $this->artisan('app:ensure-key')->assertSuccessful();
    expect(File::get($this->keyEnvironment.'/.env'))->toBe($content);
});

test('setup creates an absent application key exactly once', function (): void {
    File::put($this->keyEnvironment.'/.env', "APP_KEY=\n");
    $this->artisan('app:ensure-key')->assertSuccessful();
    $first = File::get($this->keyEnvironment.'/.env');
    expect($first)->toMatch('/APP_KEY=base64:[A-Za-z0-9+\/=]+/');
    $this->artisan('app:ensure-key')->assertSuccessful();
    expect(File::get($this->keyEnvironment.'/.env'))->toBe($first);
});

test('setup preserves an externally configured application key', function (): void {
    config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    File::put($this->keyEnvironment.'/.env', "APP_KEY=\n");
    $this->artisan('app:ensure-key')->assertSuccessful();
    expect(File::get($this->keyEnvironment.'/.env'))->toBe("APP_KEY=\n");
});
