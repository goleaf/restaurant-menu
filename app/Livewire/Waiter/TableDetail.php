<?php

namespace App\Livewire\Waiter;

use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Actions\Waiter\ConfirmDraftOrderByWaiterAction;
use App\Actions\Waiter\RejectDraftOrderByWaiterAction;
use App\Actions\Waiter\ReturnRejectedDraftOrderToDraftAction;
use App\Models\DraftOrder;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Table detail')]
class TableDetail extends Component
{
    public int $tableSessionId;

    /**
     * @var array<string, mixed>
     */
    public array $table = [];

    public string $refreshedAt = '';

    public string $rejectionReason = '';

    public string $reviewFeedbackMessage = '';

    public function mount(TableSession $tableSession): void
    {
        $this->tableSessionId = $tableSession->id;
        $this->refreshTable();
    }

    public function refreshTable(): void
    {
        $tableSession = TableSession::query()
            ->select(['id', 'branch_id'])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();

        $payload = app(BuildWaiterTableDetailAction::class)->handle($this->currentUser(), $tableSession);

        if (! $payload['has_access']) {
            abort(403);
        }

        $this->table = $payload['table'] ?? [];
        $this->refreshedAt = now()->format('H:i:s');
    }

    public function confirmDraft(ConfirmDraftOrderByWaiterAction $confirmDraftOrder): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('У этого стола нет черновика для подтверждения.'));

            return;
        }

        try {
            $order = $confirmDraftOrder->handle($draftOrder, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->rejectionReason = '';
        $this->reviewFeedbackMessage = __('Заказ подтверждён официантом. Кухня и бар пока не получают его автоматически.');
        $this->refreshTable();
        $this->table['draft']['order_id'] = $order->id;
    }

    public function rejectDraft(RejectDraftOrderByWaiterAction $rejectDraftOrder): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('У этого стола нет черновика для отклонения.'));

            return;
        }

        try {
            $rejectDraftOrder->handle($draftOrder, $this->currentUser(), $this->rejectionReason);
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->reviewFeedbackMessage = __('Черновик отклонён. Гости увидят причину.');
        $this->refreshTable();
    }

    public function returnRejectedDraftToDraft(ReturnRejectedDraftOrderToDraftAction $returnDraft): void
    {
        $this->resetValidation();
        $this->reviewFeedbackMessage = '';

        $draftOrder = $this->currentDraftOrder();

        if (! $draftOrder instanceof DraftOrder) {
            $this->addError('draft_review', __('У этого стола нет черновика для возврата.'));

            return;
        }

        try {
            $returnDraft->handle($draftOrder, $this->currentUser());
        } catch (ValidationException $exception) {
            $this->showValidationException($exception);

            return;
        }

        $this->rejectionReason = '';
        $this->reviewFeedbackMessage = __('Черновик возвращён гостям для правок.');
        $this->refreshTable();
    }

    public function render(): View
    {
        return view('livewire.waiter.table-detail');
    }

    private function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    private function currentDraftOrder(): ?DraftOrder
    {
        $tableSession = TableSession::query()
            ->select(['id'])
            ->with(['draftOrder' => fn ($query) => $query->select(['id', 'table_session_id'])])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();

        return $tableSession->draftOrder;
    }

    private function showValidationException(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($field, $messages[0] ?? __('Ошибка проверки.'));
        }
    }
}
