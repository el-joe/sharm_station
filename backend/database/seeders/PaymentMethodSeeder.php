<?php

namespace Database\Seeders;

use App\Enums\PaymentMethodType;
use App\Models\PaymentMethod;
use Illuminate\Database\Seeder;

/**
 * One fake online gateway (no real keys), a bank transfer and InstaPay (EGP) with proof upload (SRS 10).
 */
class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        PaymentMethod::query()->create([
            'code' => 'card',
            'type' => PaymentMethodType::Online,
            'driver' => 'fake',
            'name' => ['en' => 'Credit or debit card (test gateway)', 'it' => 'Carta di credito o debito (gateway di prova)'],
            'instructions' => ['en' => 'You are redirected to the secure payment page.', 'it' => 'Verrai reindirizzato alla pagina di pagamento sicura.'],
            'currencies' => ['EUR', 'USD', 'GBP'],
            'proof_required' => false,
            'mode' => 'test',
            'sort' => 0,
        ]);

        PaymentMethod::query()->create([
            'code' => 'bank_transfer',
            'type' => PaymentMethodType::Manual,
            'name' => ['en' => 'Bank transfer', 'it' => 'Bonifico bancario'],
            'instructions' => ['en' => 'Write the booking reference in the payment description, then upload the receipt.', 'it' => 'Indica il codice della prenotazione nella causale, poi carica la ricevuta.'],
            'account_details' => [
                ['label' => 'Account name', 'value' => 'Sharm Station Viaggi S.r.l.'],
                ['label' => 'IBAN', 'value' => 'IT60 X054 2811 1010 0000 0123 456'],
                ['label' => 'BIC / SWIFT', 'value' => 'BPMOIT22XXX'],
                ['label' => 'Bank', 'value' => 'Demo Bank, Milan'],
            ],
            'currencies' => null,
            'proof_required' => true,
            'deadline_hours' => 48,
            'sort' => 1,
        ]);

        PaymentMethod::query()->create([
            'code' => 'instapay',
            'type' => PaymentMethodType::Manual,
            'name' => ['en' => 'InstaPay', 'it' => 'InstaPay'],
            'instructions' => ['en' => 'Send the amount to our InstaPay address and upload the screenshot.', 'it' => 'Invia l\'importo al nostro indirizzo InstaPay e carica lo screenshot.'],
            'account_details' => [
                ['label' => 'InstaPay address', 'value' => 'sharmstation@instapay'],
                ['label' => 'Name', 'value' => 'Sharm Station Viaggi'],
            ],
            'currencies' => ['EGP'],
            'proof_required' => true,
            'deadline_hours' => 24,
            'sort' => 2,
        ]);
    }
}
