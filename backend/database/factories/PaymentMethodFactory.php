<?php

namespace Database\Factories;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentMethod>
 */
class PaymentMethodFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'bank_transfer_'.fake()->unique()->numberBetween(1, 99999),
            'type' => PaymentMethodType::Manual,
            'driver' => null,
            'name' => ['en' => 'Bank transfer', 'it' => 'Bonifico bancario'],
            'instructions' => ['en' => 'Write the booking reference in the payment description.', 'it' => 'Indica il codice della prenotazione nella causale.'],
            'account_details' => [
                ['label' => 'Account name', 'value' => 'Sharm Station Viaggi'],
                ['label' => 'IBAN', 'value' => 'IT60X0542811101000000123456'],
            ],
            'currencies' => null,
            'proof_required' => true,
            'surcharge_percent' => 0,
            'deadline_hours' => 48,
            'is_enabled' => true,
            'sort' => 0,
        ];
    }

    public function online(string $driver = 'fake'): static
    {
        return $this->state(fn (): array => [
            'code' => 'card_'.fake()->unique()->numberBetween(1, 99999),
            'type' => PaymentMethodType::Online,
            'driver' => $driver,
            'name' => ['en' => 'Credit or debit card', 'it' => 'Carta di credito o debito'],
            'instructions' => null,
            'account_details' => null,
            'currencies' => ['EUR', 'USD', 'GBP'],
            'proof_required' => false,
            'deadline_hours' => null,
        ]);
    }
}
