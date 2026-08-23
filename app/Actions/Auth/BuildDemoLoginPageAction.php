<?php

declare(strict_types=1);

namespace App\Actions\Auth;

use App\Models\User;
use App\Support\DemoLogin\DemoAccountCatalog;

final class BuildDemoLoginPageAction
{
    /**
     * @return list<array{role: string, label: string, email: string, available: bool}>
     */
    public function handle(): array
    {
        $catalogue = DemoAccountCatalog::accounts();
        $usersByEmail = User::query()
            ->select(['id', 'email'])
            ->with('roles:id,code')
            ->whereIn('email', array_column($catalogue, 'email'))
            ->get()
            ->keyBy('email');

        return array_map(
            static function (array $account) use ($usersByEmail): array {
                $user = $usersByEmail->get($account['email']);

                return [
                    'role' => $account['role']->value,
                    'label' => $account['role']->localizedLabel(),
                    'email' => $account['email'],
                    'available' => $user instanceof User && $user->hasSystemRole($account['role']),
                ];
            },
            $catalogue,
        );
    }
}
