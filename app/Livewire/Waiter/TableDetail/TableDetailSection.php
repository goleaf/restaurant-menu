<?php

declare(strict_types=1);

namespace App\Livewire\Waiter\TableDetail;

use App\Actions\Payments\ResolvePaymentAccessibleBranchIdsAction;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Actions\Waiter\ResolveWaiterAccessibleBranchIdsAction;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

abstract class TableDetailSection extends Component
{
    #[Locked]
    public int $tableSessionId;

    private BuildWaiterTableDetailAction $buildWaiterTableDetail;

    private ResolveWaiterAccessibleBranchIdsAction $resolveWaiterAccessibleBranchIds;

    private ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccessibleBranchIds;

    public function boot(
        BuildWaiterTableDetailAction $buildWaiterTableDetail,
        ResolveWaiterAccessibleBranchIdsAction $resolveWaiterAccessibleBranchIds,
        ResolvePaymentAccessibleBranchIdsAction $resolvePaymentAccessibleBranchIds,
    ): void {
        $this->buildWaiterTableDetail = $buildWaiterTableDetail;
        $this->resolveWaiterAccessibleBranchIds = $resolveWaiterAccessibleBranchIds;
        $this->resolvePaymentAccessibleBranchIds = $resolvePaymentAccessibleBranchIds;
    }

    protected function currentUser(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    protected function currentTableSession(): TableSession
    {
        return TableSession::query()
            ->select(['id', 'branch_id'])
            ->whereKey($this->tableSessionId)
            ->firstOrFail();
    }

    protected function authorizeCurrentTableSession(): TableSession
    {
        $tableSession = $this->currentTableSession();
        $user = $this->currentUser();
        $branchId = (int) $tableSession->branch_id;
        $hasWaiterAccess = $this->resolveWaiterAccessibleBranchIds
            ->handle($user)
            ->contains($branchId);
        $hasPaymentAccess = $this->resolvePaymentAccessibleBranchIds
            ->viewableBranchIds($user)
            ->contains($branchId);

        if (! $hasWaiterAccess && ! $hasPaymentAccess) {
            abort(403);
        }

        return $tableSession;
    }

    /**
     * @return array<string, mixed>
     */
    protected function freshTablePayload(): array
    {
        $tableSession = $this->authorizeCurrentTableSession();
        $payload = $this->buildWaiterTableDetail->handle($this->currentUser(), $tableSession);

        if (! $payload['has_access'] || ! is_array($payload['table'])) {
            abort(403);
        }

        return $payload['table'];
    }

    protected function showValidationException(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            foreach ($messages as $message) {
                $this->addError($field, $message);
            }
        }
    }
}
