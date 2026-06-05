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
        return [
            'table_session_id' => TableSession::factory()->active(),
            'branch_id' => fn (array $attributes): int => $this->tableSessionFor($attributes)->branch_id,
            'service_point_id' => fn (array $attributes): int => $this->tableSessionFor($attributes)->service_point_id,
            'table_session_guest_id' => null,
            'recorded_by_user_id' => User::factory(),
            'scope' => ManualPaymentScope::Table,
            'payment_method' => ManualPaymentMethod::Cash,
            'covered_subtotal_amount' => '10.00',
            'service_charge_percent' => '0.00',
            'service_charge_amount' => '0.00',
            'tips_amount' => '0.00',
            'amount' => '10.00',
            'currency' => 'EUR',
            'guest_name' => null,
            'note' => null,
            'paid_at' => now(),
            'metadata' => [],
        ];
    }

    public function cash(): static
    {
        return $this->state(fn (): array => [
            'payment_method' => ManualPaymentMethod::Cash,
        ]);
    }

    public function cardTerminal(): static
    {
        return $this->state(fn (): array => [
            'payment_method' => ManualPaymentMethod::CardTerminal,
        ]);
    }

    public function other(): static
    {
        return $this->state(fn (): array => [
            'payment_method' => ManualPaymentMethod::Other,
        ]);
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

    public function forTableSession(TableSession $tableSession): static
    {
        return $this->state(fn (): array => [
            'branch_id' => $tableSession->branch_id,
            'service_point_id' => $tableSession->service_point_id,
            'table_session_id' => $tableSession->id,
            'table_session_guest_id' => null,
            'scope' => ManualPaymentScope::Table,
            'guest_name' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function tableSessionFor(array $attributes): TableSession
    {
        return TableSession::query()
            ->select(['id', 'branch_id', 'service_point_id'])
            ->whereKey($attributes['table_session_id'])
            ->firstOrFail();
    }
}
