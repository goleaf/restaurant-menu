<?php

declare(strict_types=1);

namespace App\Actions\Backups;

use App\Models\User;
use Illuminate\Cache\CacheManager;
use Illuminate\Http\Request;

final class ResolveMediaBackupAuthorizationAction
{
    public function __construct(
        private readonly CacheManager $cache,
    ) {}

    /**
     * @return array{reason: string, user: User}
     */
    public function handle(Request $request): array
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isSuperadmin()) {
            abort(403);
        }

        $authorization = $request->session()->pull('media_backup_download_authorization');

        if (
            ! is_array($authorization)
            || (int) ($authorization['user_id'] ?? 0) !== $user->id
            || (int) ($authorization['issued_at'] ?? 0) < now()->subMinutes(5)->timestamp
            || (int) ($authorization['issued_at'] ?? 0) > now()->addMinute()->timestamp
            || preg_match('/^[A-Za-z0-9]{64}$/', (string) ($authorization['nonce'] ?? '')) !== 1
            || trim((string) ($authorization['reason'] ?? '')) === ''
        ) {
            abort(403);
        }

        $nonceKey = 'media-backup-authorization:'.hash('sha256', (string) $authorization['nonce']);

        if (! $this->cache->store('file')->add($nonceKey, true, now()->addMinutes(10))) {
            abort(409);
        }

        return [
            'reason' => trim((string) $authorization['reason']),
            'user' => $user,
        ];
    }
}
