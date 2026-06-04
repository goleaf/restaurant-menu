<?php

namespace Database\Factories;

use App\Enums\ManualPaymentMethod;
use App\Enums\ManualPaymentScope;
use App\Models\ManualPayment;
use App\Models\TableSession;
use App\Models\TableSessionGuest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ManualPayment>
 */
class ManualPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $tableSession = TableSession::factory()->active()->create();

        return [
            'branch_id' => $tableSession->branch_id,
            'service_point_id' => $tableSession->service_point_id,
            'table_session_id' => $tableSession->id,
            'table_session_guest_id' => null,
            'recorded_by_user_id' => User::factory(),
            'scope' => ManualPaymentScope::Table,
            'payment_method' => ManualPaymentMethod::Cash,
            'amount' => '10.00',
            'currency' => 'EUR',
            'guest_name' => null,
            'note' => null,
            'paid_at' => now(),
            'metadata' => [],
        ];
    }

    public function forGuest(TableSessionGuest $guest): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $guest->tableSession->branch_id,
            'service_point_id' => $guest->tableSession->service_point_id,
            'table_session_id' => $guest->table_session_id,
            'table_session_guest_id' => $guest->id,
            'scope' => ManualPaymentScope::Guest,
            'guest_name' => $guest->guest_name,
        ]);
    }
}
