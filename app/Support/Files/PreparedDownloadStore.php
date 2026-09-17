<?php

declare(strict_types=1);

namespace App\Support\Files;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** @phpstan-type Download array{path: string, filename: string, kind: string, branch_id: ?int, user_id: int, session: string, issued_at: int, sha256: string} */
final class PreparedDownloadStore
{
    public function issue(User $user, string $source, string $filename, string $kind, ?int $branchId = null): string
    {
        if (! in_array($kind, ['csv', 'sqlite', 'media'], true) || is_link($source) || ! is_file($source)) {
            throw new RuntimeException('Invalid prepared download.');
        }

        $disk = Storage::disk('local');
        $root = realpath($disk->path(''));
        $sourcePath = realpath($source);
        if ($root === false || $sourcePath === false || ! str_starts_with($sourcePath, $root.DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Prepared downloads must originate on the private local disk.');
        }

        $disk->makeDirectory('prepared-downloads');
        $this->cleanupExpired();
        $grant = Str::random(64);
        $path = $this->path($grant);
        if (! rename($source, $path)) {
            throw new RuntimeException('The prepared download could not be stored.');
        }
        chmod($path, 0600);
        $sha256 = hash_file('sha256', $path);
        if (! is_string($sha256)) {
            unlink($path);
            throw new RuntimeException('The prepared download could not be verified.');
        }

        $downloads = session()->get('prepared_downloads', []);
        while (count($downloads) >= 8) {
            $oldGrant = array_key_first($downloads);
            if (is_string($oldGrant) && preg_match('/^[A-Za-z0-9]{64}$/D', $oldGrant) === 1) {
                $disk->delete('prepared-downloads/'.$oldGrant);
            }
            unset($downloads[$oldGrant]);
        }
        $downloads[$grant] = ['filename' => $filename, 'kind' => $kind, 'branch_id' => $branchId, 'user_id' => $user->id, 'session' => hash('sha256', session()->getId()), 'issued_at' => now()->timestamp, 'sha256' => $sha256];
        session()->put('prepared_downloads', $downloads);

        return $grant;
    }

    /** @return Download */
    public function peek(Request $request, string $grant): array
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{64}$/D', $grant) === 1, 404);
        $data = $request->session()->get('prepared_downloads.'.$grant);
        $user = $request->user();
        abort_unless($user instanceof User && is_array($data) && ($data['user_id'] ?? null) === $user->id, 403);
        abort_unless(hash_equals((string) ($data['session'] ?? ''), hash('sha256', $request->session()->getId())), 403);
        abort_unless(is_int($data['issued_at'] ?? null) && $data['issued_at'] >= now()->subMinutes(5)->timestamp && $data['issued_at'] <= now()->timestamp, 403);
        abort_if(Cache::store('file')->has('prepared-download:'.hash('sha256', $grant)), 409);
        $path = $this->path($grant);
        abort_unless(! is_link($path) && is_file($path), 404);

        return ['path' => $path, ...$data];
    }

    /** @return Download */
    public function consume(Request $request, string $grant): array
    {
        $data = $this->peek($request, $grant);
        $hash = hash_file('sha256', $data['path']);
        abort_unless(is_string($hash) && hash_equals($data['sha256'], $hash), 409);
        abort_unless(Cache::store('file')->add('prepared-download:'.hash('sha256', $grant), true, now()->addMinutes(10)), 409);
        $request->session()->forget('prepared_downloads.'.$grant);

        return $data;
    }

    private function path(string $grant): string
    {
        return Storage::disk('local')->path('prepared-downloads/'.$grant);
    }

    private function cleanupExpired(): void
    {
        $checked = 0;
        foreach (new \DirectoryIterator(Storage::disk('local')->path('prepared-downloads')) as $file) {
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
