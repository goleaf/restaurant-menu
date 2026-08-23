<?php

declare(strict_types=1);

namespace App\Services\Users;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Laravel\Passkeys\Passkey;

final class PasskeyQueryService
{
    /** @return EloquentCollection<int, Passkey> */
    public function forUser(User $user): EloquentCollection
    {
        return $user->passkeys()
            ->select(['id', 'name', 'credential', 'created_at', 'last_used_at'])
            ->latest()
            ->get();
    }

    public function findForUser(User $user, int $passkeyId): Passkey
    {
        return $user->passkeys()->findOrFail($passkeyId);
    }
}
