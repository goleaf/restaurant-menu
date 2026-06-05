<?php

namespace Database\Factories;

use App\Enums\QrCodeStatus;
use App\Models\QrCode;
use App\Models\ServicePoint;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QrCode>
 */
class QrCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'service_point_id' => ServicePoint::factory(),
            'public_token' => Str::random(64),
            'short_code' => 'QR-'.Str::upper(Str::random(8)),
            'status' => QrCodeStatus::Active,
            'created_by_user_id' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (): array => [
            'status' => QrCodeStatus::Active,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
        ]);
    }

    public function disabled(): static
    {
        return $this->state(fn (): array => [
            'status' => QrCodeStatus::Disabled,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => QrCodeStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }
}
