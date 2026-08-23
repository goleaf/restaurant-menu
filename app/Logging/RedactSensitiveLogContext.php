<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;

final class RedactSensitiveLogContext
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEYS = [
        'card_number',
        'cookie',
        'cookies',
        'cvv',
        'password',
        'private_key',
        'secret',
        'session_cookie',
        'session_id',
        'token',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            context: $this->redact($record->context),
            extra: $this->redact($record->extra),
        );
    }

    /**
     * @param  array<mixed>  $values
     * @return array<mixed>
     */
    private function redact(array $values): array
    {
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->isSensitiveKey($key)) {
                $values[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                $values[$key] = $this->redact($value);
            }
        }

        return $values;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = str_replace(['-', '.'], '_', strtolower($key));

        if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        return preg_match(
            '/(?:^|_)(authorization|cookies?|credentials?|cvv|password|secret|token)(?:_|$)/',
            $normalizedKey,
        ) === 1 || preg_match(
            '/(?:^|_)(api|encryption|private|signing)_key(?:_|$)/',
            $normalizedKey,
        ) === 1 || str_contains($normalizedKey, 'card_number');
    }
}
