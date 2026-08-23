<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Models\User;
use Illuminate\Cache\CacheManager;
use Illuminate\Http\Request;

final class ResolveSqliteRestoreAuthorizationAction
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    /**
     * @return array{issued_at: int, reason: string, user_id: int}
     */
    public function handle(Request $request, bool $consume = false): array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSuperadmin()) {
            abort(403);
        }

        $authorization = $consume
            ? $request->session()->pull('sqlite_backup_restore_authorization')
            : $request->session()->get('sqlite_backup_restore_authorization');

        if (! is_array($authorization)
            || (int) ($authorization['user_id'] ?? 0) !== $user->id
            || (int) ($authorization['issued_at'] ?? 0) < now()->subMinutes(5)->timestamp
            || (int) ($authorization['issued_at'] ?? 0) > now()->addMinute()->timestamp
            || preg_match('/^[A-Za-z0-9]{64}$/', (string) ($authorization['nonce'] ?? '')) !== 1
            || trim((string) ($authorization['reason'] ?? '')) === '') {
            abort(403);
        }

        if ($consume) {
            $nonceKey = 'sqlite-restore-authorization:'.hash('sha256', (string) $authorization['nonce']);

            if (! $this->cache->store('file')->add($nonceKey, true, now()->addMinutes(10))) {
                abort(409);
            }
        }

        return [
            'issued_at' => (int) $authorization['issued_at'],
            'reason' => trim((string) $authorization['reason']),
            'user_id' => (int) $authorization['user_id'],
        ];
    }
}
