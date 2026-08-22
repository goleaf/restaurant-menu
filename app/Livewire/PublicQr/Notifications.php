<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Enums\TableSessionGuestStatus;
use App\Models\TableSessionGuest;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class Notifications extends Component
{
    #[Locked]
    public int $tableSessionId = 0;

    #[Locked]
    public int $currentGuestId = 0;

    #[Locked]
    public string $publicToken = '';

    public int $pollingIntervalSeconds = 1;

    public int $unreadCount = 0;

    public bool $canRead = false;

    /**
     * @var list<array{id: string, title: string, body: string, meta: string, tone: string, created_label: string}>
     */
    public array $notifications = [];

    public function mount(int $tableSessionId, int $currentGuestId, string $publicToken, int $pollingIntervalSeconds = 1): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->publicToken = $publicToken;
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);

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
        $data = $notification->data;
        $itemName = (string) data_get($data, 'item_name', '');
        $guestName = (string) data_get($data, 'guest_name', '');
        $reason = (string) data_get($data, 'rejection_reason', '');

        return [
            'id' => $notification->id,
            'title' => $this->titleForType($notification->type),
            'body' => match ($notification->type) {
                'join_request_created' => $guestName !== ''
                    ? __('ui.livewire.publicqr.notifications.xocet_prisoedinitsia_k_stolu', ['name' => $guestName])
                    : (string) data_get($data, 'message', __('ui.livewire.publicqr.notifications.novyi_gost_zdet_podtverzdeniia')),
                'draft_order_confirmed' => (string) data_get($data, 'message', __('ui.livewire.publicqr.notifications.oficiant_podtverdil_zakaz')),
                'draft_order_rejected' => $reason !== ''
                    ? __('ui.livewire.publicqr.notifications.pricina', ['reason' => $reason])
                    : (string) data_get($data, 'message', __('ui.livewire.publicqr.notifications.oficiant_otklonil_zakaz')),
                'kitchen_item_cooking' => $itemName !== ''
                    ? __('ui.livewire.publicqr.notifications.nacali_gotovit', ['item' => $itemName])
                    : (string) data_get($data, 'message', __('ui.livewire.publicqr.notifications.poziciia_gotovitsia')),
                'kitchen_item_ready' => $itemName !== ''
                    ? __('ui.livewire.notifications.unreadcount.gotovo', ['item' => $itemName])
                    : (string) data_get($data, 'message', __('ui.livewire.notifications.unreadcount.poziciia_gotova')),
                default => (string) data_get($data, 'message', __('ui.livewire.notifications.unreadcount.novoe_uvedomlenie')),
            },
            'meta' => $this->metaForData($data),
            'tone' => $this->toneForType($notification->type),
            'created_label' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }

    private function titleForType(string $type): string
    {
        return match ($type) {
            'join_request_created' => __('ui.livewire.publicqr.notifications.novyi_gost_zdet_podtverzdeniia_7813e12a'),
            'draft_order_confirmed' => __('ui.livewire.publicqr.notifications.zakaz_podtverzden'),
            'draft_order_rejected' => __('ui.livewire.publicqr.notifications.zakaz_otklonen'),
            'kitchen_item_cooking' => __('ui.livewire.publicqr.notifications.poziciia_gotovitsia_c07e0e57'),
            'kitchen_item_ready' => __('ui.livewire.notifications.unreadcount.poziciia_gotova_d55866f3'),
            default => __('ui.livewire.notifications.unreadcount.uvedomlenie'),
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
