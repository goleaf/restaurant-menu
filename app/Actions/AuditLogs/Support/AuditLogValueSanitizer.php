<?php

namespace App\Actions\AuditLogs\Support;

use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class AuditLogValueSanitizer
{
    /**
     * @var list<string>
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'token',
        'secret',
        'password',
        'recovery',
        'remember',
        'credential',
        'api_key',
        'private_key',
        'two_factor',
        'env',
        'backup_path',
        'database_path',
    ];

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    public function forStorage(array $values): ?array
    {
        if ($values === []) {
            return null;
        }

        return collect($values)
            ->map(fn (mixed $value, string|int $key): mixed => $this->normalizeValueForKey($key, $value))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function summary(array $values): string
    {
        if ($values === []) {
            return '—';
        }

        return collect($values)
            ->map(fn (mixed $value, string|int $key): string => $key.': '.$this->displayValueForKey((string) $key, $value))
            ->implode('; ');
    }

    private function normalizeValueForKey(string|int $key, mixed $value): mixed
    {
        if (is_string($key) && $this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        return $this->normalizeValue($value);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof CarbonInterface) {
            return $value->toISOString();
        }

        if ($value instanceof Model) {
            return $value->getKey();
        }

        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $nestedValue, string|int $key): mixed => $this->normalizeValueForKey($key, $nestedValue))
                ->all();
        }

        return $value;
    }

    private function displayValueForKey(string $key, mixed $value): string
    {
        if ($this->isSensitiveKey($key)) {
            return '[redacted]';
        }

        return $this->displayValue($value);
    }

    private function displayValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }

        if ($value === null) {
            return 'empty';
        }

        if (is_array($value)) {
            return json_encode($this->maskedArray($value), JSON_UNESCAPED_UNICODE) ?: '[]';
        }

        return (string) $value;
    }

    /**
     * @param  array<string|int, mixed>  $values
     * @return array<string|int, mixed>
     */
    private function maskedArray(array $values): array
    {
        return collect($values)
            ->map(function (mixed $value, string|int $key): mixed {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    return '[redacted]';
                }

                return is_array($value) ? $this->maskedArray($value) : $value;
            })
            ->all();
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalizedKey = str($key)
            ->lower()
            ->replace(['-', ' '], '_')
            ->toString();

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
