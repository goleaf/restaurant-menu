<?php

declare(strict_types=1);

namespace App\Support\Backups;

use App\Services\Backups\SqliteRestoreAuthorization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** @phpstan-type Candidate array{path: string, grant: string, sha256: string, schema_fingerprint: string, nonce: string, session: string, user_id: int, issued_at: int, reason: string, bytes: int} */
final readonly class PreparedRestoreStore
{
    public function __construct(private SqliteRestoreAuthorization $authorization) {}

    /** @param array{path: string, schema_fingerprint: string} $candidate
     * @return Candidate
     */
    public function issue(Request $request, array $candidate): array
    {
        $authorization = $this->authorization->handle($request);
        $this->forget($request);
        $this->cleanupExpired();
        if (! $this->validPath($candidate['path'])) {
            throw new RuntimeException('Invalid restore candidate path.');
        }
        $sha256 = hash_file('sha256', $candidate['path']);
        $bytes = filesize($candidate['path']);
        if (! is_string($sha256) || ! is_int($bytes)) {
            throw new RuntimeException('The restore candidate could not be verified.');
        }
        $data = [...$candidate, ...$authorization, 'grant' => Str::random(64), 'session' => hash('sha256', $request->session()->getId()), 'sha256' => $sha256, 'bytes' => $bytes];
        $request->session()->put('sqlite_restore_candidate', $data);

        return $data;
    }

    /** @return Candidate */
    public function consume(Request $request, string $grant): array
    {
        $authorization = $this->authorization->handle($request);
        $candidate = $request->session()->get('sqlite_restore_candidate');
        abort_unless(is_array($candidate) && preg_match('/^[A-Za-z0-9]{64}$/D', $grant) === 1, 403);
        abort_unless(hash_equals($candidate['grant'], $grant) && hash_equals($candidate['nonce'], $authorization['nonce']) && $candidate['user_id'] === $authorization['user_id'], 403);
        abort_unless(hash_equals($candidate['session'], hash('sha256', $request->session()->getId())) && $this->validPath($candidate['path']), 403);
        $hash = hash_file('sha256', $candidate['path']);
        abort_unless(is_string($hash) && hash_equals($candidate['sha256'], $hash), 409);
        $this->authorization->handle($request, consume: true);
        $request->session()->forget('sqlite_restore_candidate');

        return $candidate;
    }

    public function forget(Request $request): void
    {
        $candidate = $request->session()->pull('sqlite_restore_candidate');
        if (is_array($candidate) && is_string($candidate['path'] ?? null) && $this->validPath($candidate['path'])) {
            unlink($candidate['path']);
        }
    }

    private function validPath(string $path): bool
    {
        $root = realpath(Storage::disk('local')->path('backups/sqlite/restore-candidates'));

        return is_string($root) && ! is_link($path) && is_file($path) && dirname((string) realpath($path)) === $root;
    }

    private function cleanupExpired(): void
    {
        $checked = 0;
        foreach (new \DirectoryIterator(Storage::disk('local')->path('backups/sqlite/restore-candidates')) as $file) {
            if ($file->isDot()) {
                continue;
            }
            if (++$checked > 100) {
                break;
            }
            if (! $file->isLink() && $file->isFile() && $file->getMTime() < now()->subMinutes(10)->timestamp) {
                unlink($file->getPathname());
            }
        }
    }
}
