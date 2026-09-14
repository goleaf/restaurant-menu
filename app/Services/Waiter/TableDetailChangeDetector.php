<?php

declare(strict_types=1);

namespace App\Services\Waiter;

use Closure;
use Illuminate\Database\DatabaseManager;

final class TableDetailChangeDetector
{
    public function __construct(private readonly DatabaseManager $database) {}

    /**
     * Read authorization and presentation from one SQLite snapshot. A separate
     * later fingerprint could acknowledge a change absent from the rendered data.
     *
     * @param  Closure(): array<string, mixed>  $readPayload
     * @return array{payload: array<string, mixed>, fingerprint: string}
     */
    public function snapshot(Closure $readPayload): array
    {
        return $this->database->connection()->transaction(function () use ($readPayload): array {
            $payload = $readPayload();

            return ['payload' => $payload, 'fingerprint' => $this->fingerprint($payload)];
        });
    }

    /** @param array<string, mixed> $payload */
    public function fingerprint(array $payload): string
    {
        return hash('sha256', serialize($payload));
    }
}
