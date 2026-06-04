<?php

namespace App\Livewire\PublicQr;

use App\Enums\TableSessionGuestStatus;
use App\Models\TableSessionGuest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class Notifications extends Component
{
    public int $tableSessionId = 0;

    public int $currentGuestId = 0;

    public string $publicToken = '';

    public int $unreadCount = 0;

    public bool $canRead = false;

    /**
     * @var list<array{id: string, title: string, body: string, meta: string, tone: string, created_label: string}>
     */
    public array $notifications = [];

    public function mount(int $tableSessionId, int $currentGuestId, string $publicToken): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->publicToken = $publicToken;

        $this->refreshNotifications();
    }

    public function refreshNotifications(): void
    {
        $guest = $this->activeGuest();
        $this->canRead = $guest instanceof TableSessionGuest;

        if (! $this->canRead) {
            $this->unreadCount = 0;
            $this->notifications = [];

            return;
        }

        $this->unreadCount = (int) $guest->unreadNotifications()
            ->whereIn('type', $this->guestNotificationTypes())
            ->count();

        $this->notifications = $guest->unreadNotifications()
            ->select(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at'])
            ->whereIn('type', $this->guestNotificationTypes())
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (DatabaseNotification $notification): array => $this->presentNotification($notification))
            ->all();
    }

    public function markNotificationRead(string $notificationId): void
    {
        $guest = $this->activeGuest();

        if (! $guest instanceof TableSessionGuest) {
            $this->refreshNotifications();

            return;
        }

        $notification = $guest->unreadNotifications()
            ->whereKey($notificationId)
            ->first();

        if ($notification instanceof DatabaseNotification) {
            $notification->markAsRead();
        }

        $this->refreshNotifications();
    }

    public function markAllRead(): void
    {
        $guest = $this->activeGuest();

        if (! $guest instanceof TableSessionGuest) {
            $this->refreshNotifications();

            return;
        }

        $guest->unreadNotifications()
            ->whereIn('type', $this->guestNotificationTypes())
            ->update(['read_at' => now()]);

        $this->refreshNotifications();
    }

    public function render(): View
    {
        return view('livewire.public-qr.notifications');
    }

    private function activeGuest(): ?TableSessionGuest
    {
        $guestToken = $this->guestTokenFromCookie();

        if ($guestToken === null) {
            return null;
        }

        return TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'guest_token',
                'status',
                'joined_at',
                'left_at',
            ])
            ->whereKey($this->currentGuestId)
            ->where('table_session_id', $this->tableSessionId)
            ->where('guest_token', $guestToken)
            ->where('status', TableSessionGuestStatus::Active->value)
            ->first();
    }

    private function guestTokenFromCookie(): ?string
    {
        if ($this->publicToken === '') {
            return null;
        }

        $guestToken = request()->cookie($this->guestTokenCookieName($this->publicToken));

        if (is_string($guestToken) && strlen($guestToken) === 64) {
            return $guestToken;
        }

        $guestToken = session('guest_entries.'.$this->publicToken.'.guest_token');

        if (! is_string($guestToken) || strlen($guestToken) !== 64) {
            return null;
        }

        return $guestToken;
    }

    private function guestTokenCookieName(string $publicToken): string
    {
        return 'guest_token_'.substr(hash('sha256', $publicToken), 0, 24);
    }

    /**
     * @return list<string>
     */
    private function guestNotificationTypes(): array
    {
        return [
            'join_request_created',
            'draft_order_confirmed',
            'draft_order_rejected',
            'kitchen_item_cooking',
            'kitchen_item_ready',
        ];
    }

    /**
     * @return array{id: string, title: string, body: string, meta: string, tone: string, created_label: string}
     */
    private function presentNotification(DatabaseNotification $notification): array
    {
        $data = is_array($notification->data) ? $notification->data : [];
        $itemName = (string) data_get($data, 'item_name', '');
        $guestName = (string) data_get($data, 'guest_name', '');
        $reason = (string) data_get($data, 'rejection_reason', '');

        return [
            'id' => $notification->id,
            'title' => $this->titleForType($notification->type),
            'body' => match ($notification->type) {
                'join_request_created' => $guestName !== ''
                    ? __(':name хочет присоединиться к столу.', ['name' => $guestName])
                    : (string) data_get($data, 'message', __('Новый гость ждёт подтверждения.')),
                'draft_order_confirmed' => (string) data_get($data, 'message', __('Официант подтвердил заказ.')),
                'draft_order_rejected' => $reason !== ''
                    ? __('Причина: :reason', ['reason' => $reason])
                    : (string) data_get($data, 'message', __('Официант отклонил заказ.')),
                'kitchen_item_cooking' => $itemName !== ''
                    ? __(':item начали готовить.', ['item' => $itemName])
                    : (string) data_get($data, 'message', __('Позиция готовится.')),
                'kitchen_item_ready' => $itemName !== ''
                    ? __(':item готово.', ['item' => $itemName])
                    : (string) data_get($data, 'message', __('Позиция готова.')),
                default => (string) data_get($data, 'message', __('Новое уведомление.')),
            },
            'meta' => $this->metaForData($data),
            'tone' => $this->toneForType($notification->type),
            'created_label' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }

    private function titleForType(string $type): string
    {
        return match ($type) {
            'join_request_created' => __('Новый гость ждёт подтверждения'),
            'draft_order_confirmed' => __('Заказ подтверждён'),
            'draft_order_rejected' => __('Заказ отклонён'),
            'kitchen_item_cooking' => __('Позиция готовится'),
            'kitchen_item_ready' => __('Позиция готова'),
            default => __('Уведомление'),
        };
    }

    private function toneForType(string $type): string
    {
        return match ($type) {
            'join_request_created' => 'amber',
            'draft_order_confirmed', 'kitchen_item_ready' => 'emerald',
            'draft_order_rejected' => 'rose',
            'kitchen_item_cooking' => 'sky',
            default => 'zinc',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function metaForData(array $data): string
    {
        $parts = array_filter([
            data_get($data, 'service_point_name'),
            data_get($data, 'area_name'),
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        return implode(' · ', $parts);
    }
}
