<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Closure;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Symfony\Component\HttpFoundation\Response;

final class TwoFactorChallengeContext
{
    private const string KEY = 'auth.livewire_two_factor_challenge';

    private const int LIFETIME = 600;

    public function start(Request $request): void
    {
        $userId = $request->session()->get('login.id');
        $fingerprint = is_int($userId) || is_string($userId) ? $this->credentialFingerprint($userId) : null;
        if ($fingerprint === null) {
            $this->clear($request);

            return;
        }
        $request->session()->put(self::KEY, [
            'identifier' => Str::random(40),
            'user' => $userId,
            'credential_fingerprint' => $fingerprint,
            'expires' => now()->timestamp + self::LIFETIME,
        ]);
    }

    public function identifier(Request $request): ?string
    {
        $value = $request->session()->get(self::KEY.'.identifier');

        return is_string($value) ? $value : null;
    }

    public function valid(Request $request): bool
    {
        $context = $request->session()->get(self::KEY);

        return is_array($context) && is_string($context['identifier'] ?? null)
            && is_int($context['expires'] ?? null) && $context['expires'] > now()->timestamp
            && (is_int($context['user'] ?? null) || is_string($context['user'] ?? null))
            && $request->session()->get('login.id') === $context['user']
            && ! Cache::store('file')->has($this->consumedKey($context['identifier']))
            && is_string($context['credential_fingerprint'] ?? null)
            && hash_equals($context['credential_fingerprint'], $this->credentialFingerprint($context['user']) ?? '');
    }

    public function enforce(Request $request): void
    {
        if (! $this->valid($request)) {
            $this->clear($request);
            throw new HttpResponseException(new RedirectResponse(route('login')));
        }
    }

    /** @param Closure(): Response $operation */
    public function run(Request $request, Closure $operation): Response
    {
        if (! $this->valid($request)) {
            $this->clear($request);

            return new RedirectResponse(route('login'));
        }
        $identifier = $this->identifier($request) ?? '';
        $lockKey = 'auth-mfa-user:'.hash('sha256', (string) $request->session()->get('login.id'));
        $lockProvider = Cache::store('file')->getStore();
        abort_unless($lockProvider instanceof LockProvider, 503);
        $result = $lockProvider->lock($lockKey, 30)->get(function () use ($request, $operation, $identifier): Response {
            if (! $this->valid($request)) {
                $this->clear($request);

                return new RedirectResponse(route('login'));
            }
            $response = $operation();
            if (app(StatefulGuard::class)->check()) {
                Cache::store('file')->put($this->consumedKey($identifier), true, self::LIFETIME);
                $this->clear($request);
            }

            return $response;
        });
        if ($result === false) {
            throw ValidationException::withMessages([$request->filled('recovery_code') ? 'recovery_code' : 'code' => __('auth.throttle', ['seconds' => 1, 'minutes' => 1])]);
        }

        return $result;
    }

    public function consume(Request $request): void
    {
        $identifier = $this->identifier($request);
        if ($identifier !== null) {
            Cache::store('file')->put($this->consumedKey($identifier), true, self::LIFETIME);
        }
    }

    public function clear(Request $request): void
    {
        $request->session()->forget([self::KEY, 'login.id', 'login.remember']);
    }

    private function consumedKey(string $identifier): string
    {
        return 'auth-mfa-consumed:'.hash('sha256', $identifier);
    }

    private function credentialFingerprint(int|string $userId): ?string
    {
        $user = app(StatefulGuard::class)->getProvider()->retrieveById($userId);
        if ($user === null || ! in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true)) {
            return null;
        }
        $secret = data_get($user, 'two_factor_secret');
        if (! is_string($secret) || $secret === ''
            || (Fortify::confirmsTwoFactorAuthentication() && data_get($user, 'two_factor_confirmed_at') === null)) {
            return null;
        }

        return hash('sha256', $user::class.'|'.$user->getAuthIdentifier().'|'.$secret);
    }
}
