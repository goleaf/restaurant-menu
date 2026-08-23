<?php

declare(strict_types=1);

namespace App\Actions\Monitoring;

use App\Notifications\ProductionErrorNotification;
use Illuminate\Cache\CacheManager;
use Illuminate\Contracts\Notifications\Dispatcher;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

final class ReportProductionExceptionAction
{
    public function __construct(
        private readonly Application $application,
        private readonly CacheManager $cache,
        private readonly Dispatcher $notifications,
        private readonly LoggerInterface $logger,
    ) {}

    public function handle(Throwable $exception): void
    {
        $recipient = trim((string) config('monitoring.error_notifications.email'));

        if (! $this->application->isProduction()
            || ! (bool) config('monitoring.error_notifications.enabled', false)
            || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return;
        }

        $request = $this->currentRequest();
        $routeName = $request?->route()?->getName();
        $routeName = is_string($routeName) && $routeName !== ''
            ? $routeName
            : ($request !== null ? 'unmatched' : 'console');
        $fingerprint = hash('sha256', implode('|', [
            $exception::class,
            $exception->getFile(),
            (string) $exception->getLine(),
            $routeName,
        ]));
        $incidentId = substr($fingerprint, 0, 16);
        $requestId = Context::get('request_id');

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        $store = trim((string) config('monitoring.error_notifications.cache_store', 'file'));
        $cooldown = min(86_400, max(60, (int) config('monitoring.error_notifications.cooldown_seconds', 300)));

        try {
            if (! $this->cache->store($store)->add(
                'production-error-notification:'.$fingerprint,
                true,
                now()->addSeconds($cooldown),
            )) {
                return;
            }

            $notifiable = (new AnonymousNotifiable)->route('mail', $recipient);
            $notification = (new ProductionErrorNotification(
                requestId: $requestId,
                incidentId: $incidentId,
                exceptionClass: $exception::class,
                routeName: $routeName,
                httpMethod: $request?->method() ?? 'CONSOLE',
                occurredAt: now()->utc()->toIso8601String(),
            ))->locale((string) config('app.fallback_locale', 'en'));

            $this->notifications->sendNow($notifiable, $notification, ['mail']);
        } catch (Throwable $notificationException) {
            $this->logNotificationFailure($notificationException, $incidentId, $requestId);
        }
    }

    private function currentRequest(): ?Request
    {
        if (! $this->application->bound('request')) {
            return null;
        }

        return $this->application->make('request');
    }

    private function logNotificationFailure(
        Throwable $exception,
        string $incidentId,
        string $requestId,
    ): void {
        try {
            $this->logger->error('production_error_notification_failed', [
                'event' => 'production_error_notification_failed',
                'incident_id' => $incidentId,
                'notification_exception' => $exception::class,
                'request_id' => $requestId,
            ]);
        } catch (Throwable) {
            // Error reporting must never replace the original application exception.
        }
    }
}
