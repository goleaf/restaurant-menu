<?php

use Illuminate\Support\Facades\Route;
use Tests\Support\VerificationRuntime;

test('browser HTTP requests are served by the explicitly selected PHP runtime', function () {
    $nonce = bin2hex(random_bytes(16));
    $path = '/__verification/runtime/'.$nonce;

    Route::middleware('web')->prefix('__verification')->name('verification.')->group(function () use ($nonce): void {
        Route::get('/runtime/'.$nonce, static fn () => response()->json([
            ...VerificationRuntime::identity(),
            'nonce' => $nonce,
        ]))->name('runtime');
    });

    $page = visit($path)->assertPathIs($path)->assertNoJavaScriptErrors();
    $identity = $page->script('JSON.parse(document.body.textContent)');

    expect($identity)->toBeArray()
        ->and($identity['nonce'] ?? null)->toBe($nonce);

    VerificationRuntime::assertMatches(
        $identity,
        getenv('RESTAURANT_EXPECTED_PHP_VERSION') ?: '',
        getenv('RESTAURANT_EXPECTED_PHP_BINARY') ?: '',
    );

    unset($identity['nonce']);
    fwrite(STDOUT, "\nHTTP runtime: ".json_encode($identity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)."\n");
});
