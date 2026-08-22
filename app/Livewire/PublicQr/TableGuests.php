<?php

declare(strict_types=1);

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Enums\SupportedLocale;
use App\Enums\TableSessionGuestStatus;
use App\Models\TableSessionGuest;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Isolate]
class TableGuests extends Component
{
    #[Locked]
    public int $tableSessionId = 0;

    #[Locked]
    public int $currentGuestId = 0;

    public int $pollingIntervalSeconds = 1;

    public string $language = 'ru';

    /**
     * @var list<array{id: int, guest_name: string, status: string, status_key: string, status_tone: string, is_ready: bool, ready_key: string, is_current: bool}>
     */
    public array $guests = [];

    public function mount(int $tableSessionId, int $currentGuestId, int $pollingIntervalSeconds = 1, string $language = 'ru'): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);
        $this->language = SupportedLocale::normalize($language, 'ru');
        $this->applyLocale();

        $this->refreshGuests();
    }

    public function refreshGuests(): void
    {
        $this->applyLocale();

        $this->guests = TableSessionGuest::query()
            ->select([
                'id',
                'table_session_id',
                'guest_name',
                'status',
                'ready_at',
                'joined_at',
            ])
            ->where('table_session_id', $this->tableSessionId)
            ->orderBy('guest_name')
            ->orderBy('id')
            ->limit(50)
            ->get()
            ->map(fn (TableSessionGuest $guest): array => [
                'id' => $guest->id,
                'guest_name' => $guest->guest_name,
                'status' => $guest->status->value,
                'status_key' => $this->statusKey($guest->status),
                'status_tone' => $this->statusTone($guest->status),
                'is_ready' => $guest->ready_at !== null,
                'ready_key' => $guest->ready_at === null ? 'guest.table.not_ready' : 'guest.table.ready',
                'is_current' => $guest->id === $this->currentGuestId,
            ])
            ->all();
    }

    public function render(): View
    {
        $this->applyLocale();

        return view('livewire.public-qr.table-guests');
    }

    private function applyLocale(): void
    {
        App::setLocale($this->language);
    }

    private function statusKey(TableSessionGuestStatus $status): string
    {
        return match ($status) {
            TableSessionGuestStatus::PendingApproval => 'guest.statuses.pending_approval',
            TableSessionGuestStatus::Active => 'guest.statuses.active',
            TableSessionGuestStatus::Rejected => 'guest.statuses.rejected',
            TableSessionGuestStatus::Left => 'guest.statuses.left',
            TableSessionGuestStatus::Removed => 'guest.statuses.removed',
        };
    }

    private function statusTone(TableSessionGuestStatus $status): string
    {
        return match ($status) {
            TableSessionGuestStatus::Active => 'success',
            TableSessionGuestStatus::PendingApproval => 'warning',
            TableSessionGuestStatus::Left => 'muted',
            TableSessionGuestStatus::Rejected, TableSessionGuestStatus::Removed => 'danger',
        };
    }
}
