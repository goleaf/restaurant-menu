<?php

declare(strict_types=1);

namespace App\Livewire\Waiter\TableDetail;

use App\Actions\DraftOrders\Support\BuildDraftOrderItemModifierSnapshots;
use App\Actions\Waiter\BuildWaiterTableDetailAction;
use App\Models\TableSession;
use App\Models\User;
use App\Services\Waiter\TableDetailChangeDetector;
use App\Services\Waiter\WaiterTableQueryService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

abstract class TableDetailSection extends Component
{
    #[Locked]
    public int $tableSessionId;

    private BuildWaiterTableDetailAction $buildWaiterTableDetail;

    protected TableDetailChangeDetector $changeDetector;

    protected WaiterTableQueryService $waiterQueries;

    public function boot(
        BuildWaiterTableDetailAction $buildWaiterTableDetail,
        TableDetailChangeDetector $changeDetector,
        WaiterTableQueryService $waiterQueries,
        BuildDraftOrderItemModifierSnapshots $buildModifierSnapshots,
    ): void {
        $this->buildWaiterTableDetail = $buildWaiterTableDetail;
        $this->changeDetector = $changeDetector;
        $this->waiterQueries = $waiterQueries;
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
        return $this->waiterQueries->tableSession($this->tableSessionId);
    }

    protected function authorizeViewableTableSession(): TableSession
    {
        $tableSession = $this->currentTableSession();

        Gate::forUser($this->currentUser())->authorize('view', $tableSession);

        return $tableSession;
    }

    protected function authorizeWaiterTableSession(): TableSession
    {
        $tableSession = $this->currentTableSession();

        Gate::forUser($this->currentUser())->authorize('viewOrders', $tableSession);

        return $tableSession;
    }

    protected function authorizePaymentTableSession(): TableSession
    {
        $tableSession = $this->currentTableSession();

        Gate::forUser($this->currentUser())->authorize('viewPayments', $tableSession);

        return $tableSession;
    }

    /**
     * @return array<string, mixed>
     */
    protected function freshViewableTablePayload(): array
    {
        return $this->buildTablePayload($this->authorizeViewableTableSession());
    }

    /** @return array<string, mixed> */
    protected function freshWaiterTablePayload(): array
    {
        return $this->buildTablePayload($this->authorizeWaiterTableSession());
    }

    /** @return array<string, mixed> */
    private function buildTablePayload(TableSession $tableSession): array
    {
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
