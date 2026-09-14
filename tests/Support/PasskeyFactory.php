<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Laravel\Passkeys\Passkey;

/** @extends Factory<Passkey> */
final class PasskeyFactory extends Factory
{
    protected $model = Passkey::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Test passkey',
            'credential_id' => fake()->uuid(),
            'credential' => [],
        ];
    }
}
