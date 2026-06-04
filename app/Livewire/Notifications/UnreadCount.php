<?php

namespace App\Livewire\Notifications;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class UnreadCount extends Component
{
    public int $unreadCount = 0;

    public bool $compact = false;

    public function mount(bool $compact = false): void
    {
        $this->compact = $compact;
        $this->refreshUnreadCount();
    }

    public function refreshUnreadCount(): void
    {
        $this->unreadCount = $this->currentUser()
            ->unreadNotifications()
            ->count();
    }

    public function markAllRead(): void
    {
        $this->currentUser()
            ->unreadNotifications()
            ->update(['read_at' => now()]);

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
}
