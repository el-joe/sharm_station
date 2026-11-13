<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProofStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('local');
    seedCurrencies();

    $this->booking = Booking::factory()->create(['reference' => 'BK-3001', 'access_token' => 'secret-token', 'total' => 100, 'deposit_amount' => 30, 'balance_amount' => 70]);
    $this->bank = PaymentMethod::factory()->create(['code' => 'bank_transfer', 'sort' => 1]);
    $this->instapay = PaymentMethod::factory()->create(['code' => 'instapay', 'name' => ['en' => 'InstaPay'], 'currencies' => ['EGP'], 'sort' => 2]);
    $this->card = PaymentMethod::factory()->online()->create(['code' => 'card', 'sort' => 0]);
});

function proofUrl(string $suffix = ''): string
{
    return '/api/v1/bookings/BK-3001/proofs'.$suffix;
}

test('payment methods are filtered by the booking currency and show the amount due now', function () {
    $this->getJson('/api/v1/bookings/BK-3001/payment-methods?token=secret-token')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.code', 'card')
        ->assertJsonPath('data.0.group', 'instant')
        ->assertJsonPath('data.1.code', 'bank_transfer')
        ->assertJsonPath('data.1.account_details.1.label', 'IBAN')
        ->assertJsonPath('data.1.amount_due.amount_eur', 30)
        ->assertJsonPath('meta.currency', 'EUR')
        ->assertJsonMissingPath('data.0.credentials');

    $this->booking->update(['currency' => 'EGP', 'exchange_rate' => 51]);

    $this->getJson('/api/v1/bookings/BK-3001/payment-methods?token=secret-token')
        ->assertOk()
        ->assertJsonPath('data.*.code', ['bank_transfer', 'instapay'])
        ->assertJsonPath('data.1.amount_due.amount', 1530);
});

test('payment methods need the booking token or the owner', function () {
    $this->getJson('/api/v1/bookings/BK-3001/payment-methods')->assertNotFound();
    $this->getJson('/api/v1/bookings/BK-3001/payment-methods?token=wrong')->assertNotFound();

    $owner = User::factory()->create();
    $this->booking->update(['user_id' => $owner->id]);
    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/bookings/BK-3001/payment-methods')->assertOk();
});

