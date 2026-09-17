<?php

declare(strict_types=1);

namespace App\Support\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class PasswordResetContext
{
    private const string KEY = 'auth.livewire_password_reset';

    public function store(Request $request, string $token, string $email): void
    {
        abort_unless($token !== '' && strlen($token) <= 512 && strlen($email) <= 255 && filter_var($email, FILTER_VALIDATE_EMAIL), 404);
        $broker = (string) config('fortify.passwords');
        $minutes = (int) config('auth.passwords.'.$broker.'.expire', 60);
        $request->session()->put(self::KEY, [
            'identifier' => Str::random(40),
            'token' => $token,
            'email' => $email,
            'expires' => now()->addMinutes($minutes)->timestamp,
        ]);
    }

    public function email(Request $request): string
    {
        return $this->context($request)['email'] ?? '';
    }

    public function token(Request $request): ?string
    {
        return $this->context($request)['token'] ?? null;
    }

    public function identifier(Request $request): ?string
    {
        return $this->context($request)['identifier'] ?? null;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::KEY);
    }

    /** @return array{identifier: string, email: string, token: string, expires: int}|null */
    private function context(Request $request): ?array
    {
        $context = $request->session()->get(self::KEY);
        if (! is_array($context) || ! is_string($context['identifier'] ?? null) || ! is_string($context['email'] ?? null)
            || ! is_string($context['token'] ?? null) || ! is_int($context['expires'] ?? null)
            || $context['expires'] <= now()->timestamp) {
            $this->clear($request);

            return null;
        }

        return $context;
    }
}
