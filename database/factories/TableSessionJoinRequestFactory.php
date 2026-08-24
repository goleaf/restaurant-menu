<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SupportedLocale;
use App\Enums\TableSessionJoinRequestStatus;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\TableSessionJoinRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TableSessionJoinRequest>
 */
class TableSessionJoinRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'table_session_id' => TableSession::factory()->active(),
            'guest_name' => fake()->firstName(),
            'guest_token' => Str::random(64),
            'locale' => SupportedLocale::English->value,
            'status' => TableSessionJoinRequestStatus::Pending,
            'approved_by_guest_id' => null,
            'rejected_by_guest_id' => null,
            'approved_by_user_id' => null,
            'rejected_by_user_id' => null,
            'expires_at' => now()->addMinutes(30),
        ];
    }

    public function forTableSession(TableSession $tableSession): static
    {
        return $this->state(fn (): array => [
            'table_session_id' => $tableSession->id,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionJoinRequestStatus::Pending,
            'approved_by_guest_id' => null,
            'rejected_by_guest_id' => null,
            'approved_by_user_id' => null,
            'rejected_by_user_id' => null,
            'expires_at' => now()->addMinutes(30),
        ]);
    }

    public function approvedByGuest(TableSessionGuest $guest): static
    {
        return $this
            ->forTableSession($guest->tableSession)
            ->state(fn (): array => [
                'status' => TableSessionJoinRequestStatus::Approved,
                'approved_by_guest_id' => $guest->id,
                'rejected_by_guest_id' => null,
                'approved_by_user_id' => null,
                'rejected_by_user_id' => null,
            ]);
    }

    public function approvedByUser(User $user): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionJoinRequestStatus::Approved,
            'approved_by_guest_id' => null,
            'rejected_by_guest_id' => null,
            'approved_by_user_id' => $user->id,
            'rejected_by_user_id' => null,
        ]);
    }

    public function rejectedByGuest(TableSessionGuest $guest): static
    {
        return $this
            ->forTableSession($guest->tableSession)
            ->state(fn (): array => [
                'status' => TableSessionJoinRequestStatus::Rejected,
                'approved_by_guest_id' => null,
                'rejected_by_guest_id' => $guest->id,
                'approved_by_user_id' => null,
                'rejected_by_user_id' => null,
            ]);
    }

    public function rejectedByUser(User $user): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionJoinRequestStatus::Rejected,
            'approved_by_guest_id' => null,
            'rejected_by_guest_id' => null,
            'approved_by_user_id' => null,
            'rejected_by_user_id' => $user->id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'status' => TableSessionJoinRequestStatus::Expired,
            'approved_by_guest_id' => null,
            'rejected_by_guest_id' => null,
            'approved_by_user_id' => null,
            'rejected_by_user_id' => null,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