test('a proof upload is stored on the private disk and puts the booking in review', function () {
    $this->post(proofUrl(), [
        'token' => 'secret-token',
        'payment_method_id' => $this->bank->id,
        'files' => [UploadedFile::fake()->image('receipt.jpg'), UploadedFile::fake()->create('statement.pdf', 200, 'application/pdf')],
        'transaction_number' => 'TRX-998',
        'transaction_date' => now()->toDateString(),
        'amount' => 30,
        'sender_name' => 'Giulia Rossi',
    ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.purpose', 'deposit')
        ->assertJsonCount(2, 'data.files')
        ->assertJsonMissingPath('data.files.0.path')
        ->assertJsonPath('meta.payment_status', 'awaiting_receipt_review');

    $proof = PaymentProof::query()->sole();
    $booking = $this->booking->fresh();

    Storage::disk('local')->assertExists($proof->files[0]['path']);
    expect($proof->files[0]['mime'])->toBe('image/jpeg')
        ->and($proof->files[0]['path'])->toStartWith("payment-proofs/{$booking->id}/")
        ->and($booking->payment_status)->toBe(PaymentStatus::AwaitingReceiptReview)
        ->and($booking->status)->toBe(BookingStatus::AwaitingReceiptReview);

    $this->getJson('/api/v1/bookings/BK-3001?token=secret-token')
        ->assertOk()
        ->assertJsonPath('data.payment_status', 'awaiting_receipt_review')
        ->assertJsonPath('data.proofs.0.status', 'pending')
        ->assertJsonPath('data.proofs.0.files_count', 2)
        ->assertJsonPath('data.payment_summary.paid', 0);
});

test('proof uploads reject wrong types, large files, too many files and a second pending proof', function () {
    $body = fn (array $files): array => ['token' => 'secret-token', 'payment_method_id' => $this->bank->id, 'files' => $files];

    $this->post(proofUrl(), $body([UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload')]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['files.0']);
    $this->post(proofUrl(), $body([UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['files.0']);
    $this->post(proofUrl(), $body(array_fill(0, 4, UploadedFile::fake()->image('r.png'))), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['files']);
    $this->post(proofUrl(), ['token' => 'secret-token', 'payment_method_id' => $this->card->id, 'files' => [UploadedFile::fake()->image('r.png')]], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['payment_method_id']);

    $this->post(proofUrl(), $body([UploadedFile::fake()->image('r.png')]), ['Accept' => 'application/json'])->assertCreated();
    $this->post(proofUrl(), $body([UploadedFile::fake()->image('r2.png')]), ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors(['files']);

    $this->post(proofUrl(), ['payment_method_id' => $this->bank->id, 'files' => [UploadedFile::fake()->image('r.png')]], ['Accept' => 'application/json'])
        ->assertNotFound();
});

test('an online checkout returns a session url and a signed webhook confirms the deposit', function () {
    $response = $this->postJson('/api/v1/bookings/BK-3001/checkout', ['token' => 'secret-token', 'payment_method_id' => $this->card->id])
        ->assertCreated();

    $reference = $response->json('data.session_reference');
    expect($response->json('data.checkout_url'))->toContain('BK-3001')->toContain($reference);

    $payment = Payment::query()->sole();
    expect($payment->status)->toBe(PaymentRecordStatus::Pending)->and((float) $payment->amount)->toBe(30.0);

    $payload = json_encode(['type' => 'payment.succeeded', 'reference' => $reference, 'transaction_id' => 'tx_1']);

    $this->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => 'bad'], $payload)
        ->assertStatus(400);

    $signature = (new FakeGateway(config('payments.gateways.fake.webhook_secret')))->sign($payload);
    $this->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => $signature], $payload)
        ->assertOk();
    // Repeated delivery is ignored.
    $this->call('POST', '/api/v1/payments/webhooks/fake', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_FAKE_SIGNATURE' => $signature], $payload)
        ->assertOk();

    $booking = $this->booking->fresh();
    expect($payment->fresh()->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($payment->fresh()->transaction_id)->toBe('tx_1')
        ->and($booking->payment_status)->toBe(PaymentStatus::DepositPaid)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->paidAmount())->toBe(30.0)
        ->and($booking->dueAmount())->toBe(70.0);
});

test('the stripe skeleton refuses to start without keys and rejects unsigned webhooks', function () {
    $stripe = PaymentMethod::factory()->online('stripe')->create(['code' => 'stripe']);

    $this->postJson('/api/v1/bookings/BK-3001/checkout', ['token' => 'secret-token', 'payment_method_id' => $stripe->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['payment_method_id']);

    $this->postJson('/api/v1/payments/webhooks/stripe', ['type' => 'checkout.session.completed'])->assertStatus(400);
});

test('the stripe webhook signature is verified with the t and v1 scheme', function () {
    config(['payments.gateways.stripe.webhook_secret' => 'whsec_test']);
    $payload = json_encode(['type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]]);
    $timestamp = now()->getTimestamp();
    $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test');

    $this->call('POST', '/api/v1/payments/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature], $payload)
        ->assertOk();
    $this->call('POST', '/api/v1/payments/webhooks/stripe', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1=deadbeef'], $payload)
        ->assertStatus(400);
});

test('bookings return the payment options from the payment methods table', function () {
    $this->getJson('/api/v1/bookings/BK-3001?token=secret-token')
        ->assertOk()
        ->assertJsonPath('data.payment_options.*.code', ['card', 'bank_transfer']);

    expect(ProofStatus::cases())->toHaveCount(3);
});
