<?php

namespace App\Actions\Payments;

use App\Actions\TableSessions\CloseTableSessionAction;
use App\Models\TableSession;
use App\Models\User;

class ClosePaidTableSessionAction
{
    public function __construct(
        private readonly CloseTableSessionAction $closeTableSession,
    ) {}

    public function handle(TableSession $tableSession, User $closedBy): TableSession
    {
        return $this->closeTableSession->handle($tableSession, $closedBy);
    }
}
