<?php

use Tests\Support\VerificationRuntime;

test('verification captures the serving process runtime without a configured expectation', function () {
    expect(VerificationRuntime::identity())->toBe([
        'version' => PHP_VERSION,
        'binary' => PHP_BINARY,
        'sapi' => PHP_SAPI,
    ]);
});

test('the browser coordinator propagates explicit expectations or its actual launcher identity', function (string|false $version, string|false $binary) {
    $previousVersion = getenv('RESTAURANT_EXPECTED_PHP_VERSION');
    $previousBinary = getenv('RESTAURANT_EXPECTED_PHP_BINARY');
    putenv($version === false ? 'RESTAURANT_EXPECTED_PHP_VERSION' : 'RESTAURANT_EXPECTED_PHP_VERSION='.$version);
    putenv($binary === false ? 'RESTAURANT_EXPECTED_PHP_BINARY' : 'RESTAURANT_EXPECTED_PHP_BINARY='.$binary);

    try {
        expect(VerificationRuntime::coordinatorExpectation())->toBe([
            'version' => $version === false ? PHP_VERSION : $version,
            'binary' => $binary === false ? PHP_BINARY : $binary,
        ]);
    } finally {
        putenv($previousVersion === false ? 'RESTAURANT_EXPECTED_PHP_VERSION' : 'RESTAURANT_EXPECTED_PHP_VERSION='.$previousVersion);
        putenv($previousBinary === false ? 'RESTAURANT_EXPECTED_PHP_BINARY' : 'RESTAURANT_EXPECTED_PHP_BINARY='.$previousBinary);
    }
})->with([
    'known launcher' => [false, false],
    'explicit external selection' => ['8.6.0beta3', '/isolated/php86/bin/php'],
    'preserve invalid explicit values' => ['', ''],
]);

test('verification accepts an explicitly matching HTTP runtime identity', function () {
    $identity = ['version' => '8.6.0beta3', 'binary' => '/isolated/php86/bin/php', 'sapi' => 'cli'];

    VerificationRuntime::assertMatches($identity, '8.6.0beta3', '/isolated/php86/bin/php');

    expect($identity['version'])->toBe('8.6.0beta3');
});

test('verification rejects missing or malformed HTTP runtime evidence', function (mixed $identity) {
    expect(fn () => VerificationRuntime::assertMatches($identity, '8.6.0beta3', '/isolated/php86/bin/php'))
        ->toThrow(RuntimeException::class, 'HTTP runtime identity is incomplete.');
})->with([
    'missing response' => [null],
    'non-object response' => ['8.6.0beta3'],
    'missing values' => [[]],
    'empty version' => [['version' => '', 'binary' => '/isolated/php86/bin/php', 'sapi' => 'cli']],
    'non-string version' => [['version' => 80600, 'binary' => '/isolated/php86/bin/php', 'sapi' => 'cli']],
    'empty binary' => [['version' => '8.6.0beta3', 'binary' => ' ', 'sapi' => 'cli']],
    'missing SAPI' => [['version' => '8.6.0beta3', 'binary' => '/isolated/php86/bin/php']],
    'empty SAPI' => [['version' => '8.6.0beta3', 'binary' => '/isolated/php86/bin/php', 'sapi' => '']],
]);

test('verification never infers an expected web runtime from the CLI process', function (string $version, string $binary) {
    expect(fn () => VerificationRuntime::assertMatches(VerificationRuntime::identity(), $version, $binary))
        ->toThrow(RuntimeException::class, 'An explicit expected PHP version and binary are required.');
})->with([
    'missing version' => ['', '/isolated/php86/bin/php'],
    'missing binary' => ['8.6.0beta3', ''],
    'blank expectation' => [' ', ' '],
]);

test('verification rejects a different web runtime even when the CLI expectation was supplied', function (array $identity, string $message) {
    expect(fn () => VerificationRuntime::assertMatches($identity, '8.6.0beta3', '/isolated/php86/bin/php'))
        ->toThrow(RuntimeException::class, $message);
})->with([
    'stable server instead of beta' => [['version' => '8.5.10', 'binary' => '/isolated/php86/bin/php', 'sapi' => 'fpm-fcgi'], 'HTTP PHP version does not match the selected runtime.'],
    'different beta release' => [['version' => '8.6.0beta2', 'binary' => '/isolated/php86/bin/php', 'sapi' => 'cli'], 'HTTP PHP version does not match the selected runtime.'],
    'different binary' => [['version' => '8.6.0beta3', 'binary' => '/another/php/bin/php', 'sapi' => 'cli'], 'HTTP PHP binary does not match the selected runtime.'],
]);

test('verification compares canonical binary paths without trusting a same-version executable', function () {
    $directory = sys_get_temp_dir().'/restaurant-runtime-alias-'.bin2hex(random_bytes(8));
    mkdir($directory, 0700);
    $alias = $directory.'/php';
    symlink(PHP_BINARY, $alias);

    try {
        VerificationRuntime::assertMatches(VerificationRuntime::identity(), PHP_VERSION, $alias);

        expect(realpath($alias))->toBe(realpath(PHP_BINARY));
    } finally {
        unlink($alias);
        rmdir($directory);
    }
});
