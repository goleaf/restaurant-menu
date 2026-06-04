<?php

namespace App\Livewire\PublicQr;

use App\Actions\Branches\GetBranchPollingIntervalAction;
use App\Enums\TableSessionGuestStatus;
use App\Models\TableSessionGuest;
use Illuminate\View\View;
use Livewire\Attributes\Isolate;
use Livewire\Component;

#[Isolate]
class TableGuests extends Component
{
    public int $tableSessionId = 0;

    public int $currentGuestId = 0;

    public int $pollingIntervalSeconds = 1;

    /**
     * @var list<array{id: int, guest_name: string, status: string, status_label: string, status_tone: string, is_ready: bool, ready_label: string, is_current: bool}>
     */
    public array $guests = [];

    public function mount(int $tableSessionId, int $currentGuestId, int $pollingIntervalSeconds = 1): void
    {
        $this->tableSessionId = $tableSessionId;
        $this->currentGuestId = $currentGuestId;
        $this->pollingIntervalSeconds = GetBranchPollingIntervalAction::normalize($pollingIntervalSeconds);

        $this->refreshGuests();
    }

    public function refreshGuests(): void
    {
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
                'status_label' => $this->statusLabel($guest->status),
                'status_tone' => $this->statusTone($guest->status),
                'is_ready' => $guest->ready_at !== null,
                'ready_label' => $guest->ready_at === null ? __('Не готов') : __('Готов'),
                'is_current' => $guest->id === $this->currentGuestId,
            ])
            ->all();
    }

    public function render(): View
    {
        return view('livewire.public-qr.table-guests');
    }

    private function statusLabel(TableSessionGuestStatus $status): string
    {
        return match ($status) {
            TableSessionGuestStatus::PendingApproval => __('Ожидает'),
            TableSessionGuestStatus::Active => __('За столом'),
            TableSessionGuestStatus::Rejected => __('Не подтверждён'),
            TableSessionGuestStatus::Left => __('Ушёл'),
            TableSessionGuestStatus::Removed => __('Удалён'),
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
