<?php

declare(strict_types=1);

namespace App\Actions\TableSessions;

use App\Models\TableSession;
use Carbon\CarbonInterface;

final readonly class CreatedGuestInviteLink
{
    public function __construct(
        public TableSession $tableSession,
        public string $token,
        public CarbonInterface $expiresAt,
    ) {}
}
