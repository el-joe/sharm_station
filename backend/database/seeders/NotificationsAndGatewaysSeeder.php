<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use App\Services\Notifications\NotificationService;
use Illuminate\Database\Seeder;

/**
 * v2.4 phase 1 (idempotent, safe to run again): default email templates (EN + IT) for every event and the
 * Stripe and PayPal payment methods. Gateways are enabled but only offered once their env keys are set.
 */
class NotificationsAndGatewaysSeeder extends Seeder
{
    public function run(NotificationService $notifications): void
    {
        $created = $notifications->seedDefaults();
        $this->command?->info("Notification templates created: {$created}");

        PaymentMethod::query()->firstOrCreate(['code' => 'stripe_card'], [
            'type' => PaymentMethodType::Online,
            'driver' => 'stripe',
            'name' => ['en' => 'Credit or debit card (Stripe)', 'it' => 'Carta di credito o debito (Stripe)'],
            'instructions' => ['en' => 'You are redirected to the secure Stripe payment page.', 'it' => 'Verrai reindirizzato alla pagina di pagamento sicura di Stripe.'],
            'currencies' => ['EUR', 'USD', 'GBP', 'EGP'],
            'proof_required' => false,
            'mode' => 'test',
            'is_enabled' => true,
            'sort' => 0,
        ]);

        PaymentMethod::query()->firstOrCreate(['code' => 'paypal'], [
            'type' => PaymentMethodType::Online,
            'driver' => 'paypal',
            'name' => ['en' => 'PayPal', 'it' => 'PayPal'],
            'instructions' => ['en' => 'You are redirected to PayPal to approve the payment.', 'it' => 'Verrai reindirizzato a PayPal per approvare il pagamento.'],
            'currencies' => ['EUR', 'USD', 'GBP'],
            'proof_required' => false,
            'mode' => 'test',
            'is_enabled' => true,
            'sort' => 1,
        ]);
    }
}
