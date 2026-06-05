<?php

namespace App\Actions\Branches;

use App\Models\Branch;
use App\Support\PlainText;
use Illuminate\Support\Carbon;

class UpdateBranchTemporaryClosureAction
{
    public function handle(
        Branch $branch,
        bool $isTemporarilyClosed,
        ?string $reason = null,
        ?string $closedUntil = null,
    ): Branch {
        if (! $isTemporarilyClosed) {
            $branch->forceFill([
                'is_temporarily_closed' => false,
                'temporary_closed_reason' => null,
                'temporary_closed_until' => null,
            ])->save();

            return $branch->refresh();
        }

        $branch->forceFill([
            'is_temporarily_closed' => true,
            'temporary_closed_reason' => $this->nullableString($reason),
            'temporary_closed_until' => $this->closedUntilForStorage($branch, $closedUntil),
        ])->save();

        return $branch->refresh();
    }

    private function closedUntilForStorage(Branch $branch, ?string $closedUntil): ?string
    {
        $closedUntil = str((string) $closedUntil)->squish()->toString();

        if ($closedUntil === '') {
            return null;
        }

        return Carbon::parse($closedUntil, $this->timezoneFor($branch))
            ->setTimezone('UTC')
            ->format('Y-m-d H:i:s');
    }

    private function nullableString(?string $value): ?string
    {
        return PlainText::optional($value, 255, squish: true);
    }

    private function timezoneFor(Branch $branch): string
    {
        $timezone = $branch->getAttribute('timezone');

        return is_string($timezone) && $timezone !== '' ? $timezone : config('app.timezone', 'UTC');
    }
}
