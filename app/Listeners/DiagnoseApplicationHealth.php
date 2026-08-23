<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Actions\Health\RunProductionHealthChecksAction;
use Illuminate\Foundation\Events\DiagnosingHealth;

final class DiagnoseApplicationHealth
{
    public function __construct(
        private readonly RunProductionHealthChecksAction $runHealthChecks,
    ) {}

    /**
     * Handle the event.
     */
    public function handle(DiagnosingHealth $event): void
    {
        $this->runHealthChecks->handle();
    }
}
