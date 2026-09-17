<?php

declare(strict_types=1);

namespace App\Support\Files;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

final readonly class PreparedDownloadAttempt
{
    private const int LIFETIME_SECONDS = 7200;

    public function __construct(private PreparedDownloadStore $downloads) {}

    public static function fresh(): string
    {
        return now()->timestamp.'-'.Str::random(64);
    }

    /** @param array<string, int|string> $input
     * @param  Closure(): string  $prepare
     */
    public function handle(User $user, string $attempt, string $kind, array $input, Closure $prepare): string
    {
        abort_unless(preg_match('/^(\d{10})-[A-Za-z0-9]{64}$/D', $attempt, $parts) === 1, 409);
        abort_unless((int) $parts[1] <= now()->timestamp && (int) $parts[1] >= now()->timestamp - self::LIFETIME_SECONDS, 409);
        $key = 'prepared-attempt:'.hash('sha256', $user->id.'|'.session()->getId().'|'.$kind.'|'.$attempt);
        $fingerprint = hash('sha256', json_encode($input, JSON_THROW_ON_ERROR));
        $cache = Cache::store('file');
        $lockProvider = $cache->getStore();
        abort_unless($lockProvider instanceof LockProvider, 503);
        $grant = $lockProvider->lock($key.':lock', 30)->get(function () use ($cache, $key, $fingerprint, $prepare): string {
            $receipt = $cache->get($key);
            if (is_array($receipt)) {
                abort_unless(($receipt['state'] ?? null) === 'ready' && hash_equals($receipt['fingerprint'], $fingerprint), 409);
                session()->put('prepared_downloads.'.$receipt['grant'], $receipt['authorization']);
                $this->downloads->peek(request(), $receipt['grant']);

                return $receipt['grant'];
            }
            $expires = now()->addSeconds(self::LIFETIME_SECONDS + 60);
            $cache->put($key, ['state' => 'preparing', 'fingerprint' => $fingerprint], $expires);
            $grant = $prepare();
            $cache->put($key, ['state' => 'ready', 'fingerprint' => $fingerprint, 'grant' => $grant, 'authorization' => session()->get('prepared_downloads.'.$grant)], $expires);

            return $grant;
        });
        abort_unless(is_string($grant), 409);

        return $grant;
    }
}
