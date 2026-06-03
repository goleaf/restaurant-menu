<?php

namespace App\Actions\Waiter;

use App\Enums\DraftOrderStatus;
use App\Enums\SystemPermission;
use App\Enums\TableSessionStatus;
use App\Models\DraftOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class EnsureWaiterCanEditDraftOrderAction
{
    public function __construct(
        private readonly ResolveWaiterAccessibleBranchIdsAction $resolveAccessibleBranchIds,
    ) {}

    public function handle(DraftOrder $draftOrder, User $user): void
    {
        $tableSession = $draftOrder->tableSession;

        if ($tableSession === null || $tableSession->branch === null) {
            throw ValidationException::withMessages([
                'draft_edit' => __('Черновик больше не связан с открытым столом.'),
            ]);
        }

        $editableBranchIds = $this->resolveAccessibleBranchIds
            ->handle($user, SystemPermission::ConfirmOrders)
            ->merge($this->resolveAccessibleBranchIds->handle($user, SystemPermission::EditPendingOrders))
            ->unique()
            ->values();

        if (! $editableBranchIds->contains((int) $tableSession->branch_id)) {
            throw ValidationException::withMessages([
                'draft_edit' => __('У вас нет права редактировать черновик заказа в этом филиале.'),
            ]);
        }

        if (in_array($tableSession->status, [TableSessionStatus::Closed, TableSessionStatus::Cancelled], true)) {
            throw ValidationException::withMessages([
                'draft_edit' => __('Нельзя редактировать заказ для закрытого стола.'),
            ]);
        }

        if (! in_array($draftOrder->status, [DraftOrderStatus::SentToWaiter, DraftOrderStatus::WaiterReview], true)) {
            throw ValidationException::withMessages([
                'draft_edit' => __('Редактировать можно только черновик, отправленный официанту.'),
            ]);
        }
    }
}
