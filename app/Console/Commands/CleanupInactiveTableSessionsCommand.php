<?php

namespace App\Console\Commands;

use App\Actions\TableSessions\CleanupInactiveTableSessionsAction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('table-sessions:cleanup-inactive {--branch= : Limit cleanup to one branch id}')]
#[Description('Cancel stale pending table sessions and report inactive active sessions.')]
class CleanupInactiveTableSessionsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(CleanupInactiveTableSessionsAction $cleanupInactiveTableSessions): int
    {
        $branchOption = $this->option('branch');
        $branchId = $branchOption === null || $branchOption === ''
            ? null
            : filter_var($branchOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($branchId === false) {
            $this->components->error('The --branch option must be a positive branch id.');

            return self::FAILURE;
        }

        $result = $cleanupInactiveTableSessions->handle($branchId);

        $this->components->info(sprintf(
            'Checked %d sessions. Cancelled %d pending sessions. Active warnings: %d. Skipped with unpaid orders: %d.',
            $result['checked'],
            $result['pending_cancelled'],
            $result['active_warnings'],
            $result['skipped_unpaid_orders'],
        ));

        return self::SUCCESS;
    }
}
