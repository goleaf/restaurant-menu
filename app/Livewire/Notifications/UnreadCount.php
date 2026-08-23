<?php

namespace App\Livewire\Notifications;

use App\Actions\Notifications\MarkUserNotificationsReadAction;
use App\Models\User;
use App\Services\Notifications\UserNotificationQueryService;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class UnreadCount extends Component
{
    private UserNotificationQueryService $notificationQueries;

    public int $unreadCount = 0;

    public bool $compact = false;

    /**
     * @var list<array{id: string, title: string, body: string, meta: string, tone: string, created_label: string}>
     */
    public array $notifications = [];

    public function boot(UserNotificationQueryService $notificationQueries): void
    {
        $this->notificationQueries = $notificationQueries;
    }

    public function mount(bool $compact = false): void
    {
        $this->compact = $compact;
        $this->refreshUnreadCount();
    }

    public function refreshUnreadCount(): void
    {
        $user = $this->currentUser();

        $this->unreadCount = $this->notificationQueries->unreadCount($user);

        if ($this->compact) {
            $this->notifications = [];

            return;
        }

        $this->notifications = $this->notificationQueries->recentUnread($user)
            ->map(fn (DatabaseNotification $notification): array => $this->presentNotification($notification))
            ->all();
    }

    public function markNotificationRead(string $notificationId, MarkUserNotificationsReadAction $markRead): void
    {
        $markRead->one($this->currentUser(), $notificationId);

        $this->refreshUnreadCount();
    }

    public function markAllRead(MarkUserNotificationsReadAction $markRead): void
    {
        $markRead->all($this->currentUser());

        $this->refreshUnreadCount();
    }

    public function render(): View
    {
        return view('livewire.notifications.unread-count');
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    /**
     * @return array{id: string, title: string, body: string, meta: string, tone: string, created_label: string}
     */
    private function presentNotification(DatabaseNotification $notification): array
    {
        $data = $notification->data;
        $itemName = (string) data_get($data, 'item_name', '');
        $guestName = (string) data_get($data, 'guest_name', '');
        $itemsCount = (int) data_get($data, 'items_count', 0);

        return [
            'id' => $notification->id,
            'title' => $this->titleForType($notification->type),
            'body' => match ($notification->type) {
                'draft_order_sent_to_waiter' => $guestName !== ''
                    ? __('ui.livewire.notifications.unreadcount.otpravil_zakaz_oficiantu', ['name' => $guestName])
                    : (string) data_get($data, 'message', __('ui.livewire.notifications.unreadcount.novyi_zakaz_otpravlen_oficiantu')),
                'waiter_called' => $guestName !== ''
                    ? __('ui.livewire.notifications.unreadcount.zovet_oficianta', ['name' => $guestName])
                    : (string) data_get($data, 'message', __('ui.livewire.notifications.unreadcount.gost_zovet_oficianta')),
                'bill_requested' => $guestName !== ''
                    ? __('ui.livewire.notifications.unreadcount.poprosil_scet', ['name' => $guestName])
                    : (string) data_get($data, 'message', __('ui.livewire.notifications.unreadcount.gost_poprosil_scet')),
                'kitchen_item_ready' => $itemName !== ''
                    ? __('ui.livewire.notifications.unreadcount.gotovo', ['item' => $itemName])
                    : (string) data_get($data, 'message', __('ui.livewire.notifications.unreadcount.poziciia_gotova')),
                default => (string) data_get($data, 'message', __('ui.livewire.notifications.unreadcount.novoe_uvedomlenie')),
            },
            'meta' => $this->staffMetaForData($data, $itemsCount),
            'tone' => $this->toneForType($notification->type),
            'created_label' => $notification->created_at?->diffForHumans() ?? '',
        ];
    }

    private function titleForType(string $type): string
    {
        return match ($type) {
            'draft_order_sent_to_waiter' => __('ui.livewire.notifications.unreadcount.novyi_zakaz'),
            'waiter_called' => __('ui.livewire.notifications.unreadcount.vyzov_oficianta'),
            'bill_requested' => __('ui.livewire.notifications.unreadcount.prosba_sceta'),
            'kitchen_item_ready' => __('ui.livewire.notifications.unreadcount.poziciia_gotova_d55866f3'),
            default => __('ui.livewire.notifications.unreadcount.uvedomlenie'),
        };
    }

    private function toneForType(string $type): string
    {
        return match ($type) {
            'draft_order_sent_to_waiter' => 'sky',
            'waiter_called' => 'orange',
            'bill_requested' => 'amber',
            'kitchen_item_ready' => 'emerald',
            default => 'zinc',
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function staffMetaForData(array $data, int $itemsCount): string
    {
        $parts = array_filter([
            data_get($data, 'branch_name'),
            data_get($data, 'service_point_name'),
            data_get($data, 'area_name'),
            $itemsCount > 0 ? __('ui.livewire.notifications.unreadcount.poz', ['count' => $itemsCount]) : null,
        ], fn (mixed $value): bool => is_string($value) && $value !== '');

        return implode(' · ', $parts);
    }
}
