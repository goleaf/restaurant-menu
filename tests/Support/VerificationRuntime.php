<?php

declare(strict_types=1);

namespace Tests\Support;

use RuntimeException;

final class VerificationRuntime
{
    /** @return array{version: string, binary: string, sapi: string} */
    public static function identity(): array
    {
        return ['version' => PHP_VERSION, 'binary' => PHP_BINARY, 'sapi' => PHP_SAPI];
    }

    /** @return array{version: string, binary: string} */
    public static function coordinatorExpectation(): array
    {
        $version = getenv('RESTAURANT_EXPECTED_PHP_VERSION');
        $binary = getenv('RESTAURANT_EXPECTED_PHP_BINARY');

        return [
            'version' => $version === false ? PHP_VERSION : $version,
            'binary' => $binary === false ? PHP_BINARY : $binary,
        ];
    }

    public static function assertMatches(mixed $identity, string $expectedVersion, string $expectedBinary): void
    {
        if (trim($expectedVersion) === '' || trim($expectedBinary) === '') {
            throw new RuntimeException('An explicit expected PHP version and binary are required.');
        }

        foreach (['version', 'binary', 'sapi'] as $field) {
            if (! is_array($identity) || ! is_string($identity[$field] ?? null) || trim($identity[$field]) === '') {
                throw new RuntimeException('HTTP runtime identity is incomplete.');
            }
        }

        if ($identity['version'] !== $expectedVersion) {
            throw new RuntimeException('HTTP PHP version does not match the selected runtime.');
        }

        if ((realpath($identity['binary']) ?: $identity['binary']) !== (realpath($expectedBinary) ?: $expectedBinary)) {
            throw new RuntimeException('HTTP PHP binary does not match the selected runtime.');
        }
    }
}
