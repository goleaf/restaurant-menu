<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\SqliteRestoreRequestLock;
use Closure;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class CoordinateSqliteRestore
{
    public function __construct(private readonly SqliteRestoreRequestLock $lock, private readonly MaintenanceMode $maintenance) {}

    /** @param Closure(Request): Response $next */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            try {
                $this->lock->beginRequest($request->isMethod('POST') && $request->is('superadmin/backups/sqlite/restore'));
            } catch (\RuntimeException) {
                abort(503);
            }

            // Recheck after acquiring the lock: this request may have waited for a restore.
            abort_if($this->lock->requiresRecovery() || $this->maintenance->active(), 503);

            return $next($request);
        } finally {
            $this->lock->endRequest();
        }
    }
}
