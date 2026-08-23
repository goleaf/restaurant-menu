<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ProductionErrorNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $requestId,
        public readonly string $incidentId,
        public readonly string $exceptionClass,
        public readonly string $routeName,
        public readonly string $httpMethod,
        public readonly string $occurredAt,
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject(__('monitoring.production_error.subject', [
                'app' => config('app.name'),
                'incident' => $this->incidentId,
            ]))
            ->line(__('monitoring.production_error.introduction'))
            ->line(__('monitoring.production_error.incident', ['incident' => $this->incidentId]))
            ->line(__('monitoring.production_error.request', ['request' => $this->requestId]))
            ->line(__('monitoring.production_error.route', [
                'method' => $this->httpMethod,
                'route' => $this->routeName,
            ]))
            ->line(__('monitoring.production_error.exception', ['exception' => $this->exceptionClass]))
            ->line(__('monitoring.production_error.occurred_at', ['time' => $this->occurredAt]))
            ->line(__('monitoring.production_error.investigate'));
    }
}
