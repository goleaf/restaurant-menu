<?php

declare(strict_types=1);

use App\Actions\Monitoring\ReportProductionExceptionAction;
use App\Logging\ConfigureSanitizedLogging;
use App\Logging\RedactSensitiveLogContext;
use App\Notifications\ProductionErrorNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Monolog\Level;
use Monolog\LogRecord;

test('health endpoint verifies production dependencies without exposing details', function (): void {
    $response = $this->get('/up');

    $response
        ->assertOk()
        ->assertHeader('X-Request-Id');

    expect($response->headers->get('X-Request-Id'))
        ->toMatch('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('no-store', 'no-cache', 'must-revalidate');
});

test('health endpoint fails closed when a required dependency is unavailable', function (): void {
    $originalCacheStore = config('cache.default');
    config()->set('app.debug', false);
    config()->set('cache.default', 'null');
    app('cache')->forgetDriver('null');

    try {
        $this->get('/up')
            ->assertInternalServerError()
            ->assertHeader('X-Request-Id')
            ->assertDontSee('Production health check [cache] failed');
    } finally {
        config()->set('cache.default', $originalCacheStore);
        app('cache')->forgetDriver('null');
    }
});

test('sensitive log context is recursively redacted without removing operational fields', function (): void {
    $record = new LogRecord(
        datetime: now()->toDateTimeImmutable(),
        channel: 'testing',
        level: Level::Error,
        message: 'production_error',
        context: [
            'order_id' => 42,
            'password' => 'do-not-log-password',
            'nested' => [
                'guest_token' => 'do-not-log-token',
                'authorization_header' => 'Bearer do-not-log-authorization',
            ],
        ],
        extra: [
            'session_id' => 'do-not-log-session',
            'request_id' => 'safe-request-id',
            'table_session_id' => 84,
        ],
    );

    $redacted = (new RedactSensitiveLogContext)($record);

    expect($redacted->context)->toMatchArray([
        'order_id' => 42,
        'password' => '[REDACTED]',
        'nested' => [
            'guest_token' => '[REDACTED]',
            'authorization_header' => '[REDACTED]',
        ],
    ])->and($redacted->extra)->toMatchArray([
        'session_id' => '[REDACTED]',
        'request_id' => 'safe-request-id',
        'table_session_id' => 84,
    ]);
});

test('production log channels rotate retain and sanitize records', function (): void {
    expect(config('logging.channels.daily.driver'))->toBe('daily')
        ->and(config('logging.channels.daily.days'))->toBe(14)
        ->and(config('logging.channels.daily.tap'))->toContain(ConfigureSanitizedLogging::class)
        ->and(config('logging.channels.deprecations.driver'))->toBe('daily')
        ->and(config('logging.channels.deprecations.days'))->toBe(14)
        ->and(config('logging.channels.deprecations.tap'))->toContain(ConfigureSanitizedLogging::class);
});

test('configured log channels redact secrets before writing them to disk', function (): void {
    $channel = 'observability_test';
    $logPath = storage_path('framework/testing/observability-'.Str::uuid().'.log');

    try {
        config()->set("logging.channels.{$channel}", [
            'driver' => 'single',
            'path' => $logPath,
            'tap' => [ConfigureSanitizedLogging::class],
        ]);
        Log::forgetChannel($channel);
        Log::channel($channel)->error('observability_test', [
            'order_id' => 42,
            'api_key' => 'do-not-write-this-api-key',
            'password' => 'do-not-write-this-password',
            'nested' => ['guest_token' => 'do-not-write-this-token'],
        ]);

        $contents = File::get($logPath);

        expect($contents)
            ->toContain('observability_test', '"order_id":42', '[REDACTED]')
            ->not->toContain(
                'do-not-write-this-api-key',
                'do-not-write-this-password',
                'do-not-write-this-token',
            );
    } finally {
        Log::forgetChannel($channel);
        config()->set("logging.channels.{$channel}");
        File::delete($logPath);
    }
});

test('error alerts remain disabled outside production', function (): void {
    Notification::fake();
    config()->set([
        'monitoring.error_notifications.enabled' => true,
        'monitoring.error_notifications.email' => 'operations@example.test',
        'monitoring.error_notifications.cache_store' => 'array',
    ]);

    app(ReportProductionExceptionAction::class)->handle(new RuntimeException('local-only failure'));

    Notification::assertNothingSent();
});

test('production exceptions send one deduplicated safe on-demand alert', function (): void {
    Notification::fake();
    Cache::store('array')->flush();
    $originalEnvironment = app()->environment();
    app()->detectEnvironment(fn (): string => 'production');
    config()->set([
        'app.debug' => false,
        'monitoring.error_notifications.enabled' => true,
        'monitoring.error_notifications.email' => 'operations@example.test',
        'monitoring.error_notifications.cache_store' => 'array',
        'monitoring.error_notifications.cooldown_seconds' => 300,
    ]);

    Route::get('/__test-observability/production-error', function (): never {
        throw new RuntimeException('do-not-email-this-secret');
    })->name('testing.production-error');

    try {
        $firstResponse = $this->get('/__test-observability/production-error');
        $secondResponse = $this->get('/__test-observability/production-error');

        $firstResponse->assertInternalServerError()->assertHeader('X-Request-Id');
        $secondResponse->assertInternalServerError()->assertHeader('X-Request-Id');

        Notification::assertSentOnDemand(
            ProductionErrorNotification::class,
            function (
                ProductionErrorNotification $notification,
                array $channels,
                AnonymousNotifiable $notifiable,
            ) use ($firstResponse): bool {
                $renderedMail = (string) $notification->toMail($notifiable)->render();

                return $channels === ['mail']
                    && ($notifiable->routes['mail'] ?? null) === 'operations@example.test'
                    && $notification->requestId === $firstResponse->headers->get('X-Request-Id')
                    && $notification->routeName === 'testing.production-error'
                    && ! str_contains($renderedMail, 'do-not-email-this-secret');
            },
        );
        Notification::assertSentOnDemandTimes(ProductionErrorNotification::class, 1);
    } finally {
        app()->detectEnvironment(fn (): string => $originalEnvironment);
        Cache::store('array')->flush();
    }
});
