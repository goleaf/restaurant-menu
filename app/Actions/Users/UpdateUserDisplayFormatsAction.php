<?php

declare(strict_types=1);

namespace App\Actions\Users;

use App\Models\User;
use App\Support\DisplayPreferences;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateUserDisplayFormatsAction
{
    public function handle(User $actor, DisplayPreferences $preferences): void
    {
        $attributes = array_map(fn (string $value): ?string => $value === 'locale' ? null : $value, $preferences->values());

        DB::transaction(function () use ($actor, $attributes): void {
            $user = User::query()->select(['id', 'date_format', 'time_format', 'number_format'])->findOrFail($actor->id);
            $user->fill($attributes);

            if (! $user->saveOrFail()) {
                throw new RuntimeException('The display preferences could not be saved.');
            }
        });

        $actor->fill($attributes)->syncOriginalAttributes(array_keys($attributes));
    }
}
