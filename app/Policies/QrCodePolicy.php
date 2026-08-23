<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Branch;
use App\Models\QrCode;
use App\Models\ServicePoint;
use App\Models\User;

final class QrCodePolicy
{
    public function __construct(
        private readonly BranchPolicy $branches,
    ) {}

    public function viewAny(User $user, Branch $branch): bool
    {
        return $this->branches->generateQr($user, $branch);
    }

    public function view(User $user, QrCode $qrCode): bool
    {
        return $this->authorize($user, $qrCode);
    }

    public function create(User $user, ServicePoint $servicePoint): bool
    {
        $branch = $servicePoint->branch;

        return $branch instanceof Branch && $this->branches->generateQr($user, $branch);
    }

    public function print(User $user, QrCode $qrCode): bool
    {
        return $this->authorize($user, $qrCode);
    }

    public function generate(User $user, QrCode $qrCode): bool
    {
        return $this->authorize($user, $qrCode);
    }

    public function manage(User $user, QrCode $qrCode): bool
    {
        return $this->authorize($user, $qrCode);
    }

    public function update(User $user, QrCode $qrCode): bool
    {
        return $this->manage($user, $qrCode);
    }

    public function delete(User $user, QrCode $qrCode): bool
    {
        return $this->manage($user, $qrCode);
    }

    private function authorize(User $user, QrCode $qrCode): bool
    {
        $servicePoint = $qrCode->servicePoint;
        $branch = $servicePoint instanceof ServicePoint ? $servicePoint->branch : null;

        return $branch instanceof Branch && $this->branches->generateQr($user, $branch);
    }
}
