<?php

namespace App\Livewire\Notifications;

use App\Actions\Notifications\MarkUserNotificationsReadAction;
use App\Models\User;
use App\Services\Notifications\UserNotificationQueryService;
use App\Support\LocalizedDateFormatter;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class UnreadCount extends Component
{
    private UserNotificationQueryService $notificationQueries;

    public int $unreadCount = 0;

    public bool $panelOpen = false;

    public bool $destinationUnavailable = false;

    #[Locked]
    public string $audience = '';

    #[Locked]
    public bool $detailsRendered = false;

    private bool $hydrated = false;

    private bool $snapshotLoaded = false;

    /** @var list<array{id: string, title: string, body: string, meta: string, created_label: string, created_at: string, unread: bool, can_open: bool}> */
    private array $notifications = [];

    public function boot(UserNotificationQueryService $notificationQueries): void
    {
        $this->notificationQueries = $notificationQueries;
    }

    public function mount(): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);
        $this->audience = $this->audienceFor($user);
        $this->refreshUnreadCount();
    }

    public function hydrate(): void
    {
        $this->hydrated = true;
    }

    public function refreshUnreadCount(): void
    {
        $this->snapshotLoaded = true;
        $this->notifications = [];
        $user = $this->currentUser();

        if ($user === null) {
            return;
        }

        $snapshot = $this->notificationQueries->snapshot($user, $this->panelOpen);
        $this->unreadCount = $snapshot['count'];
        $this->notifications = $snapshot['notifications']
            ->map(fn (DatabaseNotification $notification): array => $this->presentNotification($notification, isset($snapshot['destinations'][$notification->id])))
            ->all();

        if ($this->hydrated && ! $this->panelOpen && ! $this->detailsRendered) {
            $this->skipRender();
        }
    }

    public function openPanel(): void
    {
        if ($this->currentUser() === null) {
            return;
        }

        $this->panelOpen = true;
        $this->destinationUnavailable = false;
        $this->refreshUnreadCount();
    }

    public function markNotificationRead(mixed $notificationId, MarkUserNotificationsReadAction $markRead): void
    {
        abort_unless(is_string($notificationId) && Str::isUuid($notificationId), 422);

        if (($user = $this->currentUser()) !== null) {
            $markRead->one($user, $notificationId);
        }

        $this->refreshUnreadCount();
    }

    public function markAllRead(MarkUserNotificationsReadAction $markRead): void
    {
        if (($user = $this->currentUser()) !== null) {
            $markRead->all($user);
        }

        $this->refreshUnreadCount();
    }

    public function openNotification(mixed $notificationId): void
    {
        abort_unless(is_string($notificationId) && Str::isUuid($notificationId), 422);

        $user = $this->currentUser();
        $destination = $user === null ? null : $this->notificationQueries->destination($user, $notificationId);

        if ($destination === null) {
            $this->destinationUnavailable = true;
            $this->refreshUnreadCount();

            return;
        }

        $this->redirect($destination, navigate: true);
    }

    public function render(): View
    {
        if (! $this->snapshotLoaded) {
            $this->refreshUnreadCount();
        }

        $this->detailsRendered = $this->panelOpen;

        return view('livewire.notifications.unread-count', ['notifications' => $this->notifications]);
    }

    private function currentUser(): ?User
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 401);

        if (! hash_equals($this->audience, $this->audienceFor($user))) {
            $this->unreadCount = 0;
            $this->panelOpen = false;
            $this->notifications = [];

            return null;
        }

        return $user;
    }

    private function audienceFor(User $user): string
    {
        return hash_hmac('sha256', (string) $user->getAuthIdentifier(), (string) config('app.key'));
    }

    /**
     * @return array{id: string, title: string, body: string, meta: string, created_label: string, created_at: string, unread: bool, can_open: bool}
     */
    private function presentNotification(DatabaseNotification $notification, bool $canOpen): array
    {
        $data = $notification->data;
        $itemName = (string) data_get($data, 'item_name', '');
        $guestName = (string) data_get($data, 'guest_name', data_get($data, 'sent_by_guest_name', ''));
        $itemsCount = (int) data_get($data, 'items_count', 0);

        return [
            'id' => $notification->id,
            'title' => $this->titleForType($notification->type),
            'body' => match ($notification->type) {
                'draft_order_sent_to_waiter' => $guestName !== ''
                    ? __('ui.livewire.notifications.unreadcount.otpravil_zakaz_oficiantu', ['name' => $guestName])
                    : __('ui.livewire.notifications.unreadcount.novyi_zakaz_otpravlen_oficiantu'),
                'waiter_called' => $guestName !== ''
                    ? __('ui.livewire.notifications.unreadcount.zovet_oficianta', ['name' => $guestName])
                    : __('ui.livewire.notifications.unreadcount.gost_zovet_oficianta'),
                'bill_requested' => $guestName !== ''
                    ? __('ui.livewire.notifications.unreadcount.poprosil_scet', ['name' => $guestName])
                    : __('ui.livewire.notifications.unreadcount.gost_poprosil_scet'),
                'kitchen_item_ready' => $itemName !== ''
                    ? __('ui.livewire.notifications.unreadcount.gotovo', ['item' => $itemName])
                    : __('ui.livewire.notifications.unreadcount.poziciia_gotova'),
                default => __('ui.livewire.notifications.unreadcount.novoe_uvedomlenie'),
            },
            'meta' => $this->staffMetaForData($data, $itemsCount),
            'created_label' => LocalizedDateFormatter::relative($notification->created_at) ?? '',
            'created_at' => $notification->created_at?->toISOString() ?? '',
            'unread' => $notification->read_at === null,
            'can_open' => $canOpen,
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
